<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:512'],
            'keys.auth' => ['required', 'string', 'max:512'],
        ]);
        PushSubscription::query()->updateOrCreate(
            ['user_id' => $request->user()->getKey(), 'endpoint' => $validated['endpoint']],
            ['p256dh' => $validated['keys']['p256dh'], 'auth' => $validated['keys']['auth'], 'user_agent' => $request->userAgent()],
        );

        return ApiResponse::success(['subscribed' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['endpoint' => ['required', 'string', 'url', 'max:2048']]);
        PushSubscription::query()->where('user_id', $request->user()->getKey())->where('endpoint', $validated['endpoint'])->delete();

        return ApiResponse::success(['subscribed' => false]);
    }
}
