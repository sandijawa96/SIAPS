<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSecurityCaseItem extends Model
{
    protected $fillable = [
        'case_id',
        'item_type',
        'item_id',
        'item_snapshot',
    ];

    protected $casts = [
        'item_snapshot' => 'array',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(AttendanceSecurityCase::class, 'case_id');
    }

    public function toArrayPayload(): array
    {
        return [
            'id' => (int) $this->id,
            'item_type' => $this->item_type,
            'item_id' => (int) $this->item_id,
            'item_snapshot' => is_array($this->item_snapshot) ? $this->item_snapshot : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
