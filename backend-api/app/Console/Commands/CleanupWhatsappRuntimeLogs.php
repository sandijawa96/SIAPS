<?php

namespace App\Console\Commands;

use App\Models\WhatsappNotificationSkip;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Console\Command;

class CleanupWhatsappRuntimeLogs extends Command
{
    protected $signature = 'whatsapp:cleanup-runtime-logs
        {--webhook-retention-days= : Override webhook event retention in days}
        {--skip-retention-days= : Override skip log retention in days}';

    protected $description = 'Clean old WhatsApp webhook events and skip logs.';

    public function handle(): int
    {
        $webhookRetentionDays = $this->resolveRetentionDays(
            $this->option('webhook-retention-days'),
            (int) config('whatsapp.webhook_events.retention_days', 30)
        );
        $skipRetentionDays = $this->resolveRetentionDays(
            $this->option('skip-retention-days'),
            (int) config('whatsapp.skip_logs.retention_days', 30)
        );

        $deletedWebhookEvents = 0;
        $deletedSkipLogs = 0;

        if ($webhookRetentionDays > 0) {
            $deletedWebhookEvents = WhatsappWebhookEvent::query()
                ->where('created_at', '<', now()->subDays($webhookRetentionDays))
                ->delete();
        }

        if ($skipRetentionDays > 0) {
            $deletedSkipLogs = WhatsappNotificationSkip::query()
                ->where('created_at', '<', now()->subDays($skipRetentionDays))
                ->delete();
        }

        $this->info("Deleted {$deletedWebhookEvents} webhook events and {$deletedSkipLogs} skip logs.");

        return self::SUCCESS;
    }

    private function resolveRetentionDays(mixed $optionValue, int $fallback): int
    {
        if ($optionValue !== null && $optionValue !== '') {
            return max(0, (int) $optionValue);
        }

        return max(0, $fallback);
    }
}
