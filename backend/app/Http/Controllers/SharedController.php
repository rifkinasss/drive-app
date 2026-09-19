<?php

namespace App\Http\Controllers;

use App\Http\Resources\SharedItemResource;
use App\Services\InternalShareService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SharedController
{
    public function withMe(Request $request, InternalShareService $shares): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 20), 1), 100);
        $page = $shares->sharedWithMe($request->user(), $limit, $request->input('search'));

        return ApiResponse::success([
            'items' => SharedItemResource::collection($page->getCollection())->resolve($request),
            'meta' => ['perPage' => $page->perPage(), 'nextCursor' => $page->nextCursor()?->encode(), 'previousCursor' => $page->previousCursor()?->encode()],
        ]);
    }

    public function byMe(Request $request, InternalShareService $shares): JsonResponse
    {
        $limit = min(max((int) $request->input('limit', 20), 1), 100);
        $page = $shares->sharedByMe($request->user(), $limit, $request->input('search'));

        return ApiResponse::success([
            'items' => SharedItemResource::collection($page->getCollection())->resolve($request),
            'meta' => ['currentPage' => $page->currentPage(), 'perPage' => $page->perPage(), 'lastPage' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
