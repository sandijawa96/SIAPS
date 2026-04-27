<?php

namespace App\Http\Controllers\Api;

use App\Exports\LiveTrackingExport;
use App\Http\Controllers\Controller;
use App\Models\AttendanceGovernanceLog;
use App\Models\AttendanceSchema;
use App\Models\LiveTracking;
use App\Models\User;
use App\Services\AttendanceTimeService;
use App\Services\LiveTrackingCurrentStoreService;
use App\Services\LiveTrackingSnapshotService;
use App\Support\RoleDataScope;
use App\Support\RoleNames;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class LiveTrackingController extends Controller
{
    private const FORCE_SESSION_CACHE_PREFIX = 'live_tracking:force_session:';
    private const FORCE_SESSION_USER_LIST_KEY = 'live_tracking:force_session_users';
    private const FORCE_SESSION_DEFAULT_MINUTES = 15;
    private const FORCE_SESSION_MAX_MINUTES = 240;
    private const PRIORITY_QUEUE_DEFAULT_LIMIT = 8;
    private const STATUS_TRACKING_DISABLED = 'tracking_disabled';
    private const STATUS_OUTSIDE_SCHEDULE = 'outside_schedule';
    private const SOURCE_REDIS_CURRENT_STORE = 'redis_current_store';
    private const SOURCE_REQUEST_PIPELINE = 'request_pipeline';
    private const HISTORY_MAP_COMPARE_LIMIT = 5;
    private const HISTORY_MAP_ROUTE_POINT_LIMIT = 120;
    private const HISTORY_MAP_PDF_MARKER_LIMIT = 18;
    private const HISTORY_MAP_ROUTE_COLORS = ['#2563eb', '#dc2626', '#0f766e', '#ca8a04', '#7c3aed'];

    public function __construct(
        private readonly LiveTrackingSnapshotService $liveTrackingSnapshotService,
        private readonly AttendanceTimeService $attendanceTimeService,
        private readonly LiveTrackingCurrentStoreService $liveTrackingCurrentStoreService
    ) {
    }

    /**
     * Mulai sesi pemantauan tambahan untuk siswa (oleh admin/guru).
     */
    public function startTrackingSession(Request $request)
    {
        $actor = $request->user();
        if (!$this->canManageTrackingForOthers($actor)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk memulai sesi tracking siswa',
            ], 403);
        }

        if (!$this->isLiveTrackingGloballyEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'Live tracking sedang dinonaktifkan oleh admin',
            ], 422);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'minutes' => 'nullable|integer|min:1|max:' . self::FORCE_SESSION_MAX_MINUTES,
            'reason' => 'nullable|string|max:255'
        ]);

        $target = User::find((int) $validated['user_id']);
        if (!$target || !$target->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            return response()->json([
                'success' => false,
                'message' => 'Sesi pemantauan hanya berlaku untuk siswa',
            ], 422);
        }

        if (!$this->canAccessTrackedStudent($actor, $target->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk memantau siswa ini',
            ], 403);
        }

        $duration = (int) ($validated['minutes'] ?? self::FORCE_SESSION_DEFAULT_MINUTES);
        $duration = max(1, min(self::FORCE_SESSION_MAX_MINUTES, $duration));
        $startedAt = Carbon::now();
        $expiresAt = $startedAt->copy()->addMinutes($duration);
        $session = [
            'requested_by' => (int) $actor->id,
            'requested_by_name' => $actor->nama_lengkap ?: $actor->email,
            'user_id' => (int) $target->id,
            'student_name' => $target->nama_lengkap ?: $target->email,
            'reason' => (string) ($validated['reason'] ?? ''),
            'started_at' => $startedAt->toISOString(),
            'expires_at' => $expiresAt->toISOString(),
            'minutes' => $duration
        ];

        $this->safeCachePut($this->getForceSessionKey((int) $target->id), $session, $expiresAt);
        $this->rememberActiveSessionUserId((int) $target->id);
        $this->liveTrackingCurrentStoreService->setTrackingSessionState(
            (int) $target->id,
            true,
            $session['expires_at']
        );

        AttendanceGovernanceLog::record([
            'category' => 'attendance_tracking',
            'action' => 'tracking_session_started',
            'actor_user_id' => (int) $actor->id,
            'target_type' => 'user',
            'target_id' => (int) $target->id,
            'new_values' => [
                'requested_by' => $actor->id,
                'requested_by_name' => $actor->nama_lengkap ?: $actor->email,
                'student_name' => $target->nama_lengkap ?: $target->email,
                'reason' => $session['reason'],
                'minutes' => $session['minutes'],
                'started_at' => $session['started_at'],
                'expires_at' => $session['expires_at'],
            ],
            'metadata' => [
                'source' => 'manual_tracking_session',
                'minutes' => $duration
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pemantauan tambahan berhasil diaktifkan',
            'data' => $session,
            'meta' => $this->serverTimeMeta(),
        ]);
    }

    /**
     * Hentikan sesi pemantauan tambahan siswa.
     */
    public function stopTrackingSession(Request $request)
    {
        $actor = $request->user();
        if (!$this->canManageTrackingForOthers($actor)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghentikan sesi tracking siswa',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $userId = (int) $validated['user_id'];
        if (!$this->canAccessTrackedStudent($actor, $userId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk menghentikan sesi siswa ini',
            ], 403);
        }

        $key = $this->getForceSessionKey($userId);
        $wasActive = $this->safeCacheHas($key);
        $session = $wasActive ? $this->getForceTrackingSession($userId) : null;
        $this->safeCacheForget($key);
        $this->forgetSessionUserId($userId);
        $this->liveTrackingCurrentStoreService->setTrackingSessionState($userId, false, null);

        AttendanceGovernanceLog::record([
            'category' => 'attendance_tracking',
            'action' => 'tracking_session_stopped',
            'actor_user_id' => (int) $actor->id,
            'target_type' => 'user',
            'target_id' => $userId,
            'old_values' => $wasActive && is_array($session)
                ? $session
                : ['was_active' => false],
            'new_values' => [
                'is_active' => false,
                'stopped_at' => now()->toISOString(),
            ],
            'metadata' => [
                'source' => 'manual_tracking_session',
                'was_active' => $wasActive
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => $wasActive
                ? 'Pemantauan tambahan berhasil dihentikan'
                : 'Tidak ada sesi pemantauan aktif untuk siswa ini'
        ]);
    }

    /**
     * Daftar sesi pemantauan tambahan yang masih aktif.
     */
    public function getActiveTrackingSessions(Request $request)
    {
        $actor = $request->user();
        if (!$this->canManageTrackingForOthers($actor)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat sesi tracking siswa',
            ], 403);
        }

        $rawUsers = $this->safeCacheGet(self::FORCE_SESSION_USER_LIST_KEY, []);
        $sessions = [];
        $now = Carbon::now();

        if (is_array($rawUsers)) {
            foreach ($rawUsers as $rawUserId) {
                $userId = (int) $rawUserId;
                if ($userId <= 0 || !$this->canAccessTrackedStudent($actor, $userId)) {
                    continue;
                }

                $session = $this->getForceTrackingSession($userId);
                if (empty($session)) {
                    continue;
                }

                $expiresAt = isset($session['expires_at']) ? Carbon::parse($session['expires_at']) : null;
                if (!$expiresAt || $expiresAt->lte($now)) {
                    $this->forgetSessionUserId($userId);
                    continue;
                }

                $sessions[] = [
                    'user_id' => $userId,
                    'student_name' => $session['student_name'] ?? null,
                    'requested_by' => $session['requested_by'] ?? null,
                    'requested_by_name' => $session['requested_by_name'] ?? null,
                    'reason' => $session['reason'] ?? null,
                    'started_at' => $session['started_at'] ?? null,
                    'expires_at' => $session['expires_at'] ?? null,
                    'minutes' => $session['minutes'] ?? null
                ];
            }
        }

        $toUnix = static function ($value): int {
            if (!is_string($value) && !is_object($value) && !is_numeric($value)) {
                return 0;
            }

            try {
                return Carbon::parse($value)->timestamp;
            } catch (\Throwable) {
                return 0;
            }
        };

        usort($sessions, function ($a, $b) use ($toUnix) {
            $aExpires = $toUnix($a['expires_at'] ?? null);
            $bExpires = $toUnix($b['expires_at'] ?? null);
            return $aExpires <=> $bExpires;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'total' => count($sessions),
                'sessions' => $sessions
            ],
            'meta' => $this->serverTimeMeta(),
        ]);
    }

    /**
     * Get tracking history siswa
     */
    public function getHistory(Request $request)
    {
        $currentUser = $request->user();

        $request->validate([
            'date' => 'nullable|date',
            'user_id' => 'nullable|exists:users,id'
        ]);

        $userId = (int) ($request->user_id ?? $currentUser->id);
        if ($userId !== (int) $currentUser->id) {
            if (!$this->canViewTrackingForOthers($currentUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat riwayat tracking user lain',
                ], 403);
            }

            if (!$this->canAccessTrackedStudent($currentUser, $userId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat riwayat tracking user ini',
                ], 403);
            }
        }

        $date = $request->date ? Carbon::parse($request->date) : today();

        $tracking = LiveTracking::where('user_id', $userId)
            ->whereDate('tracked_at', $date)
            ->orderBy('tracked_at', 'asc')
            ->get();

        $stats = LiveTracking::getStatsForUser($userId, $date);

        return response()->json([
            'success' => true,
            'data' => [
                'tracking' => $tracking,
                'statistics' => $stats
            ]
        ]);
    }

    /**
     * Get tracking history map data for one to five siswa.
     */
    public function getHistoryMap(Request $request)
    {
        $resolved = $this->resolveHistoryMapPayload($request);
        if ($resolved instanceof \Illuminate\Http\JsonResponse) {
            return $resolved;
        }

        return response()->json([
            'success' => true,
            'data' => $resolved['payload'],
            'meta' => $this->serverTimeMeta(),
        ]);
    }

    /**
     * Export tracking history map as server-side PDF.
     */
    public function exportHistoryMapPdf(Request $request)
    {
        $resolved = $this->resolveHistoryMapPayload($request);
        if ($resolved instanceof \Illuminate\Http\JsonResponse) {
            return $resolved;
        }

        $payload = $resolved['payload'];
        $focusUserId = (int) $request->query('focus_user_id', (int) ($payload['focus_user_id'] ?? 0));
        $selectedUserIds = array_map(static fn ($value): int => (int) $value, (array) data_get($payload, 'filters.user_ids', []));
        if ($focusUserId <= 0 || !in_array($focusUserId, $selectedUserIds, true)) {
            $focusUserId = (int) ($payload['focus_user_id'] ?? 0);
        }

        $exportScope = strtolower(trim((string) $request->query('export_scope', 'focus')));
        if (!in_array($exportScope, ['focus', 'compare'], true)) {
            $exportScope = 'focus';
        }

        $sessions = (array) ($payload['sessions'] ?? []);
        if ($exportScope === 'focus' && $focusUserId > 0) {
            $sessions = array_values(array_filter($sessions, static function (array $session) use ($focusUserId): bool {
                return (int) data_get($session, 'user.id', 0) === $focusUserId;
            }));
        }

        $figure = $this->buildHistoryMapPdfFigure($sessions, $focusUserId);
        $focusSession = collect($sessions)->first(static function (array $session) use ($focusUserId): bool {
            return (int) data_get($session, 'user.id', 0) === $focusUserId;
        }) ?: ($sessions[0] ?? null);

        $filenameDate = preg_replace('/[^0-9-]/', '', (string) data_get($payload, 'filters.date', now()->toDateString())) ?: now()->format('Y-m-d');
        $focusUserLabel = (string) data_get($focusSession, 'user.id', 'tracking');
        $filename = $exportScope === 'focus'
            ? "histori-peta-{$focusUserLabel}-{$filenameDate}.pdf"
            : "histori-peta-compare-{$filenameDate}.pdf";

        $pdf = Pdf::loadView('exports.live-tracking-history-map', [
            'title' => 'Histori Peta Siswa',
            'generatedAt' => now()->format('Y-m-d H:i:s'),
            'filters' => (array) ($payload['filters'] ?? []),
            'summary' => $payload['summary'] ?? [],
            'sessions' => $sessions,
            'focusSession' => $focusSession,
            'focusUserId' => $focusUserId,
            'exportScope' => $exportScope,
            'figure' => $figure,
            'routePointLimit' => self::HISTORY_MAP_ROUTE_POINT_LIMIT,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }

    /**
     * Search siswa options for history map compare.
     */
    public function searchHistoryMapStudents(Request $request)
    {
        $currentUser = $request->user();
        if (!$this->canViewTrackingForOthers($currentUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mencari siswa histori tracking',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:25',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter pencarian histori peta tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $search = trim((string) ($validated['search'] ?? ''));
        $limit = max(1, min(25, (int) ($validated['limit'] ?? 15)));

        $studentsQuery = $this->baseTrackedStudentsQuery($currentUser)
            ->select([
                'users.id',
                'users.nama_lengkap',
                'users.email',
                'users.nis',
                'users.username',
            ]);

        if ($search !== '') {
            $studentsQuery->where(function (Builder $query) use ($search): void {
                $query->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $students = $studentsQuery
            ->orderBy('nama_lengkap')
            ->limit($limit)
            ->get()
            ->map(function (User $student): array {
                return [
                    'id' => (int) $student->id,
                    'name' => $student->nama_lengkap ?: ($student->username ?: $student->email),
                    'email' => $student->email,
                    'class' => $this->buildKelasName($student),
                    'level' => $this->buildTingkatName($student),
                    'homeroom_teacher' => $this->buildWaliKelasInfo($student)['name'],
                    'label' => sprintf(
                        '%s • %s • Tingkat %s',
                        $student->nama_lengkap ?: ($student->username ?: $student->email),
                        $this->buildKelasName($student),
                        $this->buildTingkatName($student)
                    ),
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => $students,
            'meta' => array_merge($this->serverTimeMeta(), [
                'search' => $search,
                'limit' => $limit,
            ]),
        ]);
    }

    /**
     * Get all current tracking data for dashboard
     */
    public function getCurrentTracking(Request $request)
    {
        $requestStartedAt = microtime(true);
        $currentUser = $request->user();
        if (!$this->canViewTrackingForOthers($currentUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat data tracking seluruh siswa',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:all,active,outside_area,inactive,stale,gps_disabled,tracking_disabled,outside_schedule,no_data,no-data',
            'area' => 'nullable|in:all,inside,outside',
            'class' => 'nullable|string|max:100',
            'tingkat' => 'nullable|string|max:100',
            'wali_kelas_id' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:500',
            'include_class_summary' => 'nullable|boolean',
            'include_priority_queues' => 'nullable|boolean',
            'priority_queue_limit' => 'nullable|integer|min:1|max:25',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter live tracking tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $validated = $validator->validated();
            $search = trim((string) ($validated['search'] ?? ''));
            $classFilter = trim((string) ($validated['class'] ?? ''));
            $tingkatFilter = trim((string) ($validated['tingkat'] ?? ''));
            $waliKelasIdFilter = (int) ($validated['wali_kelas_id'] ?? 0);
            $statusFilter = strtolower(trim((string) ($validated['status'] ?? 'all')));
            $areaFilter = strtolower(trim((string) ($validated['area'] ?? 'all')));
            $page = (int) ($validated['page'] ?? 1);
            $perPage = (int) ($validated['per_page'] ?? 0);
            $includeClassSummary = $this->booleanQueryValue($validated['include_class_summary'] ?? null, false);
            $includePriorityQueues = $this->booleanQueryValue($validated['include_priority_queues'] ?? null, false);
            $priorityQueueLimit = max(1, min(
                25,
                (int) ($validated['priority_queue_limit'] ?? self::PRIORITY_QUEUE_DEFAULT_LIMIT)
            ));
            $currentStoreReadEnabled = (bool) config('attendance.live_tracking.read_current_store_enabled', true);

            $studentsQuery = $this->baseTrackedStudentsQuery($currentUser);

            if ($search !== '') {
                $studentsQuery->where(function (Builder $query) use ($search): void {
                    $query->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            }

            if ($classFilter !== '') {
                $studentsQuery->whereHas('kelas', function (Builder $query) use ($classFilter): void {
                    $query->where('nama_kelas', $classFilter);
                });
            }

            if ($tingkatFilter !== '') {
                $studentsQuery->whereHas('kelas.tingkat', function (Builder $query) use ($tingkatFilter): void {
                    $query->where('nama', $tingkatFilter);
                });
            }

            if ($waliKelasIdFilter > 0) {
                $studentsQuery->whereHas('kelas', function (Builder $query) use ($waliKelasIdFilter): void {
                    $query->where('wali_kelas_id', $waliKelasIdFilter);
                });
            }

            $countStartedAt = microtime(true);
            $scopedUserCount = (clone $studentsQuery)->count('users.id');
            $countDurationMs = round((microtime(true) - $countStartedAt) * 1000, 1);

            $currentStoreAggregates = $currentStoreReadEnabled
                ? $this->buildCurrentTrackingAggregatesFromCurrentStore(
                    $search,
                    $classFilter,
                    $tingkatFilter,
                    $waliKelasIdFilter,
                    $statusFilter,
                    $areaFilter,
                    $page,
                    $perPage,
                    $includeClassSummary,
                    $includePriorityQueues,
                    $priorityQueueLimit
                )
                : [
                    'used' => false,
                    'source' => self::SOURCE_REQUEST_PIPELINE,
                    'rows' => [],
                    'summary' => [],
                    'class_summary' => [],
                    'level_summary' => [],
                    'homeroom_summary' => [],
                    'priority_queues' => [],
                    'total' => 0,
                    'page' => $page,
                    'performance' => [
                        'candidate_count' => 0,
                        'record_count' => 0,
                        'filtered_total' => 0,
                        'page_collection_duration_ms' => 0.0,
                        'duration_ms' => 0.0,
                    ],
                ];
            $currentStoreRecordCount = (int) ($currentStoreAggregates['performance']['record_count'] ?? -1);
            $useCurrentStoreData = (bool) ($currentStoreAggregates['used'] ?? false)
                && $currentStoreRecordCount === $scopedUserCount;

            if (
                (bool) ($currentStoreAggregates['used'] ?? false)
                && !$useCurrentStoreData
            ) {
                Log::warning('Live tracking current-store record count mismatch, falling back to request pipeline', [
                    'current_store_record_count' => $currentStoreRecordCount,
                    'scoped_user_count' => $scopedUserCount,
                    'filters' => [
                        'search' => $search,
                        'status' => $statusFilter,
                        'area' => $areaFilter,
                        'class' => $classFilter,
                        'tingkat' => $tingkatFilter,
                        'wali_kelas_id' => $waliKelasIdFilter > 0 ? $waliKelasIdFilter : null,
                    ],
                ]);
            }

            $trackingDataset = $useCurrentStoreData
                ? [
                    'rows' => $currentStoreAggregates['rows'] ?? [],
                    'summary' => $currentStoreAggregates['summary'] ?? [],
                    'class_summary' => $currentStoreAggregates['class_summary'] ?? [],
                    'level_summary' => $currentStoreAggregates['level_summary'] ?? [],
                    'homeroom_summary' => $currentStoreAggregates['homeroom_summary'] ?? [],
                    'priority_queues' => $currentStoreAggregates['priority_queues'] ?? [],
                    'total' => (int) ($currentStoreAggregates['total'] ?? 0),
                    'page' => (int) ($currentStoreAggregates['page'] ?? $page),
                    'performance' => [
                        'chunk_count' => 0,
                        'snapshot_hit_count' => 0,
                        'processed_row_count' => 0,
                        'summary_duration_ms' => 0.0,
                        'priority_queue_duration_ms' => 0.0,
                        'page_collection_duration_ms' => (float) ($currentStoreAggregates['performance']['page_collection_duration_ms'] ?? 0.0),
                        'dataset_duration_ms' => 0.0,
                    ],
                ]
                : $this->buildCurrentTrackingDataset(
                    $studentsQuery,
                    $statusFilter,
                    $areaFilter,
                    $page,
                    $perPage,
                    true,
                    $includeClassSummary,
                    $includePriorityQueues,
                    $priorityQueueLimit
                );

            $trackingData = collect($trackingDataset['rows']);
            $summary = $trackingDataset['summary'];
            $liveTrackingRuntime = app(\App\Services\AttendanceRuntimeConfigService::class)->getLiveTrackingConfig();
            $classSummary = $trackingDataset['class_summary'];
            $levelSummary = $trackingDataset['level_summary'];
            $homeroomSummary = $trackingDataset['homeroom_summary'];
            $priorityQueues = $trackingDataset['priority_queues'];
            $total = (int) $trackingDataset['total'];
            $performance = [
                'scoped_user_count' => $scopedUserCount,
                'filtered_total' => $total,
                'rows_returned' => $trackingData->count(),
                'page' => $page,
                'per_page' => $perPage > 0 ? $perPage : null,
                'count_duration_ms' => $countDurationMs,
                'chunk_count' => (int) ($trackingDataset['performance']['chunk_count'] ?? 0),
                'snapshot_hit_count' => (int) ($trackingDataset['performance']['snapshot_hit_count'] ?? 0),
                'processed_row_count' => (int) ($trackingDataset['performance']['processed_row_count'] ?? 0),
                'summary_duration_ms' => (float) ($trackingDataset['performance']['summary_duration_ms'] ?? 0.0),
                'priority_queue_duration_ms' => (float) ($trackingDataset['performance']['priority_queue_duration_ms'] ?? 0.0),
                'page_collection_duration_ms' => (float) ($trackingDataset['performance']['page_collection_duration_ms'] ?? 0.0),
                'dataset_duration_ms' => (float) ($trackingDataset['performance']['dataset_duration_ms'] ?? 0.0),
                'current_store_candidate_count' => (int) ($currentStoreAggregates['performance']['candidate_count'] ?? 0),
                'current_store_record_count' => (int) ($currentStoreAggregates['performance']['record_count'] ?? 0),
                'current_store_filtered_total' => (int) ($currentStoreAggregates['performance']['filtered_total'] ?? 0),
                'current_store_duration_ms' => (float) ($currentStoreAggregates['performance']['duration_ms'] ?? 0.0),
                'current_store_page_collection_duration_ms' => (float) ($currentStoreAggregates['performance']['page_collection_duration_ms'] ?? 0.0),
                'current_store_source' => $useCurrentStoreData
                    ? self::SOURCE_REDIS_CURRENT_STORE
                    : self::SOURCE_REQUEST_PIPELINE,
                'current_store_read_enabled' => $currentStoreReadEnabled,
                'duration_ms' => round((microtime(true) - $requestStartedAt) * 1000, 1),
                'current_store_write_path' => 'redis_hash_set',
            ];

            $pagination = null;
            if ($perPage > 0) {
                $lastPage = max(1, (int) ceil($total / $perPage));
                $page = max(1, min((int) ($trackingDataset['page'] ?? $page), $lastPage));
                $pagination = [
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $lastPage,
                    'from' => $total > 0 ? (($page - 1) * $perPage) + 1 : 0,
                    'to' => min($page * $perPage, $total),
                ];
            }

            $performance['page'] = $pagination['page'] ?? 1;

            return response()->json([
                'success' => true,
                'data' => $trackingData->values()->all(),
                'meta' => array_merge($this->serverTimeMeta(), [
                    'summary' => $summary,
                    'history_policy' => [
                        'enabled' => (bool) ($liveTrackingRuntime['enabled'] ?? true),
                        'min_distance_meters' => (int) ($liveTrackingRuntime['min_distance_meters'] ?? 20),
                        'retention_days' => (int) ($liveTrackingRuntime['retention_days'] ?? 30),
                        'cleanup_time' => (string) ($liveTrackingRuntime['cleanup_time'] ?? '02:15'),
                        'current_store_rebuild_time' => (string) ($liveTrackingRuntime['current_store_rebuild_time'] ?? '00:10'),
                        'read_current_store_enabled' => (bool) ($liveTrackingRuntime['read_current_store_enabled'] ?? $currentStoreReadEnabled),
                        'persist_idle_seconds' => (int) ($liveTrackingRuntime['persist_idle_seconds'] ?? 300),
                        'sampling_mode' => 'movement_or_change_or_heartbeat',
                        'source' => (string) ($liveTrackingRuntime['source'] ?? 'config'),
                    ],
                    'class_summary' => $classSummary,
                    'level_summary' => $levelSummary,
                    'homeroom_summary' => $homeroomSummary,
                    'summary_source' => $useCurrentStoreData
                        ? self::SOURCE_REDIS_CURRENT_STORE
                        : self::SOURCE_REQUEST_PIPELINE,
                    'group_summary_source' => $useCurrentStoreData
                        ? self::SOURCE_REDIS_CURRENT_STORE
                        : self::SOURCE_REQUEST_PIPELINE,
                    'priority_queue_source' => $useCurrentStoreData
                        ? self::SOURCE_REDIS_CURRENT_STORE
                        : self::SOURCE_REQUEST_PIPELINE,
                    'list_source' => $useCurrentStoreData
                        ? self::SOURCE_REDIS_CURRENT_STORE
                        : self::SOURCE_REQUEST_PIPELINE,
                    'priority_queues' => $priorityQueues,
                    'priority_queue_limit' => $includePriorityQueues ? $priorityQueueLimit : null,
                    'filters' => [
                        'search' => $search,
                        'status' => $statusFilter,
                        'area' => $areaFilter,
                        'class' => $classFilter,
                        'tingkat' => $tingkatFilter,
                        'wali_kelas_id' => $waliKelasIdFilter > 0 ? $waliKelasIdFilter : null,
                    ],
                    'pagination' => $pagination,
                    'performance' => $performance,
                ]),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to build current live tracking payload', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'duration_ms' => round((microtime(true) - $requestStartedAt) * 1000, 1),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data tracking'
            ], 500);
        }
    }

    /**
     * Get current location siswa
     */
    public function getCurrentLocation(Request $request)
    {
        $currentUser = $request->user();

        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $targetUserId = (int) $request->user_id;
        if ($targetUserId !== (int) $currentUser->id) {
            if (!$this->canViewTrackingForOthers($currentUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat lokasi user lain',
                ], 403);
            }

            if (!$this->canAccessTrackedStudent($currentUser, $targetUserId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat lokasi user ini',
                ], 403);
            }
        }

        $snapshot = $this->liveTrackingSnapshotService->get($targetUserId);
        if (!$snapshot) {
            return response()->json([
                'success' => false,
                'message' => 'Data tracking tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->applyUserScheduleStateToSnapshot(
                $this->resolveTrackedUser($targetUserId) ?? $currentUser,
                $this->liveTrackingSnapshotService->appendRealtimeStatus($snapshot),
                $this->getForceTrackingSession($targetUserId)
            ),
            'meta' => $this->serverTimeMeta(),
        ]);
    }

    /**
     * Get siswa dalam radius tertentu
     */
    public function getUsersInRadius(Request $request)
    {
        $currentUser = $request->user();
        if (!$this->canViewTrackingForOthers($currentUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat data tracking radius',
            ], 403);
        }

        $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'required|numeric|min:1|max:1000'
        ]);

        $students = $this->baseTrackedStudentsQuery($currentUser)->get();
        $snapshotsByUserId = collect($this->liveTrackingSnapshotService->getMany(
            $students->pluck('id')->map(static fn ($id): int => (int) $id)->all()
        ))->keyBy(static fn (array $snapshot): int => (int) $snapshot['user_id']);

        $tracking = $students->map(function (User $student) use ($request, $snapshotsByUserId) {
            $snapshot = $snapshotsByUserId->get($student->id);
            if (!is_array($snapshot)) {
                return null;
            }

            $item = $this->liveTrackingSnapshotService->appendRealtimeStatus($snapshot);
            if (!($item['is_tracking_active'] ?? false)) {
                return null;
            }

                $distance = $this->calculateDistanceMeters(
                    (float) $request->latitude,
                    (float) $request->longitude,
                    (float) $item['latitude'],
                    (float) $item['longitude']
                );

                if ($distance > (float) $request->radius) {
                    return null;
                }

                return [
                    'user_id' => $student->id,
                    'distance' => round($distance, 2),
                    'latitude' => $item['latitude'],
                    'longitude' => $item['longitude'],
                    'accuracy' => $item['accuracy'],
                    'speed' => $item['speed'],
                    'heading' => $item['heading'],
                    'is_in_school_area' => $item['is_in_school_area'],
                    'tracked_at' => $item['tracked_at'],
                    'tracking_status' => $item['tracking_status'],
                    'location_name' => $item['location_name'],
                    'gps_quality_status' => $item['gps_quality_status'],
                    'device_source' => $item['device_source'],
                    'user' => [
                        'id' => $student->id,
                        'nama_lengkap' => $student->nama_lengkap,
                        'email' => $student->email,
                        'kelas' => $this->buildKelasName($student),
                    ],
                ];
            })
            ->filter()
            ->sortBy('distance')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $tracking->all(),
            'meta' => $this->serverTimeMeta(),
        ]);
    }

    /**
     * Export live tracking data.
     */
    public function export(Request $request)
    {
        $currentUser = $request->user();
        if (!$this->canViewTrackingForOthers($currentUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk export data tracking',
            ], 403);
        }

        $request->validate([
            'format' => 'nullable|in:csv,xlsx,excel,pdf',
            'date_range' => 'nullable|in:today,yesterday,week,month,custom',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'filter_status' => 'nullable|in:all,active,inactive,outside_area,stale,gps_disabled,tracking_disabled,outside_schedule,no-data,no_data',
            'filter_area' => 'nullable|in:all,inside,outside',
            'filter_search' => 'nullable|string',
            'filter_class' => 'nullable|string',
            'filter_tingkat' => 'nullable|string|max:100',
            'filter_wali_kelas_id' => 'nullable|integer|min:1',
            'include_basicInfo' => 'nullable|boolean',
            'include_locationData' => 'nullable|boolean',
            'include_timestamps' => 'nullable|boolean',
            'include_deviceInfo' => 'nullable|boolean',
            'include_trackingState' => 'nullable|boolean',
            'include_statistics' => 'nullable|boolean',
        ]);

        [$startDate, $endDate] = $this->resolveDateRange(
            (string) $request->input('date_range', 'today'),
            $request->input('start_date'),
            $request->input('end_date')
        );
        $liveTrackingRuntime = app(\App\Services\AttendanceRuntimeConfigService::class)->getLiveTrackingConfig();
        $headings = $this->resolveExportHeadings($request);
        $exportContext = $this->buildExportContext($request, $startDate, $endDate, $liveTrackingRuntime);

        $query = LiveTracking::query()
            ->with(['user:id,nama_lengkap,email', 'user.kelas:id,nama_kelas'])
            ->whereBetween('tracked_at', [$startDate->startOfDay(), $endDate->endOfDay()]);

        if (!RoleDataScope::canViewAllSiswa($currentUser)) {
            $query->whereHas('user', function (Builder $userQuery) use ($currentUser): void {
                RoleDataScope::applySiswaReadScope($userQuery, $currentUser);
            });
        }

        $status = (string) $request->input('filter_status', 'all');
        if ($status === 'active') {
            $query->where('is_in_school_area', true);
        } elseif (in_array($status, ['inactive', 'outside_area'], true)) {
            $query->where('is_in_school_area', false);
        } elseif ($status === self::STATUS_OUTSIDE_SCHEDULE) {
            return $this->exportRows(
                collect(),
                $headings,
                (string) $request->input('format', 'csv'),
                $startDate,
                $endDate,
                $exportContext
            );
        } elseif ($status === self::STATUS_TRACKING_DISABLED) {
            return $this->exportRows(
                collect(),
                $headings,
                (string) $request->input('format', 'csv'),
                $startDate,
                $endDate,
                $exportContext
            );
        } elseif ($status === 'gps_disabled') {
            // Riwayat tabel live tracking hanya menyimpan titik lokasi yang valid.
            return $this->exportRows(
                collect(),
                $headings,
                (string) $request->input('format', 'csv'),
                $startDate,
                $endDate,
                $exportContext
            );
        } elseif (in_array($status, ['no-data', 'no_data'], true)) {
            // Live tracking rows always contain a tracked point.
            return $this->exportRows(
                collect(),
                $headings,
                (string) $request->input('format', 'csv'),
                $startDate,
                $endDate,
                $exportContext
            );
        }

        $area = (string) $request->input('filter_area', 'all');
        if ($area === 'inside') {
            $query->where('is_in_school_area', true);
        } elseif ($area === 'outside') {
            $query->where('is_in_school_area', false);
        }

        if ($request->filled('filter_search')) {
            $search = trim((string) $request->input('filter_search'));
            $query->whereHas('user', function ($userQuery) use ($search) {
                $userQuery->where(function ($inner) use ($search) {
                    $inner->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            });
        }

        if ($request->filled('filter_class')) {
            $classFilter = trim((string) $request->input('filter_class'));
            $query->whereHas('user.kelas', function ($kelasQuery) use ($classFilter) {
                $kelasQuery->where('nama_kelas', $classFilter);
            });
        }

        if ($request->filled('filter_tingkat')) {
            $levelFilter = trim((string) $request->input('filter_tingkat'));
            $query->whereHas('user.kelas.tingkat', function ($tingkatQuery) use ($levelFilter) {
                $tingkatQuery->where('nama', $levelFilter);
            });
        }

        if ($request->filled('filter_wali_kelas_id')) {
            $waliKelasId = (int) $request->input('filter_wali_kelas_id');
            $query->whereHas('user.kelas', function ($kelasQuery) use ($waliKelasId) {
                $kelasQuery->where('wali_kelas_id', $waliKelasId);
            });
        }

        $staleThreshold = now()->subSeconds($this->staleWindowSeconds());

        $rows = $query->orderBy('tracked_at', 'desc')->get()->filter(function (LiveTracking $tracking) use ($status, $staleThreshold) {
            if ($status !== 'stale') {
                return true;
            }

            $trackedAt = $tracking->tracked_at instanceof Carbon
                ? $tracking->tracked_at
                : Carbon::parse($tracking->tracked_at);

            return $trackedAt->lt($staleThreshold);
        })->values()->map(function (LiveTracking $tracking, int $index) use ($staleThreshold, $headings) {
            $kelasName = optional($tracking->user?->kelas?->first())->nama_kelas ?? '-';
            $trackedAt = $tracking->tracked_at instanceof Carbon
                ? $tracking->tracked_at
                : Carbon::parse($tracking->tracked_at);
            $deviceInfo = is_array($tracking->device_info) ? $tracking->device_info : [];
            $trackingStatus = $trackedAt->lt($staleThreshold)
                ? 'Stale'
                : ($tracking->is_in_school_area ? 'Active' : 'Outside Area');

            $row = [
                'No' => $index + 1,
                'Nama' => $tracking->user?->nama_lengkap ?? '-',
                'Kelas' => $kelasName,
                'Lokasi' => $tracking->location_name ?? '-',
                'Latitude' => $tracking->latitude,
                'Longitude' => $tracking->longitude,
                'Akurasi' => $tracking->accuracy ?? '-',
                'Kecepatan' => $tracking->speed ?? '-',
                'Sumber Device' => $tracking->device_source ?? '-',
                'Kualitas GPS' => $tracking->gps_quality_status ?? '-',
                'IP Address' => $tracking->ip_address ?? '-',
                'Platform' => $deviceInfo['platform'] ?? '-',
                'Session ID' => $deviceInfo['session_id'] ?? '-',
                'Status Tracking' => $trackingStatus,
                'Dalam Area Sekolah' => $tracking->is_in_school_area ? 'Ya' : 'Tidak',
                'Waktu Tracking' => optional($tracking->tracked_at)->format('Y-m-d H:i:s') ?? '-',
            ];

            $filteredRow = [];
            foreach ($headings as $heading) {
                $filteredRow[$heading] = $row[$heading] ?? '-';
            }

            return $filteredRow;
        })->values();

        return $this->exportRows(
            $rows,
            $headings,
            (string) $request->input('format', 'csv'),
            $startDate,
            $endDate,
            $exportContext
        );
    }

    /**
     * Determine if a user can manage live tracking for other users (including start/stop session).
     */
    private function canManageTrackingForOthers(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasPermissionTo('manage_live_tracking');
    }

    /**
     * Build cache key for a student's forced tracking session.
     */
    private function getForceSessionKey(int $userId): string
    {
        return self::FORCE_SESSION_CACHE_PREFIX . $userId;
    }

    /**
     * Get active force tracking session for a student.
     */
    private function getForceTrackingSession(int $userId): ?array
    {
        $session = $this->safeCacheGet($this->getForceSessionKey($userId));
        if (!is_array($session) || empty($session['user_id']) || (int) $session['user_id'] !== $userId) {
            return null;
        }

        $expiresAt = isset($session['expires_at']) ? Carbon::parse($session['expires_at']) : null;
        if (!$expiresAt || $expiresAt->lte(now())) {
            $this->forgetSessionUserId($userId);
            $this->safeCacheForget($this->getForceSessionKey($userId));
            return null;
        }

        return $session;
    }

    /**
     * Keep index of students with active sessions.
     */
    private function rememberActiveSessionUserId(int $userId): void
    {
        $current = $this->safeCacheGet(self::FORCE_SESSION_USER_LIST_KEY, []);
        if (!is_array($current)) {
            $current = [];
        }

        if (!in_array($userId, $current, true)) {
            $current[] = $userId;
        }

        $this->safeCachePut(self::FORCE_SESSION_USER_LIST_KEY, $current, now()->addMinutes(self::FORCE_SESSION_MAX_MINUTES));
    }

    /**
     * Remove user id from active session index.
     */
    private function forgetSessionUserId(int $userId): void
    {
        $current = $this->safeCacheGet(self::FORCE_SESSION_USER_LIST_KEY, []);
        if (!is_array($current)) {
            return;
        }

        $filtered = array_values(array_filter(
            $current,
            fn($item) => (int) $item !== $userId
        ));

        if (empty($filtered)) {
            $this->safeCacheForget(self::FORCE_SESSION_USER_LIST_KEY);
            return;
        }

        $this->safeCachePut(self::FORCE_SESSION_USER_LIST_KEY, $filtered, now()->addMinutes(self::FORCE_SESSION_MAX_MINUTES));
    }

    /**
     * Determine if a user can view other users' tracking data.
     */
    private function canViewTrackingForOthers(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        if ($user->hasPermissionTo('view_live_tracking')) {
            return true;
        }

        return $user->hasPermissionTo('manage_live_tracking');
    }

    private function serverTimeMeta(): array
    {
        $serverNow = now()->setTimezone(config('app.timezone'));

        return [
            'server_now' => $serverNow->toISOString(),
            'server_epoch_ms' => $serverNow->valueOf(),
            'server_date' => $serverNow->toDateString(),
            'timezone' => config('app.timezone'),
            'stale_threshold_seconds' => $this->staleWindowSeconds(),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveDateRange(string $dateRange, ?string $startDate = null, ?string $endDate = null): array
    {
        $today = Carbon::today();

        return match ($dateRange) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            'week' => [$today->copy()->subDays(6), $today->copy()],
            'month' => [$today->copy()->subDays(29), $today->copy()],
            'custom' => [
                $startDate ? Carbon::parse($startDate) : $today->copy(),
                $endDate ? Carbon::parse($endDate) : $today->copy(),
            ],
            default => [$today->copy(), $today->copy()],
        };
    }

    private function exportRows(
        Collection $rows,
        array $headings,
        string $format,
        Carbon $startDate,
        Carbon $endDate,
        array $context = []
    )
    {
        $normalizedFormat = strtolower($format);
        if ($normalizedFormat === 'excel') {
            $normalizedFormat = 'xlsx';
        }

        $filenameDate = $startDate->format('Ymd') . '-' . $endDate->format('Ymd');

        if ($normalizedFormat === 'pdf') {
            $pdf = Pdf::loadView('exports.live-tracking-table', [
                'title' => 'Laporan Live Tracking',
                'headers' => $headings,
                'rows' => $rows->map(fn(array $row) => array_values($row))->all(),
                'generatedAt' => now()->format('Y-m-d H:i:s'),
                'period' => $startDate->format('Y-m-d') . ' s/d ' . $endDate->format('Y-m-d'),
                'context' => $context,
            ]);

            return $pdf->download("live-tracking-{$filenameDate}.pdf");
        }

        $writerType = $normalizedFormat === 'xlsx' ? ExcelFormat::XLSX : ExcelFormat::CSV;
        $extension = $normalizedFormat === 'xlsx' ? 'xlsx' : 'csv';

        return Excel::download(
            new LiveTrackingExport($headings, $rows->all(), $this->buildSpreadsheetExportContextRows($context)),
            "live-tracking-{$filenameDate}.{$extension}",
            $writerType
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildExportContext(Request $request, Carbon $startDate, Carbon $endDate, array $runtime): array
    {
        $persistIdleMinutes = max(1, (int) round(((int) ($runtime['persist_idle_seconds'] ?? 300)) / 60));
        $filters = [];

        if ($request->filled('filter_status') && $request->input('filter_status') !== 'all') {
            $filters[] = 'Status: ' . $this->formatExportFilterValue('status', (string) $request->input('filter_status'));
        }

        if ($request->filled('filter_area') && $request->input('filter_area') !== 'all') {
            $filters[] = 'Area: ' . $this->formatExportFilterValue('area', (string) $request->input('filter_area'));
        }

        if ($request->filled('filter_search')) {
            $filters[] = 'Cari: ' . trim((string) $request->input('filter_search'));
        }

        if ($request->filled('filter_class')) {
            $filters[] = 'Kelas: ' . trim((string) $request->input('filter_class'));
        }

        if ($request->filled('filter_tingkat')) {
            $filters[] = 'Tingkat: ' . trim((string) $request->input('filter_tingkat'));
        }

        if ($request->filled('filter_wali_kelas_id')) {
            $filters[] = 'Wali Kelas ID: #' . (int) $request->input('filter_wali_kelas_id');
        }

        return [
            'period' => $startDate->format('Y-m-d') . ' s/d ' . $endDate->format('Y-m-d'),
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'filters' => $filters,
            'notes' => [
                'Histori live tracking tidak menyimpan setiap ping GPS.',
                sprintf(
                    'Titik disimpan saat perpindahan mencapai minimal %d meter, saat status penting berubah, dan checkpoint tiap %d menit saat diam.',
                    (int) ($runtime['min_distance_meters'] ?? 20),
                    $persistIdleMinutes
                ),
                'Status Di luar jadwal, GPS mati, dan Belum ada data bisa tampil kosong di export histori karena tabel histori hanya menyimpan titik lokasi valid.',
            ],
            'runtime_summary' => sprintf(
                'Gerak >= %d m | Checkpoint diam %d menit | Retensi %d hari | Cleanup %s',
                (int) ($runtime['min_distance_meters'] ?? 20),
                $persistIdleMinutes,
                (int) ($runtime['retention_days'] ?? 30),
                (string) ($runtime['cleanup_time'] ?? '02:15')
            ),
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<int, array<int, mixed>>
     */
    private function buildSpreadsheetExportContextRows(array $context): array
    {
        if (empty($context)) {
            return [];
        }

        $rows = [
            ['Laporan Live Tracking'],
            ['Periode', $context['period'] ?? '-'],
            ['Generated', $context['generated_at'] ?? now()->format('Y-m-d H:i:s')],
        ];

        if (!empty($context['runtime_summary'])) {
            $rows[] = ['Policy Histori', (string) $context['runtime_summary']];
        }

        foreach (($context['notes'] ?? []) as $note) {
            $rows[] = ['Catatan', (string) $note];
        }

        if (!empty($context['filters'])) {
            $rows[] = ['Filter Aktif', implode(' | ', array_map('strval', $context['filters']))];
        }

        $rows[] = [];

        return $rows;
    }

    private function formatExportFilterValue(string $type, string $value): string
    {
        return match ($type) {
            'status' => match (strtolower(trim($value))) {
                'active' => 'Dalam area',
                'outside_area', 'inactive' => 'Luar area',
                'tracking_disabled' => 'Tracking nonaktif',
                'outside_schedule' => 'Di luar jadwal',
                'stale' => 'Stale',
                'gps_disabled' => 'GPS mati',
                'no_data', 'no-data' => 'Belum ada data',
                default => $value,
            },
            'area' => match (strtolower(trim($value))) {
                'inside' => 'Dalam area',
                'outside' => 'Luar area',
                default => $value,
            },
            default => $value,
        };
    }

    /**
     * @return array<int, string>
     */
    private function resolveExportHeadings(Request $request): array
    {
        $selection = [
            'basicInfo' => $this->booleanQueryValue($request->query('include_basicInfo'), true),
            'locationData' => $this->booleanQueryValue($request->query('include_locationData'), true),
            'timestamps' => $this->booleanQueryValue($request->query('include_timestamps'), true),
            'deviceInfo' => $this->booleanQueryValue($request->query('include_deviceInfo'), true),
            'trackingState' => $this->booleanQueryValue(
                $request->query('include_trackingState', $request->query('include_statistics')),
                true
            ),
        ];

        $groups = [
            'basicInfo' => ['Nama', 'Kelas'],
            'locationData' => ['Lokasi', 'Latitude', 'Longitude', 'Akurasi', 'Kecepatan'],
            'timestamps' => ['Waktu Tracking'],
            'deviceInfo' => ['Sumber Device', 'Kualitas GPS', 'IP Address', 'Platform', 'Session ID'],
            'trackingState' => ['Status Tracking', 'Dalam Area Sekolah'],
        ];

        $headings = ['No'];
        foreach ($groups as $group => $columns) {
            if (!($selection[$group] ?? false)) {
                continue;
            }

            foreach ($columns as $column) {
                $headings[] = $column;
            }
        }

        if (count($headings) === 1) {
            foreach ($groups as $columns) {
                foreach ($columns as $column) {
                    $headings[] = $column;
                }
            }
        }

        return $headings;
    }

    private function booleanQueryValue(mixed $value, bool $default = false): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return $default;
    }

    private function calculateDistanceMeters(float $centerLat, float $centerLng, float $targetLat, float $targetLng): float
    {
        $earthRadius = 6371000.0;
        $latFrom = deg2rad($centerLat);
        $lngFrom = deg2rad($centerLng);
        $latTo = deg2rad($targetLat);
        $lngTo = deg2rad($targetLng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2)
            + cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)
        ));

        return $angle * $earthRadius;
    }

    /**
     * @return array<int, int>
     */
    private function normalizeHistoryMapUserIds(mixed $rawUserIds, mixed $singleUserId = null, int $fallbackUserId = 0): array
    {
        $values = [];

        if (is_array($rawUserIds)) {
            $values = $rawUserIds;
        } elseif (is_string($rawUserIds) && trim($rawUserIds) !== '') {
            $values = array_map('trim', explode(',', $rawUserIds));
        } elseif ($rawUserIds !== null && $rawUserIds !== '') {
            $values = [$rawUserIds];
        }

        if ($singleUserId !== null && $singleUserId !== '') {
            $values[] = $singleUserId;
        }

        if ($values === [] && $fallbackUserId > 0) {
            $values[] = $fallbackUserId;
        }

        $userIds = array_values(array_unique(array_filter(array_map(
            static fn ($value): int => (int) $value,
            $values
        ), static fn (int $value): bool => $value > 0)));

        return $userIds;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveHistoryMapDateWindow(Carbon $date, ?string $startTime = null, ?string $endTime = null): array
    {
        $startAt = $date->copy()->startOfDay();
        $endAt = $date->copy()->endOfDay();

        if ($startTime) {
            [$hour, $minute] = array_pad(array_map('intval', explode(':', $startTime)), 2, 0);
            $startAt->setTime($hour, $minute, 0);
        }

        if ($endTime) {
            [$hour, $minute] = array_pad(array_map('intval', explode(':', $endTime)), 2, 0);
            $endAt->setTime($hour, $minute, 59);
        }

        return [$startAt, $endAt];
    }

    /**
     * @return array<string, mixed>|\Illuminate\Http\JsonResponse
     */
    private function resolveHistoryMapPayload(Request $request): array|\Illuminate\Http\JsonResponse
    {
        $currentUser = $request->user();

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|integer|exists:users,id',
            'user_ids' => 'nullable',
            'date' => 'nullable|date',
            'start_time' => 'nullable|date_format:H:i',
            'end_time' => 'nullable|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter histori peta tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $userIds = $this->normalizeHistoryMapUserIds(
            $validated['user_ids'] ?? null,
            $validated['user_id'] ?? null,
            (int) ($currentUser?->id ?? 0)
        );

        if ($userIds === []) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu siswa untuk melihat histori peta',
            ], 422);
        }

        if (count($userIds) > self::HISTORY_MAP_COMPARE_LIMIT) {
            return response()->json([
                'success' => false,
                'message' => 'Histori peta maksimal dapat dibandingkan untuk 5 siswa sekaligus',
            ], 422);
        }

        foreach ($userIds as $userId) {
            if ($userId !== (int) $currentUser->id) {
                if (!$this->canViewTrackingForOthers($currentUser)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses untuk melihat riwayat tracking user lain',
                    ], 403);
                }

                if (!$this->canAccessTrackedStudent($currentUser, $userId)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Anda tidak memiliki akses untuk melihat riwayat tracking user ini',
                    ], 403);
                }
            }
        }

        $date = isset($validated['date']) ? Carbon::parse((string) $validated['date']) : today();
        [$windowStart, $windowEnd] = $this->resolveHistoryMapDateWindow(
            $date,
            isset($validated['start_time']) ? (string) $validated['start_time'] : null,
            isset($validated['end_time']) ? (string) $validated['end_time'] : null
        );

        if ($windowEnd->lt($windowStart)) {
            return response()->json([
                'success' => false,
                'message' => 'Rentang waktu histori peta tidak valid',
            ], 422);
        }

        $students = $this->baseTrackedStudentsQuery($currentUser)
            ->whereIn('users.id', $userIds)
            ->get()
            ->keyBy(static fn (User $student): int => (int) $student->id);

        $trackingByUserId = LiveTracking::query()
            ->whereIn('user_id', $userIds)
            ->whereBetween('tracked_at', [$windowStart, $windowEnd])
            ->orderBy('user_id')
            ->orderBy('tracked_at', 'asc')
            ->get()
            ->groupBy(static fn (LiveTracking $tracking): int => (int) $tracking->user_id);

        $sessions = [];
        $summary = [
            'selected_students' => count($userIds),
            'students_with_points' => 0,
            'total_points' => 0,
            'estimated_distance_meters' => 0.0,
        ];

        foreach ($userIds as $userId) {
            $student = $students->get($userId);
            if (!$student instanceof User) {
                continue;
            }

            $session = $this->buildHistoryMapSession(
                $student,
                $trackingByUserId->get($userId, collect())
            );

            $sessions[] = $session;
            $summary['total_points'] += (int) data_get($session, 'statistics.total_points', 0);
            $summary['estimated_distance_meters'] += (float) data_get($session, 'statistics.estimated_distance_meters', 0);

            if ((int) data_get($session, 'statistics.total_points', 0) > 0) {
                $summary['students_with_points']++;
            }
        }

        $summary['estimated_distance_meters'] = round((float) $summary['estimated_distance_meters'], 1);
        $summary['estimated_distance_km'] = round(((float) $summary['estimated_distance_meters']) / 1000, 2);

        return [
            'validated' => $validated,
            'window_start' => $windowStart,
            'window_end' => $windowEnd,
            'payload' => [
                'sessions' => $sessions,
                'summary' => $summary,
                'filters' => [
                    'date' => $date->toDateString(),
                    'start_time' => $validated['start_time'] ?? null,
                    'end_time' => $validated['end_time'] ?? null,
                    'user_ids' => $userIds,
                ],
                'focus_user_id' => $userIds[0] ?? null,
                'compare_limit' => self::HISTORY_MAP_COMPARE_LIMIT,
                'rendering' => [
                    'route_point_limit' => self::HISTORY_MAP_ROUTE_POINT_LIMIT,
                ],
            ],
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $points
     * @return array<int, array<string, mixed>>
     */
    private function simplifyHistoryMapPoints(array $points, int $maxPoints = self::HISTORY_MAP_ROUTE_POINT_LIMIT): array
    {
        $count = count($points);
        if ($count <= $maxPoints || $maxPoints < 3) {
            return array_values($points);
        }

        $mustKeep = [0, $count - 1];
        foreach ($points as $index => $point) {
            if (!empty($point['transition']) || !empty($point['is_start']) || !empty($point['is_end'])) {
                $mustKeep[] = $index;
            }
        }

        $mustKeep = array_values(array_unique(array_filter($mustKeep, static fn (int $index): bool => $index >= 0 && $index < $count)));
        sort($mustKeep);

        if (count($mustKeep) >= $maxPoints) {
            return array_values(array_map(
                static fn (int $index): array => $points[$index],
                array_slice($mustKeep, 0, $maxPoints)
            ));
        }

        $remainingIndexes = array_values(array_filter(
            range(0, $count - 1),
            static fn (int $index): bool => !in_array($index, $mustKeep, true)
        ));

        $availableSlots = $maxPoints - count($mustKeep);
        $selected = $mustKeep;

        if ($availableSlots > 0 && $remainingIndexes !== []) {
            if (count($remainingIndexes) <= $availableSlots) {
                $selected = array_merge($selected, $remainingIndexes);
            } else {
                for ($slot = 0; $slot < $availableSlots; $slot++) {
                    $position = $availableSlots === 1
                        ? 0
                        : (int) round(($slot * (count($remainingIndexes) - 1)) / ($availableSlots - 1));
                    $selected[] = $remainingIndexes[$position];
                }
            }
        }

        $selected = array_values(array_unique($selected));
        sort($selected);

        return array_values(array_map(
            static fn (int $index): array => $points[$index],
            $selected
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $sessions
     * @return array<string, mixed>
     */
    private function buildHistoryMapPdfFigure(array $sessions, int $focusUserId = 0): array
    {
        $width = 960.0;
        $height = 420.0;
        $padding = 36.0;
        $legend = [];
        $plotSessions = [];
        $minLat = null;
        $maxLat = null;
        $minLng = null;
        $maxLng = null;

        foreach (array_values($sessions) as $index => $session) {
            $color = self::HISTORY_MAP_ROUTE_COLORS[$index % count(self::HISTORY_MAP_ROUTE_COLORS)];
            $routePoints = array_values(array_filter(
                (array) ($session['route_points'] ?? $session['points'] ?? []),
                static function (array $point): bool {
                    return isset($point['latitude'], $point['longitude']);
                }
            ));

            if ($routePoints === []) {
                continue;
            }

            foreach ($routePoints as $point) {
                $lat = (float) $point['latitude'];
                $lng = (float) $point['longitude'];
                $minLat = $minLat === null ? $lat : min($minLat, $lat);
                $maxLat = $maxLat === null ? $lat : max($maxLat, $lat);
                $minLng = $minLng === null ? $lng : min($minLng, $lng);
                $maxLng = $maxLng === null ? $lng : max($maxLng, $lng);
            }

            $legend[] = [
                'user_id' => (int) data_get($session, 'user.id', 0),
                'name' => (string) data_get($session, 'user.nama_lengkap', '-'),
                'color' => $color,
            ];
            $plotSessions[] = [
                'session' => $session,
                'color' => $color,
                'points' => $routePoints,
            ];
        }

        if ($plotSessions === []) {
            return [
                'width' => $width,
                'height' => $height,
                'legend' => $legend,
                'paths' => [],
                'markers' => [],
                'has_data' => false,
            ];
        }

        $latRange = max(0.00001, (float) $maxLat - (float) $minLat);
        $lngRange = max(0.00001, (float) $maxLng - (float) $minLng);
        $plotWidth = $width - ($padding * 2);
        $plotHeight = $height - ($padding * 2);

        $toCanvasPoint = static function (float $lat, float $lng) use ($minLat, $maxLat, $minLng, $lngRange, $latRange, $padding, $plotWidth, $plotHeight): array {
            $x = $padding + (($lng - (float) $minLng) / $lngRange) * $plotWidth;
            $y = $padding + ((((float) $maxLat - $lat) / $latRange) * $plotHeight);

            return [
                'x' => round($x, 2),
                'y' => round($y, 2),
            ];
        };

        $paths = [];
        $markers = [];

        foreach ($plotSessions as $plotSession) {
            $session = $plotSession['session'];
            $color = $plotSession['color'];
            $points = $plotSession['points'];
            $canvasPoints = [];

            foreach ($points as $point) {
                $canvasPoints[] = array_merge(
                    $point,
                    $toCanvasPoint((float) $point['latitude'], (float) $point['longitude'])
                );
            }

            if (count($canvasPoints) < 2) {
                continue;
            }

            $pathD = [];
            foreach ($canvasPoints as $index => $point) {
                $command = $index === 0 ? 'M' : 'L';
                $pathD[] = sprintf('%s %s %s', $command, $point['x'], $point['y']);
            }

            $paths[] = [
                'user_id' => (int) data_get($session, 'user.id', 0),
                'color' => $color,
                'is_focus' => (int) data_get($session, 'user.id', 0) === $focusUserId,
                'path_d' => implode(' ', $pathD),
            ];

            $markerIndexes = $this->pickHistoryMapPdfMarkerIndexes($canvasPoints, (int) data_get($session, 'user.id', 0) === $focusUserId);
            foreach ($markerIndexes as $markerIndex) {
                $point = $canvasPoints[$markerIndex] ?? null;
                if (!is_array($point)) {
                    continue;
                }

                $label = !empty($point['is_start'])
                    ? 'S'
                    : (!empty($point['is_end']) ? 'E' : (string) ($point['sequence'] ?? '?'));

                $markers[] = [
                    'user_id' => (int) data_get($session, 'user.id', 0),
                    'color' => $color,
                    'x' => $point['x'],
                    'y' => $point['y'],
                    'label' => $label,
                    'is_focus' => (int) data_get($session, 'user.id', 0) === $focusUserId,
                ];
            }
        }

        return [
            'width' => $width,
            'height' => $height,
            'legend' => $legend,
            'paths' => $paths,
            'markers' => $markers,
            'has_data' => true,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $points
     * @return array<int, int>
     */
    private function pickHistoryMapPdfMarkerIndexes(array $points, bool $isFocus): array
    {
        $count = count($points);
        if ($count === 0) {
            return [];
        }

        $mustKeep = [0, $count - 1];
        foreach ($points as $index => $point) {
            if (!empty($point['transition']) || !empty($point['is_start']) || !empty($point['is_end'])) {
                $mustKeep[] = $index;
            }
        }

        $mustKeep = array_values(array_unique($mustKeep));
        sort($mustKeep);

        $limit = $isFocus ? self::HISTORY_MAP_PDF_MARKER_LIMIT : 2;
        if (count($mustKeep) >= $limit) {
            return array_slice($mustKeep, 0, $limit);
        }

        if (!$isFocus) {
            return $mustKeep;
        }

        $remainingIndexes = array_values(array_filter(
            range(0, $count - 1),
            static fn (int $index): bool => !in_array($index, $mustKeep, true)
        ));
        $availableSlots = $limit - count($mustKeep);
        $selected = $mustKeep;

        if ($availableSlots > 0 && $remainingIndexes !== []) {
            for ($slot = 0; $slot < min($availableSlots, count($remainingIndexes)); $slot++) {
                $position = $availableSlots === 1
                    ? 0
                    : (int) round(($slot * (count($remainingIndexes) - 1)) / ($availableSlots - 1));
                $selected[] = $remainingIndexes[$position];
            }
        }

        $selected = array_values(array_unique($selected));
        sort($selected);

        return array_slice($selected, 0, $limit);
    }

    /**
     * @param Collection<int, LiveTracking> $trackingRows
     * @return array<string, mixed>
     */
    private function buildHistoryMapSession(User $student, Collection $trackingRows): array
    {
        $kelasName = $this->buildKelasName($student);
        $tingkatName = $this->buildTingkatName($student);
        $waliKelas = $this->buildWaliKelasInfo($student);
        $points = [];
        $totalPoints = $trackingRows->count();
        $inSchoolArea = 0;
        $estimatedDistanceMeters = 0.0;
        $enterAreaCount = 0;
        $exitAreaCount = 0;
        $maxAccuracy = null;
        $previousRow = null;
        $previousTrackedAt = null;
        $firstTrackedAt = null;
        $lastTrackedAt = null;

        foreach ($trackingRows->values() as $index => $tracking) {
            $trackedAt = $tracking->tracked_at instanceof Carbon
                ? $tracking->tracked_at->copy()
                : Carbon::parse($tracking->tracked_at);

            if ($firstTrackedAt === null) {
                $firstTrackedAt = $trackedAt->copy();
            }

            $lastTrackedAt = $trackedAt->copy();
            if ($tracking->is_in_school_area) {
                $inSchoolArea++;
            }

            $accuracy = $tracking->accuracy !== null ? (float) $tracking->accuracy : null;
            if ($accuracy !== null) {
                $maxAccuracy = $maxAccuracy === null ? $accuracy : max($maxAccuracy, $accuracy);
            }

            $distanceFromPrevious = 0.0;
            $elapsedSecondsFromPrevious = 0;
            $transition = null;

            if ($previousRow instanceof LiveTracking && $previousTrackedAt instanceof Carbon) {
                $distanceFromPrevious = round($this->calculateDistanceMeters(
                    (float) $previousRow->latitude,
                    (float) $previousRow->longitude,
                    (float) $tracking->latitude,
                    (float) $tracking->longitude
                ), 1);
                $estimatedDistanceMeters += $distanceFromPrevious;
                $elapsedSecondsFromPrevious = max(0, $trackedAt->diffInSeconds($previousTrackedAt));

                if ((bool) $previousRow->is_in_school_area !== (bool) $tracking->is_in_school_area) {
                    if ($tracking->is_in_school_area) {
                        $transition = 'enter_area';
                        $enterAreaCount++;
                    } else {
                        $transition = 'exit_area';
                        $exitAreaCount++;
                    }
                }
            }

            $points[] = [
                'id' => (int) $tracking->id,
                'sequence' => $index + 1,
                'latitude' => (float) $tracking->latitude,
                'longitude' => (float) $tracking->longitude,
                'tracked_at' => $trackedAt->toISOString(),
                'location_id' => $tracking->location_id !== null ? (int) $tracking->location_id : null,
                'location_name' => $tracking->location_name ?: 'Lokasi tidak diketahui',
                'is_in_school_area' => (bool) $tracking->is_in_school_area,
                'accuracy' => $accuracy,
                'speed' => $tracking->speed !== null ? (float) $tracking->speed : null,
                'heading' => $tracking->heading !== null ? (float) $tracking->heading : null,
                'device_source' => $tracking->device_source ?: null,
                'gps_quality_status' => $tracking->gps_quality_status ?: 'unknown',
                'ip_address' => $tracking->ip_address ?: null,
                'distance_from_previous_meters' => $distanceFromPrevious,
                'elapsed_seconds_from_previous' => $elapsedSecondsFromPrevious,
                'cumulative_distance_meters' => round($estimatedDistanceMeters, 1),
                'is_start' => $index === 0,
                'is_end' => $index === ($totalPoints - 1),
                'transition' => $transition,
            ];

            $previousRow = $tracking;
            $previousTrackedAt = $trackedAt;
        }

        $outsideSchoolArea = max(0, $totalPoints - $inSchoolArea);
        $durationMinutes = $firstTrackedAt && $lastTrackedAt
            ? max(0, $lastTrackedAt->diffInMinutes($firstTrackedAt))
            : 0;
        $routePoints = $this->simplifyHistoryMapPoints($points);

        return [
            'user' => [
                'id' => (int) $student->id,
                'nama_lengkap' => $student->nama_lengkap,
                'email' => $student->email,
                'kelas' => $kelasName,
                'tingkat' => $tingkatName,
                'wali_kelas_id' => $waliKelas['id'],
                'wali_kelas' => $waliKelas['name'],
            ],
            'statistics' => [
                'total_points' => $totalPoints,
                'in_school_area' => $inSchoolArea,
                'outside_school_area' => $outsideSchoolArea,
                'percentage_in_school' => $totalPoints > 0
                    ? round(($inSchoolArea / $totalPoints) * 100, 2)
                    : 0.0,
                'estimated_distance_meters' => round($estimatedDistanceMeters, 1),
                'estimated_distance_km' => round($estimatedDistanceMeters / 1000, 2),
                'enter_area_count' => $enterAreaCount,
                'exit_area_count' => $exitAreaCount,
                'started_at' => $firstTrackedAt?->toISOString(),
                'ended_at' => $lastTrackedAt?->toISOString(),
                'duration_minutes' => $durationMinutes,
                'max_accuracy' => $maxAccuracy !== null ? round($maxAccuracy, 1) : null,
                'map_point_count' => count($routePoints),
                'is_route_simplified' => count($routePoints) < $totalPoints,
            ],
            'points' => $points,
            'route_points' => $routePoints,
        ];
    }

    private function staleWindowSeconds(): int
    {
        return max(1, (int) config('attendance.live_tracking.stale_seconds', 300));
    }

    private function buildKelasName(User $student): string
    {
        if (!$student->relationLoaded('kelas') || !$student->kelas) {
            return 'N/A';
        }

        if ($student->kelas instanceof \Illuminate\Database\Eloquent\Collection) {
            return $student->kelas->first()?->nama_kelas ?? 'N/A';
        }

        return $student->kelas->nama_kelas ?? 'N/A';
    }

    private function buildTingkatName(User $student): string
    {
        if (!$student->relationLoaded('kelas') || !$student->kelas) {
            return 'N/A';
        }

        if ($student->kelas instanceof \Illuminate\Database\Eloquent\Collection) {
            return $student->kelas->first()?->tingkat?->nama ?? 'N/A';
        }

        return $student->kelas->tingkat?->nama ?? 'N/A';
    }

    /**
     * @return array{id:int|null,name:string}
     */
    private function buildWaliKelasInfo(User $student): array
    {
        if (!$student->relationLoaded('kelas') || !$student->kelas) {
            return [
                'id' => null,
                'name' => 'Belum ditentukan',
            ];
        }

        $kelas = $student->kelas instanceof \Illuminate\Database\Eloquent\Collection
            ? $student->kelas->first()
            : $student->kelas;

        return [
            'id' => $kelas?->waliKelas?->id ? (int) $kelas->waliKelas->id : null,
            'name' => $kelas?->waliKelas?->nama_lengkap ?: 'Belum ditentukan',
        ];
    }

    private function buildCurrentTrackingRow(User $student, ?array $snapshot = null): array
    {
        $kelasName = $this->buildKelasName($student);
        $tingkatName = $this->buildTingkatName($student);
        $waliKelas = $this->buildWaliKelasInfo($student);
        $forceSession = $this->getForceTrackingSession((int) $student->id);

        if (!$snapshot) {
            return $this->emptyTrackingRow($student, $kelasName, $tingkatName, $waliKelas, $forceSession);
        }

        $snapshot = $this->liveTrackingSnapshotService->appendRealtimeStatus($snapshot);
        $snapshot = $this->applyMonitoringScheduleStateToSnapshot($snapshot, $forceSession);

        return [
            'user_id' => $student->id,
            'latitude' => $snapshot['latitude'],
            'longitude' => $snapshot['longitude'],
            'accuracy' => $snapshot['accuracy'],
            'speed' => $snapshot['speed'],
            'heading' => $snapshot['heading'],
            'is_in_school_area' => $snapshot['is_in_school_area'],
            'within_gps_area' => $snapshot['within_gps_area'],
            'device_info' => $snapshot['device_info'],
            'device_source' => $snapshot['device_source'],
            'gps_quality_status' => $snapshot['gps_quality_status'],
            'ip_address' => $snapshot['ip_address'],
            'tracked_at' => $snapshot['tracked_at'],
            'is_tracking_active' => $snapshot['is_tracking_active'],
            'tracking_status' => $snapshot['tracking_status'],
            'tracking_status_reason' => $snapshot['tracking_status_reason'],
            'tracking_session_active' => !empty($forceSession),
            'tracking_session_expires_at' => $forceSession['expires_at'] ?? null,
            'location_id' => $snapshot['location_id'],
            'location_name' => $snapshot['location_name'] ?? 'Unknown Location',
            'current_location' => $snapshot['current_location'],
            'nearest_location' => $snapshot['nearest_location'],
            'distance_to_nearest' => $snapshot['distance_to_nearest'],
            'stale_threshold_seconds' => $snapshot['stale_threshold_seconds'],
            'has_tracking_data' => true,
            'user' => [
                'id' => $student->id,
                'nama_lengkap' => $student->nama_lengkap,
                'email' => $student->email,
                'kelas' => $kelasName,
                'tingkat' => $tingkatName,
                'wali_kelas_id' => $waliKelas['id'],
                'wali_kelas' => $waliKelas['name'],
            ],
        ];
    }

    /**
     * @param array{id:int|null,name:string} $waliKelas
     */
    private function emptyTrackingRow(
        User $student,
        string $kelasName,
        string $tingkatName,
        array $waliKelas,
        ?array $forceSession = null
    ): array
    {
        return [
            'user_id' => $student->id,
            'latitude' => null,
            'longitude' => null,
            'accuracy' => null,
            'speed' => null,
            'heading' => null,
            'is_in_school_area' => false,
            'within_gps_area' => false,
            'device_info' => [],
            'device_source' => null,
            'gps_quality_status' => 'unknown',
            'ip_address' => null,
            'tracked_at' => null,
            'is_tracking_active' => false,
            'tracking_status' => 'no_data',
            'tracking_status_reason' => $forceSession
                ? 'pemantauan_tambahan_aktif'
                : 'belum_ada_data_hari_ini',
            'tracking_session_active' => !empty($forceSession),
            'tracking_session_expires_at' => $forceSession['expires_at'] ?? null,
            'location_id' => null,
            'location_name' => 'No tracking data',
            'current_location' => null,
            'nearest_location' => null,
            'distance_to_nearest' => null,
            'stale_threshold_seconds' => $this->staleWindowSeconds(),
            'has_tracking_data' => false,
            'user' => [
                'id' => $student->id,
                'nama_lengkap' => $student->nama_lengkap,
                'email' => $student->email,
                'kelas' => $kelasName,
                'tingkat' => $tingkatName,
                'wali_kelas_id' => $waliKelas['id'],
                'wali_kelas' => $waliKelas['name'],
            ],
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function applyCurrentTrackingFilters(Collection $rows, string $statusFilter, string $areaFilter): Collection
    {
        return $rows->filter(
            fn (array $row): bool => $this->matchesCurrentTrackingFilters($row, $statusFilter, $areaFilter)
        )->values();
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<string, int>
     */
    private function summarizeCurrentRows(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'active' => $rows->where('tracking_status', 'active')->count(),
            'outside_area' => $rows->where('tracking_status', 'outside_area')->count(),
            'stale' => $rows->where('tracking_status', 'stale')->count(),
            'gps_disabled' => $rows->where('tracking_status', 'gps_disabled')->count(),
            'tracking_disabled' => $rows->where('tracking_status', self::STATUS_TRACKING_DISABLED)->count(),
            'outside_schedule' => $rows->where('tracking_status', self::STATUS_OUTSIDE_SCHEDULE)->count(),
            'no_data' => $rows->where('tracking_status', 'no_data')->count(),
            'inside_area' => $rows->filter(static fn (array $row): bool => (bool) ($row['has_tracking_data'] ?? false) && (bool) ($row['is_in_school_area'] ?? false))->count(),
            'poor_gps' => $rows->where('gps_quality_status', 'poor')->count(),
            'moderate_gps' => $rows->where('gps_quality_status', 'moderate')->count(),
        ];
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function summarizeCurrentRowsByClass(Collection $rows): array
    {
        $classSummary = $rows
            ->groupBy(static function (array $row): string {
                $className = trim((string) data_get($row, 'user.kelas', 'N/A'));
                return $className !== '' ? $className : 'N/A';
            })
            ->map(function (Collection $classRows, string $className): array {
                $summary = $this->summarizeCurrentRows($classRows);
                $trackedCount = max(0, (int) $summary['total'] - (int) $summary['no_data']);
                $exceptionCount = (int) $summary['outside_area']
                    + (int) $summary['stale']
                    + (int) $summary['gps_disabled'];

                return [
                    'class_name' => $className,
                    'total' => (int) $summary['total'],
                    'tracked' => $trackedCount,
                    'active' => (int) $summary['active'],
                    'outside_area' => (int) $summary['outside_area'],
                    'stale' => (int) $summary['stale'],
                    'gps_disabled' => (int) $summary['gps_disabled'],
                    'tracking_disabled' => (int) ($summary['tracking_disabled'] ?? 0),
                    'outside_schedule' => (int) ($summary['outside_schedule'] ?? 0),
                    'no_data' => (int) $summary['no_data'],
                    'inside_area' => (int) $summary['inside_area'],
                    'poor_gps' => (int) $summary['poor_gps'],
                    'moderate_gps' => (int) $summary['moderate_gps'],
                    'exception_count' => $exceptionCount,
                    'tracked_rate' => (int) $summary['total'] > 0
                        ? round(($trackedCount / (int) $summary['total']) * 100, 1)
                        : 0.0,
                    'exception_rate' => (int) $summary['total'] > 0
                        ? round(($exceptionCount / (int) $summary['total']) * 100, 1)
                        : 0.0,
                ];
            })
            ->values();

        return $classSummary
            ->sort(function (array $left, array $right): int {
                $rateComparison = ($right['exception_rate'] <=> $left['exception_rate']);
                if ($rateComparison !== 0) {
                    return $rateComparison;
                }

                $countComparison = ($right['exception_count'] <=> $left['exception_count']);
                if ($countComparison !== 0) {
                    return $countComparison;
                }

                return strcmp((string) $left['class_name'], (string) $right['class_name']);
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function summarizeCurrentRowsByLevel(Collection $rows): array
    {
        $levelSummary = $rows
            ->groupBy(static function (array $row): string {
                $levelName = trim((string) data_get($row, 'user.tingkat', 'N/A'));
                return $levelName !== '' ? $levelName : 'N/A';
            })
            ->map(function (Collection $levelRows, string $levelName): array {
                $summary = $this->summarizeCurrentRows($levelRows);
                $trackedCount = max(0, (int) $summary['total'] - (int) $summary['no_data']);
                $exceptionCount = (int) $summary['outside_area']
                    + (int) $summary['stale']
                    + (int) $summary['gps_disabled'];

                return [
                    'level_name' => $levelName,
                    'total' => (int) $summary['total'],
                    'tracked' => $trackedCount,
                    'active' => (int) $summary['active'],
                    'outside_area' => (int) $summary['outside_area'],
                    'stale' => (int) $summary['stale'],
                    'gps_disabled' => (int) $summary['gps_disabled'],
                    'tracking_disabled' => (int) ($summary['tracking_disabled'] ?? 0),
                    'outside_schedule' => (int) ($summary['outside_schedule'] ?? 0),
                    'no_data' => (int) $summary['no_data'],
                    'exception_count' => $exceptionCount,
                    'tracked_rate' => (int) $summary['total'] > 0
                        ? round(($trackedCount / (int) $summary['total']) * 100, 1)
                        : 0.0,
                    'exception_rate' => (int) $summary['total'] > 0
                        ? round(($exceptionCount / (int) $summary['total']) * 100, 1)
                        : 0.0,
                ];
            })
            ->values();

        return $levelSummary
            ->sort(function (array $left, array $right): int {
                $rateComparison = ($right['exception_rate'] <=> $left['exception_rate']);
                if ($rateComparison !== 0) {
                    return $rateComparison;
                }

                $countComparison = ($right['exception_count'] <=> $left['exception_count']);
                if ($countComparison !== 0) {
                    return $countComparison;
                }

                return strcmp((string) $left['level_name'], (string) $right['level_name']);
            })
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function summarizeCurrentRowsByHomeroomTeacher(Collection $rows): array
    {
        $homeroomSummary = $rows
            ->groupBy(static function (array $row): string {
                $homeroomTeacherId = data_get($row, 'user.wali_kelas_id');
                return $homeroomTeacherId !== null
                    ? (string) (int) $homeroomTeacherId
                    : 'unassigned';
            })
            ->map(function (Collection $teacherRows, string $groupKey): array {
                $summary = $this->summarizeCurrentRows($teacherRows);
                $trackedCount = max(0, (int) $summary['total'] - (int) $summary['no_data']);
                $exceptionCount = (int) $summary['outside_area']
                    + (int) $summary['stale']
                    + (int) $summary['gps_disabled'];
                $firstRow = $teacherRows->first();
                $teacherName = trim((string) data_get($firstRow, 'user.wali_kelas', 'Belum ditentukan'));
                if ($teacherName === '') {
                    $teacherName = 'Belum ditentukan';
                }

                return [
                    'wali_kelas_id' => $groupKey !== 'unassigned' ? (int) $groupKey : null,
                    'wali_kelas_name' => $teacherName,
                    'total' => (int) $summary['total'],
                    'tracked' => $trackedCount,
                    'active' => (int) $summary['active'],
                    'outside_area' => (int) $summary['outside_area'],
                    'stale' => (int) $summary['stale'],
                    'gps_disabled' => (int) $summary['gps_disabled'],
                    'tracking_disabled' => (int) ($summary['tracking_disabled'] ?? 0),
                    'outside_schedule' => (int) ($summary['outside_schedule'] ?? 0),
                    'no_data' => (int) $summary['no_data'],
                    'exception_count' => $exceptionCount,
                    'class_count' => $teacherRows
                        ->pluck('user.kelas')
                        ->filter(static fn ($value): bool => is_string($value) && trim($value) !== '' && trim($value) !== 'N/A')
                        ->unique()
                        ->count(),
                    'level_count' => $teacherRows
                        ->pluck('user.tingkat')
                        ->filter(static fn ($value): bool => is_string($value) && trim($value) !== '' && trim($value) !== 'N/A')
                        ->unique()
                        ->count(),
                    'tracked_rate' => (int) $summary['total'] > 0
                        ? round(($trackedCount / (int) $summary['total']) * 100, 1)
                        : 0.0,
                    'exception_rate' => (int) $summary['total'] > 0
                        ? round(($exceptionCount / (int) $summary['total']) * 100, 1)
                        : 0.0,
                ];
            })
            ->values();

        return $homeroomSummary
            ->sort(function (array $left, array $right): int {
                $rateComparison = ($right['exception_rate'] <=> $left['exception_rate']);
                if ($rateComparison !== 0) {
                    return $rateComparison;
                }

                $countComparison = ($right['exception_count'] <=> $left['exception_count']);
                if ($countComparison !== 0) {
                    return $countComparison;
                }

                return strcmp((string) $left['wali_kelas_name'], (string) $right['wali_kelas_name']);
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCurrentTrackingDataset(
        Builder $studentsQuery,
        string $statusFilter,
        string $areaFilter,
        int $page,
        int $perPage,
        bool $includeSummary,
        bool $includeClassSummary,
        bool $includePriorityQueues,
        int $priorityQueueLimit
    ): array {
        $datasetStartedAt = microtime(true);
        $requestedPage = max(1, $page);
        $offset = $perPage > 0 ? (($requestedPage - 1) * $perPage) : 0;
        $pageRows = [];
        $filteredTotal = 0;
        $summary = $this->createCurrentSummaryAccumulator();
        $classSummaryAccumulator = [];
        $levelSummaryAccumulator = [];
        $homeroomSummaryAccumulator = [];
        $priorityQueues = [
            'gps_disabled' => [],
            'stale' => [],
            'outside_area' => [],
        ];
        $performance = [
            'chunk_count' => 0,
            'snapshot_hit_count' => 0,
            'processed_row_count' => 0,
            'summary_duration_ms' => 0.0,
            'priority_queue_duration_ms' => 0.0,
            'page_collection_duration_ms' => 0.0,
        ];

        $this->streamTrackedStudentRows(clone $studentsQuery, function (array $row) use (
            $statusFilter,
            $areaFilter,
            $perPage,
            $offset,
            $includeSummary,
            $includeClassSummary,
            $includePriorityQueues,
            $priorityQueueLimit,
            &$pageRows,
            &$filteredTotal,
            &$summary,
            &$classSummaryAccumulator,
            &$levelSummaryAccumulator,
            &$homeroomSummaryAccumulator,
            &$priorityQueues,
            &$performance
        ): void {
            $performance['processed_row_count']++;
            if (!$this->matchesCurrentTrackingFilters($row, $statusFilter, $areaFilter)) {
                return;
            }

            $filteredTotal++;
            if ($includeSummary) {
                $summaryStartedAt = microtime(true);
                $this->accumulateCurrentSummary($summary, $row);

                if ($includeClassSummary) {
                    $this->accumulateClassSummary($classSummaryAccumulator, $row);
                    $this->accumulateLevelSummary($levelSummaryAccumulator, $row);
                    $this->accumulateHomeroomSummary($homeroomSummaryAccumulator, $row);
                }
                $performance['summary_duration_ms'] += (microtime(true) - $summaryStartedAt) * 1000;
            }

            if ($includePriorityQueues) {
                $priorityQueueStartedAt = microtime(true);
                $this->appendRowToPriorityQueues($priorityQueues, $row, $priorityQueueLimit);
                $performance['priority_queue_duration_ms'] += (microtime(true) - $priorityQueueStartedAt) * 1000;
            }

            if ($perPage > 0) {
                if ($filteredTotal > $offset && count($pageRows) < $perPage) {
                    $pageRows[] = $row;
                }

                return;
            }

            $pageRows[] = $row;
        }, $performance);

        $resolvedPage = $requestedPage;
        if ($perPage > 0) {
            $lastPage = max(1, (int) ceil($filteredTotal / $perPage));
            if ($requestedPage > $lastPage) {
                $resolvedPage = $lastPage;
                $pageCollectionStartedAt = microtime(true);
                $pageRows = $this->collectCurrentTrackingPageRows(
                    clone $studentsQuery,
                    $statusFilter,
                    $areaFilter,
                    $resolvedPage,
                    $perPage,
                    $performance
                );
                $performance['page_collection_duration_ms'] += (microtime(true) - $pageCollectionStartedAt) * 1000;
            }
        }

        return [
            'rows' => $pageRows,
            'summary' => $this->finalizeCurrentSummary($summary),
            'class_summary' => $includeClassSummary
                ? $this->finalizeCurrentClassSummary($classSummaryAccumulator)
                : [],
            'level_summary' => $includeClassSummary
                ? $this->finalizeCurrentLevelSummary($levelSummaryAccumulator)
                : [],
            'homeroom_summary' => $includeClassSummary
                ? $this->finalizeCurrentHomeroomSummary($homeroomSummaryAccumulator)
                : [],
            'priority_queues' => $includePriorityQueues
                ? [
                    'gps_disabled' => array_values($priorityQueues['gps_disabled']),
                    'stale' => array_values($priorityQueues['stale']),
                    'outside_area' => array_values($priorityQueues['outside_area']),
                ]
                : [],
            'total' => $filteredTotal,
            'page' => $resolvedPage,
            'performance' => [
                'chunk_count' => (int) $performance['chunk_count'],
                'snapshot_hit_count' => (int) $performance['snapshot_hit_count'],
                'processed_row_count' => (int) $performance['processed_row_count'],
                'summary_duration_ms' => round((float) $performance['summary_duration_ms'], 1),
                'priority_queue_duration_ms' => round((float) $performance['priority_queue_duration_ms'], 1),
                'page_collection_duration_ms' => round((float) $performance['page_collection_duration_ms'], 1),
                'dataset_duration_ms' => round((microtime(true) - $datasetStartedAt) * 1000, 1),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCurrentTrackingAggregatesFromCurrentStore(
        string $search,
        string $classFilter,
        string $tingkatFilter,
        int $waliKelasIdFilter,
        string $statusFilter,
        string $areaFilter,
        int $page,
        int $perPage,
        bool $includeClassSummary,
        bool $includePriorityQueues,
        int $priorityQueueLimit
    ): array {
        $startedAt = microtime(true);
        $records = $this->liveTrackingCurrentStoreService->readRecords(
            $classFilter,
            $tingkatFilter,
            $waliKelasIdFilter
        );
        $candidateCount = count($records);

        if ($records === []) {
            return [
                'used' => false,
                'source' => self::SOURCE_REQUEST_PIPELINE,
                'summary' => [],
                'class_summary' => [],
                'level_summary' => [],
                'homeroom_summary' => [],
                'priority_queues' => [],
                'total' => 0,
                'performance' => [
                    'candidate_count' => 0,
                    'record_count' => 0,
                    'filtered_total' => 0,
                    'duration_ms' => round((microtime(true) - $startedAt) * 1000, 1),
                ],
            ];
        }

        $rows = collect($records)
            ->map(fn (array $record): array => $this->buildCurrentTrackingRowFromCurrentStore($record))
            ->filter(fn (array $row): bool => $this->matchesCurrentTrackingSearch($row, $search))
            ->values();

        $filteredRows = $this->applyCurrentTrackingFilters($rows, $statusFilter, $areaFilter);
        $pageCollectionStartedAt = microtime(true);
        $total = $filteredRows->count();
        $resolvedPage = max(1, $page);
        $pageRows = $perPage > 0
            ? $filteredRows->forPage($resolvedPage, $perPage)->values()->all()
            : $filteredRows->values()->all();

        if ($perPage > 0) {
            $lastPage = max(1, (int) ceil($total / $perPage));
            $resolvedPage = max(1, min($resolvedPage, $lastPage));
            $pageRows = $filteredRows->forPage($resolvedPage, $perPage)->values()->all();
        }
        $pageCollectionDurationMs = round((microtime(true) - $pageCollectionStartedAt) * 1000, 1);

        return [
            'used' => true,
            'source' => self::SOURCE_REDIS_CURRENT_STORE,
            'rows' => $pageRows,
            'summary' => $this->summarizeCurrentRows($filteredRows),
            'class_summary' => $includeClassSummary
                ? $this->summarizeCurrentRowsByClass($filteredRows)
                : [],
            'level_summary' => $includeClassSummary
                ? $this->summarizeCurrentRowsByLevel($filteredRows)
                : [],
            'homeroom_summary' => $includeClassSummary
                ? $this->summarizeCurrentRowsByHomeroomTeacher($filteredRows)
                : [],
            'priority_queues' => $includePriorityQueues
                ? $this->buildCurrentPriorityQueues($filteredRows, 'all', $priorityQueueLimit)
                : [],
            'total' => $total,
            'page' => $resolvedPage,
            'performance' => [
                'candidate_count' => $candidateCount,
                'record_count' => $rows->count(),
                'filtered_total' => $total,
                'page_collection_duration_ms' => $pageCollectionDurationMs,
                'duration_ms' => round((microtime(true) - $startedAt) * 1000, 1),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @return array<string, mixed>
     */
    private function buildCurrentTrackingRowFromCurrentStore(array $record): array
    {
        $effectiveSession = $this->resolveCurrentStoreTrackingSession($record);
        $forceSession = $effectiveSession['is_active']
            ? ['expires_at' => $effectiveSession['expires_at']]
            : null;

        $user = [
            'id' => (int) ($record['user_id'] ?? 0),
            'nama_lengkap' => (string) ($record['nama_lengkap'] ?? 'Unknown'),
            'email' => (string) ($record['email'] ?? ''),
            'kelas' => (string) ($record['kelas'] ?? 'N/A'),
            'tingkat' => (string) ($record['tingkat'] ?? 'N/A'),
            'wali_kelas_id' => $record['wali_kelas_id'] ?? null,
            'wali_kelas' => (string) ($record['wali_kelas'] ?? 'Belum ditentukan'),
        ];

        $hasTrackingData = (bool) ($record['has_tracking_data'] ?? false);
        $snapshotStatus = strtolower(trim((string) ($record['snapshot_status'] ?? 'online')));
        if (!$hasTrackingData || $snapshotStatus === 'no_data') {
            return [
                'user_id' => $user['id'],
                'nis' => (string) ($record['nis'] ?? ''),
                'username' => (string) ($record['username'] ?? ''),
                'latitude' => null,
                'longitude' => null,
                'accuracy' => null,
                'speed' => null,
                'heading' => null,
                'is_in_school_area' => false,
                'within_gps_area' => false,
                'device_info' => [],
                'device_source' => null,
                'gps_quality_status' => 'unknown',
                'ip_address' => null,
                'tracked_at' => null,
                'is_tracking_active' => false,
                'tracking_status' => 'no_data',
                'tracking_status_reason' => $effectiveSession['is_active']
                    ? 'pemantauan_tambahan_aktif'
                    : 'belum_ada_data_hari_ini',
                'tracking_session_active' => $effectiveSession['is_active'],
                'tracking_session_expires_at' => $effectiveSession['expires_at'],
                'location_id' => null,
                'location_name' => 'No tracking data',
                'current_location' => null,
                'nearest_location' => null,
                'distance_to_nearest' => null,
                'stale_threshold_seconds' => $this->staleWindowSeconds(),
                'has_tracking_data' => false,
                'user' => $user,
            ];
        }

        $snapshot = $this->liveTrackingSnapshotService->appendRealtimeStatus([
            'user_id' => $user['id'],
            'latitude' => $record['latitude'] ?? null,
            'longitude' => $record['longitude'] ?? null,
            'accuracy' => $record['accuracy'] ?? null,
            'speed' => $record['speed'] ?? null,
            'heading' => $record['heading'] ?? null,
            'tracked_at' => $record['tracked_at'] ?? now()->toISOString(),
            'status' => $snapshotStatus,
            'is_in_school_area' => (bool) ($record['is_in_school_area'] ?? false),
            'within_gps_area' => (bool) ($record['within_gps_area'] ?? false),
            'location_id' => $record['location_id'] ?? null,
            'location_name' => $record['location_name'] ?? null,
            'current_location' => is_array($record['current_location'] ?? null)
                ? $record['current_location']
                : null,
            'nearest_location' => is_array($record['nearest_location'] ?? null)
                ? $record['nearest_location']
                : null,
            'distance_to_nearest' => $record['distance_to_nearest'] ?? null,
            'gps_quality_status' => $record['gps_quality_status'] ?? 'unknown',
            'device_source' => $record['device_source'] ?? 'unknown',
            'device_info' => is_array($record['device_info'] ?? null)
                ? $record['device_info']
                : [],
            'ip_address' => $record['ip_address'] ?? null,
        ]);
        $snapshot = $this->applyMonitoringScheduleStateToSnapshot($snapshot, $forceSession);

        return [
            'user_id' => $user['id'],
            'nis' => (string) ($record['nis'] ?? ''),
            'username' => (string) ($record['username'] ?? ''),
            'latitude' => $snapshot['latitude'],
            'longitude' => $snapshot['longitude'],
            'accuracy' => $snapshot['accuracy'],
            'speed' => $snapshot['speed'],
            'heading' => $snapshot['heading'],
            'is_in_school_area' => $snapshot['is_in_school_area'],
            'within_gps_area' => $snapshot['within_gps_area'],
            'device_info' => is_array($snapshot['device_info'] ?? null)
                ? $snapshot['device_info']
                : [],
            'device_source' => $snapshot['device_source'],
            'gps_quality_status' => $snapshot['gps_quality_status'],
            'ip_address' => $snapshot['ip_address'],
            'tracked_at' => $snapshot['tracked_at'],
            'is_tracking_active' => $snapshot['is_tracking_active'],
            'tracking_status' => $snapshot['tracking_status'],
            'tracking_status_reason' => $snapshot['tracking_status_reason'],
            'tracking_session_active' => $effectiveSession['is_active'],
            'tracking_session_expires_at' => $effectiveSession['expires_at'],
            'location_id' => $snapshot['location_id'],
            'location_name' => $snapshot['location_name'] ?? 'Unknown Location',
            'current_location' => is_array($snapshot['current_location'] ?? null)
                ? $snapshot['current_location']
                : null,
            'nearest_location' => is_array($snapshot['nearest_location'] ?? null)
                ? $snapshot['nearest_location']
                : null,
            'distance_to_nearest' => $snapshot['distance_to_nearest'] ?? null,
            'stale_threshold_seconds' => $snapshot['stale_threshold_seconds'],
            'has_tracking_data' => true,
            'user' => $user,
        ];
    }

    private function matchesCurrentTrackingSearch(array $row, string $search): bool
    {
        $normalizedSearch = trim(strtolower($search));
        if ($normalizedSearch === '') {
            return true;
        }

        $haystacks = [
            (string) data_get($row, 'user.nama_lengkap', ''),
            (string) data_get($row, 'user.email', ''),
            (string) ($row['nis'] ?? ''),
            (string) ($row['username'] ?? ''),
        ];

        foreach ($haystacks as $haystack) {
            if ($haystack !== '' && str_contains(strtolower($haystack), $normalizedSearch)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $record
     * @return array{is_active:bool, expires_at:?string}
     */
    private function resolveCurrentStoreTrackingSession(array $record): array
    {
        $expiresAt = $record['tracking_session_expires_at'] ?? null;
        if (!(bool) ($record['tracking_session_active'] ?? false) || !is_string($expiresAt) || trim($expiresAt) === '') {
            return [
                'is_active' => false,
                'expires_at' => null,
            ];
        }

        try {
            $expiresAtCarbon = Carbon::parse($expiresAt);
        } catch (\Throwable) {
            return [
                'is_active' => false,
                'expires_at' => null,
            ];
        }

        if ($expiresAtCarbon->lte(now())) {
            return [
                'is_active' => false,
                'expires_at' => null,
            ];
        }

        return [
            'is_active' => true,
            'expires_at' => $expiresAtCarbon->toISOString(),
        ];
    }

    private function streamTrackedStudentRows(Builder $studentsQuery, callable $consumer, array &$performance = []): void
    {
        if (!isset($performance['chunk_count'])) {
            $performance['chunk_count'] = 0;
        }

        if (!isset($performance['snapshot_hit_count'])) {
            $performance['snapshot_hit_count'] = 0;
        }

        $studentsQuery
            ->orderBy('id')
            ->chunkById(200, function (Collection $students) use ($consumer, &$performance): void {
                $performance['chunk_count']++;
                $snapshotsByUserId = collect($this->liveTrackingSnapshotService->getMany(
                    $students->pluck('id')->map(static fn ($id): int => (int) $id)->all()
                ))->keyBy(static fn (array $snapshot): int => (int) $snapshot['user_id']);
                $performance['snapshot_hit_count'] += $snapshotsByUserId->count();

                foreach ($students as $student) {
                    $snapshot = $snapshotsByUserId->get((int) $student->id);
                    $consumer(
                        $this->buildCurrentTrackingRow($student, is_array($snapshot) ? $snapshot : null)
                    );
                }
            }, 'id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function collectCurrentTrackingPageRows(
        Builder $studentsQuery,
        string $statusFilter,
        string $areaFilter,
        int $page,
        int $perPage,
        array &$performance = []
    ): array {
        $offset = max(0, ($page - 1) * $perPage);
        $rows = [];
        $filteredTotal = 0;

        $this->streamTrackedStudentRows($studentsQuery, function (array $row) use (
            $statusFilter,
            $areaFilter,
            $offset,
            $perPage,
            &$rows,
            &$filteredTotal,
            &$performance
        ): void {
            $performance['processed_row_count'] = (int) ($performance['processed_row_count'] ?? 0) + 1;
            if (!$this->matchesCurrentTrackingFilters($row, $statusFilter, $areaFilter)) {
                return;
            }

            $filteredTotal++;
            if ($filteredTotal <= $offset || count($rows) >= $perPage) {
                return;
            }

            $rows[] = $row;
        }, $performance);

        return $rows;
    }

    private function matchesCurrentTrackingFilters(array $row, string $statusFilter, string $areaFilter): bool
    {
        $normalizedStatus = match ($statusFilter) {
            'inactive' => 'outside_area',
            'no-data' => 'no_data',
            default => $statusFilter,
        };

        if ($normalizedStatus !== '' && $normalizedStatus !== 'all') {
            if ((string) ($row['tracking_status'] ?? 'no_data') !== $normalizedStatus) {
                return false;
            }
        }

        if ($areaFilter === 'inside') {
            return (bool) ($row['has_tracking_data'] ?? false) && (bool) ($row['is_in_school_area'] ?? false);
        }

        if ($areaFilter === 'outside') {
            return (bool) ($row['has_tracking_data'] ?? false) && !(bool) ($row['is_in_school_area'] ?? false);
        }

        return true;
    }

    /**
     * @return array<string, int>
     */
    private function createCurrentSummaryAccumulator(): array
    {
        return [
            'total' => 0,
            'active' => 0,
            'outside_area' => 0,
            'stale' => 0,
            'gps_disabled' => 0,
            'tracking_disabled' => 0,
            'outside_schedule' => 0,
            'no_data' => 0,
            'inside_area' => 0,
            'poor_gps' => 0,
            'moderate_gps' => 0,
        ];
    }

    private function accumulateCurrentSummary(array &$summary, array $row): void
    {
        $summary['total']++;

        $status = (string) ($row['tracking_status'] ?? 'no_data');
        if (array_key_exists($status, $summary)) {
            $summary[$status]++;
        }

        if ((bool) ($row['has_tracking_data'] ?? false) && (bool) ($row['is_in_school_area'] ?? false)) {
            $summary['inside_area']++;
        }

        $gpsQualityStatus = (string) ($row['gps_quality_status'] ?? '');
        if ($gpsQualityStatus === 'poor') {
            $summary['poor_gps']++;
        } elseif ($gpsQualityStatus === 'moderate') {
            $summary['moderate_gps']++;
        }
    }

    /**
     * @param array<string, int> $summary
     * @return array<string, int>
     */
    private function finalizeCurrentSummary(array $summary): array
    {
        return $summary;
    }

    private function accumulateClassSummary(array &$groups, array $row): void
    {
        $className = trim((string) data_get($row, 'user.kelas', 'N/A'));
        $className = $className !== '' ? $className : 'N/A';

        if (!isset($groups[$className])) {
            $groups[$className] = [
                'class_name' => $className,
                'summary' => $this->createCurrentSummaryAccumulator(),
            ];
        }

        $this->accumulateCurrentSummary($groups[$className]['summary'], $row);
    }

    /**
     * @param array<string, array{class_name:string, summary:array<string, int>}> $groups
     * @return array<int, array<string, mixed>>
     */
    private function finalizeCurrentClassSummary(array $groups): array
    {
        $rows = array_map(function (array $group): array {
            $summary = $group['summary'];
            $trackedCount = max(0, (int) $summary['total'] - (int) $summary['no_data']);
            $exceptionCount = (int) $summary['outside_area']
                + (int) $summary['stale']
                + (int) $summary['gps_disabled'];

            return [
                'class_name' => $group['class_name'],
                'total' => (int) $summary['total'],
                'tracked' => $trackedCount,
                'active' => (int) $summary['active'],
                'outside_area' => (int) $summary['outside_area'],
                'stale' => (int) $summary['stale'],
                'gps_disabled' => (int) $summary['gps_disabled'],
                'tracking_disabled' => (int) ($summary['tracking_disabled'] ?? 0),
                'outside_schedule' => (int) ($summary['outside_schedule'] ?? 0),
                'no_data' => (int) $summary['no_data'],
                'inside_area' => (int) $summary['inside_area'],
                'poor_gps' => (int) $summary['poor_gps'],
                'moderate_gps' => (int) $summary['moderate_gps'],
                'exception_count' => $exceptionCount,
                'tracked_rate' => (int) $summary['total'] > 0
                    ? round(($trackedCount / (int) $summary['total']) * 100, 1)
                    : 0.0,
                'exception_rate' => (int) $summary['total'] > 0
                    ? round(($exceptionCount / (int) $summary['total']) * 100, 1)
                    : 0.0,
            ];
        }, array_values($groups));

        usort($rows, function (array $left, array $right): int {
            $rateComparison = ($right['exception_rate'] <=> $left['exception_rate']);
            if ($rateComparison !== 0) {
                return $rateComparison;
            }

            $countComparison = ($right['exception_count'] <=> $left['exception_count']);
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strcmp((string) $left['class_name'], (string) $right['class_name']);
        });

        return array_values($rows);
    }

    private function accumulateLevelSummary(array &$groups, array $row): void
    {
        $levelName = trim((string) data_get($row, 'user.tingkat', 'N/A'));
        $levelName = $levelName !== '' ? $levelName : 'N/A';

        if (!isset($groups[$levelName])) {
            $groups[$levelName] = [
                'level_name' => $levelName,
                'summary' => $this->createCurrentSummaryAccumulator(),
            ];
        }

        $this->accumulateCurrentSummary($groups[$levelName]['summary'], $row);
    }

    /**
     * @param array<string, array{level_name:string, summary:array<string, int>}> $groups
     * @return array<int, array<string, mixed>>
     */
    private function finalizeCurrentLevelSummary(array $groups): array
    {
        $rows = array_map(function (array $group): array {
            $summary = $group['summary'];
            $trackedCount = max(0, (int) $summary['total'] - (int) $summary['no_data']);
            $exceptionCount = (int) $summary['outside_area']
                + (int) $summary['stale']
                + (int) $summary['gps_disabled'];

            return [
                'level_name' => $group['level_name'],
                'total' => (int) $summary['total'],
                'tracked' => $trackedCount,
                'active' => (int) $summary['active'],
                'outside_area' => (int) $summary['outside_area'],
                'stale' => (int) $summary['stale'],
                'gps_disabled' => (int) $summary['gps_disabled'],
                'tracking_disabled' => (int) ($summary['tracking_disabled'] ?? 0),
                'outside_schedule' => (int) ($summary['outside_schedule'] ?? 0),
                'no_data' => (int) $summary['no_data'],
                'exception_count' => $exceptionCount,
                'tracked_rate' => (int) $summary['total'] > 0
                    ? round(($trackedCount / (int) $summary['total']) * 100, 1)
                    : 0.0,
                'exception_rate' => (int) $summary['total'] > 0
                    ? round(($exceptionCount / (int) $summary['total']) * 100, 1)
                    : 0.0,
            ];
        }, array_values($groups));

        usort($rows, function (array $left, array $right): int {
            $rateComparison = ($right['exception_rate'] <=> $left['exception_rate']);
            if ($rateComparison !== 0) {
                return $rateComparison;
            }

            $countComparison = ($right['exception_count'] <=> $left['exception_count']);
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strcmp((string) $left['level_name'], (string) $right['level_name']);
        });

        return array_values($rows);
    }

    private function accumulateHomeroomSummary(array &$groups, array $row): void
    {
        $homeroomTeacherId = data_get($row, 'user.wali_kelas_id');
        $groupKey = $homeroomTeacherId !== null
            ? (string) (int) $homeroomTeacherId
            : 'unassigned';
        $teacherName = trim((string) data_get($row, 'user.wali_kelas', 'Belum ditentukan'));
        $teacherName = $teacherName !== '' ? $teacherName : 'Belum ditentukan';

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'wali_kelas_id' => $groupKey !== 'unassigned' ? (int) $groupKey : null,
                'wali_kelas_name' => $teacherName,
                'summary' => $this->createCurrentSummaryAccumulator(),
                'class_set' => [],
                'level_set' => [],
            ];
        }

        $className = trim((string) data_get($row, 'user.kelas', 'N/A'));
        if ($className !== '' && $className !== 'N/A') {
            $groups[$groupKey]['class_set'][$className] = true;
        }

        $levelName = trim((string) data_get($row, 'user.tingkat', 'N/A'));
        if ($levelName !== '' && $levelName !== 'N/A') {
            $groups[$groupKey]['level_set'][$levelName] = true;
        }

        $this->accumulateCurrentSummary($groups[$groupKey]['summary'], $row);
    }

    /**
     * @param array<string, array{wali_kelas_id:int|null, wali_kelas_name:string, summary:array<string, int>, class_set:array<string, bool>, level_set:array<string, bool>}> $groups
     * @return array<int, array<string, mixed>>
     */
    private function finalizeCurrentHomeroomSummary(array $groups): array
    {
        $rows = array_map(function (array $group): array {
            $summary = $group['summary'];
            $trackedCount = max(0, (int) $summary['total'] - (int) $summary['no_data']);
            $exceptionCount = (int) $summary['outside_area']
                + (int) $summary['stale']
                + (int) $summary['gps_disabled'];

            return [
                'wali_kelas_id' => $group['wali_kelas_id'],
                'wali_kelas_name' => $group['wali_kelas_name'],
                'total' => (int) $summary['total'],
                'tracked' => $trackedCount,
                'active' => (int) $summary['active'],
                'outside_area' => (int) $summary['outside_area'],
                'stale' => (int) $summary['stale'],
                'gps_disabled' => (int) $summary['gps_disabled'],
                'tracking_disabled' => (int) ($summary['tracking_disabled'] ?? 0),
                'outside_schedule' => (int) ($summary['outside_schedule'] ?? 0),
                'no_data' => (int) $summary['no_data'],
                'exception_count' => $exceptionCount,
                'class_count' => count($group['class_set']),
                'level_count' => count($group['level_set']),
                'tracked_rate' => (int) $summary['total'] > 0
                    ? round(($trackedCount / (int) $summary['total']) * 100, 1)
                    : 0.0,
                'exception_rate' => (int) $summary['total'] > 0
                    ? round(($exceptionCount / (int) $summary['total']) * 100, 1)
                    : 0.0,
            ];
        }, array_values($groups));

        usort($rows, function (array $left, array $right): int {
            $rateComparison = ($right['exception_rate'] <=> $left['exception_rate']);
            if ($rateComparison !== 0) {
                return $rateComparison;
            }

            $countComparison = ($right['exception_count'] <=> $left['exception_count']);
            if ($countComparison !== 0) {
                return $countComparison;
            }

            return strcmp((string) $left['wali_kelas_name'], (string) $right['wali_kelas_name']);
        });

        return array_values($rows);
    }

    private function appendRowToPriorityQueues(array &$queues, array $row, int $limit): void
    {
        $status = (string) ($row['tracking_status'] ?? 'no_data');
        if (!array_key_exists($status, $queues)) {
            return;
        }

        if (count($queues[$status]) >= $limit) {
            return;
        }

        $queues[$status][] = $row;
    }

    /**
     * @param Collection<int, array<string, mixed>> $rows
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildCurrentPriorityQueues(Collection $rows, string $areaFilter, int $limit): array
    {
        return [
            'gps_disabled' => $this->applyCurrentTrackingFilters($rows, 'gps_disabled', $areaFilter)
                ->take($limit)
                ->values()
                ->all(),
            'stale' => $this->applyCurrentTrackingFilters($rows, 'stale', $areaFilter)
                ->take($limit)
                ->values()
                ->all(),
            'outside_area' => $this->applyCurrentTrackingFilters($rows, 'outside_area', $areaFilter)
                ->take($limit)
                ->values()
                ->all(),
        ];
    }

    private function baseTrackedStudentsQuery(?User $actor): Builder
    {
        $query = User::query()
            ->select([
                'users.id',
                'users.nama_lengkap',
                'users.email',
                'users.nis',
                'users.username',
            ])
            ->whereHas('roles', function (Builder $query): void {
                $query->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->with([
                'kelas' => function ($kelasQuery): void {
                    $kelasQuery->select([
                        'kelas.id',
                        'kelas.nama_kelas',
                        'kelas.tingkat_id',
                        'kelas.wali_kelas_id',
                    ]);
                },
                'kelas.tingkat:id,nama',
                'kelas.waliKelas:id,nama_lengkap',
            ]);

        RoleDataScope::applySiswaReadScope($query, $actor);

        return $query;
    }

    private function canAccessTrackedStudent(?User $actor, int $targetUserId): bool
    {
        if (!$actor) {
            return false;
        }

        if ((int) $actor->id === $targetUserId) {
            return true;
        }

        return $this->baseTrackedStudentsQuery($actor)
            ->where('id', $targetUserId)
            ->exists();
    }

    private function resolveTrackedUser(int $userId): ?User
    {
        return User::query()
            ->with(['kelas.tingkat', 'kelas.waliKelas:id,nama_lengkap'])
            ->find($userId);
    }

    /**
     * Apply a neutral state when the monitoring roster is outside the common student schedule.
     *
     * This endpoint can return hundreds of rows, so it intentionally uses a shared
     * monitoring window instead of resolving per-student calendar checks one by one.
     *
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed>|null $forceSession
     * @return array<string, mixed>
     */
    private function applyMonitoringScheduleStateToSnapshot(array $snapshot, ?array $forceSession = null): array
    {
        if (!$this->isLiveTrackingGloballyEnabled()) {
            return $this->markTrackingDisabled($snapshot);
        }

        if (!empty($forceSession)) {
            return $snapshot;
        }

        if (($snapshot['tracking_status'] ?? null) === 'no_data') {
            return $snapshot;
        }

        if ($this->isWithinMonitoringWindow()) {
            return $snapshot;
        }

        return $this->markOutsideSchedule($snapshot);
    }

    /**
     * Apply the user's actual working window for single-user responses.
     *
     * @param array<string, mixed> $snapshot
     * @param array<string, mixed>|null $forceSession
     * @return array<string, mixed>
     */
    private function applyUserScheduleStateToSnapshot(User $user, array $snapshot, ?array $forceSession = null): array
    {
        if (!$this->isLiveTrackingGloballyEnabled()) {
            return $this->markTrackingDisabled($snapshot);
        }

        if (!empty($forceSession)) {
            return $snapshot;
        }

        if (($snapshot['tracking_status'] ?? null) === 'no_data') {
            return $snapshot;
        }

        $now = now();
        if (!$this->attendanceTimeService->isWorkingDay($user, $now)) {
            return $this->markOutsideSchedule($snapshot);
        }

        $workingHours = $this->attendanceTimeService->getWorkingHours($user);
        if ($this->isWithinWorkingHoursWindow($workingHours, $now)) {
            return $snapshot;
        }

        return $this->markOutsideSchedule($snapshot);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function markOutsideSchedule(array $snapshot): array
    {
        $snapshot['tracking_status'] = self::STATUS_OUTSIDE_SCHEDULE;
        $snapshot['tracking_status_reason'] = 'di_luar_jadwal_tracking';
        $snapshot['is_tracking_active'] = false;

        return $snapshot;
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    private function markTrackingDisabled(array $snapshot): array
    {
        if (($snapshot['tracking_status'] ?? null) === 'no_data') {
            return $snapshot;
        }

        $snapshot['tracking_status'] = self::STATUS_TRACKING_DISABLED;
        $snapshot['tracking_status_reason'] = 'tracking_dinonaktifkan_admin';
        $snapshot['is_tracking_active'] = false;

        return $snapshot;
    }

    private function isLiveTrackingGloballyEnabled(): bool
    {
        static $cachedVersion = null;
        static $cachedEnabled = null;

        $runtimeVersion = (int) $this->safeCacheGet('attendance_runtime_version', 1);
        if (is_bool($cachedEnabled) && $cachedVersion === $runtimeVersion) {
            return $cachedEnabled;
        }

        $runtimeConfig = app(\App\Services\AttendanceRuntimeConfigService::class)->getLiveTrackingConfig();
        $cachedVersion = $runtimeVersion;
        $cachedEnabled = (bool) ($runtimeConfig['enabled'] ?? true);

        return $cachedEnabled;
    }

    private function safeCacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (\Throwable $exception) {
            Log::warning('Live tracking controller cache get failed', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return $default;
        }
    }

    private function safeCachePut(string $key, mixed $value, \DateTimeInterface|\DateInterval|int|null $ttl = null): void
    {
        try {
            Cache::put($key, $value, $ttl);
        } catch (\Throwable $exception) {
            Log::warning('Live tracking controller cache put failed', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function safeCacheHas(string $key): bool
    {
        try {
            return Cache::has($key);
        } catch (\Throwable $exception) {
            Log::warning('Live tracking controller cache has failed', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function safeCacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $exception) {
            Log::warning('Live tracking controller cache forget failed', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function isWithinMonitoringWindow(): bool
    {
        return $this->isWithinWorkingHoursWindow($this->monitoringWorkingHours(), now());
    }

    /**
     * @return array{jam_masuk:string,jam_pulang:string,hari_kerja:array<int,string>}
     */
    private function monitoringWorkingHours(): array
    {
        static $cached = null;

        if (is_array($cached)) {
            return $cached;
        }

        $schema = $this->resolveStudentTrackingSchema();
        $candidate = $schema?->getEffectiveWorkingHours() ?? [];

        $cached = [
            'jam_masuk' => (string) ($candidate['jam_masuk'] ?? '07:00'),
            'jam_pulang' => (string) ($candidate['jam_pulang'] ?? '14:00'),
            'hari_kerja' => $this->normalizeWorkingDays($schema?->hari_kerja ?? null),
        ];

        return $cached;
    }

    private function resolveStudentTrackingSchema(): ?AttendanceSchema
    {
        $studentRoleAliases = RoleNames::aliases(RoleNames::SISWA);

        $studentSchema = AttendanceSchema::query()
            ->where('is_active', true)
            ->whereIn('target_role', $studentRoleAliases)
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->first();

        if ($studentSchema instanceof AttendanceSchema) {
            return $studentSchema;
        }

        return AttendanceSchema::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * @param array<string, mixed> $workingHours
     */
    private function isWithinWorkingHoursWindow(array $workingHours, Carbon $currentTime): bool
    {
        $normalizedWorkingDays = array_values(array_filter(array_map(
            fn($day) => $this->normalizeDayToken((string) $day),
            $this->normalizeWorkingDays($workingHours['hari_kerja'] ?? null)
        )));

        if ($normalizedWorkingDays !== []) {
            $dayAliases = $this->dayAliases($currentTime);
            if (!array_intersect($dayAliases, $normalizedWorkingDays)) {
                return false;
            }
        }

        $jamMulai = $this->parseWorkTime((string) ($workingHours['jam_masuk'] ?? '07:00'));
        $jamSelesai = $this->parseWorkTime((string) ($workingHours['jam_pulang'] ?? '14:00'));

        if ($jamSelesai->lt($jamMulai)) {
            $jamSelesai = $jamMulai->copy()->addHours(8);
        }

        $currentClock = Carbon::createFromFormat('H:i:s', $currentTime->format('H:i:s'));

        return !$currentClock->lt($jamMulai) && !$currentClock->gt($jamSelesai);
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeWorkingDays(mixed $value): array
    {
        $default = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        if (is_array($value)) {
            $days = array_values(array_filter(array_map(static fn ($item) => trim((string) $item), $value)));
            return $days !== [] ? $days : $default;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return $default;
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $this->normalizeWorkingDays($decoded);
            }

            $days = array_values(array_filter(array_map('trim', explode(',', $trimmed))));
            return $days !== [] ? $days : $default;
        }

        return $default;
    }

    private function normalizeDayToken(string $value): string
    {
        $normalized = strtolower(trim($value));
        return str_replace(["'", 'â€™', '`', ' '], '', $normalized);
    }

    /**
     * @return array<int, string>
     */
    private function dayAliases(Carbon $date): array
    {
        return match ($date->dayOfWeek) {
            Carbon::MONDAY => ['senin', 'monday'],
            Carbon::TUESDAY => ['selasa', 'tuesday'],
            Carbon::WEDNESDAY => ['rabu', 'wednesday'],
            Carbon::THURSDAY => ['kamis', 'thursday'],
            Carbon::FRIDAY => ['jumat', "jum'at", 'friday'],
            Carbon::SATURDAY => ['sabtu', 'saturday'],
            Carbon::SUNDAY => ['minggu', 'sunday'],
            default => [],
        };
    }

    private function parseWorkTime(string $time): Carbon
    {
        $normalized = trim($time);
        if ($normalized === '') {
            $normalized = '07:00:00';
        }

        try {
            return Carbon::createFromFormat('H:i:s', $normalized);
        } catch (\Throwable) {
            return Carbon::createFromFormat('H:i', substr($normalized, 0, 5));
        }
    }

}

