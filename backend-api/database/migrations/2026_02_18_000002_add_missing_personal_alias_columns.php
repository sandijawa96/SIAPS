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
        if (Schema::hasTable('data_pribadi_siswa') && !Schema::hasColumn('data_pribadi_siswa', 'nama_wali')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->string('nama_wali')->nullable();
            });
        }

        if (Schema::hasTable('data_kepegawaian') && !Schema::hasColumn('data_kepegawaian', 'status_pernikahan')) {
            Schema::table('data_kepegawaian', function (Blueprint $table) {
                $table->string('status_pernikahan')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('data_pribadi_siswa') && Schema::hasColumn('data_pribadi_siswa', 'nama_wali')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->dropColumn('nama_wali');
            });
        }

        if (Schema::hasTable('data_kepegawaian') && Schema::hasColumn('data_kepegawaian', 'status_pernikahan')) {
            Schema::table('data_kepegawaian', function (Blueprint $table) {
                $table->dropColumn('status_pernikahan');
            });
        }
    }
};
