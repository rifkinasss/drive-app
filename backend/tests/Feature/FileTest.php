<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Origin', 'http://localhost');
        Storage::fake('cloud');
    }

    public function test_root_and_folder_file_lists_return_only_active_owned_files(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $user->id]);
        File::factory()->create(['owner_id' => $user->id, 'original_name' => 'root.pdf']);
        File::factory()->create(['owner_id' => $user->id, 'folder_id' => $folder->id, 'original_name' => 'nested.pdf']);
        File::factory()->trashed()->create(['owner_id' => $user->id, 'original_name' => 'deleted.pdf']);

        $this->actingAs($user, 'sanctum');
        $this->getJson('/api/files')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'root.pdf');
        $this->getJson('/api/files?folderId='.$folder->uuid)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'nested.pdf');
    }

    public function test_file_response_uses_uuid_and_hides_internal_fields(): void
    {
        $user = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $user->id, 'name' => 'Reports']);
        $file = File::factory()->create(['owner_id' => $user->id, 'folder_id' => $folder->id, 'is_starred' => true]);
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/files/'.$file->uuid.'/details')->assertOk();
        $response->assertJsonPath('data.id', $file->uuid)
            ->assertJsonPath('data.type', 'file')
            ->assertJsonPath('data.extension', 'pdf')
            ->assertJsonPath('data.sizeBytes', $file->size_bytes)
            ->assertJsonPath('data.owner.id', $user->id)
            ->assertJsonPath('data.location.id', $folder->uuid)
            ->assertJsonPath('data.location.name', 'Reports')
            ->assertJsonPath('data.starred', true)
            ->assertJsonPath('data.shared', false)
            ->assertJsonMissingPath('data.stored_name')
            ->assertJsonMissingPath('data.owner_id')
            ->assertJsonMissingPath('data.folder_id');
        $this->assertNotSame((string) $file->id, $response->json('data.id'));
    }

    public function test_rename_updates_display_name_and_extension_but_not_physical_identity_or_mime(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->create([
            'owner_id' => $user->id,
            'original_name' => 'report.pdf',
            'extension' => 'pdf',
            'mime_type' => 'application/pdf',
            'stored_name' => 'stable.bin',
        ]);
        $this->actingAs($user, 'sanctum');

        $this->patchJson('/api/files/'.$file->uuid, ['name' => ' report-final.pdf '])
            ->assertOk()
            ->assertJsonPath('data.name', 'report-final.pdf')
            ->assertJsonPath('data.extension', 'pdf')
            ->assertJsonPath('data.mimeType', 'application/pdf');
        $this->assertSame('stable.bin', $file->fresh()->stored_name);

        $this->patchJson('/api/files/'.$file->uuid, ['name' => 'report-final'])
            ->assertOk()
            ->assertJsonPath('data.extension', null)
            ->assertJsonPath('data.mimeType', 'application/pdf');
    }

    public function test_file_names_are_unique_per_owner_and_folder_but_not_across_folders_or_owners(): void
    {
        $user = User::factory()->create();
        $first = Folder::factory()->create(['owner_id' => $user->id]);
        $second = Folder::factory()->create(['owner_id' => $user->id]);
        File::factory()->create(['owner_id' => $user->id, 'folder_id' => $first->id, 'original_name' => 'Report.pdf']);
        $conflictingFile = File::factory()->create(['owner_id' => $user->id, 'folder_id' => $first->id, 'original_name' => 'draft.pdf']);
        $otherFolderFile = File::factory()->create(['owner_id' => $user->id, 'folder_id' => $second->id, 'original_name' => 'report.pdf']);
        $otherUser = User::factory()->create();
        File::factory()->create(['owner_id' => $otherUser->id, 'original_name' => 'report.pdf']);
        $this->actingAs($user, 'sanctum');

        $this->patchJson('/api/files/'.$otherFolderFile->uuid, ['name' => 'REPORT.PDF'])->assertOk();
        $this->patchJson('/api/files/'.$conflictingFile->uuid, ['name' => 'report.pdf'])->assertUnprocessable();
    }

    public function test_move_and_star_are_owner_only_and_move_rejects_invalid_targets(): void
    {
        $owner = User::factory()->create();
        $target = Folder::factory()->create(['owner_id' => $owner->id]);
        $file = File::factory()->create(['owner_id' => $owner->id]);
        $trashed = Folder::factory()->create(['owner_id' => $owner->id, 'trashed_at' => now()]);
        $this->actingAs($owner, 'sanctum');

        $this->postJson('/api/files/'.$file->uuid.'/move', ['folderId' => $target->uuid])
            ->assertOk()
            ->assertJsonPath('data.folderId', $target->uuid);
        $this->postJson('/api/files/'.$file->uuid.'/move', ['folderId' => null])->assertOk()->assertJsonPath('data.folderId', null);
        $this->postJson('/api/files/'.$file->uuid.'/move', ['folderId' => $trashed->uuid])->assertUnprocessable();
        $this->postJson('/api/files/'.$file->uuid.'/star')->assertOk()->assertJsonPath('data.starred', true);
        $this->postJson('/api/files/'.$file->uuid.'/star')->assertOk()->assertJsonPath('data.starred', true);
        $this->deleteJson('/api/files/'.$file->uuid.'/star')->assertOk()->assertJsonPath('data.starred', false);
        $this->deleteJson('/api/files/'.$file->uuid.'/star')->assertOk()->assertJsonPath('data.starred', false);

        $other = User::factory()->create();
        $this->actingAs($other, 'sanctum');
        $this->getJson('/api/files/'.$file->uuid)->assertNotFound();
        $this->postJson('/api/files/'.$file->uuid.'/star')->assertNotFound();
        $this->postJson('/api/files/'.$file->uuid.'/move', ['folderId' => null])->assertNotFound();

        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->actingAs($admin, 'sanctum');
        $this->getJson('/api/files/'.$file->uuid)->assertNotFound();
    }

    public function test_file_name_validation_and_folder_owner_integrity_are_enforced(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherFolder = Folder::factory()->create(['owner_id' => $other->id]);
        $file = File::factory()->create(['owner_id' => $user->id]);
        $this->actingAs($user, 'sanctum');

        foreach (['   ', '.', '..', 'with/slash', 'with\\slash'] as $name) {
            $this->patchJson('/api/files/'.$file->uuid, ['name' => $name])->assertUnprocessable();
        }
        $this->postJson('/api/files/'.$file->uuid.'/move', ['folderId' => $otherFolder->uuid])->assertUnprocessable();
    }

    public function test_pending_and_disabled_users_cannot_use_file_api(): void
    {
        foreach ([UserStatus::Pending, UserStatus::Disabled] as $status) {
            $user = User::factory()->create();
            $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'password'])->assertOk();
            $user->update(['status' => $status]);
            Auth::forgetGuards();
            $this->getJson('/api/files')->assertForbidden();
        }
    }

    public function test_upload_stores_bytes_and_server_derived_metadata(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $contents = 'known upload contents';

        $response = $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('My Report.PDF', $contents),
        ])->assertCreated();

        $file = File::query()->where('uuid', $response->json('data.id'))->firstOrFail();
        $this->assertSame('My Report.PDF', $file->original_name);
        $this->assertSame('pdf', $file->extension);
        $this->assertSame(strlen($contents), $file->size_bytes);
        $this->assertSame(hash('sha256', $contents), $file->checksum);
        $this->assertSame('cloud', $file->disk);
        $this->assertStringNotContainsString('My Report.PDF', $file->path);
        $this->assertStringNotContainsString('My Report.PDF', $file->stored_name);
        Storage::disk('cloud')->assertExists($file->path);
        $this->assertArrayNotHasKey('path', $response->json('data'));
        $this->assertArrayNotHasKey('stored_name', $response->json('data'));
    }

    public function test_upload_validates_owned_folder_and_rejects_trashed_or_other_user_folders(): void
    {
        $owner = User::factory()->create();
        $folder = Folder::factory()->create(['owner_id' => $owner->id]);
        $other = User::factory()->create();
        $otherFolder = Folder::factory()->create(['owner_id' => $other->id]);
        $this->actingAs($owner, 'sanctum');

        $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('nested.txt', 'nested'),
            'folderId' => $folder->uuid,
        ])->assertCreated();
        $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('private.txt', 'private'),
            'folderId' => $otherFolder->uuid,
        ])->assertUnprocessable();
    }

    public function test_duplicate_upload_supports_conflict_keep_both_and_replace(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $this->post('/api/files/upload', ['file' => UploadedFile::fake()->createWithContent('report.txt', 'one')])->assertCreated();

        $this->post('/api/files/upload', ['file' => UploadedFile::fake()->createWithContent('REPORT.txt', 'two')])
            ->assertStatus(409)
            ->assertJsonPath('code', 'FILE_NAME_CONFLICT');
        $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('report.txt', 'two'),
            'conflictStrategy' => 'keep_both',
        ])->assertCreated()->assertJsonPath('data.name', 'report (1).txt');
        $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('report.txt', 'three'),
            'conflictStrategy' => 'keep_both',
        ])->assertCreated()->assertJsonPath('data.name', 'report (2).txt');

        $original = File::query()->where('original_name', 'report.txt')->firstOrFail();
        $oldUuid = $original->uuid;
        $oldPath = $original->path;
        $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('REPORT.txt', 'replacement'),
            'conflictStrategy' => 'replace',
        ])->assertCreated()->assertJsonPath('data.id', $oldUuid);
        $this->assertSame($oldPath, $original->fresh()->path);
        $this->assertSame(hash('sha256', 'replacement'), $original->fresh()->checksum);
        $this->assertSame('replacement', Storage::disk('cloud')->get($oldPath));
    }

    public function test_upload_rejects_blocked_extensions_and_size_and_sanitizes_unsafe_names(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $blocked = $this->post('/api/files/upload', ['file' => UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')]);
        $this->assertSame(422, $blocked->status(), $blocked->getContent());
        $unsafe = $this->post('/api/files/upload', ['file' => UploadedFile::fake()->createWithContent('../escape.txt', 'escape')])->assertCreated();
        $this->assertSame('escape.txt', $unsafe->json('data.name'));
        config(['cloud.max_upload_size_bytes' => 4]);
        $large = $this->post('/api/files/upload', ['file' => UploadedFile::fake()->createWithContent('large.txt', '12345')]);
        $this->assertSame(422, $large->status(), $large->getContent());
        $this->assertDatabaseCount('files', 1);
        $this->assertCount(1, Storage::disk('cloud')->allFiles());
    }

    public function test_owner_can_download_private_bytes_with_attachment_headers(): void
    {
        $user = User::factory()->create();
        $file = $this->storedFile($user, 'Laporan Final.pdf', 'pdf bytes', 'application/pdf');
        $this->actingAs($user, 'sanctum');

        $response = $this->get('/api/files/'.$file->uuid.'/download')->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertSame('pdf bytes', $response->streamedContent());
    }

    public function test_preview_allows_safe_mime_types_and_rejects_active_content(): void
    {
        $user = User::factory()->create();
        $pdf = $this->storedFile($user, 'document.pdf', 'pdf bytes', 'application/pdf');
        $html = $this->storedFile($user, 'page.html', '<script>alert(1)</script>', 'text/html');
        $svg = $this->storedFile($user, 'image.svg', '<svg></svg>', 'image/svg+xml');
        $this->actingAs($user, 'sanctum');

        $this->get('/api/files/'.$pdf->uuid.'/preview')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename=document.pdf')
            ->assertStreamedContent('pdf bytes');
        $this->get('/api/files/'.$html->uuid.'/preview')->assertUnsupportedMediaType();
        $this->get('/api/files/'.$svg->uuid.'/preview')->assertUnsupportedMediaType();
        $this->get('/api/files/'.$html->uuid.'/download')->assertOk()->assertStreamedContent('<script>alert(1)</script>');
    }

    public function test_single_byte_ranges_are_streamed_without_loading_full_file(): void
    {
        $user = User::factory()->create();
        $file = $this->storedFile($user, 'video.mp4', '0123456789', 'video/mp4');
        $this->actingAs($user, 'sanctum');

        $this->withHeaders(['Range' => 'bytes=0-3'])
            ->get('/api/files/'.$file->uuid.'/preview')
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-3/10')
            ->assertHeader('Content-Length', '4')
            ->assertStreamedContent('0123');
        $this->withHeaders(['Range' => 'bytes=6-'])
            ->get('/api/files/'.$file->uuid.'/download')
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 6-9/10')
            ->assertStreamedContent('6789');
        $this->withHeaders(['Range' => 'bytes=99-'])
            ->get('/api/files/'.$file->uuid.'/download')
            ->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */10');
    }

    public function test_other_users_admin_pending_disabled_and_trashed_files_cannot_stream(): void
    {
        $owner = User::factory()->create();
        $file = $this->storedFile($owner, 'private.txt', 'private', 'text/plain');
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->get('/api/files/'.$file->uuid.'/download')->assertNotFound();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum');
        $this->get('/api/files/'.$file->uuid.'/preview')->assertNotFound();

        $trashed = $this->storedFile($owner, 'trashed.txt', 'trashed', 'text/plain', ['trashed_at' => now()]);
        $this->actingAs($owner, 'sanctum');
        $this->get('/api/files/'.$trashed->uuid.'/download')->assertNotFound();
        $this->get('/api/files/'.$trashed->uuid.'/preview')->assertNotFound();
    }

    public function test_missing_physical_file_returns_controlled_not_found(): void
    {
        $user = User::factory()->create();
        $file = $this->storedFile($user, 'missing.txt', 'will be removed', 'text/plain');
        Storage::disk('cloud')->delete($file->path);
        $this->actingAs($user, 'sanctum');

        $this->get('/api/files/'.$file->uuid.'/download')->assertNotFound();
    }

    public function test_download_filename_reflects_renames_and_supports_utf8_safely(): void
    {
        $user = User::factory()->create();
        $file = $this->storedFile($user, 'old.txt', 'content', 'text/plain');
        $this->actingAs($user, 'sanctum');
        $this->patchJson('/api/files/'.$file->uuid, ['name' => 'Laporan Akhir 2026_日本語.txt'])->assertOk();

        $response = $this->get('/api/files/'.$file->uuid.'/download')->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('filename*=utf-8\'\'Laporan%20Akhir%202026_', $response->headers->get('Content-Disposition'));
        $this->assertSame($file->path, $file->fresh()->path);
    }

    public function test_storage_endpoint_reports_quota_used_available_and_trash_bytes(): void
    {
        $user = User::factory()->create(['quota_bytes' => 100, 'used_bytes' => 0]);
        $active = $this->storedFile($user, 'active.txt', '12345', 'text/plain');
        $this->storedFile($user, 'trashed.txt', '123', 'text/plain', ['trashed_at' => now()]);
        $user->update(['used_bytes' => 8]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/storage')
            ->assertOk()
            ->assertJsonPath('data.quotaBytes', 100)
            ->assertJsonPath('data.usedBytes', 8)
            ->assertJsonPath('data.availableBytes', 92)
            ->assertJsonPath('data.trashBytes', 3)
            ->assertJsonPath('data.usagePercentage', 8)
            ->assertJsonPath('data.categories.Documents', 5)
            ->assertJsonPath('data.categories.Trash', 3)
            ->assertJsonPath('data.largestFiles.0.id', $active->uuid);
    }

    public function test_storage_endpoint_reports_owner_cleanup_candidates_without_trash(): void
    {
        $user = User::factory()->create(['quota_bytes' => 1_000_000_000, 'used_bytes' => 0]);
        $other = User::factory()->create();
        $large = File::factory()->create(['owner_id' => $user->id, 'original_name' => 'large.bin', 'size_bytes' => 100 * 1024 * 1024, 'checksum' => str_repeat('a', 64)]);
        $old = File::factory()->create(['owner_id' => $user->id, 'original_name' => 'old.pdf', 'size_bytes' => 20, 'updated_at' => now()->subDays(181)]);
        File::factory()->create(['owner_id' => $user->id, 'original_name' => 'duplicate.pdf', 'size_bytes' => 20, 'checksum' => str_repeat('b', 64)]);
        File::factory()->create(['owner_id' => $user->id, 'original_name' => 'duplicate-copy.pdf', 'size_bytes' => 20, 'checksum' => str_repeat('b', 64)]);
        File::factory()->trashed()->create(['owner_id' => $user->id, 'original_name' => 'deleted.pdf', 'size_bytes' => 30]);
        File::factory()->create(['owner_id' => $other->id, 'original_name' => 'other-large.bin', 'size_bytes' => 200 * 1024 * 1024, 'checksum' => str_repeat('b', 64)]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/storage')->assertOk()
            ->assertJsonPath('data.trashCount', 1)
            ->assertJsonPath('data.cleanup.oldDays', 180)
            ->assertJsonPath('data.cleanup.largeMinBytes', 104857600)
            ->assertJsonPath('data.cleanup.largeCount', 1)
            ->assertJsonPath('data.cleanup.largeBytes', 104857600)
            ->assertJsonPath('data.cleanup.oldCount', 1)
            ->assertJsonPath('data.cleanup.oldFiles.0.id', $old->uuid)
            ->assertJsonPath('data.cleanup.largeFiles.0.id', $large->uuid)
            ->assertJsonPath('data.cleanup.duplicateGroups.0.fileCount', 2)
            ->assertJsonPath('data.cleanup.duplicateGroups.0.reclaimableBytes', 20)
            ->assertJsonCount(2, 'data.cleanup.duplicateGroups.0.files');
    }

    public function test_storage_cleanup_excludes_shared_with_me_and_trashed_files(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        File::factory()->create(['owner_id' => $other->id, 'size_bytes' => 100 * 1024 * 1024, 'checksum' => str_repeat('c', 64)]);
        File::factory()->trashed()->create(['owner_id' => $user->id, 'size_bytes' => 100 * 1024 * 1024]);
        $this->actingAs($user, 'sanctum');

        $this->getJson('/api/storage')->assertOk()
            ->assertJsonPath('data.cleanup.largeCount', 0)
            ->assertJsonPath('data.cleanup.oldCount', 0)
            ->assertJsonCount(0, 'data.cleanup.duplicateGroups');
    }

    public function test_upload_accounting_and_non_storage_mutations(): void
    {
        $user = User::factory()->create(['quota_bytes' => 100, 'used_bytes' => 0]);
        $this->actingAs($user, 'sanctum');
        $response = $this->post('/api/files/upload', ['file' => UploadedFile::fake()->createWithContent('accounted.txt', '12345')])->assertCreated();
        $fileId = $response->json('data.id');

        $this->assertSame(5, $user->fresh()->used_bytes);
        $this->patchJson('/api/files/'.$fileId, ['name' => 'renamed.txt'])->assertOk();
        $this->postJson('/api/files/'.$fileId.'/star')->assertOk();
        $this->assertSame(5, $user->fresh()->used_bytes);
    }

    public function test_quota_exceeded_rejects_upload_without_metadata_or_physical_bytes(): void
    {
        $user = User::factory()->create(['quota_bytes' => 5, 'used_bytes' => 0]);
        $this->actingAs($user, 'sanctum');

        $this->post('/api/files/upload', ['file' => UploadedFile::fake()->createWithContent('too-large.txt', '123456')])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'STORAGE_QUOTA_EXCEEDED')
            ->assertJsonPath('data.quotaBytes', 5)
            ->assertJsonPath('data.usedBytes', 0)
            ->assertJsonPath('data.requiredBytes', 6);
        $this->assertDatabaseCount('files', 0);
        $this->assertSame(0, $user->fresh()->used_bytes);
        $this->assertSame([], Storage::disk('cloud')->allFiles());
    }

    public function test_keep_both_adds_full_size_and_replace_applies_size_delta(): void
    {
        $user = User::factory()->create(['quota_bytes' => 20, 'used_bytes' => 0]);
        $this->actingAs($user, 'sanctum');
        $this->post('/api/files/upload', ['file' => UploadedFile::fake()->createWithContent('same.txt', '12345')])->assertCreated();
        $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('same.txt', '12'),
            'conflictStrategy' => 'keep_both',
        ])->assertCreated();
        $this->assertSame(7, $user->fresh()->used_bytes);

        $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('same.txt', '1234567890'),
            'conflictStrategy' => 'replace',
        ])->assertCreated();
        $this->assertSame(12, $user->fresh()->used_bytes);

        $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('same.txt', '1'),
            'conflictStrategy' => 'replace',
        ])->assertCreated();
        $this->assertSame(3, $user->fresh()->used_bytes);
    }

    public function test_replace_smaller_file_is_allowed_for_over_quota_user(): void
    {
        $user = User::factory()->create(['quota_bytes' => 5, 'used_bytes' => 0]);
        $this->actingAs($user, 'sanctum');
        $this->post('/api/files/upload', ['file' => UploadedFile::fake()->createWithContent('over.txt', '12345')])->assertCreated();
        $user->update(['used_bytes' => 10]);

        $this->post('/api/files/upload', [
            'file' => UploadedFile::fake()->createWithContent('over.txt', '1'),
            'conflictStrategy' => 'replace',
        ])->assertCreated();
        $this->assertSame(6, $user->fresh()->used_bytes);
    }

    public function test_reconciliation_restores_usage_and_dry_run_does_not_mutate(): void
    {
        $user = User::factory()->create(['quota_bytes' => 100, 'used_bytes' => 99]);
        File::factory()->create(['owner_id' => $user->id, 'size_bytes' => 7]);

        $this->artisan('cloud:storage-reconcile', ['--user' => $user->email, '--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('difference=-92');
        $this->assertSame(99, $user->fresh()->used_bytes);

        $this->artisan('cloud:storage-reconcile', ['--user' => $user->email])->assertSuccessful();
        $this->assertSame(7, $user->fresh()->used_bytes);
    }

    public function test_file_trash_restore_preserves_bytes_location_and_quota(): void
    {
        $user = User::factory()->create(['quota_bytes' => 100, 'used_bytes' => 5]);
        $folder = Folder::factory()->create(['owner_id' => $user->id]);
        $file = $this->storedFile($user, 'recover.txt', '12345', 'text/plain', ['folder_id' => $folder->id]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/files/'.$file->uuid.'/trash')->assertOk();
        $trashed = $file->fresh();
        $this->assertNotNull($trashed->trashed_at);
        $this->assertSame(5, $user->fresh()->used_bytes);
        Storage::disk('cloud')->assertExists($file->path);
        $this->getJson('/api/files?folderId='.$folder->uuid)->assertOk()->assertJsonCount(0, 'data');

        $this->postJson('/api/files/'.$file->uuid.'/restore')->assertOk()->assertJsonPath('data.folderId', $folder->uuid);
        $this->assertNull($file->fresh()->trashed_at);
        $this->assertSame(5, $user->fresh()->used_bytes);
        Storage::disk('cloud')->assertExists($file->path);
    }

    public function test_file_restore_conflict_returns_409_and_keep_both_renames(): void
    {
        $user = User::factory()->create();
        $active = $this->storedFile($user, 'report.txt', 'active', 'text/plain');
        $trashed = $this->storedFile($user, 'report.txt', 'old', 'text/plain', ['trashed_at' => now(), 'trash_batch_id' => str()->uuid()]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/files/'.$trashed->uuid.'/restore')->assertConflict()->assertJsonPath('code', 'RESTORE_CONFLICT');
        $this->postJson('/api/files/'.$trashed->uuid.'/restore', ['conflictStrategy' => 'keep_both'])
            ->assertOk()
            ->assertJsonPath('data.name', 'report (1).txt');
        $this->assertNull($trashed->fresh()->trashed_at);
        $this->assertSame('active', Storage::disk('cloud')->get($active->path));
    }

    public function test_permanent_file_delete_removes_bytes_metadata_and_quota(): void
    {
        $user = User::factory()->create(['quota_bytes' => 100, 'used_bytes' => 5]);
        $file = $this->storedFile($user, 'delete.txt', '12345', 'text/plain', ['trashed_at' => now(), 'trash_batch_id' => str()->uuid()]);
        $this->actingAs($user, 'sanctum');

        $this->deleteJson('/api/files/'.$file->uuid.'/permanent')
            ->assertOk()
            ->assertJsonPath('data.freedBytes', 5);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertSame(0, $user->fresh()->used_bytes);
        Storage::disk('cloud')->assertMissing($file->path);
    }

    public function test_recursive_folder_trash_restore_preserves_independent_child_trash(): void
    {
        $user = User::factory()->create(['quota_bytes' => 100, 'used_bytes' => 10]);
        $root = Folder::factory()->create(['owner_id' => $user->id, 'name' => 'Projects']);
        $independent = Folder::factory()->create(['owner_id' => $user->id, 'parent_id' => $root->id, 'name' => 'Independent']);
        $nested = Folder::factory()->create(['owner_id' => $user->id, 'parent_id' => $root->id, 'name' => 'Nested']);
        $rootFile = $this->storedFile($user, 'root.txt', '12345', 'text/plain', ['folder_id' => $root->id]);
        $nestedFile = $this->storedFile($user, 'nested.txt', '12345', 'text/plain', ['folder_id' => $nested->id]);
        $this->actingAs($user, 'sanctum');

        $this->postJson('/api/folders/'.$independent->uuid.'/trash')->assertOk();
        $independentBatch = $independent->fresh()->trash_batch_id;
        $this->postJson('/api/folders/'.$root->uuid.'/trash')->assertOk();
        $this->assertNotNull($root->fresh()->trashed_at);
        $this->assertNotNull($rootFile->fresh()->trashed_at);
        $this->assertNotNull($nested->fresh()->trashed_at);
        $this->assertNotNull($nestedFile->fresh()->trashed_at);
        $this->assertSame($independentBatch, $independent->fresh()->trash_batch_id);
        $this->assertSame(10, $user->fresh()->used_bytes);

        $this->postJson('/api/folders/'.$root->uuid.'/restore')->assertOk();
        $this->assertNull($root->fresh()->trashed_at);
        $this->assertNull($nested->fresh()->trashed_at);
        $this->assertNull($nestedFile->fresh()->trashed_at);
        $this->assertNotNull($independent->fresh()->trashed_at);
        $this->assertNotNull($independent->fresh()->trash_batch_id);
    }

    public function test_folder_permanent_delete_and_empty_trash_decrement_quota(): void
    {
        $user = User::factory()->create(['quota_bytes' => 100, 'used_bytes' => 8]);
        $folder = Folder::factory()->create(['owner_id' => $user->id, 'name' => 'Old']);
        $file = $this->storedFile($user, 'old.txt', '12345678', 'text/plain', ['folder_id' => $folder->id]);
        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/folders/'.$folder->uuid.'/trash')->assertOk();

        $this->getJson('/api/trash')->assertOk()->assertJsonPath('data.0.type', 'folder');
        $this->delete('/api/trash')->assertOk()->assertJsonPath('data.freedBytes', 8);
        $this->assertDatabaseMissing('folders', ['id' => $folder->id]);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertSame(0, $user->fresh()->used_bytes);
        Storage::disk('cloud')->assertMissing($file->path);
    }

    private function storedFile(User $owner, string $name, string $contents, string $mimeType, array $attributes = []): File
    {
        $file = File::factory()->create(array_merge([
            'owner_id' => $owner->id,
            'original_name' => $name,
            'extension' => pathinfo($name, PATHINFO_EXTENSION),
            'mime_type' => $mimeType,
            'size_bytes' => strlen($contents),
            'checksum' => hash('sha256', $contents),
            'disk' => 'cloud',
            'path' => 'users/'.$owner->id.'/files/'.str()->uuid(),
        ], $attributes));
        Storage::disk('cloud')->put($file->path, $contents);

        return $file;
    }
}
