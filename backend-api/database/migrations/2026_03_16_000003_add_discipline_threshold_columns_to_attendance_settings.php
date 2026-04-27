<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_settings', 'total_violation_minutes_semester_limit')) {
                $table->integer('total_violation_minutes_semester_limit')
                    ->nullable()
                    ->after('violation_percentage_threshold');
            }

            if (!Schema::hasColumn('attendance_settings', 'alpha_days_semester_limit')) {
                $table->integer('alpha_days_semester_limit')
                    ->nullable()
                    ->after('total_violation_minutes_semester_limit');
            }

            if (!Schema::hasColumn('attendance_settings', 'late_minutes_monthly_limit')) {
                $table->integer('late_minutes_monthly_limit')
                    ->nullable()
                    ->after('alpha_days_semester_limit');
            }

            if (!Schema::hasColumn('attendance_settings', 'notify_wali_kelas_on_alpha_limit')) {
                $table->boolean('notify_wali_kelas_on_alpha_limit')
                    ->nullable()
                    ->after('late_minutes_monthly_limit');
            }

            if (!Schema::hasColumn('attendance_settings', 'notify_kesiswaan_on_alpha_limit')) {
                $table->boolean('notify_kesiswaan_on_alpha_limit')
                    ->nullable()
                    ->after('notify_wali_kelas_on_alpha_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            foreach ([
                'notify_kesiswaan_on_alpha_limit',
                'notify_wali_kelas_on_alpha_limit',
                'late_minutes_monthly_limit',
                'alpha_days_semester_limit',
                'total_violation_minutes_semester_limit',
            ] as $column) {
                if (Schema::hasColumn('attendance_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
