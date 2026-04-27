<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Support\RoleNames;

class AttendanceSchema extends Model
{
    use HasFactory;

    protected $table = 'attendance_settings';

    protected $fillable = [
        'schema_name',
        'schema_type',
        'target_role',
        'target_status',
        'schema_description',
        'is_active',
        'is_default',
        'is_mandatory',
        'priority',
        'version',
        'jam_masuk_default',
        'jam_pulang_default',
        'toleransi_default',
        'minimal_open_time_staff',
        'wajib_gps',
        'wajib_foto',
        'face_verification_enabled',
        'face_template_required',
        'hari_kerja',
        'lokasi_gps_ids',
        'siswa_jam_masuk',
        'siswa_jam_pulang',
        'siswa_toleransi',
        'minimal_open_time_siswa',
        'violation_minutes_threshold',
        'violation_percentage_threshold',
        'total_violation_minutes_semester_limit',
        'alpha_days_semester_limit',
        'late_minutes_monthly_limit',
        'discipline_thresholds_enabled',
        'semester_total_violation_mode',
        'notify_wali_kelas_on_total_violation_limit',
        'notify_kesiswaan_on_total_violation_limit',
        'semester_alpha_mode',
        'monthly_late_mode',
        'notify_wali_kelas_on_late_limit',
        'notify_kesiswaan_on_late_limit',
        'notify_wali_kelas_on_alpha_limit',
        'notify_kesiswaan_on_alpha_limit',
        'auto_alpha_enabled',
        'auto_alpha_run_time',
        'discipline_alerts_enabled',
        'discipline_alerts_run_time',
        'live_tracking_retention_days',
        'live_tracking_cleanup_time',
        'live_tracking_min_distance_meters',
        'live_tracking_enabled',
        'face_result_when_template_missing',
        'face_reject_to_manual_review',
        'face_skip_when_photo_missing',
        'radius_absensi',
        'gps_accuracy',
        'verification_mode',
        'attendance_scope',
        'target_tingkat_ids',
        'target_kelas_ids',
        'updated_by'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'is_mandatory' => 'boolean',
        'wajib_gps' => 'boolean',
        'wajib_foto' => 'boolean',
        'face_verification_enabled' => 'boolean',
        'face_template_required' => 'boolean',
        'hari_kerja' => 'array',
        'lokasi_gps_ids' => 'array',
        'jam_masuk_default' => 'string',
        'jam_pulang_default' => 'string',
        'siswa_jam_masuk' => 'string',
        'siswa_jam_pulang' => 'string',
        'toleransi_default' => 'integer',
        'minimal_open_time_staff' => 'integer',
        'siswa_toleransi' => 'integer',
        'minimal_open_time_siswa' => 'integer',
        'violation_minutes_threshold' => 'integer',
        'violation_percentage_threshold' => 'float',
        'total_violation_minutes_semester_limit' => 'integer',
        'alpha_days_semester_limit' => 'integer',
        'late_minutes_monthly_limit' => 'integer',
        'discipline_thresholds_enabled' => 'boolean',
        'semester_total_violation_mode' => 'string',
        'notify_wali_kelas_on_total_violation_limit' => 'boolean',
        'notify_kesiswaan_on_total_violation_limit' => 'boolean',
        'semester_alpha_mode' => 'string',
        'monthly_late_mode' => 'string',
        'notify_wali_kelas_on_late_limit' => 'boolean',
        'notify_kesiswaan_on_late_limit' => 'boolean',
        'notify_wali_kelas_on_alpha_limit' => 'boolean',
        'notify_kesiswaan_on_alpha_limit' => 'boolean',
        'auto_alpha_enabled' => 'boolean',
        'auto_alpha_run_time' => 'string',
        'discipline_alerts_enabled' => 'boolean',
        'discipline_alerts_run_time' => 'string',
        'live_tracking_retention_days' => 'integer',
        'live_tracking_cleanup_time' => 'string',
        'live_tracking_min_distance_meters' => 'integer',
        'live_tracking_enabled' => 'boolean',
        'face_result_when_template_missing' => 'string',
        'face_reject_to_manual_review' => 'boolean',
        'face_skip_when_photo_missing' => 'boolean',
        'radius_absensi' => 'integer',
        'gps_accuracy' => 'integer',
        'verification_mode' => 'string',
        'attendance_scope' => 'string',
        'target_tingkat_ids' => 'array',
        'target_kelas_ids' => 'array',
        'priority' => 'integer',
        'version' => 'integer'
    ];

