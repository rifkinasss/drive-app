<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
        ], $status);
    }

    public static function error(string $message, array $errors = [], int $status = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }

    public static function domainError(string $message, string $code, array $data = [], int $status = 422): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ], $status);
    }

    public static function conflict(string $message, array $data): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'code' => 'FILE_NAME_CONFLICT',
            'data' => $data,
        ], 409);
    }

    public static function quotaExceeded(array $data): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Storage quota exceeded.',
            'code' => 'STORAGE_QUOTA_EXCEEDED',
            'data' => $data,
        ], 422);
    }

    public static function restoreConflict(array $data): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Restore would create a duplicate name.',
            'code' => 'RESTORE_CONFLICT',
            'data' => $data,
        ], 409);
    }
}
