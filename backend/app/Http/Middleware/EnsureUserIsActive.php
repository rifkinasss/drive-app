<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $authenticatedUser = $request->user();
        $user = $authenticatedUser instanceof User
            ? User::query()->find($authenticatedUser->getAuthIdentifier())
            : null;

        if ($user?->status === UserStatus::Active) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        Auth::forgetGuards();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return ApiResponse::error('This account is not active.', [], 403);
    }
}
