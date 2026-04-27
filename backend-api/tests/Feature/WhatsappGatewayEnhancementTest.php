<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WhatsappGateway;
use App\Models\WhatsappNotificationSkip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Tests\TestCase;

class WhatsappGatewayEnhancementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(PermissionMiddleware::class);

        Cache::forever('settings.whatsapp.api_url', 'https://wa.test');
        Cache::forever('settings.whatsapp.api_key', 'test-key');
        Cache::forever('settings.whatsapp.device_id', 'device-alpha-01');
        Cache::forever('settings.whatsapp.notification_enabled', true);
        Cache::forget('settings.whatsapp.webhook_secret');
        Cache::forget('runtime_settings.namespace.whatsapp');
    }

    public function test_status_uses_info_payload_from_gateway_docs(): void
    {
        Http::fake([
            'https://wa.test/info-devices*' => Http::response([
                'status' => true,
                'info' => [
                    [
                        'id' => 'device-alpha-01',
                        'status' => 'connected',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.gateway_device.id', 'device-alpha-01')
            ->assertJsonPath('data.gateway_device.status', 'connected');
    }

    public function test_status_returns_not_connected_when_gateway_device_payload_missing(): void
    {
        Http::fake([
            'https://wa.test/info-devices*' => Http::response([
                'status' => true,
                'info' => [],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/whatsapp/status')
            ->assertOk()
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.gateway_device', null);
    }

    public function test_send_stores_gateway_message_id_and_supports_reply_to_message_id(): void
    {
        Http::fake([
            'https://wa.test/send-message' => Http::response([
                'status' => true,
                'msg' => 'Message sent successfully!',
                'data' => [
                    'key' => [
                        'id' => 'wamid.TEST-123',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/send', [
                'phone_number' => '081234567890',
                'message' => 'Tes kirim gateway',
                'reply_to_message_id' => 'wamid.PARENT-456',
                'type' => 'pengumuman',
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://wa.test/send-message'
                && ($data['msgid'] ?? null) === 'wamid.PARENT-456'
                && (int) ($data['full'] ?? 0) === 1;
        });

        /** @var WhatsappGateway $record */
        $record = WhatsappGateway::query()->latest('id')->firstOrFail();
        $this->assertSame('wamid.TEST-123', $record->gateway_message_id);
        $this->assertSame('wamid.PARENT-456', data_get($record->metadata, 'reply_to_message_id'));
    }

    public function test_send_treats_false_status_with_message_id_as_sent(): void
    {
        Http::fake([
            'https://wa.test/send-message' => Http::response([
                'status' => false,
                'msg' => 'Gateway returned false after WhatsApp accepted the message.',
                'data' => [
                    'key' => [
                        'id' => 'wamid.FALSE-BUT-SENT',
                    ],
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/send', [
                'phone_number' => '081234567890',
                'message' => 'Tes false status dengan message id',
                'type' => 'pengumuman',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        /** @var WhatsappGateway $record */
        $record = WhatsappGateway::query()->latest('id')->firstOrFail();
        $this->assertSame(WhatsappGateway::STATUS_SENT, $record->status);
        $this->assertSame('wamid.FALSE-BUT-SENT', $record->gateway_message_id);
        $this->assertSame(0, (int) $record->retry_count);
    }

    public function test_send_timeout_is_kept_pending_verification_instead_of_failed(): void
    {
        Http::fake([
            'https://wa.test/send-message' => function () {
                throw new ConnectionException('cURL error 28: Operation timed out after 20000 milliseconds');
            },
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/send', [
                'phone_number' => '081234567890',
                'message' => 'Tes timeout gateway',
                'type' => 'pengumuman',
            ])
            ->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('gateway.pending_verification', true)
            ->assertJsonPath('data.status', WhatsappGateway::STATUS_PENDING);

        /** @var WhatsappGateway $record */
        $record = WhatsappGateway::query()->latest('id')->firstOrFail();
        $this->assertSame(WhatsappGateway::STATUS_PENDING, $record->status);
        $this->assertSame(0, (int) $record->retry_count);
        $this->assertSame('pending', data_get($record->metadata, 'delivery_verification.status'));
    }

    public function test_legacy_broadcast_endpoint_returns_gone(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/broadcast', [
                'message' => 'Tes',
            ])
            ->assertStatus(410)
            ->assertJsonPath('data.replacement_endpoint', '/api/broadcast-campaigns');
    }

    public function test_check_number_endpoint_returns_gateway_result(): void
    {
        Http::fake([
            'https://wa.test/check-number' => Http::response([
                'status' => true,
                'msg' => [
                    'exists' => true,
                    'jid' => '6281234567890@s.whatsapp.net',
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/check-number', [
                'phone_number' => '081234567890',
            ])
            ->assertOk()
            ->assertJsonPath('data.exists', true)
            ->assertJsonPath('data.jid', '6281234567890@s.whatsapp.net')
            ->assertJsonPath('data.phone_number', '6281234567890');
    }

    public function test_generate_qr_accepts_qrcode_payload_even_when_gateway_status_is_false(): void
    {
        Http::fake([
            'https://wa.test/generate-qr' => Http::response([
                'status' => false,
                'qrcode' => 'data:image/png;base64,abc123',
                'message' => 'Please scan qrcode',
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/generate-qr', [
                'force' => true,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.qrcode', 'data:image/png;base64,abc123');
    }

    public function test_device_logout_and_delete_proxy_to_gateway(): void
    {
        Http::fake([
            'https://wa.test/logout-device' => Http::response([
                'status' => true,
                'message' => 'device disconnected',
            ], 200),
            'https://wa.test/delete-device' => Http::response([
                'status' => true,
                'message' => 'Device deleted',
            ], 200),
        ]);

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/logout-device')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/delete-device')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_webhook_marks_matching_notification_as_delivered_when_secret_is_valid(): void
    {
        Cache::forever('settings.whatsapp.webhook_secret', 'secret-123');
        Cache::forget('runtime_settings.namespace.whatsapp');

        $notification = WhatsappGateway::create([
            'phone_number' => '6281234567890',
            'message' => 'Tes webhook',
            'type' => WhatsappGateway::TYPE_PENGUMUMAN,
            'status' => WhatsappGateway::STATUS_SENT,
            'gateway_message_id' => 'wamid.TEST-DELIVERED',
            'metadata' => [],
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        $this->postJson('/api/whatsapp/webhook', [
            'event' => 'delivered',
            'message_id' => 'wamid.TEST-DELIVERED',
            'status' => 'delivered',
        ], [
            'X-Webhook-Secret' => 'secret-123',
        ])->assertOk()
            ->assertJsonPath('data.delivery_marked', true)
            ->assertJsonPath('data.matched_notification_id', $notification->id);

        $notification->refresh();
        $this->assertSame(WhatsappGateway::STATUS_DELIVERED, $notification->status);
        $this->assertNotNull($notification->delivered_at);
    }

    public function test_webhook_rejects_invalid_secret_when_configured(): void
    {
        Cache::forever('settings.whatsapp.webhook_secret', 'secret-123');
        Cache::forget('runtime_settings.namespace.whatsapp');

        $this->postJson('/api/whatsapp/webhook', [
            'event' => 'delivered',
            'message_id' => 'wamid.TEST-DELIVERED',
        ], [
            'X-Webhook-Secret' => 'secret-wrong',
        ])->assertStatus(401);
    }

    public function test_webhook_rejects_when_secret_has_not_been_configured(): void
    {
        Cache::forget('settings.whatsapp.webhook_secret');
        Cache::forget('runtime_settings.namespace.whatsapp');

        $this->postJson('/api/whatsapp/webhook', [
            'event' => 'delivered',
            'message_id' => 'wamid.TEST-DELIVERED',
        ])->assertStatus(503);
    }

    public function test_update_settings_requires_webhook_secret(): void
    {
        Cache::forget('settings.whatsapp.webhook_secret');
        Cache::forget('runtime_settings.namespace.whatsapp');

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/settings', [
                'api_url' => 'https://wa.test',
                'api_key' => 'test-key',
                'device_id' => 'device-alpha-01',
                'notification_enabled' => true,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_skip_events_endpoint_returns_summary_and_recent_items(): void
    {
        $user = User::factory()->create();

        WhatsappNotificationSkip::create([
            'type' => WhatsappGateway::TYPE_ABSENSI,
            'reason' => WhatsappNotificationSkip::REASON_MISSING_PHONE,
            'target_user_id' => $user->id,
            'phone_candidate' => null,
            'metadata' => ['source' => 'attendance_checkin'],
        ]);

        WhatsappNotificationSkip::create([
            'type' => WhatsappGateway::TYPE_IZIN,
            'reason' => WhatsappNotificationSkip::REASON_NOTIFICATIONS_DISABLED,
            'target_user_id' => $user->id,
            'phone_candidate' => '6281234567890',
            'metadata' => ['source' => 'izin_submitted'],
        ]);

        $admin = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/whatsapp/skip-events?limit=5')
            ->assertOk()
            ->assertJsonPath('data.summary.missing_phone_last_24h', 1)
            ->assertJsonPath('data.summary.disabled_last_24h', 1)
            ->assertJsonPath('data.events.0.reason', WhatsappNotificationSkip::REASON_NOTIFICATIONS_DISABLED);
    }

    public function test_manual_send_is_rejected_without_creating_notification_row_when_global_switch_is_off(): void
    {
        Cache::forever('settings.whatsapp.notification_enabled', false);
        Cache::forget('runtime_settings.namespace.whatsapp');

        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/whatsapp/send', [
                'phone_number' => '081234567890',
                'message' => 'Tes manual',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseCount('whatsapp_notifications', 0);
    }
}
