<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ManualAttendanceIncidentBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'status',
        'tanggal',
        'scope_type',
        'scope_payload',
        'attendance_status',
        'jam_masuk',
        'jam_pulang',
        'keterangan',
        'reason',
        'progress_percentage',
        'total_scope_users',
        'total_candidates',
        'processed_count',
        'created_count',
        'skipped_existing_count',
        'skipped_leave_count',
        'skipped_non_required_count',
        'skipped_non_working_count',
        'failed_count',
        'preview_summary',
        'sample_failures',
        'started_at',
        'completed_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'scope_payload' => 'array',
        'preview_summary' => 'array',
        'sample_failures' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items()
    {
        return $this->hasMany(ManualAttendanceIncidentBatchItem::class, 'batch_id');
    }
}
