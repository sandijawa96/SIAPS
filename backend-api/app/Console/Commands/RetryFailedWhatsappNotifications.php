<?php

namespace App\Console\Commands;

use App\Models\WhatsappGateway;
use App\Services\WhatsappGatewayClient;
use Illuminate\Console\Command;

class RetryFailedWhatsappNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'whatsapp:retry-failed
        {--limit=100 : Maximum failed notifications to process per run}
        {--cooldown-seconds= : Override retry cooldown in seconds}
        {--dry-run : Show candidates without sending requests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Retry failed WhatsApp notifications that still have retry quota.';

    public function __construct(
        private readonly WhatsappGatewayClient $gatewayClient
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $cooldownSeconds = $this->resolveCooldownSeconds();
        $dryRun = (bool) $this->option('dry-run');

        $query = WhatsappGateway::query()
            ->where('status', WhatsappGateway::STATUS_FAILED)
            ->whereColumn('retry_count', '<', 'max_retries')
            ->where(function ($builder) {
                $builder->whereNull('scheduled_at')
                    ->orWhere('scheduled_at', '<=', now());
            })
            ->orderBy('updated_at')
            ->limit($limit);

        if ($cooldownSeconds > 0) {
            $query->where('updated_at', '<=', now()->subSeconds($cooldownSeconds));
        }

        $candidates = $query->get();

        if ($candidates->isEmpty()) {
            $this->info('No retryable WhatsApp notifications found.');
            return self::SUCCESS;
        }

        $this->info("Retrying {$candidates->count()} WhatsApp notifications...");

        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($candidates as $notification) {
            if ($dryRun) {
                $skippedCount++;
                $this->line("DRY-RUN #{$notification->id} -> {$notification->phone_number}");
                continue;
            }

            $metadata = is_array($notification->metadata) ? $notification->metadata : [];
            $result = $this->gatewayClient->sendMessage(
                (string) $notification->phone_number,
                (string) $notification->message,
                [
                    'footer' => isset($metadata['footer']) ? (string) $metadata['footer'] : null,
                    'msgid' => isset($metadata['reply_to_message_id']) ? (string) $metadata['reply_to_message_id'] : null,
                ]
            );

            if ($result['ok']) {
                $notification->markAsSent(is_array($result['gateway_response']) ? $result['gateway_response'] : null);
                $notification->update(['scheduled_at' => null]);
                $sentCount++;
                continue;
            }

            if (($result['pending_verification'] ?? false) === true) {
                $notification->markAsPendingVerification(
                    (string) ($result['message'] ?? 'Status pengiriman WhatsApp belum pasti.'),
                    is_array($result['gateway_response']) ? $result['gateway_response'] : null
                );
                $skippedCount++;
                continue;
            }

            $notification->markAsFailed(
                (string) ($result['message'] ?? 'gateway_error'),
                is_array($result['gateway_response']) ? $result['gateway_response'] : null
            );

            if ($notification->canRetry()) {
                $notification->update([
                    'scheduled_at' => now()->addSeconds($this->nextRetryDelaySeconds(
                        (int) $notification->retry_count,
                        $cooldownSeconds
                    )),
                ]);
            } else {
                $notification->update(['scheduled_at' => null]);
            }

            $failedCount++;
        }

        $this->info("Retry complete. Sent: {$sentCount}, Failed: {$failedCount}, Skipped: {$skippedCount}.");

        return self::SUCCESS;
    }

    private function resolveCooldownSeconds(): int
    {
        $option = $this->option('cooldown-seconds');
        if ($option !== null && $option !== '') {
            return max(0, (int) $option);
        }

        return max(0, (int) config('whatsapp.auto_retry.cooldown_seconds', 300));
    }

    private function nextRetryDelaySeconds(int $currentRetryCount, int $baseCooldown): int
    {
        if ($baseCooldown <= 0) {
            return 0;
        }

        $multiplier = 2 ** max(0, $currentRetryCount - 1);
        $delay = $baseCooldown * $multiplier;

        return min($delay, 86_400);
    }
}
