<?php

namespace App\Services;

use App\Models\RuntimeSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RuntimeSettingStore
{
    private const CACHE_PREFIX = 'runtime_settings.namespace.';

    /**
     * @return array<string, mixed>
     */
    public function all(string $namespace): array
    {
        $cacheKey = $this->cacheKey($namespace);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        if (!$this->tableExists()) {
            return [];
        }

        $items = RuntimeSetting::query()
            ->where('namespace', $namespace)
            ->get(['key', 'value', 'type']);

        $resolved = [];
        foreach ($items as $item) {
            $resolved[(string) $item->key] = $this->decodeValue(
                (string) $item->type,
                $item->value
            );
        }

        Cache::forever($cacheKey, $resolved);

        return $resolved;
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, string> $types
     * @return array<string, mixed>
     */
    public function putMany(string $namespace, array $values, array $types = []): array
    {
        $current = $this->all($namespace);
        $tableExists = $this->tableExists();

        if ($tableExists) {
            foreach ($values as $key => $value) {
                $type = $types[$key] ?? $this->inferType($value);

                RuntimeSetting::query()->updateOrCreate(
                    [
                        'namespace' => $namespace,
                        'key' => (string) $key,
                    ],
                    [
                        'value' => $this->encodeValue($type, $value),
                        'type' => $type,
                    ]
                );
            }
        }

        foreach ($values as $key => $value) {
            $current[(string) $key] = $value;
        }

        if ($tableExists) {
            Cache::forever($this->cacheKey($namespace), $current);
        }

        return $current;
    }

    private function tableExists(): bool
    {
        return Schema::hasTable('runtime_settings');
    }

    private function cacheKey(string $namespace): string
    {
        return self::CACHE_PREFIX . $namespace;
    }

    private function inferType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }

        if (is_int($value)) {
            return 'integer';
        }

        if (is_float($value)) {
            return 'float';
        }

        if (is_array($value)) {
            return 'json';
        }

        return 'string';
    }

    private function encodeValue(string $type, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'integer', 'float', 'string' => (string) $value,
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }

    private function decodeValue(string $type, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOL),
            'integer' => (int) $value,
            'float' => (float) $value,
            'json' => is_array($value) ? $value : (json_decode((string) $value, true) ?: []),
            default => (string) $value,
        };
    }
}
