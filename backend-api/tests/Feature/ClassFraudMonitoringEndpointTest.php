<?php

namespace Tests\Feature;

use App\Models\AttendanceFraudAssessment;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassFraudMonitoringEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        config()->set('attendance.security.rollout_mode', 'warning_mode');
    }

    public function test_wali_kelas_can_only_access_fraud_monitoring_for_owned_class(): void
    {
        [$tingkat, $tahunAjaran] = $this->createAcademicContext();
        $waliA = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $waliB = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $studentA = $this->createUserWithRole(RoleNames::SISWA);
        $studentB = $this->createUserWithRole(RoleNames::SISWA);
        $classA = $this->createClass($tingkat, $tahunAjaran, $waliA, 'X IPA 1');
        $classB = $this->createClass($tingkat, $tahunAjaran, $waliB, 'X IPA 2');

        $this->attachStudentToClass($studentA, $classA, $tahunAjaran);
        $this->attachStudentToClass($studentB, $classB, $tahunAjaran);

        $assessmentA = $this->createFraudAssessment($classA, $studentA, [
            'validation_status' => 'warning',
            'risk_level' => 'low',
            'risk_score' => 0,
            'fraud_flags_count' => 2,
            'decision_code' => 'mock_location',
        ], [
            'flag_key' => 'mock_location',
            'category' => 'gps_integrity',
            'severity' => 'high',
            'score' => 60,
            'label' => 'Mock location terdeteksi',
            'reason' => 'Sinyal fake GPS terdeteksi.',
        ]);

        $this->createFraudAssessment($classB, $studentB, [
            'validation_status' => 'warning',
            'risk_level' => 'low',
            'risk_score' => 0,
            'fraud_flags_count' => 1,
            'decision_code' => 'developer_options',
        ], [
            'flag_key' => 'developer_options',
            'category' => 'device_integrity',
            'severity' => 'medium',
            'score' => 20,
            'label' => 'Developer options aktif',
            'reason' => 'Developer options aktif saat submit.',
        ]);

        $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/wali-kelas/kelas/{$classA->id}/fraud-assessments/summary")
            ->assertOk()
            ->assertJsonPath('data.kelas.id', $classA->id)
            ->assertJsonPath('data.summary.total_assessments', 1)
            ->assertJsonPath('data.summary.precheck_warning_count', 0)
            ->assertJsonPath('data.summary.submit_warning_count', 1)
            ->assertJsonPath('data.summary.top_flags.0.flag_key', 'mock_location')
            ->assertJsonPath('data.summary.follow_up_students.0.user_id', $studentA->id)
            ->assertJsonMissingPath('data.summary.rejected_count')
            ->assertJsonMissingPath('data.summary.manual_review_count')
            ->assertJsonMissingPath('data.summary.high_risk_count')
            ->assertJsonMissingPath('data.summary.recent_high_risk_assessments');

        $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/wali-kelas/kelas/{$classA->id}/fraud-assessments")
            ->assertOk()
            ->assertJsonPath('data.assessments.data.0.id', $assessmentA->id)
            ->assertJsonPath('data.assessments.data.0.student.user_id', $studentA->id);

        $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/wali-kelas/kelas/{$classA->id}/fraud-assessments/{$assessmentA->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $assessmentA->id)
            ->assertJsonPath('data.flags.0.flag_key', 'mock_location');

        $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/wali-kelas/kelas/{$classB->id}/fraud-assessments/summary")
            ->assertForbidden();
    }

    public function test_wakasek_kesiswaan_can_access_class_fraud_monitoring_from_monitoring_route(): void
    {
        [$tingkat, $tahunAjaran] = $this->createAcademicContext();
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $wakasek = $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $student = $this->createUserWithRole(RoleNames::SISWA);
        $class = $this->createClass($tingkat, $tahunAjaran, $wali, 'X IPA 3');

        $this->attachStudentToClass($student, $class, $tahunAjaran);

        $assessment = $this->createFraudAssessment($class, $student, [
            'validation_status' => 'warning',
            'risk_level' => 'low',
            'risk_score' => 0,
            'fraud_flags_count' => 2,
            'decision_code' => 'device_spoofing',
            'is_blocking' => false,
        ], [
            'flag_key' => 'device_spoofing',
            'category' => 'device_integrity',
            'severity' => 'high',
            'score' => 55,
            'label' => 'Risiko device spoofing',
            'reason' => 'Device binding tidak sesuai.',
        ]);

        $this->actingAs($wakasek, 'sanctum')
            ->getJson("/api/monitoring-kelas/kelas/{$class->id}/fraud-assessments/summary")
            ->assertOk()
            ->assertJsonPath('data.kelas.id', $class->id)
            ->assertJsonPath('data.summary.warning_count', 1)
            ->assertJsonPath('data.summary.precheck_warning_count', 0)
            ->assertJsonPath('data.summary.submit_warning_count', 1)
            ->assertJsonPath('data.summary.recent_warning_assessments.0.id', $assessment->id)
            ->assertJsonPath('data.config.rollout_mode', 'warning_mode')
            ->assertJsonMissingPath('data.summary.rejected_count')
            ->assertJsonMissingPath('data.summary.manual_review_count')
            ->assertJsonMissingPath('data.summary.high_risk_count')
            ->assertJsonMissingPath('data.summary.recent_high_risk_assessments')
            ->assertJsonMissingPath('data.config.warning_score')
            ->assertJsonMissingPath('data.config.manual_review_score')
            ->assertJsonMissingPath('data.config.reject_score')
            ->assertJsonMissingPath('data.config.critical_score');

        $this->actingAs($wakasek, 'sanctum')
            ->getJson("/api/monitoring-kelas/kelas/{$class->id}/fraud-assessments")
            ->assertOk()
            ->assertJsonPath('data.assessments.data.0.validation_status', 'warning');
    }

    private function createFraudAssessment(
        Kelas $kelas,
        User $student,
        array $assessmentOverrides = [],
        array $flagOverrides = []
    ): AttendanceFraudAssessment {
        $assessment = AttendanceFraudAssessment::query()->create(array_merge([
            'user_id' => $student->id,
            'kelas_id' => $kelas->id,
            'assessment_date' => now()->toDateString(),
            'source' => 'attendance_submit',
            'attempt_type' => 'masuk',
            'rollout_mode' => 'warning_mode',
            'validation_status' => 'warning',
            'risk_level' => 'low',
            'risk_score' => 0,
            'fraud_flags_count' => 1,
            'decision_code' => 'developer_options',
            'decision_reason' => 'Sinyal fraud perlu evaluasi.',
            'recommended_action' => 'Tampilkan warning kepada admin untuk tindak lanjut.',
            'is_blocking' => false,
            'device_id' => 'device-monitoring-1',
            'metadata' => [
                'kelas_label_snapshot' => $kelas->nama_lengkap,
                'student_name_snapshot' => $student->nama_lengkap,
                'student_identifier_snapshot' => $student->nisn ?: $student->nis ?: $student->username,
            ],
        ], $assessmentOverrides));

        $assessment->flags()->create(array_merge([
            'user_id' => $student->id,
            'attendance_id' => null,
            'flag_key' => 'developer_options',
            'category' => 'device_integrity',
            'severity' => 'medium',
            'score' => 20,
            'blocking_recommended' => false,
            'label' => 'Developer options aktif',
            'reason' => 'Developer options aktif saat submit.',
            'evidence' => [
                'kelas_id' => $kelas->id,
                'user_id' => $student->id,
            ],
        ], $flagOverrides));

        return $assessment->fresh();
    }

    private function createAcademicContext(): array
    {
        $tingkat = Tingkat::create([
            'nama' => 'Kelas X',
            'kode' => 'X',
            'urutan' => 10,
            'is_active' => true,
        ]);

        $tahunAjaran = TahunAjaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => now()->startOfYear()->toDateString(),
            'tanggal_selesai' => now()->endOfYear()->toDateString(),
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'semester' => 'genap',
        ]);

        return [$tingkat, $tahunAjaran];
    }

    private function createClass(Tingkat $tingkat, TahunAjaran $tahunAjaran, User $waliKelas, string $namaKelas): Kelas
    {
        return Kelas::create([
            'nama_kelas' => $namaKelas,
            'tingkat_id' => $tingkat->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'wali_kelas_id' => $waliKelas->id,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
        ]);
    }

    private function attachStudentToClass(User $student, Kelas $kelas, TahunAjaran $tahunAjaran): void
    {
        $kelas->siswa()->attach($student->id, [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
}
