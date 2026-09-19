<?php

namespace App\Console\Commands;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Services\FileTrashService;
use App\Services\SystemSettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class CleanupTrash extends Command
{
    protected $signature = 'cloud:trash-cleanup {--dry-run : Report eligible trash without deleting it} {--execute : Permanently delete eligible trash} {--user= : Restrict to a user UUID or email} {--limit=500 : Maximum total roots to inspect}';

    protected $description = 'Permanently remove expired Trash roots (dry-run unless --execute is supplied).';

    public function handle(SystemSettingService $settings, FileTrashService $trash): int
    {
        if ($this->option('execute') && $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --execute.');

            return self::INVALID;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        if ($limit === false) {
            $this->error('--limit must be between 1 and 10000.');

            return self::INVALID;
        }
        $userQuery = User::query();
        if ($this->option('user')) {
            $userQuery->where(fn ($query) => $query->where('uuid', $this->option('user'))->orWhere('email', $this->option('user')));
        }
        $userCount = (clone $userQuery)->count();
        if ($this->option('user') && $userCount === 0) {
            $this->error('No matching user.');

            return self::INVALID;
        }
        $days = $settings->getInt('storage.trash_retention_days');
        $days = $days > 0 ? $days : 30;
        $cutoff = now()->subDays($days);
        $lock = Cache::lock('cloud:trash-cleanup', 86400);
        if (! $lock->get()) {
            $this->warn('Another Trash cleanup is already running.');

            return self::SUCCESS;
        }
        $dryRun = ! $this->option('execute');
        $summary = ['deletedFiles' => 0, 'deletedFolders' => 0, 'freedBytes' => 0, 'failedItems' => 0, 'usersAffected' => 0];
        $estimated = ['topLevelFiles' => 0, 'topLevelFolders' => 0, 'files' => 0, 'folders' => 0, 'bytes' => 0];
        $estimatedUsersAffected = 0;
        $rootsInspected = 0;

        try {
            foreach ($userQuery->orderBy('id')->cursor() as $user) {
                if ($rootsInspected >= $limit) {
                    break;
                }
                $files = File::query()->ownedBy($user)->whereNotNull('trashed_at')->where('trashed_at', '<=', $cutoff)
                    ->where(fn ($query) => $query->whereNull('folder_id')->orWhereDoesntHave('folder', fn ($folder) => $folder->whereNotNull('trashed_at')))
                    ->orderBy('id')->limit($limit - $rootsInspected)->get();
                $rootsInspected += $files->count();
                $folders = Folder::query()->ownedBy($user)->whereNotNull('trashed_at')->where('trashed_at', '<=', $cutoff)
                    ->where(fn ($query) => $query->whereNull('parent_id')->orWhereDoesntHave('parent', fn ($parent) => $parent->whereNotNull('trashed_at')))
                    ->orderBy('id')->limit($limit - $rootsInspected)->get();
                $rootsInspected += $folders->count();
                $userAffected = false;
                if ($files->isNotEmpty() || $folders->isNotEmpty()) {
                    $estimatedUsersAffected++;
                }

                foreach ($files as $file) {
                    $estimated['topLevelFiles']++;
                    $estimated['files']++;
                    $estimated['bytes'] += (int) $file->size_bytes;
                    if (! $dryRun) {
                        try {
                            $result = $trash->deleteExpiredFile($user, $file->getKey());
                            $summary['deletedFiles'] += $result['deletedFiles'];
                            $summary['freedBytes'] += $result['freedBytes'];
                            $userAffected = true;
                        } catch (Throwable $exception) {
                            $summary['failedItems']++;
                            report($exception);
                        }
                    }
                }
                foreach ($folders as $folder) {
                    $estimated['topLevelFolders']++;
                    $estimate = $trash->estimateExpiredFolder($user, $folder);
                    $estimated['folders'] += $estimate['folders'];
                    $estimated['files'] += $estimate['files'];
                    $estimated['bytes'] += $estimate['bytes'];
                    if (! $dryRun) {
                        try {
                            $result = $trash->deleteExpiredFolder($user, $folder->getKey());
                            $summary['deletedFiles'] += $result['deletedFiles'];
                            $summary['deletedFolders'] += $result['deletedFolders'];
                            $summary['freedBytes'] += $result['freedBytes'];
                            $userAffected = true;
                        } catch (Throwable $exception) {
                            $summary['failedItems']++;
                            report($exception);
                        }
                    }
                }
                if ($userAffected) {
                    $summary['usersAffected']++;
                }
            }
        } finally {
            $lock->release();
        }

        if ($dryRun) {
            $this->info('DRY RUN — no records or bytes changed.');
            $this->line('eligibleTopLevelFiles: '.$estimated['topLevelFiles']);
            $this->line('eligibleTopLevelFolders: '.$estimated['topLevelFolders']);
            $this->line('estimatedFilesDeleted: '.$estimated['files']);
            $this->line('estimatedFoldersDeleted: '.$estimated['folders']);
            $this->line('estimatedBytesFreed: '.$estimated['bytes']);
            $this->line('usersAffected: '.$estimatedUsersAffected);
        } else {
            $this->line(json_encode($summary, JSON_THROW_ON_ERROR));
            logger()->info('cloud.trash_cleanup.completed', $summary + ['failedItems' => $summary['failedItems'], 'dryRun' => false]);
        }

        return $summary['failedItems'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
