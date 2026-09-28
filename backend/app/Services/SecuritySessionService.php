<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class SecuritySessionService
{
    public function list(User $user, string $currentId): array
    {
        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->orderByDesc('last_activity')
            ->limit(20)
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $session): array => [
                'id' => hash('sha256', (string) $session->id),
                'device' => $this->device((string) ($session->user_agent ?? '')),
                'ipAddress' => $this->maskIp($session->ip_address),
                'lastActiveAt' => now()->setTimestamp((int) $session->last_activity)->toIso8601String(),
                'isCurrent' => hash_equals((string) $session->id, $currentId),
            ])->values()->all();
    }

    private function device(string $userAgent): string
    {
        $browser = str_contains($userAgent, 'Edg/') ? 'Edge' : (str_contains($userAgent, 'Chrome/') ? 'Chrome' : (str_contains($userAgent, 'Firefox/') ? 'Firefox' : (str_contains($userAgent, 'Safari/') ? 'Safari' : 'Browser')));
        $platform = str_contains($userAgent, 'Android') ? 'Android' : (str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') ? 'iOS' : (str_contains($userAgent, 'Windows') ? 'Windows' : (str_contains($userAgent, 'Mac OS') ? 'macOS' : (str_contains($userAgent, 'Linux') ? 'Linux' : 'device'))));

        return $browser.' on '.$platform;
    }

    private function maskIp(?string $ip): ?string
    {
        if ($ip === null || $ip === '') {
            return null;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);

            return $parts[0].'.'.$parts[1].'.•••.•••';
        }

        return substr($ip, 0, 8).'…';
    }
}
