<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\UpdateSystemSettingsRequest;
use App\Services\SystemSettingService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class SystemSettingController
{
    public function index(SystemSettingService $settings): JsonResponse
    {
        return ApiResponse::success($settings->allGroups());
    }

    public function show(string $group, SystemSettingService $settings): JsonResponse
    {
        return ApiResponse::success(['group' => $group, 'settings' => $settings->getGroup($group)]);
    }

    public function update(UpdateSystemSettingsRequest $request, string $group, SystemSettingService $settings): JsonResponse
    {
        return ApiResponse::success([
            'group' => $group,
            'settings' => $settings->setGroup($group, $request->validated('settings'), $request->user()->getKey()),
        ], 'System settings updated successfully.');
    }
}
