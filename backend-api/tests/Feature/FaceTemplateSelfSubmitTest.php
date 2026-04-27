<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserFaceTemplateSubmissionState;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaceTemplateSelfSubmitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);

        Storage::fake('public');
        Http::fake([
            'http://127.0.0.1:9001/enroll' => Http::response([
                'success' => true,
                'template_vector' => [0.1, 0.2, 0.3],
                'template_version' => 'opencv-yunet-sface-v1',
                'quality_score' => 0.9345,
            ], 200),
        ]);
    }

    public function test_student_can_self_submit_three_times_and_fourth_attempt_is_blocked(): void
    {
        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = $this->actingAs($student, 'sanctum')
                ->postJson('/api/face-templates/self-submit', [
                    'foto' => 'data:image/jpeg;base64,' . base64_encode('template-attempt-' . $attempt),
                ]);

            $response->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.submission_state.self_submit_count', $attempt);
        }

        $blockedResponse = $this->actingAs($student, 'sanctum')
            ->postJson('/api/face-templates/self-submit', [
                'foto' => 'data:image/jpeg;base64,' . base64_encode('template-attempt-4'),
            ]);

        $blockedResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.submission_state.self_submit_count', 3)
            ->assertJsonPath('data.submission_state.requires_admin_unlock', true);

        $this->assertDatabaseHas('user_face_template_submission_states', [
            'user_id' => $student->id,
            'self_submit_count' => 3,
            'unlock_allowance_remaining' => 0,
        ]);
    }

    public function test_authorized_staff_can_unlock_one_additional_student_self_submit(): void
    {
        $student = User::factory()->create();
        $student->assignRole(RoleNames::SISWA);

        UserFaceTemplateSubmissionState::create([
            'user_id' => $student->id,
            'self_submit_count' => 3,
            'unlock_allowance_remaining' => 0,
        ]);

        $waliKelas = User::factory()->create();
        $waliKelas->assignRole(RoleNames::WALI_KELAS);
        $waliKelas->givePermissionTo('unlock_face_template_submit_quota');

        $unlockResponse = $this->actingAs($waliKelas, 'sanctum')
            ->postJson('/api/face-templates/users/' . $student->id . '/unlock-self-submit');

        $unlockResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.submission_state.unlock_allowance_remaining', 1);

        $submitResponse = $this->actingAs($student, 'sanctum')
            ->postJson('/api/face-templates/self-submit', [
                'foto' => 'data:image/jpeg;base64,' . base64_encode('template-after-unlock'),
            ]);

        $submitResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.submission_state.self_submit_count', 4)
            ->assertJsonPath('data.submission_state.unlock_allowance_remaining', 0);

        $this->assertDatabaseHas('user_face_template_submission_states', [
            'user_id' => $student->id,
            'self_submit_count' => 4,
            'unlock_allowance_remaining' => 0,
            'last_unlocked_by' => $waliKelas->id,
        ]);
    }

    public function test_non_student_cannot_use_self_submit_endpoint(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleNames::GURU);

        $this->actingAs($staff, 'sanctum')
            ->postJson('/api/face-templates/self-submit', [
                'foto' => 'data:image/jpeg;base64,' . base64_encode('pegawai-template'),
            ])
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
