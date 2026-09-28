<?php

namespace App\Http\Controllers;

use App\Http\Requests\Folder\StoreFolderRequest;
use App\Http\Requests\Folder\UpdateFolderRequest;
use App\Http\Resources\FolderDetailsResource;
use App\Http\Resources\FolderResource;
use App\Models\Folder;
use App\Services\FolderService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderController
{
    public function index(Request $request): JsonResponse
    {
        $parentId = $request->input('parentId');
        $parent = null;

        if ($parentId !== null) {
            $parent = Folder::query()
                ->ownedBy($request->user())
                ->where('uuid', $parentId)
                ->notTrashed()
                ->first();

            if ($parent === null) {
                return ApiResponse::error('The parent folder is invalid.', ['parentId' => ['The parent folder is invalid.']], 422);
            }
        }

        $folders = Folder::query()
            ->ownedBy($request->user())
            ->notTrashed()
            ->when($parent === null, fn ($query) => $query->root(), fn ($query) => $query->where('parent_id', $parent->getKey()))
            ->with('parent')
            ->orderByRaw('LOWER(name) ASC')
            ->get();

        return ApiResponse::success($this->resourceData($folders, $request));
    }

    public function store(StoreFolderRequest $request, FolderService $folders): JsonResponse
    {
        $folder = $folders->create(
            $request->user(),
            $request->string('name')->toString(),
            $request->input('parentId'),
        )->load('parent');

        return ApiResponse::success($this->resourceData($folder, $request), 'Folder created.', 201);
    }

    public function show(Request $request, Folder $folder): JsonResponse
    {
        $this->assertOwner($request, $folder);
        $this->assertNotTrashed($folder);

        return ApiResponse::success($this->resourceData($folder, $request));
    }

    public function details(Request $request, Folder $folder): JsonResponse
    {
        $this->assertOwner($request, $folder);
        $this->assertNotTrashed($folder);

        return ApiResponse::success((new FolderDetailsResource($folder->load(['parent', 'owner'])))->resolve($request));
    }

    public function update(UpdateFolderRequest $request, Folder $folder, FolderService $folders): JsonResponse
    {
        $this->assertOwner($request, $folder);
        $this->assertNotTrashed($folder);

        $folder = $folders->rename($request->user(), $folder, $request->string('name')->toString());

        return ApiResponse::success($this->resourceData($folder, $request), 'Folder renamed.');
    }

    public function breadcrumb(Request $request, Folder $folder, FolderService $folders): JsonResponse
    {
        $this->assertOwner($request, $folder);
        $this->assertNotTrashed($folder);

        return ApiResponse::success($folders->breadcrumb($folder->load('parent')));
    }

    private function assertOwner(Request $request, Folder $folder): void
    {
        abort_unless($folder->owner_id === $request->user()->getKey(), 404);
    }

    private function assertNotTrashed(Folder $folder): void
    {
        abort_if($folder->trashed_at !== null, 404);
    }

    private function resourceData(Folder|Collection $folders, Request $request): array
    {
        if ($folders instanceof Folder) {
            return (new FolderResource($folders))->resolve($request);
        }

        return $folders->map(fn (Folder $folder): array => (new FolderResource($folder))->resolve($request))->values()->all();
    }
}
