<?php

namespace App\Services;

use App\Exceptions\StorageQuotaExceededException;
use App\Models\File;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StorageQuotaService
{
    public function getQuota(User $user): int
    {
        return (int) $user->quota_bytes;
    }

    public function getUsed(User $user): int
    {
        return max(0, (int) $user->used_bytes);
    }

    public function getAvailable(User $user): int
    {
        return max($this->getQuota($user) - $this->getUsed($user), 0);
    }

    public function assertCanStore(User $user, int $additionalBytes): void
    {
        $this->transaction($user, function (User $lockedUser) use ($additionalBytes): void {
            $this->assertCanStoreLocked($lockedUser, $additionalBytes);
        });
    }

    public function applyDelta(User $user, int $delta): void
    {
        $this->transaction($user, function (User $lockedUser) use ($delta): void {
            $this->applyDeltaLocked($lockedUser, $delta);
        });
    }

    public function transaction(User $user, Closure $operation): mixed
    {
        return DB::transaction(function () use ($user, $operation): mixed {
            $lockedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            return $operation($lockedUser);
        });
    }

    public function assertCanStoreLocked(User $user, int $additionalBytes): void
    {
        if ($additionalBytes <= 0) {
            return;
        }

        $quota = max(0, (int) $user->quota_bytes);
        $used = max(0, (int) $user->used_bytes);
        $available = max($quota - $used, 0);

        if ($additionalBytes > $available) {
            throw new StorageQuotaExceededException($quota, $used, $available, $additionalBytes);
        }
    }

    public function applyDeltaLocked(User $user, int $delta): void
    {
        $current = max(0, (int) $user->used_bytes);
        $next = $current + $delta;
        if ($next < 0) {
            Log::error('Storage usage would become negative.', ['user_id' => $user->getKey(), 'used_bytes' => $current, 'delta' => $delta]);
            throw ValidationException::withMessages(['storage' => 'Storage usage is inconsistent. Reconciliation is required.']);
        }

        $user->update(['used_bytes' => $next]);
    }

    public function recalculate(User $user, bool $dryRun = false): array
    {
        $calculate = function (User $lockedUser) use ($dryRun): array {
            $calculated = max(0, (int) File::query()->where('owner_id', $lockedUser->getKey())->sum('size_bytes'));
            $stored = max(0, (int) $lockedUser->used_bytes);

            if (! $dryRun) {
                $lockedUser->update(['used_bytes' => $calculated]);
            }

            return [
                'user' => $lockedUser,
                'stored' => $stored,
                'calculated' => $calculated,
                'difference' => $calculated - $stored,
            ];
        };

        return $dryRun
            ? $calculate($user->fresh())
            : $this->transaction($user, $calculate);
    }

    public function setQuota(User $user, int $quotaBytes): User
    {
        if ($quotaBytes <= 0) {
            throw ValidationException::withMessages(['quotaBytes' => 'Quota must be greater than zero.']);
        }

        return $this->transaction($user, function (User $lockedUser) use ($quotaBytes): User {
            if ($quotaBytes < (int) $lockedUser->used_bytes) {
                throw ValidationException::withMessages(['quotaBytes' => 'Quota cannot be lower than used storage.']);
            }

            $lockedUser->update(['quota_bytes' => $quotaBytes]);

            return $lockedUser->fresh();
        });
    }
}
