<?php

namespace App\Traits;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait LogsActivity
{
    /**
     * Boot the trait.
     */
    protected static function bootLogsActivity()
    {
        // Log model events
        static::created(function (Model $model) {
            $model->logActivity('create');
        });

        static::updated(function (Model $model) {
            $model->logActivity('update');
        });

        static::deleted(function (Model $model) {
            $model->logActivity('delete');
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $model->logActivity('restore');
            });
        }
    }

    /**
     * Log an activity.
     */
    public function logActivity(string $action, ?string $description = null, ?array $metadata = null)
    {
        // Get the model name for the module
        $module = Str::snake(class_basename($this));

        // Get changed attributes for updates
        $changes = [];
        if ($action === 'update' && method_exists($this, 'getDirty')) {
            $changes = [
                'old' => array_intersect_key($this->getOriginal(), $this->getDirty()),
                'new' => $this->getDirty()
            ];
        }

        // Get model identifier
        $identifier = $this->getActivityLogIdentifier();

        // Build description if not provided
        if (!$description) {
            $description = $this->getActivityLogDescription($action, $identifier);
        }

        // Merge metadata
        $metadata = array_merge(
            $metadata ?? [],
            [
                'model' => get_class($this),
                'model_id' => $this->getKey(),
                'changes' => $changes,
                'identifier' => $identifier
            ]
        );

        // Create activity log
        return ActivityLog::create([
            'causer_id' => auth()->id(),
            'causer_type' => auth()->id() ? \App\Models\User::class : null,
            'event' => $action,
            'log_name' => $module,
            'module' => $module,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'properties' => $metadata
        ]);
    }

    /**
     * Get the identifier for activity log.
     */
    protected function getActivityLogIdentifier(): string
    {
        // Try common identifier fields
        $identifierFields = ['name', 'title', 'email', 'username', 'code', 'id'];
        
        foreach ($identifierFields as $field) {
            if (isset($this->attributes[$field])) {
                return $this->attributes[$field];
            }
        }

        // Fallback to model key
        return $this->getKey();
    }

    /**
     * Get the activity log description.
     */
    protected function getActivityLogDescription(string $action, string $identifier): string
    {
        $module = Str::title(Str::snake(class_basename($this), ' '));
        
        switch ($action) {
            case 'create':
                return "Created new {$module}: {$identifier}";
            case 'update':
                return "Updated {$module}: {$identifier}";
            case 'delete':
                return "Deleted {$module}: {$identifier}";
            case 'restore':
                return "Restored {$module}: {$identifier}";
            default:
                return "{$action} {$module}: {$identifier}";
        }
    }

    /**
     * Get activity logs for this model.
     */
    public function activities()
    {
        return ActivityLog::where('module', Str::snake(class_basename($this)))
            ->where('properties->model_id', $this->getKey())
            ->orderBy('created_at', 'desc');
    }

    /**
     * Log custom activity.
     */
    public function logCustomActivity(string $action, string $description, ?array $metadata = null)
    {
        return $this->logActivity($action, $description, $metadata);
    }

    /**
     * Get recent activities.
     */
    public function recentActivities($limit = 10)
    {
        return $this->activities()->limit($limit)->get();
    }

    /**
     * Get activities by date range.
     */
    public function activitiesByDateRange($startDate, $endDate)
    {
        return $this->activities()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
    }

    /**
     * Get activities by action.
     */
    public function activitiesByAction($action)
    {
        return $this->activities()
            ->where('event', $action)
            ->get();
    }

    /**
     * Get activities with changes.
     */
    public function activitiesWithChanges()
    {
        return $this->activities()
            ->whereNotNull('properties->changes')
            ->get();
    }

    /**
     * Get activities by user.
     */
    public function activitiesByUser($userId)
    {
        return $this->activities()
            ->where('causer_id', $userId)
            ->get();
    }

    /**
     * Get activity statistics.
     */
    public function activityStatistics()
    {
        $activities = $this->activities();

        return [
            'total' => $activities->count(),
            'by_action' => $activities->select('event', \DB::raw('count(*) as count'))
                ->groupBy('event')
                ->pluck('count', 'event'),
            'by_user' => $activities->select('causer_id', \DB::raw('count(*) as count'))
                ->groupBy('causer_id')
                ->pluck('count', 'causer_id'),
            'latest' => $activities->first(),
            'oldest' => $activities->orderBy('created_at', 'asc')->first()
        ];
    }
}
