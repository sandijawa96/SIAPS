<?php

namespace Tests\Feature;

use App\Models\AttendanceSchema;
use App\Models\AttendanceSchemaAssignment;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceSchemaAssignmentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigning_new_schema_replaces_existing_assignment_without_overlap(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 07:00:00'));
        $this->withoutMiddleware(PermissionMiddleware::class);

        $admin = User::factory()->create();
        $student = $this->createSiswaUser();

        $oldSchema = $this->createSchema('Schema Lama');
        $newSchema = $this->createSchema('Schema Baru');

        AttendanceSchemaAssignment::create([
            'user_id' => $student->id,
            'attendance_setting_id' => $oldSchema->id,
            'start_date' => '2026-03-01',
            'end_date' => null,
            'is_active' => true,
            'assignment_type' => 'manual',
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/attendance-schemas/{$newSchema->id}/assign-user", [
                'user_id' => $student->id,
                'start_date' => '2026-03-10',
                'notes' => 'Pindah skema aktif',
                'assignment_type' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('attendance_schema_assignments', [
            'user_id' => $student->id,
            'attendance_setting_id' => $oldSchema->id,
            'start_date' => '2026-03-01 00:00:00',
            'end_date' => '2026-03-09 00:00:00',
            'is_active' => true,
            'assignment_type' => 'manual',
        ]);

        $this->assertDatabaseHas('attendance_schema_assignments', [
            'user_id' => $student->id,
            'attendance_setting_id' => $newSchema->id,
            'start_date' => '2026-03-10 00:00:00',
            'end_date' => null,
            'is_active' => true,
            'assignment_type' => 'manual',
            'notes' => 'Pindah skema aktif',
        ]);

        $this->assertSame(
            1,
            AttendanceSchemaAssignment::forUser($student->id)->current('2026-03-11')->count()
        );

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/attendance-schemas/user/{$student->id}/effective")
            ->assertOk()
            ->assertJsonPath('data.id', $newSchema->id)
            ->assertJsonPath('assignment_type', 'manual');
    }

    public function test_temporary_override_preserves_suffix_of_previous_assignment(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 07:00:00'));
        $this->withoutMiddleware(PermissionMiddleware::class);

        $admin = User::factory()->create();
        $student = $this->createSiswaUser();

        $baseSchema = $this->createSchema('Schema Dasar');
        $overrideSchema = $this->createSchema('Schema Override');

        AttendanceSchemaAssignment::create([
            'user_id' => $student->id,
            'attendance_setting_id' => $baseSchema->id,
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-31',
            'is_active' => true,
            'assignment_type' => 'manual',
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/attendance-schemas/{$overrideSchema->id}/assign-user", [
                'user_id' => $student->id,
                'start_date' => '2026-03-10',
                'end_date' => '2026-03-20',
                'assignment_type' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('attendance_schema_assignments', [
            'user_id' => $student->id,
            'attendance_setting_id' => $baseSchema->id,
            'start_date' => '2026-03-01 00:00:00',
            'end_date' => '2026-03-09 00:00:00',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('attendance_schema_assignments', [
            'user_id' => $student->id,
            'attendance_setting_id' => $baseSchema->id,
            'start_date' => '2026-03-21 00:00:00',
            'end_date' => '2026-03-31 00:00:00',
            'is_active' => true,
        ]);

        $this->assertSame(
            $baseSchema->id,
            (int) app(\App\Services\AttendanceSchemaService::class)->getEffectiveSchema($student, '2026-03-25')?->id
        );
    }

    public function test_effective_schema_respects_target_kelas_and_beats_generic_schema(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 07:00:00'));
        $this->withoutMiddleware(PermissionMiddleware::class);

        $student = $this->createSiswaUser();
        [$kelasCocok, $kelasLain] = $this->attachStudentToAcademicContext($student);

        $globalDefault = $this->createSchema('Global Default', [
            'schema_type' => 'global',
            'is_default' => true,
            'priority' => 0,
        ]);

        $genericSchema = $this->createSchema('Generic Tinggi', [
            'priority' => 100,
            'is_default' => false,
        ]);

        $this->createSchema('Kelas Lain', [
            'priority' => 500,
            'is_default' => false,
            'target_kelas_ids' => [$kelasLain->id],
        ]);

        $kelasSchema = $this->createSchema('Kelas Khusus', [
            'priority' => 30,
            'is_default' => false,
            'target_kelas_ids' => [$kelasCocok->id],
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson("/api/attendance-schemas/user/{$student->id}/effective")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($kelasSchema->id, (int) $response->json('data.id'));
        $this->assertSame('auto', (string) $response->json('assignment_type'));
        $this->assertNotSame($genericSchema->id, (int) $response->json('data.id'));
        $this->assertNotSame($globalDefault->id, (int) $response->json('data.id'));
    }

    public function test_auto_assign_endpoint_uses_selected_schema_rule_and_skips_manual_assignments(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 07:00:00'));
        $this->withoutMiddleware(PermissionMiddleware::class);

        $admin = User::factory()->create();
        $studentWithManual = $this->createSiswaUser();
        $studentAuto = $this->createSiswaUser();

        [$kelasCocok] = $this->attachStudentToAcademicContext($studentWithManual);
        $this->attachStudentToAcademicContext($studentAuto, $kelasCocok);

        $manualSchema = $this->createSchema('Manual Existing', [
            'is_default' => false,
            'priority' => 20,
        ]);

        $targetSchema = $this->createSchema('Target Kelas', [
            'is_default' => false,
            'priority' => 40,
            'target_kelas_ids' => [$kelasCocok->id],
        ]);

        AttendanceSchemaAssignment::create([
            'user_id' => $studentWithManual->id,
            'attendance_setting_id' => $manualSchema->id,
            'start_date' => '2026-03-01',
            'end_date' => null,
            'is_active' => true,
            'assignment_type' => 'manual',
            'assigned_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/attendance-schemas/auto-assign', [
                'schema_id' => $targetSchema->id,
                'user_ids' => [$studentWithManual->id, $studentAuto->id],
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(2, (int) $response->json('data.summary.total_users'));
        $this->assertSame(1, (int) $response->json('data.summary.assigned_count'));
        $this->assertSame(1, (int) $response->json('data.summary.manual_skipped_count'));

        $this->assertDatabaseHas('attendance_schema_assignments', [
            'user_id' => $studentAuto->id,
            'attendance_setting_id' => $targetSchema->id,
            'assignment_type' => 'auto',
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('attendance_schema_assignments', [
            'user_id' => $studentWithManual->id,
            'attendance_setting_id' => $manualSchema->id,
            'assignment_type' => 'manual',
            'is_active' => true,
        ]);
    }

    private function createSchema(string $name, array $overrides = []): AttendanceSchema
    {
        return AttendanceSchema::create(array_merge([
            'schema_name' => $name,
            'schema_type' => 'custom',
            'is_active' => true,
            'is_default' => false,
            'is_mandatory' => true,
            'priority' => 10,
            'version' => 1,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
            'siswa_toleransi' => 10,
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
        ], $overrides));
    }

    private function createSiswaUser(): User
    {
        $user = User::factory()->create(['status_kepegawaian' => 'Siswa']);
        $role = Role::firstOrCreate(
            ['name' => 'Siswa'],
            [
                'display_name' => 'Siswa',
                'description' => 'Siswa role for attendance tests',
                'level' => 0,
                'is_active' => true,
                'guard_name' => 'web',
            ]
        );
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0:Kelas,1:Kelas}
     */
    private function attachStudentToAcademicContext(User $student, ?Kelas $targetKelas = null): array
    {
        $tahunAjaran = TahunAjaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'semester' => 'full',
            'is_active' => true,
            'status' => 'active',
        ]);

        $tingkatA = Tingkat::create([
            'nama' => 'XII',
            'kode' => '12',
            'urutan' => 12,
            'is_active' => true,
        ]);

        $tingkatB = Tingkat::create([
            'nama' => 'XI',
            'kode' => '11',
            'urutan' => 11,
            'is_active' => true,
        ]);

        $kelasCocok = $targetKelas ?: Kelas::create([
            'nama_kelas' => 'XII IPA 1',
            'tingkat_id' => $tingkatA->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);

        $kelasLain = Kelas::create([
            'nama_kelas' => 'XI IPS 1',
            'tingkat_id' => $tingkatB->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);

        $student->kelas()->syncWithoutDetaching([
            $kelasCocok->id => [
                'tahun_ajaran_id' => $tahunAjaran->id,
                'status' => 'aktif',
                'tanggal_masuk' => now()->toDateString(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        return [$kelasCocok, $kelasLain];
    }
}
