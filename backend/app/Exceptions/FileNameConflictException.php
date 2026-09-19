<?php

namespace App\Exceptions;

use App\Models\File;
use RuntimeException;

class FileNameConflictException extends RuntimeException
{
    public function __construct(public readonly File $existingFile)
    {
        parent::__construct('A file with this name already exists.');
    }
}
