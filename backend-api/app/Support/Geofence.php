<?php

namespace App\Support;

class Geofence
{
    public const TYPE_CIRCLE = 'circle';
    public const TYPE_POLYGON = 'polygon';

    /**
     * Normalize geofence type with safe fallback.
     */
    public static function normalizeType(?string $type): string
    {
        $normalized = strtolower(trim((string) $type));

        return in_array($normalized, [self::TYPE_CIRCLE, self::TYPE_POLYGON], true)
            ? $normalized
            : self::TYPE_CIRCLE;
    }

    /**
     * Normalize polygon GeoJSON into a strict Polygon payload.
     *
     * @param mixed $value
     * @return array<string, mixed>|null
     */
    public static function normalizeGeoJson($value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return null;
            }
            $value = $decoded;
        }

        if (!is_array($value)) {
            return null;
        }

        if (isset($value['type']) && strtolower((string) $value['type']) === 'feature') {
            $value = $value['geometry'] ?? null;
        }

        if (!is_array($value)) {
            return null;
        }

        if (strtolower((string) ($value['type'] ?? '')) !== 'polygon') {
            return null;
        }

        $rings = $value['coordinates'] ?? null;
        if (!is_array($rings) || empty($rings) || !is_array($rings[0] ?? null)) {
            return null;
        }

        $ring = [];
        foreach ($rings[0] as $point) {
            if (!is_array($point) || count($point) < 2 || !is_numeric($point[0]) || !is_numeric($point[1])) {
                return null;
            }

            $ring[] = [
                (float) $point[0],
                (float) $point[1],
            ];
        }

        $ring = self::closeRing($ring);
        if (count($ring) < 4) {
            return null;
        }

        return [
            'type' => 'Polygon',
            'coordinates' => [$ring],
        ];
    }

    /**
     * @param array<string, mixed> $location
     * @return array<string, mixed>
     */
    public static function evaluate(array $location, float $latitude, float $longitude): array
    {
        $type = self::normalizeType((string) ($location['geofence_type'] ?? null));
        $centerLatitude = isset($location['latitude']) ? (float) $location['latitude'] : null;
        $centerLongitude = isset($location['longitude']) ? (float) $location['longitude'] : null;

        if ($type === self::TYPE_POLYGON) {
            $geoJson = self::normalizeGeoJson($location['geofence_geojson'] ?? null);
            if ($geoJson !== null) {
                $ring = self::extractRing($geoJson);
                $inside = self::pointInPolygon($latitude, $longitude, $ring);
                $distanceToBoundary = self::distanceToPolygonBoundary($latitude, $longitude, $ring);
                $centroid = self::calculatePolygonCentroid($ring);

                return [
                    'geofence_type' => self::TYPE_POLYGON,
                    'inside' => $inside,
                    'distance_to_area' => round($inside ? 0.0 : $distanceToBoundary, 2),
                    'distance_to_boundary' => round($distanceToBoundary, 2),
                    'distance_to_center' => $centroid !== null
                        ? round(self::haversineDistance($latitude, $longitude, $centroid['latitude'], $centroid['longitude']), 2)
                        : null,
                    'effective_radius' => null,
                    'geometry_center' => $centroid,
                ];
            }
        }

        $radius = max(0.0, (float) ($location['radius'] ?? 0));
        $distanceToCenter = (
            $centerLatitude !== null && $centerLongitude !== null
        )
            ? self::haversineDistance($latitude, $longitude, $centerLatitude, $centerLongitude)
            : PHP_FLOAT_MAX;

        return [
            'geofence_type' => self::TYPE_CIRCLE,
            'inside' => $distanceToCenter <= $radius,
            'distance_to_area' => round(max(0.0, $distanceToCenter - $radius), 2),
            'distance_to_boundary' => round(abs($radius - $distanceToCenter), 2),
            'distance_to_center' => round($distanceToCenter, 2),
            'effective_radius' => round($radius, 2),
            'geometry_center' => ($centerLatitude !== null && $centerLongitude !== null)
                ? [
                    'latitude' => $centerLatitude,
                    'longitude' => $centerLongitude,
                ]
                : null,
        ];
    }

    /**
     * @param array<string, mixed>|null $geoJson
     * @return array<int, array{0: float, 1: float}>
     */
    public static function extractRing(?array $geoJson): array
    {
        if (!is_array($geoJson)) {
            return [];
        }

        $normalized = self::normalizeGeoJson($geoJson);
        if ($normalized === null) {
            return [];
        }

        /** @var array<int, array{0: float, 1: float}> $ring */
        $ring = $normalized['coordinates'][0];

        return $ring;
    }

    /**
     * Validate the geofence payload for store/update flow.
     *
     * @param mixed $geoJson
     * @return array{valid: bool, message: string|null, normalized: array<string, mixed>|null}
     */
    public static function validatePayload(string $type, $geoJson): array
    {
        $normalizedType = self::normalizeType($type);
        if ($normalizedType === self::TYPE_CIRCLE) {
            return [
                'valid' => true,
                'message' => null,
                'normalized' => null,
            ];
        }

        $normalized = self::normalizeGeoJson($geoJson);
        if ($normalized === null) {
            return [
                'valid' => false,
                'message' => 'Polygon geofence harus berupa GeoJSON Polygon yang valid.',
                'normalized' => null,
            ];
        }

        return [
            'valid' => true,
            'message' => null,
            'normalized' => $normalized,
        ];
    }

    public static function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * @param array<int, array{0: float, 1: float}> $ring
     */
    private static function pointInPolygon(float $latitude, float $longitude, array $ring): bool
    {
        $inside = false;
        $count = count($ring);

        if ($count < 4) {
            return false;
        }

        $x = $longitude;
        $y = $latitude;
        $j = $count - 1;

        for ($i = 0; $i < $count; $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];

            $intersects = (($yi > $y) !== ($yj > $y))
                && ($x < (($xj - $xi) * ($y - $yi) / (($yj - $yi) ?: 0.0000000001)) + $xi);

            if ($intersects) {
                $inside = !$inside;
            }

            $j = $i;
        }

        return $inside;
    }

    /**
     * @param array<int, array{0: float, 1: float}> $ring
     */
    private static function distanceToPolygonBoundary(float $latitude, float $longitude, array $ring): float
    {
        $count = count($ring);
        if ($count < 2) {
            return PHP_FLOAT_MAX;
        }

        $minDistance = PHP_FLOAT_MAX;

        for ($i = 0; $i < $count - 1; $i++) {
            $start = self::projectToMeters($ring[$i][1], $ring[$i][0], $latitude, $longitude);
            $end = self::projectToMeters($ring[$i + 1][1], $ring[$i + 1][0], $latitude, $longitude);
            $distance = self::distancePointToSegment(0.0, 0.0, $start['x'], $start['y'], $end['x'], $end['y']);

            if ($distance < $minDistance) {
                $minDistance = $distance;
            }
        }

        return $minDistance;
    }

    /**
     * @param array<int, array{0: float, 1: float}> $ring
     * @return array{latitude: float, longitude: float}|null
     */
    private static function calculatePolygonCentroid(array $ring): ?array
    {
        $uniquePoints = $ring;
        if (count($uniquePoints) > 1 && $uniquePoints[0] === $uniquePoints[count($uniquePoints) - 1]) {
            array_pop($uniquePoints);
        }

        if (empty($uniquePoints)) {
            return null;
        }

        $latitudes = array_column(array_map(function (array $point): array {
            return [
                'longitude' => $point[0],
                'latitude' => $point[1],
            ];
        }, $uniquePoints), 'latitude');

        $longitudes = array_column(array_map(function (array $point): array {
            return [
                'longitude' => $point[0],
                'latitude' => $point[1],
            ];
        }, $uniquePoints), 'longitude');

        return [
            'latitude' => array_sum($latitudes) / count($latitudes),
            'longitude' => array_sum($longitudes) / count($longitudes),
        ];
    }

    /**
     * @param array<int, array{0: float, 1: float}> $ring
     * @return array<int, array{0: float, 1: float}>
     */
    private static function closeRing(array $ring): array
    {
        if (count($ring) < 3) {
            return $ring;
        }

        $first = $ring[0];
        $last = $ring[count($ring) - 1];

        if ($first !== $last) {
            $ring[] = $first;
        }

        return $ring;
    }

    /**
     * @return array{x: float, y: float}
     */
    private static function projectToMeters(float $latitude, float $longitude, float $referenceLatitude, float $referenceLongitude): array
    {
        $earthRadius = 6371000.0;
        $x = deg2rad($longitude - $referenceLongitude) * $earthRadius * cos(deg2rad($referenceLatitude));
        $y = deg2rad($latitude - $referenceLatitude) * $earthRadius;

        return [
            'x' => $x,
            'y' => $y,
        ];
    }

    private static function distancePointToSegment(
        float $px,
        float $py,
        float $ax,
        float $ay,
        float $bx,
        float $by
    ): float {
        $dx = $bx - $ax;
        $dy = $by - $ay;

        if ($dx == 0.0 && $dy == 0.0) {
            return sqrt(($px - $ax) ** 2 + ($py - $ay) ** 2);
        }

        $t = (($px - $ax) * $dx + ($py - $ay) * $dy) / (($dx * $dx) + ($dy * $dy));
        $t = max(0.0, min(1.0, $t));

        $projectionX = $ax + ($t * $dx);
        $projectionY = $ay + ($t * $dy);

        return sqrt(($px - $projectionX) ** 2 + ($py - $projectionY) ** 2);
    }
}
