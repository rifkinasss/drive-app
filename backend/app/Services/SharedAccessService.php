<?php

namespace App\Services;

use App\Enums\SharePermission;
use App\Models\File;
use App\Models\Folder;
use App\Models\InternalShare;
use App\Models\User;
use Illuminate\Support\Collection;

class SharedAccessService
{
    public function permission(User $user, File|Folder $item): ?SharePermission
    {
        if ($item->owner_id === $user->getKey() || $user->status?->value !== 'active') {
            return $item->owner_id === $user->getKey() ? SharePermission::Editor : null;
        }

        $shares = $this->sharesFor($user, $item);

        return $shares->sortByDesc(fn (InternalShare $share): int => $share->permission->rank())->first()?->permission;
    }

    public function canView(User $user, File|Folder $item): bool
    {
        return $this->permission($user, $item) !== null && $item->trashed_at === null && $this->ownerIsActive($item->owner_id);
    }

    public function canEditFile(User $user, File $file): bool
    {
        return $this->permission($user, $file)?->rank() >= SharePermission::Editor->rank()
            && $file->trashed_at === null
            && $file->owner_id !== $user->getKey();
    }

    public function sharedRoot(User $user, Folder $folder): ?Folder
    {
        $candidates = $this->folderShares($user, $folder);
        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortByDesc(fn (array $candidate): int => $candidate['depth'])->first()['folder'];
    }

    public function folderShares(User $user, Folder $folder): Collection
    {
        $chain = [];
        $current = $folder;
        $depth = 0;
        while ($current !== null) {
            $chain[] = ['folder' => $current, 'depth' => $depth++];
            $current = $current->parent;
        }

        $ids = collect($chain)->pluck('folder.id');
        $shares = InternalShare::query()->where('recipient_id', $user->getKey())->where('shareable_type', 'folder')->whereIn('shareable_id', $ids)->get()->keyBy('shareable_id');

        return collect($chain)->filter(fn (array $item): bool => $shares->has($item['folder']->getKey()))->map(function (array $item) use ($shares): array {
            $item['share'] = $shares->get($item['folder']->getKey());

            return $item;
        })->values();
    }

    private function sharesFor(User $user, File|Folder $item): Collection
    {
        $type = $item instanceof File ? 'file' : 'folder';
        $shares = InternalShare::query()->where('recipient_id', $user->getKey())->where('shareable_type', $type)->where('shareable_id', $item->getKey())->get();
        if ($item instanceof File && $item->folder_id !== null) {
            $shares = $shares->concat($this->ancestorFolderShares($user, $item->folder));
        } elseif ($item instanceof Folder) {
            $shares = $shares->concat($this->ancestorFolderShares($user, $item->parent));
        }

        return $shares;
    }

    private function ancestorFolderShares(User $user, ?Folder $folder): Collection
    {
        if ($folder === null) {
            return collect();
        }

        $current = $folder;
        $ids = [];
        while ($current !== null) {
            $ids[] = $current->getKey();
            $current = $current->parent;
        }

        return InternalShare::query()->where('recipient_id', $user->getKey())->where('shareable_type', 'folder')->whereIn('shareable_id', $ids)->get();
    }

    private function ownerIsActive(int $ownerId): bool
    {
        return User::query()->whereKey($ownerId)->where('status', 'active')->exists();
    }
}
