<?php

namespace App\Console\Commands;

class CleanupDeleteStaging extends CleanupStaging
{
    protected $signature = 'cloud:cleanup-delete-staging {--dry-run : Report only} {--execute : Delete eligible staging objects} {--older-than=24 : Minimum age in hours}';

    protected $description = 'Remove old committed-delete staging objects; dry-run unless --execute is supplied.';

    protected function kind(): string
    {
        return 'delete';
    }
}
