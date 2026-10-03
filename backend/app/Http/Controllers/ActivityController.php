<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivityRequest;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ActivityController
{
    public function __invoke(ActivityRequest $request): JsonResponse
    {
        $limit = (int) $request->input('limit', 20);
        $query = Activity::query()->where('user_id', $request->user()->getKey())->with('actor')
            ->orderByDesc('created_at')->orderByDesc('id');

        if ($request->filled('action')) {
            $query->whereIn('action', (array) $request->input('action'));
        }
        if ($request->filled('type')) {
            if ($request->input('type') === 'system') {
                $query->whereNull('subject_type');
            } else {
                $query->where('subject_type', $request->input('type'));
            }
        }
        if ($request->filled('resource_type') && $request->filled('resource_id')) {
            $query->where('subject_type', $request->input('resource_type'))
                ->where('subject_uuid', $request->input('resource_id'));
        }
        if ($request->filled('from')) {
            $query->where('created_at', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->where('created_at', '<=', $request->date('to'));
        }
        if ($request->filled('search')) {
            $query->whereRaw('LOWER(subject_name) LIKE ?', ['%'.mb_strtolower($request->input('search')).'%']);
        }

        $page = $query->cursorPaginate(min(max($limit, 1), 100))->withQueryString();

        return ApiResponse::success([
            'items' => ActivityResource::collection($page->getCollection())->resolve($request),
            'meta' => [
                'perPage' => $page->perPage(),
                'nextCursor' => $page->nextCursor()?->encode(),
                'previousCursor' => $page->previousCursor()?->encode(),
            ],
        ]);
    }
}
