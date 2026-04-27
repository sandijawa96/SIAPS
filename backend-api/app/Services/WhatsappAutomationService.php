<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class WhatsappAutomationService
{
    private const CACHE_KEY = 'settings.whatsapp.automations';
    private const SETTINGS_NAMESPACE = 'whatsapp';
    private const SETTINGS_KEY = 'automations';

    public function __construct(
        private readonly RuntimeSettingStore $settingStore
    ) {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $defaults = config('whatsapp.automations', []);
        $stored = $this->settingStore->all(self::SETTINGS_NAMESPACE);
        $overrides = is_array($stored[self::SETTINGS_KEY] ?? null) ? $stored[self::SETTINGS_KEY] : null;

        if ($overrides === null) {
            $legacy = Cache::get(self::CACHE_KEY, []);
            $overrides = is_array($legacy) ? $legacy : [];

            if ($overrides !== []) {
                $this->settingStore->putMany(self::SETTINGS_NAMESPACE, [
                    self::SETTINGS_KEY => $overrides,
                ], [
                    self::SETTINGS_KEY => 'json',
                ]);
            }
        }

        $automations = [];
        foreach ($defaults as $key => $defaultConfig) {
            if (!is_array($defaultConfig)) {
                continue;
            }

            $override = is_array($overrides[$key] ?? null) ? $overrides[$key] : [];
            $current = array_merge($defaultConfig, $override);

            $automations[$key] = [
                'key' => (string) $key,
                'label' => trim((string) ($current['label'] ?? Str::headline((string) $key))),
                'type' => trim((string) ($current['type'] ?? 'pengumuman')),
                'audience' => trim((string) ($current['audience'] ?? 'Umum')),
                'enabled' => (bool) ($current['enabled'] ?? true),
                'template' => (string) ($current['template'] ?? ''),
                'footer' => trim((string) ($current['footer'] ?? '')),
                'placeholders' => array_values(array_filter(
                    is_array($current['placeholders'] ?? null) ? $current['placeholders'] : [],
                    fn ($value) => is_string($value) && trim($value) !== ''
                )),
            ];
        }

        return $automations;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function allForApi(): array
    {
        return array_values($this->all());
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array
    {
        $all = $this->all();

        return $all[$key] ?? null;
    }

    public function isEnabled(string $key): bool
    {
        return (bool) ($this->get($key)['enabled'] ?? false);
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    public function update(array $items): array
    {
        $current = $this->all();
        $stored = $this->settingStore->all(self::SETTINGS_NAMESPACE);
        $overrides = $stored[self::SETTINGS_KEY] ?? Cache::get(self::CACHE_KEY, []);
        if (!is_array($overrides)) {
            $overrides = [];
        }

        foreach ($items as $item) {
            $key = trim((string) ($item['key'] ?? ''));
            if ($key === '' || !array_key_exists($key, $current)) {
                continue;
            }

            $base = $current[$key];
            $overrides[$key] = [
                'enabled' => array_key_exists('enabled', $item)
                    ? (bool) $item['enabled']
                    : (bool) ($base['enabled'] ?? true),
                'template' => array_key_exists('template', $item)
                    ? trim((string) $item['template'])
                    : (string) ($base['template'] ?? ''),
                'footer' => array_key_exists('footer', $item)
                    ? trim((string) $item['footer'])
                    : (string) ($base['footer'] ?? ''),
            ];
        }

        $this->settingStore->putMany(self::SETTINGS_NAMESPACE, [
            self::SETTINGS_KEY => $overrides,
        ], [
            self::SETTINGS_KEY => 'json',
        ]);
        Cache::forever(self::CACHE_KEY, $overrides);

        return $this->allForApi();
    }

    /**
     * @param array<string, scalar|null> $variables
     * @return array<string, string>|null
     */
    public function render(string $key, array $variables): ?array
    {
        $automation = $this->get($key);
        if (!$automation || !($automation['enabled'] ?? false)) {
            return null;
        }

        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{' . $name . '}'] = $this->stringifyValue($value);
        }

        $message = strtr((string) ($automation['template'] ?? ''), $replacements);
        $footer = strtr((string) ($automation['footer'] ?? ''), $replacements);

        return [
            'message' => trim($message),
            'footer' => trim($footer),
        ];
    }

    private function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Ya' : 'Tidak';
        }

        return trim((string) $value);
    }
}
