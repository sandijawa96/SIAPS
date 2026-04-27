<?php

namespace Tests\Feature;

use App\Jobs\DispatchAttendanceWhatsappNotification;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Services\ManualAttendanceIncidentService;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManualAttendanceWhatsappQueueTest extends TestCase
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

    public function test_single_manual_create_queues_attendance_whatsapp_notification(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->assignRole($this->ensureRole(RoleNames::ADMIN));
        $admin->givePermissionTo($this->ensurePermission('manual_attendance'));

        $student = User::factory()->create();
        $student->assignRole($this->ensureRole(RoleNames::SISWA));

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manual-attendance/create', [
                'user_id' => $student->id,
                'tanggal' => now()->toDateString(),
                'jam_masuk' => '07:05',
                'status' => 'hadir',
                'reason' => 'Manual create from operator',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true);

        Queue::assertPushed(DispatchAttendanceWhatsappNotification::class, function (DispatchAttendanceWhatsappNotification $job) use ($student) {
            $attendance = Absensi::query()
                ->where('user_id', $student->id)
                ->whereDate('tanggal', now()->toDateString())
                ->first();

            return $attendance !== null
                && $job->attendanceId === (int) $attendance->id
                && $job->event === 'checkin'
                && $job->allowManual === true
                && $job->queue === DispatchAttendanceWhatsappNotification::QUEUE_NAME;
        });
    }

    public function test_bulk_manual_create_queues_attendance_whatsapp_notifications(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        $admin->assignRole($this->ensureRole(RoleNames::ADMIN));
        $admin->givePermissionTo($this->ensurePermission('manual_attendance'));

        $student = User::factory()->create();
        $student->assignRole($this->ensureRole(RoleNames::SISWA));

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manual-attendance/bulk-create', [
                'attendance_list' => [[
                    'user_id' => $student->id,
                    'tanggal' => now()->toDateString(),
                    'jam_masuk' => '07:05',
                    'status' => 'hadir',
                    'reason' => 'Bulk create from operator',
                ]],
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        Queue::assertPushed(DispatchAttendanceWhatsappNotification::class, function (DispatchAttendanceWhatsappNotification $job) use ($student) {
            $attendance = Absensi::query()
                ->where('user_id', $student->id)
                ->whereDate('tanggal', now()->toDateString())
                ->first();

            return $attendance !== null
                && $job->attendanceId === (int) $attendance->id
                && $job->event === 'checkin'
                && $job->allowManual === true
                && $job->queue === DispatchAttendanceWhatsappNotification::QUEUE_NAME;
        });
    }

    public function test_incident_batch_processing_queues_attendance_whatsapp_notifications(): void
    {
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-04-08 08:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole($this->ensureRole(RoleNames::ADMIN));

        [$kelas, $tahunAjaran] = $this->createClassContext($admin);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Incident',
            'email' => 'incident@example.test',
            'is_active' => true,
        ]);
        $student->assignRole($this->ensureRole(RoleNames::SISWA));
        $this->attachStudentToClass($student, $kelas, $tahunAjaran);

        $incidentService = app(ManualAttendanceIncidentService::class);
        $batch = $incidentService->createBatch($admin, [
            'tanggal' => now()->toDateString(),
            'scope_type' => 'classes',
            'kelas_ids' => [$kelas->id],
            'status' => 'hadir',
            'jam_masuk' => '07:05',
            'reason' => 'Gangguan server saat jam masuk',
        ]);

        $incidentService->processBatch($batch->id);

        Queue::assertPushed(DispatchAttendanceWhatsappNotification::class, function (DispatchAttendanceWhatsappNotification $job) use ($student) {
            $attendance = Absensi::query()
                ->where('user_id', $student->id)
                ->whereDate('tanggal', now()->toDateString())
                ->first();

            return $attendance !== null
                && $job->attendanceId === (int) $attendance->id
                && $job->event === 'checkin'
                && $job->allowManual === true
                && $job->queue === DispatchAttendanceWhatsappNotification::QUEUE_NAME;
        });
    }

    private function ensureRole(string $name): Role
    {
        return Role::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            [
                'display_name' => $name,
                'description' => $name . ' role for manual attendance WA test',
                'level' => 0,
                'is_active' => true,
            ]
        );
    }

    private function ensurePermission(string $name): Permission
    {
        return Permission::firstOrCreate(
            ['name' => $name, 'guard_name' => 'web'],
            [
                'display_name' => $name,
                'description' => $name . ' permission for manual attendance WA test',
                'module' => 'attendance',
            ]
        );
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
                    'description' => 'Role for manual attendance WA test',
                    'level' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    private function createClassContext(User $waliKelas): array
    {
        $tingkat = Tingkat::create([
            'nama' => 'Kelas 10',
            'kode' => 'X',
            'deskripsi' => 'Tingkat untuk testing',
            'urutan' => 10,
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
            'nama_kelas' => 'X-A',
            'tingkat_id' => $tingkat->id,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $tahunAjaran->id,
            'wali_kelas_id' => $waliKelas->id,
            'kapasitas' => 36,
            'jumlah_siswa' => 1,
            'is_active' => true,
        ]);

        return [$kelas, $tahunAjaran];
    }

    private function attachStudentToClass(User $student, Kelas $kelas, TahunAjaran $tahunAjaran): void
    {
        $student->kelas()->attach($kelas->id, [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
