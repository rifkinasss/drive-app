<?php

namespace App\Exceptions;

use RuntimeException;

class ApplicationUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Drive is temporarily unavailable for maintenance.')
    {
        parent::__construct($message);
    }
}
