<?php

namespace App\Http\Controllers\Admin;

use App\Services\AdminOverviewService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminOverviewController
{
    public function __invoke(AdminOverviewService $overview): JsonResponse
    {
        return ApiResponse::success($overview->summarize());
    }
}
