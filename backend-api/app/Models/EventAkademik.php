<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Support\RoleNames;

class EventAkademik extends Model
{
    use HasFactory;

    protected $table = 'event_akademik';

    /**
     * Jenis event constants
     */
    const JENIS_UJIAN = 'ujian';
    const JENIS_LIBUR = 'libur';
    const JENIS_KEGIATAN = 'kegiatan';
    const JENIS_DEADLINE = 'deadline';
    const JENIS_RAPAT = 'rapat';
    const JENIS_PELATIHAN = 'pelatihan';

    protected $fillable = [
        'tahun_ajaran_id',
        'periode_akademik_id',
        'nama',
        'jenis',
        'tanggal_mulai',
        'tanggal_selesai',
        'waktu_mulai',
        'waktu_selesai',
        'tingkat_id',
        'kelas_id',
        'is_wajib',
        'is_active',
        'deskripsi',
        'lokasi',
        'metadata'
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'waktu_mulai' => 'datetime:H:i',
            'waktu_selesai' => 'datetime:H:i',
            'is_wajib' => 'boolean',
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
     * Relasi dengan PeriodeAkademik
     */
    public function periodeAkademik()
    {
        return $this->belongsTo(PeriodeAkademik::class);
    }

    /**
     * Relasi dengan Tingkat
     */
    public function tingkat()
    {
        return $this->belongsTo(Tingkat::class);
    }

    /**
     * Relasi dengan Kelas
     */
    public function kelas()
    {
        return $this->belongsTo(Kelas::class);
    }

    /**
     * Scope untuk filter berdasarkan jenis
     */
    public function scopeByJenis($query, $jenis)
    {
        return $query->where('jenis', $jenis);
    }

    /**
     * Scope untuk event aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk event wajib
     */
    public function scopeWajib($query)
    {
        return $query->where('is_wajib', true);
    }

    /**
     * Scope untuk event yang sedang berjalan
     */
    public function scopeBerjalan($query)
    {
        $today = Carbon::today();
        return $query->where('tanggal_mulai', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $today);
            })
            ->where('is_active', true);
    }

    /**
     * Scope untuk event mendatang
     */
    public function scopeMendatang($query, $days = 7)
    {
        $days = max(1, (int) $days);
        $today = Carbon::today();
        $futureDate = $today->copy()->addDays($days);

        return $query->where('tanggal_mulai', '>', $today)
            ->where('tanggal_mulai', '<=', $futureDate)
            ->where('is_active', true);
    }

    /**
     * Scope untuk event hari ini
     */
    public function scopeHariIni($query)
    {
        $today = Carbon::today();
        return $query->where('tanggal_mulai', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('tanggal_selesai')
                    ->orWhere('tanggal_selesai', '>=', $today);
            })
            ->where('is_active', true);
    }

    /**
     * Scope untuk event berdasarkan tingkat
     */
    public function scopeByTingkat($query, $tingkatId)
    {
        return $query->where(function ($q) use ($tingkatId) {
            $q->where('tingkat_id', $tingkatId)
                ->orWhereNull('tingkat_id'); // Event untuk semua tingkat
        });
    }

    /**
     * Scope untuk event berdasarkan kelas
     */
    public function scopeByKelas($query, $kelasId)
    {
        return $query->where(function ($q) use ($kelasId) {
            $q->where('kelas_id', $kelasId)
                ->orWhereNull('kelas_id'); // Event untuk semua kelas
        });
    }

    /**
     * Cek apakah event sedang berjalan
     */
    public function isBerjalan()
    {
        $today = Carbon::today();
        $endDate = $this->tanggal_selesai ?? $this->tanggal_mulai;

        return $this->is_active &&
            $this->tanggal_mulai <= $today &&
            $endDate >= $today;
    }

    /**
     * Cek apakah event sudah selesai
     */
    public function isSelesai()
    {
        $endDate = $this->tanggal_selesai ?? $this->tanggal_mulai;
        return Carbon::today() > $endDate;
    }

    /**
     * Cek apakah event belum dimulai
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
            self::JENIS_UJIAN => 'Ujian',
            self::JENIS_LIBUR => 'Libur',
            self::JENIS_KEGIATAN => 'Kegiatan',
            self::JENIS_DEADLINE => 'Deadline',
            self::JENIS_RAPAT => 'Rapat',
            self::JENIS_PELATIHAN => 'Pelatihan'
        ];

        return $jenisMap[$this->jenis] ?? 'Unknown';
    }

    /**
     * Mendapatkan durasi event dalam hari
     */
    public function getDurasiHariAttribute()
    {
        if (!$this->tanggal_selesai) {
            return 1; // Event satu hari
        }

        return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    /**
     * Mendapatkan sisa hari event
     */
    public function getSisaHariAttribute()
    {
        if ($this->isSelesai()) {
            return 0;
        }

        $today = Carbon::today();
        if ($this->isBelumMulai()) {
            return $today->diffInDays($this->tanggal_mulai);
        }

        $endDate = $this->tanggal_selesai ?? $this->tanggal_mulai;
        return $today->diffInDays($endDate);
    }

    /**
     * Mendapatkan waktu display
     */
    public function getWaktuDisplayAttribute()
    {
        if (!$this->waktu_mulai && !$this->waktu_selesai) {
            return 'Sepanjang Hari';
        }

        $waktuMulai = $this->waktu_mulai ? Carbon::parse($this->waktu_mulai)->format('H:i') : '';
        $waktuSelesai = $this->waktu_selesai ? Carbon::parse($this->waktu_selesai)->format('H:i') : '';

        if ($waktuMulai && $waktuSelesai) {
            return "{$waktuMulai} - {$waktuSelesai}";
        } elseif ($waktuMulai) {
            return "Mulai {$waktuMulai}";
        } elseif ($waktuSelesai) {
            return "Sampai {$waktuSelesai}";
        }

        return 'Sepanjang Hari';
    }

    /**
     * Mendapatkan tanggal display
     */
    public function getTanggalDisplayAttribute()
    {
        if (!$this->tanggal_selesai || $this->tanggal_mulai->eq($this->tanggal_selesai)) {
            return $this->tanggal_mulai->format('d M Y');
        }

        return $this->tanggal_mulai->format('d M Y') . ' - ' . $this->tanggal_selesai->format('d M Y');
    }

    /**
     * Mendapatkan scope display (tingkat/kelas)
     */
    public function getScopeDisplayAttribute()
    {
        if ($this->kelas) {
            return "Kelas " . ($this->kelas->nama_kelas ?? $this->kelas->nama ?? '-');
        } elseif ($this->tingkat) {
            return "Tingkat {$this->tingkat->nama}";
        } else {
            return 'Semua';
        }
    }

    /**
     * Cek apakah event berlaku untuk user tertentu
     */
    public function isApplicableForUser($user)
    {
        // Jika tidak ada batasan tingkat/kelas, berlaku untuk semua
        if (!$this->tingkat_id && !$this->kelas_id) {
            return true;
        }

        [$kelasIds, $tingkatIds] = $this->resolveApplicableStudentScope($user);

        // Cek berdasarkan kelas user (untuk siswa)
        if ($user->hasRole(RoleNames::aliases(RoleNames::SISWA)) && $this->kelas_id) {
            return in_array((int) $this->kelas_id, $kelasIds, true);
        }

        // Cek berdasarkan tingkat user (untuk siswa)
        if ($user->hasRole(RoleNames::aliases(RoleNames::SISWA)) && $this->tingkat_id) {
            return in_array((int) $this->tingkat_id, $tingkatIds, true);
        }

        // Untuk guru/admin, berlaku untuk semua event
        if ($user->hasRole(RoleNames::flattenAliases([
            RoleNames::GURU,
            RoleNames::ADMIN,
            RoleNames::SUPER_ADMIN,
        ]))) {
            return true;
        }

        return false;
    }

    /**
     * Mendapatkan event mendatang untuk user tertentu
     */
    public static function getUpcomingForUser($user, $days = 7, $tahunAjaranId = null)
    {
        $query = self::mendatang($days);

        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        if ($user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            [$kelasIds, $tingkatIds] = (new self())->resolveApplicableStudentScope($user);

            $query->where(function ($q) use ($kelasIds, $tingkatIds) {
                $q->whereNull('kelas_id') // Event untuk semua kelas
                    ->whereNull('tingkat_id') // Event untuk semua tingkat
                    ->orWhereIn('kelas_id', $kelasIds)
                    ->orWhereIn('tingkat_id', $tingkatIds);
            });
        }

        return $query->orderBy('tanggal_mulai')->get();
    }

    /**
     * Mendapatkan event hari ini untuk user tertentu
     */
    public static function getTodayForUser($user, $tahunAjaranId = null)
    {
        $query = self::hariIni();

        if ($tahunAjaranId) {
            $query->where('tahun_ajaran_id', $tahunAjaranId);
        }

        if ($user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            [$kelasIds, $tingkatIds] = (new self())->resolveApplicableStudentScope($user);

            $query->where(function ($q) use ($kelasIds, $tingkatIds) {
                $q->whereNull('kelas_id')
                    ->whereNull('tingkat_id')
                    ->orWhereIn('kelas_id', $kelasIds)
                    ->orWhereIn('tingkat_id', $tingkatIds);
            });
        }

        return $query->orderBy('waktu_mulai')->get();
    }

    /**
     * @return array{0: array<int, int>, 1: array<int, int>}
     */
    private function resolveApplicableStudentScope($user): array
    {
        if (!$user || !$user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            return [[], []];
        }

        $kelasRelation = $user->kelas();
        $pivotTable = $kelasRelation->getTable();
        $relatedTable = $kelasRelation->getRelated()->getTable();

        if (\Illuminate\Support\Facades\Schema::hasColumn($pivotTable, 'status')) {
            $kelasRelation->wherePivot('status', 'aktif');
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn($pivotTable, 'is_active')) {
            $kelasRelation->wherePivot('is_active', true);
        }

        $kelasRows = $kelasRelation
            ->with('tingkat:id,nama')
            ->select("{$relatedTable}.id", "{$relatedTable}.tingkat_id")
            ->get();

        $kelasIds = $kelasRows->pluck('id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        $tingkatIds = $kelasRows->pluck('tingkat_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();

        return [$kelasIds, $tingkatIds];
    }
}
