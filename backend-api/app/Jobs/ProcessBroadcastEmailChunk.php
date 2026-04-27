<?php

namespace App\Jobs;

use App\Services\BroadcastCampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessBroadcastEmailChunk implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 180;

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $targets
     */
    public function __construct(
        public int $campaignId,
        public array $payload,
        public array $targets,
    ) {
        $this->onQueue((string) config('broadcast.email.queue', 'broadcast-email'));
    }

    public function handle(BroadcastCampaignService $broadcastCampaignService): void
    {
        $broadcastCampaignService->processEmailChunk($this->campaignId, $this->payload, $this->targets);
    }

    public function failed(Throwable $throwable): void
    {
        app(BroadcastCampaignService::class)->markChunkAsFailed(
            $this->campaignId,
            'email',
            count($this->targets),
            'Chunk email gagal diproses: ' . $throwable->getMessage()
        );
    }
}
