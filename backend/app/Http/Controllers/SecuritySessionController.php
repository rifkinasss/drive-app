<?php

namespace App\Http\Controllers;

use App\Services\SecuritySessionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecuritySessionController
{
    public function index(Request $request, SecuritySessionService $sessions): JsonResponse
    {
        return ApiResponse::success(['items' => $sessions->list($request->user(), $request->session()->getId())]);
    }

    public function destroy(Request $request, string $session, SecuritySessionService $sessions): JsonResponse
    {
        $result = $sessions->revoke($request->user(), $session, $request->session()->getId());

        return match ($result) {
            'revoked' => ApiResponse::success(null, 'Session revoked.'),
            'current' => ApiResponse::domainError('The current session cannot be revoked.', 'CURRENT_SESSION_REVOKE_FORBIDDEN', [], 422),
            default => ApiResponse::error('Session not found.', [], 404),
        };
    }

    public function destroyOthers(Request $request, SecuritySessionService $sessions): JsonResponse
    {
        $count = $sessions->revokeOthers($request->user(), $request->session()->getId());

        return ApiResponse::success(['revokedCount' => $count], 'Other sessions revoked.');
    }
}
