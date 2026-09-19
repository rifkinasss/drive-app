<?php

namespace App\Services;

use App\Models\SystemSetting;
use App\Support\SystemSettingRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SystemSettingService
{
    public function get(string $key): mixed
    {
        $definition = SystemSettingRegistry::definition($key);

        if ($key === 'storage.max_upload_size_bytes' && ! SystemSetting::query()->where('key', $key)->exists()) {
            return (int) config('cloud.max_upload_size_bytes');
        }

        try {
            return Cache::rememberForever($this->cacheKey($key), function () use ($key, $definition): mixed {
                return SystemSetting::query()->where('key', $key)->first()?->value ?? $definition['default'];
            });
        } catch (QueryException $exception) {
            if ($this->settingsTableMissing($exception)) {
                return $definition['default'];
            }
            throw $exception;
        }
    }

    public function getString(string $key): string
    {
        return (string) $this->get($key);
    }

    public function getInt(string $key): int
    {
        return (int) $this->get($key);
    }

    public function getBool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function getJson(string $key): array
    {
        return (array) $this->get($key);
    }

    public function getGroup(string $group): array
    {
        $this->assertGroup($group);
        $values = [];
        foreach (SystemSettingRegistry::all() as $key => $definition) {
            if ($definition['group'] === $group) {
                $values[$definition['api']] = $this->get($key);
            }
        }

        return $values;
    }

    public function allGroups(): array
    {
        $groups = [];
        foreach (SystemSettingRegistry::groups() as $group) {
            $groups[$group] = $this->getGroup($group);
        }

        return $groups;
    }

    public function setGroup(string $group, array $values, int $updatedBy): array
    {
        $this->assertGroup($group);
        $normalized = [];
        $errors = [];
        foreach ($values as $apiKey => $value) {
            $key = SystemSettingRegistry::keyFor($group, (string) $apiKey);
            if ($key === null) {
                $errors[$apiKey][] = 'This setting is not registered for this group.';

                continue;
            }
            $definition = SystemSettingRegistry::definition($key);
            if (($definition['writable'] ?? true) === false) {
                $errors[$apiKey][] = 'This setting is read-only.';

                continue;
            }
            $validator = Validator::make(['value' => $value], ['value' => $definition['rules']]);
            if ($validator->fails()) {
                $errors[$apiKey] = $validator->errors()->get('value');

                continue;
            }
            $normalized[$key] = $this->normalize($definition['type'], $value);
            if ($key === 'storage.blocked_extensions') {
                $normalized[$key] = array_values(array_unique(array_map(
                    static fn (mixed $extension): string => mb_strtolower(ltrim(trim((string) $extension), '.')),
                    $normalized[$key],
                )));
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($normalized, $updatedBy): void {
            foreach ($normalized as $key => $value) {
                $definition = SystemSettingRegistry::definition($key);
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'group' => $definition['group'], 'type' => $definition['type'], 'updated_by' => $updatedBy],
                );
                Cache::forget($this->cacheKey($key));
            }
        });

        return $this->getGroup($group);
    }

    private function normalize(string $type, mixed $value): mixed
    {
        return match ($type) {
            'integer' => (int) $value,
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'json' => array_values((array) $value),
            default => (string) $value,
        };
    }

    private function assertGroup(string $group): void
    {
        if (! in_array($group, SystemSettingRegistry::groups(), true)) {
            throw ValidationException::withMessages(['group' => 'Unknown settings group.']);
        }
    }

    private function cacheKey(string $key): string
    {
        return 'cloud.system_setting.'.str_replace('.', '_', $key);
    }

    private function settingsTableMissing(QueryException $exception): bool
    {
        return str_contains(mb_strtolower($exception->getMessage()), 'system_settings')
            && (str_contains(mb_strtolower($exception->getMessage()), 'does not exist') || str_contains(mb_strtolower($exception->getMessage()), 'no such table'));
    }
}
