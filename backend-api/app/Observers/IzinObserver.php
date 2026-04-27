<?php

namespace App\Observers;

use App\Jobs\DispatchIzinWhatsappNotification;
use App\Models\Izin;
use Illuminate\Support\Facades\Log;

class IzinObserver
{
    public function created(Izin $izin): void
    {
        if ((string) $izin->status !== 'pending') {
            return;
        }

        $izinId = (int) $izin->id;

        try {
            DispatchIzinWhatsappNotification::dispatch($izinId, 'submitted')->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Failed to queue WA notification for submitted izin', [
                'izin_id' => $izinId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function updated(Izin $izin): void
    {
        if (!$izin->wasChanged('status')) {
            return;
        }

        $status = (string) $izin->status;
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return;
        }

        $izinId = (int) $izin->id;

        try {
            DispatchIzinWhatsappNotification::dispatch($izinId, 'decision')->afterCommit();
        } catch (\Throwable $e) {
            Log::warning('Failed to queue WA notification for izin decision', [
                'izin_id' => $izinId,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
