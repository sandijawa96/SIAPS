<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDisciplineAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'recipient_user_id',
        'notification_id',
        'whatsapp_notification_id',
        'rule_key',
        'audience',
        'period_type',
        'period_key',
        'period_label',
        'semester',
        'tahun_ajaran_id',
        'tahun_ajaran_ref',
        'triggered_at',
        'payload',
    ];

    protected $casts = [
        'triggered_at' => 'datetime',
        'period_type' => 'string',
        'period_key' => 'string',
        'period_label' => 'string',
        'payload' => 'array',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function notification()
    {
        return $this->belongsTo(Notification::class);
    }

    public function whatsappNotification()
    {
        return $this->belongsTo(WhatsappGateway::class, 'whatsapp_notification_id');
    }
}
