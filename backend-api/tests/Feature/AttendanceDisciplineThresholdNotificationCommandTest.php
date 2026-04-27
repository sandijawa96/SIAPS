<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\AttendanceDisciplineCase;
use App\Models\AttendanceSchema;
use App\Models\DataKepegawaian;
use App\Models\Kelas;
use App\Models\Notification;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceDisciplineThresholdNotificationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Cache::forget('settings.whatsapp.automations');
        Cache::forever('settings.whatsapp.api_url', 'https://wa.test');
        Cache::forever('settings.whatsapp.api_key', 'test-key');
        Cache::forever('settings.whatsapp.device_id', '6281230000000');
        Cache::forever('settings.whatsapp.notification_enabled', true);

        Http::fake([
            'https://wa.test/send-message' => Http::response([
                'status' => true,
                'msg' => 'Message sent successfully!',
            ], 200),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_command_creates_internal_and_whatsapp_alerts_once_per_recipient(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-17 23:58:00'));

        AttendanceSchema::create([
            'schema_name' => 'Default Threshold Schema',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00',
            'jam_pulang_default' => '15:00',
            'toleransi_default' => 0,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '07:00',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 0,
            'minimal_open_time_siswa' => 70,
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 1200,
            'alpha_days_semester_limit' => 3,
            'late_minutes_monthly_limit' => 120,
            'semester_total_violation_mode' => 'monitor_only',
            'semester_alpha_mode' => 'alertable',
            'monthly_late_mode' => 'monitor_only',
            'notify_wali_kelas_on_alpha_limit' => true,
            'notify_kesiswaan_on_alpha_limit' => true,
        ]);

        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $kesiswaan = $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $student = $this->createUserWithRole(RoleNames::SISWA);
        $student->update(['nama_lengkap' => 'Siswa Alpha']);
        $wali->update(['nama_lengkap' => 'Wali Alpha']);
        $kesiswaan->update(['nama_lengkap' => 'Kesiswaan Alpha']);

        DataKepegawaian::create([
            'user_id' => $wali->id,
            'status_kepegawaian' => 'ASN',
            'no_hp' => '081234567890',
        ]);
        DataKepegawaian::create([
            'user_id' => $kesiswaan->id,
            'status_kepegawaian' => 'ASN',
            'no_hp' => '081298765432',
        ]);

        $tingkat = Tingkat::create([
            'nama' => 'Kelas 10',
            'kode' => 'X',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $tahunAjaran = TahunAjaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'is_active' => true,
            'status' => TahunAjaran::STATUS_ACTIVE,
            'semester' => 'full',
        ]);

        $kelas = Kelas::create([
            'nama_kelas' => 'X-A',
            'tingkat_id' => $tingkat->id,
            'wali_kelas_id' => $wali->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'jumlah_siswa' => 1,
            'is_active' => true,
        ]);

        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelas->id,
            'siswa_id' => $student->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => '2025-07-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (['2026-01-05', '2026-02-10', '2026-03-12'] as $date) {
            Absensi::create([
                'user_id' => $student->id,
                'kelas_id' => $kelas->id,
                'tanggal' => $date,
                'status' => 'alpha',
                'metode_absensi' => 'manual',
            ]);
        }

        $this->artisan('attendance:notify-discipline-thresholds', [
            '--month' => '2026-03',
        ])->assertExitCode(0);

        $this->assertDatabaseCount('attendance_discipline_cases', 1);
        $this->assertDatabaseCount('attendance_discipline_alerts', 2);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('whatsapp_notifications', 2);

        $case = AttendanceDisciplineCase::query()->first();
        $this->assertNotNull($case);
        $this->assertSame(AttendanceDisciplineCase::STATUS_READY_FOR_PARENT_BROADCAST, $case->status);
        $this->assertSame(3, $case->metric_value);
        $this->assertSame(3, $case->metric_limit);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $wali->id,
            'title' => 'Batas alpha semester terlampaui',
            'type' => 'warning',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $kesiswaan->id,
            'title' => 'Batas alpha semester terlampaui',
            'type' => 'warning',
        ]);

        $waliNotification = Notification::query()->where('user_id', $wali->id)->latest('id')->first();
        $this->assertNotNull($waliNotification);
        $this->assertSame('system', data_get($waliNotification->data, 'message_category'));
        $this->assertSame('attendance_discipline_threshold', data_get($waliNotification->data, 'source'));
        $this->assertSame('semester_alpha_limit', data_get($waliNotification->data, 'discipline_alert.rule_key'));
        $this->assertSame(3, data_get($waliNotification->data, 'discipline_alert.metric_value'));
        $this->assertSame('hari', data_get($waliNotification->data, 'discipline_alert.metric_unit'));

        $this->assertDatabaseHas('whatsapp_notifications', [
            'phone_number' => '6281234567890',
            'status' => 'sent',
            'type' => 'reminder',
        ]);
        $this->assertDatabaseHas('whatsapp_notifications', [
            'phone_number' => '6281298765432',
            'status' => 'sent',
            'type' => 'reminder',
        ]);

        $this->artisan('attendance:notify-discipline-thresholds', [
            '--month' => '2026-03',
        ])->assertExitCode(0);

        $this->assertDatabaseCount('attendance_discipline_cases', 1);
        $this->assertDatabaseCount('attendance_discipline_alerts', 2);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('whatsapp_notifications', 2);
    }

    public function test_command_can_alert_monthly_late_and_semester_total_violation_rules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-17 23:58:00'));

        AttendanceSchema::create([
            'schema_name' => 'Generic Threshold Schema',
            'schema_type' => 'global',
            'is_default' => true,
            'is_active' => true,
            'is_mandatory' => true,
            'jam_masuk_default' => '07:00',
            'jam_pulang_default' => '15:00',
            'toleransi_default' => 0,
            'minimal_open_time_staff' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
            'siswa_jam_masuk' => '07:00',
            'siswa_jam_pulang' => '14:00',
            'siswa_toleransi' => 0,
            'minimal_open_time_siswa' => 70,
            'discipline_thresholds_enabled' => true,
            'total_violation_minutes_semester_limit' => 30,
            'alpha_days_semester_limit' => 9,
            'late_minutes_monthly_limit' => 15,
            'semester_total_violation_mode' => 'alertable',
            'semester_alpha_mode' => 'monitor_only',
            'monthly_late_mode' => 'alertable',
            'notify_wali_kelas_on_total_violation_limit' => true,
            'notify_kesiswaan_on_total_violation_limit' => true,
            'notify_wali_kelas_on_late_limit' => true,
            'notify_kesiswaan_on_late_limit' => true,
        ]);

        [$wali, $kesiswaan, $student, $kelas] = $this->seedAlertingClassContext();

        Absensi::create([
            'user_id' => $student->id,
            'kelas_id' => $kelas->id,
            'tanggal' => '2026-03-12',
            'jam_masuk' => '07:30:00',
            'jam_pulang' => '14:00:00',
            'status' => 'terlambat',
            'metode_absensi' => 'manual',
        ]);

        $this->artisan('attendance:notify-discipline-thresholds', [
            '--month' => '2026-03',
        ])->assertExitCode(0);

        $this->assertDatabaseCount('attendance_discipline_cases', 2);
        $this->assertDatabaseCount('attendance_discipline_alerts', 4);
        $this->assertDatabaseCount('notifications', 4);
        $this->assertDatabaseCount('whatsapp_notifications', 4);

        $this->assertDatabaseHas('attendance_discipline_cases', [
            'user_id' => $student->id,
            'rule_key' => 'monthly_late_limit',
            'metric_value' => 30,
            'metric_limit' => 15,
            'period_type' => 'month',
            'period_key' => '2026-03',
        ]);
        $this->assertDatabaseHas('attendance_discipline_cases', [
            'user_id' => $student->id,
            'rule_key' => 'semester_total_violation_limit',
            'metric_value' => 30,
            'metric_limit' => 30,
            'period_type' => 'semester',
        ]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $wali->id,
            'title' => 'Batas keterlambatan bulanan terlampaui',
            'type' => 'warning',
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $kesiswaan->id,
            'title' => 'Batas total pelanggaran semester terlampaui',
            'type' => 'warning',
        ]);
    }

    /**
     * @return array{0:User,1:User,2:User,3:Kelas}
     */
    private function seedAlertingClassContext(): array
    {
        $wali = $this->createUserWithRole(RoleNames::WALI_KELAS);
        $kesiswaan = $this->createUserWithRole(RoleNames::WAKASEK_KESISWAAN);
        $student = $this->createUserWithRole(RoleNames::SISWA);

        $student->update(['nama_lengkap' => 'Siswa Alert']);
        $wali->update(['nama_lengkap' => 'Wali Alert']);
        $kesiswaan->update(['nama_lengkap' => 'Kesiswaan Alert']);

        DataKepegawaian::create([
            'user_id' => $wali->id,
            'status_kepegawaian' => 'ASN',
            'no_hp' => '081234567890',
        ]);
        DataKepegawaian::create([
            'user_id' => $kesiswaan->id,
            'status_kepegawaian' => 'ASN',
            'no_hp' => '081298765432',
        ]);

        $tingkat = Tingkat::create([
            'nama' => 'Kelas 10',
            'kode' => 'X',
            'urutan' => 1,
            'is_active' => true,
        ]);

        $tahunAjaran = TahunAjaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'is_active' => true,
            'status' => TahunAjaran::STATUS_ACTIVE,
            'semester' => 'full',
        ]);

        $kelas = Kelas::create([
            'nama_kelas' => 'X-A',
            'tingkat_id' => $tingkat->id,
            'wali_kelas_id' => $wali->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'jumlah_siswa' => 1,
            'is_active' => true,
        ]);

        DB::table('kelas_siswa')->insert([
            'kelas_id' => $kelas->id,
            'siswa_id' => $student->id,
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
            'tanggal_masuk' => '2025-07-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$wali, $kesiswaan, $student, $kelas];
    }

    private function createUserWithRole(string $canonicalRole): User
    {
        $role = Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->where('guard_name', 'web')
            ->firstOrFail();

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
