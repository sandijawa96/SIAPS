<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\RoleAccessMatrix;
use App\Support\RoleNames;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $isTesting = app()->environment('testing');
        $isFreshDatabase = Role::count() === 0 && Permission::count() === 0;

        // Full baseline seeding (includes static IDs) is only safe for testing/fresh DB.
        if ($isTesting || $isFreshDatabase) {
            $this->call([
                PermissionsTableSeeder::class,
                RolesTableSeeder::class,
            ]);

            $this->syncPostgresSequence('roles');
            $this->ensureBaselineRoles();

            $this->call([
                RoleHasPermissionsSeeder::class,
                RoleHierarchiesSeeder::class,
            ]);

            $this->syncPostgresSequence('permissions');
            $this->syncPostgresSequence('roles');
            $this->syncPostgresSequence('role_hierarchies');
        } else {
            // Existing production-like DB: keep it idempotent and non-destructive.
            $this->call([
                PermissionsTableSeeder::class,
            ]);

            $this->syncPostgresSequence('roles');
            $this->ensureBaselineRoles();

            $this->call([
                RoleHasPermissionsSeeder::class,
            ]);

            $this->syncPostgresSequence('permissions');
            $this->syncPostgresSequence('roles');
        }

        if ($isTesting) {
            // Ensure role compatibility expected by tests.
            $superAdminRole = Role::updateOrCreate(
                ['name' => 'Super_Admin', 'guard_name' => 'web'],
                ['display_name' => 'Super Administrator', 'description' => 'Full system access', 'level' => 100, 'is_active' => true]
            );
            $guruRole = Role::updateOrCreate(
                ['name' => 'Guru', 'guard_name' => 'web'],
                ['display_name' => 'Guru', 'description' => 'Guru pengajar', 'level' => 50, 'is_active' => true]
            );
            $waliRole = Role::updateOrCreate(
                ['name' => 'Wali Kelas', 'guard_name' => 'web'],
                ['display_name' => 'Wali Kelas', 'description' => 'Wali kelas', 'level' => 40, 'is_active' => true]
            );
            $siswaRole = Role::updateOrCreate(
                ['name' => 'Siswa', 'guard_name' => 'web'],
                ['display_name' => 'Siswa', 'description' => 'Siswa', 'level' => 10, 'is_active' => true]
            );

            // Super admin gets all permissions for tests that validate wildcard access.
            $superAdminRole->syncPermissions(Permission::where('guard_name', 'web')->get());

            // Create test users expected by EnhancedRolePermissionTest.
            $superAdmin = User::firstOrCreate(
                ['username' => 'superadmin'],
                [
                    'email' => 'superadmin@test.local',
                    'password' => Hash::make('password123'),
                    'nama_lengkap' => 'Super Admin Test',
                    'jenis_kelamin' => 'L',
                    'is_active' => true,
                ]
            );
            $guru = User::firstOrCreate(
                ['username' => 'guru'],
                [
                    'email' => 'guru@test.local',
                    'password' => Hash::make('password123'),
                    'nama_lengkap' => 'Guru Test',
                    'jenis_kelamin' => 'L',
                    'is_active' => true,
                ]
            );
            $waliKelas = User::firstOrCreate(
                ['username' => 'walikelas'],
                [
                    'email' => 'walikelas@test.local',
                    'password' => Hash::make('password123'),
                    'nama_lengkap' => 'Wali Kelas Test',
                    'jenis_kelamin' => 'P',
                    'is_active' => true,
                ]
            );
            $multiRoleTeacher = User::firstOrCreate(
                ['username' => 'multiroleteacher'],
                [
                    'email' => 'multiroleteacher@test.local',
                    'password' => Hash::make('password123'),
                    'nama_lengkap' => 'Multi Role Teacher',
                    'jenis_kelamin' => 'L',
                    'is_active' => true,
                ]
            );

            $superAdmin->syncRoles([$superAdminRole->name]);
            $guru->syncRoles([$guruRole->name]);
            $waliKelas->syncRoles([$waliRole->name]);
            $multiRoleTeacher->syncRoles([$guruRole->name, $waliRole->name]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncPostgresSequence(string $table): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            SELECT setval(
                pg_get_serial_sequence('{$table}', 'id'),
                COALESCE((SELECT MAX(id) FROM {$table}), 1),
                COALESCE((SELECT MAX(id) FROM {$table}), 0) > 0
            )
        ");
    }

    private function ensureBaselineRoles(): void
    {
        foreach (RoleAccessMatrix::baselineRoles() as $roleDefinition) {
            Role::firstOrCreate(
                ['name' => $roleDefinition['name'], 'guard_name' => 'web'],
                [
                    'display_name' => $roleDefinition['display_name'],
                    'description' => $roleDefinition['description'],
                    'level' => $roleDefinition['level'],
                    'is_active' => (bool) $roleDefinition['is_active'],
                ]
            );
        }
    }
}
