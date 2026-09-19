<?php

namespace App\Exceptions;

use RuntimeException;

class ApplicationUnavailableException extends RuntimeException
{
    public function __construct(string $message = 'Cloud is temporarily unavailable for maintenance.')
    {
        parent::__construct($message);
    }
}
