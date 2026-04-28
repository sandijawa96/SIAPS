<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SbtSetting extends Model
{
    protected $fillable = [
        'enabled',
        'exam_url',
        'exam_host',
        'webview_user_agent',
        'security_mode',
        'supervisor_code_hash',
        'supervisor_code_updated_at',
        'minimum_app_version',
        'require_dnd',
        'require_screen_pinning',
        'require_overlay_protection',
        'ios_lock_on_background',
        'minimum_battery_level',
        'heartbeat_interval_seconds',
        'maintenance_enabled',
        'maintenance_message',
        'announcement',
        'config_version',
        'updated_by',
    ];

    protected $hidden = [
        'supervisor_code_hash',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'supervisor_code_updated_at' => 'datetime',
        'require_dnd' => 'boolean',
        'require_screen_pinning' => 'boolean',
        'require_overlay_protection' => 'boolean',
        'ios_lock_on_background' => 'boolean',
        'minimum_battery_level' => 'integer',
        'heartbeat_interval_seconds' => 'integer',
        'maintenance_enabled' => 'boolean',
        'config_version' => 'integer',
    ];

    public static function defaults(): array
    {
        return [
            'enabled' => true,
            'exam_url' => 'https://res.sman1sumbercirebon.sch.id',
            'exam_host' => 'res.sman1sumbercirebon.sch.id',
            'webview_user_agent' => 'SBT-SMANIS/1.0',
            'security_mode' => 'warning',
            'minimum_app_version' => null,
            'require_dnd' => false,
            'require_screen_pinning' => true,
            'require_overlay_protection' => true,
            'ios_lock_on_background' => true,
            'minimum_battery_level' => 20,
            'heartbeat_interval_seconds' => 30,
            'maintenance_enabled' => false,
            'maintenance_message' => null,
            'announcement' => null,
            'config_version' => 1,
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], self::defaults());
    }

    public function hasSupervisorCode(): bool
    {
        return filled($this->supervisor_code_hash);
    }

    public function requiresSupervisorCode(): bool
    {
        return in_array($this->security_mode, ['supervisor_code', 'locked'], true);
    }
}
