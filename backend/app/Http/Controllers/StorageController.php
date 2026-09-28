<?php

namespace App\Http\Controllers;

use App\Services\StorageSummaryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorageController
{
    public function __invoke(Request $request, StorageSummaryService $storage): JsonResponse
    {
        return ApiResponse::success($storage->summarize($request->user(), $request));
    }
}
