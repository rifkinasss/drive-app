<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;

class LastAdminGuardService
{
    public function isLastActiveAdmin(User $user): bool
    {
        if ($user->role !== UserRole::Admin || $user->status !== UserStatus::Active) {
            return false;
        }

        return ! User::query()
            ->where('role', UserRole::Admin->value)
            ->where('status', UserStatus::Active->value)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    public function canDemote(User $user): bool
    {
        return ! $this->isLastActiveAdmin($user);
    }

    public function canDisable(User $user): bool
    {
        return ! $this->isLastActiveAdmin($user);
    }
}
