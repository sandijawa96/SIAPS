<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\Izin;
use App\Models\User;
use App\Services\AttendanceAutomationStateService;
use App\Services\AttendanceSchemaService;
use App\Services\AttendanceSnapshotService;
use App\Services\AttendanceTimeService;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MarkStudentAlphaAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:mark-student-alpha
        {--date= : Target date in YYYY-MM-DD (default: today)}
        {--from-date= : Start date in YYYY-MM-DD for backfill}
        {--to-date= : End date in YYYY-MM-DD for backfill}
        {--current-month : Backfill current month up to yesterday}
        {--ignore-mobile-signal : Backfill without requiring device binding/mobile login signal}
        {--dry-run : Show candidates without writing records}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark alpha attendance for students with mobile login signal who did not check in on working day.';

    public function __construct(
        private readonly AttendanceTimeService $attendanceTimeService,
        private readonly AttendanceSchemaService $attendanceSchemaService,
        private readonly AttendanceSnapshotService $attendanceSnapshotService,
        private readonly AttendanceAutomationStateService $attendanceAutomationStateService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $targetDates = $this->resolveTargetDates();
        if ($targetDates === null) {
            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $ignoreMobileSignal = (bool) $this->option('ignore-mobile-signal');
        $studentRoleAliases = RoleNames::aliases(RoleNames::SISWA);

        $stats = [
            'dates' => count($targetDates),
            'student_date_checks' => 0,
            'unique_student_ids' => [],
            'skipped_not_mobile_logged_in' => 0,
            'skipped_attendance_not_required' => 0,
            'skipped_auto_alpha_disabled' => 0,
            'skipped_non_working_day' => 0,
            'skipped_approved_leave' => 0,
            'skipped_existing_attendance' => 0,
            'created' => 0,
            'dry_run_candidates' => 0,
            'failed' => 0,
        ];

        if ($stats['dates'] === 0) {
            $this->info('Tidak ada tanggal yang perlu diproses.');
            return self::SUCCESS;
        }

        $dateSummary = count($targetDates) === 1
            ? $targetDates[0]->toDateString()
            : sprintf(
                '%s s/d %s',
                $targetDates[0]->toDateString(),
                $targetDates[array_key_last($targetDates)]->toDateString()
            );

        $this->info(sprintf(
            'Marking alpha for %s (%s)...',
            $dateSummary,
            $dryRun ? 'dry-run' : 'write-mode'
        ));

        if ($ignoreMobileSignal) {
            $today = now()->startOfDay();
            $containsNonPastDate = collect($targetDates)->contains(
                fn (Carbon $targetDate): bool => $targetDate->greaterThanOrEqualTo($today)
            );

            if ($containsNonPastDate) {
                $this->error('Option --ignore-mobile-signal hanya boleh dipakai untuk tanggal lampau.');
                return self::INVALID;
            }

            $this->warn('Mode ignore-mobile-signal aktif: backfill tidak memerlukan device binding.');
        }

        foreach ($targetDates as $targetDate) {
            $targetDateString = $targetDate->toDateString();
            $this->line(sprintf('Processing %s...', $targetDateString));

            $query = User::query()
                ->select([
                    'id',
                    'nama_lengkap',
                    'username',
                    'is_active',
                    'device_id',
                    'device_locked',
                    'device_bound_at',
                ])
                ->where('is_active', true)
                ->whereHas('roles', function ($query) use ($studentRoleAliases) {
                    $query->whereIn('name', $studentRoleAliases);
                })
                ->orderBy('id');

            if (!$ignoreMobileSignal) {
                $query
                    ->whereNotNull('device_id')
                    ->whereNotNull('device_bound_at')
                    ->where('device_locked', true);
            }

            $query->chunkById(200, function ($students) use ($targetDate, $targetDateString, $dryRun, $ignoreMobileSignal, &$stats) {
                    foreach ($students as $student) {
                        $stats['student_date_checks']++;
                        $stats['unique_student_ids'][(int) $student->id] = true;

                        if (!$ignoreMobileSignal && !$this->isEligibleByMobileLoginSignal($student, $targetDate)) {
                            $stats['skipped_not_mobile_logged_in']++;
                            continue;
                        }

                        $schemaContext = $this->resolveAttendanceSchemaContext($student, $targetDate);

                        if (!($schemaContext['attendance_required'] ?? false)) {
                            $stats['skipped_attendance_not_required']++;
                            continue;
                        }

                        if (!($schemaContext['auto_alpha_enabled'] ?? false)) {
                            $stats['skipped_auto_alpha_disabled']++;
                            continue;
                        }

                        if (!($schemaContext['is_working_day'] ?? false)) {
                            $stats['skipped_non_working_day']++;
                            continue;
                        }

                        if ($this->hasApprovedLeaveCoverage($student, $targetDate)) {
                            $stats['skipped_approved_leave']++;
                            continue;
                        }

                        $existing = Absensi::query()
                            ->where('user_id', (int) $student->id)
                            ->whereDate('tanggal', $targetDateString)
                            ->exists();

                        if ($existing) {
                            $stats['skipped_existing_attendance']++;
                            continue;
                        }

                        if ($dryRun) {
                            $stats['dry_run_candidates']++;
                            continue;
                        }

                        try {
                            $snapshot = $this->attendanceSnapshotService->captureForUser($student, [
                                'schema' => $schemaContext['schema'] ?? null,
                                'working_hours' => $schemaContext['working_hours'] ?? null,
                            ]);
                            $attendance = Absensi::query()->firstOrCreate(
                                [
                                    'user_id' => (int) $student->id,
                                    'tanggal' => $targetDateString,
                                ],
                                [
                                    'kelas_id' => $this->resolveStudentClassId($student),
                                    'status' => 'alpha',
                                    'metode_absensi' => 'manual',
                                    'keterangan' => 'Auto alpha: tidak ada absensi masuk pada hari kerja (siswa mobile app aktif).',
                                    'is_manual' => false,
                                    'attendance_setting_id' => $snapshot['attendance_setting_id'] ?? null,
                                    'settings_snapshot' => $snapshot['settings_snapshot'] ?? null,
                                ]
                            );

                            if ($attendance->wasRecentlyCreated) {
                                $stats['created']++;
                            } else {
                                $stats['skipped_existing_attendance']++;
                            }
                        } catch (\Throwable $e) {
                            $stats['failed']++;
                            $this->error(sprintf(
                                'Failed user_id=%d (%s) date=%s: %s',
                                (int) $student->id,
                                (string) ($student->nama_lengkap ?: $student->username ?: '-'),
                                $targetDateString,
                                $e->getMessage()
                            ));
                        }
                    }
                });
        }

        $this->table(
            ['Metric', 'Value'],
            [
                ['Processed dates', (string) $stats['dates']],
                ['Unique students', (string) count($stats['unique_student_ids'])],
                ['Student-date checks', (string) $stats['student_date_checks']],
                ['Skipped (not mobile logged-in)', (string) $stats['skipped_not_mobile_logged_in']],
                ['Skipped (attendance not required)', (string) $stats['skipped_attendance_not_required']],
                ['Skipped (auto alpha disabled)', (string) $stats['skipped_auto_alpha_disabled']],
                ['Skipped (non working day)', (string) $stats['skipped_non_working_day']],
                ['Skipped (approved leave)', (string) $stats['skipped_approved_leave']],
                ['Skipped (existing attendance)', (string) $stats['skipped_existing_attendance']],
                ['Created alpha', (string) $stats['created']],
                ['Dry-run candidates', (string) $stats['dry_run_candidates']],
                ['Failed', (string) $stats['failed']],
            ]
        );

        if (!$dryRun) {
            $this->attendanceAutomationStateService->write('auto_alpha', [
                'last_run_at' => now()->toISOString(),
                'last_status' => $stats['failed'] > 0 ? 'warning' : 'healthy',
                'summary' => [
                    'dates' => $stats['dates'],
                    'created' => $stats['created'],
                    'failed' => $stats['failed'],
                ],
            ]);
        }

        return $stats['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function resolveTargetDates(): ?array
    {
        $dateOption = trim((string) $this->option('date'));
        $fromDateOption = trim((string) $this->option('from-date'));
        $toDateOption = trim((string) $this->option('to-date'));
        $currentMonth = (bool) $this->option('current-month');
        $ignoreMobileSignal = (bool) $this->option('ignore-mobile-signal');

        if ($ignoreMobileSignal && $dateOption === '' && $fromDateOption === '' && $toDateOption === '' && !$currentMonth) {
            $this->error('Option --ignore-mobile-signal hanya boleh dipakai bersama --date, --from-date/--to-date, atau --current-month.');
            return null;
        }

        if ($currentMonth && ($dateOption !== '' || $fromDateOption !== '' || $toDateOption !== '')) {
            $this->error('Option --current-month tidak bisa digabung dengan --date / --from-date / --to-date.');
            return null;
        }

        if ($dateOption !== '' && ($fromDateOption !== '' || $toDateOption !== '')) {
            $this->error('Option --date tidak bisa digabung dengan --from-date / --to-date.');
            return null;
        }

        if (($fromDateOption !== '' && $toDateOption === '') || ($fromDateOption === '' && $toDateOption !== '')) {
            $this->error('Option --from-date dan --to-date harus diisi berpasangan.');
            return null;
        }

        if ($currentMonth) {
            $start = now()->startOfMonth()->startOfDay();
            $end = now()->subDay()->startOfDay();

            return $end->lt($start) ? [] : $this->buildDateRange($start, $end);
        }

        if ($fromDateOption !== '' && $toDateOption !== '') {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $fromDateOption)->startOfDay();
                $end = Carbon::createFromFormat('Y-m-d', $toDateOption)->startOfDay();
            } catch (\Throwable $e) {
                $this->error('Format --from-date/--to-date tidak valid. Gunakan YYYY-MM-DD.');
                return null;
            }

            if ($end->lt($start)) {
                $this->error('--to-date tidak boleh lebih kecil dari --from-date.');
                return null;
            }

            return $this->buildDateRange($start, $end);
        }

        if ($dateOption === '') {
            return [now()->startOfDay()];
        }

        try {
            return [Carbon::createFromFormat('Y-m-d', $dateOption)->startOfDay()];
        } catch (\Throwable $e) {
            $this->error('Invalid --date format. Use YYYY-MM-DD.');
            return null;
        }
    }

    private function buildDateRange(Carbon $start, Carbon $end): array
    {
        $dates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dates[] = $date->copy()->startOfDay();
        }

        return $dates;
    }

    private function isEligibleByMobileLoginSignal(User $student, Carbon $targetDate): bool
    {
        if (!$student->device_locked || empty($student->device_id) || !$student->device_bound_at) {
            return false;
        }

        try {
            $boundAt = $student->device_bound_at instanceof Carbon
                ? $student->device_bound_at->copy()
                : Carbon::parse((string) $student->device_bound_at);

            return $boundAt->lte($targetDate->copy()->endOfDay());
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function resolveStudentClassId(User $student): ?int
    {
        $activeClass = $student->kelas()
            ->wherePivot('is_active', true)
            ->select('kelas.id')
            ->first();

        if ($activeClass) {
            return (int) $activeClass->id;
        }

        $fallbackClass = $student->kelas()
            ->select('kelas.id')
            ->first();

        return $fallbackClass ? (int) $fallbackClass->id : null;
    }

    private function hasApprovedLeaveCoverage(User $student, Carbon $targetDate): bool
    {
        return Izin::query()
            ->where('user_id', $student->id)
            ->where('status', 'approved')
            ->whereDate('tanggal_mulai', '<=', $targetDate->toDateString())
            ->whereDate('tanggal_selesai', '>=', $targetDate->toDateString())
            ->exists();
    }

    /**
     * @return array{schema:?AttendanceSchema,attendance_required:bool,auto_alpha_enabled:bool,is_working_day:bool,working_hours:array<string,mixed>}
     */
    private function resolveAttendanceSchemaContext(User $student, Carbon $targetDate): array
    {
        $schema = $this->attendanceSchemaService->getEffectiveSchema($student, $targetDate->toDateString());
        $attendanceRequired = $schema instanceof AttendanceSchema
            ? $schema->allowsAttendanceForUser($student)
            : false;
        $autoAlphaEnabled = $attendanceRequired
            ? $this->isAutoAlphaEnabled($schema)
            : false;
        $workingHours = $attendanceRequired
            ? $this->attendanceTimeService->getWorkingHoursForDate($student, $targetDate->copy())
            : [];
        $isWorkingDay = $attendanceRequired
            ? $this->attendanceTimeService->isWorkingDayForDate($student, $targetDate->copy())
            : false;

        return [
            'schema' => $schema,
            'attendance_required' => $attendanceRequired,
            'auto_alpha_enabled' => $autoAlphaEnabled,
            'is_working_day' => $isWorkingDay,
            'working_hours' => $workingHours,
        ];
    }

    private function isAutoAlphaEnabled(?AttendanceSchema $schema): bool
    {
        if (!$schema instanceof AttendanceSchema) {
            return false;
        }

        if ($schema->auto_alpha_enabled === null) {
            return (bool) config('attendance.auto_alpha.enabled', true);
        }

        return (bool) $schema->auto_alpha_enabled;
    }
}
