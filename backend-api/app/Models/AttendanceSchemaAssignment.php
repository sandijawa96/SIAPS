<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class AttendanceSchemaAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_setting_id',
        'start_date',
        'end_date',
        'is_active',
        'notes',
        'assigned_by',
        'assignment_type'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean'
    ];

    /**
     * Relationship with User
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship with AttendanceSchema
     */
    public function schema(): BelongsTo
    {
        return $this->belongsTo(AttendanceSchema::class, 'attendance_setting_id');
    }

    /**
     * Relationship with User who assigned this schema
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Scope for active assignments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for current assignments (within date range)
     */
    public function scopeCurrent($query, $date = null)
    {
        $date = $date ?: now()->toDateString();

        return $query->where('is_active', true)
            ->whereDate('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhereDate('end_date', '>=', $date);
            });
    }

    /**
     * Scope for specific user
     */
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if assignment is currently active
     */
    public function isCurrentlyActive($date = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $date = $date ?: now()->toDateString();
        $startDate = Carbon::parse($this->start_date)->toDateString();
        $endDate = $this->end_date ? Carbon::parse($this->end_date)->toDateString() : null;

        return $startDate <= $date && ($endDate === null || $endDate >= $date);
    }

    /**
     * Get assignment duration in days
     */
    public function getDurationInDays(): ?int
    {
        if (!$this->end_date) {
            return null; // Unlimited
        }

        return Carbon::parse($this->start_date)->diffInDays(Carbon::parse($this->end_date)) + 1;
    }

    /**
     * Check for overlapping assignments
     */
    public static function hasOverlappingAssignment($userId, $startDate, $endDate = null, $excludeId = null): bool
    {
        $query = self::where('user_id', $userId)
            ->where('is_active', true);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        // Check for overlaps
        $query->where(function ($q) use ($startDate, $endDate) {
            $q->where(function ($subQ) use ($startDate, $endDate) {
                // Case 1: New assignment starts within existing assignment
                $subQ->whereDate('start_date', '<=', $startDate)
                    ->where(function ($dateQ) use ($startDate) {
                        $dateQ->whereNull('end_date')
                            ->orWhereDate('end_date', '>=', $startDate);
                    });
            })->orWhere(function ($subQ) use ($startDate, $endDate) {
                // Case 2: New assignment ends within existing assignment
                if ($endDate) {
                    $subQ->whereDate('start_date', '<=', $endDate)
                        ->where(function ($dateQ) use ($endDate) {
                            $dateQ->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $endDate);
                        });
                }
            })->orWhere(function ($subQ) use ($startDate, $endDate) {
                // Case 3: New assignment encompasses existing assignment
                if ($endDate) {
                    $subQ->whereDate('start_date', '>=', $startDate)
                        ->whereDate('start_date', '<=', $endDate);
                }
            });
        });

        return $query->exists();
    }

    /**
     * Deactivate assignment
     */
    public function deactivate($reason = null): bool
    {
        $this->is_active = false;
        $this->notes = $this->notes . ($reason ? "\nDeactivated: " . $reason : "\nDeactivated");

        return $this->save();
    }

    /**
     * Extend assignment end date
     */
    public function extend($newEndDate, $reason = null): bool
    {
        $this->end_date = $newEndDate;
        $this->notes = $this->notes . ($reason ? "\nExtended: " . $reason : "\nExtended to " . $newEndDate);

        return $this->save();
    }
}
