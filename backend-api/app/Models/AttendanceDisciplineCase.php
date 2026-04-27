<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDisciplineCase extends Model
{
    use HasFactory;

    public const STATUS_READY_FOR_PARENT_BROADCAST = 'ready_for_parent_broadcast';
    public const STATUS_PARENT_BROADCAST_SENT = 'parent_broadcast_sent';

    protected $fillable = [
        'user_id',
        'kelas_id',
        'rule_key',
        'status',
        'period_type',
        'period_key',
        'period_label',
        'semester',
        'tahun_ajaran_id',
        'tahun_ajaran_ref',
        'metric_value',
        'metric_limit',
        'broadcast_campaign_id',
        'first_triggered_at',
        'last_triggered_at',
        'payload',
    ];

    protected $casts = [
        'metric_value' => 'integer',
        'metric_limit' => 'integer',
        'period_type' => 'string',
        'period_key' => 'string',
        'period_label' => 'string',
        'first_triggered_at' => 'datetime',
        'last_triggered_at' => 'datetime',
        'payload' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    public function broadcastCampaign()
    {
        return $this->belongsTo(BroadcastCampaign::class, 'broadcast_campaign_id');
    }
}
