<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrowserRequest;
use App\Http\Resources\FileResource;
use App\Http\Resources\FolderResource;
use App\Services\BrowserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class BrowserController
{
    public function __invoke(BrowserRequest $request, BrowserService $browser): JsonResponse
    {
        $result = $browser->browse(
            $request->user(),
            $request->input('folderId'),
            $request->input('sort', 'name'),
            $request->input('direction', 'asc'),
            $request->input('search'),
        );

        return ApiResponse::success([
            'currentFolder' => $result['currentFolder'] ? (new FolderResource($result['currentFolder']))->resolve($request) : null,
            'breadcrumb' => $result['breadcrumb'],
            'folders' => FolderResource::collection($result['folders'])->resolve($request),
            'files' => FileResource::collection($result['files'])->resolve($request),
            'meta' => $result['meta'],
        ]);
    }
}
