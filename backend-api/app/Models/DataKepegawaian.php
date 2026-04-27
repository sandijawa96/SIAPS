<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataKepegawaian extends Model
{
    use SoftDeletes;

    protected $table = 'data_kepegawaian';

    const STATUS_KEPEGAWAIAN = ['ASN', 'Honorer'];

    protected $fillable = [
        'user_id',
        'nip',
        'nuptk',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'alamat_jalan',
        'rt',
        'rw',
        'nama_dusun',
        'kelurahan',
        'kecamatan',
        'kota_kabupaten',
        'provinsi',
        'kode_pos',
        'no_hp',
        'no_telepon_kantor',
        'email_notifikasi',

        // Data keluarga
        'nama_ibu_kandung',
        'nama_pasangan',
        'nip_suami_istri',
        'pekerjaan_pasangan',
        'jumlah_anak',
        'status_pernikahan',
        'status_perkawinan',
        'no_kk',

        // Data kepegawaian
        'status_kepegawaian',
        'jenis_ptk',
        'nomor_sk',
        'tanggal_sk',
        'sk_cpns',
        'tanggal_cpns',
        'tmt_cpns',
        'sk_pengangkatan',
        'tmt_pengangkatan',
        'lembaga_pengangkatan',
        'sumber_gaji',
        'golongan',
        'tmt',
        'tmt_pns',
        'masa_kontrak_mulai',
        'masa_kontrak_selesai',
        'jabatan',
        'tugas_tambahan',
        'sub_jabatan',
        'bidang_studi',
        'pangkat_golongan',
        'sertifikasi',
        'tahun_sertifikasi',
        'nomor_sertifikasi',
        'sudah_lisensi_kepala_sekolah',
        'pernah_diklat_kepengawasan',
        'keahlian_braille',
        'keahlian_bahasa_isyarat',
        'npwp',
        'nama_wajib_pajak',
        'kewarganegaraan',
        'karpeg',
        'karis_karsu',
        'nuks',

        // Data pendidikan
        'pendidikan_terakhir',
        'jurusan',
        'universitas',
        'institusi',
        'tahun_lulus',
        'no_ijazah',
        'gelar_depan',
        'gelar_belakang',

        // Data mengajar
        'mata_pelajaran',
        'jam_mengajar_per_minggu',
        'kelas_yang_diajar',

        // Data keluarga tambahan
        'data_anak',

        // Data tambahan
        'alamat_domisili',
        'bank',
        'nomor_rekening_bank',
        'rekening_atas_nama',
        'lintang',
        'bujur',
        'is_active',
        'keterangan',
        'sertifikat',
        'pelatihan',

        // Data sistem absensi
        'metode_absensi',
        'gps_tracking',
        'last_tracked_location',
        'jam_masuk',
        'jam_pulang',
        'hari_kerja',

        // Data notifikasi
        'notifikasi_settings'
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            if ($model->status_kepegawaian && !in_array($model->status_kepegawaian, self::STATUS_KEPEGAWAIAN)) {
                throw new \InvalidArgumentException('Status kepegawaian tidak valid. Harus ASN atau Honorer.');
            }
        });
    }

    protected $casts = [
        'tanggal_lahir' => 'date:Y-m-d',
        'tanggal_sk' => 'date:Y-m-d',
        'tanggal_cpns' => 'date:Y-m-d',
        'tmt' => 'date',
        'tmt_pns' => 'date',
        'masa_kontrak_mulai' => 'date',
        'masa_kontrak_selesai' => 'date',
        'tmt_cpns' => 'date',
        'tmt_pengangkatan' => 'date',
        'tahun_lulus' => 'integer',
        'tahun_sertifikasi' => 'integer',
        'jumlah_anak' => 'integer',
        'jam_mengajar_per_minggu' => 'integer',
        'lintang' => 'decimal:7',
        'bujur' => 'decimal:7',
        'metode_absensi' => 'json',
        'last_tracked_location' => 'json',
        'hari_kerja' => 'json',
        'notifikasi_settings' => 'json',
        'sub_jabatan' => 'json',
        'mata_pelajaran' => 'json',
        'kelas_yang_diajar' => 'json',
        'data_anak' => 'json',
        'sertifikat' => 'json',
        'pelatihan' => 'json',
        'sudah_lisensi_kepala_sekolah' => 'boolean',
        'pernah_diklat_kepengawasan' => 'boolean',
        'keahlian_braille' => 'boolean',
        'keahlian_bahasa_isyarat' => 'boolean',
        'gps_tracking' => 'boolean',
        'is_active' => 'boolean'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
