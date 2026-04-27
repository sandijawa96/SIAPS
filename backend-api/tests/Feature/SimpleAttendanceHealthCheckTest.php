<?php

namespace Tests\Feature;

use App\Models\AttendanceSchema;
use App\Models\LokasiGps;
use App\Models\User;
use App\Services\AttendanceAutomationStateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\TestCase;

class SimpleAttendanceHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_health_check_marks_scheduler_as_warning_when_automation_is_stale(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        Carbon::setTestNow(Carbon::parse('2026-03-18 12:00:00'));

        $admin = User::factory()->create();

        AttendanceSchema::create([
            'schema_name' => 'Default Global',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'toleransi_default' => 15,
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'discipline_thresholds_enabled' => true,
            'semester_total_violation_mode' => 'monitor_only',
            'semester_alpha_mode' => 'alertable',
            'monthly_late_mode' => 'monitor_only',
        ]);

        LokasiGps::create([
            'nama_lokasi' => 'Sekolah A',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius' => 200,
            'is_active' => true,
        ]);

        $stateService = app(AttendanceAutomationStateService::class);
        $stateService->write('auto_alpha', [
            'last_run_at' => '2026-03-16T23:50:00+07:00',
            'last_status' => 'healthy',
        ]);
        $stateService->write('discipline_alerts', [
            'last_run_at' => '2026-03-16T23:57:00+07:00',
            'last_status' => 'healthy',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/simple-attendance/health-check')
            ->assertOk()
            ->assertJsonPath('data.overall_status', 'warning')
            ->assertJsonPath('data.checks.auto_alpha.status', 'warning')
            ->assertJsonPath('data.checks.discipline_alerts.status', 'warning');
    }

    public function test_health_check_reports_face_service_as_healthy_when_service_responds(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        $admin = User::factory()->create();

        AttendanceSchema::create([
            'schema_name' => 'Default Global',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'toleransi_default' => 15,
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
        ]);

        Http::fake([
            'http://127.0.0.1:9001/health' => Http::response([
                'status' => 'ok',
                'engine' => 'opencv-yunet-sface',
                'yunet_model_loaded' => true,
                'sface_model_loaded' => true,
                'template_version' => 'opencv-yunet-sface-v1',
            ], 200),
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/simple-attendance/health-check')
            ->assertOk()
            ->assertJsonPath('data.checks.face_service.status', 'healthy')
            ->assertJsonPath('data.checks.face_service.engine', 'opencv-yunet-sface')
            ->assertJsonPath('data.checks.face_service.template_version', 'opencv-yunet-sface-v1');
    }
}
