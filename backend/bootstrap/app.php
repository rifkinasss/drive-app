<?php

use App\Exceptions\AdminUserException;
use App\Exceptions\ApplicationUnavailableException;
use App\Exceptions\FileNameConflictException;
use App\Exceptions\PublicShareUnavailableException;
use App\Exceptions\ShareConflictException;
use App\Exceptions\StorageQuotaExceededException;
use App\Exceptions\TrashConflictException;
use App\Http\Middleware\EnsureApplicationAvailable;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([__DIR__.'/../app/Console/Commands'])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->statefulApi();
        $middleware->throttleApi();
        $middleware->alias([
            'active.account' => EnsureUserIsActive::class,
            'admin' => EnsureUserIsAdmin::class,
            'available' => EnsureApplicationAvailable::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->expectsJson() || $request->is('api/*'),
        );

        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('Unauthenticated.', [], 401);
        });

        $exceptions->render(function (AdminUserException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::domainError($exception->getMessage(), $exception->codeName, $exception->data, $exception->status);
        });

        $exceptions->render(function (ApplicationUnavailableException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($exception->getMessage(), [], 503);
        });

        $exceptions->render(function (ValidationException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error('Validation failed.', $exception->errors(), 422);
        });

        $exceptions->render(function (FileNameConflictException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::conflict('A file with this name already exists.', [
                'existingFileId' => $exception->existingFile->uuid,
                'name' => $exception->existingFile->original_name,
            ]);
        });

        $exceptions->render(function (ShareConflictException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($exception->getMessage(), [], 409);
        });

        $exceptions->render(function (PublicShareUnavailableException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::error($exception->getMessage(), [], 404);
        });

        $exceptions->render(function (StorageQuotaExceededException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::quotaExceeded([
                'quotaBytes' => $exception->quotaBytes,
                'usedBytes' => $exception->usedBytes,
                'availableBytes' => $exception->availableBytes,
                'requiredBytes' => $exception->requiredBytes,
            ]);
        });

        $exceptions->render(function (TrashConflictException $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return ApiResponse::restoreConflict([
                'type' => $exception->type,
                'existingId' => $exception->existingId,
                'name' => $exception->name,
            ]);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $message = match ($exception->getStatusCode()) {
                404 => 'Resource not found.',
                405 => 'Method not allowed.',
                default => $exception->getStatusCode() >= 500
                    ? 'Server error.'
                    : ($exception->getMessage() ?: 'Request failed.'),
            };

            return ApiResponse::error($message, [], $exception->getStatusCode());
        });

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*') || $exception instanceof HttpResponseException) {
                return null;
            }

            report($exception);

            return ApiResponse::error('Server error.', [], 500);
        });
    })
    ->create();
