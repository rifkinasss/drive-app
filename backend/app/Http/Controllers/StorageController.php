<?php

namespace App\Http\Controllers;

use App\Services\StorageQuotaService;
use App\Support\ApiResponse;
use App\Http\Resources\FileResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageController
{
    public function __invoke(Request $request, StorageQuotaService $quota): JsonResponse
    {
        $user = $request->user()->fresh();
        $quotaBytes = $quota->getQuota($user);
        $usedBytes = $quota->getUsed($user);
        $trashBytes = (int) $user->files()->whereNotNull('trashed_at')->sum('size_bytes');
        $usagePercentage = $quotaBytes > 0 ? round(($usedBytes / $quotaBytes) * 100, 2) : 0.0;
        $categories = ['Documents' => 0, 'Images' => 0, 'Videos' => 0, 'Archives' => 0, 'Backups' => 0, 'Code' => 0, 'Other' => 0, 'Trash' => $trashBytes];
        $files = $user->files()->whereNull('trashed_at')->get(['uuid', 'original_name', 'extension', 'mime_type', 'size_bytes', 'folder_id', 'owner_id', 'created_at', 'updated_at']);
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
        $largestFiles = $user->files()->whereNull('trashed_at')->with('folder')->orderByDesc('size_bytes')->limit(5)->get();

        return ApiResponse::success([
            'quotaBytes' => $quotaBytes,
            'usedBytes' => $usedBytes,
            'availableBytes' => $quota->getAvailable($user),
            'trashBytes' => $trashBytes,
            'usagePercentage' => $usagePercentage,
            'categories' => $categories,
            'largestFiles' => $largestFiles->map(fn ($file): array => FileResource::make($file)->resolve($request))->all(),
        ]);
    }
}
