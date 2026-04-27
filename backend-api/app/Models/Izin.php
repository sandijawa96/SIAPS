<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class Izin extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Jenis izin untuk siswa.
     *
     * @var array<int, string>
     */
    public const JENIS_IZIN_SISWA = [
        'sakit',
        'izin',
        'keperluan_keluarga',
        'dispensasi',
        'tugas_sekolah',
    ];

    /**
     * Jenis izin untuk pegawai/non-siswa.
     *
     * @var array<int, string>
     */
    public const JENIS_IZIN_PEGAWAI = [
        'sakit',
        'izin',
        'keperluan_keluarga',
        'dinas_luar',
        'cuti',
    ];

    protected $table = 'izin';

    /**
     * Atribut yang dapat diisi secara massal
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'kelas_id',
        'jenis_izin',
        'tanggal_mulai',
        'tanggal_selesai',
        'alasan',
        'dokumen_pendukung',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'catatan_approval',
    ];

    /**
     * Atribut yang harus di-cast
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tanggal_mulai' => 'date:Y-m-d',
        'tanggal_selesai' => 'date:Y-m-d',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected $appends = [
        'durasi',
        'status_color',
        'status_label',
        'jenis_izin_label',
        'dokumen_pendukung_url',
        'dokumen_pendukung_nama',
    ];

    /**
     * Relasi belongs-to dengan User (siswa yang mengajukan izin)
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relasi belongs-to dengan Kelas
     */
    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'kelas_id');
    }

    /**
     * Relasi belongs-to dengan User (yang menyetujui)
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Relasi belongs-to dengan User (yang menolak)
     */
    public function rejectedBy()
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /**
     * Scope untuk filter berdasarkan status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter berdasarkan kelas
     */
    public function scopeKelas($query, $kelasId)
    {
        return $query->where('kelas_id', $kelasId);
    }

    /**
     * Scope untuk filter berdasarkan user
     */
    public function scopeUser($query, $userId)
    {
        return $query->where('user_id', $userId);
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
        return $query->whereDate('tanggal_mulai', '<=', $tanggal)
                    ->whereDate('tanggal_selesai', '>=', $tanggal);
    }

    /**
     * Scope untuk izin yang pending
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope untuk izin yang approved
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope untuk izin yang rejected
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    /**
     * Mendapatkan durasi izin dalam hari
     */
    public function getDurasiAttribute()
    {
        if ($this->tanggal_mulai && $this->tanggal_selesai) {
            return $this->tanggal_mulai->diffInDays($this->tanggal_selesai) + 1;
        }
        return 1;
    }

    /**
     * Cek apakah izin masih berlaku
     */
    public function isActive()
    {
        $today = Carbon::today();
        return $this->status === 'approved' && 
               $this->tanggal_mulai <= $today && 
               $this->tanggal_selesai >= $today;
    }

    /**
     * Cek apakah izin sudah expired
     */
    public function isExpired()
    {
        return $this->tanggal_selesai < Carbon::today();
    }

    /**
     * Approve izin
     */
    public function approve($approvedBy, $catatan = null)
    {
        $this->update([
            'status' => 'approved',
            'approved_by' => $approvedBy,
            'approved_at' => now(),
            'catatan_approval' => $catatan
        ]);
    }

    /**
     * Reject izin
     */
    public function reject($rejectedBy, $catatan = null)
    {
        $this->update([
            'status' => 'rejected',
            'rejected_by' => $rejectedBy,
            'rejected_at' => now(),
            'catatan_approval' => $catatan
        ]);
    }

    /**
     * Mendapatkan status badge color
     */
    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            default => 'secondary'
        };
    }

    /**
     * Mendapatkan status label
     */
    public function getStatusLabelAttribute()
    {
        return static::getStatusLabel((string) $this->status);
    }

    /**
     * Mendapatkan jenis izin label
     */
    public function getJenisIzinLabelAttribute()
    {
        return static::getJenisIzinLabel((string) $this->jenis_izin);
    }

    public function getDokumenPendukungUrlAttribute(): ?string
    {
        $path = trim((string) $this->dokumen_pendukung);
        if ($path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }

    public function getDokumenPendukungNamaAttribute(): ?string
    {
        $path = trim((string) $this->dokumen_pendukung);
        if ($path === '') {
            return null;
        }

        return basename($path);
    }

    /**
     * Daftar jenis izin siswa.
     *
     * @return array<int, string>
     */
    public static function studentJenisIzin(): array
    {
        return self::JENIS_IZIN_SISWA;
    }

    /**
     * Daftar jenis izin pegawai/non-siswa.
     *
     * @return array<int, string>
     */
    public static function employeeJenisIzin(): array
    {
        return self::JENIS_IZIN_PEGAWAI;
    }

    /**
     * Label human-readable untuk nilai jenis_izin.
     */
    public static function getJenisIzinLabel(string $jenisIzin): string
    {
        return match($jenisIzin) {
            'sakit' => 'Sakit',
            'izin' => 'Izin Pribadi',
            'keperluan_keluarga' => 'Urusan Keluarga',
            'dispensasi' => 'Dispensasi Sekolah',
            'tugas_sekolah' => 'Tugas Sekolah',
            'dinas_luar' => 'Dinas Luar',
            'cuti' => 'Cuti',
            default => ucfirst($jenisIzin)
        };
    }

    public static function getStatusLabel(string $status): string
    {
        return match(strtolower(trim($status))) {
            'pending' => 'Menunggu Persetujuan',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            default => 'Tidak Diketahui'
        };
    }
}
