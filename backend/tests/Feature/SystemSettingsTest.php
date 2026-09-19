<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\InvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SystemSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_active_admin_can_read_and_update_settings(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $this->getJson('/api/admin/settings')->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/settings')->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/settings/general')
            ->assertOk()
            ->assertJsonPath('data.settings.instanceName', 'Cloud by NasLabs');

        $this->patchJson('/api/admin/settings/general', ['instanceName' => 'NasLabs Cloud'])
            ->assertOk()
            ->assertJsonPath('data.settings.instanceName', 'NasLabs Cloud');
        $this->getJson('/api/admin/settings/general')->assertJsonPath('data.settings.instanceName', 'NasLabs Cloud');
        $this->assertDatabaseHas('system_settings', ['key' => 'general.instance_name', 'updated_by' => $admin->id]);
    }

    public function test_unknown_keys_wrong_groups_and_invalid_values_are_rejected_atomically(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->patchJson('/api/admin/settings/general', ['instanceName' => 'Changed', 'timezone' => 'Not/AZone'])
            ->assertUnprocessable();
        $this->getJson('/api/admin/settings/general')->assertJsonPath('data.settings.instanceName', 'Cloud by NasLabs');

        $this->patchJson('/api/admin/settings/general', ['maxUploadSizeBytes' => 1])
            ->assertUnprocessable();
        $this->patchJson('/api/admin/settings/general', ['notRegistered' => true])
            ->assertUnprocessable();
        $this->patchJson('/api/admin/settings/storage', ['trashCountsTowardQuota' => false])
            ->assertUnprocessable();
    }

    public function test_default_quota_is_used_for_new_invitation_users_without_changing_existing_users(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $existing = User::factory()->create(['quota_bytes' => 1234]);
        $this->actingAs($admin, 'sanctum');

        $this->patchJson('/api/admin/settings/storage', ['defaultUserQuotaBytes' => 987654321])->assertOk();
        $this->postJson('/api/admin/users/invitations', [
            'name' => 'Quota Invite',
            'email' => 'quota-invite@example.test',
            'role' => 'user',
        ])->assertCreated();

        $this->assertSame(1234, $existing->fresh()->quota_bytes);
        $this->assertSame(987654321, User::query()->where('email', 'quota-invite@example.test')->value('quota_bytes'));
        Notification::assertSentTo(User::query()->where('email', 'quota-invite@example.test')->firstOrFail(), InvitationNotification::class);
    }

    public function test_password_policy_is_applied_to_new_passwords(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');
        $this->patchJson('/api/admin/settings/security', [
            'passwordMinLength' => 12,
            'passwordRequireUppercase' => true,
            'passwordRequireNumber' => true,
        ])->assertOk();

        $this->postJson('/api/admin/users', [
            'name' => 'Weak Password',
            'email' => 'weak-password@example.test',
            'role' => 'user',
            'password' => 'shortpass',
            'password_confirmation' => 'shortpass',
        ])->assertUnprocessable();
    }

    public function test_maintenance_blocks_normal_users_but_keeps_admin_and_health_available(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $this->actingAs($admin, 'sanctum');
        $this->patchJson('/api/admin/settings/maintenance', ['enabled' => true, 'message' => 'Maintenance window.'])->assertOk();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/browser')
            ->assertStatus(503)
            ->assertJsonPath('message', 'Maintenance window.');
        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/settings')->assertOk();
        $this->getJson('/api/health')->assertOk()->assertJsonPath('data.maintenance', true);
    }
}
