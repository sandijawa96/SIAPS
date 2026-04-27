<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DeviceBindingRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(
            [
                'name' => 'manage_settings',
                'guard_name' => 'web',
            ],
            [
                'display_name' => 'Manage Settings',
                'description' => 'Manage device registry and settings',
                'module' => 'settings',
            ]
        );
    }

    public function test_mobile_login_registers_current_device_for_staff_without_locking(): void
    {
        $staff = User::factory()->create([
            'email' => 'guru-mobile@test.local',
            'password' => Hash::make('password123'),
        ]);
        $staff->assignRole($this->firstOrCreateRole('Guru'));

        $this->postJson('/api/mobile/login', [
            'email' => 'guru-mobile@test.local',
            'password' => 'password123',
            'device_id' => 'staff-device-001',
            'device_name' => 'Redmi Note 13',
            'device_info' => [
                'platform' => 'Android',
                'app_version' => '1.4.0',
                'app_build_number' => '140',
                'app_version_label' => 'SIAPS 1.4.0+140',
            ],
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.device_id', 'staff-device-001')
            ->assertJsonPath('data.user.device_name', 'Redmi Note 13')
            ->assertJsonPath('data.user.device_locked', false);

        $staff->refresh();

        $this->assertSame('staff-device-001', $staff->device_id);
        $this->assertSame('Redmi Note 13', $staff->device_name);
        $this->assertFalse((bool) $staff->device_locked);
        $this->assertNotNull($staff->device_bound_at);
        $this->assertNotNull($staff->last_device_activity);
        $this->assertSame('SIAPS 1.4.0+140', data_get($staff->device_info, 'app_version_label'));

        $deviceToken = DeviceToken::query()
            ->where('user_id', $staff->id)
            ->where('device_type', 'android')
            ->first();

        $this->assertNotNull($deviceToken);
        $this->assertSame('android-u' . $staff->id . '-staff-device-001', $deviceToken->device_id);
        $this->assertTrue((bool) $deviceToken->is_active);
    }

    public function test_staff_mobile_login_is_not_blocked_by_student_device_binding(): void
    {
        $student = User::factory()->create([
            'device_id' => 'siaps-student-device-001',
            'device_locked' => true,
        ]);
        $student->assignRole($this->firstOrCreateRole('Siswa'));

        $staff = User::factory()->create([
            'email' => 'admin-mobile@test.local',
            'password' => Hash::make('password123'),
        ]);
        $staff->assignRole($this->firstOrCreateRole('Guru'));

        $this->postJson('/api/mobile/login', [
            'email' => 'admin-mobile@test.local',
            'password' => 'password123',
            'device_id' => 'siaps-student-device-001',
            'device_name' => 'Shared Admin Phone',
            'device_info' => [
                'platform' => 'Android',
                'app_version_label' => 'SIAPS 1.4.0+140',
            ],
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.device_locked', false);
    }

    public function test_legacy_android_build_id_student_login_is_blocked_and_requires_reinstall(): void
    {
        $legacyDeviceId = 'UP1A.231005.007';
        $studentRole = $this->firstOrCreateRole('Siswa');

        $boundStudent = User::factory()->create([
            'nama_lengkap' => 'Siswa Sudah Terikat',
            'device_id' => $legacyDeviceId,
            'device_name' => 'Xiaomi 21081111RG',
            'device_bound_at' => now()->subDay(),
            'device_locked' => true,
        ]);
        $boundStudent->assignRole($studentRole);

        $attemptingStudent = User::factory()->create([
            'username' => '252610999',
            'nama_lengkap' => 'Siswa Perlu Update',
            'device_id' => null,
            'device_name' => null,
            'device_bound_at' => null,
            'device_locked' => false,
        ]);
        $attemptingStudent->assignRole($studentRole);
        $attemptingStudent->dataPribadiSiswa()->create([
            'tanggal_lahir' => '2008-05-16',
        ]);

        $this->postJson('/api/mobile/login-siswa', [
            'nis' => '252610999',
            'tanggal_lahir' => '16/05/2008',
            'device_id' => $legacyDeviceId,
            'device_name' => 'Xiaomi 21081111RG',
            'device_info' => [
                'platform' => 'Android',
                'legacy_android_build_id' => $legacyDeviceId,
            ],
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Aplikasi versi lama tidak didukung. Hapus aplikasi lalu instal ulang versi terbaru.')
            ->assertJsonPath('data.requires_reinstall', true);

        $attemptingStudent->refresh();

        $this->assertNull($attemptingStudent->device_id);
        $this->assertFalse((bool) $attemptingStudent->device_locked);
    }

    public function test_student_bound_to_legacy_android_build_id_can_migrate_to_fixed_device_id(): void
    {
        $student = User::factory()->create([
            'username' => '252611000',
            'nama_lengkap' => 'Siswa Migrasi Device',
            'device_id' => 'UP1A.231005.007',
            'device_name' => 'Xiaomi 21081111RG',
            'device_bound_at' => now()->subDay(),
            'device_locked' => true,
        ]);
        $student->assignRole($this->firstOrCreateRole('Siswa'));
        $student->dataPribadiSiswa()->create([
            'tanggal_lahir' => '2008-05-16',
        ]);

        $this->postJson('/api/mobile/login-siswa', [
            'nis' => '252611000',
            'tanggal_lahir' => '16/05/2008',
            'device_id' => 'siaps-0123456789abcdef0123456789abcdef',
            'device_name' => 'Xiaomi 21081111RG',
            'device_info' => [
                'platform' => 'Android',
                'legacy_android_build_id' => 'UP1A.231005.007',
            ],
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.device_id', 'siaps-0123456789abcdef0123456789abcdef')
            ->assertJsonPath('data.user.device_locked', true);

        $student->refresh();

        $this->assertSame('siaps-0123456789abcdef0123456789abcdef', $student->device_id);
        $this->assertTrue((bool) $student->device_locked);
    }

    public function test_device_binding_status_for_staff_returns_tracking_metadata_without_locking(): void
    {
        $staff = User::factory()->create([
            'device_id' => 'staff-device-002',
            'device_name' => 'iPhone 14',
            'device_bound_at' => now()->subMinutes(15),
            'device_locked' => false,
            'device_info' => [
                'platform' => 'iOS',
                'app_version_label' => 'SIAPS 2.0.1+201',
            ],
        ]);
        $staff->assignRole($this->firstOrCreateRole('Guru'));

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/device-binding/status')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.binding_enabled', false)
            ->assertJsonPath('data.is_bound', true)
            ->assertJsonPath('data.device_id', 'staff-device-002')
            ->assertJsonPath('data.device_name', 'iPhone 14')
            ->assertJsonPath('data.device_locked', false)
            ->assertJsonPath('data.security_mode', 'tracking_only')
            ->assertJsonPath('data.can_bind', true);
    }

    public function test_device_registry_admin_list_includes_students_and_staff_with_app_versions(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_settings');

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Binding',
            'device_id' => 'student-device-001',
            'device_name' => 'Samsung A55',
            'device_bound_at' => now()->subHour(),
            'device_locked' => true,
            'device_info' => [
                'app_version' => '1.5.0',
                'app_build_number' => '150',
                'app_version_label' => 'SIAPS 1.5.0+150',
            ],
        ]);
        $student->assignRole($this->firstOrCreateRole('Siswa'));

        $staff = User::factory()->create([
            'nama_lengkap' => 'Guru Registry',
            'status_kepegawaian' => 'PNS',
            'device_id' => 'staff-device-003',
            'device_name' => 'Xiaomi Pad 6',
            'device_bound_at' => now()->subMinutes(25),
            'device_locked' => false,
            'device_info' => [
                'app_version' => '2.1.0',
                'app_build_number' => '210',
                'app_version_label' => 'SIAPS 2.1.0+210',
            ],
        ]);
        $staff->assignRole($this->firstOrCreateRole('Guru'));

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/device-binding/users')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.total_registered_devices', 2)
            ->assertJsonPath('summary.total_bound_devices', 2)
            ->assertJsonPath('summary.locked_devices', 1)
            ->assertJsonPath('summary.tracking_only_devices', 1)
            ->assertJsonFragment([
                'nama_lengkap' => 'Siswa Binding',
                'app_version_label' => 'SIAPS 1.5.0+150',
                'security_mode' => 'strict_binding',
            ])
            ->assertJsonFragment([
                'nama_lengkap' => 'Guru Registry',
                'app_version_label' => 'SIAPS 2.1.0+210',
                'security_mode' => 'tracking_only',
            ]);
    }

    private function firstOrCreateRole(string $name): Role
    {
        return Role::firstOrCreate(
            [
                'name' => $name,
                'guard_name' => 'web',
            ],
            [
                'display_name' => $name,
                'description' => $name . ' role for device registry tests',
                'module' => 'auth',
            ]
        );
    }
}
