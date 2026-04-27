<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogControllerIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(
            ['name' => 'Admin', 'guard_name' => 'web'],
            ['display_name' => 'Admin', 'description' => 'Role for activity log test', 'level' => 10, 'is_active' => true]
        );

        $permissions = [
            'view_activity_logs' => 'View activity logs',
            'manage_activity_logs' => 'Manage activity logs',
        ];

        foreach ($permissions as $name => $displayName) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['display_name' => $displayName, 'description' => 'Permission for activity log test', 'module' => 'system']
            );
        }

        $role->givePermissionTo(array_keys($permissions));

        $this->admin = User::factory()->create();
        $this->admin->assignRole($role);

        Sanctum::actingAs($this->admin);
    }

    public function test_index_returns_schema_backed_logs_with_legacy_aliases(): void
    {
        ActivityLog::create([
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
            'event' => 'create_user',
            'log_name' => 'users',
            'module' => 'users',
            'description' => 'Created user from test',
            'ip_address' => '127.0.0.1',
            'properties' => [
                'old' => ['status' => 'pending'],
                'new' => ['status' => 'active'],
            ],
        ]);

        $response = $this->getJson('/api/activity-logs');

        $response->assertOk()
            ->assertJsonPath('data.data.0.action', 'create_user')
            ->assertJsonPath('data.data.0.notes', 'Created user from test')
            ->assertJsonPath('data.data.0.user.nama_lengkap', $this->admin->nama_lengkap)
            ->assertJsonPath('data.data.0.user_id', $this->admin->id);
    }

    public function test_statistics_aggregates_modules_actions_and_top_users_correctly(): void
    {
        $userTwo = User::factory()->create();

        ActivityLog::create([
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
            'event' => 'create',
            'log_name' => 'users',
            'module' => 'users',
            'description' => 'Created something',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        ActivityLog::create([
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
            'event' => 'update',
            'log_name' => 'users',
            'module' => 'users',
            'description' => 'Updated something',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        ActivityLog::create([
            'causer_id' => $userTwo->id,
            'causer_type' => User::class,
            'event' => 'export',
            'log_name' => 'reports',
            'module' => 'reports',
            'description' => 'Exported something',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->getJson('/api/activity-logs/statistics?period=week');

        $response->assertOk()
            ->assertJsonPath('data.total_activities', 3)
            ->assertJsonPath('data.activities_by_module.0.module', 'users')
            ->assertJsonPath('data.activities_by_module.0.count', 2)
            ->assertJsonPath('data.activities_by_action.0.count', 1)
            ->assertJsonCount(2, 'data.top_users');
    }

    public function test_export_excel_returns_real_xlsx_download(): void
    {
        ActivityLog::create([
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
            'event' => 'login',
            'log_name' => 'auth',
            'module' => 'auth',
            'description' => 'User logged in',
        ]);

        $response = $this->get('/api/activity-logs/export?format=excel');

        $response->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_cleanup_deletes_old_logs_and_writes_schema_correct_audit_log(): void
    {
        ActivityLog::create([
            'causer_id' => $this->admin->id,
            'causer_type' => User::class,
            'event' => 'old_event',
            'log_name' => 'system',
            'module' => 'system',
            'description' => 'Old log',
        ]);

        DB::table('activity_logs')->where('event', 'old_event')->update([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ]);

        $response = $this->postJson('/api/activity-logs/cleanup', [
            'retention_days' => 5,
            'confirm' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.deleted_count', 1);

        $this->assertDatabaseMissing('activity_logs', [
            'event' => 'old_event',
            'description' => 'Old log',
        ]);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'cleanup_activity_logs',
            'causer_id' => $this->admin->id,
            'module' => 'system',
        ]);
    }
}
