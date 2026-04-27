<?php

namespace App\Http\Controllers\Api;

use App\Exports\AkademikTableExport;
use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\Kelas;
use App\Models\Tingkat;
use App\Models\User;
use App\Services\AttendanceSchemaService;
use App\Services\AttendanceDisciplineService;
use App\Services\AttendanceTimeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Support\RoleDataScope;
use App\Support\RoleNames;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    private AttendanceSchemaService $attendanceSchemaService;
    private AttendanceTimeService $attendanceTimeService;
    private AttendanceDisciplineService $attendanceDisciplineService;
    /** @var array<int, array{jam_masuk: string, jam_pulang: string, toleransi: int}> */
    private array $attendancePolicyCache = [];

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

    public function daily(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'tanggal' => 'required|date',
            'role' => 'nullable|string|exists:roles,name',
            'kelas_id' => 'nullable|exists:kelas,id',
            'tingkat_id' => 'nullable|exists:tingkat,id',
            'status' => 'nullable|string|in:hadir,terlambat,izin,sakit,alpha,alpa,belum_absen',
            'status_disiplin' => $this->disciplineStatusValidationRule(),
            'view' => 'nullable|string|in:student_recap,detail',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $targetDate = Carbon::parse((string) $request->tanggal);

        return $this->buildPeriodReportResponse(
            $request,
            $user,
            $targetDate->copy()->startOfDay(),
            $targetDate->copy()->endOfDay(),
            true,
            'none'
        );
    }

    public function monthly(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'bulan' => 'required|integer|between:1,12',
            'tahun' => 'required|integer|min:2000|max:2099',
            'role' => 'nullable|string|exists:roles,name',
            'kelas_id' => 'nullable|exists:kelas,id',
            'tingkat_id' => 'nullable|exists:tingkat,id',
            'status' => 'nullable|string|in:hadir,terlambat,izin,sakit,alpha,alpa,belum_absen',
            'status_disiplin' => $this->disciplineStatusValidationRule(),
            'view' => 'nullable|string|in:student_recap,detail',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $start_date = Carbon::create($request->tahun, $request->bulan, 1)->startOfDay();
        $end_date = $start_date->copy()->endOfMonth()->endOfDay();

        return $this->buildPeriodReportResponse($request, $user, $start_date, $end_date, false, 'monthly');
    }

    public function range(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'role' => 'nullable|string|exists:roles,name',
            'kelas_id' => 'nullable|exists:kelas,id',
            'tingkat_id' => 'nullable|exists:tingkat,id',
            'status' => 'nullable|string|in:hadir,terlambat,izin,sakit,alpha,alpa,belum_absen',
            'status_disiplin' => $this->disciplineStatusValidationRule(),
            'view' => 'nullable|string|in:student_recap,detail',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $start_date = Carbon::parse((string) $request->start_date)->startOfDay();
        $end_date = Carbon::parse((string) $request->end_date)->endOfDay();

        $thresholdMode = $start_date->isSameMonth($end_date) ? 'monthly' : 'none';

        return $this->buildPeriodReportResponse($request, $user, $start_date, $end_date, false, $thresholdMode);
    }

    public function yearly(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|min:2000|max:2099',
            'role' => 'nullable|string|exists:roles,name',
            'kelas_id' => 'nullable|exists:kelas,id',
            'tingkat_id' => 'nullable|exists:tingkat,id',
            'status' => 'nullable|string|in:hadir,terlambat,izin,sakit,alpha,alpa,belum_absen',
            'status_disiplin' => $this->disciplineStatusValidationRule(),
            'view' => 'nullable|string|in:student_recap,detail',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $start_date = Carbon::create($request->tahun, 1, 1);
        $end_date = $start_date->copy()->endOfYear();

        if ($request->filled('kelas_id') && !$this->canAccessRequestedKelas($user, (int) $request->kelas_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke kelas tersebut'
            ], 403);
        }

        $query = $this->buildAttendancePeriodQuery($request, $user, $start_date, $end_date);

        $perPage = max(5, min((int) $request->input('per_page', 25), 200));
        $page = max(1, (int) $request->input('page', 1));
        $viewMode = $this->resolveReportViewMode($request->input('view'));
        $normalizedStatus = $this->normalizeExportStatus($request->input('status'));
        $normalizedDisciplineStatus = $this->normalizeDisciplineStatusFilter($request->input('status_disiplin'));
        $absensi = $query->get();
        $workingDayContextUser = $this->resolveWorkingDayContextUser($absensi, $user);
        $policy = $this->resolveViolationPolicy($workingDayContextUser);
        $targetUsers = $this->resolveTargetStudentsForReport($request, $user, $absensi);

        // Group by month
        $monthlyData = [];
        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($request->tahun, $month, 1);
            $end = $start->copy()->endOfMonth();
            $monthlyRecords = $absensi->whereBetween('tanggal', [$start, $end]);
            $monthlySummary = $this->buildAggregateSummary($monthlyRecords, $start, $end, $policy, 'none', $targetUsers);
            $monthlyData[] = array_merge($monthlySummary, [
                'month' => $month,
                'bulan' => $start->format('F'),
            ]);
        }
        $monthlyData = $this->filterRowsByDisciplineStatus($monthlyData, $normalizedDisciplineStatus);
        $summary = $this->buildAggregateSummary($absensi, $start_date, $end_date, $policy, 'none', $targetUsers);

        if ($viewMode === 'student_recap') {
            $detailRows = $this->buildStudentRecapRows($absensi, $policy, $start_date, $end_date, $normalizedStatus, 'none', $targetUsers);
            $detailRows = $this->filterRowsByDisciplineStatus($detailRows, $normalizedDisciplineStatus);
            $detailPaginator = $this->paginateArrayRows($detailRows, $page, $perPage);
            $detail = collect($detailPaginator->items())->values();
        } else {
            $detailPaginator = $this->paginateArrayRows($monthlyData, $page, $perPage);
            $detail = collect($detailPaginator->items())->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'view_mode' => $viewMode,
                'detail' => $detail,
                'pagination' => [
                    'current_page' => $detailPaginator->currentPage(),
                    'last_page' => $detailPaginator->lastPage(),
                    'per_page' => $detailPaginator->perPage(),
                    'total' => $detailPaginator->total(),
                    'from' => $detailPaginator->firstItem() ?? 0,
                    'to' => $detailPaginator->lastItem() ?? 0,
                ],
            ],
        ]);
    }

    public function semester(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'tahun' => 'required|integer|min:2000|max:2099',
            'semester' => 'required|integer|in:1,2',
            'role' => 'nullable|string|exists:roles,name',
            'kelas_id' => 'nullable|exists:kelas,id',
            'tingkat_id' => 'nullable|exists:tingkat,id',
            'status' => 'nullable|string|in:hadir,terlambat,izin,sakit,alpha,alpa,belum_absen',
            'status_disiplin' => $this->disciplineStatusValidationRule(),
            'view' => 'nullable|string|in:student_recap,detail',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $semester = (int) $request->semester;
        $startMonth = $semester === 1 ? 1 : 7;
        $endMonth = $semester === 1 ? 6 : 12;
        $start_date = Carbon::create($request->tahun, $startMonth, 1);
        $end_date = Carbon::create($request->tahun, $endMonth, 1)->endOfMonth();

        if ($request->filled('kelas_id') && !$this->canAccessRequestedKelas($user, (int) $request->kelas_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke kelas tersebut'
            ], 403);
        }

        $query = $this->buildAttendancePeriodQuery($request, $user, $start_date, $end_date);

        $perPage = max(5, min((int) $request->input('per_page', 25), 200));
        $page = max(1, (int) $request->input('page', 1));
        $viewMode = $this->resolveReportViewMode($request->input('view'));
        $normalizedStatus = $this->normalizeExportStatus($request->input('status'));
        $normalizedDisciplineStatus = $this->normalizeDisciplineStatusFilter($request->input('status_disiplin'));
        $absensi = $query->get();
        $workingDayContextUser = $this->resolveWorkingDayContextUser($absensi, $user);
        $policy = $this->resolveViolationPolicy($workingDayContextUser);
        $targetUsers = $this->resolveTargetStudentsForReport($request, $user, $absensi);

        $monthlyData = [];
        for ($month = $startMonth; $month <= $endMonth; $month++) {
            $start = Carbon::create($request->tahun, $month, 1);
            $end = $start->copy()->endOfMonth();
            $monthlyRecords = $absensi->whereBetween('tanggal', [$start, $end]);
            $monthlySummary = $this->buildAggregateSummary($monthlyRecords, $start, $end, $policy, 'monthly', $targetUsers);
            $monthlyData[] = array_merge($monthlySummary, [
                'month' => $month,
                'bulan' => $start->format('F'),
                'semester' => $semester,
            ]);
        }
        $monthlyData = $this->filterRowsByDisciplineStatus($monthlyData, $normalizedDisciplineStatus);
        $summary = array_merge(
            $this->buildAggregateSummary($absensi, $start_date, $end_date, $policy, 'semester', $targetUsers),
            ['semester' => $semester]
        );

        if ($viewMode === 'student_recap') {
            $detailRows = $this->buildStudentRecapRows($absensi, $policy, $start_date, $end_date, $normalizedStatus, 'semester', $targetUsers);
            $detailRows = $this->filterRowsByDisciplineStatus($detailRows, $normalizedDisciplineStatus);
            $detailPaginator = $this->paginateArrayRows($detailRows, $page, $perPage);
            $detail = collect($detailPaginator->items())->values();
        } else {
            $detailPaginator = $this->paginateArrayRows($monthlyData, $page, $perPage);
            $detail = collect($detailPaginator->items())->values();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'view_mode' => $viewMode,
                'detail' => $detail,
                'pagination' => [
                    'current_page' => $detailPaginator->currentPage(),
                    'last_page' => $detailPaginator->lastPage(),
                    'per_page' => $detailPaginator->perPage(),
                    'total' => $detailPaginator->total(),
                    'from' => $detailPaginator->firstItem() ?? 0,
                    'to' => $detailPaginator->lastItem() ?? 0,
                ],
            ],
        ]);
    }

    private function resolveReportViewMode($view): string
    {
        $normalized = strtolower(trim((string) $view));

        return $normalized === 'student_recap' ? 'student_recap' : 'detail';
    }

    private function resolveExportViewMode($view): string
    {
        $normalized = strtolower(trim((string) $view));

        return $normalized === 'detail' ? 'detail' : 'student_recap';
    }

    private function buildPeriodReportResponse(
        Request $request,
        ?User $user,
        Carbon $start_date,
        Carbon $end_date,
        bool $includeDailyTotal = false,
        string $thresholdMode = 'none'
    )
    {
        if ($request->filled('kelas_id') && !$this->canAccessRequestedKelas($user, (int) $request->kelas_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke kelas tersebut'
            ], 403);
        }

        $query = $this->buildAttendancePeriodQuery($request, $user, $start_date, $end_date);

        $perPage = max(5, min((int) $request->input('per_page', 25), 200));
        $page = max(1, (int) $request->input('page', 1));
        $viewMode = $this->resolveReportViewMode($request->input('view'));
        $fullAbsensi = $query->get();
        $workingDayContextUser = $this->resolveWorkingDayContextUser($fullAbsensi, $user);
        $policy = $this->resolveViolationPolicy($workingDayContextUser);
        $targetUsers = $this->resolveTargetStudentsForReport($request, $user, $fullAbsensi);
        $normalizedStatus = $this->normalizeExportStatus($request->input('status'));
        $normalizedDisciplineStatus = $this->normalizeDisciplineStatusFilter($request->input('status_disiplin'));
        $summary = $this->buildAggregateSummary(
            $fullAbsensi,
            $start_date,
            $end_date,
            $policy,
            $thresholdMode,
            $targetUsers
        );

        if ($includeDailyTotal) {
            $summary['total'] = $fullAbsensi->count();
            $summary['hadir'] = $summary['total_hadir'];
            $summary['izin'] = $summary['total_izin'];
            $summary['sakit'] = $summary['total_sakit'];
            $summary['terlambat'] = $summary['total_terlambat'];
            $summary['alpha'] = $summary['total_alpha'];
            $summary['tap_hari'] = $summary['total_tap_hari'];
            $summary['terlambat_menit'] = $summary['total_terlambat_menit'];
            $summary['alpha_menit'] = $summary['total_alpha_menit'];
            $summary['alpa_menit'] = $summary['total_alpa_menit'];
            $summary['tap_menit'] = $summary['total_tap_menit'];
            $summary['belum_absen'] = $summary['total_belum_absen'];
        }

        if ($viewMode === 'student_recap') {
            $detailRows = $this->buildStudentRecapRows(
                $fullAbsensi,
                $policy,
                $start_date,
                $end_date,
                $normalizedStatus,
                $thresholdMode,
                $targetUsers
            );
            $detailRows = $this->filterRowsByDisciplineStatus($detailRows, $normalizedDisciplineStatus);
            $detailPaginator = $this->paginateArrayRows($detailRows, $page, $perPage);
            $detail = collect($detailPaginator->items())->values();
        } else {
            $detailQuery = clone $query;
            $this->applyStatusFilterToQuery($detailQuery, $normalizedStatus);
            if ($normalizedDisciplineStatus !== null) {
                $detailRows = $detailQuery
                    ->orderBy('created_at', 'desc')
                    ->get()
                    ->map(fn (Absensi $item) => $this->appendViolationMetricsToAttendance($item, $policy, $thresholdMode))
                    ->all();
                $detailRows = $this->filterRowsByDisciplineStatus($detailRows, $normalizedDisciplineStatus);
                $detailPaginator = $this->paginateArrayRows($detailRows, $page, $perPage);
                $detail = collect($detailPaginator->items())->values();
            } else {
                $detailPaginator = $detailQuery
                    ->orderBy('created_at', 'desc')
                    ->paginate($perPage, ['*'], 'page', $page);

                $detail = collect($detailPaginator->items())->map(function ($item) use ($policy, $thresholdMode) {
                    return $this->appendViolationMetricsToAttendance($item, $policy, $thresholdMode);
                })->values();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'view_mode' => $viewMode,
                'detail' => $detail,
                'pagination' => [
                    'current_page' => $detailPaginator->currentPage(),
                    'last_page' => $detailPaginator->lastPage(),
                    'per_page' => $detailPaginator->perPage(),
                    'total' => $detailPaginator->total(),
                    'from' => $detailPaginator->firstItem() ?? 0,
                    'to' => $detailPaginator->lastItem() ?? 0,
                ],
            ]
        ]);
    }

    private function buildAttendancePeriodQuery(
        Request $request,
        ?User $user,
        Carbon $start_date,
        Carbon $end_date
    ) {
        $query = Absensi::with(['user.kelas', 'kelas'])
            ->whereBetween('tanggal', [$start_date->toDateString(), $end_date->toDateString()]);

        if ($request->has('role')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->role($request->role);
            });
        }

        $this->applyAbsensiScope($query, $user);

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', (int) $request->kelas_id);
        }

        if ($request->filled('tingkat_id')) {
            $query->whereHas('kelas', function ($q) use ($request) {
                $q->where('tingkat_id', (int) $request->tingkat_id);
            });
        }

        return $query;
    }

    private function applyStatusFilterToQuery($query, ?string $normalizedStatus): void
    {
        if ($normalizedStatus === null) {
            return;
        }

        $query->whereRaw('LOWER(status) = ?', [$normalizedStatus]);
    }

    private function filterAttendanceCollectionByStatus($records, ?string $normalizedStatus)
    {
        $collection = $records instanceof \Illuminate\Support\Collection
            ? $records
            : collect($records);

        if ($normalizedStatus === null) {
            return $collection->values();
        }

        return $collection->filter(function ($item) use ($normalizedStatus) {
            if (!$item instanceof Absensi) {
                return false;
            }

            return ($this->normalizeExportStatus($item->status) ?? '') === $normalizedStatus;
        })->values();
    }

    private function buildAggregateSummary(
        $records,
        Carbon $periodStart,
        Carbon $periodEnd,
        array $policy,
        string $thresholdMode = 'none',
        $targetUsers = []
    ): array
    {
        $recapRows = $this->buildStudentRecapRows(
            $records,
            $policy,
            $periodStart,
            $periodEnd,
            null,
            $thresholdMode,
            $targetUsers
        );

        $summary = [
            'total_hari_kerja' => 0,
            'total_hadir' => 0,
            'total_izin' => 0,
            'total_sakit' => 0,
            'total_terlambat' => 0,
            'total_terlambat_menit' => 0,
            'total_tap_hari' => 0,
            'total_tap_menit' => 0,
            'total_belum_absen' => 0,
            'total_alpha' => 0,
            'total_alpha_menit' => 0,
            'total_alpa_menit' => 0,
            'total_pelanggaran_menit' => 0,
            'total_menit_kerja' => 0,
            'working_minutes_per_day' => 0,
            'batas_pelanggaran_menit' => (int) ($policy['violation_minutes_threshold'] ?? 0),
            'batas_pelanggaran_persen' => (float) ($policy['violation_percentage_threshold'] ?? 0),
            'jumlah_siswa_melewati_batas_pelanggaran' => 0,
            'jumlah_siswa_melewati_batas_keterlambatan_bulanan' => 0,
            'jumlah_siswa_melewati_batas_alpha_semester' => 0,
            'discipline_thresholds' => [
                'mode' => $thresholdMode,
                'monthly_late' => [],
                'semester_total_violation' => [],
                'semester_alpha' => [],
                'attention_needed' => false,
            ],
        ];

        foreach ($recapRows as $row) {
            $summary['total_hari_kerja'] += (int) ($row['total_hari_kerja'] ?? 0);
            $summary['total_hadir'] += (int) ($row['hadir'] ?? 0);
            $summary['total_izin'] += (int) ($row['izin'] ?? 0);
            $summary['total_sakit'] += (int) ($row['sakit'] ?? 0);
            $summary['total_terlambat'] += (int) ($row['terlambat'] ?? 0);
            $summary['total_terlambat_menit'] += (int) ($row['terlambat_menit'] ?? 0);
            $summary['total_tap_hari'] += (int) ($row['tap_hari'] ?? 0);
            $summary['total_tap_menit'] += (int) ($row['tap_menit'] ?? 0);
            $summary['total_belum_absen'] += (int) ($row['belum_absen'] ?? 0);
            $summary['total_alpha'] += (int) ($row['alpha'] ?? 0);
            $summary['total_alpha_menit'] += (int) ($row['alpa_menit'] ?? 0);
            $summary['total_pelanggaran_menit'] += (int) ($row['total_pelanggaran_menit'] ?? 0);
            $summary['total_menit_kerja'] += (int) ($row['total_menit_kerja'] ?? 0);

            if (!empty($row['melewati_batas_pelanggaran'])) {
                $summary['jumlah_siswa_melewati_batas_pelanggaran']++;
            }

            if (!empty($row['discipline_thresholds']['monthly_late']['exceeded'])) {
                $summary['jumlah_siswa_melewati_batas_keterlambatan_bulanan']++;
            }

            if (!empty($row['discipline_thresholds']['semester_alpha']['exceeded'])) {
                $summary['jumlah_siswa_melewati_batas_alpha_semester']++;
            }
        }

        $summary['total_alpa_menit'] = $summary['total_alpha_menit'];
        $summary['working_minutes_per_day'] = $summary['total_hari_kerja'] > 0
            ? (int) round($summary['total_menit_kerja'] / $summary['total_hari_kerja'])
            : $this->getWorkingMinutesPerDay();
        $summary['terlambat_hari'] = $summary['total_terlambat'];
        $summary['tap_hari'] = $summary['total_tap_hari'];
        $summary['persentase_pelanggaran'] = $summary['total_menit_kerja'] > 0
            ? round(($summary['total_pelanggaran_menit'] / $summary['total_menit_kerja']) * 100, 2)
            : 0.0;
        $summary['melewati_batas_pelanggaran'] = $summary['jumlah_siswa_melewati_batas_pelanggaran'] > 0;
        $summary['discipline_thresholds'] = $this->buildDisciplineThresholdPayload(
            $summary['total_terlambat_menit'],
            $summary['total_alpha'],
            $summary['total_pelanggaran_menit'],
            $policy,
            $thresholdMode,
            $periodStart,
            $periodEnd,
            $summary['jumlah_siswa_melewati_batas_pelanggaran'] > 0
        );
        $summary['batas_pelanggaran_menit'] = (int) data_get(
            $summary['discipline_thresholds'],
            'summary_limit_minutes',
            $summary['batas_pelanggaran_menit']
        );
        $summary['batas_pelanggaran_persen'] = (float) data_get(
            $summary['discipline_thresholds'],
            'summary_limit_percentage',
            $summary['batas_pelanggaran_persen']
        );

        return $summary;
    }

    /**
     * Build aggregated rows where each student appears once.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildStudentRecapRows(
        $records,
        array $policy,
        ?Carbon $periodStart = null,
        ?Carbon $periodEnd = null,
        ?string $statusFilter = null,
        string $thresholdMode = 'none',
        $targetUsers = []
    ): array
    {
        $collection = $records instanceof \Illuminate\Support\Collection
            ? $records
            : collect($records);

        $groupedByStudent = $collection
            ->filter(fn ($item) => $item instanceof Absensi)
            ->groupBy(fn (Absensi $attendance) => (int) $attendance->user_id);

        $targetUsersById = collect($targetUsers)
            ->filter(fn ($item) => $item instanceof User)
            ->keyBy(fn (User $targetUser) => (int) $targetUser->id);

        foreach ($groupedByStudent as $studentId => $studentRecords) {
            $attendanceWithUser = $studentRecords->first(
                fn ($item) => $item instanceof Absensi && $item->user instanceof User
            );

            if ($attendanceWithUser instanceof Absensi && $attendanceWithUser->user instanceof User) {
                $targetUsersById->put((int) $studentId, $attendanceWithUser->user);
            }
        }

        $userIds = array_values(array_unique(array_merge(
            array_map('intval', array_keys($groupedByStudent->all())),
            $targetUsersById->keys()->map(fn ($id) => (int) $id)->all()
        )));

        $rows = [];
        foreach ($userIds as $studentId) {
            $studentItems = $groupedByStudent->get($studentId, collect())
                ->filter(fn ($item) => $item instanceof Absensi)
                ->values();

            $sample = $studentItems->first();
            $student = $targetUsersById->get($studentId);

            if (!$sample instanceof Absensi && !$student instanceof User) {
                continue;
            }

            $contextUser = $student instanceof User ? $student : $sample->user;
            $belumAbsen = $contextUser instanceof User && $periodStart && $periodEnd
                ? $this->countMissingAttendanceDays($periodStart, $periodEnd, $contextUser, $studentItems)
                : 0;

            $matchingItems = $this->filterAttendanceCollectionByStatus($studentItems, $statusFilter);
            if ($statusFilter === 'belum_absen') {
                if ($belumAbsen <= 0) {
                    continue;
                }
            } elseif ($statusFilter !== null && $matchingItems->isEmpty()) {
                continue;
            }

            $hadirCount = $studentItems->filter(function ($item) {
                $status = $this->normalizeExportStatus($item->status) ?? '';
                return in_array($status, ['hadir', 'terlambat'], true);
            })->count();

            $terlambatCount = $this->countLateAttendance($studentItems);
            $izinCount = $this->countByStatus($studentItems, 'izin');
            $sakitCount = $this->countByStatus($studentItems, 'sakit');
            $alphaCount = $this->countByStatus($studentItems, 'alpha');
            $studentPolicy = $this->resolveViolationPolicy($contextUser);
            $workingMinutesPerDay = $this->resolveWorkingMinutesPerDayForUser($contextUser);
            $totalHariKerja = ($periodStart && $periodEnd)
                ? $this->countWorkingDays($periodStart, $periodEnd, $contextUser)
                : $studentItems->count();
            $totalMenitDasar = $totalHariKerja * $workingMinutesPerDay;
            $persentaseKehadiran = $totalHariKerja > 0
                ? round(($hadirCount / $totalHariKerja) * 100, 1)
                : 0.0;

            $violationMetrics = $this->summarizeViolationMetrics($studentItems);
            $totalPelanggaranMenit = (int) ($violationMetrics['total_pelanggaran_menit'] ?? 0);
            $persentasePelanggaran = $totalMenitDasar > 0
                ? round(($totalPelanggaranMenit / $totalMenitDasar) * 100, 2)
                : 0.0;
            $batasPelanggaranMenit = (int) ($studentPolicy['violation_minutes_threshold'] ?? $policy['violation_minutes_threshold'] ?? 0);
            $batasPelanggaranPersen = (float) ($studentPolicy['violation_percentage_threshold'] ?? $policy['violation_percentage_threshold'] ?? 0.0);
            $legacyExceeded = !(bool) ($studentPolicy['uses_new_thresholds'] ?? false)
                && $this->isViolationThresholdExceeded(
                    $totalPelanggaranMenit,
                    $persentasePelanggaran,
                    (int) ($studentPolicy['legacy_violation_minutes_threshold'] ?? $batasPelanggaranMenit),
                    (float) ($studentPolicy['legacy_violation_percentage_threshold'] ?? $batasPelanggaranPersen)
                );
            $disciplineThresholds = $this->buildDisciplineThresholdPayload(
                (int) ($violationMetrics['terlambat_menit'] ?? 0),
                $alphaCount,
                $totalPelanggaranMenit,
                $studentPolicy,
                $thresholdMode,
                $periodStart,
                $periodEnd,
                false
            );

            $namaSiswa = (string) (
                $contextUser?->nama_lengkap
                ?? $contextUser?->name
                ?? $contextUser?->username
                ?? 'Unknown'
            );
            $kelasNama = $sample instanceof Absensi
                ? $this->resolveAttendanceClassName($sample)
                : $this->resolveUserClassName($contextUser);

            $rows[] = [
                'user_id' => (int) ($contextUser?->id ?? $sample?->user_id ?? 0),
                'nama' => $namaSiswa,
                'nama_lengkap' => $namaSiswa,
                'kelas_id' => $sample instanceof Absensi
                    ? ($sample->kelas_id ?: $this->resolveAttendanceClassId($sample))
                    : $this->resolveUserClassId($contextUser),
                'kelas' => $kelasNama,
                'kelas_nama' => $kelasNama,
                'total_records' => $studentItems->count(),
                'total_hari_kerja' => $totalHariKerja,
                'hadir' => $hadirCount,
                'terlambat' => $terlambatCount,
                'terlambat_hari' => $terlambatCount,
                'izin' => $izinCount,
                'sakit' => $sakitCount,
                'alpha' => $alphaCount,
                'persentase_kehadiran' => $persentaseKehadiran,
                'working_minutes_per_day' => $workingMinutesPerDay,
                'total_menit_kerja' => $totalMenitDasar,
                'tap_hari' => (int) ($violationMetrics['tap_hari'] ?? 0),
                'terlambat_menit' => (int) ($violationMetrics['terlambat_menit'] ?? 0),
                'tap_menit' => (int) ($violationMetrics['tap_menit'] ?? 0),
                'belum_absen' => $belumAbsen,
                'alpa_menit' => (int) ($violationMetrics['alpha_menit'] ?? 0),
                'total_pelanggaran_menit' => $totalPelanggaranMenit,
                'persentase_pelanggaran' => $persentasePelanggaran,
                'batas_pelanggaran_menit' => (int) data_get($disciplineThresholds, 'summary_limit_minutes', $batasPelanggaranMenit),
                'batas_pelanggaran_persen' => (float) data_get($disciplineThresholds, 'summary_limit_percentage', $batasPelanggaranPersen),
                'melewati_batas_pelanggaran' => (bool) data_get($disciplineThresholds, 'attention_needed', false) || $legacyExceeded,
                'discipline_thresholds' => $disciplineThresholds,
            ];
        }

        usort($rows, static function (array $left, array $right): int {
            return strcasecmp((string) ($left['nama'] ?? ''), (string) ($right['nama'] ?? ''));
        });

        return array_values($rows);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function paginateArrayRows(array $rows, int $page, int $perPage): LengthAwarePaginator
    {
        $safePage = max(1, $page);
        $safePerPage = max(1, $perPage);
        $total = count($rows);
        $offset = ($safePage - 1) * $safePerPage;

        return new LengthAwarePaginator(
            array_values(array_slice($rows, $offset, $safePerPage)),
            $total,
            $safePerPage,
            $safePage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    public function exportExcel(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'role' => 'nullable|string|exists:roles,name',
            'kelas_id' => 'nullable|exists:kelas,id',
            'tingkat_id' => 'nullable|exists:tingkat,id',
            'status' => 'nullable|string|in:hadir,terlambat,izin,sakit,alpha,alpa,belum_absen',
            'status_disiplin' => $this->disciplineStatusValidationRule(),
            'fields' => 'nullable',
            'format' => 'required|in:xlsx,csv',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->filled('kelas_id') && !$this->canAccessRequestedKelas($user, (int) $request->kelas_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke kelas tersebut'
            ], 403);
        }

        try {
            $dataset = $this->prepareExportDataset($request);
            $timestamp = now()->format('Ymd_His');
            $format = strtolower((string) $request->input('format', 'xlsx'));
            $extension = $format === 'csv' ? 'csv' : 'xlsx';
            $writerType = $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;

            return Excel::download(
                new AkademikTableExport($dataset['rows'], $dataset['columns'], $dataset['meta']),
                "Laporan_Kehadiran_{$timestamp}.{$extension}",
                $writerType
            );
        } catch (\Throwable $e) {
            Log::error('Attendance report Excel export failed', [
                'user_id' => $user?->id,
                'request' => $request->only([
                    'start_date',
                    'end_date',
                    'role',
                    'kelas_id',
                    'tingkat_id',
                    'status',
                    'fields',
                    'format',
                ]),
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor laporan kehadiran ke Excel',
            ], 500);
        }
    }

    public function exportPdf(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'role' => 'nullable|string|exists:roles,name',
            'kelas_id' => 'nullable|exists:kelas,id',
            'tingkat_id' => 'nullable|exists:tingkat,id',
            'status' => 'nullable|string|in:hadir,terlambat,izin,sakit,alpha,alpa,belum_absen',
            'status_disiplin' => $this->disciplineStatusValidationRule(),
            'fields' => 'nullable',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->filled('kelas_id') && !$this->canAccessRequestedKelas($user, (int) $request->kelas_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke kelas tersebut'
            ], 403);
        }

        try {
            $dataset = $this->prepareExportDataset($request);
            $timestamp = now()->format('Ymd_His');

            $pdf = Pdf::loadView('exports.akademik-table', [
                'title' => $dataset['meta']['title'],
                'subtitle' => $dataset['meta']['subtitle'],
                'generatedBy' => $dataset['meta']['generated_by'],
                'generatedAt' => $dataset['meta']['generated_at'],
                'filterSummary' => $dataset['meta']['filter_summary'],
                'disciplineLimitSummary' => $dataset['meta']['discipline_limit_summary'] ?? null,
                'columns' => $dataset['columns'],
                'rows' => $dataset['rows']->all(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("Laporan_Kehadiran_{$timestamp}.pdf");
        } catch (\Throwable $e) {
            Log::error('Attendance report PDF export failed', [
                'user_id' => $user?->id,
                'request' => $request->only([
                    'start_date',
                    'end_date',
                    'role',
                    'kelas_id',
                    'tingkat_id',
                    'status',
                    'fields',
                ]),
                'message' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor laporan kehadiran ke PDF',
            ], 500);
        }
    }

    /**
     * Build rows/columns/meta payload for attendance report export.
     *
     * @return array{
     *   rows:\Illuminate\Support\Collection<int, array<string, mixed>>,
     *   columns:array<int, array{key:string,label:string,width:int}>,
     *   meta:array<string, mixed>
     * }
     */
    private function prepareExportDataset(Request $request): array
    {
        $viewMode = $this->resolveExportViewMode($request->input('view'));
        $startDate = Carbon::parse((string) $request->start_date)->startOfDay();
        $endDate = Carbon::parse((string) $request->end_date)->endOfDay();
        $thresholdMode = $this->resolveExportThresholdMode($startDate, $endDate);
        $query = $this->buildAttendancePeriodQuery($request, $request->user(), $startDate, $endDate);
        $normalizedStatus = $this->normalizeExportStatus($request->input('status'));
        $normalizedDisciplineStatus = $this->normalizeDisciplineStatusFilter($request->input('status_disiplin'));

        $absensi = $query
            ->orderBy('tanggal', 'asc')
            ->orderBy('kelas_id', 'asc')
            ->orderBy('user_id', 'asc')
            ->get();

        $workingDayContextUser = $this->resolveWorkingDayContextUser($absensi, $request->user());
        $policy = $this->resolveViolationPolicy($workingDayContextUser);
        $disciplineSummaryRows = $this->buildStudentRecapRows(
            $absensi,
            $policy,
            $startDate,
            $endDate,
            null,
            $thresholdMode
        );

        if ($viewMode === 'student_recap') {
            $recapRows = $normalizedStatus === null
                ? $disciplineSummaryRows
                : $this->buildStudentRecapRows($absensi, $policy, $startDate, $endDate, $normalizedStatus, $thresholdMode);
            $recapRows = $this->filterRowsByDisciplineStatus($recapRows, $normalizedDisciplineStatus);
            $rows = collect($recapRows)->values()->map(function (array $item, int $index): array {
                $terlambatMenit = (int) ($item['terlambat_menit'] ?? 0);
                $alpaMenit = (int) ($item['alpa_menit'] ?? 0);
                $tapHari = (int) ($item['tap_hari'] ?? 0);
                $tapMenit = (int) ($item['tap_menit'] ?? 0);

                return [
                    'no' => $index + 1,
                    'tanggal' => '-',
                    'nama' => $item['nama_lengkap'] ?? $item['nama'] ?? '-',
                    'kelas' => $item['kelas_nama'] ?? $item['kelas'] ?? '-',
                    'hadir' => (int) ($item['hadir'] ?? 0),
                    'terlambat' => (int) ($item['terlambat'] ?? 0) . ' (' . $terlambatMenit . 'm)',
                    'tap' => $tapHari . ' (' . $tapMenit . 'm)',
                    'izin' => (int) ($item['izin'] ?? 0),
                    'sakit' => (int) ($item['sakit'] ?? 0),
                    'alpha' => (int) ($item['alpha'] ?? 0) . ' (' . $alpaMenit . 'm)',
                    'persentase_kehadiran' => number_format((float) ($item['persentase_kehadiran'] ?? 0), 1) . '%',
                    'pelanggaran' => (int) ($item['total_pelanggaran_menit'] ?? 0)
                        . 'm (' . number_format((float) ($item['persentase_pelanggaran'] ?? 0), 2) . '%)',
                    'status_batas' => $this->formatExportDisciplineTableStatus(
                        (array) ($item['discipline_thresholds'] ?? []),
                        !empty($item['melewati_batas_pelanggaran'])
                    ),
                ];
            });
        } else {
            $filteredAbsensi = $this->filterAttendanceCollectionByStatus($absensi, $normalizedStatus);
            $detailRows = $filteredAbsensi
                ->values()
                ->map(fn (Absensi $item) => $this->appendViolationMetricsToAttendance($item, $policy, $thresholdMode))
                ->all();
            $detailRows = $this->filterRowsByDisciplineStatus($detailRows, $normalizedDisciplineStatus);

            $rows = collect($detailRows)->values()->map(
                function (array $detail, int $index): array {
                    $status = strtolower((string) ($detail['status'] ?? ''));
                    $kehadiranPersen = in_array($status, ['hadir', 'terlambat'], true) ? 100 : 0;
                    $kelasName = (string) ($detail['kelas_nama'] ?? '-');
                    $tapHari = (int) ($detail['tap_hari'] ?? 0);
                    $tapMenit = (int) ($detail['tap_menit'] ?? 0);
                    $tanggal = !empty($detail['tanggal'])
                        ? Carbon::parse((string) $detail['tanggal'])->format('Y-m-d')
                        : '-';

                    return [
                        'no' => $index + 1,
                        'tanggal' => $tanggal,
                        'nama' => data_get($detail, 'user.nama_lengkap')
                            ?? data_get($detail, 'user.username')
                            ?? '-',
                        'kelas' => $kelasName,
                        'hadir' => in_array($status, ['hadir', 'terlambat'], true) ? 1 : 0,
                        'terlambat' => ((int) ($detail['terlambat_menit'] ?? 0)) > 0
                            ? ('1 (' . (int) ($detail['terlambat_menit'] ?? 0) . 'm)')
                            : '0 (0m)',
                        'tap' => $tapHari . ' (' . $tapMenit . 'm)',
                        'izin' => $status === 'izin' ? 1 : 0,
                        'sakit' => $status === 'sakit' ? 1 : 0,
                        'alpha' => $status === 'alpha'
                            ? ('1 (' . (int) ($detail['alpa_menit'] ?? 0) . 'm)')
                            : '0 (' . (int) ($detail['alpa_menit'] ?? 0) . 'm)',
                        'persentase_kehadiran' => $kehadiranPersen . '%',
                        'pelanggaran' => (int) ($detail['total_pelanggaran_menit'] ?? 0)
                            . 'm (' . number_format((float) ($detail['persentase_pelanggaran'] ?? 0), 2) . '%)',
                        'status_batas' => $this->formatExportDisciplineTableStatus(
                            (array) ($detail['discipline_thresholds'] ?? []),
                            !empty($detail['melewati_batas_pelanggaran'])
                        ),
                    ];
                }
            );
        }

        $availableColumns = $this->attendanceExportColumns();
        $selectedColumns = $this->resolveExportColumns($request->input('fields'), $availableColumns);
        $generatedBy = $request->user()?->nama_lengkap ?? $request->user()?->username ?? 'System';

        $meta = [
            'title' => 'Laporan Kehadiran',
            'subtitle' => 'Sistem Absensi Sekolah',
            'sheet_title' => 'Laporan Kehadiran',
            'generated_by' => $generatedBy,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'filter_summary' => $this->buildAttendanceFilterSummary($request),
            'discipline_limit_summary' => $this->buildExportDisciplineLimitSummary(
                $disciplineSummaryRows,
                $policy,
                $thresholdMode
            ),
        ];

        return [
            'rows' => $rows,
            'columns' => $selectedColumns,
            'meta' => $meta,
        ];
    }

    /**
     * @return array<int, array{key:string,label:string,width:int}>
     */
    private function attendanceExportColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'width' => 6],
            ['key' => 'tanggal', 'label' => 'Tanggal', 'width' => 13],
            ['key' => 'nama', 'label' => 'Nama', 'width' => 26],
            ['key' => 'kelas', 'label' => 'Kelas', 'width' => 12],
            ['key' => 'hadir', 'label' => 'Hadir Efektif', 'width' => 13],
            ['key' => 'terlambat', 'label' => 'Terlambat (hari/m)', 'width' => 19],
            ['key' => 'tap', 'label' => 'TAP (hari/m)', 'width' => 15],
            ['key' => 'izin', 'label' => 'Izin', 'width' => 8],
            ['key' => 'sakit', 'label' => 'Sakit', 'width' => 8],
            ['key' => 'alpha', 'label' => 'Alpha (hari/m)', 'width' => 17],
            ['key' => 'persentase_kehadiran', 'label' => '% Kehadiran', 'width' => 14],
            ['key' => 'pelanggaran', 'label' => 'Pelanggaran Total', 'width' => 20],
            ['key' => 'status_batas', 'label' => 'Status Disiplin', 'width' => 18],
        ];
    }

    private function resolveExportThresholdMode(Carbon $startDate, Carbon $endDate): string
    {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        $isFullMonth = $start->isSameDay($start->copy()->startOfMonth())
            && $end->isSameDay($end->copy()->endOfMonth())
            && $start->isSameMonth($end);

        if ($this->isFullSemesterRange($start, $end)) {
            return 'semester';
        }

        return $isFullMonth ? 'monthly' : 'none';
    }

    private function isFullSemesterRange(Carbon $startDate, Carbon $endDate): bool
    {
        if ((int) $startDate->year !== (int) $endDate->year) {
            return false;
        }

        $firstSemester = $startDate->month === 1
            && $endDate->month === 6
            && $startDate->isSameDay($startDate->copy()->startOfMonth())
            && $endDate->isSameDay($endDate->copy()->endOfMonth());

        $secondSemester = $startDate->month === 7
            && $endDate->month === 12
            && $startDate->isSameDay($startDate->copy()->startOfMonth())
            && $endDate->isSameDay($endDate->copy()->endOfMonth());

        return $firstSemester || $secondSemester;
    }

    /**
     * Keep threshold policy in the export header so the data table remains readable.
     *
     * @param array<int, array<string, mixed>> $recapRows
     */
    private function buildExportDisciplineLimitSummary(array $recapRows, array $fallbackPolicy, string $thresholdMode): string
    {
        $fallbackPayload = $this->buildDisciplineThresholdPayload(
            0,
            0,
            0,
            $fallbackPolicy,
            $thresholdMode,
            null,
            null,
            false
        );

        if (empty($recapRows)) {
            return $this->formatExportPolicyHeaderLimit($fallbackPayload);
        }

        $limitsByClass = [];
        $uniqueLimits = [];
        foreach ($recapRows as $row) {
            $className = trim((string) ($row['kelas_nama'] ?? $row['kelas'] ?? '-')) ?: '-';
            $payload = (array) ($row['discipline_thresholds'] ?? []);
            $limitText = $this->formatExportPolicyHeaderLimit(empty($payload) ? $fallbackPayload : $payload);

            $limitsByClass[$className][$limitText] = true;
            $uniqueLimits[$limitText] = true;
        }

        $uniqueLimitTexts = array_keys($uniqueLimits);
        if (count($uniqueLimitTexts) <= 1) {
            return $uniqueLimitTexts[0] ?? $this->formatExportPolicyHeaderLimit($fallbackPayload);
        }

        ksort($limitsByClass, SORT_NATURAL | SORT_FLAG_CASE);

        $segments = [];
        foreach ($limitsByClass as $className => $limitLookup) {
            $segments[] = "{$className}: " . implode('; ', array_keys($limitLookup));
        }

        return implode(' | ', $segments);
    }

    private function formatExportPolicyHeaderLimit(array $disciplineThresholds): string
    {
        $monthlyLateLimit = (int) data_get($disciplineThresholds, 'monthly_late.limit', 0);
        $semesterViolationLimit = (int) data_get($disciplineThresholds, 'semester_total_violation.limit', 0);
        $semesterAlphaLimit = (int) data_get($disciplineThresholds, 'semester_alpha.limit', 0);

        return "Telat {$monthlyLateLimit}m/bulan; Total {$semesterViolationLimit}m/semester; Alpha {$semesterAlphaLimit} hari/semester";
    }

    private function formatExportDisciplineTableStatus(array $disciplineThresholds, bool $fallbackExceeded): string
    {
        return $this->formatDisciplineStatusLabel(
            $this->resolveDisciplineStatusCode($disciplineThresholds, $fallbackExceeded)
        );
    }

    private function disciplineStatusValidationRule(): string
    {
        return 'nullable|string|in:' . implode(',', $this->validDisciplineStatusCodes());
    }

    /**
     * @return array<int, string>
     */
    private function validDisciplineStatusCodes(): array
    {
        return [
            'dalam_batas',
            'monitoring_periode',
            'perlu_perhatian',
            'melewati_batas_telat',
            'melewati_batas_total',
            'melewati_batas_alpha',
        ];
    }

    private function normalizeDisciplineStatusFilter($status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $status));

        return in_array($normalized, $this->validDisciplineStatusCodes(), true)
            ? $normalized
            : null;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function filterRowsByDisciplineStatus(array $rows, ?string $statusFilter): array
    {
        if ($statusFilter === null) {
            return array_values($rows);
        }

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->matchesDisciplineStatusFilter($row, $statusFilter)
        ));
    }

    private function matchesDisciplineStatusFilter(array $row, string $statusFilter): bool
    {
        $code = $this->resolveDisciplineStatusCode(
            (array) ($row['discipline_thresholds'] ?? []),
            (bool) ($row['melewati_batas_pelanggaran'] ?? false)
        );

        return $code === $statusFilter;
    }

    private function resolveDisciplineStatusCode(array $disciplineThresholds, bool $fallbackExceeded): string
    {
        if ((bool) data_get($disciplineThresholds, 'monthly_late.exceeded', false)) {
            return 'melewati_batas_telat';
        }

        if ((bool) data_get($disciplineThresholds, 'semester_total_violation.exceeded', false)) {
            return 'melewati_batas_total';
        }

        if ((bool) data_get($disciplineThresholds, 'semester_alpha.exceeded', false)) {
            return 'melewati_batas_alpha';
        }

        if ((bool) data_get($disciplineThresholds, 'attention_needed', false) || $fallbackExceeded) {
            return 'perlu_perhatian';
        }

        $mode = (string) ($disciplineThresholds['mode'] ?? 'none');
        return $mode === 'none' ? 'monitoring_periode' : 'dalam_batas';
    }

    private function formatDisciplineStatusLabel(string $statusCode): string
    {
        return match ($statusCode) {
            'melewati_batas_telat' => 'Melewati batas telat',
            'melewati_batas_total' => 'Melewati batas total',
            'melewati_batas_alpha' => 'Melewati batas alpha',
            'perlu_perhatian' => 'Perlu perhatian',
            'monitoring_periode' => 'Monitoring periode',
            default => 'Dalam batas',
        };
    }

    /**
     * @param mixed $rawFields
     * @param array<int, array{key:string,label:string,width:int}> $availableColumns
     * @return array<int, array{key:string,label:string,width:int}>
     */
    private function resolveExportColumns($rawFields, array $availableColumns): array
    {
        $requestedFields = [];
        if (is_string($rawFields)) {
            $requestedFields = array_values(array_filter(array_map('trim', explode(',', $rawFields))));
        } elseif (is_array($rawFields)) {
            $requestedFields = array_values(array_filter(array_map(
                static fn ($item): string => trim((string) $item),
                $rawFields
            )));
        }

        if ($requestedFields === []) {
            return $availableColumns;
        }

        $allowedLookup = collect($availableColumns)->keyBy('key');
        $selected = [];
        foreach ($requestedFields as $field) {
            if ($allowedLookup->has($field)) {
                $selected[] = $allowedLookup->get($field);
            }
        }

        return $selected !== [] ? $selected : $availableColumns;
    }

    private function buildAttendanceFilterSummary(Request $request): string
    {
        $parts = [
            'Periode: ' . (string) $request->start_date . ' s/d ' . (string) $request->end_date,
        ];

        if ($request->filled('tingkat_id')) {
            $tingkat = Tingkat::query()->find((int) $request->tingkat_id);
            $parts[] = 'Tingkat: ' . ($tingkat?->nama ?? ('#' . (int) $request->tingkat_id));
        }

        if ($request->filled('kelas_id')) {
            $kelas = Kelas::query()->find((int) $request->kelas_id);
            $parts[] = 'Kelas: ' . ($kelas?->nama_kelas ?? $kelas?->nama ?? ('#' . (int) $request->kelas_id));
        }

        if ($request->filled('status')) {
            $parts[] = 'Status: ' . $this->formatStatusLabel((string) $request->status);
        }

        if ($request->filled('status_disiplin')) {
            $statusDisiplin = $this->normalizeDisciplineStatusFilter($request->status_disiplin);
            if ($statusDisiplin !== null) {
                $parts[] = 'Status Disiplin: ' . $this->formatDisciplineStatusLabel($statusDisiplin);
            }
        }

        return implode(' | ', $parts);
    }

    private function normalizeExportStatus($status): ?string
    {
        if ($status === null || $status === '') {
            return null;
        }

        $normalized = strtolower(trim((string) $status));
        if ($normalized === 'alpa') {
            return 'alpha';
        }

        return $normalized;
    }

    private function formatStatusLabel(string $status): string
    {
        return match (strtolower($status)) {
            'hadir' => 'Hadir',
            'terlambat' => 'Terlambat',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alpha', 'alpa' => 'Alpha',
            default => ucfirst($status ?: '-'),
        };
    }

    private function countWorkingDays($start_date, $end_date, ?User $contextUser = null): int
    {
        $workingDays = 0;
        for ($date = $start_date->copy(); $date->lte($end_date); $date->addDay()) {
            if ($contextUser) {
                if ($this->attendanceTimeService->isWorkingDay($contextUser, $date->copy())) {
                    $workingDays++;
                }
                continue;
            }

            if (!$date->isWeekend()) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    private function countLateMinutes($records): int
    {
        return (int) $records->sum(function ($item) {
            return $this->computeLateMinutesFromAttendance($item);
        });
    }

    private function countTapMinutes($records): int
    {
        return (int) $records->sum(function ($item) {
            if (!$item instanceof Absensi) {
                return 0;
            }

            $status = $this->normalizeExportStatus($item->status) ?? '';
            if (empty($item->jam_masuk) || !empty($item->jam_pulang) || $status === 'alpha') {
                return 0;
            }

            $workingMinutesPerDay = $this->resolveWorkingMinutesPerDayForUser($item->user ?? null);
            return (int) round($workingMinutesPerDay * 0.5);
        });
    }

    /**
     * Build normalized violation metrics based on effective schema working-hours per user.
     *
     * @return array{
     *     alpha_menit:int,
     *     terlambat_hari:int,
     *     terlambat_menit:int,
     *     tap_hari:int,
     *     tap_menit:int,
     *     total_pelanggaran_menit:int,
     *     total_menit_dasar:int,
     *     persentase_pelanggaran:float,
     *     average_working_minutes_per_day:int
     * }
     */
    private function summarizeViolationMetrics($records): array
    {
        $totalMenitDasar = (int) $records->sum(function ($item) {
            if (!$item instanceof Absensi) {
                return 0;
            }

            return $this->resolveWorkingMinutesPerDayForUser($item->user ?? null);
        });

        $alphaMenit = (int) $records->sum(function ($item) {
            if (!$item instanceof Absensi) {
                return 0;
            }

            $status = $this->normalizeExportStatus($item->status) ?? '';
            if ($status !== 'alpha') {
                return 0;
            }

            return $this->resolveWorkingMinutesPerDayForUser($item->user ?? null);
        });

        $terlambatHari = (int) $records->sum(function ($item) {
            if (!$item instanceof Absensi) {
                return 0;
            }

            return $this->computeLateMinutesFromAttendance($item) > 0 ? 1 : 0;
        });
        $tapHari = (int) $records->sum(function ($item) {
            if (!$item instanceof Absensi) {
                return 0;
            }

            return !empty($item->jam_masuk) && empty($item->jam_pulang) ? 1 : 0;
        });
        $terlambatMenit = $this->countLateMinutes($records);
        $tapMenit = $this->countTapMinutes($records);
        $totalPelanggaranMenit = $alphaMenit + $terlambatMenit + $tapMenit;
        $persentasePelanggaran = $totalMenitDasar > 0
            ? round(($totalPelanggaranMenit / $totalMenitDasar) * 100, 2)
            : 0.0;
        $averageWorkingMinutesPerDay = $records->count() > 0
            ? (int) round($totalMenitDasar / $records->count())
            : $this->getWorkingMinutesPerDay();

        return [
            'alpha_menit' => $alphaMenit,
            'terlambat_hari' => $terlambatHari,
            'terlambat_menit' => $terlambatMenit,
            'tap_hari' => $tapHari,
            'tap_menit' => $tapMenit,
            'total_pelanggaran_menit' => $totalPelanggaranMenit,
            'total_menit_dasar' => $totalMenitDasar,
            'persentase_pelanggaran' => $persentasePelanggaran,
            'average_working_minutes_per_day' => $averageWorkingMinutesPerDay,
        ];
    }

    /**
     * Apply role-based class scope to attendance report query.
     */
    private function applyAbsensiScope($query, ?User $user): void
    {
        if (!$user || RoleDataScope::canViewAllKelas($user)) {
            return;
        }

        $classIds = RoleDataScope::accessibleClassIds($user);
        if ($classIds === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('kelas_id', $classIds);
    }

    private function resolveTargetStudentsForReport(Request $request, ?User $viewer, $records)
    {
        $recordUsers = collect($records)
            ->filter(fn ($item) => $item instanceof Absensi && $item->user instanceof User)
            ->map(fn (Absensi $attendance) => $attendance->user)
            ->keyBy(fn (User $student) => (int) $student->id);

        if ($request->filled('role')) {
            $requestedRoleAliases = RoleNames::aliasesFor((string) $request->input('role'));
            if (!array_intersect($requestedRoleAliases, RoleNames::aliases(RoleNames::SISWA))) {
                return $recordUsers->values();
            }
        }

        try {
            $targetQuery = User::query()
                ->select(['users.id', 'users.username', 'users.email', 'users.nama_lengkap', 'users.is_active'])
                ->with(['kelas' => function ($kelasQuery) {
                    $kelasQuery
                        ->where('kelas.is_active', true)
                        ->where('kelas_siswa.is_active', true)
                        ->where('kelas_siswa.status', 'aktif')
                        ->orderBy('kelas.nama_kelas');
                }])
                ->where('users.is_active', true)
                ->whereHas('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
                })
                ->whereHas('kelas', function ($kelasQuery) use ($request) {
                    $kelasQuery
                        ->where('kelas.is_active', true)
                        ->where('kelas_siswa.is_active', true)
                        ->where('kelas_siswa.status', 'aktif');

                    if ($request->filled('kelas_id')) {
                        $kelasQuery->where('kelas.id', (int) $request->input('kelas_id'));
                    }

                    if ($request->filled('tingkat_id')) {
                        $kelasQuery->where('kelas.tingkat_id', (int) $request->input('tingkat_id'));
                    }
                })
                ->orderBy('users.nama_lengkap');

            RoleDataScope::applySiswaReadScope($targetQuery, $viewer);

            $targetQuery->get()->each(function (User $student) use ($recordUsers) {
                $recordUsers->put((int) $student->id, $student);
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve target students for attendance report', [
                'user_id' => $viewer?->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $recordUsers->values();
    }

    /**
     * Validate whether a requested class is within current user's report scope.
     */
    private function canAccessRequestedKelas(?User $user, int $kelasId): bool
    {
        if ($kelasId <= 0 || !$user) {
            return false;
        }

        if (RoleDataScope::canViewAllKelas($user)) {
            return true;
        }

        return in_array($kelasId, RoleDataScope::accessibleClassIds($user), true);
    }

    /**
     * Count records by status in case-insensitive way.
     */
    private function countByStatus($records, string $status): int
    {
        $targetStatus = $this->normalizeExportStatus($status) ?? strtolower($status);

        return $records->filter(function ($item) use ($targetStatus) {
            $rowStatus = $this->normalizeExportStatus($item->status) ?? '';

            return $rowStatus === $targetStatus;
        })->count();
    }

    /**
     * Count effective presence: hadir + terlambat.
     */
    private function countPresentAttendance($records): int
    {
        return $records->filter(function ($item) {
            $status = $this->normalizeExportStatus($item->status) ?? '';
            return in_array($status, ['hadir', 'terlambat'], true);
        })->count();
    }

    /**
     * Count late records from attendance collection.
     */
    private function countLateAttendance($records): int
    {
        return $records->filter(function ($item) {
            if (!$item instanceof Absensi) {
                return false;
            }

            $status = $this->normalizeExportStatus($item->status) ?? '';
            if (!in_array($status, ['hadir', 'terlambat'], true)) {
                return false;
            }

            return $this->computeLateMinutesFromAttendance($item) > 0;
        })->count();
    }

    /**
     * Get standard working minutes per day used for alpha minute conversion.
     */
    private function getWorkingMinutesPerDay(): int
    {
        $defaultMinutes = 8 * 60;
        $globalHours = $this->getGlobalDefaultWorkingHours();
        $jamMasuk = (string) ($globalHours['jam_masuk'] ?? '07:00');
        $jamPulang = (string) ($globalHours['jam_pulang'] ?? '15:00');

        try {
            $start = $this->parseClockTime($jamMasuk);
            $end = $this->parseClockTime($jamPulang);
            $minutes = $start->diffInMinutes($end, false);
            return $minutes > 0 ? $minutes : $defaultMinutes;
        } catch (\Throwable $e) {
            return $defaultMinutes;
        }
    }

    /**
     * Resolve effective working minutes/day from schema policy per user.
     */
    private function resolveWorkingMinutesPerDayForUser(?User $user): int
    {
        $policy = $this->resolveAttendancePolicyForUser($user);
        $defaultMinutes = $this->getWorkingMinutesPerDay();

        try {
            $start = $this->parseClockTime((string) ($policy['jam_masuk'] ?? '07:00'));
            $end = $this->parseClockTime((string) ($policy['jam_pulang'] ?? '15:00'));
            $minutes = $start->diffInMinutes($end, false);
            return $minutes > 0 ? (int) $minutes : $defaultMinutes;
        } catch (\Throwable $e) {
            return $defaultMinutes;
        }
    }

    private function resolveViolationPolicy(?User $user = null): array
    {
        $thresholdConfig = $this->attendanceDisciplineService->resolveThresholdConfig($user);

        return array_merge($thresholdConfig, [
            'violation_minutes_threshold' => (int) ($thresholdConfig['legacy_violation_minutes_threshold'] ?? 480),
            'violation_percentage_threshold' => (float) ($thresholdConfig['legacy_violation_percentage_threshold'] ?? 10.0),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDisciplineThresholdPayload(
        int $lateMinutes,
        int $alphaDays,
        int $totalViolationMinutes,
        array $policy,
        string $thresholdMode,
        ?Carbon $periodStart,
        ?Carbon $periodEnd,
        bool $forceAttention = false
    ): array {
        $monthlyLateLimit = (int) ($policy['late_minutes_monthly_limit'] ?? 0);
        $semesterViolationLimit = (int) ($policy['total_violation_minutes_semester_limit'] ?? 0);
        $semesterAlphaLimit = (int) ($policy['alpha_days_semester_limit'] ?? 0);

        $monthlyLateExceeded = $thresholdMode === 'monthly'
            && $monthlyLateLimit > 0
            && $lateMinutes >= $monthlyLateLimit;
        $semesterViolationExceeded = $thresholdMode === 'semester'
            && $semesterViolationLimit > 0
            && $totalViolationMinutes >= $semesterViolationLimit;
        $semesterAlphaExceeded = $thresholdMode === 'semester'
            && $semesterAlphaLimit > 0
            && $alphaDays >= $semesterAlphaLimit;

        $attentionNeeded = $forceAttention || $monthlyLateExceeded || $semesterViolationExceeded || $semesterAlphaExceeded;

        return [
            'mode' => $thresholdMode,
            'monthly_late' => [
                'rule_key' => AttendanceDisciplineService::RULE_KEY_MONTHLY_LATE,
                'label' => 'Keterlambatan Bulanan',
                'minutes' => $lateMinutes,
                'limit' => $monthlyLateLimit,
                'mode' => (string) ($policy['monthly_late_mode'] ?? AttendanceDisciplineService::THRESHOLD_MODE_MONITOR_ONLY),
                'alertable' => (string) ($policy['monthly_late_mode'] ?? AttendanceDisciplineService::THRESHOLD_MODE_MONITOR_ONLY) === AttendanceDisciplineService::THRESHOLD_MODE_ALERTABLE,
                'exceeded' => $monthlyLateExceeded,
                'notify_wali_kelas' => (bool) ($policy['notify_wali_kelas_on_late_limit'] ?? false),
                'notify_kesiswaan' => (bool) ($policy['notify_kesiswaan_on_late_limit'] ?? false),
                'start_date' => $periodStart?->toDateString(),
                'end_date' => $periodEnd?->toDateString(),
            ],
            'semester_total_violation' => [
                'rule_key' => AttendanceDisciplineService::RULE_KEY_SEMESTER_TOTAL_VIOLATION,
                'label' => 'Total Pelanggaran Semester',
                'minutes' => $totalViolationMinutes,
                'limit' => $semesterViolationLimit,
                'mode' => (string) ($policy['semester_total_violation_mode'] ?? AttendanceDisciplineService::THRESHOLD_MODE_MONITOR_ONLY),
                'alertable' => (string) ($policy['semester_total_violation_mode'] ?? AttendanceDisciplineService::THRESHOLD_MODE_MONITOR_ONLY) === AttendanceDisciplineService::THRESHOLD_MODE_ALERTABLE,
                'exceeded' => $semesterViolationExceeded,
                'notify_wali_kelas' => (bool) ($policy['notify_wali_kelas_on_total_violation_limit'] ?? false),
                'notify_kesiswaan' => (bool) ($policy['notify_kesiswaan_on_total_violation_limit'] ?? false),
                'start_date' => $periodStart?->toDateString(),
                'end_date' => $periodEnd?->toDateString(),
            ],
            'semester_alpha' => [
                'rule_key' => AttendanceDisciplineService::RULE_KEY_SEMESTER_ALPHA,
                'label' => 'Alpha Semester',
                'days' => $alphaDays,
                'limit' => $semesterAlphaLimit,
                'mode' => (string) ($policy['semester_alpha_mode'] ?? AttendanceDisciplineService::THRESHOLD_MODE_MONITOR_ONLY),
                'alertable' => (string) ($policy['semester_alpha_mode'] ?? AttendanceDisciplineService::THRESHOLD_MODE_MONITOR_ONLY) === AttendanceDisciplineService::THRESHOLD_MODE_ALERTABLE,
                'exceeded' => $semesterAlphaExceeded,
                'notify_wali_kelas' => (bool) ($policy['notify_wali_kelas_on_alpha_limit'] ?? true),
                'notify_kesiswaan' => (bool) ($policy['notify_kesiswaan_on_alpha_limit'] ?? true),
                'start_date' => $periodStart?->toDateString(),
                'end_date' => $periodEnd?->toDateString(),
            ],
            'attention_needed' => $attentionNeeded,
            'summary_limit_minutes' => $thresholdMode === 'monthly'
                ? $monthlyLateLimit
                : ($thresholdMode === 'semester' ? $semesterViolationLimit : (int) ($policy['legacy_violation_minutes_threshold'] ?? 0)),
            'summary_limit_percentage' => $thresholdMode === 'none'
                ? (float) ($policy['legacy_violation_percentage_threshold'] ?? 0.0)
                : 0.0,
        ];
    }

    private function isViolationThresholdExceeded(int $totalViolationMinutes, float $violationPercentage, int $minutesThreshold, float $percentageThreshold): bool
    {
        $byMinutes = $minutesThreshold > 0 && $totalViolationMinutes >= $minutesThreshold;
        $byPercentage = $percentageThreshold > 0 && $violationPercentage >= $percentageThreshold;

        return $byMinutes || $byPercentage;
    }

    /**
     * Enrich attendance detail row with violation metrics for frontend table.
     */
    private function appendViolationMetricsToAttendance(
        Absensi $attendance,
        ?array $policy = null,
        string $thresholdMode = 'none'
    ): array
    {
        $workingMinutesPerDay = $this->resolveWorkingMinutesPerDayForUser($attendance->user ?? null);
        $status = $this->normalizeExportStatus($attendance->status) ?? '';
        $terlambatMenit = $this->computeLateMinutesFromAttendance($attendance);
        $effectivePolicy = $this->resolveViolationPolicy($attendance->user ?? null);
        if (is_array($policy)) {
            $effectivePolicy = array_merge($policy, $effectivePolicy);
        }

        $tapMenit = (!empty($attendance->jam_masuk) && empty($attendance->jam_pulang) && $status !== 'alpha')
            ? (int) round($workingMinutesPerDay * 0.5)
            : 0;
        $alpaMenit = $status === 'alpha' ? $workingMinutesPerDay : 0;
        $totalPelanggaranMenit = $terlambatMenit + $tapMenit + $alpaMenit;
        $persentasePelanggaran = $workingMinutesPerDay > 0
            ? round(($totalPelanggaranMenit / $workingMinutesPerDay) * 100, 2)
            : 0.0;
        $legacyExceeded = !(bool) ($effectivePolicy['uses_new_thresholds'] ?? false)
            && $this->isViolationThresholdExceeded(
                $totalPelanggaranMenit,
                $persentasePelanggaran,
                (int) ($effectivePolicy['legacy_violation_minutes_threshold'] ?? $effectivePolicy['violation_minutes_threshold'] ?? 0),
                (float) ($effectivePolicy['legacy_violation_percentage_threshold'] ?? $effectivePolicy['violation_percentage_threshold'] ?? 0)
            );
        $disciplineThresholds = $this->buildDisciplineThresholdPayload(
            $terlambatMenit,
            $status === 'alpha' ? 1 : 0,
            $totalPelanggaranMenit,
            $effectivePolicy,
            $thresholdMode,
            $attendance->tanggal ? Carbon::parse((string) $attendance->tanggal)->startOfDay() : null,
            $attendance->tanggal ? Carbon::parse((string) $attendance->tanggal)->endOfDay() : null,
            false
        );

        return array_merge($attendance->toArray(), [
            'kelas_id' => $attendance->kelas_id ?: $this->resolveAttendanceClassId($attendance),
            'kelas_nama' => $this->resolveAttendanceClassName($attendance),
            'working_minutes_per_day' => $workingMinutesPerDay,
            'terlambat_hari' => $terlambatMenit > 0 ? 1 : 0,
            'terlambat_menit' => $terlambatMenit,
            'tap_hari' => $tapMenit > 0 ? 1 : 0,
            'tap_menit' => $tapMenit,
            'alpa_menit' => $alpaMenit,
            'total_pelanggaran_menit' => $totalPelanggaranMenit,
            'total_menit_kerja' => $workingMinutesPerDay,
            'persentase_pelanggaran' => $persentasePelanggaran,
            'batas_pelanggaran_menit' => (int) data_get($disciplineThresholds, 'summary_limit_minutes', 0),
            'batas_pelanggaran_persen' => (float) data_get($disciplineThresholds, 'summary_limit_percentage', 0),
            'melewati_batas_pelanggaran' => (bool) data_get($disciplineThresholds, 'attention_needed', false) || $legacyExceeded,
            'discipline_thresholds' => $disciplineThresholds,
        ]);
    }

    private function computeLateMinutesFromAttendance($attendance): int
    {
        if (!$attendance instanceof Absensi || !$attendance->user instanceof User || empty($attendance->jam_masuk)) {
            return 0;
        }

        return $this->attendanceDisciplineService->calculateLateMinutesFromAttendance($attendance->user, $attendance);
    }

    private function countMissingAttendanceDays(
        Carbon $periodStart,
        Carbon $periodEnd,
        User $student,
        $studentItems
    ): int {
        $boundedPeriod = $this->resolveMissingAttendanceEvaluationPeriod($periodStart, $periodEnd);
        if ($boundedPeriod === null) {
            return 0;
        }

        $attendanceDates = collect($studentItems)
            ->filter(fn ($item) => $item instanceof Absensi && $item->tanggal)
            ->map(function (Absensi $attendance): string {
                return Carbon::parse((string) $attendance->tanggal)->toDateString();
            })
            ->unique()
            ->flip();

        $missingDays = 0;
        for ($date = $boundedPeriod['start']->copy(); $date->lte($boundedPeriod['end']); $date->addDay()) {
            if (!$this->attendanceTimeService->isWorkingDay($student, $date->copy())) {
                continue;
            }

            if ($attendanceDates->has($date->toDateString())) {
                continue;
            }

            $missingDays++;
        }

        return $missingDays;
    }

    /**
     * Belum absen is an operational monitoring status, so future dates are not counted yet.
     *
     * @return array{start: Carbon, end: Carbon}|null
     */
    private function resolveMissingAttendanceEvaluationPeriod(Carbon $periodStart, Carbon $periodEnd): ?array
    {
        $start = $periodStart->copy()->startOfDay();
        $end = $periodEnd->copy()->startOfDay();
        $today = now()->startOfDay();

        if ($start->gt($today)) {
            return null;
        }

        if ($end->gt($today)) {
            $end = $today;
        }

        if ($end->lt($start)) {
            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function resolveWorkingDayContextUser($records, ?User $fallbackUser = null): ?User
    {
        $collection = $records instanceof \Illuminate\Support\Collection
            ? $records
            : collect($records);

        $attendanceOwner = $collection
            ->first(function ($item) {
                return $item instanceof Absensi && $item->user instanceof User;
            });

        if ($attendanceOwner instanceof Absensi && $attendanceOwner->user instanceof User) {
            return $attendanceOwner->user;
        }

        return $fallbackUser instanceof User ? $fallbackUser : null;
    }

    /**
     * Resolve jam masuk + toleransi from effective schema per user.
     *
     * @return array{jam_masuk: string, jam_pulang: string, toleransi: int}
     */
    private function resolveAttendancePolicyForUser(?User $user): array
    {
        $globalHours = $this->getGlobalDefaultWorkingHours($user);
        $defaults = [
            'jam_masuk' => (string) ($globalHours['jam_masuk'] ?? '07:00'),
            'jam_pulang' => (string) ($globalHours['jam_pulang'] ?? '15:00'),
            'toleransi' => 15,
        ];

        if (!$user) {
            return $defaults;
        }

        $userId = (int) $user->id;
        if (isset($this->attendancePolicyCache[$userId])) {
            return $this->attendancePolicyCache[$userId];
        }

        try {
            $effectiveSchema = $this->attendanceSchemaService->getEffectiveSchema($user);
            $workingHours = $effectiveSchema?->getEffectiveWorkingHours($user) ?? [];

            $policy = [
                'jam_masuk' => (string) ($workingHours['jam_masuk'] ?? $defaults['jam_masuk']),
                'jam_pulang' => (string) ($workingHours['jam_pulang'] ?? $defaults['jam_pulang']),
                'toleransi' => (int) ($workingHours['toleransi'] ?? $defaults['toleransi']),
            ];
        } catch (\Throwable $e) {
            $policy = $defaults;
        }

        $this->attendancePolicyCache[$userId] = $policy;
        return $policy;
    }

    private function parseClockTime(string $value): Carbon
    {
        $time = trim($value);

        try {
            if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                return Carbon::createFromFormat('H:i', $time);
            }

            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
                return Carbon::createFromFormat('H:i:s', $time);
            }

            return Carbon::parse($time);
        } catch (\Throwable $e) {
            $fallbackHour = (string) (($this->getGlobalDefaultWorkingHours()['jam_masuk'] ?? '07:00'));
            return Carbon::createFromFormat('H:i', substr($fallbackHour, 0, 5));
        }
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

    private function resolveAttendanceClassId(Absensi $attendance): ?int
    {
        if ($attendance->kelas_id) {
            return (int) $attendance->kelas_id;
        }

        $user = $attendance->user;
        if (!$user) {
            return null;
        }

        if ($user->relationLoaded('kelas') && $user->kelas->isNotEmpty()) {
            $activeClass = $user->kelas->first(function ($kelas) {
                return (bool) ($kelas->pivot->is_active ?? false);
            });

            if ($activeClass) {
                return (int) $activeClass->id;
            }

            return (int) $user->kelas->first()->id;
        }

        $activeClass = $user->kelas()->wherePivot('is_active', true)->first();
        if ($activeClass) {
            return (int) $activeClass->id;
        }

        $fallbackClass = $user->kelas()->first();
        return $fallbackClass ? (int) $fallbackClass->id : null;
    }

    private function resolveAttendanceClassName(Absensi $attendance): string
    {
        if ($attendance->kelas) {
            return $attendance->kelas->nama_kelas
                ?? $attendance->kelas->nama
                ?? '-';
        }

        $user = $attendance->user;
        if (!$user) {
            return '-';
        }

        if ($user->relationLoaded('kelas') && $user->kelas->isNotEmpty()) {
            $activeClass = $user->kelas->first(function ($kelas) {
                return (bool) ($kelas->pivot->is_active ?? false);
            });

            $fallback = $activeClass ?: $user->kelas->first();
            return $fallback?->nama_kelas
                ?? $fallback?->nama
                ?? '-';
        }

        $activeClass = $user->kelas()->wherePivot('is_active', true)->first();
        if ($activeClass) {
            return $activeClass->nama_kelas
                ?? $activeClass->nama
                ?? '-';
        }

        $fallbackClass = $user->kelas()->first();
        return $fallbackClass?->nama_kelas
            ?? $fallbackClass?->nama
            ?? '-';
    }

    private function resolveUserClassId(?User $user): ?int
    {
        $class = $this->resolveUserActiveClass($user);
        return $class ? (int) $class->id : null;
    }

    private function resolveUserClassName(?User $user): string
    {
        $class = $this->resolveUserActiveClass($user);

        return $class?->nama_kelas
            ?? $class?->nama
            ?? '-';
    }

    private function resolveUserActiveClass(?User $user): ?Kelas
    {
        if (!$user) {
            return null;
        }

        if ($user->relationLoaded('kelas') && $user->kelas->isNotEmpty()) {
            $activeClass = $user->kelas->first(function ($kelas) {
                return (bool) ($kelas->pivot->is_active ?? true);
            });

            return $activeClass ?: $user->kelas->first();
        }

        $activeClass = $user->kelas()
            ->where('kelas.is_active', true)
            ->wherePivot('is_active', true)
            ->first();

        return $activeClass ?: $user->kelas()->first();
    }
}
