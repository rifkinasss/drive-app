<?php

namespace App\Enums;

enum SharePermission: string
{
    case Viewer = 'viewer';
    case Editor = 'editor';

    public function rank(): int
    {
        return $this === self::Editor ? 2 : 1;
    }
}
