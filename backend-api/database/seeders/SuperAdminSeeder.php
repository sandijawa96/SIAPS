<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = (string) env('SUPER_ADMIN_EMAIL', 'admin@sman1sumbercirebon.sch.id');
        $username = (string) env('SUPER_ADMIN_USERNAME', 'superadmin');
        $configuredPassword = env('SUPER_ADMIN_PASSWORD');

        if (!$configuredPassword && app()->environment('production')) {
            throw new RuntimeException('SUPER_ADMIN_PASSWORD must be set in production environment.');
        }

        $superAdmin = User::where('email', $email)->first();

        if (!$superAdmin) {
            $initialPassword = $configuredPassword ?: Str::random(20);

            $superAdmin = User::create([
                'email' => $email,
                'username' => $username,
                'password' => Hash::make($initialPassword),
                'nama_lengkap' => 'Super Admin',
                'jenis_kelamin' => 'L',
                'is_active' => 1,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
            ]);

            if (!$configuredPassword && $this->command) {
                $this->command->warn("Generated temporary SUPER_ADMIN_PASSWORD: {$initialPassword}");
            }
        } else {
            $updates = [
                'username' => $username,
                'nama_lengkap' => $superAdmin->nama_lengkap ?: 'Super Admin',
                'jenis_kelamin' => $superAdmin->jenis_kelamin ?: 'L',
                'is_active' => 1,
            ];

            if ($configuredPassword) {
                $updates['password'] = Hash::make((string) $configuredPassword);
            }

            $superAdmin->fill($updates);
            if ($superAdmin->isDirty()) {
                $superAdmin->save();
            }
        }

        $superAdminRole = Role::firstOrCreate(
            ['name' => 'Super_Admin', 'guard_name' => 'web'],
            [
                'display_name' => 'Super Administrator',
                'description' => 'Full system access',
                'level' => 1,
                'is_active' => true,
            ]
        );

        $allPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->get();

        if ($allPermissions->isNotEmpty()) {
            $superAdminRole->syncPermissions($allPermissions);
            $this->command?->info("Assigned {$allPermissions->count()} permissions to Super_Admin role");
        } else {
            $this->command?->warn('No web permissions found. Run permission seeder first.');
        }

        if (!$superAdmin->hasRole('Super_Admin')) {
            $superAdmin->assignRole('Super_Admin');
            $this->command?->info("Assigned Super_Admin role to user: {$superAdmin->nama_lengkap}");
        } else {
            $this->command?->info("User {$superAdmin->nama_lengkap} already has Super_Admin role");
        }

        $userPermissions = $superAdmin->getAllPermissions()->count();
        $this->command?->info("Super admin setup complete: {$superAdmin->nama_lengkap} ({$superAdmin->email}), permissions={$userPermissions}");

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
