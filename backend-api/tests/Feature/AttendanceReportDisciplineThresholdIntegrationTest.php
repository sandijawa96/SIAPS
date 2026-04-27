<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportDisciplineThresholdIntegrationTest extends TestCase
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

    public function test_monthly_report_uses_monthly_late_threshold_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));

        $user = User::factory()->create([
            'nama_lengkap' => 'Siswa Threshold Bulanan',
        ]);
        $user->assignRole(RoleNames::WAKASEK_KESISWAAN);

        AttendanceSchema::create([
            'schema_name' => 'Default Schema Monthly Threshold',
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
            'alpha_days_semester_limit' => 3,
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
            ->getJson('/api/reports/attendance/monthly?bulan=8&tahun=2026&view=student_recap')
            ->assertStatus(200)
            ->assertJsonPath('data.summary.discipline_thresholds.mode', 'monthly')
            ->assertJsonPath('data.summary.discipline_thresholds.monthly_late.limit', 15)
            ->assertJsonPath('data.summary.batas_pelanggaran_menit', 15)
            ->assertJsonPath('data.summary.discipline_thresholds.monthly_late.exceeded', true)
            ->assertJsonPath('data.summary.jumlah_siswa_melewati_batas_keterlambatan_bulanan', 1)
            ->assertJsonPath('data.detail.0.discipline_thresholds.monthly_late.exceeded', true);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/reports/attendance/monthly?bulan=8&tahun=2026&view=student_recap&status_disiplin=melewati_batas_telat')
            ->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.detail.0.nama', 'Siswa Threshold Bulanan');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/reports/attendance/monthly?bulan=8&tahun=2026&view=student_recap&status_disiplin=dalam_batas')
            ->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 0);

        $exportRequest = \Illuminate\Http\Request::create('/api/reports/export/excel', 'GET', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'format' => 'xlsx',
            'view' => 'student_recap',
            'status_disiplin' => 'melewati_batas_telat',
        ]);
        $exportRequest->setUserResolver(fn () => $user);
        $controller = app(\App\Http\Controllers\Api\ReportController::class);
        $method = new \ReflectionMethod($controller, 'prepareExportDataset');
        $method->setAccessible(true);

        $dataset = $method->invoke($controller, $exportRequest);

        $this->assertCount(1, $dataset['rows']);
        $this->assertSame('Siswa Threshold Bulanan', $dataset['rows']->first()['nama']);
        $this->assertSame('Melewati batas telat', $dataset['rows']->first()['status_batas']);
    }

    public function test_semester_report_uses_semester_threshold_summary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-20 09:00:00'));

        $user = User::factory()->create([
            'nama_lengkap' => 'Siswa Threshold Semester',
        ]);
        $user->assignRole(RoleNames::WAKASEK_KESISWAAN);

        AttendanceSchema::create([
            'schema_name' => 'Default Schema Semester Threshold',
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
            'total_violation_minutes_semester_limit' => 400,
            'alpha_days_semester_limit' => 1,
            'late_minutes_monthly_limit' => 120,
            'monthly_late_mode' => 'monitor_only',
            'semester_total_violation_mode' => 'monitor_only',
            'semester_alpha_mode' => 'alertable',
            'notify_wali_kelas_on_alpha_limit' => true,
            'notify_kesiswaan_on_alpha_limit' => true,
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-08-05',
            'status' => 'alpha',
            'metode_absensi' => 'manual',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/reports/attendance/semester?tahun=2026&semester=2&view=student_recap')
            ->assertStatus(200)
            ->assertJsonPath('data.summary.discipline_thresholds.mode', 'semester')
            ->assertJsonPath('data.summary.batas_pelanggaran_menit', 400)
            ->assertJsonPath('data.summary.discipline_thresholds.semester_alpha.limit', 1)
            ->assertJsonPath('data.summary.discipline_thresholds.semester_alpha.exceeded', true)
            ->assertJsonPath('data.summary.jumlah_siswa_melewati_batas_alpha_semester', 1)
            ->assertJsonPath('data.detail.0.discipline_thresholds.semester_alpha.exceeded', true);
    }

    public function test_export_discipline_limit_header_is_grouped_by_class_when_thresholds_differ(): void
    {
        $controller = app(\App\Http\Controllers\Api\ReportController::class);
        $method = new \ReflectionMethod($controller, 'buildExportDisciplineLimitSummary');
        $method->setAccessible(true);

        $summary = $method->invoke(
            $controller,
            [
                [
                    'kelas' => 'Kelas X',
                    'discipline_thresholds' => $this->thresholdPayload(30, 600, 2),
                ],
                [
                    'kelas' => 'Kelas XI',
                    'discipline_thresholds' => $this->thresholdPayload(60, 900, 3),
                ],
            ],
            [
                'late_minutes_monthly_limit' => 120,
                'total_violation_minutes_semester_limit' => 1200,
                'alpha_days_semester_limit' => 8,
            ],
            'monthly'
        );

        $this->assertStringContainsString('Kelas X: Telat 30m/bulan; Total 600m/semester; Alpha 2 hari/semester', $summary);
        $this->assertStringContainsString('Kelas XI: Telat 60m/bulan; Total 900m/semester; Alpha 3 hari/semester', $summary);
        $this->assertStringContainsString(' | ', $summary);
    }

    private function thresholdPayload(int $monthlyLateLimit, int $semesterViolationLimit, int $semesterAlphaLimit): array
    {
        return [
            'monthly_late' => [
                'limit' => $monthlyLateLimit,
            ],
            'semester_total_violation' => [
                'limit' => $semesterViolationLimit,
            ],
            'semester_alpha' => [
                'limit' => $semesterAlphaLimit,
            ],
        ];
    }
}
