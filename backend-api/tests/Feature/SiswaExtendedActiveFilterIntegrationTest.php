<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SiswaExtendedActiveFilterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TahunAjaran $tahunAjaranLama;
    private TahunAjaran $tahunAjaranAktif;
    private Kelas $kelasLama;
    private Kelas $kelasAktif;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        $tingkat = Tingkat::create([
            'nama' => 'X',
            'kode' => 'X',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $this->tahunAjaranLama = TahunAjaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'semester' => 'full',
            'status' => 'completed',
            'is_active' => false,
        ]);

        $this->tahunAjaranAktif = TahunAjaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'semester' => 'full',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->kelasLama = Kelas::create([
            'nama_kelas' => 'X-1',
            'tingkat_id' => $tingkat->id,
            'tahun_ajaran_id' => $this->tahunAjaranLama->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);

        $this->kelasAktif = Kelas::create([
            'nama_kelas' => 'X-2',
            'tingkat_id' => $tingkat->id,
            'tahun_ajaran_id' => $this->tahunAjaranAktif->id,
            'kapasitas' => 36,
            'is_active' => true,
        ]);
    }

    public function test_kelas_filter_only_uses_active_assignment(): void
    {
        $authorizedUser = $this->makeAuthorizedUser();

        $siswaPindahan = $this->makeSiswa('ACTIVE001', 'active001@example.test');
        $siswaTetapLama = $this->makeSiswa('ACTIVE002', 'active002@example.test');

        // Historical class membership (inactive)
        $this->assignKelas($siswaPindahan->id, $this->kelasLama->id, $this->tahunAjaranLama->id, false);
        // Current active class membership
        $this->assignKelas($siswaPindahan->id, $this->kelasAktif->id, $this->tahunAjaranAktif->id, true);
        $this->assignKelas($siswaTetapLama->id, $this->kelasLama->id, $this->tahunAjaranLama->id, true);

        $response = $this->actingAs($authorizedUser, 'sanctum')
            ->getJson('/api/siswa-extended?kelas_id=' . $this->kelasLama->id);

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('data.total', 1);
        $returnedIds = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertSame([$siswaTetapLama->id], $returnedIds);
    }

    public function test_tahun_ajaran_filter_only_uses_active_assignment(): void
    {
        $authorizedUser = $this->makeAuthorizedUser();

        $siswaPindahan = $this->makeSiswa('ACTIVE003', 'active003@example.test');
        $siswaTahunLama = $this->makeSiswa('ACTIVE004', 'active004@example.test');

        $this->assignKelas($siswaPindahan->id, $this->kelasLama->id, $this->tahunAjaranLama->id, false);
        $this->assignKelas($siswaPindahan->id, $this->kelasAktif->id, $this->tahunAjaranAktif->id, true);
        $this->assignKelas($siswaTahunLama->id, $this->kelasLama->id, $this->tahunAjaranLama->id, true);

        $response = $this->actingAs($authorizedUser, 'sanctum')
            ->getJson('/api/siswa-extended?tahun_ajaran_id=' . $this->tahunAjaranLama->id);

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('data.total', 1);
        $returnedIds = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertSame([$siswaTahunLama->id], $returnedIds);
    }

    public function test_status_filter_accepts_numeric_boolean_value(): void
    {
        $authorizedUser = $this->makeAuthorizedUser();

        $siswaAktif = $this->makeSiswa('ACTIVE005', 'active005@example.test', true);
        $siswaNonAktif = $this->makeSiswa('ACTIVE006', 'active006@example.test', false);

        $this->assignKelas($siswaAktif->id, $this->kelasAktif->id, $this->tahunAjaranAktif->id, true);
        $this->assignKelas($siswaNonAktif->id, $this->kelasAktif->id, $this->tahunAjaranAktif->id, true);

        $response = $this->actingAs($authorizedUser, 'sanctum')
            ->getJson('/api/siswa-extended?is_active=0');

        $response->assertStatus(200)->assertJsonPath('success', true)->assertJsonPath('data.total', 1);
        $returnedIds = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertSame([$siswaNonAktif->id], $returnedIds);
    }

    private function makeAuthorizedUser(): User
    {
        $user = User::factory()->create();
        $user->syncPermissions(['manage_students']);

        return $user;
    }

    private function makeSiswa(string $nis, string $email, bool $isActive = true): User
    {
        $siswa = User::factory()->create([
            'username' => strtolower($nis),
            'email' => $email,
            'nis' => $nis,
            'nisn' => $nis,
            'is_active' => $isActive,
        ]);
        $siswa->assignRole('Siswa');

        return $siswa;
    }

    private function assignKelas(int $siswaId, int $kelasId, int $tahunAjaranId, bool $isActive): void
    {
        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelasId,
            'siswa_id' => $siswaId,
            'tahun_ajaran_id' => $tahunAjaranId,
            'status' => $isActive ? 'aktif' : 'pindah',
            'is_active' => $isActive,
            'tanggal_masuk' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

