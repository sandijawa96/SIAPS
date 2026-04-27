<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class PengajuanIzin extends Model
{
    use HasFactory;

    protected $table = 'pengajuan_izin';

    /**
     * Atribut yang dapat diisi secara massal
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'jenis_izin',
        'tanggal_mulai',
        'tanggal_selesai',
        'alasan',
        'dokumen_pendukung',
        'status',
        'approved_by',
        'approved_at',
        'catatan_approval',
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
            'approved_at' => 'datetime',
        ];
    }

    /**
     * Status pengajuan izin
     */
    const STATUS_PENDING = 'Pending';
    const STATUS_APPROVED = 'Disetujui';
    const STATUS_REJECTED = 'Ditolak';

    /**
     * Jenis izin
     */
    const JENIS_SAKIT = 'Sakit';
    const JENIS_IZIN = 'Izin';
    const JENIS_CUTI = 'Cuti';
    const JENIS_DINAS = 'Dinas Luar';
    const JENIS_LAINNYA = 'Lainnya';

    /**
     * Relasi belongs-to dengan User (pengaju)
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relasi belongs-to dengan User (approver)
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Scope untuk filter berdasarkan status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter pengajuan pending
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope untuk filter pengajuan approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Scope untuk filter pengajuan rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    /**
     * Scope untuk filter berdasarkan jenis izin
     */
    public function scopeJenisIzin($query, $jenisIzin)
    {
        return $query->where('jenis_izin', $jenisIzin);
    }

    /**
     * Scope untuk filter berdasarkan tanggal
     */
    public function scopeTanggal($query, $tanggal)
    {
        return $query->where('tanggal_mulai', '<=', $tanggal)
                    ->where('tanggal_selesai', '>=', $tanggal);
    }

    /**
     * Scope untuk filter berdasarkan bulan
     */
    public function scopeBulan($query, $bulan, $tahun = null)
    {
        $tahun = $tahun ?? date('Y');
        return $query->whereMonth('tanggal_mulai', $bulan)
                    ->whereYear('tanggal_mulai', $tahun);
    }

    /**
     * Scope untuk filter berdasarkan tahun
     */
    public function scopeTahun($query, $tahun)
    {
        return $query->whereYear('tanggal_mulai', $tahun);
    }

    /**
     * Mendapatkan durasi izin dalam hari
     */
    public function getDurasiHariAttribute()
    {
        return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
    }

    /**
     * Cek apakah izin sedang berlangsung
     */
    public function isBerlangsung()
    {
        $today = Carbon::today();
        return $this->status === self::STATUS_APPROVED &&
               $this->tanggal_mulai <= $today &&
               $this->tanggal_selesai >= $today;
    }

    /**
     * Cek apakah izin sudah selesai
     */
    public function isSelesai()
    {
        return Carbon::today() > $this->tanggal_selesai;
    }

    /**
     * Cek apakah izin belum dimulai
     */
    public function isBelumMulai()
    {
        return Carbon::today() < $this->tanggal_mulai;
    }

    /**
     * Mendapatkan status izin berdasarkan tanggal
     */
    public function getStatusIzinAttribute()
    {
        if ($this->status !== self::STATUS_APPROVED) {
            return $this->status;
        }

        if ($this->isBelumMulai()) {
            return 'Akan Datang';
        } elseif ($this->isBerlangsung()) {
            return 'Sedang Berlangsung';
        } else {
            return 'Selesai';
        }
    }

    /**
     * Accessor untuk dokumen pendukung URL
     */
    public function getDokumenPendukungUrlAttribute()
    {
        return $this->dokumen_pendukung ? asset('storage/' . $this->dokumen_pendukung) : null;
    }

    /**
     * Approve pengajuan izin
     */
    public function approve($approvedBy, $catatan = null)
    {
        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'catatan_approval' => $catatan
        ]);

        return $this;
    }

    /**
     * Reject pengajuan izin
     */
    public function reject($approvedBy, $catatan = null)
    {
        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'catatan_approval' => $catatan
        ]);

        return $this;
    }

    /**
     * Mendapatkan daftar jenis izin
     */
    public static function getJenisIzinList()
    {
        return [
            self::JENIS_SAKIT,
            self::JENIS_IZIN,
            self::JENIS_CUTI,
            self::JENIS_DINAS,
            self::JENIS_LAINNYA,
        ];
    }

    /**
     * Mendapatkan daftar status
     */
    public static function getStatusList()
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
        ];
    }

    /**
     * Cek apakah user memiliki izin yang bertabrakan
     */
    public static function hasConflictingIzin($userId, $tanggalMulai, $tanggalSelesai, $excludeId = null)
    {
        $query = self::where('user_id', $userId)
                    ->where('status', self::STATUS_APPROVED)
                    ->where(function ($q) use ($tanggalMulai, $tanggalSelesai) {
                        $q->whereBetween('tanggal_mulai', [$tanggalMulai, $tanggalSelesai])
                          ->orWhereBetween('tanggal_selesai', [$tanggalMulai, $tanggalSelesai])
                          ->orWhere(function ($q2) use ($tanggalMulai, $tanggalSelesai) {
                              $q2->where('tanggal_mulai', '<=', $tanggalMulai)
                                 ->where('tanggal_selesai', '>=', $tanggalSelesai);
                          });
                    });

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Mendapatkan total hari izin user dalam periode
     */
    public static function getTotalHariIzin($userId, $startDate, $endDate, $jenisIzin = null)
    {
        $query = self::where('user_id', $userId)
                    ->where('status', self::STATUS_APPROVED)
                    ->where(function ($q) use ($startDate, $endDate) {
                        $q->whereBetween('tanggal_mulai', [$startDate, $endDate])
                          ->orWhereBetween('tanggal_selesai', [$startDate, $endDate])
                          ->orWhere(function ($q2) use ($startDate, $endDate) {
                              $q2->where('tanggal_mulai', '<=', $startDate)
                                 ->where('tanggal_selesai', '>=', $endDate);
                          });
                    });

        if ($jenisIzin) {
            $query->where('jenis_izin', $jenisIzin);
        }

        $totalHari = 0;
        $izinList = $query->get();

        foreach ($izinList as $izin) {
            $mulai = Carbon::parse(max($izin->tanggal_mulai, $startDate));
            $selesai = Carbon::parse(min($izin->tanggal_selesai, $endDate));
            $totalHari += $mulai->diffInDays($selesai) + 1;
        }

        return $totalHari;
    }
}
