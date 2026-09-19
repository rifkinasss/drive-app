<?php

namespace App\Http\Controllers;

use App\Http\Requests\Trash\RestoreTrashRequest;
use App\Http\Resources\FolderResource;
use App\Models\Folder;
use App\Services\FileTrashService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderTrashController
{
    public function trash(Request $request, Folder $folder, FileTrashService $trash): JsonResponse
    {
        $this->authorize($request, $folder, 'trash');
        $folder = $trash->trashFolder($request->user(), $folder);

        return ApiResponse::success((new FolderResource($folder))->resolve($request), 'Folder moved to Trash.');
    }

    public function restore(RestoreTrashRequest $request, Folder $folder, FileTrashService $trash): JsonResponse
    {
        $this->authorize($request, $folder, 'restore');
        $folder = $trash->restoreFolder($request->user(), $folder, $request->input('conflictStrategy'));

        return ApiResponse::success((new FolderResource($folder))->resolve($request), 'Folder restored.');
    }

    public function permanent(Request $request, Folder $folder, FileTrashService $trash): JsonResponse
    {
        $this->authorize($request, $folder, 'forceDelete');
        $result = $trash->permanentFolder($request->user(), $folder);

        return ApiResponse::success($result, 'Folder permanently deleted.');
    }

    private function authorize(Request $request, Folder $folder, string $ability): void
    {
        abort_unless($request->user()->can($ability, $folder), 404);
    }
}
