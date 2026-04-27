<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_pribadi_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Data Kontak
            $table->string('no_hp_siswa', 15)->nullable();
            $table->string('no_telepon_rumah', 15)->nullable();
            $table->string('no_hp_ortu', 15)->nullable();
            
            // Data Keluarga
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('pekerjaan_ayah')->nullable();
            $table->string('pekerjaan_ibu')->nullable();
            $table->string('penghasilan_ortu')->nullable();
            $table->integer('anak_ke')->nullable();
            $table->integer('jumlah_saudara')->nullable();
            
            // Data Fisik
            $table->string('golongan_darah', 3)->nullable();
            $table->integer('tinggi_badan')->nullable();
            $table->integer('berat_badan')->nullable();
            $table->text('riwayat_penyakit')->nullable();
            
            // Data Akademik
            $table->string('asal_sekolah')->nullable();
            $table->string('tahun_lulus_sd')->nullable();
            $table->decimal('nilai_un_sd', 5, 2)->nullable();
            $table->text('prestasi')->nullable();
            $table->text('hobi')->nullable();
            $table->text('cita_cita')->nullable();
            
            // Data Tambahan
            $table->enum('status_pernikahan_ortu', ['menikah', 'cerai_hidup', 'cerai_mati'])->default('menikah');
            $table->string('wali_siswa')->nullable();
            $table->string('hubungan_wali')->nullable();
            $table->string('no_hp_wali', 15)->nullable();
            $table->text('alamat_wali')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('user_id');
            $table->index('nama_ayah');
            $table->index('nama_ibu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_pribadi_siswa');
    }
};
