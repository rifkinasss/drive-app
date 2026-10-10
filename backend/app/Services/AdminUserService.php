<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Exceptions\AdminUserException;
use App\Models\Activity;
use App\Models\File;
use App\Models\Folder;
use App\Models\InternalShare;
use App\Models\PublicShareLink;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class AdminUserService
{
    public function list(array $filters): LengthAwarePaginator
    {
        $query = User::query();
        $search = trim((string) ($filters['search'] ?? ''));

        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($users) use ($needle): void {
                $users->whereRaw('LOWER(name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$needle]);
            });
        }
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (($filters['verification'] ?? null) === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif (($filters['verification'] ?? null) === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        $sort = $filters['sort'] ?? 'created';
        $column = match ($sort) {
            'name' => 'name',
            'email' => 'email',
            'storage' => 'used_bytes',
            'quota' => 'quota_bytes',
            'lastActive' => 'created_at',
            default => 'created_at',
        };
        $direction = $filters['direction'] ?? 'desc';

        return $query->orderBy($column, $direction)->orderBy('id', 'desc')
            ->paginate((int) ($filters['perPage'] ?? 20), ['*'], 'page', (int) ($filters['page'] ?? 1))
            ->withQueryString();
    }

    public function detail(User $user): array
    {
        $invitation = UserInvitation::query()->where('user_id', $user->getKey())->latest('id')->first();

        return [
            'user' => $user,
            'invitation' => $invitation === null ? null : [
                'status' => $this->invitationStatus($invitation),
                'sentAt' => $invitation->sent_at?->toISOString(),
                'expiresAt' => $invitation->expires_at?->toISOString(),
                'acceptedAt' => $invitation->accepted_at?->toISOString(),
            ],
            'counts' => [
                'files' => File::query()->where('owner_id', $user->getKey())->count(),
                'folders' => Folder::query()->where('owner_id', $user->getKey())->count(),
                'receivedShares' => InternalShare::query()->where('recipient_id', $user->getKey())->count(),
            ],
        ];
    }

    public function update(User $target, array $attributes): User
    {
        return DB::transaction(function () use ($target, $attributes): User {
            $this->lockActiveAdmins();
            $user = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            if (array_key_exists('role', $attributes)
                && $attributes['role'] === UserRole::User->value
                && $user->role === UserRole::Admin
                && $user->status === UserStatus::Active
                && $this->activeAdminCount() <= 1) {
                throw new AdminUserException('LAST_ADMIN_REQUIRED', 'At least one active admin is required.', [], 409);
            }

            if (array_key_exists('quotaBytes', $attributes)) {
                $requested = (int) $attributes['quotaBytes'];
                $used = max(0, (int) $user->used_bytes);
                if ($requested <= 0 || $requested < $used) {
                    throw new AdminUserException('QUOTA_BELOW_CURRENT_USAGE', 'Quota cannot be lower than current usage.', [
                        'usedBytes' => $used,
                        'requestedQuotaBytes' => $requested,
                    ]);
                }
                $user->quota_bytes = $requested;
            }
            if (array_key_exists('name', $attributes)) {
                $user->name = $attributes['name'];
            }
            if (array_key_exists('role', $attributes)) {
                $user->role = $attributes['role'];
            }
            $user->save();

            return $user->fresh();
        });
    }

    public function disable(User $actor, User $target): User
    {
        return DB::transaction(function () use ($actor, $target): User {
            if ($actor->is($target)) {
                throw new AdminUserException('CANNOT_DISABLE_SELF', 'You cannot disable your own account.', [], 409);
            }
            $this->lockActiveAdmins();
            $user = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $this->assertNotLastActiveAdmin($user);
            if ($user->status !== UserStatus::Disabled) {
                $user->update(['status' => UserStatus::Disabled]);
            }

            return $user->fresh();
        });
    }

    public function enable(User $target): User
    {
        return DB::transaction(function () use ($target): User {
            $user = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            if ($user->status === UserStatus::Pending) {
                throw new AdminUserException('INVALID_USER_STATE', 'Pending users must accept their invitation before activation.', [], 409);
            }
            if ($user->status === UserStatus::Disabled) {
                $user->update(['status' => UserStatus::Active]);
            }

            return $user->fresh();
        });
    }

    public function verifyEmail(User $target): User
    {
        return DB::transaction(function () use ($target): User {
            $user = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            if ($user->email_verified_at === null) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user->fresh();
        });
    }

    public function unverifyEmail(User $actor, User $target): User
    {
        return DB::transaction(function () use ($actor, $target): User {
            if ($actor->is($target)) {
                throw new AdminUserException('CANNOT_UNVERIFY_SELF', 'You cannot remove verification from your own account.', [], 409);
            }
            $user = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            if ($user->email_verified_at !== null) {
                $user->forceFill(['email_verified_at' => null])->save();
            }

            return $user->fresh();
        });
    }

    public function setPassword(User $actor, User $target, string $password): User
    {
        return DB::transaction(function () use ($actor, $target, $password): User {
            if ($actor->is($target)) {
                throw new AdminUserException('CANNOT_SET_OWN_PASSWORD', 'Use the profile security settings to change your own password.', [], 409);
            }
            $user = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $user->forceFill(['password' => $password])->save();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();

            return $user->fresh();
        });
    }

    public function sendPasswordReset(User $target): void
    {
        if ($target->status !== UserStatus::Active) {
            throw new AdminUserException('INVALID_USER_STATE', 'Password reset is available only for active users.', [], 409);
        }

        Password::sendResetLink(['email' => $target->email]);
    }

    public function delete(User $actor, User $target): void
    {
        DB::transaction(function () use ($actor, $target): void {
            if ($actor->is($target)) {
                throw new AdminUserException('CANNOT_DELETE_SELF', 'You cannot delete your own account.', [], 409);
            }
            $this->lockActiveAdmins();
            $user = User::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();
            $this->assertNotLastActiveAdmin($user);

            if (File::query()->where('owner_id', $user->getKey())->exists()
                || Folder::query()->where('owner_id', $user->getKey())->exists()
                || InternalShare::query()->where('owner_id', $user->getKey())->exists()
                || PublicShareLink::query()->where('owner_id', $user->getKey())->exists()
                || (int) $user->used_bytes > 0) {
                throw new AdminUserException('USER_OWNS_CONTENT', 'This user still owns storage content.', [], 409);
            }

            InternalShare::query()->where('owner_id', $user->getKey())->orWhere('recipient_id', $user->getKey())->delete();
            PublicShareLink::query()->where('owner_id', $user->getKey())->delete();
            UserInvitation::query()->where('user_id', $user->getKey())->delete();
            $user->emailVerificationTokens()->delete();
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            DB::table('sessions')->where('user_id', $user->getKey())->delete();
            DatabaseNotification::query()->where('notifiable_type', User::class)->where('notifiable_id', $user->getKey())->delete();
            Activity::query()->where('user_id', $user->getKey())->delete();
            Activity::query()->where('actor_id', $user->getKey())->update(['actor_id' => null]);
            $user->delete();
        });
    }

    public function summary(): array
    {
        $aggregate = User::query()->selectRaw('COUNT(*) as total_users')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users")
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_users")
            ->selectRaw("SUM(CASE WHEN status = 'disabled' THEN 1 ELSE 0 END) as disabled_users")
            ->selectRaw("SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count")
            ->selectRaw("SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as user_count")
            ->selectRaw('SUM(CASE WHEN email_verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified_users')
            ->selectRaw('SUM(CASE WHEN email_verified_at IS NULL THEN 1 ELSE 0 END) as unverified_users')
            ->selectRaw('COALESCE(SUM(quota_bytes), 0) as total_quota_bytes')
            ->selectRaw('COALESCE(SUM(used_bytes), 0) as total_used_bytes')
            ->first();
        $quota = (int) $aggregate->total_quota_bytes;
        $used = (int) $aggregate->total_used_bytes;

        return [
            'totalUsers' => (int) $aggregate->total_users,
            'activeUsers' => (int) $aggregate->active_users,
            'pendingUsers' => (int) $aggregate->pending_users,
            'disabledUsers' => (int) $aggregate->disabled_users,
            'adminCount' => (int) $aggregate->admin_count,
            'userCount' => (int) $aggregate->user_count,
            'verifiedUsers' => (int) $aggregate->verified_users,
            'unverifiedUsers' => (int) $aggregate->unverified_users,
            'totalQuotaBytes' => $quota,
            'totalUsedBytes' => $used,
            'usagePercentage' => $quota > 0 ? round(($used / $quota) * 100, 2) : 0,
        ];
    }

    private function lockActiveAdmins(): void
    {
        User::query()->where('status', UserStatus::Active)->where('role', UserRole::Admin)->lockForUpdate()->get();
    }

    private function activeAdminCount(): int
    {
        return User::query()->where('status', UserStatus::Active)->where('role', UserRole::Admin)->count();
    }

    private function assertNotLastActiveAdmin(User $user): void
    {
        if ($user->status === UserStatus::Active && $user->role === UserRole::Admin && $this->activeAdminCount() <= 1) {
            throw new AdminUserException('LAST_ADMIN_REQUIRED', 'At least one active admin is required.', [], 409);
        }
    }

    private function invitationStatus(UserInvitation $invitation): string
    {
        if ($invitation->accepted_at !== null) {
            return 'accepted';
        }
        if ($invitation->revoked_at !== null) {
            return 'revoked';
        }
        if ($invitation->expires_at?->isPast()) {
            return 'expired';
        }

        return 'pending';
    }
}
