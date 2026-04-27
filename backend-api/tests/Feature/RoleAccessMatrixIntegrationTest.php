<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PermissionCatalog;
use App\Support\RoleAccessMatrix;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessMatrixIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_role_access_matrix_permissions_are_in_catalog(): void
    {
        $catalogNames = PermissionCatalog::names();

        foreach (RoleAccessMatrix::permissionMap() as $roleName => $permissions) {
            foreach ($permissions as $permission) {
                if ($permission === RoleAccessMatrix::WILDCARD_ALL) {
                    continue;
                }

                $this->assertTrue(
                    in_array($permission, $catalogNames, true),
                    "Role {$roleName} references unknown permission {$permission}"
                );
            }
        }
    }

    public function test_feature_matrix_endpoint_returns_synced_baseline(): void
    {
        $superAdmin = User::where('username', 'superadmin')->firstOrFail();

        $response = $this->actingAs($superAdmin, 'sanctum')
            ->getJson('/api/roles/feature-matrix');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'role_name',
                        'display_name',
                        'description',
                        'level',
                        'features',
                        'recommended_permissions',
                        'active_permissions',
                        'missing_permissions',
                        'extra_permissions',
                    ],
                ],
            ]);

        $rows = collect($response->json('data'));

        $wali = $rows->firstWhere('role_name', RoleNames::WALI_KELAS);
        $this->assertNotNull($wali);
        $this->assertContains('approve_izin', $wali['recommended_permissions']);
        $this->assertContains('approve_izin', $wali['active_permissions']);
        $this->assertSame([], $wali['missing_permissions']);
        $this->assertSame([], $wali['extra_permissions']);

        $admin = $rows->firstWhere('role_name', RoleNames::ADMIN);
        $this->assertNotNull($admin);
        $this->assertContains('manage_users', $admin['recommended_permissions']);
        $this->assertContains('manage_users', $admin['active_permissions']);
        $this->assertSame([], $admin['missing_permissions']);
    }

    public function test_my_feature_profile_endpoint_is_available_for_authenticated_user_without_view_roles_permission(): void
    {
        $guru = User::factory()->create();
        $guru->assignRole(RoleNames::GURU);

        $this->assertFalse($guru->hasPermissionTo('view_roles'));

        $response = $this->actingAs($guru, 'sanctum')
            ->getJson('/api/roles/my-feature-profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonPath('data.assigned_roles.0', RoleNames::GURU)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'user_id',
                    'assigned_roles',
                    'roles' => [
                        '*' => [
                            'assigned_role_name',
                            'canonical_role_name',
                            'display_name',
                            'description',
                            'level',
                            'features',
                            'recommended_permissions',
                        ],
                    ],
                    'features',
                    'effective_permissions',
                    'recommended_permissions',
                    'missing_permissions',
                    'extra_permissions',
                ],
            ]);

        $roles = collect($response->json('data.roles'));
        $guruRole = $roles->firstWhere('canonical_role_name', RoleNames::GURU);

        $this->assertNotNull($guruRole);
        $this->assertContains('submit_izin', $guruRole['recommended_permissions']);
        $this->assertContains('Ajukan izin pribadi', $response->json('data.features'));
    }
}
