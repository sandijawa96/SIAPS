<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addStudentColumns();
        $this->addEmployeeColumns();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->dropStudentColumns();
        $this->dropEmployeeColumns();
    }

    private function addStudentColumns(): void
    {
        if (!Schema::hasTable('data_pribadi_siswa')) {
            return;
        }

        $this->addColumnIfMissing('data_pribadi_siswa', 'dusun', fn (Blueprint $table) => $table->string('dusun')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'jenis_tinggal', fn (Blueprint $table) => $table->string('jenis_tinggal')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'alat_transportasi', fn (Blueprint $table) => $table->string('alat_transportasi')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'skhun', fn (Blueprint $table) => $table->string('skhun')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'penerima_kps', fn (Blueprint $table) => $table->boolean('penerima_kps')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'no_kps', fn (Blueprint $table) => $table->string('no_kps')->nullable());

        $this->addColumnIfMissing('data_pribadi_siswa', 'tahun_lahir_ayah', fn (Blueprint $table) => $table->integer('tahun_lahir_ayah')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'pendidikan_ayah', fn (Blueprint $table) => $table->string('pendidikan_ayah')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'penghasilan_ayah', fn (Blueprint $table) => $table->string('penghasilan_ayah')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'nik_ayah', fn (Blueprint $table) => $table->string('nik_ayah', 20)->nullable());

        $this->addColumnIfMissing('data_pribadi_siswa', 'tahun_lahir_ibu', fn (Blueprint $table) => $table->integer('tahun_lahir_ibu')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'pendidikan_ibu', fn (Blueprint $table) => $table->string('pendidikan_ibu')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'penghasilan_ibu', fn (Blueprint $table) => $table->string('penghasilan_ibu')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'nik_ibu', fn (Blueprint $table) => $table->string('nik_ibu', 20)->nullable());

        $this->addColumnIfMissing('data_pribadi_siswa', 'tahun_lahir_wali', fn (Blueprint $table) => $table->integer('tahun_lahir_wali')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'pendidikan_wali', fn (Blueprint $table) => $table->string('pendidikan_wali')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'nama_wali', fn (Blueprint $table) => $table->string('nama_wali')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'pekerjaan_wali', fn (Blueprint $table) => $table->string('pekerjaan_wali')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'penghasilan_wali', fn (Blueprint $table) => $table->string('penghasilan_wali')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'nik_wali', fn (Blueprint $table) => $table->string('nik_wali', 20)->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'email_wali', fn (Blueprint $table) => $table->string('email_wali')->nullable());

        $this->addColumnIfMissing('data_pribadi_siswa', 'no_peserta_ujian_nasional', fn (Blueprint $table) => $table->string('no_peserta_ujian_nasional')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'no_seri_ijazah', fn (Blueprint $table) => $table->string('no_seri_ijazah')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'penerima_kip', fn (Blueprint $table) => $table->boolean('penerima_kip')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'nomor_kip', fn (Blueprint $table) => $table->string('nomor_kip')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'nama_di_kip', fn (Blueprint $table) => $table->string('nama_di_kip')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'nomor_kks', fn (Blueprint $table) => $table->string('nomor_kks')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'no_registrasi_akta_lahir', fn (Blueprint $table) => $table->string('no_registrasi_akta_lahir')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'bank', fn (Blueprint $table) => $table->string('bank')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'nomor_rekening_bank', fn (Blueprint $table) => $table->string('nomor_rekening_bank')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'rekening_atas_nama', fn (Blueprint $table) => $table->string('rekening_atas_nama')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'layak_pip', fn (Blueprint $table) => $table->boolean('layak_pip')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'alasan_layak_pip', fn (Blueprint $table) => $table->text('alasan_layak_pip')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'kebutuhan_khusus', fn (Blueprint $table) => $table->string('kebutuhan_khusus')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'lintang', fn (Blueprint $table) => $table->decimal('lintang', 10, 7)->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'bujur', fn (Blueprint $table) => $table->decimal('bujur', 10, 7)->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'no_kk', fn (Blueprint $table) => $table->string('no_kk', 20)->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'lingkar_kepala', fn (Blueprint $table) => $table->decimal('lingkar_kepala', 5, 2)->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'jarak_rumah_km', fn (Blueprint $table) => $table->decimal('jarak_rumah_km', 6, 2)->nullable());

        // Align with model payload fields already used in the app.
        $this->addColumnIfMissing('data_pribadi_siswa', 'npsn_asal', fn (Blueprint $table) => $table->string('npsn_asal')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'alamat_sekolah_asal', fn (Blueprint $table) => $table->text('alamat_sekolah_asal')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'tahun_lulus_asal', fn (Blueprint $table) => $table->integer('tahun_lulus_asal')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'metode_absensi', fn (Blueprint $table) => $table->json('metode_absensi')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'gps_tracking', fn (Blueprint $table) => $table->boolean('gps_tracking')->default(false));
        $this->addColumnIfMissing('data_pribadi_siswa', 'last_tracked_location', fn (Blueprint $table) => $table->json('last_tracked_location')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'email_notifikasi', fn (Blueprint $table) => $table->string('email_notifikasi')->nullable());
        $this->addColumnIfMissing('data_pribadi_siswa', 'notifikasi_settings', fn (Blueprint $table) => $table->json('notifikasi_settings')->nullable());
    }

    private function addEmployeeColumns(): void
    {
        if (!Schema::hasTable('data_kepegawaian')) {
            return;
        }

        // Align with model payload fields already used in the app.
        $this->addColumnIfMissing('data_kepegawaian', 'tempat_lahir', fn (Blueprint $table) => $table->string('tempat_lahir')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'tanggal_lahir', fn (Blueprint $table) => $table->date('tanggal_lahir')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'jenis_kelamin', fn (Blueprint $table) => $table->string('jenis_kelamin', 1)->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'agama', fn (Blueprint $table) => $table->string('agama')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'alamat', fn (Blueprint $table) => $table->text('alamat')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'rt', fn (Blueprint $table) => $table->string('rt', 3)->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'rw', fn (Blueprint $table) => $table->string('rw', 3)->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'kelurahan', fn (Blueprint $table) => $table->string('kelurahan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'kecamatan', fn (Blueprint $table) => $table->string('kecamatan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'kota_kabupaten', fn (Blueprint $table) => $table->string('kota_kabupaten')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'provinsi', fn (Blueprint $table) => $table->string('provinsi')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'kode_pos', fn (Blueprint $table) => $table->string('kode_pos', 10)->nullable());

        $this->addColumnIfMissing('data_kepegawaian', 'nama_dusun', fn (Blueprint $table) => $table->string('nama_dusun')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'alamat_jalan', fn (Blueprint $table) => $table->text('alamat_jalan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'jenis_ptk', fn (Blueprint $table) => $table->string('jenis_ptk')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'tugas_tambahan', fn (Blueprint $table) => $table->string('tugas_tambahan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'sk_cpns', fn (Blueprint $table) => $table->string('sk_cpns')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'tanggal_cpns', fn (Blueprint $table) => $table->date('tanggal_cpns')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'sk_pengangkatan', fn (Blueprint $table) => $table->string('sk_pengangkatan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'tmt_pengangkatan', fn (Blueprint $table) => $table->date('tmt_pengangkatan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'lembaga_pengangkatan', fn (Blueprint $table) => $table->string('lembaga_pengangkatan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'sumber_gaji', fn (Blueprint $table) => $table->string('sumber_gaji')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'nama_ibu_kandung', fn (Blueprint $table) => $table->string('nama_ibu_kandung')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'status_perkawinan', fn (Blueprint $table) => $table->string('status_perkawinan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'status_pernikahan', fn (Blueprint $table) => $table->string('status_pernikahan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'nip_suami_istri', fn (Blueprint $table) => $table->string('nip_suami_istri')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'tmt_pns', fn (Blueprint $table) => $table->date('tmt_pns')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'sudah_lisensi_kepala_sekolah', fn (Blueprint $table) => $table->boolean('sudah_lisensi_kepala_sekolah')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'pernah_diklat_kepengawasan', fn (Blueprint $table) => $table->boolean('pernah_diklat_kepengawasan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'keahlian_braille', fn (Blueprint $table) => $table->boolean('keahlian_braille')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'keahlian_bahasa_isyarat', fn (Blueprint $table) => $table->boolean('keahlian_bahasa_isyarat')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'npwp', fn (Blueprint $table) => $table->string('npwp')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'nama_wajib_pajak', fn (Blueprint $table) => $table->string('nama_wajib_pajak')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'kewarganegaraan', fn (Blueprint $table) => $table->string('kewarganegaraan')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'bank', fn (Blueprint $table) => $table->string('bank')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'nomor_rekening_bank', fn (Blueprint $table) => $table->string('nomor_rekening_bank')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'rekening_atas_nama', fn (Blueprint $table) => $table->string('rekening_atas_nama')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'no_kk', fn (Blueprint $table) => $table->string('no_kk', 20)->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'karpeg', fn (Blueprint $table) => $table->string('karpeg')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'karis_karsu', fn (Blueprint $table) => $table->string('karis_karsu')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'lintang', fn (Blueprint $table) => $table->decimal('lintang', 10, 7)->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'bujur', fn (Blueprint $table) => $table->decimal('bujur', 10, 7)->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'nuks', fn (Blueprint $table) => $table->string('nuks')->nullable());

        $this->addColumnIfMissing('data_kepegawaian', 'sertifikasi', fn (Blueprint $table) => $table->string('sertifikasi')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'tahun_sertifikasi', fn (Blueprint $table) => $table->integer('tahun_sertifikasi')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'nomor_sertifikasi', fn (Blueprint $table) => $table->string('nomor_sertifikasi')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'tmt_cpns', fn (Blueprint $table) => $table->date('tmt_cpns')->nullable());

        $this->addColumnIfMissing('data_kepegawaian', 'metode_absensi', fn (Blueprint $table) => $table->json('metode_absensi')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'gps_tracking', fn (Blueprint $table) => $table->boolean('gps_tracking')->default(false));
        $this->addColumnIfMissing('data_kepegawaian', 'last_tracked_location', fn (Blueprint $table) => $table->json('last_tracked_location')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'jam_masuk', fn (Blueprint $table) => $table->time('jam_masuk')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'jam_pulang', fn (Blueprint $table) => $table->time('jam_pulang')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'hari_kerja', fn (Blueprint $table) => $table->json('hari_kerja')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'email_notifikasi', fn (Blueprint $table) => $table->string('email_notifikasi')->nullable());
        $this->addColumnIfMissing('data_kepegawaian', 'notifikasi_settings', fn (Blueprint $table) => $table->json('notifikasi_settings')->nullable());
    }

    private function dropStudentColumns(): void
    {
        $this->dropColumnsIfExists('data_pribadi_siswa', [
            'dusun',
            'jenis_tinggal',
            'alat_transportasi',
            'skhun',
            'penerima_kps',
            'no_kps',
            'tahun_lahir_ayah',
            'pendidikan_ayah',
            'penghasilan_ayah',
            'nik_ayah',
            'tahun_lahir_ibu',
            'pendidikan_ibu',
            'penghasilan_ibu',
            'nik_ibu',
            'tahun_lahir_wali',
            'pendidikan_wali',
            'nama_wali',
            'pekerjaan_wali',
            'penghasilan_wali',
            'nik_wali',
            'email_wali',
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
            'kebutuhan_khusus',
            'lintang',
            'bujur',
            'no_kk',
            'lingkar_kepala',
            'jarak_rumah_km',
            'npsn_asal',
            'alamat_sekolah_asal',
            'tahun_lulus_asal',
            'metode_absensi',
            'gps_tracking',
            'last_tracked_location',
            'email_notifikasi',
            'notifikasi_settings',
        ]);
    }

    private function dropEmployeeColumns(): void
    {
        $this->dropColumnsIfExists('data_kepegawaian', [
            'tempat_lahir',
            'tanggal_lahir',
            'jenis_kelamin',
            'agama',
            'alamat',
            'rt',
            'rw',
            'kelurahan',
            'kecamatan',
            'kota_kabupaten',
            'provinsi',
            'kode_pos',
            'nama_dusun',
            'alamat_jalan',
            'jenis_ptk',
            'tugas_tambahan',
            'sk_cpns',
            'tanggal_cpns',
            'sk_pengangkatan',
            'tmt_pengangkatan',
            'lembaga_pengangkatan',
            'sumber_gaji',
            'nama_ibu_kandung',
            'status_perkawinan',
            'status_pernikahan',
            'nip_suami_istri',
            'tmt_pns',
            'sudah_lisensi_kepala_sekolah',
            'pernah_diklat_kepengawasan',
            'keahlian_braille',
            'keahlian_bahasa_isyarat',
            'npwp',
            'nama_wajib_pajak',
            'kewarganegaraan',
            'bank',
            'nomor_rekening_bank',
            'rekening_atas_nama',
            'no_kk',
            'karpeg',
            'karis_karsu',
            'lintang',
            'bujur',
            'nuks',
            'sertifikasi',
            'tahun_sertifikasi',
            'nomor_sertifikasi',
            'tmt_cpns',
            'metode_absensi',
            'gps_tracking',
            'last_tracked_location',
            'jam_masuk',
            'jam_pulang',
            'hari_kerja',
            'email_notifikasi',
            'notifikasi_settings',
        ]);
    }

    private function addColumnIfMissing(string $tableName, string $columnName, callable $definition): void
    {
        if (Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }

    /**
     * @param array<int, string> $columns
     */
    private function dropColumnsIfExists(string $tableName, array $columns): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        foreach ($columns as $columnName) {
            if (!Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($columnName) {
                $table->dropColumn($columnName);
            });
        }
    }
};
