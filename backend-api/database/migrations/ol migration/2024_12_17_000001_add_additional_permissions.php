<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Daftar permissions baru yang akan ditambahkan
        $newPermissions = [
            [
                'name' => 'manage_users',
                'display_name' => 'Manajemen Pengguna',
                'description' => 'Dapat mengelola data pengguna sistem'
            ],
            [
                'name' => 'view_realtime_attendance',
                'display_name' => 'Lihat Absensi Realtime',
                'description' => 'Dapat melihat data absensi secara realtime'
            ],
            [
                'name' => 'manage_academic_calendar',
                'display_name' => 'Manajemen Kalender Akademik',
                'description' => 'Dapat mengelola kalender akademik'
            ]
        ];

        // Buat permissions baru
        foreach ($newPermissions as $permissionData) {
            Permission::firstOrCreate(
                [
                    'name' => $permissionData['name'],
                    'guard_name' => 'web'
                ],
                [
                    'display_name' => $permissionData['display_name'],
                    'description' => $permissionData['description']
                ]
            );
        }

        // Log permissions yang telah dibuat
        Log::info('Additional permissions created successfully', [
            'permissions' => array_column($newPermissions, 'name')
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Hapus permissions yang dibuat
        $permissionsToDelete = [
            'manage_users',
            'view_realtime_attendance',
            'manage_academic_calendar'
        ];

        foreach ($permissionsToDelete as $permissionName) {
            $permission = Permission::where('name', $permissionName)
                ->where('guard_name', 'web')
                ->first();
            if ($permission) {
                $permission->delete();
            }
        }

        Log::info('Additional permissions deleted successfully', [
            'permissions' => $permissionsToDelete
        ]);
    }
};
