<?php

namespace App\Jobs;

use App\Models\Absensi;
use App\Services\WhatsappNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchAttendanceWhatsappNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE_NAME = 'attendance-whatsapp';

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(
        public int $attendanceId,
        public string $event,
        public bool $allowManual = false,
    ) {
        $this->onQueue(self::QUEUE_NAME);
    }

    public function handle(WhatsappNotificationService $whatsappNotificationService): void
    {
        $attendance = Absensi::with('user', 'kelas')->find($this->attendanceId);
        if (!$attendance || ((bool) $attendance->is_manual && !$this->allowManual)) {
            return;
        }

        if ($this->event === 'checkin') {
            if ($attendance->jam_masuk === null) {
                return;
            }

            $whatsappNotificationService->notifyAttendanceCheckIn($attendance);
            return;
        }

        if ($this->event === 'checkout') {
            if ($attendance->jam_pulang === null) {
                return;
            }

            $whatsappNotificationService->notifyAttendanceCheckOut($attendance);
        }
    }

    public function failed(\Throwable $throwable): void
    {
        Log::warning('Queued WA notification for attendance failed', [
            'attendance_id' => $this->attendanceId,
            'event' => $this->event,
            'allow_manual' => $this->allowManual,
            'queue' => self::QUEUE_NAME,
            'error' => $throwable->getMessage(),
        ]);
    }
}
