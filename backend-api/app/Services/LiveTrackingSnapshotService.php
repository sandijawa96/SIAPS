<?php

namespace App\Services;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LiveTrackingSnapshotService
{
    private const SNAPSHOT_CACHE_PREFIX = 'live_tracking:snapshot:';
    private const ACTIVE_USER_IDS_CACHE_KEY = 'live_tracking:active_user_ids';

    private const STATUS_ACTIVE = 'active';
    private const STATUS_OUTSIDE_AREA = 'outside_area';
    private const STATUS_STALE = 'stale';
    private const STATUS_GPS_DISABLED = 'gps_disabled';
    private const STATUS_NO_DATA = 'no_data';

    public function put(array $snapshot): array
    {
        $normalized = $this->normalizeSnapshot($snapshot);
        if (!$normalized) {
            return [];
        }

        $trackedAt = Carbon::parse((string) $normalized['tracked_at']);
        $expiresAt = $this->resolveExpiry($trackedAt);
        $userId = (int) $normalized['user_id'];

        $this->safeCachePut($this->cacheKey($userId), $normalized, $expiresAt);
        $this->rememberActiveUserId($userId, $expiresAt);

        return $normalized;
    }

    public function get(int $userId, bool $todayOnly = true): ?array
    {
        $snapshot = $this->safeCacheGet($this->cacheKey($userId));
        $normalized = $this->normalizeSnapshot(is_array($snapshot) ? $snapshot : null);

        if (!$normalized) {
            $this->forget($userId);
            return null;
        }

        $trackedAt = Carbon::parse((string) $normalized['tracked_at']);
        if ($todayOnly && !$trackedAt->isToday()) {
            return null;
        }

        return $normalized;
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, array<string, mixed>>
     */
    public function getMany(array $userIds = [], bool $todayOnly = true): array
    {
        $ids = $userIds !== [] ? $userIds : $this->indexedUserIds();
        $normalizedIds = array_values(array_filter(
            array_map(static fn ($rawId): int => (int) $rawId, $ids),
            static fn (int $userId): bool => $userId > 0
        ));

        if ($normalizedIds === []) {
            if ($userIds === []) {
                $this->syncActiveUserIds([]);
            }

            return [];
        }

        $cachedSnapshots = $this->safeCacheMany(array_map(
            fn (int $userId): string => $this->cacheKey($userId),
            $normalizedIds
        ));
        $snapshots = [];
        $validIds = [];

        foreach ($normalizedIds as $userId) {
            $snapshot = $cachedSnapshots[$this->cacheKey($userId)] ?? null;
            $normalized = $this->normalizeSnapshot(is_array($snapshot) ? $snapshot : null);

            if (!$normalized) {
                $this->forget($userId);
                continue;
            }

            $trackedAt = Carbon::parse((string) $normalized['tracked_at']);
            if ($todayOnly && !$trackedAt->isToday()) {
                continue;
            }

            $snapshots[] = $normalized;
            $validIds[] = $userId;
        }

        $this->syncActiveUserIds($validIds);

        usort($snapshots, function (array $left, array $right): int {
            return strcmp((string) ($right['tracked_at'] ?? ''), (string) ($left['tracked_at'] ?? ''));
        });

        return $snapshots;
    }

    public function forget(int $userId): void
    {
        $this->safeCacheForget($this->cacheKey($userId));
        $this->syncActiveUserIds(array_values(array_filter(
            $this->indexedUserIds(),
            static fn ($id): bool => (int) $id !== $userId
        )));
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>
     */
    public function appendRealtimeStatus(
        array $snapshot,
        ?CarbonInterface $now = null,
        ?int $staleWindowSeconds = null
    ): array {
        $trackedAt = Carbon::parse((string) ($snapshot['tracked_at'] ?? now()->toISOString()));
        $currentTime = $now ? Carbon::instance($now) : now();
        $staleSeconds = max(1, (int) ($staleWindowSeconds ?? config('attendance.live_tracking.stale_seconds', 300)));

        $isFresh = $trackedAt->gte($currentTime->copy()->subSeconds($staleSeconds));
        $isInside = (bool) ($snapshot['is_in_school_area'] ?? false);
        $rawStatus = strtolower(trim((string) ($snapshot['status'] ?? 'online')));
        $isTrackingActive = $isFresh && $rawStatus !== self::STATUS_GPS_DISABLED;

        if (!$isFresh) {
            $trackingStatus = self::STATUS_STALE;
            $trackingStatusReason = 'data_kedaluwarsa';
        } elseif ($rawStatus === self::STATUS_GPS_DISABLED) {
            $trackingStatus = self::STATUS_GPS_DISABLED;
            $trackingStatusReason = 'gps_nonaktif';
        } elseif ($isInside) {
            $trackingStatus = self::STATUS_ACTIVE;
            $trackingStatusReason = 'tracking_terbaru';
        } else {
            $trackingStatus = self::STATUS_OUTSIDE_AREA;
            $trackingStatusReason = 'di_luar_area';
        }

        return array_merge($snapshot, [
            'is_tracking_active' => $isTrackingActive,
            'tracking_status' => $trackingStatus,
            'tracking_status_reason' => $trackingStatusReason,
            'stale_threshold_seconds' => $staleSeconds,
        ]);
    }

    private function cacheKey(int $userId): string
    {
        return self::SNAPSHOT_CACHE_PREFIX . $userId;
    }

    /**
     * @param array<string, mixed>|null $snapshot
     * @return array<string, mixed>|null
     */
    private function normalizeSnapshot(?array $snapshot): ?array
    {
        if (!$snapshot) {
            return null;
        }

        $userId = (int) ($snapshot['user_id'] ?? 0);
        $trackedAt = $snapshot['tracked_at'] ?? null;

        if ($userId <= 0 || !$trackedAt) {
            return null;
        }

        return [
            'user_id' => $userId,
            'user_name' => $snapshot['user_name'] ?? null,
            'latitude' => isset($snapshot['latitude']) ? (float) $snapshot['latitude'] : null,
            'longitude' => isset($snapshot['longitude']) ? (float) $snapshot['longitude'] : null,
            'accuracy' => isset($snapshot['accuracy']) ? (float) $snapshot['accuracy'] : null,
            'speed' => isset($snapshot['speed']) ? (float) $snapshot['speed'] : null,
            'heading' => isset($snapshot['heading']) ? (float) $snapshot['heading'] : null,
            'tracked_at' => Carbon::parse((string) $trackedAt)->toISOString(),
            'status' => (string) ($snapshot['status'] ?? 'online'),
            'is_in_school_area' => (bool) ($snapshot['is_in_school_area'] ?? false),
            'within_gps_area' => (bool) ($snapshot['within_gps_area'] ?? false),
            'location_id' => isset($snapshot['location_id']) ? (int) $snapshot['location_id'] : null,
            'location_name' => $snapshot['location_name'] ?? null,
            'current_location' => is_array($snapshot['current_location'] ?? null) ? $snapshot['current_location'] : null,
            'nearest_location' => is_array($snapshot['nearest_location'] ?? null) ? $snapshot['nearest_location'] : null,
            'distance_to_nearest' => isset($snapshot['distance_to_nearest']) ? (float) $snapshot['distance_to_nearest'] : null,
            'gps_quality_status' => (string) ($snapshot['gps_quality_status'] ?? LiveTrackingContextService::GPS_QUALITY_UNKNOWN),
            'device_source' => (string) ($snapshot['device_source'] ?? LiveTrackingContextService::DEVICE_SOURCE_UNKNOWN),
            'device_info' => is_array($snapshot['device_info'] ?? null) ? $snapshot['device_info'] : [],
            'ip_address' => $snapshot['ip_address'] ?? null,
            'tracking_session_active' => (bool) ($snapshot['tracking_session_active'] ?? false),
            'tracking_session_expires_at' => $snapshot['tracking_session_expires_at'] ?? null,
        ];
    }

    private function resolveExpiry(CarbonInterface $trackedAt): CarbonInterface
    {
        $hoursAfterMidnight = max(1, (int) config('attendance.live_tracking.snapshot_expire_hours_after_midnight', 6));
        return $trackedAt->copy()->endOfDay()->addHours($hoursAfterMidnight);
    }

    private function rememberActiveUserId(int $userId, CarbonInterface $expiresAt): void
    {
        $ids = $this->indexedUserIds();
        if (!in_array($userId, $ids, true)) {
            $ids[] = $userId;
        }

        $this->safeCachePut(self::ACTIVE_USER_IDS_CACHE_KEY, array_values(array_unique($ids)), $expiresAt);
    }

    /**
     * @return array<int, int>
     */
    private function indexedUserIds(): array
    {
        $ids = $this->safeCacheGet(self::ACTIVE_USER_IDS_CACHE_KEY, []);
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn ($value): int => (int) $value, $ids), static fn ($id): bool => $id > 0));
    }

    /**
     * @param array<int, int> $userIds
     */
    private function syncActiveUserIds(array $userIds): void
    {
        if ($userIds === []) {
            $this->safeCacheForget(self::ACTIVE_USER_IDS_CACHE_KEY);
            return;
        }

        $this->safeCachePut(
            self::ACTIVE_USER_IDS_CACHE_KEY,
            array_values(array_unique(array_map(static fn ($value): int => (int) $value, $userIds))),
            now()->endOfDay()->addHours(max(1, (int) config('attendance.live_tracking.snapshot_expire_hours_after_midnight', 6)))
        );
    }

    private function safeCacheGet(string $key, mixed $default = null): mixed
    {
        try {
            return Cache::get($key, $default);
        } catch (\Throwable $exception) {
            Log::warning('Live tracking snapshot cache get failed', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);

            return $default;
        }
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    private function safeCacheMany(array $keys): array
    {
        try {
            $result = Cache::many($keys);
            return is_array($result) ? $result : [];
        } catch (\Throwable $exception) {
            Log::warning('Live tracking snapshot cache many failed', [
                'key_count' => count($keys),
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function safeCachePut(string $key, mixed $value, CarbonInterface $expiresAt): void
    {
        try {
            Cache::put($key, $value, $expiresAt);
        } catch (\Throwable $exception) {
            Log::warning('Live tracking snapshot cache put failed', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function safeCacheForget(string $key): void
    {
        try {
            Cache::forget($key);
        } catch (\Throwable $exception) {
            Log::warning('Live tracking snapshot cache forget failed', [
                'key' => $key,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
