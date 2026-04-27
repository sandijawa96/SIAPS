<?php

namespace App\Jobs;

use App\Models\Izin;
use App\Services\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchIzinWhatsappNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'izin-whatsapp';

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public int $izinId,
        public string $event,
    ) {
        $this->onQueue(self::QUEUE_NAME);
    }

    public function handle(WhatsappNotificationService $whatsappNotificationService): void
    {
        $izin = Izin::with('user')->find($this->izinId);
        if (!$izin) {
            return;
        }

        if ($this->event === 'submitted') {
            if ((string) $izin->status !== 'pending') {
                return;
            }

            $whatsappNotificationService->notifyIzinSubmitted($izin);
            return;
        }

        if ($this->event === 'decision') {
            if (!in_array((string) $izin->status, ['approved', 'rejected'], true)) {
                return;
            }

            $whatsappNotificationService->notifyIzinDecision($izin);
        }
    }

    public function failed(\Throwable $throwable): void
    {
        Log::warning('Queued WA notification for izin failed', [
            'izin_id' => $this->izinId,
            'event' => $this->event,
            'queue' => self::QUEUE_NAME,
            'error' => $throwable->getMessage(),
        ]);
    }
}
