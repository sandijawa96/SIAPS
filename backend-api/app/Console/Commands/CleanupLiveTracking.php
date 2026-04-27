<?php

namespace App\Console\Commands;

use App\Models\LiveTracking;
use App\Services\AttendanceAutomationStateService;
use App\Services\AttendanceRuntimeConfigService;
use Illuminate\Console\Command;

class CleanupLiveTracking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'live-tracking:cleanup
        {--days= : Override retention days for live tracking history}
        {--batch-size=5000 : Delete batch size per query}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete live tracking history rows older than configured retention period.';

    public function handle(): int
    {
        $runtimeConfig = app(AttendanceRuntimeConfigService::class)->getLiveTrackingConfig();
        $days = $this->option('days');
        $batchSize = max(100, (int) $this->option('batch-size'));
        $retentionDays = $days !== null
            ? max(1, (int) $days)
            : max(1, (int) ($runtimeConfig['retention_days'] ?? config('attendance.live_tracking.retention_days', 30)));

        $deleted = LiveTracking::cleanup($retentionDays, $batchSize);
        app(AttendanceAutomationStateService::class)->write('live_tracking_cleanup', [
            'last_run_at' => now()->toISOString(),
            'last_status' => 'healthy',
            'deleted_rows' => $deleted,
            'retention_days' => $retentionDays,
            'batch_size' => $batchSize,
        ]);

        $this->info("Deleted {$deleted} live tracking rows older than {$retentionDays} days in batches of {$batchSize}.");

        return self::SUCCESS;
    }
}
