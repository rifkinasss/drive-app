<?php

namespace App\Exceptions;

use RuntimeException;

class PublicShareUnavailableException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('This shared item is unavailable.');
    }
}
