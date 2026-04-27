<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WhatsappGateway extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'whatsapp_notifications';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DELIVERED = 'delivered';

    public const TYPE_ABSENSI = 'absensi';
    public const TYPE_IZIN = 'izin';
    public const TYPE_PENGUMUMAN = 'pengumuman';
    public const TYPE_REMINDER = 'reminder';
    public const TYPE_LAPORAN = 'laporan';

    protected $fillable = [
        'phone_number',
        'message',
        'type',
        'status',
        'metadata',
        'scheduled_at',
        'sent_at',
        'delivered_at',
        'gateway_message_id',
        'error_message',
        'retry_count',
        'max_retries',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'retry_count' => 'integer',
        'max_retries' => 'integer',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', self::STATUS_DELIVERED);
    }

    public function markAsSent(?array $gatewayResponse = null): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $gatewayMessageId = null;
        if ($gatewayResponse !== null) {
            $metadata['gateway_response'] = $gatewayResponse;
            $gatewayMessageId = self::extractGatewayMessageId($gatewayResponse);
            if ($gatewayMessageId !== null) {
                $metadata['gateway_message_id'] = $gatewayMessageId;
            }
        }

        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'gateway_message_id' => $gatewayMessageId ?: $this->gateway_message_id,
            'error_message' => null,
            'metadata' => $metadata,
        ]);
    }

    public function markAsFailed(string $errorMessage, ?array $gatewayResponse = null): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $gatewayMessageId = null;
        if ($gatewayResponse !== null) {
            $metadata['gateway_response'] = $gatewayResponse;
            $gatewayMessageId = self::extractGatewayMessageId($gatewayResponse);
            if ($gatewayMessageId !== null) {
                $metadata['gateway_message_id'] = $gatewayMessageId;
            }
        }

        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'retry_count' => (int) $this->retry_count + 1,
            'gateway_message_id' => $gatewayMessageId ?: $this->gateway_message_id,
            'metadata' => $metadata,
        ]);
    }

    public function markAsPendingVerification(string $message, ?array $gatewayResponse = null): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $metadata['delivery_verification'] = [
            'status' => 'pending',
            'reason' => $message,
            'recorded_at' => now()->toISOString(),
        ];

        $gatewayMessageId = null;
        if ($gatewayResponse !== null) {
            $metadata['gateway_response'] = $gatewayResponse;
            $gatewayMessageId = self::extractGatewayMessageId($gatewayResponse);
            if ($gatewayMessageId !== null) {
                $metadata['gateway_message_id'] = $gatewayMessageId;
            }
        }

        $this->update([
            'status' => self::STATUS_PENDING,
            'scheduled_at' => null,
            'gateway_message_id' => $gatewayMessageId ?: $this->gateway_message_id,
            'error_message' => $message,
            'metadata' => $metadata,
        ]);
    }

    public function markAsDelivered(array $deliveryInfo = []): void
    {
        $metadata = is_array($this->metadata) ? $this->metadata : [];
        $metadata['delivery'] = array_filter([
            'event_type' => $deliveryInfo['event_type'] ?? null,
            'status' => $deliveryInfo['status'] ?? null,
            'message_id' => $deliveryInfo['message_id'] ?? $this->gateway_message_id,
            'received_at' => now()->toISOString(),
        ], static fn($value) => $value !== null && $value !== '');

        $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
            'error_message' => null,
            'metadata' => $metadata,
        ]);
    }

    public function canRetry(): bool
    {
        return $this->status === self::STATUS_FAILED && (int) $this->retry_count < (int) $this->max_retries;
    }

    public static function normalizePhoneNumber(string $phoneNumber): string
    {
        return PhoneNumber::normalizeIndonesianWa($phoneNumber);
    }

    private static function extractGatewayMessageId(?array $gatewayResponse): ?string
    {
        if (!is_array($gatewayResponse)) {
            return null;
        }

        $candidates = [
            data_get($gatewayResponse, 'data.key.id'),
            data_get($gatewayResponse, 'key.id'),
            data_get($gatewayResponse, 'data.message_id'),
            data_get($gatewayResponse, 'message_id'),
            data_get($gatewayResponse, 'msgid'),
            data_get($gatewayResponse, 'id'),
            data_get($gatewayResponse, 'data.id'),
            data_get($gatewayResponse, 'data.message.key.id'),
            data_get($gatewayResponse, 'result.key.id'),
            data_get($gatewayResponse, 'response.key.id'),
            data_get($gatewayResponse, 'messages.0.key.id'),
            data_get($gatewayResponse, 'data.messages.0.key.id'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
