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
        Schema::create('event_akademik', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->onDelete('cascade');
            $table->foreignId('periode_akademik_id')->nullable()->constrained('periode_akademik')->onDelete('set null');
            $table->string('nama', 200);
            $table->enum('jenis', ['ujian', 'libur', 'kegiatan', 'deadline', 'rapat', 'pelatihan']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->time('waktu_mulai')->nullable();
            $table->time('waktu_selesai')->nullable();
            $table->foreignId('tingkat_id')->nullable()->constrained('tingkat')->onDelete('set null');
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->onDelete('set null');
            $table->boolean('is_wajib')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('deskripsi')->nullable();
            $table->string('lokasi')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['tahun_ajaran_id', 'jenis']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
            $table->index('is_active');
            $table->index('is_wajib');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_akademik');
    }
};
