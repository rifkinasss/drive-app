<?php

namespace App\Console\Commands;

use App\Services\StorageMaintenanceService;
use Illuminate\Console\Command;
use Throwable;

class StorageAudit extends Command
{
    protected $signature = 'cloud:storage-audit';

    protected $description = 'Audit managed storage for missing objects and unreferenced physical objects; never deletes data.';

    public function handle(StorageMaintenanceService $maintenance): int
    {
        try {
            $result = $maintenance->audit();
        } catch (Throwable $exception) {
            report($exception);
            $this->error('Storage audit failed; see application logs.');

            return self::FAILURE;
        }

        $this->line(json_encode($result, JSON_THROW_ON_ERROR));
        logger()->info('cloud.storage_audit.completed', $result);

        return self::SUCCESS;
    }
}
