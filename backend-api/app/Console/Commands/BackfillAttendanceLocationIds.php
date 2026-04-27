<?php

namespace App\Console\Commands;

use App\Models\Absensi;
use App\Models\LokasiGps;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BackfillAttendanceLocationIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:backfill-location-ids
        {--execute : Apply updates to database. Without this option, command runs in dry-run mode}
        {--allow-nearest : Assign nearest active location when coordinates are outside all radius}
        {--single-location-fallback : If exactly one active location exists, use it when coordinates are missing}
        {--chunk=200 : Chunk size while scanning attendance rows}
        {--limit=0 : Max rows to process (0 = no limit)}
        {--date-from= : Filter by attendance date from (Y-m-d)}
        {--date-to= : Filter by attendance date to (Y-m-d)}
        {--user-id=* : Filter by one or more user IDs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill lokasi_masuk_id/lokasi_pulang_id from historical attendance coordinates.';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $allowNearest = (bool) $this->option('allow-nearest');
        $singleLocationFallback = (bool) $this->option('single-location-fallback');
        $chunk = max(1, (int) $this->option('chunk'));
        $limit = max(0, (int) $this->option('limit'));
        $userIds = collect((array) $this->option('user-id'))
            ->filter(static fn ($id): bool => is_numeric($id))
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $dateFrom = $this->option('date-from');
        $dateTo = $this->option('date-to');
        if (($dateFrom && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateFrom))
            || ($dateTo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $dateTo))) {
            $this->error('Invalid date format. Use Y-m-d for --date-from and --date-to.');
            return self::INVALID;
        }

        if (!$execute) {
            $this->warn('Dry-run mode enabled. Use --execute to write updates.');
        }
        if ($allowNearest) {
            $this->warn('Nearest fallback enabled: coordinates outside radius can still be assigned to nearest location.');
        }

        $activeLocations = LokasiGps::query()
            ->where('is_active', true)
            ->get(['id', 'nama_lokasi', 'latitude', 'longitude', 'radius', 'geofence_type', 'geofence_geojson']);

        if ($activeLocations->isEmpty()) {
            $this->error('No active locations found. Aborting backfill.');
            return self::FAILURE;
        }

        $singleFallbackLocationId = null;
        if ($singleLocationFallback) {
            if ($activeLocations->count() === 1) {
                $singleFallbackLocationId = (int) $activeLocations->first()->id;
                $this->warn("Single-location fallback enabled using location ID {$singleFallbackLocationId}.");
            } else {
                $this->warn('Single-location fallback ignored because active location count is not exactly one.');
            }
        }

        $query = Absensi::query()
            ->select([
                'id',
                'user_id',
                'tanggal',
                'latitude_masuk',
                'longitude_masuk',
                'lokasi_masuk_id',
                'latitude_pulang',
                'longitude_pulang',
                'lokasi_pulang_id',
            ])
            ->where(function (Builder $builder) use ($singleFallbackLocationId): void {
                if ($singleFallbackLocationId !== null) {
                    $builder
                        ->whereNull('lokasi_masuk_id')
                        ->orWhereNull('lokasi_pulang_id');
                    return;
                }

                $builder
                    ->where(function (Builder $q): void {
                        $q->whereNull('lokasi_masuk_id')
                            ->whereNotNull('latitude_masuk')
                            ->whereNotNull('longitude_masuk');
                    })
                    ->orWhere(function (Builder $q): void {
                        $q->whereNull('lokasi_pulang_id')
                            ->whereNotNull('latitude_pulang')
                            ->whereNotNull('longitude_pulang');
                    });
            })
            ->orderBy('id');

        $this->applyFilters($query, $dateFrom, $dateTo, $userIds);
        $totalTargetRows = (clone $query)->count();

        if ($totalTargetRows === 0) {
            $this->info('No attendance rows need location backfill for selected filters.');
            return self::SUCCESS;
        }

        $summary = [
            'rows_scanned' => 0,
            'rows_with_updates' => 0,
            'checkin_assigned' => 0,
            'checkout_assigned' => 0,
            'checkin_unresolved' => 0,
            'checkout_unresolved' => 0,
            'checkin_assigned_by_single_fallback' => 0,
            'checkout_assigned_by_single_fallback' => 0,
            'updated_rows' => 0,
            'would_update_rows' => 0,
        ];

        $this->info("Active locations: {$activeLocations->count()}");
        $this->info("Target attendance rows: {$totalTargetRows}");

        foreach ($query->lazyById($chunk) as $attendance) {
            if ($limit > 0 && $summary['rows_scanned'] >= $limit) {
                break;
            }

            $summary['rows_scanned']++;
            $updatePayload = [];

            if ($attendance->lokasi_masuk_id === null
                && $attendance->latitude_masuk !== null
                && $attendance->longitude_masuk !== null) {
                $match = $this->matchLocation(
                    (float) $attendance->latitude_masuk,
                    (float) $attendance->longitude_masuk,
                    $activeLocations,
                    $allowNearest
                );

                if ($match !== null) {
                    $updatePayload['lokasi_masuk_id'] = $match['location_id'];
                    $summary['checkin_assigned']++;
                } else {
                    $summary['checkin_unresolved']++;
                }
            } elseif ($attendance->lokasi_masuk_id === null && $singleFallbackLocationId !== null) {
                $updatePayload['lokasi_masuk_id'] = $singleFallbackLocationId;
                $summary['checkin_assigned']++;
                $summary['checkin_assigned_by_single_fallback']++;
            }

            if ($attendance->lokasi_pulang_id === null
                && $attendance->latitude_pulang !== null
                && $attendance->longitude_pulang !== null) {
                $match = $this->matchLocation(
                    (float) $attendance->latitude_pulang,
                    (float) $attendance->longitude_pulang,
                    $activeLocations,
                    $allowNearest
                );

                if ($match !== null) {
                    $updatePayload['lokasi_pulang_id'] = $match['location_id'];
                    $summary['checkout_assigned']++;
                } else {
                    $summary['checkout_unresolved']++;
                }
            } elseif ($attendance->lokasi_pulang_id === null && $singleFallbackLocationId !== null) {
                $updatePayload['lokasi_pulang_id'] = $singleFallbackLocationId;
                $summary['checkout_assigned']++;
                $summary['checkout_assigned_by_single_fallback']++;
            }

            if ($updatePayload === []) {
                continue;
            }

            $summary['rows_with_updates']++;
            if ($execute) {
                Absensi::query()
                    ->whereKey($attendance->id)
                    ->update($updatePayload);
                $summary['updated_rows']++;
            } else {
                $summary['would_update_rows']++;
            }
        }

        $this->newLine();
        $this->info('Backfill summary:');
        $this->line('Rows scanned: ' . $summary['rows_scanned']);
        $this->line('Rows with updates: ' . $summary['rows_with_updates']);
        $this->line('Check-in assigned: ' . $summary['checkin_assigned']);
        $this->line('Check-out assigned: ' . $summary['checkout_assigned']);
        $this->line('Check-in assigned by single fallback: ' . $summary['checkin_assigned_by_single_fallback']);
        $this->line('Check-out assigned by single fallback: ' . $summary['checkout_assigned_by_single_fallback']);
        $this->line('Check-in unresolved: ' . $summary['checkin_unresolved']);
        $this->line('Check-out unresolved: ' . $summary['checkout_unresolved']);
        $this->line('Rows updated: ' . $summary['updated_rows']);
        $this->line('Rows that would update (dry-run): ' . $summary['would_update_rows']);

        return self::SUCCESS;
    }

    private function applyFilters(Builder $query, ?string $dateFrom, ?string $dateTo, Collection $userIds): void
    {
        if ($dateFrom) {
            $query->whereDate('tanggal', '>=', Carbon::parse($dateFrom)->toDateString());
        }

        if ($dateTo) {
            $query->whereDate('tanggal', '<=', Carbon::parse($dateTo)->toDateString());
        }

        if ($userIds->isNotEmpty()) {
            $query->whereIn('user_id', $userIds->all());
        }
    }

    /**
     * @return array{location_id:int,distance:float,within_radius:bool}|null
     */
    private function matchLocation(
        float $latitude,
        float $longitude,
        Collection $activeLocations,
        bool $allowNearest
    ): ?array {
        $nearestLocation = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($activeLocations as $location) {
            $evaluation = $location->evaluateCoordinate($latitude, $longitude);
            $distance = (float) ($evaluation['distance_to_area'] ?? PHP_FLOAT_MAX);

            if ($evaluation['inside'] ?? false) {
                return [
                    'location_id' => (int) $location->id,
                    'distance' => $distance,
                    'within_radius' => true,
                ];
            }

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestLocation = $location;
            }
        }

        if ($allowNearest && $nearestLocation) {
            return [
                'location_id' => (int) $nearestLocation->id,
                'distance' => $nearestDistance,
                'within_radius' => false,
            ];
        }

        return null;
    }
}
