<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\Notification;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    public function getConfigurationSummary(): array
    {
        $enabled = (bool) config('push.enabled');
        $provider = (string) config('push.provider');
        $projectId = (string) config('push.firebase.project_id');
        $serviceAccountPath = $this->resolveServiceAccountPath();

        if (!$enabled || $provider !== 'fcm') {
            return [
                'mode' => 'in_app_only',
                'configured' => false,
                'sent' => false,
                'message' => 'Push real-time belum dikonfigurasi. Notifikasi dikirim melalui inbox aplikasi.',
            ];
        }

        if ($projectId === '') {
            return [
                'mode' => 'in_app_only',
                'configured' => false,
                'sent' => false,
                'message' => 'Push gateway aktif tetapi FIREBASE_PROJECT_ID belum diisi.',
            ];
        }

        if ($serviceAccountPath === null) {
            return [
                'mode' => 'in_app_only',
                'configured' => false,
                'sent' => false,
                'message' => 'Push gateway aktif tetapi file service account FCM belum ditemukan.',
            ];
        }

        return [
            'mode' => 'push_and_in_app',
            'configured' => true,
            'sent' => false,
            'message' => 'Push gateway FCM HTTP v1 aktif.',
        ];
    }

    public function sendNotification(Notification $notification): array
    {
        $configuration = $this->getConfigurationSummary();

        if (!$configuration['configured']) {
            Log::info('Push notification skipped (gateway not configured)', [
                'notification_id' => $notification->id ?? null,
                'user_id' => $notification->user_id ?? null,
                'title' => $notification->title ?? null,
            ]);

            return $configuration;
        }

        $displayTargets = $this->resolveDisplayTargets($notification);

        $deviceTokens = DeviceToken::query()
            ->where('user_id', $notification->user_id)
            ->where('is_active', true)
            ->whereNotNull('push_token')
            ->get([
                'id',
                'user_id',
                'device_id',
                'device_name',
                'device_type',
                'push_token',
            ])
            ->filter(function (DeviceToken $token) {
                return is_string($token->push_token) && trim($token->push_token) !== '';
            })
            ->filter(function (DeviceToken $token) use ($displayTargets) {
                return $this->shouldDeliverToDeviceType($displayTargets, $token->device_type);
            })
            ->all();

        if ($deviceTokens === []) {
            return [
                'sent' => false,
                'mode' => $configuration['mode'],
                'configured' => true,
                'attempted_tokens' => 0,
                'success_count' => 0,
                'failure_count' => 0,
                'success_by_device_type' => [],
                'failure_by_device_type' => [],
                'results' => [],
                'message' => 'Push gateway aktif, tetapi tidak ada device token aktif yang sesuai target platform.',
            ];
        }

        $accessToken = $this->fetchFirebaseAccessToken();
        $endpoint = $this->resolveFirebaseSendEndpoint();

        if ($accessToken === null || $endpoint === null) {
            return [
                'sent' => false,
                'mode' => 'in_app_only',
                'configured' => false,
                'attempted_tokens' => count($deviceTokens),
                'success_count' => 0,
                'failure_count' => count($deviceTokens),
                'success_by_device_type' => [],
                'failure_by_device_type' => [],
                'results' => [],
                'message' => 'Push gateway aktif, tetapi kredensial FCM HTTP v1 tidak valid.',
            ];
        }

        $client = new Client([
            'timeout' => 10,
        ]);

        $successCount = 0;
        $failureCount = 0;
        $successByDeviceType = [];
        $failureByDeviceType = [];
        $results = [];

        foreach ($deviceTokens as $deviceToken) {
            $token = trim((string) $deviceToken->push_token);
            $deviceType = $this->normalizeDeviceType($deviceToken->device_type);
            $deviceName = $deviceToken->device_name ?: $deviceToken->device_id;

            try {
                $response = $client->post($endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $accessToken,
                        'Content-Type' => 'application/json; charset=UTF-8',
                    ],
                    'json' => $this->buildFcmPayload($notification, $token, $deviceType),
                ]);

                if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                    $successCount++;
                    $this->incrementCounter($successByDeviceType, $deviceType);

                    $responseData = json_decode((string) $response->getBody(), true);
                    $results[] = [
                        'device_token_id' => (int) $deviceToken->id,
                        'device_type' => $deviceType,
                        'device_name' => $deviceName,
                        'token_suffix' => $this->tokenSuffix($token),
                        'status' => 'success',
                        'fcm_message_name' => is_array($responseData) ? (string) ($responseData['name'] ?? '') : '',
                    ];
                } else {
                    $failureCount++;
                    $this->incrementCounter($failureByDeviceType, $deviceType);
                    $results[] = [
                        'device_token_id' => (int) $deviceToken->id,
                        'device_type' => $deviceType,
                        'device_name' => $deviceName,
                        'token_suffix' => $this->tokenSuffix($token),
                        'status' => 'failed',
                        'http_status' => $response->getStatusCode(),
                        'error_code' => null,
                        'error_message' => 'HTTP status non-2xx',
                    ];
                }
            } catch (RequestException $e) {
                $failureCount++;
                $this->incrementCounter($failureByDeviceType, $deviceType);

                $statusCode = $e->getResponse()?->getStatusCode();
                $responseBody = $e->getResponse()?->getBody()?->getContents();
                $errorCode = $this->extractFcmErrorCode($responseBody);
                $errorMessage = $this->extractFcmErrorMessage($responseBody) ?: $e->getMessage();
                $deactivated = false;

                if ($this->shouldDeactivateToken($errorCode)) {
                    try {
                        $deviceToken->forceFill(['is_active' => false])->save();
                        $deactivated = true;
                    } catch (\Throwable $deactivateError) {
                        Log::warning('Failed to deactivate invalid FCM token', [
                            'device_token_id' => $deviceToken->id,
                            'user_id' => $notification->user_id ?? null,
                            'error_code' => $errorCode,
                            'message' => $deactivateError->getMessage(),
                        ]);
                    }
                }

                $results[] = [
                    'device_token_id' => (int) $deviceToken->id,
                    'device_type' => $deviceType,
                    'device_name' => $deviceName,
                    'token_suffix' => $this->tokenSuffix($token),
                    'status' => 'failed',
                    'http_status' => $statusCode,
                    'error_code' => $errorCode,
                    'error_message' => $errorMessage,
                    'token_deactivated' => $deactivated,
                ];

                Log::error('FCM HTTP v1 push send failed for token', [
                    'notification_id' => $notification->id ?? null,
                    'user_id' => $notification->user_id ?? null,
                    'device_token_id' => $deviceToken->id,
                    'device_type' => $deviceType,
                    'http_status' => $statusCode,
                    'error_code' => $errorCode,
                    'message' => $errorMessage,
                    'endpoint' => $endpoint,
                    'device_token_suffix' => $this->tokenSuffix($token),
                ]);
            } catch (\Throwable $e) {
                $failureCount++;
                $this->incrementCounter($failureByDeviceType, $deviceType);

                $results[] = [
                    'device_token_id' => (int) $deviceToken->id,
                    'device_type' => $deviceType,
                    'device_name' => $deviceName,
                    'token_suffix' => $this->tokenSuffix($token),
                    'status' => 'failed',
                    'http_status' => null,
                    'error_code' => null,
                    'error_message' => $e->getMessage(),
                ];

                Log::error('FCM HTTP v1 push send failed for token', [
                    'notification_id' => $notification->id ?? null,
                    'user_id' => $notification->user_id ?? null,
                    'device_token_id' => $deviceToken->id,
                    'device_type' => $deviceType,
                    'message' => $e->getMessage(),
                    'endpoint' => $endpoint,
                    'device_token_suffix' => $this->tokenSuffix($token),
                ]);
            }
        }

        Log::info('FCM HTTP v1 push send result', [
            'notification_id' => $notification->id ?? null,
            'user_id' => $notification->user_id ?? null,
            'attempted_tokens' => count($deviceTokens),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'success_by_device_type' => $successByDeviceType,
            'failure_by_device_type' => $failureByDeviceType,
        ]);

        return [
            'sent' => $successCount > 0,
            'mode' => 'push_and_in_app',
            'configured' => true,
            'attempted_tokens' => count($deviceTokens),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'success_by_device_type' => $successByDeviceType,
            'failure_by_device_type' => $failureByDeviceType,
            'results' => $results,
            'message' => $successCount > 0
                ? 'Push berhasil dikirim ke sebagian/seluruh device aktif.'
                : 'Push gateway aktif, tetapi tidak ada device yang menerima notifikasi.',
        ];
    }

    /**
     * @return array{web: bool, mobile: bool}
     */
    private function resolveDisplayTargets(Notification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $presentation = is_array($data['presentation'] ?? null) ? $data['presentation'] : [];
        $targets = is_array($presentation['targets'] ?? null) ? $presentation['targets'] : [];

        return [
            'web' => array_key_exists('web', $targets) ? (bool) $targets['web'] : true,
            'mobile' => array_key_exists('mobile', $targets) ? (bool) $targets['mobile'] : true,
        ];
    }

    /**
     * @param array{web: bool, mobile: bool} $targets
     */
    private function shouldDeliverToDeviceType(array $targets, ?string $deviceType): bool
    {
        $normalizedDeviceType = $this->normalizeDeviceType($deviceType);

        if ($normalizedDeviceType === 'web') {
            return $targets['web'];
        }

        if (in_array($normalizedDeviceType, ['android', 'ios'], true)) {
            return $targets['mobile'];
        }

        return $targets['web'] && $targets['mobile'];
    }

    private function buildFcmPayload(Notification $notification, string $token, string $deviceType): array
    {
        $androidChannelId = trim((string) config('push.firebase.android_channel_id', 'siaps_notifications'));
        if ($androidChannelId === '') {
            $androidChannelId = 'siaps_notifications';
        }

        $normalizedDeviceType = $this->normalizeDeviceType($deviceType);
        $dataPayload = $this->normalizeFcmDataPayload($notification);

        $message = [
            'token' => $token,
            'notification' => [
                'title' => (string) $notification->title,
                'body' => (string) $notification->message,
            ],
            'data' => $dataPayload,
        ];

        if ($normalizedDeviceType === 'android') {
            $message['android'] = [
                'priority' => 'high',
                'ttl' => '120s',
                'notification' => [
                    'title' => (string) $notification->title,
                    'body' => (string) $notification->message,
                    'channel_id' => $androidChannelId,
                    'icon' => 'ic_notification',
                    'color' => '#64B5F6',
                    'sound' => 'default',
                    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                ],
            ];
        }

        return [
            'message' => array_merge($message, [
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                        ],
                    ],
                ],
                'webpush' => [
                    'headers' => [
                        'Urgency' => 'high',
                    ],
                ],
            ]),
        ];
    }

    private function resolveServiceAccountPath(): ?string
    {
        $configured = trim((string) config('push.firebase.service_account_path'));
        if ($configured === '') {
            return null;
        }

        if (is_file($configured)) {
            return $configured;
        }

        $basePath = base_path($configured);
        if (is_file($basePath)) {
            return $basePath;
        }

        return null;
    }

    private function resolveFirebaseSendEndpoint(): ?string
    {
        $projectId = trim((string) config('push.firebase.project_id'));
        if ($projectId === '') {
            return null;
        }

        $configured = trim((string) config('push.firebase.endpoint'));
        if ($configured !== '') {
            return $configured;
        }

        return "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
    }

    private function fetchFirebaseAccessToken(): ?string
    {
        $serviceAccountPath = $this->resolveServiceAccountPath();
        if ($serviceAccountPath === null) {
            return null;
        }

        try {
            $credentials = new ServiceAccountCredentials(
                ['https://www.googleapis.com/auth/firebase.messaging'],
                $serviceAccountPath
            );

            $authToken = $credentials->fetchAuthToken();
            $token = $authToken['access_token'] ?? null;

            return is_string($token) && $token !== '' ? $token : null;
        } catch (\Throwable $e) {
            Log::error('Failed to fetch FCM HTTP v1 access token', [
                'service_account_path' => $serviceAccountPath,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function normalizeFcmDataPayload(Notification $notification): array
    {
        $rawData = is_array($notification->data) ? $notification->data : [];
        $merged = array_merge($rawData, [
            'notification_id' => $notification->id,
            'type' => $notification->type,
            'title' => (string) $notification->title,
            'message' => (string) $notification->message,
        ]);

        $normalized = [];
        foreach ($merged as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$key] = (string) ($value ?? '');
                continue;
            }

            $normalized[$key] = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
        }

        return $normalized;
    }

    private function normalizeDeviceType(?string $deviceType): string
    {
        $normalized = strtolower(trim((string) $deviceType));

        return $normalized !== '' ? $normalized : 'unknown';
    }

    private function incrementCounter(array &$target, string $key): void
    {
        if (!isset($target[$key])) {
            $target[$key] = 0;
        }

        $target[$key]++;
    }

    private function tokenSuffix(string $token): string
    {
        return substr($token, -12);
    }

    private function extractFcmErrorCode(?string $responseBody): ?string
    {
        if (!is_string($responseBody) || trim($responseBody) === '') {
            return null;
        }

        $payload = json_decode($responseBody, true);
        if (!is_array($payload)) {
            return null;
        }

        $details = $payload['error']['details'] ?? null;
        if (is_array($details)) {
            foreach ($details as $detail) {
                if (!is_array($detail)) {
                    continue;
                }

                if (!empty($detail['errorCode']) && is_string($detail['errorCode'])) {
                    return strtoupper(trim($detail['errorCode']));
                }
            }
        }

        $status = $payload['error']['status'] ?? null;
        if (is_string($status) && trim($status) !== '') {
            return strtoupper(trim($status));
        }

        return null;
    }

    private function extractFcmErrorMessage(?string $responseBody): ?string
    {
        if (!is_string($responseBody) || trim($responseBody) === '') {
            return null;
        }

        $payload = json_decode($responseBody, true);
        if (!is_array($payload)) {
            return trim($responseBody);
        }

        $message = $payload['error']['message'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return $message;
        }

        return trim($responseBody);
    }

    private function shouldDeactivateToken(?string $errorCode): bool
    {
        if ($errorCode === null || $errorCode === '') {
            return false;
        }

        return in_array($errorCode, ['UNREGISTERED', 'INVALID_ARGUMENT', 'NOT_FOUND'], true);
    }
}
