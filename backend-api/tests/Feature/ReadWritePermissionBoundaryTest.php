<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadWritePermissionBoundaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_view_siswa_permission_can_access_read_routes_but_not_write_routes(): void
    {
        $user = $this->makeUserWithPermissions(['view_siswa']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/siswa')
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/siswa-extended')
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/siswa', [])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/siswa/import', [])
            ->assertStatus(403);
    }

    public function test_manage_students_permission_can_reach_siswa_write_routes(): void
    {
        $user = $this->makeUserWithPermissions(['manage_students']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/siswa', [])
            ->assertStatus(422);
    }

    public function test_view_pegawai_permission_can_access_read_routes_but_not_write_routes(): void
    {
        $user = $this->makeUserWithPermissions(['view_pegawai']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/pegawai')
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/pegawai', [])
            ->assertStatus(403);
    }

    public function test_manage_pegawai_permission_can_reach_pegawai_write_routes(): void
    {
        $user = $this->makeUserWithPermissions(['manage_pegawai']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/pegawai', [])
            ->assertStatus(422);
    }

    public function test_non_manager_cannot_access_event_akademik_libur_sync_routes(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/preview-libur-nasional', [])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-libur-nasional', [])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/auto-sync-libur-nasional', [])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/preview-kalender-indonesia', [])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-kalender-indonesia', [])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-kalender-indonesia-lengkap', [])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/auto-sync-kalender-indonesia', [])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-kalender-absensi', [])
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/event-akademik/sync-kalender-absensi/status')
            ->assertStatus(403);
    }

    public function test_manage_event_akademik_permission_can_reach_libur_sync_routes(): void
    {
        $user = $this->makeUserWithPermissions(['manage_event_akademik']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/preview-libur-nasional', [])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-libur-nasional', [])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/preview-kalender-indonesia', [])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-kalender-indonesia', [])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-kalender-indonesia-lengkap', [])
            ->assertStatus(422);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/auto-sync-kalender-indonesia', [])
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-kalender-absensi', [])
            ->assertStatus(200);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/event-akademik/sync-kalender-absensi/status')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'summary',
                    'missing_in_kalender',
                    'orphan_in_kalender',
                ],
            ]);
    }

    private function makeUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->syncPermissions($permissions);

        return $user;
    }
}