    /**
     * Relationship with User who updated this schema
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Relationship with attendance records using this schema
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(Absensi::class, 'attendance_setting_id');
    }

    /**
     * Relationship with schema assignments
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AttendanceSchemaAssignment::class, 'attendance_setting_id');
    }

    /**
     * Relationship with change logs
     */
    public function changeLogs(): HasMany
    {
        return $this->hasMany(AttendanceSchemaChangeLog::class, 'attendance_setting_id');
    }

    /**
     * Scope for active schemas
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for default schema
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true)->where('is_active', true);
    }

    /**
     * Scope for mandatory attendance schemas
     */
    public function scopeMandatory($query)
    {
        return $query->where('is_mandatory', true)->where('is_active', true);
    }

    /**
     * Scope for specific role
     */
    public function scopeForRole($query, $role)
    {
        return $query->where('target_role', $role)->where('is_active', true);
    }

    /**
     * Scope for specific status kepegawaian
     */
    public function scopeForStatus($query, $status)
    {
        return $query->where('target_status', $status)->where('is_active', true);
    }

    /**
     * Scope for role and status combination
     */
    public function scopeForRoleAndStatus($query, $role, $status)
    {
        return $query->where('target_role', $role)
            ->where('target_status', $status)
            ->where('is_active', true);
    }

    /**
     * Get effective working hours based on schema type and user role
     */
    public function getEffectiveWorkingHours(?User $user = null): array
    {
        // For siswa, use siswa-specific settings
        if ($this->isTargetingStudentRole() || ($user && $user->hasRole(RoleNames::aliases(RoleNames::SISWA)))) {
            return [
                'jam_masuk' => $this->siswa_jam_masuk !== null && $this->siswa_jam_masuk !== ''
                    ? (string) $this->siswa_jam_masuk
                    : '07:00',
                'jam_pulang' => $this->siswa_jam_pulang !== null && $this->siswa_jam_pulang !== ''
                    ? (string) $this->siswa_jam_pulang
                    : '14:00',
                'toleransi' => $this->siswa_toleransi !== null
                    ? (int) $this->siswa_toleransi
                    : 10,
                'minimal_open_time' => $this->minimal_open_time_siswa !== null
                    ? (int) $this->minimal_open_time_siswa
                    : 70,
                'face_template_required' => $this->face_template_required !== null
                    ? (bool) $this->face_template_required
                    : (bool) config('attendance.face.template_required', true),
            ];
        }

        // For staff/guru, use default settings
        return [
            'jam_masuk' => $this->jam_masuk_default !== null && $this->jam_masuk_default !== ''
                ? (string) $this->jam_masuk_default
                : '07:00',
            'jam_pulang' => $this->jam_pulang_default !== null && $this->jam_pulang_default !== ''
                ? (string) $this->jam_pulang_default
                : '15:00',
            'toleransi' => $this->toleransi_default !== null
                ? (int) $this->toleransi_default
                : 15,
            'minimal_open_time' => $this->minimal_open_time_staff !== null
                ? (int) $this->minimal_open_time_staff
                : 70,
            'face_template_required' => $this->face_template_required !== null
                ? (bool) $this->face_template_required
                : (bool) config('attendance.face.template_required', true),
        ];
    }

    /**
     * Get schema snapshot for attendance record
     */
    public function getSnapshot(): array
    {
        return [
            'schema_id' => $this->id,
            'schema_name' => $this->schema_name,
            'schema_type' => $this->schema_type,
            'target_role' => $this->target_role,
            'target_status' => $this->target_status,
            'is_mandatory' => $this->is_mandatory,
            'version' => $this->version,
            'working_hours' => $this->getEffectiveWorkingHours(),
            'wajib_gps' => $this->wajib_gps,
            'wajib_foto' => $this->wajib_foto,
            'hari_kerja' => $this->hari_kerja,
            'lokasi_gps_ids' => $this->lokasi_gps_ids,
            'verification_mode' => $this->verification_mode,
            'attendance_scope' => $this->attendance_scope,
            'target_tingkat_ids' => $this->target_tingkat_ids,
            'target_kelas_ids' => $this->target_kelas_ids,
            'snapshot_timestamp' => now()->toISOString()
        ];
    }

    /**
     * Check if this schema matches user's role and status
     */
    public function matchesUser(User $user): bool
    {
        // Check role match
        $roleMatch = true;
        if ($this->target_role) {
            $roleMatch = $user->hasRole(RoleNames::aliasesFor($this->target_role));
        }

        // Check status match
        $statusMatch = true;
        if ($this->target_status) {
            $statusMatch = $user->status_kepegawaian === $this->target_status;
        }

        return $roleMatch
            && $statusMatch
            && $this->matchesTargetEducationGroup($user);
    }

