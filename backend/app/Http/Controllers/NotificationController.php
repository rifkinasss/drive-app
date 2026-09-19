<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationIndexRequest;
use App\Http\Resources\NotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController
{
    public function index(NotificationIndexRequest $request): JsonResponse
    {
        $query = $request->user()->notifications()->latest('created_at')->latest('id');
        $status = $request->input('status', 'all');
        if ($status === 'unread') {
            $query->whereNull('read_at');
        } elseif ($status === 'read') {
            $query->whereNotNull('read_at');
        }
        $page = $query->paginate((int) $request->input('perPage', 20), ['*'], 'page', (int) $request->input('page', 1));

        return ApiResponse::success([
            'items' => NotificationResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'currentPage' => $page->currentPage(),
                'lastPage' => $page->lastPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return ApiResponse::success(['unreadCount' => $request->user()->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $owned = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        if ($owned->read_at === null) {
            $owned->markAsRead();
        }

        return ApiResponse::success(['notification' => (new NotificationResource($owned->fresh()))->resolve($request)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return ApiResponse::success(['unreadCount' => 0]);
    }
}
