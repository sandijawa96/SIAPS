<?php

namespace App\Services;

use App\Exceptions\FaceRecognitionServiceException;
use App\Models\Absensi;
use App\Models\AttendanceFaceVerification;
use App\Models\AttendanceSchema;
use App\Models\User;
use App\Models\UserFaceTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttendanceFaceVerificationService
{
    public function __construct(
        private readonly AttendanceRuntimeConfigService $attendanceRuntimeConfigService,
        private readonly FaceRecognitionClient $faceRecognitionClient,
    ) {
    }

    /**
     * Determine verification mode from schema or fallback config.
     */
    public function determineMode(?AttendanceSchema $schema): string
    {
        $schemaMode = $schema?->verification_mode;
        if (in_array($schemaMode, ['sync_final', 'async_pending'], true)) {
            return $schemaMode;
        }

        return (string) config('attendance.face.default_mode', 'async_pending');
    }

    /**
     * Queue-based flow entrypoint.
     */
    public function markPending(Absensi $attendance, string $checkType): void
    {
        $scoreColumn = $checkType === 'checkout' ? 'face_score_checkout' : 'face_score_checkin';
        $attendance->update([
            $scoreColumn => null,
            'verification_status' => $this->resolveNextVerificationStatus($attendance->verification_status, 'pending'),
            'is_verified' => false,
            'verified_at' => null,
            'verified_by' => null,
        ]);
    }

    /**
     * Process verification immediately (sync) or by queue worker (async handler).
     */
    public function processVerification(
        Absensi $attendance,
        User $user,
        string $checkType,
        ?AttendanceSchema $schema = null,
        ?string $photoPath = null
    ): array {
        $startedAt = microtime(true);
        $threshold = (float) config('attendance.face.threshold', 0.72);
        $engineVersion = (string) config('attendance.face.engine_version', 'face-service-v1-cpu-placeholder');
        $facePolicy = $this->attendanceRuntimeConfigService->getFaceVerificationPolicyConfig();
        $mode = $this->determineMode($schema);

        if (!(bool) ($facePolicy['enabled'] ?? true)) {
            $result = [
                'result' => 'verified',
                'score' => null,
                'reason_code' => 'face_feature_disabled',
            ];

            return $this->persistVerificationByMode(
                $attendance,
                $user,
                $checkType,
                $threshold,
                $engineVersion,
                $startedAt,
                $result,
                $mode
            );
        }

        if (empty($photoPath) && (bool) ($facePolicy['skip_when_photo_missing'] ?? true)) {
            $result = [
                'result' => 'verified',
                'score' => null,
                'reason_code' => 'photo_not_provided_skip',
            ];

            return $this->persistVerificationByMode(
                $attendance,
                $user,
                $checkType,
                $threshold,
                $engineVersion,
                $startedAt,
                $result,
                $mode
            );
        }

        $activeTemplate = UserFaceTemplate::active()
            ->where('user_id', $user->id)
            ->orderByDesc('enrolled_at')
            ->orderByDesc('id')
            ->first();

        if (!$activeTemplate) {
            $fallbackResult = (string) ($facePolicy['result_when_template_missing'] ?? 'verified');
            if (!in_array($fallbackResult, ['verified', 'rejected', 'manual_review'], true)) {
                $fallbackResult = 'verified';
            }

            $result = [
                'result' => $fallbackResult,
                'score' => null,
                'reason_code' => 'template_missing',
            ];

            return $this->persistVerificationByMode(
                $attendance,
                $user,
                $checkType,
                $threshold,
                $engineVersion,
                $startedAt,
                $result,
                $mode
            );
        }

        $templateVector = $this->resolveTemplateVector($activeTemplate);
        if ($templateVector === null) {
            $result = [
                'result' => 'manual_review',
                'score' => null,
                'reason_code' => 'template_vector_unavailable',
            ];

            return $this->persistVerificationByMode(
                $attendance,
                $user,
                $checkType,
                $threshold,
                $engineVersion,
                $startedAt,
                $result,
                $mode
            );
        }

        $absolutePhotoPath = $this->resolveStoragePath($photoPath);
        if ($absolutePhotoPath === null) {
            $result = [
                'result' => 'manual_review',
                'score' => null,
                'reason_code' => 'photo_not_found',
            ];

            return $this->persistVerificationByMode(
                $attendance,
                $user,
                $checkType,
                $threshold,
                $engineVersion,
                $startedAt,
                $result,
                $mode
            );
        }

        try {
            $serviceResult = $this->faceRecognitionClient->verify(
                $absolutePhotoPath,
                $templateVector,
                $threshold,
                basename($absolutePhotoPath)
            );

            $result = [
                'result' => (string) ($serviceResult['result'] ?? 'manual_review'),
                'score' => isset($serviceResult['score']) ? (float) $serviceResult['score'] : null,
                'reason_code' => (string) ($serviceResult['reason_code'] ?? 'face_service_unknown'),
                'metadata' => is_array($serviceResult['metadata'] ?? null) ? $serviceResult['metadata'] : [],
                'engine_version' => (string) ($serviceResult['template_version'] ?? $engineVersion),
            ];

            if (
                $result['result'] === 'rejected' &&
                $result['reason_code'] === 'below_threshold' &&
                (bool) ($facePolicy['reject_to_manual_review'] ?? true)
            ) {
                $result['result'] = 'manual_review';
                $result['reason_code'] = 'below_threshold_manual_review';
            }

            return $this->persistVerificationByMode(
                $attendance,
                $user,
                $checkType,
                $threshold,
                (string) ($result['engine_version'] ?? $engineVersion),
                $startedAt,
                $result,
                $mode
            );
        } catch (FaceRecognitionServiceException $exception) {
            Log::warning('Face verification service call failed', [
                'attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'check_type' => $checkType,
                'message' => $exception->getMessage(),
            ]);

            $result = [
                'result' => 'manual_review',
                'score' => null,
                'reason_code' => 'face_service_unavailable',
                'metadata' => [
                    'service_error' => $exception->getMessage(),
                ],
            ];

            return $this->persistVerificationByMode(
                $attendance,
                $user,
                $checkType,
                $threshold,
                $engineVersion,
                $startedAt,
                $result,
                $mode
            );
        }
    }

    private function persistVerificationByMode(
        Absensi $attendance,
        User $user,
        string $checkType,
        float $threshold,
        string $engineVersion,
        float $startedAt,
        array $result,
        string $mode
    ): array {
        $normalizedResult = $this->normalizeResultForMode($result, $mode);

        return $this->persistVerification(
            $attendance,
            $user,
            $checkType,
            $threshold,
            $engineVersion,
            $startedAt,
            $normalizedResult
        );
    }

    private function normalizeResultForMode(array $result, string $mode): array
    {
        if ($mode !== 'sync_final') {
            return $result;
        }

        $normalizedResult = strtolower(trim((string) ($result['result'] ?? '')));
        if ($normalizedResult !== 'manual_review') {
            return $result;
        }

        $reasonCode = strtolower(trim((string) ($result['reason_code'] ?? 'manual_review')));
        if ($reasonCode === '' || $reasonCode === 'manual_review') {
            $reasonCode = 'sync_final_manual_review_rejected';
        }
        if ($reasonCode === 'below_threshold_manual_review') {
            $reasonCode = 'below_threshold';
        }

        $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
        $metadata['sync_final_forced_reject'] = true;
        $metadata['sync_final_original_result'] = 'manual_review';

        $result['result'] = 'rejected';
        $result['reason_code'] = $reasonCode;
        $result['metadata'] = $metadata;

        return $result;
    }

    private function persistVerification(
        Absensi $attendance,
        User $user,
        string $checkType,
        float $threshold,
        string $engineVersion,
        float $startedAt,
        array $result
    ): array {
        $processingMs = (int) round((microtime(true) - $startedAt) * 1000);
        $score = $result['score'];
        $finalResult = $result['result'];
        $reasonCode = $result['reason_code'] ?? null;
        $metadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];

        AttendanceFaceVerification::create([
            'absensi_id' => $attendance->id,
            'user_id' => $user->id,
            'check_type' => $checkType,
            'score' => $score,
            'threshold' => $threshold,
            'result' => $finalResult,
            'reason_code' => $reasonCode,
            'engine_version' => $engineVersion,
            'processing_ms' => $processingMs,
            'metadata' => [
                'mode' => 'face_service',
                'processed_at' => now()->toISOString(),
                ...$metadata,
            ],
        ]);

        $scoreColumn = $checkType === 'checkout' ? 'face_score_checkout' : 'face_score_checkin';
        $nextStatus = $this->resolveNextVerificationStatus($attendance->verification_status, $finalResult);
        $isVerified = $nextStatus === 'verified';

        $attendance->update([
            $scoreColumn => $score,
            'verification_status' => $nextStatus,
            'is_verified' => $isVerified,
            'verified_at' => $isVerified ? Carbon::now() : null,
            'verified_by' => null,
        ]);

        Log::info('Attendance face verification processed', [
            'attendance_id' => $attendance->id,
            'user_id' => $user->id,
            'check_type' => $checkType,
            'result' => $finalResult,
            'score' => $score,
            'threshold' => $threshold,
            'processing_ms' => $processingMs,
            'reason_code' => $reasonCode,
        ]);

        return [
            'status' => $nextStatus,
            'result' => $finalResult,
            'score' => $score,
            'threshold' => $threshold,
            'reason_code' => $reasonCode,
            'processing_ms' => $processingMs,
            'engine_version' => $engineVersion,
        ];
    }

    /**
     * @return array<int, float>|null
     */
    private function resolveTemplateVector(UserFaceTemplate $template): ?array
    {
        $decoded = $this->decodeTemplateVector($template->template_vector);
        if ($decoded !== null) {
            return $decoded;
        }

        $absoluteTemplatePath = $this->resolveStoragePath($template->template_path);
        if ($absoluteTemplatePath === null) {
            return null;
        }

        try {
            $payload = $this->faceRecognitionClient->enroll(
                $absoluteTemplatePath,
                basename($absoluteTemplatePath)
            );

            $templateVector = isset($payload['template_vector']) && is_array($payload['template_vector'])
                ? array_values(array_map('floatval', $payload['template_vector']))
                : null;

            if ($templateVector === null) {
                return null;
            }

            $template->update([
                'template_vector' => json_encode($templateVector, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'template_version' => (string) ($payload['template_version'] ?? $template->template_version),
                'quality_score' => isset($payload['quality_score']) ? (float) $payload['quality_score'] : $template->quality_score,
            ]);

            return $templateVector;
        } catch (FaceRecognitionServiceException $exception) {
            Log::warning('Failed to backfill face template vector', [
                'template_id' => $template->id,
                'user_id' => $template->user_id,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<int, float>|null
     */
    private function decodeTemplateVector(?string $value): ?array
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded) || $decoded === []) {
            return null;
        }

        return array_values(array_map('floatval', $decoded));
    }

    private function resolveStoragePath(?string $relativePath): ?string
    {
        if (!is_string($relativePath) || trim($relativePath) === '') {
            return null;
        }

        $normalized = ltrim($relativePath, '/');
        if (!Storage::disk('public')->exists($normalized)) {
            return null;
        }

        return Storage::disk('public')->path($normalized);
    }

    private function resolveNextVerificationStatus(?string $currentStatus, string $newStatus): string
    {
        $current = $currentStatus ?: 'pending';

        if ($newStatus === 'pending') {
            return $current;
        }

        if (in_array($newStatus, ['rejected', 'manual_review'], true)) {
            return $newStatus;
        }

        if ($newStatus === 'verified' && in_array($current, ['rejected', 'manual_review'], true)) {
            return $current;
        }

        return in_array($newStatus, ['verified', 'rejected', 'manual_review'], true)
            ? $newStatus
            : $current;
    }
}
