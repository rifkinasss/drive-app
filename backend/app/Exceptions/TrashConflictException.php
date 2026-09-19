<?php

namespace App\Exceptions;

use RuntimeException;

class TrashConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $type,
        public readonly string $existingId,
        public readonly string $name,
    ) {
        parent::__construct('Restore would create a duplicate name.');
    }
}
