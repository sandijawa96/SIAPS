<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotificationEndpointSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_admin_can_create_and_broadcast_notifications(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $recipient = $this->createUserWithRole(RoleNames::SISWA);
        $inactiveRecipient = $this->createUserWithRole(RoleNames::SISWA);
        $inactiveRecipient->update(['is_active' => false]);

        $storeResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/notifications', [
                'title' => 'Uji notifikasi',
                'message' => 'Pesan in-app',
                'type' => 'info',
                'user_ids' => [(int) $recipient->id],
                'data' => ['source' => 'smoke_test'],
            ]);

        $storeResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Notifikasi berhasil dibuat',
            ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'title' => 'Uji notifikasi',
            'message' => 'Pesan in-app',
            'type' => 'info',
        ]);

        $recipientRoleName = $recipient->roles()->value('name');
        $this->assertNotNull($recipientRoleName);

        $broadcastResponse = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/notifications/broadcast', [
                'title' => 'Broadcast notifikasi',
                'message' => 'Pesan broadcast',
                'type' => 'warning',
                'role' => $recipientRoleName,
                'data' => ['source' => 'smoke_broadcast'],
            ]);

        $broadcastResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Broadcast notifikasi berhasil',
            ])
            ->assertJsonPath('data.total_recipients', 1);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $recipient->id,
            'title' => 'Broadcast notifikasi',
            'type' => 'warning',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'user_id' => $inactiveRecipient->id,
            'title' => 'Broadcast notifikasi',
            'type' => 'warning',
        ]);
    }

    public function test_user_can_read_and_mark_notification_as_read(): void
    {
        $user = $this->createUserWithRole(RoleNames::SISWA);

        $notification = Notification::create([
            'user_id' => $user->id,
            'title' => 'Notif baru',
            'message' => 'Mohon dibaca',
            'type' => 'info',
            'data' => ['source' => 'seeded'],
            'is_read' => false,
            'created_by' => $user->id,
        ]);

        $indexResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $indexResponse->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonPath('data.total', 1);

        $countResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread/count');

        $countResponse->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        $markReadResponse = $this->actingAs($user, 'sanctum')
            ->postJson("/api/notifications/{$notification->id}/read");

        $markReadResponse->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'is_read' => true,
        ]);
    }

    public function test_notifications_are_filtered_by_display_platform(): void
    {
        $user = $this->createUserWithRole(RoleNames::SISWA);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Web only',
            'message' => 'Tampil hanya di web',
            'type' => 'info',
            'data' => [
                'presentation' => [
                    'in_app' => true,
                    'popup' => false,
                    'targets' => [
                        'web' => true,
                        'mobile' => false,
                    ],
                ],
            ],
            'is_read' => false,
            'created_by' => $user->id,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Mobile only',
            'message' => 'Tampil hanya di mobile',
            'type' => 'info',
            'data' => [
                'presentation' => [
                    'in_app' => true,
                    'popup' => false,
                    'targets' => [
                        'web' => false,
                        'mobile' => true,
                    ],
                ],
            ],
            'is_read' => false,
            'created_by' => $user->id,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Both platforms',
            'message' => 'Tampil di web dan mobile',
            'type' => 'info',
            'data' => [
                'presentation' => [
                    'in_app' => true,
                    'popup' => false,
                    'targets' => [
                        'web' => true,
                        'mobile' => true,
                    ],
                ],
            ],
            'is_read' => false,
            'created_by' => $user->id,
        ]);

        $webIndexResponse = $this->withHeaders(['X-Client-Platform' => 'web'])
            ->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $webIndexResponse->assertStatus(200)
            ->assertJsonCount(2, 'data.data')
            ->assertJsonFragment(['title' => 'Web only'])
            ->assertJsonFragment(['title' => 'Both platforms'])
            ->assertJsonMissing(['title' => 'Mobile only']);

        $webCountResponse = $this->withHeaders(['X-Client-Platform' => 'web'])
            ->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread/count');

        $webCountResponse->assertStatus(200)
            ->assertJsonPath('data.unread_count', 2);

        $mobileIndexResponse = $this->withHeaders(['X-Client-Platform' => 'mobile'])
            ->actingAs($user, 'sanctum')
            ->getJson('/api/notifications');

        $mobileIndexResponse->assertStatus(200)
            ->assertJsonCount(2, 'data.data')
            ->assertJsonFragment(['title' => 'Mobile only'])
            ->assertJsonFragment(['title' => 'Both platforms'])
            ->assertJsonMissing(['title' => 'Web only']);

        $mobileCountResponse = $this->withHeaders(['X-Client-Platform' => 'mobile'])
            ->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread/count');

        $mobileCountResponse->assertStatus(200)
            ->assertJsonPath('data.unread_count', 2);
    }

    public function test_notifications_support_category_filter_and_unread_breakdown(): void
    {
        $user = $this->createUserWithRole(RoleNames::SISWA);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Pesan Sistem',
            'message' => 'Notifikasi proses izin',
            'type' => 'info',
            'data' => [
                'source' => 'izin_workflow',
            ],
            'is_read' => false,
            'created_by' => $user->id,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Pengumuman 1',
            'message' => 'Pengumuman sekolah',
            'type' => 'info',
            'data' => [
                'broadcast_campaign_id' => 77,
                'source' => 'broadcast_message_page',
                'presentation' => [
                    'in_app' => true,
                    'popup' => false,
                ],
            ],
            'is_read' => false,
            'created_by' => $user->id,
        ]);

        Notification::create([
            'user_id' => $user->id,
            'title' => 'Pengumuman 2',
            'message' => 'Flyer kegiatan',
            'type' => 'info',
            'data' => [
                'source' => 'broadcast_message_page',
                'presentation' => [
                    'in_app' => false,
                    'popup' => true,
                ],
                'popup' => [
                    'variant' => 'flyer',
                ],
            ],
            'is_read' => false,
            'created_by' => $user->id,
        ]);

        $systemResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?category=system');

        $systemResponse->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonFragment(['title' => 'Pesan Sistem'])
            ->assertJsonMissing(['title' => 'Pengumuman 1']);

        $announcementResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?category=announcement');

        $announcementResponse->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonFragment(['title' => 'Pengumuman 1'])
            ->assertJsonFragment(['title' => 'Pengumuman 2'])
            ->assertJsonMissing(['title' => 'Pesan Sistem']);

        $countResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread/count');

        $countResponse->assertStatus(200)
            ->assertJsonPath('data.unread_count', 3)
            ->assertJsonPath('data.unread_count_total', 3)
            ->assertJsonPath('data.system_unread_count', 1)
            ->assertJsonPath('data.announcement_unread_count', 2);

        $markAnnouncementAsReadResponse = $this->actingAs($user, 'sanctum')
            ->postJson('/api/notifications/read-all', [
                'category' => 'announcement',
            ]);

        $markAnnouncementAsReadResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $afterMarkCountResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread/count');

        $afterMarkCountResponse->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1)
            ->assertJsonPath('data.system_unread_count', 1)
            ->assertJsonPath('data.announcement_unread_count', 0);
    }

    public function test_active_notification_scope_hides_expired_and_old_legacy_announcements(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 08:00:00'));

        $user = $this->createUserWithRole(RoleNames::SISWA);

        $oldLegacyAnnouncement = $this->makeNotification($user, 'Pengumuman lama', [
            'broadcast_campaign_id' => 11,
            'source' => 'broadcast_message_page',
        ]);
        $oldLegacyAnnouncement->forceFill([
            'created_at' => now()->subDays(45),
            'updated_at' => now()->subDays(45),
        ])->save();

        $recentAnnouncement = $this->makeNotification($user, 'Pengumuman aktif', [
            'broadcast_campaign_id' => 12,
            'source' => 'broadcast_message_page',
        ]);

        $expiredAnnouncement = $this->makeNotification($user, 'Pengumuman expired', [
            'message_category' => 'announcement',
        ], [
            'display_start_at' => now()->subDays(10),
            'display_end_at' => now()->subDay(),
            'expires_at' => now()->subDay(),
        ]);

        $pinnedExpiredAnnouncement = $this->makeNotification($user, 'Pengumuman pinned', [
            'message_category' => 'announcement',
        ], [
            'display_start_at' => now()->subDays(10),
            'display_end_at' => now()->subDay(),
            'expires_at' => now()->subDay(),
            'pinned_at' => now(),
        ]);

        $systemNotification = $this->makeNotification($user, 'Sistem lama', [
            'source' => 'izin_workflow',
        ]);
        $systemNotification->forceFill([
            'created_at' => now()->subDays(90),
            'updated_at' => now()->subDays(90),
        ])->save();

        $activeAnnouncements = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?category=announcement');

        $activeAnnouncements->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonFragment(['title' => 'Pengumuman aktif'])
            ->assertJsonFragment(['title' => 'Pengumuman pinned'])
            ->assertJsonMissing(['title' => 'Pengumuman lama'])
            ->assertJsonMissing(['title' => 'Pengumuman expired']);

        $activeCount = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications/unread/count');

        $activeCount->assertStatus(200)
            ->assertJsonPath('data.unread_count_total', 3)
            ->assertJsonPath('data.system_unread_count', 1)
            ->assertJsonPath('data.announcement_unread_count', 2);

        $allAnnouncements = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?category=announcement&scope=all');

        $allAnnouncements->assertStatus(200)
            ->assertJsonPath('data.total', 4)
            ->assertJsonFragment(['title' => 'Pengumuman lama'])
            ->assertJsonFragment(['title' => 'Pengumuman expired']);

        $expiredAnnouncements = $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications?category=announcement&scope=expired');

        $expiredAnnouncements->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonFragment(['title' => 'Pengumuman lama'])
            ->assertJsonFragment(['title' => 'Pengumuman expired'])
            ->assertJsonMissing(['title' => 'Pengumuman pinned']);
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

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $overrides
     */
    private function makeNotification(User $user, string $title, array $data = [], array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'user_id' => $user->id,
            'title' => $title,
            'message' => $title,
            'type' => 'info',
            'data' => $data,
            'is_read' => false,
            'created_by' => $user->id,
        ], $overrides));
    }
}
