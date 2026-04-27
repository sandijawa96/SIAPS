<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\Izin;
use App\Models\User;
use App\Models\WhatsappGateway;
use App\Models\WhatsappNotificationSkip;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsappNotificationService
{
    public function __construct(
        private readonly WhatsappGatewayClient $gatewayClient,
        private readonly WhatsappAutomationService $automationService
    ) {}

    public function notifyAttendanceCheckIn(Absensi $attendance): void
    {
        $attendance->loadMissing('user', 'kelas');
        if (!$attendance->user) {
            return;
        }

        $studentName = $this->resolveUserDisplayName($attendance->user);
        $className = $this->resolveClassLabel($attendance->user, $attendance->kelas?->nama_kelas);
        $statusLabel = strtolower((string) $attendance->status) === 'terlambat'
            ? 'Terlambat'
            : 'Hadir';
        $dateLabel = $this->formatDate($attendance->tanggal);
        $timeLabel = $this->formatTime($attendance->jam_masuk);
        $manualLabel = (bool) $attendance->is_manual ? ' (dicatat manual oleh petugas)' : '';
        $reference = 'ABS-' . $attendance->id;

        $this->sendAutomationToStudentContacts(
            $attendance->user,
            'attendance_checkin',
            WhatsappGateway::TYPE_ABSENSI,
            [
                'student_name' => $studentName,
                'class_name' => $className,
                'date_label' => $dateLabel,
                'time_label' => $timeLabel,
                'status_label' => $statusLabel,
                'manual_label' => $manualLabel,
                'reference' => $reference,
            ],
            [
                'source' => 'attendance_checkin',
                'attendance_id' => $attendance->id,
                'tanggal' => $attendance->tanggal?->format('Y-m-d'),
                'status' => $attendance->status,
                'is_manual' => (bool) $attendance->is_manual,
            ]
        );
    }

    public function notifyAttendanceCheckOut(Absensi $attendance): void
    {
        $attendance->loadMissing('user', 'kelas');
        if (!$attendance->user) {
            return;
        }

        $studentName = $this->resolveUserDisplayName($attendance->user);
        $className = $this->resolveClassLabel($attendance->user, $attendance->kelas?->nama_kelas);
        $dateLabel = $this->formatDate($attendance->tanggal);
        $checkInLabel = $this->formatTime($attendance->jam_masuk);
        $timeLabel = $this->formatTime($attendance->jam_pulang);
        $manualLabel = (bool) $attendance->is_manual ? ' (dicatat manual oleh petugas)' : '';
        $durationLabel = $this->formatDuration($attendance->jam_masuk, $attendance->jam_pulang);
        $reference = 'ABS-' . $attendance->id;

        $this->sendAutomationToStudentContacts(
            $attendance->user,
            'attendance_checkout',
            WhatsappGateway::TYPE_ABSENSI,
            [
                'student_name' => $studentName,
                'class_name' => $className,
                'date_label' => $dateLabel,
                'check_in_label' => $checkInLabel,
                'time_label' => $timeLabel,
                'manual_label' => $manualLabel,
                'duration_label' => $durationLabel,
                'reference' => $reference,
            ],
            [
                'source' => 'attendance_checkout',
                'attendance_id' => $attendance->id,
                'tanggal' => $attendance->tanggal?->format('Y-m-d'),
                'status' => $attendance->status,
                'is_manual' => (bool) $attendance->is_manual,
            ]
        );
    }

    public function notifyIzinSubmitted(Izin $izin): void
    {
        $izin->loadMissing('user', 'kelas');
        if (!$izin->user) {
            return;
        }

        $studentName = $this->resolveUserDisplayName($izin->user);
        $className = $this->resolveClassLabel($izin->user, $izin->kelas?->nama_kelas);
        $dateRange = $this->formatDateRange($izin->tanggal_mulai, $izin->tanggal_selesai);
        $jenis = Izin::getJenisIzinLabel((string) $izin->jenis_izin);
        $reference = 'IZN-' . $izin->id;

        $this->sendAutomationToStudentContacts(
            $izin->user,
            'izin_submitted',
            WhatsappGateway::TYPE_IZIN,
            [
                'student_name' => $studentName,
                'class_name' => $className,
                'jenis_label' => $jenis,
                'date_range' => $dateRange,
                'reference' => $reference,
            ],
            [
                'source' => 'izin_submitted',
                'izin_id' => $izin->id,
                'status' => $izin->status,
                'jenis_izin' => $izin->jenis_izin,
                'tanggal_mulai' => $izin->tanggal_mulai?->format('Y-m-d'),
                'tanggal_selesai' => $izin->tanggal_selesai?->format('Y-m-d'),
            ]
        );
    }

    public function notifyIzinDecision(Izin $izin): void
    {
        $izin->loadMissing('user', 'kelas');
        if (!$izin->user) {
            return;
        }

        $status = strtolower((string) $izin->status);
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return;
        }

        $studentName = $this->resolveUserDisplayName($izin->user);
        $className = $this->resolveClassLabel($izin->user, $izin->kelas?->nama_kelas);
        $dateRange = $this->formatDateRange($izin->tanggal_mulai, $izin->tanggal_selesai);
        $jenis = Izin::getJenisIzinLabel((string) $izin->jenis_izin);
        $decisionLabel = Izin::getStatusLabel($status);
        $reference = 'IZN-' . $izin->id;

        $approvalNote = trim((string) ($izin->catatan_approval ?? ''));
        $approvalNoteBlock = $approvalNote !== ''
            ? 'Catatan petugas: *' . $approvalNote . '*' . "\n"
            : '';

        $this->sendAutomationToStudentContacts(
            $izin->user,
            'izin_decision',
            WhatsappGateway::TYPE_IZIN,
            [
                'student_name' => $studentName,
                'class_name' => $className,
                'jenis_label' => $jenis,
                'date_range' => $dateRange,
                'decision_label' => $decisionLabel,
                'approval_note_block' => $approvalNoteBlock,
                'reference' => $reference,
            ],
            [
                'source' => 'izin_decision',
                'izin_id' => $izin->id,
                'status' => $izin->status,
                'jenis_izin' => $izin->jenis_izin,
                'tanggal_mulai' => $izin->tanggal_mulai?->format('Y-m-d'),
                'tanggal_selesai' => $izin->tanggal_selesai?->format('Y-m-d'),
                'approved_by' => $izin->approved_by,
                'rejected_by' => $izin->rejected_by,
            ]
        );
    }

    public function sendToUser(User $user, string $message, string $type, array $metadata = []): array
    {
        $phoneNumber = $this->resolveUserPhoneNumber($user);
        if ($phoneNumber === null) {
            Log::info('WA notification skipped: parent phone number not available', [
                'user_id' => $user->id,
                'type' => $type,
                'source' => $metadata['source'] ?? null,
            ]);

            $skip = $this->recordSkip($type, WhatsappNotificationSkip::REASON_MISSING_PHONE, array_merge($metadata, [
                'target_user_id' => $user->id,
                'recipient_scope' => 'parent_only',
            ]));

            return [
                'ok' => false,
                'reason' => 'missing_phone_number',
                'skip_id' => $skip->id,
            ];
        }

        return $this->sendToPhone($phoneNumber, $message, $type, array_merge($metadata, [
            'target_user_id' => $user->id,
            'recipient_scope' => 'parent_only',
        ]));
    }

    public function sendAutomationToStudentContacts(
        User $user,
        string $automationKey,
        string $type,
        array $variables = [],
        array $metadata = []
    ): array {
        $rendered = $this->automationService->render($automationKey, $variables);
        if ($rendered === null) {
            return [
                'ok' => false,
                'reason' => 'automation_disabled',
            ];
        }

        $phoneNumber = $this->resolveUserPhoneNumber($user);
        if ($phoneNumber === null) {
            Log::info('WA notification skipped: parent phone number not available', [
                'user_id' => $user->id,
                'type' => $type,
                'source' => $metadata['source'] ?? null,
                'automation_key' => $automationKey,
            ]);

            $skip = $this->recordSkip($type, WhatsappNotificationSkip::REASON_MISSING_PHONE, array_merge($metadata, [
                'target_user_id' => $user->id,
                'recipient_scope' => 'parent_only',
                'automation_key' => $automationKey,
            ]));

            return [
                'ok' => false,
                'reason' => 'missing_phone_number',
                'skip_id' => $skip->id,
            ];
        }

        return $this->sendToPhone(
            $phoneNumber,
            (string) $rendered['message'],
            $type,
            array_merge($metadata, [
                'target_user_id' => $user->id,
                'recipient_scope' => 'parent_only',
                'automation_key' => $automationKey,
            ]),
            [
                'footer' => $rendered['footer'] ?? null,
            ]
        );
    }

    public function sendAutomationToOperationalUser(
        User $user,
        string $automationKey,
        string $type,
        array $variables = [],
        array $metadata = []
    ): array {
        $rendered = $this->automationService->render($automationKey, $variables);
        if ($rendered === null) {
            return [
                'ok' => false,
                'reason' => 'automation_disabled',
            ];
        }

        $phoneNumber = $this->resolveOperationalUserPhoneNumber($user);
        if ($phoneNumber === null) {
            Log::info('WA notification skipped: operational phone number not available', [
                'user_id' => $user->id,
                'type' => $type,
                'source' => $metadata['source'] ?? null,
                'automation_key' => $automationKey,
            ]);

            $skip = $this->recordSkip($type, WhatsappNotificationSkip::REASON_MISSING_PHONE, array_merge($metadata, [
                'target_user_id' => $user->id,
                'recipient_scope' => 'operational_user',
                'automation_key' => $automationKey,
            ]));

            return [
                'ok' => false,
                'reason' => 'missing_phone_number',
                'skip_id' => $skip->id,
            ];
        }

        return $this->sendToPhone(
            $phoneNumber,
            (string) $rendered['message'],
            $type,
            array_merge($metadata, [
                'target_user_id' => $user->id,
                'recipient_scope' => 'operational_user',
                'automation_key' => $automationKey,
            ]),
            [
                'footer' => $rendered['footer'] ?? null,
            ]
        );
    }

    public function sendToPhone(
        string $phoneNumber,
        string $message,
        string $type,
        array $metadata = [],
        array $options = []
    ): array
    {
        $normalizedPhone = WhatsappGateway::normalizePhoneNumber($phoneNumber);
        if ($normalizedPhone === '' || trim($message) === '') {
            return [
                'ok' => false,
                'reason' => 'invalid_payload',
            ];
        }

        $resolvedMetadata = $metadata;
        if (!empty($options['footer'])) {
            $resolvedMetadata['footer'] = (string) $options['footer'];
        }
        if (!empty($options['msgid'])) {
            $resolvedMetadata['reply_to_message_id'] = (string) $options['msgid'];
        }

        $record = WhatsappGateway::create([
            'phone_number' => $normalizedPhone,
            'message' => trim($message),
            'type' => $type,
            'status' => WhatsappGateway::STATUS_PENDING,
            'metadata' => $resolvedMetadata,
            'retry_count' => 0,
            'max_retries' => 3,
            'created_by' => null,
        ]);

        $result = $this->gatewayClient->sendMessage($normalizedPhone, trim($message), [
            'footer' => $options['footer'] ?? null,
            'msgid' => $options['msgid'] ?? ($resolvedMetadata['reply_to_message_id'] ?? null),
        ]);
        if ($result['ok']) {
            $record->markAsSent(is_array($result['gateway_response']) ? $result['gateway_response'] : null);
            return [
                'ok' => true,
                'record_id' => $record->id,
            ];
        }

        if (($result['pending_verification'] ?? false) === true) {
            $record->markAsPendingVerification(
                (string) $result['message'],
                is_array($result['gateway_response']) ? $result['gateway_response'] : null
            );

            return [
                'ok' => true,
                'record_id' => $record->id,
                'reason' => 'pending_verification',
                'pending_verification' => true,
            ];
        }

        $reason = (string) ($result['reason'] ?? '');
        if (in_array($reason, ['notifications_disabled', 'missing_configuration'], true)) {
            $record->forceDelete();

            $skip = $this->recordSkip(
                $type,
                $reason === 'notifications_disabled'
                    ? WhatsappNotificationSkip::REASON_NOTIFICATIONS_DISABLED
                    : WhatsappNotificationSkip::REASON_MISSING_CONFIGURATION,
                $resolvedMetadata,
                $normalizedPhone
            );

            return [
                'ok' => false,
                'reason' => $reason,
                'skip_id' => $skip->id,
            ];
        }

        $record->markAsFailed(
            (string) $result['message'],
            is_array($result['gateway_response']) ? $result['gateway_response'] : null
        );

        return [
            'ok' => false,
            'record_id' => $record->id,
            'reason' => $result['message'] ?? 'gateway_error',
        ];
    }

    private function recordSkip(string $type, string $reason, array $metadata = [], ?string $phoneCandidate = null): WhatsappNotificationSkip
    {
        return WhatsappNotificationSkip::create([
            'type' => $type,
            'reason' => $reason,
            'target_user_id' => isset($metadata['target_user_id']) && is_numeric($metadata['target_user_id'])
                ? (int) $metadata['target_user_id']
                : null,
            'phone_candidate' => $phoneCandidate,
            'metadata' => $metadata,
        ]);
    }

    public function resolveUserPhoneNumber(User $user): ?string
    {
        $user->loadMissing('dataPribadiSiswa');

        $candidates = [
            $user->dataPribadiSiswa?->no_hp_ortu,
            $user->dataPribadiSiswa?->no_hp_ayah,
            $user->dataPribadiSiswa?->no_hp_ibu,
            $user->dataPribadiSiswa?->no_hp_wali,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $normalized = WhatsappGateway::normalizePhoneNumber($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    public function resolveOperationalUserPhoneNumber(User $user): ?string
    {
        $user->loadMissing('dataKepegawaian');

        $candidates = [
            $user->dataKepegawaian?->no_hp,
            $user->dataKepegawaian?->no_telepon_kantor,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }

            $normalized = WhatsappGateway::normalizePhoneNumber($candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return null;
    }

    private function resolveUserDisplayName(User $user): string
    {
        $name = trim((string) ($user->nama_lengkap ?? ''));
        if ($name !== '') {
            return $name;
        }

        $username = trim((string) ($user->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        $email = trim((string) ($user->email ?? ''));
        return $email !== '' ? $email : '-';
    }

    private function resolveClassLabel(User $user, ?string $directClassName = null): string
    {
        $direct = trim((string) ($directClassName ?? ''));
        if ($direct !== '') {
            return $direct;
        }

        $activeClass = $user->kelas()
            ->wherePivot('is_active', true)
            ->orderByDesc('kelas_siswa.updated_at')
            ->first();

        return $activeClass?->nama_kelas ?: '-';
    }

    private function resolveSchoolName(): string
    {
        $cachedName = Cache::get('settings.school_profile.nama_sekolah');
        if (is_string($cachedName) && trim($cachedName) !== '') {
            return trim($cachedName);
        }

        $fallback = trim((string) config('app.name', 'Sekolah'));
        return $fallback !== '' ? $fallback : 'Sekolah';
    }

    private function buildHeader(string $title): string
    {
        return '[' . $this->resolveSchoolName() . '] ' . $title;
    }

    private function formatDuration($jamMasuk, $jamPulang): string
    {
        if (!$jamMasuk || !$jamPulang) {
            return '-';
        }

        try {
            $masuk = Carbon::parse($jamMasuk);
            $pulang = Carbon::parse($jamPulang);
            $minutes = max(0, $masuk->diffInMinutes($pulang));
            $hoursPart = intdiv($minutes, 60);
            $minutesPart = $minutes % 60;

            return sprintf('%d jam %02d menit', $hoursPart, $minutesPart);
        } catch (\Throwable) {
            return '-';
        }
    }

    private function formatDateRange($startDate, $endDate): string
    {
        $start = $startDate ? Carbon::parse($startDate)->locale('id')->isoFormat('D MMMM YYYY') : '-';
        $end = $endDate ? Carbon::parse($endDate)->locale('id')->isoFormat('D MMMM YYYY') : '-';

        if ($start === $end) {
            return $start;
        }

        return "{$start} - {$end}";
    }

    private function formatDate($date): string
    {
        if (!$date) {
            return '-';
        }

        return Carbon::parse($date)->locale('id')->isoFormat('D MMMM YYYY');
    }

    private function formatTime($time): string
    {
        if (!$time) {
            return '-';
        }

        return Carbon::parse($time)->format('H:i');
    }
}
