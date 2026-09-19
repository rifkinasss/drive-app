<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class BrowserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_browser_returns_direct_root_and_nested_items_with_breadcrumb(): void
    {
        $user = User::factory()->create();
        $root = Folder::factory()->create(['owner_id' => $user->id, 'name' => 'Projects']);
        $child = Folder::factory()->create(['owner_id' => $user->id, 'parent_id' => $root->id, 'name' => '2026']);
        $otherNested = Folder::factory()->create(['owner_id' => $user->id, 'parent_id' => $child->id, 'name' => 'Hidden child']);
        File::factory()->create(['owner_id' => $user->id, 'original_name' => 'root.txt']);
        File::factory()->create(['owner_id' => $user->id, 'folder_id' => $child->id, 'original_name' => 'nested.txt']);
        File::factory()->create(['owner_id' => $user->id, 'folder_id' => $otherNested->id, 'original_name' => 'deep.txt']);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/browser')
            ->assertOk()
            ->assertJsonPath('data.currentFolder', null)
            ->assertJsonCount(1, 'data.folders')
            ->assertJsonCount(1, 'data.files');
        $this->getJson('/api/browser?folderId='.$child->uuid)
            ->assertOk()
            ->assertJsonPath('data.currentFolder.id', $child->uuid)
            ->assertJsonPath('data.breadcrumb.0.name', 'Projects')
            ->assertJsonPath('data.breadcrumb.1.name', '2026')
            ->assertJsonCount(1, 'data.folders')
            ->assertJsonPath('data.files.0.name', 'nested.txt');
    }

    public function test_browser_search_and_sort_are_current_location_only_and_exclude_trash(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $user->id]);
        Folder::factory()->create(['owner_id' => $user->id, 'parent_id' => $folder->id, 'name' => 'Report Folder']);
        File::factory()->create(['owner_id' => $user->id, 'folder_id' => $folder->id, 'original_name' => 'zeta.txt', 'size_bytes' => 20]);
        File::factory()->create(['owner_id' => $user->id, 'folder_id' => $folder->id, 'original_name' => 'Report.pdf', 'size_bytes' => 5]);
        File::factory()->create(['owner_id' => $user->id, 'folder_id' => $folder->id, 'original_name' => 'deleted.txt', 'trashed_at' => now(), 'size_bytes' => 1]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/browser?folderId='.$folder->uuid.'&search=REPORT')
            ->assertOk()
            ->assertJsonCount(1, 'data.folders')
            ->assertJsonCount(1, 'data.files')
            ->assertJsonPath('data.files.0.name', 'Report.pdf');
        $this->getJson('/api/browser?folderId='.$folder->uuid.'&sort=size&direction=desc')
            ->assertOk()
            ->assertJsonPath('data.files.0.name', 'zeta.txt')
            ->assertJsonPath('data.meta.sort', 'size');
    }

    public function test_browser_is_private_and_rejects_trashed_or_invalid_folder(): void
    {
        $owner = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $owner->id]);
        $trashed = Folder::factory()->create(['owner_id' => $owner->id, 'trashed_at' => now()]);
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson('/api/browser?folderId='.$folder->uuid)->assertNotFound();
        $this->getJson('/api/browser?folderId='.$trashed->uuid)->assertNotFound();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->getJson('/api/browser?folderId='.$folder->uuid)->assertNotFound();
    }

    public function test_recent_returns_modified_active_files_with_limit_and_location(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $user->id, 'name' => 'Projects']);
        File::factory()->create(['owner_id' => $user->id, 'folder_id' => $folder->id, 'original_name' => 'new.txt', 'updated_at' => now()]);
        File::factory()->create(['owner_id' => $user->id, 'original_name' => 'old.txt', 'updated_at' => now()->subDay()]);
        File::factory()->create(['owner_id' => $user->id, 'original_name' => 'deleted.txt', 'trashed_at' => now(), 'updated_at' => now()->addMinute()]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/recent?limit=1')
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.name', 'new.txt')
            ->assertJsonPath('data.items.0.folderId', $folder->uuid)
            ->assertJsonPath('data.meta.limit', 1);
    }

    public function test_folder_star_and_starred_collection_are_idempotent_and_private(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $user->id, 'name' => 'Starred folder']);
        $file = File::factory()->create(['owner_id' => $user->id, 'original_name' => 'starred.txt', 'is_starred' => true]);
        $trashed = Folder::factory()->create(['owner_id' => $user->id, 'name' => 'Trashed starred', 'is_starred' => true, 'trashed_at' => now()]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/folders/'.$folder->uuid.'/star')->assertOk();
        $this->postJson('/api/folders/'.$folder->uuid.'/star')->assertOk();
        $this->getJson('/api/starred')
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonFragment(['type' => 'folder', 'id' => $folder->uuid])
            ->assertJsonFragment(['type' => 'file', 'id' => $file->uuid]);
        $this->deleteJson('/api/folders/'.$folder->uuid.'/star')->assertOk();
        $this->getJson('/api/starred')->assertJsonCount(1, 'data.items');
        $this->assertNotContains($trashed->uuid, array_column($this->getJson('/api/starred')->json('data.items'), 'id'));
    }

    public function test_pending_and_disabled_users_cannot_access_browser_recent_or_starred(): void
    {
        foreach ([UserStatus::Pending, UserStatus::Disabled] as $status) {
            $user = User::factory()->create();
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
            $user->update(['status' => $status]);
            Auth::forgetGuards();
            $this->actingAs($user, 'sanctum');
            $this->getJson('/api/browser')->assertForbidden();
            $this->actingAs($user, 'sanctum');
            $this->getJson('/api/recent')->assertForbidden();
            $this->actingAs($user, 'sanctum');
            $this->getJson('/api/starred')->assertForbidden();
        }
    }
}
