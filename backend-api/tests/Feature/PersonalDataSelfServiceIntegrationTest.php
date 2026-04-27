<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PersonalDataSelfServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seedRole(RoleNames::SUPER_ADMIN, 'Super Admin');
        $this->seedRole(RoleNames::SISWA, 'Siswa');
        $this->seedRole(RoleNames::GURU, 'Guru');
    }

    public function test_super_admin_cannot_access_personal_data_self_service_endpoint(): void
    {
        $superAdmin = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $superAdmin->assignRole(RoleNames::SUPER_ADMIN);

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/me/personal-data');

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_student_can_update_self_profile_but_cannot_change_email(): void
    {
        $student = User::factory()->create([
            'username' => '252610777',
            'nis' => '252610777',
            'nisn' => '0107770001',
            'email' => '252610777@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'P',
        ]);
        $student->assignRole(RoleNames::SISWA);
        $student->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'no_hp_siswa' => '081200000001',
        ]);

        $forbiddenEmailResponse = $this->actingAs($student, 'sanctum')
            ->patchJson('/api/me/personal-data', [
                'email' => 'newmail@example.com',
            ]);

        $forbiddenEmailResponse->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $successResponse = $this->actingAs($student, 'sanctum')
            ->patchJson('/api/me/personal-data', [
                'nama_lengkap' => 'Siswa Update',
                'no_hp_siswa' => '081255551111',
                'penerima_kip' => true,
            ]);

        $successResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'nama_lengkap' => 'Siswa Update',
            'email' => '252610777@sman1sumbercirebon.sch.id',
        ]);

        $this->assertDatabaseHas('data_pribadi_siswa', [
            'user_id' => $student->id,
            'no_hp_siswa' => '081255551111',
            'penerima_kip' => true,
        ]);
    }

    public function test_employee_can_update_email_and_contact_from_personal_data_endpoint(): void
    {
        $employee = User::factory()->create([
            'email' => 'pegawai.awal@sman1sumbercirebon.sch.id',
            'nip' => '196809071991011999',
            'jenis_kelamin' => 'L',
        ]);
        $employee->assignRole(RoleNames::GURU);
        $employee->dataKepegawaian()->create([
            'status_kepegawaian' => 'ASN',
            'no_hp' => '081200000009',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->patchJson('/api/me/personal-data', [
                'email' => 'pegawai.update@sman1sumbercirebon.sch.id',
                'no_hp' => '081288889999',
                'alamat_jalan' => 'Jl. Melati No. 7',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'email' => 'pegawai.update@sman1sumbercirebon.sch.id',
        ]);

        $this->assertDatabaseHas('data_kepegawaian', [
            'user_id' => $employee->id,
            'no_hp' => '081288889999',
            'alamat_jalan' => 'Jl. Melati No. 7',
        ]);
    }

    public function test_student_cannot_update_restricted_system_field_via_self_service(): void
    {
        $student = User::factory()->create([
            'username' => '252611001',
            'nis' => '252611001',
            'email' => '252611001@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $student->assignRole(RoleNames::SISWA);
        $student->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'gps_tracking' => false,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->patchJson('/api/me/personal-data', [
                'gps_tracking' => true,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['gps_tracking']);

        $this->assertDatabaseHas('data_pribadi_siswa', [
            'user_id' => $student->id,
            'gps_tracking' => false,
        ]);
    }

    public function test_employee_cannot_update_status_kepegawaian_via_self_service(): void
    {
        $employee = User::factory()->create([
            'email' => 'pegawai.locked@sman1sumbercirebon.sch.id',
            'nip' => '196809071991011888',
            'jenis_kelamin' => 'P',
            'status_kepegawaian' => 'ASN',
        ]);
        $employee->assignRole(RoleNames::GURU);
        $employee->dataKepegawaian()->create([
            'status_kepegawaian' => 'ASN',
            'no_hp' => '081200000011',
        ]);

        $response = $this->actingAs($employee, 'sanctum')
            ->patchJson('/api/me/personal-data', [
                'status_kepegawaian' => 'Honorer',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status_kepegawaian']);

        $this->assertDatabaseHas('users', [
            'id' => $employee->id,
            'status_kepegawaian' => 'ASN',
        ]);
    }

    private function seedRole(string $name, string $displayName): void
    {
        Role::updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            [
                'display_name' => $displayName,
                'description' => 'Role for personal data self-service test',
                'level' => 1,
                'is_active' => true,
            ]
        );
    }
}
