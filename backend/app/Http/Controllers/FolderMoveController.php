<?php

namespace App\Http\Controllers;

use App\Http\Requests\Folder\MoveFolderRequest;
use App\Http\Resources\FolderResource;
use App\Models\Folder;
use App\Services\FolderService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FolderMoveController
{
    public function __invoke(MoveFolderRequest $request, Folder $folder, FolderService $folders): JsonResponse
    {
        abort_unless($folder->owner_id === $request->user()->getKey(), 404);
        abort_if($folder->trashed_at !== null, 404);

        $folder = $folders->move($request->user(), $folder, $request->input('parentId'));

        return ApiResponse::success((new FolderResource($folder))->resolve($request), 'Folder moved.');
    }
}
