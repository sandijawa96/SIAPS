<?php

namespace App\Services;

use App\Models\User;
use App\Models\AttendanceSchema;
use App\Models\AttendanceSchemaAssignment;
use App\Models\AttendanceSchemaChangeLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AttendanceSchemaService
{
    private const CACHE_INDEX_ALL_KEYS = 'attendance_schema:effective_keys';
    private const CACHE_INDEX_USER_IDS = 'attendance_schema:effective_user_ids';
    private const CACHE_INDEX_USER_KEYS_PREFIX = 'attendance_schema:effective_user_keys:';
    private const RUNTIME_CACHE_VERSION_KEY = 'attendance_runtime_version';

    /**
     * Get effective schema for user with priority logic
     */
    public function getEffectiveSchema(User $user, $date = null): ?AttendanceSchema
    {
        $selection = $this->resolveEffectiveSchemaSelection($user, $date);
        $schema = $selection['schema'] ?? null;

        return $schema instanceof AttendanceSchema ? $schema : null;
    }

    /**
     * Get effective schema together with source/assignment metadata.
     *
     * @return array{
     *     schema:?AttendanceSchema,
     *     assignment_type:string,
     *     assignment_id:?int,
     *     start_date:?string,
     *     end_date:?string,
     *     assignment_reason:?string
     * }
     */
    public function getEffectiveSchemaContext(User $user, $date = null): array
    {
        $selection = $this->resolveEffectiveSchemaSelection($user, $date);

        return [
            'schema' => $selection['schema'] ?? null,
            'assignment_type' => (string) ($selection['assignment_type'] ?? 'none'),
            'assignment_id' => isset($selection['assignment_id']) ? (int) $selection['assignment_id'] : null,
            'start_date' => $selection['start_date'] ?? null,
            'end_date' => $selection['end_date'] ?? null,
            'assignment_reason' => $selection['assignment_reason'] ?? null,
        ];
    }

    /**
     * Create new attendance schema
     */
    public function createSchema(array $data, $createdBy = null): AttendanceSchema
    {
        DB::beginTransaction();

        try {
            // Set default values
            $data['version'] = 1;
            $data['updated_by'] = $createdBy ?: auth()->id();

            // Create schema
            $schema = AttendanceSchema::create($data);

            // Log creation
            AttendanceSchemaChangeLog::logChange(
                $schema->id,
                'created',
                null,
                $schema->toArray(),
                $createdBy,
                'Schema created'
            );

            DB::commit();

            // Clear cache
            $this->clearSchemaCache();

            return $schema;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create attendance schema: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update attendance schema
     */
    public function updateSchema(AttendanceSchema $schema, array $data, $updatedBy = null, $reason = null): AttendanceSchema
    {
        DB::beginTransaction();

        try {
            $oldValues = $schema->toArray();

            // Increment version
            $data['version'] = $schema->version + 1;
            $data['updated_by'] = $updatedBy ?: auth()->id();

            // Update schema
            $schema->update($data);
            $schema->refresh();

            // Log changes
            AttendanceSchemaChangeLog::logChange(
                $schema->id,
                'updated',
                $oldValues,
                $schema->toArray(),
                $updatedBy,
                $reason ?: 'Schema updated'
            );

            DB::commit();

            // Clear cache
            $this->clearSchemaCache();

            return $schema;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update attendance schema: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Assign schema to user manually
     */
    public function assignSchemaToUser(
        User $user,
        AttendanceSchema $schema,
        $startDate = null,
        $endDate = null,
        $notes = null,
        $assignedBy = null,
        ?string $assignmentType = 'manual'
    ): AttendanceSchemaAssignment
    {
        $startAt = Carbon::parse($startDate ?: now()->toDateString())->startOfDay();
        $endAt = $endDate ? Carbon::parse($endDate)->startOfDay() : null;
        $assignedBy = $assignedBy ?: auth()->id();
        $normalizedType = $this->normalizeAssignmentType($assignmentType);

        if ($endAt !== null && $endAt->lt($startAt)) {
            throw new \InvalidArgumentException('End date must be after or equal to start date');
        }

        DB::beginTransaction();

        try {
            $this->replaceOverlappingAssignmentsForUser(
                $user->id,
                $startAt,
                $endAt,
                sprintf(
                    'Digantikan oleh schema "%s" (%s).',
                    $schema->schema_name,
                    $normalizedType
                ),
                $assignedBy
            );

            $assignment = AttendanceSchemaAssignment::create([
                'user_id' => $user->id,
                'attendance_setting_id' => $schema->id,
                'start_date' => $startAt->toDateString(),
                'end_date' => $endAt?->toDateString(),
                'is_active' => true,
                'notes' => $this->normalizeAssignmentNotes($notes),
                'assigned_by' => $assignedBy,
                'assignment_type' => $normalizedType,
            ]);

            // Log assignment
            AttendanceSchemaChangeLog::logChange(
                $schema->id,
                'assigned',
                null,
                [
                    'user_id' => $user->id,
                    'user_name' => $user->nama_lengkap,
                    'start_date' => $startAt->toDateString(),
                    'end_date' => $endAt?->toDateString(),
                    'notes' => $notes,
                    'assignment_type' => $normalizedType,
                ],
                $assignedBy,
                "Schema assigned to user: {$user->nama_lengkap}"
            );

            DB::commit();

            // Clear user's schema cache
            $this->clearUserSchemaCache($user->id);

            return $assignment;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to assign schema to user: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Bulk assign schema to multiple users
     */
    public function bulkAssignSchema(
        array $userIds,
        AttendanceSchema $schema,
        $startDate = null,
        $endDate = null,
        $notes = null,
        $assignedBy = null,
        ?string $assignmentType = 'bulk'
    ): array
    {
        $startDate = $startDate ?: now()->toDateString();
        $assignedBy = $assignedBy ?: auth()->id();
        $results = [];

        foreach (array_values(array_unique(array_map('intval', $userIds))) as $userId) {
            try {
                $user = User::find($userId);
                if (!$user) {
                    $results[$userId] = ['success' => false, 'message' => 'User not found'];
                    continue;
                }

                $assignment = $this->assignSchemaToUser(
                    $user,
                    $schema,
                    $startDate,
                    $endDate,
                    $notes,
                    $assignedBy,
                    $assignmentType
                );

                $results[$userId] = ['success' => true, 'assignment' => $assignment];
            } catch (\Throwable $e) {
                Log::warning('Failed to bulk assign schema for user', [
                    'user_id' => $userId,
                    'schema_id' => $schema->id,
                    'message' => $e->getMessage(),
                ]);
                $results[$userId] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        AttendanceSchemaChangeLog::logChange(
            $schema->id,
            'bulk_assigned',
            null,
            [
                'user_count' => count(array_filter($results, fn($result) => !empty($result['success']))),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'notes' => $notes,
                'assignment_type' => $this->normalizeAssignmentType($assignmentType),
            ],
            $assignedBy,
            "Schema bulk assigned to " . count($userIds) . " users"
        );

        return $results;
    }

    /**
     * Auto assign schemas based on user roles and status
     */
    public function autoAssignSchemas(?array $userIds = null, ?AttendanceSchema $schema = null): array
    {
        $users = $userIds ? User::whereIn('id', $userIds)->get() : User::all();
        $results = [];
        $systemUserId = auth()->id() ?: 1;

        foreach ($users as $user) {
            try {
                // Skip if user already has manual assignment
                $hasManualAssignment = AttendanceSchemaAssignment::forUser($user->id)
                    ->current()
                    ->whereIn('assignment_type', ['manual', 'bulk'])
                    ->exists();

                if ($hasManualAssignment) {
                    $results[$user->id] = ['success' => false, 'message' => 'User has manual assignment'];
                    continue;
                }

                if ($schema instanceof AttendanceSchema) {
                    if (!$schema->matchesUser($user)) {
                        $results[$user->id] = ['success' => false, 'message' => 'User does not match schema target'];
                        continue;
                    }

                    $assignment = $this->assignSchemaToUser(
                        $user,
                        $schema,
                        now()->toDateString(),
                        null,
                        'Auto-assigned from schema target rule',
                        $systemUserId,
                        'auto'
                    );

                    $results[$user->id] = [
                        'success' => true,
                        'schema' => $schema,
                        'assignment' => $assignment,
                        'assignment_type' => 'auto',
                        'message' => 'Schema assigned automatically from selected schema rule',
                    ];
                    continue;
                }

                $match = $this->findBestSchemaMatchForUser($user);
                $matchedSchema = $match['schema'] ?? null;
                $assignmentType = $match['assignment_type'] ?? 'none';

                if (!$matchedSchema instanceof AttendanceSchema) {
                    $results[$user->id] = ['success' => false, 'message' => 'No matching schema found'];
                    continue;
                }

                if ($assignmentType === 'default') {
                    $this->clearCurrentAutomaticAssignments($user->id, 'Menggunakan schema default global.');
                    $results[$user->id] = [
                        'success' => true,
                        'schema' => $matchedSchema,
                        'assignment' => null,
                        'assignment_type' => 'default',
                        'message' => 'User now resolves to default schema',
                    ];
                    continue;
                }

                $assignment = $this->assignSchemaToUser(
                    $user,
                    $matchedSchema,
                    now()->toDateString(),
                    null,
                    'Auto-assigned based on role, status, tingkat, dan kelas',
                    $systemUserId,
                    'auto'
                );

                $results[$user->id] = [
                    'success' => true,
                    'schema' => $matchedSchema,
                    'assignment' => $assignment,
                    'assignment_type' => 'auto',
                    'message' => 'Schema assigned automatically',
                ];
            } catch (\Exception $e) {
                $results[$user->id] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Find best matching schema for user
     */
    private function findBestSchemaMatchForUser(User $user): array
    {
        $schemas = AttendanceSchema::active()
            ->orderBy('priority', 'desc')
            ->get();

        $bestMatch = null;
        $highestScore = PHP_INT_MIN;

        foreach ($schemas as $schema) {
            if ($schema->isGlobalDefaultBaseline()) {
                continue;
            }

            if ($schema->matchesUser($user)) {
                $score = $schema->getPriorityScore($user);
                if ($score > $highestScore) {
                    $highestScore = $score;
                    $bestMatch = $schema;
                }
            }
        }

        if ($bestMatch instanceof AttendanceSchema) {
            return [
                'schema' => $bestMatch,
                'assignment_type' => 'auto',
                'assignment_reason' => 'Schema otomatis dipilih berdasarkan role, status, tingkat, atau kelas.',
            ];
        }

        $defaultSchema = $this->getDefaultFallbackSchema();

        return [
            'schema' => $defaultSchema,
            'assignment_type' => $defaultSchema ? 'default' : 'none',
            'assignment_reason' => $defaultSchema
                ? 'Menggunakan schema default global karena tidak ada rule yang lebih spesifik.'
                : 'Tidak ada schema aktif yang tersedia.',
        ];
    }

    /**
     * Get schema snapshot for attendance record
     */
    public function getSchemaSnapshot(AttendanceSchema $schema, ?User $user = null): array
    {
        return $schema->getSnapshot();
    }

    /**
     * Check if user is required to attend based on their schema
     */
    public function isAttendanceRequired(User $user, $date = null): bool
    {
        $schema = $this->getEffectiveSchema($user, $date);

        if (!$schema) {
            return false; // No schema = no attendance required
        }

        return $schema->isAttendanceRequired();
    }

    /**
     * Get working hours for user based on their schema
     */
    public function getWorkingHours(User $user, $date = null): array
    {
        $schema = $this->getEffectiveSchema($user, $date);

        if (!$schema) {
            $schema = AttendanceSchema::query()
                ->where('schema_type', 'global')
                ->where('is_default', true)
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->first();
        }

        if (!$schema) {
            // Last fallback if DB has no schema yet.
            return [
                'jam_masuk' => '07:00',
                'jam_pulang' => '15:00',
                'toleransi' => 15,
                'minimal_open_time' => 70,
            ];
        }

        return $schema->getEffectiveWorkingHours($user);
    }

    /**
     * Get all schemas with usage statistics
     */
    public function getSchemasWithStats(): array
    {
        $schemas = AttendanceSchema::with(['assignments', 'attendanceRecords'])
            ->orderBy('priority', 'desc')
            ->get();

        return $schemas->map(function ($schema) {
            return [
                'schema' => $schema,
                'active_assignments' => $schema->assignments()->active()->count(),
                'total_assignments' => $schema->assignments()->count(),
                'attendance_records' => $schema->attendanceRecords()->count(),
                'last_used' => $schema->attendanceRecords()->latest()->first()?->created_at,
            ];
        })->toArray();
    }

    /**
     * Clear schema-related cache
     */
    public function clearSchemaCache(): void
    {
        $allKeys = $this->getCacheIndex(self::CACHE_INDEX_ALL_KEYS);
        foreach ($allKeys as $key) {
            Cache::forget($key);
        }

        $userIds = $this->getCacheIndex(self::CACHE_INDEX_USER_IDS);
        foreach ($userIds as $userId) {
            Cache::forget(self::CACHE_INDEX_USER_KEYS_PREFIX . $userId);
            Cache::forget("working_hours_user_{$userId}");
        }

        Cache::forget(self::CACHE_INDEX_ALL_KEYS);
        Cache::forget(self::CACHE_INDEX_USER_IDS);
        $this->bumpAttendanceRuntimeVersion();

        Log::info('Attendance schema cache cleared');
    }

    /**
     * Clear specific user's schema cache
     */
    public function clearUserSchemaCache(int $userId): void
    {
        $userKeysIndex = self::CACHE_INDEX_USER_KEYS_PREFIX . $userId;
        $userKeys = $this->getCacheIndex($userKeysIndex);
        foreach ($userKeys as $key) {
            Cache::forget($key);
        }

        $this->removeFromCacheIndex(self::CACHE_INDEX_ALL_KEYS, $userKeys);
        Cache::forget($userKeysIndex);
        Cache::forget("working_hours_user_{$userId}");
        $this->bumpAttendanceRuntimeVersion();

        Log::info("User schema cache cleared for user: {$userId}");
    }

    /**
     * Register effective schema cache keys for targeted invalidation.
     */
    private function registerEffectiveSchemaCacheKey(string $cacheKey, int $userId): void
    {
        $this->appendToCacheIndex(self::CACHE_INDEX_ALL_KEYS, $cacheKey);
        $this->appendToCacheIndex(self::CACHE_INDEX_USER_IDS, $userId);
        $this->appendToCacheIndex(self::CACHE_INDEX_USER_KEYS_PREFIX . $userId, $cacheKey);
    }

    /**
     * Append unique value to cache index.
     */
    private function appendToCacheIndex(string $indexKey, string|int $value): void
    {
        $items = $this->getCacheIndex($indexKey);
        if (!in_array($value, $items, true)) {
            $items[] = $value;
            Cache::put($indexKey, $items, now()->addDay());
        }
    }

    /**
     * Get normalized cache index array.
     */
    private function getCacheIndex(string $indexKey): array
    {
        $items = Cache::get($indexKey, []);
        return is_array($items) ? $items : [];
    }

    /**
     * Remove multiple values from cache index.
     */
    private function removeFromCacheIndex(string $indexKey, array $values): void
    {
        if (empty($values)) {
            return;
        }

        $items = $this->getCacheIndex($indexKey);
        if (empty($items)) {
            return;
        }

        $filtered = array_values(array_filter($items, function ($item) use ($values) {
            return !in_array($item, $values, true);
        }));

        if (empty($filtered)) {
            Cache::forget($indexKey);
            return;
        }

        Cache::put($indexKey, $filtered, now()->addDay());
    }

    private function bumpAttendanceRuntimeVersion(): void
    {
        $current = Cache::get(self::RUNTIME_CACHE_VERSION_KEY);
        if ($current === null) {
            Cache::forever(self::RUNTIME_CACHE_VERSION_KEY, 2);
            return;
        }

        Cache::increment(self::RUNTIME_CACHE_VERSION_KEY);
    }

    /**
     * Resolve cached effective schema selection metadata.
     *
     * @return array{
     *     schema:?AttendanceSchema,
     *     assignment_type:string,
     *     assignment_id:?int,
     *     start_date:?string,
     *     end_date:?string,
     *     assignment_reason:?string
     * }
     */
    private function resolveEffectiveSchemaSelection(User $user, $date = null): array
    {
        $date = $date ?: now()->toDateString();
        $cacheKey = $this->getEffectiveSchemaCacheKey($user->id, (string) $date);
        $this->registerEffectiveSchemaCacheKey($cacheKey, $user->id);

        return Cache::remember($cacheKey, 300, function () use ($user, $date) {
            $assignment = $this->resolveCurrentAssignment($user->id, (string) $date);

            if ($assignment && $assignment->schema && $assignment->schema->is_active) {
                return [
                    'schema' => $assignment->schema,
                    'assignment_type' => $this->normalizeAssignmentType($assignment->assignment_type),
                    'assignment_id' => (int) $assignment->id,
                    'start_date' => $assignment->start_date?->toDateString(),
                    'end_date' => $assignment->end_date?->toDateString(),
                    'assignment_reason' => $this->describeStoredAssignment($assignment),
                ];
            }

            return $this->findBestSchemaMatchForUser($user);
        });
    }

    private function getEffectiveSchemaCacheKey(int $userId, string $date): string
    {
        $version = (int) Cache::get(self::RUNTIME_CACHE_VERSION_KEY, 1);

        return "effective_schema_user_{$userId}_{$date}_v{$version}";
    }

    private function resolveCurrentAssignment(int $userId, string $date): ?AttendanceSchemaAssignment
    {
        return AttendanceSchemaAssignment::forUser($userId)
            ->current($date)
            ->with('schema')
            ->orderByRaw("
                CASE assignment_type
                    WHEN 'manual' THEN 0
                    WHEN 'bulk' THEN 1
                    WHEN 'auto' THEN 2
                    ELSE 3
                END
            ")
            ->orderBy('created_at', 'desc')
            ->first();
    }

    private function getDefaultFallbackSchema(): ?AttendanceSchema
    {
        return AttendanceSchema::default()
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function normalizeAssignmentType(?string $assignmentType): string
    {
        $normalized = strtolower(trim((string) $assignmentType));

        return in_array($normalized, ['manual', 'bulk', 'auto', 'default'], true)
            ? $normalized
            : 'manual';
    }

    private function normalizeAssignmentNotes($notes): ?string
    {
        $value = trim((string) ($notes ?? ''));

        return $value !== '' ? $value : null;
    }

    private function appendAssignmentNote(?string $existingNotes, string $note): string
    {
        $existing = trim((string) ($existingNotes ?? ''));
        $entry = trim($note);

        if ($existing === '') {
            return $entry;
        }

        return $existing . "\n" . $entry;
    }

    private function describeStoredAssignment(AttendanceSchemaAssignment $assignment): string
    {
        return match ($this->normalizeAssignmentType($assignment->assignment_type)) {
            'bulk' => 'Schema ditetapkan melalui assignment massal.',
            'auto' => 'Schema dikunci melalui auto assignment.',
            default => 'Schema ditetapkan manual untuk siswa ini.',
        };
    }

    private function clearCurrentAutomaticAssignments(int $userId, ?string $reason = null): void
    {
        $assignments = AttendanceSchemaAssignment::forUser($userId)
            ->where('is_active', true)
            ->where('assignment_type', 'auto')
            ->get();

        foreach ($assignments as $assignment) {
            $assignment->is_active = false;
            $assignment->notes = $this->appendAssignmentNote(
                $assignment->notes,
                $reason ?: 'Dinonaktifkan otomatis.'
            );
            $assignment->save();
        }

        if ($assignments->isNotEmpty()) {
            $this->clearUserSchemaCache($userId);
        }
    }

    private function replaceOverlappingAssignmentsForUser(
        int $userId,
        Carbon $newStart,
        ?Carbon $newEnd,
        string $reason,
        ?int $assignedBy = null
    ): void {
        $query = AttendanceSchemaAssignment::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->where(function ($builder) use ($newStart) {
                $builder->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $newStart->toDateString());
            });

        if ($newEnd !== null) {
            $query->whereDate('start_date', '<=', $newEnd->toDateString());
        }

        $overlappingAssignments = $query
            ->orderBy('start_date')
            ->get();

        foreach ($overlappingAssignments as $assignment) {
            $existingStart = Carbon::parse($assignment->start_date)->startOfDay();
            $existingEnd = $assignment->end_date
                ? Carbon::parse($assignment->end_date)->startOfDay()
                : null;

            $hasPrefix = $existingStart->lt($newStart);
            $hasSuffix = $newEnd !== null && ($existingEnd === null || $existingEnd->gt($newEnd));
            $originalNotes = $assignment->notes;
            $originalEndDate = $existingEnd?->toDateString();

            if ($hasPrefix && $hasSuffix) {
                $assignment->end_date = $newStart->copy()->subDay()->toDateString();
                $assignment->notes = $this->appendAssignmentNote(
                    $originalNotes,
                    'Bagian awal assignment dipertahankan. ' . $reason
                );
                $assignment->save();

                AttendanceSchemaAssignment::create([
                    'user_id' => $assignment->user_id,
                    'attendance_setting_id' => $assignment->attendance_setting_id,
                    'start_date' => $newEnd->copy()->addDay()->toDateString(),
                    'end_date' => $originalEndDate,
                    'is_active' => true,
                    'notes' => $this->appendAssignmentNote(
                        $originalNotes,
                        'Bagian akhir assignment dipulihkan otomatis setelah override berakhir.'
                    ),
                    'assigned_by' => $assignment->assigned_by ?: $assignedBy,
                    'assignment_type' => $this->normalizeAssignmentType($assignment->assignment_type),
                ]);

                continue;
            }

            if ($hasPrefix) {
                $assignment->end_date = $newStart->copy()->subDay()->toDateString();
                $assignment->notes = $this->appendAssignmentNote(
                    $originalNotes,
                    'Assignment dipotong sebelum schema baru aktif. ' . $reason
                );
                $assignment->save();
                continue;
            }

            if ($hasSuffix && $newEnd !== null) {
                $assignment->start_date = $newEnd->copy()->addDay()->toDateString();
                $assignment->notes = $this->appendAssignmentNote(
                    $originalNotes,
                    'Assignment digeser setelah periode override berakhir. ' . $reason
                );
                $assignment->save();
                continue;
            }

            $assignment->is_active = false;
            $assignment->notes = $this->appendAssignmentNote($originalNotes, $reason);
            $assignment->save();
        }
    }

    /**
     * Validate schema data
     */
    public function validateSchemaData(array $data): array
    {
        $errors = [];

        // Required fields
        if (empty($data['schema_name'])) {
            $errors['schema_name'] = 'Schema name is required';
        }

        if (empty($data['schema_type'])) {
            $errors['schema_type'] = 'Schema type is required';
        }

        // Time validation
        if (!empty($data['jam_masuk_default']) && !empty($data['jam_pulang_default'])) {
            if ($data['jam_masuk_default'] >= $data['jam_pulang_default']) {
                $errors['jam_pulang_default'] = 'Jam pulang must be after jam masuk';
            }
        }

        if (!empty($data['siswa_jam_masuk']) && !empty($data['siswa_jam_pulang'])) {
            if ($data['siswa_jam_masuk'] >= $data['siswa_jam_pulang']) {
                $errors['siswa_jam_pulang'] = 'Siswa jam pulang must be after jam masuk';
            }
        }

        // Tolerance validation
        if (isset($data['toleransi_default']) && ($data['toleransi_default'] < 0 || $data['toleransi_default'] > 120)) {
            $errors['toleransi_default'] = 'Toleransi must be between 0 and 120 minutes';
        }

        if (isset($data['siswa_toleransi']) && ($data['siswa_toleransi'] < 0 || $data['siswa_toleransi'] > 120)) {
            $errors['siswa_toleransi'] = 'Siswa toleransi must be between 0 and 120 minutes';
        }

        if (isset($data['violation_minutes_threshold']) && ($data['violation_minutes_threshold'] < 0 || $data['violation_minutes_threshold'] > 100000)) {
            $errors['violation_minutes_threshold'] = 'Violation minutes threshold must be between 0 and 100000';
        }

        if (isset($data['violation_percentage_threshold']) && ($data['violation_percentage_threshold'] < 0 || $data['violation_percentage_threshold'] > 100)) {
            $errors['violation_percentage_threshold'] = 'Violation percentage threshold must be between 0 and 100';
        }

        if (isset($data['total_violation_minutes_semester_limit']) && ($data['total_violation_minutes_semester_limit'] < 0 || $data['total_violation_minutes_semester_limit'] > 100000)) {
            $errors['total_violation_minutes_semester_limit'] = 'Total violation minutes semester limit must be between 0 and 100000';
        }

        if (isset($data['alpha_days_semester_limit']) && ($data['alpha_days_semester_limit'] < 0 || $data['alpha_days_semester_limit'] > 365)) {
            $errors['alpha_days_semester_limit'] = 'Alpha days semester limit must be between 0 and 365';
        }

        if (isset($data['late_minutes_monthly_limit']) && ($data['late_minutes_monthly_limit'] < 0 || $data['late_minutes_monthly_limit'] > 100000)) {
            $errors['late_minutes_monthly_limit'] = 'Late minutes monthly limit must be between 0 and 100000';
        }

        foreach ([
            'semester_total_violation_mode',
            'semester_alpha_mode',
            'monthly_late_mode',
        ] as $field) {
            if (isset($data[$field]) && !in_array($data[$field], ['monitor_only', 'alertable'], true)) {
                $errors[$field] = $field . ' must be monitor_only or alertable';
            }
        }

        return $errors;
    }
}
