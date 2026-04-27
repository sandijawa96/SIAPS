<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleScopedReadAccessIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private TahunAjaran $tahunAjaran;
    private Tingkat $tingkat;

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

    public function test_wali_kelas_only_sees_own_class_and_students(): void
    {
        $wali = User::factory()->create();
        $wali->assignRole(RoleNames::WALI_KELAS);

        $waliLain = User::factory()->create();
        $waliLain->assignRole(RoleNames::WALI_KELAS);

        $kelasSaya = $this->createKelas('X-1', $wali->id);
        $kelasLain = $this->createKelas('X-2', $waliLain->id);

        $siswaSaya = User::factory()->create([
            'username' => 'siswa_scope_1',
            'email' => 'siswa_scope_1@example.test',
            'nis' => 'SCOPE001',
            'nisn' => 'SCOPE001',
        ]);
        $siswaSaya->assignRole(RoleNames::SISWA);

        $siswaLain = User::factory()->create([
            'username' => 'siswa_scope_2',
            'email' => 'siswa_scope_2@example.test',
            'nis' => 'SCOPE002',
            'nisn' => 'SCOPE002',
        ]);
        $siswaLain->assignRole(RoleNames::SISWA);

        $this->assignStudentToClass($siswaSaya->id, $kelasSaya->id);
        $this->assignStudentToClass($siswaLain->id, $kelasLain->id);

        $kelasResponse = $this->actingAs($wali, 'sanctum')->getJson('/api/kelas');
        $kelasResponse->assertStatus(200);
        $this->assertSame([$kelasSaya->id], collect($kelasResponse->json())->pluck('id')->all());

        $siswaResponse = $this->actingAs($wali, 'sanctum')->getJson('/api/siswa');
        $siswaResponse->assertStatus(200)->assertJsonPath('data.total', 1);
        $this->assertSame([$siswaSaya->id], collect($siswaResponse->json('data.data'))->pluck('id')->all());

        $siswaExtendedResponse = $this->actingAs($wali, 'sanctum')->getJson('/api/siswa-extended');
        $siswaExtendedResponse->assertStatus(200)->assertJsonPath('data.total', 1);
        $this->assertSame(
            [$siswaSaya->id],
            collect($siswaExtendedResponse->json('data.data'))->pluck('id')->all()
        );

        $this->actingAs($wali, 'sanctum')
            ->getJson('/api/siswa/' . $siswaLain->id)
            ->assertStatus(404);

        $this->actingAs($wali, 'sanctum')
            ->getJson('/api/kelas/' . $kelasSaya->id . '/siswa')
            ->assertStatus(200)
            ->assertJsonPath('total_siswa', 1)
            ->assertJsonPath('data.0.id', $siswaSaya->id);

        $this->actingAs($wali, 'sanctum')
            ->getJson('/api/kelas/' . $kelasLain->id . '/siswa')
            ->assertStatus(404);
    }

    public function test_guru_only_sees_classes_and_students_from_active_teaching_schedule(): void
    {
        $guru = User::factory()->create();
        $guru->assignRole(RoleNames::GURU);

        $guruLain = User::factory()->create();
        $guruLain->assignRole(RoleNames::GURU);

        $kelasDiajar = $this->createKelas('X-3');
        $kelasLain = $this->createKelas('X-4');

        $siswaDiajar = User::factory()->create([
            'username' => 'siswa_guru_scope_1',
            'email' => 'siswa_guru_scope_1@example.test',
            'nis' => 'SCOPE101',
            'nisn' => 'SCOPE101',
        ]);
        $siswaDiajar->assignRole(RoleNames::SISWA);

        $siswaLain = User::factory()->create([
            'username' => 'siswa_guru_scope_2',
            'email' => 'siswa_guru_scope_2@example.test',
            'nis' => 'SCOPE102',
            'nisn' => 'SCOPE102',
        ]);
        $siswaLain->assignRole(RoleNames::SISWA);

        $this->assignStudentToClass($siswaDiajar->id, $kelasDiajar->id);
        $this->assignStudentToClass($siswaLain->id, $kelasLain->id);

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

        DB::table('jadwal_mengajar')->insert([
            'guru_id' => $guruLain->id,
            'kelas_id' => $kelasLain->id,
            'mata_pelajaran' => 'Bahasa Indonesia',
            'hari' => 'selasa',
            'jam_mulai' => '08:00:00',
            'jam_selesai' => '09:00:00',
            'ruangan' => 'R2',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $kelasResponse = $this->actingAs($guru, 'sanctum')->getJson('/api/kelas');
        $kelasResponse->assertStatus(200);
        $this->assertSame([$kelasDiajar->id], collect($kelasResponse->json())->pluck('id')->all());

        $siswaResponse = $this->actingAs($guru, 'sanctum')->getJson('/api/siswa');
        $siswaResponse->assertStatus(200)->assertJsonPath('data.total', 1);
        $this->assertSame([$siswaDiajar->id], collect($siswaResponse->json('data.data'))->pluck('id')->all());

        $this->actingAs($guru, 'sanctum')
            ->getJson('/api/siswa/' . $siswaLain->id)
            ->assertStatus(404);
    }

    public function test_wali_kelas_with_guru_role_still_sees_only_wali_students_in_siswa_index(): void
    {
        $waliGuru = User::factory()->create();
        $waliGuru->assignRole(RoleNames::WALI_KELAS);
        $waliGuru->assignRole(RoleNames::GURU);

        $kelasWali = $this->createKelas('X-5', $waliGuru->id);
        $kelasDiajarBukanWali = $this->createKelas('X-6');

        $siswaKelasWali = User::factory()->create([
            'username' => 'siswa_wali_only_1',
            'email' => 'siswa_wali_only_1@example.test',
            'nis' => 'SCOPE201',
            'nisn' => 'SCOPE201',
        ]);
        $siswaKelasWali->assignRole(RoleNames::SISWA);

        $siswaKelasDiajar = User::factory()->create([
            'username' => 'siswa_wali_only_2',
            'email' => 'siswa_wali_only_2@example.test',
            'nis' => 'SCOPE202',
            'nisn' => 'SCOPE202',
        ]);
        $siswaKelasDiajar->assignRole(RoleNames::SISWA);

        $this->assignStudentToClass($siswaKelasWali->id, $kelasWali->id);
        $this->assignStudentToClass($siswaKelasDiajar->id, $kelasDiajarBukanWali->id);

        DB::table('jadwal_mengajar')->insert([
            'guru_id' => $waliGuru->id,
            'kelas_id' => $kelasDiajarBukanWali->id,
            'mata_pelajaran' => 'Fisika',
            'hari' => 'rabu',
            'jam_mulai' => '09:00:00',
            'jam_selesai' => '10:00:00',
            'ruangan' => 'R3',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($waliGuru, 'sanctum')->getJson('/api/siswa');
        $response->assertStatus(200)->assertJsonPath('data.total', 1);
        $this->assertSame(
            [$siswaKelasWali->id],
            collect($response->json('data.data'))->pluck('id')->all()
        );

        $extendedResponse = $this->actingAs($waliGuru, 'sanctum')->getJson('/api/siswa-extended');
        $extendedResponse->assertStatus(200)->assertJsonPath('data.total', 1);
        $this->assertSame(
            [$siswaKelasWali->id],
            collect($extendedResponse->json('data.data'))->pluck('id')->all()
        );
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

    private function assignStudentToClass(int $siswaId, int $kelasId): void
    {
        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelasId,
            'siswa_id' => $siswaId,
            'tahun_ajaran_id' => $this->tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
