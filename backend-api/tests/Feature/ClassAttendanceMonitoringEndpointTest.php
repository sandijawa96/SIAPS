<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassAttendanceMonitoringEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRequiredRoles();
    }

    public function test_wali_kelas_only_sees_its_managed_classes_on_monitoring_endpoint(): void
    {
        $waliA = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $waliB = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $studentA = $this->createUserWithRole(RoleNames::SISWA);
        $studentB = $this->createUserWithRole(RoleNames::SISWA);

        [$tingkat, $tahunAjaran] = $this->createAcademicContext();
        $classA = $this->createClass($tingkat, $tahunAjaran, $waliA, 'X IPA 1');
        $classB = $this->createClass($tingkat, $tahunAjaran, $waliB, 'X IPA 2');

        $this->attachStudentToClass($studentA, $classA, $tahunAjaran);
        $this->attachStudentToClass($studentB, $classB, $tahunAjaran);

        Absensi::create([
            'user_id' => $studentA->id,
            'kelas_id' => $classA->id,
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '07:05:00',
            'status' => 'hadir',
            'metode_absensi' => 'mobile',
        ]);

        Izin::create([
            'user_id' => $studentA->id,
            'kelas_id' => $classA->id,
            'jenis_izin' => 'izin',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'alasan' => 'Perlu izin keluarga',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($waliA, 'sanctum')
            ->getJson('/api/monitoring-kelas/kelas');

        $response->assertOk();

        $rows = collect($response->json());

        $this->assertCount(1, $rows);
        $this->assertSame($classA->id, (int) $rows->first()['id']);
        $this->assertSame(1, (int) $rows->first()['hadir_hari_ini']);
        $this->assertSame(1, (int) $rows->first()['izin_pending']);
    }

    public function test_wakasek_kesiswaan_can_see_all_classes_in_active_year(): void
    {
        $wakasek = $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $waliA = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $waliB = $this->createUserWithRole(RoleNames::WALI_KELAS);

        [$tingkat, $tahunAjaran] = $this->createAcademicContext();
        $classA = $this->createClass($tingkat, $tahunAjaran, $waliA, 'X IPA 1');
        $classB = $this->createClass($tingkat, $tahunAjaran, $waliB, 'X IPA 2');

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson('/api/monitoring-kelas/kelas');

        $response->assertOk();

        $classIds = collect($response->json())->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->assertEqualsCanonicalizing([$classA->id, $classB->id], $classIds);
    }

    public function test_wakasek_kesiswaan_can_view_class_detail_without_being_wali(): void
    {
        $wakasek = $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $student = $this->createUserWithRole(RoleNames::SISWA);

        [$tingkat, $tahunAjaran] = $this->createAcademicContext();
        $class = $this->createClass($tingkat, $tahunAjaran, $wali, 'X IPA 1');
        $this->attachStudentToClass($student, $class, $tahunAjaran);

        $response = $this->actingAs($wakasek, 'sanctum')
            ->getJson("/api/monitoring-kelas/kelas/{$class->id}");

        $response->assertOk()
            ->assertJsonPath('kelas.id', $class->id)
            ->assertJsonPath('kelas.nama_kelas', 'X IPA 1')
            ->assertJsonPath('kelas.siswa.0.id', $student->id);
    }

    public function test_other_roles_cannot_access_monitoring_kelas_endpoint(): void
    {
        $guru = $this->createUserWithRole(RoleNames::GURU);

        $this->actingAs($guru, 'sanctum')
            ->getJson('/api/monitoring-kelas/kelas')
            ->assertForbidden();
    }

    private function seedRequiredRoles(): void
    {
        foreach ([RoleNames::WALI_KELAS, RoleNames::WAKASEK_KESISWAAN, RoleNames::SISWA, RoleNames::GURU] as $roleName) {
            foreach (RoleNames::aliases($roleName) as $alias) {
                Role::firstOrCreate(
                    ['name' => $alias, 'guard_name' => 'web'],
                    [
                        'display_name' => $alias,
                        'description' => $alias,
                        'level' => 1,
                        'is_active' => true,
                    ]
                );
            }
        }
    }

    private function createUserWithRole(string $canonicalRole): User
    {
        $role = Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->where('guard_name', 'web')
            ->firstOrFail();

        $user = User::factory()->create();
        $user->assignRole($role->name);

        return $user;
    }

    private function createAcademicContext(): array
    {
        $tingkat = Tingkat::create([
            'nama' => 'Kelas X',
            'kode' => 'X',
            'urutan' => 10,
            'is_active' => true,
        ]);

        $tahunAjaran = TahunAjaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => now()->startOfYear()->toDateString(),
            'tanggal_selesai' => now()->endOfYear()->toDateString(),
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'semester' => 'genap',
        ]);

        return [$tingkat, $tahunAjaran];
    }

    private function createClass(Tingkat $tingkat, TahunAjaran $tahunAjaran, User $waliKelas, string $namaKelas): Kelas
    {
        return Kelas::create([
            'nama_kelas' => $namaKelas,
            'tingkat_id' => $tingkat->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'wali_kelas_id' => $waliKelas->id,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
        ]);
    }

    private function attachStudentToClass(User $student, Kelas $kelas, TahunAjaran $tahunAjaran): void
    {
        $kelas->siswa()->attach($student->id, [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
