<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'kelas';

    /**
     * Atribut yang dapat diisi secara massal
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'nama_kelas',
        'tingkat_id',
        'jurusan',
        'wali_kelas_id',
        'tahun_ajaran_id',
        'kapasitas',
        'jumlah_siswa',
        'keterangan',
        'is_active',
    ];

    /**
     * Atribut yang harus di-cast
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'kapasitas' => 'integer',
        ];
    }

    /**
     * Relasi belongs-to dengan User sebagai wali kelas
     */
    public function waliKelas()
    {
        return $this->belongsTo(User::class, 'wali_kelas_id');
    }

    /**
     * Relasi belongs-to dengan Tingkat
     */
    public function tingkat()
    {
        return $this->belongsTo(Tingkat::class, 'tingkat_id');
    }

    /**
     * Relasi belongs-to dengan TahunAjaran
     */
    public function tahunAjaran()
    {
        return $this->belongsTo(TahunAjaran::class);
    }

    /**
     * Relasi many-to-many dengan User (siswa) melalui tabel kelas_siswa
     */
    public function siswa()
    {
        return $this->belongsToMany(User::class, 'kelas_siswa', 'kelas_id', 'siswa_id')
                    ->withPivot('tahun_ajaran_id', 'status', 'is_active', 'tanggal_masuk', 'tanggal_keluar', 'keterangan')
                    ->withTimestamps();
    }

    /**
     * Scope untuk filter kelas aktif
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope untuk filter berdasarkan tingkat
     */
    public function scopeTingkat($query, $tingkatId)
    {
        return $query->where('tingkat_id', $tingkatId);
    }

    /**
     * Scope untuk filter berdasarkan jurusan
     */
    public function scopeJurusan($query, $jurusan)
    {
        return $query->where('jurusan', $jurusan);
    }

    /**
     * Scope untuk filter berdasarkan tahun ajaran
     */
    public function scopeTahunAjaran($query, $tahunAjaranId)
    {
        return $query->where('tahun_ajaran_id', $tahunAjaranId);
    }

    /**
     * Mendapatkan jumlah siswa aktif di kelas
     */
    public function getJumlahSiswaAttribute()
    {
        return $this->siswa()
                    ->wherePivot('status', 'aktif')
                    ->wherePivot('is_active', true)
                    ->count();
    }

    /**
     * Mendapatkan sisa kapasitas kelas
     */
    public function getSisaKapasitasAttribute()
    {
        return $this->kapasitas - $this->jumlah_siswa;
    }

    /**
     * Cek apakah kelas masih memiliki kapasitas
     */
    public function hasKapasitas()
    {
        return $this->sisa_kapasitas > 0;
    }

    /**
     * Mendapatkan daftar siswa aktif berdasarkan semester
     */
    public function getSiswaBySemester($semester)
    {
        return $this->siswa()
                    ->wherePivot('status', 'aktif')
                    ->get();
    }

    /**
     * Mendapatkan nama lengkap kelas (tingkat + jurusan + nama kelas)
     */
    public function getNamaLengkapAttribute()
    {
        $tingkatNama = $this->tingkat ? $this->tingkat->nama : '';
        return "{$tingkatNama} {$this->jurusan} {$this->nama_kelas}";
    }

    /**
     * Mendapatkan daftar tingkat unik
     */
    public static function getTingkatList()
    {
        return self::with('tingkat')->get()->pluck('tingkat.nama')->unique();
    }

    /**
     * Mendapatkan daftar jurusan unik
     */
    public static function getJurusanList()
    {
        return self::distinct()->pluck('jurusan');
    }

    /**
     * Menambahkan siswa ke kelas
     */
    public function tambahSiswa($userId, $tahunAjaranId)
    {
        if (!$this->hasKapasitas()) {
            throw new \Exception('Kapasitas kelas sudah penuh');
        }

        return $this->siswa()->attach($userId, [
            'tahun_ajaran_id' => $tahunAjaranId,
            'status' => 'aktif',
            'tanggal_masuk' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Mengeluarkan siswa dari kelas
     */
    public function keluarkanSiswa($userId, $tahunAjaranId, $status = 'keluar', $catatan = null)
    {
        return $this->siswa()
                    ->wherePivot('tahun_ajaran_id', $tahunAjaranId)
                    ->updateExistingPivot($userId, [
                        'status' => $status,
                        'tanggal_keluar' => now()->toDateString(),
                        'keterangan' => $catatan,
                        'updated_at' => now()
                    ]);
    }
}
