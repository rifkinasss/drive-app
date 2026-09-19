<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Throwable;

class StorageMaintenanceService
{
    public function cleanStaging(string $prefix, int $olderThanHours, bool $execute): array
    {
        $disk = Storage::disk(config('cloud.disk'));
        $cutoff = now()->subHours($olderThanHours)->timestamp;
        $counts = ['files' => 0, 'bytes' => 0, 'failed' => 0, 'youngestAgeHours' => null, 'oldestAgeHours' => null];

        if (! $disk->directoryExists($prefix)) {
            return $counts;
        }

        foreach ($disk->getDriver()->listContents($prefix, true) as $entry) {
            if (! $entry->isFile() || ($entry->lastModified() ?: now()->timestamp) > $cutoff) {
                continue;
            }
            $counts['files']++;
            $counts['bytes'] += $entry->fileSize() ?? 0;
            $ageHours = (int) floor((now()->timestamp - $entry->lastModified()) / 3600);
            $counts['youngestAgeHours'] = $counts['youngestAgeHours'] === null ? $ageHours : min($counts['youngestAgeHours'], $ageHours);
            $counts['oldestAgeHours'] = $counts['oldestAgeHours'] === null ? $ageHours : max($counts['oldestAgeHours'], $ageHours);
            if ($execute) {
                try {
                    if (! $disk->delete($entry->path())) {
                        $counts['failed']++;
                    }
                } catch (Throwable) {
                    $counts['failed']++;
                }
            }
        }

        return $counts;
    }

    public function audit(): array
    {
        $diskName = config('cloud.disk');
        $disk = Storage::disk($diskName);
        $report = ['metadataRowsChecked' => 0, 'missingPhysicalFiles' => 0, 'physicalFilesScanned' => 0, 'physicalOrphans' => 0, 'stagingFiles' => 0, 'deleteStagingFiles' => 0, 'pendingDeleteStagingFiles' => 0, 'legacyDeleteStagingFiles' => 0, 'estimatedOrphanBytes' => 0];
        File::query()->orderBy('id')->chunkById(250, function ($files) use (&$report, $diskName): void {
            foreach ($files as $file) {
                $report['metadataRowsChecked']++;
                $fileDisk = $file->disk ?: $diskName;
                if ($file->path === null || ! Storage::disk($fileDisk)->exists($file->path)) {
                    $report['missingPhysicalFiles']++;
                    logger()->warning('cloud.storage_audit.db_orphan_detected', ['file_uuid' => $file->uuid]);
                }
            }
        });

        $pending = [];
        foreach ($disk->directoryExists('users') ? $disk->getDriver()->listContents('users', true) : [] as $entry) {
            if (! $entry->isFile()) {
                continue;
            }
            $path = $entry->path();
            if (str_starts_with($path, 'users/') && preg_match('#^users/[^/]+/files/[^/]+$#', $path)) {
                $report['physicalFilesScanned']++;
                $pending[$path] = $entry->fileSize() ?? 0;
                if (count($pending) === 250) {
                    $this->countOrphans($pending, $report, $diskName);
                    $pending = [];
                }
            }
        }
        if ($pending !== []) {
            $this->countOrphans($pending, $report, $diskName);
        }

        foreach (['tmp', 'tmp-delete', 'tmp-delete-pending', 'tmp-delete-committed'] as $prefix) {
            if (! $disk->directoryExists($prefix)) {
                continue;
            }
            foreach ($disk->getDriver()->listContents($prefix, true) as $entry) {
                if ($entry->isFile()) {
                    $key = match ($prefix) {
                        'tmp' => 'stagingFiles',
                        'tmp-delete-committed' => 'deleteStagingFiles',
                        'tmp-delete-pending' => 'pendingDeleteStagingFiles',
                        default => 'legacyDeleteStagingFiles',
                    };
                    $report[$key]++;
                }
            }
        }

        return $report;
    }

    private function countOrphans(array $paths, array &$report, string $diskName): void
    {
        $referenced = File::query()->whereIn('path', array_keys($paths))
            ->where(fn ($query) => $query->where('disk', $diskName)->orWhereNull('disk'))
            ->pluck('path')->all();
        $found = array_fill_keys($referenced, true);
        foreach ($paths as $path => $size) {
            if (! isset($found[$path])) {
                $report['physicalOrphans']++;
                $report['estimatedOrphanBytes'] += $size;
                logger()->warning('cloud.storage_audit.physical_orphan_detected', ['object_key_hash' => hash('sha256', $path)]);
            }
        }
    }
}
