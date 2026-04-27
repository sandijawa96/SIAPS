<?php

namespace Tests\Feature;

use App\Models\EventAkademik;
use App\Models\Kelas;
use App\Models\PeriodeAkademik;
use App\Models\Role;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AcademicRouteResolutionIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_periode_static_route_is_resolved_correctly(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/periode-akademik/current/periode')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => null,
            ]);
    }

    public function test_current_periode_uses_active_tahun_ajaran_and_returns_setup_meta_when_empty(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Aktif',
            'tanggal_mulai' => $today->copy()->subMonths(2)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(8)->format('Y-m-d'),
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/periode-akademik/current/periode')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => null,
                'meta' => [
                    'needs_setup' => true,
                    'reason' => 'no_running_periode',
                    'tahun_ajaran_id' => $tahunAjaran->id,
                ],
            ]);
    }

    public function test_event_upcoming_and_today_static_routes_are_resolved_correctly(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/event-akademik/user/upcoming?days=7')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/event-akademik/user/today')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_event_upcoming_and_today_default_to_active_tahun_ajaran(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        $tahunAjaranActive = TahunAjaran::create([
            'nama' => 'TA Active',
            'tanggal_mulai' => $today->copy()->subMonths(3)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(6)->format('Y-m-d'),
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $tahunAjaranOther = TahunAjaran::create([
            'nama' => 'TA Other',
            'tanggal_mulai' => $today->copy()->subYears(1)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addYears(1)->format('Y-m-d'),
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_COMPLETED,
            'is_active' => false,
            'preparation_progress' => 100,
        ]);

        EventAkademik::create([
            'tahun_ajaran_id' => $tahunAjaranActive->id,
            'nama' => 'Upcoming Active',
            'jenis' => EventAkademik::JENIS_KEGIATAN,
            'tanggal_mulai' => $today->copy()->addDays(2)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addDays(2)->format('Y-m-d'),
            'is_wajib' => false,
            'is_active' => true,
        ]);
        EventAkademik::create([
            'tahun_ajaran_id' => $tahunAjaranOther->id,
            'nama' => 'Upcoming Other',
            'jenis' => EventAkademik::JENIS_KEGIATAN,
            'tanggal_mulai' => $today->copy()->addDays(2)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addDays(2)->format('Y-m-d'),
            'is_wajib' => false,
            'is_active' => true,
        ]);
        EventAkademik::create([
            'tahun_ajaran_id' => $tahunAjaranActive->id,
            'nama' => 'Today Active',
            'jenis' => EventAkademik::JENIS_KEGIATAN,
            'tanggal_mulai' => $today->format('Y-m-d'),
            'tanggal_selesai' => $today->format('Y-m-d'),
            'is_wajib' => false,
            'is_active' => true,
        ]);
        EventAkademik::create([
            'tahun_ajaran_id' => $tahunAjaranOther->id,
            'nama' => 'Today Other',
            'jenis' => EventAkademik::JENIS_KEGIATAN,
            'tanggal_mulai' => $today->format('Y-m-d'),
            'tanggal_selesai' => $today->format('Y-m-d'),
            'is_wajib' => false,
            'is_active' => true,
        ]);

        $upcomingResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/event-akademik/user/upcoming?days=7')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'tahun_ajaran_id' => $tahunAjaranActive->id,
                ],
            ]);

        $upcomingData = $upcomingResponse->json('data');
        $this->assertCount(1, $upcomingData);
        $this->assertSame('Upcoming Active', $upcomingData[0]['nama']);

        $todayResponse = $this->actingAs($user, 'sanctum')
            ->getJson('/api/event-akademik/user/today')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'meta' => [
                    'tahun_ajaran_id' => $tahunAjaranActive->id,
                ],
            ]);

        $todayData = $todayResponse->json('data');
        $this->assertCount(1, $todayData);
        $this->assertSame('Today Active', $todayData[0]['nama']);
    }

    public function test_event_index_date_range_includes_overlapping_multi_day_event(): void
    {
        $user = User::factory()->create();
        $today = Carbon::today();

        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Overlap',
            'tanggal_mulai' => $today->copy()->subMonths(1)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(6)->format('Y-m-d'),
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        EventAkademik::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Rentang Panjang',
            'jenis' => EventAkademik::JENIS_KEGIATAN,
            'tanggal_mulai' => $today->copy()->subDays(3)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addDays(3)->format('Y-m-d'),
            'is_wajib' => false,
            'is_active' => true,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/event-akademik?no_pagination=1&tanggal_mulai=' . $today->format('Y-m-d') . '&tanggal_selesai=' . $today->copy()->addDay()->format('Y-m-d'))
            ->assertStatus(200)
            ->assertJsonPath('data.0.nama', 'Rentang Panjang');
    }

    public function test_event_today_for_student_uses_active_class_only(): void
    {
        $today = Carbon::today();
        Role::updateOrCreate(
            ['name' => RoleNames::SISWA, 'guard_name' => 'web'],
            ['display_name' => RoleNames::SISWA, 'description' => 'Role siswa untuk test event akademik', 'level' => 1, 'is_active' => true]
        );
        $user = User::factory()->create();
        $user->assignRole(RoleNames::SISWA);

        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Siswa Aktif',
            'tanggal_mulai' => $today->copy()->subMonths(1)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(6)->format('Y-m-d'),
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $tingkat = Tingkat::create([
            'nama' => 'Kelas XI',
            'kode' => 'XI',
            'deskripsi' => 'Test',
            'urutan' => 2,
            'is_active' => true,
        ]);

        $oldClass = Kelas::create([
            'nama_kelas' => 'XI IPA 1',
            'tingkat_id' => $tingkat->id,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
        ]);

        $activeClass = Kelas::create([
            'nama_kelas' => 'XI IPA 2',
            'tingkat_id' => $tingkat->id,
            'jurusan' => 'IPA',
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kapasitas' => 36,
            'jumlah_siswa' => 0,
            'is_active' => true,
        ]);

        $user->kelas()->attach($oldClass->id, [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'pindah',
            'is_active' => false,
        ]);
        $user->kelas()->attach($activeClass->id, [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'status' => 'aktif',
            'is_active' => true,
        ]);

        EventAkademik::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $oldClass->id,
            'nama' => 'Event Kelas Lama',
            'jenis' => EventAkademik::JENIS_KEGIATAN,
            'tanggal_mulai' => $today->format('Y-m-d'),
            'tanggal_selesai' => $today->format('Y-m-d'),
            'is_wajib' => false,
            'is_active' => true,
        ]);

        EventAkademik::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'kelas_id' => $activeClass->id,
            'nama' => 'Event Kelas Aktif',
            'jenis' => EventAkademik::JENIS_KEGIATAN,
            'tanggal_mulai' => $today->format('Y-m-d'),
            'tanggal_selesai' => $today->format('Y-m-d'),
            'is_wajib' => false,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/event-akademik/user/today')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Event Kelas Aktif', $data[0]['nama']);
    }

    public function test_store_event_auto_assigns_periode_when_missing(): void
    {
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);

        $user = User::factory()->create();
        $today = Carbon::today();

        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Auto Period',
            'tanggal_mulai' => $today->copy()->subMonths(2)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(8)->format('Y-m-d'),
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $periode = PeriodeAkademik::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Periode Aktif',
            'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
            'tanggal_mulai' => $today->copy()->subDays(5)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addDays(5)->format('Y-m-d'),
            'semester' => PeriodeAkademik::SEMESTER_BOTH,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik', [
                'tahun_ajaran_id' => $tahunAjaran->id,
                'nama' => 'Event Auto Period',
                'jenis' => EventAkademik::JENIS_KEGIATAN,
                'tanggal_mulai' => $today->format('Y-m-d'),
                'tanggal_selesai' => $today->format('Y-m-d'),
                'is_active' => true,
            ])
            ->assertStatus(201)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertSame($periode->id, $response->json('data.periode_akademik_id'));
    }

    public function test_activate_tahun_ajaran_endpoint_creates_default_periode(): void
    {
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);

        $user = User::factory()->create();
        $today = Carbon::today();

        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Activate Endpoint',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_DRAFT,
            'is_active' => false,
            'preparation_progress' => 0,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/tahun-ajaran/{$tahunAjaran->id}/activate")
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'setup' => [
                    'created' => 2,
                    'skipped' => false,
                ],
            ]);

        $this->assertDatabaseHas('tahun_ajaran', [
            'id' => $tahunAjaran->id,
            'status' => TahunAjaran::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('periode_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
            'semester' => PeriodeAkademik::SEMESTER_GANJIL,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('periode_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
            'semester' => PeriodeAkademik::SEMESTER_GENAP,
            'is_active' => true,
        ]);
    }

    public function test_sync_kalender_indonesia_creates_commemorative_events_as_kegiatan(): void
    {
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);

        $user = User::factory()->create();
        $today = Carbon::today();

        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Kalender Indo',
            'tanggal_mulai' => $today->copy()->startOfYear()->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->endOfYear()->format('Y-m-d'),
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-kalender-indonesia', [
                'tahun_ajaran_id' => $tahunAjaran->id,
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('event_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Hari Kartini',
            'jenis' => EventAkademik::JENIS_KEGIATAN,
        ]);

        $this->assertDatabaseHas('event_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Hari Kemerdekaan Republik Indonesia',
            'jenis' => EventAkademik::JENIS_LIBUR,
        ]);

        $this->assertDatabaseMissing('event_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Hari Kartini',
            'jenis' => EventAkademik::JENIS_LIBUR,
        ]);
    }

    public function test_sync_kalender_indonesia_lengkap_creates_libur_cuti_and_peringatan(): void
    {
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);

        Http::fake([
            'https://api-harilibur.vercel.app/api*' => Http::response([
                [
                    'holiday_date' => '2026-01-01',
                    'holiday_name' => 'Tahun Baru Masehi',
                    'is_national_holiday' => true,
                ],
                [
                    'holiday_date' => '2026-03-20',
                    'holiday_name' => 'Cuti Bersama Hari Raya Nyepi',
                    'is_national_holiday' => false,
                ],
            ], 200),
        ]);

        $user = User::factory()->create();

        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Kalender Lengkap',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-kalender-indonesia-lengkap', [
                'tahun_ajaran_id' => $tahunAjaran->id,
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertDatabaseHas('event_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Tahun Baru Masehi',
            'jenis' => EventAkademik::JENIS_LIBUR,
        ]);

        $this->assertDatabaseHas('event_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Cuti Bersama Hari Raya Nyepi',
            'jenis' => EventAkademik::JENIS_LIBUR,
        ]);

        $this->assertDatabaseHas('event_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Hari Kartini',
            'jenis' => EventAkademik::JENIS_KEGIATAN,
        ]);
    }

    public function test_sync_kalender_indonesia_lengkap_uses_fallback_when_libur_api_fails(): void
    {
        $this->withoutMiddleware(\Spatie\Permission\Middleware\PermissionMiddleware::class);

        Http::fake([
            'https://api-harilibur.vercel.app/api*' => Http::response([
                'message' => 'payment required',
            ], 402),
        ]);

        $user = User::factory()->create();

        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Kalender Fallback',
            'tanggal_mulai' => '2026-01-01',
            'tanggal_selesai' => '2026-12-31',
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/event-akademik/sync-kalender-indonesia-lengkap', [
                'tahun_ajaran_id' => $tahunAjaran->id,
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'summary' => [
                        'had_api_error' => true,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('event_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Hari Raya Idulfitri 1447 Hijriah',
            'jenis' => EventAkademik::JENIS_LIBUR,
        ]);

        $this->assertDatabaseHas('event_akademik', [
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Cuti Bersama Hari Raya Idulfitri 1447 Hijriah',
            'jenis' => EventAkademik::JENIS_LIBUR,
        ]);
    }
}
