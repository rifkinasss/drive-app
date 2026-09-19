<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\EmailVerificationToken;
use App\Models\User;
use App\Notifications\EmailVerificationNotification;
use App\Support\SecureToken;

class EmailVerificationService
{
    public function issue(User $user): array
    {
        $token = SecureToken::generate();
        $verificationToken = $user->emailVerificationTokens()->create([
            'token_hash' => $token['hash'],
            'token_ciphertext' => $token['plainText'],
            'expires_at' => now()->addHours(config('cloud.verification_ttl_hours')),
        ]);

        return [$verificationToken, $token['plainText']];
    }

    public function send(EmailVerificationToken $verificationToken): void
    {
        $verificationToken->user->notify(new EmailVerificationNotification($verificationToken, $verificationToken->token_ciphertext));
        $verificationToken->forceFill(['sent_at' => now()])->save();
    }

    public function findValid(string $token): ?EmailVerificationToken
    {
        $verificationToken = EmailVerificationToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('verified_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->with('user')
            ->first();

        return in_array($verificationToken?->user?->status, [UserStatus::Active, UserStatus::Disabled], true)
            ? $verificationToken
            : null;
    }

    public function findValidForResend(User $user): ?EmailVerificationToken
    {
        return $user->emailVerificationTokens()
            ->whereNull('verified_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    public function verify(string $token): bool
    {
        $verificationToken = $this->findValid($token);

        if ($verificationToken === null) {
            return false;
        }

        $verificationToken->user->forceFill(['email_verified_at' => now()])->save();
        $verificationToken->forceFill(['verified_at' => now()])->save();

        return true;
    }

    public function revokeFor(User $user): void
    {
        $user->emailVerificationTokens()->whereNull('verified_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }

    public function url(string $token): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/'.config('cloud.verification_url_path').'/'.$token;
    }
}
