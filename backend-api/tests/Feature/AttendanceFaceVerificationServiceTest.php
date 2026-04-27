<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\User;
use App\Models\UserFaceTemplate;
use App\Services\AttendanceFaceVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttendanceFaceVerificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_verification_uses_face_service_and_persists_result(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        Storage::disk('public')->put('absensi/checkin.jpg', 'selfie-image');

        UserFaceTemplate::create([
            'user_id' => $user->id,
            'template_vector' => json_encode([0.1, 0.2, 0.3]),
            'template_path' => 'face-templates/template.jpg',
            'template_version' => 'opencv-yunet-sface-v1',
            'quality_score' => 0.9123,
            'enrolled_at' => now(),
            'is_active' => true,
        ]);

        $attendance = Absensi::create([
            'user_id' => $user->id,
            'tanggal' => now()->toDateString(),
            'jam_masuk' => '07:00:00',
            'status' => 'hadir',
            'metode_absensi' => 'selfie',
            'foto_masuk' => 'absensi/checkin.jpg',
            'verification_status' => 'pending',
            'is_verified' => false,
        ]);

        Http::fake([
            'http://127.0.0.1:9001/verify' => Http::response([
                'success' => true,
                'result' => 'verified',
                'score' => 0.8841,
                'threshold' => 0.363,
                'reason_code' => 'matched',
                'template_version' => 'opencv-yunet-sface-v1',
                'metadata' => [
                    'processing_ms' => 140,
                    'detection_score' => 0.98,
                ],
            ], 200),
        ]);

        $result = app(AttendanceFaceVerificationService::class)->processVerification(
            $attendance,
            $user,
            'checkin',
            null,
            'absensi/checkin.jpg'
        );

        $this->assertSame('verified', $result['result']);
        $this->assertSame('verified', $result['status']);
        $this->assertSame('matched', $result['reason_code']);
        $this->assertSame('opencv-yunet-sface-v1', $result['engine_version']);

        $this->assertDatabaseHas('attendance_face_verifications', [
            'absensi_id' => $attendance->id,
            'user_id' => $user->id,
            'check_type' => 'checkin',
            'result' => 'verified',
            'reason_code' => 'matched',
            'engine_version' => 'opencv-yunet-sface-v1',
        ]);

        $attendance->refresh();
        $this->assertSame('verified', $attendance->verification_status);
        $this->assertTrue($attendance->is_verified);
        $this->assertSame('0.8841', (string) $attendance->face_score_checkin);
    }
}
