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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AbsensiController extends Controller
{
    private const ATTENDANCE_RELATIONS = [
        'lokasiMasuk:id,nama_lokasi',
        'lokasiPulang:id,nama_lokasi',
    ];

    private const DEFAULT_WORKING_DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

    private const DAY_MAP = [
        'Minggu' => 0,
        'Senin' => 1,
        'Selasa' => 2,
        'Rabu' => 3,
        'Kamis' => 4,
        'Jumat' => 5,
        'Sabtu' => 6,
    ];

    private const ALPHA_STATUSES = ['alpha', 'alpa'];

    protected AttendanceSchemaService $attendanceSchemaService;
    protected AttendanceTimeService $attendanceTimeService;
    protected AttendanceDisciplineService $attendanceDisciplineService;

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

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthenticatedResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildHistoryPaginator($user, $request, $this->resolveEffectiveTahunAjaran($request)),
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthenticatedResponse();
        }

        [$month, $year] = $this->resolvePeriod($request);

        return response()->json([
            'success' => true,
            'data' => $this->buildStatisticsPayload(
                $user,
                $month,
                $year,
                $this->resolveEffectiveTahunAjaran($request)
            ),
        ]);
    }

    public function todayStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthenticatedResponse();
        }

        $absensi = $this->buildAttendanceQueryForUser($user)
            ->whereDate('tanggal', today())
            ->first();

        if (!$absensi) {
            return $this->attendanceNotFoundResponse();
        }

        $status = [
            'sudah_checkin' => true,
            'sudah_checkout' => $absensi->jam_pulang !== null,
            'detail' => $this->transformAttendance($absensi),
        ];

        return response()->json([
            'success' => true,
            'data' => $status,
        ]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->unauthenticatedResponse();
        }

        $absensi = Absensi::with(self::ATTENDANCE_RELATIONS)->find($id);

        if (!$absensi) {
            return $this->attendanceNotFoundResponse();
        }

        if ((int) $absensi->user_id !== (int) $user->id) {
            return $this->forbiddenAttendanceResponse();
        }

        return response()->json([
            'success' => true,
            'data' => $this->transformAttendance($absensi),
        ]);
    }

    private function transformAttendance(Absensi $attendance): array
    {
        $status = $this->normalizeStatus($attendance->status);
        $validationStatus = strtolower(trim((string) $attendance->validation_status)) === 'valid'
            ? 'valid'
            : 'warning';
        $warningSummary = $attendance->fraud_decision_reason;

        return [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'tanggal' => $attendance->tanggal?->format('Y-m-d'),
            'jam_masuk' => $this->formatTime($attendance->jam_masuk),
            'jam_pulang' => $this->formatTime($attendance->jam_pulang),
            'jam_masuk_format' => $attendance->jam_masuk_format,
            'jam_pulang_format' => $attendance->jam_pulang_format,
            'durasi_kerja' => $attendance->durasi_kerja,
            'durasi_kerja_format' => $attendance->durasi_kerja_format,
            'durasi_sekolah' => $attendance->durasi_kerja,
            'durasi_sekolah_format' => $attendance->durasi_kerja_format,
            'status' => $attendance->status,
            'status_label' => $this->formatStatusLabel($status),
            'is_late' => $status === 'terlambat',
            'has_check_in' => !empty($attendance->jam_masuk),
            'has_check_out' => !empty($attendance->jam_pulang),
            'metode_absensi' => $attendance->metode_absensi,
            'keterangan' => $attendance->keterangan,
            'is_manual' => (bool) $attendance->is_manual,
            'is_verified' => (bool) $attendance->is_verified,
            'verification_status' => $attendance->verification_status,
            'verified_at' => $attendance->verified_at?->toIso8601String(),
            'face_score_checkin' => $this->toFloat($attendance->face_score_checkin),
            'face_score_checkout' => $this->toFloat($attendance->face_score_checkout),
            'latitude_masuk' => $this->toFloat($attendance->latitude_masuk),
            'longitude_masuk' => $this->toFloat($attendance->longitude_masuk),
            'latitude_pulang' => $this->toFloat($attendance->latitude_pulang),
            'longitude_pulang' => $this->toFloat($attendance->longitude_pulang),
            'gps_accuracy_masuk' => $this->toFloat($attendance->gps_accuracy_masuk),
            'gps_accuracy_pulang' => $this->toFloat($attendance->gps_accuracy_pulang),
            'foto_masuk' => $attendance->foto_masuk,
            'foto_pulang' => $attendance->foto_pulang,
            'foto_masuk_url' => $attendance->foto_masuk_url,
            'foto_pulang_url' => $attendance->foto_keluar_url,
            'lokasi_masuk_id' => $attendance->lokasi_masuk_id,
            'lokasi_pulang_id' => $attendance->lokasi_pulang_id,
            'lokasi_masuk_nama' => $attendance->lokasiMasuk?->nama_lokasi,
            'lokasi_pulang_nama' => $attendance->lokasiPulang?->nama_lokasi,
            'source_type' => 'attendance',
            'source_is_synthetic' => false,
            'validation_status' => $validationStatus,
            'has_warning' => $validationStatus !== 'valid',
            'warning_summary' => $warningSummary,
            'risk_level' => 'low',
            'risk_score' => 0,
            'fraud_flags_count' => (int) ($attendance->fraud_flags_count ?? 0),
            'created_at' => $attendance->created_at?->toIso8601String(),
            'updated_at' => $attendance->updated_at?->toIso8601String(),
        ];
    }

    private function formatTime($value): ?string
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        try {
            return Carbon::parse((string) $value)->format('H:i:s');
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    private function formatStatusLabel(string $status): string
    {
        return match ($status) {
            'hadir' => 'Hadir',
            'terlambat' => 'Terlambat',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alpha' => 'Alpha',
            default => ucfirst(str_replace('_', ' ', $status ?: 'unknown')),
        };
    }

    private function normalizeStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));
        return $normalized === 'alpa' ? 'alpha' : $normalized;
    }

    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function buildHistoryPaginator(
        User $user,
        Request $request,
        ?TahunAjaran $tahunAjaran = null
    ): LengthAwarePaginator
    {
        [$rangeStart, $rangeEnd] = $this->resolveHistoryRange($request);
        [$rangeStart, $rangeEnd] = $this->clampRangeToTahunAjaran($rangeStart, $rangeEnd, $tahunAjaran);

        if ($rangeEnd->lt($rangeStart)) {
            return $this->emptyHistoryPaginator($request);
        }

        $query = $this->buildAttendanceQueryForUser($user);
        $query->whereDate('tanggal', '>=', $rangeStart->toDateString())
            ->whereDate('tanggal', '<=', $rangeEnd->toDateString());

        $attendanceRecords = $query->orderBy('tanggal', 'desc')->get();
        $items = $attendanceRecords
            ->map(fn (Absensi $attendance) => $this->transformAttendance($attendance))
            ->all();

        $existingDateKeys = [];
        foreach ($attendanceRecords as $attendance) {
            $dateKey = $attendance->tanggal?->format('Y-m-d');
            if ($dateKey) {
                $existingDateKeys[$dateKey] = true;
            }
        }

        $items = array_merge(
            $items,
            $this->buildHolidayHistoryItems($user, $rangeStart, $rangeEnd, $existingDateKeys)
        );

        usort($items, static function (array $left, array $right): int {
            return strcmp((string) ($right['tanggal'] ?? ''), (string) ($left['tanggal'] ?? ''));
        });

        $perPage = max(1, (int) $request->get('per_page', 15));
        $page = max(1, (int) $request->get('page', 1));
        $total = count($items);
        $offset = ($page - 1) * $perPage;

        return new \Illuminate\Pagination\LengthAwarePaginator(
            array_slice($items, $offset, $perPage),
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function emptyHistoryPaginator(Request $request): LengthAwarePaginator
    {
        $perPage = max(1, (int) $request->get('per_page', 15));
        $page = max(1, (int) $request->get('page', 1));

        return new \Illuminate\Pagination\LengthAwarePaginator(
            [],
            0,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );
    }

    private function buildAttendanceQueryForUser(User $user): Builder
    {
        return Absensi::with(self::ATTENDANCE_RELATIONS)
            ->where('user_id', $user->id);
    }

    private function resolveHistoryRange(Request $request): array
    {
        if ($request->filled('start_date') && $request->filled('end_date')) {
            return [
                Carbon::parse((string) $request->start_date)->startOfDay(),
                Carbon::parse((string) $request->end_date)->endOfDay(),
            ];
        }

        [$month, $year] = $this->resolvePeriod($request);
        $start = Carbon::create($year, $month, 1)->startOfDay();

        return [
            $start,
            $start->copy()->endOfMonth()->endOfDay(),
        ];
    }

    private function resolvePeriod(Request $request): array
    {
        return [
            (int) $request->get('month', date('m')),
            (int) $request->get('year', date('Y')),
        ];
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

    private function buildHolidayHistoryItems(
        User $user,
        Carbon $startDate,
        Carbon $endDate,
        array $existingDateKeys
    ): array {
        $items = [];
        $today = Carbon::today();
        $cursorEnd = $endDate->lt($today) ? $endDate->copy() : $today->copy()->endOfDay();

        if ($cursorEnd->lt($startDate)) {
            return [];
        }

        for ($date = $startDate->copy(); $date->lte($cursorEnd); $date->addDay()) {
            $dateKey = $date->toDateString();

            if (isset($existingDateKeys[$dateKey])) {
                continue;
            }

            if ($this->attendanceTimeService->isWorkingDay($user, $date->copy())) {
                continue;
            }

            $items[] = $this->buildHolidayHistoryItem($user, $date->copy());
        }

        return $items;
    }

    private function buildHolidayHistoryItem(User $user, Carbon $date): array
    {
        return [
            'id' => 'holiday-' . $date->format('Ymd'),
            'user_id' => (string) $user->id,
            'tanggal' => $date->format('Y-m-d'),
            'jam_masuk' => null,
            'jam_pulang' => null,
            'jam_masuk_format' => null,
            'jam_pulang_format' => null,
            'durasi_kerja' => null,
            'durasi_kerja_format' => null,
            'durasi_sekolah' => null,
            'durasi_sekolah_format' => null,
            'status' => 'libur',
            'status_label' => 'Libur',
            'is_late' => false,
            'has_check_in' => false,
            'has_check_out' => false,
            'metode_absensi' => null,
            'keterangan' => 'Hari libur sesuai skema aktif',
            'is_manual' => false,
            'is_verified' => false,
            'verification_status' => null,
            'verified_at' => null,
            'face_score_checkin' => null,
            'face_score_checkout' => null,
            'latitude_masuk' => null,
            'longitude_masuk' => null,
            'latitude_pulang' => null,
            'longitude_pulang' => null,
            'gps_accuracy_masuk' => null,
            'gps_accuracy_pulang' => null,
            'foto_masuk' => null,
            'foto_pulang' => null,
            'foto_masuk_url' => null,
            'foto_pulang_url' => null,
            'lokasi_masuk_id' => null,
            'lokasi_pulang_id' => null,
            'lokasi_masuk_nama' => null,
            'lokasi_pulang_nama' => null,
            'source_type' => 'synthetic_holiday',
            'source_is_synthetic' => true,
            'created_at' => $date->copy()->startOfDay()->toIso8601String(),
            'updated_at' => $date->copy()->startOfDay()->toIso8601String(),
        ];
    }

    private function buildStatisticsPayload(
        User $user,
        int $month,
        int $year,
        ?TahunAjaran $tahunAjaran = null
    ): array
    {
        if (!$this->isMonthInTahunAjaran($month, $year, $tahunAjaran)) {
            $policy = $this->resolvePolicy($user);
            $workingMinutesPerDay = $this->getWorkingMinutesPerDay($user);

            return [
                'total_hari_kerja' => 0,
                'total_hari_sekolah_bulan' => 0,
                'total_hari_sekolah_berjalan' => 0,
                'total_hari_tanpa_catatan' => 0,
                'school_days_in_month' => 0,
                'elapsed_school_days' => 0,
                'unrecorded_days' => 0,
                'menit_kerja_per_hari' => $workingMinutesPerDay,
                'menit_sekolah_per_hari' => $workingMinutesPerDay,
                'total_menit_kerja' => 0,
                'total_menit_sekolah' => 0,
                'total_hadir' => 0,
                'present_days' => 0,
                'total_izin' => 0,
                'total_sakit' => 0,
                'total_alpha' => 0,
                'absent_days' => 0,
                'total_alpha_menit' => 0,
                'total_terlambat' => 0,
                'late_days' => 0,
                'total_terlambat_menit' => 0,
                'late_minutes' => 0,
                'total_tap_hari' => 0,
                'tap_days' => 0,
                'total_tap_menit' => 0,
                'total_pelanggaran_menit' => 0,
                'persentase_pelanggaran' => 0.0,
                'batas_pelanggaran_menit' => $policy['violation_minutes_threshold'],
                'batas_pelanggaran_persen' => $policy['violation_percentage_threshold'],
                'melewati_batas_pelanggaran' => false,
                'persentase_kehadiran' => 0,
                'attendance_percentage' => 0,
                'period' => [
                    'month' => $month,
                    'year' => $year,
                    'range_start' => null,
                    'range_end' => null,
                    'evaluation_end' => null,
                    'is_current_month' => false,
                    'today_included' => false,
                ],
            ];
        }

        [$periodStart, $periodEnd] = $this->resolveStatisticsRange($month, $year, $tahunAjaran);
        $periodMetrics = $this->buildAttendancePeriodMetrics($user, $periodStart, $periodEnd);
        $policy = $this->resolvePolicy($user);
        $workingMinutesPerDay = $this->getWorkingMinutesPerDay($user);
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
        $totalAlpha = (int) ($periodMetrics['alpha_days'] ?? 0);
        $totalPresent = (int) ($periodMetrics['present_days'] ?? 0);
        $totalLateDays = (int) ($periodMetrics['late_days'] ?? 0);
        $totalTerlambatMenit = (int) ($disciplineMetrics['late_minutes'] ?? 0);
        $totalTapDays = (int) ($disciplineMetrics['tap_days'] ?? 0);
        $totalTapMenit = (int) ($disciplineMetrics['tap_minutes'] ?? 0);
        $totalAlphaMenit = (int) ($disciplineMetrics['alpha_minutes'] ?? 0);
        $totalPelanggaranMenit = (int) ($disciplineMetrics['total_violation_minutes'] ?? 0);
        $totalHariKerja = $periodMetrics['working_days'];
        $totalMenitKerja = $totalHariKerja * $workingMinutesPerDay;
        $persentasePelanggaran = $totalMenitKerja > 0
            ? round(($totalPelanggaranMenit / $totalMenitKerja) * 100, 2)
            : 0.0;
        $disciplineSnapshot = $this->attendanceDisciplineService->buildUserDisciplineSnapshot(
            $user,
            Carbon::create($year, $month, 1)->startOfMonth(),
            $tahunAjaran
        );
        $usesNewThresholds = (bool) ($disciplineSnapshot['config']['uses_new_thresholds'] ?? false);
        $legacyExceeded = !$usesNewThresholds
            ? $this->isViolationThresholdExceeded(
                $totalPelanggaranMenit,
                $persentasePelanggaran,
                (int) ($policy['legacy_violation_minutes_threshold'] ?? $policy['violation_minutes_threshold']),
                (float) ($policy['legacy_violation_percentage_threshold'] ?? $policy['violation_percentage_threshold'])
            )
            : false;

        return [
            'total_hari_kerja' => $totalHariKerja,
            'total_hari_sekolah_bulan' => (int) ($periodMetrics['school_days_in_month'] ?? $totalHariKerja),
            'total_hari_sekolah_berjalan' => $totalHariKerja,
            'total_hari_tanpa_catatan' => (int) ($periodMetrics['unrecorded_days'] ?? 0),
            'school_days_in_month' => (int) ($periodMetrics['school_days_in_month'] ?? $totalHariKerja),
            'elapsed_school_days' => $totalHariKerja,
            'unrecorded_days' => (int) ($periodMetrics['unrecorded_days'] ?? 0),
            'menit_kerja_per_hari' => $workingMinutesPerDay,
            'menit_sekolah_per_hari' => $workingMinutesPerDay,
            'total_menit_kerja' => $totalMenitKerja,
            'total_menit_sekolah' => $totalMenitKerja,
            'total_hadir' => (int) ($periodMetrics['hadir_days'] ?? 0),
            'present_days' => $totalPresent,
            'total_izin' => (int) ($periodMetrics['izin_days'] ?? 0),
            'total_sakit' => (int) ($periodMetrics['sakit_days'] ?? 0),
            'total_alpha' => $totalAlpha,
            'absent_days' => $totalAlpha,
            'total_alpha_menit' => $totalAlphaMenit,
            'total_terlambat' => $totalLateDays,
            'late_days' => $totalLateDays,
            'total_terlambat_menit' => $totalTerlambatMenit,
            'late_minutes' => $totalTerlambatMenit,
            'total_tap_hari' => $totalTapDays,
            'tap_days' => $totalTapDays,
            'total_tap_menit' => $totalTapMenit,
            'total_pelanggaran_menit' => $totalPelanggaranMenit,
            'persentase_pelanggaran' => $persentasePelanggaran,
            'batas_pelanggaran_menit' => $usesNewThresholds
                ? (int) data_get($disciplineSnapshot, 'semester_total_violation.limit', 0)
                : (int) ($policy['legacy_violation_minutes_threshold'] ?? $policy['violation_minutes_threshold']),
            'batas_pelanggaran_persen' => $usesNewThresholds
                ? 0.0
                : (float) ($policy['legacy_violation_percentage_threshold'] ?? $policy['violation_percentage_threshold']),
            'melewati_batas_pelanggaran' => (bool) data_get($disciplineSnapshot, 'attention_needed', false) || $legacyExceeded,
            'persentase_kehadiran' => $this->calculateAttendancePercentage($periodMetrics),
            'attendance_percentage' => $this->calculateAttendancePercentage($periodMetrics),
            'discipline_thresholds' => [
                'mode' => 'monthly',
                'monthly_late' => data_get($disciplineSnapshot, 'monthly_late', []),
                'semester_total_violation' => data_get($disciplineSnapshot, 'semester_total_violation', []),
                'semester_alpha' => data_get($disciplineSnapshot, 'semester_alpha', []),
                'attention_needed' => (bool) data_get($disciplineSnapshot, 'attention_needed', false),
            ],
            'period' => [
                'month' => $month,
                'year' => $year,
                'range_start' => $periodStart->toDateString(),
                'range_end' => $periodEnd->toDateString(),
                'evaluation_end' => $periodMetrics['evaluation_end'] instanceof Carbon
                    ? $periodMetrics['evaluation_end']->toDateString()
                    : null,
                'is_current_month' => (bool) ($periodMetrics['is_current_month'] ?? false),
                'today_included' => (bool) ($periodMetrics['today_included'] ?? false),
            ],
        ];
    }

    private function isMonthInTahunAjaran(int $month, int $year, ?TahunAjaran $tahunAjaran): bool
    {
        if (!$tahunAjaran || !$tahunAjaran->tanggal_mulai || !$tahunAjaran->tanggal_selesai) {
            return true;
        }

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->endOfDay();
        $tahunAjaranStart = Carbon::parse((string) $tahunAjaran->tanggal_mulai)->startOfDay();
        $tahunAjaranEnd = Carbon::parse((string) $tahunAjaran->tanggal_selesai)->endOfDay();

        return !($monthEnd->lt($tahunAjaranStart) || $monthStart->gt($tahunAjaranEnd));
    }

    private function countWorkingDays($month, $year, $user = null)
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        $hariKerja = self::DEFAULT_WORKING_DAYS;
        if ($user) {
            $effectiveSchema = $this->attendanceSchemaService->getEffectiveSchema($user);
            if ($effectiveSchema && $effectiveSchema->hari_kerja) {
                $hariKerja = $effectiveSchema->hari_kerja;
            }
        }

        $workingDayNumbers = array_map(static function ($day) {
            return self::DAY_MAP[$day] ?? null;
        }, $hariKerja);

        $workingDayNumbers = array_values(array_filter($workingDayNumbers, static function ($day) {
            return $day !== null;
        }));

        $workingDays = 0;
        for ($date = $start; $date->lte($end); $date->addDay()) {
            if (in_array($date->dayOfWeek, $workingDayNumbers, true)) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    private function resolveStatisticsRange(int $month, int $year, ?TahunAjaran $tahunAjaran = null): array
    {
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = $periodStart->copy()->endOfMonth()->endOfDay();

        return $this->clampRangeToTahunAjaran($periodStart, $periodEnd, $tahunAjaran);
    }

    private function buildAttendancePeriodMetrics(User $user, Carbon $periodStart, Carbon $periodEnd): array
    {
        if ($periodEnd->lt($periodStart)) {
            return [
                'working_days' => 0,
                'school_days_in_month' => 0,
                'hadir_days' => 0,
                'present_days' => 0,
                'late_days' => 0,
                'izin_days' => 0,
                'sakit_days' => 0,
                'alpha_days' => 0,
                'unrecorded_days' => 0,
                'evaluation_end' => null,
                'is_current_month' => false,
                'today_included' => false,
            ];
        }

        $attendanceByDate = $this->buildAttendanceRecordsByDate($user, $periodStart, $periodEnd);
        $leaveStatusByDate = $this->buildApprovedLeaveStatusByDate($user, $periodStart, $periodEnd);
        $today = Carbon::today();
        $isCurrentMonth = $periodStart->year === $today->year && $periodStart->month === $today->month;
        $monthSchoolDays = 0;
        $elapsedSchoolDays = 0;
        $hadirDays = 0;
        $presentDays = 0;
        $lateDays = 0;
        $izinDays = 0;
        $sakitDays = 0;
        $alphaDays = 0;
        $unrecordedDays = 0;
        $todayIncluded = false;
        $evaluationEnd = null;

        for ($date = $periodStart->copy(); $date->lte($periodEnd); $date->addDay()) {
            if (!$this->isAttendanceEvaluationDay($user, $date->copy())) {
                continue;
            }

            $monthSchoolDays++;
            $dateKey = $date->toDateString();
            $attendance = $attendanceByDate[$dateKey] ?? null;
            $leaveStatus = $leaveStatusByDate[$dateKey] ?? null;
            $includeToday = $date->isSameDay($today) && ($attendance !== null || $leaveStatus !== null);
            $isElapsed = $date->lt($today) || $includeToday;

            if (!$isElapsed) {
                continue;
            }

            if ($includeToday) {
                $todayIncluded = true;
            }

            $elapsedSchoolDays++;
            $evaluationEnd = $date->copy();

            if ($attendance instanceof Absensi) {
                $status = $this->normalizeStatus($attendance->status);
                $lateMinutes = $this->attendanceDisciplineService->calculateLateMinutesFromAttendance($user, $attendance);
                $isLateAttendance = $lateMinutes > 0;

                if ($status === 'hadir') {
                    $hadirDays++;
                    $presentDays++;
                    if ($isLateAttendance) {
                        $lateDays++;
                    }
                    continue;
                }

                if ($status === 'terlambat') {
                    if ($isLateAttendance) {
                        $lateDays++;
                    }
                    $presentDays++;
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

                if ($status === 'alpha') {
                    $alphaDays++;
                    continue;
                }
            }

            if ($leaveStatus === 'sakit') {
                $sakitDays++;
                continue;
            }

            if ($leaveStatus === 'izin') {
                $izinDays++;
                continue;
            }

            $unrecordedDays++;
        }

        return [
            'working_days' => $elapsedSchoolDays,
            'school_days_in_month' => $monthSchoolDays,
            'hadir_days' => $hadirDays,
            'present_days' => $presentDays,
            'late_days' => $lateDays,
            'izin_days' => $izinDays,
            'sakit_days' => $sakitDays,
            'alpha_days' => $alphaDays,
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

    private function buildApprovedLeaveStatusByDate(User $user, Carbon $startDate, Carbon $endDate): array
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

        $statusByDate = [];
        foreach ($leaveRecords as $leave) {
            $leaveStart = Carbon::parse((string) $leave->tanggal_mulai)->startOfDay();
            $leaveEnd = Carbon::parse((string) $leave->tanggal_selesai)->startOfDay();
            $mappedStatus = strtolower((string) $leave->jenis_izin) === 'sakit' ? 'sakit' : 'izin';

            for ($date = $leaveStart->copy(); $date->lte($leaveEnd); $date->addDay()) {
                if ($date->lt($startDate) || $date->gt($endDate)) {
                    continue;
                }

                $dateKey = $date->toDateString();
                if (($statusByDate[$dateKey] ?? null) === 'sakit') {
                    continue;
                }

                $statusByDate[$dateKey] = $mappedStatus;
            }
        }

        return $statusByDate;
    }

    private function isAttendanceEvaluationDay(User $user, Carbon $date): bool
    {
        if (!$this->attendanceTimeService->isAttendanceRequiredOnDate($user, $date->copy())) {
            return false;
        }

        return $this->attendanceTimeService->isWorkingDayForDate($user, $date->copy());
    }

    private function calculateAttendancePercentage(array $periodMetrics): float
    {
        $workingDays = (int) ($periodMetrics['working_days'] ?? 0);
        if ($workingDays === 0) {
            return 0;
        }

        $totalPresent = (int) ($periodMetrics['present_days'] ?? 0);

        return round(($totalPresent / $workingDays) * 100, 2);
    }

    private function getWorkingMinutesPerDay(?User $user = null): int
    {
        $defaultMinutes = 8 * 60;

        if (!$user) {
            return $defaultMinutes;
        }

        $effectiveSchema = $this->attendanceSchemaService->getEffectiveSchema($user);
        if (!$effectiveSchema) {
            return $defaultMinutes;
        }

        $workingHours = $effectiveSchema->getEffectiveWorkingHours($user);
        $jamMasuk = $workingHours['jam_masuk'] ?? null;
        $jamPulang = $workingHours['jam_pulang'] ?? null;

        if (!$jamMasuk || !$jamPulang) {
            return $defaultMinutes;
        }

        try {
            $start = Carbon::parse((string) $jamMasuk);
            $end = Carbon::parse((string) $jamPulang);
            $minutes = abs($start->diffInMinutes($end, false));
            return $minutes > 0 ? $minutes : $defaultMinutes;
        } catch (\Throwable $e) {
            return $defaultMinutes;
        }
    }

    private function resolvePolicy(User $user): array
    {
        $globalSchema = $this->getGlobalDefaultSchema();
        $globalHours = $this->getGlobalDefaultWorkingHours($user);

        $defaults = [
            'jam_masuk' => (string) ($globalHours['jam_masuk'] ?? '07:00'),
            'violation_minutes_threshold' => (int) ($globalSchema?->violation_minutes_threshold ?? 480),
            'violation_percentage_threshold' => (float) ($globalSchema?->violation_percentage_threshold ?? 10.0),
            'legacy_violation_minutes_threshold' => (int) ($globalSchema?->violation_minutes_threshold ?? 480),
            'legacy_violation_percentage_threshold' => (float) ($globalSchema?->violation_percentage_threshold ?? 10.0),
        ];

        $thresholdConfig = $this->attendanceDisciplineService->resolveThresholdConfig($user);

        $effectiveSchema = $this->attendanceSchemaService->getEffectiveSchema($user);
        if (!$effectiveSchema) {
            return array_merge($defaults, $thresholdConfig);
        }

        $workingHours = $effectiveSchema->getEffectiveWorkingHours($user);

        return array_merge($thresholdConfig, [
            'jam_masuk' => (string) ($workingHours['jam_masuk'] ?? $defaults['jam_masuk']),
            'violation_minutes_threshold' => (int) ($effectiveSchema->violation_minutes_threshold ?? $defaults['violation_minutes_threshold']),
            'violation_percentage_threshold' => (float) ($effectiveSchema->violation_percentage_threshold ?? $defaults['violation_percentage_threshold']),
            'legacy_violation_minutes_threshold' => (int) ($effectiveSchema->violation_minutes_threshold ?? $defaults['legacy_violation_minutes_threshold']),
            'legacy_violation_percentage_threshold' => (float) ($effectiveSchema->violation_percentage_threshold ?? $defaults['legacy_violation_percentage_threshold']),
        ]);
    }

    private function countLateMinutes(int $userId, $month, $year, string $jamMasukReference): int
    {
        $jamMasukDefault = Carbon::parse($jamMasukReference);

        return (int) Absensi::where('user_id', $userId)
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->whereNotNull('jam_masuk')
            ->get()
            ->sum(function ($attendance) use ($jamMasukDefault) {
                try {
                    $jamMasukActual = Carbon::parse((string) $attendance->jam_masuk);
                    return $jamMasukActual->gt($jamMasukDefault)
                        ? $jamMasukActual->diffInMinutes($jamMasukDefault, true)
                        : 0;
                } catch (\Throwable $e) {
                    return 0;
                }
            });
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
                'toleransi' => (int) ($hours['toleransi'] ?? 15),
            ];
        }

        return [
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi' => 15,
        ];
    }

    private function countTapMinutes(int $userId, $month, $year, int $workingMinutesPerDay): int
    {
        $tapCount = Absensi::where('user_id', $userId)
            ->whereYear('tanggal', $year)
            ->whereMonth('tanggal', $month)
            ->whereNotNull('jam_masuk')
            ->whereNull('jam_pulang')
            ->whereNotIn('status', self::ALPHA_STATUSES)
            ->count();

        return (int) round($tapCount * ($workingMinutesPerDay * 0.5));
    }

    private function resolveWorkingDayNumbers(?User $user = null): array
    {
        $hariKerja = self::DEFAULT_WORKING_DAYS;
        if ($user) {
            $effectiveSchema = $this->attendanceSchemaService->getEffectiveSchema($user);
            if ($effectiveSchema && $effectiveSchema->hari_kerja) {
                $hariKerja = $effectiveSchema->hari_kerja;
            }
        }

        return array_values(array_filter(
            array_map(static function ($day) {
                return self::DAY_MAP[$day] ?? null;
            }, $hariKerja),
            static function ($day) {
                return $day !== null;
            }
        ));
    }

    private function resolveStatusQueryValues(string $status): array
    {
        $normalized = $this->normalizeStatus($status);

        if ($normalized === 'alpha') {
            return self::ALPHA_STATUSES;
        }

        return [$normalized];
    }

    private function unauthenticatedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'User tidak terautentikasi',
        ], 401);
    }

    private function attendanceNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Data absensi tidak ditemukan',
        ], 404);
    }

    private function forbiddenAttendanceResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki akses untuk melihat data ini',
        ], 403);
    }

    private function isViolationThresholdExceeded(int $totalViolationMinutes, float $violationPercentage, int $minutesThreshold, float $percentageThreshold): bool
    {
        $byMinutes = $minutesThreshold > 0 && $totalViolationMinutes >= $minutesThreshold;
        $byPercentage = $percentageThreshold > 0 && $violationPercentage >= $percentageThreshold;

        return $byMinutes || $byPercentage;
    }
}
