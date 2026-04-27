<?php

namespace App\Services;

use App\Models\WhatsappGateway;
use App\Models\WhatsappWebhookEvent;
use Illuminate\Support\Facades\Log;

class WhatsappWebhookService
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function process(array $payload, array $headers = []): array
    {
        $messageId = $this->extractMessageId($payload);
        $eventType = $this->resolveEventType($payload);
        $status = $this->resolveStatus($payload);
        $sanitizedPayload = $this->sanitizeValue($payload);
        $sanitizedHeaders = $this->sanitizeValue($headers);
        $matchedNotification = null;
        $deliveryMarked = false;

        if ($messageId !== null) {
            $matchedNotification = WhatsappGateway::query()
                ->where('gateway_message_id', $messageId)
                ->latest('id')
                ->first();
        }

        if ($matchedNotification instanceof WhatsappGateway && $this->shouldMarkDelivered($eventType, $status)) {
            $matchedNotification->markAsDelivered([
                'event_type' => $eventType,
                'status' => $status,
                'message_id' => $messageId,
            ]);
            $deliveryMarked = true;
        }

        $event = WhatsappWebhookEvent::create([
            'event_type' => $eventType,
            'status' => $status,
            'message_id' => $messageId,
            'device' => is_string($payload['device'] ?? null) ? trim((string) $payload['device']) : null,
            'from_number' => is_string($payload['from'] ?? null) ? trim((string) $payload['from']) : null,
            'matched_notification_id' => $matchedNotification?->id,
            'delivery_marked' => $deliveryMarked,
            'payload' => is_array($sanitizedPayload) ? $sanitizedPayload : ['value' => $sanitizedPayload],
            'headers' => is_array($sanitizedHeaders) ? $sanitizedHeaders : ['value' => $sanitizedHeaders],
        ]);

        Log::info('WhatsApp gateway webhook received', [
            'event_type' => $eventType,
            'status' => $status,
            'message_id' => $messageId,
            'matched_notification_id' => $matchedNotification?->id,
            'delivery_marked' => $deliveryMarked,
            'device' => $payload['device'] ?? null,
            'from' => $payload['from'] ?? null,
            'headers' => $headers,
        ]);

        return [
            'event_type' => $eventType,
            'status' => $status,
            'message_id' => $messageId,
            'webhook_event_id' => $event->id,
            'matched_notification_id' => $matchedNotification?->id,
            'delivery_marked' => $deliveryMarked,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractMessageId(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'msgid'),
            data_get($payload, 'message_id'),
            data_get($payload, 'messageId'),
            data_get($payload, 'id'),
            data_get($payload, 'key.id'),
            data_get($payload, 'data.key.id'),
            data_get($payload, 'data.id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveEventType(array $payload): string
    {
        $candidates = [
            data_get($payload, 'event'),
            data_get($payload, 'type'),
            data_get($payload, 'event_type'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtolower(trim($candidate));
            }
        }

        return 'incoming_message';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveStatus(array $payload): ?string
    {
        $candidates = [
            data_get($payload, 'status'),
            data_get($payload, 'message_status'),
            data_get($payload, 'delivery_status'),
            data_get($payload, 'data.status'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return strtolower(trim($candidate));
            }
        }

        return null;
    }

    private function shouldMarkDelivered(string $eventType, ?string $status): bool
    {
        if ($status !== null && in_array($status, ['delivered', 'read'], true)) {
            return true;
        }

        return in_array($eventType, [
            'delivered',
            'message.delivered',
            'delivery',
            'delivery_receipt',
            'message.read',
            'read',
        ], true);
    }

    private function sanitizeValue(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 6) {
            return '[max-depth-reached]';
        }

        if (is_string($value)) {
            $maxLength = max(50, (int) config('whatsapp.webhook_events.max_string_length', 500));

            return mb_strlen($value) > $maxLength
                ? mb_substr($value, 0, $maxLength) . '...[truncated]'
                : $value;
        }

        if (!is_array($value)) {
            return $value;
        }

        if (($value['type'] ?? null) === 'Buffer' && isset($value['data']) && is_array($value['data'])) {
            return [
                'type' => 'Buffer',
                'byte_count' => count($value['data']),
                'omitted' => true,
            ];
        }

        $maxItems = max(10, (int) config('whatsapp.webhook_events.max_items', 50));
        $sanitized = [];
        $count = 0;

        foreach ($value as $key => $item) {
            if ($count >= $maxItems) {
                $sanitized['__truncated_items'] = count($value) - $maxItems;
                break;
            }

            if ($key === 'stream' && is_array($item)) {
                $sanitized[$key] = '[stream-omitted]';
            } else {
                $sanitized[$key] = $this->sanitizeValue($item, $depth + 1);
            }

            $count++;
        }

        return $sanitized;
    }
}
