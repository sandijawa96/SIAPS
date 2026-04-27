<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

class AuthEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected $roleGuru;
    protected $roleWaliKelas;
    protected $roleSuperAdmin;
    protected $roleGuruApi;
    protected $roleWaliKelasApi;
    protected $roleSuperAdminApi;
    protected $permAbsensiDiri;
    protected $permInputNilai;
    protected $permAbsensiDiriApi;
    protected $permInputNilaiApi;
    protected $user;
    protected $superAdmin;
    protected $userNoRole;

    protected function setUp(): void
    {
        parent::setUp();

        // Create roles for web guard
        $this->roleGuru = Role::create([
            'name' => 'Guru_web',
            'display_name' => 'Guru',
            'guard_name' => 'web'
        ]);
        $this->roleWaliKelas = Role::create([
            'name' => 'Wali_Kelas_web',
            'display_name' => 'Wali Kelas',
            'guard_name' => 'web'
        ]);
        $this->roleSuperAdmin = Role::create([
            'name' => 'Super_Admin_web',
            'display_name' => 'Super Admin',
            'guard_name' => 'web'
        ]);

        // Create roles for API guard
        $this->roleGuruApi = Role::create([
            'name' => 'Guru_api',
            'display_name' => 'Guru',
            'guard_name' => 'api'
        ]);
        $this->roleWaliKelasApi = Role::create([
            'name' => 'Wali_Kelas_api',
            'display_name' => 'Wali Kelas',
            'guard_name' => 'api'
        ]);
        $this->roleSuperAdminApi = Role::create([
            'name' => 'Super_Admin_api',
            'display_name' => 'Super Admin',
            'guard_name' => 'api'
        ]);

        // Create permissions for web guard
        $this->permAbsensiDiri = Permission::create([
            'name' => 'absensi_diri_web',
            'display_name' => 'Absensi Diri',
            'guard_name' => 'web'
        ]);
        $this->permInputNilai = Permission::create([
            'name' => 'input_nilai_web',
            'display_name' => 'Input Nilai',
            'guard_name' => 'web'
        ]);

        // Create permissions for API guard
        $this->permAbsensiDiriApi = Permission::create([
            'name' => 'absensi_diri_api',
            'display_name' => 'Absensi Diri',
            'guard_name' => 'api'
        ]);
        $this->permInputNilaiApi = Permission::create([
            'name' => 'input_nilai_api',
            'display_name' => 'Input Nilai',
            'guard_name' => 'api'
        ]);

        // Assign permissions to web roles
        $this->roleGuru->givePermissionTo($this->permAbsensiDiri);
        $this->roleWaliKelas->givePermissionTo($this->permInputNilai);

        // Assign permissions to API roles
        $this->roleGuruApi->givePermissionTo($this->permAbsensiDiriApi);
        $this->roleWaliKelasApi->givePermissionTo($this->permInputNilaiApi);

        // Create users
        $this->user = User::factory()->create([
            'email' => 'user@test.com',
            'password' => Hash::make('password123')
        ]);
        // Assign roles
        $this->user->assignRole([
            $this->roleGuru,
            $this->roleWaliKelas,
            $this->roleGuruApi,
            $this->roleWaliKelasApi
        ]);

        $this->superAdmin = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => Hash::make('password123')
        ]);
        $this->superAdmin->assignRole([
            $this->roleSuperAdmin,
            $this->roleSuperAdminApi
        ]);

        $this->userNoRole = User::factory()->create([
            'email' => 'norole@test.com',
            'password' => Hash::make('password123')
        ]);
    }

    /** @test */
    public function login_web_returns_union_of_permissions()
    {
        $response = $this->postJson('/api/web/login', [
            'email' => 'user@test.com',
            'password' => 'password123'
        ]);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'permissions',
                            'roles'
                        ],
                        'access_token'
                    ]
                ]);

        $permissions = $response->json('data.user.permissions');
        $this->assertContains('absensi_diri_web', $permissions);
        $this->assertContains('input_nilai_web', $permissions);
    }

    /** @test */
    public function login_mobile_returns_union_of_permissions()
    {
        $response = $this->postJson('/api/mobile/login', [
            'email' => 'user@test.com',
            'password' => 'password123'
        ]);

        // Debug response
        if ($response->status() !== 200) {
            dump('Mobile login response:', $response->json());
        }

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'user' => [
                            'permissions',
                            'roles'
                        ],
                        'token'
                    ]
                ]);

        $permissions = $response->json('data.user.permissions');
        $this->assertContains('absensi_diri_api', $permissions);
        $this->assertContains('input_nilai_api', $permissions);
    }

    /** @test */
    public function profile_endpoint_includes_all_permissions()
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/web/profile');

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'permissions',
                        'roles'
                    ]
                ]);

        $permissions = $response->json('data.permissions');
        $this->assertContains('absensi_diri_web', $permissions);
        $this->assertContains('input_nilai_web', $permissions);
    }

    /** @test */
    public function superadmin_has_access_to_all_permissions()
    {
        $token = $this->superAdmin->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/check-permission', [
            'permission' => 'any_random_permission'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'has_permission' => true
                    ]
                ]);
    }

    /** @test */
    public function user_without_role_has_no_permissions()
    {
        $token = $this->userNoRole->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/check-permission', [
            'permission' => 'absensi_diri_web'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'has_permission' => false
                    ]
                ]);
    }

    /** @test */
    public function overlapping_permissions_are_handled_correctly()
    {
        // Give both roles the same permission
        $this->roleGuru->givePermissionTo($this->permInputNilai);
        
        $response = $this->postJson('/api/web/login', [
            'email' => 'user@test.com',
            'password' => 'password123'
        ]);

        $permissions = $response->json('data.user.permissions');
        
        // Should only appear once in the permissions array
        $this->assertEquals(
            1,
            collect($permissions)->filter(function ($permission) {
                return $permission === 'input_nilai_web';
            })->count()
        );
    }

    /** @test */
    public function invalid_permission_check_returns_false()
    {
        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/check-permission', [
            'permission' => 'non_existent_permission'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'has_permission' => false
                    ]
                ]);
    }

    /** @test */
    public function cascade_delete_works_correctly()
    {
        // Delete a role and verify permissions are removed
        $this->roleGuru->delete();

        $token = $this->user->createToken('test-token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/check-permission', [
            'permission' => 'absensi_diri_web'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'has_permission' => false
                    ]
                ]);
    }
}
