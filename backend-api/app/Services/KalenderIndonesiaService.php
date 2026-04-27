<?php

namespace App\Services;

use App\Models\EventAkademik;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class KalenderIndonesiaService
{
    /**
     * Kalender Indonesia (hari besar + peringatan) dengan tanggal tetap.
     *
     * @var array<int, array<string, int|string|bool>>
     */
    private const COMMEMORATIVE_DAYS = [
        ['month' => 1, 'day' => 1, 'name' => 'Tahun Baru Masehi', 'is_holiday' => true],
        ['month' => 1, 'day' => 25, 'name' => 'Hari Gizi Nasional', 'is_holiday' => false],
        ['month' => 2, 'day' => 21, 'name' => 'Hari Bahasa Ibu Internasional', 'is_holiday' => false],
        ['month' => 3, 'day' => 8, 'name' => 'Hari Perempuan Internasional', 'is_holiday' => false],
        ['month' => 4, 'day' => 21, 'name' => 'Hari Kartini', 'is_holiday' => false],
        ['month' => 5, 'day' => 1, 'name' => 'Hari Buruh Internasional', 'is_holiday' => true],
        ['month' => 5, 'day' => 2, 'name' => 'Hari Pendidikan Nasional', 'is_holiday' => false],
        ['month' => 5, 'day' => 20, 'name' => 'Hari Kebangkitan Nasional', 'is_holiday' => false],
        ['month' => 7, 'day' => 23, 'name' => 'Hari Anak Nasional', 'is_holiday' => false],
        ['month' => 8, 'day' => 17, 'name' => 'Hari Kemerdekaan Republik Indonesia', 'is_holiday' => true],
        ['month' => 10, 'day' => 1, 'name' => 'Hari Kesaktian Pancasila', 'is_holiday' => false],
        ['month' => 10, 'day' => 2, 'name' => 'Hari Batik Nasional', 'is_holiday' => false],
        ['month' => 10, 'day' => 22, 'name' => 'Hari Santri Nasional', 'is_holiday' => false],
        ['month' => 11, 'day' => 10, 'name' => 'Hari Pahlawan', 'is_holiday' => false],
        ['month' => 11, 'day' => 25, 'name' => 'Hari Guru Nasional', 'is_holiday' => false],
        ['month' => 12, 'day' => 22, 'name' => 'Hari Ibu', 'is_holiday' => false],
        ['month' => 12, 'day' => 25, 'name' => 'Hari Natal', 'is_holiday' => true],
    ];

    public function syncKalenderIndonesia(int $tahunAjaranId, array $options = []): array
    {
        try {
            $tahunAjaran = TahunAjaran::findOrFail($tahunAjaranId);
            $publish = (bool) ($options['publish'] ?? true);
            $forceUpdate = (bool) ($options['force_update'] ?? false);

            $events = $this->buildKalenderIndonesiaEvents(
                $tahunAjaran->tanggal_mulai,
                $tahunAjaran->tanggal_selesai
            );

            $syncedCount = 0;
            $syncedLiburCount = 0;
            $syncedKegiatanCount = 0;
            $skippedCount = 0;

            foreach ($events as $event) {
                $existing = EventAkademik::query()
                    ->where('tahun_ajaran_id', $tahunAjaranId)
                    ->where('tanggal_mulai', $event['tanggal_mulai'])
                    ->where('nama', $event['nama'])
                    ->first();

                if (!$existing && $event['jenis'] === EventAkademik::JENIS_LIBUR) {
                    $sameDayHolidayExists = EventAkademik::query()
                        ->where('tahun_ajaran_id', $tahunAjaranId)
                        ->where('tanggal_mulai', $event['tanggal_mulai'])
                        ->where('jenis', EventAkademik::JENIS_LIBUR)
                        ->exists();

                    if ($sameDayHolidayExists) {
                        $skippedCount++;
                        continue;
                    }
                }

                if (!$existing) {
                    EventAkademik::create([
                        'tahun_ajaran_id' => $tahunAjaranId,
                        'nama' => $event['nama'],
                        'jenis' => $event['jenis'],
                        'tanggal_mulai' => $event['tanggal_mulai'],
                        'tanggal_selesai' => $event['tanggal_selesai'],
                        'is_wajib' => false,
                        'is_active' => $publish,
                        'deskripsi' => $event['deskripsi'],
                        'metadata' => [
                            'source' => 'kalender-indonesia',
                            'calendar_category' => $event['calendar_category'],
                            'is_holiday' => $event['is_holiday'],
                            'external_id' => $event['external_id'],
                            'sync_state' => $publish ? 'published' : 'draft',
                            'synced_at' => now()->toDateTimeString(),
                            'sync_version' => 1,
                        ],
                    ]);
                    $syncedCount++;
                    if ($event['jenis'] === EventAkademik::JENIS_LIBUR) {
                        $syncedLiburCount++;
                    } else {
                        $syncedKegiatanCount++;
                    }
                } elseif ($forceUpdate) {
                    $currentMetadata = is_array($existing->metadata) ? $existing->metadata : [];
                    $existing->update([
                        'jenis' => $event['jenis'],
                        'tanggal_selesai' => $event['tanggal_selesai'],
                        'deskripsi' => $event['deskripsi'],
                        'is_active' => $publish,
                        'metadata' => array_merge($currentMetadata, [
                            'source' => 'kalender-indonesia',
                            'calendar_category' => $event['calendar_category'],
                            'is_holiday' => $event['is_holiday'],
                            'external_id' => $event['external_id'],
                            'sync_state' => $publish ? 'published' : 'draft',
                            'synced_at' => now()->toDateTimeString(),
                            'sync_version' => (int) ($currentMetadata['sync_version'] ?? 1) + 1,
                        ]),
                    ]);
                    $syncedCount++;
                    if ($event['jenis'] === EventAkademik::JENIS_LIBUR) {
                        $syncedLiburCount++;
                    } else {
                        $syncedKegiatanCount++;
                    }
                } else {
                    $skippedCount++;
                }
            }

            return [
                'success' => true,
                'synced' => $syncedCount,
                'synced_libur' => $syncedLiburCount,
                'synced_kegiatan' => $syncedKegiatanCount,
                'skipped' => $skippedCount,
                'publish' => $publish,
                'force_update' => $forceUpdate,
                'total' => count($events),
            ];
        } catch (\Exception $e) {
            Log::error('Error syncing kalender indonesia: ' . $e->getMessage());
            throw $e;
        }
    }

    public function previewKalenderIndonesia(int $tahunAjaranId): array
    {
        try {
            $tahunAjaran = TahunAjaran::findOrFail($tahunAjaranId);

            $events = $this->buildKalenderIndonesiaEvents(
                $tahunAjaran->tanggal_mulai,
                $tahunAjaran->tanggal_selesai
            );

            foreach ($events as &$event) {
                $event['already_exists'] = EventAkademik::query()
                    ->where('tahun_ajaran_id', $tahunAjaranId)
                    ->where('tanggal_mulai', $event['tanggal_mulai'])
                    ->where('nama', $event['nama'])
                    ->exists();
            }

            return $events;
        } catch (\Exception $e) {
            Log::error('Error previewing kalender indonesia: ' . $e->getMessage());
            throw $e;
        }
    }

    public function autoSyncAllActiveTahunAjaran(array $options = []): array
    {
        try {
            $targets = TahunAjaran::query()
                ->whereIn('status', [TahunAjaran::STATUS_ACTIVE, TahunAjaran::STATUS_PREPARATION])
                ->get();

            $results = [];
            foreach ($targets as $tahunAjaran) {
                $result = $this->syncKalenderIndonesia((int) $tahunAjaran->id, $options);
                $results[] = [
                    'tahun_ajaran' => $tahunAjaran->nama,
                    'result' => $result,
                ];
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('Error auto syncing kalender indonesia: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * @param \DateTimeInterface|string $startDate
     * @param \DateTimeInterface|string $endDate
     * @return array<int, array<string, string>>
     */
    private function buildKalenderIndonesiaEvents($startDate, $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();
        $results = [];

        for ($year = $start->year; $year <= $end->year; $year++) {
            foreach (self::COMMEMORATIVE_DAYS as $day) {
                $date = Carbon::create($year, (int) $day['month'], (int) $day['day'])->startOfDay();
                if (!$date->between($start, $end, true)) {
                    continue;
                }

                $isoDate = $date->format('Y-m-d');
                $name = (string) $day['name'];
                $isHoliday = (bool) ($day['is_holiday'] ?? false);
                $results[] = [
                    'nama' => $name,
                    'jenis' => $isHoliday ? EventAkademik::JENIS_LIBUR : EventAkademik::JENIS_KEGIATAN,
                    'tanggal_mulai' => $isoDate,
                    'tanggal_selesai' => $isoDate,
                    'deskripsi' => $isHoliday
                        ? "Hari besar (libur) kalender Indonesia: {$name}"
                        : "Peringatan kalender Indonesia: {$name}",
                    'calendar_category' => $isHoliday ? 'hari_besar_libur' : 'peringatan',
                    'is_holiday' => $isHoliday,
                    'external_id' => sha1("kalender-indonesia|{$isoDate}|{$name}|".($isHoliday ? 'libur' : 'peringatan')),
                ];
            }
        }

        usort($results, static fn(array $a, array $b) => strcmp($a['tanggal_mulai'], $b['tanggal_mulai']));

        return $results;
    }
}
