<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lokasi_gps', function (Blueprint $table) {
            // Add new fields if they don't exist
            if (!Schema::hasColumn('lokasi_gps', 'warna_marker')) {
                $table->string('warna_marker', 7)->default('#2196F3')->after('is_active');
            }

            if (!Schema::hasColumn('lokasi_gps', 'roles')) {
                $table->text('roles')->nullable()->after('warna_marker');
            }

            if (!Schema::hasColumn('lokasi_gps', 'waktu_mulai')) {
                $table->string('waktu_mulai', 5)->default('06:00')->after('roles');
            }

            if (!Schema::hasColumn('lokasi_gps', 'waktu_selesai')) {
                $table->string('waktu_selesai', 5)->default('18:00')->after('waktu_mulai');
            }

            // Update hari_aktif to text if it's json
            if (Schema::hasColumn('lokasi_gps', 'hari_aktif')) {
                $table->text('hari_aktif')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('lokasi_gps', function (Blueprint $table) {
            $table->dropColumn(['warna_marker', 'roles', 'waktu_mulai', 'waktu_selesai']);
        });
    }
};
