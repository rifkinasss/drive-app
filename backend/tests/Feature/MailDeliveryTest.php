<?php

namespace Tests\Feature;

use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\EmailVerificationNotification;
use App\Notifications\InvitationNotification;
use App\Notifications\QueuedPasswordResetNotification;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MailDeliveryTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.frontend_url' => 'https://drive.naslabs.my.id']);
    }

    public function test_transactional_mail_notifications_are_queued_after_commit_and_encrypted(): void
    {
        config([
            'queue.default' => 'database',
            'queue.connections.database.after_commit' => true,
        ]);
        $user = User::factory()->create(['name' => 'Cloud User']);
        $invitation = UserInvitation::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'invite-token'),
            'token_ciphertext' => 'invite-token',
            'expires_at' => now()->addHours(72),
        ]);
        $verification = EmailVerificationToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'verify-token'),
            'token_ciphertext' => 'verify-token',
            'expires_at' => now()->addHours(24),
        ]);
        $notifications = [
            new InvitationNotification($invitation, 'invite-token'),
            new EmailVerificationNotification($verification, 'verify-token'),
            new QueuedPasswordResetNotification('reset-token'),
        ];

        DB::beginTransaction();
        foreach ($notifications as $notification) {
            $user->notify($notification);
        }
        $this->assertDatabaseCount('jobs', 0);
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);

        DB::beginTransaction();
        foreach ($notifications as $notification) {
            $user->notify($notification);
        }
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();

        $this->assertDatabaseCount('jobs', 3);
        foreach (DB::table('jobs')->pluck('payload') as $payload) {
            $this->assertStringNotContainsString('invite-token', $payload);
            $this->assertStringNotContainsString('verify-token', $payload);
            $this->assertStringNotContainsString('reset-token', $payload);
        }
    }

    public function test_transactional_emails_use_frontend_links_and_expiry_copy(): void
    {
        $user = User::factory()->create([
            'name' => 'Cloud User',
            'email' => 'cloud-user@example.test',
        ]);
        $invitation = UserInvitation::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'invite-token'),
            'token_ciphertext' => 'invite-token',
            'expires_at' => now()->addHours(72),
        ]);
        $verification = EmailVerificationToken::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', 'verify-token'),
            'token_ciphertext' => 'verify-token',
            'expires_at' => now()->addHours(24),
        ]);

        $invitationMail = (new InvitationNotification($invitation, 'invite-token'))->toMail($user);
        $verificationMail = (new EmailVerificationNotification($verification, 'verify-token'))->toMail($user);
        $resetMail = (new QueuedPasswordResetNotification('reset-token'))->toMail($user);

        $this->assertSame('https://drive.naslabs.my.id/invite/invite-token', $invitationMail->actionUrl);
        $this->assertStringContainsString('Hello Cloud User', $invitationMail->greeting);
        $this->assertStringContainsString('expires', implode(' ', [...$invitationMail->introLines, ...$invitationMail->outroLines]));
        $this->assertSame('https://drive.naslabs.my.id/verify-email/verify-token', $verificationMail->actionUrl);
        $this->assertStringContainsString('expires', implode(' ', [...$verificationMail->introLines, ...$verificationMail->outroLines]));
        $this->assertSame('Reset your Drive by NasLabs password', $resetMail->subject);
        $this->assertSame(
            'https://drive.naslabs.my.id/reset-password?token=reset-token&email=cloud-user%40example.test',
            $resetMail->actionUrl,
        );
        $this->assertStringContainsString('60 minutes', implode(' ', [...$resetMail->introLines, ...$resetMail->outroLines]));
    }

    public function test_database_queue_after_commit_and_smtp_timeout_are_configured(): void
    {
        $this->assertSame('database', config('queue.default'));
        $this->assertTrue(config('queue.connections.database.after_commit'));
        $this->assertSame('database-uuids', config('queue.failed.driver'));
        $this->assertGreaterThan(0, config('mail.mailers.smtp.timeout'));
    }

    public function test_mail_diagnostic_rejects_invalid_recipient_without_sending(): void
    {
        $this->artisan('cloud:mail-test', ['recipient' => 'not-an-email'])
            ->assertFailed()
            ->expectsOutput('The recipient must be a valid email address.');
    }

    public function test_failed_mail_job_is_recoverable_and_does_not_expose_queued_token(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
            'mail.mailers.smtp.timeout' => 1,
            'queue.default' => 'database',
            'queue.connections.database.after_commit' => true,
            'queue.failed.driver' => 'database-uuids',
        ]);
        app('mail.manager')->purge('smtp');

        $user = User::factory()->pending()->create();
        $token = 'private-invitation-token-for-failure-test';
        $invitation = UserInvitation::query()->create([
            'user_id' => $user->id,
            'token_hash' => hash('sha256', $token),
            'token_ciphertext' => $token,
            'expires_at' => now()->addHours(72),
        ]);
        $user->notify(new InvitationNotification($invitation, $token));
        $this->assertDatabaseCount('jobs', 1);
        $queuedPayload = (string) DB::table('jobs')->value('payload');
        $this->assertStringNotContainsString($token, $queuedPayload);

        $this->artisan('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--timeout' => 5,
            '--sleep' => 0,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('failed_jobs', 1);
        $failedPayload = (string) DB::table('failed_jobs')->value('payload');
        $this->assertStringNotContainsString($token, $failedPayload);
        $this->assertSame('pending', $user->fresh()->status->value);
        $this->assertNull($invitation->fresh()->accepted_at);
        $this->assertNull($invitation->fresh()->revoked_at);
        $this->artisan('queue:failed')->assertExitCode(0);
    }
}
