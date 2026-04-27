<?php

namespace Tests\Feature;

use App\Models\AttendanceDisciplineOverride;
use App\Models\AttendanceSchema;
use App\Models\AttendanceSchemaAssignment;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Services\AttendanceDisciplineService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceDisciplineOverrideIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_discipline_threshold_config_uses_global_default_not_assigned_schema(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 07:00:00'));
        $student = $this->createSiswaUser();
        $admin = User::factory()->create();

        $globalSchema = $this->createSchema('Global Default', [
            'schema_type' => 'global',
            'is_default' => true,
            'priority' => 0,
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 1200,
            'alpha_days_semester_limit' => 8,
            'late_minutes_monthly_limit' => 120,
        ]);

        $customSchema = $this->createSchema('Schema Khusus', [
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 333,
            'alpha_days_semester_limit' => 2,
            'late_minutes_monthly_limit' => 30,
        ]);

        AttendanceSchemaAssignment::create([
            'user_id' => $student->id,
            'attendance_setting_id' => $customSchema->id,
            'start_date' => '2026-04-01',
            'end_date' => null,
            'is_active' => true,
            'assignment_type' => 'manual',
            'assigned_by' => $admin->id,
        ]);

        $this->assertSame($customSchema->id, app(\App\Services\AttendanceSchemaService::class)->getEffectiveSchema($student)?->id);

        $config = app(AttendanceDisciplineService::class)->resolveThresholdConfig($student);

        $this->assertSame('global', $config['config_source']);
        $this->assertSame($globalSchema->id, $config['schema_id']);
        $this->assertSame(1200, $config['total_violation_minutes_semester_limit']);
        $this->assertSame(8, $config['alpha_days_semester_limit']);
        $this->assertSame(120, $config['late_minutes_monthly_limit']);
    }

    public function test_discipline_override_priority_is_user_then_kelas_then_tingkat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 07:00:00'));
        $tahunAjaran = TahunAjaran::query()->firstOrCreate([
            'nama' => '2025/2026',
        ], [
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'status' => TahunAjaran::STATUS_ACTIVE,
        ]);

        $tingkat = Tingkat::create([
            'nama' => 'XII',
            'kode' => 'XII',
            'urutan' => 12,
            'is_active' => true,
        ]);

        $kelasA = Kelas::create([
            'nama_kelas' => 'XII IPA 1',
            'tingkat_id' => $tingkat->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);

        $kelasB = Kelas::create([
            'nama_kelas' => 'XII IPA 2',
            'tingkat_id' => $tingkat->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);

        $studentWithUserOverride = $this->createSiswaUser();
        $studentWithClassOverride = $this->createSiswaUser();
        $studentWithTingkatOverride = $this->createSiswaUser();

        $this->attachStudentToClass($studentWithUserOverride, $kelasA);
        $this->attachStudentToClass($studentWithClassOverride, $kelasA);
        $this->attachStudentToClass($studentWithTingkatOverride, $kelasB);

        $this->createSchema('Global Default', [
            'schema_type' => 'global',
            'is_default' => true,
            'priority' => 0,
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 1200,
            'alpha_days_semester_limit' => 8,
            'late_minutes_monthly_limit' => 120,
        ]);

        AttendanceDisciplineOverride::create([
            'scope_type' => 'tingkat',
            'target_tingkat_id' => $tingkat->id,
            'is_active' => true,
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 900,
            'alpha_days_semester_limit' => 7,
            'late_minutes_monthly_limit' => 90,
            'semester_total_violation_mode' => 'monitor_only',
            'semester_alpha_mode' => 'alertable',
            'monthly_late_mode' => 'monitor_only',
        ]);

        AttendanceDisciplineOverride::create([
            'scope_type' => 'kelas',
            'target_kelas_id' => $kelasA->id,
            'is_active' => true,
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 600,
            'alpha_days_semester_limit' => 5,
            'late_minutes_monthly_limit' => 60,
            'semester_total_violation_mode' => 'alertable',
            'semester_alpha_mode' => 'alertable',
            'monthly_late_mode' => 'monitor_only',
        ]);

        AttendanceDisciplineOverride::create([
            'scope_type' => 'user',
            'target_user_id' => $studentWithUserOverride->id,
            'is_active' => true,
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 300,
            'alpha_days_semester_limit' => 3,
            'late_minutes_monthly_limit' => 30,
            'semester_total_violation_mode' => 'alertable',
            'semester_alpha_mode' => 'alertable',
            'monthly_late_mode' => 'alertable',
        ]);

        $service = app(AttendanceDisciplineService::class);

        $userConfig = $service->resolveThresholdConfig($studentWithUserOverride);
        $classConfig = $service->resolveThresholdConfig($studentWithClassOverride);
        $tingkatConfig = $service->resolveThresholdConfig($studentWithTingkatOverride);

        $this->assertSame('override', $userConfig['config_source']);
        $this->assertSame('user', $userConfig['override_scope_type']);
        $this->assertSame(300, $userConfig['total_violation_minutes_semester_limit']);

        $this->assertSame('override', $classConfig['config_source']);
        $this->assertSame('kelas', $classConfig['override_scope_type']);
        $this->assertSame(600, $classConfig['total_violation_minutes_semester_limit']);

        $this->assertSame('override', $tingkatConfig['config_source']);
        $this->assertSame('tingkat', $tingkatConfig['override_scope_type']);
        $this->assertSame(900, $tingkatConfig['total_violation_minutes_semester_limit']);
    }

    public function test_admin_can_crud_discipline_override_from_simple_attendance_routes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 07:00:00'));
        $this->withoutMiddleware(PermissionMiddleware::class);

        $admin = User::factory()->create();
        $student = $this->createSiswaUser();

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/simple-attendance/discipline-overrides', [
                'scope_type' => 'user',
                'target_user_id' => $student->id,
                'discipline_thresholds_enabled' => true,
                'total_violation_minutes_semester_limit' => 450,
                'alpha_days_semester_limit' => 4,
                'late_minutes_monthly_limit' => 45,
                'semester_total_violation_mode' => 'alertable',
                'semester_alpha_mode' => 'alertable',
                'monthly_late_mode' => 'monitor_only',
                'notes' => 'Aturan khusus siswa akhir',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'success');

        $overrideId = (int) $response->json('data.id');

        $this->assertDatabaseHas('attendance_discipline_overrides', [
            'id' => $overrideId,
            'scope_type' => 'user',
            'target_user_id' => $student->id,
            'total_violation_minutes_semester_limit' => 450,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/simple-attendance/discipline-overrides?include_inactive=true')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.active', 1);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/simple-attendance/discipline-overrides/{$overrideId}", [
                'scope_type' => 'user',
                'target_user_id' => $student->id,
                'discipline_thresholds_enabled' => true,
                'total_violation_minutes_semester_limit' => 500,
                'alpha_days_semester_limit' => 5,
                'late_minutes_monthly_limit' => 50,
                'semester_total_violation_mode' => 'monitor_only',
                'semester_alpha_mode' => 'alertable',
                'monthly_late_mode' => 'alertable',
                'notes' => 'Direvisi',
            ])
            ->assertOk()
            ->assertJsonPath('data.total_violation_minutes_semester_limit', 500)
            ->assertJsonPath('data.monthly_late_mode', 'alertable');

        $this->actingAs($admin, 'sanctum')
            ->deleteJson("/api/simple-attendance/discipline-overrides/{$overrideId}")
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertDatabaseMissing('attendance_discipline_overrides', [
            'id' => $overrideId,
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
        $role = Role::firstOrCreate([
            'name' => 'Siswa',
            'guard_name' => 'web',
        ], [
            'display_name' => 'Siswa',
        ]);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa ' . fake()->unique()->numerify('###'),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'nis' => fake()->unique()->numerify('########'),
            'nisn' => fake()->unique()->numerify('##########'),
        ]);
        $student->assignRole($role);

        return $student;
    }

    private function attachStudentToClass(User $student, Kelas $kelas): void
    {
        $tahunAjaran = TahunAjaran::query()->firstOrCreate([
            'nama' => '2025/2026',
        ], [
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'status' => TahunAjaran::STATUS_ACTIVE,
        ]);

        $student->kelas()->attach($kelas->id, [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
