<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add new permissions
        $permissions = [
            [
                'name' => 'view_live_tracking',
                'display_name' => 'View Live Tracking',
                'description' => 'View real-time student location tracking',
                'module' => 'tracking'
            ],
            [
                'name' => 'manage_live_tracking',
                'display_name' => 'Manage Live Tracking',
                'description' => 'Manage live tracking settings and data',
                'module' => 'tracking'
            ]
        ];

        foreach ($permissions as $permission) {
            Permission::create($permission);
        }

        // Assign to Super Admin role
        $superAdmin = Role::where('name', 'Super_Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo([
                'view_live_tracking',
                'manage_live_tracking'
            ]);
        }

        // Assign view permission to Wali Kelas
        $waliKelas = Role::where('name', 'Wali_Kelas')->first();
        if ($waliKelas) {
            $waliKelas->givePermissionTo('view_live_tracking');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove permissions
        Permission::whereIn('name', [
            'view_live_tracking',
            'manage_live_tracking'
        ])->delete();
    }
};
