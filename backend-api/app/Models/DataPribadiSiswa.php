<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DataPribadiSiswa extends Model
{
    use SoftDeletes;

    protected $table = 'data_pribadi_siswa';

    protected $fillable = [
        'user_id',
        'tempat_lahir',
        'tanggal_lahir',
        'jenis_kelamin',
        'agama',
        'alamat',
        'rt',
        'rw',
        'dusun',
        'kelurahan',
        'kecamatan',
        'kota_kabupaten',
        'provinsi',
        'kode_pos',
        'jenis_tinggal',
        'alat_transportasi',
        'skhun',
        'no_hp_siswa',
        'email_siswa',
        'email_notifikasi',
        'no_telepon_rumah',
        'no_hp_ortu',
        'penerima_kps',
        'no_kps',
        'no_kk',

        // Data orang tua/wali
        'nama_ayah',
        'tahun_lahir_ayah',
        'pekerjaan_ayah',
        'pendidikan_ayah',
        'penghasilan_ayah',
        'nik_ayah',
        'no_hp_ayah',
        'email_ayah',

        'nama_ibu',
        'tahun_lahir_ibu',
        'pekerjaan_ibu',
        'pendidikan_ibu',
        'penghasilan_ibu',
        'nik_ibu',
        'no_hp_ibu',
        'email_ibu',

        // Data wali
        'wali_siswa',
        'hubungan_wali',
        'nama_wali',
        'tahun_lahir_wali',
        'pekerjaan_wali',
        'pendidikan_wali',
        'penghasilan_wali',
        'nik_wali',
        'no_hp_wali',
        'email_wali',
        'alamat_wali',

        // Data tambahan siswa
        'anak_ke',
        'jumlah_saudara',
        'golongan_darah',
        'tinggi_badan',
        'berat_badan',
        'lingkar_kepala',
        'jarak_rumah_km',
        'kebutuhan_khusus',
        'lintang',
        'bujur',

        // Data akademik
        'asal_sekolah',
        'npsn_asal',
        'alamat_sekolah_asal',
        'tahun_lulus_asal',
        'tahun_lulus_sd',
        'nilai_un_sd',
        'no_peserta_ujian_nasional',
        'no_seri_ijazah',
        'penerima_kip',
        'nomor_kip',
        'nama_di_kip',
        'nomor_kks',
        'no_registrasi_akta_lahir',
        'bank',
        'nomor_rekening_bank',
        'rekening_atas_nama',
        'layak_pip',
        'alasan_layak_pip',
        'tahun_masuk',
        'tanggal_masuk_sekolah',
        'kelas_awal_id',
        'tahun_ajaran_awal_id',
        'tanggal_masuk_kelas_awal',
        'status',

        // Data sistem
        'metode_absensi',
        'gps_tracking',
        'last_tracked_location',
        'notifikasi_settings',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date:Y-m-d',
        'tanggal_masuk_sekolah' => 'date:Y-m-d',
        'tanggal_masuk_kelas_awal' => 'date:Y-m-d',
        'tahun_masuk' => 'integer',
        'kelas_awal_id' => 'integer',
        'tahun_ajaran_awal_id' => 'integer',
        'tahun_lulus_asal' => 'integer',
        'tahun_lulus_sd' => 'integer',
        'tahun_lahir_ayah' => 'integer',
        'tahun_lahir_ibu' => 'integer',
        'tahun_lahir_wali' => 'integer',
        'anak_ke' => 'integer',
        'jumlah_saudara' => 'integer',
        'tinggi_badan' => 'integer',
        'berat_badan' => 'integer',
        'nilai_un_sd' => 'decimal:2',
        'lingkar_kepala' => 'decimal:2',
        'jarak_rumah_km' => 'decimal:2',
        'lintang' => 'decimal:7',
        'bujur' => 'decimal:7',
        'penerima_kps' => 'boolean',
        'penerima_kip' => 'boolean',
        'layak_pip' => 'boolean',
        'metode_absensi' => 'json',
        'last_tracked_location' => 'json',
        'notifikasi_settings' => 'json',
        'gps_tracking' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
