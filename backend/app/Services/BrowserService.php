<?php

namespace App\Services;

use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class BrowserService
{
    public function __construct(private readonly FolderService $breadcrumbs) {}

    public function browse(User $user, ?string $folderUuid, string $sort = 'name', string $direction = 'asc', ?string $search = null): array
    {
        $currentFolder = $folderUuid === null
            ? null
            : Folder::query()->ownedBy($user)->where('uuid', $folderUuid)->notTrashed()->firstOrFail();
        $parentId = $currentFolder?->getKey();
        $search = trim((string) $search);
        $folders = $this->folders($user, $parentId, $sort, $direction, $search);
        $files = $this->files($user, $parentId, $sort, $direction, $search);

        return [
            'currentFolder' => $currentFolder,
            'breadcrumb' => $currentFolder ? $this->breadcrumbs->breadcrumb($currentFolder->load('parent')) : [],
            'folders' => $folders,
            'files' => $files,
            'meta' => [
                'sort' => $sort,
                'direction' => $direction,
                'search' => $search === '' ? null : $search,
            ],
        ];
    }

    private function folders(User $user, ?int $parentId, string $sort, string $direction, string $search): Collection
    {
        return Folder::query()
            ->ownedBy($user)
            ->notTrashed()
            ->where('parent_id', $parentId)
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->with('parent')
            ->when($sort === 'modified', fn ($query) => $query->orderBy('updated_at', $direction))
            ->when($sort === 'created', fn ($query) => $query->orderBy('created_at', $direction))
            ->when($sort === 'size', fn ($query) => $query->orderByRaw('LOWER(name) ASC'))
            ->when($sort === 'name', fn ($query) => $query->orderByRaw('LOWER(name) '.$direction))
            ->orderBy('id', 'asc')
            ->get();
    }

    private function files(User $user, ?int $parentId, string $sort, string $direction, string $search): Collection
    {
        return File::query()
            ->ownedBy($user)
            ->notTrashed()
            ->where('folder_id', $parentId)
            ->when($search !== '', fn ($query) => $query->whereRaw('LOWER(original_name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->with('folder')
            ->when($sort === 'modified', fn ($query) => $query->orderBy('updated_at', $direction))
            ->when($sort === 'created', fn ($query) => $query->orderBy('created_at', $direction))
            ->when($sort === 'size', fn ($query) => $query->orderBy('size_bytes', $direction))
            ->when($sort === 'name', fn ($query) => $query->orderByRaw('LOWER(original_name) '.$direction))
            ->orderBy('id', 'asc')
            ->get();
    }
}
