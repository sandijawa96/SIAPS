<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceSecurityCase extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'case_number',
        'user_id',
        'kelas_id',
        'opened_by',
        'assigned_to',
        'resolved_by',
        'case_date',
        'status',
        'priority',
        'summary',
        'student_statement',
        'staff_notes',
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'case_date' => 'date:Y-m-d',
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AttendanceSecurityCaseItem::class, 'case_id');
    }

    public function evidence(): HasMany
    {
        return $this->hasMany(AttendanceSecurityCaseEvidence::class, 'case_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(AttendanceSecurityCaseActivity::class, 'case_id');
    }

    public static function labelForStatus(?string $status): string
    {
        return match (strtolower(trim((string) $status))) {
            'resolved' => 'Selesai',
            'escalated' => 'Dieskalasi',
            'reopened' => 'Dibuka ulang',
            default => 'Terbuka',
        };
    }

    public static function labelForPriority(?string $priority): string
    {
        return match (strtolower(trim((string) $priority))) {
            'critical' => 'Kritis',
            'high' => 'Tinggi',
            'low' => 'Rendah',
            default => 'Sedang',
        };
    }

    public static function labelForResolution(?string $resolution): ?string
    {
        $resolution = strtolower(trim((string) $resolution));
        if ($resolution === '') {
            return null;
        }

        return match ($resolution) {
            'confirmed_violation' => 'Terbukti melanggar',
            'false_positive' => 'False positive',
            'student_guided' => 'Siswa dibina',
            'device_fixed' => 'Perangkat diperbaiki',
            'parent_notified' => 'Orang tua diberitahu',
            default => 'Ditindaklanjuti',
        };
    }

    public function isClosed(): bool
    {
        return in_array(strtolower((string) $this->status), ['resolved', 'escalated'], true);
    }

    public function toListArray(): array
    {
        $studentIdentifier = $this->user?->nisn
            ?: $this->user?->nis
            ?: $this->user?->username;

        return [
            'id' => (int) $this->id,
            'case_number' => $this->case_number,
            'case_date' => $this->case_date?->toDateString(),
            'status' => $this->status,
            'status_label' => self::labelForStatus($this->status),
            'priority' => $this->priority,
            'priority_label' => self::labelForPriority($this->priority),
            'summary' => $this->summary,
            'resolution' => $this->resolution,
            'resolution_label' => self::labelForResolution($this->resolution),
            'student' => [
                'user_id' => $this->user_id ? (int) $this->user_id : null,
                'name' => $this->user?->nama_lengkap,
                'identifier' => $studentIdentifier,
            ],
            'kelas' => [
                'id' => $this->kelas_id ? (int) $this->kelas_id : null,
                'name' => $this->kelas?->nama_lengkap,
            ],
            'opened_by' => $this->opener ? [
                'id' => (int) $this->opener->id,
                'name' => $this->opener->nama_lengkap,
            ] : null,
            'assigned_to' => $this->assignee ? [
                'id' => (int) $this->assignee->id,
                'name' => $this->assignee->nama_lengkap,
            ] : null,
            'items_count' => (int) ($this->items_count ?? ($this->relationLoaded('items') ? $this->items->count() : 0)),
            'evidence_count' => (int) ($this->evidence_count ?? ($this->relationLoaded('evidence') ? $this->evidence->count() : 0)),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public function toDetailArray(): array
    {
        return array_merge($this->toListArray(), [
            'student_statement' => $this->student_statement,
            'staff_notes' => $this->staff_notes,
            'resolved_by' => $this->resolver ? [
                'id' => (int) $this->resolver->id,
                'name' => $this->resolver->nama_lengkap,
            ] : null,
            'items' => $this->relationLoaded('items')
                ? $this->items->map(static fn(AttendanceSecurityCaseItem $item): array => $item->toArrayPayload())->values()->all()
                : [],
            'evidence' => $this->relationLoaded('evidence')
                ? $this->evidence->map(static fn(AttendanceSecurityCaseEvidence $evidence): array => $evidence->toArrayPayload())->values()->all()
                : [],
            'activities' => $this->relationLoaded('activities')
                ? $this->activities->map(static fn(AttendanceSecurityCaseActivity $activity): array => $activity->toArrayPayload())->values()->all()
                : [],
        ]);
    }
}
