<?php

namespace Tests\Feature;

use App\Models\AttendanceDisciplineAlert;
use App\Models\DataPribadiSiswa;
use App\Models\AttendanceDisciplineCase;
use App\Models\BroadcastCampaign;
use App\Models\Notification;
use App\Models\User;
use App\Models\WhatsappGateway;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceDisciplineCaseEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_admin_can_view_attendance_discipline_case_history(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $student = $this->createUserWithRole(RoleNames::SISWA);
        $student->update(['nama_lengkap' => 'Siswa Histori']);

        AttendanceDisciplineCase::create([
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
                'student_name' => 'Siswa Histori',
                'class_name' => 'X-A',
                'semester_label' => 'Genap',
                'reference' => 'DISC-1',
            ],
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/attendance-discipline-cases');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.student.name', 'Siswa Histori')
            ->assertJsonPath('data.data.0.metric_value', 4)
            ->assertJsonPath('data.data.0.status', AttendanceDisciplineCase::STATUS_READY_FOR_PARENT_BROADCAST)
            ->assertJsonPath('meta.summary.total', 1)
            ->assertJsonPath('meta.summary.ready_for_parent_broadcast', 1);
    }

    public function test_admin_can_filter_attendance_discipline_cases_by_status_and_parent_phone(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $studentWithPhone = $this->createUserWithRole(RoleNames::SISWA);
        $studentWithoutPhone = $this->createUserWithRole(RoleNames::SISWA);

        DataPribadiSiswa::create([
            'user_id' => $studentWithPhone->id,
            'no_hp_ortu' => '081234567890',
        ]);

        DataPribadiSiswa::create([
            'user_id' => $studentWithoutPhone->id,
            'no_hp_ortu' => '',
        ]);

        AttendanceDisciplineCase::create([
            'user_id' => $studentWithPhone->id,
            'rule_key' => 'semester_alpha_limit',
            'status' => AttendanceDisciplineCase::STATUS_READY_FOR_PARENT_BROADCAST,
            'semester' => 'genap',
            'tahun_ajaran_ref' => '2025/2026',
            'metric_value' => 4,
            'metric_limit' => 3,
            'first_triggered_at' => now()->subDay(),
            'last_triggered_at' => now(),
            'payload' => [
                'student_name' => 'Siswa With Phone',
                'class_name' => 'X-A',
            ],
        ]);

        AttendanceDisciplineCase::create([
            'user_id' => $studentWithoutPhone->id,
            'rule_key' => 'semester_alpha_limit',
            'status' => AttendanceDisciplineCase::STATUS_PARENT_BROADCAST_SENT,
            'semester' => 'genap',
            'tahun_ajaran_ref' => '2025/2026',
            'metric_value' => 5,
            'metric_limit' => 3,
            'first_triggered_at' => now()->subDays(2),
            'last_triggered_at' => now()->subHour(),
            'payload' => [
                'student_name' => 'Siswa Without Phone',
                'class_name' => 'X-B',
            ],
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/attendance-discipline-cases?status=ready_for_parent_broadcast&parent_phone=available');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.data.0.student.id', $studentWithPhone->id)
            ->assertJsonPath('data.data.0.parent_phone_available', true)
            ->assertJsonPath('meta.summary.total', 1)
            ->assertJsonPath('meta.summary.parent_phone_available', 1)
            ->assertJsonPath('meta.summary.parent_phone_missing', 0);
    }

    public function test_admin_can_view_attendance_discipline_case_detail_with_alert_trail(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $recipient = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $student = $this->createUserWithRole(RoleNames::SISWA);
        $student->update(['nama_lengkap' => 'Siswa Detail']);

        DataPribadiSiswa::create([
            'user_id' => $student->id,
            'no_hp_ortu' => '081234567890',
            'no_hp_wali' => '081111111111',
        ]);

        $campaign = BroadcastCampaign::create([
            'title' => 'Broadcast Orang Tua',
            'message' => 'Mohon tindak lanjut kehadiran siswa.',
            'type' => 'warning',
            'message_category' => 'system',
            'channels' => ['whatsapp' => true],
            'audience' => ['mode' => 'user', 'user_id' => $student->id],
            'status' => 'sent',
            'total_target' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
            'summary' => ['channels' => [['channel' => 'WhatsApp', 'target_count' => 1, 'sent' => 1, 'failed' => 0]]],
            'created_by' => $admin->id,
            'sent_at' => now(),
        ]);

        $case = AttendanceDisciplineCase::create([
            'user_id' => $student->id,
            'rule_key' => 'semester_alpha_limit',
            'status' => AttendanceDisciplineCase::STATUS_PARENT_BROADCAST_SENT,
            'semester' => 'genap',
            'tahun_ajaran_ref' => '2025/2026',
            'metric_value' => 4,
            'metric_limit' => 3,
            'broadcast_campaign_id' => $campaign->id,
            'first_triggered_at' => now()->subDay(),
            'last_triggered_at' => now(),
            'payload' => [
                'student_name' => 'Siswa Detail',
                'class_name' => 'X-A',
                'semester_label' => 'Genap',
                'reference' => 'DISC-DETAIL-1',
            ],
        ]);

        $notification = Notification::create([
            'user_id' => $recipient->id,
            'title' => 'Batas alpha semester terlampaui',
            'message' => 'Siswa Detail telah mencapai 4 hari alpha.',
            'type' => 'warning',
            'data' => ['source' => 'attendance_discipline_threshold'],
            'is_read' => false,
        ]);

        $waRecord = WhatsappGateway::create([
            'phone_number' => '6281234567890',
            'message' => 'Alert internal WA',
            'type' => WhatsappGateway::TYPE_REMINDER,
            'status' => WhatsappGateway::STATUS_SENT,
            'metadata' => ['source' => 'attendance_discipline_threshold'],
            'sent_at' => now(),
        ]);

        AttendanceDisciplineAlert::create([
            'user_id' => $student->id,
            'recipient_user_id' => $recipient->id,
            'notification_id' => $notification->id,
            'whatsapp_notification_id' => $waRecord->id,
            'rule_key' => 'semester_alpha_limit',
            'audience' => 'wali_kelas',
            'semester' => 'genap',
            'tahun_ajaran_ref' => '2025/2026',
            'triggered_at' => now(),
            'payload' => [
                'student_name' => 'Siswa Detail',
            ],
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/attendance-discipline-cases/' . $case->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $case->id)
            ->assertJsonPath('data.student.name', 'Siswa Detail')
            ->assertJsonPath('data.parent_phone_available', true)
            ->assertJsonPath('data.parent_contacts.0.value', '081234567890')
            ->assertJsonPath('data.alerts.0.recipient.id', $recipient->id)
            ->assertJsonPath('data.alerts.0.whatsapp.status', WhatsappGateway::STATUS_SENT)
            ->assertJsonPath('data.broadcast_campaign.id', $campaign->id)
            ->assertJsonPath('data.broadcast_campaign.summary.0.channel', 'WhatsApp');
    }

    public function test_admin_can_export_attendance_discipline_cases_as_csv_and_pdf(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $student = $this->createUserWithRole(RoleNames::SISWA);
        $student->update(['nama_lengkap' => 'Siswa Export Alert']);

        AttendanceDisciplineCase::create([
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
                'student_name' => 'Siswa Export Alert',
                'class_name' => 'X-A',
                'semester_label' => 'Genap',
            ],
        ]);

        $csvResponse = $this->actingAs($admin, 'sanctum')
            ->get('/api/attendance-discipline-cases/export?format=csv');

        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $csvResponse->headers->get('content-type'));
        $this->assertStringContainsString('attendance-discipline-cases-', (string) $csvResponse->headers->get('content-disposition'));
        $this->assertStringContainsString('Siswa Export Alert', $csvResponse->streamedContent());

        $pdfResponse = $this->actingAs($admin, 'sanctum')
            ->get('/api/attendance-discipline-cases/export?format=pdf');

        $pdfResponse->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $pdfResponse->headers->get('content-type'));
        $this->assertStringContainsString('attendance-discipline-cases-', (string) $pdfResponse->headers->get('content-disposition'));
    }

    private function createUserWithRole(string $canonicalRole): User
    {
        $role = Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->where('guard_name', 'web')
            ->firstOrFail();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
