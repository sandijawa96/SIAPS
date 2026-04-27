<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AbsensiStatisticsAlphaMinutesIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_statistics_returns_alpha_minutes_metric(): void
    {
        Carbon::setTestNow('2026-08-31 18:00:00');

        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        AttendanceSchema::create([
            'schema_name' => 'Default Schema',
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
            'siswa_jam_masuk' => '07:00',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
            'discipline_thresholds_enabled' => false,
            'violation_minutes_threshold' => 300,
            'violation_percentage_threshold' => 10.00,
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-03',
            'status' => 'alpha',
            'metode_absensi' => 'manual',
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-04',
            'status' => 'alpha',
            'metode_absensi' => 'manual',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/absensi/statistics?month=8&year=2026')
            ->assertStatus(200)
            ->assertJsonPath('data.total_alpha', 2)
            ->assertJsonPath('data.total_alpha_menit', 840)
            ->assertJsonPath('data.total_pelanggaran_menit', 840)
            ->assertJsonPath('data.batas_pelanggaran_menit', 300)
            ->assertJsonPath('data.melewati_batas_pelanggaran', true);
    }

    public function test_statistics_returns_new_discipline_threshold_snapshot(): void
    {
        Carbon::setTestNow('2026-08-31 18:00:00');

        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        AttendanceSchema::create([
            'schema_name' => 'Default Schema New Threshold',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00',
            'jam_pulang_default' => '15:00',
            'toleransi_default' => 0,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '07:00',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 0,
            'minimal_open_time_siswa' => 70,
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 1200,
            'alpha_days_semester_limit' => 2,
            'late_minutes_monthly_limit' => 15,
            'monthly_late_mode' => 'monitor_only',
            'semester_total_violation_mode' => 'monitor_only',
            'semester_alpha_mode' => 'alertable',
            'notify_wali_kelas_on_alpha_limit' => true,
            'notify_kesiswaan_on_alpha_limit' => true,
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-04',
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '14:00:00',
            'status' => 'hadir',
            'metode_absensi' => 'manual',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/absensi/statistics?month=8&year=2026')
            ->assertStatus(200)
            ->assertJsonPath('data.discipline_thresholds.mode', 'monthly')
            ->assertJsonPath('data.discipline_thresholds.monthly_late.limit', 15)
            ->assertJsonPath('data.discipline_thresholds.monthly_late.minutes', 30)
            ->assertJsonPath('data.discipline_thresholds.monthly_late.exceeded', true)
            ->assertJsonPath('data.discipline_thresholds.semester_total_violation.limit', 1200)
            ->assertJsonPath('data.discipline_thresholds.semester_alpha.limit', 2)
            ->assertJsonPath('data.batas_pelanggaran_menit', 1200)
            ->assertJsonPath('data.batas_pelanggaran_persen', 0)
            ->assertJsonPath('data.melewati_batas_pelanggaran', true);
    }

    public function test_statistics_late_threshold_counts_from_scheduled_start_time(): void
    {
        Carbon::setTestNow('2026-08-31 18:00:00');

        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        AttendanceSchema::create([
            'schema_name' => 'Statistics Tolerance Schema',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00',
            'jam_pulang_default' => '15:00',
            'toleransi_default' => 0,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '07:00',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 1200,
            'alpha_days_semester_limit' => 2,
            'late_minutes_monthly_limit' => 15,
            'monthly_late_mode' => 'monitor_only',
            'semester_total_violation_mode' => 'monitor_only',
            'semester_alpha_mode' => 'alertable',
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-04',
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '14:00:00',
            'status' => 'hadir',
            'metode_absensi' => 'manual',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/absensi/statistics?month=8&year=2026')
            ->assertStatus(200)
            ->assertJsonPath('data.total_terlambat_menit', 30)
            ->assertJsonPath('data.discipline_thresholds.monthly_late.minutes', 30)
            ->assertJsonPath('data.discipline_thresholds.monthly_late.exceeded', true);
    }

    public function test_statistics_returns_tap_day_and_minute_metrics(): void
    {
        Carbon::setTestNow('2026-08-31 18:00:00');

        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        $this->createDefaultStudentSchema();

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-04',
            'jam_masuk' => '07:00:00',
            'status' => 'hadir',
            'metode_absensi' => 'manual',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/absensi/statistics?month=8&year=2026')
            ->assertStatus(200)
            ->assertJsonPath('data.present_days', 1)
            ->assertJsonPath('data.tap_days', 1)
            ->assertJsonPath('data.total_tap_hari', 1)
            ->assertJsonPath('data.total_tap_menit', 210)
            ->assertJsonPath('data.total_pelanggaran_menit', 210);
    }

    public function test_statistics_current_month_counts_today_only_when_status_exists(): void
    {
        Carbon::setTestNow('2026-08-05 18:30:00');

        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        $this->createDefaultStudentSchema();

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-03',
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '14:00:00',
            'status' => 'hadir',
            'metode_absensi' => 'manual',
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-04',
            'jam_masuk' => '07:20:00',
            'jam_pulang' => '14:00:00',
            'status' => 'terlambat',
            'metode_absensi' => 'manual',
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-05',
            'status' => 'alpha',
            'metode_absensi' => 'manual',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/absensi/statistics?month=8&year=2026');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_hari_sekolah_bulan', 21)
            ->assertJsonPath('data.total_hari_sekolah_berjalan', 3)
            ->assertJsonPath('data.total_hari_kerja', 3)
            ->assertJsonPath('data.present_days', 2)
            ->assertJsonPath('data.total_alpha', 1)
            ->assertJsonPath('data.period.today_included', true)
            ->assertJsonPath('data.period.evaluation_end', '2026-08-05');

        $this->assertEquals(66.67, (float) $response->json('data.attendance_percentage'));
    }

    public function test_statistics_current_month_excludes_today_when_no_status_exists_yet(): void
    {
        Carbon::setTestNow('2026-08-05 09:00:00');

        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        $this->createDefaultStudentSchema();

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-03',
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '14:00:00',
            'status' => 'hadir',
            'metode_absensi' => 'manual',
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-04',
            'jam_masuk' => '07:20:00',
            'jam_pulang' => '14:00:00',
            'status' => 'terlambat',
            'metode_absensi' => 'manual',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/absensi/statistics?month=8&year=2026');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_hari_sekolah_bulan', 21)
            ->assertJsonPath('data.total_hari_sekolah_berjalan', 2)
            ->assertJsonPath('data.total_hari_kerja', 2)
            ->assertJsonPath('data.present_days', 2)
            ->assertJsonPath('data.total_alpha', 0)
            ->assertJsonPath('data.period.today_included', false)
            ->assertJsonPath('data.period.evaluation_end', '2026-08-04');

        $this->assertEquals(100.0, (float) $response->json('data.attendance_percentage'));
    }

    private function createDefaultStudentSchema(): void
    {
        AttendanceSchema::create([
            'schema_name' => 'Default Schema Current Month',
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
            'siswa_jam_masuk' => '07:00',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
            'discipline_thresholds_enabled' => false,
            'violation_minutes_threshold' => 300,
            'violation_percentage_threshold' => 10.00,
        ]);
    }
}
