<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Services\ManualAttendanceService;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ManualAttendanceAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private ManualAttendanceService $manualAttendanceService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manualAttendanceService = app(ManualAttendanceService::class);
        $this->seedRequiredRoles();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_wali_kelas_only_sees_manual_attendance_for_students_in_managed_classes(): void
    {
        $waliKelas = User::factory()->create();
        $waliKelas->assignRole(RoleNames::WALI_KELAS);

        $siswaInClass = User::factory()->create();
        $siswaInClass->assignRole(RoleNames::SISWA);

        $siswaOutsideClass = User::factory()->create();
        $siswaOutsideClass->assignRole(RoleNames::SISWA);

        [$kelas, $tahunAjaran] = $this->createClassContext($waliKelas);
        $this->attachStudentToClass($siswaInClass, $kelas, $tahunAjaran);

        $this->assertTrue($waliKelas->hasRole(RoleNames::WALI_KELAS));
        $this->assertSame([$kelas->id], $waliKelas->kelasWali()->pluck('id')->values()->all());
        $this->assertTrue($siswaInClass->hasRole(RoleNames::SISWA));
        $this->assertTrue(
            $siswaInClass->kelas()
                ->where('kelas.id', $kelas->id)
                ->wherePivot('is_active', true)
                ->exists()
        );

        $this->createManualAttendance($siswaInClass->id, now()->toDateString(), $kelas->id);
        $this->createManualAttendance($siswaOutsideClass->id, now()->toDateString());

        $visibleUserIds = $this->manualAttendanceService
            ->buildManualAttendanceQuery($waliKelas)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $this->assertSame([$siswaInClass->id], $visibleUserIds);
    }

    public function test_wali_kelas_can_manage_only_students_in_its_active_classes(): void
    {
        $waliKelas = User::factory()->create();
        $waliKelas->assignRole(RoleNames::WALI_KELAS);

        $siswaInClass = User::factory()->create();
        $siswaInClass->assignRole(RoleNames::SISWA);

        $siswaOutsideClass = User::factory()->create();
        $siswaOutsideClass->assignRole(RoleNames::SISWA);

        [$kelas, $tahunAjaran] = $this->createClassContext($waliKelas);
        $this->attachStudentToClass($siswaInClass, $kelas, $tahunAjaran);

        $this->assertTrue(
            $this->manualAttendanceService->canManageAttendanceForUser($waliKelas, $siswaInClass)
        );
        $this->assertFalse(
            $this->manualAttendanceService->canManageAttendanceForUser($waliKelas, $siswaOutsideClass)
        );
    }

    public function test_admin_level_visibility_excludes_super_admin_and_admin_targets(): void
    {
        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(RoleNames::SUPER_ADMIN);

        $adminTarget = User::factory()->create();
        $adminTarget->assignRole(RoleNames::ADMIN);

        $regularStudent = User::factory()->create();
        $regularStudent->assignRole(RoleNames::SISWA);

        $this->createManualAttendance($adminTarget->id, now()->toDateString());
        $this->createManualAttendance($regularStudent->id, now()->subDay()->toDateString());

        $visibleUserIds = $this->manualAttendanceService
            ->buildManualAttendanceQuery($superAdmin)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $this->assertNotContains($adminTarget->id, $visibleUserIds);
        $this->assertContains($regularStudent->id, $visibleUserIds);
    }

    public function test_wakasek_kesiswaan_can_manage_all_students_but_not_non_students(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        $siswa = User::factory()->create();
        $siswa->assignRole(RoleNames::SISWA);

        $guru = User::factory()->create();
        $guru->assignRole(RoleNames::GURU);

        $this->assertTrue($this->manualAttendanceService->canManageAttendanceForUser($wakasek, $siswa));
        $this->assertFalse($this->manualAttendanceService->canManageAttendanceForUser($wakasek, $guru));

        $manageableUserIds = collect($this->manualAttendanceService->getManageableUsers($wakasek))
            ->pluck('id')
            ->all();

        $this->assertContains($siswa->id, $manageableUserIds);
        $this->assertNotContains($guru->id, $manageableUserIds);
    }

    public function test_search_manageable_users_supports_identifier_lookup_for_mobile(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Mobile Search',
            'nis' => '2400111',
            'username' => 'siswa.mobile',
        ]);
        $student->assignRole(RoleNames::SISWA);

        $results = $this->manualAttendanceService->searchManageableUsers($wakasek, '2400111');

        $this->assertCount(1, $results);
        $this->assertSame($student->id, $results[0]['id']);
        $this->assertSame('2400111', $results[0]['identifier']);
    }

    public function test_admin_can_view_auto_alpha_bucket_without_mixing_manual_entries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $siswa = User::factory()->create();
        $siswa->assignRole(RoleNames::SISWA);

        $manualAttendance = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $autoAlphaAttendance = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDay()->toDateString(),
            'status' => 'alpha',
            'metode_absensi' => 'manual',
            'is_manual' => false,
        ]);

        $autoAlphaIds = $this->manualAttendanceService
            ->buildManualAttendanceQuery($admin, ['bucket' => 'auto_alpha'])
            ->pluck('id')
            ->all();

        $manualIds = $this->manualAttendanceService
            ->buildManualAttendanceQuery($admin)
            ->pluck('id')
            ->all();

        $this->assertContains($autoAlphaAttendance->id, $autoAlphaIds);
        $this->assertNotContains($manualAttendance->id, $autoAlphaIds);
        $this->assertContains($manualAttendance->id, $manualIds);
        $this->assertNotContains($autoAlphaAttendance->id, $manualIds);
    }

    public function test_admin_correction_bucket_includes_existing_manual_and_realtime_rows(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $siswa = User::factory()->create();
        $siswa->assignRole(RoleNames::SISWA);

        $manualAttendance = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $realtimeAttendance = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDay()->toDateString(),
            'status' => 'terlambat',
            'metode_absensi' => 'gps',
            'is_manual' => false,
        ]);

        $correctionIds = $this->manualAttendanceService
            ->buildManualAttendanceQuery($admin, ['bucket' => 'correction'])
            ->pluck('id')
            ->all();

        $this->assertContains($manualAttendance->id, $correctionIds);
        $this->assertContains($realtimeAttendance->id, $correctionIds);
    }

    public function test_pending_checkout_list_defaults_to_h_plus_one_records(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10 08:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $siswa = User::factory()->create();
        $siswa->assignRole(RoleNames::SISWA);

        $yesterdayAttendance = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDay()->toDateString(),
            'jam_masuk' => now()->subDay()->setTime(7, 5)->format('H:i:s'),
            'status' => 'hadir',
        ]);
        $olderAttendance = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDays(3)->toDateString(),
            'jam_masuk' => now()->subDays(3)->setTime(7, 15)->format('H:i:s'),
            'status' => 'hadir',
        ]);

        $result = $this->manualAttendanceService->getPendingCheckoutHistory($admin, []);
        $ids = collect($result['data'])->pluck('id')->all();

        $this->assertContains($yesterdayAttendance->id, $ids);
        $this->assertNotContains($olderAttendance->id, $ids);
    }

    public function test_resolve_pending_checkout_h_plus_one_allows_wali_kelas_scope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 08:00:00'));

        $waliKelas = User::factory()->create();
        $waliKelas->assignRole(RoleNames::WALI_KELAS);

        $siswaInClass = User::factory()->create();
        $siswaInClass->assignRole(RoleNames::SISWA);

        [$kelas, $tahunAjaran] = $this->createClassContext($waliKelas);
        $this->attachStudentToClass($siswaInClass, $kelas, $tahunAjaran);

        $attendance = Absensi::create([
            'user_id' => $siswaInClass->id,
            'kelas_id' => $kelas->id,
            'tanggal' => now()->subDay()->toDateString(),
            'jam_masuk' => now()->subDay()->setTime(7, 10)->format('H:i:s'),
            'status' => 'hadir',
            'is_manual' => false,
        ]);

        $result = $this->manualAttendanceService->resolvePendingCheckout(
            $attendance->id,
            [
                'jam_pulang' => '15:30',
                'reason' => 'Follow up lupa tap-out H+1',
            ],
            $waliKelas->id
        );

        $this->assertTrue($result['success'], $result['message']);

        $attendance->refresh();
        $this->assertNotNull($attendance->jam_pulang);
        $this->assertTrue((bool) $attendance->is_manual);
    }

    public function test_resolve_pending_checkout_h_plus_n_requires_override_permission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 08:00:00'));

        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $siswa = User::factory()->create();
        $siswa->assignRole(RoleNames::SISWA);

        $attendance = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDays(3)->toDateString(),
            'jam_masuk' => now()->subDays(3)->setTime(7, 20)->format('H:i:s'),
            'status' => 'hadir',
        ]);

        $withoutOverride = $this->manualAttendanceService->resolvePendingCheckout(
            $attendance->id,
            [
                'jam_pulang' => '15:20',
                'reason' => 'Koreksi tap-out',
            ],
            $admin->id
        );

        $this->assertFalse($withoutOverride['success']);
        $this->assertStringContainsString('H+1', $withoutOverride['message']);

        Permission::firstOrCreate(
            [
                'name' => 'manual_attendance_backdate_override',
                'guard_name' => 'web',
            ],
            [
                'display_name' => 'Manual Attendance Backdate Override',
                'module' => 'attendance',
            ]
        );
        $admin->givePermissionTo('manual_attendance_backdate_override');

        $withOverride = $this->manualAttendanceService->resolvePendingCheckout(
            $attendance->id,
            [
                'jam_pulang' => '15:20',
                'reason' => 'Koreksi tap-out',
                'override_reason' => 'Validasi oleh wakasek kesiswaan',
            ],
            $admin->id
        );

        $this->assertTrue($withOverride['success'], $withOverride['message']);
    }

    public function test_statistics_alpha_minutes_follow_each_users_working_hours(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);
        Permission::firstOrCreate(
            ['name' => 'manual_attendance', 'guard_name' => 'web'],
            ['display_name' => 'Manual Attendance', 'module' => 'attendance']
        );
        $admin->givePermissionTo('manual_attendance');

        AttendanceSchema::create([
            'schema_name' => 'Global Default',
            'schema_type' => 'global',
            'is_active' => true,
            'is_default' => true,
            'jam_masuk_default' => '07:00',
            'jam_pulang_default' => '15:00',
            'toleransi_default' => 15,
            'siswa_jam_masuk' => '07:00',
            'siswa_jam_pulang' => '15:00',
            'siswa_toleransi' => 15,
            'hari_kerja' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
            'violation_minutes_threshold' => 480,
            'violation_percentage_threshold' => 10,
        ]);

        $siswaDefault = User::factory()->create();
        $siswaDefault->assignRole(RoleNames::SISWA);

        $siswaOverride = User::factory()->create();
        $siswaOverride->assignRole(RoleNames::SISWA);

        DB::table('user_attendance_overrides')->insert([
            'user_id' => $siswaOverride->id,
            'jam_masuk' => '07:00',
            'jam_pulang' => '12:00',
            'toleransi' => 15,
            'hari_kerja' => json_encode(['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
            'is_active' => true,
            'created_by' => $admin->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Absensi::create([
            'user_id' => $siswaDefault->id,
            'tanggal' => now()->subDay()->toDateString(),
            'status' => 'alpha',
            'metode_absensi' => 'manual',
            'is_manual' => false,
        ]);

        Absensi::create([
            'user_id' => $siswaOverride->id,
            'tanggal' => now()->subDays(2)->toDateString(),
            'status' => 'alpha',
            'metode_absensi' => 'manual',
            'is_manual' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/manual-attendance/statistics?bucket=auto_alpha');

        $response->assertStatus(200)
            ->assertJsonPath('data.by_status.alpha', 2)
            ->assertJsonPath('data.alpa_menit', 780);
    }

    public function test_statistics_correction_bucket_returns_breakdown_by_source(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);
        Permission::firstOrCreate(
            ['name' => 'manual_attendance', 'guard_name' => 'web'],
            ['display_name' => 'Manual Attendance', 'module' => 'attendance']
        );
        $admin->givePermissionTo('manual_attendance');

        $siswa = User::factory()->create();
        $siswa->assignRole(RoleNames::SISWA);

        Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDay()->toDateString(),
            'status' => 'terlambat',
            'metode_absensi' => 'gps',
            'is_manual' => false,
        ]);

        Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDays(2)->toDateString(),
            'status' => 'alpha',
            'metode_absensi' => 'manual',
            'is_manual' => false,
        ]);

        $izin = Izin::create([
            'user_id' => $siswa->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => now()->subDays(3)->toDateString(),
            'tanggal_selesai' => now()->subDays(3)->toDateString(),
            'alasan' => 'Koreksi hasil approval izin',
            'status' => 'approved',
        ]);

        Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDays(3)->toDateString(),
            'status' => 'izin',
            'metode_absensi' => 'manual',
            'is_manual' => true,
            'izin_id' => $izin->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/manual-attendance/statistics?bucket=correction');

        $response->assertOk()
            ->assertJsonPath('data.by_source.manual', 1)
            ->assertJsonPath('data.by_source.realtime', 1)
            ->assertJsonPath('data.by_source.auto_alpha', 1)
            ->assertJsonPath('data.by_source.leave_approval', 1);
    }

    public function test_manual_attendance_creation_persists_schema_snapshot(): void
    {
        $admin = User::factory()->create(['nama_lengkap' => 'Admin Snapshot']);
        $admin->assignRole(RoleNames::ADMIN);

        Permission::firstOrCreate(
            ['name' => 'manual_attendance', 'guard_name' => 'web'],
            ['display_name' => 'Manual Attendance', 'module' => 'attendance']
        );
        $admin->givePermissionTo('manual_attendance');

        AttendanceSchema::create([
            'schema_name' => 'Global Snapshot Manual',
            'schema_type' => 'global',
            'is_active' => true,
            'is_default' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'toleransi_default' => 15,
            'siswa_jam_masuk' => '07:05:00',
            'siswa_jam_pulang' => '14:05:00',
            'siswa_toleransi' => 12,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'violation_minutes_threshold' => 480,
            'violation_percentage_threshold' => 10,
        ]);

        $siswa = User::factory()->create();
        $siswa->assignRole(RoleNames::SISWA);

        $result = $this->manualAttendanceService->createManualAttendance([
            'user_id' => $siswa->id,
            'tanggal' => '2026-03-10',
            'status' => 'hadir',
            'jam_masuk' => '07:15:00',
            'jam_pulang' => '14:10:00',
            'keterangan' => 'Manual test',
        ], $admin->id);

        $this->assertTrue($result['success'], $result['message'] ?? 'Manual attendance failed');

        $attendance = Absensi::query()->latest('id')->first();
        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->attendance_setting_id);
        $this->assertIsArray($attendance->settings_snapshot);
        $this->assertSame('07:05:00', data_get($attendance->settings_snapshot, 'working_hours.jam_masuk'));
        $this->assertSame('14:05:00', data_get($attendance->settings_snapshot, 'working_hours.jam_pulang'));
        $this->assertSame(12, (int) data_get($attendance->settings_snapshot, 'working_hours.toleransi'));
    }

    public function test_mobile_summary_returns_lightweight_manual_attendance_counts(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-05 08:00:00'));

        $admin = User::factory()->create(['nama_lengkap' => 'Admin Mobile Summary']);
        $admin->assignRole(RoleNames::ADMIN);

        Permission::firstOrCreate(
            ['name' => 'manual_attendance', 'guard_name' => 'web'],
            ['display_name' => 'Manual Attendance', 'module' => 'attendance']
        );
        Permission::firstOrCreate(
            ['name' => 'manual_attendance_backdate_override', 'guard_name' => 'web'],
            ['display_name' => 'Manual Attendance Backdate Override', 'module' => 'attendance']
        );

        $admin->givePermissionTo(['manual_attendance', 'manual_attendance_backdate_override']);

        $siswaOne = User::factory()->create(['nama_lengkap' => 'Siswa Summary One']);
        $siswaOne->assignRole(RoleNames::SISWA);

        $siswaTwo = User::factory()->create(['nama_lengkap' => 'Siswa Summary Two']);
        $siswaTwo->assignRole(RoleNames::SISWA);

        Absensi::create([
            'user_id' => $siswaOne->id,
            'tanggal' => now()->toDateString(),
            'status' => 'hadir',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        Absensi::create([
            'user_id' => $siswaTwo->id,
            'tanggal' => now()->toDateString(),
            'status' => 'terlambat',
            'jam_masuk' => '07:15:00',
            'metode_absensi' => 'gps',
            'is_manual' => false,
        ]);

        Absensi::create([
            'user_id' => $siswaOne->id,
            'tanggal' => now()->subDay()->toDateString(),
            'status' => 'hadir',
            'jam_masuk' => '07:10:00',
            'metode_absensi' => 'gps',
            'is_manual' => false,
        ]);

        Absensi::create([
            'user_id' => $siswaTwo->id,
            'tanggal' => now()->subDays(3)->toDateString(),
            'status' => 'hadir',
            'jam_masuk' => '07:20:00',
            'metode_absensi' => 'gps',
            'is_manual' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->withHeaders([
                'X-Client-App' => 'mobileapp',
                'X-Client-Platform' => 'mobile',
            ])
            ->getJson('/api/manual-attendance/mobile-summary');

        $response->assertOk()
            ->assertJsonPath('data.manageable_students_count', 2)
            ->assertJsonPath('data.manual_today_count', 1)
            ->assertJsonPath('data.correction_today_count', 2)
            ->assertJsonPath('data.pending_checkout_h_plus_one_count', 1)
            ->assertJsonPath('data.pending_checkout_overdue_count', 1)
            ->assertJsonPath('data.can_override_backdate', true);
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
                    'description' => 'Role for manual attendance authorization test',
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

    private function createManualAttendance(int $userId, string $tanggal, ?int $kelasId = null): void
    {
        Absensi::create([
            'user_id' => $userId,
            'kelas_id' => $kelasId,
            'tanggal' => $tanggal,
            'status' => 'hadir',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);
    }
}
