<?php

namespace Tests\Feature;

use App\Models\WhatsappGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RetryFailedWhatsappNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forever('settings.whatsapp.api_url', 'https://wa.test');
        Cache::forever('settings.whatsapp.api_key', 'test-key');
        Cache::forever('settings.whatsapp.device_id', '6281230000000');
        Cache::forever('settings.whatsapp.notification_enabled', true);
    }

    public function test_retry_failed_whatsapp_notification_preserves_footer_metadata(): void
    {
        Http::fake([
            'https://wa.test/send-message' => Http::response([
                'status' => true,
                'msg' => 'Message sent successfully!',
            ], 200),
        ]);

        $notification = WhatsappGateway::create([
            'phone_number' => '6281234567890',
            'message' => 'Pesan gagal sebelumnya',
            'type' => WhatsappGateway::TYPE_PENGUMUMAN,
            'status' => WhatsappGateway::STATUS_FAILED,
            'metadata' => [
                'footer' => 'Footer lama',
            ],
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        $this->artisan('whatsapp:retry-failed', [
            '--limit' => 10,
            '--cooldown-seconds' => 0,
        ])->assertExitCode(0);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://wa.test/send-message'
                && ($data['footer'] ?? null) === 'Footer lama'
                && ($data['message'] ?? null) === 'Pesan gagal sebelumnya';
        });

        $notification->refresh();
        $this->assertSame(WhatsappGateway::STATUS_SENT, $notification->status);
    }
}
