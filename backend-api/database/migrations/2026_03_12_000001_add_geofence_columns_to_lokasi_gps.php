<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lokasi_gps', function (Blueprint $table) {
            if (!Schema::hasColumn('lokasi_gps', 'geofence_type')) {
                $table->string('geofence_type', 20)
                    ->default('circle')
                    ->after('radius');
            }

            if (!Schema::hasColumn('lokasi_gps', 'geofence_geojson')) {
                $table->json('geofence_geojson')
                    ->nullable()
                    ->after('geofence_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lokasi_gps', function (Blueprint $table) {
            if (Schema::hasColumn('lokasi_gps', 'geofence_geojson')) {
                $table->dropColumn('geofence_geojson');
            }

            if (Schema::hasColumn('lokasi_gps', 'geofence_type')) {
                $table->dropColumn('geofence_type');
            }
        });
    }
};