    /**
     * Get priority score for auto assignment
     */
    public function getPriorityScore(User $user): int
    {
        $score = $this->priority;

        // Bonus for exact role+status match
        if ($this->target_role && $this->target_status) {
            if ($user->hasRole(RoleNames::aliasesFor($this->target_role)) && $user->status_kepegawaian === $this->target_status) {
                $score += 100;
            }
        }
        // Bonus for role match only
        elseif ($this->target_role && $user->hasRole(RoleNames::aliasesFor($this->target_role))) {
            $score += 50;
        }
        // Bonus for status match only
        elseif ($this->target_status && $user->status_kepegawaian === $this->target_status) {
            $score += 30;
        }

        $score += $this->getTargetEducationPriorityBonus($user);

        return $score;
    }

    /**
     * Check if attendance is required for this schema
     */
    public function isAttendanceRequired(): bool
    {
        return $this->is_mandatory;
    }

    /**
     * Check whether user is allowed to perform attendance for this schema.
     * This enforces: mandatory flag + scope + optional target tingkat/kelas.
     */
    public function allowsAttendanceForUser(User $user): bool
    {
        if (!$this->isAttendanceRequired()) {
            return false;
        }

        if (!$this->matchesAttendanceScope($user)) {
            return false;
        }

        return $this->matchesTargetEducationGroup($user);
    }

    /**
     * Scope guard based on attendance_scope setting.
     */
    public function matchesAttendanceScope(User $user): bool
    {
        $hasStudentRole = $user->hasRole(RoleNames::aliases(RoleNames::SISWA));
        if ($hasStudentRole) {
            return true;
        }

        // Fallback legacy data siswa yang belum rapih role mapping-nya.
        return !empty($user->nis) || !empty($user->nisn);
    }

    /**
     * Optional filter by target tingkat / kelas.
     * Applies only to siswa accounts.
     */
    public function matchesTargetEducationGroup(User $user): bool
    {
        $targetTingkatIds = $this->normalizeTargetIds($this->target_tingkat_ids);
        $targetKelasIds = $this->normalizeTargetIds($this->target_kelas_ids);

        if (empty($targetTingkatIds) && empty($targetKelasIds)) {
            return true;
        }

        if (!$user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            // Target kelas/tingkat is intended for siswa mapping.
            return true;
        }

        $kelasQuery = $user->kelas();

        if (!empty($targetKelasIds)) {
            $kelasQuery->whereIn('kelas.id', $targetKelasIds);
        }

        if (!empty($targetTingkatIds)) {
            $kelasQuery->whereIn('kelas.tingkat_id', $targetTingkatIds);
        }

        return $kelasQuery->exists();
    }

    /**
     * Whether this schema is the plain global fallback baseline.
     */
    public function isGlobalDefaultBaseline(): bool
    {
        return $this->is_default
            && $this->schema_type === 'global'
            && empty($this->target_role)
            && empty($this->target_status)
            && !$this->hasTargetEducationFilter();
    }

    /**
     * Whether this schema has class/level targeting.
     */
    public function hasTargetEducationFilter(): bool
    {
        return !empty($this->normalizeTargetIds($this->target_tingkat_ids))
            || !empty($this->normalizeTargetIds($this->target_kelas_ids));
    }

    /**
     * Give targeted class/level schemas precedence over generic ones.
     */
    public function getTargetEducationPriorityBonus(User $user): int
    {
        if (!$this->matchesTargetEducationGroup($user)) {
            return 0;
        }

        $bonus = 0;

        if (!empty($this->normalizeTargetIds($this->target_tingkat_ids))) {
            $bonus += 40;
        }

        if (!empty($this->normalizeTargetIds($this->target_kelas_ids))) {
            $bonus += 80;
        }

        return $bonus;
    }

    /**
     * Get effective radius for attendance validation
     * Uses hierarchy: Location radius > Global default (100m).
     * Notes:
     * - radius_absensi on schema is kept for backward compatibility in storage,
     *   but it is no longer used as runtime geofence source-of-truth.
     */
    public function getEffectiveRadius($locationId = null): int
    {
        // 1. Location-specific radius (source-of-truth)
        if ($locationId) {
            $location = \App\Models\LokasiGps::find($locationId);
            if ($location && $location->radius > 0) {
                return $location->radius;
            }
        }

        // 2. Global default radius
        return 100;
    }

    /**
     * Get effective GPS accuracy requirement
     */
    public function getEffectiveGpsAccuracy(): int
    {
        return $this->gps_accuracy ?: 20;
    }

    /**
     * Get allowed GPS locations for this schema
     */
    public function getAllowedLocations()
    {
        if (!$this->wajib_gps) {
            return collect(); // No GPS required, return empty collection
        }

        if (empty($this->lokasi_gps_ids)) {
            // If no specific locations assigned, return all active locations
            return \App\Models\LokasiGps::active()->get();
        }

        // Return only assigned locations
        return \App\Models\LokasiGps::whereIn('id', $this->lokasi_gps_ids)
            ->where('is_active', true)
            ->get();
    }

