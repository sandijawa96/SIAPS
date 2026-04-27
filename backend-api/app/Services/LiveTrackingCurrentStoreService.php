<?php

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class LiveTrackingCurrentStoreService
{
    private const KEY_PREFIX = 'live_tracking:current:';
    private const USERS_SET_KEY = self::KEY_PREFIX . 'users';
    private const TRACKED_AT_SORTED_SET_KEY = self::KEY_PREFIX . 'tracked_at';
    private const ALL_KEYS_SET_KEY = self::KEY_PREFIX . 'keys';

    /**
     * @param array<string, mixed> $snapshot
     */
    public function upsertSnapshot(User $user, array $snapshot): void
    {
        $record = $this->buildRecord($user, $snapshot);
        if ($record === null) {
            return;
        }

        $this->writeSafely(function ($redis) use ($record): void {
            $userId = (int) $record['user_id'];
            $hashKey = $this->userHashKey($userId);
            $existing = $this->normalizeExistingRecord($redis->hgetall($hashKey));
            $expiryTimestamp = $this->resolveExpiry(
                Carbon::parse((string) $record['tracked_at'])
            )->timestamp;
            $classSetKey = $this->classSetKey((string) $record['_class_index']);
            $levelSetKey = $this->levelSetKey((string) $record['_level_index']);
            $homeroomSetKey = $this->homeroomSetKey((string) $record['_homeroom_index']);
            $managedKeys = [
                $hashKey,
                self::USERS_SET_KEY,
                self::TRACKED_AT_SORTED_SET_KEY,
                self::ALL_KEYS_SET_KEY,
                $classSetKey,
                $levelSetKey,
                $homeroomSetKey,
            ];

            $redis->pipeline(function ($pipe) use (
                $record,
                $existing,
                $hashKey,
                $userId,
                $classSetKey,
                $levelSetKey,
                $homeroomSetKey,
                $expiryTimestamp
            ): void {
                $this->removeChangedMembership($pipe, 'class', $existing, (string) $record['_class_index'], $userId);
                $this->removeChangedMembership($pipe, 'level', $existing, (string) $record['_level_index'], $userId);
                $this->removeChangedMembership($pipe, 'homeroom', $existing, (string) $record['_homeroom_index'], $userId);

                foreach ($record as $field => $value) {
                    $pipe->hset($hashKey, $field, (string) $value);
                }

                $pipe->sadd(self::USERS_SET_KEY, (string) $userId);
                $pipe->sadd($classSetKey, (string) $userId);
                $pipe->sadd($levelSetKey, (string) $userId);
                $pipe->sadd($homeroomSetKey, (string) $userId);
                $pipe->sadd(self::ALL_KEYS_SET_KEY, ...$managedKeys);
                $pipe->zadd(
                    self::TRACKED_AT_SORTED_SET_KEY,
                    (float) $record['tracked_at_epoch'],
                    (string) $userId
                );

                foreach ($managedKeys as $key) {
                    $pipe->expireat($key, $expiryTimestamp);
                }
            });
        }, [
            'operation' => 'upsert_snapshot',
            'user_id' => (int) $record['user_id'],
        ]);
    }

    public function upsertBaselineUser(User $user, array $overrides = []): void
    {
        $this->upsertSnapshot($user, array_merge([
            'user_id' => (int) $user->id,
            'tracked_at' => now()->toISOString(),
            'status' => 'no_data',
            'is_in_school_area' => false,
            'within_gps_area' => false,
            'gps_quality_status' => 'unknown',
            'device_source' => 'unknown',
            'location_name' => 'No tracking data',
            'tracking_session_active' => false,
            'tracking_session_expires_at' => null,
        ], $overrides));
    }

    public function setTrackingSessionState(int $userId, bool $isActive, ?string $expiresAt = null): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->writeSafely(function ($redis) use ($userId, $isActive, $expiresAt): void {
            $hashKey = $this->userHashKey($userId);
            $existing = $this->normalizeExistingRecord($redis->hgetall($hashKey));
            if ($existing === []) {
                return;
            }

            $trackedAt = isset($existing['tracked_at'])
                ? Carbon::parse((string) $existing['tracked_at'])
                : now();
            $expiryTimestamp = $this->resolveExpiry($trackedAt)->timestamp;

            $redis->pipeline(function ($pipe) use ($hashKey, $isActive, $expiresAt, $expiryTimestamp): void {
                $pipe->hset($hashKey, 'tracking_session_active', $isActive ? '1' : '0');
                $pipe->hset($hashKey, 'tracking_session_expires_at', $expiresAt ?? '');
                $pipe->hset($hashKey, 'updated_at', now()->toISOString());
                $pipe->expireat($hashKey, $expiryTimestamp);
            });
        }, [
            'operation' => 'set_tracking_session_state',
            'user_id' => $userId,
            'is_active' => $isActive,
        ]);
    }

    public function clearAll(): int
    {
        $deleted = 0;

        $this->writeSafely(function ($redis) use (&$deleted): void {
            $keys = $redis->smembers(self::ALL_KEYS_SET_KEY);
            if (!is_array($keys) || $keys === []) {
                $keys = $redis->keys(self::KEY_PREFIX . '*');
            }

            if (!is_array($keys) || $keys === []) {
                return;
            }

            $keys = array_values(array_unique(array_map(static fn ($key): string => (string) $key, $keys)));
            $deleted = count($keys);
            $redis->del($keys);
        }, [
            'operation' => 'clear_all',
        ]);

        return $deleted;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readRecords(
        string $classFilter = '',
        string $levelFilter = '',
        int $homeroomTeacherId = 0
    ): array {
        return $this->readSafely(function ($redis) use ($classFilter, $levelFilter, $homeroomTeacherId): array {
            $candidateUserIds = $this->resolveCandidateUserIds(
                $redis,
                $classFilter,
                $levelFilter,
                $homeroomTeacherId
            );

            if ($candidateUserIds === []) {
                return [];
            }

            return $this->fetchRecords($redis, $candidateUserIds);
        }, []);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<string, string>|null
     */
    private function buildRecord(User $user, array $snapshot): ?array
    {
        $userId = (int) ($snapshot['user_id'] ?? $user->id ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $user->loadMissing(['kelas.tingkat', 'kelas.waliKelas:id,nama_lengkap']);

        $kelasName = $this->buildKelasName($user);
        $tingkatName = $this->buildTingkatName($user);
        $waliKelas = $this->buildWaliKelasInfo($user);
        $trackedAt = $this->parseTrackedAt($snapshot['tracked_at'] ?? null);

        return [
            'user_id' => (string) $userId,
            'nama_lengkap' => (string) ($user->nama_lengkap ?: ($user->username ?: $user->email)),
            'email' => (string) ($user->email ?? ''),
            'nis' => (string) ($user->nis ?? ''),
            'username' => (string) ($user->username ?? ''),
            'kelas' => $kelasName,
            'tingkat' => $tingkatName,
            'wali_kelas_id' => $waliKelas['id'] !== null ? (string) $waliKelas['id'] : '',
            'wali_kelas' => $waliKelas['name'],
            'latitude' => $this->stringifyNullableFloat($snapshot['latitude'] ?? null),
            'longitude' => $this->stringifyNullableFloat($snapshot['longitude'] ?? null),
            'accuracy' => $this->stringifyNullableFloat($snapshot['accuracy'] ?? null),
            'speed' => $this->stringifyNullableFloat($snapshot['speed'] ?? null),
            'heading' => $this->stringifyNullableFloat($snapshot['heading'] ?? null),
            'tracked_at' => $trackedAt->toISOString(),
            'tracked_at_epoch' => (string) $trackedAt->timestamp,
            'snapshot_status' => (string) ($snapshot['status'] ?? 'online'),
            'device_source' => (string) ($snapshot['device_source'] ?? 'unknown'),
            'gps_quality_status' => (string) ($snapshot['gps_quality_status'] ?? 'unknown'),
            'is_in_school_area' => !empty($snapshot['is_in_school_area']) ? '1' : '0',
            'within_gps_area' => !empty($snapshot['within_gps_area']) ? '1' : '0',
            'has_tracking_data' => isset($snapshot['latitude'], $snapshot['longitude']) ? '1' : '0',
            'location_id' => isset($snapshot['location_id']) && $snapshot['location_id'] !== null
                ? (string) ((int) $snapshot['location_id'])
                : '',
            'location_name' => (string) ($snapshot['location_name'] ?? ''),
            'current_location' => $this->stringifyJson($snapshot['current_location'] ?? null),
            'nearest_location' => $this->stringifyJson($snapshot['nearest_location'] ?? null),
            'distance_to_nearest' => $this->stringifyNullableFloat($snapshot['distance_to_nearest'] ?? null),
            'device_info' => $this->stringifyJson($snapshot['device_info'] ?? []),
            'ip_address' => (string) ($snapshot['ip_address'] ?? ''),
            'tracking_session_active' => !empty($snapshot['tracking_session_active']) ? '1' : '0',
            'tracking_session_expires_at' => isset($snapshot['tracking_session_expires_at']) && $snapshot['tracking_session_expires_at']
                ? Carbon::parse((string) $snapshot['tracking_session_expires_at'])->toISOString()
                : '',
            'updated_at' => now()->toISOString(),
            '_class_index' => $this->dimensionToken($kelasName),
            '_level_index' => $this->dimensionToken($tingkatName),
            '_homeroom_index' => $waliKelas['id'] !== null
                ? (string) ((int) $waliKelas['id'])
                : 'unassigned',
        ];
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

        $kelas = $student->kelas instanceof EloquentCollection
            ? $student->kelas->first()
            : $student->kelas;

        return [
            'id' => $kelas?->waliKelas?->id ? (int) $kelas->waliKelas->id : null,
            'name' => $kelas?->waliKelas?->nama_lengkap ?: 'Belum ditentukan',
        ];
    }

    private function buildKelasName(User $student): string
    {
        if (!$student->relationLoaded('kelas') || !$student->kelas) {
            return 'N/A';
        }

        if ($student->kelas instanceof EloquentCollection) {
            return $student->kelas->first()?->nama_kelas ?? 'N/A';
        }

        return $student->kelas->nama_kelas ?? 'N/A';
    }

    private function buildTingkatName(User $student): string
    {
        if (!$student->relationLoaded('kelas') || !$student->kelas) {
            return 'N/A';
        }

        if ($student->kelas instanceof EloquentCollection) {
            return $student->kelas->first()?->tingkat?->nama ?? 'N/A';
        }

        return $student->kelas->tingkat?->nama ?? 'N/A';
    }

    /**
     * @param callable(object): void $callback
     * @param array<string, mixed> $context
     */
    private function writeSafely(callable $callback, array $context = []): void
    {
        try {
            $redis = Redis::connection('cache');
            $callback($redis);
        } catch (\Throwable $e) {
            Log::warning('Failed to sync live tracking current-state store', array_merge($context, [
                'error' => $e->getMessage(),
            ]));
        }
    }

    /**
     * @template T
     * @param callable(object): T $callback
     * @param T $fallback
     * @return T
     */
    private function readSafely(callable $callback, mixed $fallback): mixed
    {
        try {
            $redis = Redis::connection('cache');
            return $callback($redis);
        } catch (\Throwable $e) {
            Log::warning('Failed to read live tracking current-state store', [
                'error' => $e->getMessage(),
            ]);

            return $fallback;
        }
    }

    /**
     * @param array<mixed> $existing
     * @return array<string, string>
     */
    private function normalizeExistingRecord(mixed $existing): array
    {
        if (!is_array($existing)) {
            return [];
        }

        return array_reduce(array_keys($existing), function (array $carry, $key) use ($existing): array {
            $carry[(string) $key] = (string) ($existing[$key] ?? '');
            return $carry;
        }, []);
    }

    private function removeChangedMembership(
        object $pipe,
        string $dimension,
        array $existing,
        string $newIndex,
        int $userId
    ): void {
        $field = match ($dimension) {
            'class' => '_class_index',
            'level' => '_level_index',
            default => '_homeroom_index',
        };

        $oldIndex = trim((string) ($existing[$field] ?? ''));
        if ($oldIndex === '' || $oldIndex === $newIndex) {
            return;
        }

        $oldKey = match ($dimension) {
            'class' => $this->classSetKey($oldIndex),
            'level' => $this->levelSetKey($oldIndex),
            default => $this->homeroomSetKey($oldIndex),
        };

        $pipe->srem($oldKey, (string) $userId);
    }

    /**
     * @return array<int, int>
     */
    private function resolveCandidateUserIds(
        object $redis,
        string $classFilter,
        string $levelFilter,
        int $homeroomTeacherId
    ): array {
        $candidateIds = $this->normalizeUserIds($redis->smembers(self::USERS_SET_KEY));
        if ($candidateIds === []) {
            return [];
        }

        if (trim($classFilter) !== '') {
            $candidateIds = array_values(array_intersect(
                $candidateIds,
                $this->normalizeUserIds($redis->smembers(
                    $this->classSetKey($this->dimensionToken($classFilter))
                ))
            ));
        }

        if ($candidateIds === []) {
            return [];
        }

        if (trim($levelFilter) !== '') {
            $candidateIds = array_values(array_intersect(
                $candidateIds,
                $this->normalizeUserIds($redis->smembers(
                    $this->levelSetKey($this->dimensionToken($levelFilter))
                ))
            ));
        }

        if ($candidateIds === []) {
            return [];
        }

        if ($homeroomTeacherId > 0) {
            $candidateIds = array_values(array_intersect(
                $candidateIds,
                $this->normalizeUserIds($redis->smembers(
                    $this->homeroomSetKey((string) $homeroomTeacherId)
                ))
            ));
        }

        sort($candidateIds);

        return array_values(array_unique($candidateIds));
    }

    /**
     * @param array<int, int> $userIds
     * @return array<int, array<string, mixed>>
     */
    private function fetchRecords(object $redis, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rawRecords = $redis->pipeline(function ($pipe) use ($userIds): void {
            foreach ($userIds as $userId) {
                $pipe->hgetall($this->userHashKey($userId));
            }
        });

        if (!is_array($rawRecords)) {
            return [];
        }

        $records = [];
        foreach ($rawRecords as $rawRecord) {
            $normalized = $this->normalizeStoredRecord($rawRecord);
            if ($normalized === null) {
                continue;
            }

            $records[] = $normalized;
        }

        return $records;
    }

    /**
     * @param array<mixed> $rawIds
     * @return array<int, int>
     */
    private function normalizeUserIds(mixed $rawIds): array
    {
        if (!is_array($rawIds)) {
            return [];
        }

        $normalized = array_values(array_filter(
            array_map(static fn ($value): int => (int) $value, $rawIds),
            static fn (int $userId): bool => $userId > 0
        ));

        sort($normalized);

        return array_values(array_unique($normalized));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeStoredRecord(mixed $record): ?array
    {
        if (!is_array($record) || $record === []) {
            return null;
        }

        $normalized = $this->normalizeExistingRecord($record);
        $userId = (int) ($normalized['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }

        $trackedAt = trim((string) ($normalized['tracked_at'] ?? ''));
        $trackedAtIso = null;
        $trackedAtEpoch = 0;

        if ($trackedAt !== '') {
            try {
                $trackedAtCarbon = Carbon::parse($trackedAt);
                $trackedAtIso = $trackedAtCarbon->toISOString();
                $trackedAtEpoch = $trackedAtCarbon->timestamp;
            } catch (\Throwable) {
                $trackedAtIso = null;
                $trackedAtEpoch = 0;
            }
        }

        return [
            'user_id' => $userId,
            'nama_lengkap' => (string) ($normalized['nama_lengkap'] ?? ''),
            'email' => (string) ($normalized['email'] ?? ''),
            'nis' => (string) ($normalized['nis'] ?? ''),
            'username' => (string) ($normalized['username'] ?? ''),
            'kelas' => (string) ($normalized['kelas'] ?? 'N/A'),
            'tingkat' => (string) ($normalized['tingkat'] ?? 'N/A'),
            'wali_kelas_id' => $this->nullableInt($normalized['wali_kelas_id'] ?? ''),
            'wali_kelas' => (string) ($normalized['wali_kelas'] ?? 'Belum ditentukan'),
            'latitude' => $this->nullableFloat($normalized['latitude'] ?? ''),
            'longitude' => $this->nullableFloat($normalized['longitude'] ?? ''),
            'accuracy' => $this->nullableFloat($normalized['accuracy'] ?? ''),
            'speed' => $this->nullableFloat($normalized['speed'] ?? ''),
            'heading' => $this->nullableFloat($normalized['heading'] ?? ''),
            'tracked_at' => $trackedAtIso,
            'tracked_at_epoch' => $trackedAtEpoch,
            'snapshot_status' => (string) ($normalized['snapshot_status'] ?? 'online'),
            'device_source' => (string) ($normalized['device_source'] ?? 'unknown'),
            'gps_quality_status' => (string) ($normalized['gps_quality_status'] ?? 'unknown'),
            'is_in_school_area' => $this->stringToBool($normalized['is_in_school_area'] ?? ''),
            'within_gps_area' => $this->stringToBool($normalized['within_gps_area'] ?? ''),
            'has_tracking_data' => $this->stringToBool($normalized['has_tracking_data'] ?? ''),
            'location_id' => $this->nullableInt($normalized['location_id'] ?? ''),
            'location_name' => (string) ($normalized['location_name'] ?? ''),
            'current_location' => $this->decodeJsonArray($normalized['current_location'] ?? ''),
            'nearest_location' => $this->decodeJsonArray($normalized['nearest_location'] ?? ''),
            'distance_to_nearest' => $this->nullableFloat($normalized['distance_to_nearest'] ?? ''),
            'device_info' => $this->decodeJsonArray($normalized['device_info'] ?? ''),
            'ip_address' => (string) ($normalized['ip_address'] ?? ''),
            'tracking_session_active' => $this->stringToBool($normalized['tracking_session_active'] ?? ''),
            'tracking_session_expires_at' => $this->normalizeIsoString($normalized['tracking_session_expires_at'] ?? ''),
        ];
    }

    private function userHashKey(int $userId): string
    {
        return self::KEY_PREFIX . 'user:' . $userId;
    }

    private function classSetKey(string $token): string
    {
        return self::KEY_PREFIX . 'class:' . $token;
    }

    private function levelSetKey(string $token): string
    {
        return self::KEY_PREFIX . 'level:' . $token;
    }

    private function homeroomSetKey(string $token): string
    {
        return self::KEY_PREFIX . 'homeroom:' . $token;
    }

    private function dimensionToken(?string $value): string
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            return 'unassigned';
        }

        return rawurlencode($normalized);
    }

    private function stringifyNullableFloat(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return (string) ((float) $value);
    }

    private function stringifyJson(mixed $value): string
    {
        if (!is_array($value) || $value === []) {
            return '';
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) ? $encoded : '';
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

    private function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private function stringToBool(mixed $value): bool
    {
        return in_array((string) $value, ['1', 'true', 'yes', 'on'], true);
    }

    private function normalizeIsoString(mixed $value): ?string
    {
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        try {
            return Carbon::parse($stringValue)->toISOString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonArray(mixed $value): ?array
    {
        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return null;
        }

        $decoded = json_decode($stringValue, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function resolveExpiry(Carbon $trackedAt): Carbon
    {
        $hoursAfterMidnight = max(1, (int) config('attendance.live_tracking.snapshot_expire_hours_after_midnight', 6));
        return $trackedAt->copy()->endOfDay()->addHours($hoursAfterMidnight);
    }
}
