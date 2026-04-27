<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Absensi extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang digunakan oleh model
     *
     * @var string
     */
    protected $table = 'absensi';

    /**
     * Atribut yang dapat diisi secara massal
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'kelas_id',
        'tanggal',
        'jam_masuk',
        'jam_pulang',
        'status',
        'metode_absensi',
        'latitude_masuk',
        'longitude_masuk',
        'foto_masuk',
        'lokasi_masuk_id',
        'latitude_pulang',
        'longitude_pulang',
        'foto_pulang',
        'lokasi_pulang_id',
        'qr_code_masuk',
        'qr_code_pulang',
        'keterangan',
        'device_info',
        'ip_address',
        'is_verified',
        'verification_status',
        'face_score_checkin',
        'face_score_checkout',
        'verified_at',
        'verified_by',
        'izin_id',
        'attendance_setting_id',
        'settings_snapshot',
        'is_manual',
        'gps_accuracy_masuk',
        'gps_accuracy_pulang',
        'validation_status',
        'risk_level',
        'risk_score',
        'fraud_flags_count',
        'fraud_decision_reason',
        'fraud_last_assessed_at',
    ];

    /**
     * Atribut yang harus di-cast
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            // Stored as SQL TIME; keep serialization as time-only to avoid timezone shift (e.g. 15:00 -> 08:00Z).
            'jam_masuk' => 'datetime:H:i:s',
            'jam_pulang' => 'datetime:H:i:s',
            'latitude_masuk' => 'decimal:8',
            'longitude_masuk' => 'decimal:8',
            'latitude_pulang' => 'decimal:8',
            'longitude_pulang' => 'decimal:8',
            'device_info' => 'json',
            'is_verified' => 'boolean',
            'verification_status' => 'string',
            'face_score_checkin' => 'decimal:4',
        'face_score_checkout' => 'decimal:4',
        'verified_at' => 'datetime',
        'settings_snapshot' => 'array',
        'is_manual' => 'boolean',
        'gps_accuracy_masuk' => 'decimal:2',
        'gps_accuracy_pulang' => 'decimal:2',
        'validation_status' => 'string',
        'risk_level' => 'string',
        'risk_score' => 'integer',
        'fraud_flags_count' => 'integer',
        'fraud_last_assessed_at' => 'datetime',
        ];
    }

    /**
     * Accessor untuk jam masuk dalam format H:i
     */
    public function getJamMasukFormatAttribute()
    {
        if (!$this->jam_masuk) {
            return null;
        }

        try {
            if ($this->jam_masuk instanceof Carbon) {
                return $this->jam_masuk->format('H:i');
            }
            return Carbon::parse($this->jam_masuk)->format('H:i');
        } catch (\Exception $e) {
            return $this->jam_masuk;
        }
    }

    /**
     * Accessor untuk jam pulang dalam format H:i
     */
    public function getJamPulangFormatAttribute()
    {
        if (!$this->jam_pulang) {
            return null;
        }

        try {
            if ($this->jam_pulang instanceof Carbon) {
                return $this->jam_pulang->format('H:i');
            }
            return Carbon::parse($this->jam_pulang)->format('H:i');
        } catch (\Exception $e) {
            return $this->jam_pulang;
        }
    }

    /**
     * Accessor untuk durasi kerja
     */
    public function getDurasiKerjaAttribute()
    {
        $durasiMinutes = $this->getSchoolDurationMinutes();
        if ($durasiMinutes === null) {
            return null;
        }

        $hours = floor($durasiMinutes / 60);
        $minutes = $durasiMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    /**
     * Relasi belongs-to dengan User
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi belongs-to dengan LokasiGps untuk lokasi masuk
     */
    public function lokasiMasuk()
    {
        return $this->belongsTo(LokasiGps::class, 'lokasi_masuk_id');
    }

    /**
     * Relasi belongs-to dengan LokasiGps untuk lokasi pulang
     */
    public function lokasiPulang()
    {
        return $this->belongsTo(LokasiGps::class, 'lokasi_pulang_id');
    }

    /**
     * Relasi belongs-to dengan User sebagai verifier
     */
    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Relasi belongs-to dengan AttendanceSchema
     */
    public function attendanceSchema()
    {
        return $this->belongsTo(AttendanceSchema::class, 'attendance_setting_id');
    }

    /**
     * Relasi belongs-to dengan Kelas
     */
    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    /**
     * Relasi belongs-to dengan Izin
     */
    public function izin()
    {
        return $this->belongsTo(Izin::class, 'izin_id');
    }

    /**
     * Relasi has-many dengan AttendanceAuditLog
     */
    public function auditLogs()
    {
        return $this->hasMany(AttendanceAuditLog::class, 'attendance_id');
    }

    /**
     * Relasi has-many dengan AttendanceFraudAssessment
     */
    public function fraudAssessments()
    {
        return $this->hasMany(AttendanceFraudAssessment::class, 'attendance_id');
    }

    /**
     * Relasi has-many dengan AttendanceFaceVerification
     */
    public function faceVerifications()
    {
        return $this->hasMany(AttendanceFaceVerification::class, 'absensi_id');
    }

    /**
     * Scope untuk filter berdasarkan tanggal
     */
    public function scopeTanggal($query, $tanggal)
    {
        return $query->whereDate('tanggal', $tanggal);
    }

    /**
     * Scope untuk filter berdasarkan bulan
     */
    public function scopeBulan($query, $bulan, $tahun = null)
    {
        $tahun = $tahun ?? date('Y');
        return $query->whereMonth('tanggal', $bulan)
            ->whereYear('tanggal', $tahun);
    }

    /**
     * Scope untuk filter berdasarkan tahun
     */
    public function scopeTahun($query, $tahun)
    {
        return $query->whereYear('tanggal', $tahun);
    }

    /**
     * Scope untuk filter berdasarkan status absen
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter yang terlambat
     */
    public function scopeTerlambat($query)
    {
        return $query->where('status', 'terlambat');
    }

    /**
     * Scope untuk filter yang sudah absen masuk
     */
    public function scopeSudahMasuk($query)
    {
        return $query->whereNotNull('jam_masuk');
    }

    /**
     * Scope untuk filter yang sudah absen keluar
     */
    public function scopeSudahKeluar($query)
    {
        return $query->whereNotNull('jam_pulang');
    }

    /**
     * Scope untuk filter yang belum absen keluar
     */
    public function scopeBelumKeluar($query)
    {
        return $query->whereNotNull('jam_masuk')
            ->whereNull('jam_pulang');
    }

    /**
     * Scope untuk filter manual attendance
     */
    public function scopeManual($query)
    {
        return $query->where('is_manual', true);
    }

    /**
     * Scope untuk filter non-manual attendance
     */
    public function scopeAutomatic($query)
    {
        return $query->where('is_manual', false);
    }

    /**
     * Accessor untuk durasi kerja dalam format jam:menit
     */
    public function getDurasiKerjaFormatAttribute()
    {
        $durasiMinutes = $this->getSchoolDurationMinutes();
        if ($durasiMinutes === null) {
            return '-';
        }

        $hours = floor($durasiMinutes / 60);
        $minutes = $durasiMinutes % 60;

        return sprintf('%02d:%02d', $hours, $minutes);
    }

    private function getSchoolDurationMinutes(): ?int
    {
        $rawMasuk = $this->getRawOriginal('jam_masuk');
        $rawPulang = $this->getRawOriginal('jam_pulang');

        if (!$rawMasuk || !$rawPulang) {
            return null;
        }

        $masukMinutes = $this->timeStringToMinutes((string) $rawMasuk);
        $pulangMinutes = $this->timeStringToMinutes((string) $rawPulang);

        if ($masukMinutes === null || $pulangMinutes === null || $pulangMinutes < $masukMinutes) {
            return null;
        }

        return $pulangMinutes - $masukMinutes;
    }

    private function timeStringToMinutes(string $value): ?int
    {
        $parts = explode(':', trim($value));
        if (count($parts) < 2) {
            return null;
        }

        $hours = is_numeric($parts[0]) ? (int) $parts[0] : null;
        $minutes = is_numeric($parts[1]) ? (int) $parts[1] : null;

        if ($hours === null || $minutes === null) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    /**
     * Accessor untuk status keterlambatan
     */
    public function getStatusTerlambatAttribute()
    {
        return $this->status === 'terlambat' ? 'Terlambat' : 'Tepat Waktu';
    }

    /**
     * Accessor untuk foto masuk URL
     */
    public function getFotoMasukUrlAttribute()
    {
        return $this->foto_masuk ? asset('storage/' . $this->foto_masuk) : null;
    }

    /**
     * Accessor untuk foto keluar URL
     */
    public function getFotoKeluarUrlAttribute()
    {
        return $this->foto_pulang ? asset('storage/' . $this->foto_pulang) : null;
    }

    /**
     * Compatibility accessor for legacy callers using `jam_keluar`.
     */
    public function getJamKeluarAttribute()
    {
        return $this->jam_pulang;
    }

    /**
     * Compatibility mutator for legacy callers that still set `jam_keluar`.
     */
    public function setJamKeluarAttribute($value)
    {
        $this->attributes['jam_pulang'] = $value;
    }

    /**
     * Cek apakah sudah absen masuk hari ini
     */
    public static function sudahAbsenMasukHariIni($userId)
    {
        return self::where('user_id', $userId)
            ->whereDate('tanggal', today())
            ->whereNotNull('jam_masuk')
            ->exists();
    }

    /**
     * Cek apakah sudah absen keluar hari ini
     */
    public static function sudahAbsenKeluarHariIni($userId)
    {
        return self::where('user_id', $userId)
            ->whereDate('tanggal', today())
            ->whereNotNull('jam_pulang')
            ->exists();
    }

    /**
     * Mendapatkan absensi hari ini untuk user tertentu
     */
    public static function absensiHariIni($userId)
    {
        return self::where('user_id', $userId)
            ->whereDate('tanggal', today())
            ->first();
    }

    /**
     * Menghitung total hari kerja dalam periode
     */
    public static function totalHariKerja($userId, $startDate, $endDate)
    {
        return self::where('user_id', $userId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereIn('status', ['hadir', 'terlambat'])
            ->count();
    }

    /**
     * Menghitung total keterlambatan dalam periode
     */
    public static function totalTerlambat($userId, $startDate, $endDate)
    {
        return self::where('user_id', $userId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->where('status', 'terlambat')
            ->count();
    }

    /**
     * Menghitung rata-rata durasi kerja dalam periode
     */
    public static function rataRataDurasiKerja($userId, $startDate, $endDate)
    {
        $records = self::where('user_id', $userId)
            ->whereBetween('tanggal', [$startDate, $endDate])
            ->whereNotNull('jam_masuk')
            ->whereNotNull('jam_pulang')
            ->get(['jam_masuk', 'jam_pulang']);

        if ($records->isEmpty()) {
            return 0;
        }

        return $records->avg(function ($record) {
            try {
                $masuk = $record->jam_masuk instanceof Carbon ? $record->jam_masuk : Carbon::parse($record->jam_masuk);
                $pulang = $record->jam_pulang instanceof Carbon ? $record->jam_pulang : Carbon::parse($record->jam_pulang);
                return $pulang->diffInMinutes($masuk);
            } catch (\Exception $e) {
                return 0;
            }
        });
    }
}
