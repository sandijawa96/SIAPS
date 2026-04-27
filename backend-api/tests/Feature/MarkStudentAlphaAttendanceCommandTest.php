<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkStudentAlphaAttendanceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ignore_mobile_signal_is_rejected_for_today(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-11 10:00:00', config('app.timezone')));

        $this->artisan('attendance:mark-student-alpha', [
            '--date' => '2026-03-11',
            '--ignore-mobile-signal' => true,
            '--dry-run' => true,
        ])
            ->expectsOutput('Option --ignore-mobile-signal hanya boleh dipakai untuk tanggal lampau.')
            ->assertExitCode(2);
    }

    public function test_command_persists_schema_snapshot_on_created_auto_alpha_record(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 23:55:00', config('app.timezone')));

        $student = User::factory()->create([
            'device_id' => 'android-student-1',
            'device_locked' => true,
            'device_bound_at' => Carbon::parse('2026-03-01 08:00:00', config('app.timezone')),
        ]);
        $student->assignRole(RoleNames::SISWA);

        $schema = AttendanceSchema::create([
            'schema_name' => 'Snapshot Schema',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
        ]);

        $this->artisan('attendance:mark-student-alpha', [
            '--date' => '2026-03-10',
        ])->assertExitCode(0);

        $attendance = Absensi::query()
            ->where('user_id', $student->id)
            ->whereDate('tanggal', '2026-03-10')
            ->first();

        $this->assertNotNull($attendance);
        $this->assertSame('alpha', $attendance->status);
        $this->assertSame($schema->id, (int) $attendance->attendance_setting_id);
        $this->assertIsArray($attendance->settings_snapshot);
        $this->assertSame($schema->id, (int) data_get($attendance->settings_snapshot, 'schema.id'));
        $this->assertSame('07:00:00', data_get($attendance->settings_snapshot, 'working_hours.jam_masuk'));
        $this->assertSame('14:00:00', data_get($attendance->settings_snapshot, 'working_hours.jam_pulang'));
        $this->assertSame(10, (int) data_get($attendance->settings_snapshot, 'working_hours.toleransi'));
    }

    public function test_command_skips_students_when_effective_schema_does_not_require_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 23:55:00', config('app.timezone')));

        $student = User::factory()->create([
            'device_id' => 'android-student-2',
            'device_locked' => true,
            'device_bound_at' => Carbon::parse('2026-03-01 08:00:00', config('app.timezone')),
        ]);
        $student->assignRole(RoleNames::SISWA);

        AttendanceSchema::create([
            'schema_name' => 'Tidak Wajib Absensi',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => false,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
        ]);

        $this->artisan('attendance:mark-student-alpha', [
            '--date' => '2026-03-10',
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('absensi', [
            'user_id' => $student->id,
            'tanggal' => '2026-03-10',
        ]);
    }

    public function test_command_skips_students_when_effective_schema_disables_auto_alpha(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-10 23:55:00', config('app.timezone')));

        $student = User::factory()->create([
            'device_id' => 'android-student-3',
            'device_locked' => true,
            'device_bound_at' => Carbon::parse('2026-03-01 08:00:00', config('app.timezone')),
        ]);
        $student->assignRole(RoleNames::SISWA);

        AttendanceSchema::create([
            'schema_name' => 'Auto Alpha Nonaktif',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'auto_alpha_enabled' => false,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
        ]);

        $this->artisan('attendance:mark-student-alpha', [
            '--date' => '2026-03-10',
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('absensi', [
            'user_id' => $student->id,
            'tanggal' => '2026-03-10',
        ]);
    }
}
