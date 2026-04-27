<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class JadwalPelajaranIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private int $tahunAjaranId;
    private int $tingkatId;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seedRolesAndPermissions();
        $this->seedAcademicReferences();
    }

    public function test_conflicting_schedule_is_rejected(): void
    {
        $operator = $this->createUserWithRole(RoleNames::WAKASEK_KURIKULUM);
        $guru = $this->createUserWithRole(RoleNames::GURU);

        $kelasA = $this->createKelas('X-IPA-1');
        $kelasB = $this->createKelas('X-IPA-2');
        $mapel = $this->createMapel('MTK10', 'Matematika');

        $assignmentPayloadA = [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id' => $kelasA->id,
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'jam_per_minggu' => 4,
            'status' => 'aktif',
        ];

        $assignmentPayloadB = [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id' => $kelasB->id,
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'jam_per_minggu' => 4,
            'status' => 'aktif',
        ];

        $this->actingAs($operator, 'sanctum')->postJson('/api/guru-mapel', $assignmentPayloadA)->assertStatus(201);
        $this->actingAs($operator, 'sanctum')->postJson('/api/guru-mapel', $assignmentPayloadB)->assertStatus(201);

        $scheduleA = [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id' => $kelasA->id,
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'semester' => 'full',
            'hari' => 'senin',
            'jam_ke' => 1,
            'jam_mulai' => '07:00',
            'jam_selesai' => '08:00',
            'ruangan' => 'R-101',
            'status' => 'draft',
            'is_active' => true,
        ];

        $this->actingAs($operator, 'sanctum')
            ->postJson('/api/jadwal-pelajaran', $scheduleA)
            ->assertStatus(201);

        $scheduleConflict = [
            'guru_id' => $guru->id,
            'mata_pelajaran_id' => $mapel->id,
            'kelas_id' => $kelasB->id,
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'semester' => 'full',
            'hari' => 'senin',
            'jam_ke' => 1,
            'jam_mulai' => '07:30',
            'jam_selesai' => '08:30',
            'ruangan' => 'R-102',
            'status' => 'draft',
            'is_active' => true,
        ];

        $response = $this->actingAs($operator, 'sanctum')
            ->postJson('/api/jadwal-pelajaran', $scheduleConflict);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Konflik jadwal terdeteksi. Periksa guru/kelas/ruangan pada slot waktu yang sama pada JP ke-1',
            ]);

        $this->assertGreaterThanOrEqual(1, (int) $response->json('conflicts.summary.guru'));
    }

    public function test_guru_my_schedule_only_contains_own_schedule(): void
    {
        $operator = $this->createUserWithRole(RoleNames::WAKASEK_KURIKULUM);
        $guruA = $this->createUserWithRole(RoleNames::GURU);
        $guruB = $this->createUserWithRole(RoleNames::GURU);
        $mapel = $this->createMapel('BIO10', 'Biologi');
        $kelasA = $this->createKelas('X-IPA-3');
        $kelasB = $this->createKelas('X-IPA-4');

        foreach ([[$guruA, $kelasA], [$guruB, $kelasB]] as [$guru, $kelas]) {
            $this->actingAs($operator, 'sanctum')->postJson('/api/guru-mapel', [
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $mapel->id,
                'kelas_id' => $kelas->id,
                'tahun_ajaran_id' => $this->tahunAjaranId,
                'jam_per_minggu' => 2,
                'status' => 'aktif',
            ])->assertStatus(201);
        }

        DB::table('jadwal_mengajar')->insert([
            [
                'guru_id' => $guruA->id,
                'kelas_id' => $kelasA->id,
                'tahun_ajaran_id' => $this->tahunAjaranId,
                'semester' => 'full',
                'mata_pelajaran' => $mapel->nama_mapel,
                'mata_pelajaran_id' => $mapel->id,
                'hari' => 'selasa',
                'jam_mulai' => '09:00:00',
                'jam_selesai' => '09:45:00',
                'jam_ke' => 3,
                'ruangan' => 'R-201',
                'status' => 'published',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'guru_id' => $guruB->id,
                'kelas_id' => $kelasB->id,
                'tahun_ajaran_id' => $this->tahunAjaranId,
                'semester' => 'full',
                'mata_pelajaran' => $mapel->nama_mapel,
                'mata_pelajaran_id' => $mapel->id,
                'hari' => 'rabu',
                'jam_mulai' => '10:00:00',
                'jam_selesai' => '10:45:00',
                'jam_ke' => 4,
                'ruangan' => 'R-202',
                'status' => 'published',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($guruA, 'sanctum')
            ->getJson('/api/jadwal-pelajaran/my-schedule?no_pagination=1');

        $response->assertStatus(200)->assertJson(['success' => true]);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($guruA->id, (int) $data[0]['guru_id']);
    }

    public function test_publish_rejects_conflicting_draft_schedule(): void
    {
        $operator = $this->createUserWithRole(RoleNames::WAKASEK_KURIKULUM);
        $guru = $this->createUserWithRole(RoleNames::GURU);
        $kelasA = $this->createKelas('X-IPA-5');
        $kelasB = $this->createKelas('X-IPA-6');
        $mapel = $this->createMapel('KIM10', 'Kimia');

        foreach ([$kelasA, $kelasB] as $kelas) {
            $this->actingAs($operator, 'sanctum')->postJson('/api/guru-mapel', [
                'guru_id' => $guru->id,
                'mata_pelajaran_id' => $mapel->id,
                'kelas_id' => $kelas->id,
                'tahun_ajaran_id' => $this->tahunAjaranId,
                'jam_per_minggu' => 2,
                'status' => 'aktif',
            ])->assertStatus(201);
        }

        DB::table('jadwal_mengajar')->insert([
            [
                'guru_id' => $guru->id,
                'kelas_id' => $kelasA->id,
                'tahun_ajaran_id' => $this->tahunAjaranId,
                'semester' => 'full',
                'mata_pelajaran' => $mapel->nama_mapel,
                'mata_pelajaran_id' => $mapel->id,
                'hari' => 'senin',
                'jam_mulai' => '07:00:00',
                'jam_selesai' => '07:45:00',
                'jam_ke' => 1,
                'ruangan' => 'R-301',
                'status' => 'draft',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'guru_id' => $guru->id,
                'kelas_id' => $kelasB->id,
                'tahun_ajaran_id' => $this->tahunAjaranId,
                'semester' => 'full',
                'mata_pelajaran' => $mapel->nama_mapel,
                'mata_pelajaran_id' => $mapel->id,
                'hari' => 'senin',
                'jam_mulai' => '07:30:00',
                'jam_selesai' => '08:15:00',
                'jam_ke' => 1,
                'ruangan' => 'R-302',
                'status' => 'draft',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($operator, 'sanctum')
            ->postJson('/api/jadwal-pelajaran/publish', [
                'tahun_ajaran_id' => $this->tahunAjaranId,
                'semester' => 'full',
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Publish ditolak karena masih ada konflik jadwal pada data draft',
            ]);

        $this->assertGreaterThan(0, (int) $response->json('conflicts.summary.total'));
        $this->assertDatabaseHas('jadwal_mengajar', [
            'kelas_id' => $kelasA->id,
            'status' => 'draft',
        ]);
    }

    private function seedRolesAndPermissions(): void
    {
        foreach ([RoleNames::WAKASEK_KURIKULUM, RoleNames::GURU] as $index => $roleName) {
            Role::updateOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
                ['display_name' => $roleName, 'description' => 'Role for schedule integration test', 'level' => $index + 1, 'is_active' => true]
            );
        }

        foreach (['assign_guru_mapel', 'manage_jadwal_pelajaran', 'view_jadwal_pelajaran'] as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission, 'guard_name' => 'web'],
                ['display_name' => $permission, 'description' => 'Permission for schedule integration test', 'module' => 'academic']
            );
        }

        Role::where('name', RoleNames::WAKASEK_KURIKULUM)->firstOrFail()
            ->syncPermissions(['assign_guru_mapel', 'manage_jadwal_pelajaran', 'view_jadwal_pelajaran']);

        Role::where('name', RoleNames::GURU)->firstOrFail()
            ->syncPermissions(['view_jadwal_pelajaran']);
    }

    private function seedAcademicReferences(): void
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
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
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

    private function createKelas(string $namaKelas): Kelas
    {
        return Kelas::create([
            'nama_kelas' => $namaKelas,
            'tingkat_id' => $this->tingkatId,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $this->tahunAjaranId,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
        ]);
    }

    private function createMapel(string $kode, string $nama): MataPelajaran
    {
        return MataPelajaran::create([
            'kode_mapel' => $kode,
            'nama_mapel' => $nama,
            'tingkat_id' => $this->tingkatId,
            'is_active' => true,
        ]);
    }
}
