<?php

namespace App\Http\Controllers;

use App\Http\Requests\File\MoveFileRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Services\FileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FileMoveController
{
    public function __invoke(MoveFileRequest $request, File $file, FileService $files): JsonResponse
    {
        abort_unless($file->owner_id === $request->user()->getKey(), 404);
        abort_if($file->trashed_at !== null, 404);

        $file = $files->move($request->user(), $file, $request->input('folderId'));

        return ApiResponse::success((new FileResource($file))->resolve($request), 'File moved.');
    }
}
