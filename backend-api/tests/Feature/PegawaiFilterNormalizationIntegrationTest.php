<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PegawaiFilterNormalizationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_pegawai_index_supports_role_filter_array(): void
    {
        $viewer = $this->makeUserWithPermissions(['view_pegawai']);

        $guru = $this->makePegawai('Guru', 'ASN', true, 'Guru Satu', 'guru_satu@example.test');
        $wali = $this->makePegawai('Wali Kelas', 'Honorer', true, 'Wali Satu', 'wali_satu@example.test');
        $this->makePegawai('Admin', 'ASN', true, 'Admin Satu', 'admin_satu@example.test');

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/pegawai?role[]=Guru&role[]=Wali%20Kelas');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $resultIds = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertContains($guru->id, $resultIds);
        $this->assertContains($wali->id, $resultIds);
    }

    public function test_pegawai_index_maps_status_kepegawaian_aliases(): void
    {
        $viewer = $this->makeUserWithPermissions(['view_pegawai']);

        $asn = $this->makePegawai('Guru', 'ASN', true, 'ASN Satu', 'asn_satu@example.test');
        $honorer = $this->makePegawai('Admin', 'Honorer', true, 'Honorer Satu', 'honorer_satu@example.test');

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/pegawai?status_kepegawaian[]=PNS&status_kepegawaian[]=Honorer');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $resultIds = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertContains($asn->id, $resultIds);
        $this->assertContains($honorer->id, $resultIds);
    }

    public function test_pegawai_index_parses_boolean_is_active_filter(): void
    {
        $viewer = $this->makeUserWithPermissions(['view_pegawai']);

        $inactive = $this->makePegawai('Guru', 'ASN', false, 'Inactive Satu', 'inactive_satu@example.test');
        $active = $this->makePegawai('Admin', 'ASN', true, 'Active Satu', 'active_satu@example.test');

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/pegawai?is_active=0');

        $response->assertStatus(200)->assertJsonPath('success', true);
        $resultIds = collect($response->json('data.data'))->pluck('id')->all();

        $this->assertContains($inactive->id, $resultIds);
        $this->assertNotContains($active->id, $resultIds);
    }

    private function makeUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->syncPermissions($permissions);

        return $user;
    }

    private function makePegawai(
        string $role,
        string $statusKepegawaian,
        bool $isActive,
        string $namaLengkap,
        string $email
    ): User {
        $pegawai = User::factory()->create([
            'nama_lengkap' => $namaLengkap,
            'email' => $email,
            'status_kepegawaian' => $statusKepegawaian,
            'is_active' => $isActive,
        ]);

        $pegawai->assignRole($role);

        return $pegawai;
    }
}

