<?php

namespace App\Http\Controllers;

use App\Services\SystemSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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

        return ApiResponse::success([
            'status' => 'ok',
            'service' => 'cloud-api',
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'maintenance' => $settings->getBool('maintenance.enabled'),
            'checks' => ['database' => 'ok'],
        ]);
    }
}
