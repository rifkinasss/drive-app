<?php

namespace App\Http\Controllers;

use App\Exceptions\FileNameConflictException;
use App\Http\Requests\File\UploadFileRequest;
use App\Http\Resources\FileResource;
use App\Services\UploadFileService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class FileUploadController
{
    public function __invoke(UploadFileRequest $request, UploadFileService $uploads): JsonResponse
    {
        try {
            $file = $uploads->upload(
                $request->user(),
                $request->file('file'),
                $request->input('folderId'),
                $request->input('conflictStrategy'),
            );
        } catch (FileNameConflictException $exception) {
            return ApiResponse::conflict('A file with this name already exists.', [
                'existingFileId' => $exception->existingFile->uuid,
                'name' => $exception->existingFile->original_name,
            ]);
        }

        return ApiResponse::success((new FileResource($file))->resolve($request), 'File uploaded.', 201);
    }
}
