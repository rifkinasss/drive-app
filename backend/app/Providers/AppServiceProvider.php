<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use App\Policies\FilePolicy;
use App\Policies\FolderPolicy;
use App\Support\ApiResponse;
use Dedoc\Scramble\Scramble;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Scramble::configure()->useConfig([
            'api_path' => 'api',
            'api_domain' => null,
            'info' => [
                'version' => config('docs.version', '1.0.0'),
                'description' => 'Private cloud storage backend API for Cloud by NasLabs.',
            ],
            'ui' => ['title' => 'Cloud by NasLabs API'],
            'servers' => ['Cloud by NasLabs API' => rtrim((string) config('app.url'), '/')],
            'renderer' => 'elements',
            'renderers' => ['elements' => ['view' => 'scramble::docs', 'tryItCredentialsPolicy' => 'include']],
            'middleware' => ['web'],
        ]);
        Scramble::configure()->routes(fn ($route): bool => str_starts_with($route->uri(), 'api/'));
        if (! config('docs.enabled', true)) {
            Scramble::configure()->expose(false);
        }

        Gate::define('admin', fn (User $user): bool => $user->role === UserRole::Admin);
        Gate::policy(File::class, FilePolicy::class);
        Gate::policy(Folder::class, FolderPolicy::class);

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        RateLimiter::for('api', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->user()?->getAuthIdentifier() ?? $request->ip());
        });

        RateLimiter::for('auth-login', function (Request $request): Limit {
            $email = mb_strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)
                ->by($email.'|'.$request->ip())
                ->response(fn (): JsonResponse => ApiResponse::error(
                    'Too many login attempts. Please try again later.',
                    [],
                    429,
                ));
        });

        RateLimiter::for('password-reset-request', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by('password-reset:'.$request->ip())
                ->response(fn (): JsonResponse => ApiResponse::error(
                    'Too many requests. Please try again later.',
                    [],
                    429,
                ));
        });

        RateLimiter::for('mail-resend', function (Request $request): Limit {
            return Limit::perMinute(5)
                ->by('mail-resend:'.$request->user()?->getAuthIdentifier().'|'.$request->ip())
                ->response(fn (): JsonResponse => ApiResponse::error(
                    'Too many requests. Please try again later.',
                    [],
                    429,
                ));
        });

        RateLimiter::for('public-share', function (Request $request): Limit {
            return Limit::perMinute(120)->by($request->ip());
        });
    }
}
