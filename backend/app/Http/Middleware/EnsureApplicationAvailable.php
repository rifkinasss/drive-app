<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Exceptions\ApplicationUnavailableException;
use App\Services\SystemSettingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApplicationAvailable
{
    public function __construct(private readonly SystemSettingService $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->getBool('maintenance.enabled')) {
            return $next($request);
        }

        if ($request->user()?->role === UserRole::Admin) {
            return $next($request);
        }

        throw new ApplicationUnavailableException($this->settings->getString('maintenance.message'));
    }
}
