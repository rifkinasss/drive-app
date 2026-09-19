<?php

namespace App\Support;

use InvalidArgumentException;

final class SystemSettingRegistry
{
    public static function groups(): array
    {
        return config('cloud_settings.groups', []);
    }

    public static function all(): array
    {
        return config('cloud_settings.settings', []);
    }

    public static function definition(string $key): array
    {
        $definition = self::all()[$key] ?? null;
        if ($definition === null) {
            throw new InvalidArgumentException('Unknown system setting.');
        }

        return $definition;
    }

    public static function keyFor(string $group, string $apiKey): ?string
    {
        foreach (self::all() as $key => $definition) {
            if ($definition['group'] === $group && ($definition['api'] === $apiKey || str_ends_with($key, '.'.$apiKey))) {
                return $key;
            }
        }

        return null;
    }
}
