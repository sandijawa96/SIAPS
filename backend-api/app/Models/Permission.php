<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    protected $fillable = [
        'name',
        'display_name',
        'module',
        'guard_name'
    ];

    protected $attributes = [
        'guard_name' => 'web'
    ];
}
