<?php

namespace Tests\Feature;

use App\Imports\SiswaImport;
use App\Models\Role;
use App\Models\SiswaTransisi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SiswaImportEmailGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::updateOrCreate(
            ['name' => 'Siswa', 'guard_name' => 'web'],
            [
                'display_name' => 'Siswa',
                'description' => 'Role for siswa import test',
                'level' => 1,
                'is_active' => true,
            ]
        );

        $tingkatId = DB::table('tingkat')->insertGetId([
            'nama' => 'Kelas X',
            'kode' => 'X',
            'deskripsi' => 'Tingkat test import siswa',
            'urutan' => 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tingkatXiId = DB::table('tingkat')->insertGetId([
            'nama' => 'Kelas XI',
            'kode' => 'XI',
            'deskripsi' => 'Tingkat lanjutan test import siswa',
            'urutan' => 2,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tahunAjaranId = DB::table('tahun_ajaran')->insertGetId([
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

        $tahunAjaranLanjutanId = DB::table('tahun_ajaran')->insertGetId([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'semester' => 'full',
            'is_active' => false,
            'status' => 'draft',
            'preparation_progress' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('kelas')->insert([
            'nama_kelas' => 'Kelas X-1',
            'tingkat_id' => $tingkatId,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $tahunAjaranId,
            'wali_kelas_id' => null,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('kelas')->insert([
            'nama_kelas' => 'Kelas X-2',
            'tingkat_id' => $tingkatId,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $tahunAjaranId,
            'wali_kelas_id' => null,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('kelas')->insert([
            'nama_kelas' => 'Kelas XI-1',
            'tingkat_id' => $tingkatXiId,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $tahunAjaranLanjutanId,
            'wali_kelas_id' => null,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('kelas')->insert([
            'nama_kelas' => 'Kelas XI-0',
            'tingkat_id' => $tingkatXiId,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $tahunAjaranId,
            'wali_kelas_id' => null,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_import_generates_student_email_from_nis_when_email_is_empty(): void
    {
        $import = new SiswaImport();

        $rows = new Collection([
            new Collection(['TEMPLATE IMPORT DATA SISWA']),
            new Collection(['Petunjuk']),
            new Collection([]),
            new Collection(['No', 'NIS*', 'Nama Lengkap*', 'Email', 'NISN*', 'Tanggal Lahir*', 'Jenis Kelamin*', 'Kelas*', 'Tahun Ajaran*', 'Tanggal Masuk*', 'No. Telepon Orang Tua*', 'Status']),
            new Collection([1, '252610999', 'SISWA IMPORT TEST', null, '0199999999', '2010-01-01', 'L', 'Kelas X-1', '2025/2026', '2025-07-02', '081234567890', 'Aktif']),
        ]);

        $import->collection($rows);

        $user = User::where('nis', '252610999')->first();

        $this->assertNotNull($user);
        $this->assertSame('252610999', $user->username);
        $this->assertSame('252610999@sman1sumbercirebon.sch.id', $user->email);
        $this->assertTrue($user->hasRole('Siswa'));
        $this->assertSame([], $import->getErrors());
    }

    public function test_import_same_tingkat_change_is_treated_as_correction_without_transition_history(): void
    {
        $kelasAsalId = (int) DB::table('kelas')->where('nama_kelas', 'Kelas X-1')->value('id');
        $kelasTujuanId = (int) DB::table('kelas')->where('nama_kelas', 'Kelas X-2')->value('id');
        $tahunAjaranId = (int) DB::table('tahun_ajaran')->where('nama', '2025/2026')->value('id');

        $user = User::create([
            'username' => '252610120',
            'email' => '252610120@sman1sumbercirebon.sch.id',
            'password' => bcrypt('12345678'),
            'nama_lengkap' => 'SISWA SEBELUM IMPORT',
            'nis' => '252610120',
            'nisn' => '0123456789',
            'jenis_kelamin' => 'L',
            'is_active' => true,
        ]);
        $user->assignRole('Siswa');

        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelasAsalId,
            'siswa_id' => $user->id,
            'tahun_ajaran_id' => $tahunAjaranId,
            'tanggal_masuk' => '2025-07-02',
            'tanggal_keluar' => null,
            'status' => 'aktif',
            'keterangan' => null,
            'created_at' => now()->subDays(10),
            'is_active' => true,
            'updated_at' => now()->subDays(10),
        ]);

        $import = new SiswaImport();
        $rows = new Collection([
            new Collection(['TEMPLATE IMPORT DATA SISWA']),
            new Collection(['Petunjuk']),
            new Collection([]),
            new Collection(['No', 'NIS*', 'Nama Lengkap*', 'Email', 'NISN*', 'Tanggal Lahir*', 'Jenis Kelamin*', 'Kelas*', 'Tahun Ajaran*', 'Tanggal Masuk*', 'No. Telepon Orang Tua*', 'Status']),
            // NIS berubah, NISN tetap -> harus match user existing via NISN.
            new Collection([1, '252610121', 'SISWA SETELAH IMPORT', null, '0123456789', '2010-01-01', 'L', 'Kelas X-2', '2025/2026', '2025-08-01', '081234567899', 'Aktif']),
        ]);

        $import->collection($rows);

        $user->refresh();
        $this->assertSame('252610121', $user->nis);
        $this->assertSame('252610121', $user->username);
        $this->assertSame('252610121@sman1sumbercirebon.sch.id', $user->email);
        $this->assertSame('SISWA SETELAH IMPORT', $user->nama_lengkap);

        $this->assertDatabaseMissing('kelas_siswa', [
            'siswa_id' => $user->id,
            'kelas_id' => $kelasAsalId,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('kelas_siswa', [
            'siswa_id' => $user->id,
            'kelas_id' => $kelasTujuanId,
            'tahun_ajaran_id' => $tahunAjaranId,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => '2025-08-01',
            'keterangan' => 'Koreksi data import: penyesuaian kelas tingkat sama',
        ]);

        $this->assertSame(
            0,
            DB::table('siswa_transisi')->where('siswa_id', $user->id)->count()
        );
        $this->assertSame(
            1,
            DB::table('kelas_siswa')->where('siswa_id', $user->id)->count()
        );

        $this->assertSame(0, $import->getSkipped());
        $this->assertSame([], $import->getErrors());
    }

    public function test_import_naik_tingkat_creates_history_and_transition_type_naik_kelas(): void
    {
        $kelasAsalId = (int) DB::table('kelas')->where('nama_kelas', 'Kelas X-1')->value('id');
        $kelasTujuanId = (int) DB::table('kelas')->where('nama_kelas', 'Kelas XI-1')->value('id');
        $tahunAjaranAsalId = (int) DB::table('tahun_ajaran')->where('nama', '2025/2026')->value('id');
        $tahunAjaranTujuanId = (int) DB::table('tahun_ajaran')->where('nama', '2026/2027')->value('id');

        $user = User::create([
            'username' => '252610130',
            'email' => '252610130@sman1sumbercirebon.sch.id',
            'password' => bcrypt('12345678'),
            'nama_lengkap' => 'SISWA NAIK KELAS',
            'nis' => '252610130',
            'nisn' => '0130000000',
            'jenis_kelamin' => 'L',
            'is_active' => true,
        ]);
        $user->assignRole('Siswa');

        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelasAsalId,
            'siswa_id' => $user->id,
            'tahun_ajaran_id' => $tahunAjaranAsalId,
            'tanggal_masuk' => '2025-07-10',
            'tanggal_keluar' => null,
            'status' => 'aktif',
            'keterangan' => null,
            'created_at' => now()->subMonths(3),
            'is_active' => true,
            'updated_at' => now()->subMonths(3),
        ]);

        $import = new SiswaImport();
        $rows = new Collection([
            new Collection(['TEMPLATE IMPORT DATA SISWA']),
            new Collection(['Petunjuk']),
            new Collection([]),
            new Collection(['No', 'NIS*', 'Nama Lengkap*', 'Email', 'NISN*', 'Tanggal Lahir*', 'Jenis Kelamin*', 'Kelas*', 'Tahun Ajaran*', 'Tanggal Masuk*', 'No. Telepon Orang Tua*', 'Status']),
            new Collection([1, '252610130', 'SISWA NAIK KELAS', null, '0130000000', '2010-01-02', 'L', 'Kelas XI-1', '2026/2027', '2026-07-05', '081200000001', 'Aktif']),
        ]);

        $import->collection($rows);

        $this->assertDatabaseHas('kelas_siswa', [
            'siswa_id' => $user->id,
            'kelas_id' => $kelasAsalId,
            'tahun_ajaran_id' => $tahunAjaranAsalId,
            'status' => 'pindah',
            'is_active' => false,
            'tanggal_keluar' => '2026-07-05',
        ]);

        $this->assertDatabaseHas('kelas_siswa', [
            'siswa_id' => $user->id,
            'kelas_id' => $kelasTujuanId,
            'tahun_ajaran_id' => $tahunAjaranTujuanId,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => '2026-07-05',
            'keterangan' => 'Naik kelas otomatis melalui import siswa',
        ]);

        $this->assertTrue(
            DB::table('siswa_transisi')
                ->where('siswa_id', $user->id)
                ->where('type', 'naik_kelas')
                ->where('kelas_asal_id', $kelasAsalId)
                ->where('kelas_tujuan_id', $kelasTujuanId)
                ->where('tahun_ajaran_id', $tahunAjaranTujuanId)
                ->whereDate('tanggal_transisi', '2026-07-05')
                ->exists()
        );

        $this->assertSame(0, $import->getSkipped());
        $this->assertSame([], $import->getErrors());
        $this->assertGreaterThanOrEqual(1, SiswaTransisi::query()->where('type', 'naik_kelas')->count());
    }

    public function test_import_naik_tingkat_rejects_same_or_lower_tahun_ajaran(): void
    {
        $kelasAsalId = (int) DB::table('kelas')->where('nama_kelas', 'Kelas X-1')->value('id');
        $kelasXiSameYearId = (int) DB::table('kelas')->where('nama_kelas', 'Kelas XI-0')->value('id');
        $tahunAjaranAsalId = (int) DB::table('tahun_ajaran')->where('nama', '2025/2026')->value('id');

        $user = User::create([
            'username' => '252610131',
            'email' => '252610131@sman1sumbercirebon.sch.id',
            'password' => bcrypt('12345678'),
            'nama_lengkap' => 'SISWA VALIDASI NAIK KELAS',
            'nis' => '252610131',
            'nisn' => '0131000000',
            'jenis_kelamin' => 'L',
            'is_active' => true,
        ]);
        $user->assignRole('Siswa');

        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelasAsalId,
            'siswa_id' => $user->id,
            'tahun_ajaran_id' => $tahunAjaranAsalId,
            'tanggal_masuk' => '2025-07-10',
            'tanggal_keluar' => null,
            'status' => 'aktif',
            'keterangan' => null,
            'created_at' => now()->subMonths(3),
            'is_active' => true,
            'updated_at' => now()->subMonths(3),
        ]);

        $import = new SiswaImport();
        $rows = new Collection([
            new Collection(['TEMPLATE IMPORT DATA SISWA']),
            new Collection(['Petunjuk']),
            new Collection([]),
            new Collection(['No', 'NIS*', 'Nama Lengkap*', 'Email', 'NISN*', 'Tanggal Lahir*', 'Jenis Kelamin*', 'Kelas*', 'Tahun Ajaran*', 'Tanggal Masuk*', 'No. Telepon Orang Tua*', 'Status']),
            // Target kelas lebih tinggi, tapi masih tahun ajaran yang sama -> harus ditolak.
            new Collection([1, '252610131', 'SISWA VALIDASI NAIK KELAS', null, '0131000000', '2010-01-03', 'L', 'Kelas XI-0', '2025/2026', '2025-08-02', '081200000002', 'Aktif']),
        ]);

        $import->collection($rows);

        $this->assertDatabaseHas('kelas_siswa', [
            'siswa_id' => $user->id,
            'kelas_id' => $kelasAsalId,
            'tahun_ajaran_id' => $tahunAjaranAsalId,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_keluar' => null,
        ]);

        $this->assertDatabaseMissing('kelas_siswa', [
            'siswa_id' => $user->id,
            'kelas_id' => $kelasXiSameYearId,
            'tahun_ajaran_id' => $tahunAjaranAsalId,
            'is_active' => true,
        ]);

        $this->assertSame(
            0,
            DB::table('siswa_transisi')
                ->where('siswa_id', $user->id)
                ->where('type', 'naik_kelas')
                ->count()
        );

        $this->assertNotEmpty($import->getErrors());
        $this->assertStringContainsString(
            'tahun ajaran tujuan lebih tinggi',
            implode("\n", $import->getErrors())
        );
    }
}
