<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceFraudFlag extends Model
{
    protected $fillable = [
        'assessment_id',
        'attendance_id',
        'user_id',
        'flag_key',
        'category',
        'severity',
        'score',
        'blocking_recommended',
        'label',
        'reason',
        'evidence',
    ];

    protected $casts = [
        'assessment_id' => 'integer',
        'attendance_id' => 'integer',
        'user_id' => 'integer',
        'score' => 'integer',
        'blocking_recommended' => 'boolean',
        'evidence' => 'array',
    ];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(AttendanceFraudAssessment::class, 'assessment_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Absensi::class, 'attendance_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function toMonitoringArray(): array
    {
        $evidence = is_array($this->evidence) ? $this->evidence : [];

        return [
            'id' => (int) $this->id,
            'flag_key' => $this->flag_key,
            'category' => $this->category,
            'severity' => $this->severity,
            'score' => (int) $this->score,
            'blocking_recommended' => (bool) $this->blocking_recommended,
            'label' => $this->label,
            'reason' => $this->reason,
            'occurrence_count' => max(1, (int) ($evidence['occurrence_count'] ?? 1)),
            'evidence' => $evidence,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
