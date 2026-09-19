<?php

namespace Tests\Feature;

use App\Enums\UserStatus;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class FolderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost');
    }

    public function test_user_can_create_root_and_nested_folders(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $root = $this->postJson('/api/folders', ['name' => 'Projects', 'parentId' => null])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Projects')
            ->assertJsonPath('data.parentId', null);
        $rootId = $root->json('data.id');

        $child = $this->postJson('/api/folders', ['name' => '2026', 'parentId' => $rootId])
            ->assertCreated()
            ->assertJsonPath('data.name', '2026')
            ->assertJsonPath('data.parentId', $rootId);

        $this->assertNotSame((string) $user->id, $rootId);
        $this->getJson('/api/folders')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/folders?parentId='.$rootId)
            ->assertOk()
            ->assertJsonPath('data.0.id', $child->json('data.id'));
    }

    public function test_folder_names_are_trimmed_case_insensitive_and_private_to_owner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/folders', ['name' => '  Project Files  '])->assertCreated()->assertJsonPath('data.name', 'Project Files');
        $this->postJson('/api/folders', ['name' => 'project files'])->assertUnprocessable();

        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum');
        $this->postJson('/api/folders', ['name' => 'Project Files'])->assertCreated();
    }

    public function test_same_name_is_allowed_under_different_parents(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $projects = $this->postJson('/api/folders', ['name' => 'Projects'])->json('data.id');
        $archive = $this->postJson('/api/folders', ['name' => 'Archive'])->json('data.id');

        $this->postJson('/api/folders', ['name' => 'Documents', 'parentId' => $projects])->assertCreated();
        $this->postJson('/api/folders', ['name' => 'Documents', 'parentId' => $archive])->assertCreated();
    }

    public function test_other_users_parent_and_folder_are_not_accessible(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'sanctum');
        $folderId = $this->postJson('/api/folders', ['name' => 'Private'])->json('data.id');

        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum');
        $this->postJson('/api/folders', ['name' => 'Child', 'parentId' => $folderId])->assertUnprocessable();
        $this->getJson('/api/folders/'.$folderId)->assertNotFound();
        $this->patchJson('/api/folders/'.$folderId, ['name' => 'Changed'])->assertNotFound();
        $this->postJson('/api/folders/'.$folderId.'/move', ['parentId' => null])->assertNotFound();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'sanctum');
        $this->getJson('/api/folders/'.$folderId)->assertNotFound();
    }

    public function test_user_can_rename_and_move_folder_to_root(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $parentId = $this->postJson('/api/folders', ['name' => 'Projects'])->json('data.id');
        $childId = $this->postJson('/api/folders', ['name' => '2026', 'parentId' => $parentId])->json('data.id');

        $this->patchJson('/api/folders/'.$childId, ['name' => 'Reports'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Reports');
        $this->postJson('/api/folders/'.$childId.'/move', ['parentId' => null])
            ->assertOk()
            ->assertJsonPath('data.parentId', null);
    }

    public function test_move_rejects_self_descendant_and_duplicate_target(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $a = $this->postJson('/api/folders', ['name' => 'A'])->json('data.id');
        $b = $this->postJson('/api/folders', ['name' => 'B', 'parentId' => $a])->json('data.id');
        $c = $this->postJson('/api/folders', ['name' => 'C', 'parentId' => $b])->json('data.id');

        $this->postJson('/api/folders/'.$a.'/move', ['parentId' => $c])->assertUnprocessable();
        $this->postJson('/api/folders/'.$a.'/move', ['parentId' => $a])->assertUnprocessable();

        $target = $this->postJson('/api/folders', ['name' => 'Target'])->json('data.id');
        $this->postJson('/api/folders', ['name' => 'A', 'parentId' => $target])->assertCreated();
        $this->postJson('/api/folders/'.$a.'/move', ['parentId' => $target])->assertUnprocessable();
    }

    public function test_breadcrumb_is_root_first_and_excludes_virtual_root(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $projects = $this->postJson('/api/folders', ['name' => 'Projects'])->json('data.id');
        $year = $this->postJson('/api/folders', ['name' => '2026', 'parentId' => $projects])->json('data.id');
        $reports = $this->postJson('/api/folders', ['name' => 'Reports', 'parentId' => $year])->json('data.id');

        $this->getJson('/api/folders/'.$reports.'/breadcrumb')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Projects')
            ->assertJsonPath('data.1.name', '2026')
            ->assertJsonPath('data.2.name', 'Reports');
    }

    public function test_invalid_names_are_rejected(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');

        foreach (['   ', '.', '..', 'with/slash', 'with\\slash'] as $name) {
            $this->postJson('/api/folders', ['name' => $name])->assertUnprocessable();
        }
    }

    public function test_pending_and_disabled_users_cannot_access_folder_api(): void
    {
        foreach ([UserStatus::Pending, UserStatus::Disabled] as $status) {
            $user = User::factory()->create();
            $this->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ])->assertOk();
            $user->update(['status' => $status]);
            Auth::forgetGuards();
            $this->getJson('/api/folders')->assertForbidden();
        }
    }

    public function test_trashed_parent_cannot_receive_new_children_or_appear_in_list(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $user->id, 'name' => 'Old', 'trashed_at' => now()]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/folders')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/folders', ['name' => 'Child', 'parentId' => $folder->uuid])->assertUnprocessable();
        $this->getJson('/api/folders/'.$folder->uuid)->assertNotFound();
    }
}
