<?php

namespace App\Console\Commands;

use App\Services\AttendanceAutomationStateService;
use App\Services\AttendanceDisciplineAlertService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyAttendanceDisciplineThresholds extends Command
{
    protected $signature = 'attendance:notify-discipline-thresholds
        {--month= : Reference month in YYYY-MM format}
        {--dry-run : Evaluate candidates without creating notifications}';

    protected $description = 'Dispatch attendance discipline threshold alerts for monthly late, semester total violation, and semester alpha.';

    public function __construct(
        private readonly AttendanceDisciplineAlertService $alertService,
        private readonly AttendanceAutomationStateService $attendanceAutomationStateService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $month = trim((string) $this->option('month'));
        $dryRun = (bool) $this->option('dry-run');

        try {
            $reference = $month !== ''
                ? Carbon::createFromFormat('Y-m', $month)->startOfMonth()
                : now()->startOfMonth();
        } catch (\Throwable $exception) {
            $this->error('Format --month tidak valid. Gunakan YYYY-MM.');
            return self::INVALID;
        }

        $stats = $this->alertService->dispatchThresholdAlerts($reference, $dryRun);

        $this->table(
            ['Metric', 'Value'],
            [
                ['Reference month', $reference->format('Y-m')],
                ['Checked students', (string) ($stats['checked_students'] ?? 0)],
                ['Threshold exceeded students', (string) ($stats['threshold_exceeded_students'] ?? 0)],
                ['Triggered rules', (string) ($stats['threshold_exceeded_rules'] ?? 0)],
                ['Candidate alerts', (string) ($stats['candidate_alerts'] ?? 0)],
                ['Created cases', (string) ($stats['created_cases'] ?? 0)],
                ['Created alerts', (string) ($stats['created_alerts'] ?? 0)],
                ['Created internal notifications', (string) ($stats['created_notifications'] ?? 0)],
                ['Created WhatsApp messages', (string) ($stats['created_whatsapp'] ?? 0)],
                ['Skipped existing', (string) ($stats['skipped_existing'] ?? 0)],
                ['Skipped no recipient', (string) ($stats['skipped_no_recipient'] ?? 0)],
                ['Failed', (string) ($stats['failed'] ?? 0)],
            ]
        );

        if (!$dryRun) {
            $this->attendanceAutomationStateService->write('discipline_alerts', [
                'last_run_at' => now()->toISOString(),
                'last_status' => ((int) ($stats['failed'] ?? 0)) > 0 ? 'warning' : 'healthy',
                'summary' => [
                    'checked_students' => (int) ($stats['checked_students'] ?? 0),
                    'triggered_rules' => (int) ($stats['threshold_exceeded_rules'] ?? 0),
                    'created_alerts' => (int) ($stats['created_alerts'] ?? 0),
                    'failed' => (int) ($stats['failed'] ?? 0),
                ],
            ]);
        }

        return ((int) ($stats['failed'] ?? 0)) > 0 ? self::FAILURE : self::SUCCESS;
    }
}
