<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('data_pribadi_siswa', 'status')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->after('pekerjaan_ibu');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('data_pribadi_siswa', 'status')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
