<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class StudentWebLoginEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create([
            'name' => 'Siswa_web',
            'display_name' => 'Siswa',
            'guard_name' => 'web',
        ]);

        Role::create([
            'name' => 'Siswa_api',
            'display_name' => 'Siswa',
            'guard_name' => 'api',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_student_can_login_to_web_without_touching_mobile_device_binding(): void
    {
        $student = User::factory()->create([
            'username' => '24001234',
            'email' => 'siswa@test.com',
            'device_id' => 'bound-device-123',
            'device_name' => 'Android Siswa',
            'device_locked' => true,
            'device_bound_at' => now()->subDay(),
            'last_device_activity' => null,
        ]);
        $student->assignRole('Siswa_web');
        $student->dataPribadiSiswa()->create([
            'tanggal_lahir' => '2008-05-16',
        ]);

        $response = $this->postJson('/api/web/login-siswa', [
            'nis' => '24001234',
            'tanggal_lahir' => '16/05/2008',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.auth_type', 'sanctum')
            ->assertJsonPath('data.user.device_id', 'bound-device-123')
            ->assertJsonPath('data.user.device_locked', true);

        $student->refresh();
        $this->assertSame('bound-device-123', $student->device_id);
        $this->assertSame('Android Siswa', $student->device_name);
        $this->assertTrue((bool) $student->device_locked);
        $this->assertNull($student->last_device_activity);
    }

    public function test_mobile_student_login_still_requires_device_binding_fields(): void
    {
        $student = User::factory()->create([
            'username' => '24005678',
            'email' => 'siswa-mobile@test.com',
        ]);
        $student->assignRole('Siswa_web');
        $student->dataPribadiSiswa()->create([
            'tanggal_lahir' => '2008-07-21',
        ]);

        $response = $this->postJson('/api/mobile/login-siswa', [
            'nis' => '24005678',
            'tanggal_lahir' => '21/07/2008',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_id', 'device_name']);
    }

    public function test_student_web_session_cannot_submit_attendance_from_dashboard_client(): void
    {
        $student = User::factory()->create([
            'username' => '24009999',
            'email' => 'siswa-web-submit@test.com',
        ]);
        $student->assignRole('Siswa_web');

        $response = $this->actingAs($student, 'sanctum')
            ->withHeaders([
                'X-Client-Platform' => 'web',
                'X-Client-App' => 'dashboard-web',
            ])
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'MOBILE_APP_ONLY');
    }
}
