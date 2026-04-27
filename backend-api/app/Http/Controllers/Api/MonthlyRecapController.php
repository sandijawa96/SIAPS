<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\Izin;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Services\AttendanceSchemaService;
use App\Services\AttendanceDisciplineService;
use App\Services\AttendanceTimeService;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MonthlyRecapController extends Controller
{
    private AttendanceSchemaService $attendanceSchemaService;
    private AttendanceTimeService $attendanceTimeService;
    private AttendanceDisciplineService $attendanceDisciplineService;

    public function __construct(
        AttendanceSchemaService $attendanceSchemaService,
        AttendanceTimeService $attendanceTimeService,
        AttendanceDisciplineService $attendanceDisciplineService
    )
    {
        $this->attendanceSchemaService = $attendanceSchemaService;
        $this->attendanceTimeService = $attendanceTimeService;
        $this->attendanceDisciplineService = $attendanceDisciplineService;
    }

    /**
     * Get monthly recap data for current user
     */
    public function getCurrentMonth(Request $request)
    {
        try {
            $user = Auth::user();
            $now = Carbon::now();
            $tahunAjaran = $this->resolveEffectiveTahunAjaran($request);

            $data = $this->calculateMonthlyRecap($user->id, $now->year, $now->month, $tahunAjaran);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'month' => $now->format('F Y'),
                'meta' => [
                    'tahun_ajaran_id' => $tahunAjaran?->id,
                ],
                'message' => 'Data rekapitulasi bulan berjalan berhasil dimuat'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat data rekapitulasi'
            ], 500);
        }
    }

    /**
     * Get monthly recap data for previous month
     */
    public function getPreviousMonth(Request $request)
    {
        try {
            $user = Auth::user();
            $previousMonth = Carbon::now()->subMonth();
            $tahunAjaran = $this->resolveEffectiveTahunAjaran($request);

            $data = $this->calculateMonthlyRecap($user->id, $previousMonth->year, $previousMonth->month, $tahunAjaran);

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'month' => $previousMonth->format('F Y'),
                'meta' => [
                    'tahun_ajaran_id' => $tahunAjaran?->id,
                ],
                'message' => 'Data rekapitulasi bulan sebelumnya berhasil dimuat'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat data rekapitulasi'
            ], 500);
        }
    }

    /**
     * Get monthly recap data for specific month and year
     */
    public function getSpecificMonth(Request $request)
    {
        $request->validate([
            'year' => 'required|integer|min:2020|max:2030',
            'month' => 'required|integer|min:1|max:12',
            'tahun_ajaran_id' => 'nullable|integer|exists:tahun_ajaran,id',
        ]);

        try {
            $user = Auth::user();
            $year = $request->year;
            $month = $request->month;
            $tahunAjaran = $this->resolveEffectiveTahunAjaran($request);

            $data = $this->calculateMonthlyRecap($user->id, $year, $month, $tahunAjaran);
            $monthName = Carbon::createFromDate($year, $month, 1)->format('F Y');

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'month' => $monthName,
                'meta' => [
                    'tahun_ajaran_id' => $tahunAjaran?->id,
                ],
                'message' => "Data rekapitulasi $monthName berhasil dimuat"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat data rekapitulasi'
            ], 500);
        }
    }

    /**
     * Calculate monthly recap data
     */
    private function calculateMonthlyRecap($userId, $year, $month, ?TahunAjaran $tahunAjaran = null)
    {
        $user = User::find($userId);
        $requestedMonthStart = Carbon::createFromDate($year, $month, 1)->startOfDay();
        $requestedMonthEnd = $requestedMonthStart->copy()->endOfMonth()->endOfDay();
        [$periodStart, $periodEnd] = $this->clampRangeToTahunAjaran(
            $requestedMonthStart->copy(),
            $requestedMonthEnd->copy(),
            $tahunAjaran
        );
        $periodMetrics = $this->buildMonthlyRecapPeriodMetrics($user, $periodStart, $periodEnd);

        // Initialize counters
        $masuk = (int) ($periodMetrics['masuk_days'] ?? 0);
        $cuti = (int) ($periodMetrics['cuti_days'] ?? 0);
        $alpa = 0; // Explicit alpha days from discipline metrics
        $alpaMenit = 0;
        $dinas = (int) ($periodMetrics['dinas_days'] ?? 0);
        $izin = (int) ($periodMetrics['izin_days'] ?? 0);
        $sakit = (int) ($periodMetrics['sakit_days'] ?? 0);
        $terlambat = 0;
        $tap = 0;
        $totalTK = 0;

        $policy = $this->resolvePolicy($user);
        $jamMasukDefault = Carbon::parse($policy['jam_masuk']);
        $jamPulangDefault = Carbon::parse($policy['jam_pulang']);
        $jamSekolahPerHari = abs($jamMasukDefault->diffInMinutes($jamPulangDefault, false)); // 480 menit = 8 jam sekolah

        $schoolDays = (int) ($periodMetrics['school_days_elapsed'] ?? 0);
        $disciplineMetrics = $periodMetrics['evaluation_end'] instanceof Carbon
            ? $this->attendanceDisciplineService->calculateMetrics(
                $user,
                $periodStart->copy()->startOfDay(),
                $periodMetrics['evaluation_end']->copy()->endOfDay()
            )
            : [
                'late_minutes' => 0,
                'tap_minutes' => 0,
                'alpha_days' => 0,
                'alpha_minutes' => 0,
                'total_violation_minutes' => 0,
            ];

        $alpa = (int) ($disciplineMetrics['alpha_days'] ?? 0);
        $alpaMenit = (int) ($disciplineMetrics['alpha_minutes'] ?? 0);
        $terlambatHari = (int) ($disciplineMetrics['late_days'] ?? 0);
        $terlambat = (int) ($disciplineMetrics['late_minutes'] ?? 0);
        $tapHari = (int) ($disciplineMetrics['tap_days'] ?? 0);
        $tap = (int) ($disciplineMetrics['tap_minutes'] ?? 0);
        $totalTK = (int) ($disciplineMetrics['total_violation_minutes'] ?? 0);
        $totalSchoolMinutes = $schoolDays * $jamSekolahPerHari;
        $persentasePelanggaran = $totalSchoolMinutes > 0
            ? round(($totalTK / $totalSchoolMinutes) * 100, 2)
            : 0.0;
        $disciplineSnapshot = $this->attendanceDisciplineService->buildUserDisciplineSnapshot(
            $user,
            $requestedMonthStart->copy(),
            $tahunAjaran
        );
        $usesNewThresholds = (bool) ($disciplineSnapshot['config']['uses_new_thresholds'] ?? false);
        $legacyExceeded = !$usesNewThresholds
            ? $this->isViolationThresholdExceeded(
                (int) round($totalTK),
                $persentasePelanggaran,
                (int) ($policy['legacy_violation_minutes_threshold'] ?? $policy['violation_minutes_threshold']),
                (float) ($policy['legacy_violation_percentage_threshold'] ?? $policy['violation_percentage_threshold'])
            )
            : false;
        $melewatiBatasPelanggaran = (bool) ($disciplineSnapshot['attention_needed'] ?? false) || $legacyExceeded;

        return [
            'masuk' => $masuk,
            'cuti' => $cuti,
            'alpa' => $alpa,
            'alpa_hari' => $alpa,
            'alpa_menit' => (int) round($alpaMenit),
            'dinas' => $dinas,
            'izin' => $izin,
            'sakit' => $sakit,
            'terlambat_hari' => $terlambatHari,
            'terlambat_menit' => (int) round($terlambat),
            'terlambat' => (int) round($terlambat),
            'tap_hari' => $tapHari,
            'tap_menit' => (int) round($tap),
            'tap' => (int) round($tap),
            'totalTK' => (int) round($totalTK),
            'pelanggaran_menit' => (int) round($totalTK),
            'persentase_pelanggaran' => $persentasePelanggaran,
            'batas_pelanggaran_menit' => $usesNewThresholds
                ? (int) data_get($disciplineSnapshot, 'semester_total_violation.limit', 0)
                : (int) ($policy['legacy_violation_minutes_threshold'] ?? $policy['violation_minutes_threshold']),
            'batas_pelanggaran_persen' => $usesNewThresholds
                ? 0.0
                : (float) ($policy['legacy_violation_percentage_threshold'] ?? $policy['violation_percentage_threshold']),
            'melewati_batas_pelanggaran' => $melewatiBatasPelanggaran,
            'menit_kerja_per_hari' => $jamSekolahPerHari,
            'menit_sekolah_per_hari' => $jamSekolahPerHari,
            'total_menit_kerja_bulan' => $totalSchoolMinutes,
            'total_menit_sekolah_bulan' => $totalSchoolMinutes,
            'working_days' => $schoolDays,
            'school_days' => $schoolDays,
            'school_days_in_month' => (int) ($periodMetrics['school_days_in_month'] ?? $schoolDays),
            'unrecorded_days' => (int) ($periodMetrics['unrecorded_days'] ?? 0),
            'attendance_rate' => $schoolDays > 0 ? round(($masuk / $schoolDays) * 100, 2) : 0,
            'period' => [
                'range_start' => $periodStart->toDateString(),
                'range_end' => $periodEnd->toDateString(),
                'evaluation_end' => $periodMetrics['evaluation_end'] instanceof Carbon
                    ? $periodMetrics['evaluation_end']->toDateString()
                    : null,
                'is_current_month' => (bool) ($periodMetrics['is_current_month'] ?? false),
                'today_included' => (bool) ($periodMetrics['today_included'] ?? false),
            ],
            'discipline_thresholds' => [
                'mode' => 'monthly',
                'monthly_late' => data_get($disciplineSnapshot, 'monthly_late', []),
                'semester_total_violation' => data_get($disciplineSnapshot, 'semester_total_violation', []),
                'semester_alpha' => data_get($disciplineSnapshot, 'semester_alpha', []),
                'attention_needed' => (bool) data_get($disciplineSnapshot, 'attention_needed', false),
            ],
        ];
    }

    private function resolvePolicy(?User $user): array
    {
        $globalSchema = $this->getGlobalDefaultSchema();
        $globalHours = $this->getGlobalDefaultWorkingHours($user);

        $defaults = [
            'jam_masuk' => (string) ($globalHours['jam_masuk'] ?? '07:00'),
            'jam_pulang' => (string) ($globalHours['jam_pulang'] ?? '15:00'),
            'violation_minutes_threshold' => (int) ($globalSchema?->violation_minutes_threshold ?? 480),
            'violation_percentage_threshold' => (float) ($globalSchema?->violation_percentage_threshold ?? 10.0),
            'legacy_violation_minutes_threshold' => (int) ($globalSchema?->violation_minutes_threshold ?? 480),
            'legacy_violation_percentage_threshold' => (float) ($globalSchema?->violation_percentage_threshold ?? 10.0),
        ];

        $thresholdConfig = $this->attendanceDisciplineService->resolveThresholdConfig($user);

        if (!$user) {
            return array_merge($defaults, $thresholdConfig);
        }

        $schema = $this->attendanceSchemaService->getEffectiveSchema($user);
        if (!$schema) {
            return array_merge($defaults, $thresholdConfig);
        }

        $workingHours = $schema->getEffectiveWorkingHours($user);

        return array_merge($thresholdConfig, [
            'jam_masuk' => (string) ($workingHours['jam_masuk'] ?? $defaults['jam_masuk']),
            'jam_pulang' => (string) ($workingHours['jam_pulang'] ?? $defaults['jam_pulang']),
            'violation_minutes_threshold' => (int) ($schema->violation_minutes_threshold ?? $defaults['violation_minutes_threshold']),
            'violation_percentage_threshold' => (float) ($schema->violation_percentage_threshold ?? $defaults['violation_percentage_threshold']),
            'legacy_violation_minutes_threshold' => (int) ($schema->violation_minutes_threshold ?? $defaults['legacy_violation_minutes_threshold']),
            'legacy_violation_percentage_threshold' => (float) ($schema->violation_percentage_threshold ?? $defaults['legacy_violation_percentage_threshold']),
        ]);
    }

    private function getGlobalDefaultSchema(): ?AttendanceSchema
    {
        $schema = AttendanceSchema::query()
            ->where('schema_type', 'global')
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();

        if ($schema instanceof AttendanceSchema) {
            return $schema;
        }

        $fallback = AttendanceSchema::query()
            ->where('schema_type', 'global')
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();

        return $fallback instanceof AttendanceSchema ? $fallback : null;
    }

    private function getGlobalDefaultWorkingHours(?User $user = null): array
    {
        $schema = $this->getGlobalDefaultSchema();
        if ($schema instanceof AttendanceSchema) {
            $hours = $schema->getEffectiveWorkingHours($user);
            return [
                'jam_masuk' => (string) ($hours['jam_masuk'] ?? '07:00'),
                'jam_pulang' => (string) ($hours['jam_pulang'] ?? '15:00'),
            ];
        }

        return [
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
        ];
    }

    private function isViolationThresholdExceeded(int $totalViolationMinutes, float $violationPercentage, int $minutesThreshold, float $percentageThreshold): bool
    {
        $byMinutes = $minutesThreshold > 0 && $totalViolationMinutes >= $minutesThreshold;
        $byPercentage = $percentageThreshold > 0 && $violationPercentage >= $percentageThreshold;

        return $byMinutes || $byPercentage;
    }

    private function resolveEffectiveTahunAjaran(Request $request): ?TahunAjaran
    {
        if ($request->filled('tahun_ajaran_id')) {
            $requested = TahunAjaran::query()->find((int) $request->tahun_ajaran_id);
            if ($requested instanceof TahunAjaran) {
                return $requested;
            }
        }

        return TahunAjaran::query()
            ->where('status', TahunAjaran::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();
    }

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

        if ($endDate->lt($tahunAjaranStart) || $startDate->gt($tahunAjaranEnd)) {
            return [$tahunAjaranStart, $tahunAjaranStart->copy()->subSecond()];
        }

        $effectiveStart = $startDate->lt($tahunAjaranStart)
            ? $tahunAjaranStart
            : $startDate;
        $effectiveEnd = $endDate->gt($tahunAjaranEnd)
            ? $tahunAjaranEnd
            : $endDate;

        return [$effectiveStart, $effectiveEnd];
    }

    private function buildMonthlyRecapPeriodMetrics(User $user, Carbon $periodStart, Carbon $periodEnd): array
    {
        if ($periodEnd->lt($periodStart)) {
            return [
                'school_days_in_month' => 0,
                'school_days_elapsed' => 0,
                'masuk_days' => 0,
                'izin_days' => 0,
                'sakit_days' => 0,
                'cuti_days' => 0,
                'dinas_days' => 0,
                'unrecorded_days' => 0,
                'evaluation_end' => null,
                'is_current_month' => false,
                'today_included' => false,
            ];
        }

        $attendanceByDate = $this->buildAttendanceRecordsByDate($user, $periodStart, $periodEnd);
        $leaveByDate = $this->buildApprovedLeaveKindByDate($user, $periodStart, $periodEnd);
        $today = Carbon::today();
        $isCurrentMonth = $periodStart->year === $today->year && $periodStart->month === $today->month;
        $schoolDaysInMonth = 0;
        $schoolDaysElapsed = 0;
        $masukDays = 0;
        $izinDays = 0;
        $sakitDays = 0;
        $cutiDays = 0;
        $dinasDays = 0;
        $unrecordedDays = 0;
        $todayIncluded = false;
        $evaluationEnd = null;

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            if (!$this->isAttendanceEvaluationDay($user, $date->copy())) {
                continue;
            }

            $schoolDaysInMonth++;
            $dateKey = $date->toDateString();
            $attendance = $attendanceByDate[$dateKey] ?? null;
            $leaveKind = $leaveByDate[$dateKey] ?? null;
            $includeToday = $date->isSameDay($today) && ($attendance !== null || $leaveKind !== null);
            $isElapsed = $date->lt($today) || $includeToday;

            if (!$isElapsed) {
                continue;
            }

            if ($includeToday) {
                $todayIncluded = true;
            }

            $schoolDaysElapsed++;
            $evaluationEnd = $date->copy();

            if ($attendance instanceof Absensi) {
                $status = $this->normalizeStatus($attendance->status);

                if (!empty($attendance->jam_masuk) || in_array($status, ['hadir', 'terlambat'], true)) {
                    $masukDays++;
                    continue;
                }

                if ($status === 'izin') {
                    $izinDays++;
                    continue;
                }

                if ($status === 'sakit') {
                    $sakitDays++;
                    continue;
                }
            }

            if ($leaveKind === 'cuti') {
                $cutiDays++;
                continue;
            }

            if ($leaveKind === 'dinas') {
                $dinasDays++;
                continue;
            }

            if ($leaveKind === 'izin') {
                $izinDays++;
                continue;
            }

            if ($leaveKind === 'sakit') {
                $sakitDays++;
                continue;
            }

            $unrecordedDays++;
        }

        return [
            'school_days_in_month' => $schoolDaysInMonth,
            'school_days_elapsed' => $schoolDaysElapsed,
            'masuk_days' => $masukDays,
            'izin_days' => $izinDays,
            'sakit_days' => $sakitDays,
            'cuti_days' => $cutiDays,
            'dinas_days' => $dinasDays,
            'unrecorded_days' => $unrecordedDays,
            'evaluation_end' => $evaluationEnd,
            'is_current_month' => $isCurrentMonth,
            'today_included' => $todayIncluded,
        ];
    }

    private function buildAttendanceRecordsByDate(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $records = Absensi::query()
            ->where('user_id', $user->id)
            ->whereDate('tanggal', '>=', $startDate->toDateString())
            ->whereDate('tanggal', '<=', $endDate->toDateString())
            ->orderByDesc('id')
            ->get();

        $attendanceByDate = [];
        foreach ($records as $attendance) {
            if (!$attendance instanceof Absensi || !$attendance->tanggal) {
                continue;
            }

            $dateKey = $attendance->tanggal->format('Y-m-d');
            if (!isset($attendanceByDate[$dateKey])) {
                $attendanceByDate[$dateKey] = $attendance;
            }
        }

        return $attendanceByDate;
    }

    private function buildApprovedLeaveKindByDate(User $user, Carbon $startDate, Carbon $endDate): array
    {
        $allowedJenisIzin = $user->hasRole(RoleNames::aliases(RoleNames::SISWA))
            ? Izin::studentJenisIzin()
            : Izin::employeeJenisIzin();

        $leaveRecords = Izin::query()
            ->where('user_id', $user->id)
            ->where('status', 'approved')
            ->whereIn('jenis_izin', $allowedJenisIzin)
            ->whereDate('tanggal_mulai', '<=', $endDate->toDateString())
            ->whereDate('tanggal_selesai', '>=', $startDate->toDateString())
            ->get(['tanggal_mulai', 'tanggal_selesai', 'jenis_izin']);

        $leaveByDate = [];
        foreach ($leaveRecords as $leave) {
            $leaveStart = Carbon::parse((string) $leave->tanggal_mulai)->startOfDay();
            $leaveEnd = Carbon::parse((string) $leave->tanggal_selesai)->startOfDay();
            $leaveKind = match (strtolower((string) $leave->jenis_izin)) {
                'cuti' => 'cuti',
                'dinas_luar' => 'dinas',
                'sakit' => 'sakit',
                default => 'izin',
            };

            for ($date = $leaveStart->copy(); $date->lte($leaveEnd); $date->addDay()) {
                if ($date->lt($startDate) || $date->gt($endDate)) {
                    continue;
                }

                $dateKey = $date->toDateString();
                if (!isset($leaveByDate[$dateKey]) || $leaveByDate[$dateKey] === 'izin') {
                    $leaveByDate[$dateKey] = $leaveKind;
                }
            }
        }

        return $leaveByDate;
    }

    private function isAttendanceEvaluationDay(User $user, Carbon $date): bool
    {
        if (!$this->attendanceTimeService->isAttendanceRequiredOnDate($user, $date->copy())) {
            return false;
        }

        return $this->attendanceTimeService->isWorkingDayForDate($user, $date->copy());
    }

    private function normalizeStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));

        return $normalized === 'alpa' ? 'alpha' : $normalized;
    }
}

