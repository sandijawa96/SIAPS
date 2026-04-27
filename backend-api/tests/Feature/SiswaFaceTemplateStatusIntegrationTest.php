<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFaceTemplate;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaFaceTemplateStatusIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_siswa_index_includes_active_face_template_flag(): void
    {
        $viewer = User::factory()->create();
        $viewer->syncPermissions(['manage_students']);

        $studentWithTemplate = User::factory()->create([
            'username' => 'siswa_face_001',
            'email' => 'siswa_face_001@example.test',
            'nis' => 'FACE001',
            'nisn' => 'FACE001',
        ]);
        $studentWithTemplate->assignRole(RoleNames::SISWA);

        $studentWithoutTemplate = User::factory()->create([
            'username' => 'siswa_face_002',
            'email' => 'siswa_face_002@example.test',
            'nis' => 'FACE002',
            'nisn' => 'FACE002',
        ]);
        $studentWithoutTemplate->assignRole(RoleNames::SISWA);

        UserFaceTemplate::create([
            'user_id' => $studentWithTemplate->id,
            'template_vector' => json_encode([0.1, 0.2, 0.3]),
            'template_path' => 'face-templates/siswa-face-001.jpg',
            'template_version' => 'opencv-yunet-sface-v1',
            'quality_score' => 0.9321,
            'enrolled_at' => now(),
            'enrolled_by' => $viewer->id,
            'is_active' => true,
        ]);

        UserFaceTemplate::create([
            'user_id' => $studentWithoutTemplate->id,
            'template_vector' => json_encode([0.4, 0.5, 0.6]),
            'template_path' => 'face-templates/siswa-face-002-old.jpg',
            'template_version' => 'opencv-yunet-sface-v1',
            'quality_score' => 0.8111,
            'enrolled_at' => now()->subDay(),
            'enrolled_by' => $viewer->id,
            'is_active' => false,
        ]);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/siswa?per_page=100');

        $response->assertStatus(200)->assertJsonPath('success', true);

        $rows = collect($response->json('data.data'))->keyBy('id');

        $this->assertTrue((bool) $rows->get($studentWithTemplate->id)['has_active_face_template']);
        $this->assertFalse((bool) $rows->get($studentWithoutTemplate->id)['has_active_face_template']);
    }
}
