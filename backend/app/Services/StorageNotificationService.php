<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserStorageAlertState;
use App\Notifications\StorageQuotaWarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class StorageNotificationService
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function evaluate(User $user): void
    {
        try {
            DB::transaction(function () use ($user): void {
                $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $thresholds = $this->thresholds();
                $state = UserStorageAlertState::query()->where('user_id', $lockedUser->getKey())->lockForUpdate()->first();
                if ($state === null) {
                    $state = UserStorageAlertState::create(['user_id' => $lockedUser->getKey(), 'notified_thresholds' => []]);
                }

                $used = max(0, (int) $lockedUser->used_bytes);
                $quota = max(0, (int) $lockedUser->quota_bytes);
                $percentage = $quota > 0 ? round(($used / $quota) * 100, 2) : 0.0;
                $notified = array_map('intval', (array) $state->notified_thresholds);
                $notified = array_values(array_intersect($notified, array_filter($thresholds, fn (int $threshold): bool => $percentage >= $threshold)));
                $crossed = array_values(array_diff(array_filter($thresholds, fn (int $threshold): bool => $percentage >= $threshold), $notified));

                foreach ($crossed as $threshold) {
                    $lockedUser->notify(new StorageQuotaWarning($threshold, $used, $quota, $percentage));
                }
                $state->update(['notified_thresholds' => array_values(array_unique(array_merge($notified, $crossed)))]);
            });
        } catch (Throwable $exception) {
            Log::warning('Unable to record storage quota notification.', [
                'user_id' => $user->getKey(),
                'exception' => $exception,
            ]);
        }
    }

    private function thresholds(): array
    {
        $thresholds = array_map('intval', $this->settings->getJson('storage.quota_warning_thresholds'));
        sort($thresholds);

        return array_values(array_unique(array_filter($thresholds, static fn (int $threshold): bool => $threshold > 0 && $threshold <= 100)));
    }
}
