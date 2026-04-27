<?php

namespace Tests\Feature;

use App\Exports\ManualAttendanceIncidentBatchExport;
use App\Models\Absensi;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\ManualAttendanceIncidentBatch;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualAttendanceIncidentBatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRequiredRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_incident_preview_counts_existing_leave_and_missing_students(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 08:00:00'));

        $admin = $this->createAdminPerformer();
        [$kelas, $tahunAjaran] = $this->createClassContext($admin);

        $existingStudent = $this->createStudentInClass($kelas, $tahunAjaran, 'Siswa Existing');
        $leaveStudent = $this->createStudentInClass($kelas, $tahunAjaran, 'Siswa Leave');
        $missingStudent = $this->createStudentInClass($kelas, $tahunAjaran, 'Siswa Missing');

        Absensi::create([
            'user_id' => $existingStudent->id,
            'kelas_id' => $kelas->id,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
            'metode_absensi' => 'gps',
            'is_manual' => false,
        ]);

        Izin::create([
            'user_id' => $leaveStudent->id,
            'kelas_id' => $kelas->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'alasan' => 'Sakit keluarga',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/manual-attendance/incidents/preview', [
            'tanggal' => now()->toDateString(),
            'scope_type' => 'classes',
            'kelas_ids' => [$kelas->id],
            'status' => 'hadir',
            'reason' => 'Gangguan server saat jam masuk',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total_scope_users', 3)
            ->assertJsonPath('data.existing_attendance_count', 1)
            ->assertJsonPath('data.approved_leave_count', 1)
            ->assertJsonPath('data.eligible_missing_count', 1);

        $sampleIds = collect($response->json('data.sample_eligible_students'))->pluck('id')->all();
        $this->assertSame([$missingStudent->id], $sampleIds);
    }

    public function test_incident_batch_creates_attendance_only_for_missing_students_when_run_sync(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 08:00:00'));
        config(['queue.default' => 'sync']);

        $admin = $this->createAdminPerformer();
        [$kelas, $tahunAjaran] = $this->createClassContext($admin);

        $existingStudent = $this->createStudentInClass($kelas, $tahunAjaran, 'Siswa Existing');
        $missingStudent = $this->createStudentInClass($kelas, $tahunAjaran, 'Siswa Missing');

        Absensi::create([
            'user_id' => $existingStudent->id,
            'kelas_id' => $kelas->id,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
            'metode_absensi' => 'gps',
            'is_manual' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/manual-attendance/incidents', [
            'tanggal' => now()->toDateString(),
            'scope_type' => 'classes',
            'kelas_ids' => [$kelas->id],
            'status' => 'hadir',
            'jam_masuk' => '07:05',
            'reason' => 'Gangguan server saat jam masuk',
        ]);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.created_count', 1)
            ->assertJsonPath('data.skipped_existing_count', 1);

        $this->assertDatabaseHas('absensi', [
            'user_id' => $missingStudent->id,
            'tanggal' => now()->toDateString(),
            'is_manual' => true,
            'status' => 'hadir',
        ]);

        $this->assertDatabaseHas('manual_attendance_incident_batch_items', [
            'batch_id' => $response->json('data.id'),
            'user_id' => $missingStudent->id,
            'result_code' => 'created',
        ]);

        $this->assertDatabaseHas('manual_attendance_incident_batch_items', [
            'batch_id' => $response->json('data.id'),
            'user_id' => $existingStudent->id,
            'result_code' => 'skipped_existing',
        ]);

        $this->assertEquals(1, Absensi::query()
            ->where('user_id', $existingStudent->id)
            ->whereDate('tanggal', now()->toDateString())
            ->count());
    }

    public function test_incident_preview_supports_scope_by_level(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 08:00:00'));

        $admin = $this->createAdminPerformer();
        [$kelasLevelOne, $tahunAjaran] = $this->createClassContext($admin, 'Kelas 10', 'X', 'X Level 1');
        [$kelasLevelTwo] = $this->createClassContext($admin, 'Kelas 11', 'XI', 'XI Level 2');

        $studentLevelOne = $this->createStudentInClass($kelasLevelOne, $tahunAjaran, 'Siswa Tingkat 10');
        $this->createStudentInClass($kelasLevelTwo, $tahunAjaran, 'Siswa Tingkat 11');

        $response = $this->actingAs($admin, 'sanctum')->postJson('/api/manual-attendance/incidents/preview', [
            'tanggal' => now()->toDateString(),
            'scope_type' => 'levels',
            'tingkat_ids' => [$kelasLevelOne->tingkat_id],
            'status' => 'hadir',
            'reason' => 'Gangguan server tingkat tertentu',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total_scope_users', 1)
            ->assertJsonPath('data.eligible_missing_count', 1);

        $sampleIds = collect($response->json('data.sample_eligible_students'))->pluck('id')->all();
        $this->assertSame([$studentLevelOne->id], $sampleIds);
    }

    public function test_incident_batch_export_returns_downloadable_file(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 08:00:00'));
        config(['queue.default' => 'sync']);

        $admin = $this->createAdminPerformer();
        [$kelas, $tahunAjaran] = $this->createClassContext($admin);
        $this->createStudentInClass($kelas, $tahunAjaran, 'Siswa Export');

        $createResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/manual-attendance/incidents', [
            'tanggal' => now()->toDateString(),
            'scope_type' => 'classes',
            'kelas_ids' => [$kelas->id],
            'status' => 'hadir',
            'jam_masuk' => '07:05',
            'reason' => 'Gangguan server saat jam masuk',
        ]);

        $batchId = (int) $createResponse->json('data.id');

        $response = $this->actingAs($admin, 'sanctum')->get("/api/manual-attendance/incidents/{$batchId}/export?format=csv");

        $response->assertOk();
        $this->assertStringContainsString(
            "manual-attendance-incident-batch-{$batchId}",
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_recent_incident_batch_list_returns_latest_batches_for_user_scope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 08:00:00'));

        $admin = $this->createAdminPerformer();
        [$kelas, $tahunAjaran] = $this->createClassContext($admin);
        $this->createStudentInClass($kelas, $tahunAjaran, 'Siswa Recent Batch');

        $this->actingAs($admin, 'sanctum')->postJson('/api/manual-attendance/incidents', [
            'tanggal' => now()->toDateString(),
            'scope_type' => 'classes',
            'kelas_ids' => [$kelas->id],
            'status' => 'hadir',
            'reason' => 'Gangguan server batch pertama',
        ])->assertStatus(202);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/manual-attendance/incidents?limit=5');

        $response->assertOk()
            ->assertJsonPath('data.0.scope_type', 'classes')
            ->assertJsonPath('data.0.tanggal', now()->toDateString());
    }

    public function test_incident_batch_export_can_filter_result_group(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 08:00:00'));
        config(['queue.default' => 'sync']);

        $admin = $this->createAdminPerformer();
        [$kelas, $tahunAjaran] = $this->createClassContext($admin);

        $existingStudent = $this->createStudentInClass($kelas, $tahunAjaran, 'Siswa Existing Export');
        $this->createStudentInClass($kelas, $tahunAjaran, 'Siswa Missing Export');

        Absensi::create([
            'user_id' => $existingStudent->id,
            'kelas_id' => $kelas->id,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
            'metode_absensi' => 'gps',
            'is_manual' => false,
        ]);

        $createResponse = $this->actingAs($admin, 'sanctum')->postJson('/api/manual-attendance/incidents', [
            'tanggal' => now()->toDateString(),
            'scope_type' => 'classes',
            'kelas_ids' => [$kelas->id],
            'status' => 'hadir',
            'reason' => 'Gangguan server saat jam masuk',
        ]);

        $batchId = (int) $createResponse->json('data.id');

        $batch = ManualAttendanceIncidentBatch::query()->findOrFail($batchId);
        $exportRows = (new ManualAttendanceIncidentBatchExport($batch, 'failed', ['failed']))->array();
        $flatRows = collect($exportRows)->flatten()->filter()->values()->all();

        $this->assertContains('Filter Export', $flatRows);
        $this->assertContains('Gagal Saja', $flatRows);
        $this->assertNotContains('Siswa Existing Export', $flatRows);
    }

    public function test_incident_endpoints_are_web_only_for_mobile_clients(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 08:00:00'));

        $admin = $this->createAdminPerformer();

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeaders([
                'X-Client-App' => 'mobileapp',
                'X-Client-Platform' => 'mobile',
            ])
            ->getJson('/api/manual-attendance/incidents');

        $response->assertForbidden()
            ->assertJsonPath('message', 'Insiden Server hanya tersedia di aplikasi web.');
    }

    private function createAdminPerformer(): User
    {
        $admin = User::factory()->create(['nama_lengkap' => 'Admin Incident']);
        $admin->assignRole(RoleNames::ADMIN);

        Permission::firstOrCreate(
            ['name' => 'manual_attendance', 'guard_name' => 'web'],
            ['display_name' => 'Manual Attendance', 'module' => 'attendance']
        );

        $admin->givePermissionTo('manual_attendance');

        return $admin;
    }

    private function createStudentInClass(Kelas $kelas, TahunAjaran $tahunAjaran, string $name): User
    {
        $student = User::factory()->create(['nama_lengkap' => $name, 'is_active' => true]);
        $student->assignRole(RoleNames::SISWA);
        $student->kelas()->attach($kelas->id, [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $student;
    }

    private function createClassContext(
        User $waliKelas,
        string $tingkatNama = 'Kelas 10',
        string $tingkatKode = 'X',
        string $kelasNama = 'X Incident'
    ): array
    {
        $tingkat = Tingkat::create([
            'nama' => $tingkatNama,
            'kode' => $tingkatKode,
            'deskripsi' => 'Tingkat untuk testing',
            'urutan' => (int) preg_replace('/\D+/', '', $tingkatKode) ?: 10,
            'is_active' => true,
        ]);

        $tahunAjaran = TahunAjaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'semester' => 'full',
            'is_active' => true,
            'status' => 'active',
            'preparation_progress' => 100,
        ]);

        $kelas = Kelas::create([
            'nama_kelas' => $kelasNama,
            'tingkat_id' => $tingkat->id,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $tahunAjaran->id,
            'wali_kelas_id' => $waliKelas->id,
            'kapasitas' => 40,
            'jumlah_siswa' => 3,
            'is_active' => true,
        ]);

        return [$kelas, $tahunAjaran];
    }

    private function seedRequiredRoles(): void
    {
        $requiredRoles = [
            RoleNames::SUPER_ADMIN,
            RoleNames::ADMIN,
            RoleNames::WAKASEK_KESISWAAN,
            RoleNames::WALI_KELAS,
            RoleNames::GURU,
            RoleNames::SISWA,
        ];

        foreach ($requiredRoles as $index => $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'display_name' => $roleName,
                    'description' => 'Role for manual attendance incident tests',
                    'level' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
