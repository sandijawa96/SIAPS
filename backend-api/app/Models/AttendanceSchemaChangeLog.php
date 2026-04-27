<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSchemaChangeLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'attendance_setting_id',
        'action',
        'old_values',
        'new_values',
        'changed_by',
        'changed_at',
        'reason'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'changed_at' => 'datetime'
    ];

    public $timestamps = false; // We use changed_at instead

    /**
     * Relationship with AttendanceSchema
     */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(AttendanceSchema::class, 'attendance_setting_id');
    }

    /**
     * Relationship with User who made the change
     */
    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Scope for specific schema
     */
    public function scopeForSchema($query, $schemaId)
    {
        return $query->where('attendance_setting_id', $schemaId);
    }

    /**
     * Scope for specific action
     */
    public function scopeAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for recent changes
     */
    public function scopeRecent($query, $days = 30)
    {
        return $query->where('changed_at', '>=', now()->subDays($days));
    }

    /**
     * Create a change log entry
     */
    public static function logChange($schemaId, $action, $oldValues = null, $newValues = null, $changedBy = null, $reason = null): self
    {
        return self::create([
            'attendance_setting_id' => $schemaId,
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_by' => $changedBy ?: auth()->id(),
            'changed_at' => now(),
            'reason' => $reason
        ]);
    }

    /**
     * Get formatted change summary
     */
    public function getChangeSummary(): string
    {
        $summary = "Action: {$this->action}";

        if ($this->old_values && $this->new_values) {
            $changes = [];
            foreach ($this->new_values as $key => $newValue) {
                $oldValue = $this->old_values[$key] ?? 'null';
                if ($oldValue != $newValue) {
                    $changes[] = "{$key}: {$oldValue} → {$newValue}";
                }
            }

            if (!empty($changes)) {
                $summary .= "\nChanges: " . implode(', ', $changes);
            }
        }

        if ($this->reason) {
            $summary .= "\nReason: {$this->reason}";
        }

        return $summary;
    }

    /**
     * Get affected fields
     */
    public function getAffectedFields(): array
    {
        if (!$this->old_values || !$this->new_values) {
            return [];
        }

        $affected = [];
        foreach ($this->new_values as $key => $newValue) {
            $oldValue = $this->old_values[$key] ?? null;
            if ($oldValue != $newValue) {
                $affected[] = $key;
            }
        }

        return $affected;
    }

    /**
     * Check if specific field was changed
     */
    public function wasFieldChanged($field): bool
    {
        return in_array($field, $this->getAffectedFields());
    }

    /**
     * Get old value for specific field
     */
    public function getOldValue($field)
    {
        return $this->old_values[$field] ?? null;
    }

    /**
     * Get new value for specific field
     */
    public function getNewValue($field)
    {
        return $this->new_values[$field] ?? null;
    }
}
