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

class MonthlyRecapAlphaMinutesIntegrationTest extends TestCase
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

    public function test_monthly_recap_returns_alpha_minutes_and_consistent_totals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));

        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-03',
            'jam_masuk' => '07:05:00',
            'jam_pulang' => '15:10:00',
            'status' => 'hadir',
            'metode_absensi' => 'manual',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/monthly-recap/current')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertArrayHasKey('alpa_menit', $data);
        $this->assertArrayHasKey('alpa_hari', $data);
        $this->assertArrayHasKey('menit_sekolah_per_hari', $data);
        $this->assertArrayHasKey('total_menit_sekolah_bulan', $data);
        $this->assertSame($data['menit_kerja_per_hari'], $data['menit_sekolah_per_hari']);
        $this->assertSame($data['total_menit_kerja_bulan'], $data['total_menit_sekolah_bulan']);
        $this->assertSame($data['alpa'], $data['alpa_hari']);
        $this->assertSame($data['alpa_hari'] * 480, $data['alpa_menit']);
        $this->assertSame(
            $data['alpa_menit'] + $data['terlambat'] + $data['tap'],
            $data['totalTK']
        );
    }

    public function test_monthly_recap_uses_violation_threshold_policy_from_default_schema(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));

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
            'violation_minutes_threshold' => 120,
            'violation_percentage_threshold' => 5.00,
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-04',
            'status' => 'alpha',
            'metode_absensi' => 'manual',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/monthly-recap/current')
            ->assertStatus(200);

        $data = $response->json('data');

        $this->assertSame(120, $data['batas_pelanggaran_menit']);
        $this->assertEquals(5.0, $data['batas_pelanggaran_persen']);
        $this->assertSame(420, $data['pelanggaran_menit']);
        $this->assertTrue($data['melewati_batas_pelanggaran']);
    }

    public function test_monthly_recap_returns_new_discipline_threshold_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));

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

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/monthly-recap/current')
            ->assertStatus(200);

        $response
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

    public function test_monthly_recap_late_threshold_counts_from_scheduled_start_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));

        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        AttendanceSchema::create([
            'schema_name' => 'Tolerance Aware Schema',
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
            ->getJson('/api/monthly-recap/current')
            ->assertStatus(200)
            ->assertJsonPath('data.terlambat', 30)
            ->assertJsonPath('data.discipline_thresholds.monthly_late.minutes', 30)
            ->assertJsonPath('data.discipline_thresholds.monthly_late.exceeded', true);
    }

    public function test_monthly_recap_returns_tap_day_and_minute_metrics(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));

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
            ->getJson('/api/monthly-recap/current')
            ->assertStatus(200)
            ->assertJsonPath('data.tap_hari', 1)
            ->assertJsonPath('data.tap_menit', 210)
            ->assertJsonPath('data.tap', 210)
            ->assertJsonPath('data.totalTK', 210);
    }

    public function test_monthly_recap_current_month_excludes_today_when_no_status_exists_yet(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 09:00:00'));

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

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/monthly-recap/current')
            ->assertStatus(200)
            ->assertJsonPath('data.working_days', 2)
            ->assertJsonPath('data.school_days', 2)
            ->assertJsonPath('data.school_days_in_month', 21)
            ->assertJsonPath('data.unrecorded_days', 0)
            ->assertJsonPath('data.attendance_rate', 100)
            ->assertJsonPath('data.period.today_included', false)
            ->assertJsonPath('data.period.evaluation_end', '2026-08-04');
    }

    public function test_monthly_recap_current_month_counts_today_when_status_exists(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-05 18:30:00'));

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

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/monthly-recap/current')
            ->assertStatus(200)
            ->assertJsonPath('data.working_days', 3)
            ->assertJsonPath('data.school_days', 3)
            ->assertJsonPath('data.school_days_in_month', 21)
            ->assertJsonPath('data.alpa', 1)
            ->assertJsonPath('data.attendance_rate', 66.67)
            ->assertJsonPath('data.period.today_included', true)
            ->assertJsonPath('data.period.evaluation_end', '2026-08-05');
    }

    private function createDefaultStudentSchema(): void
    {
        AttendanceSchema::create([
            'schema_name' => 'Monthly Recap Current Month',
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
