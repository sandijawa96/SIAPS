<?php

namespace Tests\Feature;

use App\Jobs\DispatchIzinApproverNotifications;
use App\Jobs\DispatchIzinDecisionNotification;
use App\Jobs\DispatchIzinWhatsappNotification;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Absensi;
use App\Models\AttendanceAuditLog;
use App\Models\AttendanceSchema;
use App\Models\Notification;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappGateway;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class IzinRoleRestrictionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private int $tingkatId;
    private int $tahunAjaranId;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->seedRolesAndPermissions();
        $this->seedAcademicMasterData();
    }

    public function test_wali_kelas_can_approve_student_izin_only_for_own_class(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $otherWali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswaOwnClass = $this->createUserWithRole(RoleNames::SISWA);
        $siswaOtherClass = $this->createUserWithRole(RoleNames::SISWA);

        $kelasOwn = $this->createKelas($wali->id, 'X-IPA-1');
        $kelasOther = $this->createKelas($otherWali->id, 'X-IPA-2');

        $this->assignSiswaToKelas($siswaOwnClass->id, $kelasOwn->id);
        $this->assignSiswaToKelas($siswaOtherClass->id, $kelasOther->id);

        $izinOwn = $this->createPendingIzin($siswaOwnClass->id, $kelasOwn->id);
        $izinOther = $this->createPendingIzin($siswaOtherClass->id, $kelasOther->id);

        $approveOwn = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izinOwn->id}/approve", [
                'catatan_approval' => 'Disetujui wali kelas',
            ]);

        $approveOwn->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Pengajuan izin disetujui',
            ]);

        $this->assertDatabaseHas('izin', [
            'id' => $izinOwn->id,
            'status' => 'approved',
            'approved_by' => $wali->id,
        ]);

        $approveOther = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izinOther->id}/approve", [
                'catatan_approval' => 'Tidak boleh',
            ]);

        $approveOther->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Approval izin siswa hanya untuk Super Admin, Admin, Wakasek Kesiswaan, atau Wali Kelas pada kelas terkait',
            ]);
    }

    public function test_wakasek_kesiswaan_can_approve_any_student_izin(): void
    {
        $wakasek = $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelas = $this->createKelas($wali->id, 'XI-IPA-1');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);
        $izin = $this->createPendingIzin($siswa->id, $kelas->id);

        $response = $this->actingAs($wakasek, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve", [
                'catatan_approval' => 'Disetujui wakasek kesiswaan',
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('izin', [
            'id' => $izin->id,
            'status' => 'approved',
            'approved_by' => $wakasek->id,
        ]);
    }

    public function test_admin_with_approve_permission_can_approve_student_izin(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelas = $this->createKelas($wali->id, 'XII-IPA-1');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);
        $izin = $this->createPendingIzin($siswa->id, $kelas->id);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve", [
                'catatan_approval' => 'Admin approve',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Pengajuan izin disetujui',
            ]);
    }

    public function test_admin_cannot_approve_non_student_izin_when_feature_disabled(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);
        $pegawai = User::factory()->create();

        $izin = Izin::create([
            'user_id' => $pegawai->id,
            'kelas_id' => null,
            'jenis_izin' => 'cuti',
            'tanggal_mulai' => Carbon::today()->addDay()->toDateString(),
            'tanggal_selesai' => Carbon::today()->addDays(2)->toDateString(),
            'alasan' => 'Keperluan keluarga',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve", [
                'catatan_approval' => 'Disetujui admin',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Approval izin pegawai dinonaktifkan',
            ]);
    }

    public function test_wali_kelas_approval_queue_only_contains_their_students(): void
    {
        $waliA = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $waliB = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswaA = $this->createUserWithRole(RoleNames::SISWA);
        $siswaB = $this->createUserWithRole(RoleNames::SISWA);

        $kelasA = $this->createKelas($waliA->id, 'X-IPA-3');
        $kelasB = $this->createKelas($waliB->id, 'X-IPA-4');

        $this->assignSiswaToKelas($siswaA->id, $kelasA->id);
        $this->assignSiswaToKelas($siswaB->id, $kelasB->id);

        $izinA = $this->createPendingIzin($siswaA->id, $kelasA->id);
        $this->createPendingIzin($siswaB->id, $kelasB->id);

        $response = $this->actingAs($waliA, 'sanctum')
            ->getJson('/api/izin/approval/list?type=siswa');

        $response->assertStatus(200)->assertJson(['success' => true]);

        $items = $response->json('data.data');
        $this->assertCount(1, $items);
        $this->assertSame($izinA->id, $items[0]['id']);
    }

    public function test_admin_can_open_student_approval_queue(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/izin/approval/list?type=siswa');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_wali_kelas_can_view_own_student_izin_detail_and_document_only_for_own_class(): void
    {
        Storage::fake('public');

        $waliA = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $waliB = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswaA = $this->createUserWithRole(RoleNames::SISWA);
        $siswaB = $this->createUserWithRole(RoleNames::SISWA);

        $kelasA = $this->createKelas($waliA->id, 'X-IPA-9');
        $kelasB = $this->createKelas($waliB->id, 'X-IPA-10');

        $this->assignSiswaToKelas($siswaA->id, $kelasA->id);
        $this->assignSiswaToKelas($siswaB->id, $kelasB->id);

        Storage::disk('public')->put('izin_documents/test-own.pdf', 'dummy');
        Storage::disk('public')->put('izin_documents/test-other.pdf', 'dummy');

        $izinOwn = $this->createIzin(
            $siswaA->id,
            $kelasA->id,
            Carbon::today()->addDay()->toDateString(),
            Carbon::today()->addDay()->toDateString(),
            'pending',
            'izin',
            'izin_documents/test-own.pdf'
        );

        $izinOther = $this->createIzin(
            $siswaB->id,
            $kelasB->id,
            Carbon::today()->addDay()->toDateString(),
            Carbon::today()->addDay()->toDateString(),
            'pending',
            'izin',
            'izin_documents/test-other.pdf'
        );

        $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/izin/{$izinOwn->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $izinOwn->id);

        $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/izin/{$izinOther->id}")
            ->assertStatus(403);

        $this->actingAs($waliA, 'sanctum')
            ->get("/api/izin/{$izinOwn->id}/document")
            ->assertStatus(200);

        $this->actingAs($waliA, 'sanctum')
            ->get("/api/izin/{$izinOther->id}/document")
            ->assertStatus(403);
    }

    public function test_wali_kelas_student_statistics_are_scoped_to_own_class(): void
    {
        $waliA = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $waliB = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswaA = $this->createUserWithRole(RoleNames::SISWA);
        $siswaB = $this->createUserWithRole(RoleNames::SISWA);

        $kelasA = $this->createKelas($waliA->id, 'XI-IPA-2');
        $kelasB = $this->createKelas($waliB->id, 'XI-IPA-3');

        $this->assignSiswaToKelas($siswaA->id, $kelasA->id);
        $this->assignSiswaToKelas($siswaB->id, $kelasB->id);

        $this->createIzin(
            $siswaA->id,
            $kelasA->id,
            Carbon::today()->addDay()->toDateString(),
            Carbon::today()->addDay()->toDateString(),
            'approved'
        );
        $this->createIzin(
            $siswaB->id,
            $kelasB->id,
            Carbon::today()->addDay()->toDateString(),
            Carbon::today()->addDay()->toDateString(),
            'pending'
        );

        $response = $this->actingAs($waliA, 'sanctum')
            ->getJson('/api/izin/statistics?type=siswa');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.approved', 1)
            ->assertJsonPath('data.pending', 0);
    }

    public function test_siswa_cannot_submit_izin_without_active_kelas_assignment(): void
    {
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $response = $this->actingAs($siswa, 'sanctum')
            ->post('/api/izin', [
                'jenis_izin' => 'izin',
                'tanggal_mulai' => Carbon::today()->addDay()->toDateString(),
                'tanggal_selesai' => Carbon::today()->addDays(2)->toDateString(),
                'alasan' => 'Acara keluarga',
                'dokumen_pendukung' => UploadedFile::fake()->image('bukti-izin.jpg'),
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Siswa belum terdaftar pada kelas aktif',
            ]);
    }

    public function test_siswa_jenis_options_endpoint_returns_student_types_only_with_metadata(): void
    {
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $response = $this->actingAs($siswa, 'sanctum')
            ->getJson('/api/izin/jenis/siswa');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $values = collect($response->json('data'))->pluck('value')->all();

        $this->assertSame([
            'sakit',
            'izin',
            'keperluan_keluarga',
            'dispensasi',
            'tugas_sekolah',
        ], $values);

        $this->assertNotContains('cuti', $values);
        $this->assertNotContains('dinas_luar', $values);
        $response->assertJsonPath('data.0.label', 'Sakit')
            ->assertJsonPath('data.0.group', 'kesehatan')
            ->assertJsonPath('data.0.evidence_policy.required', false)
            ->assertJsonPath('data.0.evidence_policy.rule', 'required_if_multi_day')
            ->assertJsonPath('data.1.label', 'Izin Pribadi')
            ->assertJsonPath('data.2.label', 'Urusan Keluarga')
            ->assertJsonPath('data.3.label', 'Dispensasi Sekolah');
    }

    public function test_siswa_cannot_submit_non_student_jenis_izin(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);
        $kelas = $this->createKelas($wali->id, 'X-IPA-11');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $response = $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/izin', [
                'jenis_izin' => 'cuti',
                'tanggal_mulai' => Carbon::today()->addDay()->toDateString(),
                'tanggal_selesai' => Carbon::today()->addDays(2)->toDateString(),
                'alasan' => 'Mencoba jenis non siswa',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertArrayHasKey('jenis_izin', $response->json('errors') ?? []);
    }

    public function test_siswa_submit_sakit_multi_day_requires_attachment(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);
        $kelas = $this->createKelas($wali->id, 'X-IPA-12');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $response = $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/izin', [
                'jenis_izin' => 'sakit',
                'tanggal_mulai' => Carbon::today()->addDay()->toDateString(),
                'tanggal_selesai' => Carbon::today()->addDays(2)->toDateString(),
                'alasan' => 'Sakit beberapa hari tanpa lampiran',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertArrayHasKey('dokumen_pendukung', $response->json('errors') ?? []);
    }

    public function test_siswa_submit_sakit_single_day_can_skip_attachment(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);
        $kelas = $this->createKelas($wali->id, 'X-IPA-12A');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->startOfDay();

        $response = $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/izin', [
                'jenis_izin' => 'sakit',
                'tanggal_mulai' => $nextMonday->toDateString(),
                'tanggal_selesai' => $nextMonday->toDateString(),
                'alasan' => 'Demam dan perlu istirahat',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.jenis_izin', 'sakit')
            ->assertJsonPath('data.evidence_required', false)
            ->assertJsonPath('data.school_days_affected', 1)
            ->assertJsonPath('data.non_working_days_skipped', 0);
    }

    public function test_siswa_submit_tugas_sekolah_can_skip_attachment(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);
        $kelas = $this->createKelas($wali->id, 'X-IPA-13');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $nextMonday = Carbon::now()->next(Carbon::MONDAY)->startOfDay();

        $response = $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/izin', [
                'jenis_izin' => 'tugas_sekolah',
                'tanggal_mulai' => $nextMonday->toDateString(),
                'tanggal_selesai' => $nextMonday->toDateString(),
                'alasan' => 'Tugas observasi dari guru mapel',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.jenis_izin', 'tugas_sekolah')
            ->assertJsonPath('data.jenis_izin_label', 'Tugas Sekolah')
            ->assertJsonPath('data.school_days_affected', 1)
            ->assertJsonPath('data.non_working_days_skipped', 0);
    }

    public function test_approval_queue_is_ordered_by_start_date_then_created_at(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);
        $kelas = $this->createKelas($wali->id, 'X-IPA-13A');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $later = $this->createIzin(
            $siswa->id,
            $kelas->id,
            Carbon::now()->next(Carbon::WEDNESDAY)->toDateString(),
            Carbon::now()->next(Carbon::WEDNESDAY)->toDateString(),
            'pending',
            'izin'
        );

        $earlier = $this->createIzin(
            $siswa->id,
            $kelas->id,
            Carbon::now()->next(Carbon::MONDAY)->toDateString(),
            Carbon::now()->next(Carbon::MONDAY)->toDateString(),
            'pending',
            'sakit'
        );

        $response = $this->actingAs($wali, 'sanctum')
            ->getJson('/api/izin/approval/list?type=siswa');

        $response->assertStatus(200)
            ->assertJsonPath('data.data.0.id', $earlier->id)
            ->assertJsonPath('data.data.1.id', $later->id);
    }

    public function test_approval_queue_marks_pending_requests_that_have_passed_start_date(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 8, 8, 0, 0));

        try {
            $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
            $siswa = $this->createUserWithRole(RoleNames::SISWA);
            $kelas = $this->createKelas($wali->id, 'X-IPA-13B');
            $this->assignSiswaToKelas($siswa->id, $kelas->id);

            $izin = $this->createIzin(
                $siswa->id,
                $kelas->id,
                '2026-04-06',
                '2026-04-06',
                'pending',
                'izin'
            );

            $response = $this->actingAs($wali, 'sanctum')
                ->getJson('/api/izin/approval/list?type=siswa');

            $response->assertStatus(200)
                ->assertJsonPath('data.data.0.id', $izin->id)
                ->assertJsonPath('data.data.0.pending_review_state', 'overdue')
                ->assertJsonPath('data.data.0.is_pending_overdue', true)
                ->assertJsonPath('data.data.0.pending_overdue_days', 2)
                ->assertJsonPath('data.data.0.pending_review_label', 'Terlambat 2 hari');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_approval_queue_returns_pending_review_priority_summary(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 10, 8, 0, 0));

        try {
            $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
            $siswaA = $this->createUserWithRole(RoleNames::SISWA);
            $siswaB = $this->createUserWithRole(RoleNames::SISWA);
            $siswaC = $this->createUserWithRole(RoleNames::SISWA);

            $kelas = $this->createKelas($wali->id, 'X-IPA-13C');
            $this->assignSiswaToKelas($siswaA->id, $kelas->id);
            $this->assignSiswaToKelas($siswaB->id, $kelas->id);
            $this->assignSiswaToKelas($siswaC->id, $kelas->id);

            $this->createIzin($siswaA->id, $kelas->id, '2026-04-08', '2026-04-08', 'pending', 'izin');
            $this->createIzin($siswaB->id, $kelas->id, '2026-04-10', '2026-04-10', 'pending', 'izin');
            $this->createIzin($siswaC->id, $kelas->id, '2026-04-12', '2026-04-12', 'pending', 'izin');

            $response = $this->actingAs($wali, 'sanctum')
                ->getJson('/api/izin/approval/list?type=siswa');

            $response->assertStatus(200)
                ->assertJsonPath('meta.pending_review_summary.total_pending', 3)
                ->assertJsonPath('meta.pending_review_summary.overdue', 1)
                ->assertJsonPath('meta.pending_review_summary.due_today', 1)
                ->assertJsonPath('meta.pending_review_summary.upcoming', 1)
                ->assertJsonPath('meta.pending_review_summary.urgent', 2);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_wali_kelas_approve_creates_absensi_for_each_school_day(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);
        $kelas = $this->createKelas($wali->id, 'X-IPA-5');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $start = Carbon::now()->next(Carbon::MONDAY)->startOfDay();
        $end = $start->copy()->addDays(2); // Monday to Wednesday

        $izin = $this->createIzin(
            $siswa->id,
            $kelas->id,
            $start->toDateString(),
            $end->toDateString(),
            'pending'
        );

        $response = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve", [
                'catatan_approval' => 'Disetujui wali',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Pengajuan izin disetujui',
            ]);

        $this->assertDatabaseHas('izin', [
            'id' => $izin->id,
            'status' => 'approved',
            'approved_by' => $wali->id,
        ]);

        $this->assertSame(3, DB::table('absensi')->where('izin_id', $izin->id)->count());
    }

    public function test_late_approval_can_replace_existing_alpha_attendance_for_the_same_day(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 8, 8, 0, 0));

        try {
            $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
            $siswa = $this->createUserWithRole(RoleNames::SISWA);
            $kelas = $this->createKelas($wali->id, 'X-IPA-5A');
            $this->assignSiswaToKelas($siswa->id, $kelas->id);

            $izin = $this->createIzin(
                $siswa->id,
                $kelas->id,
                '2026-04-06',
                '2026-04-06',
                'pending',
                'izin'
            );

            $attendance = Absensi::create([
                'user_id' => $siswa->id,
                'kelas_id' => $kelas->id,
                'tanggal' => '2026-04-06',
                'status' => 'alpha',
                'metode_absensi' => 'manual',
                'keterangan' => 'Auto alpha: tidak ada absensi masuk pada hari kerja (siswa mobile app aktif).',
                'is_manual' => false,
                'jam_masuk' => null,
                'jam_pulang' => null,
            ]);

            $response = $this->actingAs($wali, 'sanctum')
                ->postJson("/api/izin/{$izin->id}/approve", [
                    'catatan_approval' => 'Disetujui terlambat oleh wali kelas',
                ]);

            $response->assertStatus(200)
                ->assertJsonPath('data.approval_summary.updated_count', 1)
                ->assertJsonPath('data.approval_summary.created_count', 0);

            $this->assertDatabaseHas('absensi', [
                'id' => $attendance->id,
                'status' => 'izin',
                'izin_id' => $izin->id,
                'is_manual' => true,
                'metode_absensi' => 'manual',
            ]);

            $this->assertDatabaseHas('attendance_audit_logs', [
                'attendance_id' => $attendance->id,
                'action_type' => 'updated',
                'performed_by' => $wali->id,
            ]);

            $auditLog = AttendanceAuditLog::query()
                ->where('attendance_id', $attendance->id)
                ->latest('id')
                ->first();

            $this->assertNotNull($auditLog);
            $this->assertSame('leave_approval', data_get($auditLog?->metadata, 'source'));
            $this->assertSame($izin->id, (int) data_get($auditLog?->metadata, 'izin_id'));
            $this->assertSame('alpha', data_get($auditLog?->metadata, 'previous_status'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_approval_skips_attendance_creation_when_effective_schema_does_not_require_attendance(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);
        $kelas = $this->createKelas($wali->id, 'X-IPA-5B');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);
        $this->createDefaultAttendanceSchema(false);

        $start = Carbon::now()->next(Carbon::MONDAY)->startOfDay();
        $izin = $this->createIzin(
            $siswa->id,
            $kelas->id,
            $start->toDateString(),
            $start->toDateString(),
            'pending'
        );

        $response = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve", [
                'catatan_approval' => 'Disetujui tetapi schema tidak mewajibkan absensi',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.approval_summary.created_count', 0)
            ->assertJsonPath('data.approval_summary.updated_count', 0)
            ->assertJsonPath('data.approval_summary.skipped_attendance_not_required_count', 1);

        $this->assertDatabaseMissing('absensi', [
            'user_id' => $siswa->id,
            'tanggal' => $start->toDateString(),
        ]);
    }

    public function test_student_submit_is_idempotent_with_client_request_id(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelas = $this->createKelas($wali->id, 'X-IPA-20');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $payload = [
            'jenis_izin' => 'tugas_sekolah',
            'tanggal_mulai' => Carbon::today()->addDay()->toDateString(),
            'tanggal_selesai' => Carbon::today()->addDay()->toDateString(),
            'alasan' => 'Mengikuti tugas observasi sekolah',
            'client_request_id' => 'mobile-izin-idempotent-001',
        ];

        $firstResponse = $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/izin', $payload);

        $firstResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $firstIzinId = (int) $firstResponse->json('data.id');

        $secondResponse = $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/izin', $payload);

        $secondResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Pengajuan izin sebelumnya sudah diterima dan sedang ditinjau')
            ->assertJsonPath('data.id', $firstIzinId);

        $this->assertSame(1, Izin::query()->where('user_id', $siswa->id)->count());
    }

    public function test_izin_submission_and_approval_dispatch_notifications_to_queue(): void
    {
        Queue::fake();

        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelas = $this->createKelas($wali->id, 'X-IPA-21');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $submitResponse = $this->actingAs($siswa, 'sanctum')
            ->postJson('/api/izin', [
                'jenis_izin' => 'tugas_sekolah',
                'tanggal_mulai' => Carbon::today()->addDay()->toDateString(),
                'tanggal_selesai' => Carbon::today()->addDay()->toDateString(),
                'alasan' => 'Observasi laboratorium',
            ]);

        $submitResponse->assertStatus(201)
            ->assertJsonPath('success', true);

        $izinId = (int) $submitResponse->json('data.id');

        Queue::assertPushed(DispatchIzinApproverNotifications::class, function (DispatchIzinApproverNotifications $job) use ($izinId) {
            return $job->izinId === $izinId;
        });
        Queue::assertPushed(DispatchIzinWhatsappNotification::class, function (DispatchIzinWhatsappNotification $job) use ($izinId) {
            return $job->izinId === $izinId && $job->event === 'submitted';
        });

        $approveResponse = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izinId}/approve", [
                'catatan_approval' => 'Disetujui wali kelas',
            ]);

        $approveResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        Queue::assertPushed(DispatchIzinDecisionNotification::class, function (DispatchIzinDecisionNotification $job) use ($izinId, $wali) {
            return $job->izinId === $izinId
                && $job->status === 'approved'
                && $job->actorUserId === $wali->id;
        });
        Queue::assertPushed(DispatchIzinWhatsappNotification::class, function (DispatchIzinWhatsappNotification $job) use ($izinId) {
            return $job->izinId === $izinId && $job->event === 'decision';
        });
    }

    public function test_approver_can_read_izin_observability_summary(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelas = $this->createKelas($wali->id, 'X-IPA-22');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);
        $izin = $this->createPendingIzin($siswa->id, $kelas->id);

        Notification::create([
            'user_id' => $wali->id,
            'title' => 'Pengajuan izin menunggu review',
            'message' => 'Permintaan approval baru',
            'type' => 'info',
            'data' => [
                'izin_id' => $izin->id,
                'message_category' => 'izin_approval_request',
                'source' => 'izin_workflow',
            ],
            'is_read' => false,
            'created_by' => $siswa->id,
        ]);

        WhatsappGateway::create([
            'phone_number' => '6281355512345',
            'message' => 'Pengajuan izin diterima',
            'type' => WhatsappGateway::TYPE_IZIN,
            'status' => WhatsappGateway::STATUS_FAILED,
            'metadata' => ['source' => 'izin_submitted', 'izin_id' => $izin->id],
            'error_message' => 'Gateway timeout',
            'retry_count' => 1,
            'max_retries' => 3,
        ]);

        DB::table('failed_jobs')->insert([
            'uuid' => 'izin-observability-failed-job-1',
            'connection' => 'sync',
            'queue' => DispatchIzinApproverNotifications::QUEUE_NAME,
            'payload' => json_encode([
                'displayName' => DispatchIzinApproverNotifications::class,
                'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
                'data' => ['commandName' => DispatchIzinApproverNotifications::class],
            ]),
            'exception' => 'Simulated job failure',
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($wali, 'sanctum')
            ->getJson('/api/izin/observability');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.delivery.in_app.created_window', 1)
            ->assertJsonPath('data.delivery.whatsapp.failed_window', 1)
            ->assertJsonPath('data.failures.summary.failed_jobs_window_count', 1);
    }

    public function test_student_cannot_access_izin_observability(): void
    {
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $response = $this->actingAs($siswa, 'sanctum')
            ->getJson('/api/izin/observability');

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_wali_kelas_reject_requires_pending_status(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);
        $kelas = $this->createKelas($wali->id, 'X-IPA-6');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $izin = $this->createPendingIzin($siswa->id, $kelas->id);

        $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve")
            ->assertStatus(200);

        $response = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/reject", [
                'catatan_approval' => 'Tidak valid',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Izin sudah diproses sebelumnya',
            ]);
    }

    public function test_wali_kelas_cannot_process_non_student_izin_when_feature_disabled(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $guru = $this->createUserWithRole(RoleNames::GURU);
        $kelas = $this->createKelas($wali->id, 'X-IPA-7');

        $izin = $this->createIzin(
            $guru->id,
            $kelas->id,
            Carbon::today()->addDay()->toDateString(),
            Carbon::today()->addDay()->toDateString(),
            'pending',
            'cuti'
        );

        $response = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Approval izin pegawai dinonaktifkan',
            ]);
    }

    public function test_approval_list_rejects_pegawai_type_when_feature_disabled(): void
    {
        $admin = $this->createUserWithRole(RoleNames::ADMIN);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/izin/approval/list?type=pegawai');

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Approval izin pegawai dinonaktifkan. Gunakan type=siswa.',
            ]);
    }

    public function test_non_student_cannot_submit_izin_even_with_submit_permission(): void
    {
        $guru = $this->createUserWithRole(RoleNames::GURU);
        $guru->givePermissionTo('submit_izin');

        $response = $this->actingAs($guru, 'sanctum')
            ->postJson('/api/izin', [
                'jenis_izin' => 'izin',
                'tanggal_mulai' => Carbon::today()->addDay()->toDateString(),
                'tanggal_selesai' => Carbon::today()->addDay()->toDateString(),
                'alasan' => 'Keperluan pribadi',
            ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Fitur pengajuan izin hanya tersedia untuk siswa',
            ]);
    }

    public function test_approve_response_contains_processed_and_skipped_date_summary(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);
        $kelas = $this->createKelas($wali->id, 'X-IPA-14');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $start = Carbon::now()->next(Carbon::FRIDAY)->startOfDay();
        $end = $start->copy()->addDays(2); // Friday to Sunday

        Absensi::create([
            'user_id' => $siswa->id,
            'kelas_id' => $kelas->id,
            'tanggal' => $start->toDateString(),
            'jam_masuk' => '07:05:00',
            'jam_pulang' => '15:00:00',
            'status' => 'hadir',
        ]);

        $izin = $this->createIzin(
            $siswa->id,
            $kelas->id,
            $start->toDateString(),
            $end->toDateString(),
            'pending'
        );

        $response = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve", [
                'catatan_approval' => 'Disetujui wali dengan ringkasan',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.approval_summary.processed_count', 1)
            ->assertJsonPath('data.approval_summary.created_count', 0)
            ->assertJsonPath('data.approval_summary.updated_count', 0)
            ->assertJsonPath('data.approval_summary.skipped_non_working_count', 2)
            ->assertJsonPath('data.approval_summary.skipped_existing_attendance_count', 1);

        $this->assertSame(
            [$start->toDateString()],
            $response->json('data.approval_summary.skipped_existing_attendance_dates')
        );
        $this->assertSame(
            [$start->copy()->addDay()->toDateString(), $end->toDateString()],
            $response->json('data.approval_summary.skipped_non_working_days')
        );
    }

    public function test_wali_kelas_denies_approve_access_for_other_class(): void
    {
        $waliA = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $waliB = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelasB = $this->createKelas($waliB->id, 'X-IPA-8');
        $this->assignSiswaToKelas($siswa->id, $kelasB->id);
        $izin = $this->createPendingIzin($siswa->id, $kelasB->id);

        $response = $this->actingAs($waliA, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve");

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Approval izin siswa hanya untuk Super Admin, Admin, Wakasek Kesiswaan, atau Wali Kelas pada kelas terkait',
            ]);
    }

    public function test_approve_auto_closes_related_approver_notifications(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $wakasek = $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelas = $this->createKelas($wali->id, 'X-IPA-15');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);
        $izin = $this->createPendingIzin($siswa->id, $kelas->id);

        $approvalNotificationWali = Notification::create([
            'user_id' => $wali->id,
            'title' => 'Pengajuan izin menunggu review',
            'message' => 'Permintaan approval baru',
            'type' => 'info',
            'data' => [
                'izin_id' => $izin->id,
                'message_category' => 'izin_approval_request',
            ],
            'is_read' => false,
            'created_by' => $siswa->id,
        ]);

        $approvalNotificationWakasek = Notification::create([
            'user_id' => $wakasek->id,
            'title' => 'Pengajuan izin menunggu review',
            'message' => 'Permintaan approval baru',
            'type' => 'info',
            'data' => [
                'izin_id' => $izin->id,
                'message_category' => 'izin_approval_request',
            ],
            'is_read' => false,
            'created_by' => $siswa->id,
        ]);

        $legacyApprovalNotification = Notification::create([
            'user_id' => $wakasek->id,
            'title' => 'Pengajuan izin menunggu review',
            'message' => 'Format lama tanpa message_category',
            'type' => 'info',
            'data' => [
                'izin_id' => $izin->id,
            ],
            'is_read' => false,
            'created_by' => $siswa->id,
        ]);

        $studentDecisionNotification = Notification::create([
            'user_id' => $siswa->id,
            'title' => 'Pengajuan izin disetujui',
            'message' => 'Notifikasi hasil untuk siswa',
            'type' => 'success',
            'data' => [
                'izin_id' => $izin->id,
                'message_category' => 'izin_decision_result',
            ],
            'is_read' => false,
            'created_by' => $wali->id,
        ]);

        $unrelatedNotification = Notification::create([
            'user_id' => $wakasek->id,
            'title' => 'Pengajuan izin menunggu review',
            'message' => 'Izin lain',
            'type' => 'info',
            'data' => [
                'izin_id' => $izin->id + 999,
                'message_category' => 'izin_approval_request',
            ],
            'is_read' => false,
            'created_by' => $siswa->id,
        ]);

        $response = $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve", [
                'catatan_approval' => 'Disetujui wali kelas',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.notification_cleanup.izin_id', $izin->id)
            ->assertJsonPath('data.notification_cleanup.status', 'approved')
            ->assertJsonPath('data.notification_cleanup.marked_as_read', 3);

        $this->assertDatabaseHas('notifications', [
            'id' => $approvalNotificationWali->id,
            'is_read' => true,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $approvalNotificationWakasek->id,
            'is_read' => true,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $legacyApprovalNotification->id,
            'is_read' => true,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $studentDecisionNotification->id,
            'is_read' => false,
        ]);

        $this->assertDatabaseHas('notifications', [
            'id' => $unrelatedNotification->id,
            'is_read' => false,
        ]);
    }

    public function test_already_processed_response_contains_current_status_for_client_sync(): void
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $wakasek = $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelas = $this->createKelas($wali->id, 'X-IPA-16');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);
        $izin = $this->createPendingIzin($siswa->id, $kelas->id);

        $this->actingAs($wali, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/approve", [
                'catatan_approval' => 'Diproses lebih dulu oleh wali',
            ])
            ->assertStatus(200);

        $response = $this->actingAs($wakasek, 'sanctum')
            ->postJson("/api/izin/{$izin->id}/reject", [
                'catatan_approval' => 'Request kedua dari approver lain',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Izin sudah diproses sebelumnya')
            ->assertJsonPath('data.izin_id', $izin->id)
            ->assertJsonPath('data.current_status', 'approved')
            ->assertJsonPath('data.approved_by', $wali->id);
    }

    private function seedRolesAndPermissions(): void
    {
        $roles = [
            [RoleNames::SISWA, 'Siswa'],
            [RoleNames::WALI_KELAS, 'Wali Kelas'],
            [RoleNames::WAKASEK_KESISWAAN, 'Wakasek Kesiswaan'],
            [RoleNames::ADMIN, 'Admin'],
            [RoleNames::GURU, 'Guru'],
        ];

        foreach ($roles as $index => [$name, $displayName]) {
            Role::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'display_name' => $displayName,
                    'description' => 'Role for izin integration test',
                    'level' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        $permissions = [
            ['submit_izin', 'Submit Izin', 'izin'],
            ['approve_izin', 'Approve Izin', 'izin'],
            ['view_all_izin', 'View All Izin', 'izin'],
        ];

        foreach ($permissions as [$name, $displayName, $module]) {
            Permission::updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'display_name' => $displayName,
                    'description' => 'Permission for izin integration test',
                    'module' => $module,
                ]
            );
        }

        Role::where('name', RoleNames::SISWA)->firstOrFail()
            ->syncPermissions(['submit_izin']);

        Role::where('name', RoleNames::ADMIN)->firstOrFail()
            ->syncPermissions(['approve_izin', 'view_all_izin']);
    }

    private function seedAcademicMasterData(): void
    {
        $this->tingkatId = DB::table('tingkat')->insertGetId([
            'nama' => 'Kelas X',
            'kode' => 'X',
            'deskripsi' => 'Tingkat test',
            'urutan' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->tahunAjaranId = DB::table('tahun_ajaran')->insertGetId([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'semester' => 'full',
            'is_active' => true,
            'status' => 'active',
            'preparation_progress' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createUserWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return $user;
    }

    private function createKelas(int $waliKelasId, string $namaKelas): Kelas
    {
        return Kelas::create([
            'nama_kelas' => $namaKelas,
            'tingkat_id' => $this->tingkatId,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'wali_kelas_id' => $waliKelasId,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
        ]);
    }

    private function assignSiswaToKelas(int $siswaId, int $kelasId): void
    {
        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelasId,
            'siswa_id' => $siswaId,
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'tanggal_masuk' => Carbon::today()->toDateString(),
            'status' => 'aktif',
            'keterangan' => null,
            'created_at' => now(),
            'updated_at' => now(),
            'is_active' => true,
        ]);
    }

    private function createPendingIzin(int $userId, int $kelasId): Izin
    {
        return $this->createIzin(
            $userId,
            $kelasId,
            Carbon::today()->addDay()->toDateString(),
            Carbon::today()->addDays(2)->toDateString(),
            'pending'
        );
    }

    private function createIzin(
        int $userId,
        int $kelasId,
        string $tanggalMulai,
        string $tanggalSelesai,
        string $status = 'pending',
        string $jenisIzin = 'izin',
        ?string $dokumenPendukung = null
    ): Izin {
        return Izin::create([
            'user_id' => $userId,
            'kelas_id' => $kelasId,
            'jenis_izin' => $jenisIzin,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'alasan' => 'Keperluan keluarga',
            'status' => $status,
            'dokumen_pendukung' => $dokumenPendukung,
        ]);
    }

    private function createDefaultAttendanceSchema(bool $isMandatory = true, ?bool $autoAlphaEnabled = true): AttendanceSchema
    {
        return AttendanceSchema::create([
            'schema_name' => $isMandatory ? 'Schema Wajib Siswa' : 'Schema Non Wajib Siswa',
            'schema_type' => 'global',
            'target_role' => RoleNames::SISWA,
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => $isMandatory,
            'auto_alpha_enabled' => $autoAlphaEnabled,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
        ]);
    }
}
