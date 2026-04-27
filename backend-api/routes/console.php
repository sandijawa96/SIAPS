<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\AttendanceRuntimeConfigService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

if ((bool) config('whatsapp.auto_retry.enabled', true)) {
    Schedule::command('whatsapp:retry-failed --limit=' . (int) config('whatsapp.auto_retry.batch_size', 100))
        ->everyFiveMinutes()
        ->withoutOverlapping();
}

Schedule::command('whatsapp:cleanup-runtime-logs')
    ->dailyAt('02:40')
    ->withoutOverlapping();

/** @var AttendanceRuntimeConfigService $attendanceRuntimeConfig */
$attendanceRuntimeConfig = app(AttendanceRuntimeConfigService::class);
$autoAlphaConfig = $attendanceRuntimeConfig->getAutoAlphaConfig();
$disciplineAlertsConfig = $attendanceRuntimeConfig->getDisciplineAlertsConfig();
$liveTrackingConfig = $attendanceRuntimeConfig->getLiveTrackingConfig();

if ((bool) ($autoAlphaConfig['enabled'] ?? true)) {
    Schedule::command('attendance:mark-student-alpha')
        ->dailyAt((string) ($autoAlphaConfig['run_time'] ?? '23:50'))
        ->withoutOverlapping();
}

if ((bool) ($disciplineAlertsConfig['enabled'] ?? true)) {
    Schedule::command('attendance:notify-discipline-thresholds')
        ->dailyAt((string) ($disciplineAlertsConfig['run_time'] ?? '23:57'))
        ->withoutOverlapping();
}

Schedule::command('live-tracking:cleanup')
    ->dailyAt((string) ($liveTrackingConfig['cleanup_time'] ?? config('attendance.live_tracking.cleanup_time', '02:15')))
    ->withoutOverlapping();

Schedule::command('live-tracking:rebuild-current-store')
    ->dailyAt((string) ($liveTrackingConfig['current_store_rebuild_time'] ?? config('attendance.live_tracking.current_store_rebuild_time', '00:10')))
    ->withoutOverlapping();

Schedule::command('izin:send-pending-review-reminders')
    ->dailyAt('07:10')
    ->withoutOverlapping();

Schedule::command('backup:auto-run')
    ->everyMinute()
    ->withoutOverlapping();
