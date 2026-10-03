<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
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
            ->map(fn (object $session): array => $this->present($session, $currentId))
            ->values()
            ->all();
    }

    public function revoke(User $user, string $opaqueId, string $currentId): string
    {
        $session = $this->findByOpaqueId($user, $opaqueId);

        if ($session === null) {
            return 'not_found';
        }

        if (hash_equals((string) $session->id, $currentId)) {
            return 'current';
        }

        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', $session->id)
            ->delete();

        return 'revoked';
    }

    public function revokeOthers(User $user, string $currentId): int
    {
        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentId)
            ->delete();
    }

    private function findByOpaqueId(User $user, string $opaqueId): ?object
    {
        return DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->get(['id'])
            ->first(fn (object $session): bool => hash_equals(hash('sha256', (string) $session->id), $opaqueId));
    }

    private function present(object $session, string $currentId): array
    {
        $lastActiveAt = CarbonImmutable::createFromTimestamp((int) $session->last_activity);
        $device = $this->deviceDetails((string) ($session->user_agent ?? ''));

        return [
            'id' => hash('sha256', (string) $session->id),
            'device' => $device['deviceLabel'],
            'deviceLabel' => $device['deviceLabel'],
            'browser' => $device['browser'],
            'os' => $device['os'],
            'ipAddress' => $this->maskIp($session->ip_address),
            'lastActiveAt' => $lastActiveAt->toIso8601String(),
            'createdAt' => null,
            'approximateStatus' => $lastActiveAt->greaterThanOrEqualTo(now()->subMinutes(30)) ? 'active' : 'recent',
            'isCurrent' => hash_equals((string) $session->id, $currentId),
        ];
    }

    private function deviceDetails(string $userAgent): array
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
        $os = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone') || str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'Unknown device',
        };

        return [
            'browser' => $browser,
            'os' => $os,
            'deviceLabel' => $browser === 'Unknown browser' && $os === 'Unknown device'
                ? 'Perangkat tidak dikenal'
                : $browser.' · '.$os,
        ];
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
