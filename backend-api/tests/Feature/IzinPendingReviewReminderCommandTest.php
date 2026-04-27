<?php

namespace Tests\Feature;

use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Notification;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IzinPendingReviewReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    private int $tingkatId;
    private int $tahunAjaranId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seedAcademicMasterData();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_overdue_pending_student_leave_creates_wali_and_wakasek_reminders_without_duplicates(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 10, 7, 10, 0));

        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $wakasek = $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelas = $this->createKelas($wali->id, 'X-IPA-R1');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $izin = $this->createPendingIzin(
            $siswa->id,
            $kelas->id,
            '2026-04-08',
            '2026-04-08'
        );

        $this->artisan('izin:send-pending-review-reminders', [
            '--date' => '2026-04-10',
        ])->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 2);
        $this->assertReminderExists($wali->id, $izin->id, 'overdue_escalation', '2026-04-10');
        $this->assertReminderExists($wakasek->id, $izin->id, 'overdue_escalation', '2026-04-10');

        $this->artisan('izin:send-pending-review-reminders', [
            '--date' => '2026-04-10',
        ])->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_due_today_pending_leave_only_reminds_wali_kelas(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 10, 7, 10, 0));

        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $siswa = $this->createUserWithRole(RoleNames::SISWA);

        $kelas = $this->createKelas($wali->id, 'X-IPA-R2');
        $this->assignSiswaToKelas($siswa->id, $kelas->id);

        $izin = $this->createPendingIzin(
            $siswa->id,
            $kelas->id,
            '2026-04-10',
            '2026-04-10'
        );

        $this->artisan('izin:send-pending-review-reminders', [
            '--date' => '2026-04-10',
        ])->assertExitCode(0);

        $this->assertDatabaseCount('notifications', 1);
        $this->assertReminderExists($wali->id, $izin->id, 'due_today', '2026-04-10');
    }

    private function seedRoles(): void
    {
        foreach ([RoleNames::SISWA, RoleNames::WALI_KELAS, RoleNames::WAKASEK_KESISWAAN] as $name) {
            Role::query()->updateOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                [
                    'display_name' => $name,
                    'description' => 'Role for pending izin reminder test',
                    'level' => 1,
                    'is_active' => true,
                ]
            );
        }
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

    private function createPendingIzin(int $userId, int $kelasId, string $tanggalMulai, string $tanggalSelesai): Izin
    {
        return Izin::create([
            'user_id' => $userId,
            'kelas_id' => $kelasId,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'alasan' => 'Keperluan keluarga',
            'status' => 'pending',
        ]);
    }

    private function assertReminderExists(int $targetUserId, int $izinId, string $reminderType, string $reminderDate): void
    {
        $notification = Notification::query()
            ->where('user_id', $targetUserId)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame($izinId, (int) data_get($notification?->data, 'izin_id'));
        $this->assertSame('izin_pending_review_reminder', data_get($notification?->data, 'message_category'));
        $this->assertSame($reminderType, data_get($notification?->data, 'reminder_type'));
        $this->assertSame($reminderDate, data_get($notification?->data, 'reminder_date'));
    }
}
