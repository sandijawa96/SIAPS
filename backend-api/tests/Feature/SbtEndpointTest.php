<?php

namespace Tests\Feature;

use App\Models\SbtExamSession;
use App\Models\SbtSecurityEvent;
use App\Models\SbtSetting;
use App\Models\MobileRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SbtEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(
            [
                'name' => 'manage_settings',
                'guard_name' => 'web',
            ],
            [
                'display_name' => 'Manage Settings',
                'description' => 'Manage system settings',
                'module' => 'settings',
            ]
        );
    }

    public function test_public_mobile_config_returns_default_sbt_settings(): void
    {
        $this->getJson('/api/sbt/mobile/config')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.exam_url', 'https://res.sman1sumbercirebon.sch.id')
            ->assertJsonPath('data.exam_host', 'res.sman1sumbercirebon.sch.id')
            ->assertJsonPath('data.webview_user_agent', 'SBT-SMANIS/1.0')
            ->assertJsonPath('data.security_mode', 'warning')
            ->assertJsonPath('data.ios_lock_on_background', true);

        $this->assertDatabaseHas('sbt_settings', [
            'id' => 1,
            'exam_host' => 'res.sman1sumbercirebon.sch.id',
        ]);
    }

    public function test_mobile_session_and_security_event_are_recorded(): void
    {
        $this->postJson('/api/sbt/mobile/sessions', [
            'app_session_id' => 'sbt-session-test-001',
            'device_name' => 'Android Emulator',
            'app_version' => '1.0.0',
            'platform' => 'android',
            'exam_url' => 'https://res.sman1sumbercirebon.sch.id',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.session.app_session_id', 'sbt-session-test-001')
            ->assertJsonPath('data.session.status', 'active');

        $this->postJson('/api/sbt/mobile/events', [
            'app_session_id' => 'sbt-session-test-001',
            'event_type' => 'APP_PAUSED',
            'severity' => 'high',
            'message' => 'Aplikasi ujian keluar dari foreground.',
            'metadata' => [
                'source' => 'test',
            ],
        ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.event.event_type', 'APP_PAUSED')
            ->assertJsonPath('data.event.severity', 'high');

        $session = SbtExamSession::query()->where('app_session_id', 'sbt-session-test-001')->first();
        $this->assertNotNull($session);
        $this->assertSame(1, SbtSecurityEvent::query()->where('sbt_exam_session_id', $session->id)->count());
    }

    public function test_sbt_version_check_uses_sbt_app_key_without_conflicting_with_siaps(): void
    {
        MobileRelease::query()->create([
            'app_key' => 'siaps',
            'app_name' => 'SIAPS Mobile',
            'target_audience' => 'all',
            'platform' => 'android',
            'release_channel' => 'stable',
            'public_version' => '9.9.9',
            'build_number' => 999,
            'download_url' => 'https://example.test/siaps.apk',
            'update_mode' => 'required',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->getJson('/api/sbt/mobile/version-check?platform=android&app_version=1.0.0&build_number=1')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.has_update', false)
            ->assertJsonPath('data.must_update', false);

        MobileRelease::query()->create([
            'app_key' => 'sbt-smanis',
            'app_name' => 'SBT SMANIS',
            'target_audience' => 'siswa',
            'platform' => 'android',
            'release_channel' => 'stable',
            'public_version' => '1.1.0',
            'build_number' => 2,
            'download_url' => 'https://example.test/sbt.apk',
            'update_mode' => 'required',
            'minimum_supported_build_number' => 2,
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->getJson('/api/sbt/mobile/version-check?platform=android&app_version=1.0.0&build_number=1')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.app_key', 'sbt-smanis')
            ->assertJsonPath('data.has_update', true)
            ->assertJsonPath('data.must_update', true)
            ->assertJsonPath('data.update_mode', 'required')
            ->assertJsonPath('data.latest.download_url', 'https://example.test/sbt.apk');
    }

    public function test_supervisor_code_unlock_uses_admin_sbt_setting(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_settings');

        $payload = [
            'enabled' => true,
            'exam_url' => 'https://res.sman1sumbercirebon.sch.id',
            'webview_user_agent' => 'SBT-SMANIS/1.0 Ujian',
            'security_mode' => 'supervisor_code',
            'supervisor_code' => '2468',
            'clear_supervisor_code' => false,
            'minimum_app_version' => null,
            'require_dnd' => false,
            'require_screen_pinning' => true,
            'require_overlay_protection' => true,
            'ios_lock_on_background' => true,
            'minimum_battery_level' => 20,
            'heartbeat_interval_seconds' => 30,
            'maintenance_enabled' => false,
            'maintenance_message' => null,
            'announcement' => null,
        ];

        $this->actingAs($admin, 'sanctum')
            ->putJson('/api/sbt/admin/settings', $payload)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.security_mode', 'supervisor_code')
            ->assertJsonPath('data.webview_user_agent', 'SBT-SMANIS/1.0 Ujian')
            ->assertJsonPath('data.has_supervisor_code', true);

        $this->assertTrue(SbtSetting::current()->requiresSupervisorCode());

        $this->postJson('/api/sbt/mobile/unlock', [
            'app_session_id' => 'sbt-unlock-test-001',
            'supervisor_code' => '0000',
            'event_type' => 'APP_PAUSED',
        ])
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.allowed', false);

        $this->postJson('/api/sbt/mobile/unlock', [
            'app_session_id' => 'sbt-unlock-test-001',
            'supervisor_code' => '2468',
            'event_type' => 'APP_PAUSED',
        ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.allowed', true);

        $this->assertDatabaseHas('sbt_security_events', [
            'app_session_id' => 'sbt-unlock-test-001',
            'event_type' => 'SUPERVISOR_UNLOCK_FAILED',
            'severity' => 'high',
        ]);
        $this->assertDatabaseHas('sbt_security_events', [
            'app_session_id' => 'sbt-unlock-test-001',
            'event_type' => 'SUPERVISOR_UNLOCK_SUCCESS',
            'severity' => 'low',
        ]);
    }
}
