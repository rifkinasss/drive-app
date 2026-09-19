<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserSearchController
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->input('q', ''));
        if (mb_strlen($query) < 2) {
            return ApiResponse::success(['items' => [], 'meta' => ['limit' => 10]]);
        }

        $items = User::query()->where('status', 'active')->whereKeyNot($request->user()->getKey())
            ->where(fn ($builder) => $builder->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($query).'%'])->orWhereRaw('LOWER(email) LIKE ?', ['%'.mb_strtolower($query).'%']))
            ->orderBy('name')->limit(10)->get(['id', 'name', 'email'])
            ->map(fn (User $user): array => ['id' => $user->getKey(), 'name' => $user->name, 'email' => $user->email])->values();

        return ApiResponse::success(['items' => $items, 'meta' => ['limit' => 10]]);
    }
}
