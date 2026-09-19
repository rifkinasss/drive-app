<?php

namespace App\Http\Controllers;

use App\Http\Resources\FolderResource;
use App\Models\Folder;
use App\Services\FolderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderStarController
{
    public function store(Request $request, Folder $folder, FolderService $folders): JsonResponse
    {
        $this->authorize($request, $folder);
        $folder = $folders->star($request->user(), $folder);

        return ApiResponse::success((new FolderResource($folder->fresh(['parent'])))->resolve($request), 'Folder starred.');
    }

    public function destroy(Request $request, Folder $folder, FolderService $folders): JsonResponse
    {
        $this->authorize($request, $folder);
        $folder = $folders->unstar($request->user(), $folder);

        return ApiResponse::success((new FolderResource($folder->fresh(['parent'])))->resolve($request), 'Folder unstarred.');
    }

    private function authorize(Request $request, Folder $folder): void
    {
        abort_unless($request->user()->can('update', $folder), 404);
        abort_if($folder->trashed_at !== null, 404);
    }
}
