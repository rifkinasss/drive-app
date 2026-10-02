<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_sanctum_csrf_cookie_endpoint_is_available(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
    }

    public function test_user_can_login_and_read_current_user(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test']);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => false,
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Signed in successfully.')
            ->assertJsonPath('data.user.email', $user->email)
            ->assertJsonMissingPath('data.user.password');

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.user.id', $user->id)
            ->assertJsonPath('data.user.emailVerifiedAt', null);
    }

    public function test_remembered_login_queues_a_persistent_cookie_with_the_configured_duration(): void
    {
        $user = User::factory()->create(['email' => 'remembered@example.test']);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'remember' => true,
        ])->assertOk();

        $rememberCookie = collect($response->baseResponse->headers->getCookies())
            ->first(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_'));

        $this->assertNotNull($rememberCookie);
        $this->assertSame(43200 * 60, $rememberCookie->getMaxAge());
        $this->getJson('/api/auth/user')->assertOk();
    }

    public function test_invalid_login_is_generic_and_returns_unauthorized(): void
    {
        User::factory()->create(['email' => 'person@example.test']);

        $this->postJson('/api/auth/login', [
            'email' => 'person@example.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are invalid.');

        $this->postJson('/api/auth/login', [
            'email' => 'missing@example.test',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
            ->assertJsonPath('message', 'The provided credentials are invalid.');
    }

    public function test_current_user_requires_authentication(): void
    {
        $this->getJson('/api/auth/user')
            ->assertUnauthorized()
            ->assertJsonPath('success', false);
    }

    public function test_authenticated_user_can_update_only_their_display_name(): void
    {
        $user = User::factory()->create(['name' => 'Before']);

        $this->actingAs($user, 'sanctum')
            ->patchJson('/api/auth/profile', ['name' => '  Updated Name  ', 'email' => 'other@example.test'])
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Updated Name')
            ->assertJsonPath('data.user.email', $user->email);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Updated Name', 'email' => $user->email]);
    }

    public function test_logout_invalidates_the_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Signed out successfully.');

        $this->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_login_is_throttled_after_repeated_failures(): void
    {
        User::factory()->create(['email' => 'person@example.test']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/login', [
                'email' => 'person@example.test',
                'password' => 'wrong-password',
            ])->assertUnauthorized();
        }

        $this->postJson('/api/auth/login', [
            'email' => 'person@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many login attempts. Please try again later.');
    }

    public function test_forgot_password_does_not_reveal_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'person@example.test']);

        $payload = ['email' => 'person@example.test'];
        $this->postJson('/api/auth/forgot-password', $payload)
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link will be sent.');

        $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.test'])
            ->assertOk()
            ->assertJsonPath('message', 'If an account exists for that email, a password reset link will be sent.');

        Notification::assertSentTo($user, QueuedPasswordResetNotification::class);
    }

    public function test_forgot_password_is_rate_limited_per_client_ip(): void
    {
        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.test'])
                ->assertOk()
                ->assertJsonPath('message', 'If an account exists for that email, a password reset link will be sent.');
        }

        $this->postJson('/api/auth/forgot-password', ['email' => 'another-missing@example.test'])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many requests. Please try again later.');
    }

    public function test_valid_password_reset_changes_the_password(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test']);
        $token = Password::createToken($user);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk()
            ->assertJsonPath('message', 'Password has been reset successfully.');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertUnprocessable();
        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'new-password',
        ])->assertOk();
    }

    public function test_invalid_password_reset_token_returns_safe_unprocessable_response(): void
    {
        $user = User::factory()->create(['email' => 'person@example.test']);

        $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'token' => 'invalid-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The password reset token is invalid or has expired.');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }
}
