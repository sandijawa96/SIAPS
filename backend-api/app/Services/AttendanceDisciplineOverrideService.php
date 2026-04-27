<?php

namespace App\Services;

use App\Models\AttendanceDisciplineOverride;
use App\Models\AttendanceGovernanceLog;
use App\Models\Kelas;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceDisciplineOverrideService
{
    private const RUNTIME_CACHE_VERSION_KEY = 'attendance_runtime_version';
    private const CACHE_PREFIX = 'attendance_discipline_override:resolved_user:';

    /**
     * @return EloquentCollection<int, AttendanceDisciplineOverride>
     */
    public function listOverrides(bool $includeInactive = true): EloquentCollection
    {
        $query = AttendanceDisciplineOverride::query()
            ->with([
                'tingkat:id,nama',
                'kelas:id,nama_kelas,tingkat_id',
                'kelas.tingkat:id,nama',
                'targetUser:id,nama_lengkap,nis,nisn',
                'updatedByUser:id,nama_lengkap',
            ])
            ->orderByDesc('is_active')
            ->orderByRaw(
                "CASE scope_type
                    WHEN 'user' THEN 3
                    WHEN 'kelas' THEN 2
                    WHEN 'tingkat' THEN 1
                    ELSE 0
                END DESC"
            )
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        if (!$includeInactive) {
            $query->active();
        }

        return $query->get();
    }

    public function resolveForUser(?User $user): ?AttendanceDisciplineOverride
    {
        if (!$user instanceof User) {
            return null;
        }

        $cacheKey = $this->buildCacheKey((int) $user->id);

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($user) {
            return $this->resolveForUserFresh($user);
        });
    }

    public function createOverride(array $data, ?int $actorUserId = null): AttendanceDisciplineOverride
    {
        $normalized = $this->normalizePayload($data);

        $this->assertNoDuplicateTarget($normalized['scope_type'], $normalized['target_id']);

        return DB::transaction(function () use ($normalized, $actorUserId) {
            $override = AttendanceDisciplineOverride::create([
                ...$normalized['attributes'],
                'created_by' => $actorUserId,
                'updated_by' => $actorUserId,
            ]);

            $override->loadMissing($this->relationsForSerialization());
            $this->recordGovernance('created', null, $override, $actorUserId);
            $this->bumpRuntimeVersion();

            return $override;
        });
    }

    public function updateOverride(AttendanceDisciplineOverride $override, array $data, ?int $actorUserId = null): AttendanceDisciplineOverride
    {
        $normalized = $this->normalizePayload($data);

        $this->assertNoDuplicateTarget(
            $normalized['scope_type'],
            $normalized['target_id'],
            (int) $override->id
        );

        return DB::transaction(function () use ($override, $normalized, $actorUserId) {
            $oldValues = $this->serializeOverride($override->fresh($this->relationsForSerialization()) ?: $override);

            $override->fill($normalized['attributes']);
            $override->updated_by = $actorUserId;
            $override->save();
            $override->loadMissing($this->relationsForSerialization());

            $this->recordGovernance('updated', $oldValues, $override, $actorUserId);
            $this->bumpRuntimeVersion();

            return $override;
        });
    }

    public function deleteOverride(AttendanceDisciplineOverride $override, ?int $actorUserId = null): void
    {
        DB::transaction(function () use ($override, $actorUserId) {
            $oldValues = $this->serializeOverride($override->fresh($this->relationsForSerialization()) ?: $override);
            $override->delete();

            AttendanceGovernanceLog::record([
                'category' => 'attendance_discipline_override',
                'action' => 'deleted',
                'actor_user_id' => $actorUserId ?: auth()->id(),
                'target_type' => 'attendance_discipline_override',
                'target_id' => $override->id,
                'old_values' => $oldValues,
                'new_values' => null,
                'metadata' => [
                    'scope_type' => $oldValues['scope_type'] ?? null,
                    'scope_label' => $oldValues['scope_label'] ?? null,
                ],
            ]);

            $this->bumpRuntimeVersion();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeOverride(AttendanceDisciplineOverride $override): array
    {
        $override->loadMissing($this->relationsForSerialization());

        return [
            'id' => (int) $override->id,
            'scope_type' => (string) $override->scope_type,
            'scope_label' => (string) $override->scope_label,
            'is_active' => (bool) $override->is_active,
            'discipline_thresholds_enabled' => (bool) $override->discipline_thresholds_enabled,
            'total_violation_minutes_semester_limit' => (int) ($override->total_violation_minutes_semester_limit ?? 0),
            'alpha_days_semester_limit' => (int) ($override->alpha_days_semester_limit ?? 0),
            'late_minutes_monthly_limit' => (int) ($override->late_minutes_monthly_limit ?? 0),
            'semester_total_violation_mode' => (string) ($override->semester_total_violation_mode ?? 'monitor_only'),
            'notify_wali_kelas_on_total_violation_limit' => (bool) ($override->notify_wali_kelas_on_total_violation_limit ?? false),
            'notify_kesiswaan_on_total_violation_limit' => (bool) ($override->notify_kesiswaan_on_total_violation_limit ?? false),
            'semester_alpha_mode' => (string) ($override->semester_alpha_mode ?? 'alertable'),
            'monthly_late_mode' => (string) ($override->monthly_late_mode ?? 'monitor_only'),
            'notify_wali_kelas_on_late_limit' => (bool) ($override->notify_wali_kelas_on_late_limit ?? false),
            'notify_kesiswaan_on_late_limit' => (bool) ($override->notify_kesiswaan_on_late_limit ?? false),
            'notify_wali_kelas_on_alpha_limit' => (bool) ($override->notify_wali_kelas_on_alpha_limit ?? true),
            'notify_kesiswaan_on_alpha_limit' => (bool) ($override->notify_kesiswaan_on_alpha_limit ?? true),
            'notes' => $override->notes,
            'target_tingkat_id' => $override->target_tingkat_id ? (int) $override->target_tingkat_id : null,
            'target_kelas_id' => $override->target_kelas_id ? (int) $override->target_kelas_id : null,
            'target_user_id' => $override->target_user_id ? (int) $override->target_user_id : null,
            'target_tingkat' => $override->tingkat ? [
                'id' => (int) $override->tingkat->id,
                'nama' => (string) $override->tingkat->nama,
            ] : null,
            'target_kelas' => $override->kelas ? [
                'id' => (int) $override->kelas->id,
                'nama_kelas' => (string) ($override->kelas->nama_kelas ?? ''),
                'tingkat_id' => $override->kelas->tingkat_id ? (int) $override->kelas->tingkat_id : null,
                'tingkat_nama' => $override->kelas->relationLoaded('tingkat') && $override->kelas->tingkat
                    ? (string) $override->kelas->tingkat->nama
                    : null,
            ] : null,
            'target_user' => $override->targetUser ? [
                'id' => (int) $override->targetUser->id,
                'nama_lengkap' => (string) $override->targetUser->nama_lengkap,
                'nis' => (string) ($override->targetUser->nis ?? ''),
                'nisn' => (string) ($override->targetUser->nisn ?? ''),
            ] : null,
            'updated_by_user' => $override->updatedByUser ? [
                'id' => (int) $override->updatedByUser->id,
                'nama_lengkap' => (string) $override->updatedByUser->nama_lengkap,
            ] : null,
            'updated_at' => optional($override->updated_at)?->toISOString(),
            'created_at' => optional($override->created_at)?->toISOString(),
        ];
    }

    private function resolveForUserFresh(User $user): ?AttendanceDisciplineOverride
    {
        $userOverride = AttendanceDisciplineOverride::query()
            ->with($this->relationsForSerialization())
            ->active()
            ->where('scope_type', AttendanceDisciplineOverride::SCOPE_USER)
            ->where('target_user_id', (int) $user->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();

        if ($userOverride instanceof AttendanceDisciplineOverride) {
            return $userOverride;
        }

        $activeClass = $this->resolvePrimaryClass($user);
        if ($activeClass instanceof Kelas) {
            $classOverride = AttendanceDisciplineOverride::query()
                ->with($this->relationsForSerialization())
                ->active()
                ->where('scope_type', AttendanceDisciplineOverride::SCOPE_KELAS)
                ->where('target_kelas_id', (int) $activeClass->id)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();

            if ($classOverride instanceof AttendanceDisciplineOverride) {
                return $classOverride;
            }

            if ($activeClass->tingkat_id) {
                $tingkatOverride = AttendanceDisciplineOverride::query()
                    ->with($this->relationsForSerialization())
                    ->active()
                    ->where('scope_type', AttendanceDisciplineOverride::SCOPE_TINGKAT)
                    ->where('target_tingkat_id', (int) $activeClass->tingkat_id)
                    ->orderByDesc('updated_at')
                    ->orderByDesc('id')
                    ->first();

                if ($tingkatOverride instanceof AttendanceDisciplineOverride) {
                    return $tingkatOverride;
                }
            }
        }

        return null;
    }

    /**
     * @return array{scope_type:string,target_id:int,attributes:array<string,mixed>}
     */
    private function normalizePayload(array $data): array
    {
        $scopeType = strtolower(trim((string) ($data['scope_type'] ?? '')));

        if (!in_array($scopeType, [
            AttendanceDisciplineOverride::SCOPE_TINGKAT,
            AttendanceDisciplineOverride::SCOPE_KELAS,
            AttendanceDisciplineOverride::SCOPE_USER,
        ], true)) {
            throw ValidationException::withMessages([
                'scope_type' => 'Scope override tidak valid.',
            ]);
        }

        $targetTingkatId = null;
        $targetKelasId = null;
        $targetUserId = null;
        $targetId = 0;

        if ($scopeType === AttendanceDisciplineOverride::SCOPE_TINGKAT) {
            $targetTingkatId = (int) ($data['target_tingkat_id'] ?? 0);
            $targetId = $targetTingkatId;
            $this->assertTargetExists(Tingkat::class, $targetTingkatId, 'target_tingkat_id');
        } elseif ($scopeType === AttendanceDisciplineOverride::SCOPE_KELAS) {
            $targetKelasId = (int) ($data['target_kelas_id'] ?? 0);
            $targetId = $targetKelasId;
            $this->assertTargetExists(Kelas::class, $targetKelasId, 'target_kelas_id');
        } else {
            $targetUserId = (int) ($data['target_user_id'] ?? 0);
            $targetId = $targetUserId;
            $this->assertTargetExists(User::class, $targetUserId, 'target_user_id');
            $this->assertStudentTarget($targetUserId);
        }

        $attributes = [
            'scope_type' => $scopeType,
            'target_tingkat_id' => $targetTingkatId,
            'target_kelas_id' => $targetKelasId,
            'target_user_id' => $targetUserId,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'discipline_thresholds_enabled' => array_key_exists('discipline_thresholds_enabled', $data)
                ? (bool) $data['discipline_thresholds_enabled']
                : true,
            'total_violation_minutes_semester_limit' => (int) ($data['total_violation_minutes_semester_limit'] ?? 1200),
            'alpha_days_semester_limit' => (int) ($data['alpha_days_semester_limit'] ?? 8),
            'late_minutes_monthly_limit' => (int) ($data['late_minutes_monthly_limit'] ?? 120),
            'semester_total_violation_mode' => (string) ($data['semester_total_violation_mode'] ?? 'monitor_only'),
            'notify_wali_kelas_on_total_violation_limit' => (bool) ($data['notify_wali_kelas_on_total_violation_limit'] ?? false),
            'notify_kesiswaan_on_total_violation_limit' => (bool) ($data['notify_kesiswaan_on_total_violation_limit'] ?? false),
            'semester_alpha_mode' => (string) ($data['semester_alpha_mode'] ?? 'alertable'),
            'monthly_late_mode' => (string) ($data['monthly_late_mode'] ?? 'monitor_only'),
            'notify_wali_kelas_on_late_limit' => (bool) ($data['notify_wali_kelas_on_late_limit'] ?? false),
            'notify_kesiswaan_on_late_limit' => (bool) ($data['notify_kesiswaan_on_late_limit'] ?? false),
            'notify_wali_kelas_on_alpha_limit' => array_key_exists('notify_wali_kelas_on_alpha_limit', $data)
                ? (bool) $data['notify_wali_kelas_on_alpha_limit']
                : true,
            'notify_kesiswaan_on_alpha_limit' => array_key_exists('notify_kesiswaan_on_alpha_limit', $data)
                ? (bool) $data['notify_kesiswaan_on_alpha_limit']
                : true,
            'notes' => $this->normalizeNotes($data['notes'] ?? null),
        ];

        return [
            'scope_type' => $scopeType,
            'target_id' => $targetId,
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function relationsForSerialization(): array
    {
        return [
            'tingkat:id,nama',
            'kelas:id,nama_kelas,tingkat_id',
            'kelas.tingkat:id,nama',
            'targetUser:id,nama_lengkap,nis,nisn',
            'updatedByUser:id,nama_lengkap',
        ];
    }

    private function assertTargetExists(string $modelClass, int $targetId, string $field): void
    {
        if ($targetId <= 0 || !$modelClass::query()->whereKey($targetId)->exists()) {
            throw ValidationException::withMessages([
                $field => 'Target override tidak ditemukan.',
            ]);
        }
    }

    private function assertStudentTarget(int $targetUserId): void
    {
        $user = User::query()->find($targetUserId);
        if (!$user instanceof User || !$user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            throw ValidationException::withMessages([
                'target_user_id' => 'Override hanya dapat diarahkan ke siswa.',
            ]);
        }
    }

    private function assertNoDuplicateTarget(string $scopeType, int $targetId, ?int $exceptId = null): void
    {
        $query = AttendanceDisciplineOverride::query()
            ->where('scope_type', $scopeType);

        if ($scopeType === AttendanceDisciplineOverride::SCOPE_TINGKAT) {
            $query->where('target_tingkat_id', $targetId);
        } elseif ($scopeType === AttendanceDisciplineOverride::SCOPE_KELAS) {
            $query->where('target_kelas_id', $targetId);
        } else {
            $query->where('target_user_id', $targetId);
        }

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'scope_type' => 'Override untuk target tersebut sudah ada. Ubah data yang ada, jangan membuat duplikat.',
            ]);
        }
    }

    private function normalizeNotes($value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function resolvePrimaryClass(User $user): ?Kelas
    {
        if ($user->relationLoaded('kelas') && $user->kelas instanceof Collection && $user->kelas->isNotEmpty()) {
            $activeClass = $user->kelas->first(function ($kelas) {
                return (bool) ($kelas->pivot->is_active ?? false);
            });

            $resolved = $activeClass ?: $user->kelas->first();
            if ($resolved instanceof Kelas) {
                $resolved->loadMissing('tingkat:id,nama');
                return $resolved;
            }
        }

        $activeClass = $user->kelas()
            ->with('tingkat:id,nama')
            ->wherePivot('is_active', true)
            ->first();

        if ($activeClass instanceof Kelas) {
            return $activeClass;
        }

        return $user->kelas()
            ->with('tingkat:id,nama')
            ->first();
    }

    /**
     * @param array<string, mixed>|null $oldValues
     */
    private function recordGovernance(
        string $action,
        ?array $oldValues,
        AttendanceDisciplineOverride $override,
        ?int $actorUserId = null
    ): void {
        $newValues = $this->serializeOverride($override);

        AttendanceGovernanceLog::record([
            'category' => 'attendance_discipline_override',
            'action' => $action,
            'actor_user_id' => $actorUserId ?: auth()->id(),
            'target_type' => 'attendance_discipline_override',
            'target_id' => $override->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => [
                'scope_type' => $override->scope_type,
                'scope_label' => $override->scope_label,
                'changed_fields' => $oldValues === null
                    ? array_keys($newValues)
                    : array_values(array_filter(array_keys($newValues), function ($field) use ($newValues, $oldValues) {
                        return json_encode($newValues[$field] ?? null) !== json_encode($oldValues[$field] ?? null);
                    })),
            ],
        ]);
    }

    private function buildCacheKey(int $userId): string
    {
        $version = (int) Cache::get(self::RUNTIME_CACHE_VERSION_KEY, 1);

        return self::CACHE_PREFIX . $userId . ':v' . $version;
    }

    private function bumpRuntimeVersion(): void
    {
        $current = Cache::get(self::RUNTIME_CACHE_VERSION_KEY);

        if ($current === null) {
            Cache::forever(self::RUNTIME_CACHE_VERSION_KEY, 2);
            return;
        }

        Cache::increment(self::RUNTIME_CACHE_VERSION_KEY);
    }
}
