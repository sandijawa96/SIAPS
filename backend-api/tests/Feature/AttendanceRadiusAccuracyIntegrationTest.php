<?php

namespace Tests\Feature;

use App\Models\LokasiGps;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceRadiusAccuracyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_submit_attendance_rejects_missing_gps_accuracy_when_gps_required(): void
    {
        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults([
            'gps_accuracy' => 20,
            'verification_mode' => 'sync_final',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'INVALID_GPS_LOCATION')
            ->assertJsonFragment([
                'message' => 'Akurasi GPS tidak tersedia. Aktifkan mode lokasi akurasi tinggi lalu coba lagi.',
            ]);
    }

    public function test_submit_attendance_rejects_when_gps_accuracy_exceeds_threshold(): void
    {
        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults([
            'gps_accuracy' => 15,
            'verification_mode' => 'sync_final',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 45,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'INVALID_GPS_LOCATION');
    }

    public function test_submit_attendance_uses_location_radius_instead_of_schema_radius_override(): void
    {
        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults([
            'radius_absensi' => 1000, // legacy field, should be ignored at runtime
            'verification_mode' => 'sync_final',
        ], 50);

        // ~89 meter dari titik lokasi, harus ditolak karena radius lokasi 50m.
        $outsideLatitude = (float) $location->latitude + 0.0008;

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => $outsideLatitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 5,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'INVALID_GPS_LOCATION')
            ->assertJsonPath('data.details.effective_radius', 50);
    }

    public function test_submit_attendance_accepts_when_inside_radius_and_accuracy_valid(): void
    {
        config()->set('attendance.face.enabled', false);

        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults([
            'gps_accuracy' => 25,
            'verification_mode' => 'sync_final',
        ], 100);

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
            ->assertJsonPath('data.verification.mode', 'sync_final');
    }

    private function createSiswaUser(): User
    {
        $user = User::factory()->create();
        $this->assignRole($user, 'Siswa');

        return $user;
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

    private function seedAttendanceDefaults(array $overrides = [], int $radius = 100): LokasiGps
    {
        $location = LokasiGps::create([
            'nama_lokasi' => 'Sekolah',
            'deskripsi' => 'Lokasi utama sekolah',
            'latitude' => -6.750000,
            'longitude' => 108.550000,
            'radius' => $radius,
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
}
