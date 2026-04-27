<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_settings')) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_settings', 'live_tracking_enabled')) {
                $table->boolean('live_tracking_enabled')
                    ->nullable()
                    ->after('live_tracking_min_distance_meters');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_settings')) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_settings', 'live_tracking_enabled')) {
                $table->dropColumn('live_tracking_enabled');
            }
        });
    }
};
