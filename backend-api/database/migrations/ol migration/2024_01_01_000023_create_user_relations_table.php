<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pivot table untuk siswa dan kelas
        Schema::create('kelas_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->onDelete('cascade');
            $table->foreignId('siswa_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->onDelete('cascade');
            $table->date('tanggal_masuk')->nullable();
            $table->date('tanggal_keluar')->nullable();
            $table->enum('status', ['aktif', 'pindah', 'lulus', 'keluar'])->default('aktif');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['kelas_id', 'tahun_ajaran_id']);
            $table->index('siswa_id');
            $table->index('status');
            
            // Unique constraint
            $table->unique(['kelas_id', 'siswa_id', 'tahun_ajaran_id']);
        });

        // Pivot table untuk guru dan mata pelajaran
        Schema::create('guru_mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->string('mata_pelajaran');
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->onDelete('cascade');
            $table->foreignId('tahun_ajaran_id')->constrained('tahun_ajaran')->onDelete('cascade');
            $table->integer('jam_per_minggu')->default(0);
            $table->enum('status', ['aktif', 'tidak_aktif'])->default('aktif');
            $table->timestamps();

            // Indexes
            $table->index(['guru_id', 'tahun_ajaran_id']);
            $table->index('mata_pelajaran');
            $table->index('kelas_id');
            $table->index('status');
        });

        // Table untuk jadwal mengajar
        Schema::create('jadwal_mengajar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('kelas_id')->constrained('kelas')->onDelete('cascade');
            $table->string('mata_pelajaran');
            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('ruangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['guru_id', 'hari']);
            $table->index(['kelas_id', 'hari']);
            $table->index('mata_pelajaran');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_mengajar');
        Schema::dropIfExists('guru_mata_pelajaran');
        Schema::dropIfExists('kelas_siswa');
    }
};
