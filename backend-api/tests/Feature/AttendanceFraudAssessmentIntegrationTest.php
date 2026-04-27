<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\AttendanceFraudAssessment;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceFraudAssessmentIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        config()->set('attendance.security.event_logging_enabled', true);
        config()->set('attendance.security.rollout_mode', 'warning_mode');
        config()->set('attendance.gps.block_mocked', true);
    }

    public function test_submit_attendance_records_warning_fraud_assessment_and_updates_attendance_fields(): void
    {
        $user = $this->createUserWithRole(RoleNames::SISWA);
        $this->seedAttendanceDefaults();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => -6.750000,
                'longitude' => 108.550000,
                'accuracy' => 8,
                'device_id' => 'device-student-1',
                'device_info' => [
                    'platform' => 'Android',
                    'app_version' => '1.0.0',
                    'package_name' => 'id.sch.sman1sumbercirebon.siaps',
                ],
                'request_nonce' => 'nonce-warning-1',
                'request_timestamp' => now()->toIso8601String(),
                'anti_fraud_payload' => [
                    'platform' => 'android',
                    'package_name' => 'id.sch.sman1sumbercirebon.siaps',
                    'client_timestamp' => now()->toIso8601String(),
                    'location_captured_at' => now()->toIso8601String(),
                    'developer_options_enabled' => true,
                    'suspicious_network' => true,
                ],
                'foto' => $this->validBase64Image(),
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'warning')
            ->assertJsonPath('data.has_warning', true)
            ->assertJsonPath('data.risk_level', 'low')
            ->assertJsonPath('data.risk_score', 0);

        $assessment = AttendanceFraudAssessment::query()->first();
        $this->assertNotNull($assessment);
        $this->assertSame('warning', $assessment->validation_status);
        $this->assertSame(0, (int) $assessment->risk_score);
        $this->assertNotEmpty($assessment->decision_reason);

        $attendance = Absensi::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($attendance);
        $this->assertSame('warning', $attendance->validation_status);
        $this->assertSame(0, (int) $attendance->risk_score);
        $this->assertGreaterThanOrEqual(2, (int) $attendance->fraud_flags_count);
    }

    public function test_submit_attendance_in_strict_mode_still_records_warning_and_saves_attendance(): void
    {
        config()->set('attendance.security.rollout_mode', 'strict_mode');

        $user = $this->createUserWithRole(RoleNames::SISWA);
        $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => -6.750000,
                'longitude' => 108.550000,
                'accuracy' => 8,
                'device_id' => 'device-student-2',
                'device_info' => [
                    'platform' => 'Android',
                    'app_version' => '1.0.0',
                    'package_name' => 'id.sch.sman1sumbercirebon.siaps',
                ],
                'request_nonce' => 'nonce-strict-1',
                'request_timestamp' => now()->toIso8601String(),
                'anti_fraud_payload' => [
                    'platform' => 'android',
                    'package_name' => 'id.sch.sman1sumbercirebon.siaps',
                    'client_timestamp' => now()->toIso8601String(),
                    'location_captured_at' => now()->toIso8601String(),
                    'emulator_detected' => true,
                    'root_detected' => true,
                    'instrumentation_detected' => true,
                ],
                'foto' => $this->validBase64Image(),
            ])
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'warning')
            ->assertJsonPath('data.has_warning', true)
            ->assertJsonPath('data.fraud_assessment.validation_status', 'warning')
            ->assertJsonPath('data.fraud_assessment.is_blocking', false);

        $this->assertDatabaseCount('absensi', 1);
        $this->assertDatabaseHas('attendance_fraud_assessments', [
            'user_id' => $user->id,
            'validation_status' => 'warning',
        ]);
    }

    public function test_manager_can_access_fraud_assessment_report_endpoints(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage_attendance_settings');

        $student = $this->createUserWithRole(RoleNames::SISWA);
        $assessment = AttendanceFraudAssessment::query()->create([
            'user_id' => $student->id,
            'assessment_date' => now()->toDateString(),
            'source' => 'attendance_submit',
            'attempt_type' => 'masuk',
            'rollout_mode' => 'warning_mode',
            'validation_status' => 'warning',
            'risk_level' => 'low',
            'risk_score' => 0,
            'fraud_flags_count' => 2,
            'decision_code' => 'device_spoofing',
            'decision_reason' => 'Perangkat tidak sesuai binding akun.',
            'recommended_action' => 'Evaluasi manual oleh admin.',
            'is_blocking' => false,
            'device_id' => 'device-report-1',
        ]);
        $assessment->flags()->create([
            'user_id' => $student->id,
            'flag_key' => 'device_spoofing',
            'category' => 'device_integrity',
            'severity' => 'high',
            'score' => 55,
            'blocking_recommended' => true,
            'label' => 'Risiko Device Spoofing',
            'reason' => 'Device mismatch.',
            'evidence' => ['device_id' => 'device-report-1'],
        ]);

        $summaryResponse = $this->actingAs($manager, 'sanctum')
            ->getJson('/api/simple-attendance/fraud-assessments/summary');

        $summaryResponse
            ->assertOk()
            ->assertJsonPath('data.overview.total_assessments', 1)
            ->assertJsonPath('data.overview.warning_count', 1)
            ->assertJsonPath('data.overview.precheck_warning_count', 0)
            ->assertJsonPath('data.overview.submit_warning_count', 1)
            ->assertJsonPath('data.top_flags.0.flag_key', 'device_spoofing')
            ->assertJsonMissingPath('data.overview.rejected_count')
            ->assertJsonMissingPath('data.overview.manual_review_count')
            ->assertJsonMissingPath('data.overview.high_risk_count')
            ->assertJsonPath('data.config.validation_statuses.0', 'valid')
            ->assertJsonPath('data.config.validation_statuses.1', 'warning')
            ->assertJsonPath('data.config.sources.0', 'attendance_precheck')
            ->assertJsonPath('data.config.sources.1', 'attendance_submit')
            ->assertJsonMissingPath('data.config.thresholds');

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/simple-attendance/fraud-assessments')
            ->assertOk()
            ->assertJsonPath('data.data.0.validation_status', 'warning')
            ->assertJsonPath('data.data.0.flags.0.flag_key', 'device_spoofing');

        $this->actingAs($manager, 'sanctum')
            ->getJson("/api/simple-attendance/fraud-assessments/{$assessment->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $assessment->id)
            ->assertJsonPath('data.validation_status', 'warning')
            ->assertJsonPath('data.flags.0.label', 'Risiko Device Spoofing');
    }

    public function test_manager_fraud_monitoring_filters_only_accept_valid_and_warning_status(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage_attendance_settings');

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/simple-attendance/fraud-assessments?validation_status=manual_review')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['validation_status']);
    }

    public function test_precheck_warning_is_deduplicated_and_submit_reuses_warning_without_duplicate_frequency_flag(): void
    {
        $user = $this->createUserWithRole(RoleNames::SISWA);
        $this->seedAttendanceDefaults();

        $warningPayload = [
            'action_type' => 'checkin',
            'trigger' => 'precheck_popup',
            'acknowledged' => true,
            'acknowledged_at' => now()->toIso8601String(),
            'warning_hash' => 'warning-hash-precheck-1',
            'issues' => [
                [
                    'event_key' => 'developer_options_enabled',
                    'label' => 'Developer options aktif',
                    'message' => 'Developer options masih aktif pada perangkat saat proses absensi.',
                    'severity' => 'medium',
                    'category' => 'device_integrity',
                ],
            ],
        ];

        $precheckPayload = [
            'action_type' => 'checkin',
            'device_id' => 'device-precheck-1',
            'device_info' => [
                'platform' => 'Android',
                'app_version' => '1.0.0',
                'package_name' => 'id.sch.sman1sumbercirebon.siaps',
            ],
            'request_timestamp' => now()->toIso8601String(),
            'anti_fraud_payload' => [
                'platform' => 'android',
                'package_name' => 'id.sch.sman1sumbercirebon.siaps',
                'client_timestamp' => now()->toIso8601String(),
                'developer_options_enabled' => true,
            ],
            'security_warning_payload' => $warningPayload,
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/precheck/security-warning', $precheckPayload)
            ->assertOk()
            ->assertJsonPath('data.logged', true)
            ->assertJsonPath('data.fraud_assessment.validation_status', 'warning');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/precheck/security-warning', $precheckPayload)
            ->assertOk()
            ->assertJsonPath('data.logged', false)
            ->assertJsonPath('data.deduplicated', true);

        $submitResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => -6.750000,
                'longitude' => 108.550000,
                'accuracy' => 8,
                'device_id' => 'device-precheck-1',
                'device_info' => [
                    'platform' => 'Android',
                    'app_version' => '1.0.0',
                    'package_name' => 'id.sch.sman1sumbercirebon.siaps',
                ],
                'request_nonce' => 'nonce-precheck-submit-1',
                'request_timestamp' => now()->toIso8601String(),
                'anti_fraud_payload' => [
                    'platform' => 'android',
                    'package_name' => 'id.sch.sman1sumbercirebon.siaps',
                    'client_timestamp' => now()->toIso8601String(),
                    'location_captured_at' => now()->toIso8601String(),
                    'developer_options_enabled' => true,
                ],
                'security_warning_payload' => $warningPayload,
                'foto' => $this->validBase64Image(),
            ]);

        $submitResponse
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'warning')
            ->assertJsonPath('data.fraud_flags_count', 1);

        $this->assertDatabaseCount('attendance_fraud_assessments', 2);

        $submitAssessment = AttendanceFraudAssessment::query()
            ->where('user_id', $user->id)
            ->where('source', 'attendance_submit')
            ->first();

        $this->assertNotNull($submitAssessment);
        $this->assertFalse($submitAssessment->flags()->where('flag_key', 'duplicate_frequency')->exists());
        $this->assertTrue($submitAssessment->flags()->where('flag_key', 'developer_options')->exists());
    }

    public function test_non_student_mobile_submit_is_rejected_without_creating_student_fraud_assessment(): void
    {
        $teacher = $this->createUserWithRole(RoleNames::GURU);
        $this->seedAttendanceDefaults();

        $this->actingAs($teacher, 'api')
            ->withHeaders([
                'X-Client-Platform' => 'mobile',
                'X-Client-App' => 'siaps-mobile',
            ])
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => -6.750000,
                'longitude' => 108.550000,
                'accuracy' => 8,
                'device_id' => 'device-guru-1',
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'ATTENDANCE_FORBIDDEN');

        $this->assertDatabaseCount('absensi', 0);
        $this->assertDatabaseCount('attendance_fraud_assessments', 0);
        $this->assertDatabaseCount('attendance_security_events', 0);
    }

    private function seedAttendanceDefaults(array $overrides = []): void
    {
        $location = DB::table('lokasi_gps')->insertGetId([
            'nama_lokasi' => 'Sekolah',
            'deskripsi' => 'Lokasi utama sekolah',
            'latitude' => -6.750000,
            'longitude' => 108.550000,
            'radius' => 100,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = array_merge([
            'schema_name' => 'Default Schema',
            'schema_type' => 'global',
            'is_active' => true,
            'is_default' => true,
            'is_mandatory' => true,
            'priority' => 0,
            'version' => 1,
            'jam_masuk_default' => now()->format('H:i:s'),
            'jam_pulang_default' => now()->addHour()->format('H:i:s'),
            'toleransi_default' => 180,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'face_template_required' => false,
            'hari_kerja' => json_encode(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']),
            'lokasi_gps_ids' => json_encode([$location]),
            'radius_absensi' => 100,
            'gps_accuracy' => 20,
            'siswa_jam_masuk' => now()->format('H:i:s'),
            'siswa_jam_pulang' => now()->addHour()->format('H:i:s'),
            'siswa_toleransi' => 180,
            'minimal_open_time_siswa' => 70,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        DB::table('attendance_settings')->insert($payload);
    }

    private function createUserWithRole(string $canonicalRole): User
    {
        $role = Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->where('guard_name', 'web')
            ->first();

        if (!$role) {
            foreach (RoleNames::aliases($canonicalRole) as $alias) {
                $role = Role::firstOrCreate(
                    ['name' => $alias, 'guard_name' => 'web'],
                    [
                        'display_name' => $alias,
                        'description' => $alias,
                        'level' => 1,
                        'is_active' => true,
                    ]
                );
            }
        }

        $user = User::factory()->create();
        $user->assignRole($role->name);

        return $user;
    }

    private function validBase64Image(): string
    {
        return 'data:image/jpeg;base64,' . base64_encode('test-image-content');
    }
}
