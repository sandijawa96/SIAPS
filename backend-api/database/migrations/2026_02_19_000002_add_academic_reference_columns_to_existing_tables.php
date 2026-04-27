<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('guru_mata_pelajaran', function (Blueprint $table) {
            if (!Schema::hasColumn('guru_mata_pelajaran', 'mata_pelajaran_id')) {
                $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->after('mata_pelajaran');
                $table->foreign('mata_pelajaran_id')
                    ->references('id')
                    ->on('mata_pelajaran')
                    ->nullOnDelete();
            }
        });

        Schema::table('jadwal_mengajar', function (Blueprint $table) {
            if (!Schema::hasColumn('jadwal_mengajar', 'mata_pelajaran_id')) {
                $table->unsignedBigInteger('mata_pelajaran_id')->nullable()->after('mata_pelajaran');
                $table->foreign('mata_pelajaran_id')
                    ->references('id')
                    ->on('mata_pelajaran')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('jadwal_mengajar', 'tahun_ajaran_id')) {
                $table->unsignedBigInteger('tahun_ajaran_id')->nullable()->after('kelas_id');
                $table->foreign('tahun_ajaran_id')
                    ->references('id')
                    ->on('tahun_ajaran')
                    ->nullOnDelete();
            }

            if (!Schema::hasColumn('jadwal_mengajar', 'semester')) {
                $table->enum('semester', ['ganjil', 'genap', 'full'])->nullable()->after('tahun_ajaran_id');
            }

            if (!Schema::hasColumn('jadwal_mengajar', 'jam_ke')) {
                $table->unsignedTinyInteger('jam_ke')->nullable()->after('jam_selesai');
            }

            if (!Schema::hasColumn('jadwal_mengajar', 'status')) {
                $table->enum('status', ['draft', 'published', 'archived'])->default('published')->after('ruangan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jadwal_mengajar', function (Blueprint $table) {
            if (Schema::hasColumn('jadwal_mengajar', 'mata_pelajaran_id')) {
                $table->dropForeign(['mata_pelajaran_id']);
                $table->dropColumn('mata_pelajaran_id');
            }

            if (Schema::hasColumn('jadwal_mengajar', 'tahun_ajaran_id')) {
                $table->dropForeign(['tahun_ajaran_id']);
                $table->dropColumn('tahun_ajaran_id');
            }

            if (Schema::hasColumn('jadwal_mengajar', 'semester')) {
                $table->dropColumn('semester');
            }

            if (Schema::hasColumn('jadwal_mengajar', 'jam_ke')) {
                $table->dropColumn('jam_ke');
            }

            if (Schema::hasColumn('jadwal_mengajar', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('guru_mata_pelajaran', function (Blueprint $table) {
            if (Schema::hasColumn('guru_mata_pelajaran', 'mata_pelajaran_id')) {
                $table->dropForeign(['mata_pelajaran_id']);
                $table->dropColumn('mata_pelajaran_id');
            }
        });
    }
};

