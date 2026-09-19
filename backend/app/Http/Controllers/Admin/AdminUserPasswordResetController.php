<?php

namespace App\Http\Controllers\Admin;

use App\Models\User;
use App\Services\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminUserPasswordResetController
{
    public function __invoke(User $user, AdminUserService $users): JsonResponse
    {
        $users->sendPasswordReset($user);

        return ApiResponse::success(null, 'Password reset email sent successfully.');
    }
}
