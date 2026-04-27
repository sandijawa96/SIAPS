<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WhatsappSettingsDeviceIdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);
        Cache::forget('settings.whatsapp.api_url');
        Cache::forget('settings.whatsapp.api_key');
        Cache::forget('settings.whatsapp.device_id');
        Cache::forget('settings.whatsapp.notification_enabled');
        Cache::forget('runtime_settings.namespace.whatsapp');
    }

    public function test_whatsapp_settings_preserve_non_numeric_device_id(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/settings', [
                'api_url' => 'https://gateway.example.test',
                'api_key' => 'secret-key',
                'device_id' => 'device-alpha-01',
                'notification_enabled' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'device_id' => 'device-alpha-01',
                    'notification_enabled' => true,
                ],
            ]);

        $this->assertSame('device-alpha-01', Cache::get('settings.whatsapp.device_id'));
        $this->assertDatabaseHas('runtime_settings', [
            'namespace' => 'whatsapp',
            'key' => 'device_id',
            'value' => 'device-alpha-01',
            'type' => 'string',
        ]);
    }

    public function test_whatsapp_settings_survive_cache_clear_because_source_of_truth_is_database(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/settings', [
                'api_url' => 'https://gateway.example.test',
                'api_key' => 'secret-key',
                'device_id' => 'device-alpha-01',
                'notification_enabled' => true,
            ])
            ->assertOk();

        Cache::forget('settings.whatsapp.api_url');
        Cache::forget('settings.whatsapp.api_key');
        Cache::forget('settings.whatsapp.device_id');
        Cache::forget('settings.whatsapp.notification_enabled');
        Cache::forget('runtime_settings.namespace.whatsapp');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('data.api_url', 'https://gateway.example.test')
            ->assertJsonPath('data.device_id', 'device-alpha-01')
            ->assertJsonPath('data.has_api_key', true);
    }

    public function test_blank_api_key_input_keeps_existing_stored_key(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/settings', [
                'api_url' => 'https://gateway.example.test',
                'api_key' => 'secret-key',
                'device_id' => 'device-alpha-01',
                'notification_enabled' => true,
            ])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/settings', [
                'api_url' => 'https://gateway.example.test',
                'api_key' => '',
                'device_id' => 'device-alpha-01',
                'notification_enabled' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.notification_enabled', false);

        Cache::forget('settings.whatsapp.api_url');
        Cache::forget('settings.whatsapp.api_key');
        Cache::forget('settings.whatsapp.device_id');
        Cache::forget('settings.whatsapp.notification_enabled');
        Cache::forget('runtime_settings.namespace.whatsapp');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('data.has_api_key', true)
            ->assertJsonPath('data.notification_enabled', false);
    }
}
