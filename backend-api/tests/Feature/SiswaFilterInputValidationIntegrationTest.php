<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaFilterInputValidationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_siswa_index_ignores_non_numeric_kelas_id_filter(): void
    {
        $user = $this->makeUserWithPermissions(['view_siswa']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/siswa?kelas_id=X')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_siswa_extended_index_ignores_non_numeric_kelas_id_filter(): void
    {
        $user = $this->makeUserWithPermissions(['view_siswa']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/siswa-extended?kelas_id=X&is_active=0')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_siswa_extended_index_ignores_non_numeric_tahun_ajaran_id_filter(): void
    {
        $user = $this->makeUserWithPermissions(['view_siswa']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/siswa-extended?tahun_ajaran_id=abc')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    private function makeUserWithPermissions(array $permissions): User
    {
        $user = User::factory()->create();
        $user->syncPermissions($permissions);

        return $user;
    }
}

