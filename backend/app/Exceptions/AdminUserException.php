<?php

namespace App\Exceptions;

use RuntimeException;

class AdminUserException extends RuntimeException
{
    public function __construct(
        public readonly string $codeName,
        string $message,
        public readonly array $data = [],
        public readonly int $status = 422,
    ) {
        parent::__construct($message);
    }
}
