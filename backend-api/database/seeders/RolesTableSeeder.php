<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    public function run()
    {
        $roles = [
            ['id' => 1, 'name' => 'Super_Admin', 'display_name' => 'Super Administrator', 'description' => 'Full system access', 'level' => 1, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 2, 'name' => 'Admin', 'display_name' => 'Administrator', 'description' => 'System administrator', 'level' => 2, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 3, 'name' => 'Kepala_Sekolah', 'display_name' => 'Kepala Sekolah', 'description' => 'Pimpinan sekolah', 'level' => 3, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 4, 'name' => 'Wakasek_Kurikulum', 'display_name' => 'Wakil Kepala Sekolah Kurikulum', 'description' => 'Manajemen kurikulum', 'level' => 4, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 5, 'name' => 'Wakasek_Kesiswaan', 'display_name' => 'Wakil Kepala Sekolah Kesiswaan', 'description' => 'Manajemen kesiswaan', 'level' => 5, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 6, 'name' => 'Wakasek_Humas', 'display_name' => 'Wakil Kepala Sekolah Humas', 'description' => 'Hubungan masyarakat', 'level' => 6, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 7, 'name' => 'Wakasek_Sarpras', 'display_name' => 'Wakil Kepala Sekolah Sarpras', 'description' => 'Sarana prasarana', 'level' => 7, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 8, 'name' => 'Guru_BK', 'display_name' => 'Guru BK', 'description' => 'Bimbingan konseling', 'level' => 8, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 9, 'name' => 'Wali Kelas', 'display_name' => 'Wali Kelas', 'description' => 'Wali kelas', 'level' => 9, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 10, 'name' => 'Guru', 'display_name' => 'Guru', 'description' => 'Guru pengajar', 'level' => 10, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 11, 'name' => 'Staff_TU', 'display_name' => 'Staff Tata Usaha', 'description' => 'Administrasi sekolah', 'level' => 11, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 12, 'name' => 'Staff', 'display_name' => 'Staff', 'description' => 'Staff sekolah', 'level' => 12, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 13, 'name' => 'Pegawai', 'display_name' => 'Pegawai', 'description' => 'Pegawai sekolah', 'level' => 13, 'is_active' => 1, 'guard_name' => 'web'],
            ['id' => 14, 'name' => 'Siswa', 'display_name' => 'Siswa', 'description' => 'Siswa', 'level' => 14, 'is_active' => 1, 'guard_name' => 'web'],
        ];

        DB::table('roles')->insert($roles);
    }
}
