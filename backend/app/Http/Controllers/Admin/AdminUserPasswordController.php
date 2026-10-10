<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\SetUserPasswordRequest;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminUserPasswordController
{
    public function __invoke(SetUserPasswordRequest $request, User $user, AdminUserService $users): JsonResponse
    {
        return ApiResponse::success(new AdminUserResource($users->setPassword($request->user(), $user, $request->string('password')->toString())), 'Password updated successfully.');
    }
}
