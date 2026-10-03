<?php

namespace App\Services;

use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\User;
use Illuminate\Http\Request;

class StorageSummaryService
{
    public function __construct(private readonly StorageQuotaService $quota) {}

    public function summarize(User $user, Request $request): array
    {
        $user = $user->fresh();
        $quotaBytes = $this->quota->getQuota($user);
        $usedBytes = $this->quota->getUsed($user);
        $trashBytes = (int) $user->files()->whereNotNull('trashed_at')->sum('size_bytes');
        $trashCount = (int) $user->files()->whereNotNull('trashed_at')->count();
        $categories = $this->categories($user, $trashBytes);
        $largestFiles = $user->files()->whereNull('trashed_at')->with('folder')->orderByDesc('size_bytes')->limit(5)->get();

        return [
            'quotaBytes' => $quotaBytes,
            'usedBytes' => $usedBytes,
            'availableBytes' => $this->quota->getAvailable($user),
            'trashBytes' => $trashBytes,
            'trashCount' => $trashCount,
            'usagePercentage' => $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0.0,
            'categories' => $categories,
            'largestFiles' => $largestFiles->map(fn ($file): array => FileResource::make($file)->resolve($request))->all(),
            'cleanup' => $this->cleanup($user),
        ];
    }

    private function cleanup(User $user): array
    {
        $oldDays = 180;
        $largeMinBytes = 100 * 1024 * 1024;
        $active = fn () => $user->files()->whereNull('trashed_at')->with('folder');
        $map = fn (File $file): array => [
            'type' => 'file',
            'id' => $file->uuid,
            'name' => $file->original_name,
            'extension' => $file->extension,
            'mimeType' => $file->mime_type,
            'sizeBytes' => (int) $file->size_bytes,
            'folderId' => $file->folder?->uuid,
            'location' => $file->folder ? ['folderId' => $file->folder->uuid, 'folderName' => $file->folder->name] : null,
            'starred' => (bool) $file->is_starred,
            'createdAt' => $file->created_at?->toISOString(),
            'updatedAt' => $file->updated_at?->toISOString(),
        ];
        $oldSince = now()->subDays($oldDays);
        $largeStats = $active()->where('size_bytes', '>=', $largeMinBytes)->selectRaw('COALESCE(SUM(size_bytes), 0) as bytes, COUNT(*) as file_count')->first();
        $oldStats = $active()->where('updated_at', '<=', $oldSince)->selectRaw('COALESCE(SUM(size_bytes), 0) as bytes, COUNT(*) as file_count')->first();
        $largeFiles = $active()->where('size_bytes', '>=', $largeMinBytes)->orderByDesc('size_bytes')->limit(20)->get()->map($map)->values()->all();
        $oldFiles = $active()->where('updated_at', '<=', $oldSince)->orderBy('updated_at')->limit(20)->get()->map($map)->values()->all();
        $groups = $active()->whereNotNull('checksum')->selectRaw('checksum, size_bytes, COUNT(*) as duplicate_count')->groupBy('checksum', 'size_bytes')->havingRaw('COUNT(*) > 1')->orderByDesc('duplicate_count')->limit(20)->get();
        $duplicates = $groups->map(function ($group) use ($active, $map): array {
            $files = $active()->where('checksum', $group->checksum)->where('size_bytes', $group->size_bytes)->orderByDesc('updated_at')->get()->map($map)->values()->all();
            $fileCount = (int) $group->duplicate_count;
            return ['checksum' => $group->checksum, 'sizeBytes' => (int) $group->size_bytes, 'fileCount' => $fileCount, 'reclaimableBytes' => max(0, ($fileCount - 1) * (int) $group->size_bytes), 'files' => $files];
        })->values()->all();

        return [
            'oldDays' => $oldDays,
            'largeMinBytes' => $largeMinBytes,
            'largeCount' => (int) ($largeStats->file_count ?? 0),
            'largeBytes' => (int) ($largeStats->bytes ?? 0),
            'oldCount' => (int) ($oldStats->file_count ?? 0),
            'oldBytes' => (int) ($oldStats->bytes ?? 0),
            'largeFiles' => $largeFiles,
            'oldFiles' => $oldFiles,
            'duplicateGroups' => $duplicates,
        ];
    }

    private function categories(User $user, int $trashBytes): array
    {
        $categories = ['Documents' => 0, 'Images' => 0, 'Videos' => 0, 'Archives' => 0, 'Backups' => 0, 'Code' => 0, 'Other' => 0, 'Trash' => $trashBytes];
        $files = $user->files()->whereNull('trashed_at')->get(['extension', 'mime_type', 'size_bytes']);

        foreach ($files as $file) {
            $mime = (string) $file->mime_type;
            $extension = strtolower((string) $file->extension);
            $category = str_starts_with($mime, 'image/') ? 'Images'
                : (str_starts_with($mime, 'video/') ? 'Videos'
                    : (preg_match('/zip|compressed|archive/', $mime) === 1 || in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz'], true) ? 'Archives'
                        : (in_array($extension, ['js', 'ts', 'tsx', 'jsx', 'json', 'sql', 'css', 'html', 'yml', 'yaml', 'py', 'php'], true) ? 'Code'
                            : (str_starts_with($mime, 'text/') || str_contains($mime, 'pdf') || str_contains($mime, 'word') ? 'Documents' : 'Other'))));
            $categories[$category] += (int) $file->size_bytes;
        }

        return $categories;
    }
}
