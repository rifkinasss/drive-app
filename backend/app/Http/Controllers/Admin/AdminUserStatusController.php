<?php

namespace App\Http\Controllers\Admin;

use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminUserStatusController
{
    public function disable(User $user, AdminUserService $users): JsonResponse
    {
        return ApiResponse::success(new AdminUserResource($users->disable(request()->user(), $user)), 'User disabled successfully.');
    }

    public function enable(User $user, AdminUserService $users): JsonResponse
    {
        return ApiResponse::success(new AdminUserResource($users->enable($user)), 'User enabled successfully.');
    }

    public function verify(User $user, AdminUserService $users): JsonResponse
    {
        return ApiResponse::success(new AdminUserResource($users->verifyEmail($user)), 'Email verified successfully.');
    }

    public function unverify(User $user, AdminUserService $users): JsonResponse
    {
        return ApiResponse::success(new AdminUserResource($users->unverifyEmail(request()->user(), $user)), 'Email verification removed.');
    }
}
