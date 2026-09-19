<?php

namespace App\Http\Controllers;

use App\Http\Resources\FileResource;
use App\Models\File;
use App\Services\FileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileStarController
{
    public function store(Request $request, File $file, FileService $files): JsonResponse
    {
        $this->assertAccessible($request, $file);

        return ApiResponse::success(
            (new FileResource($files->star($request->user(), $file)))->resolve($request),
            'File starred.',
        );
    }

    public function destroy(Request $request, File $file, FileService $files): JsonResponse
    {
        $this->assertAccessible($request, $file);

        return ApiResponse::success(
            (new FileResource($files->unstar($request->user(), $file)))->resolve($request),
            'File unstarred.',
        );
    }

    private function assertAccessible(Request $request, File $file): void
    {
        abort_unless($file->owner_id === $request->user()->getKey(), 404);
        abort_if($file->trashed_at !== null, 404);
    }
}
