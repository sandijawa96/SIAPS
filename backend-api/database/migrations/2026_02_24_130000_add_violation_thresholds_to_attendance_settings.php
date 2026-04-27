<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_settings', 'violation_minutes_threshold')) {
                $table->integer('violation_minutes_threshold')
                    ->default(480)
                    ->after('minimal_open_time_siswa')
                    ->comment('Batas menit pelanggaran per periode laporan');
            }

            if (!Schema::hasColumn('attendance_settings', 'violation_percentage_threshold')) {
                $table->decimal('violation_percentage_threshold', 5, 2)
                    ->default(10.00)
                    ->after('violation_minutes_threshold')
                    ->comment('Batas persentase pelanggaran per periode laporan');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_settings', 'violation_percentage_threshold')) {
                $table->dropColumn('violation_percentage_threshold');
            }

            if (Schema::hasColumn('attendance_settings', 'violation_minutes_threshold')) {
                $table->dropColumn('violation_minutes_threshold');
            }
        });
    }
};

