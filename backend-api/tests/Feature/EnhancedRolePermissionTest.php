<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EnhancedRolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $guru;
    protected $waliKelas;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Run the role permission seeder
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        
        // Get test users
        $this->superAdmin = User::where('username', 'superadmin')->first();
        $this->guru = User::where('username', 'guru')->first();
        $this->waliKelas = User::where('username', 'walikelas')->first();
    }

    public function test_roles_have_correct_structure()
    {
        $roles = Role::all();
        
        $this->assertGreaterThan(0, $roles->count());
        
        foreach ($roles as $role) {
            $this->assertNotNull($role->name);
            $this->assertNotNull($role->display_name);
            $this->assertNotNull($role->level);
            $this->assertTrue(in_array($role->is_active, [0, 1, true, false]));
        }
    }

    public function test_permissions_have_modules()
    {
        $permissions = Permission::all();
        
        $this->assertGreaterThan(0, $permissions->count());
        
        foreach ($permissions as $permission) {
            $this->assertNotNull($permission->name);
            $this->assertNotNull($permission->display_name);
            $this->assertNotNull($permission->module);
        }
    }

    public function test_role_hierarchy_endpoint()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/roles/hierarchy');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'display_name',
                        'level',
                        'users_count'
                    ]
                ]
            ]);
    }

    public function test_effective_permissions_endpoint()
    {
        $guruRole = Role::where('name', 'Guru')->first();
        $waliKelasRole = Role::where('name', 'Wali Kelas')->first();

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/roles/effective-permissions', [
                'role_ids' => [$guruRole->id, $waliKelasRole->id]
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'roles',
                    'effective_permissions'
                ]
            ]);
    }

    public function test_permissions_grouped_by_module()
    {
        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->getJson('/api/permissions/by-module');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        '*' => [
                            'id',
                            'name',
                            'display_name',
                            'module'
                        ]
                    ]
                ]
            ]);
    }

    public function test_user_with_multiple_roles()
    {
        $multiRoleUser = User::where('username', 'multiroleteacher')->first();
        
        $this->assertTrue($multiRoleUser->hasRole(['Guru', 'Wali Kelas']));
        $this->assertTrue($multiRoleUser->hasAnyRole(['Guru', 'Wali Kelas']));
    }

    public function test_role_level_hierarchy()
    {
        $superAdminRole = Role::where('name', 'Super_Admin')->first();
        $guruRole = Role::where('name', 'Guru')->first();
        $siswaRole = Role::where('name', 'Siswa')->first();

        $this->assertGreaterThan($guruRole->level, $superAdminRole->level);
        $this->assertGreaterThan($siswaRole->level, $guruRole->level);
    }

    public function test_permission_modules_exist()
    {
        $modules = Permission::distinct('module')->pluck('module');
        
        $expectedModules = [
            'users',
            'roles',
            'permissions',
            'students',
            'employees',
            'classes',
            'academic',
            'attendance',
            'leave',
            'settings',
            'reports',
            'notifications',
            'communication',
            'tracking',
            'system',
        ];

        foreach ($expectedModules as $module) {
            $this->assertContains($module, $modules->toArray());
        }
    }

    public function test_role_creation_with_enhanced_fields()
    {
        $roleData = [
            'name' => 'Test_Role',
            'display_name' => 'Test Role',
            'description' => 'A test role for testing',
            'level' => 25,
            'is_active' => true,
            'permissions' => ['view_absensi', 'view_reports']
        ];

        $response = $this->actingAs($this->superAdmin, 'sanctum')
            ->postJson('/api/roles', $roleData);

        $response->assertStatus(201);
        
        $role = Role::where('name', 'Test_Role')->first();
        $this->assertNotNull($role);
        $this->assertEquals('Test Role', $role->display_name);
        $this->assertEquals('A test role for testing', $role->description);
        $this->assertEquals(25, $role->level);
        $this->assertTrue($role->is_active);
    }

    public function test_unauthorized_access_to_role_management()
    {
        $response = $this->actingAs($this->guru, 'sanctum')
            ->getJson('/api/roles');

        $response->assertStatus(403);
    }

    public function test_super_admin_has_all_permissions()
    {
        $allPermissions = Permission::all();
        
        foreach ($allPermissions as $permission) {
            $this->assertTrue($this->superAdmin->can($permission->name));
        }
    }
}
