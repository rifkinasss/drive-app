<?php

namespace App\Http\Controllers;

use App\Services\PublicShareAccessService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicShareBrowserController
{
    public function __invoke(Request $request, string $token, PublicShareAccessService $access): JsonResponse
    {
        $context = $access->folder($token, $request->input('folderId'), $request->header('X-Share-Password'));
        $root = $context['item'];
        $current = $context['currentFolder'];
        $sort = in_array($request->input('sort', 'name'), ['name', 'modified', 'created', 'size'], true) ? $request->input('sort', 'name') : 'name';
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $search = trim((string) $request->input('search', ''));
        $folders = $current->children()->whereNull('trashed_at')->when($search !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->when($sort === 'modified', fn ($query) => $query->orderBy('updated_at', $direction))
            ->when($sort === 'created', fn ($query) => $query->orderBy('created_at', $direction))
            ->when($sort !== 'modified' && $sort !== 'created', fn ($query) => $query->orderByRaw('LOWER(name) '.$direction))->get();
        $files = $current->files()->whereNull('trashed_at')->when($search !== '', fn ($query) => $query->whereRaw('LOWER(original_name) LIKE ?', ['%'.mb_strtolower($search).'%']))
            ->when($sort === 'modified', fn ($query) => $query->orderBy('updated_at', $direction))
            ->when($sort === 'created', fn ($query) => $query->orderBy('created_at', $direction))
            ->when($sort === 'size', fn ($query) => $query->orderBy('size_bytes', $direction))
            ->when($sort !== 'modified' && $sort !== 'created' && $sort !== 'size', fn ($query) => $query->orderByRaw('LOWER(original_name) '.$direction))->get();

        return ApiResponse::success([
            'sharedRoot' => $this->folder($root, null),
            'currentFolder' => $this->folder($current, $current->getKey() === $root->getKey() ? null : $this->parentUuid($current, $root)),
            'breadcrumb' => $this->breadcrumb($current, $root),
            'folders' => $folders->map(fn ($folder) => $this->folder($folder, $current->uuid))->values()->all(),
            'files' => $files->map(fn ($file) => ['id' => $file->uuid, 'name' => $file->original_name, 'mimeType' => $file->mime_type, 'sizeBytes' => $file->size_bytes, 'updatedAt' => $file->updated_at?->utc()->toISOString()])->values()->all(),
            'permission' => 'viewer',
            'allowDownload' => (bool) $context['link']->allow_download,
            'meta' => ['sort' => $sort, 'direction' => $direction, 'search' => $search === '' ? null : $search],
        ]);
    }

    private function folder($folder, ?string $parentId): array
    {
        return ['id' => $folder->uuid, 'name' => $folder->name, 'parentId' => $parentId, 'updatedAt' => $folder->updated_at?->utc()->toISOString()];
    }

    private function parentUuid($folder, $root): ?string
    {
        return $folder->parent_id === $root->getKey() ? $root->uuid : $folder->parent?->uuid;
    }

    private function breadcrumb($folder, $root): array
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
}
