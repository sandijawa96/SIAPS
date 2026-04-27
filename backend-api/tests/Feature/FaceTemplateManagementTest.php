<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFaceTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
    }

    public function test_admin_can_enroll_face_template_via_face_service(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_attendance_settings');
        $targetUser = User::factory()->create([
            'nama_lengkap' => 'Siswa Template',
        ]);

        Http::fake([
            'http://127.0.0.1:9001/enroll' => Http::response([
                'success' => true,
                'template_vector' => [0.1, 0.2, 0.3],
                'template_version' => 'opencv-yunet-sface-v1',
                'quality_score' => 0.9451,
                'detection_score' => 0.9821,
                'metadata' => [
                    'processing_ms' => 112,
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/face-templates/enroll', [
                'user_id' => $targetUser->id,
                'foto' => 'data:image/jpeg;base64,' . base64_encode('template-image'),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $targetUser->id)
            ->assertJsonPath('data.template_version', 'opencv-yunet-sface-v1');

        $template = UserFaceTemplate::query()
            ->where('user_id', $targetUser->id)
            ->where('is_active', true)
            ->first();

        $this->assertNotNull($template);
        $this->assertSame('[0.1,0.2,0.3]', $template->template_vector);
        $this->assertSame('opencv-yunet-sface-v1', $template->template_version);
        $this->assertSame($admin->id, $template->enrolled_by);
        Storage::disk('public')->assertExists($template->template_path);
    }

    public function test_show_returns_active_template_status(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_attendance_settings');
        $targetUser = User::factory()->create();

        $template = UserFaceTemplate::create([
            'user_id' => $targetUser->id,
            'template_vector' => json_encode([0.1, 0.2]),
            'template_path' => 'face-templates/template.jpg',
            'template_version' => 'opencv-yunet-sface-v1',
            'quality_score' => 0.8877,
            'enrolled_at' => now(),
            'enrolled_by' => $admin->id,
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/face-templates/users/' . $targetUser->id)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_active_template', true)
            ->assertJsonPath('data.active_template.id', $template->id)
            ->assertJsonPath('data.submission_state.limit', 3)
            ->assertJsonPath('data.submission_state.self_submit_count', 0)
            ->assertJsonPath('data.submission_state.can_self_submit_now', true);
    }
}
