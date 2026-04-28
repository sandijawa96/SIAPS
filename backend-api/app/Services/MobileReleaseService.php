<?php

namespace App\Services;

use App\Models\MobileRelease;
use App\Models\MobileUpdatePolicy;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MobileReleaseService
{
    private const APP_KEY_DEFAULT = 'siaps';
    private const APP_NAME_DEFAULT = 'SIAPS Mobile';
    private const AUDIENCE_ALL = 'all';
    private const AUDIENCE_SISWA = 'siswa';
    private const AUDIENCE_STAFF = 'staff';
    private const STORAGE_DISK_PRIVATE = 'local';
    private const STORAGE_DISK_LEGACY_PUBLIC = 'public';

    public function listForAdmin(array $filters = []): EloquentCollection
    {
        $query = MobileRelease::query()
            ->with($this->releaseRelations())
            ->orderBy('app_name')
            ->orderByRaw("CASE platform WHEN 'android' THEN 0 WHEN 'ios' THEN 1 ELSE 2 END")
            ->orderBy('release_channel')
            ->orderByDesc('build_number')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if (!empty($filters['app_key'])) {
            $query->forApp((string) $filters['app_key']);
        }

        if (!empty($filters['platform'])) {
            $query->forPlatform((string) $filters['platform']);
        }

        if (!empty($filters['release_channel'])) {
            $query->forChannel((string) $filters['release_channel']);
        }

        if (!empty($filters['target_audience'])) {
            $query->where('target_audience', strtolower(trim((string) $filters['target_audience'])));
        }

        if (array_key_exists('is_published', $filters) && $filters['is_published'] !== null && $filters['is_published'] !== '') {
            $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN));
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->get();
    }

    public function listForCatalog(User $user, array $filters = []): EloquentCollection
    {
        $query = MobileRelease::query()
            ->with('updatePolicies')
            ->published()
            ->active()
            ->orderBy('app_name')
            ->orderByRaw("CASE platform WHEN 'android' THEN 0 WHEN 'ios' THEN 1 ELSE 2 END")
            ->orderBy('release_channel')
            ->orderByDesc('build_number')
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if (!empty($filters['app_key'])) {
            $query->forApp((string) $filters['app_key']);
        }

        if (!empty($filters['platform'])) {
            $query->forPlatform((string) $filters['platform']);
        }

        $query->forChannel((string) ($filters['release_channel'] ?? 'stable'));

        if (!$this->canBypassAudience($user)) {
            $query->whereIn('target_audience', [
                self::AUDIENCE_ALL,
                $this->resolveAudienceForUser($user),
            ]);
        }

        $items = $query->get();
        if ($items->isEmpty()) {
            return $items;
        }

        $latestPerPlatform = $items
            ->groupBy(fn (MobileRelease $release) => implode('|', [
                strtolower((string) $release->app_key),
                strtolower((string) $release->platform),
            ]))
            ->map(fn (Collection $grouped) => $grouped->first())
            ->filter()
            ->values();

        return new EloquentCollection($latestPerPlatform->all());
    }

    public function create(array $validated, ?int $actorUserId = null, ?UploadedFile $assetFile = null): MobileRelease
    {
        return DB::transaction(function () use ($validated, $actorUserId, $assetFile) {
            $release = new MobileRelease();
            $release->fill($this->normalizePayload($validated));
            $release->created_by = $actorUserId;
            $release->updated_by = $actorUserId;
            if ($assetFile !== null) {
                $this->storeAssetFile($release, $assetFile);
            }
            $this->syncPublishedState($release, null);
            $release->save();
            $this->syncUpdatePolicies($release, $validated['policies'] ?? null, $actorUserId);
            $this->deactivateSiblingReleases($release);

            return $release->fresh($this->releaseRelations());
        });
    }

    public function update(
        MobileRelease $release,
        array $validated,
        ?int $actorUserId = null,
        ?UploadedFile $assetFile = null
    ): MobileRelease {
        return DB::transaction(function () use ($release, $validated, $actorUserId, $assetFile) {
            $removeAsset = (bool) ($validated['remove_asset'] ?? false);
            $hadManagedAssetBeforeUpdate = is_string($release->asset_path) && trim($release->asset_path) !== '';

            if ($removeAsset) {
                $this->deleteStoredAssetIfManaged($release);
                $this->clearManagedAssetMetadata($release);
            }

            $release->fill($this->normalizePayload($validated));
            $release->updated_by = $actorUserId;

            if ($assetFile !== null) {
                $this->deleteStoredAssetIfManaged($release);
                $this->storeAssetFile($release, $assetFile);
            } elseif (
                !$removeAsset
                && $hadManagedAssetBeforeUpdate
                && is_string($release->download_url)
                && trim($release->download_url) !== ''
            ) {
                // Switching from managed asset to external URL should remove old private file.
                $this->deleteStoredAssetIfManaged($release);
                $this->clearManagedAssetMetadata($release);
            }

            $this->syncPublishedState($release, $release->getOriginal('is_published'));
            $release->save();
            $this->syncUpdatePolicies($release, $validated['policies'] ?? null, $actorUserId);
            $this->deactivateSiblingReleases($release);

            return $release->fresh($this->releaseRelations());
        });
    }

    public function delete(MobileRelease $release): void
    {
        $this->deleteStoredAssetIfManaged($release);
        $release->delete();
    }

    public function getLatestPublished(
        string $platform,
        string $releaseChannel = 'stable',
        string $appKey = self::APP_KEY_DEFAULT
    ): ?MobileRelease {
        return MobileRelease::query()
            ->with('updatePolicies')
            ->published()
            ->active()
            ->forApp($appKey)
            ->forPlatform($platform)
            ->forChannel($releaseChannel)
            ->orderByDesc('build_number')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function buildAuthenticatedVersionCheck(
        User $user,
        string $platform,
        ?string $currentVersion,
        int|string|null $currentBuildNumber,
        string $releaseChannel = 'stable',
        string $appKey = self::APP_KEY_DEFAULT
    ): ?array {
        $latest = $this->getLatestPublished($platform, $releaseChannel, $appKey);
        if (!$latest) {
            return null;
        }

        $audience = $this->resolveAudienceForUser($user);
        $effectivePolicy = $this->resolveEffectivePolicy($latest, $audience);

        return $this->buildPolicyAwarePayload(
            $latest,
            $currentVersion,
            $currentBuildNumber,
            $effectivePolicy,
            $audience
        );
    }

    /**
     * Public version gate for apps that do not use SIAPS login, such as SBT.
     *
     * @return array<string, mixed>|null
     */
    public function buildPublicVersionCheck(
        string $platform,
        ?string $currentVersion,
        int|string|null $currentBuildNumber,
        string $releaseChannel = 'stable',
        string $appKey = 'sbt-smanis',
        string $audience = self::AUDIENCE_SISWA
    ): ?array {
        $normalizedAudience = strtolower(trim($audience));
        if (!in_array($normalizedAudience, [self::AUDIENCE_SISWA, self::AUDIENCE_STAFF], true)) {
            $normalizedAudience = self::AUDIENCE_SISWA;
        }

        $latest = MobileRelease::query()
            ->with('updatePolicies')
            ->published()
            ->active()
            ->forApp($appKey)
            ->forPlatform($platform)
            ->forChannel($releaseChannel)
            ->whereIn('target_audience', [self::AUDIENCE_ALL, $normalizedAudience])
            ->orderByDesc('build_number')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->first();

        if (!$latest) {
            return null;
        }

        $effectivePolicy = $this->resolveEffectivePolicy($latest, $normalizedAudience);

        return $this->buildPolicyAwarePayload(
            $latest,
            $currentVersion,
            $currentBuildNumber,
            $effectivePolicy,
            $normalizedAudience
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForAdmin(MobileRelease $release): array
    {
        return [
            'id' => $release->id,
            'app_key' => $release->app_key,
            'app_name' => $release->app_name,
            'app_label' => $release->app_label,
            'app_description' => $release->app_description,
            'target_audience' => $release->target_audience,
            'target_audience_label' => $release->target_audience_label,
            'bundle_identifier' => $this->resolveBundleIdentifier($release),
            'platform' => $release->platform,
            'platform_label' => $release->platform_label,
            'release_channel' => $release->release_channel,
            'public_version' => $release->public_version,
            'build_number' => (int) $release->build_number,
            'download_url' => $release->download_url,
            'effective_download_url' => $this->resolveEffectiveDownloadUrl($release),
            'download_kind' => $this->resolveDownloadKind($release),
            'asset_path' => $release->asset_path,
            'asset_disk' => $release->asset_disk,
            'asset_original_name' => $release->asset_original_name,
            'asset_mime_type' => $release->asset_mime_type,
            'checksum_sha256' => $release->checksum_sha256,
            'file_size_bytes' => $release->file_size_bytes !== null ? (int) $release->file_size_bytes : null,
            'release_notes' => $release->release_notes,
            'distribution_notes' => $release->distribution_notes,
            'update_mode' => $release->update_mode,
            'minimum_supported_version' => $release->minimum_supported_version,
            'minimum_supported_build_number' => $release->minimum_supported_build_number !== null
                ? (int) $release->minimum_supported_build_number
                : null,
            'update_policies' => $this->serializeUpdatePolicies($release),
            'is_active' => (bool) $release->is_active,
            'is_published' => (bool) $release->is_published,
            'published_at' => optional($release->published_at)?->toISOString(),
            'created_at' => optional($release->created_at)?->toISOString(),
            'updated_at' => optional($release->updated_at)?->toISOString(),
            'creator' => $release->creator ? [
                'id' => $release->creator->id,
                'nama_lengkap' => $release->creator->nama_lengkap,
            ] : null,
            'updater' => $release->updater ? [
                'id' => $release->updater->id,
                'nama_lengkap' => $release->updater->nama_lengkap,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForCatalog(MobileRelease $release): array
    {
        return [
            'id' => $release->id,
            'app_key' => $release->app_key,
            'app_name' => $release->app_name,
            'app_label' => $release->app_label,
            'app_description' => $release->app_description,
            'target_audience' => $release->target_audience,
            'target_audience_label' => $release->target_audience_label,
            'bundle_identifier' => $this->resolveBundleIdentifier($release),
            'platform' => $release->platform,
            'platform_label' => $release->platform_label,
            'release_channel' => $release->release_channel,
            'public_version' => $release->public_version,
            'build_number' => (int) $release->build_number,
            'download_url' => $this->resolveEffectiveDownloadUrl($release),
            'download_kind' => $this->resolveDownloadKind($release),
            'asset_original_name' => $release->asset_original_name,
            'asset_mime_type' => $release->asset_mime_type,
            'checksum_sha256' => $release->checksum_sha256,
            'file_size_bytes' => $release->file_size_bytes !== null ? (int) $release->file_size_bytes : null,
            'release_notes' => $release->release_notes,
            'distribution_notes' => $release->distribution_notes,
            'update_mode' => $release->update_mode,
            'minimum_supported_version' => $release->minimum_supported_version,
            'minimum_supported_build_number' => $release->minimum_supported_build_number !== null
                ? (int) $release->minimum_supported_build_number
                : null,
            'published_at' => optional($release->published_at)?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeForPublic(MobileRelease $release): array
    {
        return [
            'id' => $release->id,
            'app_key' => $release->app_key,
            'app_name' => $release->app_name,
            'app_label' => $release->app_label,
            'bundle_identifier' => $this->resolveBundleIdentifier($release),
            'platform' => $release->platform,
            'platform_label' => $release->platform_label,
            'release_channel' => $release->release_channel,
            'public_version' => $release->public_version,
            'build_number' => (int) $release->build_number,
            'download_url' => $this->resolveEffectiveDownloadUrl($release),
            'download_kind' => $this->resolveDownloadKind($release),
            'asset_original_name' => $release->asset_original_name,
            'asset_mime_type' => $release->asset_mime_type,
            'checksum_sha256' => $release->checksum_sha256,
            'file_size_bytes' => $release->file_size_bytes !== null ? (int) $release->file_size_bytes : null,
            'release_notes' => $release->release_notes,
            'distribution_notes' => $release->distribution_notes,
            'update_mode' => $release->update_mode,
            'minimum_supported_version' => $release->minimum_supported_version,
            'minimum_supported_build_number' => $release->minimum_supported_build_number !== null
                ? (int) $release->minimum_supported_build_number
                : null,
            'published_at' => optional($release->published_at)?->toISOString(),
        ];
    }

    public function canAccessCatalogRelease(User $user, MobileRelease $release): bool
    {
        if ($this->canManageReleases($user)) {
            return true;
        }

        $targetAudience = strtolower(trim((string) ($release->target_audience ?: self::AUDIENCE_ALL)));
        if ($targetAudience === self::AUDIENCE_ALL) {
            return true;
        }

        return $targetAudience === $this->resolveAudienceForUser($user);
    }

    public function canManageReleases(User $user): bool
    {
        return $this->canBypassAudience($user);
    }

    private function resolveEffectiveDownloadUrl(MobileRelease $release): ?string
    {
        if (is_string($release->asset_path) && trim($release->asset_path) !== '') {
            return route('mobile-releases.download', ['mobileRelease' => $release->id]);
        }

        return $this->normalizeNullableString($release->download_url);
    }

    private function resolveDownloadKind(MobileRelease $release): string
    {
        if (is_string($release->asset_path) && trim($release->asset_path) !== '') {
            return 'managed_asset';
        }

        if (is_string($release->download_url) && trim($release->download_url) !== '') {
            return 'external_url';
        }

        return 'unavailable';
    }

    private function deactivateSiblingReleases(MobileRelease $release): void
    {
        if (!$release->is_active) {
            return;
        }

        MobileRelease::query()
            ->where('id', '!=', $release->id)
            ->where('app_key', $release->app_key)
            ->where('platform', $release->platform)
            ->where('release_channel', $release->release_channel)
            ->update(['is_active' => false]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $normalized = $payload;

        $appKey = strtolower(trim((string) ($payload['app_key'] ?? self::APP_KEY_DEFAULT)));
        $normalized['app_key'] = $appKey !== '' ? Str::slug($appKey, '-') : self::APP_KEY_DEFAULT;
        $normalized['app_name'] = $this->normalizeNullableString($payload['app_name'] ?? null) ?: self::APP_NAME_DEFAULT;
        $normalized['app_description'] = $this->normalizeNullableString($payload['app_description'] ?? null);
        $normalized['target_audience'] = strtolower(trim((string) ($payload['target_audience'] ?? self::AUDIENCE_ALL)));
        $normalized['bundle_identifier'] = $this->normalizeNullableString($payload['bundle_identifier'] ?? null);
        $normalized['platform'] = strtolower(trim((string) $payload['platform']));
        $normalized['release_channel'] = strtolower(trim((string) ($payload['release_channel'] ?? 'stable')));
        $normalized['public_version'] = trim((string) $payload['public_version']);
        $normalized['download_url'] = $this->normalizeNullableString($payload['download_url'] ?? null);
        if (array_key_exists('asset_path', $payload)) {
            $normalized['asset_path'] = $this->normalizeNullableString($payload['asset_path']);
        }
        if (array_key_exists('asset_disk', $payload)) {
            $normalized['asset_disk'] = $this->normalizeNullableString($payload['asset_disk']);
        }
        if (array_key_exists('asset_original_name', $payload)) {
            $normalized['asset_original_name'] = $this->normalizeNullableString($payload['asset_original_name']);
        }
        if (array_key_exists('asset_mime_type', $payload)) {
            $normalized['asset_mime_type'] = $this->normalizeNullableString($payload['asset_mime_type']);
        }
        if (array_key_exists('checksum_sha256', $payload)) {
            $normalized['checksum_sha256'] = $this->normalizeNullableString($payload['checksum_sha256']);
        }
        $normalized['release_notes'] = $this->normalizeNullableString($payload['release_notes'] ?? null);
        $normalized['distribution_notes'] = $this->normalizeNullableString($payload['distribution_notes'] ?? null);
        $normalized['minimum_supported_version'] = $this->normalizeNullableString($payload['minimum_supported_version'] ?? null);
        if (array_key_exists('file_size_bytes', $payload)) {
            $normalized['file_size_bytes'] = is_numeric($payload['file_size_bytes'] ?? null)
                ? (int) $payload['file_size_bytes']
                : null;
        }
        $normalized['minimum_supported_build_number'] = is_numeric($payload['minimum_supported_build_number'] ?? null)
            ? (int) $payload['minimum_supported_build_number']
            : null;
        $normalized['build_number'] = (int) $payload['build_number'];
        $normalized['is_active'] = (bool) ($payload['is_active'] ?? false);
        $normalized['is_published'] = (bool) ($payload['is_published'] ?? false);
        $normalized['update_mode'] = strtolower(trim((string) ($payload['update_mode'] ?? 'optional')));

        return $normalized;
    }

    private function syncPublishedState(MobileRelease $release, mixed $originalPublishedValue): void
    {
        if ($release->is_published) {
            if (!$release->published_at || !$originalPublishedValue) {
                $release->published_at = now();
            }

            return;
        }

        $release->published_at = null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function storeAssetFile(MobileRelease $release, UploadedFile $assetFile): void
    {
        $appKey = strtolower(trim((string) ($release->app_key ?: self::APP_KEY_DEFAULT)));
        $platform = strtolower(trim((string) ($release->platform ?: 'unknown')));
        $channel = strtolower(trim((string) ($release->release_channel ?: 'stable')));
        $extension = strtolower((string) ($assetFile->getClientOriginalExtension() ?: $assetFile->extension() ?: 'bin'));
        $baseName = pathinfo((string) $assetFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeBaseName = Str::slug($baseName !== '' ? $baseName : 'mobile-release');
        $filename = sprintf(
            '%s-%s-%s.%s',
            now()->format('YmdHis'),
            $safeBaseName !== '' ? $safeBaseName : 'mobile-release',
            Str::lower(Str::random(8)),
            $extension
        );
        $path = $assetFile->storeAs("app-downloads/{$appKey}/{$platform}/{$channel}", $filename, self::STORAGE_DISK_PRIVATE);

        $release->asset_path = $path;
        $release->asset_disk = self::STORAGE_DISK_PRIVATE;
        $release->asset_original_name = $assetFile->getClientOriginalName();
        $release->asset_mime_type = $assetFile->getClientMimeType() ?: $assetFile->getMimeType();
        $release->download_url = null;
        $release->file_size_bytes = $assetFile->getSize();
        $release->checksum_sha256 = hash_file('sha256', $assetFile->getRealPath());
    }

    private function deleteStoredAssetIfManaged(MobileRelease $release): void
    {
        $assetPath = $release->asset_path;
        if (!is_string($assetPath) || trim($assetPath) === '') {
            return;
        }

        Storage::disk($this->resolveStorageDisk($release))->delete($assetPath);
    }

    private function clearManagedAssetMetadata(MobileRelease $release): void
    {
        $release->asset_path = null;
        $release->asset_disk = null;
        $release->asset_original_name = null;
        $release->asset_mime_type = null;
        $release->checksum_sha256 = null;
        $release->file_size_bytes = null;
    }

    private function resolveStorageDisk(MobileRelease $release): string
    {
        $assetDisk = strtolower(trim((string) ($release->asset_disk ?: '')));

        return $assetDisk !== '' ? $assetDisk : self::STORAGE_DISK_LEGACY_PUBLIC;
    }

    public function resolveBundleIdentifier(MobileRelease $release): ?string
    {
        $explicit = $this->normalizeNullableString($release->bundle_identifier);
        if ($explicit !== null) {
            return $explicit;
        }

        return match (strtolower(trim((string) $release->app_key))) {
            'siaps' => 'id.sch.sman1sumbercirebon.siaps',
            'sbt-smanis' => 'id.sch.sman1sumbercirebon.sbt',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function releaseRelations(): array
    {
        return [
            'creator:id,nama_lengkap',
            'updater:id,nama_lengkap',
            'updatePolicies:id,mobile_release_id,audience,update_mode,minimum_supported_version,minimum_supported_build_number,created_by,updated_by',
        ];
    }

    /**
     * @return array<string, array<string, mixed>|null>
     */
    private function serializeUpdatePolicies(MobileRelease $release): array
    {
        $policyMap = $this->loadPolicyMap($release);

        return [
            self::AUDIENCE_ALL => [
                'audience' => self::AUDIENCE_ALL,
                'update_mode' => $release->update_mode,
                'minimum_supported_version' => $release->minimum_supported_version,
                'minimum_supported_build_number' => $release->minimum_supported_build_number !== null
                    ? (int) $release->minimum_supported_build_number
                    : null,
                'source' => 'release_default',
            ],
            self::AUDIENCE_SISWA => $policyMap->has(self::AUDIENCE_SISWA)
                ? $this->serializeStoredPolicy($policyMap->get(self::AUDIENCE_SISWA))
                : null,
            self::AUDIENCE_STAFF => $policyMap->has(self::AUDIENCE_STAFF)
                ? $this->serializeStoredPolicy($policyMap->get(self::AUDIENCE_STAFF))
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeStoredPolicy(MobileUpdatePolicy $policy): array
    {
        return [
            'audience' => $policy->audience,
            'update_mode' => $policy->update_mode,
            'minimum_supported_version' => $policy->minimum_supported_version,
            'minimum_supported_build_number' => $policy->minimum_supported_build_number !== null
                ? (int) $policy->minimum_supported_build_number
                : null,
            'source' => 'audience_override',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveEffectivePolicy(MobileRelease $release, string $audience): array
    {
        $policyMap = $this->loadPolicyMap($release);
        $normalizedAudience = strtolower(trim($audience));

        if ($policyMap->has($normalizedAudience)) {
            /** @var MobileUpdatePolicy $policy */
            $policy = $policyMap->get($normalizedAudience);

            return $this->serializeStoredPolicy($policy);
        }

        return [
            'audience' => self::AUDIENCE_ALL,
            'update_mode' => $release->update_mode,
            'minimum_supported_version' => $release->minimum_supported_version,
            'minimum_supported_build_number' => $release->minimum_supported_build_number !== null
                ? (int) $release->minimum_supported_build_number
                : null,
            'source' => 'release_default',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPolicyAwarePayload(
        MobileRelease $latest,
        ?string $currentVersion,
        int|string|null $currentBuildNumber,
        array $effectivePolicy,
        string $requestedAudience
    ): array {
        $currentVersion = $this->normalizeNullableString($currentVersion);
        $currentBuildNumber = is_numeric($currentBuildNumber) ? (int) $currentBuildNumber : null;
        $latestBuildNumber = (int) $latest->build_number;
        $minimumSupportedBuild = $effectivePolicy['minimum_supported_build_number'] ?? null;
        $minimumSupportedVersion = $this->normalizeNullableString($effectivePolicy['minimum_supported_version'] ?? null);

        $hasUpdate = false;
        if ($currentBuildNumber !== null) {
            $hasUpdate = $latestBuildNumber > $currentBuildNumber;
        } elseif ($currentVersion !== null) {
            $hasUpdate = version_compare($latest->public_version, $currentVersion, '>');
        }

        $isSupported = true;
        if ($currentBuildNumber !== null && $minimumSupportedBuild !== null) {
            $isSupported = $currentBuildNumber >= (int) $minimumSupportedBuild;
        } elseif ($currentVersion !== null && $minimumSupportedVersion !== null) {
            $isSupported = version_compare($currentVersion, $minimumSupportedVersion, '>=');
        }

        $mustUpdate = !$isSupported || ($hasUpdate && ($effectivePolicy['update_mode'] ?? 'optional') === 'required');
        $effectiveUpdateMode = $hasUpdate ? ($mustUpdate ? 'required' : 'optional') : 'none';

        return [
            'app_key' => $latest->app_key,
            'app_name' => $latest->app_name,
            'platform' => $latest->platform,
            'release_channel' => $latest->release_channel,
            'requested_audience' => $requestedAudience,
            'policy_audience' => $effectivePolicy['audience'] ?? self::AUDIENCE_ALL,
            'policy_source' => $effectivePolicy['source'] ?? 'release_default',
            'effective_policy' => $effectivePolicy,
            'current_version' => $currentVersion,
            'current_build_number' => $currentBuildNumber,
            'has_update' => $hasUpdate,
            'is_supported' => $isSupported,
            'must_update' => $mustUpdate,
            'update_mode' => $effectiveUpdateMode,
            'latest' => $this->serializeForPublic($latest),
        ];
    }

    private function syncUpdatePolicies(MobileRelease $release, mixed $policies, ?int $actorUserId): void
    {
        if ($policies === null) {
            return;
        }

        $normalizedPolicies = $this->normalizePolicies($policies);
        $audiences = array_keys($normalizedPolicies);

        MobileUpdatePolicy::query()
            ->where('mobile_release_id', $release->id)
            ->whereIn('audience', [self::AUDIENCE_SISWA, self::AUDIENCE_STAFF])
            ->when(
                !empty($audiences),
                fn ($query) => $query->whereNotIn('audience', $audiences)
            )
            ->when(
                empty($audiences),
                fn ($query) => $query
            )
            ->delete();

        foreach ($normalizedPolicies as $audience => $payload) {
            $policy = MobileUpdatePolicy::query()->firstOrNew([
                'mobile_release_id' => $release->id,
                'audience' => $audience,
            ]);

            if (!$policy->exists) {
                $policy->created_by = $actorUserId;
            }

            $policy->update_mode = $payload['update_mode'];
            $policy->minimum_supported_version = $payload['minimum_supported_version'];
            $policy->minimum_supported_build_number = $payload['minimum_supported_build_number'];
            $policy->updated_by = $actorUserId;
            $policy->save();
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function normalizePolicies(mixed $policies): array
    {
        if (!is_array($policies)) {
            return [];
        }

        $normalized = [];

        foreach ($policies as $policy) {
            if (!is_array($policy)) {
                continue;
            }

            $audience = strtolower(trim((string) ($policy['audience'] ?? '')));
            if (!in_array($audience, [self::AUDIENCE_SISWA, self::AUDIENCE_STAFF], true)) {
                continue;
            }

            $normalized[$audience] = [
                'update_mode' => strtolower(trim((string) ($policy['update_mode'] ?? 'optional'))),
                'minimum_supported_version' => $this->normalizeNullableString($policy['minimum_supported_version'] ?? null),
                'minimum_supported_build_number' => is_numeric($policy['minimum_supported_build_number'] ?? null)
                    ? (int) $policy['minimum_supported_build_number']
                    : null,
            ];
        }

        return $normalized;
    }

    private function resolveAudienceForUser(User $user): string
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::SISWA))
            ? self::AUDIENCE_SISWA
            : self::AUDIENCE_STAFF;
    }

    private function canBypassAudience(User $user): bool
    {
        try {
            return $user->hasPermissionTo('manage_settings');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return \Illuminate\Support\Collection<string, MobileUpdatePolicy>
     */
    private function loadPolicyMap(MobileRelease $release): Collection
    {
        $policies = $release->relationLoaded('updatePolicies')
            ? $release->updatePolicies
            : $release->updatePolicies()->get();

        return $policies->keyBy(fn (MobileUpdatePolicy $policy) => strtolower((string) $policy->audience));
    }
}
