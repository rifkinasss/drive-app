<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
        Storage::fake('cloud');
    }

    public function test_mutations_record_safe_personal_timeline_events(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $folder = $this->postJson('/api/folders', ['name' => 'Projects'])->assertCreated()->json('data');
        $file = $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('report.txt', 'content'),
            'folderId' => $folder['id'],
        ])->assertCreated()->json('data');
        $this->patchJson('/api/files/'.$file['id'], ['name' => 'final.txt'])->assertOk();
        $this->postJson('/api/files/'.$file['id'].'/star')->assertOk();
        $this->deleteJson('/api/files/'.$file['id'].'/star')->assertOk();
        $this->postJson('/api/files/'.$file['id'].'/trash')->assertOk();
        $this->postJson('/api/files/'.$file['id'].'/restore')->assertOk();

        $actions = Activity::query()->where('user_id', $user->id)->orderBy('id')->pluck('action')->map->value->all();
        $this->assertSame([
            'folder.created', 'file.uploaded', 'file.renamed', 'file.starred',
            'file.unstarred', 'file.trashed', 'file.restored',
        ], $actions);
        $this->getJson('/api/activity')->assertOk()
            ->assertJsonPath('data.items.0.action', 'file.restored')
            ->assertJsonPath('data.items.0.subject.name', 'final.txt')
            ->assertJsonMissingPath('data.items.0.subject_id')
            ->assertJsonMissingPath('data.items.0.metadata.path');
    }

    public function test_idempotent_star_and_same_folder_move_do_not_add_events(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $user->id]);
        $file = File::factory()->create(['owner_id' => $user->id, 'folder_id' => $folder->id]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/files/'.$file->uuid.'/star')->assertOk();
        $this->postJson('/api/files/'.$file->uuid.'/star')->assertOk();
        $this->postJson('/api/files/'.$file->uuid.'/move', ['folderId' => $folder->uuid])->assertOk();

        $this->assertSame(1, Activity::query()->where('user_id', $user->id)->count());
        $this->assertSame('file.starred', Activity::query()->first()->action->value);
    }

    public function test_recursive_trash_and_permanent_delete_record_root_snapshot_only(): void
    {
        $user = User::factory()->create();
        $root = Folder::factory()->create(['owner_id' => $user->id, 'name' => 'Archive']);
        $child = Folder::factory()->create(['owner_id' => $user->id, 'parent_id' => $root->id]);
        File::factory()->create(['owner_id' => $user->id, 'folder_id' => $child->id, 'original_name' => 'old.txt', 'size_bytes' => 0, 'trashed_at' => now(), 'trash_batch_id' => str()->uuid()]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/folders/'.$root->uuid.'/trash')->assertOk();
        $this->postJson('/api/folders/'.$root->uuid.'/restore')->assertOk();
        $this->postJson('/api/folders/'.$root->uuid.'/trash')->assertOk();
        $this->deleteJson('/api/folders/'.$root->uuid.'/permanent')->assertOk();

        $actions = Activity::query()->where('user_id', $user->id)->orderBy('id')->pluck('action')->map->value->all();
        $this->assertSame(['folder.trashed', 'folder.restored', 'folder.trashed', 'folder.deleted'], $actions);
        $this->assertDatabaseHas('activities', ['action' => 'folder.deleted', 'subject_name' => 'Archive']);
    }

    public function test_activity_feed_is_private_and_supports_filters_and_cursor_pagination(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Activity::create(['user_id' => $owner->id, 'actor_id' => $owner->id, 'action' => 'file.uploaded', 'subject_type' => 'file', 'subject_uuid' => str()->uuid(), 'subject_name' => 'Report.pdf', 'created_at' => now()->subMinute()]);
        Activity::create(['user_id' => $owner->id, 'actor_id' => $owner->id, 'action' => 'file.uploaded', 'subject_type' => 'file', 'subject_uuid' => str()->uuid(), 'subject_name' => 'Report-2.pdf', 'created_at' => now()->subMinutes(2)]);
        Activity::create(['user_id' => $owner->id, 'actor_id' => $owner->id, 'action' => 'folder.created', 'subject_type' => 'folder', 'subject_uuid' => str()->uuid(), 'subject_name' => 'Photos', 'created_at' => now()]);
        Activity::create(['user_id' => $other->id, 'actor_id' => $other->id, 'action' => 'file.uploaded', 'subject_type' => 'file', 'subject_uuid' => str()->uuid(), 'subject_name' => 'Private.txt', 'created_at' => now()]);
        $this->actingAs($owner, 'sanctum');

        $first = $this->getJson('/api/activity?limit=1&action[]=file.uploaded&search=report')->assertOk();
        $first->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.subject.name', 'Report.pdf');
        $this->assertNotNull($first->json('data.meta.nextCursor'));
        $this->getJson('/api/activity')->assertJsonMissing(['name' => 'Private.txt']);
        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->getJson('/api/activity')->assertJsonCount(0, 'data.items');
    }

    public function test_download_is_recorded_but_preview_and_range_requests_are_not(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->create(['owner_id' => $user->id, 'mime_type' => 'text/plain', 'size_bytes' => 4, 'disk' => 'cloud', 'path' => 'users/'.$user->id.'/files/'.str()->uuid()]);
        Storage::disk('cloud')->put($file->path, 'data');
        $this->actingAs($user, 'sanctum');

        $this->get('/api/files/'.$file->uuid.'/preview')->assertOk();
        $this->get('/api/files/'.$file->uuid.'/download')->assertOk();
        $this->withHeaders(['Range' => 'bytes=0-1'])->get('/api/files/'.$file->uuid.'/download')->assertStatus(206);

        $this->assertSame(1, Activity::query()->where('action', 'file.downloaded')->count());
    }
}
