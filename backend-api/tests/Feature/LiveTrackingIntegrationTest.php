<?php

namespace Tests\Feature;

use App\Models\LiveTracking;
use App\Models\Kelas;
use App\Models\LokasiGps;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Services\LiveTrackingCurrentStoreService;
use App\Services\LiveTrackingSnapshotService;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class LiveTrackingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRequiredRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_update_location_allows_tracking_on_weekday_school_hours(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        LokasiGps::create([
            'nama_lokasi' => 'Sekolah A',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius' => 200,
            'is_active' => true,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-02-09 08:00:00')); // Monday

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 10,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('live_tracking', [
            'user_id' => $user->id,
            'is_in_school_area' => 1,
        ]);
    }

    public function test_update_location_rejects_tracking_on_weekend(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        Carbon::setTestNow(Carbon::parse('2026-02-14 08:00:00')); // Saturday

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Tracking realtime hanya aktif pada hari efektif sesuai jadwal absensi',
            ]);
    }

    public function test_update_location_uses_attendance_policy_working_hours(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        LokasiGps::create([
            'nama_lokasi' => 'Sekolah B',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius' => 200,
            'is_active' => true,
        ]);

        DB::table('attendance_settings')->insert([
            'schema_name' => 'Policy Siswa Pagi',
            'schema_type' => 'global',
            'is_active' => true,
            'is_default' => true,
            'siswa_jam_masuk' => '09:00:00',
            'siswa_jam_pulang' => '11:00:00',
            'hari_kerja' => json_encode(['Senin']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forever('attendance_runtime_version', (int) Cache::get('attendance_runtime_version', 1) + 1);

        Carbon::setTestNow(Carbon::parse('2026-02-09 08:00:00')); // Monday

        $tooEarly = $this->actingAs($user, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
            ]);

        $tooEarly->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Tracking realtime hanya aktif saat jam absensi (09:00-11:00)',
            ]);

        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00')); // Monday
        Cache::forever('attendance_runtime_version', (int) Cache::get('attendance_runtime_version', 1) + 1);

        $insideWindow = $this->actingAs($user, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
            ]);

        $insideWindow->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_update_location_rejects_non_siswa_user(): void
    {
        $user = User::factory()->create();
        $user->assignRole(RoleNames::GURU);

        Carbon::setTestNow(Carbon::parse('2026-02-09 08:00:00')); // Monday

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Fitur update lokasi realtime hanya untuk siswa',
            ]);
    }

    public function test_student_cannot_view_other_users_history(): void
    {
        $studentA = User::factory()->create();
        $studentA->assignRole(RoleNames::SISWA);

        $studentB = User::factory()->create();
        $studentB->assignRole(RoleNames::SISWA);

        LiveTracking::create([
            'user_id' => $studentB->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'is_in_school_area' => true,
            'tracked_at' => now(),
        ]);

        $response = $this->actingAs($studentA, 'sanctum')
            ->getJson('/api/live-tracking/history?user_id=' . $studentB->id);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat riwayat tracking user lain',
            ]);
    }

    public function test_student_cannot_access_aggregate_tracking_endpoints(): void
    {
        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        $currentResponse = $this->actingAs($student, 'sanctum')
            ->getJson('/api/live-tracking/current');

        $currentResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat data tracking seluruh siswa',
            ]);

        $radiusResponse = $this->actingAs($student, 'sanctum')
            ->postJson('/api/live-tracking/users-in-radius', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'radius' => 500,
            ]);

        $radiusResponse->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat data tracking radius',
            ]);
    }

    public function test_student_can_access_own_location_but_not_other_user_location(): void
    {
        $studentA = User::factory()->create();
        $studentA->assignRole(RoleNames::SISWA);

        $studentB = User::factory()->create();
        $studentB->assignRole(RoleNames::SISWA);

        $this->seedTrackingSnapshot($studentA, [
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'is_in_school_area' => true,
        ]);

        $this->seedTrackingSnapshot($studentB, [
            'latitude' => -6.21000000,
            'longitude' => 106.82666600,
            'is_in_school_area' => true,
        ]);

        $ownLocation = $this->actingAs($studentA, 'sanctum')
            ->getJson('/api/live-tracking/current-location?user_id=' . $studentA->id);

        $ownLocation->assertStatus(200)->assertJson(['success' => true]);

        $otherLocation = $this->actingAs($studentA, 'sanctum')
            ->getJson('/api/live-tracking/current-location?user_id=' . $studentB->id);

        $otherLocation->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk melihat lokasi user lain',
            ]);
    }

    public function test_guru_without_live_tracking_permission_cannot_view_other_users_tracking(): void
    {
        $guru = User::factory()->create();
        $guru->assignRole(RoleNames::GURU);

        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        LiveTracking::create([
            'user_id' => $student->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'is_in_school_area' => true,
            'tracked_at' => now(),
        ]);

        $this->actingAs($guru, 'sanctum')
            ->getJson('/api/live-tracking/history?user_id=' . $student->id)
            ->assertStatus(403);

        $this->actingAs($guru, 'sanctum')
            ->getJson('/api/live-tracking/current')
            ->assertStatus(403);
    }

    public function test_admin_can_view_other_users_tracking_data(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        LiveTracking::create([
            'user_id' => $student->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'is_in_school_area' => true,
            'tracked_at' => now(),
        ]);
        $this->seedTrackingSnapshot($student, [
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'is_in_school_area' => true,
        ]);

        $history = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/history?user_id=' . $student->id);
        $history->assertStatus(200)->assertJson(['success' => true]);

        $current = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/current');
        $current->assertStatus(200)->assertJson(['success' => true]);

        $location = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/current-location?user_id=' . $student->id);
        $location->assertStatus(200)->assertJson(['success' => true]);
    }

    public function test_admin_can_fetch_history_map_for_up_to_five_students(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $studentA = User::factory()->create([
            'nama_lengkap' => 'Siswa Jalur A',
            'email' => 'jalur-a@example.test',
        ]);
        $studentA->assignRole(RoleNames::SISWA);

        $studentB = User::factory()->create([
            'nama_lengkap' => 'Siswa Jalur B',
            'email' => 'jalur-b@example.test',
        ]);
        $studentB->assignRole(RoleNames::SISWA);

        LiveTracking::create([
            'user_id' => $studentA->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'accuracy' => 9.5,
            'is_in_school_area' => true,
            'location_name' => 'Gerbang Barat',
            'tracked_at' => Carbon::parse('2026-02-09 07:30:00'),
        ]);

        LiveTracking::create([
            'user_id' => $studentA->id,
            'latitude' => -6.20050000,
            'longitude' => 106.81700000,
            'accuracy' => 11.0,
            'is_in_school_area' => true,
            'location_name' => 'Koridor Utama',
            'tracked_at' => Carbon::parse('2026-02-09 07:40:00'),
        ]);

        LiveTracking::create([
            'user_id' => $studentA->id,
            'latitude' => -6.20150000,
            'longitude' => 106.81850000,
            'accuracy' => 14.0,
            'is_in_school_area' => false,
            'location_name' => 'Luar Gerbang',
            'tracked_at' => Carbon::parse('2026-02-09 07:55:00'),
        ]);

        LiveTracking::create([
            'user_id' => $studentB->id,
            'latitude' => -6.20200000,
            'longitude' => 106.81900000,
            'accuracy' => 10.0,
            'is_in_school_area' => true,
            'location_name' => 'Lapangan Timur',
            'tracked_at' => Carbon::parse('2026-02-09 08:05:00'),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/history-map?user_ids=' . $studentA->id . ',' . $studentB->id . '&date=2026-02-09');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.compare_limit', 5)
            ->assertJsonPath('data.summary.selected_students', 2)
            ->assertJsonPath('data.summary.total_points', 4)
            ->assertJsonCount(2, 'data.sessions')
            ->assertJsonPath('data.sessions.0.user.id', $studentA->id)
            ->assertJsonPath('data.sessions.0.statistics.total_points', 3)
            ->assertJsonPath('data.sessions.0.statistics.exit_area_count', 1)
            ->assertJsonPath('data.sessions.0.points.0.sequence', 1)
            ->assertJsonPath('data.sessions.0.points.2.sequence', 3)
            ->assertJsonPath('data.sessions.0.points.2.transition', 'exit_area')
            ->assertJsonPath('data.sessions.1.user.id', $studentB->id)
            ->assertJsonPath('data.sessions.1.statistics.total_points', 1)
            ->assertJsonPath('data.focus_user_id', $studentA->id);
    }

    public function test_history_map_rejects_more_than_five_students(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $studentIds = [];
        foreach (range(1, 6) as $index) {
            $student = User::factory()->create([
                'nama_lengkap' => 'Siswa Compare ' . $index,
            ]);
            $student->assignRole(RoleNames::SISWA);
            $studentIds[] = $student->id;
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/history-map?user_ids=' . implode(',', $studentIds));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Histori peta maksimal dapat dibandingkan untuk 5 siswa sekaligus');
    }

    public function test_admin_can_search_students_for_history_map_compare(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $matchingStudent = User::factory()->create([
            'nama_lengkap' => 'Siswa Peta Global',
            'email' => 'global-map@example.test',
            'nis' => 'MAP-001',
            'username' => 'global.map',
        ]);
        $matchingStudent->assignRole(RoleNames::SISWA);

        $otherStudent = User::factory()->create([
            'nama_lengkap' => 'Siswa Lain',
            'email' => 'other-map@example.test',
        ]);
        $otherStudent->assignRole(RoleNames::SISWA);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/history-map/students?search=global&limit=10');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matchingStudent->id)
            ->assertJsonPath('data.0.name', $matchingStudent->nama_lengkap);
    }

    public function test_history_map_simplifies_route_points_for_dense_sessions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Jalur Padat',
            'email' => 'jalur-padat@example.test',
        ]);
        $student->assignRole(RoleNames::SISWA);

        foreach (range(0, 139) as $index) {
            LiveTracking::create([
                'user_id' => $student->id,
                'latitude' => -6.20000000 + ($index * 0.00002),
                'longitude' => 106.81666600 + ($index * 0.00002),
                'accuracy' => 8.5,
                'is_in_school_area' => $index < 90,
                'location_name' => 'Titik ' . ($index + 1),
                'tracked_at' => Carbon::parse('2026-02-09 07:00:00')->addMinutes($index),
            ]);
        }

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/history-map?user_ids=' . $student->id . '&date=2026-02-09');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.sessions.0.statistics.total_points', 140)
            ->assertJsonPath('data.sessions.0.statistics.is_route_simplified', true);

        $routePoints = data_get($response->json(), 'data.sessions.0.route_points', []);
        $this->assertIsArray($routePoints);
        $this->assertLessThan(140, count($routePoints));
        $this->assertSame(1, (int) data_get($routePoints, '0.sequence', 0));
        $this->assertSame(140, (int) data_get($routePoints, (count($routePoints) - 1) . '.sequence', 0));
    }

    public function test_admin_can_export_history_map_pdf(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa PDF Histori',
            'email' => 'pdf-histori@example.test',
        ]);
        $student->assignRole(RoleNames::SISWA);

        LiveTracking::create([
            'user_id' => $student->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'accuracy' => 8.0,
            'is_in_school_area' => true,
            'location_name' => 'Gerbang Utama',
            'tracked_at' => Carbon::parse('2026-02-09 07:30:00'),
        ]);

        LiveTracking::create([
            'user_id' => $student->id,
            'latitude' => -6.20070000,
            'longitude' => 106.81710000,
            'accuracy' => 9.0,
            'is_in_school_area' => false,
            'location_name' => 'Simpang Timur',
            'tracked_at' => Carbon::parse('2026-02-09 07:50:00'),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/live-tracking/history-map/export-pdf?user_ids=' . $student->id . '&date=2026-02-09&focus_user_id=' . $student->id . '&export_scope=focus');

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('.pdf', (string) $response->headers->get('content-disposition'));
    }

    public function test_current_tracking_can_return_priority_queues_in_single_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $gpsDisabledStudent = User::factory()->create(['nama_lengkap' => 'Siswa GPS Mati']);
        $gpsDisabledStudent->assignRole(RoleNames::SISWA);
        $this->seedTrackingSnapshot($gpsDisabledStudent, [
            'tracked_at' => now()->toISOString(),
            'status' => 'gps_disabled',
            'is_in_school_area' => true,
        ]);

        $staleStudent = User::factory()->create(['nama_lengkap' => 'Siswa Stale']);
        $staleStudent->assignRole(RoleNames::SISWA);
        $this->seedTrackingSnapshot($staleStudent, [
            'tracked_at' => now()->subMinutes(10)->toISOString(),
            'is_in_school_area' => true,
        ]);

        $outsideAreaStudent = User::factory()->create(['nama_lengkap' => 'Siswa Luar Area']);
        $outsideAreaStudent->assignRole(RoleNames::SISWA);
        $this->seedTrackingSnapshot($outsideAreaStudent, [
            'tracked_at' => now()->toISOString(),
            'is_in_school_area' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/current?include_priority_queues=1&priority_queue_limit=2');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.priority_queue_limit', 2)
            ->assertJsonPath('meta.performance.scoped_user_count', 3)
            ->assertJsonPath('meta.performance.filtered_total', 3)
            ->assertJsonPath('meta.performance.snapshot_hit_count', 3)
            ->assertJsonPath('meta.performance.chunk_count', 1)
            ->assertJsonCount(1, 'meta.priority_queues.gps_disabled')
            ->assertJsonCount(1, 'meta.priority_queues.stale')
            ->assertJsonCount(1, 'meta.priority_queues.outside_area')
            ->assertJsonPath('meta.priority_queues.gps_disabled.0.user_id', $gpsDisabledStudent->id)
            ->assertJsonPath('meta.priority_queues.stale.0.user_id', $staleStudent->id)
            ->assertJsonPath('meta.priority_queues.outside_area.0.user_id', $outsideAreaStudent->id);
    }

    public function test_current_tracking_can_use_current_store_for_summary_and_priority_meta(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $gpsDisabledStudent = User::factory()->create([
            'nama_lengkap' => 'Siswa Redis GPS Mati',
            'email' => 'gps-disabled@example.test',
            'nis' => '1001',
            'username' => 'gps.disabled',
        ]);
        $gpsDisabledStudent->assignRole(RoleNames::SISWA);
        $this->seedTrackingSnapshot($gpsDisabledStudent, [
            'tracked_at' => now()->toISOString(),
            'status' => 'gps_disabled',
            'is_in_school_area' => true,
        ]);

        $staleStudent = User::factory()->create([
            'nama_lengkap' => 'Siswa Redis Stale',
            'email' => 'stale@example.test',
            'nis' => '1002',
            'username' => 'stale.student',
        ]);
        $staleStudent->assignRole(RoleNames::SISWA);
        $this->seedTrackingSnapshot($staleStudent, [
            'tracked_at' => now()->subMinutes(10)->toISOString(),
            'is_in_school_area' => true,
        ]);

        $outsideAreaStudent = User::factory()->create([
            'nama_lengkap' => 'Siswa Redis Luar Area',
            'email' => 'outside@example.test',
            'nis' => '1003',
            'username' => 'outside.student',
        ]);
        $outsideAreaStudent->assignRole(RoleNames::SISWA);
        $this->seedTrackingSnapshot($outsideAreaStudent, [
            'tracked_at' => now()->toISOString(),
            'is_in_school_area' => false,
        ]);

        $currentStore = Mockery::mock(LiveTrackingCurrentStoreService::class);
        $currentStore->shouldReceive('readRecords')
            ->once()
            ->with('', '', 0)
            ->andReturn([
                [
                    'user_id' => $gpsDisabledStudent->id,
                    'nama_lengkap' => $gpsDisabledStudent->nama_lengkap,
                    'email' => $gpsDisabledStudent->email,
                    'nis' => $gpsDisabledStudent->nis,
                    'username' => $gpsDisabledStudent->username,
                    'kelas' => 'N/A',
                    'tingkat' => 'N/A',
                    'wali_kelas_id' => null,
                    'wali_kelas' => 'Belum ditentukan',
                    'latitude' => -6.2,
                    'longitude' => 106.8,
                    'accuracy' => 8.0,
                    'speed' => 0.0,
                    'heading' => 0.0,
                    'tracked_at' => now()->toISOString(),
                    'tracked_at_epoch' => now()->timestamp,
                    'snapshot_status' => 'gps_disabled',
                    'device_source' => 'mobile',
                    'gps_quality_status' => 'good',
                    'is_in_school_area' => true,
                    'within_gps_area' => true,
                    'has_tracking_data' => true,
                    'location_id' => null,
                    'location_name' => 'Gerbang Utama',
                    'current_location' => [
                        'name' => 'Gerbang Utama',
                        'distance_meters' => 0,
                    ],
                    'nearest_location' => [
                        'name' => 'Lapangan Utama',
                        'distance_meters' => 32.4,
                    ],
                    'distance_to_nearest' => 32.4,
                    'device_info' => [
                        'platform' => 'android',
                        'session_id' => 'sess-gps-disabled',
                    ],
                    'ip_address' => '10.0.0.1',
                    'tracking_session_active' => false,
                    'tracking_session_expires_at' => null,
                ],
                [
                    'user_id' => $staleStudent->id,
                    'nama_lengkap' => $staleStudent->nama_lengkap,
                    'email' => $staleStudent->email,
                    'nis' => $staleStudent->nis,
                    'username' => $staleStudent->username,
                    'kelas' => 'N/A',
                    'tingkat' => 'N/A',
                    'wali_kelas_id' => null,
                    'wali_kelas' => 'Belum ditentukan',
                    'latitude' => -6.2005,
                    'longitude' => 106.8005,
                    'accuracy' => 10.0,
                    'speed' => 0.0,
                    'heading' => 0.0,
                    'tracked_at' => now()->subMinutes(10)->toISOString(),
                    'tracked_at_epoch' => now()->subMinutes(10)->timestamp,
                    'snapshot_status' => 'online',
                    'device_source' => 'mobile',
                    'gps_quality_status' => 'moderate',
                    'is_in_school_area' => true,
                    'within_gps_area' => true,
                    'has_tracking_data' => true,
                    'location_id' => null,
                    'location_name' => 'Koridor Barat',
                    'current_location' => [
                        'name' => 'Koridor Barat',
                    ],
                    'nearest_location' => [
                        'name' => 'Kelas XI-2',
                    ],
                    'distance_to_nearest' => 14.8,
                    'device_info' => [
                        'platform' => 'android',
                    ],
                    'ip_address' => '10.0.0.2',
                    'tracking_session_active' => false,
                    'tracking_session_expires_at' => null,
                ],
                [
                    'user_id' => $outsideAreaStudent->id,
                    'nama_lengkap' => $outsideAreaStudent->nama_lengkap,
                    'email' => $outsideAreaStudent->email,
                    'nis' => $outsideAreaStudent->nis,
                    'username' => $outsideAreaStudent->username,
                    'kelas' => 'N/A',
                    'tingkat' => 'N/A',
                    'wali_kelas_id' => null,
                    'wali_kelas' => 'Belum ditentukan',
                    'latitude' => -6.201,
                    'longitude' => 106.801,
                    'accuracy' => 12.0,
                    'speed' => 0.0,
                    'heading' => 0.0,
                    'tracked_at' => now()->toISOString(),
                    'tracked_at_epoch' => now()->timestamp,
                    'snapshot_status' => 'online',
                    'device_source' => 'mobile',
                    'gps_quality_status' => 'good',
                    'is_in_school_area' => false,
                    'within_gps_area' => false,
                    'has_tracking_data' => true,
                    'location_id' => null,
                    'location_name' => 'Luar Gerbang',
                    'current_location' => [
                        'name' => 'Luar Gerbang',
                    ],
                    'nearest_location' => [
                        'name' => 'Gerbang Utama',
                    ],
                    'distance_to_nearest' => 87.6,
                    'device_info' => [
                        'platform' => 'ios',
                    ],
                    'ip_address' => '10.0.0.3',
                    'tracking_session_active' => false,
                    'tracking_session_expires_at' => null,
                ],
            ]);
        $this->app->instance(LiveTrackingCurrentStoreService::class, $currentStore);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/current?include_class_summary=1&include_priority_queues=1&priority_queue_limit=2');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.list_source', 'redis_current_store')
            ->assertJsonPath('meta.summary_source', 'redis_current_store')
            ->assertJsonPath('meta.group_summary_source', 'redis_current_store')
            ->assertJsonPath('meta.priority_queue_source', 'redis_current_store')
            ->assertJsonPath('meta.performance.current_store_source', 'redis_current_store')
            ->assertJsonPath('meta.summary.gps_disabled', 1)
            ->assertJsonPath('meta.summary.stale', 1)
            ->assertJsonPath('meta.summary.outside_area', 1)
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.user_id', $gpsDisabledStudent->id)
            ->assertJsonPath('data.0.device_info.platform', 'android')
            ->assertJsonPath('data.0.current_location.name', 'Gerbang Utama')
            ->assertJsonPath('data.0.nearest_location.name', 'Lapangan Utama')
            ->assertJsonPath('data.0.distance_to_nearest', 32.4)
            ->assertJsonPath('meta.priority_queues.gps_disabled.0.user_id', $gpsDisabledStudent->id)
            ->assertJsonPath('meta.priority_queues.stale.0.user_id', $staleStudent->id)
            ->assertJsonPath('meta.priority_queues.outside_area.0.user_id', $outsideAreaStudent->id);
    }

    public function test_current_tracking_can_disable_current_store_read_path_via_config(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));
        config()->set('attendance.live_tracking.read_current_store_enabled', false);

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Request Pipeline',
            'email' => 'pipeline@example.test',
            'nis' => '2001',
            'username' => 'pipeline.student',
        ]);
        $student->assignRole(RoleNames::SISWA);
        $this->seedTrackingSnapshot($student, [
            'tracked_at' => now()->toISOString(),
            'is_in_school_area' => true,
        ]);

        $currentStore = Mockery::mock(LiveTrackingCurrentStoreService::class);
        $currentStore->shouldNotReceive('readRecords');
        $this->app->instance(LiveTrackingCurrentStoreService::class, $currentStore);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/current?include_class_summary=1&include_priority_queues=1');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.list_source', 'request_pipeline')
            ->assertJsonPath('meta.summary_source', 'request_pipeline')
            ->assertJsonPath('meta.group_summary_source', 'request_pipeline')
            ->assertJsonPath('meta.priority_queue_source', 'request_pipeline')
            ->assertJsonPath('meta.history_policy.read_current_store_enabled', false)
            ->assertJsonPath('meta.performance.current_store_read_enabled', false)
            ->assertJsonPath('meta.performance.current_store_source', 'request_pipeline')
            ->assertJsonPath('data.0.user_id', $student->id);
    }

    public function test_current_tracking_marks_rows_as_tracking_disabled_when_policy_is_globally_disabled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));
        config()->set('attendance.live_tracking.read_current_store_enabled', false);

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Tracking Global Off',
            'email' => 'tracking-off@example.test',
            'nis' => '3001',
            'username' => 'tracking.off',
        ]);
        $student->assignRole(RoleNames::SISWA);

        DB::table('attendance_settings')->insert([
            'schema_name' => 'Global Tracking Disabled',
            'schema_type' => 'global',
            'is_active' => true,
            'is_default' => true,
            'live_tracking_enabled' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        Cache::forever('attendance_runtime_version', (int) Cache::get('attendance_runtime_version', 1) + 1);

        $this->seedTrackingSnapshot($student, [
            'tracked_at' => now()->toISOString(),
            'is_in_school_area' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/current?include_class_summary=1');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.history_policy.enabled', false)
            ->assertJsonPath('meta.summary.tracking_disabled', 1)
            ->assertJsonPath('data.0.user_id', $student->id)
            ->assertJsonPath('data.0.tracking_status', 'tracking_disabled')
            ->assertJsonPath('data.0.tracking_status_reason', 'tracking_dinonaktifkan_admin');
    }

    public function test_admin_can_export_live_tracking_data_as_csv(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        LiveTracking::create([
            'user_id' => $student->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'accuracy' => 9.5,
            'is_in_school_area' => true,
            'tracked_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/live-tracking/export?format=csv&date_range=today');

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString(
            'live-tracking-20260209-20260209.csv',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_admin_can_export_live_tracking_data_with_selected_columns(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Export Kolom',
        ]);
        $student->assignRole(RoleNames::SISWA);

        LiveTracking::create([
            'user_id' => $student->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'accuracy' => 8.1,
            'speed' => 1.5,
            'location_name' => 'Gerbang Timur',
            'device_source' => 'mobile',
            'gps_quality_status' => 'good',
            'device_info' => [
                'platform' => 'android',
                'session_id' => 'mobile-session-1',
            ],
            'ip_address' => '10.0.0.25',
            'is_in_school_area' => true,
            'tracked_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->get('/api/live-tracking/export?format=csv&date_range=today&include_basicInfo=1&include_trackingState=1&include_locationData=0&include_timestamps=0&include_deviceInfo=1');

        $response->assertStatus(200);

        $file = $response->baseResponse->getFile();
        $this->assertNotNull($file);

        $content = file_get_contents($file->getPathname());
        $this->assertNotFalse($content);
        $this->assertStringContainsString('Nama', $content);
        $this->assertStringContainsString('Sumber Device', $content);
        $this->assertStringContainsString('IP Address', $content);
        $this->assertStringContainsString('Platform', $content);
        $this->assertStringContainsString('Session ID', $content);
        $this->assertStringContainsString('Siswa Export Kolom', $content);
        $this->assertStringContainsString('mobile', $content);
        $this->assertStringContainsString('android', $content);
        $this->assertStringContainsString('mobile-session-1', $content);
        $this->assertStringNotContainsString('Latitude', $content);
        $this->assertStringNotContainsString('Longitude', $content);
        $this->assertStringNotContainsString('Waktu Tracking', $content);
    }

    public function test_users_in_radius_returns_only_latest_fresh_point_per_student(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        $this->seedTrackingSnapshot($student, [
            'latitude' => -6.30000000,
            'longitude' => 106.91666600,
            'is_in_school_area' => false,
            'tracked_at' => now()->toISOString(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/live-tracking/users-in-radius', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'radius' => 500,
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_current_location_marks_today_but_stale_tracking(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        $this->seedTrackingSnapshot($student, [
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'is_in_school_area' => true,
            'tracked_at' => now()->subMinutes(10)->toISOString(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/current-location?user_id=' . $student->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_tracking_active', false)
            ->assertJsonPath('data.tracking_status', 'stale');
    }

    public function test_siswa_sender_flow_updates_realtime_and_history_channels(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 08:30:00')); // Monday

        $admin = User::factory()->create([
            'nama_lengkap' => 'Admin Monitoring',
        ]);
        $admin->assignRole(RoleNames::ADMIN);

        $siswa = User::factory()->create([
            'nama_lengkap' => 'Siswa Sender Web',
        ]);
        $siswa->assignRole(RoleNames::SISWA);

        LokasiGps::create([
            'nama_lokasi' => 'Sekolah C',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius' => 250,
            'is_active' => true,
        ]);

        $payload = [
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'accuracy' => 8,
        ];

        $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', $payload)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $siswa->id);

        $activeUsers = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/lokasi-gps/active-users');

        $activeUsers->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_active_users', 1)
            ->assertJsonPath('data.users_in_gps_area', 1)
            ->assertJsonPath('data.active_users.0.user_id', $siswa->id);

        $history = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/live-tracking/history?user_id=' . $siswa->id . '&date=2026-02-09');

        $history->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.tracking')
            ->assertJsonPath('data.tracking.0.user_id', $siswa->id);
    }

    public function test_update_location_persists_tracking_metadata_and_current_snapshot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 08:45:00'));

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Metadata',
        ]);
        $student->assignRole(RoleNames::SISWA);

        LokasiGps::create([
            'nama_lokasi' => 'Sekolah Metadata',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius' => 250,
            'is_active' => true,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 12,
                'speed' => 1.5,
                'heading' => 180,
                'device_source' => 'web',
                'device_session_id' => 'web-test-session',
                'platform' => 'Windows',
                'app_version' => 'frontend-web',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.device_source', 'web')
            ->assertJsonPath('data.gps_quality_status', 'good')
            ->assertJsonPath('data.location_name', 'Sekolah Metadata')
            ->assertJsonPath('data.current_location.nama_lokasi', 'Sekolah Metadata');

        $this->assertDatabaseHas('live_tracking', [
            'user_id' => $student->id,
            'location_name' => 'Sekolah Metadata',
            'device_source' => 'web',
            'gps_quality_status' => 'good',
        ]);
    }

    public function test_live_tracking_cleanup_command_deletes_old_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        LiveTracking::create([
            'user_id' => $student->id,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'is_in_school_area' => true,
            'tracked_at' => now()->subDays(40),
        ]);

        LiveTracking::create([
            'user_id' => $student->id,
            'latitude' => -6.20100000,
            'longitude' => 106.81766600,
            'is_in_school_area' => true,
            'tracked_at' => now()->subDays(5),
        ]);

        $this->artisan('live-tracking:cleanup --days=30')
            ->assertExitCode(0);

        $this->assertDatabaseCount('live_tracking', 1);
    }

    public function test_wali_kelas_with_tracking_permission_only_sees_own_class_students(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 10:00:00'));

        $wali = User::factory()->create([
            'nama_lengkap' => 'Wali Tracking',
        ]);
        $wali->assignRole(RoleNames::WALI_KELAS);

        $tingkat = Tingkat::create([
            'nama' => 'Kelas 7',
            'kode' => 'VII',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $tahunAjaran = TahunAjaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'is_active' => true,
            'status' => TahunAjaran::STATUS_ACTIVE,
            'semester' => 'ganjil',
        ]);

        $kelasWali = Kelas::create([
            'nama_kelas' => '7A',
            'tingkat_id' => $tingkat->id,
            'wali_kelas_id' => $wali->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 30,
            'jumlah_siswa' => 0,
            'is_active' => true,
        ]);

        $kelasLain = Kelas::create([
            'nama_kelas' => '7B',
            'tingkat_id' => $tingkat->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 30,
            'jumlah_siswa' => 0,
            'is_active' => true,
        ]);

        $studentA = User::factory()->create(['nama_lengkap' => 'Siswa 7A']);
        $studentA->assignRole(RoleNames::SISWA);
        $studentB = User::factory()->create(['nama_lengkap' => 'Siswa 7B']);
        $studentB->assignRole(RoleNames::SISWA);

        DB::table('kelas_siswa')->insert([
            [
                'kelas_id' => $kelasWali->id,
                'siswa_id' => $studentA->id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'status' => 'aktif',
                'is_active' => true,
                'tanggal_masuk' => '2025-07-10',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'kelas_id' => $kelasLain->id,
                'siswa_id' => $studentB->id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'status' => 'aktif',
                'is_active' => true,
                'tanggal_masuk' => '2025-07-10',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $this->seedTrackingSnapshot($studentA, [
            'location_name' => 'Lokasi 7A',
        ]);
        $this->seedTrackingSnapshot($studentB, [
            'location_name' => 'Lokasi 7B',
        ]);

        $response = $this->actingAs($wali, 'sanctum')
            ->getJson('/api/live-tracking/current');

        $response->assertStatus(200)
            ->assertJsonPath('meta.summary.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user_id', $studentA->id);
    }

    private function seedRequiredRoles(): void
    {
        $roles = [
            RoleNames::SISWA,
            RoleNames::GURU,
            RoleNames::ADMIN,
            RoleNames::WALI_KELAS,
        ];

        foreach ($roles as $index => $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'display_name' => $roleName,
                    'description' => 'Role for live tracking integration test',
                    'level' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        Permission::firstOrCreate(
            ['name' => 'view_live_tracking', 'guard_name' => 'web'],
            [
                'display_name' => 'View Live Tracking',
                'description' => 'View student live tracking',
                'module' => 'tracking',
            ]
        );

        Permission::firstOrCreate(
            ['name' => 'manage_live_tracking', 'guard_name' => 'web'],
            [
                'display_name' => 'Manage Live Tracking',
                'description' => 'Manage student live tracking',
                'module' => 'tracking',
            ]
        );

        Role::where('name', RoleNames::ADMIN)
            ->where('guard_name', 'web')
            ->firstOrFail()
            ->syncPermissions(['view_live_tracking', 'manage_live_tracking']);

        Role::where('name', RoleNames::WALI_KELAS)
            ->where('guard_name', 'web')
            ->firstOrFail()
            ->syncPermissions(['view_live_tracking']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function seedTrackingSnapshot(User $user, array $overrides = []): void
    {
        $base = [
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?: $user->email,
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'accuracy' => 10.0,
            'speed' => null,
            'heading' => null,
            'tracked_at' => now()->toISOString(),
            'status' => 'online',
            'is_in_school_area' => true,
            'within_gps_area' => true,
            'location_id' => null,
            'location_name' => 'Snapshot Test',
            'current_location' => null,
            'nearest_location' => null,
            'distance_to_nearest' => null,
            'gps_quality_status' => 'good',
            'device_source' => 'web',
            'device_info' => ['source' => 'test'],
            'ip_address' => '127.0.0.1',
            'tracking_session_active' => false,
            'tracking_session_expires_at' => null,
        ];

        app(LiveTrackingSnapshotService::class)->put(array_merge($base, $overrides));
    }
}
