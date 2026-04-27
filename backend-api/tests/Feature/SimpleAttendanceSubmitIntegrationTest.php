<?php

namespace Tests\Feature;

use App\Jobs\DispatchAttendanceWhatsappNotification;
use App\Models\LokasiGps;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SimpleAttendanceSubmitIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_submit_attendance_records_warning_for_invalid_gps_location_without_blocking_submit(): void
    {
        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => 0.000000,
                'longitude' => 0.000000,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.validation_status', 'warning')
            ->assertJsonPath('data.has_warning', true);

        $this->assertDatabaseCount('absensi', 1);
        $this->assertDatabaseHas('attendance_security_events', [
            'user_id' => $user->id,
            'status' => 'flagged',
        ]);
    }

    public function test_submit_attendance_from_web_client_is_recorded_as_warning_without_blocking_submit(): void
    {
        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->withHeaders([
                'X-Client-Platform' => 'web',
                'X-Client-App' => 'dashboard-web',
            ])
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.validation_status', 'warning')
            ->assertJsonPath('data.has_warning', true);

        $this->assertDatabaseCount('absensi', 1);
        $this->assertDatabaseHas('attendance_security_events', [
            'user_id' => $user->id,
            'event_key' => 'mobile_app_only_violation',
            'status' => 'flagged',
        ]);
    }

    public function test_submit_attendance_rejects_invalid_photo_payload(): void
    {
        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => 'data:image/jpeg;base64,@@@invalid@@@',
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'INVALID_PHOTO_FORMAT');
    }

    public function test_submit_attendance_rejects_duplicate_check_in(): void
    {
        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults();

        $payload = [
            'jenis_absensi' => 'masuk',
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'accuracy' => 10,
            'foto' => $this->validBase64Image(),
        ];

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', $payload)
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', $payload)
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'DUPLICATE_ATTENDANCE');
    }

    public function test_submit_attendance_requires_face_template_when_policy_enabled(): void
    {
        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults([
            'face_template_required' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'FACE_TEMPLATE_REQUIRED');
    }

    public function test_validate_attendance_time_returns_invalid_when_face_template_is_required_and_missing(): void
    {
        $user = $this->createSiswaUser();
        $this->seedAttendanceDefaults([
            'face_template_required' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/validate-time', [
                'jenis_absensi' => 'masuk',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.code', 'FACE_TEMPLATE_REQUIRED');
    }

    public function test_submit_attendance_rejects_not_working_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-22 08:00:00')); // Minggu

        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults([
            'hari_kerja' => ['Senin'],
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'NOT_WORKING_DAY');
    }

    public function test_submit_attendance_rejects_checkout_without_checkin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-23 15:30:00')); // Senin

        $user = $this->createSiswaUser();
        $this->seedAttendanceDefaults([
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'pulang',
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'NO_CHECK_IN');
    }

    public function test_submit_attendance_rejects_invalid_payload_with_validation_error(): void
    {
        $user = $this->createSiswaUser();
        $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'invalid_type',
                'latitude' => 999,
                'longitude' => 999,
            ])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'errors' => [
                    'jenis_absensi',
                    'latitude',
                    'longitude',
                ],
            ]);
    }

    public function test_submit_attendance_rejects_duplicate_checkout_after_user_has_checked_out(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-24 16:00:00')); // Selasa

        $user = $this->createSiswaUser();
        $this->seedAttendanceDefaults([
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
        ]);

        DB::table('absensi')->insert([
            'user_id' => $user->id,
            'tanggal' => now()->format('Y-m-d'),
            'jam_masuk' => '07:05:00',
            'jam_pulang' => '15:45:00',
            'status' => 'hadir',
            'metode_absensi' => 'selfie',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'pulang',
            ])
            ->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('code', 'DUPLICATE_ATTENDANCE');
    }

    public function test_submit_attendance_saves_checkin_photo_and_returns_success(): void
    {
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-02-25 08:00:00')); // Rabu

        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $attendance = DB::table('absensi')
            ->where('user_id', $user->id)
            ->whereDate('tanggal', now()->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->jam_masuk);
        $this->assertNotNull($attendance->foto_masuk);
        $this->assertStringStartsWith('absensi/absensi_' . $user->id . '_checkin_', $attendance->foto_masuk);
        Storage::disk('public')->assertExists($attendance->foto_masuk);
    }

    public function test_submit_attendance_queues_checkin_whatsapp_notification(): void
    {
        Queue::fake();
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-02-25 08:00:00')); // Rabu

        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        Queue::assertPushed(DispatchAttendanceWhatsappNotification::class, function (DispatchAttendanceWhatsappNotification $job) use ($user) {
            $attendance = DB::table('absensi')
                ->where('user_id', $user->id)
                ->whereDate('tanggal', now()->toDateString())
                ->first();

            return $attendance !== null
                && $job->attendanceId === (int) $attendance->id
                && $job->event === 'checkin'
                && $job->queue === DispatchAttendanceWhatsappNotification::QUEUE_NAME;
        });
    }

    public function test_submit_attendance_saves_checkout_photo_and_updates_existing_record(): void
    {
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-02-26 08:00:00')); // Kamis

        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        Carbon::setTestNow(Carbon::parse('2026-02-26 16:00:00'));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'pulang',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $attendance = DB::table('absensi')
            ->where('user_id', $user->id)
            ->whereDate('tanggal', now()->toDateString())
            ->first();

        $this->assertNotNull($attendance);
        $this->assertNotNull($attendance->jam_masuk);
        $this->assertNotNull($attendance->jam_pulang);
        $this->assertNotNull($attendance->foto_pulang);
        $this->assertStringStartsWith('absensi/absensi_' . $user->id . '_checkout_', $attendance->foto_pulang);
        Storage::disk('public')->assertExists($attendance->foto_pulang);
    }

    public function test_submit_attendance_queues_checkout_whatsapp_notification(): void
    {
        Queue::fake();
        Storage::fake('public');
        Carbon::setTestNow(Carbon::parse('2026-02-26 08:00:00')); // Kamis

        $user = $this->createSiswaUser();
        $location = $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'masuk',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        Carbon::setTestNow(Carbon::parse('2026-02-26 16:00:00'));

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/submit', [
                'jenis_absensi' => 'pulang',
                'latitude' => (float) $location->latitude,
                'longitude' => (float) $location->longitude,
                'accuracy' => 10,
                'foto' => $this->validBase64Image(),
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        Queue::assertPushed(DispatchAttendanceWhatsappNotification::class, function (DispatchAttendanceWhatsappNotification $job) use ($user) {
            $attendance = DB::table('absensi')
                ->where('user_id', $user->id)
                ->whereDate('tanggal', now()->toDateString())
                ->first();

            return $attendance !== null
                && $job->attendanceId === (int) $attendance->id
                && $job->event === 'checkout'
                && $job->queue === DispatchAttendanceWhatsappNotification::QUEUE_NAME;
        });
    }

    public function test_validate_attendance_time_accepts_jenis_absensi_alias(): void
    {
        $user = $this->createSiswaUser();
        $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/validate-time', [
                'jenis_absensi' => 'masuk',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('meta.resolved_type', 'masuk');
    }

    public function test_validate_attendance_time_requires_type_or_jenis_absensi(): void
    {
        $user = $this->createSiswaUser();
        $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/validate-time', [])
            ->assertStatus(422)
            ->assertJsonPath('status', 'error')
            ->assertJsonStructure([
                'errors' => ['type'],
            ]);
    }

    public function test_validate_attendance_time_uses_schema_minimal_open_time_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-02 05:55:00')); // Senin

        $user = $this->createSiswaUser();
        $this->seedAttendanceDefaults([
            'jam_masuk_default' => '07:00:00',
            'jam_pulang_default' => '15:00:00',
            'siswa_jam_masuk' => '07:00:00',
            'siswa_jam_pulang' => '15:00:00',
            'toleransi_default' => 10,
            'siswa_toleransi' => 10,
            'minimal_open_time_staff' => 70,
            'minimal_open_time_siswa' => 70,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/validate-time', [
                'jenis_absensi' => 'masuk',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.window.earliest', '05:50')
            ->assertJsonPath('data.window.latest', '07:10');
    }

    public function test_validate_attendance_time_rejects_non_student_user_cleanly(): void
    {
        $user = $this->createNonSiswaUser();
        $this->seedAttendanceDefaults();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/simple-attendance/validate-time', [
                'jenis_absensi' => 'masuk',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.valid', false)
            ->assertJsonPath('data.code', 'ATTENDANCE_FORBIDDEN');
    }

    private function seedAttendanceDefaults(array $overrides = []): LokasiGps
    {
        $location = LokasiGps::create([
            'nama_lokasi' => 'Sekolah',
            'deskripsi' => 'Lokasi utama sekolah',
            'latitude' => -6.750000,
            'longitude' => 108.550000,
            'radius' => 100,
            'is_active' => true,
        ]);

        $payload = array_merge([
            'schema_name' => 'Default Schema',
            'schema_type' => 'global',
            'is_active' => true,
            'is_default' => true,
            'is_mandatory' => true,
            'priority' => 0,
            'version' => 1,
            'jam_masuk_default' => now()->format('H:i:s'),
            'jam_pulang_default' => now()->addHour()->format('H:i:s'),
            'toleransi_default' => 180,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'face_template_required' => false,
            'hari_kerja' => json_encode([
                'Senin',
                'Selasa',
                'Rabu',
                'Kamis',
                'Jumat',
                'Sabtu',
                'Minggu',
            ]),
            'lokasi_gps_ids' => json_encode([$location->id]),
            'radius_absensi' => 100,
            'gps_accuracy' => 20,
            'siswa_jam_masuk' => now()->format('H:i:s'),
            'siswa_jam_pulang' => now()->addHour()->format('H:i:s'),
            'siswa_toleransi' => 180,
            'minimal_open_time_siswa' => 70,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);

        if (isset($payload['hari_kerja']) && is_array($payload['hari_kerja'])) {
            $payload['hari_kerja'] = json_encode($payload['hari_kerja']);
        }
        if (isset($payload['lokasi_gps_ids']) && is_array($payload['lokasi_gps_ids'])) {
            $payload['lokasi_gps_ids'] = json_encode($payload['lokasi_gps_ids']);
        }

        DB::table('attendance_settings')->insert($payload);

        Cache::forever('attendance_runtime_version', (int) Cache::get('attendance_runtime_version', 1) + 1);

        return $location;
    }

    private function validBase64Image(): string
    {
        return 'data:image/jpeg;base64,' . base64_encode('test-image-content');
    }

    private function createSiswaUser(): User
    {
        $user = User::factory()->create();
        $this->assignRole($user, 'Siswa');

        return $user;
    }

    private function createNonSiswaUser(): User
    {
        $user = User::factory()->create();
        $this->assignRole($user, 'Guru');

        return $user;
    }

    private function assignRole(User $user, string $roleName): void
    {
        $role = Role::firstOrCreate(
            ['name' => $roleName],
            [
                'display_name' => $roleName,
                'description' => $roleName . ' role for attendance test',
                'level' => 0,
                'is_active' => true,
                'guard_name' => 'web',
            ]
        );

        $user->assignRole($role);
    }
}