    /**
     * Check if user can attend at specific location
     */
    public function canAttendAtLocation($userLatitude, $userLongitude, $locationId = null): array
    {
        if (!$this->wajib_gps) {
            return [
                'can_attend' => true,
                'reason' => 'GPS not required for this schema',
                'distance' => null,
                'location' => null
            ];
        }

        $allowedLocations = $this->getAllowedLocations();

        if ($allowedLocations->isEmpty()) {
            return [
                'can_attend' => false,
                'reason' => 'No GPS locations configured for this schema',
                'distance' => null,
                'location' => null
            ];
        }

        $nearestLocation = null;
        $nearestDistance = PHP_FLOAT_MAX;
        $nearestEvaluation = null;

        foreach ($allowedLocations as $location) {
            $evaluation = $location->evaluateCoordinate((float) $userLatitude, (float) $userLongitude);
            $distance = (float) ($evaluation['distance_to_area'] ?? PHP_FLOAT_MAX);
            $effectiveRadius = $this->getEffectiveRadius($location->id);

            if ($evaluation['inside'] ?? false) {
                return [
                    'can_attend' => true,
                    'reason' => 'Within allowed area',
                    'distance' => round($distance, 2),
                    'distance_to_boundary' => round((float) ($evaluation['distance_to_boundary'] ?? $distance), 2),
                    'distance_to_center' => $evaluation['distance_to_center'] ?? null,
                    'location' => $location,
                    'effective_radius' => $effectiveRadius,
                    'geofence_type' => $location->getNormalizedGeofenceType(),
                ];
            }

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestLocation = $location;
                $nearestEvaluation = $evaluation;
            }
        }

        return [
            'can_attend' => false,
            'reason' => 'Outside allowed area',
            'distance' => round($nearestDistance, 2),
            'distance_to_boundary' => round((float) ($nearestEvaluation['distance_to_boundary'] ?? $nearestDistance), 2),
            'distance_to_center' => $nearestEvaluation['distance_to_center'] ?? null,
            'location' => $nearestLocation,
            'effective_radius' => $this->getEffectiveRadius($nearestLocation?->id),
            'required_distance' => $nearestDistance,
            'geofence_type' => $nearestLocation?->getNormalizedGeofenceType(),
        ];
    }

    /**
     * Get schema configuration for mobile app
     */
    public function getMobileConfig(?User $user = null): array
    {
        $workingHours = $this->getEffectiveWorkingHours($user);
        $allowedLocations = $this->getAllowedLocations();

        return [
            'schema_id' => $this->id,
            'schema_name' => $this->schema_name,
            'schema_type' => $this->schema_type,
            'is_mandatory' => $this->is_mandatory,
            'working_hours' => $workingHours,
            'requirements' => [
                'wajib_gps' => $this->wajib_gps,
                'wajib_foto' => $this->wajib_foto,
                'face_verification_enabled' => $this->isFaceVerificationEnabled(),
                'gps_accuracy' => $this->getEffectiveGpsAccuracy(),
                'gps_accuracy_grace' => (float) config('attendance.gps.accuracy_grace_meters', 0),
            ],
            'locations' => $allowedLocations->map(function ($location) {
                return [
                    'id' => $location->id,
                    'nama_lokasi' => $location->nama_lokasi,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'radius' => $this->getEffectiveRadius($location->id),
                    'geofence_type' => $location->getNormalizedGeofenceType(),
                    'geofence_geojson' => $location->geofence_geojson,
                    'alamat' => $location->alamat ?? $location->deskripsi,
                ];
            }),
            'hari_kerja' => $this->hari_kerja,
            'version' => $this->version
        ];
    }

    public function isFaceVerificationEnabled(): bool
    {
        if ($this->face_verification_enabled !== null) {
            return (bool) $this->face_verification_enabled;
        }

        return (bool) data_get(
            app(\App\Services\AttendanceRuntimeConfigService::class)->getFaceVerificationPolicyConfig(),
            'enabled',
            true
        );
    }

    /**
     * Check if target role of this schema points to student role.
     */
    private function isTargetingStudentRole(): bool
    {
        return RoleNames::normalize($this->target_role) === RoleNames::SISWA;
    }

    /**
     * Normalize stored JSON/array target IDs to unique integer list.
     *
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizeTargetIds($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = array_map(static fn($id) => (int) $id, $value);
        $ids = array_filter($ids, static fn($id) => $id > 0);

        return array_values(array_unique($ids));
    }
}
