<?php

namespace App\Http\Controllers;

use App\Enums\SharePermission;
use App\Http\Requests\ShareRequest;
use App\Http\Requests\UpdateShareRequest;
use App\Http\Resources\ShareResource;
use App\Models\File;
use App\Models\Folder;
use App\Models\InternalShare;
use App\Models\User;
use App\Services\InternalShareService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShareController
{
    public function fileStore(ShareRequest $request, File $file, InternalShareService $shares): JsonResponse
    {
        $this->owner($request->user(), $file->owner_id);
        $share = $shares->create($request->user(), $file, (int) $request->input('recipientId'), $request->enum('permission', SharePermission::class));

        return ApiResponse::success((new ShareResource($share))->resolve($request), 'File shared.', 201);
    }

    public function folderStore(ShareRequest $request, Folder $folder, InternalShareService $shares): JsonResponse
    {
        $this->owner($request->user(), $folder->owner_id);
        $share = $shares->create($request->user(), $folder, (int) $request->input('recipientId'), $request->enum('permission', SharePermission::class));

        return ApiResponse::success((new ShareResource($share))->resolve($request), 'Folder shared.', 201);
    }

    public function fileIndex(Request $request, File $file, InternalShareService $shares): JsonResponse
    {
        $this->owner($request->user(), $file->owner_id);

        return ApiResponse::success(ShareResource::collection($shares->list($request->user(), $file))->resolve($request));
    }

    public function folderIndex(Request $request, Folder $folder, InternalShareService $shares): JsonResponse
    {
        $this->owner($request->user(), $folder->owner_id);

        return ApiResponse::success(ShareResource::collection($shares->list($request->user(), $folder))->resolve($request));
    }

    public function update(UpdateShareRequest $request, InternalShare $share, InternalShareService $shares): JsonResponse
    {
        $this->owner($request->user(), $share->owner_id);
        $share = $shares->update($request->user(), $share, $request->enum('permission', SharePermission::class));

        return ApiResponse::success((new ShareResource($share))->resolve($request), 'Share permission updated.');
    }

    public function destroy(Request $request, InternalShare $share, InternalShareService $shares): JsonResponse
    {
        $this->owner($request->user(), $share->owner_id);
        $shares->revoke($request->user(), $share);

        return ApiResponse::success(null, 'Share revoked.');
    }

    private function owner(User $user, int $ownerId): void
    {
        abort_unless($user->getKey() === $ownerId, 404);
    }
}
