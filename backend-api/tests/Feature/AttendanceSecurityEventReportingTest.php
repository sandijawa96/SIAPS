<?php

namespace Tests\Feature;

use App\Models\AttendanceSecurityEvent;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceSecurityEventReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        config()->set('attendance.gps.block_mocked', true);
        config()->set('attendance.security.event_logging_enabled', true);
    }

    public function test_submit_attendance_allows_mock_location_warning_and_records_security_event(): void
    {
        config()->set('attendance.security.allow_submit_with_security_warnings', false);

        $user = $this->createUserWithRole(RoleNames::SISWA);
        $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => -6.75,
                'longitude' => 108.55,
                'accuracy' => 10,
                'is_mocked' => true,
                'device_id' => 'device-mock-1',
                'foto' => $this->validBase64Image(),
            ])
            ->assertOk()
            ->assertJsonPath('data.validation_status', 'warning')
            ->assertJsonPath('data.has_warning', true);

        $event = AttendanceSecurityEvent::query()->first();

        $this->assertNotNull($event);
        $this->assertSame($user->id, (int) $event->user_id);
        $this->assertSame('mock_location_detected', $event->event_key);
        $this->assertSame('flagged', $event->status);
        $this->assertSame('high', $event->severity);
        $this->assertSame('device-mock-1', $event->device_id);
        $this->assertDatabaseCount('absensi', 1);
    }

    public function test_locked_student_device_mismatch_stays_blocking_even_when_warning_mode_is_enabled(): void
    {
        config()->set('attendance.security.rollout_mode', 'warning_mode');
        config()->set('attendance.security.allow_submit_with_security_warnings', true);

        $user = $this->createUserWithRole(RoleNames::SISWA);
        $user->forceFill([
            'device_locked' => true,
            'device_id' => 'bound-device-001',
            'device_name' => 'Android Siswa',
            'device_bound_at' => now(),
        ])->save();
        $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'device_id' => 'other-device-999',
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'DEVICE_LOCK_VIOLATION');

        $event = AttendanceSecurityEvent::query()->latest('id')->first();

        $this->assertNotNull($event);
        $this->assertSame($user->id, (int) $event->user_id);
        $this->assertSame('device_lock_violation', $event->event_key);
        $this->assertSame('blocked', $event->status);
        $this->assertSame('other-device-999', $event->device_id);
    }

    public function test_locked_student_missing_device_id_stays_blocking_even_when_warning_mode_is_enabled(): void
    {
        config()->set('attendance.security.rollout_mode', 'warning_mode');
        config()->set('attendance.security.allow_submit_with_security_warnings', true);

        $user = $this->createUserWithRole(RoleNames::SISWA);
        $user->forceFill([
            'device_locked' => true,
            'device_id' => 'bound-device-002',
            'device_name' => 'Android Siswa',
            'device_bound_at' => now(),
        ])->save();
        $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
            ])
            ->assertStatus(400)
            ->assertJsonPath('code', 'DEVICE_ID_REQUIRED');

        $event = AttendanceSecurityEvent::query()->latest('id')->first();

        $this->assertNotNull($event);
        $this->assertSame($user->id, (int) $event->user_id);
        $this->assertSame('device_id_missing_on_locked_account', $event->event_key);
        $this->assertSame('blocked', $event->status);
    }

    public function test_manager_can_access_security_event_report_and_summary(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage_attendance_settings');

        [$tingkat, $tahunAjaran] = $this->createAcademicContext();
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $student = $this->createUserWithRole(RoleNames::SISWA);
        $class = $this->createClass($tingkat, $tahunAjaran, $wali, 'X IPA 1');
        $this->attachStudentToClass($student, $class, $tahunAjaran);

        AttendanceSecurityEvent::record([
            'user_id' => $student->id,
            'kelas_id' => $class->id,
            'category' => 'gps_integrity',
            'event_key' => 'mock_location_detected',
            'severity' => 'high',
            'status' => 'blocked',
            'attempt_type' => 'masuk',
            'event_date' => now()->toDateString(),
            'device_id' => 'device-001',
            'metadata' => [
                'message' => 'Mock location terdeteksi dan absensi diblokir.',
            ],
        ]);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/simple-attendance/security-events')
            ->assertOk()
            ->assertJsonPath('data.data.0.event_key', 'mock_location_detected')
            ->assertJsonPath('data.data.0.event_label', 'Mock location / Fake GPS terdeteksi')
            ->assertJsonPath('data.data.0.student.user_id', $student->id);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/simple-attendance/security-events/summary')
            ->assertOk()
            ->assertJsonPath('data.overview.total_events', 1)
            ->assertJsonPath('data.overview.blocked_events', 1)
            ->assertJsonPath('data.follow_up_candidates.0.user_id', $student->id);
    }

    public function test_wali_kelas_only_can_access_security_events_for_owned_class(): void
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

        AttendanceSecurityEvent::record([
            'user_id' => $studentA->id,
            'kelas_id' => $classA->id,
            'category' => 'gps_integrity',
            'event_key' => 'mock_location_detected',
            'severity' => 'high',
            'status' => 'blocked',
            'attempt_type' => 'masuk',
            'event_date' => now()->toDateString(),
        ]);

        AttendanceSecurityEvent::record([
            'user_id' => $studentB->id,
            'kelas_id' => $classB->id,
            'category' => 'device_integrity',
            'event_key' => 'device_lock_violation',
            'severity' => 'high',
            'status' => 'blocked',
            'attempt_type' => 'masuk',
            'event_date' => now()->toDateString(),
        ]);

        $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/wali-kelas/kelas/{$classA->id}/security-events")
            ->assertOk()
            ->assertJsonPath('data.kelas.id', $classA->id)
            ->assertJsonPath('data.summary.total_events', 1)
            ->assertJsonPath('data.events.data.0.student.user_id', $studentA->id);

        $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/wali-kelas/kelas/{$classB->id}/security-events")
            ->assertForbidden();
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

    private function validBase64Image(): string
    {
        return 'data:image/jpeg;base64,' . base64_encode('test-image-content');
    }
}
