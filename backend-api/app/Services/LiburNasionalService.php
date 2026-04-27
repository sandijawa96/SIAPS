<?php

namespace App\Services;

use App\Models\EventAkademik;
use App\Models\TahunAjaran;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LiburNasionalService
{
    private string $apiUrl = 'https://api-harilibur.vercel.app/api';
    private bool $hadApiError = false;
    private array $apiErrors = [];
    private const OFFICIAL_FALLBACK_BY_YEAR = [
        2026 => [
            ['date' => '2026-01-01', 'name' => 'Tahun Baru Masehi', 'is_national_holiday' => true],
            ['date' => '2026-01-16', 'name' => 'Isra Mikraj Nabi Muhammad SAW', 'is_national_holiday' => true],
            ['date' => '2026-01-21', 'name' => 'Cuti Bersama Tahun Baru Imlek', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-02-17', 'name' => 'Tahun Baru Imlek 2577 Kongzili', 'is_national_holiday' => true],
            ['date' => '2026-03-18', 'name' => 'Cuti Bersama Hari Suci Nyepi', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-03-19', 'name' => 'Hari Suci Nyepi Tahun Baru Saka 1948', 'is_national_holiday' => true],
            ['date' => '2026-03-19', 'name' => 'Cuti Bersama Hari Raya Nyepi', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-03-20', 'name' => 'Hari Raya Idulfitri 1447 Hijriah', 'is_national_holiday' => true],
            ['date' => '2026-03-23', 'name' => 'Cuti Bersama Hari Raya Idulfitri 1447 Hijriah', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-03-24', 'name' => 'Cuti Bersama Hari Raya Idulfitri 1447 Hijriah', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-03-27', 'name' => 'Cuti Bersama Hari Raya Idulfitri 1447 Hijriah', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-04-02', 'name' => 'Cuti Bersama Wafat Isa Al Masih', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-04-03', 'name' => 'Wafat Isa Al Masih', 'is_national_holiday' => true],
            ['date' => '2026-04-05', 'name' => 'Hari Paskah', 'is_national_holiday' => true],
            ['date' => '2026-05-01', 'name' => 'Hari Buruh Internasional', 'is_national_holiday' => true],
            ['date' => '2026-05-14', 'name' => 'Kenaikan Isa Al Masih', 'is_national_holiday' => true],
            ['date' => '2026-05-15', 'name' => 'Cuti Bersama Kenaikan Isa Al Masih', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-05-18', 'name' => 'Cuti Bersama Hari Raya Waisak', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-05-27', 'name' => 'Hari Raya Waisak 2570 BE', 'is_national_holiday' => true],
            ['date' => '2026-05-28', 'name' => 'Cuti Bersama Hari Raya Waisak', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-06-01', 'name' => 'Hari Lahir Pancasila', 'is_national_holiday' => true],
            ['date' => '2026-06-17', 'name' => 'Hari Raya Iduladha 1447 Hijriah', 'is_national_holiday' => true],
            ['date' => '2026-06-26', 'name' => '1 Muharam 1448 Hijriah', 'is_national_holiday' => true],
            ['date' => '2026-08-17', 'name' => 'Hari Kemerdekaan Republik Indonesia', 'is_national_holiday' => true],
            ['date' => '2026-09-16', 'name' => 'Maulid Nabi Muhammad SAW', 'is_national_holiday' => true],
            ['date' => '2026-12-17', 'name' => 'Cuti Bersama Hari Raya Natal', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-12-24', 'name' => 'Cuti Bersama Hari Raya Natal', 'is_national_holiday' => false, 'is_collective_leave' => true],
            ['date' => '2026-12-25', 'name' => 'Hari Raya Natal', 'is_national_holiday' => true],
            ['date' => '2026-12-31', 'name' => 'Cuti Bersama Tahun Baru Masehi 2027', 'is_national_holiday' => false, 'is_collective_leave' => true],
        ],
    ];

    /**
     * Sync libur nasional untuk tahun ajaran tertentu
     */
    public function syncLiburNasional(int $tahunAjaranId, array $options = [])
    {
        try {
            $this->hadApiError = false;
            $this->apiErrors = [];

            $tahunAjaran = TahunAjaran::findOrFail($tahunAjaranId);
            $publish = (bool) ($options['publish'] ?? true);
            $forceUpdate = (bool) ($options['force_update'] ?? false);

            $liburNasional = $this->fetchLiburNasional(
                $tahunAjaran->tanggal_mulai,
                $tahunAjaran->tanggal_selesai
            );

            $syncedCount = 0;
            $syncedLiburNasionalCount = 0;
            $syncedCutiBersamaCount = 0;
            $skippedCount = 0;

            foreach ($liburNasional as $libur) {
                $externalId = sha1($tahunAjaranId . '|' . $libur['tanggal_mulai'] . '|' . $libur['nama']);
                $isCutiBersama = (bool) ($libur['is_collective_leave'] ?? false);
                $calendarCategory = $isCutiBersama ? 'cuti_bersama' : 'libur_nasional';
                $source = (string) ($libur['source'] ?? 'api-harilibur');

                $existing = EventAkademik::where('tahun_ajaran_id', $tahunAjaranId)
                    ->where('jenis', 'libur')
                    ->where(function ($query) use ($externalId, $libur) {
                        $query->where('metadata->external_id', $externalId)
                            ->orWhere(function ($fallback) use ($libur) {
                                $fallback->where('tanggal_mulai', $libur['tanggal_mulai'])
                                    ->where('nama', $libur['nama']);
                            });
                    })
                    ->first();

                if (!$existing) {
                    EventAkademik::create([
                        'tahun_ajaran_id' => $tahunAjaranId,
                        'nama' => $libur['nama'],
                        'jenis' => 'libur',
                        'tanggal_mulai' => $libur['tanggal_mulai'],
                        'tanggal_selesai' => $libur['tanggal_selesai'],
                        'is_wajib' => true,
                        'is_active' => $publish,
                        'deskripsi' => $libur['deskripsi'],
                        'metadata' => [
                            'source' => $source,
                            'external_id' => $externalId,
                            'is_national_holiday' => (bool) ($libur['is_national_holiday'] ?? !$isCutiBersama),
                            'is_collective_leave' => $isCutiBersama,
                            'calendar_category' => $calendarCategory,
                            'sync_state' => $publish ? 'published' : 'draft',
                            'synced_at' => now()->toDateTimeString(),
                            'sync_version' => 1
                        ]
                    ]);
                    $syncedCount++;
                    if ($isCutiBersama) {
                        $syncedCutiBersamaCount++;
                    } else {
                        $syncedLiburNasionalCount++;
                    }
                } elseif ($forceUpdate) {
                    $currentMetadata = is_array($existing->metadata) ? $existing->metadata : [];
                    $existing->update([
                        'tanggal_selesai' => $libur['tanggal_selesai'],
                        'deskripsi' => $libur['deskripsi'],
                        'is_active' => $publish,
                        'metadata' => array_merge($currentMetadata, [
                            'source' => $source,
                            'external_id' => $externalId,
                            'is_national_holiday' => (bool) ($libur['is_national_holiday'] ?? !$isCutiBersama),
                            'is_collective_leave' => $isCutiBersama,
                            'calendar_category' => $calendarCategory,
                            'sync_state' => $publish ? 'published' : 'draft',
                            'synced_at' => now()->toDateTimeString(),
                            'sync_version' => (int) ($currentMetadata['sync_version'] ?? 1) + 1
                        ])
                    ]);
                    $syncedCount++;
                    if ($isCutiBersama) {
                        $syncedCutiBersamaCount++;
                    } else {
                        $syncedLiburNasionalCount++;
                    }
                } else {
                    $skippedCount++;
                }
            }

            return [
                'success' => true,
                'synced' => $syncedCount,
                'synced_libur_nasional' => $syncedLiburNasionalCount,
                'synced_cuti_bersama' => $syncedCutiBersamaCount,
                'skipped' => $skippedCount,
                'publish' => $publish,
                'force_update' => $forceUpdate,
                'total' => count($liburNasional),
                'had_api_error' => $this->hadApiError,
                'api_errors' => $this->apiErrors,
            ];
        } catch (\Exception $e) {
            Log::error('Error syncing libur nasional: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Fetch libur nasional dari API eksternal
     */
    private function fetchLiburNasional($startDate, $endDate)
    {
        try {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $liburNasional = [];

            for ($year = $start->year; $year <= $end->year; $year++) {
                $yearData = $this->fetchLiburNasionalByYear($year);

                foreach ($yearData as $libur) {
                    $holidayDate = isset($libur['holiday_date']) ? (string) $libur['holiday_date'] : null;
                    $holidayName = trim((string) ($libur['holiday_name'] ?? ''));
                    if (empty($holidayDate) || $holidayName === '') {
                        continue;
                    }

                    try {
                        $liburDate = Carbon::parse($holidayDate);
                    } catch (\Throwable $e) {
                        continue;
                    }

                    $lowerHolidayName = strtolower($holidayName);
                    $isCutiBersama = array_key_exists('is_collective_leave', $libur)
                        ? (bool) $libur['is_collective_leave']
                        : str_contains($lowerHolidayName, 'cuti bersama');
                    $isNationalHoliday = array_key_exists('is_national_holiday', $libur)
                        ? (bool) $libur['is_national_holiday']
                        : !$isCutiBersama;

                    if ($liburDate->between($start, $end)) {
                        $descriptionPrefix = $isCutiBersama ? 'Cuti Bersama' : 'Hari Libur Nasional';
                        $liburNasional[] = [
                            'nama' => $holidayName,
                            'tanggal_mulai' => $holidayDate,
                            'tanggal_selesai' => $holidayDate,
                            'deskripsi' => "{$descriptionPrefix}: {$holidayName}",
                            'is_national_holiday' => $isNationalHoliday,
                            'is_collective_leave' => $isCutiBersama || !$isNationalHoliday,
                            'source' => (string) ($libur['source'] ?? 'api-harilibur'),
                        ];
                    }
                }
            }

            return $this->uniqueLiburByDateAndName($liburNasional);
        } catch (\Exception $e) {
            Log::error('Error fetching libur nasional: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch libur nasional untuk tahun tertentu
     */
    private function fetchLiburNasionalByYear(int $year): array
    {
        try {
            $response = Http::timeout(30)->get($this->apiUrl, [
                'year' => $year
            ]);

            if ($response->successful()) {
                $normalized = $this->normalizeApiHariliburRows($response->json());
                if (!empty($normalized)) {
                    return $normalized;
                }

                $fallback = $this->getFallbackLiburNasionalByYear($year);
                if (!empty($fallback)) {
                    $this->hadApiError = true;
                    $this->apiErrors[] = "Year {$year} returned empty payload; fallback data applied";
                    Log::warning("Empty libur nasional payload for year {$year}; using fallback data");
                    return $fallback;
                }

                return [];
            }

            $this->hadApiError = true;
            $fallback = $this->getFallbackLiburNasionalByYear($year);
            if (!empty($fallback)) {
                $this->apiErrors[] = "Failed to fetch year {$year}: HTTP {$response->status()} (fallback data applied)";
                Log::warning("Failed to fetch libur nasional for year {$year}: {$response->status()}, using fallback data");
                return $fallback;
            }

            $this->apiErrors[] = "Failed to fetch year {$year}: HTTP {$response->status()}";
            Log::warning("Failed to fetch libur nasional for year {$year}: " . $response->status());
            return [];
        } catch (\Exception $e) {
            $this->hadApiError = true;
            $fallback = $this->getFallbackLiburNasionalByYear($year);
            if (!empty($fallback)) {
                $this->apiErrors[] = "Failed to fetch year {$year}: {$e->getMessage()} (fallback data applied)";
                Log::warning("Error fetching libur nasional for year {$year}: {$e->getMessage()}, using fallback data");
                return $fallback;
            }

            $this->apiErrors[] = "Failed to fetch year {$year}: {$e->getMessage()}";
            Log::error("Error fetching libur nasional for year {$year}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * @param mixed $payload
     * @return array<int, array<string, mixed>>
     */
    private function normalizeApiHariliburRows($payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        $normalized = [];
        foreach ($payload as $row) {
            if (!is_array($row)) {
                continue;
            }

            $holidayDate = isset($row['holiday_date']) ? (string) $row['holiday_date'] : '';
            $holidayName = trim((string) ($row['holiday_name'] ?? ''));
            if ($holidayDate === '' || $holidayName === '') {
                continue;
            }

            $lowerName = strtolower($holidayName);
            $isCollectiveLeave = array_key_exists('is_collective_leave', $row)
                ? (bool) $row['is_collective_leave']
                : str_contains($lowerName, 'cuti bersama');
            $isNationalHoliday = array_key_exists('is_national_holiday', $row)
                ? (bool) $row['is_national_holiday']
                : !$isCollectiveLeave;

            $normalized[] = [
                'holiday_date' => $holidayDate,
                'holiday_name' => $holidayName,
                'is_national_holiday' => $isNationalHoliday,
                'is_collective_leave' => $isCollectiveLeave || !$isNationalHoliday,
                'source' => 'api-harilibur',
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>> $records
     * @return array<int, array<string, mixed>>
     */
    private function uniqueLiburByDateAndName(array $records): array
    {
        $unique = [];
        $keys = [];

        foreach ($records as $row) {
            $date = (string) ($row['tanggal_mulai'] ?? '');
            $name = trim((string) ($row['nama'] ?? ''));
            if ($date === '' || $name === '') {
                continue;
            }

            $key = strtolower($date . '|' . $name);
            if (isset($keys[$key])) {
                continue;
            }

            $keys[$key] = true;
            $unique[] = $row;
        }

        usort($unique, static function (array $a, array $b): int {
            $dateDiff = strcmp((string) ($a['tanggal_mulai'] ?? ''), (string) ($b['tanggal_mulai'] ?? ''));
            if ($dateDiff !== 0) {
                return $dateDiff;
            }

            return strcmp((string) ($a['nama'] ?? ''), (string) ($b['nama'] ?? ''));
        });

        return $unique;
    }

    /**
     * Official fallback (SKB 3 Menteri) when external API is unavailable.
     */
    private function getFallbackLiburNasionalByYear(int $year): array
    {
        $records = self::OFFICIAL_FALLBACK_BY_YEAR[$year] ?? [];
        if (empty($records)) {
            return [];
        }

        return array_map(static function (array $record): array {
            $isNationalHoliday = (bool) ($record['is_national_holiday'] ?? true);
            $isCollectiveLeave = (bool) ($record['is_collective_leave'] ?? !$isNationalHoliday);

            return [
                'holiday_date' => (string) $record['date'],
                'holiday_name' => (string) $record['name'],
                'is_national_holiday' => $isNationalHoliday,
                'is_collective_leave' => $isCollectiveLeave,
                'source' => 'fallback-skb',
            ];
        }, $records);
    }

    /**
     * Get preview libur nasional tanpa menyimpan ke database
     */
    public function previewLiburNasional($tahunAjaranId)
    {
        try {
            $tahunAjaran = TahunAjaran::findOrFail($tahunAjaranId);

            $liburNasional = $this->fetchLiburNasional(
                $tahunAjaran->tanggal_mulai,
                $tahunAjaran->tanggal_selesai
            );

            foreach ($liburNasional as &$libur) {
                $existing = EventAkademik::where('tahun_ajaran_id', $tahunAjaranId)
                    ->where('tanggal_mulai', $libur['tanggal_mulai'])
                    ->where('nama', $libur['nama'])
                    ->where('jenis', 'libur')
                    ->exists();

                $libur['already_exists'] = $existing;
            }

            return $liburNasional;
        } catch (\Exception $e) {
            Log::error('Error previewing libur nasional: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Auto sync libur nasional untuk semua tahun ajaran aktif
     */
    public function autoSyncAllActiveTahunAjaran(array $options = [])
    {
        try {
            $activeTahunAjaran = TahunAjaran::whereIn('status', ['active', 'preparation'])->get();
            $results = [];

            foreach ($activeTahunAjaran as $tahunAjaran) {
                $result = $this->syncLiburNasional((int) $tahunAjaran->id, $options);
                $results[] = [
                    'tahun_ajaran' => $tahunAjaran->nama,
                    'result' => $result
                ];
            }

            return $results;
        } catch (\Exception $e) {
            Log::error('Error auto syncing libur nasional: ' . $e->getMessage());
            throw $e;
        }
    }
}

