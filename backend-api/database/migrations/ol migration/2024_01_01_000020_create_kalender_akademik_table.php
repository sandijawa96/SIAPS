<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kalender_akademik', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kegiatan');
            $table->text('deskripsi')->nullable();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->enum('jenis_kegiatan', ['libur', 'ujian', 'kegiatan_sekolah', 'hari_besar', 'rapat']);
            $table->enum('status_absensi', ['libur', 'wajib_hadir', 'opsional'])->default('wajib_hadir');
            $table->json('target_peserta')->nullable(); // role atau kelas yang terlibat
            $table->string('lokasi')->nullable();
            $table->string('warna', 7)->default('#3498db'); // untuk tampilan kalender
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
            $table->index('jenis_kegiatan');
            $table->index('status_absensi');
            $table->index('is_active');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kalender_akademik');
    }
};
