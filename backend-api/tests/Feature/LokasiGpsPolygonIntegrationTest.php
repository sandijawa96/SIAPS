<?php

namespace Tests\Feature;

use App\Models\LokasiGps;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LokasiGpsPolygonIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-02-09 08:00:00')); // Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_check_distance_accepts_coordinate_inside_polygon_area(): void
    {
        $user = $this->createSiswaUser();
        $this->seedPolygonLocation();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/lokasi-gps/check-distance', [
                'latitude' => -6.750000,
                'longitude' => 108.550000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.can_attend', true)
            ->assertJsonPath('data.locations.0.geofence_type', 'polygon')
            ->assertJsonPath('data.locations.0.is_within_area', true);
    }

    public function test_check_distance_rejects_coordinate_outside_polygon_area(): void
    {
        $user = $this->createSiswaUser();
        $this->seedPolygonLocation();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/lokasi-gps/check-distance', [
                'latitude' => -6.754000,
                'longitude' => 108.554000,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.can_attend', false)
            ->assertJsonPath('data.locations.0.geofence_type', 'polygon')
            ->assertJsonPath('data.locations.0.is_within_area', false);

        $this->assertGreaterThan(
            0,
            (float) $response->json('data.locations.0.distance')
        );
    }

    public function test_submit_attendance_accepts_coordinate_inside_polygon_area(): void
    {
        config()->set('attendance.face.enabled', false);

        $user = $this->createSiswaUser();
        $location = $this->seedPolygonLocation();
        $this->seedAttendanceDefaults($location, [
            'gps_accuracy' => 25,
            'verification_mode' => 'sync_final',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => -6.750000,
                'longitude' => 108.550000,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.verification.mode', 'sync_final');
    }

    public function test_update_location_marks_student_inside_polygon_school_area(): void
    {
        $user = $this->createSiswaUser();
        $location = $this->seedPolygonLocation();
        $this->seedAttendanceDefaults($location);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.750000,
                'longitude' => 108.550000,
                'accuracy' => 8,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('live_tracking', [
            'user_id' => $user->id,
            'is_in_school_area' => 1,
        ]);
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
                'description' => $roleName . ' role for polygon geofence test',
                'level' => 0,
                'is_active' => true,
                'guard_name' => 'web',
            ]
        );

        $user->assignRole($role);
    }

    private function seedPolygonLocation(): LokasiGps
    {
        return LokasiGps::create([
            'nama_lokasi' => 'Sekolah Polygon',
            'deskripsi' => 'Area sekolah berbatas polygon',
            'latitude' => -6.750000,
            'longitude' => 108.550000,
            'radius' => 100,
            'geofence_type' => 'polygon',
            'geofence_geojson' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [108.549000, -6.751000],
                    [108.551000, -6.751000],
                    [108.551000, -6.749000],
                    [108.549000, -6.749000],
                    [108.549000, -6.751000],
                ]],
            ],
            'is_active' => true,
        ]);
    }

    private function seedAttendanceDefaults(LokasiGps $location, array $overrides = []): void
    {
        $payload = array_merge([
            'schema_name' => 'Polygon Schema',
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
    }

    private function validBase64Image(): string
    {
        return 'data:image/jpeg;base64,' . base64_encode('test-image-content');
    }
}
