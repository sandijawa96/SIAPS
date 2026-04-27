<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SbtExamSession extends Model
{
    protected $fillable = [
        'session_code',
        'app_session_id',
        'participant_identifier',
        'student_name',
        'device_id',
        'device_name',
        'app_version',
        'platform',
        'exam_url',
        'status',
        'started_at',
        'last_heartbeat_at',
        'finished_at',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'last_heartbeat_at' => 'datetime',
        'finished_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(SbtSecurityEvent::class, 'sbt_exam_session_id');
    }
}
