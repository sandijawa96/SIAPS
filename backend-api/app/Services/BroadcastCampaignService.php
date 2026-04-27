<?php

namespace App\Services;

use App\Jobs\ProcessBroadcastEmailChunk;
use App\Jobs\ProcessBroadcastNotificationChunk;
use App\Jobs\ProcessBroadcastWhatsappChunk;
use App\Mail\BroadcastCampaignMail;
use App\Models\AttendanceDisciplineCase;
use App\Models\BroadcastCampaign;
use App\Models\Notification;
use App\Models\User;
use App\Models\WhatsappGateway;
use App\Support\RoleNames;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BroadcastCampaignService
{
    private const CHANNEL_IN_APP = 'in_app';
    private const CHANNEL_WHATSAPP = 'whatsapp';
    private const CHANNEL_EMAIL = 'email';

    public function __construct(
        private readonly PushNotificationService $pushNotificationService,
        private readonly WhatsappGatewayClient $whatsappGatewayClient,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function dispatch(BroadcastCampaign $campaign, array $payload): BroadcastCampaign
    {
        $selectedChannels = $this->normalizeChannels((array) ($payload['channels'] ?? []));
        $audience = (array) ($payload['audience'] ?? []);
        $audienceMode = (string) ($audience['mode'] ?? 'all');
        $channelResults = [];
        $queuedJobs = 0;
        $queuedDispatches = [];

        if ($selectedChannels['in_app'] || $selectedChannels['popup']) {
            if ($audienceMode === 'manual') {
                $channelResults[] = $this->makeChannelRow(
                    self::CHANNEL_IN_APP,
                    'Aplikasi',
                    true,
                    0,
                    0,
                    'Nomor manual tidak didukung untuk inbox aplikasi.',
                    $this->makePushSummary(),
                    true,
                );
            } else {
                $targetUserIds = $this->resolveNotificationTargetIds($audience);
                $chunks = $this->chunkValues($targetUserIds, $this->notificationChunkSize());
                $channelResults[] = $this->makeChannelRow(
                    self::CHANNEL_IN_APP,
                    'Aplikasi',
                    true,
                    count($targetUserIds),
                    count($chunks),
                    $targetUserIds === [] ? 'Tidak ada target notifikasi aktif yang cocok.' : null,
                    $this->makePushSummary(),
                );

                $notificationPayload = $this->buildNotificationJobPayload($payload, $selectedChannels);
                foreach ($chunks as $chunk) {
                    $queuedDispatches[] = [
                        'channel' => self::CHANNEL_IN_APP,
                        'payload' => $notificationPayload,
                        'chunk' => $chunk,
                    ];
                    $queuedJobs++;
                }
            }
        }

        if ($selectedChannels['whatsapp']) {
            $whatsappTargets = $this->resolveWhatsappTargets($audience);
            $whatsappAvailability = $this->whatsappGatewayClient->getAvailability();

            if (!$whatsappAvailability['can_send']) {
                $channelResults[] = $this->makeChannelRow(
                    self::CHANNEL_WHATSAPP,
                    'WhatsApp',
                    true,
                    $whatsappTargets->count(),
                    0,
                    $whatsappAvailability['reason'] === 'notifications_disabled'
                        ? 'Kanal WhatsApp dilewati karena switch global WA sedang nonaktif.'
                        : 'Kanal WhatsApp dilewati karena konfigurasi gateway belum lengkap.',
                    null,
                    true
                );
            } else {
                $chunks = $this->chunkWhatsappTargets($whatsappTargets, $this->whatsappChunkSize());
                $channelResults[] = $this->makeChannelRow(
                    self::CHANNEL_WHATSAPP,
                    'WhatsApp',
                    true,
                    $whatsappTargets->count(),
                    count($chunks),
                    $whatsappTargets->isEmpty() ? 'Tidak ada nomor WhatsApp valid yang cocok.' : null,
                );

                $whatsappPayload = $this->buildWhatsappJobPayload($payload);
                foreach ($chunks as $chunk) {
                    $queuedDispatches[] = [
                        'channel' => self::CHANNEL_WHATSAPP,
                        'payload' => $whatsappPayload,
                        'chunk' => $chunk,
                    ];
                    $queuedJobs++;
                }
            }
        }

        if ($selectedChannels['email']) {
            if ($audienceMode === 'manual') {
                $channelResults[] = $this->makeChannelRow(
                    self::CHANNEL_EMAIL,
                    'Email',
                    true,
                    0,
                    0,
                    'Target manual hanya didukung untuk kanal WhatsApp.',
                    null,
                    true,
                );
            } else {
                $emailTargets = $this->resolveEmailTargets($audience);
                $chunks = $this->chunkEmailTargets($emailTargets, $this->emailChunkSize());
                $channelResults[] = $this->makeChannelRow(
                    self::CHANNEL_EMAIL,
                    'Email',
                    true,
                    $emailTargets->count(),
                    count($chunks),
                    $emailTargets->isEmpty() ? 'Tidak ada email valid yang cocok.' : null,
                );

                $emailPayload = $this->buildEmailJobPayload($payload);
                foreach ($chunks as $chunk) {
                    $queuedDispatches[] = [
                        'channel' => self::CHANNEL_EMAIL,
                        'payload' => $emailPayload,
                        'chunk' => $chunk,
                    ];
                    $queuedJobs++;
                }
            }
        }

        $campaign->update([
            'status' => $queuedJobs > 0 ? 'processing' : 'skipped',
            'total_target' => $this->maxTargetCount($channelResults),
            'sent_count' => 0,
            'failed_count' => 0,
            'summary' => [
                'channels' => $channelResults,
                'meta' => [
                    'discipline_case_id' => (int) (($payload['data']['discipline_case_id'] ?? 0)),
                    'queued_jobs' => $queuedJobs,
                ],
            ],
            'sent_at' => null,
        ]);

        if ($queuedJobs === 0) {
            $this->finalizeCampaign($campaign->id);
        } else {
            foreach ($queuedDispatches as $dispatch) {
                $channel = (string) ($dispatch['channel'] ?? '');
                $chunk = (array) ($dispatch['chunk'] ?? []);
                $jobPayload = (array) ($dispatch['payload'] ?? []);

                if ($channel === self::CHANNEL_IN_APP) {
                    ProcessBroadcastNotificationChunk::dispatch($campaign->id, $jobPayload, $chunk);
                    continue;
                }

                if ($channel === self::CHANNEL_WHATSAPP) {
                    ProcessBroadcastWhatsappChunk::dispatch($campaign->id, $jobPayload, $chunk);
                    continue;
                }

                if ($channel === self::CHANNEL_EMAIL) {
                    ProcessBroadcastEmailChunk::dispatch($campaign->id, $jobPayload, $chunk);
                }
            }
        }

        return $campaign->fresh(['creator']) ?? $campaign;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, int> $userIds
     */
    public function processNotificationChunk(int $campaignId, array $payload, array $userIds): void
    {
        $campaign = BroadcastCampaign::find($campaignId);
        if (!$campaign instanceof BroadcastCampaign) {
            return;
        }

        $sentCount = 0;
        $failedCount = 0;
        $pushSummary = $this->makePushSummary();
        $uniqueUserIds = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn(int $id): bool => $id > 0)));

        foreach ($uniqueUserIds as $userId) {
            try {
                $notification = Notification::create([
                    'user_id' => $userId,
                    'title' => (string) ($payload['title'] ?? $campaign->title),
                    'message' => (string) ($payload['message'] ?? $campaign->message),
                    'type' => (string) ($payload['type'] ?? $campaign->type),
                    'data' => $this->buildNotificationData($campaign, $payload),
                    'is_read' => false,
                    'display_start_at' => $campaign->display_start_at,
                    'display_end_at' => $campaign->display_end_at,
                    'expires_at' => $campaign->expires_at,
                    'pinned_at' => $campaign->pinned_at,
                    'priority' => (int) $campaign->priority,
                    'created_by' => $campaign->created_by,
                ]);

                $pushResult = $this->pushNotificationService->sendNotification($notification);
                $this->mergePushSummary($pushSummary, $pushResult);
                $sentCount++;
            } catch (\Throwable $throwable) {
                $failedCount++;

                Log::error('Broadcast notification chunk item failed', [
                    'broadcast_campaign_id' => $campaignId,
                    'user_id' => $userId,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        $this->applyChannelChunkProgress(
            $campaignId,
            self::CHANNEL_IN_APP,
            $sentCount,
            $failedCount,
            [
                'push' => $pushSummary,
                'note' => $failedCount > 0
                    ? "Sebagian notifikasi aplikasi gagal diproses ({$failedCount})."
                    : null,
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $targets
     */
    public function processWhatsappChunk(int $campaignId, array $payload, array $targets): void
    {
        $campaign = BroadcastCampaign::find($campaignId);
        if (!$campaign instanceof BroadcastCampaign) {
            return;
        }

        $availability = $this->whatsappGatewayClient->getAvailability();
        if (!$availability['can_send']) {
            $this->applyChannelChunkProgress(
                $campaignId,
                self::CHANNEL_WHATSAPP,
                0,
                0,
                [
                    'skipped' => count($targets),
                    'note' => $availability['reason'] === 'notifications_disabled'
                        ? 'Chunk WhatsApp dilewati karena switch global WA sedang nonaktif.'
                        : 'Chunk WhatsApp dilewati karena konfigurasi gateway belum lengkap.',
                ]
            );

            return;
        }

        $sentCount = 0;
        $failedCount = 0;
        $failedSamples = [];

        foreach ($targets as $target) {
            $phoneNumber = trim((string) ($target['phone_number'] ?? ''));
            if ($phoneNumber === '') {
                $failedCount++;
                continue;
            }

            try {
                $record = WhatsappGateway::create([
                    'phone_number' => $phoneNumber,
                    'message' => (string) ($payload['message'] ?? $campaign->message),
                    'type' => WhatsappGateway::TYPE_PENGUMUMAN,
                    'status' => WhatsappGateway::STATUS_PENDING,
                    'metadata' => [
                        'source' => 'broadcast_campaign',
                        'broadcast_campaign_id' => $campaign->id,
                        'target_user_id' => $target['user_id'] ?? null,
                        'footer' => $payload['footer'] ?? null,
                        'reply_to_message_id' => $payload['reply_to_message_id'] ?? null,
                        'requested_by' => $campaign->created_by,
                    ],
                    'retry_count' => 0,
                    'max_retries' => 3,
                    'created_by' => $campaign->created_by,
                ]);

                $result = $this->whatsappGatewayClient->sendMessage(
                    $phoneNumber,
                    (string) ($payload['message'] ?? $campaign->message),
                    [
                        'footer' => $payload['footer'] ?? null,
                        'msgid' => $payload['reply_to_message_id'] ?? null,
                    ]
                );

                if ($result['ok']) {
                    $record->markAsSent(is_array($result['gateway_response']) ? $result['gateway_response'] : null);
                    $sentCount++;
                    continue;
                }

                if (($result['pending_verification'] ?? false) === true) {
                    $record->markAsPendingVerification(
                        (string) $result['message'],
                        is_array($result['gateway_response']) ? $result['gateway_response'] : null
                    );
                    $sentCount++;
                    continue;
                }

                $record->markAsFailed(
                    (string) $result['message'],
                    is_array($result['gateway_response']) ? $result['gateway_response'] : null
                );

                $failedCount++;
                if (count($failedSamples) < 20) {
                    $failedSamples[] = [
                        'user_id' => $target['user_id'] ?? null,
                        'phone_number' => $phoneNumber,
                        'message' => $result['message'] ?? 'Pengiriman WhatsApp gagal',
                        'http_status' => $result['http_status'] ?? null,
                    ];
                }
            } catch (\Throwable $throwable) {
                $failedCount++;

                if (count($failedSamples) < 20) {
                    $failedSamples[] = [
                        'user_id' => $target['user_id'] ?? null,
                        'phone_number' => $phoneNumber,
                        'message' => $throwable->getMessage(),
                    ];
                }

                Log::error('Broadcast WhatsApp chunk item failed', [
                    'broadcast_campaign_id' => $campaignId,
                    'phone_number' => $phoneNumber,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        $this->applyChannelChunkProgress(
            $campaignId,
            self::CHANNEL_WHATSAPP,
            $sentCount,
            $failedCount,
            [
                'note' => $failedCount > 0
                    ? "Sebagian pengiriman WhatsApp gagal ({$failedCount})."
                    : null,
                'failed_samples' => $failedSamples,
            ]
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, array<string, mixed>> $targets
     */
    public function processEmailChunk(int $campaignId, array $payload, array $targets): void
    {
        $campaign = BroadcastCampaign::find($campaignId);
        if (!$campaign instanceof BroadcastCampaign) {
            return;
        }

        $sentCount = 0;
        $failedCount = 0;
        $failedSamples = [];

        foreach ($targets as $target) {
            $email = trim((string) ($target['email'] ?? ''));
            if ($email === '') {
                $failedCount++;
                continue;
            }

            try {
                Mail::to($email)->send(new BroadcastCampaignMail([
                    'subject' => $payload['subject'] ?? $payload['title'] ?? $campaign->title,
                    'title' => $payload['title'] ?? $campaign->title,
                    'message' => $payload['message'] ?? $campaign->message,
                    'type' => $payload['type'] ?? $campaign->type,
                    'cta_label' => $payload['cta_label'] ?? null,
                    'cta_url' => $payload['cta_url'] ?? null,
                ]));

                $sentCount++;
            } catch (\Throwable $throwable) {
                $failedCount++;

                if (count($failedSamples) < 20) {
                    $failedSamples[] = [
                        'user_id' => $target['user_id'] ?? null,
                        'email' => $email,
                        'message' => $throwable->getMessage(),
                    ];
                }

                Log::error('Broadcast email chunk item failed', [
                    'broadcast_campaign_id' => $campaignId,
                    'email' => $email,
                    'message' => $throwable->getMessage(),
                ]);
            }
        }

        $this->applyChannelChunkProgress(
            $campaignId,
            self::CHANNEL_EMAIL,
            $sentCount,
            $failedCount,
            [
                'note' => $failedCount > 0
                    ? "Sebagian pengiriman email gagal ({$failedCount})."
                    : null,
                'failed_samples' => $failedSamples,
            ]
        );
    }

    public function markChunkAsFailed(int $campaignId, string $channelKey, int $failedCount, string $message): void
    {
        $this->applyChannelChunkProgress(
            $campaignId,
            $channelKey,
            0,
            max(0, $failedCount),
            [
                'note' => $message,
            ]
        );
    }

    /**
     * @param array<string, bool> $channels
     * @return array<string, bool>
     */
    private function normalizeChannels(array $channels): array
    {
        return [
            'in_app' => (bool) ($channels['in_app'] ?? false),
            'popup' => (bool) ($channels['popup'] ?? false),
            'whatsapp' => (bool) ($channels['whatsapp'] ?? false),
            'email' => (bool) ($channels['email'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $audience
     */
    private function buildActiveUserQuery(array $audience): Builder
    {
        $mode = (string) ($audience['mode'] ?? 'all');
        $query = User::query()->where('is_active', true);

        if ($mode === 'role') {
            $roleAliases = RoleNames::aliasesFor((string) ($audience['role'] ?? ''));
            if ($roleAliases !== []) {
                $query->whereHas('roles', function ($roleQuery) use ($roleAliases) {
                    $roleQuery->whereIn('name', $roleAliases);
                });
            }
        }

        if ($mode === 'class') {
            $kelasId = (int) ($audience['kelas_id'] ?? 0);
            if ($kelasId > 0) {
                $query->whereHas('kelas', function ($kelasQuery) use ($kelasId) {
                    $kelasQuery->where('kelas.id', $kelasId)
                        ->where('kelas_siswa.status', 'aktif');
                });
            }
        }

        if ($mode === 'user') {
            $userId = (int) ($audience['user_id'] ?? 0);
            if ($userId > 0) {
                $query->where('id', $userId);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($mode === 'manual') {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $audience
     * @return array<int, int>
     */
    private function resolveNotificationTargetIds(array $audience): array
    {
        return $this->buildActiveUserQuery($audience)
            ->pluck('id')
            ->map(static fn($id): int => (int) $id)
            ->all();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildNotificationData(BroadcastCampaign $campaign, array $payload): array
    {
        $base = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $base['broadcast_campaign_id'] = $campaign->id;
        $base['message_category'] = (string) ($payload['message_category'] ?? $campaign->message_category ?? 'announcement');

        if (!isset($base['presentation']) || !is_array($base['presentation'])) {
            $channels = is_array($payload['channels'] ?? null) ? $payload['channels'] : [];
            $base['presentation'] = [
                'in_app' => (bool) ($channels['in_app'] ?? false),
                'popup' => (bool) ($channels['popup'] ?? false),
            ];
        }

        if (is_array($payload['popup'] ?? null) && $payload['popup'] !== []) {
            $base['popup'] = $payload['popup'];
        }

        $base['lifecycle'] = array_filter([
            'display_start_at' => $campaign->display_start_at?->toIso8601String(),
            'display_end_at' => $campaign->display_end_at?->toIso8601String(),
            'expires_at' => $campaign->expires_at?->toIso8601String(),
            'pinned_at' => $campaign->pinned_at?->toIso8601String(),
            'priority' => (int) $campaign->priority,
        ], static fn ($value): bool => $value !== null && $value !== '');

        return $base;
    }

    /**
     * @param array<string, mixed> $audience
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveWhatsappTargets(array $audience): Collection
    {
        $mode = (string) ($audience['mode'] ?? 'all');
        if ($mode === 'manual') {
            return collect((array) ($audience['manual_recipients'] ?? []))
                ->map(fn($number) => WhatsappGateway::normalizePhoneNumber((string) $number))
                ->filter(fn($number) => $number !== '')
                ->unique()
                ->values()
                ->map(fn($number) => [
                    'user_id' => null,
                    'phone_number' => $number,
                ]);
        }

        return $this->buildActiveUserQuery($audience)
            ->with([
                'roles:id,name',
                'dataPribadiSiswa:id,user_id,no_hp_siswa,no_hp_ortu,no_hp_ayah,no_hp_ibu,no_hp_wali',
                'dataKepegawaian:id,user_id,no_hp,no_telepon_kantor',
            ])
            ->get()
            ->map(function (User $user) {
                $phoneNumber = $this->resolveUserPhoneNumber($user);
                if ($phoneNumber === null) {
                    return null;
                }

                return [
                    'user_id' => $user->id,
                    'phone_number' => $phoneNumber,
                ];
            })
            ->filter()
            ->unique('phone_number')
            ->values();
    }

    private function resolveUserPhoneNumber(User $user): ?string
    {
        $candidates = [
            $user->dataPribadiSiswa?->no_hp_siswa,
            $user->dataPribadiSiswa?->no_hp_ortu,
            $user->dataPribadiSiswa?->no_hp_ayah,
            $user->dataPribadiSiswa?->no_hp_ibu,
            $user->dataPribadiSiswa?->no_hp_wali,
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

    /**
     * @param array<string, mixed> $audience
     * @return Collection<int, array<string, mixed>>
     */
    private function resolveEmailTargets(array $audience): Collection
    {
        $mode = (string) ($audience['mode'] ?? 'all');
        if ($mode === 'manual') {
            return collect();
        }

        return $this->buildActiveUserQuery($audience)
            ->get()
            ->map(function (User $user) {
                $email = $this->resolveUserEmail($user);
                if ($email === null) {
                    return null;
                }

                return [
                    'user_id' => $user->id,
                    'email' => $email,
                    'name' => $user->nama_lengkap ?: $user->username ?: ('User #' . $user->id),
                ];
            })
            ->filter()
            ->unique('email')
            ->values();
    }

    private function resolveUserEmail(User $user): ?string
    {
        $email = trim((string) ($user->email ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return strtolower($email);
    }

    /**
     * @param array<string, mixed> $summary
     * @param array<string, mixed> $result
     */
    private function mergePushSummary(array &$summary, array $result): void
    {
        $summary['mode'] = (string) ($result['mode'] ?? $summary['mode']);
        $summary['configured'] = (bool) ($result['configured'] ?? $summary['configured']);
        $summary['sent'] += (int) (($result['sent'] ?? false) === true);
        $summary['skipped'] += (int) (($result['sent'] ?? false) !== true);
        $summary['attempted_tokens'] += (int) ($result['attempted_tokens'] ?? 0);
        $summary['success_count'] += (int) ($result['success_count'] ?? 0);
        $summary['failure_count'] += (int) ($result['failure_count'] ?? 0);

        $this->mergeCounterMap(
            $summary['success_by_device_type'],
            is_array($result['success_by_device_type'] ?? null) ? $result['success_by_device_type'] : []
        );
        $this->mergeCounterMap(
            $summary['failure_by_device_type'],
            is_array($result['failure_by_device_type'] ?? null) ? $result['failure_by_device_type'] : []
        );

        if (is_array($result['results'] ?? null) && $result['results'] !== []) {
            $summary['results'] = array_values(array_merge($summary['results'], $result['results']));
            if (count($summary['results']) > 50) {
                $summary['results'] = array_slice($summary['results'], -50);
            }
        }

        if (!empty($result['message'])) {
            $summary['message'] = (string) $result['message'];
        }
    }

    /**
     * @param array<string, int> $target
     * @param array<string, mixed> $incoming
     */
    private function mergeCounterMap(array &$target, array $incoming): void
    {
        foreach ($incoming as $key => $value) {
            $type = strtolower(trim((string) $key));
            if ($type === '') {
                $type = 'unknown';
            }

            if (!isset($target[$type])) {
                $target[$type] = 0;
            }

            $target[$type] += (int) $value;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function makePushSummary(): array
    {
        $summary = $this->pushNotificationService->getConfigurationSummary();

        return [
            'mode' => (string) ($summary['mode'] ?? 'in_app_only'),
            'configured' => (bool) ($summary['configured'] ?? false),
            'sent' => 0,
            'skipped' => 0,
            'attempted_tokens' => 0,
            'success_count' => 0,
            'failure_count' => 0,
            'success_by_device_type' => [],
            'failure_by_device_type' => [],
            'results' => [],
            'message' => (string) ($summary['message'] ?? 'Push real-time belum dikonfigurasi.'),
        ];
    }

    private function notificationChunkSize(): int
    {
        return max(1, (int) config('broadcast.notifications.chunk_size', 200));
    }

    private function whatsappChunkSize(): int
    {
        return max(1, (int) config('broadcast.whatsapp.chunk_size', 50));
    }

    private function emailChunkSize(): int
    {
        return max(1, (int) config('broadcast.email.chunk_size', 100));
    }

    /**
     * @param array<int, int> $values
     * @return array<int, array<int, int>>
     */
    private function chunkValues(array $values, int $chunkSize): array
    {
        if ($values === []) {
            return [];
        }

        return array_values(array_filter(array_chunk($values, $chunkSize)));
    }

    /**
     * @param Collection<int, array<string, mixed>> $targets
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function chunkWhatsappTargets(Collection $targets, int $chunkSize): array
    {
        if ($targets->isEmpty()) {
            return [];
        }

        return array_values(array_filter(
            $targets->chunk($chunkSize)->map(fn(Collection $chunk) => $chunk->values()->all())->all()
        ));
    }

    /**
     * @param Collection<int, array<string, mixed>> $targets
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function chunkEmailTargets(Collection $targets, int $chunkSize): array
    {
        if ($targets->isEmpty()) {
            return [];
        }

        return array_values(array_filter(
            $targets->chunk($chunkSize)->map(fn(Collection $chunk) => $chunk->values()->all())->all()
        ));
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, bool> $channels
     * @return array<string, mixed>
     */
    private function buildNotificationJobPayload(array $payload, array $channels): array
    {
        return [
            'title' => (string) ($payload['title'] ?? ''),
            'message' => (string) ($payload['message'] ?? ''),
            'type' => (string) ($payload['type'] ?? 'info'),
            'message_category' => (string) ($payload['message_category'] ?? 'announcement'),
            'channels' => $channels,
            'data' => is_array($payload['data'] ?? null) ? $payload['data'] : [],
            'popup' => is_array($payload['popup'] ?? null) ? $payload['popup'] : [],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildWhatsappJobPayload(array $payload): array
    {
        $whatsappOptions = is_array($payload['whatsapp'] ?? null) ? $payload['whatsapp'] : [];

        return [
            'message' => (string) ($payload['message'] ?? ''),
            'footer' => $whatsappOptions['footer'] ?? null,
            'reply_to_message_id' => $whatsappOptions['reply_to_message_id'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function buildEmailJobPayload(array $payload): array
    {
        $emailOptions = is_array($payload['email'] ?? null) ? $payload['email'] : [];
        $popupOptions = is_array($payload['popup'] ?? null) ? $payload['popup'] : [];

        return [
            'subject' => $emailOptions['subject'] ?? ($payload['title'] ?? 'Broadcast Message'),
            'title' => (string) ($payload['title'] ?? 'Broadcast Message'),
            'message' => (string) ($payload['message'] ?? ''),
            'type' => (string) ($payload['type'] ?? 'info'),
            'cta_label' => $popupOptions['cta_label'] ?? null,
            'cta_url' => $popupOptions['cta_url'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed>|null $pushSummary
     * @return array<string, mixed>
     */
    private function makeChannelRow(
        string $key,
        string $label,
        bool $selected,
        int $targetCount,
        int $pendingJobs,
        ?string $note = null,
        ?array $pushSummary = null,
        bool $skipped = false,
    ): array {
        $row = [
            'key' => $key,
            'channel' => $label,
            'selected' => $selected,
            'skipped' => $skipped,
            'target_count' => max(0, $targetCount),
            'sent' => 0,
            'failed' => 0,
            'skipped_count' => 0,
            'pending_jobs' => max(0, $pendingJobs),
            'note' => $note,
        ];

        if ($pushSummary !== null) {
            $row['push'] = $pushSummary;
        }

        return $row;
    }

    /**
     * @param array<int, array<string, mixed>> $channels
     */
    private function maxTargetCount(array $channels): int
    {
        $selectedRows = array_values(array_filter($channels, static fn(array $row): bool => (bool) ($row['selected'] ?? false)));
        if ($selectedRows === []) {
            return 0;
        }

        return max(array_map(static fn(array $row): int => (int) ($row['target_count'] ?? 0), $selectedRows));
    }

    /**
     * @param array<string, mixed> $extras
     */
    private function applyChannelChunkProgress(int $campaignId, string $channelKey, int $sentCount, int $failedCount, array $extras = []): void
    {
        DB::transaction(function () use ($campaignId, $channelKey, $sentCount, $failedCount, $extras) {
            $campaign = BroadcastCampaign::query()->lockForUpdate()->find($campaignId);
            if (!$campaign instanceof BroadcastCampaign) {
                return;
            }

            $summary = is_array($campaign->summary) ? $campaign->summary : [];
            $channels = is_array($summary['channels'] ?? null) ? $summary['channels'] : [];
            $channelFound = false;

            foreach ($channels as &$row) {
                if (($row['key'] ?? null) !== $channelKey) {
                    continue;
                }

                $channelFound = true;
                $pendingJobs = (int) ($row['pending_jobs'] ?? 0);
                if ($pendingJobs <= 0) {
                    return;
                }

                $row['sent'] = max(0, (int) ($row['sent'] ?? 0) + $sentCount);
                $row['failed'] = max(0, (int) ($row['failed'] ?? 0) + $failedCount);
                $row['skipped_count'] = max(0, (int) ($row['skipped_count'] ?? 0) + (int) ($extras['skipped'] ?? 0));
                $row['pending_jobs'] = max(0, $pendingJobs - 1);

                if (!empty($extras['note'])) {
                    $row['note'] = (string) $extras['note'];
                }

                if (is_array($extras['failed_samples'] ?? null) && $extras['failed_samples'] !== []) {
                    $existing = is_array($row['failed_samples'] ?? null) ? $row['failed_samples'] : [];
                    $row['failed_samples'] = array_slice(array_values(array_merge($existing, $extras['failed_samples'])), -20);
                }

                if (is_array($extras['push'] ?? null)) {
                    $row['push'] = is_array($row['push'] ?? null) ? $row['push'] : $this->makePushSummary();
                    $this->mergePushSummary($row['push'], $extras['push']);
                }
            }
            unset($row);

            if (!$channelFound) {
                return;
            }

            $summary['channels'] = $channels;
            $campaign->summary = $summary;
            $campaign->sent_count = array_sum(array_map(static fn(array $row): int => (int) ($row['sent'] ?? 0), $channels));
            $campaign->failed_count = array_sum(array_map(static fn(array $row): int => (int) ($row['failed'] ?? 0), $channels));
            $campaign->total_target = $this->maxTargetCount($channels);

            $allFinished = true;
            foreach ($channels as $row) {
                $selected = (bool) ($row['selected'] ?? false);
                $pendingJobs = (int) ($row['pending_jobs'] ?? 0);
                if ($selected && $pendingJobs > 0) {
                    $allFinished = false;
                    break;
                }
            }

            if ($allFinished) {
                $this->finalizeCampaignLocked($campaign);
            }

            $campaign->save();
        });
    }

    private function finalizeCampaign(int $campaignId): void
    {
        DB::transaction(function () use ($campaignId) {
            $campaign = BroadcastCampaign::query()->lockForUpdate()->find($campaignId);
            if (!$campaign instanceof BroadcastCampaign) {
                return;
            }

            $this->finalizeCampaignLocked($campaign);
            $campaign->save();
        });
    }

    private function finalizeCampaignLocked(BroadcastCampaign $campaign): void
    {
        $summary = is_array($campaign->summary) ? $campaign->summary : [];
        $channels = is_array($summary['channels'] ?? null) ? $summary['channels'] : [];
        $sentCount = array_sum(array_map(static fn(array $row): int => (int) ($row['sent'] ?? 0), $channels));
        $failedCount = array_sum(array_map(static fn(array $row): int => (int) ($row['failed'] ?? 0), $channels));

        $status = 'skipped';
        if ($sentCount > 0 && $failedCount === 0) {
            $status = 'sent';
        } elseif ($sentCount > 0 && $failedCount > 0) {
            $status = 'partial';
        } elseif ($sentCount === 0 && $failedCount > 0) {
            $status = 'failed';
        }

        $campaign->status = $status;
        $campaign->sent_count = $sentCount;
        $campaign->failed_count = $failedCount;
        $campaign->total_target = $this->maxTargetCount($channels);
        $campaign->sent_at = now();

        $disciplineCaseId = (int) (($summary['meta']['discipline_case_id'] ?? 0));
        if ($disciplineCaseId > 0 && $this->channelSentCount($channels, self::CHANNEL_WHATSAPP) > 0) {
            AttendanceDisciplineCase::query()
                ->where('id', $disciplineCaseId)
                ->update([
                    'status' => AttendanceDisciplineCase::STATUS_PARENT_BROADCAST_SENT,
                    'broadcast_campaign_id' => (int) $campaign->id,
                ]);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $channels
     */
    private function channelSentCount(array $channels, string $channelKey): int
    {
        foreach ($channels as $row) {
            if (($row['key'] ?? null) === $channelKey) {
                return (int) ($row['sent'] ?? 0);
            }
        }

        return 0;
    }
}
