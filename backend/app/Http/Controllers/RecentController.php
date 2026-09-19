<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecentRequest;
use App\Http\Resources\CollectionItemResource;
use App\Services\RecentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class RecentController
{
    public function __invoke(RecentRequest $request, RecentService $recent): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);

        return ApiResponse::success([
            'items' => CollectionItemResource::collection($recent->files($request->user(), $limit))->resolve($request),
            'meta' => ['limit' => min(max($limit, 1), 100)],
        ]);
    }
}
