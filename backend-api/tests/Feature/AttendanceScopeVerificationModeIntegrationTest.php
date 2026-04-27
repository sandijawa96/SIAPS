<?php

namespace Tests\Feature;

use App\Models\LokasiGps;
use App\Jobs\ProcessAttendanceFaceVerification;
use App\Models\User;
use App\Models\UserFaceTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceScopeVerificationModeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_siswa_only_scope_rejects_non_student_user(): void
    {
        $user = User::factory()->create();
        $this->assignRole($user, 'Pegawai');
        $location = $this->seedAttendanceDefaults([
            'attendance_scope' => 'siswa_only',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(403)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'ATTENDANCE_FORBIDDEN');
    }

    public function test_sync_final_mode_returns_immediate_verification_for_student(): void
    {
        config()->set('attendance.face.enabled', false);

        $user = User::factory()->create();
        $this->assignRole($user, 'Siswa');
        $location = $this->seedAttendanceDefaults([
            'attendance_scope' => 'siswa_only',
            'verification_mode' => 'sync_final',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.verification.mode', 'sync_final')
            ->assertJsonPath('data.verification.result', 'verified');
    }

    public function test_sync_final_mode_rejects_and_rolls_back_when_verification_fails(): void
    {
        config()->set('attendance.face.enabled', true);

        $user = User::factory()->create();
        $this->assignRole($user, 'Siswa');
        $location = $this->seedAttendanceDefaults([
            'attendance_scope' => 'siswa_only',
            'verification_mode' => 'sync_final',
            'face_verification_enabled' => true,
            'face_template_required' => false,
            'face_result_when_template_missing' => 'manual_review',
        ]);

        $today = now()->toDateString();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'FACE_VERIFICATION_FAILED')
            ->assertJsonPath('data.verification.mode', 'sync_final')
            ->assertJsonPath('data.verification.status', 'rejected');

        $this->assertDatabaseMissing('absensi', [
            'user_id' => $user->id,
            'tanggal' => $today,
        ]);
    }

    public function test_async_pending_mode_dispatches_face_verification_job_for_student(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->assignRole($user, 'Siswa');
        $location = $this->seedAttendanceDefaults([
            'attendance_scope' => 'siswa_only',
            'verification_mode' => 'async_pending',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.verification.mode', 'async_pending');

        Queue::assertPushed(ProcessAttendanceFaceVerification::class);
    }

    public function test_async_pending_mode_skips_face_verification_job_when_face_toggle_disabled(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $this->assignRole($user, 'Siswa');
        $location = $this->seedAttendanceDefaults([
            'attendance_scope' => 'siswa_only',
            'verification_mode' => 'async_pending',
            'face_verification_enabled' => false,
            'face_template_required' => true,
        ]);
        $this->createActiveFaceTemplate($user);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.verification.enabled', false)
            ->assertJsonPath('data.verification.result', 'verified')
            ->assertJsonPath('data.verification.reason_code', 'face_verification_disabled');

        Queue::assertNotPushed(ProcessAttendanceFaceVerification::class);

        $this->assertDatabaseHas('absensi', [
            'user_id' => $user->id,
            'is_verified' => 1,
            'verification_status' => 'verified',
        ]);
    }

    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            [
                'display_name' => $roleName,
                'description' => $roleName . ' role for attendance test',
                'level' => 0,
                'is_active' => true,
                'guard_name' => 'web',
            ]
        );

        $user->assignRole($role);
    }

    private function seedAttendanceDefaults(array $overrides = []): LokasiGps
    {
        $location = LokasiGps::create([
            'nama_lokasi' => 'Sekolah',
            'deskripsi' => 'Lokasi utama sekolah',
            'latitude' => -6.750000,
            'longitude' => 108.550000,
            'radius' => 100,
            'is_active' => true,
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
            'hari_kerja' => json_encode([
                'Senin',
                'Selasa',
                'Rabu',
                'Kamis',
                'Jumat',
                'Sabtu',
                'Minggu',
            ]),
            'lokasi_gps_ids' => json_encode([$location->id]),
            'radius_absensi' => 100,
            'gps_accuracy' => 20,
            'siswa_jam_masuk' => now()->format('H:i:s'),
            'siswa_jam_pulang' => now()->addHour()->format('H:i:s'),
            'siswa_toleransi' => 180,
            'minimal_open_time_siswa' => 70,
            'verification_mode' => 'async_pending',
            'attendance_scope' => 'siswa_only',
            'target_tingkat_ids' => null,
            'target_kelas_ids' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        DB::table('attendance_settings')->insert($payload);
        Cache::forever('attendance_runtime_version', (int) Cache::get('attendance_runtime_version', 1) + 1);

        return $location;
    }

    private function validBase64Image(): string
    {
        return 'data:image/jpeg;base64,' . base64_encode('test-image-content');
    }

    private function createActiveFaceTemplate(User $user): UserFaceTemplate
    {
        return UserFaceTemplate::create([
            'user_id' => $user->id,
            'template_path' => 'face-templates/test-template.jpg',
            'template_version' => 'opencv-yunet-sface-v1',
            'quality_score' => 0.98,
            'is_active' => true,
            'enrolled_at' => now(),
        ]);
    }
}
