<?php

namespace Tests\Feature;

use App\Models\MobileRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MobileReleaseEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(
            [
                'name' => 'manage_settings',
                'guard_name' => 'web',
            ],
            [
                'display_name' => 'Manage Settings',
                'description' => 'Manage system settings',
                'module' => 'settings',
            ]
        );
    }

    public function test_authenticated_catalog_returns_only_releases_available_to_current_user_audience(): void
    {
        $student = User::factory()->create();
        $student->assignRole($this->studentRole());

        $staff = User::factory()->create();
        $staff->assignRole($this->staffRole());

        MobileRelease::query()->create([
            'app_key' => 'siaps',
            'app_name' => 'SIAPS Mobile',
            'target_audience' => 'all',
            'platform' => 'android',
            'release_channel' => 'stable',
            'public_version' => '1.2.0',
            'build_number' => 120,
            'download_url' => 'https://example.test/siaps.apk',
            'update_mode' => 'optional',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        MobileRelease::query()->create([
            'app_key' => 'ujian',
            'app_name' => 'Aplikasi Ujian',
            'target_audience' => 'siswa',
            'platform' => 'android',
            'release_channel' => 'stable',
            'public_version' => '2.0.0',
            'build_number' => 200,
            'download_url' => 'https://example.test/ujian.apk',
            'update_mode' => 'optional',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        MobileRelease::query()->create([
            'app_key' => 'guru-tools',
            'app_name' => 'Guru Tools',
            'target_audience' => 'staff',
            'platform' => 'android',
            'release_channel' => 'stable',
            'public_version' => '1.0.0',
            'build_number' => 100,
            'download_url' => 'https://example.test/guru-tools.apk',
            'update_mode' => 'optional',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/mobile-releases/catalog')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['app_key' => 'siaps'])
            ->assertJsonFragment(['app_key' => 'ujian'])
            ->assertJsonMissing(['app_key' => 'guru-tools']);

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/mobile-releases/catalog')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['app_key' => 'siaps'])
            ->assertJsonFragment(['app_key' => 'guru-tools'])
            ->assertJsonMissing(['app_key' => 'ujian']);
    }

    public function test_authenticated_catalog_only_returns_latest_release_per_app_and_platform(): void
    {
        $student = User::factory()->create();
        $student->assignRole($this->studentRole());

        MobileRelease::query()->create([
            'app_key' => 'siaps',
            'app_name' => 'SIAPS Mobile',
            'target_audience' => 'all',
            'platform' => 'android',
            'release_channel' => 'stable',
            'public_version' => '1.0.0',
            'build_number' => 100,
            'download_url' => 'https://example.test/siaps-100.apk',
            'update_mode' => 'optional',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now()->subDay(),
        ]);

        MobileRelease::query()->create([
            'app_key' => 'siaps',
            'app_name' => 'SIAPS Mobile',
            'target_audience' => 'all',
            'platform' => 'android',
            'release_channel' => 'stable',
            'public_version' => '1.1.0',
            'build_number' => 110,
            'download_url' => 'https://example.test/siaps-110.apk',
            'update_mode' => 'optional',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        MobileRelease::query()->create([
            'app_key' => 'siaps',
            'app_name' => 'SIAPS Mobile',
            'target_audience' => 'all',
            'platform' => 'ios',
            'release_channel' => 'stable',
            'public_version' => '1.1.0',
            'build_number' => 110,
            'download_url' => 'https://example.test/siaps-ios-110.ipa',
            'update_mode' => 'optional',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/mobile-releases/catalog')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data');

        $items = collect($response->json('data'));

        $androidRelease = $items->firstWhere('platform', 'android');
        $iosRelease = $items->firstWhere('platform', 'ios');

        $this->assertNotNull($androidRelease);
        $this->assertNotNull($iosRelease);
        $this->assertSame('siaps', $androidRelease['app_key']);
        $this->assertSame('1.1.0', $androidRelease['public_version']);
        $this->assertSame(110, $androidRelease['build_number']);
        $this->assertSame('siaps', $iosRelease['app_key']);
        $this->assertSame('1.1.0', $iosRelease['public_version']);
        $this->assertSame(110, $iosRelease['build_number']);
        $this->assertFalse($items->contains(fn (array $item) => ($item['platform'] ?? null) === 'android' && ($item['build_number'] ?? null) === 100));
    }

    public function test_authenticated_version_check_returns_optional_for_supported_client_and_required_for_unsupported_client(): void
    {
        $user = User::factory()->create();

        MobileRelease::query()->create([
            'app_key' => 'siaps',
            'app_name' => 'SIAPS Mobile',
            'target_audience' => 'all',
            'platform' => 'android',
            'release_channel' => 'stable',
            'public_version' => '1.2.0',
            'build_number' => 120,
            'download_url' => 'https://example.test/android.apk',
            'update_mode' => 'optional',
            'minimum_supported_version' => '1.0.0',
            'minimum_supported_build_number' => 100,
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/mobile-releases/check-authenticated?platform=android&app_version=1.1.0&build_number=110')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.app_key', 'siaps')
            ->assertJsonPath('data.has_update', true)
            ->assertJsonPath('data.is_supported', true)
            ->assertJsonPath('data.must_update', false)
            ->assertJsonPath('data.update_mode', 'optional')
            ->assertJsonPath('data.latest.public_version', '1.2.0');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/mobile-releases/check-authenticated?platform=android&app_version=0.9.0&build_number=90')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.has_update', true)
            ->assertJsonPath('data.is_supported', false)
            ->assertJsonPath('data.must_update', true)
            ->assertJsonPath('data.update_mode', 'required');
    }

    public function test_authenticated_version_check_for_managed_asset_returns_authenticated_download_url(): void
    {
        $user = User::factory()->create();

        Storage::disk('local')->put('app-downloads/siaps/android/stable/siaps.apk', 'dummy apk');

        MobileRelease::query()->create([
            'app_key' => 'siaps',
            'app_name' => 'SIAPS Mobile',
            'target_audience' => 'all',
            'platform' => 'android',
            'release_channel' => 'stable',
            'public_version' => '1.3.0',
            'build_number' => 130,
            'asset_path' => 'app-downloads/siaps/android/stable/siaps.apk',
            'asset_disk' => 'local',
            'asset_original_name' => 'siaps.apk',
            'asset_mime_type' => 'application/vnd.android.package-archive',
            'checksum_sha256' => hash('sha256', 'dummy apk'),
            'file_size_bytes' => 9,
            'update_mode' => 'required',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $downloadUrl = $this->actingAs($user, 'sanctum')
            ->getJson('/api/mobile-releases/check-authenticated?platform=android&app_version=1.2.0&build_number=120')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json('data.latest.download_url');

        $this->assertIsString($downloadUrl);
        $this->assertStringContainsString('/api/mobile-releases/1/download', $downloadUrl);
        $this->assertStringNotContainsString('signature=', $downloadUrl);
        $this->assertStringNotContainsString('expires=', $downloadUrl);
    }

    public function test_admin_can_create_release_and_new_active_release_deactivates_previous_one_for_same_app_platform_channel(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_settings');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/mobile-releases', [
                'app_key' => 'siaps',
                'app_name' => 'SIAPS Mobile',
                'target_audience' => 'all',
                'platform' => 'android',
                'release_channel' => 'stable',
                'public_version' => '1.0.0',
                'build_number' => 100,
                'download_url' => 'https://example.test/android-100.apk',
                'update_mode' => 'optional',
                'is_published' => true,
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.app_key', 'siaps');

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/mobile-releases', [
                'app_key' => 'siaps',
                'app_name' => 'SIAPS Mobile',
                'target_audience' => 'all',
                'platform' => 'android',
                'release_channel' => 'stable',
                'public_version' => '1.1.0',
                'build_number' => 110,
                'download_url' => 'https://example.test/android-110.apk',
                'update_mode' => 'required',
                'minimum_supported_version' => '1.0.0',
                'minimum_supported_build_number' => 100,
                'is_published' => true,
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.public_version', '1.1.0');

        $this->assertDatabaseHas('mobile_releases', [
            'app_key' => 'siaps',
            'platform' => 'android',
            'build_number' => 100,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('mobile_releases', [
            'app_key' => 'siaps',
            'platform' => 'android',
            'build_number' => 110,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_upload_private_asset_and_authenticated_download_can_access_it(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_settings');

        $file = UploadedFile::fake()->create(
            'siaps-android-release.apk',
            2048,
            'application/vnd.android.package-archive'
        );

        $response = $this->actingAs($admin, 'sanctum')
            ->post('/api/mobile-releases', [
                'app_key' => 'siaps',
                'app_name' => 'SIAPS Mobile',
                'target_audience' => 'all',
                'platform' => 'android',
                'release_channel' => 'stable',
                'public_version' => '1.3.0',
                'build_number' => 130,
                'update_mode' => 'required',
                'is_published' => '1',
                'is_active' => '1',
                'asset_file' => $file,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.asset_original_name', 'siaps-android-release.apk')
            ->assertJsonPath('data.asset_disk', 'local')
            ->assertJsonPath('data.download_kind', 'managed_asset');

        $release = MobileRelease::query()->firstOrFail();

        $this->assertNotNull($release->asset_path);
        $this->assertSame('local', $release->asset_disk);
        Storage::disk('local')->assertExists($release->asset_path);

        $this->actingAs($admin, 'sanctum')
            ->get("/api/mobile-releases/{$release->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_admin_update_without_new_file_keeps_existing_managed_asset(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_settings');

        $createResponse = $this->actingAs($admin, 'sanctum')
            ->post('/api/mobile-releases', [
                'app_key' => 'siaps',
                'app_name' => 'SIAPS Mobile',
                'target_audience' => 'all',
                'platform' => 'android',
                'release_channel' => 'stable',
                'public_version' => '1.3.0',
                'build_number' => 130,
                'update_mode' => 'required',
                'is_published' => '1',
                'is_active' => '1',
                'asset_file' => UploadedFile::fake()->create(
                    'siaps-android-release.apk',
                    2048,
                    'application/vnd.android.package-archive'
                ),
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $releaseId = (int) $createResponse->json('data.id');
        $release = MobileRelease::query()->findOrFail($releaseId);
        $originalAssetPath = (string) $release->asset_path;

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/mobile-releases/{$releaseId}", [
                'app_key' => 'siaps',
                'app_name' => 'SIAPS Mobile',
                'target_audience' => 'all',
                'platform' => 'android',
                'release_channel' => 'stable',
                'public_version' => '1.3.1',
                'build_number' => 131,
                'update_mode' => 'required',
                'is_published' => true,
                'is_active' => true,
                'release_notes' => 'Patch metadata only',
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.download_kind', 'managed_asset');

        $release->refresh();

        $this->assertSame($originalAssetPath, $release->asset_path);
        Storage::disk('local')->assertExists($originalAssetPath);
    }

    public function test_admin_switch_to_external_url_removes_old_managed_asset_file(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_settings');

        $createResponse = $this->actingAs($admin, 'sanctum')
            ->post('/api/mobile-releases', [
                'app_key' => 'siaps',
                'app_name' => 'SIAPS Mobile',
                'target_audience' => 'all',
                'platform' => 'android',
                'release_channel' => 'stable',
                'public_version' => '1.4.0',
                'build_number' => 140,
                'update_mode' => 'required',
                'is_published' => '1',
                'is_active' => '1',
                'asset_file' => UploadedFile::fake()->create(
                    'siaps-android-release.apk',
                    2048,
                    'application/vnd.android.package-archive'
                ),
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true);

        $releaseId = (int) $createResponse->json('data.id');
        $release = MobileRelease::query()->findOrFail($releaseId);
        $originalAssetPath = (string) $release->asset_path;
        Storage::disk('local')->assertExists($originalAssetPath);

        $this->actingAs($admin, 'sanctum')
            ->putJson("/api/mobile-releases/{$releaseId}", [
                'app_key' => 'siaps',
                'app_name' => 'SIAPS Mobile',
                'target_audience' => 'all',
                'platform' => 'android',
                'release_channel' => 'stable',
                'public_version' => '1.4.0',
                'build_number' => 140,
                'download_url' => 'https://example.test/siaps-140.apk',
                'update_mode' => 'required',
                'is_published' => true,
                'is_active' => true,
            ])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.download_kind', 'external_url');

        $release->refresh();

        $this->assertNull($release->asset_path);
        $this->assertSame('https://example.test/siaps-140.apk', $release->download_url);
        Storage::disk('local')->assertMissing($originalAssetPath);
    }

    public function test_ios_download_link_returns_ota_install_manifest_url(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_settings');

        $release = MobileRelease::query()->create([
            'app_key' => 'sbt-smanis',
            'app_name' => 'SBT SMANIS',
            'target_audience' => 'siswa',
            'bundle_identifier' => 'id.sch.sman1sumbercirebon.sbt',
            'platform' => 'ios',
            'release_channel' => 'stable',
            'public_version' => '1.0.0',
            'build_number' => 1,
            'download_url' => 'https://example.test/sbt-smanis.ipa',
            'update_mode' => 'optional',
            'is_active' => true,
            'is_published' => true,
            'published_at' => now(),
        ]);

        $payload = $this->actingAs($admin, 'sanctum')
            ->getJson("/api/mobile-releases/{$release->id}/download-link")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->json('data');

        $this->assertStringStartsWith('itms-services://?action=download-manifest&url=', $payload['download_url']);
        $this->assertStringContainsString('/api/mobile-releases/' . $release->id . '/ios-manifest', $payload['ios_manifest_url']);
        $this->assertSame($payload['download_url'], $payload['ios_install_url']);

        $manifestResponse = $this->get($payload['ios_manifest_url'])
            ->assertStatus(200)
            ->assertHeader('content-type', 'text/xml; charset=UTF-8');

        $manifest = $manifestResponse->getContent();
        $this->assertStringContainsString('<key>bundle-identifier</key>', $manifest);
        $this->assertStringContainsString('<string>id.sch.sman1sumbercirebon.sbt</string>', $manifest);
        $this->assertStringContainsString('<string>https://example.test/sbt-smanis.ipa</string>', $manifest);
    }

    public function test_authenticated_version_check_uses_role_specific_policy_override(): void
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('manage_settings');

        $student = User::factory()->create();
        $student->assignRole($this->studentRole());

        $staff = User::factory()->create();
        $staff->assignRole($this->staffRole());

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/mobile-releases', [
                'app_key' => 'siaps',
                'app_name' => 'SIAPS Mobile',
                'target_audience' => 'all',
                'platform' => 'android',
                'release_channel' => 'stable',
                'public_version' => '1.2.0',
                'build_number' => 120,
                'download_url' => 'https://example.test/android-120.apk',
                'update_mode' => 'optional',
                'minimum_supported_build_number' => 100,
                'policies' => [
                    [
                        'audience' => 'staff',
                        'update_mode' => 'required',
                        'minimum_supported_build_number' => 120,
                    ],
                ],
                'is_published' => true,
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.update_policies.staff.update_mode', 'required');

        $this->actingAs($student, 'sanctum')
            ->getJson('/api/mobile-releases/check-authenticated?platform=android&app_version=1.1.0&build_number=110')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requested_audience', 'siswa')
            ->assertJsonPath('data.policy_audience', 'all')
            ->assertJsonPath('data.must_update', false)
            ->assertJsonPath('data.update_mode', 'optional');

        $this->actingAs($staff, 'sanctum')
            ->getJson('/api/mobile-releases/check-authenticated?platform=android&app_version=1.1.0&build_number=110')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.requested_audience', 'staff')
            ->assertJsonPath('data.policy_audience', 'staff')
            ->assertJsonPath('data.must_update', true)
            ->assertJsonPath('data.update_mode', 'required');
    }

    private function studentRole(): Role
    {
        return Role::firstOrCreate(
            [
                'name' => 'Siswa',
                'guard_name' => 'web',
            ],
            [
                'display_name' => 'Siswa',
                'description' => 'Student role for mobile release policy tests',
                'module' => 'auth',
            ]
        );
    }

    private function staffRole(): Role
    {
        return Role::firstOrCreate(
            [
                'name' => 'Guru',
                'guard_name' => 'web',
            ],
            [
                'display_name' => 'Guru',
                'description' => 'Staff role for mobile release policy tests',
                'module' => 'auth',
            ]
        );
    }
}
