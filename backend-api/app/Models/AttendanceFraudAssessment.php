<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceFraudAssessment extends Model
{
    protected $fillable = [
        'user_id',
        'attendance_id',
        'kelas_id',
        'assessment_date',
        'source',
        'attempt_type',
        'rollout_mode',
        'validation_status',
        'risk_level',
        'risk_score',
        'fraud_flags_count',
        'decision_code',
        'decision_reason',
        'recommended_action',
        'is_blocking',
        'latitude',
        'longitude',
        'accuracy',
        'distance_meters',
        'device_id',
        'device_fingerprint',
        'ip_address',
        'request_nonce',
        'request_signature',
        'request_timestamp',
        'client_timestamp',
        'raw_payload',
        'normalized_payload',
        'metadata',
    ];

    protected $casts = [
        'assessment_date' => 'date:Y-m-d',
        'risk_score' => 'integer',
        'fraud_flags_count' => 'integer',
        'is_blocking' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'distance_meters' => 'float',
        'request_timestamp' => 'datetime',
        'client_timestamp' => 'datetime',
        'raw_payload' => 'array',
        'normalized_payload' => 'array',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Absensi::class, 'attendance_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function flags(): HasMany
    {
        return $this->hasMany(AttendanceFraudFlag::class, 'assessment_id');
    }

    public static function labelForValidationStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'warning' => 'Warning',
            default => 'Valid',
        };
    }

    public static function labelForRiskLevel(?string $level): string
    {
        return match (strtolower(trim((string) $level))) {
            'critical' => 'Kritis',
            'high' => 'Tinggi',
            'medium' => 'Sedang',
            default => 'Rendah',
        };
    }

    public static function labelForRolloutMode(?string $mode): string
    {
        return match (strtolower(trim((string) $mode))) {
            'logging_only' => 'Logging Only',
            default => 'Warning Only',
        };
    }

    public static function labelForSource(?string $source): string
    {
        return match (strtolower(trim((string) $source))) {
            'attendance_precheck' => 'Pra-cek aplikasi',
            'attendance_submit' => 'Presensi / submit',
            default => 'Tahap tidak diketahui',
        };
    }

    public function toMonitoringArray(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $normalizedValidationStatus = strtolower(trim((string) $this->validation_status)) === 'valid'
            ? 'valid'
            : 'warning';
        $noticeBoxes = array_values(array_filter([
            is_array($metadata['precheck_notice'] ?? null) ? $metadata['precheck_notice'] : null,
            is_array($metadata['submit_notice'] ?? null) ? $metadata['submit_notice'] : null,
        ]));
        $flags = $this->relationLoaded('flags')
            ? $this->flags->map(static fn(AttendanceFraudFlag $flag): array => $flag->toMonitoringArray())->values()->all()
            : [];
        $warningSummary = trim((string) ($this->decision_reason ?? ''));
        if ($warningSummary === '' && $flags !== []) {
            $warningSummary = collect($flags)
                ->pluck('label')
                ->filter()
                ->take(3)
                ->implode(', ');
        }
        $hasWarning = $normalizedValidationStatus === 'warning' || $flags !== [];

        return [
            'id' => (int) $this->id,
            'assessment_date' => $this->assessment_date?->toDateString(),
            'source' => $this->source,
            'source_label' => self::labelForSource($this->source),
            'attempt_type' => $this->attempt_type,
            'rollout_mode' => $this->rollout_mode,
            'rollout_mode_label' => self::labelForRolloutMode($this->rollout_mode),
            'validation_status' => $normalizedValidationStatus,
            'validation_status_label' => self::labelForValidationStatus($normalizedValidationStatus),
            'has_warning' => $hasWarning,
            'warning_summary' => $warningSummary !== '' ? $warningSummary : null,
            'risk_level' => 'low',
            'risk_level_label' => self::labelForRiskLevel('low'),
            'risk_score' => 0,
            'fraud_flags_count' => (int) $this->fraud_flags_count,
            'decision_code' => $this->decision_code,
            'decision_reason' => $warningSummary !== '' ? $warningSummary : $this->decision_reason,
            'recommended_action' => $this->recommended_action,
            'is_blocking' => false,
            'student' => [
                'user_id' => $this->user_id ? (int) $this->user_id : null,
                'name' => $this->user?->nama_lengkap ?: ($metadata['student_name_snapshot'] ?? null),
                'identifier' => $this->user?->nisn
                    ?: $this->user?->nis
                    ?: $this->user?->username
                    ?: ($metadata['student_identifier_snapshot'] ?? null),
            ],
            'kelas' => [
                'id' => $this->kelas_id ? (int) $this->kelas_id : null,
                'name' => $this->kelas?->nama_lengkap ?: ($metadata['kelas_label_snapshot'] ?? null),
            ],
            'attendance_id' => $this->attendance_id ? (int) $this->attendance_id : null,
            'device_id' => $this->device_id,
            'device_fingerprint' => $this->device_fingerprint,
            'ip_address' => $this->ip_address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'distance_meters' => $this->distance_meters,
            'request_nonce' => $this->request_nonce,
            'request_timestamp' => $this->request_timestamp?->toIso8601String(),
            'client_timestamp' => $this->client_timestamp?->toIso8601String(),
            'notice_boxes' => $noticeBoxes,
            'occurrence_count' => max(1, (int) ($metadata['occurrence_count'] ?? 1)),
            'first_seen_at' => $metadata['first_seen_at'] ?? $this->created_at?->toIso8601String(),
            'last_seen_at' => $metadata['last_seen_at'] ?? $this->updated_at?->toIso8601String(),
            'metadata' => $metadata,
            'fraud_flags' => array_map(
                static fn(array $flag): array => array_filter([
                    'flag_key' => $flag['flag_key'] ?? null,
                    'label' => $flag['label'] ?? null,
                    'severity' => $flag['severity'] ?? null,
                    'category' => $flag['category'] ?? null,
                    'reason' => $flag['reason'] ?? null,
                    'occurrence_count' => $flag['occurrence_count'] ?? null,
                ], static fn($value): bool => $value !== null && $value !== ''),
                $flags
            ),
            'flags' => $flags,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
