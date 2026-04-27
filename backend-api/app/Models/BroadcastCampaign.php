<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BroadcastCampaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'type',
        'message_category',
        'channels',
        'audience',
        'popup',
        'whatsapp',
        'email',
        'status',
        'display_start_at',
        'display_end_at',
        'expires_at',
        'pinned_at',
        'priority',
        'total_target',
        'sent_count',
        'failed_count',
        'summary',
        'created_by',
        'sent_at',
    ];

    protected $casts = [
        'channels' => 'array',
        'audience' => 'array',
        'popup' => 'array',
        'whatsapp' => 'array',
        'email' => 'array',
        'summary' => 'array',
        'sent_at' => 'datetime',
        'display_start_at' => 'datetime',
        'display_end_at' => 'datetime',
        'expires_at' => 'datetime',
        'pinned_at' => 'datetime',
        'priority' => 'integer',
        'total_target' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
