<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\File;
use App\Models\Folder;
use App\Models\PublicShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicShareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
        Storage::fake('cloud');
        config(['app.frontend_url' => 'http://localhost:3000']);
    }

    public function test_owner_can_enable_idempotently_and_manage_a_file_link_without_plaintext_storage(): void
    {
        $owner = User::factory()->create();
        $file = $this->storedFile($owner, 'public.pdf', 'pdf bytes', 'application/pdf');
        $this->actingAs($owner, 'sanctum');

        $first = $this->postJson('/api/files/'.$file->uuid.'/public-link')->assertCreated();
        $url = $first->json('data.url');
        $token = basename($url);
        $link = PublicShareLink::query()->firstOrFail();
        $this->assertNotSame($token, $link->token_hash);
        $this->assertStringNotContainsString($token, $link->token_encrypted);
        $this->postJson('/api/files/'.$file->uuid.'/public-link')->assertCreated()->assertJsonPath('data.url', $url);
        $this->getJson('/api/shared/links')->assertOk()->assertJsonPath('data.items.0.url', $url);
        $this->assertSame(1, Activity::query()->where('action', 'public_link.enabled')->count());
    }

    public function test_public_file_resolve_preview_download_and_privacy(): void
    {
        $owner = User::factory()->create(['name' => 'Public Owner', 'email' => 'owner@example.test']);
        $file = $this->storedFile($owner, 'public.txt', 'public bytes', 'text/plain');
        $this->actingAs($owner, 'sanctum');
        $token = basename($this->postJson('/api/files/'.$file->uuid.'/public-link')->json('data.url'));

        $this->getJson('/api/public/shares/'.$token)->assertOk()
            ->assertJsonPath('data.type', 'file')
            ->assertJsonPath('data.name', 'public.txt')
            ->assertJsonPath('data.ownerDisplayName', 'Public Owner')
            ->assertJsonMissingPath('data.ownerId')
            ->assertJsonMissingPath('data.ownerEmail')
            ->assertJsonMissingPath('data.path');
        $this->get('/api/public/shares/'.$token.'/preview')->assertOk()->assertStreamedContent('public bytes');
        $this->get('/api/public/shares/'.$token.'/download')->assertOk()->assertStreamedContent('public bytes');
    }

    public function test_disable_regenerate_and_invalid_tokens_are_generic_and_old_tokens_fail(): void
    {
        $owner = User::factory()->create();
        $file = $this->storedFile($owner, 'rotate.txt', 'data', 'text/plain');
        $this->actingAs($owner, 'sanctum');
        $old = basename($this->postJson('/api/files/'.$file->uuid.'/public-link')->json('data.url'));
        $this->deleteJson('/api/files/'.$file->uuid.'/public-link')->assertOk();
        $this->getJson('/api/public/shares/'.$old)->assertNotFound()->assertJsonPath('message', 'This shared item is unavailable.');
        $new = basename($this->postJson('/api/files/'.$file->uuid.'/public-link')->json('data.url'));
        $this->assertNotSame($old, $new);
        $rotated = basename($this->postJson('/api/files/'.$file->uuid.'/public-link/regenerate')->json('data.url'));
        $this->assertNotSame($new, $rotated);
        $this->getJson('/api/public/shares/'.$new)->assertNotFound();
        $this->getJson('/api/public/shares/'.$rotated)->assertOk();
        $this->assertSame(1, Activity::query()->where('action', 'public_link.disabled')->count());
        $this->assertSame(2, Activity::query()->where('action', 'public_link.enabled')->count());
        $this->assertSame(1, Activity::query()->where('action', 'public_link.regenerated')->count());
    }

    public function test_public_folder_browser_is_relative_and_enforces_subtree_for_child_streams(): void
    {
        $owner = User::factory()->create();
        $root = Folder::factory()->create(['owner_id' => $owner->id, 'name' => 'Projects']);
        $child = Folder::factory()->create(['owner_id' => $owner->id, 'parent_id' => $root->id, 'name' => '2026']);
        $sibling = Folder::factory()->create(['owner_id' => $owner->id, 'name' => 'Private']);
        $inside = $this->storedFile($owner, 'inside.txt', 'inside', 'text/plain', ['folder_id' => $child->id]);
        $outside = $this->storedFile($owner, 'outside.txt', 'outside', 'text/plain', ['folder_id' => $sibling->id]);
        $this->actingAs($owner, 'sanctum');
        $token = basename($this->postJson('/api/folders/'.$root->uuid.'/public-link')->json('data.url'));

        $this->getJson('/api/public/shares/'.$token.'/browser')->assertOk()
            ->assertJsonPath('data.breadcrumb.0.name', 'Projects')
            ->assertJsonPath('data.folders.0.name', '2026')
            ->assertJsonMissing(['name' => 'Private']);
        $this->getJson('/api/public/shares/'.$token.'/browser?folderId='.$child->uuid)->assertOk()->assertJsonPath('data.breadcrumb.1.name', '2026');
        $this->get('/api/public/shares/'.$token.'/files/'.$inside->uuid.'/download')->assertOk()->assertStreamedContent('inside');
        $this->getJson('/api/public/shares/'.$token.'/browser?folderId='.$sibling->uuid)->assertNotFound();
        $this->get('/api/public/shares/'.$token.'/files/'.$outside->uuid.'/download')->assertNotFound();
    }

    public function test_trash_suspends_public_access_restore_resumes_and_delete_removes_link(): void
    {
        $owner = User::factory()->create(['used_bytes' => 4]);
        $file = $this->storedFile($owner, 'lifecycle.txt', 'data', 'text/plain');
        $this->actingAs($owner, 'sanctum');
        $token = basename($this->postJson('/api/files/'.$file->uuid.'/public-link')->json('data.url'));
        $this->postJson('/api/files/'.$file->uuid.'/trash')->assertOk();
        $this->getJson('/api/public/shares/'.$token)->assertNotFound();
        $this->postJson('/api/files/'.$file->uuid.'/restore')->assertOk();
        $this->getJson('/api/public/shares/'.$token)->assertOk();
        $this->postJson('/api/files/'.$file->uuid.'/trash')->assertOk();
        $this->deleteJson('/api/files/'.$file->uuid.'/permanent')->assertOk();
        $this->assertDatabaseCount('public_share_links', 0);
        $this->getJson('/api/public/shares/'.$token)->assertNotFound();
    }

    public function test_public_link_management_is_owner_only_and_internal_share_is_independent(): void
    {
        $owner = User::factory()->create();
        $recipient = User::factory()->create();
        $file = $this->storedFile($owner, 'independent.txt', 'data', 'text/plain');
        $this->actingAs($owner, 'sanctum');
        $this->postJson('/api/files/'.$file->uuid.'/shares', ['recipientId' => $recipient->id, 'permission' => 'viewer'])->assertCreated();
        $token = basename($this->postJson('/api/files/'.$file->uuid.'/public-link')->json('data.url'));
        $this->actingAs($recipient, 'sanctum');
        $this->deleteJson('/api/files/'.$file->uuid.'/public-link')->assertNotFound();
        $this->actingAs($owner, 'sanctum');
        $this->deleteJson('/api/files/'.$file->uuid.'/public-link')->assertOk();
        $this->actingAs($recipient, 'sanctum');
        $this->getJson('/api/files/'.$file->uuid)->assertOk();
        $this->getJson('/api/public/shares/'.$token)->assertNotFound();
    }

    private function storedFile(User $owner, string $name, string $contents, string $mimeType, array $attributes = []): File
    {
        $file = File::factory()->create(array_merge([
            'owner_id' => $owner->id,
            'original_name' => $name,
            'extension' => pathinfo($name, PATHINFO_EXTENSION),
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'disk' => 'cloud',
            'path' => 'users/'.$owner->id.'/files/'.str()->uuid(),
        ], $attributes));
        Storage::disk('cloud')->put($file->path, $contents);

        return $file;
    }
}
