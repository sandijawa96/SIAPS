<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class LiveTracking extends Model
{
    use HasFactory;

    protected $table = 'live_tracking';

    protected $fillable = [
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'speed',
        'heading',
        'location_id',
        'location_name',
        'device_source',
        'gps_quality_status',
        'is_in_school_area',
        'device_info',
        'ip_address',
        'tracked_at'
    ];

    protected $casts = [
        'accuracy' => 'float',
        'speed' => 'float',
        'heading' => 'float',
        'location_id' => 'integer',
        'device_info' => 'array',
        'tracked_at' => 'datetime',
        'is_in_school_area' => 'boolean'
    ];

    /**
     * Relasi dengan User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function location()
    {
        return $this->belongsTo(LokasiGps::class, 'location_id');
    }

    /**
     * Scope untuk data hari ini
     */
    public function scopeToday($query)
    {
        return $query->whereDate('tracked_at', today());
    }

    /**
     * Scope untuk data dalam jam sekolah
     */
    public function scopeSchoolHours($query, $jamMulai = '07:00', $jamSelesai = '15:00')
    {
        return $query->whereTime('tracked_at', '>=', $jamMulai)
            ->whereTime('tracked_at', '<=', $jamSelesai);
    }

    /**
     * Scope untuk siswa yang di dalam area sekolah
     */
    public function scopeInSchoolArea($query)
    {
        return $query->where('is_in_school_area', true);
    }

    /**
     * Scope untuk siswa yang di luar area sekolah
     */
    public function scopeOutsideSchoolArea($query)
    {
        return $query->where('is_in_school_area', false);
    }

    /**
     * Get latest tracking untuk user
     */
    public static function getLatestForUser($userId)
    {
        return static::where('user_id', $userId)
            ->latest('tracked_at')
            ->first();
    }

    /**
     * Get tracking dalam radius tertentu
     */
    public static function getWithinRadius($centerLat, $centerLng, $radiusMeters)
    {
        // Menggunakan Haversine formula untuk menghitung jarak
        $earthRadius = 6371000; // Earth radius in meters

        return static::selectRaw("
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
        ", [$centerLat, $centerLng, $centerLat])
            ->havingRaw('distance <= ?', [$radiusMeters])
            ->orderBy('distance');
    }

    /**
     * Cleanup old tracking data (older than specified days) in batches to reduce lock duration.
     */
    public static function cleanup($daysToKeep = 30, int $batchSize = 5000)
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);
        $batchSize = max(100, $batchSize);
        $deleted = 0;

        do {
            $ids = static::query()
                ->where('tracked_at', '<', $cutoffDate)
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += static::query()
                ->whereIn('id', $ids->all())
                ->delete();
        } while (true);

        return $deleted;
    }

    /**
     * Get tracking statistics for user
     */
    public static function getStatsForUser($userId, $date = null)
    {
        $query = static::where('user_id', $userId);

        if ($date) {
            $query->whereDate('tracked_at', $date);
        } else {
            $query->today();
        }

        $total = $query->count();
        $inSchool = $query->clone()->inSchoolArea()->count();
        $outSchool = $total - $inSchool;

        return [
            'total_points' => $total,
            'in_school_area' => $inSchool,
            'outside_school_area' => $outSchool,
            'percentage_in_school' => $total > 0 ? round(($inSchool / $total) * 100, 2) : 0
        ];
    }
}
