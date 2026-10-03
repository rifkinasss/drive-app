<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SecuritySessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecuritySessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_authenticated_user_can_list_only_their_sessions_with_device_metadata(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $currentId = $this->authenticate($user);
        $this->seedSession($user, $currentId, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/140.0.0.0 Safari/537.36');
        $this->seedSession($user, 'other-session', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Version/17.0 Mobile/15E148 Safari/604.1');
        $this->seedSession($otherUser, 'foreign-session', 'Unknown agent');

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/security/sessions')->assertOk();

        $response->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.browser', 'Chrome')
            ->assertJsonPath('data.items.0.os', 'Windows')
            ->assertJsonPath('data.items.0.deviceLabel', 'Chrome · Windows')
            ->assertJsonStructure(['success', 'data' => ['items' => [['id', 'deviceLabel', 'browser', 'os', 'ipAddress', 'lastActiveAt', 'createdAt', 'approximateStatus', 'isCurrent']]]]);
    }

    public function test_user_can_revoke_one_other_session_without_exposing_raw_id(): void
    {
        $user = User::factory()->create();
        $currentId = $this->authenticate($user);
        $otherId = 'other-session';
        $this->seedSession($user, $currentId);
        $this->seedSession($user, $otherId);

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/security/sessions/'.hash('sha256', $otherId))
            ->assertOk();

        $this->assertDatabaseHas('sessions', ['id' => $currentId, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherId, 'user_id' => $user->id]);
    }

    public function test_user_can_revoke_all_other_sessions(): void
    {
        $user = User::factory()->create();
        $currentId = $this->authenticate($user);
        $this->seedSession($user, $currentId);
        $this->seedSession($user, 'other-session-1');
        $this->seedSession($user, 'other-session-2');

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/security/sessions/others')
            ->assertOk()
            ->assertJsonPath('data.revokedCount', 3);

        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_revoke_others_preserves_the_current_session(): void
    {
        $user = User::factory()->create();
        $currentId = 'current-session';
        $this->seedSession($user, $currentId);
        $this->seedSession($user, 'other-session-1');
        $this->seedSession($user, 'other-session-2');

        $count = app(SecuritySessionService::class)->revokeOthers($user, $currentId);

        $this->assertSame(2, $count);
        $this->assertDatabaseHas('sessions', ['id' => $currentId, 'user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-1', 'user_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-2', 'user_id' => $user->id]);
    }

    public function test_current_session_cannot_be_revoked(): void
    {
        $user = User::factory()->create();
        $currentId = $this->authenticate($user);
        $this->seedSession($user, $currentId);

        $result = app(SecuritySessionService::class)->revoke($user, hash('sha256', $currentId), $currentId);

        $this->assertSame('current', $result);
        $this->assertDatabaseHas('sessions', ['id' => $currentId, 'user_id' => $user->id]);
    }

    public function test_unauthenticated_and_foreign_session_access_is_rejected(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignId = 'foreign-session';
        $this->seedSession($user, $foreignId);

        $this->getJson('/api/security/sessions')->assertUnauthorized();

        $this->actingAs($otherUser, 'sanctum')
            ->deleteJson('/api/security/sessions/'.hash('sha256', $foreignId))
            ->assertNotFound();

        $this->assertDatabaseHas('sessions', ['id' => $foreignId, 'user_id' => $user->id]);
    }

    private function authenticate(User $user): string
    {
        $this->actingAs($user, 'sanctum')->getJson('/api/security/sessions')->assertOk();
        $id = $this->app['session']->getId();
        $this->withUnencryptedCookie(config('session.cookie'), $id);
        return $id;
    }

    private function seedSession(User $user, string $id, string $userAgent = 'Mozilla/5.0 Chrome/140.0.0.0 Windows NT 10.0'): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '192.0.2.15',
            'user_agent' => $userAgent,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);
    }
}
