<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PeriodeAkademik extends Model
{
    use HasFactory;

    protected $table = 'periode_akademik';

    /**
     * Jenis periode constants
     */
    const JENIS_PEMBELAJARAN = 'pembelajaran';
    const JENIS_UJIAN = 'ujian';
    const JENIS_LIBUR = 'libur';
    const JENIS_ORIENTASI = 'orientasi';

    /**
     * Semester constants
     */
    const SEMESTER_GANJIL = 'ganjil';
    const SEMESTER_GENAP = 'genap';
    const SEMESTER_BOTH = 'both';

    protected $fillable = [
        'tahun_ajaran_id',
        'nama',
        'jenis',
        'tanggal_mulai',
        'tanggal_selesai',
        'semester',
        'is_active',
        'keterangan',
        'metadata'
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
            'metadata' => 'array'
        ];
    }

    /**
     * Relasi dengan TahunAjaran
     */
    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * Relasi dengan EventAkademik
     */
    public function eventAkademik()
    {
        return $this->hasMany(EventAkademik::class);
    }

    /**
     * Scope untuk filter berdasarkan jenis
     */
    public function scopeByJenis($query, $jenis)
    {
        return $query->where('jenis', $jenis);
    }

    /**
     * Scope untuk filter berdasarkan semester
     */
    public function scopeBySemester($query, $semester)
    {
        return $query->where('semester', $semester);
    }

    /**
     * Scope untuk periode aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk periode yang sedang berjalan
     */
    public function scopeBerjalan($query)
    {
        $today = Carbon::today();
        return $query->where('tanggal_mulai', '<=', $today)
            ->where('tanggal_selesai', '>=', $today)
            ->where('is_active', true);
    }

    /**
     * Scope untuk periode pembelajaran
     */
    public function scopePembelajaran($query)
    {
        return $query->where('jenis', self::JENIS_PEMBELAJARAN);
    }

    /**
     * Scope untuk periode ujian
     */
    public function scopeUjian($query)
    {
        return $query->where('jenis', self::JENIS_UJIAN);
    }

    /**
     * Scope untuk periode libur
     */
    public function scopeLibur($query)
    {
        return $query->where('jenis', self::JENIS_LIBUR);
    }

    /**
     * Cek apakah periode sedang berjalan
     */
    public function isBerjalan()
    {
        $today = Carbon::today();
        return $this->is_active &&
            $this->tanggal_mulai <= $today &&
            $this->tanggal_selesai >= $today;
    }

    /**
     * Cek apakah periode sudah selesai
     */
    public function isSelesai()
    {
        return Carbon::today() > $this->tanggal_selesai;
    }

    /**
     * Cek apakah periode belum dimulai
     */
    public function isBelumMulai()
    {
        return Carbon::today() < $this->tanggal_mulai;
    }

    /**
     * Mendapatkan status display
     */
    public function getStatusDisplayAttribute()
    {
        if ($this->isBerjalan()) {
            return 'Sedang Berjalan';
        } elseif ($this->isSelesai()) {
            return 'Selesai';
        } elseif ($this->isBelumMulai()) {
            return 'Belum Dimulai';
        } else {
            return 'Tidak Aktif';
        }
    }

    /**
     * Mendapatkan jenis display
     */
    public function getJenisDisplayAttribute()
    {
        $jenisMap = [
            self::JENIS_PEMBELAJARAN => 'Pembelajaran',
            self::JENIS_UJIAN => 'Ujian',
            self::JENIS_LIBUR => 'Libur',
            self::JENIS_ORIENTASI => 'Orientasi'
        ];

        return $jenisMap[$this->jenis] ?? 'Unknown';
    }

    /**
     * Mendapatkan semester display
     */
    public function getSemesterDisplayAttribute()
    {
        $semesterMap = [
            self::SEMESTER_GANJIL => 'Ganjil',
            self::SEMESTER_GENAP => 'Genap',
            self::SEMESTER_BOTH => 'Ganjil & Genap'
        ];

        return $semesterMap[$this->semester] ?? 'Unknown';
    }

    /**
     * Mendapatkan durasi periode dalam hari
     */
    public function getDurasiHariAttribute()
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    /**
     * Mendapatkan sisa hari periode
     */
    public function getSisaHariAttribute()
    {
        if ($this->isSelesai()) {
            return 0;
        }

        $today = Carbon::today();
        if ($this->isBelumMulai()) {
            return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
        }

        return $today->diffInDays($this->tanggal_selesai) + 1;
    }

    /**
     * Mendapatkan progress persentase periode
     */
    public function getProgressPersentaseAttribute()
    {
        if ($this->isBelumMulai()) {
            return 0;
        }

        if ($this->isSelesai()) {
            return 100;
        }

        $totalHari = $this->durasi_hari;
        $hariTerlalui = $this->tanggal_mulai->diffInDays(Carbon::today()) + 1;

        return round(($hariTerlalui / $totalHari) * 100, 2);
    }

    /**
     * Cek apakah tanggal tertentu dalam periode ini
     */
    public function isDateInPeriod($tanggal)
    {
        $date = Carbon::parse($tanggal);
        return $this->is_active &&
            $date >= $this->tanggal_mulai &&
            $date <= $this->tanggal_selesai;
    }

    /**
     * Mendapatkan periode yang sedang berjalan untuk tahun ajaran tertentu
     */
    public static function getCurrentPeriod($tahunAjaranId = null)
    {
        $query = self::berjalan();

        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        return $query->first();
    }

    /**
     * Cek apakah absensi valid pada tanggal tertentu
     */
    public static function isValidAbsensiDate($tanggal, $tahunAjaranId = null)
    {
        $query = self::where('jenis', self::JENIS_PEMBELAJARAN)
            ->where('is_active', true)
            ->where('tanggal_mulai', '<=', $tanggal)
            ->where('tanggal_selesai', '>=', $tanggal);

        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        return $query->exists();
    }
}
