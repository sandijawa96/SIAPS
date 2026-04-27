<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSecurityCaseActivity extends Model
{
    protected $fillable = [
        'case_id',
        'actor_id',
        'activity_type',
        'description',
        'before_state',
        'after_state',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'metadata' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(AttendanceSecurityCase::class, 'case_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function toArrayPayload(): array
    {
        return [
            'id' => (int) $this->id,
            'activity_type' => $this->activity_type,
            'description' => $this->description,
            'before_state' => is_array($this->before_state) ? $this->before_state : null,
            'after_state' => is_array($this->after_state) ? $this->after_state : null,
            'metadata' => is_array($this->metadata) ? $this->metadata : null,
            'actor' => $this->actor ? [
                'id' => (int) $this->actor->id,
                'name' => $this->actor->nama_lengkap,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
