<?php

namespace Tests\Feature;

use App\Mail\BroadcastCampaignMail;
use App\Models\AttendanceDisciplineCase;
use App\Models\BroadcastCampaign;
use App\Models\Notification;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class BroadcastCampaignIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('queue.default', 'sync');
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_create_in_app_popup_broadcast_campaign_for_active_role_targets(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $activeStudent = $this->createUserWithRole(RoleNames::SISWA);
        $inactiveStudent = $this->createUserWithRole(RoleNames::SISWA);
        $inactiveStudent->update(['is_active' => false]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/broadcast-campaigns', [
                'title' => 'Pengumuman penting',
                'message' => 'Harap cek informasi terbaru.',
                'type' => 'warning',
                'message_category' => 'announcement',
                'channels' => [
                    'in_app' => true,
                    'popup' => true,
                    'whatsapp' => false,
                    'email' => false,
                ],
                'audience' => [
                    'mode' => 'role',
                    'role' => $activeStudent->roles()->value('name'),
                ],
                'data' => [
                    'source' => 'broadcast_message_page',
                ],
                'popup' => [
                    'title' => 'Info sekolah',
                    'dismiss_label' => 'Tutup',
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Broadcast campaign berhasil diantrikan',
            ])
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.total_target', 1);

        $campaignId = (int) $response->json('data.id');
        $this->assertDatabaseHas('broadcast_campaigns', [
            'id' => $campaignId,
            'title' => 'Pengumuman penting',
            'message_category' => 'announcement',
            'status' => 'sent',
            'total_target' => 1,
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $activeStudent->id,
            'title' => 'Pengumuman penting',
            'type' => 'warning',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $inactiveStudent->id,
            'title' => 'Pengumuman penting',
            'type' => 'warning',
        ]);

        $notification = Notification::query()
            ->where('user_id', $activeStudent->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame($campaignId, (int) ($notification->data['broadcast_campaign_id'] ?? 0));
        $this->assertSame('announcement', (string) ($notification->data['message_category'] ?? ''));
        $this->assertTrue((bool) ($notification->data['presentation']['popup'] ?? false));
    }

    public function test_admin_can_create_manual_whatsapp_broadcast_campaign(): void
    {
        Http::fake([
            'https://wa.test/send-message' => Http::response([
                'status' => true,
                'msg' => 'Message sent successfully!',
            ], 200),
        ]);

        Cache::forever('settings.whatsapp.api_url', 'https://wa.test');
        Cache::forever('settings.whatsapp.api_key', 'test-key');
        Cache::forever('settings.whatsapp.device_id', '6281230000000');
        Cache::forever('settings.whatsapp.notification_enabled', true);

        $admin = $this->createUserWithRole(RoleNames::ADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/broadcast-campaigns', [
                'title' => 'Tes WA Manual',
                'message' => 'Pesan WhatsApp manual',
                'type' => 'info',
                'message_category' => 'system',
                'channels' => [
                    'in_app' => false,
                    'popup' => false,
                    'whatsapp' => true,
                    'email' => false,
                ],
                'audience' => [
                    'mode' => 'manual',
                    'manual_recipients' => [
                        '081234567890',
                        '+628111111111',
                    ],
                ],
                'whatsapp' => [
                    'footer' => 'Footer WA',
                ],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Broadcast campaign berhasil diantrikan',
            ])
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.total_target', 2)
            ->assertJsonPath('data.sent_count', 2)
            ->assertJsonPath('data.failed_count', 0);

        $campaignId = (int) $response->json('data.id');
        $this->assertDatabaseHas('broadcast_campaigns', [
            'id' => $campaignId,
            'message_category' => 'system',
            'status' => 'sent',
            'total_target' => 2,
            'sent_count' => 2,
        ]);

        $this->assertDatabaseHas('whatsapp_notifications', [
            'phone_number' => '6281234567890',
            'message' => 'Pesan WhatsApp manual',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('whatsapp_notifications', [
            'phone_number' => '628111111111',
            'message' => 'Pesan WhatsApp manual',
            'status' => 'sent',
        ]);

        $campaign = BroadcastCampaign::query()->find($campaignId);
        $this->assertNotNull($campaign);
        $this->assertSame('WhatsApp', $campaign->summary['channels'][0]['channel'] ?? null);
    }

    public function test_whatsapp_broadcast_is_marked_skipped_when_global_switch_is_off(): void
    {
        Cache::forever('settings.whatsapp.api_url', 'https://wa.test');
        Cache::forever('settings.whatsapp.api_key', 'test-key');
        Cache::forever('settings.whatsapp.device_id', '6281230000000');
        Cache::forever('settings.whatsapp.notification_enabled', false);

        $admin = $this->createUserWithRole(RoleNames::ADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/broadcast-campaigns', [
                'title' => 'Tes WA Nonaktif',
                'message' => 'Pesan tidak boleh terkirim',
                'type' => 'info',
                'message_category' => 'system',
                'channels' => [
                    'in_app' => false,
                    'popup' => false,
                    'whatsapp' => true,
                    'email' => false,
                ],
                'audience' => [
                    'mode' => 'manual',
                    'manual_recipients' => [
                        '081234567890',
                    ],
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'skipped')
            ->assertJsonPath('data.sent_count', 0)
            ->assertJsonPath('data.failed_count', 0)
            ->assertJsonPath('data.summary.channels.0.skipped', true);

        $this->assertDatabaseCount('whatsapp_notifications', 0);
    }

    public function test_admin_can_create_email_broadcast_campaign_for_active_role_targets(): void
    {
        Mail::fake();

        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $activeTeacher = $this->createUserWithRole(RoleNames::SISWA);
        $activeTeacher->update(['email' => 'siswa.aktif@example.test']);

        $inactiveTeacher = $this->createUserWithRole(RoleNames::SISWA);
        $inactiveTeacher->update([
            'email' => 'siswa.nonaktif@example.test',
            'is_active' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/broadcast-campaigns', [
                'title' => 'Surat pemberitahuan',
                'message' => 'Mohon cek agenda rapat terbaru.',
                'type' => 'info',
                'message_category' => 'system',
                'channels' => [
                    'in_app' => false,
                    'popup' => false,
                    'whatsapp' => false,
                    'email' => true,
                ],
                'audience' => [
                    'mode' => 'role',
                    'role' => $activeTeacher->roles()->value('name'),
                ],
                'email' => [
                    'subject' => 'Agenda rapat guru',
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.total_target', 1)
            ->assertJsonPath('data.sent_count', 1)
            ->assertJsonPath('data.failed_count', 0);

        Mail::assertSent(BroadcastCampaignMail::class, function (BroadcastCampaignMail $mail) use ($activeTeacher) {
            return $mail->hasTo('siswa.aktif@example.test')
                && (string) ($mail->payload['subject'] ?? '') === 'Agenda rapat guru';
        });

        Mail::assertNotSent(BroadcastCampaignMail::class, function (BroadcastCampaignMail $mail) use ($inactiveTeacher) {
            return $mail->hasTo((string) $inactiveTeacher->email);
        });

        $campaignId = (int) $response->json('data.id');
        $campaign = BroadcastCampaign::query()->find($campaignId);
        $this->assertNotNull($campaign);
        $this->assertSame('system', $campaign->message_category);
        $this->assertSame('Email', $campaign->summary['channels'][0]['channel'] ?? null);
    }

    public function test_admin_can_upload_flyer_image_for_broadcast_campaign(): void
    {
        Storage::fake('public');

        $admin = $this->createUserWithRole(RoleNames::ADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/broadcast-campaigns/upload-flyer', [
                'flyer' => UploadedFile::fake()->image('flyer-sekolah.png', 1200, 1600)->size(512),
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Flyer berhasil diupload',
            ])
            ->assertJsonPath('data.name', 'flyer-sekolah.png');

        $path = (string) $response->json('data.path');
        $this->assertNotSame('', $path);
        $this->assertStringStartsWith('broadcast/flyers/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_parent_broadcast_updates_attendance_discipline_case_status(): void
    {
        Http::fake([
            'https://wa.test/send-message' => Http::response([
                'status' => true,
                'msg' => 'Message sent successfully!',
            ], 200),
        ]);

        Cache::forever('settings.whatsapp.api_url', 'https://wa.test');
        Cache::forever('settings.whatsapp.api_key', 'test-key');
        Cache::forever('settings.whatsapp.device_id', '6281230000000');
        Cache::forever('settings.whatsapp.notification_enabled', true);

        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $student = $this->createUserWithRole(RoleNames::SISWA);

        \App\Models\DataPribadiSiswa::create([
            'user_id' => $student->id,
            'no_hp_ortu' => '081234567890',
        ]);

        $case = AttendanceDisciplineCase::create([
            'user_id' => $student->id,
            'rule_key' => 'semester_alpha_limit',
            'status' => AttendanceDisciplineCase::STATUS_READY_FOR_PARENT_BROADCAST,
            'semester' => 'genap',
            'tahun_ajaran_ref' => '2025/2026',
            'metric_value' => 4,
            'metric_limit' => 3,
            'first_triggered_at' => now()->subDay(),
            'last_triggered_at' => now(),
            'payload' => [
                'student_name' => $student->nama_lengkap ?: 'Siswa',
                'class_name' => 'X-A',
                'semester_label' => 'Genap',
                'reference' => 'DISC-CASE-1',
            ],
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/broadcast-campaigns', [
                'title' => 'Peringatan Disiplin',
                'message' => 'Mohon tindak lanjut kehadiran siswa.',
                'type' => 'warning',
                'message_category' => 'system',
                'channels' => [
                    'in_app' => false,
                    'popup' => false,
                    'whatsapp' => true,
                    'email' => false,
                ],
                'audience' => [
                    'mode' => 'user',
                    'user_id' => $student->id,
                ],
                'data' => [
                    'source' => 'broadcast_message_page',
                    'discipline_case_id' => $case->id,
                ],
                'whatsapp' => [
                    'footer' => 'Footer disiplin',
                ],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.sent_count', 1);

        $case->refresh();
        $this->assertSame(AttendanceDisciplineCase::STATUS_PARENT_BROADCAST_SENT, $case->status);
        $this->assertNotNull($case->broadcast_campaign_id);
    }

    public function test_admin_can_filter_broadcast_history_by_created_date(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);

        $oldCampaign = BroadcastCampaign::create([
            'title' => 'Campaign Lama',
            'message' => 'Pesan lama',
            'type' => 'info',
            'message_category' => 'announcement',
            'channels' => ['in_app' => true],
            'audience' => ['mode' => 'all'],
            'status' => 'sent',
            'total_target' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
            'summary' => ['channels' => []],
            'created_by' => $admin->id,
            'sent_at' => now()->subDays(10),
        ]);
        $oldCampaign->timestamps = false;
        $oldCampaign->forceFill([
            'created_at' => now()->subDays(10),
            'updated_at' => now()->subDays(10),
        ])->save();

        $newCampaign = BroadcastCampaign::create([
            'title' => 'Campaign Baru',
            'message' => 'Pesan baru',
            'type' => 'info',
            'message_category' => 'system',
            'channels' => ['whatsapp' => true],
            'audience' => ['mode' => 'all'],
            'status' => 'sent',
            'total_target' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
            'summary' => ['channels' => []],
            'created_by' => $admin->id,
            'sent_at' => now(),
        ]);
        $newCampaign->timestamps = false;
        $newCampaign->forceFill([
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/broadcast-campaigns?created_from=' . now()->subDay()->format('Y-m-d') . '&created_to=' . now()->format('Y-m-d'));

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.title', 'Campaign Baru');
    }

    private function createUserWithRole(string $canonicalRole): User
    {
        $role = Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->where('guard_name', 'web')
            ->first();

        $this->assertNotNull($role, "Role not found for {$canonicalRole}");

        $user = User::factory()->create();
        $user->assignRole($role->name);

        return $user;
    }
}
