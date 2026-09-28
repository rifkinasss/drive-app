<?php

namespace Tests\Feature;

use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
        Storage::fake('cloud');
    }

    public function test_owner_can_create_and_public_visitor_can_upload_without_listing_folder(): void
    {
        $owner = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $owner->id, 'name' => 'Incoming']);
        $this->actingAs($owner, 'sanctum');
        $created = $this->postJson('/api/file-requests', ['title' => 'Send documents', 'folderId' => $folder->uuid, 'expiration' => '7d'])->assertCreated();
        $token = basename($created->json('data.url'));

        $this->getJson('/api/public/file-requests/'.$token)->assertOk()->assertJsonPath('data.title', 'Send documents')->assertJsonMissingPath('data.folderId');
        $this->post('/api/public/file-requests/'.$token.'/upload', ['file' => UploadedFile::fake()->createWithContent('secret.txt', 'private upload')])->assertCreated()->assertJsonPath('data.uploaded', true);
        $this->assertDatabaseHas('files', ['owner_id' => $owner->id, 'folder_id' => $folder->id, 'original_name' => 'secret.txt']);
        $this->getJson('/api/public/file-requests/'.$token.'/browser')->assertNotFound();
    }

    public function test_disabled_file_request_rejects_public_upload(): void
    {
        $owner = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $owner->id]);
        $this->actingAs($owner, 'sanctum');
        $created = $this->postJson('/api/file-requests', ['title' => 'Upload', 'folderId' => $folder->uuid]);
        $id = $created->json('data.id');
        $token = basename($created->json('data.url'));
        $this->patchJson('/api/file-requests/'.$id, ['enabled' => false])->assertOk();
        $this->getJson('/api/public/file-requests/'.$token)->assertNotFound();
    }
}
