<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('data_pribadi_siswa', 'no_hp_ayah')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->string('no_hp_ayah', 15)->nullable()->after('pekerjaan_ayah');
            });
        }
        if (!Schema::hasColumn('data_pribadi_siswa', 'no_hp_ibu')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->string('no_hp_ibu', 15)->nullable()->after('pekerjaan_ibu');
            });
        }
        if (!Schema::hasColumn('data_pribadi_siswa', 'email_ayah')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->string('email_ayah')->nullable()->after('no_hp_ayah');
            });
        }
        if (!Schema::hasColumn('data_pribadi_siswa', 'email_ibu')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->string('email_ibu')->nullable()->after('no_hp_ibu');
            });
        }
        if (!Schema::hasColumn('data_pribadi_siswa', 'tahun_masuk')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->year('tahun_masuk')->nullable()->after('alamat_wali');
            });
        }
        if (!Schema::hasColumn('data_pribadi_siswa', 'status')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->string('status')->default('aktif')->after('tahun_masuk');
            });
        }
    }

    public function down(): void
    {
        Schema::table('data_pribadi_siswa', function (Blueprint $table) {
            if (Schema::hasColumn('data_pribadi_siswa', 'no_hp_ayah')) {
                $table->dropColumn('no_hp_ayah');
            }
            if (Schema::hasColumn('data_pribadi_siswa', 'no_hp_ibu')) {
                $table->dropColumn('no_hp_ibu');
            }
            if (Schema::hasColumn('data_pribadi_siswa', 'email_ayah')) {
                $table->dropColumn('email_ayah');
            }
            if (Schema::hasColumn('data_pribadi_siswa', 'email_ibu')) {
                $table->dropColumn('email_ibu');
            }
            if (Schema::hasColumn('data_pribadi_siswa', 'tahun_masuk')) {
                $table->dropColumn('tahun_masuk');
            }
            if (Schema::hasColumn('data_pribadi_siswa', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
