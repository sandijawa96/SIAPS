<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\User;
use App\Services\AttendanceTimeService;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardAttendanceRateIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::forever('attendance_runtime_version', (int) Cache::get('attendance_runtime_version', 1) + 1);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_stats_uses_working_days_for_student_attendance_rate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-06 08:00:00', config('app.timezone')));
        $this->seedLegacyAttendanceSettings();

        $student = $this->createUserWithRole(RoleNames::SISWA);

        Absensi::query()->create([
            'user_id' => $student->id,
            'tanggal' => '2026-03-02',
            'status' => 'hadir',
            'jam_masuk' => '07:00:00',
            'jam_pulang' => '14:00:00',
            'metode_absensi' => 'selfie',
        ]);

        Absensi::query()->create([
            'user_id' => $student->id,
            'tanggal' => '2026-03-03',
            'status' => 'terlambat',
            'jam_masuk' => '07:10:00',
            'jam_pulang' => '14:00:00',
            'metode_absensi' => 'selfie',
        ]);

        Absensi::query()->create([
            'user_id' => $student->id,
            'tanggal' => '2026-03-04',
            'status' => 'hadir',
            'jam_masuk' => '07:03:00',
            'jam_pulang' => '14:00:00',
            'metode_absensi' => 'selfie',
        ]);

        Absensi::query()->create([
            'user_id' => $student->id,
            'tanggal' => '2026-03-05',
            'status' => 'alpha',
            'metode_absensi' => 'manual',
            'is_manual' => false,
        ]);

        $response = $this->actingAs($student, 'sanctum')
            ->getJson('/api/dashboard/stats');

        $response->assertOk()
            ->assertJsonPath('data.attendanceCount', 3)
            ->assertJsonPath('data.lateCount', 1)
            ->assertJsonPath('data.attendanceRate', '60%');
    }

    public function test_attendance_time_service_is_late_counts_from_scheduled_start(): void
    {
        $this->seedLegacyAttendanceSettings();
        $student = $this->createUserWithRole(RoleNames::SISWA);

        /** @var AttendanceTimeService $service */
        $service = app(AttendanceTimeService::class);

        $this->assertFalse($service->isLate($student, Carbon::parse('2026-03-06 07:00:00')));
        $this->assertTrue($service->isLate($student, Carbon::parse('2026-03-06 07:10:00')));
        $this->assertTrue($service->isLate($student, Carbon::parse('2026-03-06 07:16:00')));
    }

    private function seedLegacyAttendanceSettings(): void
    {
        DB::table('attendance_settings')->insert([
            'schema_name' => 'Legacy Default',
            'schema_type' => 'global',
            'jam_masuk_default' => '07:00',
            'jam_pulang_default' => '15:00',
            'toleransi_default' => 15,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => false,
            'wajib_foto' => false,
            'hari_kerja' => json_encode(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']),
            'siswa_jam_masuk' => '07:00',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 15,
            'minimal_open_time_siswa' => 70,
            'verification_mode' => 'async_pending',
            'attendance_scope' => 'siswa_only',
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forever('attendance_runtime_version', (int) Cache::get('attendance_runtime_version', 1) + 1);
    }

    private function createUserWithRole(string $canonicalRole): User
    {
        $role = Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->where('guard_name', 'web')
            ->first();

        $this->assertNotNull($role, "Role not found for {$canonicalRole}");

        $user = User::factory()->create();
        $user->assignRole($role->name);

        return $user;
    }
}
