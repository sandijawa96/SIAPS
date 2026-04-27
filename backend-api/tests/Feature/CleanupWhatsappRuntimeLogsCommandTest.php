<?php

namespace Tests\Feature;

use App\Models\WhatsappNotificationSkip;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanupWhatsappRuntimeLogsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_command_removes_old_webhook_events_and_skip_logs(): void
    {
        $webhookEvent = WhatsappWebhookEvent::create([
            'event_type' => 'delivered',
            'status' => 'delivered',
            'message_id' => 'wamid.old-webhook',
            'payload' => ['event' => 'delivered'],
            'headers' => ['user_agent' => 'phpunit'],
        ]);

        $skip = WhatsappNotificationSkip::create([
            'type' => 'izin',
            'reason' => WhatsappNotificationSkip::REASON_MISSING_PHONE,
            'metadata' => ['source' => 'izin_submitted'],
        ]);

        DB::table('whatsapp_webhook_events')
            ->where('id', $webhookEvent->id)
            ->update(['created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);

        DB::table('whatsapp_notification_skips')
            ->where('id', $skip->id)
            ->update(['created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40)]);

        $this->artisan('whatsapp:cleanup-runtime-logs', [
            '--webhook-retention-days' => 30,
            '--skip-retention-days' => 30,
        ])->assertExitCode(0);

        $this->assertDatabaseMissing('whatsapp_webhook_events', [
            'id' => $webhookEvent->id,
        ]);

        $this->assertDatabaseMissing('whatsapp_notification_skips', [
            'id' => $skip->id,
        ]);
    }
}
