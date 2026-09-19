<?php

namespace App\Support;

final class SecureToken
{
    public static function generate(): array
    {
        $plainText = bin2hex(random_bytes(32));

        return [
            'plainText' => $plainText,
            'hash' => hash('sha256', $plainText),
        ];
    }
}
