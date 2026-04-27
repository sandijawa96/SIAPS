<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AttendanceSettingsLog extends Model
{
    use HasFactory;

    protected $table = 'attendance_settings_logs';

    protected $fillable = [
        'settings_type',
        'target_id',
        'target_type',
        'old_settings',
        'new_settings',
        'changed_by',
        'change_reason',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'old_settings' => 'json',
        'new_settings' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Relasi dengan user yang melakukan perubahan
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Get target model (polymorphic)
     */
    public function target()
    {
        return $this->morphTo();
    }

    /**
     * Scope untuk filter berdasarkan tipe perubahan
     */
    public function scopeByType($query, $type)
    {
        return $query->where('settings_type', $type);
    }

    /**
     * Scope untuk filter berdasarkan user
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('changed_by', $userId);
    }

    /**
     * Scope untuk filter berdasarkan tanggal
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get changes summary
     */
    public function getChangesSummaryAttribute()
    {
        $oldSettings = $this->old_settings ?? [];
        $newSettings = $this->new_settings ?? [];

        $changes = [];

        foreach ($newSettings as $key => $newValue) {
            $oldValue = $oldSettings[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'from' => $oldValue,
                    'to' => $newValue
                ];
            }
        }

        return $changes;
    }

    /**
     * Log settings change
     */
    public static function logChange($settingsType, $targetId, $targetType, $oldSettings, $newSettings, $reason = null)
    {
        return self::create([
            'settings_type' => $settingsType,
            'target_id' => $targetId,
            'target_type' => $targetType,
            'old_settings' => $oldSettings,
            'new_settings' => $newSettings,
            'changed_by' => auth()->check() ? auth()->id() : null,
            'change_reason' => $reason,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ]);
    }
}
