<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\RoleAccessMatrix;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class RoleHasPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $rolePermissionMap = RoleAccessMatrix::permissionMap();

        $allWebPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        foreach ($rolePermissionMap as $roleName => $permissionNames) {
            $role = Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first();

            if (!$role) {
                continue;
            }

            if ($permissionNames === [RoleAccessMatrix::WILDCARD_ALL]) {
                $role->syncPermissions($allWebPermissions);
                continue;
            }

            $existingPermissionNames = array_values(array_filter(
                $permissionNames,
                static fn (string $permissionName): bool => in_array($permissionName, $allWebPermissions, true)
            ));

            $role->syncPermissions($existingPermissionNames);
        }
    }
}
