<?php

namespace App\Services;

use App\Jobs\DispatchAttendanceWhatsappNotification;
use App\Models\Absensi;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\ManualAttendanceIncidentBatch;
use App\Models\ManualAttendanceIncidentBatchItem;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

class ManualAttendanceIncidentService
{
    private const SCOPE_ALL = 'all_manageable';
    private const SCOPE_CLASSES = 'classes';
    private const SCOPE_LEVELS = 'levels';
    private const FAILURE_SAMPLE_LIMIT = 25;
    private const PROCESS_CHUNK_SIZE = 100;
    private const EXPORT_GROUP_ALL = 'all';
    private const EXPORT_GROUP_CREATED = 'created';
    private const EXPORT_GROUP_SKIPPED = 'skipped';
    private const EXPORT_GROUP_FAILED = 'failed';

    public function __construct(
        private readonly ManualAttendanceService $manualAttendanceService,
        private readonly AttendanceTimeService $attendanceTimeService,
    ) {
    }

    public function getScopeOptions(User $performer): array
    {
        $manageableClasses = $this->buildManageableClassesQuery($performer)
            ->with('tingkat:id,nama')
            ->get()
            ->map(function (Kelas $kelas) {
                return [
                    'id' => $kelas->id,
                    'tingkat_id' => $kelas->tingkat_id,
                    'nama_kelas' => $kelas->nama_kelas,
                    'nama_lengkap' => $kelas->nama_lengkap,
                    'tingkat' => $kelas->tingkat?->nama,
                    'jurusan' => $kelas->jurusan,
                    'active_students_count' => (int) ($kelas->active_students_count ?? 0),
                ];
            });

        $classes = $manageableClasses->values()->all();
        $levels = $manageableClasses
            ->filter(fn (array $kelas) => !empty($kelas['tingkat_id']))
            ->groupBy('tingkat_id')
            ->map(function (Collection $groupedClasses) {
                $first = $groupedClasses->first();

                return [
                    'id' => (int) $first['tingkat_id'],
                    'nama' => (string) ($first['tingkat'] ?? 'Tingkat'),
                    'active_students_count' => (int) $groupedClasses->sum('active_students_count'),
                    'classes_count' => $groupedClasses->count(),
                ];
            })
            ->sortBy('nama')
            ->values()
            ->all();

        $manageableStudentsCount = $this->buildIncidentScopeStudentsQuery($performer, [
            'scope_type' => self::SCOPE_ALL,
        ])->count();

        return [
            'scope_types' => [
                ['value' => self::SCOPE_ALL, 'label' => 'Semua siswa terkelola'],
                ['value' => self::SCOPE_CLASSES, 'label' => 'Kelas terpilih'],
                ['value' => self::SCOPE_LEVELS, 'label' => 'Tingkat terpilih'],
            ],
            'classes' => $classes,
            'levels' => $levels,
            'summary' => [
                'manageable_students_count' => $manageableStudentsCount,
                'manageable_classes_count' => count($classes),
                'manageable_levels_count' => count($levels),
            ],
        ];
    }

    public function preview(User $performer, array $payload): array
    {
        $date = Carbon::parse((string) $payload['tanggal'])->startOfDay();
        $scopeUsers = $this->buildIncidentScopeStudentsQuery($performer, $payload)
            ->select(['users.id', 'users.nama_lengkap', 'users.email'])
            ->with([
                'kelas' => function ($query) {
                    $query->select(['kelas.id', 'kelas.nama_kelas', 'kelas.tingkat_id', 'kelas.jurusan'])
                        ->with('tingkat:id,nama')
                        ->wherePivot('is_active', true);
                },
            ])
            ->get();

        return $this->buildPreviewSummary($scopeUsers, $date, $payload);
    }

    public function createBatch(User $performer, array $payload): ManualAttendanceIncidentBatch
    {
        $preview = $this->preview($performer, $payload);

        return ManualAttendanceIncidentBatch::create([
            'created_by' => $performer->id,
            'status' => 'queued',
            'tanggal' => $payload['tanggal'],
            'scope_type' => $payload['scope_type'],
            'scope_payload' => [
                'kelas_ids' => array_values(array_map('intval', $payload['kelas_ids'] ?? [])),
                'tingkat_ids' => array_values(array_map('intval', $payload['tingkat_ids'] ?? [])),
            ],
            'attendance_status' => $payload['status'],
            'jam_masuk' => $payload['jam_masuk'] ?? null,
            'jam_pulang' => $payload['jam_pulang'] ?? null,
            'keterangan' => $payload['keterangan'] ?? null,
            'reason' => $payload['reason'],
            'progress_percentage' => 0,
            'total_scope_users' => (int) ($preview['total_scope_users'] ?? 0),
            'total_candidates' => (int) ($preview['eligible_missing_count'] ?? 0),
            'preview_summary' => $preview,
        ]);
    }

