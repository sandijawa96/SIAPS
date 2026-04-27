<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\AttendanceDisciplineAlert;
use App\Models\AttendanceDisciplineCase;
use App\Models\Kelas;
use App\Models\Notification;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class AttendanceDisciplineAlertService
{
    public function __construct(
        private readonly AttendanceDisciplineService $disciplineService,
        private readonly PushNotificationService $pushNotificationService,
        private readonly WhatsappNotificationService $whatsappNotificationService,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function dispatchThresholdAlerts(?Carbon $referenceMonth = null, bool $dryRun = false): array
    {
        $reference = ($referenceMonth ?? now())->copy()->startOfMonth();
        $stats = [
            'checked_students' => 0,
            'threshold_exceeded_students' => 0,
            'threshold_exceeded_rules' => 0,
            'candidate_alerts' => 0,
            'created_cases' => 0,
            'created_alerts' => 0,
            'created_notifications' => 0,
            'created_whatsapp' => 0,
            'skipped_existing' => 0,
            'skipped_no_recipient' => 0,
            'failed' => 0,
        ];

        $kesiswaanRecipients = User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', RoleNames::aliases(RoleNames::WAKASEK_KESISWAAN));
            })
            ->with('dataKepegawaian')
            ->get()
            ->keyBy('id');

        User::query()
            ->where('is_active', true)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            })
            ->orderBy('id')
            ->chunkById(200, function ($students) use (&$stats, $reference, $dryRun, $kesiswaanRecipients) {
                foreach ($students as $student) {
                    if (!$student instanceof User) {
                        continue;
                    }

                    $stats['checked_students']++;
                    $snapshot = $this->disciplineService->buildUserDisciplineSnapshot($student, $reference);
                    $triggeredRules = $this->resolveTriggeredRules($snapshot);

                    if ($triggeredRules === []) {
                        continue;
                    }

                    $stats['threshold_exceeded_students']++;
                    $stats['threshold_exceeded_rules'] += count($triggeredRules);

                    foreach ($triggeredRules as $rule) {
                        $periodStart = Carbon::parse((string) ($rule['start_date'] ?? $reference->toDateString()))->startOfDay();
                        $periodEnd = Carbon::parse((string) ($rule['end_date'] ?? $reference->toDateString()))->endOfDay();
                        $classContext = $this->resolveClassContext($student, $periodStart, $periodEnd);
                        $recipients = $this->resolveRecipients($rule, $classContext, $kesiswaanRecipients);

                        if ($recipients === []) {
                            $stats['skipped_no_recipient']++;
                            continue;
                        }

                        $referenceCode = $this->buildReferenceCode($student, $rule);
                        $disciplineCase = $this->upsertDisciplineCase($student, $classContext, $rule, $referenceCode);
                        if ($disciplineCase->wasRecentlyCreated) {
                            $stats['created_cases']++;
                        }

                        foreach ($recipients as $recipientConfig) {
                            /** @var User $recipient */
                            $recipient = $recipientConfig['user'];
                            $audience = (string) $recipientConfig['audience'];
                            $automationKey = (string) $recipientConfig['automation_key'];

                            $exists = AttendanceDisciplineAlert::query()
                                ->where('user_id', (int) $student->id)
                                ->where('recipient_user_id', (int) $recipient->id)
                                ->where('rule_key', (string) $rule['rule_key'])
                                ->where('audience', $audience)
                                ->where('period_type', (string) $rule['period_type'])
                                ->where('period_key', (string) $rule['period_key'])
                                ->exists();

                            if ($exists) {
                                $stats['skipped_existing']++;
                                continue;
                            }

                            $stats['candidate_alerts']++;
                            if ($dryRun) {
                                continue;
                            }

                            try {
                                [$title, $message] = $this->buildNotificationCopy($student, $classContext, $rule);

                                $notification = Notification::create([
                                    'user_id' => (int) $recipient->id,
                                    'title' => $title,
                                    'message' => $message,
                                    'type' => 'warning',
                                    'data' => [
                                        'source' => 'attendance_discipline_threshold',
                                        'message_category' => 'system',
                                        'discipline_alert' => [
                                            'rule_key' => (string) $rule['rule_key'],
                                            'rule_label' => (string) ($rule['label'] ?? 'Pelanggaran'),
                                            'audience' => $audience,
                                            'student_id' => (int) $student->id,
                                            'student_name' => $this->resolveUserName($student),
                                            'class_name' => $classContext?->nama_kelas ?: ($rule['class_name'] ?? '-'),
                                            'metric_value' => (int) ($rule['metric_value'] ?? 0),
                                            'metric_limit' => (int) ($rule['metric_limit'] ?? 0),
                                            'metric_unit' => (string) ($rule['metric_unit'] ?? 'menit'),
                                            'period_type' => (string) ($rule['period_type'] ?? 'semester'),
                                            'period_key' => (string) ($rule['period_key'] ?? ''),
                                            'period_label' => (string) ($rule['period_label'] ?? '-'),
                                            'semester' => $rule['semester'] ?? null,
                                            'semester_label' => $rule['semester_label'] ?? null,
                                            'tahun_ajaran_id' => $rule['tahun_ajaran_id'] ?? null,
                                            'tahun_ajaran_name' => $rule['tahun_ajaran_nama'] ?? null,
                                            'reference' => $referenceCode,
                                            'discipline_case_id' => (int) $disciplineCase->id,
                                        ],
                                    ],
                                    'is_read' => false,
                                    'created_by' => null,
                                ]);

                                $this->dispatchPush($notification);
                                $stats['created_notifications']++;

                                $waResult = $this->whatsappNotificationService->sendAutomationToOperationalUser(
                                    $recipient,
                                    $automationKey,
                                    \App\Models\WhatsappGateway::TYPE_REMINDER,
                                    [
                                        'recipient_name' => $this->resolveUserName($recipient),
                                        'student_name' => $this->resolveUserName($student),
                                        'class_name' => $classContext?->nama_kelas ?: '-',
                                        'metric_label' => (string) ($rule['label'] ?? 'Pelanggaran'),
                                        'metric_value' => (string) ($rule['metric_value'] ?? 0),
                                        'metric_limit' => (string) ($rule['metric_limit'] ?? 0),
                                        'metric_unit' => (string) ($rule['metric_unit'] ?? 'menit'),
                                        'period_label' => (string) ($rule['period_label'] ?? '-'),
                                        'semester_label' => (string) ($rule['semester_label'] ?? '-'),
                                        'tahun_ajaran_name' => (string) ($rule['tahun_ajaran_nama'] ?? ''),
                                        'reference' => $referenceCode,
                                    ],
                                    [
                                        'source' => 'attendance_discipline_threshold',
                                        'rule_key' => (string) $rule['rule_key'],
                                        'audience' => $audience,
                                        'student_user_id' => (int) $student->id,
                                        'period_type' => (string) ($rule['period_type'] ?? 'semester'),
                                        'period_key' => (string) ($rule['period_key'] ?? ''),
                                    ]
                                );

                                if (($waResult['ok'] ?? false) === true) {
                                    $stats['created_whatsapp']++;
                                }

                                AttendanceDisciplineAlert::create([
                                    'user_id' => (int) $student->id,
                                    'recipient_user_id' => (int) $recipient->id,
                                    'notification_id' => (int) $notification->id,
                                    'whatsapp_notification_id' => isset($waResult['record_id']) ? (int) $waResult['record_id'] : null,
                                    'rule_key' => (string) $rule['rule_key'],
                                    'audience' => $audience,
                                    'period_type' => (string) ($rule['period_type'] ?? 'semester'),
                                    'period_key' => (string) ($rule['period_key'] ?? ''),
                                    'period_label' => (string) ($rule['period_label'] ?? ''),
                                    'semester' => (string) ($rule['semester'] ?? ''),
                                    'tahun_ajaran_id' => isset($rule['tahun_ajaran_id']) && is_numeric($rule['tahun_ajaran_id'])
                                        ? (int) $rule['tahun_ajaran_id']
                                        : null,
                                    'tahun_ajaran_ref' => (string) ($rule['tahun_ajaran_nama'] ?? ''),
                                    'triggered_at' => now(),
                                    'payload' => [
                                        'student_name' => $this->resolveUserName($student),
                                        'class_name' => $classContext?->nama_kelas ?: '-',
                                        'metric_label' => $rule['label'] ?? 'Pelanggaran',
                                        'metric_value' => (int) ($rule['metric_value'] ?? 0),
                                        'metric_limit' => (int) ($rule['metric_limit'] ?? 0),
                                        'metric_unit' => (string) ($rule['metric_unit'] ?? 'menit'),
                                        'reference' => $referenceCode,
                                        'discipline_case_id' => (int) $disciplineCase->id,
                                    ],
                                ]);

                                $stats['created_alerts']++;
                            } catch (\Throwable $exception) {
                                $stats['failed']++;
                                Log::warning('Failed to dispatch attendance discipline alert', [
                                    'student_id' => (int) $student->id,
                                    'recipient_user_id' => (int) $recipient->id,
                                    'rule_key' => $rule['rule_key'] ?? null,
                                    'audience' => $audience,
                                    'error' => $exception->getMessage(),
                                ]);
                            }
                        }
                    }
                }
            });

        return $stats;
    }

    /**
     * @return array<string, int>
     */
    public function dispatchSemesterAlphaThresholdAlerts(?Carbon $referenceMonth = null, bool $dryRun = false): array
    {
        return $this->dispatchThresholdAlerts($referenceMonth, $dryRun);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @return array<int, array<string, mixed>>
     */
    private function resolveTriggeredRules(array $snapshot): array
    {
        $rules = [];

        foreach (['monthly_late', 'semester_total_violation', 'semester_alpha'] as $key) {
            $metric = is_array($snapshot[$key] ?? null) ? $snapshot[$key] : [];
            if (!($metric['exceeded'] ?? false) || !($metric['alertable'] ?? false)) {
                continue;
            }

            $rules[] = [
                ...$metric,
                'metric_value' => (int) ($metric['minutes'] ?? $metric['days'] ?? 0),
                'metric_limit' => (int) ($metric['limit'] ?? 0),
                'metric_unit' => array_key_exists('days', $metric) ? 'hari' : 'menit',
            ];
        }

        return $rules;
    }

    /**
     * @param array<string, mixed> $rule
     * @param \Illuminate\Support\Collection<int, User> $kesiswaanRecipients
     * @return array<int, array{user:User,audience:string,automation_key:string}>
     */
    private function resolveRecipients(array $rule, ?Kelas $classContext, $kesiswaanRecipients): array
    {
        $recipients = [];
        $ruleKey = (string) ($rule['rule_key'] ?? '');

        if (($rule['notify_wali_kelas'] ?? false) && $classContext?->waliKelas instanceof User) {
            $wali = $classContext->waliKelas;
            $recipients[(int) $wali->id] = [
                'user' => $wali,
                'audience' => 'wali_kelas',
                'automation_key' => $this->resolveAutomationKey($ruleKey, 'wali_kelas'),
            ];
        }

        if ($rule['notify_kesiswaan'] ?? false) {
            foreach ($kesiswaanRecipients as $recipient) {
                if (!$recipient instanceof User) {
                    continue;
                }

                if (!isset($recipients[(int) $recipient->id])) {
                    $recipients[(int) $recipient->id] = [
                        'user' => $recipient,
                        'audience' => 'kesiswaan',
                        'automation_key' => $this->resolveAutomationKey($ruleKey, 'kesiswaan'),
                    ];
                }
            }
        }

        return array_values($recipients);
    }

    private function resolveAutomationKey(string $ruleKey, string $audience): string
    {
        return match ($ruleKey) {
            AttendanceDisciplineService::RULE_KEY_MONTHLY_LATE => $audience === 'wali_kelas'
                ? 'discipline_monthly_late_limit_wali_kelas'
                : 'discipline_monthly_late_limit_kesiswaan',
            AttendanceDisciplineService::RULE_KEY_SEMESTER_TOTAL_VIOLATION => $audience === 'wali_kelas'
                ? 'discipline_total_violation_semester_limit_wali_kelas'
                : 'discipline_total_violation_semester_limit_kesiswaan',
            default => $audience === 'wali_kelas'
                ? 'discipline_alpha_semester_limit_wali_kelas'
                : 'discipline_alpha_semester_limit_kesiswaan',
        };
    }

    private function resolveClassContext(User $student, Carbon $startDate, Carbon $endDate): ?Kelas
    {
        $attendance = Absensi::query()
            ->where('user_id', (int) $student->id)
            ->whereBetween('tanggal', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereNotNull('kelas_id')
            ->orderByDesc('tanggal')
            ->orderByDesc('id')
            ->first(['kelas_id']);

        if ($attendance?->kelas_id) {
            return Kelas::query()
                ->with('waliKelas.dataKepegawaian')
                ->find((int) $attendance->kelas_id);
        }

        if ($student->relationLoaded('kelas')) {
            $loaded = $student->kelas->first(function ($kelas) {
                return (bool) ($kelas->pivot->is_active ?? false);
            });

            if ($loaded instanceof Kelas) {
                $loaded->loadMissing('waliKelas.dataKepegawaian');
                return $loaded;
            }
        }

        return $student->kelas()
            ->with('waliKelas.dataKepegawaian')
            ->wherePivot('is_active', true)
            ->orderByDesc('kelas_siswa.updated_at')
            ->first();
    }

    /**
     * @param array<string, mixed> $rule
     * @return array{0:string,1:string}
     */
    private function buildNotificationCopy(User $student, ?Kelas $classContext, array $rule): array
    {
        $studentName = $this->resolveUserName($student);
        $className = $classContext?->nama_kelas ?: '-';
        $metricValue = (int) ($rule['metric_value'] ?? 0);
        $metricLimit = (int) ($rule['metric_limit'] ?? 0);
        $periodLabel = (string) ($rule['period_label'] ?? '-');

        return match ((string) ($rule['rule_key'] ?? '')) {
            AttendanceDisciplineService::RULE_KEY_MONTHLY_LATE => [
                'Batas keterlambatan bulanan terlampaui',
                sprintf(
                    '%s kelas %s telah mencapai %d menit keterlambatan pada %s. Batas saat ini %d menit.',
                    $studentName,
                    $className,
                    $metricValue,
                    $periodLabel,
                    $metricLimit
                ),
            ],
            AttendanceDisciplineService::RULE_KEY_SEMESTER_TOTAL_VIOLATION => [
                'Batas total pelanggaran semester terlampaui',
                sprintf(
                    '%s kelas %s telah mencapai %d menit total pelanggaran pada %s. Batas saat ini %d menit.',
                    $studentName,
                    $className,
                    $metricValue,
                    $periodLabel,
                    $metricLimit
                ),
            ],
            default => [
                'Batas alpha semester terlampaui',
                sprintf(
                    '%s kelas %s telah mencapai %d hari alpha pada %s. Batas saat ini %d hari.',
                    $studentName,
                    $className,
                    $metricValue,
                    $periodLabel,
                    $metricLimit
                ),
            ],
        };
    }

    private function resolveUserName(User $user): string
    {
        $name = trim((string) ($user->nama_lengkap ?? ''));
        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email !== '') {
            return $email;
        }

        return trim((string) ($user->username ?? 'Pengguna'));
    }

    private function dispatchPush(Notification $notification): void
    {
        try {
            $this->pushNotificationService->sendNotification($notification);
        } catch (\Throwable $exception) {
            Log::warning('Failed to push attendance discipline alert', [
                'notification_id' => (int) $notification->id,
                'user_id' => (int) $notification->user_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function upsertDisciplineCase(
        User $student,
        ?Kelas $classContext,
        array $rule,
        string $referenceCode
    ): AttendanceDisciplineCase {
        $periodType = (string) ($rule['period_type'] ?? 'semester');
        $periodKey = (string) ($rule['period_key'] ?? '');
        $periodLabel = (string) ($rule['period_label'] ?? '');
        $tahunAjaranId = isset($rule['tahun_ajaran_id']) && is_numeric($rule['tahun_ajaran_id'])
            ? (int) $rule['tahun_ajaran_id']
            : null;
        $tahunAjaranRef = trim((string) ($rule['tahun_ajaran_nama'] ?? ''));

        $case = AttendanceDisciplineCase::query()->firstOrNew([
            'user_id' => (int) $student->id,
            'rule_key' => (string) $rule['rule_key'],
            'period_type' => $periodType,
            'period_key' => $periodKey,
        ]);

        $firstTriggeredAt = $case->exists
            ? ($case->first_triggered_at ?? now())
            : now();

        $payload = is_array($case->payload) ? $case->payload : [];
        $resolvedClassName = $classContext?->nama_kelas
            ?: (string) ($payload['class_name'] ?? '-');
        $resolvedKelasId = $case->exists && $case->kelas_id
            ? $case->kelas_id
            : $classContext?->id;

        $case->fill([
            'kelas_id' => $resolvedKelasId,
            'status' => $case->status === AttendanceDisciplineCase::STATUS_PARENT_BROADCAST_SENT
                ? AttendanceDisciplineCase::STATUS_PARENT_BROADCAST_SENT
                : AttendanceDisciplineCase::STATUS_READY_FOR_PARENT_BROADCAST,
            'period_label' => $periodLabel,
            'semester' => (string) ($rule['semester'] ?? ''),
            'tahun_ajaran_id' => $tahunAjaranId,
            'tahun_ajaran_ref' => $tahunAjaranRef,
            'metric_value' => (int) ($rule['metric_value'] ?? 0),
            'metric_limit' => (int) ($rule['metric_limit'] ?? 0),
            'first_triggered_at' => $firstTriggeredAt,
            'last_triggered_at' => now(),
            'payload' => array_merge($payload, [
                'student_name' => $this->resolveUserName($student),
                'class_name' => $resolvedClassName,
                'rule_label' => (string) ($rule['label'] ?? 'Pelanggaran'),
                'metric_unit' => (string) ($rule['metric_unit'] ?? 'menit'),
                'period_label' => $periodLabel,
                'reference' => $referenceCode,
            ]),
        ]);
        $case->save();

        return $case;
    }

    /**
     * @param array<string, mixed> $rule
     */
    private function buildReferenceCode(User $student, array $rule): string
    {
        $rulePrefix = match ((string) ($rule['rule_key'] ?? '')) {
            AttendanceDisciplineService::RULE_KEY_MONTHLY_LATE => 'LATE',
            AttendanceDisciplineService::RULE_KEY_SEMESTER_TOTAL_VIOLATION => 'TVS',
            default => 'ALP',
        };

        $periodKey = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', (string) ($rule['period_key'] ?? 'NA')) ?: 'NA');

        return sprintf(
            'DISC-%d-%s-%s',
            (int) $student->id,
            $rulePrefix,
            $periodKey
        );
    }
}
