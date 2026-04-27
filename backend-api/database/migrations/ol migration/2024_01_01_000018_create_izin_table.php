<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('izin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->enum('jenis_izin', ['sakit', 'izin', 'cuti', 'dinas_luar']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->text('alasan');
            $table->string('dokumen_pendukung')->nullable(); // file surat dokter, dll
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('catatan_approval')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_emergency')->default(false);
            $table->json('contact_info')->nullable(); // kontak darurat saat izin
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('user_id');
            $table->index('jenis_izin');
            $table->index('status');
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
            $table->index('approved_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin');
    }
};
