<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\InvitationNotification;
use App\Support\SecureToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InvitationService
{
    public function issue(User $user): array
    {
        $token = SecureToken::generate();
        $invitation = $user->invitations()->create([
            'token_hash' => $token['hash'],
            'token_ciphertext' => $token['plainText'],
            'expires_at' => now()->addHours(config('cloud.invitation_ttl_hours')),
        ]);

        return [$invitation, $token['plainText']];
    }

    public function send(UserInvitation $invitation): void
    {
        $invitation->user->notify(new InvitationNotification($invitation, $invitation->token_ciphertext));
        $invitation->forceFill(['sent_at' => now()])->save();
    }

    public function findValid(string $token): ?UserInvitation
    {
        $invitation = UserInvitation::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();

        return $invitation?->user?->status->value === 'pending' ? $invitation : null;
    }

    public function findValidForResend(User $user): ?UserInvitation
    {
        return $user->invitations()
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function accept(string $token, string $password): bool
    {
        return DB::transaction(function () use ($token, $password): bool {
            $invitation = UserInvitation::query()
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->with('user')
                ->first();

            if ($invitation === null || $invitation->user?->status->value !== 'pending') {
                return false;
            }

            $invitation->user->forceFill([
                'password' => Hash::make($password),
                'status' => 'active',
                'email_verified_at' => now(),
            ])->save();
            $invitation->forceFill(['accepted_at' => now()])->save();

            return true;
        });
    }

    public function revokeFor(User $user): void
    {
        $user->invitations()->whereNull('accepted_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function url(string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/'.config('cloud.invitation_url_path').'/'.$token;
    }
}
