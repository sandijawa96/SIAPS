<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DapodikSyncRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'batch_id',
        'source',
        'dapodik_id',
        'secondary_id',
        'row_index',
        'row_hash',
        'row_data',
        'normalized_data',
        'meta',
    ];

    protected $casts = [
        'row_data' => 'array',
        'normalized_data' => 'array',
        'meta' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(DapodikSyncBatch::class, 'batch_id');
    }
}
