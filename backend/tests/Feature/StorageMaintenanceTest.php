<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Folder;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('cloud');
        Cache::flush();
        SystemSetting::query()->create(['key' => 'storage.trash_retention_days', 'group' => 'storage', 'type' => 'integer', 'value' => 30]);
    }

    public function test_trash_cleanup_is_dry_run_by_default_and_execute_releases_quota_once(): void
    {
        $user = User::factory()->create(['used_bytes' => 5]);
        Storage::disk('cloud')->put('users/'.$user->id.'/files/expired', '12345');
        $file = File::factory()->create([
            'owner_id' => $user->id,
            'path' => 'users/'.$user->id.'/files/expired',
            'disk' => 'cloud',
            'size_bytes' => 5,
            'trashed_at' => now()->subDays(31),
            'trash_batch_id' => (string) str()->uuid(),
        ]);

        Artisan::call('cloud:trash-cleanup');
        $this->assertDatabaseHas('files', ['id' => $file->id]);
        $this->assertSame(5, (int) $user->fresh()->used_bytes);
        Storage::disk('cloud')->assertExists($file->path);

        Artisan::call('cloud:trash-cleanup', ['--execute' => true]);
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertSame(0, (int) $user->fresh()->used_bytes);
        Storage::disk('cloud')->assertMissing($file->path);

        Artisan::call('cloud:trash-cleanup', ['--execute' => true]);
        $this->assertSame(0, (int) $user->fresh()->used_bytes);
    }

    public function test_upload_staging_cleanup_preserves_young_data_and_only_audits_orphans(): void
    {
        Storage::disk('cloud')->put('tmp/young', 'a');
        Storage::disk('cloud')->put('tmp/old', 'ab');
        Storage::disk('cloud')->put('tmp-delete-committed/committed-old', 'abc');
        Storage::disk('cloud')->put('tmp-delete-pending/recovery-candidate', 'abcd');
        touch(Storage::disk('cloud')->path('tmp/old'), now()->subHours(30)->timestamp);
        touch(Storage::disk('cloud')->path('tmp-delete-committed/committed-old'), now()->subHours(30)->timestamp);
        touch(Storage::disk('cloud')->path('tmp-delete-pending/recovery-candidate'), now()->subHours(30)->timestamp);

        Artisan::call('cloud:cleanup-upload-staging', ['--execute' => true]);
        Storage::disk('cloud')->assertExists('tmp/young');
        Storage::disk('cloud')->assertMissing('tmp/old');
        Artisan::call('cloud:cleanup-delete-staging', ['--execute' => true]);
        Storage::disk('cloud')->assertMissing('tmp-delete-committed/committed-old');
        Storage::disk('cloud')->assertExists('tmp-delete-pending/recovery-candidate');

        Storage::disk('cloud')->put('users/999/files/orphan', 'xyz');
        Artisan::call('cloud:storage-audit');
        Storage::disk('cloud')->assertExists('users/999/files/orphan');
    }

    public function test_non_expired_trash_is_preserved(): void
    {
        $user = User::factory()->create(['used_bytes' => 4]);
        $file = File::factory()->trashed()->create([
            'owner_id' => $user->id,
            'size_bytes' => 4,
            'trashed_at' => now()->subDays(29),
            'trash_batch_id' => (string) str()->uuid(),
        ]);

        Artisan::call('cloud:trash-cleanup', ['--execute' => true]);

        $this->assertDatabaseHas('files', ['id' => $file->id]);
        $this->assertSame(4, (int) $user->fresh()->used_bytes);
    }

    public function test_expired_folder_cleanup_preserves_independently_trashed_descendant_batch(): void
    {
        $user = User::factory()->create(['used_bytes' => 9]);
        $rootBatch = (string) str()->uuid();
        $independentBatch = (string) str()->uuid();
        $root = Folder::factory()->create(['owner_id' => $user->id, 'trashed_at' => now()->subDays(31), 'trash_batch_id' => $rootBatch]);
        $independent = Folder::factory()->create(['owner_id' => $user->id, 'parent_id' => $root->id, 'trashed_at' => now()->subDays(40), 'trash_batch_id' => $independentBatch]);
        Storage::disk('cloud')->put('users/'.$user->id.'/files/root', '12345');
        Storage::disk('cloud')->put('users/'.$user->id.'/files/independent', '6789');
        $rootFile = File::factory()->create(['owner_id' => $user->id, 'folder_id' => $root->id, 'disk' => 'cloud', 'path' => 'users/'.$user->id.'/files/root', 'size_bytes' => 5, 'trashed_at' => now()->subDays(31), 'trash_batch_id' => $rootBatch]);
        $independentFile = File::factory()->create(['owner_id' => $user->id, 'folder_id' => $independent->id, 'disk' => 'cloud', 'path' => 'users/'.$user->id.'/files/independent', 'size_bytes' => 4, 'trashed_at' => now()->subDays(40), 'trash_batch_id' => $independentBatch]);

        Artisan::call('cloud:trash-cleanup', ['--execute' => true]);

        $this->assertDatabaseMissing('folders', ['id' => $root->id]);
        $this->assertDatabaseHas('folders', ['id' => $independent->id]);
        $this->assertDatabaseMissing('files', ['id' => $rootFile->id]);
        $this->assertDatabaseHas('files', ['id' => $independentFile->id]);
        $this->assertSame(4, (int) $user->fresh()->used_bytes);
        Storage::disk('cloud')->assertMissing('users/'.$user->id.'/files/root');
        Storage::disk('cloud')->assertExists('users/'.$user->id.'/files/independent');
    }

    public function test_scheduler_registers_maintenance_commands(): void
    {
        $commands = collect(app(Schedule::class)->events())->pluck('command')->implode("\n");
        $this->assertStringContainsString('cloud:trash-cleanup', $commands);
        $this->assertStringContainsString('cloud:cleanup-upload-staging', $commands);
        $this->assertStringContainsString('cloud:cleanup-delete-staging', $commands);
    }
}
