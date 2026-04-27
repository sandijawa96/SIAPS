<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceToken extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'device_id',
        'device_name',
        'device_type',
        'push_token',
        'device_info',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'device_info' => 'array',
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if device is authorized for the user
     */
    public static function isDeviceAuthorized($userId, $deviceId)
    {
        return self::where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Register a new device for user
     */
    public static function registerDevice($userId, $deviceData)
    {
        $deviceId = (string) ($deviceData['device_id'] ?? '');
        if ($deviceId === '') {
            throw new \InvalidArgumentException('device_id is required');
        }

        $token = self::withTrashed()->where('device_id', $deviceId)->first();
        if (!$token instanceof self) {
            $token = new self([
                'device_id' => $deviceId,
            ]);
        }

        $token->fill([
            'user_id' => $userId,
            'device_name' => $deviceData['device_name'] ?? null,
            'device_type' => $deviceData['device_type'] ?? null,
            'push_token' => $deviceData['push_token'] ?? null,
            'device_info' => $deviceData['device_info'] ?? null,
            'is_active' => true,
            'last_used_at' => now(),
        ]);
        $token->deleted_at = null;
        $token->save();

        return $token;
    }

    /**
     * Update device last used timestamp
     */
    public function updateLastUsed()
    {
        $this->update(['last_used_at' => now()]);
    }
}
