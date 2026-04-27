<?php

namespace App\Console\Commands;

use App\Http\Controllers\Api\BackupController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RunAutomaticBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:auto-run {--force : Paksa jalankan backup tanpa cek jadwal}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run automatic backup based on backup settings';

    public function handle(BackupController $backupController): int
    {
        $settings = $this->readBackupSettings();
        $isForced = (bool) $this->option('force');

        if (!$isForced && !(bool) ($settings['auto_backup_enabled'] ?? false)) {
            $this->line('Automatic backup disabled. Nothing to do.');
            return self::SUCCESS;
        }

        [$isDue, $frequency, $slotKey, $reason] = $this->resolveDueState($settings, Carbon::now(), $isForced);
        if (!$isDue) {
            $this->line("Automatic backup skipped ({$reason}).");
            return self::SUCCESS;
        }

        $types = $this->normalizeBackupTypes($settings['backup_types'] ?? ['database']);
        if ($types === []) {
            $types = ['database'];
        }

        $retentionDays = max(1, (int) ($settings['retention_days'] ?? 30));
        $description = 'Automatic backup (' . $frequency . ')';

        $successes = [];
        $failures = [];

        foreach ($types as $type) {
            try {
                $result = $backupController->runBackupProcess($type, $description, null);
                $successes[] = $result;
                $this->info("Backup {$type} berhasil: {$result['filename']}");
            } catch (\Throwable $exception) {
                $failures[] = [
                    'type' => $type,
                    'error' => $exception->getMessage(),
                ];
                $this->error("Backup {$type} gagal: {$exception->getMessage()}");
            }
        }

        if ($successes !== []) {
            try {
                $cleanupResult = $backupController->cleanupExpiredBackups($retentionDays, null);
                $deletedCount = (int) ($cleanupResult['deleted_count'] ?? 0);
                $this->line("Cleanup backup selesai. {$deletedCount} file dihapus.");
            } catch (\Throwable $exception) {
                Log::warning('Automatic backup cleanup failed', [
                    'error' => $exception->getMessage(),
                ]);
                $this->warn('Backup berhasil, tetapi cleanup gagal: ' . $exception->getMessage());
            }

            $this->writeBackupState($frequency, $slotKey, Carbon::now());
        }

        if ($failures !== []) {
            Log::error('Automatic backup completed with failures', [
                'frequency' => $frequency,
                'types' => $types,
                'failures' => $failures,
                'success_count' => count($successes),
            ]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * @return array{bool,string,string,string}
     */
    private function resolveDueState(array $settings, Carbon $now, bool $isForced): array
    {
        $frequency = $this->normalizeFrequency((string) ($settings['backup_frequency'] ?? 'daily'));
        $slotKey = $this->buildSlotKey($frequency, $now, $settings);

        if ($isForced) {
            return [true, $frequency, $slotKey, 'forced'];
        }

        $state = $this->readBackupState();
        $lastSlotByFrequency = (array) ($state['slots'] ?? []);
        $lastSlot = (string) ($lastSlotByFrequency[$frequency] ?? '');

        if ($lastSlot === $slotKey) {
            return [false, $frequency, $slotKey, 'already-ran'];
        }

        $runTime = $this->parseRunTime((string) ($settings['backup_run_time'] ?? '01:00'));
        $runAt = $now->copy()->setTime($runTime['hour'], $runTime['minute'], 0);

        if ($frequency === 'hourly') {
            return [true, $frequency, $slotKey, 'due'];
        }

        if ($frequency === 'daily') {
            return $now->gte($runAt)
                ? [true, $frequency, $slotKey, 'due']
                : [false, $frequency, $slotKey, 'before-time'];
        }

        if ($frequency === 'weekly') {
            $targetDay = min(7, max(1, (int) ($settings['backup_weekly_day'] ?? 1)));
            if ($now->dayOfWeekIso !== $targetDay) {
                return [false, $frequency, $slotKey, 'not-weekly-day'];
            }

            return $now->gte($runAt)
                ? [true, $frequency, $slotKey, 'due']
                : [false, $frequency, $slotKey, 'before-time'];
        }

        $targetDay = min(31, max(1, (int) ($settings['backup_monthly_day'] ?? 1)));
        $effectiveTargetDay = min($targetDay, $now->daysInMonth);
        if ($now->day !== $effectiveTargetDay) {
            return [false, $frequency, $slotKey, 'not-monthly-day'];
        }

        return $now->gte($runAt)
            ? [true, $frequency, $slotKey, 'due']
            : [false, $frequency, $slotKey, 'before-time'];
    }

    private function normalizeFrequency(string $frequency): string
    {
        $value = strtolower(trim($frequency));
        return in_array($value, ['hourly', 'daily', 'weekly', 'monthly'], true) ? $value : 'daily';
    }

    /**
     * @param mixed $types
     * @return array<int, string>
     */
    private function normalizeBackupTypes(mixed $types): array
    {
        $raw = is_array($types) ? $types : [$types];
        $allowed = ['full', 'database', 'files'];
        $normalized = [];

        foreach ($raw as $type) {
            $value = strtolower(trim((string) $type));
            if (!in_array($value, $allowed, true)) {
                continue;
            }

            if (!in_array($value, $normalized, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized;
    }

    private function buildSlotKey(string $frequency, Carbon $now, array $settings): string
    {
        return match ($frequency) {
            'hourly' => $now->format('Y-m-d-H'),
            'daily' => $now->format('Y-m-d'),
            'weekly' => $now->format('o-\WW'),
            'monthly' => $this->buildMonthlySlotKey($now, $settings),
            default => $now->format('Y-m-d'),
        };
    }

    private function buildMonthlySlotKey(Carbon $now, array $settings): string
    {
        $targetDay = min(31, max(1, (int) ($settings['backup_monthly_day'] ?? 1)));
        $effectiveTargetDay = min($targetDay, $now->daysInMonth);
        return $now->format('Y-m') . '-d' . str_pad((string) $effectiveTargetDay, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{hour:int,minute:int}
     */
    private function parseRunTime(string $raw): array
    {
        $value = trim($raw);
        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $value, $matches) !== 1) {
            return ['hour' => 1, 'minute' => 0];
        }

        return [
            'hour' => (int) $matches[1],
            'minute' => (int) $matches[2],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function readBackupSettings(): array
    {
        $defaults = [
            'auto_backup_enabled' => false,
            'backup_frequency' => 'daily',
            'retention_days' => 30,
            'backup_types' => ['database'],
            'backup_run_time' => '01:00',
            'backup_weekly_day' => 1,
            'backup_monthly_day' => 1,
        ];

        $path = storage_path('app/backup_settings.json');
        if (!file_exists($path)) {
            return $defaults;
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }

    /**
     * @return array<string, mixed>
     */
    private function readBackupState(): array
    {
        $path = storage_path('app/backup_auto_state.json');
        if (!file_exists($path)) {
            return ['slots' => []];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        if (!is_array($decoded)) {
            return ['slots' => []];
        }

        $decoded['slots'] = is_array($decoded['slots'] ?? null) ? $decoded['slots'] : [];
        return $decoded;
    }

    private function writeBackupState(string $frequency, string $slotKey, Carbon $now): void
    {
        $state = $this->readBackupState();
        $slots = (array) ($state['slots'] ?? []);
        $slots[$frequency] = $slotKey;

        $state['slots'] = $slots;
        $state['last_run_at'] = $now->toISOString();
        $state['last_frequency'] = $frequency;
        $state['updated_at'] = $now->toISOString();

        file_put_contents(
            storage_path('app/backup_auto_state.json'),
            json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }
}

