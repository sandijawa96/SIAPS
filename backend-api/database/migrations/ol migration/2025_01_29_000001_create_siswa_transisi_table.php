<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa_transisi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('users')->onDelete('cascade');
            $table->enum('type', ['naik_kelas', 'pindah_kelas', 'lulus', 'keluar', 'aktif_kembali']);
            $table->foreignId('kelas_asal_id')->nullable()->constrained('kelas')->onDelete('set null');
            $table->foreignId('kelas_tujuan_id')->nullable()->constrained('kelas')->onDelete('set null');
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->onDelete('cascade');
            $table->date('tanggal_transisi');
            $table->text('keterangan')->nullable();
            $table->foreignId('processed_by')->constrained('users')->onDelete('cascade'); // User yang memproses
            $table->boolean('is_undone')->default(false); // Apakah sudah di-undo
            $table->boolean('can_undo')->default(true); // Apakah bisa di-undo
            $table->foreignId('undone_by')->nullable()->constrained('users')->onDelete('set null'); // User yang undo
            $table->timestamp('undone_at')->nullable(); // Kapan di-undo
            $table->text('undo_reason')->nullable(); // Alasan undo
            $table->timestamps();

            // Indexes
            $table->index(['siswa_id', 'tanggal_transisi']);
            $table->index(['type', 'tanggal_transisi']);
            $table->index('tahun_ajaran_id');
            $table->index('is_undone');
            $table->index('can_undo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_transisi');
    }
};
