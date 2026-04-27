<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleHierarchy extends Model
{
    use HasFactory;

    protected $fillable = [
        'parent_role_id',
        'child_role_id',
        'sort_order'
    ];

    protected $casts = [
        'sort_order' => 'integer'
    ];

    // Relationship to parent role
    public function parentRole()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'parent_role_id');
    }

    // Relationship to child role
    public function childRole()
    {
        return $this->belongsTo(\Spatie\Permission\Models\Role::class, 'child_role_id');
    }

    // Scope for ordering
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // Get all children of a parent role
    public static function getChildren($parentRoleId)
    {
        return self::where('parent_role_id', $parentRoleId)
            ->ordered()
            ->with('childRole')
            ->get()
            ->pluck('childRole');
    }

    // Get parent of a child role
    public static function getParent($childRoleId)
    {
        $hierarchy = self::where('child_role_id', $childRoleId)
            ->with('parentRole')
            ->first();

        return $hierarchy ? $hierarchy->parentRole : null;
    }

    // Check if role has children
    public static function hasChildren($roleId)
    {
        return self::where('parent_role_id', $roleId)->exists();
    }

    // Check if role is a child
    public static function isChild($roleId)
    {
        return self::where('child_role_id', $roleId)->exists();
    }
}
