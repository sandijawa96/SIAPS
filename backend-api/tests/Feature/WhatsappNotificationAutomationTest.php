<?php

namespace Tests\Feature;

use App\Jobs\DispatchAttendanceWhatsappNotification;
use App\Models\Absensi;
use App\Models\DataPribadiSiswa;
use App\Models\Izin;
use App\Models\User;
use App\Models\WhatsappGateway;
use App\Models\WhatsappNotificationSkip;
use App\Services\WhatsappGatewayClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WhatsappNotificationAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_notifications_use_dedicated_queue_name(): void
    {
        $job = new DispatchAttendanceWhatsappNotification(123, 'checkin');

        $this->assertSame(DispatchAttendanceWhatsappNotification::QUEUE_NAME, $job->queue);
        $this->assertSame('attendance-whatsapp', $job->queue);
    }

    public function test_absensi_observer_sends_checkin_and_checkout_notifications(): void
    {
        $this->bindGatewaySendMock(2, true);

        $user = User::factory()->create();
        DataPribadiSiswa::create([
            'user_id' => $user->id,
            'no_hp_siswa' => '081234567890',
            'no_hp_ortu' => '081355501111',
        ]);

        $absensi = Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-02-27',
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
            'metode_absensi' => 'selfie',
        ]);

        $absensi->update([
            'jam_pulang' => '14:40:00',
        ]);

        $notifications = WhatsappGateway::query()->orderBy('id')->get();
        $this->assertCount(2, $notifications);
        $this->assertSame(WhatsappGateway::STATUS_SENT, $notifications[0]->status);
        $this->assertSame(WhatsappGateway::STATUS_SENT, $notifications[1]->status);
        $this->assertSame(WhatsappGateway::TYPE_ABSENSI, $notifications[0]->type);
        $this->assertSame(WhatsappGateway::TYPE_ABSENSI, $notifications[1]->type);
        $this->assertSame('attendance_checkin', $notifications[0]->metadata['source'] ?? null);
        $this->assertSame('attendance_checkout', $notifications[1]->metadata['source'] ?? null);
        $this->assertSame('6281355501111', $notifications[0]->phone_number);
        $this->assertSame('parent_only', $notifications[0]->metadata['recipient_scope'] ?? null);
        $this->assertStringContainsString('Konfirmasi Absensi Masuk', (string) $notifications[0]->message);
        $this->assertStringContainsString('Ref: *ABS-', (string) $notifications[0]->message);
        $this->assertStringContainsString('Konfirmasi Absensi Pulang', (string) $notifications[1]->message);
        $this->assertStringContainsString('Masuk *07:05*', (string) $notifications[1]->message);
        $this->assertStringContainsString('Ref: *ABS-', (string) $notifications[1]->message);
    }

    public function test_manual_correction_does_not_send_attendance_checkout_notification(): void
    {
        $this->bindGatewaySendMock(1, true);

        $user = User::factory()->create();
        DataPribadiSiswa::create([
            'user_id' => $user->id,
            'no_hp_siswa' => '081234567890',
            'no_hp_ortu' => '081355501111',
        ]);

        $absensi = Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-02-27',
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
            'metode_absensi' => 'selfie',
            'is_manual' => false,
        ]);

        $absensi->update([
            'jam_pulang' => '14:40:00',
            'is_manual' => true,
            'metode_absensi' => 'manual',
        ]);

        $notifications = WhatsappGateway::query()->orderBy('id')->get();
        $this->assertCount(1, $notifications);
        $this->assertSame('attendance_checkin', $notifications[0]->metadata['source'] ?? null);
    }

    public function test_manual_attendance_job_can_send_checkin_notification_when_explicitly_allowed(): void
    {
        $this->bindGatewaySendMock(1, true);

        $user = User::factory()->create();
        DataPribadiSiswa::create([
            'user_id' => $user->id,
            'no_hp_siswa' => '081234567890',
            'no_hp_ortu' => '081355501111',
        ]);

        $absensi = Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-02-27',
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $job = new DispatchAttendanceWhatsappNotification($absensi->id, 'checkin', true);
        $job->handle(app(\App\Services\WhatsappNotificationService::class));

        $notifications = WhatsappGateway::query()->orderBy('id')->get();
        $this->assertCount(1, $notifications);
        $this->assertSame('attendance_checkin', $notifications[0]->metadata['source'] ?? null);
        $this->assertTrue((bool) ($notifications[0]->metadata['is_manual'] ?? false));
        $this->assertStringContainsString('dicatat manual oleh petugas', (string) $notifications[0]->message);
    }

    public function test_izin_observer_sends_submitted_and_decision_notifications(): void
    {
        $this->bindGatewaySendMock(2, true);

        $user = User::factory()->create();
        $approver = User::factory()->create();

        DataPribadiSiswa::create([
            'user_id' => $user->id,
            'no_hp_siswa' => '081355500011',
            'no_hp_ortu' => '081355500099',
        ]);

        $izin = Izin::create([
            'user_id' => $user->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => Carbon::today()->addDay()->format('Y-m-d'),
            'tanggal_selesai' => Carbon::today()->addDay()->format('Y-m-d'),
            'alasan' => 'Keperluan keluarga',
            'status' => 'pending',
        ]);

        $izin->update([
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        $notifications = WhatsappGateway::query()->orderBy('id')->get();
        $this->assertCount(2, $notifications);
        $this->assertSame(WhatsappGateway::TYPE_IZIN, $notifications[0]->type);
        $this->assertSame(WhatsappGateway::TYPE_IZIN, $notifications[1]->type);
        $this->assertSame('izin_submitted', $notifications[0]->metadata['source'] ?? null);
        $this->assertSame('izin_decision', $notifications[1]->metadata['source'] ?? null);
        $this->assertSame(WhatsappGateway::STATUS_SENT, $notifications[0]->status);
        $this->assertSame(WhatsappGateway::STATUS_SENT, $notifications[1]->status);
        $this->assertSame('6281355500099', $notifications[0]->phone_number);
        $this->assertStringContainsString('Pengajuan Izin Diterima', (string) $notifications[0]->message);
        $this->assertStringContainsString('Hasil Pengajuan Izin', (string) $notifications[1]->message);
        $this->assertStringContainsString('Jenis: *Izin Pribadi*', (string) $notifications[0]->message);
        $this->assertStringContainsString('Status: *Menunggu review*', (string) $notifications[0]->message);
        $this->assertStringContainsString('Keputusan: *Disetujui*', (string) $notifications[1]->message);
        $this->assertStringContainsString('Ref: *IZN-', (string) $notifications[0]->message);
        $this->assertStringContainsString('Ref: *IZN-', (string) $notifications[1]->message);
    }

    public function test_missing_parent_phone_creates_skip_observability_record_without_failed_notification(): void
    {
        $user = User::factory()->create();
        DataPribadiSiswa::create([
            'user_id' => $user->id,
            'no_hp_siswa' => '081234567890',
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-02-27',
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
            'metode_absensi' => 'selfie',
        ]);

        $this->assertDatabaseCount('whatsapp_notifications', 0);
        $this->assertDatabaseHas('whatsapp_notification_skips', [
            'type' => WhatsappGateway::TYPE_ABSENSI,
            'reason' => WhatsappNotificationSkip::REASON_MISSING_PHONE,
            'target_user_id' => $user->id,
        ]);
    }

    public function test_disabled_gateway_creates_skip_record_instead_of_failed_notification(): void
    {
        $mock = Mockery::mock(WhatsappGatewayClient::class);
        $mock->shouldReceive('sendMessage')
            ->once()
            ->andReturn([
                'ok' => false,
                'reason' => 'notifications_disabled',
                'message' => 'Notifikasi WhatsApp sedang nonaktif.',
                'http_status' => null,
                'gateway_response' => null,
            ]);
        $this->app->instance(WhatsappGatewayClient::class, $mock);

        $user = User::factory()->create();
        DataPribadiSiswa::create([
            'user_id' => $user->id,
            'no_hp_siswa' => '081234567890',
            'no_hp_ortu' => '081355501111',
        ]);

        Absensi::create([
            'user_id' => $user->id,
            'tanggal' => '2026-02-27',
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
            'metode_absensi' => 'selfie',
        ]);

        $this->assertDatabaseCount('whatsapp_notifications', 0);
        $this->assertDatabaseHas('whatsapp_notification_skips', [
            'type' => WhatsappGateway::TYPE_ABSENSI,
            'reason' => WhatsappNotificationSkip::REASON_NOTIFICATIONS_DISABLED,
            'target_user_id' => $user->id,
            'phone_candidate' => '6281355501111',
        ]);
    }

    private function bindGatewaySendMock(int $times, bool $ok): void
    {
        $mock = Mockery::mock(WhatsappGatewayClient::class);
        $mock->shouldReceive('sendMessage')
            ->times($times)
            ->andReturn([
                'ok' => $ok,
                'message' => $ok ? 'OK' : 'ERROR',
                'http_status' => $ok ? 200 : 502,
                'gateway_response' => ['status' => $ok],
            ]);

        $this->app->instance(WhatsappGatewayClient::class, $mock);
    }
}
