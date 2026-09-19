<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Services\LastAdminGuardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost');

        Route::middleware(['auth:sanctum', 'active.account'])
            ->get('/api/test-active', fn () => response()->json(['ok' => true]));

        Route::middleware(['auth:sanctum', 'active.account', 'admin'])
            ->get('/api/test-admin', fn () => response()->json(['ok' => true]));
    }

    public function test_active_user_can_login_and_current_user_exposes_role_and_status(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->getJson('/api/auth/user')
            ->assertOk()
            ->assertJsonPath('data.user.role', 'user')
            ->assertJsonPath('data.user.status', 'active');
    }

    public function test_pending_user_cannot_login(): void
    {
        $user = User::factory()->pending()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account is not active yet.');

        $this->getJson('/api/auth/user')->assertUnauthorized();
    }

    public function test_disabled_user_cannot_login(): void
    {
        $user = User::factory()->disabled()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('message', 'This account has been disabled.');
    }

    public function test_wrong_password_does_not_reveal_disabled_status(): void
    {
        $user = User::factory()->disabled()->create();

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();

        $this->assertSame(
            'The provided credentials are invalid.',
            $response->json('message'),
        );
    }

    public function test_existing_session_is_blocked_after_user_is_disabled(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $user->update(['status' => UserStatus::Disabled]);
        $this->assertSame(UserStatus::Disabled, $user->fresh()->status);
        Auth::forgetGuards();

        $this->getJson('/api/auth/user')
            ->assertForbidden()
            ->assertJsonPath('message', 'This account is not active.');
    }

    public function test_admin_middleware_allows_admin_and_denies_regular_user(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');
        $this->getJson('/api/test-admin')->assertOk();

        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/test-admin')
            ->assertForbidden()
            ->assertJsonPath('message', 'Forbidden.');
    }

    public function test_admin_middleware_returns_unauthorized_without_user(): void
    {
        $this->getJson('/api/test-admin')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_last_admin_guard_only_counts_active_admins(): void
    {
        $guard = app(LastAdminGuardService::class);
        $admin = User::factory()->admin()->create();

        $this->assertTrue($guard->isLastActiveAdmin($admin));
        $this->assertFalse($guard->canDemote($admin));
        $this->assertFalse($guard->canDisable($admin));

        User::factory()->admin()->create();
        $this->assertFalse($guard->isLastActiveAdmin($admin));
        $this->assertTrue($guard->canDemote($admin));

        User::factory()->admin()->disabled()->create();
        User::factory()->admin()->pending()->create();
        $this->assertFalse($guard->isLastActiveAdmin($admin));
    }

    public function test_role_and_status_are_backed_enum_values(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::User, $user->role);
        $this->assertSame(UserStatus::Active, $user->status);
    }
}
