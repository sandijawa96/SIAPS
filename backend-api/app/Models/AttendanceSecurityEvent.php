<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class AttendanceSecurityEvent extends Model
{
    protected $fillable = [
        'user_id',
        'attendance_id',
        'kelas_id',
        'category',
        'event_key',
        'severity',
        'status',
        'attempt_type',
        'event_date',
        'latitude',
        'longitude',
        'accuracy',
        'distance_meters',
        'device_id',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'event_date' => 'date:Y-m-d',
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'distance_meters' => 'float',
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

    public static function record(array $payload): ?self
    {
        try {
            return self::create([
                'user_id' => $payload['user_id'] ?? null,
                'attendance_id' => $payload['attendance_id'] ?? null,
                'kelas_id' => $payload['kelas_id'] ?? null,
                'category' => $payload['category'] ?? 'attendance_security',
                'event_key' => $payload['event_key'] ?? 'unknown_security_event',
                'severity' => $payload['severity'] ?? 'medium',
                'status' => $payload['status'] ?? 'flagged',
                'attempt_type' => $payload['attempt_type'] ?? null,
                'event_date' => $payload['event_date'] ?? now()->toDateString(),
                'latitude' => $payload['latitude'] ?? null,
                'longitude' => $payload['longitude'] ?? null,
                'accuracy' => $payload['accuracy'] ?? null,
                'distance_meters' => $payload['distance_meters'] ?? null,
                'device_id' => $payload['device_id'] ?? null,
                'ip_address' => $payload['ip_address'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist attendance security event', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return null;
        }
    }

    public static function labelForEventKey(string $eventKey): string
    {
        return match ($eventKey) {
            'mock_location_detected' => 'Mock location / Fake GPS terdeteksi',
            'developer_options_enabled' => 'Developer options aktif',
            'root_or_jailbreak_detected' => 'Root / jailbreak terdeteksi',
            'adb_or_usb_debugging_enabled' => 'ADB / USB debugging aktif',
            'emulator_detected' => 'Perangkat terindikasi emulator',
            'app_clone_detected' => 'Aplikasi clone / dual app terdeteksi',
            'app_tampering_detected' => 'Integritas aplikasi bermasalah',
            'instrumentation_detected' => 'Frida / Xposed / hooking terdeteksi',
            'signature_mismatch_detected' => 'Signature / package aplikasi tidak sesuai',
            'magisk_risk_detected' => 'Risiko Magisk terdeteksi',
            'suspicious_device_state_detected' => 'Status perangkat mencurigakan',
            'device_lock_violation' => 'Perangkat tidak sesuai dengan device terdaftar',
            'device_id_missing_on_locked_account' => 'Device ID tidak terkirim pada akun terikat perangkat',
            'mobile_app_only_violation' => 'Percobaan absensi dari web/browser',
            'outside_geofence' => 'Lokasi di luar area absensi',
            'gps_accuracy_low' => 'Akurasi GPS terlalu rendah',
            default => 'Insiden keamanan absensi',
        };
    }

    public static function labelForSeverity(string $severity): string
    {
        return match (strtolower(trim($severity))) {
            'critical' => 'Kritis',
            'high' => 'Tinggi',
            'medium' => 'Sedang',
            'low' => 'Rendah',
            default => 'Perlu ditinjau',
        };
    }

    public static function labelForStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'blocked' => 'Diblokir',
            'flagged' => 'Ditandai untuk evaluasi',
            'allowed' => 'Diizinkan',
            default => 'Tercatat',
        };
    }

    public static function labelForStage(?string $stage): string
    {
        return match (strtolower(trim((string) $stage))) {
            'attendance_precheck' => 'Pra-cek aplikasi',
            'attendance_submit' => 'Presensi / submit',
            default => 'Tahap tidak diketahui',
        };
    }

    public function scopeWhereIssueKey(Builder $query, ?string $issueKey): Builder
    {
        $issueKey = trim((string) $issueKey);
        if ($issueKey === '') {
            return $query;
        }

        return $query->where(function (Builder $issueQuery) use ($issueKey): void {
            $issueQuery
                ->where('event_key', $issueKey)
                ->orWhereJsonContains('metadata->issue_keys', $issueKey);
        });
    }

    public function scopeWhereStage(Builder $query, ?string $stage): Builder
    {
        $stage = trim((string) $stage);
        if ($stage === '') {
            return $query;
        }

        return $query->where('metadata->stage', $stage);
    }

    public static function severityRank(?string $severity): int
    {
        return match (strtolower(trim((string) $severity))) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }

    public function issueRows(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $issues = $metadata['issues'] ?? null;

        if (is_array($issues) && $issues !== []) {
            $normalized = array_values(array_filter(array_map(
                static fn($issue): ?array => self::normalizeIssueRow($issue),
                $issues
            )));

            if ($normalized !== []) {
                return $normalized;
            }
        }

        return array_values(array_filter([
            self::normalizeIssueRow([
                'event_key' => $this->event_key,
                'label' => $metadata['issue_label'] ?? self::labelForEventKey((string) $this->event_key),
                'message' => $metadata['message'] ?? null,
                'severity' => $this->severity,
                'category' => $this->category,
                'metadata' => is_array($metadata['issue_evidence'] ?? null) ? $metadata['issue_evidence'] : [],
            ]),
        ]));
    }

    public function issueKeys(): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn(array $issue): ?string => isset($issue['event_key']) ? (string) $issue['event_key'] : null,
            $this->issueRows()
        ))));
    }

    public function hasIssueKey(string $eventKey): bool
    {
        return in_array($eventKey, $this->issueKeys(), true);
    }

    public function occurrenceCount(): int
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        return max(1, (int) ($metadata['occurrence_count'] ?? 1));
    }

    private static function normalizeIssueRow($issue): ?array
    {
        if (!is_array($issue)) {
            return null;
        }

        $eventKey = trim((string) ($issue['event_key'] ?? ''));
        if ($eventKey === '') {
            return null;
        }

        $severity = strtolower(trim((string) ($issue['severity'] ?? 'medium')));
        if (!in_array($severity, ['low', 'medium', 'high', 'critical'], true)) {
            $severity = 'medium';
        }

        return [
            'event_key' => $eventKey,
            'label' => $issue['label'] ?? self::labelForEventKey($eventKey),
            'message' => $issue['message'] ?? null,
            'severity' => $severity,
            'severity_label' => self::labelForSeverity($severity),
            'category' => $issue['category'] ?? 'attendance_security',
            'metadata' => is_array($issue['metadata'] ?? null) ? $issue['metadata'] : [],
        ];
    }

    public function toReportArray(): array
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $kelasLabel = $this->kelas?->nama_lengkap ?: ($metadata['kelas_label_snapshot'] ?? null);
        $studentName = $this->user?->nama_lengkap ?: ($metadata['student_name_snapshot'] ?? null);
        $studentIdentifier = $this->user?->nisn
            ?: $this->user?->nis
            ?: $this->user?->username
            ?: ($metadata['student_identifier_snapshot'] ?? null);
        $stage = $metadata['stage'] ?? null;
        $noticeBox = is_array($metadata['notice_box'] ?? null) ? $metadata['notice_box'] : null;
        $issues = $this->issueRows();
        $issueKeys = $this->issueKeys();
        $occurrenceCount = $this->occurrenceCount();

        return [
            'id' => (int) $this->id,
            'category' => $this->category,
            'event_key' => $this->event_key,
            'event_label' => self::labelForEventKey((string) $this->event_key),
            'severity' => $this->severity,
            'severity_label' => self::labelForSeverity((string) $this->severity),
            'status' => $this->status,
            'status_label' => self::labelForStatus((string) $this->status),
            'stage' => $stage,
            'stage_label' => self::labelForStage($stage),
            'attempt_type' => $this->attempt_type,
            'event_date' => $this->event_date?->toDateString(),
            'student' => [
                'user_id' => $this->user_id ? (int) $this->user_id : null,
                'name' => $studentName,
                'identifier' => $studentIdentifier,
                'nis' => $this->user?->nis,
                'nisn' => $this->user?->nisn,
            ],
            'kelas' => [
                'id' => $this->kelas_id ? (int) $this->kelas_id : null,
                'name' => $kelasLabel,
            ],
            'attendance_id' => $this->attendance_id ? (int) $this->attendance_id : null,
            'device_id' => $this->device_id,
            'ip_address' => $this->ip_address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'accuracy' => $this->accuracy,
            'distance_meters' => $this->distance_meters,
            'message' => $metadata['message'] ?? null,
            'issue_label' => $metadata['issue_label'] ?? null,
            'issues' => $issues,
            'issue_keys' => $issueKeys,
            'issues_count' => count($issues),
            'occurrence_count' => $occurrenceCount,
            'first_seen_at' => $metadata['first_seen_at'] ?? $this->created_at?->toIso8601String(),
            'last_seen_at' => $metadata['last_seen_at'] ?? $this->updated_at?->toIso8601String(),
            'notice_box' => $noticeBox,
            'metadata' => $metadata,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
