<?php

namespace App\Http\Controllers;

use App\Http\Requests\Trash\RestoreTrashRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Services\FileTrashService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileTrashController
{
    public function trash(Request $request, File $file, FileTrashService $trash): JsonResponse
    {
        $this->authorize($request, $file, 'trash');
        $file = $trash->trashFile($request->user(), $file);

        return ApiResponse::success((new FileResource($file))->resolve($request), 'File moved to Trash.');
    }

    public function restore(RestoreTrashRequest $request, File $file, FileTrashService $trash): JsonResponse
    {
        $this->authorize($request, $file, 'restore');
        $file = $trash->restoreFile($request->user(), $file, $request->input('conflictStrategy'));

        return ApiResponse::success((new FileResource($file))->resolve($request), 'File restored.');
    }

    public function permanent(Request $request, File $file, FileTrashService $trash): JsonResponse
    {
        $this->authorize($request, $file, 'forceDelete');
        $result = $trash->permanentFile($request->user(), $file);

        return ApiResponse::success($result, 'File permanently deleted.');
    }

    private function authorize(Request $request, File $file, string $ability): void
    {
        abort_unless($request->user()->can($ability, $file), 404);
    }
}
