<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\AttendanceSchema;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportRoleScopeIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private Tingkat $tingkat;
    private TahunAjaran $tahunAjaran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $this->tingkat = Tingkat::create([
            'nama' => 'X',
            'kode' => 'X',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $this->tahunAjaran = TahunAjaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'semester' => 'full',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_wali_kelas_daily_report_is_scoped_to_own_class(): void
    {
        $waliA = User::factory()->create();
        $waliA->assignRole(RoleNames::WALI_KELAS);

        $waliB = User::factory()->create();
        $waliB->assignRole(RoleNames::WALI_KELAS);

        $kelasA = $this->createKelas('X-1', $waliA->id);
        $kelasB = $this->createKelas('X-2', $waliB->id);

        $siswaA = $this->createSiswa('report_scope_siswa_1', 'RS001');
        $siswaB = $this->createSiswa('report_scope_siswa_2', 'RS002');

        $tanggal = '2026-08-10';
        $this->createAbsensi($siswaA->id, $kelasA->id, $tanggal, 'hadir');
        $this->createAbsensi($siswaB->id, $kelasB->id, $tanggal, 'izin');

        $response = $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/reports/attendance/daily?tanggal={$tanggal}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total', 1);

        $detailUserIds = collect($response->json('data.detail'))
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        $this->assertSame([$siswaA->id], $detailUserIds);

        $this->actingAs($waliA, 'sanctum')
            ->getJson("/api/reports/attendance/daily?tanggal={$tanggal}&kelas_id={$kelasB->id}")
            ->assertStatus(403);
    }

    public function test_wakasek_kesiswaan_daily_report_can_see_cross_class_data(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        $wali = User::factory()->create();
        $wali->assignRole(RoleNames::WALI_KELAS);

        $kelasA = $this->createKelas('XI-1', $wali->id);
        $kelasB = $this->createKelas('XI-2');

        $siswaA = $this->createSiswa('report_scope_siswa_3', 'RS003');
        $siswaB = $this->createSiswa('report_scope_siswa_4', 'RS004');

        $tanggal = '2026-08-11';
        $this->createAbsensi($siswaA->id, $kelasA->id, $tanggal, 'hadir');
        $this->createAbsensi($siswaB->id, $kelasB->id, $tanggal, 'sakit');

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson("/api/reports/attendance/daily?tanggal={$tanggal}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total', 2);
    }

    public function test_guru_with_view_reports_permission_is_scoped_by_active_teaching_schedule(): void
    {
        $guru = User::factory()->create();
        $guru->assignRole(RoleNames::GURU);
        $guru->givePermissionTo('view_reports');

        $kelasDiajar = $this->createKelas('XII-1');
        $kelasLain = $this->createKelas('XII-2');

        DB::table('jadwal_mengajar')->insert([
            'guru_id' => $guru->id,
            'kelas_id' => $kelasDiajar->id,
            'mata_pelajaran' => 'Matematika',
            'hari' => 'senin',
            'jam_mulai' => '07:00:00',
            'jam_selesai' => '08:00:00',
            'ruangan' => 'R1',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $siswaA = $this->createSiswa('report_scope_siswa_5', 'RS005');
        $siswaB = $this->createSiswa('report_scope_siswa_6', 'RS006');

        $tanggal = '2026-08-12';
        $this->createAbsensi($siswaA->id, $kelasDiajar->id, $tanggal, 'hadir');
        $this->createAbsensi($siswaB->id, $kelasLain->id, $tanggal, 'izin');

        $response = $this->actingAs($guru, 'sanctum')
            ->getJson("/api/reports/attendance/daily?tanggal={$tanggal}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total', 1);

        $detailKelasIds = collect($response->json('data.detail'))
            ->pluck('kelas_id')
            ->unique()
            ->values()
            ->all();

        $this->assertSame([$kelasDiajar->id], $detailKelasIds);
    }

    public function test_monthly_report_includes_alpha_minutes_summary(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        $kelas = $this->createKelas('X-ALPHA');
        $siswa = $this->createSiswa('report_scope_siswa_alpha', 'RSALPHA');

        $this->createAbsensi($siswa->id, $kelas->id, '2026-08-13', 'alpha');

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson('/api/reports/attendance/monthly?bulan=8&tahun=2026');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_alpha', 1)
            ->assertJsonPath('data.summary.total_alpha_menit', 480)
            ->assertJsonPath('data.summary.total_alpa_menit', 480)
            ->assertJsonPath('data.summary.total_pelanggaran_menit', 480)
            ->assertJsonPath('data.summary.melewati_batas_pelanggaran', true);
    }

    public function test_daily_report_late_minutes_follow_effective_schema_working_hours(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        AttendanceSchema::create([
            'schema_name' => 'Default Schema',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00',
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
            'violation_minutes_threshold' => 480,
            'violation_percentage_threshold' => 10.00,
        ]);

        $kelas = $this->createKelas('X-LATE');
        $siswa = $this->createSiswa('report_scope_siswa_late', 'RSLATE');
        $tanggal = '2026-08-14';

        Absensi::create([
            'user_id' => $siswa->id,
            'kelas_id' => $kelas->id,
            'tanggal' => $tanggal,
            'jam_masuk' => '07:30:00',
            'status' => 'terlambat',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson("/api/reports/attendance/daily?tanggal={$tanggal}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.terlambat_menit', 30);

        $this->assertSame(30, (int) data_get($response->json('data.detail.0'), 'terlambat_menit'));
    }

    public function test_daily_report_returns_tap_day_and_minute_metrics_for_missing_checkout(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        AttendanceSchema::create([
            'schema_name' => 'Default Schema TAP',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00',
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
            'violation_minutes_threshold' => 480,
            'violation_percentage_threshold' => 10.00,
        ]);

        $kelas = $this->createKelas('X-TAP');
        $siswa = $this->createSiswa('report_scope_siswa_tap', 'RSTAP');
        $tanggal = '2026-08-18';

        Absensi::create([
            'user_id' => $siswa->id,
            'kelas_id' => $kelas->id,
            'tanggal' => $tanggal,
            'jam_masuk' => '07:00:00',
            'jam_pulang' => null,
            'status' => 'hadir',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson("/api/reports/attendance/daily?tanggal={$tanggal}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.tap_hari', 1)
            ->assertJsonPath('data.summary.tap_menit', 210);

        $this->assertSame(1, (int) data_get($response->json('data.detail.0'), 'tap_hari'));
        $this->assertSame(210, (int) data_get($response->json('data.detail.0'), 'tap_menit'));
    }

    public function test_report_shows_belum_absen_for_active_students_but_export_excludes_them(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-19 10:00:00'));

        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        AttendanceSchema::create([
            'schema_name' => 'Default Schema Missing',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
        ]);

        $kelas = $this->createKelas('X-MISSING');
        $siswaHadir = $this->createSiswa('report_scope_siswa_present', 'RSPRESENT');
        $siswaBelumAbsen = $this->createSiswa('report_scope_siswa_missing', 'RSMISSING');

        $this->attachSiswaToKelas($siswaHadir, $kelas);
        $this->attachSiswaToKelas($siswaBelumAbsen, $kelas);
        $this->createAbsensi($siswaHadir->id, $kelas->id, '2026-08-19', 'hadir');

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson('/api/reports/attendance/daily?tanggal=2026-08-19&view=student_recap');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_hadir', 1)
            ->assertJsonPath('data.summary.total_belum_absen', 1);

        $missingRow = collect($response->json('data.detail'))
            ->firstWhere('user_id', $siswaBelumAbsen->id);

        $this->assertSame(1, (int) ($missingRow['belum_absen'] ?? 0));
        $this->assertSame(0, (int) ($missingRow['alpha'] ?? 0));

        $exportRequest = \Illuminate\Http\Request::create('/api/reports/export/excel', 'GET', [
            'start_date' => '2026-08-19',
            'end_date' => '2026-08-19',
            'format' => 'csv',
        ]);
        $exportRequest->setUserResolver(fn () => $wakasek);

        $controller = app(\App\Http\Controllers\Api\ReportController::class);
        $method = new \ReflectionMethod($controller, 'prepareExportDataset');
        $method->setAccessible(true);
        $dataset = $method->invoke($controller, $exportRequest);

        $this->assertCount(1, $dataset['rows']);
        $this->assertSame($siswaHadir->nama_lengkap, $dataset['rows']->first()['nama']);

        $belumAbsenExportRequest = \Illuminate\Http\Request::create('/api/reports/export/excel', 'GET', [
            'start_date' => '2026-08-19',
            'end_date' => '2026-08-19',
            'status' => 'belum_absen',
            'format' => 'csv',
        ]);
        $belumAbsenExportRequest->setUserResolver(fn () => $wakasek);

        $filteredDataset = $method->invoke($controller, $belumAbsenExportRequest);
        $this->assertCount(0, $filteredDataset['rows']);
    }

    public function test_daily_report_violation_percentage_uses_effective_schema_working_minutes_per_student(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        AttendanceSchema::create([
            'schema_name' => 'Default Schema',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '06:30:00',
            'siswa_jam_pulang' => '15:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
            'violation_minutes_threshold' => 480,
            'violation_percentage_threshold' => 10.00,
        ]);

        $kelas = $this->createKelas('X-VIOLATION');
        $siswa = $this->createSiswa('report_scope_siswa_violation', 'RSVIO');
        $tanggal = '2026-08-17';

        Absensi::create([
            'user_id' => $siswa->id,
            'kelas_id' => $kelas->id,
            'tanggal' => $tanggal,
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '15:00:00',
            'status' => 'terlambat',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson("/api/reports/attendance/daily?tanggal={$tanggal}");

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_pelanggaran_menit', 60)
            ->assertJsonPath('data.summary.total_menit_kerja', 510)
            ->assertJsonPath('data.detail.0.working_minutes_per_day', 510);

        $this->assertEquals(11.76, (float) data_get($response->json('data.summary'), 'persentase_pelanggaran'));
    }

    public function test_daily_report_resolves_kelas_name_from_active_student_class_when_absensi_kelas_null(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        $kelas = $this->createKelas('X-KELAS-FALLBACK');
        $siswa = $this->createSiswa('report_scope_siswa_kelas_fallback', 'RSKELAS');
        $this->attachSiswaToKelas($siswa, $kelas);

        $tanggal = '2026-08-15';

        Absensi::create([
            'user_id' => $siswa->id,
            'kelas_id' => null,
            'tanggal' => $tanggal,
            'jam_masuk' => '07:30:00',
            'status' => 'terlambat',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson("/api/reports/attendance/daily?tanggal={$tanggal}");

        $response->assertStatus(200)
            ->assertJsonPath('data.detail.0.kelas_nama', 'X-KELAS-FALLBACK');
    }

    public function test_status_filter_only_filters_detail_but_keeps_monthly_summary_from_full_period_dataset(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        $kelas = $this->createKelas('X-FILTER-SUMMARY');
        $siswa = $this->createSiswa('report_scope_filter_summary', 'RSFILTER');

        $this->createAbsensi($siswa->id, $kelas->id, '2026-08-04', 'hadir');
        $this->createAbsensi($siswa->id, $kelas->id, '2026-08-05', 'alpha');

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson('/api/reports/attendance/monthly?bulan=8&tahun=2026&status=alpha&view=student_recap');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_hadir', 1)
            ->assertJsonPath('data.summary.total_alpha', 1)
            ->assertJsonPath('data.summary.total_pelanggaran_menit', 480)
            ->assertJsonPath('data.detail.0.nama', $siswa->nama_lengkap);

        $this->assertSame(1, count($response->json('data.detail')));
    }

    public function test_csv_export_defaults_to_student_recap_when_view_not_provided(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        $kelas = $this->createKelas('X-EXPORT-RECAP');
        $siswa = $this->createSiswa('report_scope_export_recap', 'RSEXP');

        $this->createAbsensi($siswa->id, $kelas->id, '2026-08-04', 'hadir');
        $this->createAbsensi($siswa->id, $kelas->id, '2026-08-05', 'alpha');

        $request = \Illuminate\Http\Request::create('/api/reports/export/excel', 'GET', [
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-31',
            'format' => 'csv',
        ]);
        $request->setUserResolver(fn () => $wakasek);

        $controller = app(\App\Http\Controllers\Api\ReportController::class);
        $method = new \ReflectionMethod($controller, 'prepareExportDataset');
        $method->setAccessible(true);
        $dataset = $method->invoke($controller, $request);

        $this->assertCount(1, $dataset['rows']);
        $this->assertSame($siswa->nama_lengkap, $dataset['rows']->first()['nama']);
        $this->assertSame(1, $dataset['rows']->first()['hadir']);
        $this->assertSame('1 (480m)', $dataset['rows']->first()['alpha']);
        $this->assertSame('0 (0m)', $dataset['rows']->first()['tap']);
        $this->assertArrayHasKey('status_batas', $dataset['rows']->first());
        $this->assertArrayHasKey('discipline_limit_summary', $dataset['meta']);
        $this->assertStringContainsString('Telat', $dataset['meta']['discipline_limit_summary']);
    }

    public function test_student_recap_attendance_percentage_uses_working_days_in_period(): void
    {
        $wakasek = User::factory()->create();
        $wakasek->assignRole(RoleNames::WAKASEK_KESISWAAN);

        AttendanceSchema::create([
            'schema_name' => 'Default Schema Recap',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '14:00:00',
            'siswa_toleransi' => 10,
            'minimal_open_time_siswa' => 70,
            'violation_minutes_threshold' => 480,
            'violation_percentage_threshold' => 10.00,
        ]);

        $kelas = $this->createKelas('X-RECAP-WORKING-DAY');
        $siswa = $this->createSiswa('report_scope_recap_working_day', 'RSWD');

        $this->createAbsensi($siswa->id, $kelas->id, '2026-08-03', 'hadir');
        $this->createAbsensi($siswa->id, $kelas->id, '2026-08-04', 'terlambat');

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson('/api/reports/attendance/range?start_date=2026-08-03&end_date=2026-08-08&view=student_recap');

        $response->assertStatus(200)
            ->assertJsonPath('data.detail.0.total_hari_kerja', 5)
            ->assertJsonPath('data.detail.0.hadir', 2);

        $this->assertEquals(40.0, (float) data_get($response->json('data.detail.0'), 'persentase_kehadiran'));
    }

    private function createKelas(string $namaKelas, ?int $waliKelasId = null): Kelas
    {
        return Kelas::create([
            'nama_kelas' => $namaKelas,
            'tingkat_id' => $this->tingkat->id,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'wali_kelas_id' => $waliKelasId,
            'kapasitas' => 36,
            'is_active' => true,
        ]);
    }

    private function createSiswa(string $username, string $identity): User
    {
        $siswa = User::factory()->create([
            'username' => $username,
            'email' => "{$username}@example.test",
            'nis' => $identity,
            'nisn' => $identity,
        ]);
        $siswa->assignRole(RoleNames::SISWA);

        return $siswa;
    }

    private function attachSiswaToKelas(User $siswa, Kelas $kelas): void
    {
        $siswa->kelas()->attach($kelas->id, [
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createAbsensi(int $userId, int $kelasId, string $tanggal, string $status): Absensi
    {
        $normalizedStatus = strtolower($status);

        return Absensi::create([
            'user_id' => $userId,
            'kelas_id' => $kelasId,
            'tanggal' => $tanggal,
            'status' => $status,
            'metode_absensi' => 'manual',
            'jam_masuk' => in_array($normalizedStatus, ['izin', 'sakit', 'alpha'], true) ? null : '07:00:00',
            'jam_pulang' => in_array($normalizedStatus, ['hadir', 'terlambat'], true) ? '14:00:00' : null,
        ]);
    }
}
