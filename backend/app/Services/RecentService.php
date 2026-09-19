<?php

namespace App\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class RecentService
{
    public function files(User $user, int $limit = 20): Collection
    {
        return File::query()
            ->ownedBy($user)
            ->notTrashed()
            ->with('folder')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(min(max($limit, 1), 100))
            ->get();
    }
}
