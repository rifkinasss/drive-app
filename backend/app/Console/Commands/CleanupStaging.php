<?php

namespace App\Console\Commands;

use App\Services\StorageMaintenanceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CleanupStaging extends Command
{
    protected $signature = 'cloud:cleanup-upload-staging {--dry-run : Report only} {--execute : Delete eligible staging objects} {--older-than=24 : Minimum age in hours}';

    protected $description = 'Remove old upload or committed-delete staging objects; dry-run unless --execute is supplied.';

    public function handle(StorageMaintenanceService $maintenance): int
    {
        if ($this->option('execute') && $this->option('dry-run')) {
            $this->error('Choose either --dry-run or --execute.');

            return self::INVALID;
        }
        $hours = filter_var($this->option('older-than'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 24, 'max_range' => 8760]]);
        if ($hours === false) {
            $this->error('--older-than must be between 24 and 8760 hours.');

            return self::INVALID;
        }
        $kind = $this->kind();
        $lock = Cache::lock('cloud:cleanup-'.$kind.'-staging', 3600);
        if (! $lock->get()) {
            $this->warn('Another cleanup is already running.');

            return self::SUCCESS;
        }
        try {
            $result = $maintenance->cleanStaging($kind === 'upload' ? 'tmp' : 'tmp-delete-committed', $hours, (bool) $this->option('execute'));
        } finally {
            $lock->release();
        }
        $this->line(json_encode($result + ['dryRun' => ! $this->option('execute')], JSON_THROW_ON_ERROR));
        logger()->info('cloud.'.$kind.'_staging_cleanup.completed', $result + ['dry_run' => ! $this->option('execute')]);

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function kind(): string
    {
        return 'upload';
    }
}
