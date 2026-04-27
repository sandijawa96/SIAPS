<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WhatsappAutomationSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forget('settings.whatsapp.automations');
        Cache::forget('runtime_settings.namespace.whatsapp');
    }

    public function test_admin_can_read_whatsapp_automations(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/whatsapp/automations');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $items = $response->json('data.automations');
        $this->assertIsArray($items);
        $this->assertNotEmpty($items);
        $this->assertContains('attendance_checkin', array_column($items, 'key'));
        $this->assertContains('discipline_alpha_semester_limit_wali_kelas', array_column($items, 'key'));
    }

    public function test_admin_can_update_whatsapp_automation_template_and_toggle(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/whatsapp/automations', [
                'automations' => [
                    [
                        'key' => 'discipline_alpha_semester_limit_wali_kelas',
                        'enabled' => false,
                        'template' => 'Tes alert {student_name}',
                        'footer' => 'Footer custom',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'key' => 'discipline_alpha_semester_limit_wali_kelas',
                'enabled' => false,
            ]);

        $stored = Cache::get('settings.whatsapp.automations');
        $this->assertIsArray($stored);
        $this->assertFalse((bool) ($stored['discipline_alpha_semester_limit_wali_kelas']['enabled'] ?? true));
        $this->assertSame('Tes alert {student_name}', $stored['discipline_alpha_semester_limit_wali_kelas']['template'] ?? null);
        $this->assertSame('Footer custom', $stored['discipline_alpha_semester_limit_wali_kelas']['footer'] ?? null);
        $this->assertDatabaseHas('runtime_settings', [
            'namespace' => 'whatsapp',
            'key' => 'automations',
            'type' => 'json',
        ]);
    }

    public function test_whatsapp_automation_settings_survive_cache_clear(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/whatsapp/automations', [
                'automations' => [
                    [
                        'key' => 'discipline_alpha_semester_limit_wali_kelas',
                        'enabled' => false,
                        'template' => 'Tes alert {student_name}',
                        'footer' => 'Footer custom',
                    ],
                ],
            ])
            ->assertOk();

        Cache::forget('settings.whatsapp.automations');
        Cache::forget('runtime_settings.namespace.whatsapp');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/whatsapp/automations')
            ->assertOk()
            ->assertJsonPath('data.automations.0.key', 'attendance_checkin');

        $items = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/whatsapp/automations')
            ->assertOk()
            ->json('data.automations');

        $target = collect($items)->firstWhere('key', 'discipline_alpha_semester_limit_wali_kelas');
        $this->assertNotNull($target);
        $this->assertFalse((bool) ($target['enabled'] ?? true));
        $this->assertSame('Tes alert {student_name}', $target['template'] ?? null);
        $this->assertSame('Footer custom', $target['footer'] ?? null);
    }

    private function createUserWithRole(string $canonicalRole): User
    {
        $role = Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->where('guard_name', 'web')
            ->firstOrFail();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
