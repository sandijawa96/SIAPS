<?php

namespace Tests\Feature;

use App\Imports\PegawaiImport;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PegawaiImportMappingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['Guru', 'Staff', 'Wali Kelas', 'Wakasek_Kesiswaan'] as $roleName) {
            Role::create([
                'name' => $roleName,
                'guard_name' => 'web',
                'display_name' => $roleName,
                'description' => 'Role for pegawai import mapping tests',
                'is_active' => true,
            ]);
        }
    }

    public function test_import_supports_heading_based_values_and_normalization(): void
    {
        $import = new PegawaiImport();

        $rows = new Collection([
            new Collection([
                'username' => '197001011999001001',
                'email' => 'guru.test@example.com',
                'nama_lengkap' => 'Guru Test',
                'jenis_kelamin' => 'Laki-laki',
                'role' => 'guru',
                'sub_role' => 'Wali_Kelas',
                'status_kepegawaian' => 'asn',
                'status' => 'aktif',
            ]),
        ]);

        $import->collection($rows);

        $this->assertSame(1, $import->getRowCount());
        $this->assertSame([], $import->getErrors());

        $user = User::where('username', '197001011999001001')->firstOrFail();

        $this->assertSame('L', $user->jenis_kelamin);
        $this->assertSame('ASN', $user->status_kepegawaian);
        $this->assertTrue((bool) $user->is_active);
        $this->assertTrue($user->hasRole('Guru'));
        $this->assertTrue($user->hasRole('Wali Kelas'));
    }

    public function test_import_supports_export_index_format_with_leading_no_column(): void
    {
        $import = new PegawaiImport();

        $rows = new Collection([
            new Collection([
                1,
                'staff01',
                'staff01@example.com',
                'Staff Test',
                'Perempuan',
                'Staff',
                '',
                'Honorer',
                'Tidak Aktif',
                '15/02/2026 10:00:00',
            ]),
        ]);

        $import->collection($rows);

        $this->assertSame(1, $import->getRowCount());
        $this->assertSame([], $import->getErrors());

        $user = User::where('username', 'staff01')->firstOrFail();

        $this->assertSame('P', $user->jenis_kelamin);
        $this->assertSame('Honorer', $user->status_kepegawaian);
        $this->assertFalse((bool) $user->is_active);
        $this->assertTrue($user->hasRole('Staff'));
    }
}
