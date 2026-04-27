<?php

namespace Tests\Unit;

use App\Models\AttendanceSchema;
use App\Models\User;
use App\Services\ManualAttendanceService;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ManualAttendanceValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_validate_attendance_data_requires_jam_masuk_for_terlambat_status(): void
    {
        $service = app(ManualAttendanceService::class);
        $student = $this->createStudent();

        $errors = $service->validateAttendanceData([
            'user_id' => $student->id,
            'tanggal' => now()->toDateString(),
            'status' => 'terlambat',
            'jam_masuk' => '',
        ]);

        $this->assertArrayHasKey('jam_masuk', $errors);
    }

    public function test_validate_attendance_data_rejects_terlambat_when_check_in_is_not_after_scheduled_start(): void
    {
        $service = app(ManualAttendanceService::class);
        $student = $this->createStudent();

        $errors = $service->validateAttendanceData([
            'user_id' => $student->id,
            'tanggal' => now()->toDateString(),
            'status' => 'terlambat',
            'jam_masuk' => '06:30',
        ]);

        $this->assertSame(
            'Jam masuk harus melebihi jam masuk terjadwal untuk status terlambat',
            $errors['jam_masuk'] ?? null
        );
    }

    public function test_validate_attendance_data_allows_one_minute_late_even_when_tolerance_is_wider(): void
    {
        $service = app(ManualAttendanceService::class);
        $student = $this->createStudent();

        $errors = $service->validateAttendanceData([
            'user_id' => $student->id,
            'tanggal' => now()->toDateString(),
            'status' => 'terlambat',
            'jam_masuk' => '06:31',
        ]);

        $this->assertArrayNotHasKey('jam_masuk', $errors);
    }

    private function createStudent(): User
    {
        AttendanceSchema::create([
            'schema_name' => 'Manual Attendance Validation Schema',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00',
            'jam_pulang_default' => '15:00',
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '06:30',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 20,
            'minimal_open_time_siswa' => 70,
            'discipline_thresholds_enabled' => false,
            'violation_minutes_threshold' => 300,
            'violation_percentage_threshold' => 10.00,
        ]);

        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        return $student;
    }
}
