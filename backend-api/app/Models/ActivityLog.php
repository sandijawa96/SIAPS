<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'log_name',
        'description',
        'subject_type',
        'subject_id',
        'causer_type',
        'causer_id',
        'properties',
        'event',
        'batch_uuid',
        'module',
        'level',
        'ip_address',
        'user_agent',
        'user_id',
        'action',
        'notes',
        'old_values',
        'new_values',
        'metadata',
        'additional_data',
        'device_info',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'properties' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Backward-compatible alias for old callers/frontend.
     */
    protected $appends = [
        'user_id',
        'action',
        'notes',
        'old_values',
        'new_values',
        'metadata',
        'additional_data',
        'device_info',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'causer_id');
    }

    public function subject()
    {
        return $this->morphTo();
    }

    public function scopeFromUser($query, $userId)
    {
        return $query->where('causer_id', $userId);
    }

    public function scopeWithAction($query, $action)
    {
        return $query->where('event', $action);
    }

    public function scopeInModule($query, $module)
    {
        return $query->where('module', $module);
    }

    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfWeek(),
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereBetween('created_at', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        ]);
    }

    public function getChangesAttribute()
    {
        $old = $this->old_values;
        $new = $this->new_values;
        if (!is_array($old) || !is_array($new)) {
            return null;
        }

        $changes = [];
        foreach ($new as $key => $value) {
            if (!array_key_exists($key, $old) || $old[$key] !== $value) {
                $changes[$key] = [
                    'old' => $old[$key] ?? null,
                    'new' => $value,
                ];
            }
        }

        return $changes;
    }

    public function getFormattedDateAttribute(): string
    {
        return $this->created_at?->format('Y-m-d H:i:s') ?? '-';
    }

    public function getTimeAgoAttribute(): ?string
    {
        return $this->created_at?->diffForHumans();
    }

    public function getBrowserAttribute(): string
    {
        return $this->parseUserAgent()['browser'] ?? 'Unknown';
    }

    public function getPlatformAttribute(): string
    {
        return $this->parseUserAgent()['platform'] ?? 'Unknown';
    }

    public function getDeviceAttribute(): string
    {
        return $this->parseUserAgent()['device'] ?? 'Unknown';
    }

    public function getUserIdAttribute(): ?int
    {
        return $this->attributes['causer_id'] ?? null;
    }

    public function setUserIdAttribute($value): void
    {
        $this->attributes['causer_id'] = $value;
    }

    public function getActionAttribute(): ?string
    {
        return $this->attributes['event'] ?? null;
    }

    public function setActionAttribute($value): void
    {
        $this->attributes['event'] = $value;
    }

    public function getNotesAttribute(): ?string
    {
        return $this->attributes['description'] ?? null;
    }

    public function setNotesAttribute($value): void
    {
        $this->attributes['description'] = $value;
    }

    public function getOldValuesAttribute(): ?array
    {
        return $this->getPropertiesValue('old');
    }

    public function setOldValuesAttribute($value): void
    {
        $this->mergeProperties(['old' => $value]);
    }

    public function getNewValuesAttribute(): ?array
    {
        return $this->getPropertiesValue('new');
    }

    public function setNewValuesAttribute($value): void
    {
        $this->mergeProperties(['new' => $value]);
    }

    public function getMetadataAttribute(): ?array
    {
        return $this->getPropertiesValue('metadata');
    }

    public function setMetadataAttribute($value): void
    {
        $this->mergeProperties(['metadata' => $value]);
    }

    public function getAdditionalDataAttribute(): ?array
    {
        return $this->getPropertiesValue('additional_data');
    }

    public function setAdditionalDataAttribute($value): void
    {
        $this->mergeProperties(['additional_data' => $value]);
    }

    public function getDeviceInfoAttribute(): ?array
    {
        return $this->getPropertiesValue('device_info');
    }

    public function setDeviceInfoAttribute($value): void
    {
        $this->mergeProperties(['device_info' => $value]);
    }

    protected function parseUserAgent(): array
    {
        if (!$this->user_agent) {
            return [];
        }

        $result = [];
        if (preg_match('/(firefox|msie|chrome|safari|opera|edge(?=\/))\/?\s*(\d+)/i', $this->user_agent, $matches)) {
            $result['browser'] = $matches[1];
        }

        if (preg_match('/windows|macintosh|linux|android|iphone|ipad/i', $this->user_agent, $matches)) {
            $result['platform'] = ucfirst($matches[0]);
        }

        if (preg_match('/(mobile|tablet)/i', $this->user_agent, $matches)) {
            $result['device'] = ucfirst($matches[1]);
        } else {
            $result['device'] = 'Desktop';
        }

        return $result;
    }

    public static function log($action, $module, $description = null, $metadata = null)
    {
        return static::create([
            'causer_id' => Auth::check() ? Auth::id() : null,
            'causer_type' => Auth::check() ? User::class : null,
            'event' => $action,
            'log_name' => $module,
            'module' => $module,
            'description' => $description ?? (string) $action,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'properties' => [
                'metadata' => $metadata,
                'old' => $metadata['old'] ?? null,
                'new' => $metadata['new'] ?? null,
                'device_info' => $metadata['device_info'] ?? null,
            ],
        ]);
    }

    public static function logModelEvent($model, $action, $description = null, $oldValues = null, $newValues = null)
    {
        $module = strtolower(class_basename($model));

        return static::create([
            'causer_id' => Auth::check() ? Auth::id() : null,
            'causer_type' => Auth::check() ? User::class : null,
            'event' => $action,
            'log_name' => $module,
            'module' => $module,
            'description' => $description ?? "Model {$module} {$action}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'subject_type' => get_class($model),
            'subject_id' => $model->getKey(),
            'properties' => [
                'old' => $oldValues,
                'new' => $newValues,
            ],
        ]);
    }

    public static function logAuth($action, $user, $description = null, $metadata = null)
    {
        return static::create([
            'causer_id' => $user->getKey(),
            'causer_type' => get_class($user),
            'event' => $action,
            'log_name' => 'auth',
            'module' => 'auth',
            'description' => $description ?? "User {$action}",
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'properties' => [
                'metadata' => $metadata,
                'device_info' => $metadata['device_info'] ?? null,
            ],
        ]);
    }

    public static function logSystem($action, $description, $metadata = null)
    {
        return static::create([
            'causer_id' => Auth::check() ? Auth::id() : null,
            'causer_type' => Auth::check() ? User::class : null,
            'event' => $action,
            'log_name' => 'system',
            'module' => 'system',
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'properties' => [
                'metadata' => $metadata,
                'device_info' => $metadata['device_info'] ?? null,
            ],
        ]);
    }

    private function getPropertiesValue(string $key): ?array
    {
        $properties = $this->properties;
        if (!is_array($properties)) {
            return null;
        }

        $value = $properties[$key] ?? null;
        return is_array($value) ? $value : null;
    }

    private function mergeProperties(array $values): void
    {
        $current = $this->properties;
        if (!is_array($current)) {
            $current = [];
        }

        foreach ($values as $key => $value) {
            $current[$key] = $value;
        }

        $this->attributes['properties'] = json_encode($current);
    }
}
