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
        Schema::table('attendance_settings', function (Blueprint $table) {
            // Add radius and GPS accuracy fields for attendance schema
            if (!Schema::hasColumn('attendance_settings', 'radius_absensi')) {
                $table->integer('radius_absensi')->default(100)->after('lokasi_gps_ids')
                    ->comment('Radius absensi dalam meter (override dari lokasi GPS)');
            }

            if (!Schema::hasColumn('attendance_settings', 'gps_accuracy')) {
                $table->integer('gps_accuracy')->default(20)->after('radius_absensi')
                    ->comment('Akurasi GPS minimum yang diterima dalam meter');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_settings', 'radius_absensi')) {
                $table->dropColumn('radius_absensi');
            }

            if (Schema::hasColumn('attendance_settings', 'gps_accuracy')) {
                $table->dropColumn('gps_accuracy');
            }
        });
    }
};
