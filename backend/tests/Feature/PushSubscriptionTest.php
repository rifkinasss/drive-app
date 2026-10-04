<?php

namespace Tests\Feature;

use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Mockery;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private array $payload = [
        'endpoint' => 'https://updates.push.services.mozilla.com/wpush/v2/test-endpoint',
        'keys' => [
            'p256dh' => 'test-public-key',
            'auth' => 'test-auth-token',
        ],
    ];

    public function test_authenticated_user_can_upsert_their_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/push-subscriptions', $this->payload)
            ->assertOk()
            ->assertJsonPath('data.subscribed', true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/push-subscriptions', array_replace_recursive($this->payload, ['keys' => ['auth' => 'updated-auth-token']]))
            ->assertOk();

        $this->assertSame(1, PushSubscription::query()->where('user_id', $user->getKey())->count());
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->getKey(), 'endpoint' => $this->payload['endpoint']]);
    }

    public function test_subscription_requires_authentication_and_valid_payload(): void
    {
        $this->postJson('/api/push-subscriptions', $this->payload)->assertUnauthorized();

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/push-subscriptions', ['endpoint' => 'not-a-url'])
            ->assertUnprocessable();
    }

    public function test_user_can_remove_only_their_own_endpoint(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner, 'sanctum')->postJson('/api/push-subscriptions', $this->payload)->assertOk();

        $this->actingAs($other, 'sanctum')
            ->deleteJson('/api/push-subscriptions/current', ['endpoint' => $this->payload['endpoint']])
            ->assertOk()
            ->assertJsonPath('data.subscribed', false);

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $owner->getKey(), 'endpoint' => $this->payload['endpoint']]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson('/api/push-subscriptions/current', ['endpoint' => $this->payload['endpoint']])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $owner->getKey(), 'endpoint' => $this->payload['endpoint']]);
    }

    public function test_eligible_push_is_delivered_to_all_user_subscriptions_without_exposing_external_urls(): void
    {
        $user = User::factory()->create();
        $publicKey = rtrim(strtr(base64_encode(str_repeat('a', 65)), '+/', '-_'), '=');
        $authKey = rtrim(strtr(base64_encode(str_repeat('b', 16)), '+/', '-_'), '=');
        PushSubscription::query()->create(['user_id' => $user->getKey(), 'endpoint' => $this->payload['endpoint'], 'p256dh' => $publicKey, 'auth' => $authKey]);
        config()->set([
            'cloud_settings.push.vapid_subject' => 'mailto:test@example.test',
            'cloud_settings.push.vapid_public_key' => 'public-vapid-key',
            'cloud_settings.push.vapid_private_key' => 'private-vapid-key',
        ]);

        $report = Mockery::mock();
        $report->shouldReceive('isSubscriptionExpired')->once()->andReturnFalse();
        $report->shouldReceive('isSuccess')->once()->andReturnTrue();
        $webPush = Mockery::mock(WebPush::class);
        $webPush->shouldReceive('queueNotification')->once()->withArgs(fn ($subscription, string $payload): bool => json_decode($payload, true, flags: JSON_THROW_ON_ERROR)['url'] === '/home');
        $webPush->shouldReceive('flush')->once()->andReturn((function () use ($report): \Generator {
            yield $report;
        })());

        $subscription = Mockery::mock(Subscription::class);
        $service = Mockery::mock(PushNotificationService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods()->shouldReceive('makeWebPush')->once()->andReturn($webPush);
        $service->shouldAllowMockingProtectedMethods()->shouldReceive('makeSubscription')->once()->andReturn($subscription);
        $service->send($user, 'share.received', 'Shared item', 'A shared item is ready.', 'https://external.example.test/unsafe');

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->getKey(), 'endpoint' => $this->payload['endpoint']]);
    }

    public function test_disabled_preference_skips_push_delivery(): void
    {
        $user = User::factory()->create();
        NotificationPreference::query()->create(['user_id' => $user->getKey(), 'shares' => false]);
        $service = Mockery::mock(PushNotificationService::class)->makePartial();
        $service->shouldAllowMockingProtectedMethods()->shouldNotReceive('makeWebPush');

        $service->send($user, 'share.received', 'Shared item', 'A shared item is ready.', '/shared');

        $this->assertDatabaseCount('push_subscriptions', 0);
    }
}
