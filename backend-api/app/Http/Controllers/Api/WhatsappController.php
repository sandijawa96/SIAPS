<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AuthHelper;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WhatsappGateway;
use App\Models\WhatsappNotificationSkip;
use App\Models\WhatsappWebhookEvent;
use App\Services\WhatsappGatewayClient;
use App\Services\WhatsappAutomationService;
use App\Services\WhatsappWebhookService;
use App\Support\RoleNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class WhatsappController extends Controller
{
    public function __construct(
        private readonly WhatsappGatewayClient $gatewayClient,
        private readonly WhatsappAutomationService $automationService,
        private readonly WhatsappWebhookService $webhookService,
    ) {
    }

    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nomor' => 'nullable|string',
            'phone_number' => 'nullable|string',
            'pesan' => 'nullable|string',
            'message' => 'nullable|string',
            'template_id' => 'nullable|string',
            'footer' => 'nullable|string',
            'msgid' => 'nullable|string|max:255',
            'reply_to_message_id' => 'nullable|string|max:255',
            'type' => 'nullable|in:absensi,izin,pengumuman,reminder,laporan',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rawPhone = (string) ($request->input('phone_number') ?: $request->input('nomor'));
        $message = (string) ($request->input('message') ?: $request->input('pesan'));
        if (trim($rawPhone) === '' || trim($message) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => [
                    'nomor' => ['Field nomor/phone_number wajib diisi'],
                    'pesan' => ['Field pesan/message wajib diisi'],
                ],
            ], 422);
        }

        $normalizedPhone = WhatsappGateway::normalizePhoneNumber($rawPhone);
        if ($normalizedPhone === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor tujuan tidak valid',
            ], 422);
        }

        $availability = $this->gatewayClient->getAvailability();
        if (!$availability['configured']) {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi WhatsApp belum lengkap. Lengkapi API URL, API Key, Device ID, dan Webhook Secret.',
            ], 422);
        }

        if (!$availability['notification_enabled']) {
            return response()->json([
                'success' => false,
                'message' => 'Notifikasi WhatsApp sedang nonaktif. Aktifkan switch global WA terlebih dahulu.',
            ], 422);
        }

        $record = WhatsappGateway::create([
            'phone_number' => $normalizedPhone,
            'message' => $message,
            'type' => $request->input('type', WhatsappGateway::TYPE_PENGUMUMAN),
            'status' => WhatsappGateway::STATUS_PENDING,
            'metadata' => [
                'source' => 'manual_send',
                'template_id' => $request->input('template_id'),
                'footer' => $request->input('footer'),
                'reply_to_message_id' => $request->input('reply_to_message_id', $request->input('msgid')),
                'requested_by' => AuthHelper::userId(),
            ],
            'retry_count' => 0,
            'max_retries' => 3,
            'created_by' => AuthHelper::userId(),
        ]);

        $result = $this->gatewayClient->sendMessage($normalizedPhone, $message, [
            'footer' => $request->input('footer'),
            'msgid' => $request->input('reply_to_message_id', $request->input('msgid')),
        ]);

        if ($result['ok']) {
            $record->markAsSent(is_array($result['gateway_response']) ? $result['gateway_response'] : null);

            return response()->json([
                'success' => true,
                'message' => 'Pesan WhatsApp berhasil dikirim',
                'data' => $record->fresh(),
                'gateway' => [
                    'message' => $result['message'],
                    'http_status' => $result['http_status'],
                ],
            ]);
        }

        if (($result['pending_verification'] ?? false) === true) {
            $record->markAsPendingVerification(
                (string) $result['message'],
                is_array($result['gateway_response']) ? $result['gateway_response'] : null
            );

            return response()->json([
                'success' => true,
                'message' => 'Pesan sudah dikirim ke gateway, tetapi status delivery belum pasti. SIAPS menunggu webhook/verifikasi lanjutan dan tidak akan menandai gagal otomatis.',
                'data' => $record->fresh(),
                'gateway' => [
                    'message' => $result['message'],
                    'http_status' => $result['http_status'],
                    'pending_verification' => true,
                ],
            ], 202);
        }

        $record->markAsFailed(
            (string) $result['message'],
            is_array($result['gateway_response']) ? $result['gateway_response'] : null
        );

        return response()->json([
            'success' => false,
            'message' => 'Gateway WhatsApp tidak merespons. Pesan ditandai gagal dan dapat dicoba ulang.',
            'error' => $result['message'],
            'data' => $record->fresh(),
            'gateway' => [
                'http_status' => $result['http_status'],
                'response' => $result['gateway_response'],
            ],
        ]);
    }

    public function broadcast(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Endpoint broadcast WhatsApp legacy sudah deprecated. Gunakan Broadcast Campaign agar pengiriman berjalan lewat queue, audit trail, dan retry terpusat.',
            'data' => [
                'replacement_endpoint' => '/api/broadcast-campaigns',
            ],
        ], 410);
    }

    public function status()
    {
        $config = $this->gatewayClient->getRuntimeConfig();
        $result = $this->gatewayClient->getDeviceInfo();

        return response()->json([
            'success' => true,
            'data' => [
                'configured' => $config['api_url'] !== '' && $config['api_key'] !== '' && $config['sender'] !== '',
                'has_api_key' => $config['api_key'] !== '',
                'connected' => (bool) ($result['connected'] ?? false),
                'notification_enabled' => (bool) ($config['notification_enabled'] ?? true),
                'delivery_tracking_ready' => trim((string) ($config['webhook_secret'] ?? '')) !== '',
                'api_url' => $config['api_url'],
                'device_id' => $config['sender'],
                'webhook_url' => url('/api/whatsapp/webhook'),
                'webhook_secret_configured' => trim((string) ($config['webhook_secret'] ?? '')) !== '',
                'gateway_message' => $result['message'] ?? null,
                'gateway_http_status' => $result['http_status'] ?? null,
                'gateway_device' => $result['device'] ?? null,
                'skip_summary' => [
                    'missing_phone_last_24h' => (int) WhatsappNotificationSkip::query()
                        ->where('reason', WhatsappNotificationSkip::REASON_MISSING_PHONE)
                        ->where('created_at', '>=', now()->subDay())
                        ->count(),
                    'disabled_last_24h' => (int) WhatsappNotificationSkip::query()
                        ->where('reason', WhatsappNotificationSkip::REASON_NOTIFICATIONS_DISABLED)
                        ->where('created_at', '>=', now()->subDay())
                        ->count(),
                    'misconfigured_last_24h' => (int) WhatsappNotificationSkip::query()
                        ->where('reason', WhatsappNotificationSkip::REASON_MISSING_CONFIGURATION)
                        ->where('created_at', '>=', now()->subDay())
                        ->count(),
                ],
            ],
        ]);
    }

    public function updateSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'api_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'device_id' => 'nullable|string',
            'webhook_secret' => 'nullable|string|max:255',
            'auto_reply_message' => 'nullable|string',
            'notification_enabled' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $current = $this->gatewayClient->getRuntimeConfig();

        $apiUrl = trim((string) ($request->input('api_url') ?? $current['api_url']));
        $apiKeyInput = trim((string) $request->input('api_key', ''));
        $apiKey = $apiKeyInput !== ''
            ? $apiKeyInput
            : trim((string) ($current['api_key'] ?? ''));
        $deviceIdRaw = (string) ($request->input('device_id') ?? $current['sender']);
        $deviceId = $this->normalizeDeviceIdentifier($deviceIdRaw);
        $webhookSecretInput = trim((string) $request->input('webhook_secret', ''));
        $webhookSecret = $webhookSecretInput !== ''
            ? $webhookSecretInput
            : trim((string) ($current['webhook_secret'] ?? ''));
        $notificationEnabled = $request->has('notification_enabled')
            ? (bool) $request->input('notification_enabled')
            : (bool) ($current['notification_enabled'] ?? true);

        if ($apiUrl === '' || $apiKey === '' || $deviceId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Konfigurasi tidak lengkap. api_url, api_key, dan device_id wajib tersedia.',
            ], 422);
        }

        if ($webhookSecret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Webhook secret wajib diisi agar SIAPS hanya menerima callback delivery yang valid dari gateway.',
            ], 422);
        }

        $saved = $this->gatewayClient->saveRuntimeConfig([
            'api_url' => $apiUrl,
            'api_key' => $apiKey,
            'device_id' => $deviceId,
            'webhook_secret' => $webhookSecret,
            'auto_reply_message' => $request->input('auto_reply_message'),
            'notification_enabled' => $notificationEnabled,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan WhatsApp berhasil diupdate',
            'data' => [
                'api_url' => $saved['api_url'],
                'api_key' => $saved['api_key'] === '' ? '' : '********',
                'device_id' => $saved['sender'],
                'notification_enabled' => (bool) $saved['notification_enabled'],
                'webhook_secret_configured' => trim((string) ($saved['webhook_secret'] ?? '')) !== '',
            ],
        ]);
    }

    public function checkNumber(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'nullable|string',
            'nomor' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rawPhone = (string) ($request->input('phone_number') ?: $request->input('nomor'));
        $normalizedPhone = WhatsappGateway::normalizePhoneNumber($rawPhone);
        if ($normalizedPhone === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor tujuan tidak valid',
            ], 422);
        }

        $result = $this->gatewayClient->checkNumber($normalizedPhone);

        return response()->json([
            'success' => $result['ok'],
            'message' => $result['message'],
            'data' => [
                'phone_number' => $normalizedPhone,
                'exists' => (bool) ($result['exists'] ?? false),
                'jid' => $result['jid'] ?? null,
            ],
            'gateway' => [
                'http_status' => $result['http_status'] ?? null,
                'response' => $result['gateway_response'] ?? null,
            ],
        ], $result['ok'] ? 200 : 422);
    }

    public function generateQr(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'force' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $result = $this->gatewayClient->generateQr(force: (bool) $request->boolean('force', true));

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? 'Permintaan QR gateway diproses.',
            'data' => [
                'qrcode' => $result['qrcode'] ?? null,
                'already_connected' => (bool) ($result['already_connected'] ?? false),
            ],
            'gateway' => [
                'http_status' => $result['http_status'] ?? null,
                'response' => $result['gateway_response'] ?? null,
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function logoutDevice(): JsonResponse
    {
        $result = $this->gatewayClient->logoutDevice();

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? 'Permintaan logout device diproses.',
            'gateway' => [
                'http_status' => $result['http_status'] ?? null,
                'response' => $result['gateway_response'] ?? null,
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function deleteDevice(): JsonResponse
    {
        $result = $this->gatewayClient->deleteDevice();

        return response()->json([
            'success' => (bool) ($result['ok'] ?? false),
            'message' => $result['message'] ?? 'Permintaan hapus device diproses.',
            'gateway' => [
                'http_status' => $result['http_status'] ?? null,
                'response' => $result['gateway_response'] ?? null,
            ],
        ], ($result['ok'] ?? false) ? 200 : 422);
    }

    public function webhookEvents(Request $request): JsonResponse
    {
        $limit = max(1, min(25, (int) $request->input('limit', 10)));

        $events = WhatsappWebhookEvent::query()
            ->with(['matchedNotification:id,phone_number,type,status,delivered_at,gateway_message_id'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (WhatsappWebhookEvent $event) {
                return [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'status' => $event->status,
                    'message_id' => $event->message_id,
                    'device' => $event->device,
                    'from_number' => $event->from_number,
                    'delivery_marked' => (bool) $event->delivery_marked,
                    'created_at' => optional($event->created_at)?->toISOString(),
                    'matched_notification' => $event->matchedNotification ? [
                        'id' => $event->matchedNotification->id,
                        'phone_number' => $event->matchedNotification->phone_number,
                        'type' => $event->matchedNotification->type,
                        'status' => $event->matchedNotification->status,
                        'delivered_at' => optional($event->matchedNotification->delivered_at)?->toISOString(),
                        'gateway_message_id' => $event->matchedNotification->gateway_message_id,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'events' => $events,
            ],
        ]);
    }

    public function skipEvents(Request $request): JsonResponse
    {
        $limit = max(1, min(25, (int) $request->input('limit', 10)));
        $windowStart = now()->subDay();

        $events = WhatsappNotificationSkip::query()
            ->with(['targetUser:id,nama_lengkap,username,email'])
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(function (WhatsappNotificationSkip $event) {
                return [
                    'id' => $event->id,
                    'type' => $event->type,
                    'reason' => $event->reason,
                    'phone_candidate' => $event->phone_candidate,
                    'created_at' => optional($event->created_at)?->toISOString(),
                    'target_user' => $event->targetUser ? [
                        'id' => $event->targetUser->id,
                        'name' => $event->targetUser->nama_lengkap ?: $event->targetUser->username ?: $event->targetUser->email,
                    ] : null,
                    'metadata' => is_array($event->metadata) ? $event->metadata : [],
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'missing_phone_last_24h' => (int) WhatsappNotificationSkip::query()
                        ->where('reason', WhatsappNotificationSkip::REASON_MISSING_PHONE)
                        ->where('created_at', '>=', $windowStart)
                        ->count(),
                    'disabled_last_24h' => (int) WhatsappNotificationSkip::query()
                        ->where('reason', WhatsappNotificationSkip::REASON_NOTIFICATIONS_DISABLED)
                        ->where('created_at', '>=', $windowStart)
                        ->count(),
                    'misconfigured_last_24h' => (int) WhatsappNotificationSkip::query()
                        ->where('reason', WhatsappNotificationSkip::REASON_MISSING_CONFIGURATION)
                        ->where('created_at', '>=', $windowStart)
                        ->count(),
                ],
                'events' => $events,
            ],
        ]);
    }

    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        if ($payload === [] && trim((string) $request->getContent()) !== '') {
            $decoded = json_decode((string) $request->getContent(), true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $config = $this->gatewayClient->getRuntimeConfig();
        $configuredSecret = trim((string) ($config['webhook_secret'] ?? ''));
        if ($configuredSecret === '') {
            return response()->json([
                'success' => false,
                'message' => 'Webhook secret belum dikonfigurasi. Simpan secret di halaman WhatsApp Gateway sebelum menerima callback.',
            ], 503);
        }

        $providedSecret = trim((string) (
            $request->header('X-Webhook-Secret')
            ?: $request->header('X-Whatsapp-Webhook-Secret')
            ?: preg_replace('/^Bearer\s+/i', '', (string) $request->header('Authorization', ''))
            ?: $request->input('webhook_secret')
            ?: $request->input('secret')
            ?: $request->input('token')
        ));

        if ($configuredSecret !== '' && !hash_equals($configuredSecret, $providedSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'Webhook secret tidak valid.',
            ], 401);
        }

        $summary = $this->webhookService->process($payload, [
            'user_agent' => $request->userAgent(),
            'x_forwarded_for' => $request->header('X-Forwarded-For'),
            'x_forwarded_proto' => $request->header('X-Forwarded-Proto'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook WhatsApp diterima.',
            'data' => $summary,
        ]);
    }

    public function automations()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'automations' => $this->automationService->allForApi(),
            ],
        ]);
    }

    public function updateAutomations(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'automations' => 'required|array|min:1',
            'automations.*.key' => 'required|string',
            'automations.*.enabled' => 'nullable|boolean',
            'automations.*.template' => 'nullable|string',
            'automations.*.footer' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data automation WhatsApp tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $automations = $this->automationService->update((array) $request->input('automations', []));

        return response()->json([
            'success' => true,
            'message' => 'Automation WhatsApp berhasil diperbarui',
            'data' => [
                'automations' => $automations,
            ],
        ]);
    }

    private function resolveBroadcastRecipients(Request $request): Collection
    {
        $explicitRecipients = collect($request->input('recipients', []))
            ->map(fn($item) => WhatsappGateway::normalizePhoneNumber((string) $item))
            ->filter(fn($number) => $number !== '')
            ->unique()
            ->values()
            ->map(fn($number) => ['user_id' => null, 'phone_number' => $number]);

        if ($explicitRecipients->isNotEmpty()) {
            return $explicitRecipients;
        }

        $query = User::query()->with([
            'roles:id,name',
            'dataPribadiSiswa:id,user_id,no_hp_siswa,no_hp_ortu,no_hp_ayah,no_hp_ibu,no_hp_wali',
            'dataKepegawaian:id,user_id,no_hp,no_telepon_kantor',
        ]);

        if ($request->filled('target_role')) {
            $roleAliases = RoleNames::aliasesFor((string) $request->input('target_role'));
            if (!empty($roleAliases)) {
                $query->whereHas('roles', function ($roleQuery) use ($roleAliases) {
                    $roleQuery->whereIn('name', $roleAliases);
                });
            }
        }

        if ($request->filled('target_kelas')) {
            $kelasId = (int) $request->input('target_kelas');
            $query->whereHas('kelas', function ($kelasQuery) use ($kelasId) {
                $kelasQuery->where('kelas.id', $kelasId);
            });
        }

        return $query->get()
            ->map(function (User $user) {
                $number = $this->resolveUserPhoneNumber($user);
                if ($number === null) {
                    return null;
                }

                return [
                    'user_id' => $user->id,
                    'phone_number' => $number,
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

    private function normalizeDeviceIdentifier(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (preg_match('/^[\d\+\-\s\(\)]+$/', $trimmed) === 1) {
            return WhatsappGateway::normalizePhoneNumber($trimmed);
        }

        return $trimmed;
    }
}
