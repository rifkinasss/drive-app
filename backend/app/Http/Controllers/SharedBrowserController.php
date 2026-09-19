<?php

namespace App\Http\Controllers;

use App\Http\Requests\BrowserRequest;
use App\Http\Resources\FileResource;
use App\Models\Folder;
use App\Services\SharedBrowserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SharedBrowserController
{
    public function __invoke(BrowserRequest $request, Folder $folder, SharedBrowserService $browser): JsonResponse
    {
        $result = $browser->browse($request->user(), $folder, $request->input('sort', 'name'), $request->input('direction', 'asc'), $request->input('search'));

        return ApiResponse::success([
            'sharedRoot' => $result['sharedRoot'],
            'currentFolder' => $result['currentFolder'],
            'breadcrumb' => $result['breadcrumb'],
            'folders' => $result['folders'],
            'files' => FileResource::collection($result['files'])->resolve($request),
            'permission' => $result['permission'],
            'meta' => $result['meta'],
        ]);
    }
}
