<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('data_pribadi_siswa')) {
            return;
        }

        Schema::table('data_pribadi_siswa', function (Blueprint $table) {
            if (!Schema::hasColumn('data_pribadi_siswa', 'tanggal_masuk_sekolah')) {
                $table->date('tanggal_masuk_sekolah')->nullable()->after('tahun_masuk');
            }

            if (!Schema::hasColumn('data_pribadi_siswa', 'kelas_awal_id')) {
                $table->unsignedBigInteger('kelas_awal_id')->nullable()->after('tanggal_masuk_sekolah');
            }

            if (!Schema::hasColumn('data_pribadi_siswa', 'tahun_ajaran_awal_id')) {
                $table->unsignedBigInteger('tahun_ajaran_awal_id')->nullable()->after('kelas_awal_id');
            }

            if (!Schema::hasColumn('data_pribadi_siswa', 'tanggal_masuk_kelas_awal')) {
                $table->date('tanggal_masuk_kelas_awal')->nullable()->after('tahun_ajaran_awal_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('data_pribadi_siswa')) {
            return;
        }

        Schema::table('data_pribadi_siswa', function (Blueprint $table) {
            foreach ([
                'tanggal_masuk_kelas_awal',
                'tahun_ajaran_awal_id',
                'kelas_awal_id',
                'tanggal_masuk_sekolah',
            ] as $column) {
                if (Schema::hasColumn('data_pribadi_siswa', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
