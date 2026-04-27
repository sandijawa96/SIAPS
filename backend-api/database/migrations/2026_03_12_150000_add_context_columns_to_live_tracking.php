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
        Schema::table('live_tracking', function (Blueprint $table) {
            if (!Schema::hasColumn('live_tracking', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->after('heading');
                $table->index('location_id');
            }

            if (!Schema::hasColumn('live_tracking', 'location_name')) {
                $table->string('location_name')->nullable()->after('location_id');
            }

            if (!Schema::hasColumn('live_tracking', 'device_source')) {
                $table->string('device_source', 32)->nullable()->after('location_name');
                $table->index('device_source');
            }

            if (!Schema::hasColumn('live_tracking', 'gps_quality_status')) {
                $table->string('gps_quality_status', 32)->nullable()->after('device_source');
                $table->index('gps_quality_status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('live_tracking', function (Blueprint $table) {
            if (Schema::hasColumn('live_tracking', 'gps_quality_status')) {
                $table->dropIndex(['gps_quality_status']);
                $table->dropColumn('gps_quality_status');
            }

            if (Schema::hasColumn('live_tracking', 'device_source')) {
                $table->dropIndex(['device_source']);
                $table->dropColumn('device_source');
            }

            if (Schema::hasColumn('live_tracking', 'location_name')) {
                $table->dropColumn('location_name');
            }

            if (Schema::hasColumn('live_tracking', 'location_id')) {
                $table->dropIndex(['location_id']);
                $table->dropColumn('location_id');
            }
        });
    }
};
