<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Kelas;
use App\Models\Absensi;
use App\Models\AttendanceFraudAssessment;
use App\Models\AttendanceSecurityCase;
use App\Models\AttendanceSecurityCaseActivity;
use App\Models\AttendanceSecurityCaseEvidence;
use App\Models\AttendanceSecurityCaseItem;
use App\Models\AttendanceSecurityEvent;
use App\Models\Izin;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class WaliKelasController extends Controller
{
    public function __construct()
    {
        // Endpoint wali kelas dipakai juga oleh mobile JWT.
        $this->middleware('auth:sanctum,api');
    }

    // Get kelas yang diwalikelasi
    public function getMyKelas()
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->buildAccessibleKelasQuery($user, $activeTahunAjaranId)
            ->with('tingkat')
            ->withCount([
                'siswa as jumlah_siswa' => function ($query) use ($activeTahunAjaranId) {
                    if ($activeTahunAjaranId) {
                        $query->where('kelas_siswa.tahun_ajaran_id', $activeTahunAjaranId);
                    }
                    $query->where('kelas_siswa.status', 'aktif');
                },
            ])
            ->get();

        $kelasIds = $kelas->pluck('id')->map(static fn ($id): int => (int) $id)->all();
        $attendanceToday = collect();
        $pendingLeaves = collect();

        if ($kelasIds !== []) {
            $today = Carbon::today();

            $attendanceToday = Absensi::query()
                ->select('kelas_id')
                ->selectRaw("SUM(CASE WHEN status IN ('hadir', 'terlambat') THEN 1 ELSE 0 END) as hadir_hari_ini")
                ->selectRaw("SUM(CASE WHEN status = 'terlambat' THEN 1 ELSE 0 END) as terlambat_hari_ini")
                ->selectRaw("SUM(CASE WHEN status IN ('alpha', 'izin', 'sakit') THEN 1 ELSE 0 END) as tidak_hadir_hari_ini")
                ->whereIn('kelas_id', $kelasIds)
                ->whereDate('tanggal', $today)
                ->groupBy('kelas_id')
                ->get()
                ->keyBy('kelas_id');

            $pendingLeaves = Izin::query()
                ->select('kelas_id')
                ->selectRaw('COUNT(*) as izin_pending')
                ->whereIn('kelas_id', $kelasIds)
                ->where('status', 'pending')
                ->groupBy('kelas_id')
                ->get()
                ->keyBy('kelas_id');
        }

        return response()->json(
            $kelas->map(function (Kelas $kelas) use ($attendanceToday, $pendingLeaves): array {
                $kelasData = $kelas->toArray();
                $attendance = $attendanceToday->get($kelas->id);
                $pending = $pendingLeaves->get($kelas->id);

                $kelasData['hadir_hari_ini'] = (int) ($attendance->hadir_hari_ini ?? 0);
                $kelasData['terlambat_hari_ini'] = (int) ($attendance->terlambat_hari_ini ?? 0);
                $kelasData['tidak_hadir_hari_ini'] = (int) ($attendance->tidak_hadir_hari_ini ?? 0);
                $kelasData['izin_pending'] = (int) ($pending->izin_pending ?? 0);

                return $kelasData;
            })->values()
        );
    }

    // Get detail kelas
    public function getKelasDetail($id)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId)
            ->load([
                'tingkat',
                'siswa' => function ($q) use ($activeTahunAjaranId) {
                    $q->select('users.id', 'nama_lengkap', 'nisn');
                    if ($activeTahunAjaranId) {
                        $q->where('kelas_siswa.tahun_ajaran_id', $activeTahunAjaranId);
                    }
                    $q->where('kelas_siswa.status', 'aktif');
                },
            ]);

        // Hitung statistik hari ini
        $today = Carbon::today();
        $hadir = Absensi::where('kelas_id', $id)
            ->where('tanggal', $today)
            ->whereIn('status', ['hadir', 'terlambat'])
            ->count();

        $terlambat = Absensi::where('kelas_id', $id)
            ->where('tanggal', $today)
            ->where('status', 'terlambat')
            ->count();

        $tidakHadir = Absensi::where('kelas_id', $id)
            ->where('tanggal', $today)
            ->whereIn('status', ['alpha', 'izin', 'sakit'])
            ->count();

        $izinPending = Izin::where('kelas_id', $id)
            ->where('status', 'pending')
            ->count();

        return response()->json([
            'kelas' => $kelas,
            'hadir_hari_ini' => $hadir,
            'terlambat_hari_ini' => $terlambat,
            'tidak_hadir_hari_ini' => $tidakHadir,
            'izin_pending' => $izinPending
        ]);
    }

    // Get absensi kelas
    public function getKelasAbsensi($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);

        $tanggal = $request->tanggal ? Carbon::parse($request->tanggal) : Carbon::today();

        $absensi = Absensi::with('user')
            ->where('kelas_id', $id)
            ->where('tanggal', $tanggal)
            ->get();

        $hadir = $absensi->whereIn('status', ['hadir', 'terlambat'])->count();
        $terlambat = $absensi->where('status', 'terlambat')->count();
        $izin = $absensi->where('status', 'izin')->count();
        $sakit = $absensi->where('status', 'sakit')->count();
        $alpha = $absensi->where('status', 'alpha')->count();

        return response()->json([
            'hadir' => $hadir,
            'terlambat' => $terlambat,
            'izin' => $izin,
            'sakit' => $sakit,
            'alpha' => $alpha,
            'detail' => $absensi->map(static function (Absensi $attendance): array {
                $validationStatus = strtolower(trim((string) $attendance->validation_status)) === 'valid'
                    ? 'valid'
                    : 'warning';

                return [
                    'id' => (int) $attendance->id,
                    'user_id' => (int) $attendance->user_id,
                    'tanggal' => $attendance->tanggal?->toDateString(),
                    'status' => $attendance->status,
                    'keterangan' => $attendance->keterangan,
                    'validation_status' => $validationStatus,
                    'has_warning' => $validationStatus !== 'valid',
                    'warning_summary' => $attendance->fraud_decision_reason,
                    'fraud_flags_count' => (int) ($attendance->fraud_flags_count ?? 0),
                    'user' => [
                        'id' => (int) ($attendance->user?->id ?? 0),
                        'nama_lengkap' => $attendance->user?->nama_lengkap,
                        'nisn' => $attendance->user?->nisn,
                    ],
                ];
            })->values()
        ]);
    }

    // Get statistik kelas
    public function getKelasStatistik($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);

        $bulan = $request->bulan ? Carbon::parse($request->bulan) : Carbon::now();
        $startDate = $bulan->startOfMonth();
        $endDate = $bulan->copy()->endOfMonth();

        // Hitung total kehadiran. Status terlambat tetap dihitung hadir,
        // tetapi dipisahkan agar monitoring disiplin mengikuti sistem baru.
        $totalTepatWaktu = Absensi::where('kelas_id', $id)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where('status', 'hadir')
            ->count();

        $totalTerlambat = Absensi::where('kelas_id', $id)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where('status', 'terlambat')
            ->count();

        $totalHadir = $totalTepatWaktu + $totalTerlambat;

        $totalTidakHadir = Absensi::where('kelas_id', $id)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereIn('status', ['alpha', 'izin', 'sakit'])
            ->count();

        // Hitung persentase kehadiran
        $totalHariEfektif = $kelas->jumlah_siswa * $bulan->daysInMonth;
        $persentaseKehadiran = $totalHariEfektif > 0 
            ? round(($totalHadir / $totalHariEfektif) * 100, 2)
            : 0;

        // Dapatkan siswa dengan alpha terbanyak
        $siswaTerbanyakAlpha = DB::table('absensi')
            ->join('users', 'absensi.user_id', '=', 'users.id')
            ->select('users.id', 'users.nama_lengkap as nama', 'users.nisn', DB::raw('count(*) as total_alpha'))
            ->where('absensi.kelas_id', $id)
            ->where('absensi.status', 'alpha')
            ->whereBetween('absensi.tanggal', [$startDate, $endDate])
            ->groupBy('users.id', 'users.nama_lengkap', 'users.nisn')
            ->orderByDesc('total_alpha')
            ->limit(5)
            ->get();

        return response()->json([
            'persentase_kehadiran' => $persentaseKehadiran,
            'total_hadir' => $totalHadir,
            'total_tepat_waktu' => $totalTepatWaktu,
            'total_terlambat' => $totalTerlambat,
            'total_tidak_hadir' => $totalTidakHadir,
            'siswa_terbanyak_alpha' => $siswaTerbanyakAlpha
        ]);
    }

    public function getKelasSecurityEvents($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'category' => 'nullable|string|max:50',
            'event_key' => 'nullable|string|max:100',
            'issue_key' => 'nullable|string|max:100',
            'severity' => 'nullable|in:low,medium,high,critical',
            'status' => 'nullable|in:blocked,flagged,allowed',
            'stage' => 'nullable|in:attendance_precheck,attendance_submit',
            'attempt_type' => 'nullable|in:masuk,pulang',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi filter laporan keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId)
            ->load('tingkat');

        $query = AttendanceSecurityEvent::query()
            ->with([
                'user:id,nama_lengkap,username,nis,nisn',
            ])
            ->where('kelas_id', $kelas->id);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('event_key')) {
            $query->where('event_key', $request->input('event_key'));
        }

        $issueKey = $request->filled('issue_key')
            ? $request->input('issue_key')
            : ($request->filled('flag_key') ? $request->input('flag_key') : null);

        if ($issueKey) {
            $query->whereIssueKey($issueKey);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } elseif ($request->filled('validation_status')) {
            if ($request->input('validation_status') === 'warning') {
                $query->whereIn('status', ['blocked', 'flagged']);
            } else {
                $query->where('status', 'allowed');
            }
        }

        $stage = $request->filled('stage')
            ? $request->input('stage')
            : ($request->filled('source') ? $request->input('source') : null);

        if ($stage) {
            $query->whereStage($stage);
        }

        if ($request->filled('attempt_type')) {
            $query->where('attempt_type', $request->input('attempt_type'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $summaryEvents = (clone $query)->orderByDesc('updated_at')->orderByDesc('created_at')->get();
        $perPage = (int) $request->input('per_page', 15);
        $events = $query->orderByDesc('updated_at')->orderByDesc('created_at')->paginate($perPage);
        $events->getCollection()->transform(
            static fn(AttendanceSecurityEvent $event): array => $event->toReportArray()
        );

        $stageBreakdown = $summaryEvents
            ->groupBy(fn(AttendanceSecurityEvent $event): string => $this->resolveSecurityEventStage($event))
            ->map(static function (Collection $group, string $stage): array {
                return [
                    'stage' => $stage,
                    'stage_label' => AttendanceSecurityEvent::labelForStage($stage),
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $severityBreakdown = $summaryEvents
            ->groupBy('severity')
            ->map(static function (Collection $group, string $severity): array {
                return [
                    'severity' => $severity,
                    'severity_label' => AttendanceSecurityEvent::labelForSeverity($severity),
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $followUpStudents = $summaryEvents
            ->groupBy('user_id')
            ->filter(static fn($group, $userId): bool => $userId !== null)
            ->map(fn(Collection $group): array => $this->buildSecurityStudentSummaryRow($group))
            ->filter(static function (array $row): bool {
                return $row['blocked_events'] >= 2
                    || $row['mock_location_events'] >= 1
                    || $row['device_events'] >= 1;
            })
            ->sortByDesc('total_events')
            ->take(10)
            ->values()
            ->all();

        $eventBreakdown = $summaryEvents
            ->flatMap(static function (AttendanceSecurityEvent $event): array {
                return array_map(static function (array $issue): array {
                    $eventKey = (string) ($issue['event_key'] ?? 'unknown_security_event');

                    return [
                        'event_key' => $eventKey,
                        'event_label' => $issue['label'] ?? AttendanceSecurityEvent::labelForEventKey($eventKey),
                    ];
                }, $event->issueRows());
            })
            ->groupBy('event_key')
            ->map(static function (Collection $group, string $eventKey): array {
                $first = $group->first();

                return [
                    'event_key' => $eventKey,
                    'event_label' => $first['event_label'] ?? AttendanceSecurityEvent::labelForEventKey($eventKey),
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => [
                    'id' => (int) $kelas->id,
                    'name' => $kelas->nama_lengkap,
                ],
                'summary' => [
                    'total_events' => $summaryEvents->count(),
                    'blocked_events' => $summaryEvents->where('status', 'blocked')->count(),
                    'flagged_events' => $summaryEvents->where('status', 'flagged')->count(),
                    'allowed_events' => $summaryEvents->where('status', 'allowed')->count(),
                    'precheck_events' => $summaryEvents
                        ->filter(fn(AttendanceSecurityEvent $event): bool => $this->resolveSecurityEventStage($event) === 'attendance_precheck')
                        ->count(),
                    'submit_events' => $summaryEvents
                        ->filter(fn(AttendanceSecurityEvent $event): bool => $this->resolveSecurityEventStage($event) === 'attendance_submit')
                        ->count(),
                    'masuk_events' => $summaryEvents->where('attempt_type', 'masuk')->count(),
                    'pulang_events' => $summaryEvents->where('attempt_type', 'pulang')->count(),
                    'device_events' => $summaryEvents
                        ->filter(fn(AttendanceSecurityEvent $event): bool => $this->isDeviceOrAppIntegrityEvent($event))
                        ->count(),
                    'mock_location_events' => $summaryEvents
                        ->filter(fn(AttendanceSecurityEvent $event): bool => $event->hasIssueKey('mock_location_detected'))
                        ->count(),
                    'unique_students' => $summaryEvents->pluck('user_id')->filter()->unique()->count(),
                    'stage_breakdown' => $stageBreakdown,
                    'severity_breakdown' => $severityBreakdown,
                    'event_breakdown' => $eventBreakdown,
                    'follow_up_students' => $followUpStudents,
                ],
                'config' => $this->buildSecurityMonitoringConfigPayload(),
                'events' => $events,
            ],
        ]);
    }

    public function exportKelasSecurityEvents($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'category' => 'nullable|string|max:50',
            'event_key' => 'nullable|string|max:100',
            'issue_key' => 'nullable|string|max:100',
            'severity' => 'nullable|in:low,medium,high,critical',
            'status' => 'nullable|in:blocked,flagged,allowed',
            'stage' => 'nullable|in:attendance_precheck,attendance_submit',
            'attempt_type' => 'nullable|in:masuk,pulang',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi export laporan keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $rows = AttendanceSecurityEvent::query()
            ->with(['user:id,nama_lengkap,username,nis,nisn'])
            ->where('kelas_id', $kelas->id)
            ->when($request->filled('category'), fn($query) => $query->where('category', $request->input('category')))
            ->when($request->filled('event_key'), fn($query) => $query->where('event_key', $request->input('event_key')))
            ->when($request->filled('issue_key'), fn($query) => $query->whereIssueKey($request->input('issue_key')))
            ->when($request->filled('severity'), fn($query) => $query->where('severity', $request->input('severity')))
            ->when($request->filled('status'), fn($query) => $query->where('status', $request->input('status')))
            ->when($request->filled('stage'), fn($query) => $query->whereStage($request->input('stage')))
            ->when($request->filled('attempt_type'), fn($query) => $query->where('attempt_type', $request->input('attempt_type')))
            ->when($request->filled('date_from'), fn($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn($query) => $query->whereDate('created_at', '<=', $request->input('date_to')))
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn(AttendanceSecurityEvent $event): array => $event->toReportArray())
            ->values();

        $timestamp = now()->format('Ymd-His');

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'ID',
                'Tahap',
                'Jenis Presensi',
                'Waktu',
                'Siswa',
                'Identitas',
                'Event Primer',
                'Semua Issue',
                'Jumlah Deteksi',
                'Severity',
                'Status',
                'Pesan',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['stage_label'] ?? '-',
                    $row['attempt_type'] ?? '-',
                    $row['last_seen_at'] ?? $row['created_at'] ?? '-',
                    $row['student']['name'] ?? '-',
                    $row['student']['identifier'] ?? '-',
                    $row['event_label'] ?? '-',
                    implode(', ', array_map(
                        static fn(array $issue): string => (string) ($issue['label'] ?? $issue['event_key'] ?? '-'),
                        is_array($row['issues'] ?? null) ? $row['issues'] : []
                    )),
                    $row['occurrence_count'] ?? 1,
                    $row['severity_label'] ?? '-',
                    $row['status_label'] ?? '-',
                    $row['message'] ?? '-',
                ]);
            }

            fclose($output);
        }, 'monitoring-kelas-security-events-' . $timestamp . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getKelasFraudAssessments($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = $this->makeFraudAssessmentFilterValidator($request, true);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi filter fraud monitoring gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId)
            ->load('tingkat');

        $perPage = (int) $request->input('per_page', 15);
        $assessments = $this->buildKelasFraudAssessmentsQuery($kelas, $request)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $assessments->getCollection()->transform(
            static fn(AttendanceFraudAssessment $assessment): array => $assessment->toMonitoringArray()
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => [
                    'id' => (int) $kelas->id,
                    'name' => $kelas->nama_lengkap,
                ],
                'assessments' => $assessments,
            ],
        ]);
    }

    public function exportKelasFraudAssessments($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = $this->makeFraudAssessmentFilterValidator($request, false);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi export fraud monitoring gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $rows = $this->buildKelasFraudAssessmentsQuery($kelas, $request)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn(AttendanceFraudAssessment $assessment): array => $assessment->toMonitoringArray())
            ->values();

        $timestamp = now()->format('Ymd-His');

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'ID',
                'Tahap',
                'Waktu',
                'Siswa',
                'Identitas',
                'Status Validasi',
                'Has Warning',
                'Warning Summary',
                'Flag',
                'Recommended Action',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['source_label'] ?? '-',
                    $row['last_seen_at'] ?? $row['created_at'] ?? '-',
                    $row['student']['name'] ?? '-',
                    $row['student']['identifier'] ?? '-',
                    $row['validation_status_label'] ?? '-',
                    !empty($row['has_warning']) ? 'Ya' : 'Tidak',
                    $row['warning_summary'] ?? '-',
                    $row['fraud_flags_count'] ?? 0,
                    $row['recommended_action'] ?? '-',
                ]);
            }

            fclose($output);
        }, 'monitoring-kelas-fraud-assessments-' . $timestamp . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getKelasFraudAssessmentSummary($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = $this->makeFraudAssessmentFilterValidator($request, false);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi ringkasan fraud monitoring gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId)
            ->load('tingkat');

        $assessments = $this->buildKelasFraudAssessmentsQuery($kelas, $request)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();
        $assessmentRows = $assessments
            ->map(static fn(AttendanceFraudAssessment $assessment): array => $assessment->toMonitoringArray())
            ->values();

        $topFlags = $assessments
            ->flatMap(static fn(AttendanceFraudAssessment $assessment) => $assessment->flags)
            ->groupBy('flag_key')
            ->map(static function ($group, string $flagKey): array {
                $first = $group->first();

                return [
                    'flag_key' => $flagKey,
                    'label' => $first?->label,
                    'severity' => $first?->severity,
                    'total' => $group->sum(static fn($flag): int => max(1, (int) (is_array($flag->evidence ?? null) ? ($flag->evidence['occurrence_count'] ?? 1) : 1))),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->take(10)
            ->all();

        $followUpStudents = $assessments
            ->groupBy('user_id')
            ->filter(static fn($group, $userId): bool => $userId !== null)
            ->map(function ($group) {
                /** @var \Illuminate\Support\Collection $group */
                $latest = $group->sortByDesc(
                    static fn(AttendanceFraudAssessment $assessment): int => $assessment->updated_at?->getTimestamp()
                        ?? $assessment->created_at?->getTimestamp()
                        ?? 0
                )->first();

                $student = $latest?->toMonitoringArray()['student'] ?? [];

                return [
                    'user_id' => $latest?->user_id ? (int) $latest->user_id : null,
                    'student_name' => $student['name'] ?? null,
                    'student_identifier' => $student['identifier'] ?? null,
                    'total_assessments' => $group->count(),
                    'warning_attempts' => $group->filter(static fn(AttendanceFraudAssessment $assessment): bool => $assessment->toMonitoringArray()['has_warning'] === true)->count(),
                    'precheck_warning_attempts' => $group->filter(static fn(AttendanceFraudAssessment $assessment): bool => $assessment->toMonitoringArray()['has_warning'] === true && $assessment->source === 'attendance_precheck')->count(),
                    'submit_warning_attempts' => $group->filter(static fn(AttendanceFraudAssessment $assessment): bool => $assessment->toMonitoringArray()['has_warning'] === true && $assessment->source === 'attendance_submit')->count(),
                    'last_assessment_at' => $latest?->created_at?->toIso8601String(),
                ];
            })
            ->filter(static function (array $row): bool {
                return $row['warning_attempts'] >= 1;
            })
            ->sortByDesc(static function (array $row): int {
                return ($row['submit_warning_attempts'] * 10)
                    + ($row['precheck_warning_attempts'] * 5)
                    + $row['warning_attempts'];
            })
            ->values()
            ->take(10)
            ->all();

        $recentWarningAssessments = $assessmentRows
            ->where('has_warning', true)
            ->take(5)
            ->values()
            ->all();

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => [
                    'id' => (int) $kelas->id,
                    'name' => $kelas->nama_lengkap,
                ],
                'config' => $this->buildFraudMonitoringConfigPayload(),
                'summary' => [
                    'total_assessments' => $assessments->count(),
                    'warning_count' => $assessmentRows->where('has_warning', true)->count(),
                    'precheck_warning_count' => $assessmentRows
                        ->where('has_warning', true)
                        ->where('source', 'attendance_precheck')
                        ->count(),
                    'submit_warning_count' => $assessmentRows
                        ->where('has_warning', true)
                        ->where('source', 'attendance_submit')
                        ->count(),
                    'unique_students' => $assessments->pluck('user_id')->filter()->unique()->count(),
                    'top_flags' => $topFlags,
                    'follow_up_students' => $followUpStudents,
                    'recent_warning_assessments' => $recentWarningAssessments,
                ],
            ],
        ]);
    }

    public function showKelasFraudAssessment($id, AttendanceFraudAssessment $assessment)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);

        if ((int) $assessment->kelas_id !== (int) $kelas->id) {
            abort(404, 'Fraud assessment tidak ditemukan pada kelas ini');
        }

        $assessment->load([
            'user:id,nama_lengkap,username,nis,nisn',
            'kelas:id,nama_kelas,jurusan,tingkat_id',
            'flags',
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $assessment->toMonitoringArray(),
        ]);
    }

    public function getKelasSecurityStudents($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = $this->makeSecurityMonitoringFilterValidator($request, true);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi filter ringkasan siswa keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId)
            ->load('tingkat');

        $events = $this->buildKelasSecurityEventsQuery($kelas, $request)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        $assessments = $this->buildKelasFraudAssessmentsQuery($kelas, $request)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        $cases = AttendanceSecurityCase::query()
            ->with(['user:id,nama_lengkap,username,nis,nisn', 'kelas:id,nama_kelas,jurusan,tingkat_id', 'items'])
            ->withCount(['items', 'evidence'])
            ->where('kelas_id', $kelas->id)
            ->get();

        $allRows = $this->buildSecurityStudentRows($events, $assessments, $cases);
        $studentScope = (string) $request->input('student_scope', 'needs_case');
        $rows = $this->filterSecurityStudentRowsByScope($allRows, $studentScope);
        $paginated = $this->paginateArrayRows(
            $rows,
            (int) $request->input('per_page', 15),
            (int) $request->input('page', 1)
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => [
                    'id' => (int) $kelas->id,
                    'name' => $kelas->nama_lengkap,
                ],
                'students' => $paginated['data'],
                'meta' => $paginated['meta'],
                'summary' => $this->buildSecurityStudentSummaryPayload($allRows),
            ],
        ]);
    }

    public function getKelasSecurityStudent($id, $userId, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = $this->makeSecurityMonitoringFilterValidator($request, false);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi filter detail siswa keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId)
            ->load('tingkat');
        $student = User::query()
            ->select('id', 'nama_lengkap', 'username', 'nis', 'nisn')
            ->findOrFail((int) $userId);

        $events = $this->buildKelasSecurityEventsQuery($kelas, $request)
            ->where('user_id', $student->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        $assessments = $this->buildKelasFraudAssessmentsQuery($kelas, $request)
            ->where('user_id', $student->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get();

        $cases = AttendanceSecurityCase::query()
            ->with([
                'user:id,nama_lengkap,username,nis,nisn',
                'kelas:id,nama_kelas,jurusan,tingkat_id',
                'items',
                'evidence.uploader:id,nama_lengkap',
                'activities.actor:id,nama_lengkap',
                'opener:id,nama_lengkap',
                'assignee:id,nama_lengkap',
                'resolver:id,nama_lengkap',
            ])
            ->withCount(['items', 'evidence'])
            ->where('kelas_id', $kelas->id)
            ->where('user_id', $student->id)
            ->orderByDesc('updated_at')
            ->get();

        $summaryRows = $this->buildSecurityStudentRows($events, $assessments, $cases);
        $timeline = $this->buildSecurityStudentTimeline($events, $assessments, $cases);

        return response()->json([
            'status' => 'success',
            'data' => [
                'kelas' => [
                    'id' => (int) $kelas->id,
                    'name' => $kelas->nama_lengkap,
                ],
                'student' => [
                    'user_id' => (int) $student->id,
                    'name' => $student->nama_lengkap,
                    'identifier' => $student->nisn ?: $student->nis ?: $student->username,
                ],
                'summary' => $summaryRows[0] ?? null,
                'timeline' => $timeline,
                'cases' => $cases
                    ->map(static fn(AttendanceSecurityCase $case): array => $case->toDetailArray())
                    ->values()
                    ->all(),
            ],
        ]);
    }

    public function getKelasSecurityCases($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'case_scope' => 'nullable|in:active,archive,all',
            'status' => 'nullable|in:open,resolved,escalated,reopened',
            'priority' => 'nullable|in:low,medium,high,critical',
            'user_id' => 'nullable|integer|exists:users,id',
            'search' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi filter kasus keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $perPage = (int) $request->input('per_page', 15);

        $cases = $this->buildKelasSecurityCasesQuery($kelas, $request)
            ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'reopened' THEN 1 WHEN 'escalated' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->paginate($perPage);

        $cases->getCollection()->transform(
            static fn(AttendanceSecurityCase $case): array => $case->toListArray()
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'cases' => $cases,
            ],
        ]);
    }

    public function storeKelasSecurityCase($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = $this->makeSecurityCasePayloadValidator($request);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pembuatan kasus keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $validated = $validator->validated();
        $requestedItems = $validated['items'] ?? [];
        $caseItems = $this->normalizeSecurityCaseItems($kelas, $requestedItems);
        $userId = $validated['user_id'] ?? $this->resolveUserIdFromSecurityCaseItems($caseItems);

        if (!$userId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kasus keamanan harus memiliki siswa atau bukti yang terkait siswa.',
            ], 422);
        }

        if ($requestedItems !== [] && $caseItems === []) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bukti yang dipilih tidak ditemukan pada kelas ini.',
            ], 422);
        }

        if ($caseItems === []) {
            $caseItems = $this->buildDefaultSecurityCaseItems($kelas, (int) $userId, $request);
        }

        $case = DB::transaction(function () use ($kelas, $validated, $caseItems, $userId, $user, $request): AttendanceSecurityCase {
            $previousClosedCases = AttendanceSecurityCase::query()
                ->with('items')
                ->where('kelas_id', $kelas->id)
                ->where('user_id', (int) $userId)
                ->whereIn('status', ['resolved', 'escalated'])
                ->get();
            /** @var AttendanceSecurityCase|null $previousClosedCase */
            $previousClosedCase = $previousClosedCases
                ->sortByDesc(fn(AttendanceSecurityCase $previousCase): int => $this->caseResolutionTimestamp($previousCase))
                ->first();
            $violationSequence = $previousClosedCases->count() + 1;
            $summary = $validated['summary'] ?? $this->buildDefaultCaseSummary($caseItems);
            if (!isset($validated['summary']) && $violationSequence > 1) {
                $summary = 'Pelanggaran Lanjutan #' . $violationSequence . ' - ' . $summary;
            }

            $case = AttendanceSecurityCase::query()->create([
                'case_number' => $this->makeSecurityCaseNumber(),
                'user_id' => (int) $userId,
                'kelas_id' => (int) $kelas->id,
                'opened_by' => (int) $user->id,
                'assigned_to' => isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
                'case_date' => now()->toDateString(),
                'status' => 'open',
                'priority' => $validated['priority'] ?? 'medium',
                'summary' => $summary,
                'student_statement' => $validated['student_statement'] ?? null,
                'staff_notes' => $validated['staff_notes'] ?? null,
            ]);

            foreach ($caseItems as $item) {
                AttendanceSecurityCaseItem::query()->create([
                    'case_id' => $case->id,
                    'item_type' => $item['item_type'],
                    'item_id' => $item['item_id'],
                    'item_snapshot' => $item['item_snapshot'],
                ]);
            }

            AttendanceSecurityCaseEvidence::query()->create([
                'case_id' => $case->id,
                'uploaded_by' => (int) $user->id,
                'evidence_type' => 'system_snapshot',
                'title' => 'Snapshot bukti sistem saat kasus dibuat',
                'description' => 'Snapshot otomatis agar data awal tidak berubah saat log presensi bertambah.',
                'metadata' => [
                    'items' => array_column($caseItems, 'item_snapshot'),
                    'violation_sequence' => $violationSequence,
                    'violation_sequence_label' => $violationSequence > 1
                        ? 'Pelanggaran Lanjutan #' . $violationSequence
                        : 'Pelanggaran #1',
                    'previous_case' => $previousClosedCase ? $previousClosedCase->toListArray() : null,
                    'previous_case_issues' => $previousClosedCase
                        ? $this->extractSecurityCaseIssueRows($previousClosedCase)
                        : [],
                    'captured_at' => now()->toIso8601String(),
                ],
            ]);

            $this->recordSecurityCaseActivity(
                $case,
                $request,
                'case_created',
                'Kasus tindak lanjut keamanan dibuat.',
                null,
                $case->toListArray()
            );

            return $case;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Kasus keamanan berhasil dibuat',
            'data' => $this->loadSecurityCaseDetail($case)->toDetailArray(),
        ], 201);
    }

    public function showKelasSecurityCase($id, AttendanceSecurityCase $case)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $this->assertSecurityCaseBelongsToKelas($case, $kelas);

        return response()->json([
            'status' => 'success',
            'data' => $this->loadSecurityCaseDetail($case)->toDetailArray(),
        ]);
    }

    public function updateKelasSecurityCase($id, AttendanceSecurityCase $case, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:open,resolved,escalated,reopened',
            'priority' => 'nullable|in:low,medium,high,critical',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'summary' => 'nullable|string|max:2000',
            'student_statement' => 'nullable|string|max:10000',
            'staff_notes' => 'nullable|string|max:10000',
            'resolution' => 'nullable|in:confirmed_violation,false_positive,student_guided,device_fixed,parent_notified,followed_up',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi pembaruan kasus keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $this->assertSecurityCaseBelongsToKelas($case, $kelas);

        $before = $case->toListArray();
        $payload = $validator->validated();
        $case->fill($payload);

        if (in_array($case->status, ['resolved', 'escalated'], true) && !$case->resolved_at) {
            $case->resolved_at = now();
            $case->resolved_by = $user->id;
        }

        if (in_array($case->status, ['open', 'reopened'], true)) {
            $case->resolved_at = null;
            $case->resolved_by = null;
        }

        $case->save();
        $this->recordSecurityCaseActivity(
            $case,
            $request,
            'case_updated',
            'Kasus keamanan diperbarui.',
            $before,
            $case->fresh()->toListArray()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kasus keamanan berhasil diperbarui',
            'data' => $this->loadSecurityCaseDetail($case)->toDetailArray(),
        ]);
    }

    public function resolveKelasSecurityCase($id, AttendanceSecurityCase $case, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'resolution' => 'required|in:confirmed_violation,false_positive,student_guided,device_fixed,parent_notified,followed_up',
            'staff_notes' => 'nullable|string|max:10000',
            'student_statement' => 'nullable|string|max:10000',
            'status' => 'nullable|in:resolved,escalated',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi penyelesaian kasus keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $this->assertSecurityCaseBelongsToKelas($case, $kelas);

        $before = $case->toListArray();
        $case->status = $request->input('status', 'resolved');
        $case->resolution = $request->input('resolution');
        $case->resolved_at = now();
        $case->resolved_by = $user->id;

        if ($request->filled('staff_notes')) {
            $case->staff_notes = $request->input('staff_notes');
        }

        if ($request->filled('student_statement')) {
            $case->student_statement = $request->input('student_statement');
        }

        $case->save();
        $this->recordSecurityCaseActivity(
            $case,
            $request,
            'case_resolved',
            'Kasus keamanan diselesaikan.',
            $before,
            $case->fresh()->toListArray()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kasus keamanan berhasil diselesaikan',
            'data' => $this->loadSecurityCaseDetail($case)->toDetailArray(),
        ]);
    }

    public function reopenKelasSecurityCase($id, AttendanceSecurityCase $case, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $this->assertSecurityCaseBelongsToKelas($case, $kelas);

        $before = $case->toListArray();
        $case->status = 'reopened';
        $case->resolved_at = null;
        $case->resolved_by = null;
        $case->save();

        $this->recordSecurityCaseActivity(
            $case,
            $request,
            'case_reopened',
            'Kasus keamanan dibuka ulang.',
            $before,
            $case->fresh()->toListArray()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Kasus keamanan dibuka ulang',
            'data' => $this->loadSecurityCaseDetail($case)->toDetailArray(),
        ]);
    }

    public function addKelasSecurityCaseNote($id, AttendanceSecurityCase $case, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'activity_type' => 'nullable|in:note,parent_contact,student_statement,staff_follow_up',
            'description' => 'required|string|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi catatan kasus keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $this->assertSecurityCaseBelongsToKelas($case, $kelas);

        $activityType = $request->input('activity_type', 'note');
        if ($activityType === 'student_statement') {
            $case->student_statement = trim((string) $request->input('description'));
            $case->save();
        } elseif ($activityType === 'staff_follow_up') {
            $case->staff_notes = trim((string) $request->input('description'));
            $case->save();
        }

        $this->recordSecurityCaseActivity(
            $case,
            $request,
            $activityType,
            $request->input('description')
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Catatan kasus keamanan berhasil ditambahkan',
            'data' => $this->loadSecurityCaseDetail($case)->toDetailArray(),
        ]);
    }

    public function uploadKelasSecurityCaseEvidence($id, AttendanceSecurityCase $case, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'evidence_type' => 'nullable|in:system_snapshot,screenshot,student_statement,parent_confirmation,device_check,other',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:5000',
            'file' => 'nullable|file|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi bukti kasus keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelas = $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);
        $this->assertSecurityCaseBelongsToKelas($case, $kelas);

        $file = $request->file('file');
        $path = null;
        $disk = null;
        $checksum = null;

        if ($file) {
            $disk = 'local';
            $path = $file->store("attendance-security-cases/{$case->id}", $disk);
            $checksum = hash_file('sha256', $file->getRealPath());
        }

        $evidence = AttendanceSecurityCaseEvidence::query()->create([
            'case_id' => $case->id,
            'uploaded_by' => $user->id,
            'evidence_type' => $request->input('evidence_type', 'other'),
            'title' => $request->input('title'),
            'description' => $request->input('description'),
            'file_disk' => $disk,
            'file_path' => $path,
            'file_original_name' => $file?->getClientOriginalName(),
            'file_mime_type' => $file?->getClientMimeType() ?: $file?->getMimeType(),
            'file_size_bytes' => $file?->getSize(),
            'checksum_sha256' => $checksum,
            'metadata' => [
                'uploaded_at' => now()->toIso8601String(),
            ],
        ]);

        $this->recordSecurityCaseActivity(
            $case,
            $request,
            'evidence_added',
            'Bukti kasus keamanan ditambahkan.',
            null,
            $evidence->toArrayPayload()
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Bukti kasus keamanan berhasil ditambahkan',
            'data' => $this->loadSecurityCaseDetail($case)->toDetailArray(),
        ], 201);
    }

    // Get izin yang perlu disetujui
    public function getKelasIzin($id, Request $request)
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return response()->json([
                'message' => 'User tidak ditemukan',
            ], 401);
        }

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $this->resolveAccessibleKelas($user, (int) $id, $activeTahunAjaranId);

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:pending,approved,rejected',
            'jenis_izin' => 'nullable|string|max:100',
            'search' => 'nullable|string|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi filter izin kelas gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = Izin::with(['user' => function($q) {
                $q->select('id', 'nama_lengkap', 'nisn');
            }])
            ->where('kelas_id', $id)
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('jenis_izin'), fn($q) => $q->where('jenis_izin', $request->input('jenis_izin')))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('created_at', '<=', $request->input('date_to')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->input('search'));
                if ($search === '') {
                    return;
                }

                $q->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('alasan', 'like', "%{$search}%")
                        ->orWhere('keterangan', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery
                                ->where('nama_lengkap', 'like', "%{$search}%")
                                ->orWhere('nisn', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('created_at', 'desc');

        // Backward compatible: response lama tetap array jika pagination tidak diminta.
        if (!$request->filled('per_page') && !$request->filled('page')) {
            return response()->json($query->get());
        }

        $perPage = (int) $request->input('per_page', 15);
        $izin = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $izin->items(),
            'meta' => [
                'current_page' => (int) $izin->currentPage(),
                'last_page' => (int) $izin->lastPage(),
                'per_page' => (int) $izin->perPage(),
                'total' => (int) $izin->total(),
                'from' => (int) ($izin->firstItem() ?? 0),
                'to' => (int) ($izin->lastItem() ?? 0),
            ],
        ]);
    }

    private function makeSecurityMonitoringFilterValidator(Request $request, bool $includePagination = true)
    {
        $rules = [
            'category' => 'nullable|string|max:50',
            'event_key' => 'nullable|string|max:100',
            'issue_key' => 'nullable|string|max:100',
            'severity' => 'nullable|in:low,medium,high,critical',
            'status' => 'nullable|in:blocked,flagged,allowed',
            'stage' => 'nullable|in:attendance_precheck,attendance_submit',
            'attempt_type' => 'nullable|in:masuk,pulang',
            'source' => 'nullable|in:attendance_precheck,attendance_submit',
            'validation_status' => 'nullable|in:valid,warning',
            'flag_key' => 'nullable|string|max:100',
            'student_scope' => 'nullable|in:needs_case,in_progress,done,all',
            'user_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ];

        if ($includePagination) {
            $rules['per_page'] = 'nullable|integer|min:1|max:100';
            $rules['page'] = 'nullable|integer|min:1';
        }

        return Validator::make($request->all(), $rules);
    }

    private function buildKelasSecurityEventsQuery(Kelas $kelas, Request $request)
    {
        $query = AttendanceSecurityEvent::query()
            ->with([
                'user:id,nama_lengkap,username,nis,nisn',
                'kelas:id,nama_kelas,jurusan,tingkat_id',
                'kelas.tingkat:id,nama',
            ])
            ->where('kelas_id', $kelas->id);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('event_key')) {
            $query->where('event_key', $request->input('event_key'));
        }

        $issueKey = $request->filled('issue_key')
            ? $request->input('issue_key')
            : ($request->filled('flag_key') ? $request->input('flag_key') : null);

        if ($issueKey) {
            $query->whereIssueKey($issueKey);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } elseif ($request->filled('validation_status')) {
            if ($request->input('validation_status') === 'warning') {
                $query->whereIn('status', ['blocked', 'flagged']);
            } else {
                $query->where('status', 'allowed');
            }
        }

        $stage = $request->filled('stage')
            ? $request->input('stage')
            : ($request->filled('source') ? $request->input('source') : null);

        if ($stage) {
            $query->whereStage($stage);
        }

        if ($request->filled('attempt_type')) {
            $query->where('attempt_type', $request->input('attempt_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return $query;
    }

    private function buildSecurityStudentRows(Collection $events, Collection $assessments, Collection $cases): array
    {
        $userIds = $events->pluck('user_id')
            ->merge($assessments->pluck('user_id'))
            ->merge($cases->pluck('user_id'))
            ->filter()
            ->unique()
            ->values();

        $rows = $userIds->map(function ($userId) use ($events, $assessments, $cases): array {
            $studentEvents = $events->where('user_id', $userId)->values();
            $studentAssessments = $assessments->where('user_id', $userId)->values();
            $studentCases = $cases->where('user_id', $userId)->values();

            /** @var AttendanceSecurityEvent|null $latestEvent */
            $latestEvent = $studentEvents
                ->sortByDesc(fn(AttendanceSecurityEvent $event): int => $this->modelActivityTimestamp($event))
                ->first();
            /** @var AttendanceFraudAssessment|null $latestAssessment */
            $latestAssessment = $studentAssessments
                ->sortByDesc(fn(AttendanceFraudAssessment $assessment): int => $this->modelActivityTimestamp($assessment))
                ->first();
            /** @var AttendanceSecurityCase|null $latestCase */
            $latestCase = $studentCases
                ->sortByDesc(fn(AttendanceSecurityCase $case): int => $this->modelActivityTimestamp($case))
                ->first();
            /** @var AttendanceSecurityCase|null $latestOpenCase */
            $latestOpenCase = $studentCases
                ->filter(static fn(AttendanceSecurityCase $case): bool => !$case->isClosed())
                ->sortByDesc(fn(AttendanceSecurityCase $case): int => $this->modelActivityTimestamp($case))
                ->first();
            /** @var AttendanceSecurityCase|null $latestClosedCase */
            $latestClosedCase = $studentCases
                ->filter(static fn(AttendanceSecurityCase $case): bool => $case->isClosed())
                ->sortByDesc(fn(AttendanceSecurityCase $case): int => $this->caseResolutionTimestamp($case))
                ->first();

            $student = $this->resolveMonitoringStudentPayload($latestEvent, $latestAssessment, $latestCase);
            $warningAssessments = $studentAssessments
                ->filter(static fn(AttendanceFraudAssessment $assessment): bool => $assessment->toMonitoringArray()['has_warning'] === true)
                ->count();
            $warningEvents = $studentEvents
                ->filter(static fn(AttendanceSecurityEvent $event): bool => in_array((string) $event->status, ['blocked', 'flagged'], true))
                ->count();
            $openCases = $studentCases
                ->filter(static fn(AttendanceSecurityCase $case): bool => !$case->isClosed())
                ->count();
            $latestTimestamp = max(
                $latestEvent ? $this->modelActivityTimestamp($latestEvent) : 0,
                $latestAssessment ? $this->modelActivityTimestamp($latestAssessment) : 0,
                $latestCase ? $this->modelActivityTimestamp($latestCase) : 0
            );

            $topIssues = $this->buildStudentIssueSummary($studentEvents, $studentAssessments);
            $totalWarnings = $warningEvents + $warningAssessments;
            $latestRawTimestamp = max(
                $latestEvent ? $this->modelActivityTimestamp($latestEvent) : 0,
                $latestAssessment ? $this->modelActivityTimestamp($latestAssessment) : 0
            );
            $resolvedCases = $studentCases->filter(static fn(AttendanceSecurityCase $case): bool => $case->isClosed())->count();
            $caseSequence = $resolvedCases + ($openCases > 0 ? 0 : 1);
            $operation = $this->resolveSecurityStudentOperationalState(
                $openCases,
                $resolvedCases,
                $totalWarnings,
                $latestRawTimestamp,
                $latestClosedCase
            );
            $caseComparison = $this->buildSecurityStudentCaseComparison(
                $topIssues,
                $latestRawTimestamp,
                $latestOpenCase,
                $latestClosedCase
            );
            $violationSequence = match ($operation['status']) {
                'done' => max(1, $resolvedCases),
                'in_progress' => max(1, $resolvedCases + 1),
                default => max(1, $caseSequence),
            };

            return [
                'user_id' => (int) $userId,
                'student' => $student,
                'operational_status' => $operation['status'],
                'operational_status_label' => $operation['label'],
                'operational_status_description' => $operation['description'],
                'violation_sequence' => $violationSequence,
                'violation_sequence_label' => $this->buildViolationSequenceLabel($operation['status'], $violationSequence),
                'security_events_count' => $studentEvents->count(),
                'fraud_assessments_count' => $studentAssessments->count(),
                'warning_events_count' => $warningEvents,
                'warning_assessments_count' => $warningAssessments,
                'total_warnings' => $totalWarnings,
                'blocked_events' => $studentEvents->where('status', 'blocked')->count(),
                'flagged_events' => $studentEvents->where('status', 'flagged')->count(),
                'mock_location_events' => $studentEvents
                    ->filter(static fn(AttendanceSecurityEvent $event): bool => $event->hasIssueKey('mock_location_detected'))
                    ->count(),
                'device_events' => $studentEvents
                    ->filter(fn(AttendanceSecurityEvent $event): bool => $this->isDeviceOrAppIntegrityEvent($event))
                    ->count(),
                'masuk_events' => $studentEvents->where('attempt_type', 'masuk')->count()
                    + $studentAssessments->where('attempt_type', 'masuk')->count(),
                'pulang_events' => $studentEvents->where('attempt_type', 'pulang')->count()
                    + $studentAssessments->where('attempt_type', 'pulang')->count(),
                'open_cases' => $openCases,
                'resolved_cases' => $resolvedCases,
                'latest_case' => $latestCase ? $latestCase->toListArray() : null,
                'active_case' => $latestOpenCase ? $latestOpenCase->toListArray() : null,
                'previous_case' => $latestClosedCase ? $latestClosedCase->toListArray() : null,
                'case_comparison' => $caseComparison,
                'latest_raw_activity_at' => $latestRawTimestamp > 0 ? Carbon::createFromTimestamp($latestRawTimestamp)->toIso8601String() : null,
                'latest_activity_at' => $latestTimestamp > 0 ? Carbon::createFromTimestamp($latestTimestamp)->toIso8601String() : null,
                'last_event_label' => $latestEvent ? AttendanceSecurityEvent::labelForEventKey((string) $latestEvent->event_key) : null,
                'top_issues' => $topIssues,
                'recommendation' => $studentEvents->isNotEmpty()
                    ? $this->buildSecurityFollowUpRecommendation($studentEvents)
                    : ($latestAssessment?->recommended_action ?: 'Tinjau riwayat assessment dan catat hasil klarifikasi siswa.'),
                'needs_follow_up' => in_array($operation['status'], ['needs_case', 'needs_reopen', 'in_progress'], true),
                '_sort_score' => ($operation['sort_weight'] * 1000000000000) + ($totalWarnings * 1000000) + $latestTimestamp,
            ];
        })
            ->sortByDesc('_sort_score')
            ->map(static function (array $row): array {
                unset($row['_sort_score']);

                return $row;
            })
            ->values()
            ->all();

        return $rows;
    }

    private function filterSecurityStudentRowsByScope(array $rows, string $scope): array
    {
        return collect($rows)
            ->filter(static function (array $row) use ($scope): bool {
                $status = (string) ($row['operational_status'] ?? 'none');

                return match ($scope) {
                    'needs_case' => in_array($status, ['needs_case', 'needs_reopen'], true),
                    'in_progress' => $status === 'in_progress',
                    'done' => $status === 'done',
                    default => true,
                };
            })
            ->values()
            ->all();
    }

    private function buildSecurityStudentSummaryPayload(array $rows): array
    {
        $collection = collect($rows);
        $needsCaseStatuses = ['needs_case', 'needs_reopen'];

        return [
            'total_students' => $collection->count(),
            'students_need_follow_up' => $collection
                ->filter(static fn(array $row): bool => in_array((string) ($row['operational_status'] ?? ''), $needsCaseStatuses, true))
                ->count(),
            'students_with_open_cases' => $collection->where('open_cases', '>', 0)->count(),
            'students_done' => $collection->where('operational_status', 'done')->count(),
            'students_with_repeat_violation' => $collection->where('operational_status', 'needs_reopen')->count(),
            'total_open_cases' => $collection->sum('open_cases'),
            'total_resolved_cases' => $collection->sum('resolved_cases'),
        ];
    }

    private function resolveSecurityStudentOperationalState(
        int $openCases,
        int $resolvedCases,
        int $totalWarnings,
        int $latestRawTimestamp,
        ?AttendanceSecurityCase $latestClosedCase
    ): array {
        if ($openCases > 0) {
            return [
                'status' => 'in_progress',
                'label' => 'Dalam Penanganan',
                'description' => 'Siswa sudah memiliki kasus aktif. Warning berikutnya menjadi tambahan bukti pada kasus aktif.',
                'sort_weight' => 2,
            ];
        }

        if ($resolvedCases > 0) {
            $previousClosedAt = $latestClosedCase ? $this->caseResolutionTimestamp($latestClosedCase) : 0;
            if ($totalWarnings > 0 && $latestRawTimestamp > 0 && $previousClosedAt > 0 && $latestRawTimestamp > $previousClosedAt) {
                return [
                    'status' => 'needs_reopen',
                    'label' => 'Pelanggaran Lanjutan',
                    'description' => 'Ada warning baru setelah kasus terakhir selesai. Perlu dibuat kasus lanjutan atau dibuka ulang sesuai kebijakan sekolah.',
                    'sort_weight' => 4,
                ];
            }

            return [
                'status' => 'done',
                'label' => 'Selesai',
                'description' => 'Kasus sudah ditindaklanjuti dan belum ada warning baru setelah penyelesaian terakhir.',
                'sort_weight' => 1,
            ];
        }

        if ($totalWarnings > 0) {
            return [
                'status' => 'needs_case',
                'label' => 'Perlu Kasus',
                'description' => 'Ada warning keamanan yang belum dibuatkan kasus tindak lanjut.',
                'sort_weight' => 3,
            ];
        }

        return [
            'status' => 'none',
            'label' => 'Tidak Ada Issue Aktif',
            'description' => 'Tidak ada warning atau kasus pada filter ini.',
            'sort_weight' => 0,
        ];
    }

    private function buildViolationSequenceLabel(string $status, int $sequence): string
    {
        if ($status === 'needs_reopen') {
            return 'Pelanggaran Lanjutan #' . $sequence;
        }

        if ($status === 'in_progress' && $sequence > 1) {
            return 'Dalam Penanganan Lanjutan #' . $sequence;
        }

        if ($status === 'done') {
            return 'Selesai #' . $sequence;
        }

        return 'Pelanggaran #' . $sequence;
    }

    private function buildSecurityStudentCaseComparison(
        array $topIssues,
        int $latestRawTimestamp,
        ?AttendanceSecurityCase $activeCase,
        ?AttendanceSecurityCase $previousCase
    ): array {
        $currentIssues = collect($topIssues)
            ->map(static fn(array $issue): array => [
                'key' => (string) ($issue['key'] ?? ''),
                'label' => (string) ($issue['label'] ?? $issue['key'] ?? ''),
                'total' => (int) ($issue['total'] ?? 0),
            ])
            ->filter(static fn(array $issue): bool => $issue['key'] !== '')
            ->values();

        $previousIssues = $previousCase
            ? collect($this->extractSecurityCaseIssueRows($previousCase))
            : collect();
        $currentKeys = $currentIssues->pluck('key')->values()->all();
        $previousKeys = $previousIssues->pluck('key')->values()->all();
        $previousTimestamp = $previousCase ? $this->caseResolutionTimestamp($previousCase) : 0;

        return [
            'active_case' => $activeCase ? $activeCase->toListArray() : null,
            'previous_case' => $previousCase ? $previousCase->toListArray() : null,
            'current_issues' => $currentIssues->all(),
            'previous_issues' => $previousIssues->all(),
            'repeated_issue_keys' => array_values(array_intersect($currentKeys, $previousKeys)),
            'new_issue_keys' => array_values(array_diff($currentKeys, $previousKeys)),
            'has_new_activity_after_previous_case' => $previousTimestamp > 0
                && $latestRawTimestamp > $previousTimestamp,
            'days_since_previous_case_resolved' => $previousTimestamp > 0 && $latestRawTimestamp > 0
                ? Carbon::createFromTimestamp($previousTimestamp)->diffInDays(Carbon::createFromTimestamp($latestRawTimestamp))
                : null,
            'previous_case_resolved_at' => $previousTimestamp > 0
                ? Carbon::createFromTimestamp($previousTimestamp)->toIso8601String()
                : null,
        ];
    }

    private function extractSecurityCaseIssueRows(AttendanceSecurityCase $case): array
    {
        if (!$case->relationLoaded('items')) {
            return [];
        }

        return $case->items
            ->flatMap(static function (AttendanceSecurityCaseItem $item): array {
                $snapshot = is_array($item->item_snapshot) ? $item->item_snapshot : [];
                $payload = is_array($snapshot['payload'] ?? null) ? $snapshot['payload'] : [];
                $rows = [];

                foreach (is_array($payload['issues'] ?? null) ? $payload['issues'] : [] as $issue) {
                    if (!is_array($issue)) {
                        continue;
                    }

                    $key = trim((string) ($issue['event_key'] ?? ''));
                    if ($key !== '') {
                        $rows[] = [
                            'key' => $key,
                            'label' => (string) ($issue['label'] ?? $key),
                        ];
                    }
                }

                $flags = is_array($payload['flags'] ?? null)
                    ? $payload['flags']
                    : (is_array($payload['fraud_flags'] ?? null) ? $payload['fraud_flags'] : []);
                foreach ($flags as $flag) {
                    if (!is_array($flag)) {
                        continue;
                    }

                    $key = trim((string) ($flag['flag_key'] ?? ''));
                    if ($key !== '') {
                        $rows[] = [
                            'key' => $key,
                            'label' => (string) ($flag['label'] ?? $key),
                        ];
                    }
                }

                $fallbackKey = trim((string) ($payload['event_key'] ?? $snapshot['label'] ?? ''));
                if ($rows === [] && $fallbackKey !== '') {
                    $rows[] = [
                        'key' => $fallbackKey,
                        'label' => (string) ($snapshot['label'] ?? $fallbackKey),
                    ];
                }

                return $rows;
            })
            ->unique(static fn(array $row): string => $row['key'])
            ->values()
            ->all();
    }

    private function buildSecurityStudentTimeline(Collection $events, Collection $assessments, Collection $cases): array
    {
        return $events
            ->map(fn(AttendanceSecurityEvent $event): array => [
                'type' => 'security_event',
                'type_label' => 'Security Event',
                'occurred_at' => $event->updated_at?->toIso8601String() ?? $event->created_at?->toIso8601String(),
                'sort_at' => $this->modelActivityTimestamp($event),
                'data' => $event->toReportArray(),
            ])
            ->merge($assessments->map(fn(AttendanceFraudAssessment $assessment): array => [
                'type' => 'fraud_assessment',
                'type_label' => 'Fraud Assessment',
                'occurred_at' => $assessment->updated_at?->toIso8601String() ?? $assessment->created_at?->toIso8601String(),
                'sort_at' => $this->modelActivityTimestamp($assessment),
                'data' => $assessment->toMonitoringArray(),
            ]))
            ->merge($cases->map(fn(AttendanceSecurityCase $case): array => [
                'type' => 'security_case',
                'type_label' => 'Kasus Tindak Lanjut',
                'occurred_at' => $case->updated_at?->toIso8601String() ?? $case->created_at?->toIso8601String(),
                'sort_at' => $this->modelActivityTimestamp($case),
                'data' => $case->toListArray(),
            ]))
            ->sortByDesc('sort_at')
            ->map(static function (array $row): array {
                unset($row['sort_at']);

                return $row;
            })
            ->values()
            ->all();
    }

    private function buildStudentIssueSummary(Collection $events, Collection $assessments): array
    {
        $eventIssues = $events->flatMap(static function (AttendanceSecurityEvent $event): array {
            return array_map(static function (array $issue): array {
                return [
                    'key' => (string) ($issue['event_key'] ?? 'unknown_security_event'),
                    'label' => (string) ($issue['label'] ?? 'Insiden keamanan absensi'),
                ];
            }, $event->issueRows());
        });

        $fraudIssues = $assessments->flatMap(static function (AttendanceFraudAssessment $assessment): array {
            return $assessment->flags->map(static fn($flag): array => [
                'key' => (string) ($flag->flag_key ?? 'fraud_warning'),
                'label' => (string) ($flag->label ?? $flag->flag_key ?? 'Fraud warning'),
            ])->all();
        });

        return $eventIssues
            ->merge($fraudIssues)
            ->groupBy('key')
            ->map(static function (Collection $group, string $key): array {
                $first = $group->first();

                return [
                    'key' => $key,
                    'label' => $first['label'] ?? $key,
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->take(5)
            ->all();
    }

    private function resolveMonitoringStudentPayload(
        ?AttendanceSecurityEvent $event,
        ?AttendanceFraudAssessment $assessment,
        ?AttendanceSecurityCase $case
    ): array {
        if ($event) {
            return $event->toReportArray()['student'];
        }

        if ($assessment) {
            return $assessment->toMonitoringArray()['student'];
        }

        $identifier = $case?->user?->nisn ?: $case?->user?->nis ?: $case?->user?->username;

        return [
            'user_id' => $case?->user_id ? (int) $case->user_id : null,
            'name' => $case?->user?->nama_lengkap,
            'identifier' => $identifier,
        ];
    }

    private function paginateArrayRows(array $rows, int $perPage, int $page): array
    {
        $perPage = max(1, min(100, $perPage));
        $total = count($rows);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($page, $lastPage));
        $offset = ($page - 1) * $perPage;
        $items = array_slice($rows, $offset, $perPage);

        return [
            'data' => array_values($items),
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'total' => $total,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => $total > 0 ? $offset + count($items) : 0,
            ],
        ];
    }

    private function buildKelasSecurityCasesQuery(Kelas $kelas, Request $request)
    {
        return AttendanceSecurityCase::query()
            ->with([
                'user:id,nama_lengkap,username,nis,nisn',
                'kelas:id,nama_kelas,jurusan,tingkat_id',
                'opener:id,nama_lengkap',
                'assignee:id,nama_lengkap',
                'resolver:id,nama_lengkap',
            ])
            ->withCount(['items', 'evidence'])
            ->where('kelas_id', $kelas->id)
            ->when($request->filled('status'), fn($query) => $query->where('status', $request->input('status')))
            ->when(!$request->filled('status') && $request->input('case_scope') === 'active', function ($query) {
                $query->whereIn('status', ['open', 'reopened']);
            })
            ->when(!$request->filled('status') && $request->input('case_scope') === 'archive', function ($query) {
                $query->whereIn('status', ['resolved', 'escalated']);
            })
            ->when($request->filled('priority'), fn($query) => $query->where('priority', $request->input('priority')))
            ->when($request->filled('user_id'), fn($query) => $query->where('user_id', (int) $request->input('user_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                if ($search === '') {
                    return;
                }

                $query->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('case_number', 'like', "%{$search}%")
                        ->orWhere('summary', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery
                                ->where('nama_lengkap', 'like', "%{$search}%")
                                ->orWhere('nisn', 'like', "%{$search}%")
                                ->orWhere('nis', 'like', "%{$search}%");
                        });
                });
            });
    }

    private function makeSecurityCasePayloadValidator(Request $request)
    {
        return Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'priority' => 'nullable|in:low,medium,high,critical',
            'assigned_to' => 'nullable|integer|exists:users,id',
            'summary' => 'nullable|string|max:2000',
            'student_statement' => 'nullable|string|max:10000',
            'staff_notes' => 'nullable|string|max:10000',
            'items' => 'nullable|array|max:30',
            'items.*.item_type' => 'required_with:items|in:fraud_assessment,security_event,attendance',
            'items.*.item_id' => 'required_with:items|integer|min:1',
        ]);
    }

    private function normalizeSecurityCaseItems(Kelas $kelas, array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $itemType = (string) ($item['item_type'] ?? '');
            $itemId = (int) ($item['item_id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }

            $snapshot = $this->resolveSecurityCaseItemSnapshot($kelas, $itemType, $itemId);
            if ($snapshot === null) {
                continue;
            }

            $normalized[] = [
                'item_type' => $itemType,
                'item_id' => $itemId,
                'item_snapshot' => $snapshot,
            ];
        }

        return collect($normalized)
            ->unique(static fn(array $row): string => $row['item_type'] . ':' . $row['item_id'])
            ->values()
            ->all();
    }

    private function buildDefaultSecurityCaseItems(Kelas $kelas, int $userId, Request $request): array
    {
        $events = $this->buildKelasSecurityEventsQuery($kelas, $request)
            ->where('user_id', $userId)
            ->whereIn('status', ['blocked', 'flagged'])
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $assessments = $this->buildKelasFraudAssessmentsQuery($kelas, $request)
            ->where('user_id', $userId)
            ->where(function ($query) {
                $query
                    ->where('validation_status', '!=', 'valid')
                    ->orWhere('fraud_flags_count', '>', 0);
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return $events
            ->map(fn(AttendanceSecurityEvent $event): array => [
                'item_type' => 'security_event',
                'item_id' => (int) $event->id,
                'item_snapshot' => $this->buildSecurityEventSnapshot($event),
            ])
            ->merge($assessments->map(fn(AttendanceFraudAssessment $assessment): array => [
                'item_type' => 'fraud_assessment',
                'item_id' => (int) $assessment->id,
                'item_snapshot' => $this->buildFraudAssessmentSnapshot($assessment),
            ]))
            ->unique(static fn(array $row): string => $row['item_type'] . ':' . $row['item_id'])
            ->values()
            ->all();
    }

    private function resolveSecurityCaseItemSnapshot(Kelas $kelas, string $itemType, int $itemId): ?array
    {
        if ($itemType === 'security_event') {
            $event = AttendanceSecurityEvent::query()
                ->with(['user:id,nama_lengkap,username,nis,nisn', 'kelas:id,nama_kelas,jurusan,tingkat_id'])
                ->where('kelas_id', $kelas->id)
                ->find($itemId);

            return $event ? $this->buildSecurityEventSnapshot($event) : null;
        }

        if ($itemType === 'fraud_assessment') {
            $assessment = AttendanceFraudAssessment::query()
                ->with([
                    'user:id,nama_lengkap,username,nis,nisn',
                    'kelas:id,nama_kelas,jurusan,tingkat_id',
                    'kelas.tingkat:id,nama',
                    'flags',
                ])
                ->where('kelas_id', $kelas->id)
                ->find($itemId);

            return $assessment ? $this->buildFraudAssessmentSnapshot($assessment) : null;
        }

        if ($itemType === 'attendance') {
            $attendance = Absensi::query()
                ->with(['user:id,nama_lengkap,username,nis,nisn'])
                ->where('kelas_id', $kelas->id)
                ->find($itemId);

            return $attendance ? $this->buildAttendanceSnapshot($attendance) : null;
        }

        return null;
    }

    private function buildSecurityEventSnapshot(AttendanceSecurityEvent $event): array
    {
        $payload = $event->toReportArray();

        return [
            'type' => 'security_event',
            'id' => (int) $event->id,
            'user_id' => $event->user_id ? (int) $event->user_id : null,
            'label' => $payload['event_label'] ?? $event->event_key,
            'attempt_type' => $event->attempt_type,
            'occurred_at' => $payload['last_seen_at'] ?? $payload['created_at'] ?? null,
            'payload' => $payload,
        ];
    }

    private function buildFraudAssessmentSnapshot(AttendanceFraudAssessment $assessment): array
    {
        $payload = $assessment->toMonitoringArray();

        return [
            'type' => 'fraud_assessment',
            'id' => (int) $assessment->id,
            'user_id' => $assessment->user_id ? (int) $assessment->user_id : null,
            'label' => $payload['warning_summary'] ?? $payload['validation_status_label'] ?? 'Fraud assessment',
            'attempt_type' => $assessment->attempt_type,
            'occurred_at' => $payload['last_seen_at'] ?? $payload['created_at'] ?? null,
            'payload' => $payload,
        ];
    }

    private function buildAttendanceSnapshot(Absensi $attendance): array
    {
        return [
            'type' => 'attendance',
            'id' => (int) $attendance->id,
            'user_id' => $attendance->user_id ? (int) $attendance->user_id : null,
            'label' => 'Absensi ' . ($attendance->tanggal?->toDateString() ?? '-'),
            'attempt_type' => null,
            'occurred_at' => $attendance->updated_at?->toIso8601String() ?? $attendance->created_at?->toIso8601String(),
            'payload' => [
                'id' => (int) $attendance->id,
                'tanggal' => $attendance->tanggal?->toDateString(),
                'status' => $attendance->status,
                'jam_masuk' => $attendance->jam_masuk_format,
                'jam_pulang' => $attendance->jam_pulang_format,
                'validation_status' => $attendance->validation_status,
                'fraud_flags_count' => (int) ($attendance->fraud_flags_count ?? 0),
                'fraud_decision_reason' => $attendance->fraud_decision_reason,
                'student' => [
                    'user_id' => $attendance->user_id ? (int) $attendance->user_id : null,
                    'name' => $attendance->user?->nama_lengkap,
                    'identifier' => $attendance->user?->nisn ?: $attendance->user?->nis ?: $attendance->user?->username,
                ],
            ],
        ];
    }

    private function resolveUserIdFromSecurityCaseItems(array $items): ?int
    {
        foreach ($items as $item) {
            $userId = $item['item_snapshot']['user_id'] ?? null;
            if ($userId) {
                return (int) $userId;
            }
        }

        return null;
    }

    private function buildDefaultCaseSummary(array $items): string
    {
        $labels = collect($items)
            ->pluck('item_snapshot.label')
            ->filter()
            ->take(3)
            ->values()
            ->all();

        if ($labels === []) {
            return 'Tindak lanjut keamanan presensi siswa.';
        }

        return 'Tindak lanjut: ' . implode(', ', $labels);
    }

    private function makeSecurityCaseNumber(): string
    {
        do {
            $number = 'SEC-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));
        } while (AttendanceSecurityCase::query()->where('case_number', $number)->exists());

        return $number;
    }

    private function recordSecurityCaseActivity(
        AttendanceSecurityCase $case,
        Request $request,
        string $activityType,
        ?string $description = null,
        ?array $before = null,
        ?array $after = null
    ): void {
        AttendanceSecurityCaseActivity::query()->create([
            'case_id' => $case->id,
            'actor_id' => Auth::id(),
            'activity_type' => $activityType,
            'description' => $description,
            'before_state' => $before,
            'after_state' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
            'metadata' => [
                'captured_at' => now()->toIso8601String(),
            ],
        ]);
    }

    private function loadSecurityCaseDetail(AttendanceSecurityCase $case): AttendanceSecurityCase
    {
        return AttendanceSecurityCase::query()
            ->with([
                'user:id,nama_lengkap,username,nis,nisn',
                'kelas:id,nama_kelas,jurusan,tingkat_id',
                'items',
                'evidence.uploader:id,nama_lengkap',
                'activities.actor:id,nama_lengkap',
                'opener:id,nama_lengkap',
                'assignee:id,nama_lengkap',
                'resolver:id,nama_lengkap',
            ])
            ->withCount(['items', 'evidence'])
            ->findOrFail($case->id);
    }

    private function assertSecurityCaseBelongsToKelas(AttendanceSecurityCase $case, Kelas $kelas): void
    {
        if ((int) $case->kelas_id !== (int) $kelas->id) {
            abort(404, 'Kasus keamanan tidak ditemukan pada kelas ini');
        }
    }

    private function modelActivityTimestamp($model): int
    {
        return $model->updated_at?->getTimestamp()
            ?? $model->created_at?->getTimestamp()
            ?? 0;
    }

    private function caseResolutionTimestamp(AttendanceSecurityCase $case): int
    {
        return $case->resolved_at?->getTimestamp()
            ?? $case->updated_at?->getTimestamp()
            ?? $case->created_at?->getTimestamp()
            ?? 0;
    }

    private function makeFraudAssessmentFilterValidator(Request $request, bool $includePagination = true)
    {
        $rules = [
            'source' => 'nullable|in:attendance_precheck,attendance_submit',
            'validation_status' => 'nullable|in:valid,warning',
            'attempt_type' => 'nullable|in:masuk,pulang',
            'flag_key' => 'nullable|string|max:100',
            'user_id' => 'nullable|integer|exists:users,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ];

        if ($includePagination) {
            $rules['per_page'] = 'nullable|integer|min:1|max:100';
        }

        return Validator::make($request->all(), $rules);
    }

    private function buildKelasFraudAssessmentsQuery(Kelas $kelas, Request $request)
    {
        $query = AttendanceFraudAssessment::query()
            ->with([
                'user:id,nama_lengkap,username,email,nis,nisn',
                'kelas:id,nama_kelas,jurusan,tingkat_id',
                'kelas.tingkat:id,nama',
                'flags',
            ])
            ->where('kelas_id', $kelas->id);

        $source = $request->filled('source')
            ? $request->input('source')
            : ($request->filled('stage') ? $request->input('stage') : null);

        if ($source) {
            $query->where('source', $source);
        }

        if ($request->filled('validation_status')) {
            if ($request->input('validation_status') === 'warning') {
                $query->where(function ($warningQuery) {
                    $warningQuery
                        ->where('validation_status', '!=', 'valid')
                        ->orWhere('fraud_flags_count', '>', 0);
                });
            } else {
                $query
                    ->where('validation_status', 'valid')
                    ->where('fraud_flags_count', 0);
            }
        } elseif ($request->filled('status')) {
            if ($request->input('status') === 'flagged') {
                $query->where(function ($warningQuery) {
                    $warningQuery
                        ->where('validation_status', '!=', 'valid')
                        ->orWhere('fraud_flags_count', '>', 0);
                });
            } elseif ($request->input('status') === 'blocked') {
                $query->where('is_blocking', true);
            } elseif ($request->input('status') === 'allowed') {
                $query
                    ->where('validation_status', 'valid')
                    ->where('fraud_flags_count', 0)
                    ->where(function ($allowedQuery) {
                        $allowedQuery
                            ->where('is_blocking', false)
                            ->orWhereNull('is_blocking');
                    });
            }
        }

        if ($request->filled('attempt_type')) {
            $query->where('attempt_type', $request->input('attempt_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        $flagKey = $request->filled('flag_key')
            ? $request->input('flag_key')
            : ($request->filled('issue_key') ? $request->input('issue_key') : null);

        if ($flagKey) {
            $query->whereHas('flags', static fn($flagQuery) => $flagQuery->where('flag_key', $flagKey));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return $query;
    }

    private function buildSecurityStudentSummaryRow(Collection $events): array
    {
        /** @var AttendanceSecurityEvent|null $latest */
        $latest = $events
            ->sortByDesc(static fn(AttendanceSecurityEvent $event): int => $event->updated_at?->getTimestamp()
                ?? $event->created_at?->getTimestamp()
                ?? 0)
            ->first();

        $student = $latest?->toReportArray()['student'] ?? [];
        $blockedEvents = $events->where('status', 'blocked')->count();
        $mockLocationEvents = $events
            ->filter(static fn(AttendanceSecurityEvent $event): bool => $event->hasIssueKey('mock_location_detected'))
            ->count();
        $deviceEvents = $events->filter(fn(AttendanceSecurityEvent $event): bool => $this->isDeviceOrAppIntegrityEvent($event))->count();
        $precheckEvents = $events->filter(fn(AttendanceSecurityEvent $event): bool => $this->resolveSecurityEventStage($event) === 'attendance_precheck')->count();
        $submitEvents = $events->filter(fn(AttendanceSecurityEvent $event): bool => $this->resolveSecurityEventStage($event) === 'attendance_submit')->count();

        return [
            'user_id' => $latest?->user_id ? (int) $latest->user_id : null,
            'student_name' => $student['name'] ?? null,
            'student_identifier' => $student['identifier'] ?? null,
            'total_events' => $events->count(),
            'blocked_events' => $blockedEvents,
            'mock_location_events' => $mockLocationEvents,
            'device_events' => $deviceEvents,
            'precheck_events' => $precheckEvents,
            'submit_events' => $submitEvents,
            'last_event_at' => $latest?->updated_at?->toIso8601String() ?? $latest?->created_at?->toIso8601String(),
            'last_event_label' => $latest ? AttendanceSecurityEvent::labelForEventKey((string) $latest->event_key) : null,
            'recommendation' => $this->buildSecurityFollowUpRecommendation($events),
        ];
    }

    private function buildSecurityFollowUpRecommendation(Collection $events): string
    {
        if ($events->filter(static fn(AttendanceSecurityEvent $event): bool => $event->hasIssueKey('mock_location_detected'))->count() > 0) {
            return 'Prioritaskan klarifikasi siswa dan cek Fake GPS, developer options, serta histori lokasi absensi.';
        }

        if ($events->filter(fn(AttendanceSecurityEvent $event): bool => $this->isDeviceOrAppIntegrityEvent($event))->count() > 0) {
            return 'Verifikasi perangkat siswa: binding device, clone app, root, debugging, signature, dan integritas aplikasi.';
        }

        if ($events->filter(fn(AttendanceSecurityEvent $event): bool => $this->resolveSecurityEventStage($event) === 'attendance_precheck')->count() > 0) {
            return 'Cek pola warning pra-cek. Jika berulang, minta siswa memperbaiki kondisi perangkat sebelum presensi.';
        }

        return 'Tinjau kronologi event bersama wali kelas dan cocokkan dengan jadwal serta lokasi sekolah.';
    }

    private function resolveSecurityEventStage(AttendanceSecurityEvent $event): string
    {
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        return (string) ($metadata['stage'] ?? 'attendance_submit');
    }

    private function isDeviceOrAppIntegrityEvent(AttendanceSecurityEvent $event): bool
    {
        $issueRows = $event->issueRows();
        $issueKeys = $event->issueKeys();
        $issueCategories = collect($issueRows)->pluck('category')->filter()->all();

        return collect($issueCategories)->intersect(['device_integrity', 'app_integrity'])->isNotEmpty()
            || collect($issueKeys)->contains(static function (string $eventKey): bool {
                return str_starts_with($eventKey, 'device_')
                    || in_array($eventKey, [
                        'developer_options_enabled',
                        'root_or_jailbreak_detected',
                        'adb_or_usb_debugging_enabled',
                        'emulator_detected',
                        'app_clone_detected',
                        'app_tampering_detected',
                        'instrumentation_detected',
                        'signature_mismatch_detected',
                        'magisk_risk_detected',
                        'suspicious_device_state_detected',
                    ], true);
            });
    }

    private function buildSecurityMonitoringConfigPayload(): array
    {
        return [
            'event_logging_enabled' => (bool) config('attendance.security.event_logging_enabled', true),
            'rollout_mode' => 'warning_mode',
            'rollout_mode_label' => AttendanceFraudAssessment::labelForRolloutMode('warning_mode'),
            'warn_user' => (bool) config('attendance.security.warn_user', true),
            'allow_submit_with_security_warnings' => true,
            'device_binding_enforced' => true,
            'warning_only' => true,
            'enforcement_label' => 'Warning dicatat, presensi tetap diproses. Hanya device binding akun siswa yang dapat memblokir presensi.',
        ];
    }

    private function buildFraudMonitoringConfigPayload(): array
    {
        return [
            'event_logging_enabled' => (bool) config('attendance.security.event_logging_enabled', true),
            'rollout_mode' => 'warning_mode',
            'rollout_mode_label' => AttendanceFraudAssessment::labelForRolloutMode('warning_mode'),
            'warn_user' => (bool) config('attendance.security.warn_user', true),
            'allow_submit_with_security_warnings' => true,
            'device_binding_enforced' => true,
            'warning_only' => true,
            'validation_statuses' => ['valid', 'warning'],
            'sources' => ['attendance_precheck', 'attendance_submit'],
            'enforcement_label' => 'Warning dicatat, presensi tetap diproses. Hanya device binding akun siswa yang dapat memblokir presensi.',
        ];
    }

    private function resolveActiveTahunAjaranId(): ?int
    {
        $activeId = TahunAjaran::query()
            ->where('status', TahunAjaran::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->value('id');

        return $activeId ? (int) $activeId : null;
    }

    private function buildAccessibleKelasQuery(User $user, ?int $activeTahunAjaranId)
    {
        $query = Kelas::query();

        if ($activeTahunAjaranId) {
            $query->where('tahun_ajaran_id', $activeTahunAjaranId);
        }

        if ($this->canMonitorAllClasses($user)) {
            return $query;
        }

        return $query->where('wali_kelas_id', $user->id);
    }

    private function resolveAccessibleKelas(User $user, int $kelasId, ?int $activeTahunAjaranId): Kelas
    {
        $kelas = Kelas::query()->findOrFail($kelasId);

        if ($activeTahunAjaranId && (int) $kelas->tahun_ajaran_id !== $activeTahunAjaranId) {
            abort(404, 'Kelas tidak ditemukan');
        }

        if ($this->canMonitorAllClasses($user)) {
            return $kelas;
        }

        if ((int) $kelas->wali_kelas_id !== (int) $user->id) {
            abort(403, 'Anda tidak memiliki akses ke kelas ini');
        }

        return $kelas;
    }

    private function canMonitorAllClasses(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::WAKASEK_KESISWAAN))
            || $user->hasRole(RoleNames::aliases(RoleNames::SUPER_ADMIN));
    }
}
