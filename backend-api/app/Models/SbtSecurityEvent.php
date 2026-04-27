<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SbtSecurityEvent extends Model
{
    protected $fillable = [
        'sbt_exam_session_id',
        'app_session_id',
        'event_type',
        'severity',
        'message',
        'occurred_at',
        'app_version',
        'device_id',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(SbtExamSession::class, 'sbt_exam_session_id');
    }
}
