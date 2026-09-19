<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserStatus;
use App\Http\Requests\Admin\CreateInvitationRequest;
use App\Http\Requests\Admin\CreateUserRequest;
use App\Http\Resources\AuthUserResource;
use App\Models\User;
use App\Services\EmailVerificationService;
use App\Services\InvitationService;
use App\Services\SystemSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSetupController
{
    public function createInvitation(CreateInvitationRequest $request, InvitationService $invitations, SystemSettingService $settings): JsonResponse
    {
        [$user, $invitation, $token] = DB::transaction(function () use ($request, $invitations, $settings): array {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => mb_strtolower($request->string('email')->toString()),
                'password' => Hash::make(Str::random(64)),
                'role' => $request->string('role')->toString(),
                'status' => UserStatus::Pending,
                'quota_bytes' => $settings->getInt('storage.default_user_quota_bytes'),
                'used_bytes' => 0,
            ]);
            [$invitation, $token] = $invitations->issue($user);

            return [$user, $invitation, $token];
        });

        $invitations->send($invitation);

        return ApiResponse::success([
            'user' => new AuthUserResource($user),
            'invitation' => [
                'expiresAt' => $invitation->expires_at?->toISOString(),
                'url' => $invitations->url($token),
            ],
        ], 'Invitation created.', 201);
    }

    public function createUser(CreateUserRequest $request, EmailVerificationService $verification, SystemSettingService $settings): JsonResponse
    {
        [$user, $verificationToken] = DB::transaction(function () use ($request, $verification, $settings): array {
            $user = User::create([
                'name' => $request->string('name')->toString(),
                'email' => mb_strtolower($request->string('email')->toString()),
                'password' => $request->string('password')->toString(),
                'role' => $request->string('role')->toString(),
                'status' => UserStatus::Active,
                'quota_bytes' => $settings->getInt('storage.default_user_quota_bytes'),
                'used_bytes' => 0,
            ]);
            [$verificationToken, $token] = $verification->issue($user);

            return [$user, $verificationToken];
        });

        $verification->send($verificationToken);

        return ApiResponse::success([
            'user' => new AuthUserResource($user),
            'verification' => ['emailVerified' => false],
        ], 'User created successfully.', 201);
    }

    public function resendInvitation(User $user, InvitationService $invitations): JsonResponse
    {
        if ($user->status !== UserStatus::Pending) {
            return ApiResponse::error('This invitation is not available.', [], 410);
        }

        $invitation = $invitations->findValidForResend($user);

        if ($invitation === null) {
            return ApiResponse::error('This invitation is not available.', [], 410);
        }

        $invitations->send($invitation);

        return ApiResponse::success(null, 'Invitation sent successfully.');
    }

    public function regenerateInvitation(User $user, InvitationService $invitations): JsonResponse
    {
        if ($user->status !== UserStatus::Pending) {
            return ApiResponse::error('This invitation is not available.', [], 410);
        }

        [$invitation, $token] = DB::transaction(function () use ($user, $invitations): array {
            $invitations->revokeFor($user);

            return $invitations->issue($user);
        });
        $invitations->send($invitation);

        return ApiResponse::success([
            'invitation' => [
                'expiresAt' => $invitation->expires_at?->toISOString(),
                'url' => $invitations->url($token),
            ],
        ], 'Invitation regenerated.');
    }

    public function resendVerification(User $user, EmailVerificationService $verification): JsonResponse
    {
        if ($user->email_verified_at !== null) {
            return ApiResponse::success(null, 'Email is already verified.');
        }

        if ($user->status !== UserStatus::Active) {
            return ApiResponse::error('Verification is not available for this account.', [], 403);
        }

        $verificationToken = $verification->findValidForResend($user);
        if ($verificationToken === null) {
            [$verificationToken] = $verification->issue($user);
        }
        $verification->send($verificationToken);

        return ApiResponse::success(null, 'Verification email sent successfully.');
    }
}
