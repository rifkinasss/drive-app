<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class EmailVerificationController
{
    public function preview(string $token, EmailVerificationService $verification): JsonResponse
    {
        $verificationToken = $verification->findValid($token);

        if ($verificationToken === null) {
            return ApiResponse::error('This verification link is not available.', [], 410);
        }

        return ApiResponse::success([
            'email' => $verificationToken->user->email,
            'expiresAt' => $verificationToken->expires_at?->toISOString(),
        ]);
    }

    public function verify(string $token, EmailVerificationService $verification): JsonResponse
    {
        if (! $verification->verify($token)) {
            return ApiResponse::error('This verification link is not available.', [], 410);
        }

        return ApiResponse::success(null, 'Email verified successfully.');
    }
}
