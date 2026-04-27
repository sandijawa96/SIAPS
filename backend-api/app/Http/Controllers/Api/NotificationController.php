<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Helpers\AuthHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NotificationController extends Controller
{
    public function __construct(
        private PushNotificationService $pushNotificationService
    ) {
    }

    public function index(Request $request)
    {
        $query = $this->notificationQueryForCurrentUser($request);
        $this->applyCategoryFilter($query, $request->get('category'));
        $this->applyPopupFilter($query, $request->get('popup'));
        $this->applyLifecycleFilter($query, $request);

        // Filter berdasarkan status read
        if ($request->has('is_read')) {
            $query->where('is_read', $request->is_read);
        }

        // Filter berdasarkan tipe
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query
            ->orderByRaw('pinned_at IS NULL')
            ->orderByDesc('priority')
            ->orderByDesc('created_at')
            ->paginate(max(1, min((int) $request->get('per_page', 15), 100)));

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|string|in:info,warning,success,error',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $notifications = [];
        $pushSummary = $this->makePushSummary();
        foreach ($request->user_ids as $user_id) {
            $notification = Notification::create([
                'user_id' => $user_id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'data' => $request->data,
                'is_read' => false,
                'created_by' => AuthHelper::userId()
            ]);
            $pushResult = $this->sendPushNotification($notification);
            $this->mergePushSummary($pushSummary, $pushResult);

            $notifications[] = $notification;
        }

        Log::info('Notification store push summary', [
            'created_by' => AuthHelper::userId(),
            'recipient_count' => count($notifications),
            'push' => $pushSummary,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil dibuat',
            'data' => [
                'notifications' => $notifications,
                'push' => $pushSummary,
            ]
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $notification = $this->notificationQueryForCurrentUser($request)->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $notification
        ]);
    }

    public function markAsRead(Request $request, $id)
    {
        $notification = $this->notificationQueryForCurrentUser($request)->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan'
            ], 404);
        }

        $notification->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil ditandai sebagai telah dibaca',
            'data' => $notification
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $query = $this->notificationQueryForCurrentUser($request)
            ->where('is_read', false);
        $this->applyCategoryFilter($query, $request->get('category'));
        $this->applyLifecycleFilter($query, $request);
        $query->update(['is_read' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Semua notifikasi berhasil ditandai sebagai telah dibaca'
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $notification = $this->notificationQueryForCurrentUser($request)->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi tidak ditemukan'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil dihapus'
        ]);
    }

    public function broadcast(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|string|in:info,warning,success,error',
            'role' => 'nullable|string|exists:roles,name',
            'data' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get target users
        $query = \App\Models\User::query()
            ->where('is_active', true);
        if ($request->has('role')) {
            $query->role($request->role);
        }
        $users = $query->get();

        $notifications = [];
        $pushSummary = $this->makePushSummary();
        foreach ($users as $user) {
            $notification = Notification::create([
                'user_id' => $user->id,
                'title' => $request->title,
                'message' => $request->message,
                'type' => $request->type,
                'data' => $request->data,
                'is_read' => false,
                'created_by' => AuthHelper::userId()
            ]);
            $pushResult = $this->sendPushNotification($notification);
            $this->mergePushSummary($pushSummary, $pushResult);

            $notifications[] = $notification;
        }

        Log::info('Notification broadcast push summary', [
            'created_by' => AuthHelper::userId(),
            'recipient_count' => count($notifications),
            'push' => $pushSummary,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Broadcast notifikasi berhasil',
            'data' => [
                'total_recipients' => count($users),
                'notifications' => $notifications,
                'push' => $pushSummary,
            ]
        ]);
    }

    public function getUnreadCount(Request $request)
    {
        $baseQuery = $this->notificationQueryForCurrentUser($request)
            ->where('is_read', false);
        $this->applyLifecycleFilter($baseQuery, $request);

        $totalCount = (clone $baseQuery)->count();

        $announcementQuery = clone $baseQuery;
        $this->applyCategoryFilter($announcementQuery, 'announcement');
        $announcementCount = $announcementQuery->count();

        $systemQuery = clone $baseQuery;
        $this->applyCategoryFilter($systemQuery, 'system');
        $systemCount = $systemQuery->count();

        $count = $totalCount;
        $requestedCategory = strtolower(trim((string) $request->get('category', '')));
        if (in_array($requestedCategory, ['announcement', 'system'], true)) {
            $categoryQuery = clone $baseQuery;
            $this->applyCategoryFilter($categoryQuery, $requestedCategory);
            $count = $categoryQuery->count();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $count,
                'unread_count_total' => $totalCount,
                'system_unread_count' => $systemCount,
                'announcement_unread_count' => $announcementCount,
            ]
        ]);
    }

    private function sendPushNotification($notification)
    {
        return $this->pushNotificationService->sendNotification($notification);
    }

    private function makePushSummary(): array
    {
        $summary = $this->pushNotificationService->getConfigurationSummary();

        return [
            'mode' => (string) ($summary['mode'] ?? 'in_app_only'),
            'configured' => (bool) ($summary['configured'] ?? false),
            'sent' => 0,
            'skipped' => 0,
            'attempted_tokens' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'success_by_device_type' => [],
            'failure_by_device_type' => [],
            'results' => [],
            'message' => (string) ($summary['message'] ?? 'Push real-time belum dikonfigurasi.'),
        ];
    }

    private function mergePushSummary(array &$summary, array $result): void
    {
        $summary['mode'] = (string) ($result['mode'] ?? $summary['mode']);
        $summary['configured'] = (bool) ($result['configured'] ?? $summary['configured']);
        $summary['sent'] += (int) (($result['sent'] ?? false) === true);
        $summary['skipped'] += (int) (($result['sent'] ?? false) !== true);
        $summary['attempted_tokens'] += (int) ($result['attempted_tokens'] ?? 0);
        $summary['success_count'] += (int) ($result['success_count'] ?? 0);
        $summary['failure_count'] += (int) ($result['failure_count'] ?? 0);
        $this->mergeCounterMap(
            $summary['success_by_device_type'],
            is_array($result['success_by_device_type'] ?? null) ? $result['success_by_device_type'] : []
        );
        $this->mergeCounterMap(
            $summary['failure_by_device_type'],
            is_array($result['failure_by_device_type'] ?? null) ? $result['failure_by_device_type'] : []
        );

        if (is_array($result['results'] ?? null) && $result['results'] !== []) {
            $summary['results'] = array_values(array_merge($summary['results'], $result['results']));
            if (count($summary['results']) > 50) {
                $summary['results'] = array_slice($summary['results'], -50);
            }
        }

        if (!empty($result['message'])) {
            $summary['message'] = (string) $result['message'];
        }
    }

    private function mergeCounterMap(array &$target, array $incoming): void
    {
        foreach ($incoming as $key => $value) {
            $type = strtolower(trim((string) $key));
            if ($type === '') {
                $type = 'unknown';
            }

            if (!isset($target[$type])) {
                $target[$type] = 0;
            }

            $target[$type] += (int) $value;
        }
    }

    private function notificationQueryForCurrentUser(Request $request)
    {
        $query = Notification::query()->where('user_id', AuthHelper::userId());
        $platform = $this->resolveDisplayPlatform($request);

        if ($platform === null) {
            return $query;
        }

        $driver = $query->getModel()->getConnection()->getDriverName();

        return $query->where(function ($scopedQuery) use ($driver, $platform) {
            if ($driver === 'sqlite') {
                $jsonPath = '$.presentation.targets.' . $platform;
                $scopedQuery->whereRaw(
                    "COALESCE(json_extract(data, ?), 1) = 1",
                    [$jsonPath]
                );
                return;
            }

            $scopedQuery
                ->whereRaw("(data->'presentation'->'targets') IS NULL")
                ->orWhereRaw(
                    "COALESCE((data->'presentation'->'targets'->>'{$platform}')::boolean, true) = true"
                );
        });
    }

    private function resolveDisplayPlatform(Request $request): ?string
    {
        $clientPlatform = strtolower(trim((string) $request->header('X-Client-Platform', '')));
        $clientApp = strtolower(trim((string) $request->header('X-Client-App', '')));
        $value = $clientPlatform !== '' ? $clientPlatform : $clientApp;

        if (in_array($value, ['web', 'dashboard-web', 'browser'], true)) {
            return 'web';
        }

        if (in_array($value, ['mobile', 'mobileapp', 'mobile-app', 'android', 'ios', 'app'], true)) {
            return 'mobile';
        }

        return null;
    }

    private function applyCategoryFilter($query, mixed $category): void
    {
        $normalizedCategory = strtolower(trim((string) $category));
        if (!in_array($normalizedCategory, ['announcement', 'system'], true)) {
            return;
        }

        [$announcementSql, $bindings] = $this->announcementCategoryExpression(
            $query->getModel()->getConnection()->getDriverName()
        );

        if ($normalizedCategory === 'announcement') {
            $query->whereRaw($announcementSql, $bindings);
            return;
        }

        $query->whereRaw('NOT (' . $announcementSql . ')', $bindings);
    }

    private function applyPopupFilter($query, mixed $popup): void
    {
        $normalized = strtolower(trim((string) $popup));
        if (!in_array($normalized, ['1', 'true', 'yes'], true)) {
            return;
        }

        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $query->whereRaw(
                "LOWER(COALESCE(CAST(json_extract(data, '$.presentation.popup') AS TEXT), 'false')) IN ('1', 'true')"
            );
            return;
        }

        $query->whereRaw(
            "LOWER(COALESCE(data->'presentation'->>'popup', 'false')) IN ('1', 'true')"
        );
    }

    private function applyLifecycleFilter($query, Request $request): void
    {
        if ($request->boolean('include_expired')) {
            return;
        }

        $scope = strtolower(trim((string) $request->get('scope', 'active')));
        if ($scope === 'all') {
            return;
        }

        $now = now();
        $legacyAnnouncementCutoff = $now->copy()->subDays(30);

        if ($scope === 'expired') {
            $query->whereNull('pinned_at')
                ->where(function ($expiredQuery) use ($now, $legacyAnnouncementCutoff) {
                    $expiredQuery
                        ->where('display_end_at', '<', $now)
                        ->orWhere('expires_at', '<', $now)
                        ->orWhere(function ($legacyQuery) use ($legacyAnnouncementCutoff) {
                            $this->applyLegacyAnnouncementExpiredFallback($legacyQuery, $legacyAnnouncementCutoff);
                        });
                });
            return;
        }

        $query->where(function ($activeQuery) use ($now, $legacyAnnouncementCutoff) {
            $activeQuery
                ->whereNotNull('pinned_at')
                ->orWhere(function ($boundedQuery) use ($now, $legacyAnnouncementCutoff) {
                    $boundedQuery
                        ->where(function ($startQuery) use ($now) {
                            $startQuery
                                ->whereNull('display_start_at')
                                ->orWhere('display_start_at', '<=', $now);
                        })
                        ->where(function ($endQuery) use ($now) {
                            $endQuery
                                ->whereNull('display_end_at')
                                ->orWhere('display_end_at', '>=', $now);
                        })
                        ->where(function ($expiresQuery) use ($now) {
                            $expiresQuery
                                ->whereNull('expires_at')
                                ->orWhere('expires_at', '>=', $now);
                        })
                        ->where(function ($legacyQuery) use ($legacyAnnouncementCutoff) {
                            $this->applyLegacyAnnouncementActiveFallback($legacyQuery, $legacyAnnouncementCutoff);
                        });
                });
        });
    }

    private function applyLegacyAnnouncementActiveFallback($query, Carbon $cutoff): void
    {
        [$announcementSql, $bindings] = $this->announcementCategoryExpression(
            $query->getModel()->getConnection()->getDriverName()
        );

        $query
            ->whereRaw('NOT (' . $announcementSql . ')', $bindings)
            ->orWhereNotNull('display_start_at')
            ->orWhereNotNull('display_end_at')
            ->orWhereNotNull('expires_at')
            ->orWhereNotNull('pinned_at')
            ->orWhere('created_at', '>=', $cutoff);
    }

    private function applyLegacyAnnouncementExpiredFallback($query, Carbon $cutoff): void
    {
        [$announcementSql, $bindings] = $this->announcementCategoryExpression(
            $query->getModel()->getConnection()->getDriverName()
        );

        $query
            ->whereRaw($announcementSql, $bindings)
            ->whereNull('display_start_at')
            ->whereNull('display_end_at')
            ->whereNull('expires_at')
            ->whereNull('pinned_at')
            ->where('created_at', '<', $cutoff);
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function announcementCategoryExpression(string $driver): array
    {
        if ($driver === 'sqlite') {
            return [
                "(
                    LOWER(COALESCE(json_extract(data, '$.message_category'), '')) = 'announcement'
                    OR (
                        json_extract(data, '$.message_category') IS NULL
                        AND (
                            json_extract(data, '$.broadcast_campaign_id') IS NOT NULL
                            OR LOWER(COALESCE(json_extract(data, '$.source'), '')) LIKE ?
                        )
                    )
                )",
                ['%broadcast%'],
            ];
        }

        return [
            "(
                LOWER(COALESCE(data->>'message_category', '')) = 'announcement'
                OR (
                    (data->>'message_category') IS NULL
                    AND (
                        (data->>'broadcast_campaign_id') IS NOT NULL
                        OR LOWER(COALESCE(data->>'source', '')) LIKE ?
                    )
                )
            )",
            ['%broadcast%'],
        ];
    }
}
