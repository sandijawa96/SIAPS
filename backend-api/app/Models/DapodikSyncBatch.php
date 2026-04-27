<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DapodikSyncBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'status',
        'base_url',
        'npsn',
        'requested_by',
        'started_at',
        'finished_at',
        'totals',
        'errors',
        'meta',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'totals' => 'array',
        'errors' => 'array',
        'meta' => 'array',
    ];

    public function records(): HasMany
    {
        return $this->hasMany(DapodikSyncRecord::class, 'batch_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
