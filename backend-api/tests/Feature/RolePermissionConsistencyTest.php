<?php

namespace Tests\Feature;

use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolePermissionConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_permissions_used_by_routes_and_authorize_exist_in_seed_data(): void
    {
        $requiredPermissions = [
            'manage_users',
            'view_users',
            'create_users',
            'update_users',
            'delete_users',
            'reset_user_passwords',
            'view_roles',
            'manage_roles',
            'manage_permissions',
            'manage_kelas',
            'view_kelas',
            'manage_students',
            'view_siswa',
            'manage_pegawai',
            'view_pegawai',
            'manage_tahun_ajaran',
            'view_tahun_ajaran',
            'manage_periode_akademik',
            'manage_event_akademik',
            'manage_settings',
            'view_settings',
            'manage_attendance_settings',
            'unlock_face_template_submit_quota',
            'manage_notifications',
            'view_reports',
            'manage_absensi',
            'view_absensi',
            'manual_attendance',
            'manual_attendance_backdate_override',
            'submit_izin',
            'approve_izin',
            'view_all_izin',
            'view_kelas_izin',
            'manage_whatsapp',
            'manage_qrcode',
            'manage_backups',
            'view_activity_logs',
            'manage_activity_logs',
            'view_live_tracking',
            'manage_live_tracking',
        ];

        $existingPermissions = Permission::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $requiredPermissions)
            ->pluck('name')
            ->all();

        $this->assertEqualsCanonicalizing($requiredPermissions, $existingPermissions);
    }

    public function test_role_aliases_cover_web_and_api_variants(): void
    {
        $waliAliases = RoleNames::aliases(RoleNames::WALI_KELAS);
        $guruAliases = RoleNames::aliases(RoleNames::GURU);
        $siswaAliases = RoleNames::aliases(RoleNames::SISWA);

        $this->assertContains('Wali_Kelas_web', $waliAliases);
        $this->assertContains('Wali_Kelas_api', $waliAliases);
        $this->assertContains('Guru_web', $guruAliases);
        $this->assertContains('Guru_api', $guruAliases);
        $this->assertContains('Siswa_web', $siswaAliases);
        $this->assertContains('Siswa_api', $siswaAliases);
    }

    public function test_admin_and_super_admin_have_critical_management_permissions(): void
    {
        $criticalPermissions = [
            'view_users',
            'create_users',
            'update_users',
            'delete_users',
            'reset_user_passwords',
            'manage_users',
            'manage_students',
            'manage_pegawai',
            'manage_kelas',
            'manage_tahun_ajaran',
            'manage_periode_akademik',
            'manage_event_akademik',
            'manage_settings',
            'manage_attendance_settings',
            'unlock_face_template_submit_quota',
            'manage_notifications',
            'view_reports',
            'manage_absensi',
            'manual_attendance',
            'manual_attendance_backdate_override',
            'submit_izin',
            'approve_izin',
            'view_all_izin',
            'view_kelas_izin',
            'manage_whatsapp',
            'manage_qrcode',
            'manage_permissions',
            'manage_backups',
            'view_activity_logs',
            'manage_activity_logs',
            'view_live_tracking',
            'manage_live_tracking',
        ];

        $admin = Role::query()->where('name', 'Admin')->where('guard_name', 'web')->firstOrFail();
        $superAdmin = Role::query()->where('name', 'Super_Admin')->where('guard_name', 'web')->firstOrFail();

        foreach ($criticalPermissions as $permission) {
            $this->assertTrue($admin->hasPermissionTo($permission), "Admin missing permission {$permission}");
            $this->assertTrue($superAdmin->hasPermissionTo($permission), "Super_Admin missing permission {$permission}");
        }
    }
}
