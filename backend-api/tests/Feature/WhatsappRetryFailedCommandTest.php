<?php

namespace Tests\Feature;

use App\Models\WhatsappGateway;
use App\Services\WhatsappGatewayClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class WhatsappRetryFailedCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_retries_failed_notifications_and_marks_sent_on_success(): void
    {
        $mock = Mockery::mock(WhatsappGatewayClient::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->andReturn([
                'ok' => true,
                'message' => 'OK',
                'http_status' => 200,
                'gateway_response' => ['status' => true],
            ]);
        $this->app->instance(WhatsappGatewayClient::class, $mock);

        $retryable = WhatsappGateway::create([
            'phone_number' => '628111000111',
            'message' => 'Retry me',
            'type' => WhatsappGateway::TYPE_ABSENSI,
            'status' => WhatsappGateway::STATUS_FAILED,
            'metadata' => ['source' => 'test'],
            'retry_count' => 1,
            'max_retries' => 3,
            'error_message' => 'gateway down',
            'created_by' => null,
        ]);

        WhatsappGateway::create([
            'phone_number' => '628111000999',
            'message' => 'Do not retry',
            'type' => WhatsappGateway::TYPE_ABSENSI,
            'status' => WhatsappGateway::STATUS_FAILED,
            'metadata' => ['source' => 'test'],
            'retry_count' => 3,
            'max_retries' => 3,
            'error_message' => 'already max retry',
            'created_by' => null,
        ]);

        $this->artisan('whatsapp:retry-failed', [
            '--limit' => 10,
            '--cooldown-seconds' => 0,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('whatsapp_notifications', [
            'id' => $retryable->id,
            'status' => WhatsappGateway::STATUS_SENT,
        ]);

        $this->assertDatabaseHas('whatsapp_notifications', [
            'phone_number' => '628111000999',
            'status' => WhatsappGateway::STATUS_FAILED,
            'retry_count' => 3,
        ]);
    }

    public function test_command_keeps_failed_status_and_schedules_next_retry_when_gateway_still_fails(): void
    {
        $mock = Mockery::mock(WhatsappGatewayClient::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->andReturn([
                'ok' => false,
                'message' => 'Gateway down',
                'http_status' => 502,
                'gateway_response' => ['status' => false],
            ]);
        $this->app->instance(WhatsappGatewayClient::class, $mock);

        $notification = WhatsappGateway::create([
            'phone_number' => '628222000111',
            'message' => 'Still fail',
            'type' => WhatsappGateway::TYPE_IZIN,
            'status' => WhatsappGateway::STATUS_FAILED,
            'metadata' => ['source' => 'test'],
            'retry_count' => 1,
            'max_retries' => 3,
            'error_message' => 'old error',
            'created_by' => null,
        ]);

        DB::table('whatsapp_notifications')
            ->where('id', $notification->id)
            ->update(['updated_at' => now()->subMinutes(10)]);

        $this->artisan('whatsapp:retry-failed', [
            '--limit' => 10,
            '--cooldown-seconds' => 60,
        ])->assertExitCode(0);

        $updated = WhatsappGateway::findOrFail($notification->id);
        $this->assertSame(WhatsappGateway::STATUS_FAILED, $updated->status);
        $this->assertSame(2, (int) $updated->retry_count);
        $this->assertNotNull($updated->scheduled_at);
        $this->assertGreaterThan(now(), $updated->scheduled_at);
    }
}
