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
        Schema::table('users', function (Blueprint $table) {
            // Hapus kolom-kolom yang terkait dengan attendance yang sudah dipindah ke AttendanceSettingsService
            $table->dropColumn([
                'jam_masuk',
                'jam_pulang',
                'wajib_absen',
                'gps_tracking',
                'mengikuti_kaldik',
                'lokasi_default'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Restore kolom jika rollback
            $table->time('jam_masuk')->nullable()->after('is_active');
            $table->time('jam_pulang')->nullable()->after('jam_masuk');
            $table->boolean('wajib_absen')->default(true)->after('jam_pulang');
            $table->boolean('gps_tracking')->default(true)->after('wajib_absen');
            $table->boolean('mengikuti_kaldik')->default(false)->after('gps_tracking');
            $table->boolean('lokasi_default')->default(true)->after('mengikuti_kaldik');
        });
    }
};
