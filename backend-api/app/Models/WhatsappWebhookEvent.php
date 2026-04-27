<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_type',
        'status',
        'message_id',
        'device',
        'from_number',
        'matched_notification_id',
        'delivery_marked',
        'payload',
        'headers',
    ];

    protected $casts = [
        'delivery_marked' => 'boolean',
        'payload' => 'array',
        'headers' => 'array',
    ];

    public function matchedNotification()
    {
        return $this->belongsTo(WhatsappGateway::class, 'matched_notification_id');
    }
}
