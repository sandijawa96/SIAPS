<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupLog extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'action',
        'filename',
        'type',
        'description',
        'user_id',
        'metadata',
        'status',
        'error_message'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the user that performed the backup action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include successful backups.
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope a query to only include failed backups.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include backups in progress.
     */
    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    /**
     * Scope a query to only include backups of a specific type.
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope a query to only include backups with a specific action.
     */
    public function scopeWithAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Get the backup duration in seconds from metadata.
     */
    public function getDurationAttribute()
    {
        return $this->metadata['duration'] ?? null;
    }

    /**
     * Get the backup size in bytes from metadata.
     */
    public function getSizeAttribute()
    {
        return $this->metadata['size'] ?? null;
    }

    /**
     * Get formatted backup size.
     */
    public function getFormattedSizeAttribute()
    {
        $size = $this->size;
        if (!$size) return null;

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Get the backup status with color code.
     */
    public function getStatusWithColorAttribute()
    {
        $colors = [
            'success' => 'green',
            'failed' => 'red',
            'in_progress' => 'yellow'
        ];

        return [
            'status' => $this->status,
            'color' => $colors[$this->status] ?? 'gray'
        ];
    }

    /**
     * Check if the backup was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if the backup failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Check if the backup is in progress.
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Update the backup status.
     */
    public function updateStatus(string $status, ?string $errorMessage = null): void
    {
        $this->update([
            'status' => $status,
            'error_message' => $errorMessage
        ]);
    }

    /**
     * Mark the backup as successful.
     */
    public function markAsSuccessful(): void
    {
        $this->updateStatus('success');
    }

    /**
     * Mark the backup as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->updateStatus('failed', $errorMessage);
    }

    /**
     * Mark the backup as in progress.
     */
    public function markAsInProgress(): void
    {
        $this->updateStatus('in_progress');
    }

    /**
     * Update backup metadata.
     */
    public function updateMetadata(array $metadata): void
    {
        $this->update([
            'metadata' => array_merge($this->metadata ?? [], $metadata)
        ]);
    }
}
