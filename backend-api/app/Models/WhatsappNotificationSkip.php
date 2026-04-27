<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsappNotificationSkip extends Model
{
    use HasFactory;

    public const REASON_MISSING_PHONE = 'missing_phone_number';
    public const REASON_NOTIFICATIONS_DISABLED = 'notifications_disabled';
    public const REASON_MISSING_CONFIGURATION = 'missing_configuration';

    protected $fillable = [
        'type',
        'reason',
        'target_user_id',
        'phone_candidate',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}
