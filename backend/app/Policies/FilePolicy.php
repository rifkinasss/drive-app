<?php

namespace App\Policies;

use App\Models\File;
use App\Models\User;
use App\Services\SharedAccessService;

class FilePolicy
{
    public function __construct(private readonly SharedAccessService $access) {}

    public function view(User $user, File $file): bool
    {
        return $this->access->canView($user, $file);
    }

    public function update(User $user, File $file): bool
    {
        return $file->owner_id === $user->getKey() || $this->access->canEditFile($user, $file);
    }

    public function move(User $user, File $file): bool
    {
        return $file->owner_id === $user->getKey();
    }

    public function star(User $user, File $file): bool
    {
        return $file->owner_id === $user->getKey();
    }

    public function trash(User $user, File $file): bool
    {
        return $file->owner_id === $user->getKey();
    }

    public function restore(User $user, File $file): bool
    {
        return $file->owner_id === $user->getKey();
    }

    public function forceDelete(User $user, File $file): bool
    {
        return $file->owner_id === $user->getKey();
    }
}
