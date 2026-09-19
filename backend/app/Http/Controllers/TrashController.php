<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Folder;
use App\Services\FileTrashService;
use App\Services\FolderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrashController
{
    public function index(Request $request, FolderService $breadcrumbs): JsonResponse
    {
        $folders = Folder::query()
            ->ownedBy($request->user())
            ->whereNotNull('trashed_at')
            ->where(function ($query): void {
                $query->whereNull('parent_id')->orWhereDoesntHave('parent', fn ($parent) => $parent->whereNotNull('trashed_at'));
            })
            ->with('parent')
            ->get()
            ->map(fn (Folder $folder): array => [
                'id' => $folder->uuid,
                'type' => 'folder',
                'name' => $folder->name,
                'originalLocation' => $folder->parent ? $breadcrumbs->breadcrumb($folder->parent) : [],
                'sizeBytes' => null,
                'trashedAt' => $folder->trashed_at?->toISOString(),
                'canRestore' => true,
            ]);
        $files = File::query()
            ->ownedBy($request->user())
            ->whereNotNull('trashed_at')
            ->where(function ($query): void {
                $query->whereNull('folder_id')->orWhereDoesntHave('folder', fn ($folder) => $folder->whereNotNull('trashed_at'));
            })
            ->with('folder')
            ->get()
            ->map(fn (File $file): array => [
                'id' => $file->uuid,
                'type' => 'file',
                'name' => $file->original_name,
                'originalLocation' => $file->folder ? $breadcrumbs->breadcrumb($file->folder) : [],
                'sizeBytes' => $file->size_bytes,
                'trashedAt' => $file->trashed_at?->toISOString(),
                'canRestore' => true,
            ]);

        return ApiResponse::success($folders->concat($files)->sortByDesc('trashedAt')->values()->all());
    }

    public function destroy(Request $request, FileTrashService $trash): JsonResponse
    {
        return ApiResponse::success($trash->emptyTrash($request->user()), 'Trash emptied.');
    }
}
