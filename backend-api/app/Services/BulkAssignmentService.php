<?php

namespace App\Services;

use App\Models\User;
use App\Models\AttendanceSchema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;

class BulkAssignmentService
{
    private const CACHE_TTL = 3600; // 1 hour
    private const USERS_PAGINATED_CACHE_INDEX = 'bulk_assignment:users_paginated_keys';
    private const USERS_WITH_SCHEMAS_CACHE_INDEX = 'bulk_assignment:users_with_schemas_keys';

    public function __construct(
        private AttendanceSchemaService $attendanceSchemaService
    ) {
    }

    /**
     * Perform bulk assignment with batch processing
     */
    public function bulkAssign(array $userIds, int $schemaId, array $options = []): array
    {
        $startTime = microtime(true);
        $results = [
            'total_users' => count($userIds),
            'processed' => 0,
            'skipped' => 0,
            'errors' => [],
            'processing_time' => 0
        ];

        try {
            // Validate schema exists
            $schema = AttendanceSchema::findOrFail($schemaId);

            // Filter out ASN users and validate user existence
            $validUserIds = $this->validateUsers($userIds);
            $results['skipped'] = count($userIds) - count($validUserIds);

            if (empty($validUserIds)) {
                throw new \Exception('No valid users found for assignment');
            }

            $assignmentResults = $this->attendanceSchemaService->bulkAssignSchema(
                $validUserIds,
                $schema,
                $options['start_date'] ?? null,
                $options['end_date'] ?? null,
                $options['notes'] ?? null,
                Auth::check() ? Auth::id() : 1,
                $options['assignment_type'] ?? 'bulk'
            );

            foreach ($assignmentResults as $userId => $result) {
                if (!empty($result['success'])) {
                    $results['processed']++;
                    continue;
                }

                $results['errors'][] = "User {$userId}: " . ($result['message'] ?? 'Unknown error');
            }

            // Clear relevant caches
            $this->clearRelatedCaches($validUserIds);

            $results['processing_time'] = round(microtime(true) - $startTime, 2);

            Log::info('Bulk assignment completed', $results);

            return $results;
        } catch (\Exception $e) {
            Log::error('Bulk assignment failed: ' . $e->getMessage());
            $results['errors'][] = $e->getMessage();
            $results['processing_time'] = round(microtime(true) - $startTime, 2);

            return $results;
        }
    }

