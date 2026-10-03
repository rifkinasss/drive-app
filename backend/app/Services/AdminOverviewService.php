<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\File;
use App\Models\Folder;
use App\Models\InternalShare;
use App\Models\PublicShareLink;
use App\Models\User;

class AdminOverviewService
{
    public function summarize(): array
    {
        $quotaBytes = max(0, (int) User::query()->sum('quota_bytes'));
        $usedBytes = max(0, (int) User::query()->sum('used_bytes'));
        $trashBytes = max(0, (int) File::query()->whereNotNull('trashed_at')->sum('size_bytes'));
        $recentSince = now()->subDays(30);

        return [
            'users' => ['total' => User::query()->count(), 'active' => User::query()->where('status', 'active')->count()],
            'storage' => ['usedBytes' => $usedBytes, 'quotaBytes' => $quotaBytes, 'availableBytes' => max($quotaBytes - $usedBytes, 0)],
            'files' => ['files' => File::query()->count(), 'folders' => Folder::query()->count()],
            'sharing' => [
                'internalShares' => InternalShare::query()->count(),
                'publicLinks' => PublicShareLink::query()->where('enabled', true)->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->count(),
            ],
            'activity' => ['recentCount' => Activity::query()->where('created_at', '>=', $recentSince)->count()],
            'trash' => [
                'items' => File::query()->whereNotNull('trashed_at')->count() + Folder::query()->whereNotNull('trashed_at')->count(),
                'sizeBytes' => $trashBytes,
            ],
            'system' => ['applicationVersion' => (string) config('docs.version', '2.0.0')],
        ];
    }
}