    public function processBatch(int $batchId): void
    {
        /** @var ManualAttendanceIncidentBatch $batch */
        $batch = ManualAttendanceIncidentBatch::query()->findOrFail($batchId);
        if (!in_array($batch->status, ['queued', 'processing'], true)) {
            return;
        }

        $performer = User::query()->findOrFail($batch->created_by);
        $date = Carbon::parse((string) $batch->tanggal)->startOfDay();

        $payload = [
            'scope_type' => $batch->scope_type,
            'kelas_ids' => $batch->scope_payload['kelas_ids'] ?? [],
            'tingkat_ids' => $batch->scope_payload['tingkat_ids'] ?? [],
            'tanggal' => $date->toDateString(),
            'status' => $batch->attendance_status,
            'jam_masuk' => $batch->jam_masuk,
            'jam_pulang' => $batch->jam_pulang,
            'keterangan' => $batch->keterangan,
            'reason' => $batch->reason,
        ];

        $scopeUsersQuery = $this->buildIncidentScopeStudentsQuery($performer, $payload)
            ->select(['users.id', 'users.nama_lengkap', 'users.email'])
            ->with([
                'kelas' => function ($query) {
                    $query->select(['kelas.id', 'kelas.nama_kelas', 'kelas.tingkat_id', 'kelas.jurusan'])
                        ->with('tingkat:id,nama')
                        ->wherePivot('is_active', true);
                },
            ]);

        $totalScopeUsers = (int) $scopeUsersQuery->count();

        $batch->update([
            'status' => 'processing',
            'started_at' => $batch->started_at ?? now(),
            'failed_at' => null,
            'completed_at' => null,
            'error_message' => null,
            'total_scope_users' => $totalScopeUsers,
            'processed_count' => 0,
            'created_count' => 0,
            'skipped_existing_count' => 0,
            'skipped_leave_count' => 0,
            'skipped_non_required_count' => 0,
            'skipped_non_working_count' => 0,
            'failed_count' => 0,
            'sample_failures' => [],
            'progress_percentage' => 0,
        ]);

        ManualAttendanceIncidentBatchItem::query()
            ->where('batch_id', $batch->id)
            ->delete();

        $processedCount = 0;
        $createdCount = 0;
        $skippedExisting = 0;
        $skippedLeave = 0;
        $skippedNonRequired = 0;
        $skippedNonWorking = 0;
        $failedCount = 0;
        $sampleFailures = [];

        $scopeUsersQuery->chunk(self::PROCESS_CHUNK_SIZE, function (Collection $users) use (
            &$processedCount,
            &$createdCount,
            &$skippedExisting,
            &$skippedLeave,
            &$skippedNonRequired,
            &$skippedNonWorking,
            &$failedCount,
            &$sampleFailures,
            $performer,
            $date,
            $payload,
            $batch,
            $totalScopeUsers
        ) {
            $batchItemRows = [];
            $userIds = $users->pluck('id')->map(fn ($id) => (int) $id)->all();

            $existingUserIds = Absensi::query()
                ->whereDate('tanggal', $date->toDateString())
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $existingLookup = array_fill_keys($existingUserIds, true);

            $leaveUserIds = Izin::query()
                ->where('status', 'approved')
                ->whereDate('tanggal_mulai', '<=', $date->toDateString())
                ->whereDate('tanggal_selesai', '>=', $date->toDateString())
                ->whereIn('user_id', $userIds)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $leaveLookup = array_fill_keys($leaveUserIds, true);

            foreach ($users as $user) {
                $processedCount++;
                $baseItem = $this->buildBatchItemRow($batch, $user);

                if (isset($existingLookup[$user->id])) {
                    $skippedExisting++;
                    $batchItemRows[] = $baseItem + [
                        'result_code' => 'skipped_existing',
                        'result_label' => 'Skip: Sudah Ada Absensi',
                        'message' => 'Sudah ada data absensi pada tanggal tersebut',
                        'attendance_id' => null,
                        'processed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    continue;
                }

                if (isset($leaveLookup[$user->id])) {
                    $skippedLeave++;
                    $batchItemRows[] = $baseItem + [
                        'result_code' => 'skipped_leave',
                        'result_label' => 'Skip: Sudah Izin',
                        'message' => 'Sudah memiliki izin yang disetujui pada tanggal tersebut',
                        'attendance_id' => null,
                        'processed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    continue;
                }

                if (!$this->attendanceTimeService->isAttendanceRequiredOnDate($user, $date->copy())) {
                    $skippedNonRequired++;
                    $batchItemRows[] = $baseItem + [
                        'result_code' => 'skipped_non_required',
                        'result_label' => 'Skip: Tidak Wajib Absen',
                        'message' => 'Schema efektif pada tanggal tersebut tidak mewajibkan absensi',
                        'attendance_id' => null,
                        'processed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    continue;
                }

                if (!$this->attendanceTimeService->isWorkingDayForDate($user, $date->copy())) {
                    $skippedNonWorking++;
                    $batchItemRows[] = $baseItem + [
                        'result_code' => 'skipped_non_working',
                        'result_label' => 'Skip: Bukan Hari Kerja',
                        'message' => 'Tanggal tersebut bukan hari kerja efektif untuk siswa ini',
                        'attendance_id' => null,
                        'processed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    continue;
                }

                $result = $this->manualAttendanceService->createManualAttendance([
                    'user_id' => $user->id,
                    'tanggal' => $date->toDateString(),
                    'status' => $payload['status'],
                    'jam_masuk' => $payload['jam_masuk'] ?: null,
                    'jam_pulang' => $payload['jam_pulang'] ?: null,
                    'keterangan' => $payload['keterangan'] ?: null,
                    'reason' => $payload['reason'],
                ], $performer->id);

                if ($result['success']) {
                    $attendance = $result['data'] instanceof Absensi ? $result['data'] : null;
                    $this->queueManualCreateWhatsappNotifications($attendance, 'manual attendance incident');

                    $createdCount++;
                    $batchItemRows[] = $baseItem + [
                        'result_code' => 'created',
                        'result_label' => 'Dibuat',
                        'message' => $result['message'] ?? 'Absensi berhasil dibuat',
                        'attendance_id' => $result['data']['id'] ?? null,
                        'processed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    continue;
                }

                $failedCount++;
                $failureMessage = $result['message'] ?? 'Gagal membuat absensi insiden';
                if (count($sampleFailures) < self::FAILURE_SAMPLE_LIMIT) {
                    $sampleFailures[] = [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'message' => $failureMessage,
                    ];
                }

                $batchItemRows[] = $baseItem + [
                    'result_code' => 'failed',
                    'result_label' => 'Gagal',
                    'message' => $failureMessage,
                    'attendance_id' => null,
                    'processed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if (!empty($batchItemRows)) {
                DB::table('manual_attendance_incident_batch_items')->upsert(
                    $batchItemRows,
                    ['batch_id', 'user_id'],
                    [
                        'kelas_id',
                        'tingkat_id',
                        'attendance_id',
                        'nama_lengkap',
                        'email',
                        'kelas_label',
                        'tingkat_label',
                        'result_code',
                        'result_label',
                        'message',
                        'processed_at',
                        'updated_at',
                    ]
                );
            }

            $candidateProcessed = max(
                0,
                $createdCount + $skippedExisting + $skippedLeave + $skippedNonRequired + $skippedNonWorking + $failedCount
            );
            $totalCandidates = max($totalScopeUsers, 1);

            $batch->forceFill([
                'processed_count' => $processedCount,
                'created_count' => $createdCount,
                'skipped_existing_count' => $skippedExisting,
                'skipped_leave_count' => $skippedLeave,
                'skipped_non_required_count' => $skippedNonRequired,
                'skipped_non_working_count' => $skippedNonWorking,
                'failed_count' => $failedCount,
                'sample_failures' => $sampleFailures,
                'progress_percentage' => min(
                    99,
                    (int) floor(($candidateProcessed / $totalCandidates) * 100)
                ),
            ])->save();
        });

        $batch->forceFill([
            'status' => 'completed',
            'processed_count' => $processedCount,
            'created_count' => $createdCount,
            'skipped_existing_count' => $skippedExisting,
            'skipped_leave_count' => $skippedLeave,
            'skipped_non_required_count' => $skippedNonRequired,
            'skipped_non_working_count' => $skippedNonWorking,
            'failed_count' => $failedCount,
            'sample_failures' => $sampleFailures,
            'progress_percentage' => 100,
            'completed_at' => now(),
            'total_candidates' => max(0, $createdCount + $failedCount),
        ])->save();
    }

    public function markBatchFailed(int $batchId, string $message): void
    {
        ManualAttendanceIncidentBatch::query()
            ->whereKey($batchId)
            ->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $message,
            ]);
    }

    public function getBatchForUser(User $performer, int $batchId): ManualAttendanceIncidentBatch
    {
        $query = ManualAttendanceIncidentBatch::query()->with('creator:id,nama_lengkap');

        if (!$this->isAdminLevelUser($performer) && !$this->isPrincipalUser($performer)) {
            $query->where('created_by', $performer->id);
        }

        return $query->findOrFail($batchId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentBatchesForUser(User $performer, int $limit = 8): array
    {
        $safeLimit = max(1, min(20, $limit));

        $query = ManualAttendanceIncidentBatch::query()
            ->with('creator:id,nama_lengkap')
            ->latest('id')
            ->limit($safeLimit);

        if (!$this->isAdminLevelUser($performer) && !$this->isPrincipalUser($performer)) {
            $query->where('created_by', $performer->id);
        }

        return $query->get()
            ->map(fn (ManualAttendanceIncidentBatch $batch) => $this->serializeBatch($batch))
            ->all();
    }

    public function serializeBatch(ManualAttendanceIncidentBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'status' => $batch->status,
            'tanggal' => $batch->tanggal?->toDateString(),
            'scope_type' => $batch->scope_type,
            'scope_payload' => $batch->scope_payload ?? [],
            'attendance_status' => $batch->attendance_status,
            'jam_masuk' => $batch->jam_masuk,
            'jam_pulang' => $batch->jam_pulang,
            'keterangan' => $batch->keterangan,
            'reason' => $batch->reason,
            'progress_percentage' => (int) $batch->progress_percentage,
            'total_scope_users' => (int) $batch->total_scope_users,
            'total_candidates' => (int) $batch->total_candidates,
            'processed_count' => (int) $batch->processed_count,
            'created_count' => (int) $batch->created_count,
            'skipped_existing_count' => (int) $batch->skipped_existing_count,
            'skipped_leave_count' => (int) $batch->skipped_leave_count,
            'skipped_non_required_count' => (int) $batch->skipped_non_required_count,
            'skipped_non_working_count' => (int) $batch->skipped_non_working_count,
            'failed_count' => (int) $batch->failed_count,
            'preview_summary' => $batch->preview_summary ?? [],
            'sample_failures' => $batch->sample_failures ?? [],
            'result_export_available' => ($batch->processed_count ?? 0) > 0,
            'started_at' => optional($batch->started_at)?->toISOString(),
            'completed_at' => optional($batch->completed_at)?->toISOString(),
            'failed_at' => optional($batch->failed_at)?->toISOString(),
            'error_message' => $batch->error_message,
            'creator' => $batch->creator ? [
                'id' => $batch->creator->id,
                'nama_lengkap' => $batch->creator->nama_lengkap,
            ] : null,
        ];
    }

    private function buildPreviewSummary(Collection $scopeUsers, Carbon $date, array $payload): array
    {
        $userIds = $scopeUsers->pluck('id')->map(fn ($id) => (int) $id)->all();

        $existingAttendanceIds = Absensi::query()
            ->whereDate('tanggal', $date->toDateString())
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $existingLookup = array_fill_keys($existingAttendanceIds, true);

        $approvedLeaveIds = Izin::query()
            ->where('status', 'approved')
            ->whereDate('tanggal_mulai', '<=', $date->toDateString())
            ->whereDate('tanggal_selesai', '>=', $date->toDateString())
            ->whereIn('user_id', $userIds)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $leaveLookup = array_fill_keys($approvedLeaveIds, true);

        $eligibleStudents = [];
        $existingCount = 0;
        $approvedLeaveCount = 0;
        $nonRequiredCount = 0;
        $nonWorkingCount = 0;

        foreach ($scopeUsers as $user) {
            if (isset($existingLookup[$user->id])) {
                $existingCount++;
                continue;
            }

            if (isset($leaveLookup[$user->id])) {
                $approvedLeaveCount++;
                continue;
            }

            if (!$this->attendanceTimeService->isAttendanceRequiredOnDate($user, $date->copy())) {
                $nonRequiredCount++;
                continue;
            }

            if (!$this->attendanceTimeService->isWorkingDayForDate($user, $date->copy())) {
                $nonWorkingCount++;
                continue;
            }

            if (count($eligibleStudents) < 15) {
                $kelas = $user->kelas->first();
                $eligibleStudents[] = [
                    'id' => $user->id,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->email,
                    'kelas' => $kelas?->nama_lengkap,
                ];
            }
        }

        return [
            'tanggal' => $date->toDateString(),
            'scope_type' => $payload['scope_type'],
            'scope_label' => $this->resolveScopeLabel($payload),
            'total_scope_users' => $scopeUsers->count(),
            'existing_attendance_count' => $existingCount,
            'approved_leave_count' => $approvedLeaveCount,
            'non_required_count' => $nonRequiredCount,
            'non_working_day_count' => $nonWorkingCount,
            'eligible_missing_count' => max(
                0,
                $scopeUsers->count() - $existingCount - $approvedLeaveCount - $nonRequiredCount - $nonWorkingCount
            ),
            'sample_eligible_students' => $eligibleStudents,
        ];
    }

    private function buildIncidentScopeStudentsQuery(User $performer, array $payload): Builder
    {
        $scopeType = strtolower((string) ($payload['scope_type'] ?? self::SCOPE_ALL));
        $kelasIds = collect($payload['kelas_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();
        $tingkatIds = collect($payload['tingkat_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->values()
            ->all();

        $query = User::query()
            ->select('users.*')
            ->where('users.is_active', true)
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->whereHas('kelas', function ($kelasQuery) {
                $kelasQuery->where('kelas.is_active', true)
                    ->where('kelas_siswa.is_active', true);
            });

        if ($this->isAdminLevelUser($performer) || $this->isPrincipalUser($performer) || $this->isWakasekKesiswaanUser($performer)) {
            if ($scopeType === self::SCOPE_CLASSES && !empty($kelasIds)) {
                $query->whereHas('kelas', function ($kelasQuery) use ($kelasIds) {
                    $kelasQuery->whereIn('kelas.id', $kelasIds)
                        ->where('kelas.is_active', true)
                        ->where('kelas_siswa.is_active', true);
                });
            }

            if ($scopeType === self::SCOPE_LEVELS && !empty($tingkatIds)) {
                $query->whereHas('kelas', function ($kelasQuery) use ($tingkatIds) {
                    $kelasQuery->whereIn('kelas.tingkat_id', $tingkatIds)
                        ->where('kelas.is_active', true)
                        ->where('kelas_siswa.is_active', true);
                });
            }

            return $query;
        }

        if ($this->isWaliKelasUser($performer)) {
            $manageableKelasIds = $performer->kelasWali()
                ->where('is_active', true)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            if (empty($manageableKelasIds)) {
                return $query->whereRaw('1 = 0');
            }

            $targetKelasIds = $scopeType === self::SCOPE_CLASSES && !empty($kelasIds)
                ? array_values(array_intersect($manageableKelasIds, $kelasIds))
                : $manageableKelasIds;

            if ($scopeType === self::SCOPE_LEVELS && !empty($tingkatIds)) {
                return $query->whereHas('kelas', function ($kelasQuery) use ($manageableKelasIds, $tingkatIds) {
                    $kelasQuery->whereIn('kelas.id', $manageableKelasIds)
                        ->whereIn('kelas.tingkat_id', $tingkatIds)
                        ->where('kelas.is_active', true)
                        ->where('kelas_siswa.is_active', true);
                });
            }

            if (empty($targetKelasIds)) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas('kelas', function ($kelasQuery) use ($targetKelasIds) {
                $kelasQuery->whereIn('kelas.id', $targetKelasIds)
                    ->where('kelas.is_active', true)
                    ->where('kelas_siswa.is_active', true);
            });
        }

        return $query->whereRaw('1 = 0');
    }

    private function buildManageableClassesQuery(User $performer): Builder
    {
        $query = Kelas::query()
            ->select('kelas.*')
            ->where('kelas.is_active', true)
            ->withCount([
                'siswa as active_students_count' => function ($studentQuery) {
                    $studentQuery->where('users.is_active', true)
                        ->where('kelas_siswa.is_active', true)
                        ->whereHas('roles', function ($roleQuery) {
                            $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
                        });
                },
            ])
            ->orderBy('nama_kelas');

        if ($this->isAdminLevelUser($performer) || $this->isPrincipalUser($performer) || $this->isWakasekKesiswaanUser($performer)) {
            return $query;
        }

        if ($this->isWaliKelasUser($performer)) {
            return $query->where('wali_kelas_id', $performer->id);
        }

        return $query->whereRaw('1 = 0');
    }

    private function resolveScopeLabel(array $payload): string
    {
        $scopeType = strtolower((string) ($payload['scope_type'] ?? self::SCOPE_ALL));
        if ($scopeType === self::SCOPE_CLASSES) {
            $kelasIds = collect($payload['kelas_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
            if (empty($kelasIds)) {
                return 'Kelas terpilih';
            }

            $labels = Kelas::query()
                ->whereIn('id', $kelasIds)
                ->with('tingkat:id,nama')
                ->get()
                ->map(fn (Kelas $kelas) => $kelas->nama_lengkap)
                ->values()
                ->all();

            return empty($labels)
                ? 'Kelas terpilih'
                : implode(', ', array_slice($labels, 0, 3)) . (count($labels) > 3 ? ' +' . (count($labels) - 3) . ' kelas' : '');
        }

        if ($scopeType === self::SCOPE_LEVELS) {
            $tingkatIds = collect($payload['tingkat_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all();
            if (empty($tingkatIds)) {
                return 'Tingkat terpilih';
            }

            $labels = Tingkat::query()
                ->whereIn('id', $tingkatIds)
                ->orderBy('urutan')
                ->orderBy('nama')
                ->pluck('nama')
                ->values()
                ->all();

            return empty($labels)
                ? 'Tingkat terpilih'
                : implode(', ', array_slice($labels, 0, 3)) . (count($labels) > 3 ? ' +' . (count($labels) - 3) . ' tingkat' : '');
        }

        return 'Semua siswa terkelola';
    }

    private function buildBatchItemRow(ManualAttendanceIncidentBatch $batch, User $user): array
    {
        $kelas = $user->kelas->first();

        return [
            'batch_id' => $batch->id,
            'user_id' => $user->id,
            'kelas_id' => $kelas?->id,
            'tingkat_id' => $kelas?->tingkat_id,
            'nama_lengkap' => $user->nama_lengkap,
            'email' => $user->email,
            'kelas_label' => $kelas?->nama_lengkap,
            'tingkat_label' => $kelas?->tingkat?->nama,
        ];
    }

    private function queueManualCreateWhatsappNotifications(?Absensi $attendance, string $source): void
    {
        if (!$attendance instanceof Absensi) {
            return;
        }

        $events = [];
        if ($attendance->jam_masuk !== null) {
            $events[] = 'checkin';
        }
        if ($attendance->jam_pulang !== null) {
            $events[] = 'checkout';
        }

        foreach ($events as $event) {
            try {
                $job = new DispatchAttendanceWhatsappNotification((int) $attendance->id, $event, true);

                Log::info('Queueing WA notification for manual attendance create', [
                    'attendance_id' => (int) $attendance->id,
                    'event' => $event,
                    'queue' => $job->queue,
                    'connection' => config('queue.default'),
                    'source' => $source,
                ]);

                Queue::push($job);
            } catch (\Throwable $exception) {
                Log::warning('Failed to queue WA notification for manual attendance create', [
                    'attendance_id' => (int) $attendance->id,
                    'event' => $event,
                    'source' => $source,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    public function getExportFilename(ManualAttendanceIncidentBatch $batch, string $extension = 'xlsx'): string
    {
        $date = optional($batch->tanggal)?->format('Ymd') ?: 'unknown';

        return "manual-attendance-incident-batch-{$batch->id}-{$date}.{$extension}";
    }

    /**
     * @return array<int, string>
     */
    public function resolveExportResultCodes(string $group): array
    {
        return match (strtolower($group)) {
            self::EXPORT_GROUP_CREATED => ['created'],
            self::EXPORT_GROUP_SKIPPED => [
                'skipped_existing',
                'skipped_leave',
                'skipped_non_required',
                'skipped_non_working',
            ],
            self::EXPORT_GROUP_FAILED => ['failed'],
            default => [],
        };
    }

    private function isAdminLevelUser(User $user): bool
    {
        return $user->hasRole(array_merge(
            RoleNames::aliases(RoleNames::SUPER_ADMIN),
            RoleNames::aliases(RoleNames::ADMIN)
        ));
    }

    private function isPrincipalUser(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::KEPALA_SEKOLAH));
    }

    private function isWaliKelasUser(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS));
    }

    private function isWakasekKesiswaanUser(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::WAKASEK_KESISWAAN));
    }
}
