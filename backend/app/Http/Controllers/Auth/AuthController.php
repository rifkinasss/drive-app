<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;

class AuthController
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->string('email')->toString())->first();
        $remember = $request->boolean('remember');

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->getAuthPassword())) {
            return ApiResponse::error('The provided credentials are invalid.', [], 401);
        }

        if ($user->status === UserStatus::Pending) {
            return ApiResponse::error('This account is not active yet.', [], 403);
        }

        if ($user->status === UserStatus::Disabled) {
            return ApiResponse::error('This account has been disabled.', [], 403);
        }

        Auth::guard('web')->login($user, $remember);
        if (Schema::hasColumn('users', 'last_active_at')) {
            $user->forceFill(['last_active_at' => now()])->save();
        }

        $request->session()->regenerate();
        RateLimiter::clear($this->loginThrottleKey($request));

        return ApiResponse::success(
            ['user' => new AuthUserResource($request->user())],
            'Signed in successfully.',
        );
    }

    public function user(Request $request): JsonResponse
    {
        return ApiResponse::success(['user' => new AuthUserResource($request->user())]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();
        $user->forceFill(['name' => trim($validated['name'])])->save();

        return ApiResponse::success(['user' => new AuthUserResource($user->fresh())], 'Profile updated.');
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::success(null, 'Signed out successfully.');
    }

    private function loginThrottleKey(Request $request): string
    {
        return mb_strtolower(trim((string) $request->input('email'))).'|'.$request->ip();
    }
}
