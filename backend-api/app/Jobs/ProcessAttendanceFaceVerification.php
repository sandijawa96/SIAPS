<?php

namespace App\Jobs;

use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Services\AttendanceFaceVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAttendanceFaceVerification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;

    public function __construct(
        public int $attendanceId,
        public string $checkType,
        public ?int $schemaId = null
    ) {
        $this->onQueue((string) config('attendance.face.queue', 'face-verification'));
    }

    public function handle(AttendanceFaceVerificationService $verificationService): void
    {
        $attendance = Absensi::with('user')->find($this->attendanceId);
        if (!$attendance || !$attendance->user) {
            Log::warning('Face verification job skipped: attendance or user not found', [
                'attendance_id' => $this->attendanceId,
                'check_type' => $this->checkType,
            ]);
            return;
        }

        $schema = null;
        if ($this->schemaId) {
            $schema = AttendanceSchema::find($this->schemaId);
        } elseif ($attendance->attendance_setting_id) {
            $schema = AttendanceSchema::find($attendance->attendance_setting_id);
        }

        $photoPath = $this->checkType === 'checkout'
            ? $attendance->foto_pulang
            : $attendance->foto_masuk;

        $verificationService->processVerification(
            $attendance,
            $attendance->user,
            $this->checkType,
            $schema,
            $photoPath
        );
    }
}

