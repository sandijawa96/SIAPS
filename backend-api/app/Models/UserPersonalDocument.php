<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserPersonalDocument extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'uploaded_by',
        'document_type',
        'title',
        'original_name',
        'mime_type',
        'size_bytes',
        'checksum_sha256',
        'storage_provider',
        'remote_path',
        'remote_url',
        'metadata',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function toApiArray(): array
    {
        return [
            'id' => (int) $this->id,
            'user_id' => (int) $this->user_id,
            'document_type' => $this->document_type,
            'document_type_label' => self::labelForType($this->document_type),
            'title' => $this->title,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => (int) $this->size_bytes,
            'size_label' => self::formatBytes((int) $this->size_bytes),
            'checksum_sha256' => $this->checksum_sha256,
            'storage_provider' => $this->storage_provider,
            'remote_path' => $this->remote_path,
            'remote_url' => $this->remote_url,
            'uploaded_by' => $this->uploader ? [
                'id' => (int) $this->uploader->id,
                'name' => $this->uploader->nama_lengkap,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    public static function labelForType(?string $type): string
    {
        return match (strtolower(trim((string) $type))) {
            'identitas' => 'Identitas',
            'akademik' => 'Akademik',
            'alamat' => 'Alamat',
            'keluarga' => 'Keluarga',
            'kepegawaian' => 'Kepegawaian',
            'administrasi' => 'Administrasi',
            'akta_nikah' => 'Akta Nikah',
            'akta_cerai' => 'Akta Cerai',
            'npwp' => 'Nomor Pokok Wajib Pajak (NPWP)',
            'askes_bpjs' => 'ASKES/BPJS',
            'skumptk' => 'SKUMPTK',
            'kpe' => 'KPE',
            'karpeg' => 'KARPEG',
            'sk_pp' => 'SK PP',
            'penambahan_masa_kerja' => 'Penambahan Masa Kerja',
            'sk_pensiun_bup' => 'SK Pensiun - BUP',
            'ktp_siswa' => 'KTP Siswa / Identitas Siswa',
            'kartu_pelajar' => 'Kartu Pelajar',
            'pas_foto' => 'Pas Foto Siswa',
            'bukti_nisn' => 'Bukti NISN',
            'ktp_ayah' => 'KTP Ayah',
            'ktp_ibu' => 'KTP Ibu',
            'ktp_wali' => 'KTP Wali',
            'akta_nikah_ortu' => 'Akta Nikah Orang Tua',
            'surat_perwalian' => 'Surat Keterangan Wali',
            'ijazah_sd' => 'Ijazah SD/MI',
            'ijazah_smp' => 'Ijazah SMP/MTs atau SKL',
            'skhun' => 'SKHUN/SHUN SMP/MTs',
            'rapor' => 'Rapor SMP/MTs',
            'surat_pindah' => 'Surat Pindah / Mutasi',
            'sertifikat_prestasi' => 'Sertifikat Prestasi',
            'kip' => 'Kartu Indonesia Pintar (KIP)',
            'kks' => 'Kartu Keluarga Sejahtera (KKS)',
            'kps' => 'KPS / PKH',
            'sktm' => 'Surat Keterangan Tidak Mampu',
            'rekening_siswa' => 'Buku Rekening Siswa',
            'pas_foto_pegawai' => 'Pas Foto Pegawai',
            'ijazah_terakhir' => 'Ijazah Terakhir',
            'transkrip_nilai' => 'Transkrip Nilai',
            'sertifikat_pendidik' => 'Sertifikat Pendidik',
            'sertifikat_pelatihan' => 'Sertifikat Pelatihan/Diklat',
            'nuptk_dokumen' => 'Dokumen NUPTK',
            'sk_pengangkatan' => 'SK Pengangkatan / Kontrak Kerja',
            'sk_cpns' => 'SK CPNS',
            'sk_pns' => 'SK PNS',
            'sk_jabatan' => 'SK Jabatan',
            'sk_tugas_tambahan' => 'SK Pembagian Tugas / Mengajar',
            'kk' => 'Kartu Keluarga',
            'ktp' => 'KTP / Identitas',
            'akta' => 'Akta Kelahiran',
            'ijazah' => 'Ijazah / Dokumen Akademik',
            'sk' => 'Surat Keputusan',
            'kesehatan' => 'Dokumen Kesehatan',
            default => 'Dokumen Lainnya',
        };
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }
}
