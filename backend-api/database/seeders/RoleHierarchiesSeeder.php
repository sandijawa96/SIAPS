<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleHierarchiesSeeder extends Seeder
{
    public function run()
    {
        // Hapus data lama
        DB::table('role_hierarchies')->truncate();

        // Data role hierarchies sesuai dengan smanis-absensi2.sql
        $roleHierarchies = [
            ['id' => 3, 'parent_role_id' => 10, 'child_role_id' => 8, 'sort_order' => 0],
            ['id' => 5, 'parent_role_id' => 10, 'child_role_id' => 6, 'sort_order' => 0],
            ['id' => 6, 'parent_role_id' => 10, 'child_role_id' => 5, 'sort_order' => 0],
            ['id' => 7, 'parent_role_id' => 10, 'child_role_id' => 4, 'sort_order' => 0],
            ['id' => 9, 'parent_role_id' => 12, 'child_role_id' => 3, 'sort_order' => 0],
            ['id' => 10, 'parent_role_id' => 10, 'child_role_id' => 7, 'sort_order' => 0],
            ['id' => 11, 'parent_role_id' => 12, 'child_role_id' => 11, 'sort_order' => 0],
            ['id' => 13, 'parent_role_id' => 10, 'child_role_id' => 9, 'sort_order' => 0],
        ];

        DB::table('role_hierarchies')->insert($roleHierarchies);
    }
}
