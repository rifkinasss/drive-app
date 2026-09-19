<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_admin_can_create_an_invitation_with_hashed_token(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');

        $response = $this->postJson('/api/admin/users/invitations', [
            'name' => 'Invited Person',
            'email' => 'invited@example.test',
            'role' => 'user',
        ])->assertCreated()
            ->assertJsonPath('data.user.status', 'pending')
            ->assertJsonPath('data.user.emailVerifiedAt', null);

        $token = Str::afterLast($response->json('data.invitation.url'), '/');
        $invitation = UserInvitation::query()->with('user')->firstOrFail();

        $this->assertNotSame($token, $invitation->token_hash);
        $this->assertNotSame($token, (string) \DB::table('user_invitations')->value('token_ciphertext'));
        $this->assertSame(UserStatus::Pending, $invitation->user->status);
        Notification::assertSentTo($invitation->user, InvitationNotification::class);
    }

    public function test_non_admin_and_guest_cannot_create_invitations(): void
    {
        $payload = ['name' => 'Person', 'email' => 'person@example.test', 'role' => 'user'];

        $this->postJson('/api/admin/users/invitations', $payload)->assertUnauthorized();

        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/admin/users/invitations', $payload)->assertForbidden();
    }

    public function test_valid_invitation_can_be_accepted_only_once(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');
        $response = $this->postJson('/api/admin/users/invitations', [
            'name' => 'Invited Person',
            'email' => 'invited@example.test',
            'role' => 'user',
        ]);
        $token = Str::afterLast($response->json('data.invitation.url'), '/');

        $this->postJson('/api/invitations/'.$token.'/accept', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk()
            ->assertJsonPath('message', 'Account activated successfully.');

        $user = User::query()->where('email', 'invited@example.test')->firstOrFail();
        $this->assertSame(UserStatus::Active, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('new-password', $user->password));

        $this->postJson('/api/invitations/'.$token.'/accept', [
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertGone();
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $response = $this->postJson('/api/admin/users/invitations', [
            'name' => 'Invited Person',
            'email' => 'expired@example.test',
            'role' => 'user',
        ]);
        $token = Str::afterLast($response->json('data.invitation.url'), '/');
        $this->travel(73)->hours();

        $this->postJson('/api/invitations/'.$token.'/accept', [
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertGone();

        $this->assertSame(UserStatus::Pending, User::where('email', 'expired@example.test')->firstOrFail()->status);
    }

    public function test_resend_reuses_valid_invitation_and_regenerate_revokes_old_token(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $response = $this->postJson('/api/admin/users/invitations', [
            'name' => 'Invited Person',
            'email' => 'resend@example.test',
            'role' => 'user',
        ]);
        $oldToken = Str::afterLast($response->json('data.invitation.url'), '/');
        $user = User::where('email', 'resend@example.test')->firstOrFail();
        $oldHash = $user->invitations()->firstOrFail()->token_hash;

        $this->postJson('/api/admin/users/'.$user->id.'/invitation/resend')
            ->assertOk();
        $this->assertSame($oldHash, $user->invitations()->firstOrFail()->fresh()->token_hash);

        $regenerated = $this->postJson('/api/admin/users/'.$user->id.'/invitation/regenerate')
            ->assertOk();
        $newToken = Str::afterLast($regenerated->json('data.invitation.url'), '/');
        $this->assertNotSame($oldToken, $newToken);
        Notification::assertSentTo(
            $user,
            InvitationNotification::class,
            fn (InvitationNotification $notification): bool => $notification->toMail($user)->actionUrl === $regenerated->json('data.invitation.url'),
        );
        $this->getJson('/api/invitations/'.$oldToken)->assertGone();
        $this->getJson('/api/invitations/'.$newToken)->assertOk();
    }

    public function test_invitation_resend_is_rate_limited(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->pending()->create();
        $user->invitations()->create([
            'token_hash' => hash('sha256', 'valid-resend-token'),
            'token_ciphertext' => 'valid-resend-token',
            'expires_at' => now()->addHours(72),
        ]);
        $this->actingAs($admin, 'sanctum');

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/admin/users/'.$user->id.'/invitation/resend')->assertOk();
        }

        $this->postJson('/api/admin/users/'.$user->id.'/invitation/resend')
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Too many requests. Please try again later.');
    }

    public function test_admin_can_create_active_unverified_user_with_password(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');

        $response = $this->postJson('/api/admin/users', [
            'name' => 'Manual Person',
            'email' => 'manual@example.test',
            'role' => 'admin',
            'password' => 'manual-password',
            'password_confirmation' => 'manual-password',
        ])->assertCreated()
            ->assertJsonPath('data.user.status', 'active')
            ->assertJsonPath('data.user.role', 'admin')
            ->assertJsonPath('data.user.emailVerifiedAt', null)
            ->assertJsonMissingPath('data.user.password');

        $user = User::where('email', 'manual@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('manual-password', $user->password));
        $this->assertDatabaseCount('user_invitations', 0);
        Notification::assertSentTo($user, EmailVerificationNotification::class);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'manual-password',
        ])->assertOk();
    }

    public function test_email_verification_sets_timestamp_without_changing_status(): void
    {
        Notification::fake();
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->postJson('/api/admin/users', [
            'name' => 'Manual Person',
            'email' => 'verify@example.test',
            'role' => 'user',
            'password' => 'manual-password',
            'password_confirmation' => 'manual-password',
        ])->assertCreated();
        $user = User::where('email', 'verify@example.test')->firstOrFail();
        $rawToken = $user->emailVerificationTokens()->firstOrFail()->token_ciphertext;

        $this->getJson('/api/email-verification/'.$rawToken)->assertOk();
        $this->postJson('/api/email-verification/'.$rawToken.'/verify')
            ->assertOk()
            ->assertJsonPath('message', 'Email verified successfully.');

        $this->assertSame(UserStatus::Active, $user->fresh()->status);
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->postJson('/api/email-verification/'.$rawToken.'/verify')->assertGone();
    }

    public function test_pending_user_cannot_use_manual_verification_and_disabled_user_stays_disabled(): void
    {
        $pending = User::factory()->pending()->create();
        $pendingToken = $pending->emailVerificationTokens()->create([
            'token_hash' => hash('sha256', 'pending-token'),
            'token_ciphertext' => 'pending-token',
            'expires_at' => now()->addDay(),
        ]);
        $this->postJson('/api/email-verification/pending-token/verify')->assertGone();
        $this->assertNull($pending->fresh()->email_verified_at);

        $disabled = User::factory()->disabled()->create();
        $disabled->emailVerificationTokens()->create([
            'token_hash' => hash('sha256', 'disabled-token'),
            'token_ciphertext' => 'disabled-token',
            'expires_at' => now()->addDay(),
        ]);
        $this->postJson('/api/email-verification/disabled-token/verify')->assertOk();
        $this->assertSame(UserStatus::Disabled, $disabled->fresh()->status);
    }
}