    /**
     * Validate users and filter out ASN users
     */
    private function validateUsers(array $userIds): array
    {
        return User::whereIn('id', $userIds)
            ->where(function ($query) {
                $query->where('status_kepegawaian', '!=', 'ASN')
                    ->orWhereNull('status_kepegawaian');
            })
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get paginated users for assignment UI
     */
    public function getPaginatedUsers(int $page = 1, int $perPage = 50, string $search = ''): array
    {
        $cacheKey = "users_paginated_{$page}_{$perPage}_" . md5($search);
        $this->registerCacheKey(self::USERS_PAGINATED_CACHE_INDEX, $cacheKey);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($page, $perPage, $search) {
            $query = User::with('roles:id,name,display_name')
                ->where(function ($q) {
                $q->where('status_kepegawaian', '!=', 'ASN')
                    ->orWhereNull('status_kepegawaian');
            });

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            }

            $users = $query->select(['id', 'nama_lengkap', 'email', 'username', 'status_kepegawaian', 'nis', 'nisn'])
                ->orderBy('nama_lengkap')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'data' => $users->items(),
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'has_more' => $users->hasMorePages()
            ];
        });
    }

    /**
     * Get users with their current schema assignments
     */
    public function getUsersWithSchemas(int $page = 1, int $perPage = 50, string $search = ''): array
    {
        $cacheKey = "users_with_schemas_{$page}_{$perPage}_" . md5($search);
        $this->registerCacheKey(self::USERS_WITH_SCHEMAS_CACHE_INDEX, $cacheKey);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($page, $perPage, $search) {
            $query = User::with('roles:id,name,display_name')
                ->where(function ($q) {
                $q->where('status_kepegawaian', '!=', 'ASN')
                    ->orWhereNull('status_kepegawaian');
            });

            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            }

            $users = $query->select(['id', 'nama_lengkap', 'email', 'username', 'status_kepegawaian', 'nis', 'nisn'])
                ->orderBy('nama_lengkap')
                ->paginate($perPage, ['*'], 'page', $page);

            // Transform data to include schema info
            $transformedData = $users->getCollection()->map(function ($user) {
                $context = $this->attendanceSchemaService->getEffectiveSchemaContext($user);
                $schema = $context['schema'] ?? null;

                return [
                    'id' => $user->id,
                    'name' => $user->nama_lengkap,
                    'nama_lengkap' => $user->nama_lengkap,
                    'email' => $user->email,
                    'username' => $user->username,
                    'nis' => $user->nis,
                    'nisn' => $user->nisn,
                    'status_kepegawaian' => $user->status_kepegawaian,
                    'roles' => $user->roles->map(fn ($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                    ])->values()->all(),
                    'schema' => $schema ? [
                        'id' => $schema->id,
                        'name' => $schema->schema_name,
                        'schema_name' => $schema->schema_name,
                        'schema_type' => $schema->schema_type,
                        'type' => $context['assignment_type'] ?? 'none',
                        'start_date' => $context['start_date'] ?? null,
                        'end_date' => $context['end_date'] ?? null,
                        'assignment_reason' => $context['assignment_reason'] ?? null,
                    ] : null
                ];
            });

            return [
                'data' => $transformedData,
                'total' => $users->total(),
                'per_page' => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'has_more' => $users->hasMorePages()
            ];
        });
    }

    /**
     * Clear related caches after bulk operations
     */
    private function clearRelatedCaches(array $userIds): void
    {
        $schemaKeysToRemove = [];

        // Clear user-specific caches
        foreach ($userIds as $userId) {
            Cache::forget("working_hours_user_{$userId}");

            $userSchemaKeysIndex = "attendance_schema:effective_user_keys:{$userId}";
            $userSchemaKeys = Cache::get($userSchemaKeysIndex, []);
            if (is_array($userSchemaKeys)) {
                foreach ($userSchemaKeys as $cacheKey) {
                    Cache::forget($cacheKey);
                }
                $schemaKeysToRemove = array_merge($schemaKeysToRemove, $userSchemaKeys);
            }
            Cache::forget($userSchemaKeysIndex);
        }

        // Keep global attendance schema index in sync
        if (!empty($schemaKeysToRemove)) {
            $globalSchemaKeys = Cache::get('attendance_schema:effective_keys', []);
            if (is_array($globalSchemaKeys)) {
                $filtered = array_values(array_filter($globalSchemaKeys, function ($key) use ($schemaKeysToRemove) {
                    return !in_array($key, $schemaKeysToRemove, true);
                }));
                if (empty($filtered)) {
                    Cache::forget('attendance_schema:effective_keys');
                } else {
                    Cache::put('attendance_schema:effective_keys', $filtered, now()->addDay());
                }
            }
        }

        $effectiveUserIds = Cache::get('attendance_schema:effective_user_ids', []);
        if (is_array($effectiveUserIds)) {
            $targetUserIds = array_map('strval', $userIds);
            $filteredUserIds = array_values(array_filter($effectiveUserIds, function ($cachedUserId) use ($targetUserIds) {
                return !in_array((string) $cachedUserId, $targetUserIds, true);
            }));

            if (empty($filteredUserIds)) {
                Cache::forget('attendance_schema:effective_user_ids');
            } else {
                Cache::put('attendance_schema:effective_user_ids', $filteredUserIds, now()->addDay());
            }
        }

        // Clear paginated caches managed by this service only
        $this->forgetIndexedCaches(self::USERS_PAGINATED_CACHE_INDEX);
        $this->forgetIndexedCaches(self::USERS_WITH_SCHEMAS_CACHE_INDEX);

        Log::info('Cleared caches for bulk assignment', ['user_count' => count($userIds)]);
    }

    /**
     * Get assignment progress (for future queue implementation)
     */
    public function getAssignmentProgress(string $jobId): array
    {
        $cacheKey = "assignment_progress_{$jobId}";

        return Cache::get($cacheKey, [
            'status' => 'not_found',
            'progress' => 0,
            'total' => 0,
            'processed' => 0,
            'errors' => []
        ]);
    }

    /**
     * Update assignment progress (for future queue implementation)
     */
    public function updateAssignmentProgress(string $jobId, array $progress): void
    {
        $cacheKey = "assignment_progress_{$jobId}";
        Cache::put($cacheKey, $progress, 3600); // 1 hour TTL
    }

    private function registerCacheKey(string $indexKey, string $cacheKey): void
    {
        $keys = Cache::get($indexKey, []);
        if (!is_array($keys)) {
            $keys = [];
        }

        if (!in_array($cacheKey, $keys, true)) {
            $keys[] = $cacheKey;
            Cache::put($indexKey, $keys, now()->addDay());
        }
    }

    private function forgetIndexedCaches(string $indexKey): void
    {
        $keys = Cache::get($indexKey, []);
        if (is_array($keys)) {
            foreach ($keys as $cacheKey) {
                Cache::forget($cacheKey);
            }
        }

        Cache::forget($indexKey);
    }
}
