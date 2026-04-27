<?php

namespace App\Services;

use App\Models\LiveTracking;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class LiveTrackingIngestService
{
    private const LAST_PERSISTED_SNAPSHOT_CACHE_PREFIX = 'live_tracking:last_persisted_snapshot:';
    private const DEFAULT_MIN_DISTANCE_METERS = 20;
    private const DEFAULT_IDLE_PERSIST_SECONDS = 300;

    public function __construct(
        private readonly AttendanceRuntimeConfigService $attendanceRuntimeConfigService
    ) {
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    public function persistSnapshot(array $snapshot): void
    {
        $userId = (int) ($snapshot['user_id'] ?? 0);
        if ($userId <= 0 || !isset($snapshot['latitude'], $snapshot['longitude'])) {
            return;
        }

        $normalizedSnapshot = $this->normalizeSnapshotForHistory($snapshot);
        if ($normalizedSnapshot === null) {
            return;
        }

        $runtimeConfig = $this->attendanceRuntimeConfigService->getLiveTrackingConfig();
        $minDistanceMeters = max(1, (int) ($runtimeConfig['min_distance_meters'] ?? self::DEFAULT_MIN_DISTANCE_METERS));
        $persistIdleSeconds = max(60, (int) ($runtimeConfig['persist_idle_seconds'] ?? self::DEFAULT_IDLE_PERSIST_SECONDS));
        $lastPersistedSnapshot = $this->resolveLastPersistedSnapshot($userId);

        if (!$this->shouldPersistSnapshot(
            $normalizedSnapshot,
            $lastPersistedSnapshot,
            $minDistanceMeters,
            $persistIdleSeconds
        )) {
            return;
        }

        try {
            LiveTracking::create([
                'user_id' => $userId,
                'latitude' => $normalizedSnapshot['latitude'],
                'longitude' => $normalizedSnapshot['longitude'],
                'accuracy' => $normalizedSnapshot['accuracy'],
                'speed' => $normalizedSnapshot['speed'],
                'heading' => $normalizedSnapshot['heading'],
                'location_id' => $normalizedSnapshot['location_id'],
                'location_name' => $normalizedSnapshot['location_name'],
                'device_source' => $normalizedSnapshot['device_source'],
                'gps_quality_status' => $normalizedSnapshot['gps_quality_status'],
                'is_in_school_area' => $normalizedSnapshot['is_in_school_area'],
                'device_info' => $normalizedSnapshot['device_info'],
                'ip_address' => $normalizedSnapshot['ip_address'],
                'tracked_at' => $normalizedSnapshot['tracked_at'],
            ]);

            $this->rememberLastPersistedSnapshot($userId, $normalizedSnapshot);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist live tracking history from snapshot', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, mixed>|null
     */
    private function normalizeSnapshotForHistory(array $snapshot): ?array
    {
        $latitude = isset($snapshot['latitude']) ? (float) $snapshot['latitude'] : null;
        $longitude = isset($snapshot['longitude']) ? (float) $snapshot['longitude'] : null;
        if ($latitude === null || $longitude === null) {
            return null;
        }

        $trackedAt = $this->parseTrackedAt($snapshot['tracked_at'] ?? null);

        return [
            'user_id' => (int) ($snapshot['user_id'] ?? 0),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => isset($snapshot['accuracy']) ? (float) $snapshot['accuracy'] : null,
            'speed' => isset($snapshot['speed']) ? (float) $snapshot['speed'] : null,
            'heading' => isset($snapshot['heading']) ? (float) $snapshot['heading'] : null,
            'location_id' => isset($snapshot['location_id']) ? (int) $snapshot['location_id'] : null,
            'location_name' => $snapshot['location_name'] ?? null,
            'device_source' => $snapshot['device_source'] ?? null,
            'gps_quality_status' => $snapshot['gps_quality_status'] ?? null,
            'is_in_school_area' => (bool) ($snapshot['is_in_school_area'] ?? false),
            'device_info' => is_array($snapshot['device_info'] ?? null) ? $snapshot['device_info'] : [],
            'ip_address' => $snapshot['ip_address'] ?? null,
            'tracked_at' => $trackedAt,
        ];
    }

    /**
     * @param array<string, mixed>|null $current
     * @param array<string, mixed>|null $lastPersisted
     */
    private function shouldPersistSnapshot(
        ?array $current,
        ?array $lastPersisted,
        int $minDistanceMeters,
        int $persistIdleSeconds
    ): bool {
        if ($current === null) {
            return false;
        }

        if ($lastPersisted === null) {
            return true;
        }

        if ($this->hasSignificantStateChange($current, $lastPersisted)) {
            return true;
        }

        $distance = $this->calculateDistanceMeters(
            (float) $lastPersisted['latitude'],
            (float) $lastPersisted['longitude'],
            (float) $current['latitude'],
            (float) $current['longitude']
        );

        if ($distance >= $minDistanceMeters) {
            return true;
        }

        $lastTrackedAt = $this->parseTrackedAt($lastPersisted['tracked_at'] ?? null);
        $currentTrackedAt = $this->parseTrackedAt($current['tracked_at'] ?? null);

        return $currentTrackedAt->greaterThanOrEqualTo(
            $lastTrackedAt->copy()->addSeconds($persistIdleSeconds)
        );
    }

    /**
     * @param array<string, mixed> $current
     * @param array<string, mixed> $lastPersisted
     */
    private function hasSignificantStateChange(array $current, array $lastPersisted): bool
    {
        foreach (['is_in_school_area', 'location_id', 'gps_quality_status', 'device_source'] as $field) {
            if (($current[$field] ?? null) !== ($lastPersisted[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    private function resolveLastPersistedSnapshot(int $userId): ?array
    {
        $cacheKey = $this->getLastPersistedSnapshotCacheKey($userId);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $latest = LiveTracking::query()
            ->where('user_id', $userId)
            ->latest('tracked_at')
            ->first();

        if (!$latest instanceof LiveTracking) {
            return null;
        }

        $normalized = [
            'user_id' => $userId,
            'latitude' => (float) $latest->latitude,
            'longitude' => (float) $latest->longitude,
            'accuracy' => $latest->accuracy !== null ? (float) $latest->accuracy : null,
            'speed' => $latest->speed !== null ? (float) $latest->speed : null,
            'heading' => $latest->heading !== null ? (float) $latest->heading : null,
            'location_id' => $latest->location_id !== null ? (int) $latest->location_id : null,
            'location_name' => $latest->location_name,
            'device_source' => $latest->device_source,
            'gps_quality_status' => $latest->gps_quality_status,
            'is_in_school_area' => (bool) $latest->is_in_school_area,
            'device_info' => is_array($latest->device_info) ? $latest->device_info : [],
            'ip_address' => $latest->ip_address,
            'tracked_at' => optional($latest->tracked_at)->toISOString() ?? now()->toISOString(),
        ];

        $this->rememberLastPersistedSnapshot($userId, $normalized);

        return $normalized;
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function rememberLastPersistedSnapshot(int $userId, array $snapshot): void
    {
        Cache::put($this->getLastPersistedSnapshotCacheKey($userId), $snapshot, now()->addDay());
    }

    private function getLastPersistedSnapshotCacheKey(int $userId): string
    {
        return self::LAST_PERSISTED_SNAPSHOT_CACHE_PREFIX . $userId;
    }

    private function parseTrackedAt(mixed $value): Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if (is_string($value) || is_numeric($value)) {
            try {
                return Carbon::parse((string) $value);
            } catch (\Throwable) {
                return now();
            }
        }

        return now();
    }

    private function calculateDistanceMeters(float $fromLat, float $fromLng, float $toLat, float $toLng): float
    {
        $earthRadius = 6371000.0;
        $latFrom = deg2rad($fromLat);
        $lngFrom = deg2rad($fromLng);
        $latTo = deg2rad($toLat);
        $lngTo = deg2rad($toLng);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(
            pow(sin($latDelta / 2), 2)
            + cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)
        ));

        return $angle * $earthRadius;
    }
}
