<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Support\Collection;

class Role extends SpatieRole
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'level',
        'is_active',
        'guard_name'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'level' => 'integer'
    ];

    protected $attributes = [
        'guard_name' => 'web',
        'is_active' => true
    ];

    // Relasi ke role hierarchies sebagai parent
    public function childHierarchies()
    {
        return $this->hasMany(RoleHierarchy::class, 'parent_role_id');
    }

    // Relasi ke role hierarchies sebagai child
    public function parentHierarchy()
    {
        return $this->hasOne(RoleHierarchy::class, 'child_role_id');
    }

    // Get parent role melalui hierarchy
    public function parentRole()
    {
        return $this->parentHierarchy()->with('parentRole');
    }

    // Get child roles melalui hierarchy
    public function childRoles()
    {
        return $this->childHierarchies()->with('childRole')->ordered();
    }

    // Check apakah role adalah primary (tidak memiliki parent)
    public function isPrimary()
    {
        return !$this->parentHierarchy()->exists();
    }

    // Scope untuk primary roles
    public function scopePrimary($query)
    {
        return $query->whereDoesntHave('parentHierarchy');
    }

    // Scope untuk child roles
    public function scopeSubRoles($query)
    {
        return $query->whereHas('parentHierarchy');
    }

    // Method untuk mendapatkan hierarki lengkap
    public function getHierarchy()
    {
        if ($this->isPrimary()) {
            return $this->load('childRoles.permissions');
        }
        
        return $this->load(['parentRole', 'permissions']);
    }

    /**
     * Get all permissions, including inherited ones.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAllPermissions(): Collection
    {
        $permissions = parent::getAllPermissions();

        $parentRole = RoleHierarchy::getParent($this->id);
        if ($parentRole) {
            $permissions = $permissions->merge($parentRole->getAllPermissions());
        }

        return $permissions->unique('id');
    }
}
