<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionsTableSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = PermissionCatalog::definitions();

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                [
                    'name' => $permission['name'],
                    'guard_name' => 'web',
                ],
                array_merge($permission, ['guard_name' => 'web'])
            );
        }

        // Keep local/testing permission catalog aligned with the active code paths.
        if (app()->environment(['local', 'testing'])) {
            Permission::query()
                ->where('guard_name', 'web')
                ->whereNotIn('name', array_column($permissions, 'name'))
                ->delete();
        }
    }
}
