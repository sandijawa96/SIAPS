<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserPersonalDocument;
use App\Services\NextcloudStorageService;
use App\Support\RoleNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class PersonalDataDocumentController extends Controller
{
    private const MAX_UPLOAD_KB = 10240;

    public function __construct(private readonly NextcloudStorageService $storage)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $targetUser = $this->resolveSelfTarget($request);
        if (!$targetUser instanceof User) {
            return $targetUser;
        }

        return $this->documentsResponse($targetUser);
    }

    public function indexForUser(Request $request, int $id): JsonResponse
    {
        $targetUser = $this->resolveAdminTarget($request, $id);
        if (!$targetUser instanceof User) {
            return $targetUser;
        }

        return $this->documentsResponse($targetUser);
    }

    public function store(Request $request): JsonResponse
    {
        $targetUser = $this->resolveSelfTarget($request);
        if (!$targetUser instanceof User) {
            return $targetUser;
        }

        return $this->storeForTarget($request, $targetUser);
    }

    public function storeForUser(Request $request, int $id): JsonResponse
    {
        $targetUser = $this->resolveAdminTarget($request, $id);
        if (!$targetUser instanceof User) {
            return $targetUser;
        }

        return $this->storeForTarget($request, $targetUser);
    }

    public function destroy(Request $request, UserPersonalDocument $document): JsonResponse
    {
        $targetUser = $this->resolveSelfTarget($request);
        if (!$targetUser instanceof User) {
            return $targetUser;
        }

        return $this->destroyForTarget($document, $targetUser);
    }

    public function destroyForUser(Request $request, int $id, UserPersonalDocument $document): JsonResponse
    {
        $targetUser = $this->resolveAdminTarget($request, $id);
        if (!$targetUser instanceof User) {
            return $targetUser;
        }

        return $this->destroyForTarget($document, $targetUser);
    }

    private function documentsResponse(User $targetUser): JsonResponse
    {
        $documents = UserPersonalDocument::query()
            ->with('uploader:id,nama_lengkap')
            ->where('user_id', $targetUser->id)
            ->latest()
            ->get()
            ->map(static fn(UserPersonalDocument $document): array => $document->toApiArray())
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'nextcloud_configured' => $this->storage->isConfigured(),
                'documents' => $documents,
            ],
        ]);
    }

    private function storeForTarget(Request $request, User $targetUser): JsonResponse
    {
        if (!$this->storage->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Nextcloud sekolah belum dikonfigurasi di server.',
            ], 503);
        }

        $validator = Validator::make($request->all(), [
            'document_type' => 'nullable|string|max:80',
            'title' => 'nullable|string|max:255',
            'file' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png,webp,doc,docx,xls,xlsx',
                'max:' . self::MAX_UPLOAD_KB,
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data upload dokumen tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('file');
        if (!$file instanceof UploadedFile) {
            return response()->json([
                'success' => false,
                'message' => 'File upload tidak ditemukan',
            ], 422);
        }

        $validated = $validator->validated();
        $documentType = $this->normalizeDocumentType((string) ($validated['document_type'] ?? 'other'));
        $relativePath = $this->buildRemotePath($targetUser, $documentType, $file);
        $replacementTypes = $this->replacementDocumentTypes($targetUser, $documentType);
        $previousDocuments = UserPersonalDocument::query()
            ->where('user_id', $targetUser->id)
            ->whereIn('document_type', $replacementTypes)
            ->get();
        $uploadPayload = null;

        try {
            $uploadPayload = $this->storage->upload($file, $relativePath);
            $document = UserPersonalDocument::query()->create([
                'user_id' => (int) $targetUser->id,
                'uploaded_by' => (int) $request->user()->id,
                'document_type' => $documentType,
                'title' => $validated['title'] ?? null,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'checksum_sha256' => hash_file('sha256', $file->getRealPath()) ?: null,
                'storage_provider' => $uploadPayload['provider'] ?? 'nextcloud',
                'remote_path' => $uploadPayload['path'] ?? $relativePath,
                'remote_url' => $uploadPayload['url'] ?? null,
                'metadata' => [
                    'etag' => $uploadPayload['etag'] ?? null,
                    'uploaded_from_ip' => $request->ip(),
                    'uploaded_user_agent' => Str::limit((string) $request->userAgent(), 255, ''),
                ],
            ]);
        } catch (RuntimeException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 502);
        } catch (Throwable $exception) {
            if ($uploadPayload === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload ke Nextcloud gagal.',
                    'error' => config('app.debug') ? $exception->getMessage() : null,
                ], 502);
            }

            if (is_array($uploadPayload) && isset($uploadPayload['path'])) {
                try {
                    $this->storage->delete((string) $uploadPayload['path']);
                } catch (Throwable) {
                    // Metadata save failure is reported below; cleanup failure must not mask it.
                }
            }

            return response()->json([
                'success' => false,
                'message' => 'Dokumen berhasil dikirim ke Nextcloud, tetapi metadata gagal disimpan.',
                'error' => config('app.debug') ? $exception->getMessage() : null,
            ], 500);
        }

        $replacementSummary = $this->cleanupReplacedDocuments($previousDocuments, $document);
        $document->load('uploader:id,nama_lengkap');

        return response()->json([
            'success' => true,
            'message' => $replacementSummary['replaced_count'] > 0
                ? 'Dokumen berhasil diganti dan file lama dibersihkan dari arsip aktif.'
                : 'Dokumen berhasil diupload ke Nextcloud sekolah.',
            'data' => $document->toApiArray(),
            'meta' => [
                'replacement' => $replacementSummary,
            ],
        ], 201);
    }

    private function destroyForTarget(UserPersonalDocument $document, User $targetUser): JsonResponse
    {
        if ((int) $document->user_id !== (int) $targetUser->id) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak ditemukan pada profil ini.',
            ], 404);
        }

        $remoteDeleted = $this->storage->delete((string) $document->remote_path);
        $document->delete();

        return response()->json([
            'success' => true,
            'message' => $remoteDeleted
                ? 'Dokumen berhasil dihapus dari Nextcloud sekolah.'
                : 'Metadata dokumen dihapus. File remote tidak dapat dikonfirmasi karena Nextcloud belum tersedia.',
            'data' => [
                'remote_deleted' => $remoteDeleted,
            ],
        ]);
    }

    private function resolveSelfTarget(Request $request): User|JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        if ($this->isSuperAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin menggunakan halaman manajemen pengguna',
            ], 403);
        }

        return $user;
    }

    private function resolveAdminTarget(Request $request, int $id): User|JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User target tidak ditemukan',
            ], 404);
        }

        if ($this->isSuperAdmin($targetUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Data pribadi Super Admin dikelola dari manajemen pengguna inti',
            ], 403);
        }

        return $targetUser;
    }

    private function buildRemotePath(User $targetUser, string $documentType, UploadedFile $file): string
    {
        $profileType = $this->isStudent($targetUser) ? 'siswa' : 'pegawai';
        $filename = $this->storage->makeSafeFilename($file->getClientOriginalName());

        return implode('/', [
            'personal-data',
            $profileType,
            'user-' . $targetUser->id,
            $documentType,
            $filename,
        ]);
    }

    private function cleanupReplacedDocuments($previousDocuments, UserPersonalDocument $replacement): array
    {
        $replacedIds = [];
        $remoteDeleteFailedIds = [];
        $cleanupFailedIds = [];

        foreach ($previousDocuments as $previousDocument) {
            if ((int) $previousDocument->id === (int) $replacement->id) {
                continue;
            }

            $metadata = is_array($previousDocument->metadata) ? $previousDocument->metadata : [];
            $previousDocument->metadata = array_merge($metadata, [
                'replaced_by_document_id' => (int) $replacement->id,
                'replaced_at' => now()->toIso8601String(),
            ]);

            try {
                $previousDocument->save();
                $previousDocument->delete();
            } catch (Throwable) {
                $cleanupFailedIds[] = (int) $previousDocument->id;
                continue;
            }

            $remoteDeleted = false;
            try {
                $remoteDeleted = $this->storage->delete((string) $previousDocument->remote_path);
            } catch (Throwable) {
                $remoteDeleted = false;
            }

            $replacedIds[] = (int) $previousDocument->id;
            if (!$remoteDeleted) {
                $remoteDeleteFailedIds[] = (int) $previousDocument->id;
            }
        }

        return [
            'replaced_count' => count($replacedIds),
            'replaced_document_ids' => $replacedIds,
            'remote_delete_failed_document_ids' => $remoteDeleteFailedIds,
            'cleanup_failed_document_ids' => $cleanupFailedIds,
        ];
    }

    private function replacementDocumentTypes(User $targetUser, string $documentType): array
    {
        $groups = $this->isStudent($targetUser)
            ? [
                ['ktp_siswa', 'identitas'],
                ['ijazah_smp', 'ijazah_sd', 'ijazah', 'akademik'],
            ]
            : [
                ['ktp', 'identitas'],
                ['ijazah_terakhir', 'ijazah', 'akademik'],
                ['sk_pengangkatan', 'sk'],
            ];

        foreach ($groups as $group) {
            if (in_array($documentType, $group, true)) {
                return $group;
            }
        }

        return [$documentType];
    }

    private function normalizeDocumentType(string $documentType): string
    {
        $normalized = Str::slug($documentType, '_');

        return $normalized !== '' ? $normalized : 'other';
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::SUPER_ADMIN));
    }

    private function isStudent(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::SISWA));
    }
}
