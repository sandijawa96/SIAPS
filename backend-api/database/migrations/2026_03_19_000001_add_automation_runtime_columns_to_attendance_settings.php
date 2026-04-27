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
            if (!Schema::hasColumn('attendance_settings', 'auto_alpha_enabled')) {
                $table->boolean('auto_alpha_enabled')
                    ->default(true)
                    ->after('notify_kesiswaan_on_alpha_limit');
            }

            if (!Schema::hasColumn('attendance_settings', 'auto_alpha_run_time')) {
                $table->string('auto_alpha_run_time', 5)
                    ->default('23:50')
                    ->after('auto_alpha_enabled');
            }

            if (!Schema::hasColumn('attendance_settings', 'discipline_alerts_enabled')) {
                $table->boolean('discipline_alerts_enabled')
                    ->default(true)
                    ->after('auto_alpha_run_time');
            }

            if (!Schema::hasColumn('attendance_settings', 'discipline_alerts_run_time')) {
                $table->string('discipline_alerts_run_time', 5)
                    ->default('23:57')
                    ->after('discipline_alerts_enabled');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_settings')) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            foreach ([
                'discipline_alerts_run_time',
                'discipline_alerts_enabled',
                'auto_alpha_run_time',
                'auto_alpha_enabled',
            ] as $column) {
                if (Schema::hasColumn('attendance_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
