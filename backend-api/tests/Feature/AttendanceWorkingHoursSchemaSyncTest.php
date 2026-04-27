<?php

namespace Tests\Feature;

use App\Models\AttendanceSchema;
use App\Models\AttendanceSchemaAssignment;
use App\Models\User;
use App\Services\AttendanceSchemaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceWorkingHoursSchemaSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_working_hours_uses_effective_assigned_schema_for_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-28 07:00:00'));

        $admin = User::factory()->create();
        $student = $this->createSiswaUser();

        $schema = AttendanceSchema::create([
            'schema_name' => 'Skema Siswa Khusus',
            'schema_type' => 'custom',
            'is_active' => true,
            'is_default' => false,
            'version' => 1,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '09:10:00',
            'siswa_jam_pulang' => '16:10:00',
            'siswa_toleransi' => 20,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'updated_by' => $admin->id,
        ]);

        AttendanceSchemaAssignment::create([
            'user_id' => $student->id,
            'attendance_setting_id' => $schema->id,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'assignment_type' => 'manual',
            'assigned_by' => $admin->id,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/simple-attendance/working-hours')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');
        $this->assertStringStartsWith('09:10', (string) ($data['jam_masuk'] ?? ''));
        $this->assertStringStartsWith('16:10', (string) ($data['jam_pulang'] ?? ''));
        $this->assertSame(20, (int) ($data['toleransi'] ?? 0));
        $this->assertSame('schema_effective', (string) ($data['source'] ?? ''));
        $this->assertSame($schema->id, (int) ($data['schema_id'] ?? 0));
    }

    public function test_working_hours_refreshes_after_schema_update_via_controller(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-28 07:00:00'));

        $this->withoutMiddleware(PermissionMiddleware::class);

        $admin = User::factory()->create();
        $student = $this->createSiswaUser();

        $schema = AttendanceSchema::create([
            'schema_name' => 'Skema Dynamic',
            'schema_type' => 'custom',
            'is_active' => true,
            'is_default' => false,
            'version' => 1,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '08:00:00',
            'siswa_jam_pulang' => '15:30:00',
            'siswa_toleransi' => 15,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'updated_by' => $admin->id,
        ]);

        AttendanceSchemaAssignment::create([
            'user_id' => $student->id,
            'attendance_setting_id' => $schema->id,
            'start_date' => now()->toDateString(),
            'end_date' => null,
            'is_active' => true,
            'assignment_type' => 'manual',
            'assigned_by' => $admin->id,
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/simple-attendance/working-hours')
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.source', 'schema_effective');

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/attendance-schemas/{$schema->id}", [
                'schema_name' => 'Skema Dynamic Updated',
                'schema_type' => 'custom',
                'jam_masuk_default' => '07:00:00',
                'jam_pulang_default' => '15:00:00',
                'siswa_jam_masuk' => '08:30:00',
                'siswa_jam_pulang' => '16:00:00',
                'siswa_toleransi' => 25,
                'minimal_open_time_staff' => 70,
                'minimal_open_time_siswa' => 70,
                'wajib_gps' => true,
                'wajib_foto' => true,
                'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
                'is_active' => true,
                'is_default' => false,
                'is_mandatory' => true,
                'priority' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/simple-attendance/working-hours')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');
        $this->assertStringStartsWith('08:30', (string) ($data['jam_masuk'] ?? ''));
        $this->assertStringStartsWith('16:00', (string) ($data['jam_pulang'] ?? ''));
        $this->assertSame(25, (int) ($data['toleransi'] ?? 0));
    }

    public function test_global_settings_does_not_create_duplicate_when_default_is_inactive(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-28 07:00:00'));

        $user = $this->createSiswaUser();

        $global = AttendanceSchema::create([
            'schema_name' => 'Default Schema',
            'schema_type' => 'global',
            'is_active' => false,
            'is_default' => true,
            'version' => 1,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
        ]);

        $first = $this->actingAs($user, 'sanctum')
            ->getJson('/api/simple-attendance/global')
            ->assertOk()
            ->assertJsonPath('status', 'success');
        $second = $this->actingAs($user, 'sanctum')
            ->getJson('/api/simple-attendance/global')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $this->assertSame($global->id, (int) $first->json('data.id'));
        $this->assertSame($global->id, (int) $second->json('data.id'));
        $this->assertDatabaseCount('attendance_settings', 1);
        $this->assertEquals(
            ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
            $first->json('data.hari_kerja')
        );
    }

    public function test_effective_schema_endpoint_is_consistent_with_working_hours_source(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-28 07:00:00'));

        $student = $this->createSiswaUser();

        AttendanceSchema::create([
            'schema_name' => 'Default Global',
            'schema_type' => 'global',
            'is_active' => true,
            'is_default' => true,
            'version' => 1,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
        ]);

        $effective = AttendanceSchema::create([
            'schema_name' => 'SMANIS',
            'schema_type' => 'hari_kerja_6',
            'priority' => 999,
            'is_active' => true,
            'is_default' => false,
            'version' => 1,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '06:30:00',
            'siswa_jam_pulang' => '15:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
        ]);

        $workingHours = $this->actingAs($student, 'sanctum')
            ->getJson('/api/simple-attendance/working-hours')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $effectiveSchema = $this->actingAs($student, 'sanctum')
            ->getJson('/api/attendance-schemas/user/' . $student->id . '/effective')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame($effective->id, (int) $workingHours->json('data.schema_id'));
        $this->assertSame($effective->id, (int) $effectiveSchema->json('data.id'));
        $this->assertSame('SMANIS', (string) $effectiveSchema->json('data.schema_name'));
        $this->assertEquals(
            ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'],
            $workingHours->json('data.hari_kerja')
        );
    }

    public function test_working_hours_falls_back_to_global_schema_model_when_effective_schema_resolution_fails(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-28 07:00:00'));

        $student = $this->createSiswaUser();

        $schema = AttendanceSchema::create([
            'schema_name' => 'Global Schema Fallback',
            'schema_type' => 'global',
            'is_active' => true,
            'is_default' => true,
            'version' => 1,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '06:45:00',
            'siswa_jam_pulang' => '14:15:00',
            'siswa_toleransi' => 12,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
        ]);

        $this->mock(AttendanceSchemaService::class, function ($mock) {
            $mock->shouldReceive('getEffectiveSchema')
                ->andThrow(new \RuntimeException('resolver failure'));
        });

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/simple-attendance/working-hours')
            ->assertOk()
            ->assertJsonPath('status', 'success');

        $data = $response->json('data');
        $this->assertSame('schema_global_fallback', (string) ($data['source'] ?? ''));
        $this->assertSame($schema->id, (int) ($data['schema_id'] ?? 0));
        $this->assertStringStartsWith('06:45', (string) ($data['jam_masuk'] ?? ''));
        $this->assertStringStartsWith('14:15', (string) ($data['jam_pulang'] ?? ''));
        $this->assertSame(12, (int) ($data['toleransi'] ?? 0));
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
}
