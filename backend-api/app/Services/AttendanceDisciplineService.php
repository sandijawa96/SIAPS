<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\PeriodeAkademik;
use App\Models\TahunAjaran;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class AttendanceDisciplineService
{
    public const RULE_KEY_MONTHLY_LATE = 'monthly_late_limit';
    public const RULE_KEY_SEMESTER_TOTAL_VIOLATION = 'semester_total_violation_limit';
    public const RULE_KEY_SEMESTER_ALPHA = 'semester_alpha_limit';
    public const THRESHOLD_MODE_MONITOR_ONLY = 'monitor_only';
    public const THRESHOLD_MODE_ALERTABLE = 'alertable';

    /**
     * @var array<int, AttendanceSchema|null>
     */
    private array $schemaCache = [];

    public function __construct(
        private readonly AttendanceTimeService $attendanceTimeService,
        private readonly AttendanceDisciplineOverrideService $attendanceDisciplineOverrideService
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveThresholdConfig(?User $user = null): array
    {
        $schema = $this->resolveGlobalDisciplineSchema();
        $config = $this->buildThresholdConfigFromSchema($schema);

        if ($user instanceof User) {
            $override = $this->attendanceDisciplineOverrideService->resolveForUser($user);
            if ($override !== null) {
                $config = $this->applyOverrideThresholdConfig($config, $override);
            }
        }

        return $config;
    }

    public function resolveWorkingMinutesPerDay(?User $user = null): int
    {
        $defaultMinutes = 8 * 60;
        if (!$user instanceof User) {
            return $defaultMinutes;
        }

        return $this->resolveWorkingMinutesFromHours($this->attendanceTimeService->getWorkingHours($user), $defaultMinutes);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveSemesterContext(?Carbon $referenceDate = null, ?TahunAjaran $tahunAjaran = null): array
    {
        $reference = ($referenceDate ?? now())->copy()->startOfDay();
        $effectiveTahunAjaran = $this->resolveEffectiveTahunAjaran($tahunAjaran, $reference);

        if ($effectiveTahunAjaran instanceof TahunAjaran) {
            $periode = PeriodeAkademik::query()
                ->where('tahun_ajaran_id', (int) $effectiveTahunAjaran->id)
                ->where('jenis', PeriodeAkademik::JENIS_PEMBELAJARAN)
                ->where('is_active', true)
                ->whereIn('semester', [
                    PeriodeAkademik::SEMESTER_GANJIL,
                    PeriodeAkademik::SEMESTER_GENAP,
                ])
                ->whereDate('tanggal_mulai', '<=', $reference->toDateString())
                ->whereDate('tanggal_selesai', '>=', $reference->toDateString())
                ->orderBy('tanggal_mulai')
                ->first();

            if ($periode instanceof PeriodeAkademik) {
                $startDate = Carbon::parse((string) $periode->tanggal_mulai)->startOfDay();
                $endDate = Carbon::parse((string) $periode->tanggal_selesai)->endOfDay();

                return [
                    'semester' => (string) $periode->semester,
                    'semester_label' => $this->semesterLabel((string) $periode->semester),
                    'tahun_ajaran_id' => (int) $effectiveTahunAjaran->id,
                    'tahun_ajaran_nama' => (string) $effectiveTahunAjaran->nama,
                    'start_date' => $startDate->toDateString(),
                    'end_date' => $endDate->toDateString(),
                    'source' => 'periode_akademik',
                ];
            }
        }

        [$semesterKey, $startDate, $endDate] = $this->resolveFallbackSemesterRange(
            $reference,
            $effectiveTahunAjaran
        );

        return [
            'semester' => $semesterKey,
            'semester_label' => $this->semesterLabel($semesterKey),
            'tahun_ajaran_id' => $effectiveTahunAjaran?->id,
            'tahun_ajaran_nama' => $effectiveTahunAjaran?->nama,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'source' => 'fallback_calendar',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildUserDisciplineSnapshot(
        User $user,
        Carbon $referenceMonth,
        ?TahunAjaran $tahunAjaran = null
    ): array {
        $config = $this->resolveThresholdConfig($user);
        $periodStart = $referenceMonth->copy()->startOfMonth()->startOfDay();
        $periodEnd = $referenceMonth->copy()->endOfMonth()->endOfDay();
        [$periodStart, $periodEnd] = $this->clampRangeToTahunAjaran($periodStart, $periodEnd, $tahunAjaran);

        $monthlyMetrics = $periodEnd->lt($periodStart)
            ? $this->emptyMetrics()
            : $this->calculateMetrics($user, $periodStart, $periodEnd);

        $semesterContext = $this->resolveSemesterContext($referenceMonth, $tahunAjaran);
        $semesterStart = Carbon::parse((string) $semesterContext['start_date'])->startOfDay();
        $semesterEnd = Carbon::parse((string) $semesterContext['end_date'])->endOfDay();
        $semesterMetrics = $semesterEnd->lt($semesterStart)
            ? $this->emptyMetrics()
            : $this->calculateMetrics($user, $semesterStart, $semesterEnd);

        $monthlyLateExceeded = $config['late_minutes_monthly_limit'] > 0
            && $monthlyMetrics['late_minutes'] >= $config['late_minutes_monthly_limit'];
        $semesterViolationExceeded = $config['total_violation_minutes_semester_limit'] > 0
            && $semesterMetrics['total_violation_minutes'] >= $config['total_violation_minutes_semester_limit'];
        $semesterAlphaExceeded = $config['alpha_days_semester_limit'] > 0
            && $semesterMetrics['alpha_days'] >= $config['alpha_days_semester_limit'];

        $monthlyLateAlertable = $config['discipline_thresholds_enabled']
            && $config['monthly_late_mode'] === self::THRESHOLD_MODE_ALERTABLE;
        $semesterViolationAlertable = $config['discipline_thresholds_enabled']
            && $config['semester_total_violation_mode'] === self::THRESHOLD_MODE_ALERTABLE;
        $semesterAlphaAlertable = $config['discipline_thresholds_enabled']
            && $config['semester_alpha_mode'] === self::THRESHOLD_MODE_ALERTABLE;

        return [
            'config' => $config,
            'monthly_late' => [
                'rule_key' => self::RULE_KEY_MONTHLY_LATE,
                'label' => 'Keterlambatan Bulanan',
                'period_type' => 'month',
                'period_key' => $periodStart->format('Y-m'),
                'period_label' => $this->monthLabel($periodStart),
                'minutes' => (int) $monthlyMetrics['late_minutes'],
                'limit' => (int) $config['late_minutes_monthly_limit'],
                'mode' => (string) $config['monthly_late_mode'],
                'alertable' => $monthlyLateAlertable,
                'exceeded' => $monthlyLateExceeded,
                'notify_wali_kelas' => (bool) $config['notify_wali_kelas_on_late_limit'],
                'notify_kesiswaan' => (bool) $config['notify_kesiswaan_on_late_limit'],
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
            ],
            'semester_total_violation' => [
                'rule_key' => self::RULE_KEY_SEMESTER_TOTAL_VIOLATION,
                'label' => 'Total Pelanggaran Semester',
                'period_type' => 'semester',
                'period_key' => $this->buildSemesterPeriodKey($semesterContext),
                'period_label' => $this->buildSemesterPeriodLabel($semesterContext),
                'minutes' => (int) $semesterMetrics['total_violation_minutes'],
                'limit' => (int) $config['total_violation_minutes_semester_limit'],
                'mode' => (string) $config['semester_total_violation_mode'],
                'alertable' => $semesterViolationAlertable,
                'exceeded' => $semesterViolationExceeded,
                'notify_wali_kelas' => (bool) $config['notify_wali_kelas_on_total_violation_limit'],
                'notify_kesiswaan' => (bool) $config['notify_kesiswaan_on_total_violation_limit'],
                'start_date' => (string) $semesterContext['start_date'],
                'end_date' => (string) $semesterContext['end_date'],
                'semester' => (string) $semesterContext['semester'],
                'semester_label' => (string) $semesterContext['semester_label'],
                'tahun_ajaran_id' => $semesterContext['tahun_ajaran_id'],
                'tahun_ajaran_nama' => $semesterContext['tahun_ajaran_nama'],
            ],
            'semester_alpha' => [
                'rule_key' => self::RULE_KEY_SEMESTER_ALPHA,
                'label' => 'Alpha Semester',
                'period_type' => 'semester',
                'period_key' => $this->buildSemesterPeriodKey($semesterContext),
                'period_label' => $this->buildSemesterPeriodLabel($semesterContext),
                'days' => (int) $semesterMetrics['alpha_days'],
                'limit' => (int) $config['alpha_days_semester_limit'],
                'mode' => (string) $config['semester_alpha_mode'],
                'alertable' => $semesterAlphaAlertable,
                'exceeded' => $semesterAlphaExceeded,
                'notify_wali_kelas' => (bool) $config['notify_wali_kelas_on_alpha_limit'],
                'notify_kesiswaan' => (bool) $config['notify_kesiswaan_on_alpha_limit'],
                'start_date' => (string) $semesterContext['start_date'],
                'end_date' => (string) $semesterContext['end_date'],
                'semester' => (string) $semesterContext['semester'],
                'semester_label' => (string) $semesterContext['semester_label'],
                'tahun_ajaran_id' => $semesterContext['tahun_ajaran_id'],
                'tahun_ajaran_nama' => $semesterContext['tahun_ajaran_nama'],
            ],
            'attention_needed' => $monthlyLateExceeded || $semesterViolationExceeded || $semesterAlphaExceeded,
            'alert_needed' => ($monthlyLateExceeded && $monthlyLateAlertable)
                || ($semesterViolationExceeded && $semesterViolationAlertable)
                || ($semesterAlphaExceeded && $semesterAlphaAlertable),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function calculateMetrics(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $records = Absensi::query()
            ->where('user_id', (int) $user->id)
            ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
            ->get([
                'id',
                'user_id',
                'tanggal',
                'jam_masuk',
                'jam_pulang',
                'status',
                'attendance_setting_id',
                'settings_snapshot',
                'kelas_id',
            ]);

        return $this->calculateMetricsFromRecords($user, $records);
    }

    /**
     * @param Collection<int, Absensi> $records
     * @return array<string, int>
     */
    public function calculateMetricsFromRecords(User $user, Collection $records, ?int $workingMinutesPerDay = null): array
    {
        $defaultMinutesPerDay = $workingMinutesPerDay ?? $this->resolveWorkingMinutesPerDay($user);
        $lateMinutes = 0;
        $lateDays = 0;
        $tapMinutes = 0;
        $tapDays = 0;
        $alphaDays = 0;
        $alphaMinutes = 0;

        foreach ($records as $attendance) {
            if (!$attendance instanceof Absensi) {
                continue;
            }

            $attendanceDate = Carbon::parse((string) $attendance->tanggal)->startOfDay();
            $workingHours = $this->resolveWorkingHoursForAttendance($user, $attendance);
            if (!$this->isRecordedWorkingDay($user, $attendanceDate, $attendance, $workingHours)) {
                continue;
            }

            $minutesPerDay = $this->resolveWorkingMinutesFromHours($workingHours, $defaultMinutesPerDay);
            $status = $this->normalizeStatus($attendance->status);

            if ($status === 'alpha') {
                $alphaDays++;
                $alphaMinutes += $minutesPerDay;
                continue;
            }

            $lateMinutesForAttendance = $this->calculateLateMinutesFromAttendance($user, $attendance, $workingHours);
            $lateMinutes += $lateMinutesForAttendance;
            if ($lateMinutesForAttendance > 0) {
                $lateDays++;
            }

            if (!empty($attendance->jam_masuk) && empty($attendance->jam_pulang)) {
                $tapDays++;
                $tapMinutes += (int) round($minutesPerDay * 0.5);
            }
        }

        return [
            'late_minutes' => (int) $lateMinutes,
            'late_days' => (int) $lateDays,
            'tap_minutes' => (int) $tapMinutes,
            'tap_days' => (int) $tapDays,
            'alpha_days' => (int) $alphaDays,
            'alpha_minutes' => (int) $alphaMinutes,
            'total_violation_minutes' => (int) ($lateMinutes + $tapMinutes + $alphaMinutes),
        ];
    }

    public function calculateLateMinutesFromAttendance(User $user, Absensi $attendance, ?array $workingHours = null): int
    {
        if (empty($attendance->jam_masuk)) {
            return 0;
        }

        $hours = is_array($workingHours) ? $workingHours : $this->resolveWorkingHoursForAttendance($user, $attendance);

        try {
            $jamMasukActual = Carbon::parse((string) $attendance->jam_masuk);
            // Minutes late are measured from the scheduled start time.
            // Tolerance still controls the check-in acceptance window elsewhere.
            $jamMasukRef = $this->parseClock((string) ($hours['jam_masuk'] ?? '07:00'));

            return $jamMasukActual->gt($jamMasukRef)
                ? (int) $jamMasukActual->diffInMinutes($jamMasukRef, true)
                : 0;
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function resolveWorkingHoursForAttendance(User $user, ?Absensi $attendance = null): array
    {
        $snapshotHours = $this->extractWorkingHoursFromSnapshot($attendance?->settings_snapshot);
        if ($snapshotHours !== null) {
            return $snapshotHours;
        }

        $schemaId = $attendance?->attendance_setting_id;
        if (is_numeric($schemaId)) {
            $schema = $this->resolveSchemaById((int) $schemaId);
            if ($schema instanceof AttendanceSchema) {
                return $this->normalizeWorkingHours($schema->getEffectiveWorkingHours($user), $schema);
            }
        }

        if ($attendance?->relationLoaded('attendanceSchema') && $attendance->attendanceSchema instanceof AttendanceSchema) {
            return $this->normalizeWorkingHours($attendance->attendanceSchema->getEffectiveWorkingHours($user), $attendance->attendanceSchema);
        }

        return $this->normalizeWorkingHours($this->attendanceTimeService->getWorkingHours($user));
    }

    private function resolveGlobalDisciplineSchema(): ?AttendanceSchema
    {
        return AttendanceSchema::query()
            ->where('schema_type', 'global')
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first()
            ?? AttendanceSchema::query()
                ->where('schema_type', 'global')
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildThresholdConfigFromSchema(?AttendanceSchema $schema): array
    {
        $enabled = $schema instanceof AttendanceSchema
            ? (bool) ($schema->discipline_thresholds_enabled ?? false)
            : false;

        $legacyMinutesThreshold = (int) ($schema?->violation_minutes_threshold ?? 480);
        $legacyPercentageThreshold = (float) ($schema?->violation_percentage_threshold ?? 10.0);

        return [
            'schema_id' => $schema?->id,
            'schema_name' => $schema?->schema_name,
            'config_source' => 'global',
            'override_applied' => false,
            'override_id' => null,
            'override_scope_type' => null,
            'override_scope_label' => null,
            'override_notes' => null,
            'uses_new_thresholds' => $enabled,
            'discipline_thresholds_enabled' => $enabled,
            'total_violation_minutes_semester_limit' => $enabled
                ? (int) ($schema?->total_violation_minutes_semester_limit ?? 1200)
                : $legacyMinutesThreshold,
            'alpha_days_semester_limit' => $enabled
                ? (int) ($schema?->alpha_days_semester_limit ?? 8)
                : 8,
            'late_minutes_monthly_limit' => $enabled
                ? (int) ($schema?->late_minutes_monthly_limit ?? 120)
                : $legacyMinutesThreshold,
            'semester_total_violation_mode' => $enabled
                ? $this->normalizeThresholdMode($schema?->semester_total_violation_mode)
                : self::THRESHOLD_MODE_MONITOR_ONLY,
            'semester_alpha_mode' => $enabled
                ? $this->normalizeThresholdMode($schema?->semester_alpha_mode, self::THRESHOLD_MODE_ALERTABLE)
                : self::THRESHOLD_MODE_MONITOR_ONLY,
            'monthly_late_mode' => $enabled
                ? $this->normalizeThresholdMode($schema?->monthly_late_mode)
                : self::THRESHOLD_MODE_MONITOR_ONLY,
            'notify_wali_kelas_on_total_violation_limit' => $enabled
                ? (bool) ($schema?->notify_wali_kelas_on_total_violation_limit ?? false)
                : false,
            'notify_kesiswaan_on_total_violation_limit' => $enabled
                ? (bool) ($schema?->notify_kesiswaan_on_total_violation_limit ?? false)
                : false,
            'notify_wali_kelas_on_alpha_limit' => $enabled
                ? (bool) ($schema?->notify_wali_kelas_on_alpha_limit ?? true)
                : false,
            'notify_kesiswaan_on_alpha_limit' => $enabled
                ? (bool) ($schema?->notify_kesiswaan_on_alpha_limit ?? true)
                : false,
            'notify_wali_kelas_on_late_limit' => $enabled
                ? (bool) ($schema?->notify_wali_kelas_on_late_limit ?? false)
                : false,
            'notify_kesiswaan_on_late_limit' => $enabled
                ? (bool) ($schema?->notify_kesiswaan_on_late_limit ?? false)
                : false,
            'legacy_violation_minutes_threshold' => $legacyMinutesThreshold,
            'legacy_violation_percentage_threshold' => $legacyPercentageThreshold,
        ];
    }

    /**
     * @param array<string, mixed> $baseConfig
     * @return array<string, mixed>
     */
    private function applyOverrideThresholdConfig(array $baseConfig, $override): array
    {
        $enabled = (bool) ($override->discipline_thresholds_enabled ?? false);

        return array_merge($baseConfig, [
            'config_source' => 'override',
            'override_applied' => true,
            'override_id' => (int) $override->id,
            'override_scope_type' => (string) $override->scope_type,
            'override_scope_label' => (string) ($override->scope_label ?? ''),
            'override_notes' => $override->notes,
            'uses_new_thresholds' => $enabled,
            'discipline_thresholds_enabled' => $enabled,
            'total_violation_minutes_semester_limit' => $enabled
                ? (int) ($override->total_violation_minutes_semester_limit ?? $baseConfig['total_violation_minutes_semester_limit'] ?? 1200)
                : (int) ($baseConfig['legacy_violation_minutes_threshold'] ?? 480),
            'alpha_days_semester_limit' => $enabled
                ? (int) ($override->alpha_days_semester_limit ?? $baseConfig['alpha_days_semester_limit'] ?? 8)
                : 8,
            'late_minutes_monthly_limit' => $enabled
                ? (int) ($override->late_minutes_monthly_limit ?? $baseConfig['late_minutes_monthly_limit'] ?? 120)
                : (int) ($baseConfig['legacy_violation_minutes_threshold'] ?? 480),
            'semester_total_violation_mode' => $enabled
                ? $this->normalizeThresholdMode(
                    $override->semester_total_violation_mode ?? $baseConfig['semester_total_violation_mode'] ?? null
                )
                : self::THRESHOLD_MODE_MONITOR_ONLY,
            'semester_alpha_mode' => $enabled
                ? $this->normalizeThresholdMode(
                    $override->semester_alpha_mode ?? $baseConfig['semester_alpha_mode'] ?? null,
                    self::THRESHOLD_MODE_ALERTABLE
                )
                : self::THRESHOLD_MODE_MONITOR_ONLY,
            'monthly_late_mode' => $enabled
                ? $this->normalizeThresholdMode(
                    $override->monthly_late_mode ?? $baseConfig['monthly_late_mode'] ?? null
                )
                : self::THRESHOLD_MODE_MONITOR_ONLY,
            'notify_wali_kelas_on_total_violation_limit' => $enabled
                ? (bool) ($override->notify_wali_kelas_on_total_violation_limit
                    ?? $baseConfig['notify_wali_kelas_on_total_violation_limit']
                    ?? false)
                : false,
            'notify_kesiswaan_on_total_violation_limit' => $enabled
                ? (bool) ($override->notify_kesiswaan_on_total_violation_limit
                    ?? $baseConfig['notify_kesiswaan_on_total_violation_limit']
                    ?? false)
                : false,
            'notify_wali_kelas_on_alpha_limit' => $enabled
                ? (bool) ($override->notify_wali_kelas_on_alpha_limit
                    ?? $baseConfig['notify_wali_kelas_on_alpha_limit']
                    ?? true)
                : false,
            'notify_kesiswaan_on_alpha_limit' => $enabled
                ? (bool) ($override->notify_kesiswaan_on_alpha_limit
                    ?? $baseConfig['notify_kesiswaan_on_alpha_limit']
                    ?? true)
                : false,
            'notify_wali_kelas_on_late_limit' => $enabled
                ? (bool) ($override->notify_wali_kelas_on_late_limit
                    ?? $baseConfig['notify_wali_kelas_on_late_limit']
                    ?? false)
                : false,
            'notify_kesiswaan_on_late_limit' => $enabled
                ? (bool) ($override->notify_kesiswaan_on_late_limit
                    ?? $baseConfig['notify_kesiswaan_on_late_limit']
                    ?? false)
                : false,
        ]);
    }

    private function resolveSchemaById(int $schemaId): ?AttendanceSchema
    {
        if (array_key_exists($schemaId, $this->schemaCache)) {
            return $this->schemaCache[$schemaId];
        }

        $this->schemaCache[$schemaId] = AttendanceSchema::query()->find($schemaId);

        return $this->schemaCache[$schemaId];
    }

    private function resolveEffectiveTahunAjaran(?TahunAjaran $tahunAjaran = null, ?Carbon $referenceDate = null): ?TahunAjaran
    {
        if ($tahunAjaran instanceof TahunAjaran) {
            return $tahunAjaran;
        }

        $reference = ($referenceDate ?? now())->copy()->startOfDay();
        $matched = TahunAjaran::query()
            ->whereDate('tanggal_mulai', '<=', $reference->toDateString())
            ->whereDate('tanggal_selesai', '>=', $reference->toDateString())
            ->orderByDesc('id')
            ->first();

        if ($matched instanceof TahunAjaran) {
            return $matched;
        }

        return TahunAjaran::query()
            ->where('status', TahunAjaran::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{0:string,1:Carbon,2:Carbon}
     */
    private function resolveFallbackSemesterRange(Carbon $referenceDate, ?TahunAjaran $tahunAjaran = null): array
    {
        $semester = $referenceDate->month >= 7
            ? PeriodeAkademik::SEMESTER_GANJIL
            : PeriodeAkademik::SEMESTER_GENAP;

        if ($semester === PeriodeAkademik::SEMESTER_GANJIL) {
            $startDate = Carbon::create($referenceDate->year, 7, 1)->startOfDay();
            $endDate = Carbon::create($referenceDate->year, 12, 31)->endOfDay();
        } else {
            $startDate = Carbon::create($referenceDate->year, 1, 1)->startOfDay();
            $endDate = Carbon::create($referenceDate->year, 6, 30)->endOfDay();
        }

        if ($tahunAjaran instanceof TahunAjaran) {
            [$startDate, $endDate] = $this->clampRangeToTahunAjaran($startDate, $endDate, $tahunAjaran);
        }

        return [$semester, $startDate, $endDate];
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function clampRangeToTahunAjaran(
        Carbon $startDate,
        Carbon $endDate,
        ?TahunAjaran $tahunAjaran
    ): array {
        if (!$tahunAjaran || !$tahunAjaran->tanggal_mulai || !$tahunAjaran->tanggal_selesai) {
            return [$startDate, $endDate];
        }

        $tahunAjaranStart = Carbon::parse((string) $tahunAjaran->tanggal_mulai)->startOfDay();
        $tahunAjaranEnd = Carbon::parse((string) $tahunAjaran->tanggal_selesai)->endOfDay();

        if ($startDate->lt($tahunAjaranStart)) {
            $startDate = $tahunAjaranStart;
        }

        if ($endDate->gt($tahunAjaranEnd)) {
            $endDate = $tahunAjaranEnd;
        }

        return [$startDate, $endDate];
    }

    /**
     * @return array<string, int>
     */
    private function emptyMetrics(): array
    {
        return [
            'late_minutes' => 0,
            'late_days' => 0,
            'tap_minutes' => 0,
            'tap_days' => 0,
            'alpha_days' => 0,
            'alpha_minutes' => 0,
            'total_violation_minutes' => 0,
        ];
    }

    private function semesterLabel(string $semester): string
    {
        return match (strtolower(trim($semester))) {
            'ganjil' => 'Ganjil',
            'genap' => 'Genap',
            default => ucfirst($semester ?: 'Semester'),
        };
    }

    private function normalizeThresholdMode(mixed $value, string $fallback = self::THRESHOLD_MODE_MONITOR_ONLY): string
    {
        $mode = strtolower(trim((string) $value));

        return in_array($mode, [self::THRESHOLD_MODE_MONITOR_ONLY, self::THRESHOLD_MODE_ALERTABLE], true)
            ? $mode
            : $fallback;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function extractWorkingHoursFromSnapshot(mixed $snapshot): ?array
    {
        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : null;
        }

        if (!is_array($snapshot)) {
            return null;
        }

        $workingHours = $snapshot['working_hours'] ?? null;
        if (!is_array($workingHours)) {
            return null;
        }

        return $this->normalizeWorkingHours($workingHours);
    }

    /**
     * @param array<string, mixed> $workingHours
     * @return array<string, mixed>
     */
    private function normalizeWorkingHours(array $workingHours, ?AttendanceSchema $schema = null): array
    {
        return [
            'jam_masuk' => (string) ($workingHours['jam_masuk'] ?? '07:00'),
            'jam_pulang' => (string) ($workingHours['jam_pulang'] ?? '15:00'),
            'toleransi' => (int) ($workingHours['toleransi'] ?? 0),
            'minimal_open_time' => (int) ($workingHours['minimal_open_time'] ?? 70),
            'wajib_gps' => $workingHours['wajib_gps'] ?? ($schema?->wajib_gps ?? true),
            'wajib_foto' => $workingHours['wajib_foto'] ?? ($schema?->wajib_foto ?? true),
            'hari_kerja' => is_array($workingHours['hari_kerja'] ?? null)
                ? $workingHours['hari_kerja']
                : (is_array($schema?->hari_kerja ?? null) ? $schema->hari_kerja : null),
            'source' => $workingHours['source'] ?? null,
        ];
    }

    private function resolveWorkingMinutesFromHours(array $workingHours, int $defaultMinutes = 480): int
    {
        $jamMasuk = $workingHours['jam_masuk'] ?? null;
        $jamPulang = $workingHours['jam_pulang'] ?? null;

        if (!$jamMasuk || !$jamPulang) {
            return $defaultMinutes;
        }

        try {
            $start = $this->parseClock((string) $jamMasuk);
            $end = $this->parseClock((string) $jamPulang);
            $minutes = abs($start->diffInMinutes($end, false));

            return $minutes > 0 ? $minutes : $defaultMinutes;
        } catch (\Throwable $exception) {
            return $defaultMinutes;
        }
    }

    private function parseClock(string $value): Carbon
    {
        $raw = trim($value);

        if (preg_match('/^\d{2}:\d{2}$/', $raw) === 1) {
            return Carbon::createFromFormat('H:i', $raw);
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $raw) === 1) {
            return Carbon::createFromFormat('H:i:s', $raw);
        }

        return Carbon::parse($raw);
    }

    private function isRecordedWorkingDay(User $user, Carbon $date, ?Absensi $attendance = null, ?array $workingHours = null): bool
    {
        $hours = is_array($workingHours) ? $workingHours : $this->resolveWorkingHoursForAttendance($user, $attendance);
        $hariKerja = $hours['hari_kerja'] ?? null;

        if (is_array($hariKerja) && $hariKerja !== []) {
            $dayName = $this->normalizeDayToken($this->getDayNameInIndonesian($date));
            $normalizedWorkingDays = array_values(array_filter(array_map(
                fn ($day) => $this->normalizeDayToken((string) $day),
                $hariKerja
            )));

            return in_array($dayName, $normalizedWorkingDays, true);
        }

        return $this->attendanceTimeService->isWorkingDay($user, $date->copy());
    }

    private function normalizeDayToken(string $day): string
    {
        $normalized = strtolower(trim($day));

        return match ($normalized) {
            'monday', 'senin' => 'senin',
            'tuesday', 'selasa' => 'selasa',
            'wednesday', 'rabu' => 'rabu',
            'thursday', 'kamis' => 'kamis',
            'friday', 'jumat', "jum'at" => 'jumat',
            'saturday', 'sabtu' => 'sabtu',
            'sunday', 'minggu' => 'minggu',
            default => $normalized,
        };
    }

    private function normalizeStatus(mixed $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return $normalized === 'alpa' ? 'alpha' : $normalized;
    }

    /**
     * @param array<string, mixed> $semesterContext
     */
    private function buildSemesterPeriodKey(array $semesterContext): string
    {
        $semester = strtolower(trim((string) ($semesterContext['semester'] ?? 'semester')));
        $tahunAjaranRef = strtolower(trim((string) ($semesterContext['tahun_ajaran_nama'] ?? 'na')));

        return $semester . '|' . $tahunAjaranRef;
    }

    /**
     * @param array<string, mixed> $semesterContext
     */
    private function buildSemesterPeriodLabel(array $semesterContext): string
    {
        return trim(
            (string) ($semesterContext['semester_label'] ?? 'Semester') . ' ' . (string) ($semesterContext['tahun_ajaran_nama'] ?? '')
        );
    }

    private function monthLabel(Carbon $date): string
    {
        return match ((int) $date->month) {
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            default => 'Desember',
        } . ' ' . $date->format('Y');
    }

    private function getDayNameInIndonesian(Carbon $date): string
    {
        return match ($date->dayOfWeek) {
            Carbon::SUNDAY => 'Minggu',
            Carbon::MONDAY => 'Senin',
            Carbon::TUESDAY => 'Selasa',
            Carbon::WEDNESDAY => 'Rabu',
            Carbon::THURSDAY => 'Kamis',
            Carbon::FRIDAY => 'Jumat',
            Carbon::SATURDAY => 'Sabtu',
            default => '',
        };
    }
}
