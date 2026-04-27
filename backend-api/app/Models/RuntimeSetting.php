<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RuntimeSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'namespace',
        'key',
        'value',
        'type',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];
}
