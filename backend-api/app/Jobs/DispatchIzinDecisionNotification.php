<?php

namespace App\Jobs;

use App\Services\IzinNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchIzinDecisionNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'izin-notifications';

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public int $izinId,
        public string $status,
        public ?int $actorUserId = null,
    ) {
        $this->onQueue(self::QUEUE_NAME);
    }

    public function handle(IzinNotificationService $izinNotificationService): void
    {
        $izinNotificationService->notifyStudentApprovalResult(
            $this->izinId,
            $this->status,
            $this->actorUserId
        );
    }

    public function failed(\Throwable $throwable): void
    {
        Log::warning('Queued decision notification job for izin failed', [
            'izin_id' => $this->izinId,
            'status' => $this->status,
            'queue' => self::QUEUE_NAME,
            'error' => $throwable->getMessage(),
        ]);
    }
}
