<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionCatalogSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_delete_legacy_permissions(): void
    {
        Permission::create([
            'name' => 'legacy_permission_x',
            'display_name' => 'Legacy Permission X',
            'description' => 'Legacy test permission',
            'module' => 'legacy',
            'guard_name' => 'web',
        ]);

        $this->artisan('permissions:sync-catalog', [
            '--guard' => 'web',
            '--prune' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('permissions', [
            'name' => 'legacy_permission_x',
            'guard_name' => 'web',
        ]);
    }

    public function test_execute_with_prune_syncs_catalog_and_removes_legacy_permissions(): void
    {
        Permission::create([
            'name' => 'legacy_permission_y',
            'display_name' => 'Legacy Permission Y',
            'description' => 'Legacy test permission',
            'module' => 'legacy',
            'guard_name' => 'web',
        ]);

        Permission::create([
            'name' => 'view_users',
            'display_name' => 'Broken Label',
            'description' => 'Broken description',
            'module' => 'broken',
            'guard_name' => 'web',
        ]);

        $this->artisan('permissions:sync-catalog', [
            '--guard' => 'web',
            '--prune' => true,
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('permissions', [
            'name' => 'legacy_permission_y',
            'guard_name' => 'web',
        ]);

        $this->assertDatabaseHas('permissions', [
            'name' => 'view_users',
            'display_name' => 'View Users',
            'module' => 'users',
            'guard_name' => 'web',
        ]);

        $this->assertSame(
            count(PermissionCatalog::definitions()),
            Permission::query()->where('guard_name', 'web')->count()
        );
    }

    public function test_execute_with_prune_keeps_legacy_permissions_that_are_still_used(): void
    {
        $legacy = Permission::create([
            'name' => 'legacy_permission_used',
            'display_name' => 'Legacy Permission Used',
            'description' => 'Legacy used permission',
            'module' => 'legacy',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'RoleLegacyUsed',
            'display_name' => 'Role Legacy Used',
            'description' => 'Role for legacy permission test',
            'level' => 1,
            'is_active' => true,
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($legacy);

        $this->artisan('permissions:sync-catalog', [
            '--guard' => 'web',
            '--prune' => true,
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('permissions', [
            'name' => 'legacy_permission_used',
            'guard_name' => 'web',
        ]);
    }

    public function test_execute_with_prune_used_deletes_legacy_permissions_that_are_still_used(): void
    {
        $legacy = Permission::create([
            'name' => 'legacy_permission_delete_used',
            'display_name' => 'Legacy Permission Delete Used',
            'description' => 'Legacy used permission to delete',
            'module' => 'legacy',
            'guard_name' => 'web',
        ]);

        $role = Role::create([
            'name' => 'RoleLegacyDeleteUsed',
            'display_name' => 'Role Legacy Delete Used',
            'description' => 'Role for prune-used test',
            'level' => 2,
            'is_active' => true,
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($legacy);

        $this->artisan('permissions:sync-catalog', [
            '--guard' => 'web',
            '--prune' => true,
            '--prune-used' => true,
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('permissions', [
            'name' => 'legacy_permission_delete_used',
            'guard_name' => 'web',
        ]);
    }
}
