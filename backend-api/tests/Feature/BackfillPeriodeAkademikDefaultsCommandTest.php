<?php

namespace Tests\Feature;

use App\Models\PeriodeAkademik;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillPeriodeAkademikDefaultsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_does_not_create_periode(): void
    {
        $today = Carbon::today();

        TahunAjaran::create([
            'nama' => 'TA Dry Run',
            'tanggal_mulai' => $today->copy()->subMonths(1)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(10)->format('Y-m-d'),
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $this->artisan('akademik:backfill-periode-default')
            ->assertExitCode(0);

        $this->assertDatabaseCount('periode_akademik', 0);
    }

    public function test_execute_creates_periode_for_active_tahun_ajaran_without_existing_periode(): void
    {
        $today = Carbon::today();

        $activeWithoutPeriode = TahunAjaran::create([
            'nama' => 'TA Active Create',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $activeWithPeriode = TahunAjaran::create([
            'nama' => 'TA Active Existing',
            'tanggal_mulai' => $today->copy()->subMonths(2)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(9)->format('Y-m-d'),
            'semester' => 'ganjil',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        TahunAjaran::create([
            'nama' => 'TA Draft',
            'tanggal_mulai' => $today->copy()->subMonths(2)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(9)->format('Y-m-d'),
            'semester' => 'genap',
            'status' => TahunAjaran::STATUS_DRAFT,
            'is_active' => false,
            'preparation_progress' => 0,
        ]);

        PeriodeAkademik::create([
            'tahun_ajaran_id' => $activeWithPeriode->id,
            'nama' => 'Manual Existing',
            'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
            'tanggal_mulai' => $today->copy()->subDays(5)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addDays(5)->format('Y-m-d'),
            'semester' => PeriodeAkademik::SEMESTER_GANJIL,
            'is_active' => true,
        ]);

        $this->artisan('akademik:backfill-periode-default', [
            '--execute' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseHas('periode_akademik', [
            'tahun_ajaran_id' => $activeWithoutPeriode->id,
            'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
            'semester' => PeriodeAkademik::SEMESTER_GANJIL,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('periode_akademik', [
            'tahun_ajaran_id' => $activeWithoutPeriode->id,
            'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
            'semester' => PeriodeAkademik::SEMESTER_GENAP,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('periode_akademik', [
            'tahun_ajaran_id' => $activeWithPeriode->id,
            'nama' => 'Manual Existing',
        ]);

        $this->assertSame(
            3,
            PeriodeAkademik::query()->count()
        );
    }

    public function test_execute_with_specific_ids_can_target_non_active_tahun_ajaran(): void
    {
        $today = Carbon::today();

        $draft = TahunAjaran::create([
            'nama' => 'TA Draft Targeted',
            'tanggal_mulai' => $today->copy()->subMonths(1)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(10)->format('Y-m-d'),
            'semester' => 'genap',
            'status' => TahunAjaran::STATUS_DRAFT,
            'is_active' => false,
            'preparation_progress' => 0,
        ]);

        $this->artisan('akademik:backfill-periode-default', [
            '--execute' => true,
            '--tahun-ajaran-id' => [$draft->id],
        ])->assertExitCode(0);

        $this->assertDatabaseHas('periode_akademik', [
            'tahun_ajaran_id' => $draft->id,
            'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
            'semester' => PeriodeAkademik::SEMESTER_GENAP,
            'is_active' => true,
        ]);
    }
}
