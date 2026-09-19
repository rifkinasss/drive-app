<?php

namespace App\Http\Controllers;

use App\Http\Resources\CollectionItemResource;
use App\Services\StarredService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StarredController
{
    public function __invoke(Request $request, StarredService $starred): JsonResponse
    {
        return ApiResponse::success([
            'items' => CollectionItemResource::collection($starred->items($request->user()))->resolve($request),
        ]);
    }
}
