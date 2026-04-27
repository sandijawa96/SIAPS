<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class AttendanceAuditLog extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'attendance_audit_logs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'attendance_id',
        'action_type',
        'performed_by',
        'performed_at',
        'reason',
        'old_values',
        'new_values',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'old_values' => 'json',
            'new_values' => 'json',
            'metadata' => 'json',
        ];
    }

    /**
     * Relationship with Absensi model
     */
    public function attendance()
    {
        return $this->belongsTo(Absensi::class, 'attendance_id');
    }

    /**
     * Relationship with User model (who performed the action)
     */
    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Scope for filtering by action type
     */
    public function scopeActionType($query, $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Scope for filtering by performer
     */
    public function scopePerformedBy($query, $userId)
    {
        return $query->where('performed_by', $userId);
    }

    /**
     * Scope for filtering by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('performed_at', [$startDate, $endDate]);
    }

    /**
     * Scope for recent logs
     */
    public function scopeRecent($query, $days = 7)
    {
        return $query->where('performed_at', '>=', Carbon::now()->subDays($days));
    }

    /**
     * Static method to create audit log
     */
    public static function createLog(
        int $attendanceId,
        string $actionType,
        int $performedBy,
        string $reason = null,
        array $oldValues = null,
        array $newValues = null,
        array $metadata = null
    ): self {
        return self::create([
            'attendance_id' => $attendanceId,
            'action_type' => $actionType,
            'performed_by' => $performedBy,
            'performed_at' => now(),
            'reason' => $reason,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get formatted action type for display
     */
    public function getFormattedActionTypeAttribute(): string
    {
        return match ($this->action_type) {
            'created' => 'Dibuat',
            'updated' => 'Diperbarui',
            'corrected' => 'Dikoreksi',
            'deleted' => 'Dihapus',
            default => ucfirst($this->action_type)
        };
    }

    /**
     * Get changes summary for display
     */
    public function getChangesSummaryAttribute(): array
    {
        if (!$this->old_values || !$this->new_values) {
            return [];
        }

        $changes = [];
        $oldValues = $this->old_values;
        $newValues = $this->new_values;

        foreach ($newValues as $key => $newValue) {
            $oldValue = $oldValues[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                    'field_name' => $this->getFieldDisplayName($key)
                ];
            }
        }

        return $changes;
    }

    /**
     * Get display name for field
     */
    private function getFieldDisplayName(string $field): string
    {
        return match ($field) {
            'tanggal' => 'Tanggal',
            'jam_masuk' => 'Jam Masuk',
            'jam_pulang' => 'Jam Pulang',
            'status' => 'Status',
            'keterangan' => 'Keterangan',
            'latitude_masuk' => 'Latitude Masuk',
            'longitude_masuk' => 'Longitude Masuk',
            'latitude_pulang' => 'Latitude Pulang',
            'longitude_pulang' => 'Longitude Pulang',
            'foto_masuk' => 'Foto Masuk',
            'foto_pulang' => 'Foto Pulang',
            default => ucfirst(str_replace('_', ' ', $field))
        };
    }

    /**
     * Get metadata value by key
     */
    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }
}
