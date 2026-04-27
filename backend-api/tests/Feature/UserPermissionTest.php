<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class UserPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected $roleGuru;
    protected $roleWaliKelas;
    protected $roleSuperAdmin;
    protected $permAbsensiDiri;
    protected $permInputNilai;
    protected $permLihatJadwal;
    protected $permLihatKelas;
    protected $permLaporanKelas;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear relevant tables first
        if (config('database.default') !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        }
        DB::table('role_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('roles')->truncate();
        DB::table('permissions')->truncate();
        if (config('database.default') !== 'sqlite') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        // Create roles
        $this->roleGuru = Role::create([
            'name' => 'Guru', 
            'guard_name' => 'web',
            'display_name' => 'Guru'
        ]);
        $this->roleWaliKelas = Role::create([
            'name' => 'Wali Kelas', 
            'guard_name' => 'web',
            'display_name' => 'Wali Kelas'
        ]);
        $this->roleSuperAdmin = Role::create([
            'name' => 'Super Admin', 
            'guard_name' => 'web',
            'display_name' => 'Super Admin'
        ]);

        // Create permissions
        $this->permAbsensiDiri = Permission::create([
            'name' => 'absensi_diri', 
            'guard_name' => 'web',
            'display_name' => 'Absensi Diri'
        ]);
        $this->permInputNilai = Permission::create([
            'name' => 'input_nilai', 
            'guard_name' => 'web',
            'display_name' => 'Input Nilai'
        ]);
        $this->permLihatJadwal = Permission::create([
            'name' => 'lihat_jadwal', 
            'guard_name' => 'web',
            'display_name' => 'Lihat Jadwal'
        ]);
        $this->permLihatKelas = Permission::create([
            'name' => 'lihat_kelas', 
            'guard_name' => 'web',
            'display_name' => 'Lihat Kelas'
        ]);
        $this->permLaporanKelas = Permission::create([
            'name' => 'laporan_kelas', 
            'guard_name' => 'web',
            'display_name' => 'Laporan Kelas'
        ]);

        // Assign permissions to roles
        $this->roleGuru->givePermissionTo([$this->permAbsensiDiri, $this->permInputNilai, $this->permLihatJadwal]);
        $this->roleWaliKelas->givePermissionTo([$this->permLihatKelas, $this->permInputNilai, $this->permLaporanKelas]);
    }

    /** @test */
    public function superadmin_has_all_permissions()
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');

        $this->assertTrue($user->hasPermissionTo('absensi_diri'));
        $this->assertTrue($user->hasPermissionTo('input_nilai'));
        $this->assertTrue($user->hasPermissionTo('lihat_jadwal'));
        $this->assertTrue($user->hasPermissionTo('lihat_kelas'));
        $this->assertTrue($user->hasPermissionTo('laporan_kelas'));
        
        // Test a non-existent permission
        $this->assertTrue($user->hasPermissionTo('some_random_permission'), 'Super Admin should have access to all permissions, even undefined ones');
    }

    /** @test */
    public function user_with_multiple_roles_gets_union_of_permissions()
    {
        $user = User::factory()->create();
        $user->assignRole([$this->roleGuru->name, $this->roleWaliKelas->name]);

        // Should have permissions from both roles
        $this->assertTrue($user->hasPermissionTo('absensi_diri'), 'Should have Guru permission');
        $this->assertTrue($user->hasPermissionTo('lihat_kelas'), 'Should have Wali Kelas permission');
        $this->assertTrue($user->hasPermissionTo('input_nilai'), 'Should have shared permission');
        
        // Should not have non-existent permission
        $this->assertFalse($user->hasPermissionTo('non_existing_permission'), 'Should not have undefined permission');
    }
}
