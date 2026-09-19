<?php

namespace App\Http\Controllers;

use App\Http\Resources\PublicLinkResource;
use App\Services\PublicShareService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicLinkListController
{
    public function __invoke(Request $request, PublicShareService $public): JsonResponse
    {
        $status = $request->input('status');
        abort_unless($status === null || in_array($status, ['active', 'disabled', 'all'], true), 422);
        $items = $public->links($request->user(), $status === 'all' ? 'all' : $status, $request->input('search'));

        return ApiResponse::success(['items' => PublicLinkResource::collection($items)->resolve($request), 'meta' => ['count' => $items->count()]]);
    }
}
