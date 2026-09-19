<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\File;
use App\Models\Folder;
use App\Models\InternalShare;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
        Storage::fake('cloud');
    }

    public function test_file_viewer_can_read_but_cannot_mutate_or_manage_access(): void
    {
        [$owner, $recipient, $file] = $this->sharedFile('viewer');
        $this->actingAs($recipient, 'sanctum');

        $this->getJson('/api/files/'.$file->uuid)->assertOk()->assertJsonPath('data.name', 'shared.txt')->assertJsonPath('data.folderId', null);
        $this->get('/api/files/'.$file->uuid.'/preview')->assertOk();
        $this->get('/api/files/'.$file->uuid.'/download')->assertOk();
        $this->patchJson('/api/files/'.$file->uuid, ['name' => 'changed.txt'])->assertNotFound();
        $this->postJson('/api/files/'.$file->uuid.'/move', ['folderId' => null])->assertNotFound();
        $this->postJson('/api/files/'.$file->uuid.'/trash')->assertNotFound();
        $this->getJson('/api/files/'.$file->uuid.'/shares')->assertNotFound();
        $this->assertSame($owner->id, $file->fresh()->owner_id);
    }

    public function test_file_editor_can_rename_only_and_activity_keeps_recipient_actor(): void
    {
        [$owner, $recipient, $file] = $this->sharedFile('editor');
        $this->actingAs($recipient, 'sanctum');

        $this->patchJson('/api/files/'.$file->uuid, ['name' => 'renamed.txt'])->assertOk();
        $this->postJson('/api/files/'.$file->uuid.'/star')->assertNotFound();
        $activity = Activity::query()->where('user_id', $owner->id)->where('action', 'file.renamed')->latest('id')->firstOrFail();
        $this->assertSame($recipient->id, $activity->actor_id);
        $this->assertSame($owner->id, $file->fresh()->owner_id);
    }

    public function test_folder_share_is_inherited_with_shared_root_boundary(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $root = Folder::factory()->create(['owner_id' => $owner->id, 'name' => 'Projects']);
        $child = Folder::factory()->create(['owner_id' => $owner->id, 'parent_id' => $root->id, 'name' => '2026']);
        $outside = Folder::factory()->create(['owner_id' => $owner->id, 'name' => 'Private']);
        $nestedFile = File::factory()->create(['owner_id' => $owner->id, 'folder_id' => $child->id, 'original_name' => 'report.txt']);
        $outsideFile = File::factory()->create(['owner_id' => $owner->id, 'folder_id' => $outside->id, 'original_name' => 'secret.txt']);
        $this->actingAs($owner, 'sanctum');
        $this->postJson('/api/folders/'.$root->uuid.'/shares', ['recipientId' => $recipient->id, 'permission' => 'viewer'])->assertCreated();

        $this->actingAs($recipient, 'sanctum');
        $this->getJson('/api/shared/folders/'.$root->uuid.'/browser')->assertOk()
            ->assertJsonPath('data.breadcrumb.0.id', $root->uuid)
            ->assertJsonPath('data.folders.0.id', $child->uuid);
        $this->getJson('/api/shared/folders/'.$child->uuid.'/browser')->assertOk()->assertJsonPath('data.breadcrumb.0.name', 'Projects');
        $this->getJson('/api/files/'.$nestedFile->uuid)->assertOk();
        $this->getJson('/api/shared/folders/'.$outside->uuid.'/browser')->assertNotFound();
        $this->getJson('/api/files/'.$outsideFile->uuid)->assertNotFound();
    }

    public function test_share_permission_update_revoke_and_shared_lists_are_private(): void
    {
        [$owner, $recipient, $file] = $this->sharedFile('viewer');
        $share = InternalShare::query()->firstOrFail();
        $this->actingAs($owner, 'sanctum');
        $this->getJson('/api/files/'.$file->uuid.'/shares')->assertOk()->assertJsonPath('data.0.permission', 'owner')->assertJsonPath('data.1.permission', 'viewer');
        $this->getJson('/api/shared/by-me')->assertOk()->assertJsonPath('data.items.0.recipientCount', 1);
        $this->patchJson('/api/shares/'.$share->uuid, ['permission' => 'editor'])->assertOk()->assertJsonPath('data.permission', 'editor');
        $this->actingAs($recipient, 'sanctum');
        $this->getJson('/api/shared/with-me')->assertOk()->assertJsonPath('data.items.0.permission', 'editor');
        $this->patchJson('/api/shares/'.$share->uuid, ['permission' => 'viewer'])->assertNotFound();
        $this->actingAs($owner, 'sanctum');
        $this->deleteJson('/api/shares/'.$share->uuid)->assertOk();
        $this->actingAs($recipient, 'sanctum');
        $this->getJson('/api/files/'.$file->uuid)->assertNotFound();
    }

    public function test_sharing_rejects_self_pending_and_disabled_recipients(): void
    {
        $owner = User::factory()->create();
        $file = File::factory()->create(['owner_id' => $owner->id]);
        $pending = User::factory()->pending()->create();
        $disabled = User::factory()->disabled()->create();
        $this->actingAs($owner, 'sanctum');
        $this->postJson('/api/files/'.$file->uuid.'/shares', ['recipientId' => $owner->id, 'permission' => 'viewer'])->assertUnprocessable();
        $this->postJson('/api/files/'.$file->uuid.'/shares', ['recipientId' => $pending->id, 'permission' => 'viewer'])->assertUnprocessable();
        $this->postJson('/api/files/'.$file->uuid.'/shares', ['recipientId' => $disabled->id, 'permission' => 'viewer'])->assertUnprocessable();
        $this->getJson('/api/users/search?q='.$pending->name)->assertOk()->assertJsonMissing(['id' => $pending->id]);
    }

    public function test_permanent_delete_removes_share_and_sharing_does_not_change_quota(): void
    {
        $owner = User::factory()->create(['used_bytes' => 4]);
        $recipient = User::factory()->create();
        $file = File::factory()->create(['owner_id' => $owner->id, 'size_bytes' => 4]);
        $this->actingAs($owner, 'sanctum');
        $this->postJson('/api/files/'.$file->uuid.'/shares', ['recipientId' => $recipient->id, 'permission' => 'viewer'])->assertCreated();
        $this->assertSame(4, $owner->fresh()->used_bytes);
        $this->postJson('/api/files/'.$file->uuid.'/trash')->assertOk();
        $this->deleteJson('/api/files/'.$file->uuid.'/permanent')->assertOk();
        $this->assertDatabaseCount('internal_shares', 0);
        $this->assertSame(0, $owner->fresh()->used_bytes);
    }

    private function sharedFile(string $permission): array
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $file = File::factory()->create(['owner_id' => $owner->id, 'original_name' => 'shared.txt', 'mime_type' => 'text/plain', 'size_bytes' => 4, 'disk' => 'cloud', 'path' => 'users/'.$owner->id.'/files/'.str()->uuid()]);
        Storage::disk('cloud')->put($file->path, 'data');
        $this->actingAs($owner, 'sanctum');
        $this->postJson('/api/files/'.$file->uuid.'/shares', ['recipientId' => $recipient->id, 'permission' => $permission])->assertCreated();

        return [$owner, $recipient, $file];
    }
}
