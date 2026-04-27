<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\AttendanceAuditLog;
use App\Models\Izin;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ManualAttendanceService
{
    private const BACKDATE_OVERRIDE_PERMISSION = 'manual_attendance_backdate_override';
    private const MAX_PENDING_CHECKOUT_GAP_DAYS = 1;
    private const HISTORY_BUCKET_MANUAL = 'manual';
    private const HISTORY_BUCKET_CORRECTION = 'correction';
    private const HISTORY_BUCKET_AUTO_ALPHA = 'auto_alpha';
    private const ALPHA_STATUSES = ['alpha', 'alpa'];

    public function __construct(
        private readonly AttendanceTimeService $attendanceTimeService,
        private readonly AttendanceSnapshotService $attendanceSnapshotService,
    ) {
    }

    /**
     * Create manual attendance record
     */
    public function createManualAttendance(array $data, int $performedBy): array
    {
        DB::beginTransaction();

        try {
            // Validate user exists and has permission
            $targetUser = User::findOrFail($data['user_id']);
            $performer = User::findOrFail($performedBy);

            // Check if performer has permission to create manual attendance for this user
            if (!$this->canManageUserAttendance($performer, $targetUser)) {
                throw new \Exception('Anda tidak memiliki izin untuk mengelola absensi user ini');
            }

            // Check for existing attendance on the same date
            $existingAttendance = Absensi::where('user_id', $data['user_id'])
                ->whereDate('tanggal', $data['tanggal'])
                ->first();

            if ($existingAttendance) {
                throw new \Exception('User sudah memiliki data absensi pada tanggal tersebut');
            }

            $status = strtolower((string) ($data['status'] ?? 'hadir'));
            if ($status === 'terlambat' && empty($data['jam_masuk'])) {
                throw new \Exception('Jam masuk wajib diisi untuk status terlambat');
            }

            $this->assertLateStatusAllowed(
                $targetUser,
                (string) $data['tanggal'],
                $status,
                $data['jam_masuk'] ?? null
            );
            $this->assertAlphaStatusAllowed($targetUser, (string) $data['tanggal'], $status);

            // Prepare attendance data
            $attendanceData = [
                'user_id' => $data['user_id'],
                'kelas_id' => $data['kelas_id'] ?? $this->resolveDefaultKelasId($targetUser),
                'tanggal' => $data['tanggal'],
                'jam_masuk' => $data['jam_masuk'] ?? null,
                'jam_pulang' => $data['jam_pulang'] ?? null,
                'status' => $data['status'] ?? 'hadir',
                'keterangan' => $data['keterangan'] ?? null,
                'metode_absensi' => 'manual',
                'is_manual' => true,
                'latitude_masuk' => $data['latitude_masuk'] ?? null,
                'longitude_masuk' => $data['longitude_masuk'] ?? null,
                'latitude_pulang' => $data['latitude_pulang'] ?? null,
                'longitude_pulang' => $data['longitude_pulang'] ?? null,
                'device_info' => [
                    'manual_entry' => true,
                    'created_by' => $performer->nama_lengkap,
                    'created_at' => now()->toISOString()
                ],
                'ip_address' => request()->ip(),
            ];

            $snapshot = $this->attendanceSnapshotService->captureForUser($targetUser);
            if (($snapshot['attendance_setting_id'] ?? null) !== null) {
                $attendanceData['attendance_setting_id'] = (int) $snapshot['attendance_setting_id'];
            }
            if (!empty($snapshot['settings_snapshot'])) {
                $attendanceData['settings_snapshot'] = $snapshot['settings_snapshot'];
            }

            // Create attendance record
            $attendance = Absensi::create($attendanceData);

            // Create audit log
            AttendanceAuditLog::createLog(
                $attendance->id,
                'created',
                $performedBy,
                $data['reason'] ?? 'Manual attendance created',
                null,
                $attendanceData,
                [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->header('User-Agent'),
                    'target_user' => $targetUser->nama_lengkap,
                    'target_user_id' => $targetUser->id,
                ]
            );

            DB::commit();

            Log::info('Manual attendance created', [
                'attendance_id' => $attendance->id,
                'target_user_id' => $targetUser->id,
                'performed_by' => $performedBy,
                'date' => $data['tanggal']
            ]);

            return [
                'success' => true,
                'data' => $attendance->load(['user', 'auditLogs.performer']),
                'message' => 'Absensi manual berhasil dibuat'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create manual attendance', [
                'error' => $e->getMessage(),
                'data' => $data,
                'performed_by' => $performedBy
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update existing attendance record manually
     */
    public function updateManualAttendance(int $attendanceId, array $data, int $performedBy): array
    {
        DB::beginTransaction();

        try {
            $attendance = Absensi::findOrFail($attendanceId);
            $performer = User::findOrFail($performedBy);
            $targetUser = $attendance->user;

            // Check permission
            if (!$this->canManageUserAttendance($performer, $targetUser)) {
                throw new \Exception('Anda tidak memiliki izin untuk mengelola absensi user ini');
            }

            // Store old values for audit
            $oldValues = $attendance->toArray();

            // Prepare update data
            $updateData = [];
            $allowedFields = ['jam_masuk', 'jam_pulang', 'status', 'keterangan', 'latitude_masuk', 'longitude_masuk', 'latitude_pulang', 'longitude_pulang'];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }

            $effectiveStatus = strtolower((string) ($updateData['status'] ?? $attendance->status ?? ''));
            $effectiveJamMasuk = $updateData['jam_masuk'] ?? $attendance->jam_masuk;
            if ($effectiveStatus === 'terlambat' && empty($effectiveJamMasuk)) {
                throw new \Exception('Jam masuk wajib diisi untuk status terlambat');
            }

            $attendanceDate = $attendance->tanggal instanceof Carbon
                ? $attendance->tanggal->toDateString()
                : (string) $attendance->tanggal;
            $this->assertLateStatusAllowed(
                $targetUser,
                $attendanceDate,
                $effectiveStatus,
                $effectiveJamMasuk
            );
            $this->assertAlphaStatusAllowed($targetUser, (string) $attendance->tanggal, $effectiveStatus);

            $this->assertCheckoutAfterCheckin($attendance, $updateData);

            // Mark as manual if not already
            if (!$attendance->is_manual) {
                $updateData['is_manual'] = true;
                $updateData['metode_absensi'] = 'manual';
            }

            // Update device info to include manual modification
            $deviceInfo = $this->normalizeDeviceInfo($attendance->device_info);

            $deviceInfo['manual_modifications'] = $deviceInfo['manual_modifications'] ?? [];
            $deviceInfo['manual_modifications'][] = [
                'modified_by' => $performer->nama_lengkap,
                'modified_at' => now()->toISOString(),
                'reason' => $data['reason'] ?? 'Manual update'
            ];
            $updateData['device_info'] = $deviceInfo;

            // Update attendance
            $attendance->update($updateData);
            $attendance->refresh();

            // Create audit log
            AttendanceAuditLog::createLog(
                $attendance->id,
                'updated',
                $performedBy,
                $data['reason'] ?? 'Manual attendance updated',
                $oldValues,
                $attendance->toArray(),
                [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->header('User-Agent'),
                    'target_user' => $targetUser->nama_lengkap,
                    'target_user_id' => $targetUser->id,
                ]
            );

            DB::commit();

            Log::info('Manual attendance updated', [
                'attendance_id' => $attendance->id,
                'target_user_id' => $targetUser->id,
                'performed_by' => $performedBy
            ]);

            return [
                'success' => true,
                'data' => $attendance->load(['user', 'auditLogs.performer']),
                'message' => 'Absensi berhasil diperbarui'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update manual attendance', [
                'error' => $e->getMessage(),
                'attendance_id' => $attendanceId,
                'data' => $data,
                'performed_by' => $performedBy
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get users that can be managed by the performer
     */
    public function getManageableUsers(User $performer): array
    {
        return $this->buildManageableUsersQuery($performer)
            ->select(['id', 'nama_lengkap', 'email', 'status_kepegawaian'])
            ->with('roles:id,name,display_name')
            ->orderBy('nama_lengkap')
            ->get()
            ->toArray();
    }

    /**
     * Search manageable students with lightweight payload for mobile flows.
     */
    public function searchManageableUsers(User $performer, string $query = '', int $limit = 20): array
    {
        $limit = max(1, min($limit, 50));
        $search = trim($query);

        $users = $this->buildManageableUsersQuery($performer)
            ->select(['id', 'nama_lengkap', 'email', 'username', 'nis', 'nisn', 'nip', 'status_kepegawaian'])
            ->with([
                'kelas' => function ($kelasQuery) {
                    $kelasQuery
                        ->select('kelas.id', 'nama_kelas')
                        ->wherePivot('is_active', true)
                        ->orderBy('kelas.nama_kelas');
                },
            ]);

        if ($search !== '') {
            $searchLike = '%' . $search . '%';
            $users->where(function (Builder $builder) use ($searchLike) {
                $builder
                    ->where('nama_lengkap', 'like', $searchLike)
                    ->orWhere('username', 'like', $searchLike)
                    ->orWhere('email', 'like', $searchLike)
                    ->orWhere('nis', 'like', $searchLike)
                    ->orWhere('nisn', 'like', $searchLike)
                    ->orWhere('nip', 'like', $searchLike);
            });
        }

        return $users
            ->orderBy('nama_lengkap')
            ->limit($limit)
            ->get()
            ->map(function (User $user) {
                $activeClass = $user->kelas->first();

                return [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->email,
                    'username' => $user->username,
                    'nis' => $user->nis,
                    'nisn' => $user->nisn,
                    'nip' => $user->nip,
                    'status_kepegawaian' => $user->status_kepegawaian,
                    'kelas_nama' => $activeClass?->nama_kelas,
                    'identifier' => $user->nis ?: ($user->nisn ?: ($user->nip ?: ($user->email ?: $user->username))),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get attendance history with audit logs
     */
    public function getAttendanceHistory(array $filters = [], ?User $performer = null): array
    {
        $query = $this->buildAttendanceHistoryQuery($filters, $performer);

        $perPage = $filters['per_page'] ?? 15;
        $result = $query->orderBy('tanggal', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $result->toArray();
    }

    /**
     * Get manual attendance records for export.
     */
    public function getAttendanceExportData(array $filters = [], ?User $performer = null): array
    {
        return $this->buildAttendanceHistoryQuery($filters, $performer)
            ->orderBy('tanggal', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }

    /**
     * Get pending checkout list for follow-up (default: H+1 / yesterday).
     */
    public function getPendingCheckoutHistory(User $performer, array $filters = []): array
    {
        $query = $this->buildPendingCheckoutQuery($performer, $filters);
        $perPage = $filters['per_page'] ?? 15;

        return $query->orderBy('tanggal', 'desc')
            ->orderBy('jam_masuk', 'desc')
            ->paginate($perPage)
            ->toArray();
    }

    /**
     * Lightweight summary for mobile attendance management hub.
     */
    public function getMobileSummary(User $performer): array
    {
        $today = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();
        $canOverride = $this->hasBackdateOverridePermission($performer);

        $manageableStudentsCount = (clone $this->buildManageableUsersQuery($performer))
            ->distinct('users.id')
            ->count('users.id');

        $correctionTodayCount = $this->buildManualAttendanceQuery(
            $performer,
            [
                'bucket' => self::HISTORY_BUCKET_CORRECTION,
                'date' => $today,
            ],
            false
        )->count();

        $manualTodayCount = $this->buildManualAttendanceQuery(
            $performer,
            [
                'bucket' => self::HISTORY_BUCKET_MANUAL,
                'date' => $today,
            ],
            false
        )->count();

        $pendingCheckoutHPlusOneCount = (clone $this->buildPendingCheckoutQuery($performer, []))->count();

        $pendingCheckoutOverdueCount = $canOverride
            ? (clone $this->buildPendingCheckoutQuery($performer, ['include_overdue' => true]))
                ->whereDate('tanggal', '<', $yesterday)
                ->count()
            : 0;

        return [
            'manageable_students_count' => $manageableStudentsCount,
            'correction_today_count' => $correctionTodayCount,
            'manual_today_count' => $manualTodayCount,
            'pending_checkout_h_plus_one_count' => $pendingCheckoutHPlusOneCount,
            'pending_checkout_overdue_count' => $pendingCheckoutOverdueCount,
            'can_override_backdate' => $canOverride,
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Resolve missing checkout with H+1 policy and optional H+N override.
     */
    public function resolvePendingCheckout(int $attendanceId, array $data, int $performedBy): array
    {
        DB::beginTransaction();

        try {
            $attendance = Absensi::findOrFail($attendanceId);
            $performer = User::findOrFail($performedBy);
            $targetUser = $attendance->user;

            if (!$this->canManageUserAttendance($performer, $targetUser)) {
                throw new \Exception('Anda tidak memiliki izin untuk mengelola absensi user ini');
            }

            if (empty($attendance->jam_masuk)) {
                throw new \Exception('Data absensi ini belum memiliki jam masuk');
            }

            if (!empty($attendance->jam_pulang)) {
                throw new \Exception('Data absensi ini sudah memiliki jam pulang');
            }

            $attendanceDate = $this->resolveAttendanceDate($attendance);
            $today = Carbon::today();
            if ($attendanceDate->gte($today)) {
                throw new \Exception('Perbaikan lupa tap-out hanya bisa untuk tanggal sebelumnya');
            }

            $dayGap = $attendanceDate->diffInDays($today);
            $requiresOverride = $dayGap > self::MAX_PENDING_CHECKOUT_GAP_DAYS;
            if ($requiresOverride && !$this->hasBackdateOverridePermission($performer)) {
                throw new \Exception('Perbaikan hanya diizinkan maksimal H+1. Hubungi admin untuk override.');
            }
            if ($requiresOverride && empty($data['override_reason'])) {
                throw new \Exception('Alasan override wajib diisi untuk perbaikan di atas H+1');
            }

            $jamPulang = $this->parseTimeForAttendanceDate((string) $data['jam_pulang'], $attendanceDate);
            $jamMasuk = $this->parseTimeForAttendanceDate($attendance->jam_masuk, $attendanceDate);
            if (!$jamMasuk) {
                throw new \Exception('Jam masuk tidak valid pada data absensi');
            }

            if (!$jamPulang) {
                throw new \Exception('Jam pulang tidak valid');
            }

            if ($jamPulang->lte($jamMasuk)) {
                throw new \Exception('Jam pulang harus setelah jam masuk');
            }

            $maxCheckoutTime = $attendanceDate->copy()->setTime(23, 59, 59);
            if ($jamPulang->gt($maxCheckoutTime)) {
                throw new \Exception('Jam pulang maksimal 23:59 pada tanggal absensi');
            }

            $oldValues = $attendance->toArray();

            $deviceInfo = $this->normalizeDeviceInfo($attendance->device_info);
            $deviceInfo['manual_modifications'] = $deviceInfo['manual_modifications'] ?? [];
            $deviceInfo['manual_modifications'][] = [
                'modified_by' => $performer->nama_lengkap,
                'modified_at' => now()->toISOString(),
                'reason' => $data['reason'] ?? 'Manual resolve missing checkout',
                'action' => 'resolve_pending_checkout',
                'day_gap' => $dayGap,
                'override_used' => $requiresOverride,
            ];

            $attendance->update([
                'jam_pulang' => $jamPulang->format('H:i:s'),
                'status' => $data['status'] ?? $attendance->status,
                'keterangan' => $data['keterangan'] ?? $attendance->keterangan,
                'metode_absensi' => 'manual',
                'is_manual' => true,
                'device_info' => $deviceInfo,
                'latitude_pulang' => $data['latitude_pulang'] ?? $attendance->latitude_pulang,
                'longitude_pulang' => $data['longitude_pulang'] ?? $attendance->longitude_pulang,
            ]);
            $attendance->refresh();

            AttendanceAuditLog::createLog(
                $attendance->id,
                'corrected',
                $performedBy,
                $data['reason'] ?? 'Lupa tap-out diselesaikan manual',
                $oldValues,
                $attendance->toArray(),
                [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->header('User-Agent'),
                    'target_user' => $targetUser->nama_lengkap,
                    'target_user_id' => $targetUser->id,
                    'resolution_policy' => $requiresOverride ? 'override_h+n' : 'h+1',
                    'day_gap' => $dayGap,
                    'override_reason' => $data['override_reason'] ?? null,
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'data' => $attendance->load(['user', 'auditLogs.performer']),
                'message' => $requiresOverride
                    ? 'Lupa tap-out berhasil diperbaiki (override H+N)'
                    : 'Lupa tap-out berhasil diperbaiki (H+1)',
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to resolve pending checkout', [
                'error' => $e->getMessage(),
                'attendance_id' => $attendanceId,
                'performed_by' => $performedBy,
                'payload' => $data,
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build manual attendance query with role-based visibility scope.
     */
    public function buildManualAttendanceQuery(User $performer, array $filters = [], bool $withRelations = false): Builder
    {
        return $this->buildAttendanceHistoryQuery($filters, $performer, $withRelations);
    }

    public function calculateAlphaMinutesFromQuery(Builder $query): int
    {
        return (clone $query)
            ->with(['user'])
            ->get()
            ->sum(function ($attendance) {
                if (!$attendance instanceof Absensi) {
                    return 0;
                }

                $status = strtolower(trim((string) $attendance->status));
                if ($status === 'alpa') {
                    $status = 'alpha';
                }

                if ($status !== 'alpha') {
                    return 0;
                }

                return $this->resolveWorkingMinutesPerDayForUser($attendance->user);
            });
    }

    /**
     * Public wrapper for permission checks on target user.
     */
    public function canManageAttendanceForUser(User $performer, User $targetUser): bool
    {
        return $this->canManageUserAttendance($performer, $targetUser);
    }

    /**
     * Public wrapper for checking H+N override access.
     */
    public function hasBackdateOverrideAccess(User $performer): bool
    {
        return $this->hasBackdateOverridePermission($performer);
    }

    /**
     * Delete manual attendance record.
     */
    public function deleteManualAttendance(int $attendanceId, string $reason, int $performedBy): array
    {
        DB::beginTransaction();

        try {
            $attendance = Absensi::findOrFail($attendanceId);
            $performer = User::findOrFail($performedBy);
            $targetUser = $attendance->user;

            if (!$attendance->is_manual) {
                throw new \Exception('Hanya data absensi manual yang dapat dihapus');
            }

            if (!$this->canManageUserAttendance($performer, $targetUser)) {
                throw new \Exception('Anda tidak memiliki izin untuk menghapus absensi user ini');
            }

            $oldValues = $attendance->toArray();

            AttendanceAuditLog::createLog(
                $attendance->id,
                'deleted',
                $performedBy,
                $reason,
                $oldValues,
                null,
                [
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->header('User-Agent'),
                    'target_user' => $targetUser->nama_lengkap,
                    'target_user_id' => $targetUser->id,
                ]
            );

            $attendance->delete();

            DB::commit();

            Log::info('Manual attendance deleted', [
                'attendance_id' => $attendanceId,
                'target_user_id' => $targetUser->id,
                'performed_by' => $performedBy
            ]);

            return [
                'success' => true,
                'message' => 'Absensi manual berhasil dihapus'
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete manual attendance', [
                'error' => $e->getMessage(),
                'attendance_id' => $attendanceId,
                'performed_by' => $performedBy
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if performer can manage target user's attendance
     */
    private function canManageUserAttendance(User $performer, User $targetUser): bool
    {
        $isStudentTarget = $targetUser->hasRole(RoleNames::aliases(RoleNames::SISWA));
        if (!$isStudentTarget) {
            // Role non-siswa tidak dikelola modul absensi ini (menggunakan JSA).
            return false;
        }

        // Super Admin and Admin can manage anyone except other admins
        if ($this->isAdminLevelUser($performer)) {
            return true;
        }

        // Kepala Sekolah can manage anyone except admins
        if ($this->isPrincipalUser($performer)) {
            return true;
        }

        // Wakasek Kesiswaan can manage all students
        if ($this->isWakasekKesiswaanUser($performer)) {
            return true;
        }

        // Wali Kelas can only manage students in their class
        if ($this->isWaliKelasUser($performer)) {
            if (!$targetUser->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
                return false;
            }

            $kelasIds = $performer->kelasWali()->pluck('id')->toArray();
            if (empty($kelasIds)) {
                return false;
            }

            return $targetUser->kelas()
                ->whereIn('kelas.id', $kelasIds)
                ->wherePivot('is_active', true)
                ->exists();
        }

        return false;
    }

    /**
     * Build query for attendance history and export.
     */
    private function buildAttendanceHistoryQuery(array $filters = [], ?User $performer = null, bool $withRelations = true): Builder
    {
        $query = Absensi::query();
        $this->applyHistoryBucketScope($query, $filters);

        if ($withRelations) {
            $query->with(['user', 'auditLogs.performer']);
        }

        if ($performer) {
            $this->applyAttendanceVisibilityScope($query, $performer);
        }

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (!empty($filters['date'])) {
            $query->whereDate('tanggal', $filters['date']);
        }

        if (!empty($filters['start_date'])) {
            $query->whereDate('tanggal', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->whereDate('tanggal', '<=', $filters['end_date']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $searchLike = '%' . $search . '%';
                $query->where(function (Builder $builder) use ($searchLike) {
                    $builder
                        ->where('keterangan', 'like', $searchLike)
                        ->orWhereHas('user', function (Builder $userQuery) use ($searchLike) {
                            $userQuery
                                ->where('nama_lengkap', 'like', $searchLike)
                                ->orWhere('email', 'like', $searchLike)
                                ->orWhere('username', 'like', $searchLike)
                                ->orWhere('nis', 'like', $searchLike)
                                ->orWhere('nisn', 'like', $searchLike)
                                ->orWhere('nip', 'like', $searchLike);
                        });
                });
            }
        }

        return $query;
    }

    private function applyHistoryBucketScope(Builder $query, array $filters = []): void
    {
        $bucket = $this->resolveHistoryBucket($filters);

        if ($bucket === self::HISTORY_BUCKET_AUTO_ALPHA) {
            $query
                ->where('is_manual', false)
                ->whereIn('status', self::ALPHA_STATUSES);

            return;
        }

        if ($bucket === self::HISTORY_BUCKET_CORRECTION) {
            return;
        }

        $query->where('is_manual', true);
    }

    private function resolveHistoryBucket(array $filters = []): string
    {
        $bucket = trim(strtolower((string) ($filters['bucket'] ?? self::HISTORY_BUCKET_MANUAL)));

        return in_array($bucket, [self::HISTORY_BUCKET_MANUAL, self::HISTORY_BUCKET_CORRECTION, self::HISTORY_BUCKET_AUTO_ALPHA], true)
            ? $bucket
            : self::HISTORY_BUCKET_MANUAL;
    }

    private function resolveWorkingMinutesPerDayForUser(?User $user): int
    {
        $defaultMinutes = 8 * 60;

        if (!$user) {
            return $defaultMinutes;
        }

        try {
            $workingHours = $this->attendanceTimeService->getWorkingHours($user);
            $jamMasuk = trim((string) ($workingHours['jam_masuk'] ?? '07:00'));
            $jamPulang = trim((string) ($workingHours['jam_pulang'] ?? '15:00'));

            $start = strlen($jamMasuk) === 5
                ? Carbon::createFromFormat('H:i', $jamMasuk)
                : Carbon::createFromFormat('H:i:s', $jamMasuk);
            $end = strlen($jamPulang) === 5
                ? Carbon::createFromFormat('H:i', $jamPulang)
                : Carbon::createFromFormat('H:i:s', $jamPulang);

            $minutes = $start->diffInMinutes($end, false);

            return $minutes > 0 ? (int) $minutes : $defaultMinutes;
        } catch (\Throwable $e) {
            return $defaultMinutes;
        }
    }

    /**
     * Build query for records with check-in but missing check-out.
     */
    private function buildPendingCheckoutQuery(User $performer, array $filters = []): Builder
    {
        $query = Absensi::query()
            ->with(['user', 'kelas', 'auditLogs.performer'])
            ->whereNotNull('jam_masuk')
            ->whereNull('jam_pulang');

        $this->applyAttendanceVisibilityScope($query, $performer);

        if (!empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        $includeOverdue = (bool) ($filters['include_overdue'] ?? false);
        $canOverride = $this->hasBackdateOverridePermission($performer);

        if (!empty($filters['date'])) {
            $query->whereDate('tanggal', $filters['date']);
        } elseif (!empty($filters['start_date']) || !empty($filters['end_date'])) {
            if (!empty($filters['start_date'])) {
                $query->whereDate('tanggal', '>=', $filters['start_date']);
            }

            if (!empty($filters['end_date'])) {
                $query->whereDate('tanggal', '<=', $filters['end_date']);
            }
        } elseif ($includeOverdue && $canOverride) {
            $query->whereDate('tanggal', '<=', Carbon::yesterday()->toDateString());
        } else {
            $query->whereDate('tanggal', Carbon::yesterday()->toDateString());
        }

        return $query;
    }

    /**
     * Apply manual attendance visibility rules to query.
     */
    private function applyAttendanceVisibilityScope(Builder $query, User $performer): void
    {
        if ($this->isAdminLevelUser($performer) || $this->isPrincipalUser($performer)) {
            $query->whereHas('user', function ($userQuery) {
                $userQuery->whereHas('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
                });
            });
            return;
        }

        if ($this->isWakasekKesiswaanUser($performer)) {
            $query->whereHas('user', function ($userQuery) {
                $this->applyStudentRoleScope($userQuery);
            });
            return;
        }

        if ($this->isWaliKelasUser($performer)) {
            $kelasIds = $performer->kelasWali()->pluck('id')->toArray();

            if (empty($kelasIds)) {
                $query->whereRaw('1 = 0');
                return;
            }

            $query->whereHas('user', function ($userQuery) use ($kelasIds) {
                $userQuery->whereHas('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
                })->whereHas('kelas', function ($kelasQuery) use ($kelasIds) {
                    $kelasQuery->whereIn('kelas.id', $kelasIds)
                        ->where('kelas_siswa.is_active', true);
                });
            });
            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * Check whether performer has admin-level access.
     */
    private function isAdminLevelUser(User $user): bool
    {
        return $user->hasRole(array_merge(
            RoleNames::aliases(RoleNames::SUPER_ADMIN),
            RoleNames::aliases(RoleNames::ADMIN)
        ));
    }

    /**
     * Check whether performer has kepala sekolah role.
     */
    private function isPrincipalUser(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::KEPALA_SEKOLAH));
    }

    /**
     * Check whether performer has wali kelas role alias.
     */
    private function isWaliKelasUser(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS));
    }

    /**
     * Check whether performer has wakasek kesiswaan role alias.
     */
    private function isWakasekKesiswaanUser(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::WAKASEK_KESISWAAN));
    }

    /**
     * Apply siswa-role only filter on user query.
     */
    private function applyStudentRoleScope($query): void
    {
        $query->whereHas('roles', function ($roleQuery) {
            $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
        });
    }

    /**
     * Base manageable-user scope shared by web and mobile flows.
     */
    private function buildManageableUsersQuery(User $performer): Builder
    {
        $query = User::query();

        if ($this->isAdminLevelUser($performer) || $this->isPrincipalUser($performer) || $this->isWakasekKesiswaanUser($performer)) {
            $this->applyStudentRoleScope($query);
            return $query;
        }

        if ($this->isWaliKelasUser($performer)) {
            $kelasIds = $performer->kelasWali()->pluck('id')->toArray();

            if (empty($kelasIds)) {
                $query->whereRaw('1 = 0');
                return $query;
            }

            $query->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })->whereHas('kelas', function ($kelasQuery) use ($kelasIds) {
                $kelasQuery->whereIn('kelas.id', $kelasIds)
                    ->where('kelas_siswa.is_active', true);
            });

            return $query;
        }

        $query->whereRaw('1 = 0');

        return $query;
    }

    /**
     * Check whether performer has override permission for H+N corrections.
     */
    private function hasBackdateOverridePermission(User $user): bool
    {
        return $user->can(self::BACKDATE_OVERRIDE_PERMISSION);
    }

    /**
     * Ensure checkout is always after checkin when either value is updated.
     */
    private function assertCheckoutAfterCheckin(Absensi $attendance, array $updateData): void
    {
        $attendanceDate = $this->resolveAttendanceDate($attendance);
        $jamMasuk = array_key_exists('jam_masuk', $updateData)
            ? $this->parseTimeForAttendanceDate($updateData['jam_masuk'], $attendanceDate)
            : $this->parseTimeForAttendanceDate($attendance->jam_masuk, $attendanceDate);
        $jamPulang = array_key_exists('jam_pulang', $updateData)
            ? $this->parseTimeForAttendanceDate($updateData['jam_pulang'], $attendanceDate)
            : $this->parseTimeForAttendanceDate($attendance->jam_pulang, $attendanceDate);

        if ($jamMasuk && $jamPulang && $jamPulang->lte($jamMasuk)) {
            throw new \Exception('Jam pulang harus setelah jam masuk');
        }
    }

    /**
     * Resolve attendance date as Carbon startOfDay.
     */
    private function resolveAttendanceDate(Absensi $attendance): Carbon
    {
        if ($attendance->tanggal instanceof Carbon) {
            return $attendance->tanggal->copy()->startOfDay();
        }

        return Carbon::parse((string) $attendance->tanggal)->startOfDay();
    }

    /**
     * Parse time/datetime input and pin date-only time to attendance date.
     */
    private function parseTimeForAttendanceDate($value, Carbon $attendanceDate): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $attendanceDate->copy()->setTime(
                $value->hour,
                $value->minute,
                $value->second
            );
        }

        $timeString = trim((string) $value);
        if ($timeString === '') {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $timeString)) {
            return Carbon::createFromFormat('Y-m-d H:i', $attendanceDate->format('Y-m-d') . ' ' . $timeString);
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $timeString)) {
            return Carbon::createFromFormat('Y-m-d H:i:s', $attendanceDate->format('Y-m-d') . ' ' . $timeString);
        }

        return Carbon::parse($timeString);
    }

    /**
     * Normalize device_info payload to array.
     */
    private function normalizeDeviceInfo($deviceInfoRaw): array
    {
        if (is_string($deviceInfoRaw)) {
            return json_decode($deviceInfoRaw, true) ?? [];
        }

        if (is_array($deviceInfoRaw)) {
            return $deviceInfoRaw;
        }

        return [];
    }

    /**
     * Resolve default class id for target user based on active class membership.
     */
    private function resolveDefaultKelasId(User $targetUser): ?int
    {
        $activeClass = $targetUser->kelas()
            ->wherePivot('is_active', true)
            ->first();

        if ($activeClass) {
            return (int) $activeClass->id;
        }

        $fallbackClass = $targetUser->kelas()->first();
        return $fallbackClass ? (int) $fallbackClass->id : null;
    }

    /**
     * Validate attendance data
     */
    public function validateAttendanceData(array $data): array
    {
        $errors = [];
        $targetUser = null;
        $parsedDate = null;

        // Required fields
        if (empty($data['user_id'])) {
            $errors['user_id'] = 'User ID is required';
        } else {
            $targetUser = User::find((int) $data['user_id']);
        }

        if (empty($data['tanggal'])) {
            $errors['tanggal'] = 'Tanggal is required';
        }

        // Date validation
        if (!empty($data['tanggal'])) {
            try {
                $parsedDate = Carbon::parse((string) $data['tanggal'])->startOfDay();
                if ($parsedDate->isFuture()) {
                    $errors['tanggal'] = 'Tanggal tidak boleh di masa depan';
                }
            } catch (\Exception $e) {
                $errors['tanggal'] = 'Format tanggal tidak valid';
            }
        }

        // Time validation
        if (!empty($data['jam_masuk']) && !empty($data['jam_pulang'])) {
            try {
                $jamMasuk = Carbon::parse($data['jam_masuk']);
                $jamPulang = Carbon::parse($data['jam_pulang']);

                if ($jamPulang->lte($jamMasuk)) {
                    $errors['jam_pulang'] = 'Jam pulang harus setelah jam masuk';
                }
            } catch (\Exception $e) {
                $errors['time'] = 'Format waktu tidak valid';
            }
        }

        // Status validation
        if (!empty($data['status'])) {
            $validStatuses = ['hadir', 'terlambat', 'izin', 'sakit', 'alpha'];
            if (!in_array(strtolower($data['status']), $validStatuses)) {
                $errors['status'] = 'Status tidak valid';
            }
        }

        $status = strtolower((string) ($data['status'] ?? ''));
        if ($status === 'terlambat' && empty($data['jam_masuk'])) {
            $errors['jam_masuk'] = 'Jam masuk wajib diisi untuk status terlambat';
        }

        if (
            $status === 'terlambat' &&
            !isset($errors['jam_masuk']) &&
            $targetUser instanceof User &&
            $parsedDate instanceof Carbon
        ) {
            try {
                $this->assertLateStatusAllowed(
                    $targetUser,
                    $parsedDate->toDateString(),
                    $status,
                    $data['jam_masuk'] ?? null
                );
            } catch (\Exception $e) {
                $errors['jam_masuk'] = $e->getMessage();
            }
        }

        if ($status === 'alpha' && $targetUser instanceof User && $parsedDate instanceof Carbon) {
            try {
                $this->assertAlphaStatusAllowed($targetUser, $parsedDate->toDateString(), $status);
            } catch (\Exception $e) {
                $errors['status'] = $e->getMessage();
            }
        }

        return $errors;
    }

    private function assertAlphaStatusAllowed(User $targetUser, string $tanggal, string $status): void
    {
        if ($status !== 'alpha') {
            return;
        }

        $targetDate = Carbon::parse($tanggal)->startOfDay();

        if (!$this->attendanceTimeService->isAttendanceRequiredOnDate($targetUser, $targetDate->copy())) {
            throw new \Exception('Status alpha tidak boleh dibuat pada tanggal yang tidak mewajibkan absensi');
        }

        if (!$this->attendanceTimeService->isWorkingDayForDate($targetUser, $targetDate->copy())) {
            throw new \Exception('Status alpha tidak boleh dibuat pada hari libur atau hari non-kerja');
        }

        if ($this->hasApprovedLeaveCoverage($targetUser, $targetDate)) {
            throw new \Exception('Status alpha tidak boleh dibuat pada tanggal yang sudah tercakup izin yang disetujui');
        }
    }

    private function hasApprovedLeaveCoverage(User $targetUser, Carbon $targetDate): bool
    {
        return Izin::query()
            ->where('user_id', $targetUser->id)
            ->where('status', 'approved')
            ->whereDate('tanggal_mulai', '<=', $targetDate->toDateString())
            ->whereDate('tanggal_selesai', '>=', $targetDate->toDateString())
            ->exists();
    }

    private function assertLateStatusAllowed(User $targetUser, string $tanggal, string $status, $jamMasuk): void
    {
        if ($status !== 'terlambat') {
            return;
        }

        $targetDate = Carbon::parse($tanggal)->startOfDay();
        $lateMinutes = $this->calculateLateMinutesFromScheduledStart($targetUser, $targetDate, $jamMasuk);

        if ($lateMinutes <= 0) {
            throw new \Exception('Jam masuk harus melebihi jam masuk terjadwal untuk status terlambat');
        }
    }

    private function calculateLateMinutesFromScheduledStart(User $targetUser, Carbon $targetDate, $jamMasuk): int
    {
        $actualCheckIn = $this->parseTimeForAttendanceDate($jamMasuk, $targetDate);
        if (!$actualCheckIn) {
            return 0;
        }

        $workingHours = $this->attendanceTimeService->getWorkingHoursForDate($targetUser, $targetDate->copy());
        $scheduledCheckIn = $this->parseTimeForAttendanceDate((string) ($workingHours['jam_masuk'] ?? '07:00'), $targetDate);

        if (!$scheduledCheckIn) {
            return 0;
        }

        return $actualCheckIn->gt($scheduledCheckIn)
            ? (int) $actualCheckIn->diffInMinutes($scheduledCheckIn, true)
            : 0;
    }
}
