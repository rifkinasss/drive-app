<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class ShareTokenService
{
    public function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function encrypt(string $token): string
    {
        return Crypt::encryptString($token);
    }

    public function decrypt(string $encrypted): string
    {
        return Crypt::decryptString($encrypted);
    }
}
