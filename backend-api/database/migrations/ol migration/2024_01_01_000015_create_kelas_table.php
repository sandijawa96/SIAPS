<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kelas');
            $table->foreignId('tingkat_id')->constrained('tingkat')->onDelete('cascade');
            $table->string('jurusan')->nullable(); // IPA/IPS/Bahasa, etc
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->onDelete('cascade');
            $table->foreignId('wali_kelas_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('kapasitas')->default(0);
            $table->integer('jumlah_siswa')->default(0);
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['tahun_ajaran_id', 'is_active']);
            $table->index('wali_kelas_id');
            $table->index('tingkat_id');
            $table->index('jurusan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
