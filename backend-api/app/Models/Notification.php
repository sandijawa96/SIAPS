<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'data',
        'is_read',
        'display_start_at',
        'display_end_at',
        'expires_at',
        'pinned_at',
        'priority',
        'created_by'
    ];

    protected $casts = [
        'data' => 'array',
        'is_read' => 'boolean',
        'display_start_at' => 'datetime',
        'display_end_at' => 'datetime',
        'expires_at' => 'datetime',
        'pinned_at' => 'datetime',
        'priority' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
