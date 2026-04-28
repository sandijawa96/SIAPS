<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MobileRelease;
use App\Services\MobileReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MobileReleaseController extends Controller
{
    public function __construct(
        private readonly MobileReleaseService $mobileReleaseService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app_key' => ['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:60'],
            'target_audience' => 'nullable|in:all,siswa,staff',
            'platform' => 'nullable|in:android,ios',
            'release_channel' => 'nullable|string|max:30',
            'is_active' => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter distribusi aplikasi tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $items = $this->mobileReleaseService->listForAdmin($validator->validated())
            ->map(fn (MobileRelease $release) => $this->mobileReleaseService->serializeForAdmin($release))
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function catalog(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app_key' => ['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:60'],
            'platform' => 'nullable|in:android,ios',
            'release_channel' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter pusat aplikasi tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $items = $this->mobileReleaseService->listForCatalog($request->user(), $validator->validated())
            ->map(fn (MobileRelease $release) => $this->mobileReleaseService->serializeForCatalog($release))
            ->all();

        return response()->json([
            'success' => true,
            'data' => $items,
            'generated_at' => now()->toISOString(),
        ]);
    }

    public function show(MobileRelease $mobileRelease): JsonResponse
    {
        $mobileRelease->loadMissing([
            'creator:id,nama_lengkap',
            'updater:id,nama_lengkap',
            'updatePolicies:id,mobile_release_id,audience,update_mode,minimum_supported_version,minimum_supported_build_number,created_by,updated_by',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->mobileReleaseService->serializeForAdmin($mobileRelease),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->makeWriteValidator($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data distribusi aplikasi tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (($validated['is_active'] ?? false) && !($validated['is_published'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Release aktif harus dipublikasikan terlebih dahulu.',
                'errors' => [
                    'is_active' => ['Release aktif harus memiliki status publish.'],
                ],
            ], 422);
        }

        $release = $this->mobileReleaseService->create(
            $validated,
            auth()->id(),
            $request->file('asset_file')
        );

        return response()->json([
            'success' => true,
            'message' => 'Distribusi aplikasi berhasil dibuat',
            'data' => $this->mobileReleaseService->serializeForAdmin($release),
        ], 201);
    }

    public function update(Request $request, MobileRelease $mobileRelease): JsonResponse
    {
        $validator = $this->makeWriteValidator($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data distribusi aplikasi tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        if (($validated['is_active'] ?? false) && !($validated['is_published'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => 'Release aktif harus dipublikasikan terlebih dahulu.',
                'errors' => [
                    'is_active' => ['Release aktif harus memiliki status publish.'],
                ],
            ], 422);
        }

        $release = $this->mobileReleaseService->update(
            $mobileRelease,
            $validated,
            auth()->id(),
            $request->file('asset_file')
        );

        return response()->json([
            'success' => true,
            'message' => 'Distribusi aplikasi berhasil diperbarui',
            'data' => $this->mobileReleaseService->serializeForAdmin($release),
        ]);
    }

    public function destroy(MobileRelease $mobileRelease): JsonResponse
    {
        $this->mobileReleaseService->delete($mobileRelease);

        return response()->json([
            'success' => true,
            'message' => 'Distribusi aplikasi berhasil dihapus',
        ]);
    }

    public function checkAuthenticated(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app_key' => ['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:60'],
            'platform' => 'required|in:android,ios',
            'app_version' => 'nullable|string|max:50',
            'build_number' => 'nullable|integer|min:0|max:2147483647',
            'release_channel' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter cek versi mobile tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $payload = $this->mobileReleaseService->buildAuthenticatedVersionCheck(
            $request->user(),
            (string) $validated['platform'],
            $validated['app_version'] ?? null,
            $validated['build_number'] ?? null,
            (string) ($validated['release_channel'] ?? 'stable'),
            (string) ($validated['app_key'] ?? 'siaps')
        );

        if ($payload === null) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada release aktif untuk platform tersebut',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $payload,
        ]);
    }

    public function download(Request $request, MobileRelease $mobileRelease): StreamedResponse|JsonResponse|RedirectResponse
    {
        $authorizationError = $this->authorizeReleaseDownload($request, $mobileRelease);
        if ($authorizationError) {
            return $authorizationError;
        }

        return $this->streamReleaseDownload($mobileRelease);
    }

    public function downloadLink(Request $request, MobileRelease $mobileRelease): JsonResponse
    {
        $authorizationError = $this->authorizeReleaseDownload($request, $mobileRelease);
        if ($authorizationError) {
            return $authorizationError;
        }

        $expiresAt = now()->addMinutes(10);
        $assetDownloadUrl = URL::temporarySignedRoute(
            'mobile-releases.signed-download',
            $expiresAt,
            ['mobileRelease' => $mobileRelease->id]
        );
        $downloadUrl = $assetDownloadUrl;
        $iosManifestUrl = null;
        $iosInstallUrl = null;

        if (strtolower((string) $mobileRelease->platform) === 'ios') {
            $iosManifestUrl = URL::temporarySignedRoute(
                'mobile-releases.ios-manifest',
                $expiresAt,
                ['mobileRelease' => $mobileRelease->id]
            );
            $iosInstallUrl = $this->buildItmsInstallUrl($iosManifestUrl);
            $downloadUrl = $iosInstallUrl;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'download_url' => $downloadUrl,
                'asset_download_url' => $assetDownloadUrl,
                'ios_manifest_url' => $iosManifestUrl,
                'ios_install_url' => $iosInstallUrl,
                'expires_at' => $expiresAt->toISOString(),
                'filename' => $this->resolveDownloadName($mobileRelease),
                'file_size_bytes' => $mobileRelease->file_size_bytes,
            ],
        ]);
    }

    public function signedDownload(Request $request, MobileRelease $mobileRelease): StreamedResponse|JsonResponse|RedirectResponse
    {
        return $this->streamReleaseDownload($mobileRelease);
    }

    public function iosManifest(Request $request, MobileRelease $mobileRelease)
    {
        if (strtolower((string) $mobileRelease->platform) !== 'ios') {
            return response()->json([
                'success' => false,
                'message' => 'Manifest OTA hanya tersedia untuk release iOS.',
            ], 422);
        }

        $bundleIdentifier = $this->mobileReleaseService->resolveBundleIdentifier($mobileRelease);
        if (!is_string($bundleIdentifier) || trim($bundleIdentifier) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Bundle identifier iOS belum diisi untuk release ini.',
            ], 422);
        }

        $packageUrl = $this->resolveIosPackageUrl($mobileRelease);
        if (!is_string($packageUrl) || trim($packageUrl) === '') {
            return response()->json([
                'success' => false,
                'message' => 'File IPA belum tersedia untuk release ini.',
            ], 404);
        }

        $manifest = $this->buildIosManifest(
            $packageUrl,
            $bundleIdentifier,
            (string) $mobileRelease->public_version,
            (string) ($mobileRelease->app_label ?: $mobileRelease->app_name ?: 'SIAPS')
        );

        return response($manifest, 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function makeWriteValidator(Request $request)
    {
        $input = $request->all();
        $input['policies'] = $this->extractPoliciesPayload($request);

        $validator = Validator::make($input, [
            'app_key' => ['nullable', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:60'],
            'app_name' => 'nullable|string|max:120',
            'app_description' => 'nullable|string|max:5000',
            'target_audience' => 'nullable|in:all,siswa,staff',
            'bundle_identifier' => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9][A-Za-z0-9.-]*[A-Za-z0-9]$/'],
            'platform' => 'required|in:android,ios',
            'release_channel' => 'nullable|string|max:30',
            'public_version' => 'required|string|max:50',
            'build_number' => 'required|integer|min:1|max:2147483647',
            'download_url' => 'nullable|url|max:2048',
            'asset_file' => 'nullable|file|max:512000',
            'remove_asset' => 'nullable|boolean',
            'checksum_sha256' => ['nullable', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'file_size_bytes' => 'nullable|integer|min:1',
            'release_notes' => 'nullable|string|max:10000',
            'distribution_notes' => 'nullable|string|max:5000',
            'update_mode' => 'required|in:optional,required',
            'minimum_supported_version' => 'nullable|string|max:50',
            'minimum_supported_build_number' => 'nullable|integer|min:1|max:2147483647',
            'policies' => 'nullable|array|max:2',
            'policies.*.audience' => 'required|string|in:siswa,staff',
            'policies.*.update_mode' => 'required|string|in:optional,required',
            'policies.*.minimum_supported_version' => 'nullable|string|max:50',
            'policies.*.minimum_supported_build_number' => 'nullable|integer|min:1|max:2147483647',
            'is_active' => 'nullable|boolean',
            'is_published' => 'nullable|boolean',
        ]);

        $validator->after(function ($validator) use ($input, $request) {
            $policies = $input['policies'] ?? null;
            if (is_array($policies)) {
                $audiences = array_map(
                    fn ($policy) => strtolower(trim((string) ($policy['audience'] ?? ''))),
                    array_filter($policies, 'is_array')
                );

                if (count($audiences) !== count(array_unique($audiences))) {
                    $validator->errors()->add('policies', 'Audience policy tidak boleh duplikat.');
                }
            }

            $hasAssetUpload = $request->file('asset_file') !== null;
            $hasAssetRemoval = filter_var($input['remove_asset'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $downloadUrl = trim((string) ($input['download_url'] ?? ''));
            $hasExternalUrl = $downloadUrl !== '';

            if (!$hasAssetUpload && !$hasExternalUrl && !$hasAssetRemoval && empty($request->route('mobileRelease'))) {
                $validator->errors()->add('asset_file', 'Unggah file aplikasi atau isi URL distribusi.');
            }
        });

        return $validator;
    }

    private function extractPoliciesPayload(Request $request): ?array
    {
        $rawPolicies = $request->input('policies');
        if (is_array($rawPolicies)) {
            return $rawPolicies;
        }

        $policiesJson = $request->input('policies_json');
        if (!is_string($policiesJson) || trim($policiesJson) === '') {
            return null;
        }

        $decoded = json_decode($policiesJson, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function streamReleaseDownload(MobileRelease $mobileRelease): StreamedResponse|JsonResponse|RedirectResponse
    {
        if ($mobileRelease->asset_path) {
            $disk = $mobileRelease->asset_disk ?: 'public';
            if (!Storage::disk($disk)->exists($mobileRelease->asset_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File aplikasi tidak ditemukan di server.',
                ], 404);
            }

            $downloadName = $this->resolveDownloadName($mobileRelease);

            return Storage::disk($disk)->download($mobileRelease->asset_path, $downloadName, [
                'Content-Type' => $mobileRelease->asset_mime_type ?: $this->guessDownloadMimeType($mobileRelease),
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        if ($mobileRelease->download_url) {
            return redirect()->away($mobileRelease->download_url);
        }

        return response()->json([
            'success' => false,
            'message' => 'Link unduhan belum tersedia untuk release ini.',
        ], 404);
    }

    private function authorizeReleaseDownload(Request $request, MobileRelease $mobileRelease): ?JsonResponse
    {
        $user = $request->user();
        $canManage = $this->mobileReleaseService->canManageReleases($user);

        if ($canManage) {
            return null;
        }

        if (!$mobileRelease->is_published || !$mobileRelease->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Release aplikasi tidak tersedia.',
            ], 404);
        }

        if (!$this->mobileReleaseService->canAccessCatalogRelease($user, $mobileRelease)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke aplikasi ini.',
            ], 403);
        }

        return null;
    }

    private function resolveDownloadName(MobileRelease $mobileRelease): string
    {
        if ($mobileRelease->asset_original_name) {
            return $mobileRelease->asset_original_name;
        }

        return sprintf(
            '%s-%s-%s.%s',
            $mobileRelease->app_key ?: 'app',
            $mobileRelease->platform ?: 'platform',
            $mobileRelease->build_number ?: 'release',
            pathinfo((string) $mobileRelease->asset_path, PATHINFO_EXTENSION) ?: 'bin'
        );
    }

    private function guessDownloadMimeType(MobileRelease $mobileRelease): string
    {
        return match (strtolower(pathinfo((string) $mobileRelease->asset_path, PATHINFO_EXTENSION))) {
            'apk' => 'application/vnd.android.package-archive',
            'ipa' => 'application/octet-stream',
            default => 'application/octet-stream',
        };
    }

    private function resolveIosPackageUrl(MobileRelease $mobileRelease): ?string
    {
        if (is_string($mobileRelease->download_url) && trim($mobileRelease->download_url) !== '') {
            return trim($mobileRelease->download_url);
        }

        if (is_string($mobileRelease->asset_path) && trim($mobileRelease->asset_path) !== '') {
            return URL::temporarySignedRoute(
                'mobile-releases.signed-download',
                now()->addHours(12),
                ['mobileRelease' => $mobileRelease->id]
            );
        }

        return null;
    }

    private function buildItmsInstallUrl(string $manifestUrl): string
    {
        return 'itms-services://?action=download-manifest&url=' . rawurlencode($manifestUrl);
    }

    private function buildIosManifest(
        string $packageUrl,
        string $bundleIdentifier,
        string $bundleVersion,
        string $title
    ): string {
        $packageUrl = $this->xml($packageUrl);
        $bundleIdentifier = $this->xml($bundleIdentifier);
        $bundleVersion = $this->xml($bundleVersion);
        $title = $this->xml($title);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>items</key>
    <array>
        <dict>
            <key>assets</key>
            <array>
                <dict>
                    <key>kind</key>
                    <string>software-package</string>
                    <key>url</key>
                    <string>{$packageUrl}</string>
                </dict>
            </array>
            <key>metadata</key>
            <dict>
                <key>bundle-identifier</key>
                <string>{$bundleIdentifier}</string>
                <key>bundle-version</key>
                <string>{$bundleVersion}</string>
                <key>kind</key>
                <string>software</string>
                <key>title</key>
                <string>{$title}</string>
            </dict>
        </dict>
    </array>
</dict>
</plist>
XML;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
