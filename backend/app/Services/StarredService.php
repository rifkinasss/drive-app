<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class StarredService
{
    public function items(User $user): Collection
    {
        $folders = Folder::query()->ownedBy($user)->notTrashed()->where('is_starred', true)->with('parent')->get();
        $files = File::query()->ownedBy($user)->notTrashed()->where('is_starred', true)->with('folder')->get();

        return $folders->concat($files)->sortByDesc(fn ($item) => [$item->updated_at?->timestamp ?? 0, $item->getKey()])->values();
    }
}
