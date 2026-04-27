<?php

namespace App\Console\Commands;

use App\Models\Izin;
use App\Services\IzinNotificationService;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendPendingIzinReviewReminders extends Command
{
    protected $signature = 'izin:send-pending-review-reminders
        {--date= : Tanggal referensi reminder dalam format YYYY-MM-DD}
        {--dry-run : Hitung kandidat tanpa membuat notifikasi}';

    protected $description = 'Send daily reminders for pending student leave requests that are due today or already overdue.';

    public function __construct(
        private readonly IzinNotificationService $izinNotificationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $referenceDate = $this->resolveReferenceDate();
        if (!$referenceDate instanceof Carbon) {
            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');
        $studentRoleAliases = RoleNames::aliases(RoleNames::SISWA);
        $stats = [
            'pending_candidates' => 0,
            'due_today' => 0,
            'overdue' => 0,
            'notifications_created' => 0,
        ];

        Izin::query()
            ->select(['id', 'tanggal_mulai'])
            ->where('status', 'pending')
            ->whereDate('tanggal_mulai', '<=', $referenceDate->toDateString())
            ->whereHas('user', function ($query) use ($studentRoleAliases) {
                $query->whereHas('roles', function ($roleQuery) use ($studentRoleAliases) {
                    $roleQuery->whereIn('name', $studentRoleAliases);
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($izinRows) use (&$stats, $referenceDate, $dryRun): void {
                foreach ($izinRows as $izin) {
                    $stats['pending_candidates']++;

                    $startDate = Carbon::parse((string) $izin->tanggal_mulai)->startOfDay();
                    if ($startDate->lt($referenceDate)) {
                        $stats['overdue']++;
                    } else {
                        $stats['due_today']++;
                    }

                    if ($dryRun) {
                        continue;
                    }

                    $stats['notifications_created'] += $this->izinNotificationService->notifyPendingReviewReminder(
                        (int) $izin->id,
                        $referenceDate->copy()
                    );
                }
            });

        $this->table(
            ['Metric', 'Value'],
            [
                ['Reference date', $referenceDate->toDateString()],
                ['Pending candidates', (string) $stats['pending_candidates']],
                ['Due today', (string) $stats['due_today']],
                ['Overdue', (string) $stats['overdue']],
                ['Notifications created', (string) $stats['notifications_created']],
            ]
        );

        return self::SUCCESS;
    }

    private function resolveReferenceDate(): ?Carbon
    {
        $dateOption = trim((string) $this->option('date'));
        if ($dateOption === '') {
            return now()->startOfDay();
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $dateOption)->startOfDay();
        } catch (\Throwable) {
            $this->error('Format --date tidak valid. Gunakan YYYY-MM-DD.');
            return null;
        }
    }
}
