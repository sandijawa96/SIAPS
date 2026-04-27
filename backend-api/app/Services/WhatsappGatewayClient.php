<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsappGatewayClient
{
    private const SETTINGS_NAMESPACE = 'whatsapp';

    public function __construct(
        private readonly RuntimeSettingStore $settingStore
    ) {
    }

    public function getRuntimeConfig(): array
    {
        $stored = $this->settingStore->all(self::SETTINGS_NAMESPACE);
        $legacyToPersist = [];

        $apiUrl = $this->resolveSetting(
            $stored,
            'api_url',
            'settings.whatsapp.api_url',
            config('whatsapp.api_url', ''),
            'string',
            $legacyToPersist
        );
        $apiKey = $this->resolveSetting(
            $stored,
            'api_key',
            'settings.whatsapp.api_key',
            config('whatsapp.api_key', ''),
            'string',
            $legacyToPersist
        );
        $sender = $this->resolveSetting(
            $stored,
            'device_id',
            'settings.whatsapp.device_id',
            config('whatsapp.sender', ''),
            'string',
            $legacyToPersist
        );
        $enabled = $this->resolveSetting(
            $stored,
            'notification_enabled',
            'settings.whatsapp.notification_enabled',
            true,
            'boolean',
            $legacyToPersist
        );
        $webhookSecret = $this->resolveSetting(
            $stored,
            'webhook_secret',
            'settings.whatsapp.webhook_secret',
            config('whatsapp.webhook_secret', ''),
            'string',
            $legacyToPersist
        );
        $autoReplyMessage = $this->resolveOptionalSetting(
            $stored,
            'auto_reply_message',
            'settings.whatsapp.auto_reply_message',
            'string',
            $legacyToPersist
        );

        if ($legacyToPersist !== []) {
            $types = [];
            foreach ($legacyToPersist as $key => $value) {
                $types[$key] = is_bool($value) ? 'boolean' : (is_array($value) ? 'json' : 'string');
            }

            $this->settingStore->putMany(self::SETTINGS_NAMESPACE, $legacyToPersist, $types);
        }

        return [
            'api_url' => trim((string) $apiUrl),
            'api_key' => trim((string) $apiKey),
            'sender' => trim((string) $sender),
            'notification_enabled' => $enabled,
            'webhook_secret' => trim((string) $webhookSecret),
            'auto_reply_message' => trim((string) $autoReplyMessage),
        ];
    }

    public function saveRuntimeConfig(array $data): array
    {
        $values = [
            'api_url' => (string) $data['api_url'],
            'api_key' => (string) $data['api_key'],
            'device_id' => (string) $data['device_id'],
            'notification_enabled' => (bool) ($data['notification_enabled'] ?? true),
        ];

        if (array_key_exists('webhook_secret', $data)) {
            $values['webhook_secret'] = (string) $data['webhook_secret'];
        }

        if (array_key_exists('auto_reply_message', $data)) {
            $values['auto_reply_message'] = (string) $data['auto_reply_message'];
        }

        $this->settingStore->putMany(self::SETTINGS_NAMESPACE, $values, [
            'api_url' => 'string',
            'api_key' => 'string',
            'device_id' => 'string',
            'notification_enabled' => 'boolean',
            'webhook_secret' => 'string',
            'auto_reply_message' => 'string',
        ]);

        // Tetap sinkronkan cache legacy selama masa transisi.
        Cache::forever('settings.whatsapp.api_url', $values['api_url']);
        Cache::forever('settings.whatsapp.api_key', $values['api_key']);
        Cache::forever('settings.whatsapp.device_id', $values['device_id']);
        Cache::forever('settings.whatsapp.notification_enabled', $values['notification_enabled']);
        if (array_key_exists('webhook_secret', $values)) {
            Cache::forever('settings.whatsapp.webhook_secret', $values['webhook_secret']);
        }

        if (array_key_exists('auto_reply_message', $values)) {
            Cache::forever('settings.whatsapp.auto_reply_message', $values['auto_reply_message']);
        }

        return $this->getRuntimeConfig();
    }

    public function sendMessage(string $phoneNumber, string $message, array $options = []): array
    {
        $config = $this->getRuntimeConfig();
        $availability = $this->resolveAvailability($config);
        if (!$availability['configured']) {
            return [
                'ok' => false,
                'reason' => 'missing_configuration',
                'message' => 'Konfigurasi WhatsApp belum lengkap (api_url/api_key/device_id).',
                'http_status' => null,
                'gateway_response' => null,
            ];
        }

        if (!$availability['notification_enabled']) {
            return [
                'ok' => false,
                'reason' => 'notifications_disabled',
                'message' => 'Notifikasi WhatsApp sedang nonaktif.',
                'http_status' => null,
                'gateway_response' => null,
            ];
        }

        $payload = [
            'api_key' => $config['api_key'],
            'sender' => $options['sender'] ?? $config['sender'],
            'number' => $phoneNumber,
            'message' => $message,
        ];

        if (!empty($options['footer'])) {
            $payload['footer'] = (string) $options['footer'];
        }

        if (!empty($options['msgid'])) {
            $payload['msgid'] = (string) $options['msgid'];
        }

        if (($options['full'] ?? true) === true) {
            $payload['full'] = 1;
        }

        return $this->request('post', '/send-message', $payload);
    }

    public function getDeviceInfo(?string $sender = null): array
    {
        $config = $this->getRuntimeConfig();
        if (!$this->isConfigured($config)) {
            return [
                'ok' => false,
                'connected' => false,
                'message' => 'Konfigurasi WhatsApp belum lengkap (api_url/api_key/device_id).',
                'http_status' => null,
                'gateway_response' => null,
            ];
        }

        $payload = [
            'api_key' => $config['api_key'],
            'number' => $sender ?: $config['sender'],
        ];

        $result = $this->request('get', '/info-devices', $payload);
        $connected = false;
        $device = null;

        if ($result['ok']) {
            $json = is_array($result['gateway_response']) ? $result['gateway_response'] : [];
            $device = data_get($json, 'info.0');
            if (!is_array($device)) {
                $device = data_get($json, 'data.0');
            }

            $stateCandidate = data_get($device, 'status');
            if (is_bool($stateCandidate)) {
                $connected = $stateCandidate;
            } elseif (is_string($stateCandidate)) {
                $normalized = strtolower(trim($stateCandidate));
                $connected = in_array($normalized, ['connected', 'open', 'online', 'ready', 'active'], true);
            } else {
                $fallbackCandidate = data_get($device, 'connection');
                if (is_string($fallbackCandidate)) {
                    $normalized = strtolower(trim($fallbackCandidate));
                    $connected = in_array($normalized, ['connected', 'open', 'online', 'ready', 'active'], true);
                } else {
                    $connected = false;
                }
            }
        }

        $result['connected'] = $connected;
        $result['device'] = $device;

        return $result;
    }

    public function checkNumber(string $phoneNumber, ?string $sender = null): array
    {
        $config = $this->getRuntimeConfig();
        if (!$this->isConfigured($config)) {
            return [
                'ok' => false,
                'message' => 'Konfigurasi WhatsApp belum lengkap (api_url/api_key/device_id).',
                'http_status' => null,
                'gateway_response' => null,
                'exists' => false,
                'jid' => null,
            ];
        }

        $result = $this->request('post', '/check-number', [
            'api_key' => $config['api_key'],
            'sender' => $sender ?: $config['sender'],
            'number' => $phoneNumber,
        ]);

        $json = is_array($result['gateway_response']) ? $result['gateway_response'] : [];
        $check = is_array(data_get($json, 'msg')) ? data_get($json, 'msg') : [];
        $result['exists'] = (bool) data_get($check, 'exists', false);
        $result['jid'] = data_get($check, 'jid');

        return $result;
    }

    public function generateQr(?string $device = null, bool $force = true): array
    {
        $config = $this->getRuntimeConfig();
        if (!$this->isConfigured($config)) {
            return [
                'ok' => false,
                'message' => 'Konfigurasi WhatsApp belum lengkap (api_url/api_key/device_id).',
                'http_status' => null,
                'gateway_response' => null,
                'qrcode' => null,
                'already_connected' => false,
            ];
        }

        $result = $this->request('post', '/generate-qr', [
            'api_key' => $config['api_key'],
            'device' => $device ?: $config['sender'],
            'force' => $force,
        ]);

        $json = is_array($result['gateway_response']) ? $result['gateway_response'] : [];
        $qrcode = data_get($json, 'qrcode');
        $message = strtolower(trim((string) data_get($json, 'msg', data_get($json, 'message', ''))));
        $alreadyConnected = str_contains($message, 'already connected');

        if (is_string($qrcode) && trim($qrcode) !== '') {
            $result['ok'] = true;
            $result['qrcode'] = $qrcode;
            $result['already_connected'] = false;
            $result['message'] = $result['message'] ?: 'QR code berhasil diambil.';

            return $result;
        }

        if ($alreadyConnected) {
            $result['ok'] = true;
            $result['qrcode'] = null;
            $result['already_connected'] = true;

            return $result;
        }

        $result['qrcode'] = null;
        $result['already_connected'] = false;

        return $result;
    }

    public function logoutDevice(?string $sender = null): array
    {
        $config = $this->getRuntimeConfig();
        if (!$this->isConfigured($config)) {
            return [
                'ok' => false,
                'message' => 'Konfigurasi WhatsApp belum lengkap (api_url/api_key/device_id).',
                'http_status' => null,
                'gateway_response' => null,
            ];
        }

        return $this->request('post', '/logout-device', [
            'api_key' => $config['api_key'],
            'sender' => $sender ?: $config['sender'],
        ]);
    }

    public function deleteDevice(?string $sender = null): array
    {
        $config = $this->getRuntimeConfig();
        if (!$this->isConfigured($config)) {
            return [
                'ok' => false,
                'message' => 'Konfigurasi WhatsApp belum lengkap (api_url/api_key/device_id).',
                'http_status' => null,
                'gateway_response' => null,
            ];
        }

        return $this->request('post', '/delete-device', [
            'api_key' => $config['api_key'],
            'sender' => $sender ?: $config['sender'],
        ]);
    }

    private function request(string $method, string $path, array $payload): array
    {
        $config = $this->getRuntimeConfig();
        $url = $this->buildUrl($config['api_url'], $path);
        if ($url === null) {
            return [
                'ok' => false,
                'reason' => 'invalid_api_url',
                'message' => 'api_url WhatsApp tidak valid.',
                'http_status' => null,
                'gateway_response' => null,
            ];
        }

        try {
            $timeout = (int) config('whatsapp.timeout', 20);
            $retryTimes = max(1, (int) config('whatsapp.retry_times', 2));
            $retrySleep = max(0, (int) config('whatsapp.retry_sleep_ms', 300));

            $request = Http::acceptJson()
                ->asJson()
                ->timeout($timeout)
                ->retry($retryTimes, $retrySleep, throw: false);

            $response = $method === 'get'
                ? $request->get($url, $payload)
                : $request->post($url, $payload);

            return $this->parseResponse($response);
        } catch (\Throwable $e) {
            $reason = $this->isLikelyTimeout($e)
                ? 'gateway_timeout'
                : 'gateway_exception';

            Log::warning('WhatsApp gateway request exception', [
                'method' => $method,
                'url' => $url,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);

            return [
                'ok' => false,
                'reason' => $reason,
                'pending_verification' => $reason === 'gateway_timeout',
                'message' => $reason === 'gateway_timeout'
                    ? 'Request ke gateway WhatsApp timeout. Status pengiriman belum pasti dan menunggu verifikasi.'
                    : 'Gagal terhubung ke gateway WhatsApp.',
                'http_status' => null,
                'gateway_response' => null,
            ];
        }
    }

    private function parseResponse(Response $response): array
    {
        $json = $response->json();
        $statusCode = $response->status();

        if (!$response->successful()) {
            return [
                'ok' => false,
                'reason' => 'gateway_error',
                'message' => $this->extractMessage($response, $json),
                'http_status' => $statusCode,
                'gateway_response' => is_array($json) ? $json : ['raw' => $response->body()],
            ];
        }

        if (is_array($json) && array_key_exists('status', $json) && $json['status'] === false) {
            if ($this->hasSendSuccessEvidence($json)) {
                return [
                    'ok' => true,
                    'reason' => 'gateway_false_status_with_send_evidence',
                    'message' => $this->extractMessage($response, $json, 'Pesan WhatsApp diterima gateway.'),
                    'http_status' => $statusCode,
                    'gateway_response' => $json,
                ];
            }

            return [
                'ok' => false,
                'reason' => 'gateway_error',
                'message' => $this->extractMessage($response, $json, 'Gateway mengembalikan status gagal.'),
                'http_status' => $statusCode,
                'gateway_response' => $json,
            ];
        }

        return [
            'ok' => true,
            'message' => $this->extractMessage($response, $json, 'Request WhatsApp berhasil.'),
            'http_status' => $statusCode,
            'gateway_response' => is_array($json) ? $json : ['raw' => $response->body()],
        ];
    }

    private function extractMessage(Response $response, mixed $json, string $default = 'Terjadi kesalahan pada gateway WhatsApp.'): string
    {
        if (is_array($json)) {
            $candidate = $json['msg'] ?? $json['message'] ?? null;
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }

            if (is_scalar($candidate)) {
                $candidate = trim((string) $candidate);
                if ($candidate !== '') {
                    return $candidate;
                }
            }
        }

        if ($response->failed()) {
            return "Gateway HTTP {$response->status()}";
        }

        return $default;
    }

    private function hasSendSuccessEvidence(array $json): bool
    {
        $messageIdCandidates = [
            data_get($json, 'data.key.id'),
            data_get($json, 'key.id'),
            data_get($json, 'data.message.key.id'),
            data_get($json, 'data.message_id'),
            data_get($json, 'message_id'),
            data_get($json, 'msgid'),
            data_get($json, 'id'),
            data_get($json, 'data.id'),
            data_get($json, 'result.key.id'),
            data_get($json, 'response.key.id'),
            data_get($json, 'messages.0.key.id'),
            data_get($json, 'data.messages.0.key.id'),
        ];

        foreach ($messageIdCandidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return true;
            }
        }

        $messageCandidates = [
            data_get($json, 'msg'),
            data_get($json, 'message'),
        ];

        foreach ($messageCandidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $normalized = strtolower(trim($candidate));
            if ($normalized === '') {
                continue;
            }

            foreach (['message sent', 'sent successfully', 'berhasil dikirim', 'pesan terkirim'] as $needle) {
                if (str_contains($normalized, $needle)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isLikelyTimeout(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'timed out')
            || str_contains($message, 'timeout')
            || str_contains($message, 'curl error 28');
    }

    private function buildUrl(string $baseUrl, string $path): ?string
    {
        $normalizedBase = rtrim(trim($baseUrl), '/');
        if ($normalizedBase === '') {
            return null;
        }

        return $normalizedBase . '/' . ltrim($path, '/');
    }

    private function isConfigured(array $config): bool
    {
        return $config['api_url'] !== '' && $config['api_key'] !== '' && $config['sender'] !== '';
    }

    public function getAvailability(): array
    {
        return $this->resolveAvailability($this->getRuntimeConfig());
    }

    private function resolveAvailability(array $config): array
    {
        $configured = $this->isConfigured($config);
        $notificationEnabled = (bool) ($config['notification_enabled'] ?? true);

        return [
            'configured' => $configured,
            'notification_enabled' => $notificationEnabled,
            'can_send' => $configured && $notificationEnabled,
            'reason' => !$configured
                ? 'missing_configuration'
                : (!$notificationEnabled ? 'notifications_disabled' : 'ready'),
        ];
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $legacyToPersist
     */
    private function resolveSetting(
        array $stored,
        string $key,
        string $legacyCacheKey,
        mixed $fallback,
        string $type,
        array &$legacyToPersist
    ): mixed {
        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        if (Cache::has($legacyCacheKey)) {
            $legacyValue = Cache::get($legacyCacheKey);
            $resolved = $type === 'boolean'
                ? (bool) $legacyValue
                : (string) $legacyValue;

            $legacyToPersist[$key] = $resolved;

            return $resolved;
        }

        return $fallback;
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $legacyToPersist
     */
    private function resolveOptionalSetting(
        array $stored,
        string $key,
        string $legacyCacheKey,
        string $type,
        array &$legacyToPersist
    ): mixed {
        if (array_key_exists($key, $stored)) {
            return $stored[$key];
        }

        if (!Cache::has($legacyCacheKey)) {
            return null;
        }

        $legacyValue = Cache::get($legacyCacheKey);
        $resolved = $type === 'boolean'
            ? (bool) $legacyValue
            : (string) $legacyValue;

        $legacyToPersist[$key] = $resolved;

        return $resolved;
    }
}
