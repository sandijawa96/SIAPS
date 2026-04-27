<?php

namespace App\Observers;

use App\Jobs\DispatchAttendanceWhatsappNotification;
use App\Models\Absensi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AbsensiObserver
{
    public function created(Absensi $absensi): void
    {
        $absensiId = (int) $absensi->id;
        if ((bool) $absensi->is_manual) {
            return;
        }

        if ($absensi->jam_masuk !== null) {
            DB::afterCommit(function () use ($absensiId) {
                try {
                    DispatchAttendanceWhatsappNotification::dispatch($absensiId, 'checkin')->afterCommit();
                } catch (\Throwable $e) {
                    Log::warning('Failed to queue WA check-in notification on absensi created', [
                        'absensi_id' => $absensiId,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }

        if ($absensi->jam_pulang !== null) {
            DB::afterCommit(function () use ($absensiId) {
                try {
                    DispatchAttendanceWhatsappNotification::dispatch($absensiId, 'checkout')->afterCommit();
                } catch (\Throwable $e) {
                    Log::warning('Failed to queue WA check-out notification on absensi created', [
                        'absensi_id' => $absensiId,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }
    }

    public function updated(Absensi $absensi): void
    {
        $absensiId = (int) $absensi->id;
        if ((bool) $absensi->is_manual) {
            return;
        }

        if ($absensi->wasChanged('jam_masuk') && $absensi->jam_masuk !== null) {
            DB::afterCommit(function () use ($absensiId) {
                try {
                    DispatchAttendanceWhatsappNotification::dispatch($absensiId, 'checkin')->afterCommit();
                } catch (\Throwable $e) {
                    Log::warning('Failed to queue WA check-in notification on absensi updated', [
                        'absensi_id' => $absensiId,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }

        if ($absensi->wasChanged('jam_pulang') && $absensi->jam_pulang !== null) {
            DB::afterCommit(function () use ($absensiId) {
                try {
                    DispatchAttendanceWhatsappNotification::dispatch($absensiId, 'checkout')->afterCommit();
                } catch (\Throwable $e) {
                    Log::warning('Failed to queue WA check-out notification on absensi updated', [
                        'absensi_id' => $absensiId,
                        'error' => $e->getMessage(),
                    ]);
                }
            });
        }
    }
}
