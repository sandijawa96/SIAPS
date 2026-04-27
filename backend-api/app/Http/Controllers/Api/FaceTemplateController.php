<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\FaceRecognitionServiceException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFaceTemplate;
use App\Models\UserFaceTemplateSubmissionState;
use App\Services\FaceRecognitionClient;
use App\Support\RoleNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FaceTemplateController extends Controller
{
    private const SELF_SUBMIT_LIMIT = 3;

    public function __construct(
        private readonly FaceRecognitionClient $faceRecognitionClient
    ) {
    }

    public function showMine(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User || !$this->isStudentUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Self submit template wajah hanya tersedia untuk siswa.',
            ], 403);
        }

        try {
            return response()->json([
                'success' => true,
                'data' => $this->buildFaceTemplatePayload($user),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to build self face template payload', [
                'userId' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status template wajah dibuka dalam mode fallback.',
                'data' => $this->buildFaceTemplateFallbackPayload($user),
            ]);
        }
    }

    public function show(User $user): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->buildFaceTemplatePayload($user),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Failed to build managed face template payload', [
                'userId' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status template wajah dibuka dalam mode fallback.',
                'data' => $this->buildFaceTemplateFallbackPayload($user),
            ]);
        }
    }

    public function enroll(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'foto' => ['nullable', 'string'],
            'foto_file' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (!$request->hasFile('foto_file') && !is_string($request->input('foto'))) {
                $validator->errors()->add('foto', 'Foto template wajah wajib diisi.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi template wajah gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = User::findOrFail((int) $request->integer('user_id'));
        try {
            $photoPath = $this->resolveTemplatePhotoPath($request, $user->id);
            $absolutePath = Storage::disk('public')->path($photoPath);
            $payload = $this->faceRecognitionClient->enroll($absolutePath, basename($absolutePath));
        } catch (\InvalidArgumentException | FaceRecognitionServiceException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        $template = DB::transaction(
            fn () => $this->storeTemplateForUser($user, $photoPath, $payload, $request->user()?->id)
        );

        return response()->json([
            'success' => true,
            'message' => 'Template wajah berhasil disimpan',
            'data' => $this->serializeTemplate($template),
        ]);
    }

    public function selfSubmit(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user instanceof User || !$this->isStudentUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Self submit template wajah hanya tersedia untuk siswa.',
            ], 403);
        }

        if (!$this->isSubmissionStateFeatureAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur kuota template wajah belum siap. Jalankan migrasi backend terbaru terlebih dahulu.',
                'data' => $this->buildFaceTemplatePayload($user),
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'foto' => ['nullable', 'string'],
            'foto_file' => ['nullable', 'file', 'image', 'max:5120'],
        ]);

        $validator->after(function ($validator) use ($request) {
            if (!$request->hasFile('foto_file') && !is_string($request->input('foto'))) {
                $validator->errors()->add('foto', 'Foto template wajah wajib diisi.');
            }
        });

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi template wajah gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $submissionState = $this->resolveSubmissionState($user);
        $selfSubmitCount = (int) $submissionState->self_submit_count;
        $unlockAllowance = (int) $submissionState->unlock_allowance_remaining;
        $withinBaseLimit = $selfSubmitCount < self::SELF_SUBMIT_LIMIT;

        if (!$withinBaseLimit && $unlockAllowance < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Kuota self submit template wajah sudah habis. Hubungi admin, wali kelas, atau kesiswaan untuk membuka 1 kali submit tambahan.',
                'data' => $this->buildFaceTemplatePayload($user),
            ], 422);
        }

        try {
            $photoPath = $this->resolveTemplatePhotoPath($request, $user->id);
            $absolutePath = Storage::disk('public')->path($photoPath);
            $payload = $this->faceRecognitionClient->enroll($absolutePath, basename($absolutePath));
        } catch (\InvalidArgumentException | FaceRecognitionServiceException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 422);
        }

        DB::transaction(function () use ($user, $photoPath, $payload, $submissionState, $selfSubmitCount, $unlockAllowance) {
            $this->storeTemplateForUser($user, $photoPath, $payload, $user->id);

            $updates = [
                'self_submit_count' => $selfSubmitCount + 1,
                'last_submitted_at' => now(),
            ];

            if ($selfSubmitCount >= self::SELF_SUBMIT_LIMIT && $unlockAllowance > 0) {
                $updates['unlock_allowance_remaining'] = max(0, $unlockAllowance - 1);
            }

            $submissionState->fill($updates);
            $submissionState->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Template wajah berhasil diperbarui dari akun siswa.',
            'data' => $this->buildFaceTemplatePayload($user->fresh()),
        ]);
    }

    public function unlockSelfSubmit(Request $request, User $user): JsonResponse
    {
        if (!$this->isStudentUser($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Unlock self submit hanya berlaku untuk akun siswa.',
            ], 422);
        }

        if (!$this->isSubmissionStateFeatureAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur kuota template wajah belum siap. Jalankan migrasi backend terbaru terlebih dahulu.',
                'data' => $this->buildFaceTemplatePayload($user),
            ], 503);
        }

        $submissionState = $this->resolveSubmissionState($user);
        $selfSubmitCount = (int) $submissionState->self_submit_count;
        $unlockAllowance = (int) $submissionState->unlock_allowance_remaining;

        if ($selfSubmitCount < self::SELF_SUBMIT_LIMIT) {
            return response()->json([
                'success' => false,
                'message' => 'Kuota self submit siswa masih tersedia. Unlock tambahan belum diperlukan.',
                'data' => $this->buildFaceTemplatePayload($user),
            ], 422);
        }

        if ($unlockAllowance > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Jatah submit tambahan sudah aktif dan belum digunakan.',
                'data' => $this->buildFaceTemplatePayload($user),
            ], 422);
        }

        $submissionState->fill([
            'unlock_allowance_remaining' => 1,
            'last_unlocked_at' => now(),
            'last_unlocked_by' => $request->user()?->id,
        ]);
        $submissionState->save();

        return response()->json([
            'success' => true,
            'message' => '1 kali submit tambahan berhasil dibuka untuk siswa ini.',
            'data' => $this->buildFaceTemplatePayload($user->fresh()),
        ]);
    }

    public function deactivate(UserFaceTemplate $template): JsonResponse
    {
        $template->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Template wajah dinonaktifkan',
        ]);
    }

    private function resolveTemplatePhotoPath(Request $request, int $userId): string
    {
        if ($request->hasFile('foto_file')) {
            $uploadedFile = $request->file('foto_file');
            $extension = strtolower((string) ($uploadedFile->extension() ?: 'jpg'));
            $filename = 'template_' . $userId . '_' . time() . '.' . $extension;
            Storage::disk('public')->putFileAs('face-templates', $uploadedFile, $filename);

            return 'face-templates/' . $filename;
        }

        $base64Image = (string) $request->input('foto', '');
        if (str_contains($base64Image, ';base64,')) {
            $imageParts = explode(';base64,', $base64Image, 2);
            $base64Body = $imageParts[1] ?? '';
        } else {
            $base64Body = $base64Image;
        }

        $decodedImage = base64_decode($base64Body, true);
        if ($decodedImage === false) {
            throw new \InvalidArgumentException('Format foto base64 tidak valid');
        }

        $filename = 'template_' . $userId . '_' . time() . '.jpg';
        Storage::disk('public')->put('face-templates/' . $filename, $decodedImage);

        return 'face-templates/' . $filename;
    }

    private function storeTemplateForUser(User $user, string $photoPath, array $payload, ?int $actorId): UserFaceTemplate
    {
        UserFaceTemplate::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return UserFaceTemplate::create([
            'user_id' => $user->id,
            'template_vector' => json_encode(
                $payload['template_vector'] ?? [],
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ),
            'template_path' => $photoPath,
            'template_version' => (string) ($payload['template_version'] ?? config('attendance.face.engine_version')),
            'quality_score' => (float) ($payload['quality_score'] ?? 0),
            'enrolled_at' => now(),
            'enrolled_by' => $actorId,
            'is_active' => true,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFaceTemplatePayload(User $user): array
    {
        $activeTemplate = $this->resolveActiveTemplate($user);
        $templatesCount = $this->resolveTemplateCount($user);
        $submissionState = $this->resolveSubmissionStateRecord($user);

        return [
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?: $user->email,
            'has_active_template' => $activeTemplate !== null,
            'active_template' => $activeTemplate ? $this->serializeTemplate($activeTemplate) : null,
            'templates_count' => $templatesCount,
            'submission_state' => $this->serializeSubmissionState($submissionState),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFaceTemplateFallbackPayload(User $user): array
    {
        return [
            'user_id' => $user->id,
            'user_name' => $user->nama_lengkap ?: $user->email,
            'has_active_template' => false,
            'active_template' => null,
            'templates_count' => 0,
            'submission_state' => $this->serializeSubmissionState(null),
        ];
    }

    private function resolveSubmissionState(User $user): UserFaceTemplateSubmissionState
    {
        return UserFaceTemplateSubmissionState::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'self_submit_count' => 0,
                'unlock_allowance_remaining' => 0,
            ]
        );
    }

    private function resolveSubmissionStateRecord(User $user): ?UserFaceTemplateSubmissionState
    {
        if (!$this->isSubmissionStateFeatureAvailable()) {
            return null;
        }

        return UserFaceTemplateSubmissionState::query()
            ->with('lastUnlockedBy')
            ->where('user_id', $user->id)
            ->first();
    }

    private function resolveActiveTemplate(User $user): ?UserFaceTemplate
    {
        if (!$this->isFaceTemplateFeatureAvailable()) {
            return null;
        }

        return UserFaceTemplate::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest('enrolled_at')
            ->latest('id')
            ->first();
    }

    private function resolveTemplateCount(User $user): int
    {
        if (!$this->isFaceTemplateFeatureAvailable()) {
            return 0;
        }

        return (int) UserFaceTemplate::query()
            ->where('user_id', $user->id)
            ->count();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSubmissionState(?UserFaceTemplateSubmissionState $state): array
    {
        $selfSubmitCount = (int) ($state?->self_submit_count ?? 0);
        $unlockAllowance = (int) ($state?->unlock_allowance_remaining ?? 0);
        $canSelfSubmitNow = $selfSubmitCount < self::SELF_SUBMIT_LIMIT || $unlockAllowance > 0;

        return [
            'limit' => self::SELF_SUBMIT_LIMIT,
            'self_submit_count' => $selfSubmitCount,
            'base_quota_remaining' => max(0, self::SELF_SUBMIT_LIMIT - $selfSubmitCount),
            'unlock_allowance_remaining' => $unlockAllowance,
            'can_self_submit_now' => $canSelfSubmitNow,
            'requires_admin_unlock' => !$canSelfSubmitNow,
            'last_submitted_at' => optional($state?->last_submitted_at)->toISOString(),
            'last_unlocked_at' => optional($state?->last_unlocked_at)->toISOString(),
            'last_unlocked_by' => $state?->last_unlocked_by,
            'last_unlocked_by_name' => $state?->lastUnlockedBy?->nama_lengkap
                ?: $state?->lastUnlockedBy?->email,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTemplate(UserFaceTemplate $template): array
    {
        return [
            'id' => $template->id,
            'user_id' => $template->user_id,
            'template_version' => $template->template_version,
            'quality_score' => $template->quality_score !== null ? (float) $template->quality_score : null,
            'template_path' => $template->template_path,
            'template_url' => User::resolveStoredPhotoUrl($template->template_path, $template->updated_at?->timestamp),
            'enrolled_at' => optional($template->enrolled_at)->toISOString(),
            'enrolled_by' => $template->enrolled_by,
            'is_active' => (bool) $template->is_active,
        ];
    }

    private function isStudentUser(User $user): bool
    {
        return $user->roles()
            ->whereIn('name', RoleNames::aliases(RoleNames::SISWA))
            ->exists();
    }

    private function isSubmissionStateFeatureAvailable(): bool
    {
        return Schema::hasTable('user_face_template_submission_states');
    }

    private function isFaceTemplateFeatureAvailable(): bool
    {
        return Schema::hasTable('user_face_templates');
    }
}
