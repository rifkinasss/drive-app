<?php

namespace App\Policies;

use App\Models\Folder;
use App\Models\User;
use App\Services\SharedAccessService;

class FolderPolicy
{
    public function __construct(private readonly SharedAccessService $access) {}

    public function view(User $user, Folder $folder): bool
    {
        return $this->access->canView($user, $folder);
    }

    public function update(User $user, Folder $folder): bool
    {
        return $folder->owner_id === $user->getKey();
    }

    public function move(User $user, Folder $folder): bool
    {
        return $folder->owner_id === $user->getKey();
    }

    public function trash(User $user, Folder $folder): bool
    {
        return $folder->owner_id === $user->getKey();
    }

    public function restore(User $user, Folder $folder): bool
    {
        return $folder->owner_id === $user->getKey();
    }

    public function forceDelete(User $user, Folder $folder): bool
    {
        return $folder->owner_id === $user->getKey();
    }
}
