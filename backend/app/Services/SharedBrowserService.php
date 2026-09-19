<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;

class SharedBrowserService
{
    public function __construct(private readonly SharedAccessService $access) {}

    public function browse(User $user, Folder $folder, string $sort = 'name', string $direction = 'asc', ?string $search = null): array
    {
        abort_unless($folder->owner_id !== $user->getKey() && $this->access->canView($user, $folder), 404);
        $root = $this->access->sharedRoot($user, $folder);
        abort_unless($root !== null && $this->within($folder, $root), 404);
        $search = trim((string) $search);

        $folders = Folder::query()->where('owner_id', $root->owner_id)->where('parent_id', $folder->getKey())->notTrashed()
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->when($sort === 'modified', fn ($query) => $query->orderBy('updated_at', $direction))
            ->when($sort === 'created', fn ($query) => $query->orderBy('created_at', $direction))
            ->when($sort !== 'modified' && $sort !== 'created', fn ($query) => $query->orderByRaw('LOWER(name) '.$direction))
            ->orderBy('id')->get();
        $files = File::query()->where('owner_id', $root->owner_id)->where('folder_id', $folder->getKey())->notTrashed()
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(original_name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->with('folder')
            ->when($sort === 'modified', fn ($query) => $query->orderBy('updated_at', $direction))
            ->when($sort === 'created', fn ($query) => $query->orderBy('created_at', $direction))
            ->when($sort === 'size', fn ($query) => $query->orderBy('size_bytes', $direction))
            ->when($sort !== 'modified' && $sort !== 'created' && $sort !== 'size', fn ($query) => $query->orderByRaw('LOWER(original_name) '.$direction))
            ->orderBy('id')->get();

        return [
            'sharedRoot' => $this->folder($root, null),
            'currentFolder' => $this->folder($folder, $folder->getKey() === $root->getKey() ? null : $this->relativeParent($folder, $root)?->uuid),
            'breadcrumb' => $this->breadcrumb($folder, $root),
            'folders' => $folders->map(fn (Folder $child): array => $this->folder($child, $folder->uuid))->values(),
            'files' => $files,
            'permission' => $this->access->permission($user, $folder)?->value,
            'meta' => ['sort' => $sort, 'direction' => $direction, 'search' => $search === '' ? null : $search],
        ];
    }

    private function within(Folder $folder, Folder $root): bool
    {
        $current = $folder;
        while ($current !== null) {
            if ($current->getKey() === $root->getKey()) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }

    private function relativeParent(Folder $folder, Folder $root): ?Folder
    {
        return $folder->parent_id === $root->getKey() ? $root : $folder->parent;
    }

    private function breadcrumb(Folder $folder, Folder $root): array
    {
        $items = [];
        $current = $folder;
        while ($current !== null) {
            $items[] = ['id' => $current->uuid, 'name' => $current->name];
            if ($current->getKey() === $root->getKey()) {
                break;
            }
            $current = $current->parent;
        }

        return array_reverse($items);
    }

    private function folder(Folder $folder, ?string $parentId): array
    {
        return ['id' => $folder->uuid, 'name' => $folder->name, 'parentId' => $parentId, 'trashedAt' => null, 'createdAt' => $folder->created_at?->toISOString(), 'updatedAt' => $folder->updated_at?->toISOString()];
    }
}
