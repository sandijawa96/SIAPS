<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_settings', 'live_tracking_retention_days')) {
                $table->unsignedInteger('live_tracking_retention_days')->nullable()->after('discipline_alerts_run_time');
            }

            if (!Schema::hasColumn('attendance_settings', 'live_tracking_cleanup_time')) {
                $table->string('live_tracking_cleanup_time', 5)->nullable()->after('live_tracking_retention_days');
            }

            if (!Schema::hasColumn('attendance_settings', 'face_result_when_template_missing')) {
                $table->string('face_result_when_template_missing', 32)->nullable()->after('live_tracking_cleanup_time');
            }

            if (!Schema::hasColumn('attendance_settings', 'face_reject_to_manual_review')) {
                $table->boolean('face_reject_to_manual_review')->nullable()->after('face_result_when_template_missing');
            }

            if (!Schema::hasColumn('attendance_settings', 'face_skip_when_photo_missing')) {
                $table->boolean('face_skip_when_photo_missing')->nullable()->after('face_reject_to_manual_review');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            foreach ([
                'face_skip_when_photo_missing',
                'face_reject_to_manual_review',
                'face_result_when_template_missing',
                'live_tracking_cleanup_time',
                'live_tracking_retention_days',
            ] as $column) {
                if (Schema::hasColumn('attendance_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
