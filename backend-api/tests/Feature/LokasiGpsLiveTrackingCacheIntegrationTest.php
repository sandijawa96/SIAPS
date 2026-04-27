<?php

namespace Tests\Feature;

use App\Models\LokasiGps;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LokasiGpsLiveTrackingCacheIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::firstOrCreate(
            ['name' => RoleNames::ADMIN, 'guard_name' => 'web'],
            [
                'display_name' => 'Admin',
                'description' => 'Role for lokasi gps live tracking cache test',
                'level' => 1,
                'is_active' => true,
            ]
        );

        Role::firstOrCreate(
            ['name' => RoleNames::SISWA, 'guard_name' => 'web'],
            [
                'display_name' => 'Siswa',
                'description' => 'Role siswa untuk update lokasi realtime',
                'level' => 2,
                'is_active' => true,
            ]
        );

        Permission::firstOrCreate(
            ['name' => 'view_live_tracking', 'guard_name' => 'web'],
            [
                'display_name' => 'View Live Tracking',
                'description' => 'View active user locations',
                'module' => 'tracking',
            ]
        );

        Role::where('name', RoleNames::ADMIN)
            ->where('guard_name', 'web')
            ->firstOrFail()
            ->syncPermissions(['view_live_tracking']);

        Carbon::setTestNow(Carbon::parse('2026-02-09 08:30:00')); // Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_active_users_and_users_in_location_work_without_redis_key_scan(): void
    {
        $admin = User::factory()->create([
            'nama_lengkap' => 'Admin Tracking',
        ]);
        $admin->assignRole(RoleNames::ADMIN);

        $siswa = User::factory()->create([
            'nama_lengkap' => 'Siswa Tracking',
        ]);
        $siswa->assignRole(RoleNames::SISWA);

        $location = LokasiGps::create([
            'nama_lokasi' => 'Sekolah Test',
            'latitude' => -6.20000000,
            'longitude' => 106.81666600,
            'radius' => 250,
            'is_active' => true,
        ]);

        $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 8,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $siswa->id);

        $activeUsers = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/lokasi-gps/active-users');

        $activeUsers->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_active_users', 1)
            ->assertJsonPath('data.users_in_gps_area', 1);

        $usersInLocation = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/lokasi-gps/{$location->id}/users");

        $usersInLocation->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_users', 1);
    }

    public function test_update_location_is_restricted_to_siswa_role(): void
    {
        $admin = User::factory()->create([
            'nama_lengkap' => 'Admin Non Siswa',
        ]);
        $admin->assignRole(RoleNames::ADMIN);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 7,
            ])
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Fitur update lokasi realtime hanya untuk siswa',
            ]);
    }

    public function test_update_location_rejects_siswa_outside_school_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-09 22:15:00')); // Monday night

        $siswa = User::factory()->create([
            'nama_lengkap' => 'Siswa Malam Hari',
        ]);
        $siswa->assignRole(RoleNames::SISWA);

        $response = $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 7,
            ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertStringContainsString(
            'Tracking realtime hanya aktif saat jam absensi',
            (string) $response->json('message')
        );
    }

    public function test_update_location_persists_history_using_movement_or_idle_checkpoint_policy(): void
    {
        $siswa = User::factory()->create([
            'nama_lengkap' => 'Siswa Throttle',
        ]);
        $siswa->assignRole(RoleNames::SISWA);

        LokasiGps::create([
            'nama_lokasi' => 'Sekolah Throttle',
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
            ->assertStatus(200);

        $this->assertDatabaseCount('live_tracking', 1);

        Carbon::setTestNow(Carbon::parse('2026-02-09 08:30:30'));
        $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', $payload)
            ->assertStatus(200);

        $this->assertDatabaseCount('live_tracking', 1);

        Carbon::setTestNow(Carbon::parse('2026-02-09 08:31:01'));
        $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', $payload)
            ->assertStatus(200);

        $this->assertDatabaseCount('live_tracking', 1);

        Carbon::setTestNow(Carbon::parse('2026-02-09 08:35:01'));
        $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', $payload)
            ->assertStatus(200);

        $this->assertDatabaseCount('live_tracking', 2);
    }

    public function test_update_location_rejects_when_live_tracking_is_globally_disabled(): void
    {
        $siswa = User::factory()->create([
            'nama_lengkap' => 'Siswa Tracking Disabled',
        ]);
        $siswa->assignRole(RoleNames::SISWA);

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

        $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/lokasi-gps/update-location', [
                'latitude' => -6.20000000,
                'longitude' => 106.81666600,
                'accuracy' => 8,
            ])
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Live tracking sedang dinonaktifkan oleh admin',
            ]);

        $this->actingAs($siswa, 'sanctum')
            ->getJson('/api/lokasi-gps/attendance-schema')
            ->assertStatus(200)
            ->assertJsonPath('data.tracking_policy.enabled', false)
            ->assertJsonPath('data.tracking_policy.window_open', false)
            ->assertJsonPath('data.tracking_policy.reason', 'globally_disabled');
    }
}
