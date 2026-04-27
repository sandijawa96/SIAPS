<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_kepegawaian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Data Kontak
            $table->string('no_hp', 15)->nullable();
            $table->string('no_telepon_kantor', 15)->nullable();
            
            // Data Kepegawaian
            $table->enum('status_kepegawaian', ['PNS', 'PPPK', 'Honorer', 'Kontrak', 'Tidak Ada'])->nullable();
            $table->string('nip_lama', 18)->nullable();
            $table->string('nuptk', 16)->nullable();
            $table->string('jabatan')->nullable();
            $table->json('sub_jabatan')->nullable();
            $table->string('pangkat_golongan')->nullable();
            $table->string('tmt_pangkat')->nullable();
            $table->date('tanggal_mulai_kerja')->nullable();
            $table->string('masa_kerja')->nullable();
            
            // Data Pendidikan
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('jurusan')->nullable();
            $table->string('universitas')->nullable();
            $table->string('tahun_lulus', 4)->nullable();
            $table->string('no_ijazah')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            
            // Data Mengajar
            $table->string('bidang_studi')->nullable();
            $table->json('mata_pelajaran')->nullable();
            $table->integer('jam_mengajar_per_minggu')->nullable();
            $table->json('kelas_yang_diajar')->nullable();
            
            // Data Keluarga
            $table->string('nama_pasangan')->nullable();
            $table->string('pekerjaan_pasangan')->nullable();
            $table->integer('jumlah_anak')->nullable();
            $table->json('data_anak')->nullable();
            
            // Data Tambahan
            $table->text('alamat_domisili')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('keterangan')->nullable();
            $table->json('sertifikat')->nullable();
            $table->json('pelatihan')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('user_id');
            $table->index('status_kepegawaian');
            $table->index('jabatan');
            $table->index('bidang_studi');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_kepegawaian');
    }
};
