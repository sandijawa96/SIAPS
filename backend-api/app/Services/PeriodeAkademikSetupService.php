<?php

namespace App\Services;

use App\Models\PeriodeAkademik;
use App\Models\TahunAjaran;
use Carbon\Carbon;

class PeriodeAkademikSetupService
{
    /**
     * Ensure at least one default active period exists for the given tahun ajaran.
     */
    public function ensureDefaultForTahunAjaran(TahunAjaran $tahunAjaran, ?int $actorId = null): array
    {
        $existingCount = PeriodeAkademik::where('tahun_ajaran_id', $tahunAjaran->id)->count();
        if ($existingCount > 0) {
            return [
                'created' => 0,
                'existing' => $existingCount,
                'skipped' => true,
                'reason' => 'periode_already_exists',
            ];
        }

        $createdPeriodIds = [];

        if (strtolower((string) $tahunAjaran->semester) === 'full') {
            $segments = $this->buildSemesterSegments(
                $tahunAjaran->tanggal_mulai->copy(),
                $tahunAjaran->tanggal_selesai->copy()
            );

            foreach ($segments as $segment) {
                $semester = (string) $segment['semester'];
                $semesterLabel = $semester === PeriodeAkademik::SEMESTER_GANJIL
                    ? 'Ganjil'
                    : 'Genap';

                $periode = PeriodeAkademik::create([
                    'tahun_ajaran_id' => $tahunAjaran->id,
                    'nama' => "Periode Pembelajaran {$semesterLabel} {$tahunAjaran->nama}",
                    'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
                    'tanggal_mulai' => $segment['tanggal_mulai']->format('Y-m-d'),
                    'tanggal_selesai' => $segment['tanggal_selesai']->format('Y-m-d'),
                    'semester' => $semester,
                    'is_active' => true,
                    'keterangan' => 'Dibuat otomatis saat aktivasi tahun ajaran',
                    'metadata' => [
                        'auto_created' => true,
                        'source' => 'tahun_ajaran_activation',
                        'actor_id' => $actorId,
                        'created_at' => now()->toDateTimeString(),
                    ],
                ]);

                $createdPeriodIds[] = $periode->id;
            }
        } else {
            $semesterMap = [
                'ganjil' => PeriodeAkademik::SEMESTER_GANJIL,
                'genap' => PeriodeAkademik::SEMESTER_GENAP,
                'full' => PeriodeAkademik::SEMESTER_BOTH,
            ];

            $defaultSemester = $semesterMap[$tahunAjaran->semester] ?? PeriodeAkademik::SEMESTER_BOTH;
            $defaultName = $defaultSemester === PeriodeAkademik::SEMESTER_BOTH
                ? "Periode Pembelajaran {$tahunAjaran->nama}"
                : 'Periode Pembelajaran ' . ucfirst($defaultSemester) . " {$tahunAjaran->nama}";

            $periode = PeriodeAkademik::create([
                'tahun_ajaran_id' => $tahunAjaran->id,
                'nama' => $defaultName,
                'jenis' => PeriodeAkademik::JENIS_PEMBELAJARAN,
                'tanggal_mulai' => $tahunAjaran->tanggal_mulai->format('Y-m-d'),
                'tanggal_selesai' => $tahunAjaran->tanggal_selesai->format('Y-m-d'),
                'semester' => $defaultSemester,
                'is_active' => true,
                'keterangan' => 'Dibuat otomatis saat aktivasi tahun ajaran',
                'metadata' => [
                    'auto_created' => true,
                    'source' => 'tahun_ajaran_activation',
                    'actor_id' => $actorId,
                    'created_at' => now()->toDateTimeString(),
                ],
            ]);

            $createdPeriodIds[] = $periode->id;
        }

        return [
            'created' => count($createdPeriodIds),
            'existing' => 0,
            'skipped' => false,
            'periode_ids' => $createdPeriodIds,
        ];
    }

    /**
     * @return array<int, array{semester:string,tanggal_mulai:Carbon,tanggal_selesai:Carbon}>
     */
    private function buildSemesterSegments(Carbon $startDate, Carbon $endDate): array
    {
        $segments = [];
        $cursor = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        while ($cursor->lte($end)) {
            $isGanjil = $cursor->month >= 7;
            $semester = $isGanjil
                ? PeriodeAkademik::SEMESTER_GANJIL
                : PeriodeAkademik::SEMESTER_GENAP;

            $segmentEnd = $isGanjil
                ? $cursor->copy()->month(12)->endOfMonth()
                : $cursor->copy()->month(6)->endOfMonth();

            if ($segmentEnd->gt($end)) {
                $segmentEnd = $end->copy();
            }

            $segments[] = [
                'semester' => $semester,
                'tanggal_mulai' => $cursor->copy(),
                'tanggal_selesai' => $segmentEnd->copy(),
            ];

            $cursor = $segmentEnd->copy()->addDay()->startOfDay();
        }

        return $segments;
    }
}
