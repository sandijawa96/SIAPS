<?php

namespace App\Services;

use App\Models\Izin;
use App\Models\Notification;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IzinNotificationService
{
    public function __construct(
        private readonly PushNotificationService $pushNotificationService
    ) {
    }

    public function notifyApproversForNewIzin(int $izinId): void
    {
        try {
            $izin = Izin::query()
                ->with(['user:id,nama_lengkap,email', 'kelas:id,nama_kelas'])
                ->find($izinId);

            if (!$izin) {
                return;
            }

            $applicant = $izin->user;
            if (!$applicant) {
                return;
            }

            $isStudentApplicant = $this->isStudentRoleUser($applicant);
            $targetUserIds = [];
            if ($isStudentApplicant) {
                $waliKelasId = $izin->kelas_id
                    ? (int) DB::table('kelas')->where('id', $izin->kelas_id)->value('wali_kelas_id')
                    : 0;
                if ($waliKelasId > 0) {
                    $targetUserIds[] = $waliKelasId;
                }

                $wakasekIds = User::query()
                    ->whereHas('roles', function ($query) {
                        $query->whereIn('name', RoleNames::aliases(RoleNames::WAKASEK_KESISWAAN));
                    })
                    ->pluck('id')
                    ->map(fn ($value) => (int) $value)
                    ->all();
                $targetUserIds = array_merge($targetUserIds, $wakasekIds);
            } else {
                $approverIds = User::query()
                    ->whereHas('roles.permissions', function ($query) {
                        $query->where('name', 'approve_izin');
                    })
                    ->pluck('id')
                    ->map(fn ($value) => (int) $value)
                    ->all();
                $targetUserIds = array_merge($targetUserIds, $approverIds);
            }

            $targetUserIds = array_values(array_unique(array_filter($targetUserIds, function (int $id) use ($izin): bool {
                return $id > 0 && $id !== (int) $izin->user_id;
            })));

            if ($targetUserIds === []) {
                return;
            }

            $applicantName = $applicant->nama_lengkap ?: ($applicant->email ?: 'Pengguna');
            $dateRange = Carbon::parse((string) $izin->tanggal_mulai)->format('d M Y')
                . ' - '
                . Carbon::parse((string) $izin->tanggal_selesai)->format('d M Y');
            $jenisLabel = Izin::getJenisIzinLabel((string) $izin->jenis_izin);
            $title = $isStudentApplicant
                ? 'Pengajuan izin menunggu review'
                : 'Pengajuan izin pegawai menunggu review';
            $message = $isStudentApplicant
                ? "{$applicantName} mengajukan {$jenisLabel} untuk {$dateRange}. Periksa dan beri keputusan."
                : "{$applicantName} mengajukan {$jenisLabel} untuk {$dateRange}. Tinjau pengajuan pegawai ini.";
            $meta = [
                'izin_id' => $izin->id,
                'user_id' => $izin->user_id,
                'kelas_id' => $izin->kelas_id,
                'jenis_izin' => $izin->jenis_izin,
                'jenis_izin_label' => $jenisLabel,
                'tanggal_mulai' => $izin->tanggal_mulai,
                'tanggal_selesai' => $izin->tanggal_selesai,
                'message_category' => 'izin_approval_request',
                'source' => 'izin_workflow',
                'workflow_status' => 'pending',
                'workflow_status_label' => Izin::getStatusLabel('pending'),
                'subject_type' => $isStudentApplicant ? 'siswa' : 'pegawai',
            ];

            foreach ($targetUserIds as $targetUserId) {
                $notification = Notification::create([
                    'user_id' => $targetUserId,
                    'title' => $title,
                    'message' => $message,
                    'type' => 'info',
                    'data' => $meta,
                    'is_read' => false,
                    'created_by' => (int) $izin->user_id,
                ]);

                $this->dispatchPushForNotification($notification);
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to dispatch approver notification for izin', [
                'izin_id' => $izinId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function notifyStudentApprovalResult(int $izinId, string $status, ?int $actorUserId = null): void
    {
        try {
            $normalizedStatus = strtolower(trim($status));
            if (!in_array($normalizedStatus, ['approved', 'rejected'], true)) {
                return;
            }

            $izin = Izin::query()
                ->with([
                    'user:id,nama_lengkap,email',
                    'approvedBy:id,nama_lengkap,email',
                    'rejectedBy:id,nama_lengkap,email',
                ])
                ->find($izinId);

            if (!$izin) {
                return;
            }

            $isStudentApplicant = $izin->user ? $this->isStudentRoleUser($izin->user) : true;
            $reviewer = $normalizedStatus === 'approved' ? $izin->approvedBy : $izin->rejectedBy;
            $reviewerName = $reviewer?->nama_lengkap ?: ($reviewer?->email ?: 'Petugas');
            $jenisLabel = Izin::getJenisIzinLabel((string) $izin->jenis_izin);
            $dateRange = Carbon::parse((string) $izin->tanggal_mulai)->format('d M Y')
                . ' - '
                . Carbon::parse((string) $izin->tanggal_selesai)->format('d M Y');
            $title = $normalizedStatus === 'approved'
                ? ($isStudentApplicant ? 'Pengajuan izin disetujui' : 'Pengajuan izin pegawai disetujui')
                : ($isStudentApplicant ? 'Pengajuan izin ditolak' : 'Pengajuan izin pegawai ditolak');
            $message = $normalizedStatus === 'approved'
                ? "{$jenisLabel} untuk {$dateRange} disetujui oleh {$reviewerName}."
                : "{$jenisLabel} untuk {$dateRange} ditolak oleh {$reviewerName}.";

            $approvalNote = trim((string) ($izin->catatan_approval ?? ''));
            if ($approvalNote !== '') {
                $message .= " Catatan: {$approvalNote}";
            }

            $notification = Notification::create([
                'user_id' => (int) $izin->user_id,
                'title' => $title,
                'message' => $message,
                'type' => $normalizedStatus === 'approved' ? 'success' : 'warning',
                'data' => [
                    'izin_id' => $izin->id,
                    'status' => $normalizedStatus,
                    'status_label' => Izin::getStatusLabel($normalizedStatus),
                    'jenis_izin' => $izin->jenis_izin,
                    'jenis_izin_label' => $jenisLabel,
                    'catatan_approval' => $izin->catatan_approval,
                    'message_category' => 'izin_decision_result',
                    'source' => 'izin_workflow',
                    'workflow_status' => $normalizedStatus,
                    'subject_type' => $isStudentApplicant ? 'siswa' : 'pegawai',
                ],
                'is_read' => false,
                'created_by' => $actorUserId ?: ($reviewer?->id ? (int) $reviewer->id : null),
            ]);

            $this->dispatchPushForNotification($notification);
        } catch (\Throwable $exception) {
            Log::warning('Failed to dispatch student notification for izin decision', [
                'izin_id' => $izinId,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function notifyPendingReviewReminder(int $izinId, Carbon|string|null $referenceDate = null): int
    {
        try {
            $izin = Izin::query()
                ->with(['user:id,nama_lengkap,email', 'kelas:id,nama_kelas'])
                ->find($izinId);

            if (!$izin || $izin->status !== 'pending') {
                return 0;
            }

            $applicant = $izin->user;
            if (!$applicant || !$this->isStudentRoleUser($applicant)) {
                return 0;
            }

            $reference = $referenceDate instanceof Carbon
                ? $referenceDate->copy()->startOfDay()
                : Carbon::parse((string) ($referenceDate ?: now()->toDateString()))->startOfDay();
            $startDate = Carbon::parse((string) $izin->tanggal_mulai)->startOfDay();

            if ($startDate->gt($reference)) {
                return 0;
            }

            $overdueDays = $startDate->lt($reference)
                ? $startDate->diffInDays($reference)
                : 0;
            $reminderType = $overdueDays > 0 ? 'overdue_escalation' : 'due_today';
            $targetUserIds = $this->resolveReminderTargetUserIds($izin, $overdueDays);

            if ($targetUserIds === []) {
                return 0;
            }

            $applicantName = $applicant->nama_lengkap ?: ($applicant->email ?: 'Siswa');
            $jenisLabel = Izin::getJenisIzinLabel((string) $izin->jenis_izin);
            $dateRange = Carbon::parse((string) $izin->tanggal_mulai)->format('d M Y')
                . ' - '
                . Carbon::parse((string) $izin->tanggal_selesai)->format('d M Y');
            $title = $overdueDays > 0
                ? 'Izin siswa terlambat direview'
                : 'Izin hari ini masih menunggu review';
            $message = $overdueDays > 0
                ? "{$applicantName} mengajukan {$jenisLabel} untuk {$dateRange}. Pengajuan ini terlambat {$overdueDays} hari dan perlu keputusan."
                : "{$applicantName} mengajukan {$jenisLabel} untuk {$dateRange}. Hari mulai izin jatuh hari ini dan masih menunggu keputusan.";

            $createdCount = 0;
            $meta = [
                'izin_id' => (int) $izin->id,
                'user_id' => (int) $izin->user_id,
                'kelas_id' => $izin->kelas_id,
                'jenis_izin' => (string) $izin->jenis_izin,
                'jenis_izin_label' => $jenisLabel,
                'tanggal_mulai' => $izin->tanggal_mulai,
                'tanggal_selesai' => $izin->tanggal_selesai,
                'message_category' => 'izin_pending_review_reminder',
                'reminder_type' => $reminderType,
                'reminder_date' => $reference->toDateString(),
                'overdue_days' => $overdueDays,
                'source' => 'izin_workflow',
                'workflow_status' => 'pending',
                'workflow_status_label' => Izin::getStatusLabel('pending'),
                'subject_type' => 'siswa',
            ];

            foreach ($targetUserIds as $targetUserId) {
                if ($this->hasPendingReminderNotification(
                    $targetUserId,
                    (int) $izin->id,
                    $reference->toDateString(),
                    $reminderType
                )) {
                    continue;
                }

                $notification = Notification::create([
                    'user_id' => $targetUserId,
                    'title' => $title,
                    'message' => $message,
                    'type' => $overdueDays > 0 ? 'warning' : 'info',
                    'data' => $meta,
                    'is_read' => false,
                    'created_by' => (int) $izin->user_id,
                ]);

                $this->dispatchPushForNotification($notification);
                $createdCount++;
            }

            return $createdCount;
        } catch (\Throwable $exception) {
            Log::warning('Failed to dispatch pending review reminder for izin', [
                'izin_id' => $izinId,
                'reference_date' => $referenceDate instanceof Carbon ? $referenceDate->toDateString() : (string) $referenceDate,
                'error' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    private function dispatchPushForNotification(Notification $notification): void
    {
        try {
            $this->pushNotificationService->sendNotification($notification);
        } catch (\Throwable $exception) {
            Log::warning('Failed to send push for izin notification', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function isStudentRoleUser(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::SISWA));
    }

    /**
     * @return array<int>
     */
    private function resolveReminderTargetUserIds(Izin $izin, int $overdueDays): array
    {
        $targetUserIds = [];

        $waliKelasId = $izin->kelas_id
            ? (int) DB::table('kelas')->where('id', $izin->kelas_id)->value('wali_kelas_id')
            : 0;
        if ($waliKelasId > 0) {
            $targetUserIds[] = $waliKelasId;
        }

        if ($overdueDays > 0) {
            $wakasekIds = User::query()
                ->whereHas('roles', function ($query) {
                    $query->whereIn('name', RoleNames::aliases(RoleNames::WAKASEK_KESISWAAN));
                })
                ->pluck('id')
                ->map(fn ($value) => (int) $value)
                ->all();

            $targetUserIds = array_merge($targetUserIds, $wakasekIds);
        }

        return array_values(array_unique(array_filter($targetUserIds, function (int $id) use ($izin): bool {
            return $id > 0 && $id !== (int) $izin->user_id;
        })));
    }

    private function hasPendingReminderNotification(
        int $targetUserId,
        int $izinId,
        string $reminderDate,
        string $reminderType
    ): bool {
        $query = Notification::query()
            ->where('user_id', $targetUserId);

        $this->applyReminderNotificationFilter($query, $izinId, $reminderDate, $reminderType);

        return $query->exists();
    }

    private function applyReminderNotificationFilter($query, int $izinId, string $reminderDate, string $reminderType): void
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $query->whereRaw(
                "CAST(COALESCE(json_extract(data, '$.izin_id'), 0) AS INTEGER) = ?",
                [$izinId]
            )->whereRaw(
                "LOWER(COALESCE(json_extract(data, '$.message_category'), '')) = 'izin_pending_review_reminder'"
            )->whereRaw(
                "LOWER(COALESCE(json_extract(data, '$.reminder_type'), '')) = ?",
                [strtolower($reminderType)]
            )->whereRaw(
                "COALESCE(json_extract(data, '$.reminder_date'), '') = ?",
                [$reminderDate]
            );

            return;
        }

        $query->whereRaw(
            "CAST(COALESCE(data->>'izin_id', '0') AS BIGINT) = ?",
            [$izinId]
        )->whereRaw(
            "LOWER(COALESCE(data->>'message_category', '')) = 'izin_pending_review_reminder'"
        )->whereRaw(
            "LOWER(COALESCE(data->>'reminder_type', '')) = ?",
            [strtolower($reminderType)]
        )->whereRaw(
            "COALESCE(data->>'reminder_date', '') = ?",
            [$reminderDate]
        );
    }
}
