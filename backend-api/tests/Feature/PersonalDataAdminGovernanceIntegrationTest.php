<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PersonalDataAdminGovernanceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seedRole(RoleNames::SUPER_ADMIN, 'Super Admin');
        $this->seedRole(RoleNames::ADMIN, 'Admin');
        $this->seedRole(RoleNames::SISWA, 'Siswa');
        $this->seedRole(RoleNames::WAKASEK_KESISWAAN, 'Wakasek Kesiswaan');
        $this->seedRole(RoleNames::GURU, 'Guru');

        foreach ([
            'manage_users',
            'view_personal_data_verification',
            'verify_personal_data_siswa',
            'verify_personal_data_pegawai',
        ] as $permissionName) {
            Permission::updateOrCreate(
                ['name' => $permissionName, 'guard_name' => 'web'],
                ['module' => 'users', 'display_name' => ucwords(str_replace('_', ' ', $permissionName))]
            );
        }
    }

    public function test_admin_with_manage_users_permission_can_update_target_user_personal_data(): void
    {
        $admin = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $admin->assignRole(RoleNames::ADMIN);
        $admin->givePermissionTo('manage_users');

        $student = User::factory()->create([
            'username' => '252610991',
            'nis' => '252610991',
            'nisn' => '0109991001',
            'email' => '252610991@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'P',
        ]);
        $student->assignRole(RoleNames::SISWA);
        $student->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'no_hp_siswa' => '081200001234',
        ]);

        $showResponse = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/users/{$student->id}/personal-data");

        $showResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $updateResponse = $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/users/{$student->id}/personal-data", [
                'nama_lengkap' => 'Siswa Dari Admin',
                'no_hp_siswa' => '081277778888',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $student->id,
            'nama_lengkap' => 'Siswa Dari Admin',
        ]);

        $this->assertDatabaseHas('data_pribadi_siswa', [
            'user_id' => $student->id,
            'no_hp_siswa' => '081277778888',
        ]);

        $this->assertActivityLogCreated(
            'personal_data_admin_update',
            'personal_data',
            $student->id,
            $admin->id
        );
    }

    public function test_user_without_manage_users_permission_cannot_access_admin_personal_data_route(): void
    {
        $teacher = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);

        $student = User::factory()->create([
            'username' => '252610992',
            'nis' => '252610992',
            'email' => '252610992@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'P',
        ]);
        $student->assignRole(RoleNames::SISWA);

        $response = $this->actingAs($teacher, 'sanctum')
            ->getJson("/api/users/{$student->id}/personal-data");

        $response->assertStatus(403);
    }

    public function test_admin_endpoint_rejects_super_admin_target(): void
    {
        $admin = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $admin->assignRole(RoleNames::ADMIN);
        $admin->givePermissionTo('manage_users');

        $targetSuperAdmin = User::factory()->create([
            'jenis_kelamin' => 'P',
        ]);
        $targetSuperAdmin->assignRole(RoleNames::SUPER_ADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/users/{$targetSuperAdmin->id}/personal-data");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
            ]);
    }

    public function test_super_admin_can_access_review_queue_and_submit_review_decision(): void
    {
        $superAdmin = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $superAdmin->assignRole(RoleNames::SUPER_ADMIN);
        $superAdmin->givePermissionTo('manage_users');

        $student = User::factory()->create([
            'username' => '252610993',
            'nis' => '252610993',
            'nisn' => '0109993001',
            'email' => '252610993@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'P',
        ]);
        $student->assignRole(RoleNames::SISWA);
        $student->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'no_hp_siswa' => '081233334444',
            'nama_ayah' => 'Ayah Test',
            'nama_ibu' => 'Ibu Test',
            'asal_sekolah' => 'SMP Test',
            'no_kk' => '3201000000011111',
        ]);

        $queueResponse = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/personal-data/review-queue?profile_type=siswa');

        $queueResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $decisionResponse = $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/personal-data/review-queue/{$student->id}/decision", [
                'action' => 'approve',
                'notes' => 'Data sudah valid',
            ]);

        $decisionResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertActivityLogCreated(
            'personal_data_review_approve',
            'personal_data_review',
            $student->id,
            $superAdmin->id
        );
    }

    public function test_non_super_admin_with_manage_users_permission_cannot_access_review_queue(): void
    {
        $admin = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $admin->assignRole(RoleNames::ADMIN);
        $admin->givePermissionTo('manage_users');

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/personal-data/review-queue');

        $response->assertStatus(403);
    }

    public function test_role_with_student_verification_permission_can_only_verify_student_personal_data(): void
    {
        $verifier = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $verifier->assignRole(RoleNames::WAKASEK_KESISWAAN);
        $verifier->givePermissionTo([
            'view_personal_data_verification',
            'verify_personal_data_siswa',
        ]);

        $student = User::factory()->create([
            'username' => '252611201',
            'nis' => '252611201',
            'nisn' => '0106112001',
            'email' => '252611201@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'P',
        ]);
        $student->assignRole(RoleNames::SISWA);
        $student->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'no_hp_siswa' => '081211112222',
        ]);

        $employee = User::factory()->create([
            'email' => 'guru.verif@sman1sumbercirebon.sch.id',
            'nip' => '196809071991011777',
            'jenis_kelamin' => 'L',
        ]);
        $employee->assignRole(RoleNames::GURU);
        $employee->dataKepegawaian()->create([
            'status_kepegawaian' => 'ASN',
            'no_hp' => '081233334444',
        ]);

        $this->actingAs($verifier, 'sanctum')
            ->getJson('/api/personal-data/review-queue?profile_type=siswa')
            ->assertStatus(200);

        $this->actingAs($verifier, 'sanctum')
            ->postJson("/api/personal-data/review-queue/{$student->id}/decision", [
                'action' => 'approve',
                'notes' => 'Data siswa valid',
            ])
            ->assertStatus(200);

        $this->actingAs($verifier, 'sanctum')
            ->postJson("/api/personal-data/review-queue/{$employee->id}/decision", [
                'action' => 'approve',
                'notes' => 'Data pegawai valid',
            ])
            ->assertStatus(403);
    }

    public function test_student_verifier_review_queue_only_returns_student_rows_even_when_requesting_all_types(): void
    {
        $verifier = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $verifier->assignRole(RoleNames::WAKASEK_KESISWAAN);
        $verifier->givePermissionTo([
            'view_personal_data_verification',
            'verify_personal_data_siswa',
        ]);

        $student = User::factory()->create([
            'username' => '252611301',
            'nis' => '252611301',
            'nisn' => '0106113001',
            'email' => '252611301@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'P',
        ]);
        $student->assignRole(RoleNames::SISWA);
        $student->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'no_hp_siswa' => '081211113333',
        ]);

        $employee = User::factory()->create([
            'email' => 'pegawai.scope@sman1sumbercirebon.sch.id',
            'nip' => '196809071991011779',
            'jenis_kelamin' => 'L',
        ]);
        $employee->assignRole(RoleNames::GURU);
        $employee->dataKepegawaian()->create([
            'status_kepegawaian' => 'ASN',
            'no_hp' => '081233335555',
        ]);

        $response = $this->actingAs($verifier, 'sanctum')
            ->getJson('/api/personal-data/review-queue?profile_type=all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $rows = collect($response->json('data.data', []));
        $rowUserIds = $rows->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($student->id, $rowUserIds);
        $this->assertNotContains($employee->id, $rowUserIds);
        $this->assertTrue($rows->every(fn (array $row) => ($row['profile_type'] ?? '') === 'siswa'));
    }

    public function test_employee_verifier_review_queue_only_returns_employee_rows_even_when_requesting_all_types(): void
    {
        $verifier = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $verifier->assignRole(RoleNames::GURU);
        $verifier->givePermissionTo([
            'view_personal_data_verification',
            'verify_personal_data_pegawai',
        ]);

        $student = User::factory()->create([
            'username' => '252611302',
            'nis' => '252611302',
            'nisn' => '0106113002',
            'email' => '252611302@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'P',
        ]);
        $student->assignRole(RoleNames::SISWA);
        $student->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'no_hp_siswa' => '081211114444',
        ]);

        $employee = User::factory()->create([
            'email' => 'pegawai.scope2@sman1sumbercirebon.sch.id',
            'nip' => '196809071991011780',
            'jenis_kelamin' => 'L',
        ]);
        $employee->assignRole(RoleNames::GURU);
        $employee->dataKepegawaian()->create([
            'status_kepegawaian' => 'ASN',
            'no_hp' => '081233336666',
        ]);

        $response = $this->actingAs($verifier, 'sanctum')
            ->getJson('/api/personal-data/review-queue?profile_type=all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $rows = collect($response->json('data.data', []));
        $rowUserIds = $rows->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($employee->id, $rowUserIds);
        $this->assertNotContains($student->id, $rowUserIds);
        $this->assertTrue($rows->every(fn (array $row) => ($row['profile_type'] ?? '') === 'pegawai'));
    }

    public function test_review_queue_supports_completion_tier_filter(): void
    {
        $superAdmin = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $superAdmin->assignRole(RoleNames::SUPER_ADMIN);
        $superAdmin->givePermissionTo('manage_users');

        $lowCompletionStudent = User::factory()->create([
            'username' => '252611101',
            'nis' => '252611101',
            'nisn' => '0106111001',
            'email' => '252611101@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $lowCompletionStudent->assignRole(RoleNames::SISWA);
        $lowCompletionStudent->dataPribadiSiswa()->create([
            'status' => 'aktif',
        ]);

        $completeStudent = User::factory()->create([
            'username' => '252611102',
            'nis' => '252611102',
            'nisn' => '0106111002',
            'email' => '252611102@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'P',
        ]);
        $completeStudent->assignRole(RoleNames::SISWA);
        $completeStudent->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'no_hp_siswa' => '081244441111',
            'nama_ayah' => 'Ayah Lengkap',
            'nama_ibu' => 'Ibu Lengkap',
            'no_kk' => '3201000000012222',
            'asal_sekolah' => 'SMP Lengkap',
        ]);

        $tingkat = Tingkat::create([
            'nama' => 'X',
            'kode' => 'X',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $tahunAjaran = TahunAjaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'semester' => 'full',
            'status' => 'active',
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $kelas = Kelas::create([
            'nama_kelas' => 'X-1',
            'tingkat_id' => $tingkat->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'jumlah_siswa' => 1,
            'is_active' => true,
        ]);

        $kelas->siswa()->attach($completeStudent->id, [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => '2026-07-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $allResponse = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/personal-data/review-queue?profile_type=siswa');

        $allResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $allRows = collect($allResponse->json('data.data', []));
        $targetRow = $allRows->firstWhere('user_id', $completeStudent->id) ?? $allRows->first();
        $this->assertNotNull($targetRow);

        $targetTier = (string) ($targetRow['completion_tier'] ?? '');
        $this->assertNotSame('', $targetTier);
        $this->assertNotSame('all', $targetTier);

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/personal-data/review-queue?profile_type=siswa&completion_tier=' . $targetTier);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $rows = collect($response->json('data.data', []));
        $userIds = $rows->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $this->assertContains($completeStudent->id, $userIds);
        $this->assertTrue($rows->every(fn (array $row) => ($row['completion_tier'] ?? null) === $targetTier));
    }

    public function test_review_queue_can_sort_by_status_priority(): void
    {
        $superAdmin = User::factory()->create([
            'jenis_kelamin' => 'L',
        ]);
        $superAdmin->assignRole(RoleNames::SUPER_ADMIN);
        $superAdmin->givePermissionTo('manage_users');

        $needsRevisionStudent = User::factory()->create([
            'username' => '252611103',
            'nis' => '252611103',
            'nisn' => '0106111003',
            'email' => '252611103@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $needsRevisionStudent->assignRole(RoleNames::SISWA);
        $needsRevisionStudent->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'no_hp_siswa' => '081255551111',
        ]);

        $waitingStudent = User::factory()->create([
            'username' => '252611104',
            'nis' => '252611104',
            'nisn' => '0106111004',
            'email' => '252611104@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'P',
        ]);
        $waitingStudent->assignRole(RoleNames::SISWA);
        $waitingStudent->dataPribadiSiswa()->create([
            'status' => 'aktif',
            'no_hp_siswa' => '081266661111',
        ]);

        $this->actingAs($superAdmin, 'sanctum')
            ->postJson("/api/personal-data/review-queue/{$needsRevisionStudent->id}/decision", [
                'action' => 'needs_revision',
                'notes' => 'Perlu lengkapi data',
            ])
            ->assertStatus(200);

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/personal-data/review-queue?profile_type=siswa&sort_by=status_verifikasi&sort_direction=asc');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $rows = collect($response->json('data.data', []));
        $firstRow = $rows->first();

        $this->assertNotNull($firstRow);
        $this->assertSame($needsRevisionStudent->id, (int) ($firstRow['user_id'] ?? 0));
        $this->assertSame('perlu_perbaikan', (string) ($firstRow['status_verifikasi'] ?? ''));
    }

    private function seedRole(string $name, string $displayName): void
    {
        Role::updateOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            [
                'display_name' => $displayName,
                'description' => 'Role for admin personal data governance test',
                'level' => 1,
                'is_active' => true,
            ]
        );
    }

    private function assertActivityLogCreated(string $action, string $module, int $subjectId, int $actorId): void
    {
        if (Schema::hasColumn('activity_logs', 'user_id') && Schema::hasColumn('activity_logs', 'action')) {
            $this->assertDatabaseHas('activity_logs', [
                'user_id' => $actorId,
                'action' => $action,
                'module' => $module,
                'subject_id' => $subjectId,
            ]);

            return;
        }

        $this->assertDatabaseHas('activity_logs', [
            'causer_id' => $actorId,
            'event' => $action,
            'module' => $module,
            'subject_id' => $subjectId,
        ]);
    }
}
