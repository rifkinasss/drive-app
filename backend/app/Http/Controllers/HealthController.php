<?php

namespace App\Http\Controllers;

use App\Services\SystemSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class HealthController
{
    public function __invoke(SystemSettingService $settings): JsonResponse
    {
        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            return ApiResponse::error('Service unavailable.', [
                'database' => ['Database connection is unavailable.'],
            ], 503);
        }

        $storageStatus = 'active';
        try {
            Storage::disk('cloud')->exists('__drive_health_check__');
        } catch (Throwable) {
            $storageStatus = 'unavailable';
        }

        $queueDriver = (string) config('queue.default', '');
        $queueStatus = $queueDriver === '' ? 'not_configured' : 'active';
        $overallStatus = $storageStatus === 'unavailable' ? 'degraded' : 'ok';
        $databaseDriver = match (DB::connection()->getDriverName()) {
            'pgsql' => 'PostgreSQL',
            'mysql', 'mariadb' => 'MySQL',
            'sqlite' => 'SQLite',
            default => 'Database',
        };

        return ApiResponse::success([
            'status' => $overallStatus,
            'version' => (string) config('docs.version', '2.0.0'),
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'maintenance' => $settings->getBool('maintenance.enabled'),
            'services' => [
                'database' => ['status' => 'connected', 'driver' => $databaseDriver],
                'storage' => ['status' => $storageStatus],
                'queue' => ['status' => $queueStatus, 'driver' => $queueDriver === 'database' ? 'database' : null],
            ],
        ]);
    }
}
