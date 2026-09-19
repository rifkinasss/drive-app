<?php

namespace App\Exceptions;

use RuntimeException;

class StorageQuotaExceededException extends RuntimeException
{
    public function __construct(
        public readonly int $quotaBytes,
        public readonly int $usedBytes,
        public readonly int $availableBytes,
        public readonly int $requiredBytes,
    ) {
        parent::__construct('Storage quota exceeded.');
    }
}
