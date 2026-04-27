<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceAdminAccessIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_non_manager_cannot_access_admin_attendance_endpoints(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/simple-attendance/users/all')
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/simple-attendance/summary')
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/user-schema-stats/global')
            ->assertStatus(403);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/bulk-assignment/stats')
            ->assertStatus(403);
    }

    public function test_simple_attendance_user_settings_allows_self_but_blocks_other_user(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/simple-attendance/user/' . $userA->id)
            ->assertStatus(200)
            ->assertJsonPath('user_id', $userA->id);

        $this->actingAs($userA, 'sanctum')
            ->getJson('/api/simple-attendance/user/' . $userB->id)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak memiliki akses untuk melihat pengaturan user lain');
    }

    public function test_manager_can_access_admin_attendance_endpoints(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('manage_attendance_settings');

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/simple-attendance/users/all')
            ->assertStatus(200);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/simple-attendance/summary')
            ->assertStatus(200);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/user-schema-stats/global')
            ->assertStatus(200);

        $this->actingAs($manager, 'sanctum')
            ->getJson('/api/bulk-assignment/stats')
            ->assertStatus(200);
    }
}

