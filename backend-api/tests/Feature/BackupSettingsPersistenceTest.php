<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupSettingsPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private string $settingsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);
        $this->settingsPath = storage_path('app/backup_settings.json');

        if (File::exists($this->settingsPath)) {
            File::delete($this->settingsPath);
        }
    }

    protected function tearDown(): void
    {
        if (File::exists($this->settingsPath)) {
            File::delete($this->settingsPath);
        }

        parent::tearDown();
    }

    public function test_backup_settings_are_persisted_and_loaded_from_json_file(): void
    {
        $user = User::factory()->create();

        $payload = [
            'auto_backup_enabled' => true,
            'backup_frequency' => 'weekly',
            'retention_days' => 14,
            'backup_types' => ['database', 'files'],
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/backups/settings', $payload)
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => $payload,
            ]);

        $this->assertFileExists($this->settingsPath);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/backups/settings')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'auto_backup_enabled' => true,
                    'backup_frequency' => 'weekly',
                    'retention_days' => 14,
                    'backup_types' => ['database', 'files'],
                ],
            ]);
    }
}
