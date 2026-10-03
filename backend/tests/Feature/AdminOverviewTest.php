<?php

namespace Tests\Feature;

use App\Enums\ActivityAction;
use App\Enums\SharePermission;
use App\Models\Activity;
use App\Models\File;
use App\Models\Folder;
use App\Models\InternalShare;
use App\Models\PublicShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_read_aggregate_overview_without_private_file_data(): void
    {
        $admin = User::factory()->admin()->create(['quota_bytes' => 10_000, 'used_bytes' => 2_000]);
        $owner = User::factory()->create(['quota_bytes' => 20_000, 'used_bytes' => 4_000]);
        $recipient = User::factory()->create(['quota_bytes' => 0, 'used_bytes' => 0]);
        $file = File::factory()->create(['owner_id' => $owner->id, 'size_bytes' => 1_250]);
        Folder::factory()->create(['owner_id' => $owner->id]);
        File::factory()->trashed()->create(['owner_id' => $owner->id, 'size_bytes' => 750]);
        Folder::factory()->trashed()->create(['owner_id' => $owner->id]);
        InternalShare::query()->create(['owner_id' => $owner->id, 'recipient_id' => $recipient->id, 'shareable_type' => 'file', 'shareable_id' => $file->id, 'permission' => SharePermission::Viewer]);
        PublicShareLink::query()->create(['owner_id' => $owner->id, 'shareable_type' => 'file', 'shareable_id' => $file->id, 'token_hash' => hash('sha256', 'overview-token'), 'token_encrypted' => 'encrypted', 'enabled' => true, 'permission' => 'viewer']);
        Activity::query()->create(['user_id' => $owner->id, 'actor_id' => $owner->id, 'action' => ActivityAction::FileUploaded, 'subject_type' => 'file', 'subject_id' => $file->id, 'subject_uuid' => $file->uuid, 'subject_name' => $file->original_name, 'created_at' => now()]);

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/overview')
            ->assertOk()
            ->assertJsonPath('data.users.total', 3)
            ->assertJsonPath('data.users.active', 3)
            ->assertJsonPath('data.storage.usedBytes', 6_000)
            ->assertJsonPath('data.storage.quotaBytes', 30_000)
            ->assertJsonPath('data.files.files', 2)
            ->assertJsonPath('data.files.folders', 2)
            ->assertJsonPath('data.sharing.internalShares', 1)
            ->assertJsonPath('data.sharing.publicLinks', 1)
            ->assertJsonPath('data.activity.recentCount', 1)
            ->assertJsonPath('data.trash.items', 2)
            ->assertJsonPath('data.trash.sizeBytes', 750)
            ->assertJsonMissingPath('data.privateFiles')
            ->assertJsonMissingPath('data.items');
    }

    public function test_only_admin_can_read_overview(): void
    {
        $user = User::factory()->create();

        $this->getJson('/api/admin/overview')->assertUnauthorized();
        $this->actingAs($user, 'sanctum')->getJson('/api/admin/overview')->assertForbidden();
    }
}
