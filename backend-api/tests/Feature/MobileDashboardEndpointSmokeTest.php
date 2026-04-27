<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MobileDashboardEndpointSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_mobile_dashboard_returns_real_payload_with_server_meta(): void
    {
        $user = $this->createUserWithRole(RoleNames::SISWA);
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        Absensi::create([
            'user_id' => $user->id,
            'kelas_id' => null,
            'tanggal' => $today,
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
            'metode_absensi' => 'selfie',
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'kelas_id' => null,
            'tanggal' => $yesterday,
            'jam_masuk' => '07:20:00',
            'status' => 'terlambat',
            'metode_absensi' => 'selfie',
        ]);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/mobile/dashboard');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.attendance_status.status', 'hadir')
            ->assertJsonPath('meta.server_date', $today)
            ->assertJsonPath('meta.timezone', config('app.timezone'));

        $this->assertNotEmpty($response->json('meta.server_now'));
        $this->assertIsInt((int) $response->json('meta.server_epoch_ms'));
        $this->assertNotEmpty($response->json('data.month_summary'));
        $this->assertNotEmpty($response->json('data.recent_activities'));
    }

    private function createUserWithRole(string $canonicalRole): User
    {
        $role = Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->where('guard_name', 'web')
            ->first();

        $this->assertNotNull($role, "Role not found for {$canonicalRole}");

        $user = User::factory()->create();
        $user->assignRole($role->name);

        return $user;
    }
}
