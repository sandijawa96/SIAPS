<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\User;

class TahunAjaran extends Model
{
    use HasFactory;

    protected $table = 'tahun_ajaran';

    /**
     * Status constants
     */
    const STATUS_DRAFT = 'draft';
    const STATUS_PREPARATION = 'preparation';
    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ARCHIVED = 'archived';

    /**
     * Atribut yang dapat diisi secara massal
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama',
        'tanggal_mulai',
        'tanggal_selesai',
        'is_active',
        'status',
        'preparation_progress',
        'metadata',
        'semester',
        'keterangan'
    ];

    /**
     * Default atribut model.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'semester' => 'full',
    ];

    /**
     * Atribut yang harus di-cast
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'is_active' => 'boolean',
            'preparation_progress' => 'integer',
            'metadata' => 'array'
        ];
    }

    /**
     * Relasi one-to-many dengan Kelas
     */
    public function kelas()
    {
        return $this->hasMany(Kelas::class);
    }


    /**
     * Scope untuk filter tahun ajaran aktif (backward compatibility)
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope untuk filter tahun ajaran berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter tahun ajaran draft
     */
    public function scopeDraft($query)
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    /**
     * Scope untuk filter tahun ajaran preparation
     */
    public function scopePreparation($query)
    {
        return $query->where('status', self::STATUS_PREPARATION);
    }

