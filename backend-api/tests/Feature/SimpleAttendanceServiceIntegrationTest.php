<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SimpleAttendanceService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SimpleAttendanceServiceIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private SimpleAttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SimpleAttendanceService::class);
    }

    public function test_update_global_settings_updates_only_default_schema(): void
    {
        $actor = User::factory()->create();

        $defaultId = DB::table('attendance_settings')->insertGetId([
            'schema_name' => 'Default',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'hari_kerja' => json_encode(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $nonDefaultId = DB::table('attendance_settings')->insertGetId([
            'schema_name' => 'Non Default',
            'schema_type' => 'custom',
            'is_default' => false,
            'is_active' => true,
            'jam_masuk_default' => '09:00:00',
            'jam_pulang_default' => '17:00:00',
            'hari_kerja' => json_encode(['Senin']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->service->updateGlobalSettings([
            'jam_masuk_default' => '08:30:00',
            'hari_kerja' => ['Senin', 'Selasa'],
        ], $actor->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('attendance_settings', [
            'id' => $defaultId,
            'jam_masuk_default' => '08:30:00',
        ]);
        $this->assertDatabaseHas('attendance_settings', [
            'id' => $nonDefaultId,
            'jam_masuk_default' => '09:00:00',
        ]);

        $settings = $this->service->getGlobalSettings();
        $this->assertStringStartsWith('08:30', (string) $settings['jam_masuk_default']);
        $this->assertSame(['Senin', 'Selasa'], $settings['hari_kerja']);
    }

    public function test_should_attend_on_date_respects_active_holiday_event(): void
    {
        $this->ensureEventAkademikTableExists();

        $user = User::factory()->create();
        DB::table('attendance_settings')->insert([
            'schema_name' => 'Default',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'hari_kerja' => json_encode(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $monday = Carbon::parse('2026-02-09');
        $tahunAjaranId = DB::table('tahun_ajaran')->insertGetId([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'semester' => 'full',
            'is_active' => true,
            'status' => 'active',
            'preparation_progress' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->service->shouldAttendOnDate($user, $monday));

        DB::table('event_akademik')->insert([
            'tahun_ajaran_id' => $tahunAjaranId,
            'nama' => 'Libur Nasional',
            'jenis' => 'libur',
            'tanggal_mulai' => $monday->toDateString(),
            'tanggal_selesai' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse($this->service->shouldAttendOnDate($user, $monday));
    }

    private function ensureEventAkademikTableExists(): void
    {
        if (Schema::hasTable('event_akademik')) {
            return;
        }

        Schema::create('event_akademik', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tahun_ajaran_id')->nullable();
            $table->string('nama')->nullable();
            $table->string('jenis');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
}
