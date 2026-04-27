<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\BackupLog;
use App\Models\Izin;
use App\Models\JadwalMengajar;
use App\Models\Kelas;
use App\Models\LokasiGps;
use App\Models\Notification;
use App\Models\TahunAjaran;
use App\Models\WhatsappGateway;
use App\Services\AttendanceTimeService;
use App\Services\WhatsappGatewayClient;
use App\Support\RoleNames;
use App\Support\RoleAccessMatrix;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardController extends Controller
{
    /**
     * Request-scoped cache to avoid repeated location resolution for identical coordinates.
     *
     * @var array<string, ?string>
     */
    private array $resolvedLocationNameCache = [];

    public function __construct(
        private readonly AttendanceTimeService $attendanceTimeService
    ) {
    }

    public function stats(Request $request)
    {
        $authUser = $request->user();
        $user = $authUser instanceof User ? $authUser : null;
        $today = now()->setTimezone(config('app.timezone'))->startOfDay();
        $userRole = $this->resolveUserRole($user);
        $stats = $this->resolveRoleStats($userRole, $user, $today);

        return response()->json([
            'success' => true,
            'data' => $stats,
            'user_role' => $userRole,
            'meta' => $this->serverTimeMeta(),
        ]);
    }

    private function resolveRoleStats(string $userRole, ?User $user, Carbon $today): array
    {
        return match ($userRole) {
            RoleNames::SUPER_ADMIN => $this->buildSuperAdminStats($today),
            RoleNames::GURU,
            RoleNames::GURU_BK => $user instanceof User
                ? $this->buildGuruStats($user, $userRole, $today)
                : $this->buildDefaultStats($userRole, $today),
            RoleNames::WALI_KELAS => $user instanceof User
                ? $this->buildWaliKelasStats($user, $userRole, $today)
                : $this->buildDefaultStats($userRole, $today),
            RoleNames::SISWA => $user instanceof User
                ? $this->buildSiswaStats($user)
                : $this->buildDefaultStats($userRole, $today),
            RoleNames::WAKASEK_KESISWAAN => $this->buildWakasekKesiswaanStats($today),
            RoleNames::WAKASEK_KURIKULUM => $user instanceof User
                ? $this->buildWakasekKurikulumStats($user, $today)
                : $this->buildDefaultStats($userRole, $today),
            RoleNames::WAKASEK_HUMAS => $this->buildWakasekHumasStats($today),
            RoleNames::WAKASEK_SARPRAS => $this->resolveSarprasGpsStats($today),
            RoleNames::KEPALA_SEKOLAH => $this->buildKepalaSekolahStats($today),
            RoleNames::ADMIN => $this->buildAdminStats($today),
            default => $this->buildDefaultStats($userRole, $today),
        };
    }

    private function buildSuperAdminStats(Carbon $today): array
    {
        return [
            'totalUsers' => User::count(),
            'totalRoles' => Role::count(),
            'totalPermissions' => Permission::count(),
            'todayActivities' => Absensi::whereDate('created_at', $today)->count(),
        ];
    }

    private function buildGuruStats(User $user, string $userRole, Carbon $today): array
    {
        $teacherWorkload = $this->resolveTeacherWorkloadStats($user, $today);
        $teacherAttendance = $this->resolveTeacherStudentAttendanceStats($user, $userRole, $today);

        return [
            // KPI utama sesuai kebutuhan role Guru
            'totalTeachingHours' => $teacherWorkload['total_teaching_hours'],
            'todaySchedules' => $teacherWorkload['today_schedules'],
            'totalClasses' => $teacherWorkload['total_classes'],
            'totalStudentsTaught' => $teacherWorkload['total_students_taught'],

            // Tetap kirim agar kompatibel dengan client lama
            'studentPresentToday' => $teacherAttendance['present'],
            'studentTotalToday' => $teacherAttendance['total'],
            'studentNotCheckedInToday' => $teacherAttendance['not_checked_in'],
            'studentLateToday' => $teacherAttendance['late'],
            'studentAttendanceSummaryToday' => $teacherAttendance['summary'],
            'studentAttendanceRateToday' => $teacherAttendance['rate'],
            'attendanceRate' => $teacherAttendance['rate'],
        ];
    }

    private function buildWaliKelasStats(User $user, string $userRole, Carbon $today): array
    {
        $waliSchedule = $this->resolveTeacherScheduleStats($user, $userRole, $today);
        $waliAttendance = $this->resolveTeacherStudentAttendanceStats($user, $userRole, $today);
        $waliPendingApprovals = $this->resolvePendingApprovalsForTeacher($user, $userRole);

        return [
            // KPI utama sesuai kebutuhan role Wali Kelas
            'todaySchedules' => $waliSchedule['today_schedules'],
            'totalClasses' => $waliSchedule['total_classes'],
            'waliStudentAttendanceSummaryToday' => $waliAttendance['summary'],
            'waliStudentAttendanceRateToday' => $waliAttendance['rate'],
            'waliStudentPresentToday' => $waliAttendance['present'],
            'waliStudentTotalToday' => $waliAttendance['total'],
            'waliStudentNotCheckedInToday' => $waliAttendance['not_checked_in'],
            'waliPendingApprovals' => $waliPendingApprovals,

            // Kompatibilitas client lama
            'pendingApprovals' => $waliPendingApprovals,
            'studentPresentToday' => $waliAttendance['present'],
            'studentTotalToday' => $waliAttendance['total'],
            'studentNotCheckedInToday' => $waliAttendance['not_checked_in'],
            'studentLateToday' => $waliAttendance['late'],
            'studentAttendanceSummaryToday' => $waliAttendance['summary'],
            'studentAttendanceRateToday' => $waliAttendance['rate'],
            'attendanceRate' => $waliAttendance['rate'],
        ];
    }

    private function buildSiswaStats(User $user): array
    {
        return [
            'attendanceCount' => Absensi::where('user_id', $user->id)
                ->whereIn('status', ['hadir', 'terlambat'])
                ->count(),
            'leaveCount' => Izin::where('user_id', $user->id)->count(),
            'attendanceRate' => $this->calculateAttendanceRate($user),
            'lateCount' => Absensi::where('user_id', $user->id)
                ->where('status', 'terlambat')
                ->count(),
        ];
    }

    private function buildWakasekKesiswaanStats(Carbon $today): array
    {
        $schoolSchedule = $this->resolveSchoolScheduleStats($today);
        $studentSnapshot = $this->resolveSchoolStudentAttendanceSnapshot($today);
        $pendingStudentLeaves = $this->resolvePendingStudentLeaves();

        return [
            'todaySchedulesSchool' => $schoolSchedule['today_schedules'],
            'totalActiveClasses' => $schoolSchedule['total_active_classes'],
            'totalStudents' => $studentSnapshot['total_students'],
            'studentsCheckedInToday' => $studentSnapshot['present_students'],
            'studentPendingLeaves' => $pendingStudentLeaves,
            'studentsLateToday' => $studentSnapshot['late_students'],
            'studentsNotCheckedInToday' => $studentSnapshot['not_checked_in_students'],
            'alphaToday' => $studentSnapshot['alpha_students'],
        ];
    }

    private function buildWakasekKurikulumStats(User $user, Carbon $today): array
    {
        $schoolSchedule = $this->resolveSchoolScheduleStats($today);
        $studentSnapshot = $this->resolveSchoolStudentAttendanceSnapshot($today);

        return [
            'todaySchedulesSchool' => $schoolSchedule['today_schedules'],
            'totalActiveClasses' => $schoolSchedule['total_active_classes'],
            'totalActiveTeachers' => $this->countTeachers(true),
            'totalStudents' => $studentSnapshot['total_students'],
            'myTodaySchedules' => $this->resolveOwnTodaySchedules($user, $today),
            'absentTeachersToday' => 0, // Placeholder sesuai request
            'alphaToday' => $studentSnapshot['alpha_students'],
            'studentAttendanceRateToday' => $studentSnapshot['attendance_rate'],
        ];
    }

    private function buildWakasekHumasStats(Carbon $today): array
    {
        $humasStudentSnapshot = $this->resolveSchoolStudentAttendanceSnapshot($today);
        $notificationStats = $this->resolveNotificationDeliveryStats($today);

        return [
            'notificationsToday' => $notificationStats['notifications_today'],
            'waSent24h' => $notificationStats['wa_sent_24h'],
            'waFailed24h' => $notificationStats['wa_failed_24h'],
            'studentAttendanceRateToday' => $humasStudentSnapshot['attendance_rate'],
        ];
    }

    private function buildKepalaSekolahStats(Carbon $today): array
    {
        $principalSnapshot = $this->resolveSchoolStudentAttendanceSnapshot($today);

        return [
            'studentPresentToday' => $principalSnapshot['present_students'],
            'studentAttendanceRateToday' => $principalSnapshot['attendance_rate'],
            'studentPendingLeaves' => $this->resolvePendingStudentLeaves(),
            'totalTeachers' => $this->countTeachers(),
            'totalStudents' => $principalSnapshot['total_students'],
        ];
    }

    private function buildAdminStats(Carbon $today): array
    {
        return [
            'totalUsers' => User::count(),
            'totalStudents' => $this->countStudents(),
            'totalTeachers' => $this->countTeachers(),
            'todayActivities' => Absensi::whereDate('created_at', $today)->count(),
        ];
    }

    private function buildDefaultStats(string $userRole, Carbon $today): array
    {
        return [
            'totalUsers' => User::count(),
            'todayActivities' => Absensi::whereDate('created_at', $today)->count(),
            'userRole' => $userRole !== '' ? $userRole : 'Unknown',
        ];
    }

    /**
     * Apply role-based visibility scope for today-attendance endpoint.
     */
    private function applyTodayAttendanceScope($query, User $user, string $userRole): int
    {
        return match ($userRole) {
            RoleNames::SUPER_ADMIN,
            RoleNames::ADMIN,
            RoleNames::KEPALA_SEKOLAH,
            RoleNames::GURU,
            RoleNames::GURU_BK => $this->applyStudentOnlyAttendanceScope($query),
            RoleNames::WALI_KELAS => $this->applyWaliKelasAttendanceScope($query, $user),
            default => $this->applyPersonalAttendanceScope($query, $user),
        };
    }

    /**
     * Limit attendance listing to student population.
     */
    private function applyStudentOnlyAttendanceScope($query): int
    {
        $query->whereHas('user', function ($userQuery) {
            $userQuery->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            });
        });

        return $this->countStudents();
    }

    /**
     * Limit attendance listing to students in wali-kelas scope.
     */
    private function applyWaliKelasAttendanceScope($query, User $user): int
    {
        $kelasIds = $this->resolveWaliClassIdsForActiveYear($user);

        if ($kelasIds === []) {
            $query->whereRaw('1 = 0');
            return 0;
        }

        $query->whereHas('user', function ($userQuery) use ($kelasIds) {
            $userQuery
                ->whereHas('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
                })
                ->whereHas('kelas', function ($kelasQuery) use ($kelasIds) {
                    $kelasQuery->whereIn('kelas.id', $kelasIds);
                });
        });

        return (int) User::whereHas('roles', function ($roleQuery) {
            $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
        })->whereHas('kelas', function ($kelasQuery) use ($kelasIds) {
            $kelasQuery->whereIn('kelas.id', $kelasIds);
        })->count();
    }

    /**
     * Limit attendance listing to current user.
     */
    private function applyPersonalAttendanceScope($query, User $user): int
    {
        $query->where('user_id', $user->id);
        return 1;
    }

    /**
     * Resolve normalized user role or fallback role.
     */
    private function resolveUserRole(?User $user): string
    {
        $resolvedPrimaryRole = RoleAccessMatrix::resolvePrimaryRoleForUser($user);
        if (is_string($resolvedPrimaryRole) && trim($resolvedPrimaryRole) !== '') {
            return $resolvedPrimaryRole;
        }

        return $this->determineDefaultRole($user);
    }

    /**
     * Determine default role for user without assigned role
     */
    private function determineDefaultRole(?User $user): string
    {
        if (!$user || !$user->email) {
            return 'User';
        }

        $email = strtolower((string) $user->email);

        // Determine role based on email pattern
        if (str_contains($email, 'siswa') || str_contains($email, 'student')) {
            return RoleNames::SISWA;
        }
        if (str_contains($email, 'guru') || str_contains($email, 'teacher')) {
            return RoleNames::GURU;
        }
        if (str_contains($email, 'admin')) {
            return RoleNames::ADMIN;
        }
        if (str_contains($email, 'kepala') || str_contains($email, 'principal')) {
            return RoleNames::KEPALA_SEKOLAH;
        }

        return 'User';
    }

    public function systemStatus()
    {
        $status = [
            $this->resolveDatabaseStatus(),
            $this->resolveApiStatus(),
            $this->resolveWhatsappStatus(),
            $this->resolveBackupStatus(),
        ];

        return response()->json([
            'success' => true,
            'data' => $status,
            'meta' => $this->serverTimeMeta(),
        ]);
    }

    public function recentActivities(Request $request)
    {
        $user = $request->user();
        $activities = [];
        $userRole = $this->resolveUserRole($user);

        switch ($userRole) {
            case RoleNames::SUPER_ADMIN:
            case RoleNames::ADMIN:
            case RoleNames::KEPALA_SEKOLAH:
                $activities = $this->getAdminActivities();
                break;

            case RoleNames::GURU:
            case RoleNames::WALI_KELAS:
            case RoleNames::GURU_BK:
                $activities = $this->getTeacherActivities();
                break;

            case RoleNames::SISWA:
                $activities = $this->getStudentActivities($user->id);
                break;

            default:
                $activities = $this->getDefaultActivities($user->id);
        }

        return response()->json([
            'success' => true,
            'data' => $activities,
            'user_role' => $userRole,
            'meta' => $this->serverTimeMeta(),
        ]);
    }

    private function calculateAttendanceRate(User $user): string
    {
        [$periodStart, $periodEnd] = $this->resolveDashboardAttendancePeriod();
        $totalWorkingDays = $this->countWorkingDaysInPeriod($user, $periodStart, $periodEnd);
        if ($totalWorkingDays <= 0) {
            return '0%';
        }

        $present = Absensi::where('user_id', $user->id)
            ->whereBetween('tanggal', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereIn('status', ['hadir', 'terlambat'])
            ->count();

        return round(($present / $totalWorkingDays) * 100) . '%';
    }

    /**
     * Dashboard KPI uses month-to-date working days to avoid row-count based percentages.
     *
     * @return array{0:Carbon,1:Carbon}
     */
    private function resolveDashboardAttendancePeriod(): array
    {
        $serverNow = now()->setTimezone(config('app.timezone'));

        return [
            $serverNow->copy()->startOfMonth()->startOfDay(),
            $serverNow->copy()->startOfDay(),
        ];
    }

    private function countWorkingDaysInPeriod(User $user, Carbon $startDate, Carbon $endDate): int
    {
        $workingDays = 0;

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            if ($this->attendanceTimeService->isWorkingDay($user, $date->copy())) {
                $workingDays++;
            }
        }

        return $workingDays;
    }

    private function getAdminActivities()
    {
        $recentUsers = User::query()
            ->with('roles:id,name')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        $recentAbsensi = Absensi::query()
            ->with('user:id,nama_lengkap,email')
            ->orderByDesc('created_at')
            ->limit(2)
            ->get();

        $activities = [];

        foreach ($recentUsers as $registeredUser) {
            $registeredRole = RoleAccessMatrix::resolvePrimaryRoleForUser($registeredUser) ?? 'User';
            $activities[] = $this->buildActivityItem(
                id: (int) $registeredUser->id,
                user: $registeredUser->nama_lengkap ?? $registeredUser->email ?? 'Pengguna',
                action: 'Terdaftar sebagai ' . ($registeredRole ?? 'User'),
                occurredAt: Carbon::parse($registeredUser->created_at),
                status: 'info'
            );
        }

        foreach ($recentAbsensi as $absensi) {
            $statusKey = strtolower((string) $absensi->status);
            $activities[] = $this->buildActivityItem(
                id: (int) $absensi->id,
                user: $absensi->user?->nama_lengkap ?? $absensi->user?->email ?? 'Pengguna',
                action: 'Melakukan absensi ' . ($absensi->status ?? 'hadir'),
                occurredAt: Carbon::parse($absensi->created_at),
                status: $statusKey === 'hadir' ? 'success' : ($statusKey === 'terlambat' ? 'warning' : 'info')
            );
        }

        return $this->sortActivityItems($activities);
    }

    private function getTeacherActivities()
    {
        $activities = [];

        $pendingIzin = Izin::query()
            ->with('user:id,nama_lengkap,email')
            ->where('status', 'pending')
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        foreach ($pendingIzin as $izin) {
            $activities[] = $this->buildActivityItem(
                id: (int) $izin->id,
                user: $izin->user?->nama_lengkap ?? $izin->user?->email ?? 'Pengguna',
                action: 'Mengajukan izin ' . ($izin->jenis_izin ?? 'izin'),
                occurredAt: Carbon::parse($izin->created_at),
                status: 'warning'
            );
        }

        $recentAbsensi = Absensi::query()
            ->with('user:id,nama_lengkap,email')
            ->whereHas('user', function ($query) {
                $query->whereHas('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
                });
            })
            ->orderByDesc('created_at')
            ->limit(2)
            ->get();

        foreach ($recentAbsensi as $absensi) {
            $statusKey = strtolower((string) $absensi->status);
            $activities[] = $this->buildActivityItem(
                id: (int) $absensi->id,
                user: $absensi->user?->nama_lengkap ?? $absensi->user?->email ?? 'Pengguna',
                action: 'Absensi ' . ($absensi->status ?? 'hadir'),
                occurredAt: Carbon::parse($absensi->created_at),
                status: $statusKey === 'hadir' ? 'success' : ($statusKey === 'terlambat' ? 'warning' : 'info')
            );
        }

        return $this->sortActivityItems($activities);
    }

    private function getStudentActivities($userId)
    {
        $absensi = Absensi::query()
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                $statusKey = strtolower((string) $item->status);
                $status = $statusKey === 'hadir'
                    ? 'success'
                    : ($statusKey === 'terlambat' ? 'warning' : 'error');

                return $this->buildActivityItem(
                    id: (int) $item->id,
                    user: 'Anda',
                    action: 'Melakukan absensi ' . ($item->status ?? 'hadir'),
                    occurredAt: Carbon::parse($item->created_at),
                    status: $status
                );
            })
            ->values()
            ->all();

        return $absensi;
    }

    private function getDefaultActivities($userId)
    {
        $logs = ActivityLog::query()
            ->with('user:id,nama_lengkap,email')
            ->when($userId, fn ($query) => $query->where('causer_id', $userId))
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return $logs->map(function (ActivityLog $log) {
            $action = trim((string) ($log->notes ?? ''));
            if ($action === '') {
                $actionText = trim((string) ($log->action ?? 'aktivitas'));
                $moduleText = trim((string) ($log->module ?? 'sistem'));
                $action = ucfirst(str_replace('_', ' ', $actionText)) . ' (' . str_replace('_', ' ', $moduleText) . ')';
            }

            return $this->buildActivityItem(
                id: (int) $log->id,
                user: $log->user?->nama_lengkap ?? $log->user?->email ?? 'Sistem',
                action: $action,
                occurredAt: Carbon::parse($log->created_at),
                status: $this->resolveActivityStatusFromText($action)
            );
        })->values()->all();
    }

    private function buildActivityItem(int $id, string $user, string $action, Carbon $occurredAt, string $status): array
    {
        return [
            'id' => $id,
            'user' => $user,
            'action' => $action,
            'time' => $occurredAt->format('H:i'),
            'occurred_at' => $occurredAt->toISOString(),
            'status' => $status,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sortActivityItems(array $items): array
    {
        return collect($items)
            ->sortByDesc(function (array $item): int {
                $raw = (string) ($item['occurred_at'] ?? '');
                $parsed = strtotime($raw);
                return $parsed !== false ? $parsed : 0;
            })
            ->take(5)
            ->values()
            ->all();
    }

    private function resolveActivityStatusFromText(string $text): string
    {
        $normalized = strtolower($text);
        if (str_contains($normalized, 'gagal') || str_contains($normalized, 'failed')) {
            return 'error';
        }
        if (str_contains($normalized, 'hapus') || str_contains($normalized, 'delete') || str_contains($normalized, 'ubah')) {
            return 'warning';
        }

        return 'info';
    }

    private function serverTimeMeta(): array
    {
        $serverNow = now()->setTimezone(config('app.timezone'));

        return [
            'server_now' => $serverNow->toISOString(),
            'server_epoch_ms' => $serverNow->valueOf(),
            'server_date' => $serverNow->toDateString(),
            'timezone' => config('app.timezone'),
        ];
    }

    public function todayAttendance(Request $request)
    {
        try {
            $user = $request->user();
            $serverNow = now()->setTimezone(config('app.timezone'));
            $requestDate = (string) $request->get('date', $serverNow->toDateString());
            $targetDate = Carbon::parse($requestDate, config('app.timezone'))->startOfDay();

            Log::info('Today Attendance Request', [
                'user_id' => $user->id,
                'requested_date' => $requestDate,
                'parsed_date' => $targetDate->format('Y-m-d'),
                'timezone' => config('app.timezone')
            ]);

            $userRole = $this->resolveUserRole($user);

            // Base query absensi untuk summary + listing.
            $query = Absensi::with([
                'user' => function ($q) {
                    $q->select('id', 'nama_lengkap', 'email', 'status_kepegawaian', 'foto_profil');
                },
                'user.roles:id,name',
                'lokasiMasuk:id,nama_lokasi',
                'lokasiPulang:id,nama_lokasi',
            ])->whereDate('tanggal', $targetDate);

            $totalUsers = $this->applyTodayAttendanceScope($query, $user, $userRole);

            // Summary tetap dihitung dari seluruh data sesuai role+tanggal (tidak terpengaruh filter list).
            $summaryStatusCounts = (clone $query)
                ->selectRaw("LOWER(COALESCE(status, 'hadir')) as status_key, COUNT(*) as total")
                ->groupBy('status_key')
                ->pluck('total', 'status_key');
            $summaryTotal = (int) (clone $query)->count();

            // Filter listing (status/search) + pagination server-side supaya ringan.
            $statusFilter = $this->normalizeAttendanceStatusFilter($request->get('status'));
            $searchTerm = trim((string) $request->get('search', ''));
            if ($searchTerm !== '') {
                $searchTerm = mb_substr($searchTerm, 0, 100);
            }

            $listQuery = (clone $query)->orderBy('jam_masuk', 'desc');
            if ($statusFilter === 'belum_absen') {
                // Endpoint ini berbasis record absensi; belum absen berarti tidak ada row.
                $listQuery->whereRaw('1 = 0');
            } elseif ($statusFilter !== null) {
                $listQuery->where('status', $statusFilter);
            }

            if ($searchTerm !== '') {
                $searchLike = '%' . $searchTerm . '%';
                $listQuery->where(function ($q) use ($searchLike) {
                    $q->whereHas('user', function ($userQuery) use ($searchLike) {
                        $userQuery
                            ->where('nama_lengkap', 'like', $searchLike)
                            ->orWhere('email', 'like', $searchLike)
                            ->orWhere('status_kepegawaian', 'like', $searchLike);
                    })
                        ->orWhereHas('user.roles', function ($roleQuery) use ($searchLike) {
                            $roleQuery->where('name', 'like', $searchLike);
                        })
                        ->orWhereHas('lokasiMasuk', function ($locationQuery) use ($searchLike) {
                            $locationQuery->where('nama_lokasi', 'like', $searchLike);
                        })
                        ->orWhereHas('lokasiPulang', function ($locationQuery) use ($searchLike) {
                            $locationQuery->where('nama_lokasi', 'like', $searchLike);
                        });
                });
            }

            $page = max(1, (int) $request->get('page', 1));
            $perPage = (int) $request->get('per_page', 15);
            $perPage = max(5, min($perPage, 100));

            $attendances = $listQuery->paginate($perPage, ['*'], 'page', $page);
            $attendanceRows = $attendances->getCollection();

            Log::info('Today Attendance Query Results', [
                'total_found_current_page' => $attendanceRows->count(),
                'total_filtered' => $attendances->total(),
                'user_role' => $userRole,
                'total_expected_users' => $totalUsers,
                'status_filter' => $statusFilter,
                'search' => $searchTerm,
                'page' => $attendances->currentPage(),
                'per_page' => $attendances->perPage(),
            ]);

            // Transform data dengan format yang konsisten
            $transformedAttendances = $attendanceRows->map(
                fn (Absensi $attendance) => $this->transformTodayAttendanceRow($attendance)
            );

            // Hitung statistik
            $hadirMurni = (int) ($summaryStatusCounts['hadir'] ?? 0);
            $terlambatCount = (int) ($summaryStatusCounts['terlambat'] ?? 0);

            $summary = [
                'total' => $summaryTotal,
                'hadir' => $hadirMurni + $terlambatCount,
                'hadir_murni' => $hadirMurni,
                'terlambat' => $terlambatCount,
                'izin' => (int) ($summaryStatusCounts['izin'] ?? 0),
                'sakit' => (int) ($summaryStatusCounts['sakit'] ?? 0),
                'alpha' => (int) ($summaryStatusCounts['alpha'] ?? 0),
            ];

            // Calculate attendance percentage
            $attendedCount = $summary['hadir'];
            $percentage = $totalUsers > 0 ? round(($attendedCount / $totalUsers) * 100) : 0;
            $summary['totalUsers'] = $totalUsers;
            $summary['attendancePercentage'] = $percentage . '%';

            Log::info('Today Attendance Final Summary', [
                'summary' => $summary,
                'attendance_percentage' => $percentage,
                'date' => $targetDate->format('Y-m-d')
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'attendances' => $transformedAttendances->values(),
                    'pagination' => [
                        'current_page' => $attendances->currentPage(),
                        'last_page' => $attendances->lastPage(),
                        'per_page' => $attendances->perPage(),
                        'total' => $attendances->total(),
                        'from' => $attendances->firstItem() ?? 0,
                        'to' => $attendances->lastItem() ?? 0,
                    ],
                    'summary' => $summary,
                    'date' => $targetDate->format('Y-m-d'),
                    'totalUsers' => $totalUsers,
                    'user_role' => $userRole
                ],
                'message' => 'Data absensi berhasil diambil',
                'meta' => $this->serverTimeMeta(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in todayAttendance', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data absensi',
                'data' => [
                    'attendances' => [],
                    'summary' => [
                        'total' => 0,
                        'hadir' => 0,
                        'terlambat' => 0,
                        'izin' => 0,
                        'sakit' => 0,
                        'alpha' => 0,
                        'totalUsers' => 0,
                        'attendancePercentage' => '0%'
                    ]
                ],
                'meta' => $this->serverTimeMeta(),
            ], 500);
        }
    }

    public function myAttendanceStatus(Request $request)
    {
        try {
            $user = $request->user();
            $today = now()->setTimezone(config('app.timezone'))->startOfDay();

            Log::info('My Attendance Status Request', [
                'user_id' => $user->id,
                'user_name' => $user->nama_lengkap ?? $user->name,
                'today_date' => $today->format('Y-m-d'),
                'timezone' => config('app.timezone')
            ]);

            // Query absensi hari ini dengan relasi
            $attendance = Absensi::with(['lokasiMasuk:id,nama_lokasi', 'lokasiPulang:id,nama_lokasi'])
                ->where('user_id', $user->id)
                ->whereDate('tanggal', $today)
                ->first();

            Log::info('Attendance Query Result', [
                'found' => $attendance ? true : false,
                'attendance_id' => $attendance ? $attendance->id : null,
                'total_user_records' => Absensi::where('user_id', $user->id)->count()
            ]);

            $status = [
                'date' => $today->format('Y-m-d'),
                'has_attendance' => $attendance ? true : false,
                'has_checked_in' => false,
                'has_checked_out' => false,
                'check_in' => null,
                'check_out' => null,
                'status' => 'Belum Absen',
                'status_key' => 'belum_absen',
                'status_label' => 'Belum Absen',
                'is_non_presence_status' => false,
                'is_holiday' => false,
                'holiday_message' => null,
                'attendance_lock_reason' => null,
                'duration' => null,
                'location_in' => null,
                'location_out' => null,
                'latitude_in' => null,
                'longitude_in' => null,
                'latitude_out' => null,
                'longitude_out' => null,
                'accuracy_in' => null,
                'accuracy_out' => null,
                'is_late' => false,
                'attendance' => null
            ];

            $isWorkingDay = $this->attendanceTimeService->isWorkingDay($user, $today->copy());

            if (!$attendance && !$isWorkingDay) {
                $holidayMessage = 'Selamat menikmati hari libur Anda. Absensi tidak dibuka hari ini.';

                $status['status'] = 'Libur';
                $status['status_key'] = 'libur';
                $status['status_label'] = 'Hari Libur';
                $status['is_holiday'] = true;
                $status['holiday_message'] = $holidayMessage;
                $status['attendance_lock_reason'] = $holidayMessage;
            }

            if ($attendance) {
                $checkinParsed = $this->parseAttendanceClockValue($attendance->jam_masuk, 'jam_masuk');
                $checkoutParsed = $this->parseAttendanceClockValue($attendance->jam_pulang, 'jam_pulang');
                $checkinTime = $checkinParsed['carbon'];
                $checkoutTime = $checkoutParsed['carbon'];

                $status['check_in'] = $checkinParsed['formatted'];
                $status['check_out'] = $checkoutParsed['formatted'];
                $status['has_checked_in'] = $checkinParsed['formatted'] !== null;
                $status['has_checked_out'] = $checkoutParsed['formatted'] !== null;

                // Set status dan informasi lainnya
                $statusKey = $this->normalizeAttendanceStatusFilter((string) ($attendance->status ?? 'hadir')) ?? 'hadir';
                $status['status'] = $attendance->status ?? 'hadir';
                $status['status_key'] = $statusKey;
                $status['status_label'] = $this->formatAttendanceStatusLabel($statusKey);
                $status['is_late'] = $statusKey === 'terlambat';
                $status['is_non_presence_status'] = $this->isNonPresenceStatus($statusKey);
                if ($status['is_non_presence_status']) {
                    $status['attendance_lock_reason'] = 'Absensi dikunci karena status '
                        . $status['status_label']
                        . ' hari ini.';
                    $status['has_checked_in'] = false;
                    $status['has_checked_out'] = false;
                    $status['check_in'] = null;
                    $status['check_out'] = null;
                    $checkinTime = null;
                    $checkoutTime = null;
                }
                $status['location_in'] = $this->resolveAttendanceLocationName($attendance, 'masuk');
                $status['location_out'] = $this->resolveAttendanceLocationName($attendance, 'pulang');
                $status['latitude_in'] = $attendance->latitude_masuk !== null ? (float) $attendance->latitude_masuk : null;
                $status['longitude_in'] = $attendance->longitude_masuk !== null ? (float) $attendance->longitude_masuk : null;
                $status['latitude_out'] = $attendance->latitude_pulang !== null ? (float) $attendance->latitude_pulang : null;
                $status['longitude_out'] = $attendance->longitude_pulang !== null ? (float) $attendance->longitude_pulang : null;
                $status['accuracy_in'] = $attendance->gps_accuracy_masuk !== null ? (float) $attendance->gps_accuracy_masuk : null;
                $status['accuracy_out'] = $attendance->gps_accuracy_pulang !== null ? (float) $attendance->gps_accuracy_pulang : null;

                // Calculate duration if both times exist
                if ($checkinTime && $checkoutTime) {
                    $duration = $checkoutTime->diff($checkinTime);
                    $status['duration'] = $duration->format('%H:%I');
                }

                // Include attendance details for frontend
                $status['attendance'] = [
                    'id' => $attendance->id,
                    'tanggal' => $attendance->tanggal,
                    'jam_masuk' => $status['check_in'],
                    'jam_pulang' => $status['check_out'],
                    'status' => $status['status'],
                    'status_key' => $status['status_key'],
                    'status_label' => $status['status_label'],
                    'metode_absensi' => $attendance->metode_absensi,
                    'keterangan' => $attendance->keterangan,
                    'latitude_masuk' => $status['latitude_in'],
                    'longitude_masuk' => $status['longitude_in'],
                    'latitude_pulang' => $status['latitude_out'],
                    'longitude_pulang' => $status['longitude_out'],
                    'gps_accuracy_masuk' => $status['accuracy_in'],
                    'gps_accuracy_pulang' => $status['accuracy_out'],
                ];

                Log::info('Attendance Status Processed', [
                    'attendance_id' => $attendance->id,
                    'has_checked_in' => $status['has_checked_in'],
                    'has_checked_out' => $status['has_checked_out'],
                    'status' => $status['status']
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $status,
                'message' => $attendance ? 'Status absensi ditemukan' : 'Belum ada absensi hari ini',
                'meta' => $this->serverTimeMeta(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in myAttendanceStatus', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil status absensi',
                'data' => [
                    'date' => now()->setTimezone(config('app.timezone'))->toDateString(),
                    'has_attendance' => false,
                    'has_checked_in' => false,
                    'has_checked_out' => false,
                    'status' => 'Error',
                    'status_key' => 'error',
                    'status_label' => 'Error',
                    'is_non_presence_status' => false,
                    'is_holiday' => false,
                    'holiday_message' => null,
                    'attendance_lock_reason' => null,
                ],
                'meta' => $this->serverTimeMeta(),
            ], 500);
        }
    }

    public function liveClassReport(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user instanceof User) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                    'meta' => $this->serverTimeMeta(),
                ], 401);
            }

            if (!$user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fitur ini hanya tersedia untuk siswa',
                    'meta' => $this->serverTimeMeta(),
                ], 403);
            }

            $serverNow = now()->setTimezone(config('app.timezone'));
            $today = $serverNow->copy()->startOfDay();
            $activeClass = $this->resolveStudentActiveClass($user);

            if (!$activeClass instanceof Kelas) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kelas aktif tidak ditemukan',
                    'data' => [
                        'date' => $today->toDateString(),
                        'class_id' => null,
                        'class_name' => null,
                        'summary' => [
                            'total_students' => 0,
                            'hadir' => 0,
                            'terlambat' => 0,
                            'izin' => 0,
                            'sakit' => 0,
                            'alpha' => 0,
                            'belum_absen' => 0,
                            'male_students' => 0,
                            'female_students' => 0,
                        ],
                        'items' => [],
                    ],
                    'meta' => $this->serverTimeMeta(),
                ]);
            }

            $classmates = $this->buildLiveClassAttendanceItems($user, $activeClass, $today, $serverNow);

            return response()->json([
                'success' => true,
                'message' => 'Laporan PD hari ini berhasil diambil',
                'data' => [
                    'date' => $today->toDateString(),
                    'class_id' => (int) $activeClass->id,
                    'class_name' => $activeClass->nama_kelas,
                    'summary' => $this->buildLiveClassAttendanceSummary($classmates),
                    'items' => $classmates,
                ],
                'meta' => $this->serverTimeMeta(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Error in liveClassReport', [
                'error' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil laporan PD hari ini',
                'data' => [
                    'date' => now()->setTimezone(config('app.timezone'))->toDateString(),
                    'class_id' => null,
                    'class_name' => null,
                    'summary' => [
                        'total_students' => 0,
                        'hadir' => 0,
                        'terlambat' => 0,
                        'izin' => 0,
                        'sakit' => 0,
                        'alpha' => 0,
                        'belum_absen' => 0,
                        'male_students' => 0,
                        'female_students' => 0,
                    ],
                    'items' => [],
                ],
                'meta' => $this->serverTimeMeta(),
            ], 500);
        }
    }

    /**
     * Transform attendance row for today-attendance listing payload.
     *
     * @return array<string, mixed>
     */
    private function transformTodayAttendanceRow(Absensi $attendance): array
    {
        $jamMasukParsed = $this->parseAttendanceClockValue($attendance->jam_masuk);
        $jamPulangParsed = $this->parseAttendanceClockValue($attendance->jam_pulang);

        $jamMasuk = $jamMasukParsed['formatted'];
        $jamPulang = $jamPulangParsed['formatted'];
        $jamMasukCarbon = $jamMasukParsed['carbon'];
        $jamPulangCarbon = $jamPulangParsed['carbon'];

        $durasiKerja = null;
        if ($jamMasukCarbon && $jamPulangCarbon) {
            $duration = $jamPulangCarbon->diff($jamMasukCarbon);
            $durasiKerja = $duration->format('%H:%I');
        }

        $statusKey = strtolower((string) ($attendance->status ?? 'hadir'));
        $terlambat = false;
        $menitTerlambat = 0;

        $workStartTime = $this->resolveAttendanceStartTime($attendance);
        $standardWorkMinutes = 8 * 60; // 8 jam = 480 menit kerja standar (1 hari kerja)

        if ($jamMasukCarbon && $jamMasukCarbon->gt($workStartTime)) {
            $terlambat = true;
            $menitTerlambat = $jamMasukCarbon->diffInMinutes($workStartTime);
        }

        if ($statusKey === 'terlambat') {
            $terlambat = true;
            if ($menitTerlambat === 0 && $jamMasukCarbon && $jamMasukCarbon->gt($workStartTime)) {
                $menitTerlambat = $jamMasukCarbon->diffInMinutes($workStartTime);
            }
        }

        $tapStatus = false;
        $tapEligible = false;
        $tapMinutes = 0;
        $totalTkMinutes = 0; // Total TK (Terlambat + TAP)

        if ($jamMasukCarbon && !$jamPulangCarbon && $statusKey !== 'alpha') {
            $tapStatus = true;
            $tapEligible = true;
            $tapMinutes = $standardWorkMinutes;
            $totalTkMinutes = $terlambat
                ? $menitTerlambat + $tapMinutes
                : $tapMinutes;
        } elseif ($terlambat && $jamPulangCarbon) {
            $totalTkMinutes = $menitTerlambat;
        }

        $locationIn = $this->resolveAttendanceLocationName($attendance, 'masuk') ?? 'Lokasi tidak diketahui';
        $locationOut = $this->resolveAttendanceLocationName($attendance, 'pulang');

        return [
            'id' => $attendance->id,
            'user_id' => $attendance->user_id,
            'user_name' => $attendance->user?->nama_lengkap ?? $attendance->user?->email ?? 'Unknown',
            'status' => $attendance->status ?? 'hadir',
            'time' => $jamMasuk ?? '-',
            'jam_masuk' => $jamMasuk,
            'jam_pulang' => $jamPulang,
            'jam_keluar' => $jamPulang, // alias for compatibility
            'durasi_kerja' => $durasiKerja,
            'terlambat' => $terlambat,
            'is_late' => $terlambat, // alias for compatibility
            'menit_terlambat' => $menitTerlambat,
            'late_minutes' => $menitTerlambat, // alias for compatibility
            'tap_status' => $tapStatus,
            'tap_eligible' => $tapEligible,
            'tap_minutes' => $tapMinutes,
            'total_tk_minutes' => $totalTkMinutes, // Total TK (Terlambat + TAP)
            'date' => $attendance->tanggal
                ? Carbon::parse($attendance->tanggal)->format('Y-m-d')
                : Carbon::today()->format('Y-m-d'),
            'location' => $locationIn,
            'location_in' => $locationIn,
            'location_out' => $locationOut ?? 'Lokasi tidak diketahui',
            'latitude_masuk' => $attendance->latitude_masuk !== null ? (float) $attendance->latitude_masuk : null,
            'longitude_masuk' => $attendance->longitude_masuk !== null ? (float) $attendance->longitude_masuk : null,
            'latitude_pulang' => $attendance->latitude_pulang !== null ? (float) $attendance->latitude_pulang : null,
            'longitude_pulang' => $attendance->longitude_pulang !== null ? (float) $attendance->longitude_pulang : null,
            'gps_accuracy_masuk' => $attendance->gps_accuracy_masuk !== null ? (float) $attendance->gps_accuracy_masuk : null,
            'gps_accuracy_pulang' => $attendance->gps_accuracy_pulang !== null ? (float) $attendance->gps_accuracy_pulang : null,
            'notes' => $attendance->keterangan ?? '-',
            'keterangan' => $attendance->keterangan ?? '-', // alias for compatibility
            'metode_absensi' => $attendance->metode_absensi ?? 'selfie',
            'role' => RoleAccessMatrix::resolvePrimaryRoleForUser($attendance->user) ?? 'User',
            'status_kepegawaian' => $attendance->user?->status_kepegawaian ?? '-',
            'user_photo_url' => $attendance->user?->foto_profil_url ?? null,
            'foto_masuk_url' => $attendance->foto_masuk_url,
            'foto_pulang_url' => $attendance->foto_keluar_url,
        ];
    }

    /**
     * Parse jam_masuk/jam_pulang values into formatted string and Carbon instance.
     *
     * @return array{formatted:?string,carbon:?Carbon}
     */
    private function parseAttendanceClockValue($rawClock, ?string $logContext = null): array
    {
        if ($rawClock === null || $rawClock === '') {
            return [
                'formatted' => null,
                'carbon' => null,
            ];
        }

        try {
            $parsed = $rawClock instanceof Carbon
                ? $rawClock
                : Carbon::parse((string) $rawClock);

            return [
                'formatted' => $parsed->format('H:i'),
                'carbon' => $parsed,
            ];
        } catch (\Throwable $exception) {
            if (is_string($logContext) && $logContext !== '') {
                Log::warning('Failed to parse ' . $logContext . ': ' . (string) $rawClock, [
                    'error' => $exception->getMessage(),
                ]);
            }

            return [
                'formatted' => (string) $rawClock,
                'carbon' => null,
            ];
        }
    }

    /**
     * Resolve teacher/wali-class schedule statistics for dashboard card.
     *
     * @return array{today_schedules:int,total_classes:int}
     */
    private function resolveTeacherScheduleStats(User $user, ?string $userRole, Carbon $today): array
    {
        $dayKey = $this->resolveScheduleDayKey($today);
        $baseScheduleQuery = $this->buildActiveScheduleBaseQuery();

        $isWaliKelasRole = RoleNames::normalize($userRole) === RoleNames::WALI_KELAS;
        if ($isWaliKelasRole) {
            $waliClassIds = Kelas::query()
                ->where('wali_kelas_id', $user->id)
                ->where('is_active', true)
                ->pluck('id');

            if ($waliClassIds->isEmpty()) {
                return ['today_schedules' => 0, 'total_classes' => 0];
            }

            $todaySchedules = (clone $baseScheduleQuery)
                ->whereIn('kelas_id', $waliClassIds->all())
                ->whereRaw('LOWER(hari) = ?', [$dayKey])
                ->count();

            return [
                'today_schedules' => (int) $todaySchedules,
                'total_classes' => (int) $waliClassIds->count(),
            ];
        }

        $teacherScheduleQuery = (clone $baseScheduleQuery)->where('guru_id', $user->id);

        $todaySchedules = (clone $teacherScheduleQuery)
            ->whereRaw('LOWER(hari) = ?', [$dayKey])
            ->count();

        $totalClasses = (clone $teacherScheduleQuery)
            ->distinct('kelas_id')
            ->count('kelas_id');

        return [
            'today_schedules' => (int) $todaySchedules,
            'total_classes' => (int) $totalClasses,
        ];
    }

    /**
     * Resolve workload KPI for teacher/guru_bk dashboard.
     *
     * @return array{total_teaching_hours:int,today_schedules:int,total_classes:int,total_students_taught:int}
     */
    private function resolveTeacherWorkloadStats(User $user, Carbon $today): array
    {
        $dayKey = $this->resolveScheduleDayKey($today);
        $teacherScheduleQuery = $this->buildActiveScheduleBaseQuery()
            ->where('guru_id', $user->id);

        $todaySchedules = (int) (clone $teacherScheduleQuery)
            ->whereRaw('LOWER(hari) = ?', [$dayKey])
            ->count();

        $totalClasses = (int) (clone $teacherScheduleQuery)
            ->distinct('kelas_id')
            ->count('kelas_id');

        $scheduleRows = (clone $teacherScheduleQuery)->get([
            'jam_ke',
            'jam_mulai',
            'jam_selesai',
        ])->all();
        $totalTeachingHours = $this->calculateTotalLessonHours($scheduleRows);

        $classIds = (clone $teacherScheduleQuery)
            ->distinct('kelas_id')
            ->pluck('kelas_id')
            ->filter()
            ->values()
            ->all();

        $totalStudentsTaught = 0;
        if ($classIds !== []) {
            $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
            $studentQuery = User::query()
                ->whereHas('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
                })
                ->whereHas('kelas', function ($kelasQuery) use ($classIds, $activeTahunAjaranId) {
                    $kelasQuery->whereIn('kelas.id', $classIds);
                    if ($activeTahunAjaranId > 0) {
                        $kelasQuery->where('kelas.tahun_ajaran_id', $activeTahunAjaranId);
                    }
                });

            $totalStudentsTaught = (int) $studentQuery
                ->distinct('users.id')
                ->count('users.id');
        }

        return [
            'total_teaching_hours' => $totalTeachingHours,
            'today_schedules' => $todaySchedules,
            'total_classes' => $totalClasses,
            'total_students_taught' => $totalStudentsTaught,
        ];
    }

    /**
     * Resolve attendance summary for students visible to teacher/wali role on a given day.
     *
     * @return array{total:int,present:int,late:int,not_checked_in:int,summary:string,rate:string}
     */
    private function resolveTeacherStudentAttendanceStats(User $user, ?string $userRole, Carbon $today): array
    {
        $studentScopeQuery = $this->buildTeacherStudentScopeQuery($user, $userRole);
        $totalStudents = (int) (clone $studentScopeQuery)->count();

        if ($totalStudents <= 0) {
            return [
                'total' => 0,
                'present' => 0,
                'late' => 0,
                'not_checked_in' => 0,
                'summary' => '0/0',
                'rate' => '0%',
            ];
        }

        $studentIdSubQuery = (clone $studentScopeQuery)->select('users.id');
        $attendanceTodayQuery = Absensi::query()
            ->whereDate('tanggal', $today->toDateString())
            ->whereIn('user_id', $studentIdSubQuery);

        $recordedCount = (int) (clone $attendanceTodayQuery)
            ->distinct('user_id')
            ->count('user_id');

        $presentCount = (int) (clone $attendanceTodayQuery)
            ->whereIn('status', ['hadir', 'terlambat'])
            ->distinct('user_id')
            ->count('user_id');

        $lateCount = (int) (clone $attendanceTodayQuery)
            ->where('status', 'terlambat')
            ->distinct('user_id')
            ->count('user_id');

        $notCheckedInCount = max(0, $totalStudents - $recordedCount);
        $ratePercent = $totalStudents > 0
            ? (int) round(($presentCount / $totalStudents) * 100)
            : 0;

        return [
            'total' => $totalStudents,
            'present' => $presentCount,
            'late' => $lateCount,
            'not_checked_in' => $notCheckedInCount,
            'summary' => "{$presentCount}/{$totalStudents}",
            'rate' => "{$ratePercent}%",
        ];
    }

    /**
     * Resolve pending student leave requests visible to teacher role.
     */
    private function resolvePendingApprovalsForTeacher(User $user, ?string $userRole): int
    {
        $query = Izin::query()
            ->where('status', 'pending')
            ->whereHas('user.roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            });

        if (RoleNames::normalize($userRole) !== RoleNames::WALI_KELAS) {
            return (int) $query->count();
        }

        $waliClassIds = $this->resolveWaliClassIdsForActiveYear($user);

        if ($waliClassIds === []) {
            return 0;
        }

        return (int) $query->whereIn('kelas_id', $waliClassIds)->count();
    }

    /**
     * Build a student query according to teacher visibility scope.
     */
    private function buildTeacherStudentScopeQuery(User $user, ?string $userRole)
    {
        $query = User::query()->whereHas('roles', function ($roleQuery) {
            $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
        });

        if (RoleNames::normalize($userRole) !== RoleNames::WALI_KELAS) {
            return $query;
        }

        $waliClassIds = $this->resolveWaliClassIdsForActiveYear($user);

        if ($waliClassIds === []) {
            $query->whereRaw('1 = 0');
            return $query;
        }

        $query->whereHas('kelas', function ($kelasQuery) use ($waliClassIds) {
            $kelasQuery->whereIn('kelas.id', $waliClassIds);
        });

        return $query;
    }

    /**
     * Resolve wali class IDs limited to active academic year.
     *
     * @return array<int, int>
     */
    private function resolveWaliClassIdsForActiveYear(User $user): array
    {
        $query = Kelas::query()
            ->where('wali_kelas_id', $user->id)
            ->where('is_active', true);

        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        if ($activeTahunAjaranId > 0) {
            $query->where('tahun_ajaran_id', $activeTahunAjaranId);
        }

        return $query->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    }

    private function resolveStudentActiveClass(User $user): ?Kelas
    {
        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $kelasRelation = $user->kelas()->where('kelas.is_active', true);

        if ($activeTahunAjaranId > 0) {
            $kelasRelation->where('kelas.tahun_ajaran_id', $activeTahunAjaranId);
        }

        $kelasCollection = $kelasRelation->get();
        if ($kelasCollection->isEmpty()) {
            return null;
        }

        return $kelasCollection->first(function (Kelas $kelas) use ($activeTahunAjaranId) {
            $pivot = $kelas->pivot;
            if (!$pivot) {
                return false;
            }

            $matchesYear = $activeTahunAjaranId <= 0
                || (int) ($pivot->tahun_ajaran_id ?? 0) === $activeTahunAjaranId
                || (int) ($kelas->tahun_ajaran_id ?? 0) === $activeTahunAjaranId;
            $isPivotActive = ((string) ($pivot->status ?? '') === 'aktif')
                || ((bool) ($pivot->is_active ?? false));

            return $matchesYear && $isPivotActive;
        }) ?? $kelasCollection->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildLiveClassAttendanceItems(User $user, Kelas $kelas, Carbon $today, Carbon $serverNow): array
    {
        $currentUserId = (int) $user->id;
        $studentRows = User::query()
            ->with('roles:id,name')
            ->select('users.id', 'users.nama_lengkap', 'users.nis', 'users.nisn', 'users.email', 'users.foto_profil', 'users.updated_at', 'users.jenis_kelamin')
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->whereHas('kelas', function ($kelasQuery) use ($kelas) {
                $kelasQuery->where('kelas.id', $kelas->id);
            })
            ->orderBy('users.nama_lengkap')
            ->get();

        $studentIds = $studentRows
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $attendanceRows = Absensi::query()
            ->whereDate('tanggal', $today->toDateString())
            ->whereIn('user_id', $studentIds)
            ->orderByDesc('created_at')
            ->get()
            ->unique('user_id')
            ->keyBy('user_id');

        $items = $studentRows->map(function (User $student) use ($attendanceRows, $currentUserId, $today, $serverNow) {
            /** @var Absensi|null $attendance */
            $attendance = $attendanceRows->get($student->id);
            $statusKey = $attendance instanceof Absensi
                ? strtolower((string) ($attendance->status ?? 'hadir'))
                : 'belum_absen';
            $jamMasukParsed = $attendance instanceof Absensi
                ? $this->parseAttendanceClockValue($attendance->jam_masuk)
                : ['formatted' => null, 'carbon' => null];
            $jamPulangParsed = $attendance instanceof Absensi
                ? $this->parseAttendanceClockValue($attendance->jam_pulang)
                : ['formatted' => null, 'carbon' => null];
            $jamMasuk = $jamMasukParsed['formatted'];
            $jamPulang = $jamPulangParsed['formatted'];
            $genderCode = $this->normalizeStudentGenderCode($student->jenis_kelamin);
            $locationLabel = $attendance instanceof Absensi
                ? ($this->resolveAttendanceLocationName($attendance, 'masuk')
                    ?? $this->resolveAttendanceLocationName($attendance, 'pulang'))
                : null;
            $roleLabel = RoleAccessMatrix::resolvePrimaryRoleForUser($student) ?? RoleNames::SISWA;
            $roleLabel = ucfirst(str_replace('_', ' ', strtolower(trim((string) $roleLabel))));
            $timeInsight = $this->resolveLiveClassTimeInsight(
                $student,
                $statusKey,
                $jamMasukParsed['carbon'],
                $jamPulangParsed['carbon'],
                $today,
                $serverNow
            );

            return [
                'user_id' => (int) $student->id,
                'name' => $student->nama_lengkap ?? $student->email ?? 'Siswa',
                'nis' => $student->nis,
                'nisn' => $student->nisn,
                'gender' => $genderCode,
                'role_label' => $roleLabel,
                'status' => $statusKey,
                'status_label' => $this->formatAttendanceStatusLabel($statusKey),
                'check_in_time' => $jamMasuk,
                'check_out_time' => $jamPulang,
                'expected_check_in_time' => $timeInsight['expected_check_in_time'],
                'expected_check_out_time' => $timeInsight['expected_check_out_time'],
                'late_minutes' => $timeInsight['late_minutes'],
                'is_late' => $timeInsight['is_late'],
                'is_checkout_pending' => $timeInsight['is_checkout_pending'],
                'indicator_key' => $timeInsight['indicator_key'],
                'indicator_label' => $timeInsight['indicator_label'],
                'time_hint' => $timeInsight['time_hint'],
                'notes' => $attendance?->keterangan,
                'location_label' => $locationLabel,
                'user_photo_url' => $student->foto_profil_url,
                'is_self' => (int) $student->id === $currentUserId,
            ];
        })->all();

        usort($items, function (array $left, array $right): int {
            $leftPriority = $this->resolveLiveClassIndicatorPriority((string) ($left['indicator_key'] ?? $left['status'] ?? 'hadir'));
            $rightPriority = $this->resolveLiveClassIndicatorPriority((string) ($right['indicator_key'] ?? $right['status'] ?? 'hadir'));
            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            $leftLateMinutes = (int) ($left['late_minutes'] ?? 0);
            $rightLateMinutes = (int) ($right['late_minutes'] ?? 0);
            if ($leftLateMinutes !== $rightLateMinutes) {
                return $rightLateMinutes <=> $leftLateMinutes;
            }

            return strcmp((string) ($left['name'] ?? ''), (string) ($right['name'] ?? ''));
        });

        return $items;
    }

    /**
     * @return array{
     *     expected_check_in_time:?string,
     *     expected_check_out_time:?string,
     *     late_minutes:int,
     *     is_late:bool,
     *     is_checkout_pending:bool,
     *     indicator_key:string,
     *     indicator_label:string,
     *     time_hint:?string
     * }
     */
    private function resolveLiveClassTimeInsight(
        User $student,
        string $statusKey,
        ?Carbon $checkInTime,
        ?Carbon $checkOutTime,
        Carbon $today,
        Carbon $serverNow
    ): array {
        $workingHours = $this->attendanceTimeService->getWorkingHours($student);
        $expectedCheckIn = $this->resolveClockTimeForDate($workingHours['jam_masuk'] ?? null, $today);
        $expectedCheckOut = $this->resolveClockTimeForDate($workingHours['jam_pulang'] ?? null, $today);
        $checkoutPendingDelayMinutes = 15;
        $lateMinutes = 0;
        $isLate = false;

        if ($checkInTime instanceof Carbon && $expectedCheckIn instanceof Carbon && $checkInTime->gt($expectedCheckIn)) {
            $lateMinutes = $checkInTime->diffInMinutes($expectedCheckIn);
            $isLate = true;
        }

        if ($statusKey === 'terlambat') {
            $isLate = true;
        }

        $isPresenceStatus = in_array($statusKey, ['hadir', 'terlambat'], true);
        $isCheckoutPending = $isPresenceStatus
            && $checkInTime instanceof Carbon
            && !($checkOutTime instanceof Carbon)
            && $expectedCheckOut instanceof Carbon
            && $serverNow->gte($expectedCheckOut->copy()->addMinutes($checkoutPendingDelayMinutes));

        $indicatorKey = match (true) {
            $statusKey === 'belum_absen' => 'belum_absen',
            $statusKey === 'terlambat' => 'terlambat',
            $isCheckoutPending => 'belum_pulang',
            $statusKey === 'alpha' => 'alpha',
            $statusKey === 'sakit' => 'sakit',
            $statusKey === 'izin' => 'izin',
            default => 'hadir',
        };

        $indicatorLabel = match ($indicatorKey) {
            'belum_absen' => 'Belum Absen',
            'terlambat' => $lateMinutes > 0 ? "Terlambat {$lateMinutes} menit" : 'Terlambat',
            'belum_pulang' => 'Belum Pulang',
            'alpha' => 'Alpha',
            'sakit' => 'Sakit',
            'izin' => 'Izin',
            default => 'Hadir',
        };

        $timeHint = match ($indicatorKey) {
            'belum_absen' => $expectedCheckIn instanceof Carbon
                ? 'Jadwal masuk ' . $expectedCheckIn->format('H:i')
                : null,
            'terlambat' => $expectedCheckIn instanceof Carbon
                ? 'Jadwal masuk ' . $expectedCheckIn->format('H:i')
                : null,
            'belum_pulang' => $expectedCheckOut instanceof Carbon
                ? 'Jadwal pulang ' . $expectedCheckOut->format('H:i')
                : null,
            default => null,
        };

        return [
            'expected_check_in_time' => $expectedCheckIn?->format('H:i'),
            'expected_check_out_time' => $expectedCheckOut?->format('H:i'),
            'late_minutes' => $lateMinutes,
            'is_late' => $isLate,
            'is_checkout_pending' => $isCheckoutPending,
            'indicator_key' => $indicatorKey,
            'indicator_label' => $indicatorLabel,
            'time_hint' => $timeHint,
        ];
    }

    private function resolveLiveClassIndicatorPriority(string $indicatorKey): int
    {
        return match ($indicatorKey) {
            'belum_absen' => 0,
            'terlambat' => 1,
            'belum_pulang' => 2,
            'alpha' => 3,
            'sakit' => 4,
            'izin' => 5,
            'hadir' => 6,
            default => 99,
        };
    }

    private function resolveClockTimeForDate($rawClock, Carbon $date): ?Carbon
    {
        if ($rawClock === null) {
            return null;
        }

        $normalized = trim((string) $rawClock);
        if ($normalized === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                return Carbon::createFromFormat($format, $normalized, config('app.timezone'))
                    ->setDate($date->year, $date->month, $date->day);
            } catch (\Throwable $exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($normalized, config('app.timezone'))
                ->setDate($date->year, $date->month, $date->day);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, int>
     */
    private function buildLiveClassAttendanceSummary(array $items): array
    {
        $summary = [
            'total_students' => count($items),
            'hadir' => 0,
            'terlambat' => 0,
            'izin' => 0,
            'sakit' => 0,
            'alpha' => 0,
            'belum_absen' => 0,
            'male_students' => 0,
            'female_students' => 0,
        ];

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'belum_absen');
            $gender = strtoupper(trim((string) ($item['gender'] ?? '')));
            if ($gender === 'L') {
                $summary['male_students']++;
            } elseif ($gender === 'P') {
                $summary['female_students']++;
            }

            if ($status === 'hadir') {
                $summary['hadir']++;
                continue;
            }

            if ($status === 'terlambat') {
                $summary['terlambat']++;
                continue;
            }

            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            } else {
                $summary['belum_absen']++;
            }
        }

        return $summary;
    }

    private function normalizeStudentGenderCode($rawGender): ?string
    {
        $normalized = strtolower(trim((string) ($rawGender ?? '')));
        if (in_array($normalized, ['l', 'lk', 'laki-laki', 'laki laki', 'male'], true)) {
            return 'L';
        }

        if (in_array($normalized, ['p', 'pr', 'perempuan', 'female'], true)) {
            return 'P';
        }

        return null;
    }

    /**
     * Resolve school-wide schedule metrics.
     *
     * @return array{today_schedules:int,total_active_classes:int}
     */
    private function resolveSchoolScheduleStats(Carbon $today): array
    {
        $dayKey = $this->resolveScheduleDayKey($today);
        $activeTahunAjaranId = $this->resolveActiveTahunAjaranId();
        $scheduleQuery = $this->buildActiveScheduleBaseQuery($activeTahunAjaranId);

        $todaySchedules = (int) (clone $scheduleQuery)
            ->whereRaw('LOWER(hari) = ?', [$dayKey])
            ->count();

        $activeClassQuery = Kelas::query()->where('is_active', true);
        if ($activeTahunAjaranId > 0) {
            $activeClassQuery->where('tahun_ajaran_id', $activeTahunAjaranId);
        }

        return [
            'today_schedules' => $todaySchedules,
            'total_active_classes' => (int) $activeClassQuery->count(),
        ];
    }

    /**
     * Resolve school-wide student attendance snapshot for today.
     *
     * @return array{total_students:int,present_students:int,recorded_students:int,late_students:int,alpha_students:int,not_checked_in_students:int,attendance_rate:string}
     */
    private function resolveSchoolStudentAttendanceSnapshot(Carbon $today): array
    {
        $studentScopeQuery = User::query()
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            });

        $totalStudents = (int) (clone $studentScopeQuery)->count();
        if ($totalStudents <= 0) {
            return [
                'total_students' => 0,
                'present_students' => 0,
                'recorded_students' => 0,
                'late_students' => 0,
                'alpha_students' => 0,
                'not_checked_in_students' => 0,
                'attendance_rate' => '0%',
            ];
        }

        $studentIdSubQuery = (clone $studentScopeQuery)->select('users.id');
        $attendanceQuery = Absensi::query()
            ->whereDate('tanggal', $today->toDateString())
            ->whereIn('user_id', $studentIdSubQuery);

        $recordedStudents = (int) (clone $attendanceQuery)
            ->distinct('user_id')
            ->count('user_id');
        $presentStudents = (int) (clone $attendanceQuery)
            ->whereIn('status', ['hadir', 'terlambat'])
            ->distinct('user_id')
            ->count('user_id');
        $lateStudents = (int) (clone $attendanceQuery)
            ->where('status', 'terlambat')
            ->distinct('user_id')
            ->count('user_id');
        $alphaStudents = (int) (clone $attendanceQuery)
            ->where('status', 'alpha')
            ->distinct('user_id')
            ->count('user_id');

        $notCheckedInStudents = max(0, $totalStudents - $recordedStudents);
        $attendanceRate = (int) round(($presentStudents / $totalStudents) * 100);

        return [
            'total_students' => $totalStudents,
            'present_students' => $presentStudents,
            'recorded_students' => $recordedStudents,
            'late_students' => $lateStudents,
            'alpha_students' => $alphaStudents,
            'not_checked_in_students' => $notCheckedInStudents,
            'attendance_rate' => "{$attendanceRate}%",
        ];
    }

    private function resolvePendingStudentLeaves(): int
    {
        return (int) Izin::query()
            ->where('status', 'pending')
            ->whereHas('user.roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->count();
    }

    /**
     * @return array{notifications_today:int,wa_sent_24h:int,wa_failed_24h:int}
     */
    private function resolveNotificationDeliveryStats(Carbon $today): array
    {
        $recentWindowStart = now()->subDay();

        $notificationsToday = (int) Notification::query()
            ->whereDate('created_at', $today->toDateString())
            ->count();

        $waSent24h = (int) WhatsappGateway::query()
            ->whereIn('status', [WhatsappGateway::STATUS_SENT, WhatsappGateway::STATUS_DELIVERED])
            ->where('created_at', '>=', $recentWindowStart)
            ->count();

        $waFailed24h = (int) WhatsappGateway::query()
            ->where('status', WhatsappGateway::STATUS_FAILED)
            ->where('created_at', '>=', $recentWindowStart)
            ->count();

        return [
            'notifications_today' => $notificationsToday,
            'wa_sent_24h' => $waSent24h,
            'wa_failed_24h' => $waFailed24h,
        ];
    }

    /**
     * @return array{activeGpsLocations:int,totalGpsLocations:int,attendanceWithGpsToday:int,attendanceWithoutGpsToday:int}
     */
    private function resolveSarprasGpsStats(Carbon $today): array
    {
        $studentIdSubQuery = User::query()
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->select('users.id');

        $todayAttendanceQuery = Absensi::query()
            ->whereDate('tanggal', $today->toDateString())
            ->whereIn('user_id', $studentIdSubQuery);

        $totalAttendances = (int) (clone $todayAttendanceQuery)->count();
        $attendanceWithGps = (int) (clone $todayAttendanceQuery)
            ->where(function ($query) {
                $query
                    ->whereNotNull('lokasi_masuk_id')
                    ->orWhere(function ($gpsQuery) {
                        $gpsQuery
                            ->whereNotNull('latitude_masuk')
                            ->whereNotNull('longitude_masuk');
                    });
            })
            ->count();

        return [
            'activeGpsLocations' => (int) LokasiGps::query()->where('is_active', true)->count(),
            'totalGpsLocations' => (int) LokasiGps::query()->count(),
            'attendanceWithGpsToday' => $attendanceWithGps,
            'attendanceWithoutGpsToday' => max(0, $totalAttendances - $attendanceWithGps),
        ];
    }

    private function countStudents(): int
    {
        return (int) User::query()
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->count();
    }

    private function countTeachers(bool $activeOnly = false): int
    {
        $query = User::query()
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::flattenAliases([
                    RoleNames::GURU,
                    RoleNames::WALI_KELAS,
                    RoleNames::GURU_BK,
                ]));
            });

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        return (int) $query->count();
    }

    private function resolveOwnTodaySchedules(User $user, Carbon $today): int
    {
        $dayKey = $this->resolveScheduleDayKey($today);
        return (int) $this->buildActiveScheduleBaseQuery()
            ->where('guru_id', $user->id)
            ->whereRaw('LOWER(hari) = ?', [$dayKey])
            ->count();
    }

    private function resolveActiveTahunAjaranId(): int
    {
        return (int) TahunAjaran::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('id');
    }

    private function buildActiveScheduleBaseQuery(?int $activeTahunAjaranId = null)
    {
        $effectiveTahunAjaranId = $activeTahunAjaranId ?? $this->resolveActiveTahunAjaranId();

        $query = JadwalMengajar::query()
            ->where('is_active', true)
            ->where(function ($statusQuery) {
                $statusQuery
                    ->whereNull('status')
                    ->orWhere('status', '!=', 'archived');
            });

        if ($effectiveTahunAjaranId > 0) {
            $query->where('tahun_ajaran_id', $effectiveTahunAjaranId);
        }

        return $query;
    }

    private function resolveScheduleDayKey(Carbon $today): string
    {
        $dayMap = [
            Carbon::SUNDAY => 'minggu',
            Carbon::MONDAY => 'senin',
            Carbon::TUESDAY => 'selasa',
            Carbon::WEDNESDAY => 'rabu',
            Carbon::THURSDAY => 'kamis',
            Carbon::FRIDAY => 'jumat',
            Carbon::SATURDAY => 'sabtu',
        ];

        return $dayMap[$today->dayOfWeek] ?? 'senin';
    }

    /**
     * Calculate total lesson periods (JP) from teacher schedules.
     *
     * @param array<int, mixed> $scheduleRows
     */
    private function calculateTotalLessonHours(array $scheduleRows): int
    {
        $totalPeriods = 0;

        foreach ($scheduleRows as $row) {
            $periodsFromJamKe = $this->resolveLessonPeriodsFromJamKe($row->jam_ke ?? null);
            if ($periodsFromJamKe !== null) {
                $totalPeriods += $periodsFromJamKe;
                continue;
            }

            $periodsFromTimeRange = $this->resolveLessonPeriodsFromTimeRange(
                $row->jam_mulai ?? null,
                $row->jam_selesai ?? null
            );
            if ($periodsFromTimeRange !== null) {
                $totalPeriods += $periodsFromTimeRange;
                continue;
            }

            $totalPeriods += 1;
        }

        return $totalPeriods;
    }

    private function resolveLessonPeriodsFromJamKe($rawJamKe): ?int
    {
        if ($rawJamKe === null) {
            return null;
        }

        $normalized = trim((string) $rawJamKe);
        if ($normalized === '') {
            return null;
        }

        // Single numeric value, e.g. "3"
        if (preg_match('/^\d+$/', $normalized) === 1) {
            // `jam_ke` tunggal umumnya menunjukkan urutan jam, bukan jumlah jam.
            return 1;
        }

        $segments = preg_split('/[,;\/]+/', $normalized) ?: [];
        $parsedPeriods = 0;

        foreach ($segments as $segmentRaw) {
            $segment = trim($segmentRaw);
            if ($segment === '') {
                continue;
            }

            // Range format, e.g. "1-3"
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $segment, $matches) === 1) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];
                if ($end >= $start) {
                    $parsedPeriods += ($end - $start + 1);
                }
                continue;
            }

            if (preg_match('/^\d+$/', $segment) === 1) {
                $parsedPeriods++;
            }
        }

        return $parsedPeriods > 0 ? $parsedPeriods : null;
    }

    private function resolveLessonPeriodsFromTimeRange($rawStart, $rawEnd): ?int
    {
        if ($rawStart === null || $rawEnd === null) {
            return null;
        }

        try {
            $start = Carbon::parse((string) $rawStart);
            $end = Carbon::parse((string) $rawEnd);
        } catch (\Throwable $exception) {
            return null;
        }

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        $minutes = $end->diffInMinutes($start);
        if ($minutes <= 0) {
            return null;
        }

        // 1 JP diasumsikan 45 menit.
        return max(1, (int) ceil($minutes / 45));
    }

    private function resolveDatabaseStatus(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'name' => 'Database',
                'status' => 'online',
                'message' => 'Koneksi database aktif',
            ];
        } catch (\Throwable $exception) {
            return [
                'name' => 'Database',
                'status' => 'offline',
                'message' => 'Database tidak terhubung',
            ];
        }
    }

    private function resolveApiStatus(): array
    {
        try {
            $startedAt = microtime(true);
            DB::select('SELECT 1');
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

            return [
                'name' => 'Server API',
                'status' => 'online',
                'message' => "API responsif ({$elapsedMs} ms)",
            ];
        } catch (\Throwable $exception) {
            return [
                'name' => 'Server API',
                'status' => 'offline',
                'message' => 'API mengalami gangguan koneksi database',
            ];
        }
    }

    private function resolveWhatsappStatus(): array
    {
        try {
            /** @var WhatsappGatewayClient $gatewayClient */
            $gatewayClient = app(WhatsappGatewayClient::class);
            $runtimeConfig = $gatewayClient->getRuntimeConfig();

            if (empty($runtimeConfig['notification_enabled'])) {
                return [
                    'name' => 'WhatsApp Gateway',
                    'status' => 'warning',
                    'message' => 'Notifikasi WhatsApp nonaktif',
                ];
            }

            $isConfigured = ($runtimeConfig['api_url'] ?? '') !== ''
                && ($runtimeConfig['api_key'] ?? '') !== ''
                && ($runtimeConfig['sender'] ?? '') !== '';

            if (!$isConfigured) {
                return [
                    'name' => 'WhatsApp Gateway',
                    'status' => 'warning',
                    'message' => 'Konfigurasi WhatsApp belum lengkap',
                ];
            }

            $recentWindowStart = now()->subDay();
            $sentCount = WhatsappGateway::query()
                ->whereIn('status', [WhatsappGateway::STATUS_SENT, WhatsappGateway::STATUS_DELIVERED])
                ->where('created_at', '>=', $recentWindowStart)
                ->count();
            $failedCount = WhatsappGateway::query()
                ->where('status', WhatsappGateway::STATUS_FAILED)
                ->where('created_at', '>=', $recentWindowStart)
                ->count();

            if ($failedCount > 0 && $failedCount >= max(1, $sentCount)) {
                return [
                    'name' => 'WhatsApp Gateway',
                    'status' => 'warning',
                    'message' => "Terdeteksi {$failedCount} notifikasi gagal (24 jam terakhir)",
                ];
            }

            return [
                'name' => 'WhatsApp Gateway',
                'status' => 'online',
                'message' => "Aktif ({$sentCount} notifikasi terkirim/24 jam)",
            ];
        } catch (\Throwable $exception) {
            return [
                'name' => 'WhatsApp Gateway',
                'status' => 'warning',
                'message' => 'Status gateway WhatsApp tidak tersedia',
            ];
        }
    }

    private function resolveBackupStatus(): array
    {
        try {
            $latestBackup = BackupLog::query()
                ->orderByDesc('created_at')
                ->first();

            if (!$latestBackup) {
                return $this->resolveBackupStatusFromFilesystem();
            }

            $lastRun = Carbon::parse($latestBackup->created_at);
            $lastRunLabel = $lastRun->format('d M Y H:i');

            if ($latestBackup->status === 'success') {
                $isFresh = $lastRun->greaterThanOrEqualTo(now()->subDays(2));
                return [
                    'name' => 'Backup System',
                    'status' => $isFresh ? 'online' : 'warning',
                    'message' => $isFresh
                        ? "Backup terakhir sukses ({$lastRunLabel})"
                        : "Backup terakhir terlalu lama ({$lastRunLabel})",
                ];
            }

            if ($latestBackup->status === 'in_progress') {
                return [
                    'name' => 'Backup System',
                    'status' => 'warning',
                    'message' => "Backup sedang berjalan sejak {$lastRunLabel}",
                ];
            }

            return [
                'name' => 'Backup System',
                'status' => 'warning',
                'message' => "Backup terakhir gagal ({$lastRunLabel})",
            ];
        } catch (\Throwable $exception) {
            return $this->resolveBackupStatusFromFilesystem();
        }
    }

    private function resolveBackupStatusFromFilesystem(): array
    {
        try {
            $backupFiles = glob(storage_path('app/backups/*.zip')) ?: [];
            $settingsPath = storage_path('app/backup_settings.json');
            $settings = [];

            if (file_exists($settingsPath)) {
                $decoded = json_decode((string) file_get_contents($settingsPath), true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }

            if ($backupFiles === []) {
                $autoBackupEnabled = (bool) ($settings['auto_backup_enabled'] ?? false);

                return [
                    'name' => 'Backup System',
                    'status' => $autoBackupEnabled ? 'warning' : 'offline',
                    'message' => $autoBackupEnabled
                        ? 'Backup otomatis aktif, tetapi belum ada file backup'
                        : 'Backup otomatis belum aktif dan belum ada file backup',
                ];
            }

            usort($backupFiles, static fn (string $left, string $right) => filemtime($right) <=> filemtime($left));
            $latestFile = $backupFiles[0];
            $lastRun = Carbon::createFromTimestamp(filemtime($latestFile));
            $lastRunLabel = $lastRun->format('d M Y H:i');
            $isFresh = $lastRun->greaterThanOrEqualTo(now()->subDays(2));

            return [
                'name' => 'Backup System',
                'status' => $isFresh ? 'online' : 'warning',
                'message' => $isFresh
                    ? "Backup file terakhir tersedia ({$lastRunLabel})"
                    : "Backup file terakhir terlalu lama ({$lastRunLabel})",
            ];
        } catch (\Throwable $exception) {
            return [
                'name' => 'Backup System',
                'status' => 'warning',
                'message' => 'Status backup tidak tersedia',
            ];
        }
    }

    private function resolveAttendanceStartTime(Absensi $attendance): Carbon
    {
        $candidate = null;
        $snapshot = $attendance->settings_snapshot;

        if (is_string($snapshot)) {
            $decoded = json_decode($snapshot, true);
            $snapshot = is_array($decoded) ? $decoded : null;
        }

        if (is_array($snapshot)) {
            $candidate = $snapshot['working_hours']['jam_masuk'] ?? null;
        }

        if (!$candidate && $attendance->attendanceSchema) {
            $hours = $attendance->attendanceSchema->getEffectiveWorkingHours($attendance->user);
            $candidate = $hours['jam_masuk'] ?? null;
        }

        if (!$candidate) {
            $candidate = $this->getGlobalDefaultJamMasuk($attendance->user);
        }

        try {
            return Carbon::parse((string) $candidate);
        } catch (\Throwable $e) {
            return Carbon::parse('07:00');
        }
    }

    private function getGlobalDefaultJamMasuk(?User $user = null): string
    {
        $schema = AttendanceSchema::query()
            ->where('schema_type', 'global')
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();

        if (!$schema instanceof AttendanceSchema) {
            $schema = AttendanceSchema::query()
                ->where('schema_type', 'global')
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->first();
        }

        if ($schema instanceof AttendanceSchema) {
            $hours = $schema->getEffectiveWorkingHours($user);
            return (string) ($hours['jam_masuk'] ?? '07:00');
        }

        return '07:00';
    }

    private function resolveAttendanceLocationName(Absensi $attendance, string $phase = 'masuk'): ?string
    {
        if ($phase === 'pulang') {
            if ($attendance->lokasiPulang?->nama_lokasi) {
                return $attendance->lokasiPulang->nama_lokasi;
            }

            return $this->resolveLocationNameFromCoordinates(
                $attendance->latitude_pulang,
                $attendance->longitude_pulang
            );
        }

        if ($attendance->lokasiMasuk?->nama_lokasi) {
            return $attendance->lokasiMasuk->nama_lokasi;
        }

        return $this->resolveLocationNameFromCoordinates(
            $attendance->latitude_masuk,
            $attendance->longitude_masuk
        );
    }

    private function resolveLocationNameFromCoordinates($latitude, $longitude): ?string
    {
        if ($latitude === null || $longitude === null) {
            return null;
        }

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $lat = (float) $latitude;
        $lng = (float) $longitude;
        $cacheKey = number_format($lat, 6, '.', '') . ',' . number_format($lng, 6, '.', '');
        if (array_key_exists($cacheKey, $this->resolvedLocationNameCache)) {
            return $this->resolvedLocationNameCache[$cacheKey];
        }

        $resolved = LokasiGps::checkValidLocation($lat, $lng);
        if (($resolved['valid'] ?? false) && isset($resolved['location']) && $resolved['location']) {
            $this->resolvedLocationNameCache[$cacheKey] = $resolved['location']->nama_lokasi ?? null;
            return $this->resolvedLocationNameCache[$cacheKey];
        }

        if (isset($resolved['nearest_location']) && $resolved['nearest_location']) {
            $this->resolvedLocationNameCache[$cacheKey] = $resolved['nearest_location']->nama_lokasi ?? null;
            return $this->resolvedLocationNameCache[$cacheKey];
        }

        $this->resolvedLocationNameCache[$cacheKey] = null;
        return null;
    }

    private function formatAttendanceStatusLabel(string $statusKey): string
    {
        return match ($statusKey) {
            'hadir' => 'Hadir',
            'terlambat' => 'Terlambat',
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alpha' => 'Alpha',
            'belum_absen' => 'Belum Absen',
            default => ucfirst(str_replace('_', ' ', $statusKey)),
        };
    }

    private function normalizeAttendanceStatusFilter($rawStatus): ?string
    {
        if (!is_string($rawStatus)) {
            return null;
        }

        $status = strtolower(trim($rawStatus));
        $map = [
            'hadir' => 'hadir',
            'terlambat' => 'terlambat',
            'izin' => 'izin',
            'sakit' => 'sakit',
            'alpha' => 'alpha',
            'belum absen' => 'belum_absen',
            'belum_absen' => 'belum_absen',
            'not checked in' => 'belum_absen',
        ];

        return $map[$status] ?? null;
    }

    private function isNonPresenceStatus(string $statusKey): bool
    {
        return in_array($statusKey, ['izin', 'sakit', 'alpha'], true);
    }
}