    /**
     * Scope untuk filter tahun ajaran completed
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    /**
     * Scope untuk filter tahun ajaran yang sedang berjalan
     */
    public function scopeBerjalan($query)
    {
        $today = Carbon::today();
        return $query->where('tanggal_mulai', '<=', $today)
            ->where('tanggal_selesai', '>=', $today)
            ->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope untuk tahun ajaran yang bisa dikelola kelasnya
     */
    public function scopeCanManageClasses($query)
    {
        return $query->whereIn('status', [
            self::STATUS_DRAFT,
            self::STATUS_PREPARATION,
            self::STATUS_ACTIVE
        ]);
    }

    /**
     * Mendapatkan tahun ajaran aktif saat ini
     */
    public static function getAktif()
    {
        return self::where('status', self::STATUS_ACTIVE)->first();
    }

    /**
     * Mendapatkan tahun ajaran yang sedang berjalan
     */
    public static function getBerjalan()
    {
        return self::berjalan()->first();
    }

    /**
     * Mendapatkan tahun ajaran berdasarkan status
     */
    public static function getByStatus($status)
    {
        return self::where('status', $status)->get();
    }

    /**
     * Cek apakah tahun ajaran sedang berjalan
     */
    public function isBerjalan()
    {
        $today = Carbon::today();
        return $this->status === self::STATUS_ACTIVE &&
            $this->tanggal_mulai <= $today &&
            $this->tanggal_selesai >= $today;
    }

    /**
     * Cek apakah tahun ajaran sudah selesai
     */
    public function isSelesai()
    {
        return $this->status === self::STATUS_COMPLETED ||
            Carbon::today() > $this->tanggal_selesai;
    }

    /**
     * Cek apakah tahun ajaran belum dimulai
     */
    public function isBelumMulai()
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PREPARATION]) ||
            Carbon::today() < $this->tanggal_mulai;
    }

    /**
     * Mendapatkan status display untuk UI
     */
    public function getStatusDisplayAttribute()
    {
        $statusMap = [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PREPARATION => 'Persiapan',
            self::STATUS_ACTIVE => $this->isBerjalan() ? 'Sedang Berjalan' : 'Aktif',
            self::STATUS_COMPLETED => 'Selesai',
            self::STATUS_ARCHIVED => 'Diarsipkan'
        ];

        return $statusMap[$this->status] ?? 'Unknown';
    }

    /**
     * Cek apakah tahun ajaran bisa diubah ke status tertentu
     */
    public function canTransitionTo($newStatus): bool
    {
        $allowedTransitions = [
            self::STATUS_DRAFT => [self::STATUS_PREPARATION, self::STATUS_ACTIVE],
            self::STATUS_PREPARATION => [self::STATUS_ACTIVE, self::STATUS_DRAFT],
            self::STATUS_ACTIVE => [self::STATUS_COMPLETED, self::STATUS_PREPARATION],
            self::STATUS_COMPLETED => [self::STATUS_ARCHIVED, self::STATUS_ACTIVE], // Allow reactivation
            self::STATUS_ARCHIVED => [self::STATUS_ACTIVE] // Allow reactivation from archive
        ];

        return in_array($newStatus, $allowedTransitions[$this->status] ?? []);
    }

    /**
     * Ubah status tahun ajaran
     */
    public function transitionTo($newStatus, $metadata = []): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            throw new \Exception("Tidak dapat mengubah status dari {$this->status} ke {$newStatus}");
        }

        // Jika transisi ke active, nonaktifkan tahun ajaran aktif lainnya
        if ($newStatus === self::STATUS_ACTIVE) {
            self::where('id', '!=', $this->id)
                ->where('status', self::STATUS_ACTIVE)
                ->update(['status' => self::STATUS_COMPLETED]);
        }

        $this->status = $newStatus;
        $this->metadata = array_merge($this->metadata ?? [], [
            'transitioned_at' => now()->toDateTimeString(),
            'transitioned_by' => auth()->id() ?? null,
            'transition_metadata' => $metadata
        ]);

        return $this->save();
    }

    /**
     * Cek apakah tahun ajaran bisa dikelola kelasnya
     */
    public function canManageClasses(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PREPARATION,
            self::STATUS_ACTIVE
        ]);
    }

    /**
     * Update progress persiapan
     */
    public function updatePreparationProgress(int $progress, array $metadata = []): bool
    {
        if (!in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PREPARATION])) {
            throw new \Exception("Tidak dapat mengupdate progress pada status {$this->status}");
        }

        $this->preparation_progress = min(100, max(0, $progress));
        $this->metadata = array_merge($this->metadata ?? [], [
            'progress_updated_at' => now()->toDateTimeString(),
            'progress_updated_by' => auth()->id() ?? null,
            'progress_metadata' => $metadata
        ]);

        return $this->save();
    }

    /**
     * Cek apakah tahun ajaran siap untuk diaktifkan
     */
    public function isReadyToActivate(): bool
    {
        if ($this->status !== self::STATUS_PREPARATION) {
            return false;
        }

        // Minimal harus ada beberapa kelas yang sudah dibuat
        $hasClasses = $this->kelas()->exists();

        // Progress persiapan harus 100%
        $isFullyPrepared = $this->preparation_progress === 100;

        // Tanggal mulai harus valid
        $hasValidDates = $this->tanggal_mulai && $this->tanggal_selesai;

        return $hasClasses && $isFullyPrepared && $hasValidDates;
    }

    /**
     * Mendapatkan durasi tahun ajaran dalam hari
     */
    public function getDurasiHariAttribute()
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    /**
     * Mendapatkan sisa hari tahun ajaran
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
     * Mendapatkan persentase progress tahun ajaran
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
     * Mendapatkan jumlah kelas dalam tahun ajaran ini
     */
    public function getJumlahKelasAttribute()
    {
        return $this->kelas()->count();
    }

    /**
     * Mendapatkan jumlah siswa dalam tahun ajaran ini
     */
    public function getJumlahSiswaAttribute()
    {
        try {
            return User::role('Siswa', 'web')
                ->whereHas('kelas', function ($query) {
                    $query->where('kelas_siswa.tahun_ajaran_id', $this->id)
                        ->where('kelas_siswa.is_active', true);
                })
                ->where('is_active', true)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Mendapatkan jumlah guru dalam tahun ajaran ini
     */
    public function getJumlahGuruAttribute()
    {
        try {
            return User::role('Guru', 'web')
                ->whereHas('kelasWali', function ($query) {
                    $query->where('tahun_ajaran_id', $this->id);
                })
                ->where('is_active', true)
                ->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Accessor untuk semester_config (untuk kompatibilitas frontend)
     */
    public function getSemesterConfigAttribute()
    {
        $semesterMapping = [
            'ganjil' => 'Ganjil',
            'genap' => 'Genap',
            'full' => 'Ganjil & Genap'
        ];

        return [
            'semester' => $semesterMapping[$this->semester] ?? 'Ganjil & Genap',
            'keterangan' => $this->keterangan
        ];
    }

    /**
     * Aktivasi tahun ajaran (menonaktifkan yang lain)
     */
    public function activate()
    {
        // Nonaktifkan semua tahun ajaran lain
        self::where('id', '!=', $this->id)->update(['is_active' => false]);

        // Aktifkan tahun ajaran ini
        $this->update(['is_active' => true]);

        return $this;
    }

    /**
     * Mendapatkan semester berdasarkan tanggal
     */
    public function getSemesterByDate($tanggal = null)
    {
        $tanggal = $tanggal ? Carbon::parse($tanggal) : Carbon::today();

        // Logika sederhana: semester 1 = Juli-Desember, semester 2 = Januari-Juni
        $bulan = $tanggal->month;

        if ($bulan >= 7 && $bulan <= 12) {
            return 1; // Semester Ganjil
        } else {
            return 2; // Semester Genap
        }
    }
}
