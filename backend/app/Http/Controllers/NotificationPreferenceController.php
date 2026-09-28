<?php

namespace App\Http\Controllers;

use App\Models\NotificationPreference;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController
{
    public function show(Request $request): JsonResponse
    {
        $preferences = NotificationPreference::query()->firstOrCreate(['user_id' => $request->user()->getKey()]);

        return ApiResponse::success($this->payload($preferences));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shares' => ['sometimes', 'boolean'],
            'quota' => ['sometimes', 'boolean'],
            'accountSecurity' => ['sometimes', 'boolean'],
        ]);
        $preferences = NotificationPreference::query()->firstOrCreate(['user_id' => $request->user()->getKey()]);
        $preferences->fill(array_filter([
            'shares' => $validated['shares'] ?? null,
            'quota' => $validated['quota'] ?? null,
            'account_security' => $validated['accountSecurity'] ?? null,
        ], static fn (mixed $value): bool => $value !== null));
        $preferences->save();

        return ApiResponse::success($this->payload($preferences));
    }

    private function payload(NotificationPreference $preferences): array
    {
        return [
            'shares' => $preferences->shares,
            'quota' => $preferences->quota,
            'accountSecurity' => $preferences->account_security,
        ];
    }
}
