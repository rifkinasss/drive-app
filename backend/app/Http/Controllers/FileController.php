<?php

namespace App\Http\Controllers;

use App\Http\Requests\File\UpdateFileRequest;
use App\Http\Resources\FileResource;
use App\Models\File;
use App\Models\Folder;
use App\Services\FileService;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FileController
{
    public function index(Request $request): JsonResponse
    {
        $folderId = $request->input('folderId');
        $folder = null;

        if ($folderId !== null) {
            $folder = Folder::query()
                ->ownedBy($request->user())
                ->where('uuid', $folderId)
                ->notTrashed()
                ->first();

            if ($folder === null) {
                return ApiResponse::error('The folder is invalid.', ['folderId' => ['The folder is invalid.']], 422);
            }
        }

        $files = File::query()
            ->ownedBy($request->user())
            ->notTrashed()
            ->where('folder_id', $folder?->getKey())
            ->when($request->boolean('starred'), fn ($query) => $query->where('is_starred', true))
            ->with('folder')
            ->orderByRaw('LOWER(original_name) ASC')
            ->get();

        return ApiResponse::success($this->resourceData($files, $request));
    }

    public function show(Request $request, File $file): JsonResponse
    {
        $this->assertAccessible($request, $file);

        return ApiResponse::success($this->resourceData($file->load('folder'), $request));
    }

    public function update(UpdateFileRequest $request, File $file, FileService $files): JsonResponse
    {
        abort_unless($request->user()->can('update', $file), 404);

        $file = $files->rename($file->owner, $file, $request->string('name')->toString(), $request->user());

        return ApiResponse::success($this->resourceData($file, $request), 'File renamed.');
    }

    private function assertAccessible(Request $request, File $file): void
    {
        abort_unless($request->user()->can('view', $file), 404);
        abort_if($file->trashed_at !== null, 404);
    }

    private function resourceData(File|Collection $files, Request $request): array
    {
        if ($files instanceof File) {
            return (new FileResource($files))->resolve($request);
        }

        return $files->map(fn (File $file): array => (new FileResource($file))->resolve($request))->values()->all();
    }
}
