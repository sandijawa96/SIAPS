<?php

namespace App\Models;

use App\Support\Geofence;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LokasiGps extends Model
{
    use HasFactory;

    protected $table = 'lokasi_gps';

    /**
     * Atribut yang dapat diisi secara massal
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_lokasi',
        'deskripsi',
        'latitude',
        'longitude',
        'radius',
        'geofence_type',
        'geofence_geojson',
        'is_active',
        'warna_marker',
        'roles',
        'waktu_mulai',
        'waktu_selesai',
        'hari_aktif'
    ];

    /**
     * Atribut yang harus di-cast
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
            'radius' => 'integer',
            'geofence_geojson' => 'array',
            'is_active' => 'boolean',
            'geofence_type' => 'string',
            'roles' => 'string',
            'hari_aktif' => 'string',
            'waktu_mulai' => 'string',
            'waktu_selesai' => 'string',
            'warna_marker' => 'string'
        ];
    }

    /**
     * Scope untuk filter lokasi aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Mendapatkan lokasi default (utama)
     */
    public static function getDefault()
    {
        return self::active()->first();
    }

    /**
     * Menghitung jarak antara dua titik koordinat menggunakan formula Haversine
     * 
     * @param float $lat1 Latitude titik 1
     * @param float $lon1 Longitude titik 1
     * @param float $lat2 Latitude titik 2
     * @param float $lon2 Longitude titik 2
     * @return float Jarak dalam meter
     */
    public static function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        return Geofence::haversineDistance((float) $lat1, (float) $lon1, (float) $lat2, (float) $lon2);
    }

    public function getGeofenceTypeAttribute($value): string
    {
        return Geofence::normalizeType(is_string($value) ? $value : null);
    }

    public function getNormalizedGeofenceType(): string
    {
        return Geofence::normalizeType($this->geofence_type);
    }

    /**
     * @return array<string, mixed>
     */
    public function evaluateCoordinate($latitude, $longitude): array
    {
        return Geofence::evaluate([
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius' => $this->radius,
            'geofence_type' => $this->geofence_type,
            'geofence_geojson' => $this->geofence_geojson,
        ], (float) $latitude, (float) $longitude);
    }

    /**
     * Cek apakah koordinat berada dalam radius lokasi ini
     * 
     * @param float $latitude
     * @param float $longitude
     * @return bool
     */
    public function isWithinRadius($latitude, $longitude)
    {
        return $this->containsCoordinate($latitude, $longitude);
    }

    public function containsCoordinate($latitude, $longitude): bool
    {
        return (bool) ($this->evaluateCoordinate($latitude, $longitude)['inside'] ?? false);
    }

    /**
     * Mendapatkan jarak dari koordinat tertentu ke lokasi ini
     * 
     * @param float $latitude
     * @param float $longitude
     * @return float Jarak dalam meter
     */
    public function getDistanceFrom($latitude, $longitude)
    {
        $evaluation = $this->evaluateCoordinate($latitude, $longitude);

        return (float) ($evaluation['distance_to_area'] ?? 0.0);
    }

    public function getDistanceToBoundary($latitude, $longitude): float
    {
        $evaluation = $this->evaluateCoordinate($latitude, $longitude);

        return (float) ($evaluation['distance_to_boundary'] ?? 0.0);
    }

    /**
     * Cek apakah user berada dalam area absensi yang valid
     * 
     * @param float $userLatitude
     * @param float $userLongitude
     * @return array
     */
    public static function checkValidLocation($userLatitude, $userLongitude)
    {
        $validLocations = self::active()->get();
        $nearestLocation = null;
        $nearestDistance = PHP_FLOAT_MAX;
        $nearestEvaluation = null;

        foreach ($validLocations as $location) {
            $evaluation = $location->evaluateCoordinate($userLatitude, $userLongitude);
            if ($evaluation['inside'] ?? false) {
                return [
                    'valid' => true,
                    'location' => $location,
                    'distance' => (float) ($evaluation['distance_to_area'] ?? 0.0),
                    'distance_to_boundary' => (float) ($evaluation['distance_to_boundary'] ?? 0.0),
                    'distance_to_center' => $evaluation['distance_to_center'] ?? null,
                    'geofence_type' => $location->getNormalizedGeofenceType(),
                ];
            }

            $distance = (float) ($evaluation['distance_to_area'] ?? PHP_FLOAT_MAX);
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestLocation = $location;
                $nearestEvaluation = $evaluation;
            }
        }

        return [
            'valid' => false,
            'nearest_location' => $nearestLocation,
            'distance' => $nearestDistance,
            'distance_to_boundary' => (float) ($nearestEvaluation['distance_to_boundary'] ?? $nearestDistance),
            'distance_to_center' => $nearestEvaluation['distance_to_center'] ?? null,
            'geofence_type' => $nearestLocation?->getNormalizedGeofenceType(),
        ];
    }

    /**
     * Mendapatkan URL Google Maps untuk lokasi ini
     */
    public function getGoogleMapsUrlAttribute()
    {
        return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
    }

    /**
     * Mendapatkan koordinat dalam format string
     */
    public function getKoordinatAttribute()
    {
        return "{$this->latitude}, {$this->longitude}";
    }

    /**
     * Mendapatkan radius dalam format yang mudah dibaca
     */
    public function getRadiusFormatAttribute()
    {
        if ($this->getNormalizedGeofenceType() === Geofence::TYPE_POLYGON) {
            return 'Polygon';
        }

        if ($this->radius >= 1000) {
            return round($this->radius / 1000, 1) . ' km';
        }

        return $this->radius . ' m';
    }

    /**
     * Scope untuk mencari lokasi berdasarkan jarak dari koordinat tertentu
     */
    public function scopeNearby($query, $latitude, $longitude, $maxDistance = 10000)
    {
        // Menggunakan formula Haversine dalam SQL untuk performa yang lebih baik
        $earthRadius = 6371000; // dalam meter

        return $query->selectRaw("
            *,
            (
                {$earthRadius} * acos(
                    cos(radians(?)) * 
                    cos(radians(latitude)) * 
                    cos(radians(longitude) - radians(?)) + 
                    sin(radians(?)) * 
                    sin(radians(latitude))
                )
            ) AS distance
        ", [$latitude, $longitude, $latitude])
            ->having('distance', '<=', $maxDistance)
            ->orderBy('distance');
    }

    /**
     * Validasi koordinat GPS
     */
    public static function validateCoordinates($latitude, $longitude)
    {
        return $latitude >= -90 && $latitude <= 90 &&
            $longitude >= -180 && $longitude <= 180;
    }

    /**
     * Mendapatkan bounding box untuk area tertentu
     */
    public function getBoundingBox()
    {
        if ($this->getNormalizedGeofenceType() === Geofence::TYPE_POLYGON) {
            $ring = Geofence::extractRing($this->geofence_geojson);
            if (!empty($ring)) {
                $latitudes = array_column(array_map(function (array $point): array {
                    return [
                        'latitude' => $point[1],
                        'longitude' => $point[0],
                    ];
                }, $ring), 'latitude');

                $longitudes = array_column(array_map(function (array $point): array {
                    return [
                        'latitude' => $point[1],
                        'longitude' => $point[0],
                    ];
                }, $ring), 'longitude');

                return [
                    'north' => max($latitudes),
                    'south' => min($latitudes),
                    'east' => max($longitudes),
                    'west' => min($longitudes),
                ];
            }
        }

        $earthRadius = 6371000; // meter
        $radiusInDegrees = $this->radius / $earthRadius * (180 / pi());

        return [
            'north' => $this->latitude + $radiusInDegrees,
            'south' => $this->latitude - $radiusInDegrees,
            'east' => $this->longitude + $radiusInDegrees / cos(deg2rad($this->latitude)),
            'west' => $this->longitude - $radiusInDegrees / cos(deg2rad($this->latitude))
        ];
    }
}
