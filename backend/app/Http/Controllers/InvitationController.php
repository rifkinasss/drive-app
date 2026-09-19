<?php

namespace App\Http\Controllers;

use App\Http\Requests\Invitation\AcceptInvitationRequest;
use App\Services\InvitationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class InvitationController
{
    public function preview(string $token, InvitationService $invitations): JsonResponse
    {
        $invitation = $invitations->findValid($token);

        if ($invitation === null) {
            return ApiResponse::error('This invitation is not available.', [], 410);
        }

        return ApiResponse::success([
            'name' => $invitation->user->name,
            'email' => $invitation->user->email,
            'expiresAt' => $invitation->expires_at?->toISOString(),
        ]);
    }

    public function accept(string $token, AcceptInvitationRequest $request, InvitationService $invitations): JsonResponse
    {
        if (! $invitations->accept($token, $request->string('password')->toString())) {
            return ApiResponse::error('This invitation is not available.', [], 410);
        }

        return ApiResponse::success(null, 'Account activated successfully.');
    }
}
