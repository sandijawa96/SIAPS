<?php

namespace App\Services;

use Carbon\Carbon;

class AttendanceAutomationStateService
{
    private const STATE_FILE = 'attendance_scheduler_state.json';

    /**
     * @return array<string, mixed>
     */
    public function read(string $key): array
    {
        $state = $this->readAll();
        $entry = $state[$key] ?? [];

        return is_array($entry) ? $entry : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function write(string $key, array $payload): void
    {
        $state = $this->readAll();
        $state[$key] = array_merge(
            is_array($state[$key] ?? null) ? $state[$key] : [],
            $payload,
            ['updated_at' => now()->toISOString()]
        );

        $this->writeAll($state);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDailyHealth(string $key, bool $enabled, string $runTime, string $label): array
    {
        if (!$enabled) {
            return [
                'status' => 'disabled',
                'label' => $label,
                'last_run_at' => null,
                'scheduled_time' => $runTime,
                'run_time' => $runTime,
                'message' => $label . ' nonaktif.',
            ];
        }

        $state = $this->read($key);
        $lastRunAt = $state['last_run_at'] ?? null;
        if (!is_string($lastRunAt) || trim($lastRunAt) === '') {
            return [
                'status' => 'warning',
                'label' => $label,
                'last_run_at' => null,
                'scheduled_time' => $runTime,
                'run_time' => $runTime,
                'message' => $label . ' belum pernah tercatat berjalan.',
            ];
        }

        try {
            $lastRun = Carbon::parse($lastRunAt);
        } catch (\Throwable $exception) {
            return [
                'status' => 'warning',
                'label' => $label,
                'last_run_at' => $lastRunAt,
                'scheduled_time' => $runTime,
                'run_time' => $runTime,
                'message' => $label . ' memiliki state run yang tidak valid.',
            ];
        }

        $isFresh = $lastRun->greaterThanOrEqualTo(now()->subHours(36));

        return [
            'status' => $isFresh ? 'healthy' : 'warning',
            'label' => $label,
            'last_run_at' => $lastRun->toISOString(),
            'scheduled_time' => $runTime,
            'run_time' => $runTime,
            'message' => $isFresh
                ? $label . ' terakhir berjalan pada ' . $lastRun->format('d M Y H:i')
                : $label . ' stale. Run terakhir tercatat pada ' . $lastRun->format('d M Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readAll(): array
    {
        $path = storage_path('app/' . self::STATE_FILE);
        if (!file_exists($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    private function writeAll(array $state): void
    {
        file_put_contents(
            storage_path('app/' . self::STATE_FILE),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}
