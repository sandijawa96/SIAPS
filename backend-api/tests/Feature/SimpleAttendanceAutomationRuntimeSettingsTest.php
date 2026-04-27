<?php

namespace Tests\Feature;

use App\Models\AttendanceSchema;
use App\Models\LokasiGps;
use App\Models\User;
use App\Services\AttendanceAutomationStateService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\TestCase;

class SimpleAttendanceAutomationRuntimeSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_global_settings_persist_automation_runtime_fields(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);

        $admin = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/simple-attendance/global', [
                'auto_alpha_enabled' => false,
                'auto_alpha_run_time' => '22:40',
                'discipline_alerts_enabled' => true,
                'discipline_alerts_run_time' => '22:55',
                'live_tracking_enabled' => false,
                'live_tracking_retention_days' => 45,
                'live_tracking_cleanup_time' => '03:10',
                'live_tracking_min_distance_meters' => 20,
                'face_verification_enabled' => false,
                'face_template_required' => true,
                'face_result_when_template_missing' => 'manual_review',
                'face_reject_to_manual_review' => false,
                'face_skip_when_photo_missing' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.auto_alpha_enabled', false)
            ->assertJsonPath('data.auto_alpha_run_time', '22:40')
            ->assertJsonPath('data.discipline_alerts_enabled', true)
            ->assertJsonPath('data.discipline_alerts_run_time', '22:55')
            ->assertJsonPath('data.live_tracking_enabled', false)
            ->assertJsonPath('data.live_tracking_retention_days', 45)
            ->assertJsonPath('data.live_tracking_cleanup_time', '03:10')
            ->assertJsonPath('data.live_tracking_min_distance_meters', 20)
            ->assertJsonPath('data.face_verification_enabled', false)
            ->assertJsonPath('data.face_template_required', true)
            ->assertJsonPath('data.face_result_when_template_missing', 'manual_review')
            ->assertJsonPath('data.face_reject_to_manual_review', false)
            ->assertJsonPath('data.face_skip_when_photo_missing', false);

        $this->assertDatabaseHas('attendance_settings', [
            'schema_type' => 'global',
            'auto_alpha_enabled' => 0,
            'auto_alpha_run_time' => '22:40',
            'discipline_alerts_enabled' => 1,
            'discipline_alerts_run_time' => '22:55',
            'live_tracking_enabled' => 0,
            'live_tracking_retention_days' => 45,
            'live_tracking_cleanup_time' => '03:10',
            'live_tracking_min_distance_meters' => 20,
            'face_verification_enabled' => 0,
            'face_template_required' => 1,
            'face_result_when_template_missing' => 'manual_review',
            'face_reject_to_manual_review' => 0,
            'face_skip_when_photo_missing' => 0,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/simple-attendance/global')
            ->assertOk()
            ->assertJsonPath('data.auto_alpha_enabled', false)
            ->assertJsonPath('data.auto_alpha_run_time', '22:40')
            ->assertJsonPath('data.discipline_alerts_enabled', true)
            ->assertJsonPath('data.discipline_alerts_run_time', '22:55')
            ->assertJsonPath('data.live_tracking_enabled', false)
            ->assertJsonPath('data.live_tracking_retention_days', 45)
            ->assertJsonPath('data.live_tracking_cleanup_time', '03:10')
            ->assertJsonPath('data.live_tracking_min_distance_meters', 20)
            ->assertJsonPath('data.face_verification_enabled', false)
            ->assertJsonPath('data.face_template_required', true)
            ->assertJsonPath('data.face_result_when_template_missing', 'manual_review')
            ->assertJsonPath('data.face_reject_to_manual_review', false)
            ->assertJsonPath('data.face_skip_when_photo_missing', false);
    }

    public function test_health_check_uses_runtime_schedule_from_global_settings(): void
    {
        $this->withoutMiddleware(PermissionMiddleware::class);
        Carbon::setTestNow(Carbon::parse('2026-03-19 10:00:00'));

        $admin = User::factory()->create();

        AttendanceSchema::create([
            'schema_name' => 'Global Runtime Settings',
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
            'auto_alpha_enabled' => false,
            'auto_alpha_run_time' => '22:40',
            'discipline_alerts_enabled' => true,
            'discipline_alerts_run_time' => '22:55',
            'live_tracking_enabled' => false,
            'live_tracking_retention_days' => 45,
            'live_tracking_cleanup_time' => '03:10',
            'live_tracking_min_distance_meters' => 20,
            'face_verification_enabled' => false,
            'face_template_required' => true,
            'face_result_when_template_missing' => 'manual_review',
            'face_reject_to_manual_review' => false,
            'face_skip_when_photo_missing' => false,
        ]);

        LokasiGps::create([
            'nama_lokasi' => 'Sekolah A',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius' => 200,
            'is_active' => true,
        ]);

        app(AttendanceAutomationStateService::class)->write('discipline_alerts', [
            'last_run_at' => '2026-03-18T22:55:00+07:00',
            'last_status' => 'healthy',
        ]);
        app(AttendanceAutomationStateService::class)->write('live_tracking_cleanup', [
            'last_run_at' => '2026-03-19T03:10:00+07:00',
            'last_status' => 'healthy',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/simple-attendance/health-check')
            ->assertOk()
            ->assertJsonPath('data.checks.auto_alpha.status', 'disabled')
            ->assertJsonPath('data.checks.auto_alpha.scheduled_time', '22:40')
            ->assertJsonPath('data.checks.discipline_alerts.scheduled_time', '22:55')
            ->assertJsonPath('data.checks.live_tracking_cleanup.scheduled_time', '03:10')
            ->assertJsonPath('data.summary.live_tracking_enabled', false)
            ->assertJsonPath('data.summary.face_enabled', false)
            ->assertJsonPath('data.summary.face_template_required', true)
            ->assertJsonPath('data.summary.live_tracking_retention_days', 45)
            ->assertJsonPath('data.summary.live_tracking_min_distance_meters', 20)
            ->assertJsonPath('data.summary.face_result_when_template_missing', 'manual_review')
            ->assertJsonPath('data.summary.face_reject_to_manual_review', false)
            ->assertJsonPath('data.summary.face_skip_when_photo_missing', false);
    }
}
