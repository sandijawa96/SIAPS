<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DapodikEntityMapping extends Model
{
    use HasFactory;

    protected $fillable = [
        'entity_type',
        'dapodik_id',
        'siaps_table',
        'siaps_id',
        'confidence',
        'match_key',
        'last_seen_batch_id',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function lastSeenBatch(): BelongsTo
    {
        return $this->belongsTo(DapodikSyncBatch::class, 'last_seen_batch_id');
    }
}
