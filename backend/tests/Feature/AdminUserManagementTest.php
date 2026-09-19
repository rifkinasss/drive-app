<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\QueuedPasswordResetNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_list_filter_and_view_users_without_private_fields(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Primary Admin', 'email' => 'admin@example.test']);
        User::factory()->create(['name' => 'Visible User', 'email' => 'visible@example.test']);
        User::factory()->pending()->create(['name' => 'Waiting User', 'email' => 'waiting@example.test']);
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/admin/users?search=visible&verification=unverified&perPage=1')
            ->assertOk()
            ->assertJsonPath('data.items.0.email', 'visible@example.test')
            ->assertJsonMissingPath('data.items.0.password')
            ->assertJsonPath('data.meta.perPage', 1);

        $user = User::query()->where('email', 'visible@example.test')->firstOrFail();
        $this->getJson('/api/admin/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.email', 'visible@example.test')
            ->assertJsonMissingPath('data.password')
            ->assertJsonMissingPath('data.invitations.0.token_ciphertext');
    }

    public function test_admin_can_update_name_role_and_quota_but_quota_cannot_drop_below_usage(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['quota_bytes' => 1000, 'used_bytes' => 100]);
        $this->actingAs($admin, 'sanctum');

        $this->patchJson('/api/admin/users/'.$target->id, [
            'name' => 'Renamed',
            'role' => 'admin',
            'quotaBytes' => 500,
        ])->assertOk()->assertJsonPath('data.quotaBytes', 500);

        $this->patchJson('/api/admin/users/'.$target->id, ['quotaBytes' => 99])
            ->assertStatus(422)
            ->assertJsonPath('code', 'QUOTA_BELOW_CURRENT_USAGE')
            ->assertJsonPath('data.usedBytes', 100);
    }

    public function test_last_active_admin_and_self_disable_are_protected(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/admin/users/'.$admin->id.'/disable')
            ->assertStatus(409)->assertJsonPath('code', 'CANNOT_DISABLE_SELF');
        $this->patchJson('/api/admin/users/'.$admin->id, ['role' => 'user'])
            ->assertStatus(409)->assertJsonPath('code', 'LAST_ADMIN_REQUIRED');
    }

    public function test_admin_can_disable_enable_and_request_reset_for_active_user(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'reset@example.test']);
        $this->actingAs($admin, 'sanctum');

        $this->postJson('/api/admin/users/'.$target->id.'/disable')->assertOk();
        $this->postJson('/api/admin/users/'.$target->id.'/enable')->assertOk();
        $this->postJson('/api/admin/users/'.$target->id.'/password-reset')->assertOk();
        Notification::assertSentTo($target, QueuedPasswordResetNotification::class);

        $pending = User::factory()->pending()->create();
        $this->postJson('/api/admin/users/'.$pending->id.'/enable')
            ->assertStatus(409)->assertJsonPath('code', 'INVALID_USER_STATE');
    }

    public function test_zero_data_user_can_be_deleted_and_owned_content_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $empty = User::factory()->create();
        $owned = User::factory()->create(['used_bytes' => 1]);
        $this->actingAs($admin, 'sanctum');

        $this->deleteJson('/api/admin/users/'.$empty->id)->assertOk();
        $this->assertDatabaseMissing('users', ['id' => $empty->id]);

        $this->deleteJson('/api/admin/users/'.$owned->id)
            ->assertStatus(409)->assertJsonPath('code', 'USER_OWNS_CONTENT');
    }

    public function test_summary_uses_accounted_quota_and_usage(): void
    {
        $admin = User::factory()->admin()->create(['quota_bytes' => 1000, 'used_bytes' => 100]);
        User::factory()->create(['quota_bytes' => 3000, 'used_bytes' => 900, 'email_verified_at' => now()]);
        $this->actingAs($admin, 'sanctum');

        $this->getJson('/api/admin/users/summary')
            ->assertOk()
            ->assertJsonPath('data.totalUsers', 2)
            ->assertJsonPath('data.adminCount', 1)
            ->assertJsonPath('data.totalQuotaBytes', 4000)
            ->assertJsonPath('data.totalUsedBytes', 1000)
            ->assertJsonPath('data.usagePercentage', 25);
    }
}
