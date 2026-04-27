<?php

namespace Tests\Unit;

use App\Models\PeriodeAkademik;
use App\Models\TahunAjaran;
use App\Services\PeriodeAkademikSetupService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeriodeAkademikSetupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_default_period_for_active_tahun_ajaran(): void
    {
        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Service',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'semester' => 'full',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        $service = app(PeriodeAkademikSetupService::class);
        $result = $service->ensureDefaultForTahunAjaran($tahunAjaran, 1);

        $this->assertSame(2, $result['created']);
        $this->assertFalse($result['skipped']);

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

    public function test_it_skips_creation_when_period_already_exists(): void
    {
        $today = Carbon::today();

        $tahunAjaran = TahunAjaran::create([
            'nama' => 'TA Existing',
            'tanggal_mulai' => $today->copy()->subMonths(1)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addMonths(10)->format('Y-m-d'),
            'semester' => 'ganjil',
            'status' => TahunAjaran::STATUS_ACTIVE,
            'is_active' => true,
            'preparation_progress' => 100,
        ]);

        PeriodeAkademik::create([
            'tahun_ajaran_id' => $tahunAjaran->id,
            'nama' => 'Manual Periode',
            'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
            'tanggal_mulai' => $today->copy()->subDays(5)->format('Y-m-d'),
            'tanggal_selesai' => $today->copy()->addDays(5)->format('Y-m-d'),
            'semester' => PeriodeAkademik::SEMESTER_GANJIL,
            'is_active' => true,
        ]);

        $service = app(PeriodeAkademikSetupService::class);
        $result = $service->ensureDefaultForTahunAjaran($tahunAjaran, 1);

        $this->assertSame(0, $result['created']);
        $this->assertTrue($result['skipped']);
        $this->assertSame(1, $result['existing']);
    }
}
