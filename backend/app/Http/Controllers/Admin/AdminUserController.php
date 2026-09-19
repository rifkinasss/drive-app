<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\ListUsersRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Http\Resources\AdminUserDetailResource;
use App\Http\Resources\AdminUserResource;
use App\Models\User;
use App\Services\AdminUserService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AdminUserController
{
    public function index(ListUsersRequest $request, AdminUserService $users): JsonResponse
    {
        $page = $users->list($request->validated());

        return ApiResponse::success([
            'items' => AdminUserResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(User $user, AdminUserService $users): JsonResponse
    {
        return ApiResponse::success(new AdminUserDetailResource($users->detail($user)));
    }

    public function update(UpdateUserRequest $request, User $user, AdminUserService $users): JsonResponse
    {
        return ApiResponse::success(new AdminUserResource($users->update($user, $request->validated())), 'User updated successfully.');
    }

    public function destroy(User $user, AdminUserService $users): JsonResponse
    {
        $users->delete(request()->user(), $user);

        return ApiResponse::success(null, 'User deleted successfully.');
    }
}
