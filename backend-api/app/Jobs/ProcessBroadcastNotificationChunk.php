<?php

namespace App\Jobs;

use App\Services\BroadcastCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessBroadcastNotificationChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    /**
     * @param array<string, mixed> $payload
     * @param array<int, int> $userIds
     */
    public function __construct(
        public int $campaignId,
        public array $payload,
        public array $userIds,
    ) {
        $this->onQueue((string) config('broadcast.notifications.queue', 'broadcast'));
    }

    public function handle(BroadcastCampaignService $broadcastCampaignService): void
    {
        $broadcastCampaignService->processNotificationChunk($this->campaignId, $this->payload, $this->userIds);
    }

    public function failed(Throwable $throwable): void
    {
        app(BroadcastCampaignService::class)->markChunkAsFailed(
            $this->campaignId,
            'in_app',
            count($this->userIds),
            'Chunk notifikasi aplikasi gagal diproses: ' . $throwable->getMessage()
        );
    }
}
