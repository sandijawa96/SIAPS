<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Permission;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiswaKelasTransitionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermission();
    }

    public function test_naik_kelas_creates_history_row_and_new_active_row(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Naik',
        ]);
        $student->assignRole(RoleNames::SISWA);

        $tingkatX = Tingkat::create([
            'nama' => 'Kelas X',
            'kode' => 'X',
            'urutan' => 1,
            'is_active' => true,
        ]);
        $tingkatXI = Tingkat::create([
            'nama' => 'Kelas XI',
            'kode' => 'XI',
            'urutan' => 2,
            'is_active' => true,
        ]);

        $tahunAjaranAsal = TahunAjaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'is_active' => true,
            'status' => TahunAjaran::STATUS_ACTIVE,
            'semester' => 'full',
        ]);
        $tahunAjaranTujuan = TahunAjaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'is_active' => true,
            'status' => TahunAjaran::STATUS_ACTIVE,
            'semester' => 'full',
        ]);

        $kelasAsal = Kelas::create([
            'nama_kelas' => '10A',
            'tingkat_id' => $tingkatX->id,
            'tahun_ajaran_id' => $tahunAjaranAsal->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);
        $kelasTujuan = Kelas::create([
            'nama_kelas' => '11MIPA1',
            'tingkat_id' => $tingkatXI->id,
            'tahun_ajaran_id' => $tahunAjaranTujuan->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);

        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelasAsal->id,
            'siswa_id' => $student->id,
            'tahun_ajaran_id' => $tahunAjaranAsal->id,
            'tanggal_masuk' => '2025-07-10',
            'tanggal_keluar' => null,
            'status' => 'aktif',
            'keterangan' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/siswa-extended/' . $student->id . '/naik-kelas', [
                'kelas_id' => $kelasTujuan->id,
                'tahun_ajaran_id' => $tahunAjaranTujuan->id,
                'tanggal' => '2026-07-08',
                'keterangan' => 'Naik kelas otomatis',
            ]);

        $response->assertStatus(200)->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('kelas_siswa', [
            'siswa_id' => $student->id,
            'kelas_id' => $kelasAsal->id,
            'tahun_ajaran_id' => $tahunAjaranAsal->id,
            'is_active' => false,
            'status' => 'pindah',
            'tanggal_keluar' => '2026-07-08',
        ]);

        $this->assertDatabaseHas('kelas_siswa', [
            'siswa_id' => $student->id,
            'kelas_id' => $kelasTujuan->id,
            'tahun_ajaran_id' => $tahunAjaranTujuan->id,
            'is_active' => true,
            'status' => 'aktif',
            'tanggal_masuk' => '2026-07-08',
        ]);

        $this->assertDatabaseHas('siswa_transisi', [
            'siswa_id' => $student->id,
            'type' => 'naik_kelas',
            'kelas_asal_id' => $kelasAsal->id,
            'kelas_tujuan_id' => $kelasTujuan->id,
            'tahun_ajaran_id' => $tahunAjaranTujuan->id,
        ]);
    }

    public function test_pindah_kelas_creates_history_row_and_new_active_row_in_same_year(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleNames::ADMIN);

        $student = User::factory()->create([
            'nama_lengkap' => 'Siswa Pindah',
        ]);
        $student->assignRole(RoleNames::SISWA);

        $tingkatX = Tingkat::create([
            'nama' => 'Kelas X',
            'kode' => 'X',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $tahunAjaran = TahunAjaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'is_active' => true,
            'status' => TahunAjaran::STATUS_ACTIVE,
            'semester' => 'full',
        ]);

        $kelasAsal = Kelas::create([
            'nama_kelas' => '10A',
            'tingkat_id' => $tingkatX->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);
        $kelasTujuan = Kelas::create([
            'nama_kelas' => '10B',
            'tingkat_id' => $tingkatX->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);

        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelasAsal->id,
            'siswa_id' => $student->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'tanggal_masuk' => '2026-07-10',
            'tanggal_keluar' => null,
            'status' => 'aktif',
            'keterangan' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/siswa-extended/' . $student->id . '/pindah-kelas', [
                'kelas_id' => $kelasTujuan->id,
                'tahun_ajaran_id' => $tahunAjaran->id,
                'tanggal' => '2026-09-15',
                'keterangan' => 'Perataan rombel',
            ]);

        $response->assertStatus(200)->assertJson([
            'success' => true,
        ]);

        $this->assertDatabaseHas('kelas_siswa', [
            'siswa_id' => $student->id,
            'kelas_id' => $kelasAsal->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'is_active' => false,
            'status' => 'pindah',
            'tanggal_keluar' => '2026-09-15',
        ]);

        $this->assertDatabaseHas('kelas_siswa', [
            'siswa_id' => $student->id,
            'kelas_id' => $kelasTujuan->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'is_active' => true,
            'status' => 'aktif',
            'tanggal_masuk' => '2026-09-15',
        ]);

        $this->assertDatabaseHas('siswa_transisi', [
            'siswa_id' => $student->id,
            'type' => 'pindah_kelas',
            'kelas_asal_id' => $kelasAsal->id,
            'kelas_tujuan_id' => $kelasTujuan->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
        ]);
    }

    private function seedRolesAndPermission(): void
    {
        $roles = [
            RoleNames::ADMIN,
            RoleNames::SISWA,
        ];

        foreach ($roles as $index => $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                [
                    'display_name' => $roleName,
                    'description' => 'Role for transition history test',
                    'level' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        Permission::firstOrCreate(
            ['name' => 'manage_students', 'guard_name' => 'web'],
            [
                'display_name' => 'Manage Students',
                'description' => 'Manage student records',
                'module' => 'students',
            ]
        );

        Role::where('name', RoleNames::ADMIN)
            ->where('guard_name', 'web')
            ->firstOrFail()
            ->syncPermissions(['manage_students']);
    }
}

