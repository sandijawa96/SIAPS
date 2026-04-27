<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

class AttendanceGovernanceLog extends Model
{
    protected $fillable = [
        'category',
        'action',
        'actor_user_id',
        'target_type',
        'target_id',
        'old_values',
        'new_values',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public static function record(array $payload): ?self
    {
        try {
            return self::create([
                'category' => $payload['category'] ?? 'attendance',
                'action' => $payload['action'] ?? 'updated',
                'actor_user_id' => $payload['actor_user_id'] ?? auth()->id(),
                'target_type' => $payload['target_type'] ?? null,
                'target_id' => $payload['target_id'] ?? null,
                'old_values' => $payload['old_values'] ?? null,
                'new_values' => $payload['new_values'] ?? null,
                'metadata' => $payload['metadata'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist attendance governance log', [
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);

            return null;
        }
    }
}
