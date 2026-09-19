<?php

namespace App\Http\Controllers\Admin;

use App\Services\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminUserSummaryController
{
    public function __invoke(AdminUserService $users): JsonResponse
    {
        return ApiResponse::success($users->summary());
    }
}
