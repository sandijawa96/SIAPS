<?php

namespace App\Services;

use App\Models\LokasiGps;
use App\Support\Geofence;

class LiveTrackingContextService
{
    public const DEVICE_SOURCE_WEB = 'web';
    public const DEVICE_SOURCE_MOBILE = 'mobile';
    public const DEVICE_SOURCE_UNKNOWN = 'unknown';

    public const GPS_QUALITY_GOOD = 'good';
    public const GPS_QUALITY_MODERATE = 'moderate';
    public const GPS_QUALITY_POOR = 'poor';
    public const GPS_QUALITY_UNKNOWN = 'unknown';

    /**
     * @return array<string, mixed>
     */
    public function resolve(float $latitude, float $longitude, ?float $accuracy = null): array
    {
        $locations = LokasiGps::query()
            ->where('is_active', true)
            ->get([
                'id',
                'nama_lokasi',
                'latitude',
                'longitude',
                'radius',
                'geofence_type',
                'geofence_geojson',
            ]);

        $isInSchoolArea = false;
        $currentLocation = null;
        $nearestLocation = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($locations as $location) {
            $evaluation = $location->evaluateCoordinate($latitude, $longitude);
            $distance = (float) ($evaluation['distance_to_area'] ?? PHP_FLOAT_MAX);

            $locationPayload = [
                'id' => (int) $location->id,
                'nama_lokasi' => $location->nama_lokasi,
                'distance' => round($distance, 2),
                'distance_to_boundary' => round((float) ($evaluation['distance_to_boundary'] ?? $distance), 2),
                'distance_to_center' => isset($evaluation['distance_to_center'])
                    ? round((float) $evaluation['distance_to_center'], 2)
                    : null,
                'geofence_type' => $location->getNormalizedGeofenceType(),
            ];

            if (($evaluation['inside'] ?? false) === true) {
                if (!$isInSchoolArea || $distance < (float) ($currentLocation['distance'] ?? PHP_FLOAT_MAX)) {
                    $isInSchoolArea = true;
                    $currentLocation = $locationPayload;
                }
            }

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestLocation = $locationPayload;
            }
        }

        return [
            'is_in_school_area' => $isInSchoolArea,
            'within_gps_area' => $isInSchoolArea,
            'location_id' => $currentLocation['id'] ?? null,
            'location_name' => $currentLocation['nama_lokasi'] ?? null,
            'current_location' => $currentLocation,
            'nearest_location' => $isInSchoolArea ? null : $nearestLocation,
            'distance_to_nearest' => !$isInSchoolArea && is_finite($nearestDistance)
                ? round($nearestDistance, 2)
                : null,
            'gps_quality_status' => $this->resolveGpsQualityStatus($accuracy),
        ];
    }

    public function resolveGpsQualityStatus(?float $accuracy): string
    {
        if ($accuracy === null || !is_finite($accuracy) || $accuracy < 0) {
            return self::GPS_QUALITY_UNKNOWN;
        }

        $goodMax = (float) config('attendance.live_tracking.gps_quality.good_max_accuracy', 20);
        $moderateMax = (float) config('attendance.live_tracking.gps_quality.moderate_max_accuracy', 50);

        if ($accuracy <= $goodMax) {
            return self::GPS_QUALITY_GOOD;
        }

        if ($accuracy <= $moderateMax) {
            return self::GPS_QUALITY_MODERATE;
        }

        return self::GPS_QUALITY_POOR;
    }

    public function normalizeDeviceSource(?string $source): string
    {
        $normalized = strtolower(trim((string) $source));

        return match ($normalized) {
            self::DEVICE_SOURCE_WEB => self::DEVICE_SOURCE_WEB,
            self::DEVICE_SOURCE_MOBILE => self::DEVICE_SOURCE_MOBILE,
            default => self::DEVICE_SOURCE_UNKNOWN,
        };
    }
}
