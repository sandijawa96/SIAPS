<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use App\Support\RoleAccessMatrix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Support\RoleNames;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    protected $guardName = 'web'; // Set default guard name

    public function index()
    {
        $roles = Role::where('name', '!=', 'Super_Admin')
            ->with(['permissions'])
            ->select('id', 'name', 'display_name', 'description', 'level', 'is_active')
            ->orderBy('level', 'desc')
            ->get();

        // Add hierarchy data for backward compatibility
        $roles = $roles->map(function ($role) {
            $parentRole = \App\Models\RoleHierarchy::getParent($role->id);
            $childRoles = \App\Models\RoleHierarchy::getChildren($role->id);

            $role->is_primary = !$parentRole;
            $role->parent_role_id = $parentRole ? $parentRole->id : null;
            $role->parent_role = $parentRole;
            $role->sub_roles = $childRoles;
            $role->sort_order = 0; // Default value for compatibility

            return $role;
        });

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    public function getPrimaryRoles()
    {
        try {
            // Get only primary roles (roles that don't have parent_role_id in role_hierarchies)
            $primaryRoleIds = \App\Models\RoleHierarchy::pluck('child_role_id')->toArray();

            $roles = Role::where('name', '!=', 'Super_Admin')
                ->whereNotIn('id', $primaryRoleIds) // Exclude roles that are children
                ->select('id', 'name', 'display_name', 'description', 'level', 'is_active')
                ->orderBy('level', 'desc')
                ->get();

            // Mark them as primary roles
            $roles = $roles->map(function ($role) {
                $role->is_primary = true;
                return $role;
            });

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil primary roles'
            ], 500);
        }
    }

    public function getSubRoles($parentId)
    {
        try {
            // Validate parent role exists
            $parentRole = Role::findOrFail($parentId);

            // Get child roles from role_hierarchies table
            $childRoles = Role::join('role_hierarchies', 'roles.id', '=', 'role_hierarchies.child_role_id')
                ->where('role_hierarchies.parent_role_id', $parentId)
                ->where('roles.is_active', true) // Only get active roles
                ->select('roles.*', 'role_hierarchies.sort_order')
                ->orderBy('role_hierarchies.sort_order')
                ->get();

            // Log for debugging
            Log::info('Getting sub roles for parent ID: ' . $parentId);
            Log::info('Found child roles:', $childRoles->toArray());

            return response()->json([
                'success' => true,
                'data' => $childRoles
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::warning('Parent role not found: ' . $parentId);
            return response()->json([
                'success' => false,
                'message' => 'Role tidak ditemukan'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error getting sub roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil sub roles'
            ], 500);
        }
    }

    public function getAvailableRoles()
    {
        $roles = Role::where('name', '!=', RoleNames::SUPER_ADMIN)
            ->where('is_active', true)
            ->select('name as value', 'display_name as label')
            ->orderBy('level', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    public function featureMatrix()
    {
        $rolesByName = Role::query()
            ->where('guard_name', $this->guardName)
            ->with('permissions:id,name,guard_name')
            ->get()
            ->keyBy('name');

        $catalogPermissionNames = PermissionCatalog::names();

        $matrix = collect(RoleAccessMatrix::definitions())
            ->map(function (array $definition, string $roleName) use ($rolesByName, $catalogPermissionNames) {
                $recommendedPermissions = $definition['permissions'] === [RoleAccessMatrix::WILDCARD_ALL]
                    ? $catalogPermissionNames
                    : $definition['permissions'];

                $activePermissions = $rolesByName->has($roleName)
                    ? $rolesByName[$roleName]
                        ->permissions
                        ->pluck('name')
                        ->unique()
                        ->sort()
                        ->values()
                        ->all()
                    : [];

                sort($recommendedPermissions);

                return [
                    'role_name' => $roleName,
                    'display_name' => $definition['display_name'],
                    'description' => $definition['description'],
                    'level' => $definition['level'],
                    'features' => $definition['features'],
                    'recommended_permissions' => $recommendedPermissions,
                    'active_permissions' => $activePermissions,
                    'missing_permissions' => array_values(array_diff($recommendedPermissions, $activePermissions)),
                    'extra_permissions' => array_values(array_diff($activePermissions, $recommendedPermissions)),
                ];
            })
            ->sortBy('level')
            ->values();

        return response()->json([
            'success' => true,
            'data' => $matrix,
        ]);
    }

    public function myFeatureProfile(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $user->loadMissing('roles:id,name,display_name,description,level,guard_name');

        $definitions = RoleAccessMatrix::definitions();
        $catalogPermissionNames = PermissionCatalog::names();

        $assignedRoles = $user->roles
            ->pluck('name')
            ->filter(fn ($roleName): bool => is_string($roleName) && $roleName !== '')
            ->values();

        $roleProfiles = $assignedRoles
            ->map(function (string $assignedRoleName) use ($definitions, $catalogPermissionNames) {
                $canonicalRoleName = RoleNames::normalize($assignedRoleName) ?? $assignedRoleName;
                $definition = $definitions[$canonicalRoleName] ?? null;

                $recommendedPermissions = [];
                if ($definition) {
                    $recommendedPermissions = $definition['permissions'] === [RoleAccessMatrix::WILDCARD_ALL]
                        ? $catalogPermissionNames
                        : $definition['permissions'];
                    sort($recommendedPermissions);
                }

                return [
                    'assigned_role_name' => $assignedRoleName,
                    'canonical_role_name' => $canonicalRoleName,
                    'display_name' => $definition['display_name'] ?? $assignedRoleName,
                    'description' => $definition['description'] ?? null,
                    'level' => $definition['level'] ?? null,
                    'features' => $definition['features'] ?? [],
                    'recommended_permissions' => $recommendedPermissions,
                ];
            })
            ->values();

        $effectivePermissions = $user->getAllPermissions()
            ->pluck('name')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $recommendedPermissions = $roleProfiles
            ->pluck('recommended_permissions')
            ->flatten()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $features = $roleProfiles
            ->pluck('features')
            ->flatten()
            ->unique()
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'assigned_roles' => $assignedRoles->all(),
                'roles' => $roleProfiles->all(),
                'features' => $features,
                'effective_permissions' => $effectivePermissions,
                'recommended_permissions' => $recommendedPermissions,
                'missing_permissions' => array_values(array_diff($recommendedPermissions, $effectivePermissions)),
                'extra_permissions' => array_values(array_diff($effectivePermissions, $recommendedPermissions)),
            ],
        ]);
    }

    public function store(Request $request)
    {
        try {
            $requestedPermissions = collect($request->input('permissions', []))
                ->filter(fn ($permission) => is_string($permission) && $permission !== '')
                ->values();
            $catalogPermissionNames = PermissionCatalog::names();

            $data = [
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'level' => $request->level,
                'guard_name' => 'web'
            ];

            $role = Role::create($data);

            // Create hierarchy relationship if not primary
            if (!($request->is_primary ?? false) && $request->parent_role_id) {
                \App\Models\RoleHierarchy::create([
                    'parent_role_id' => $request->parent_role_id,
                    'child_role_id' => $role->id,
                    'sort_order' => $request->sort_order ?? 0
                ]);
            }

            if ($requestedPermissions->isNotEmpty()) {
                $existingPermissions = \App\Models\Permission::whereIn(
                    'name',
                    array_values(array_intersect($requestedPermissions->all(), $catalogPermissionNames))
                )
                    ->where('guard_name', $this->guardName)
                    ->pluck('name')
                    ->all();
                $role->syncPermissions($existingPermissions);
            }

            // Jika ini adalah sub-role, inherit permissions dari parent
            $parentRole = \App\Models\RoleHierarchy::getParent($role->id);
            if ($parentRole) {
                $parentPermissions = $parentRole->permissions->pluck('name')->toArray();
                $mergedPermissions = collect(array_merge($parentPermissions, $requestedPermissions->all()))
                    ->filter(fn ($permissionName) => in_array($permissionName, $catalogPermissionNames, true))
                    ->unique()
                    ->values();
                $existingMergedPermissions = \App\Models\Permission::whereIn('name', $mergedPermissions)
                    ->where('guard_name', $this->guardName)
                    ->pluck('name')
                    ->all();
                $role->syncPermissions($existingMergedPermissions);
            }

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil dibuat',
                'data' => $role->load('permissions')
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat role'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $role = Role::with('permissions')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $role
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Role tidak ditemukan'
            ], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $role = Role::findOrFail($id);
            $requestedPermissions = collect($request->input('permissions', []))
                ->filter(fn ($permission) => is_string($permission) && $permission !== '')
                ->values();
            $catalogPermissionNames = PermissionCatalog::names();

            // Prevent updating Super_Admin role
            if ($role->name === 'Super_Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Role Super Admin tidak dapat diubah'
                ], 403);
            }

            $data = [
                'name' => $request->name,
                'display_name' => $request->display_name,
                'description' => $request->description,
                'level' => $request->level
            ];

            $role->update($data);

            // Update hierarchy relationship
            if ($request->has('is_primary')) {
                // Delete existing hierarchy if exists
                \App\Models\RoleHierarchy::where('child_role_id', $role->id)->delete();

                // Create new hierarchy if not primary
                if (!$request->is_primary && $request->parent_role_id) {
                    \App\Models\RoleHierarchy::create([
                        'parent_role_id' => $request->parent_role_id,
                        'child_role_id' => $role->id,
                        'sort_order' => $request->sort_order ?? 0
                    ]);
                }
            } elseif ($request->has('sort_order')) {
                // Update sort order only
                $hierarchy = \App\Models\RoleHierarchy::where('child_role_id', $role->id)->first();
                if ($hierarchy) {
                    $hierarchy->update(['sort_order' => $request->sort_order]);
                }
            }

            if ($request->has('permissions')) {
                // Get parent role after potential hierarchy update
                $parentRole = \App\Models\RoleHierarchy::getParent($role->id);
                $existingPermissions = \App\Models\Permission::whereIn(
                    'name',
                    array_values(array_intersect($requestedPermissions->all(), $catalogPermissionNames))
                )
                    ->where('guard_name', $this->guardName)
                    ->pluck('name')
                    ->all();

                // If this is a sub-role, merge with parent permissions
                if ($parentRole) {
                    $parentPermissions = $parentRole->permissions->pluck('name')->toArray();
                    $mergedPermissions = array_values(array_unique(array_merge($parentPermissions, $existingPermissions)));
                    $role->syncPermissions(
                        array_values(array_filter(
                            $mergedPermissions,
                            fn ($permissionName) => in_array($permissionName, $catalogPermissionNames, true)
                        ))
                    );
                } else {
                    $role->syncPermissions($existingPermissions);
                }
            }

            // Load updated role with permissions and hierarchy info
            $role->load('permissions');
            $parentRole = \App\Models\RoleHierarchy::getParent($role->id);
            $role->is_primary = !$parentRole;
            $role->parent_role = $parentRole;

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil diupdate',
                'data' => $role
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate role'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);

            // Prevent deletion of Super_Admin role
            if ($role->name === 'Super_Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Role Super Admin tidak dapat dihapus'
                ], 403);
            }

            $role->delete();

            return response()->json([
                'success' => true,
                'message' => 'Role berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus role'
            ], 500);
        }
    }

    public function hierarchy()
    {
        try {
            $roles = Role::where('name', '!=', 'Super_Admin')
                ->with('permissions')
                ->withCount('users')
                ->orderBy('level', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil hierarki role'
            ], 500);
        }
    }

    public function getEffectivePermissions(Request $request)
    {
        try {
            $roleIds = $request->input('role_ids', []);
            $roles = Role::with('permissions')
                ->whereIn('id', $roleIds)
                ->get();
            $permissions = $roles->flatMap(fn ($role) => $role->permissions)
                ->unique('id')
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'roles' => $roles->map(fn ($role) => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name,
                    ])->values(),
                    'effective_permissions' => $permissions,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil effective permissions'
            ], 500);
        }
    }

    public function assignPermissions(Request $request, $id)
    {
        try {
            $role = Role::findOrFail($id);

            // Prevent updating Super_Admin role
            if ($role->name === 'Super_Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Role Super Admin tidak dapat diubah'
                ], 403);
            }

            $permissions = collect($request->input('permissions', []))
                ->filter(fn ($permissionName) => is_string($permissionName) && $permissionName !== '')
                ->filter(fn ($permissionName) => in_array($permissionName, PermissionCatalog::names(), true))
                ->values()
                ->all();

            $existingPermissions = \App\Models\Permission::query()
                ->where('guard_name', $this->guardName)
                ->whereIn('name', $permissions)
                ->pluck('name')
                ->all();

            $role->syncPermissions($existingPermissions);

            return response()->json([
                'success' => true,
                'message' => 'Permissions berhasil diassign',
                'data' => $role->load('permissions')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal assign permissions'
            ], 500);
        }
    }

    public function toggleStatus(Request $request, $id)
    {
        try {
            $role = Role::findOrFail($id);

            // Prevent updating Super_Admin role
            if ($role->name === 'Super_Admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Role Super Admin tidak dapat diubah'
                ], 403);
            }

            $role->update([
                'is_active' => !$role->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status role berhasil diubah',
                'data' => $role
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status role'
            ], 500);
        }
    }
}

