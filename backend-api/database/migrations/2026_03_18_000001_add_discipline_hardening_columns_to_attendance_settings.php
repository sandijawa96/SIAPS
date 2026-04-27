<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_settings')) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_settings', 'discipline_thresholds_enabled')) {
                $table->boolean('discipline_thresholds_enabled')
                    ->default(true)
                    ->after('late_minutes_monthly_limit');
            }

            if (!Schema::hasColumn('attendance_settings', 'semester_total_violation_mode')) {
                $table->string('semester_total_violation_mode', 30)
                    ->default('monitor_only')
                    ->after('discipline_thresholds_enabled');
            }

            if (!Schema::hasColumn('attendance_settings', 'notify_wali_kelas_on_total_violation_limit')) {
                $table->boolean('notify_wali_kelas_on_total_violation_limit')
                    ->default(false)
                    ->after('semester_total_violation_mode');
            }

            if (!Schema::hasColumn('attendance_settings', 'notify_kesiswaan_on_total_violation_limit')) {
                $table->boolean('notify_kesiswaan_on_total_violation_limit')
                    ->default(false)
                    ->after('notify_wali_kelas_on_total_violation_limit');
            }

            if (!Schema::hasColumn('attendance_settings', 'semester_alpha_mode')) {
                $table->string('semester_alpha_mode', 30)
                    ->default('alertable')
                    ->after('notify_kesiswaan_on_total_violation_limit');
            }

            if (!Schema::hasColumn('attendance_settings', 'monthly_late_mode')) {
                $table->string('monthly_late_mode', 30)
                    ->default('monitor_only')
                    ->after('semester_alpha_mode');
            }

            if (!Schema::hasColumn('attendance_settings', 'notify_wali_kelas_on_late_limit')) {
                $table->boolean('notify_wali_kelas_on_late_limit')
                    ->default(false)
                    ->after('monthly_late_mode');
            }

            if (!Schema::hasColumn('attendance_settings', 'notify_kesiswaan_on_late_limit')) {
                $table->boolean('notify_kesiswaan_on_late_limit')
                    ->default(false)
                    ->after('notify_wali_kelas_on_late_limit');
            }
        });

        DB::table('attendance_settings')
            ->orderBy('id')
            ->get([
                'id',
                'total_violation_minutes_semester_limit',
                'alpha_days_semester_limit',
                'late_minutes_monthly_limit',
                'notify_wali_kelas_on_alpha_limit',
                'notify_kesiswaan_on_alpha_limit',
            ])
            ->each(function ($row): void {
                $enabled = $row->total_violation_minutes_semester_limit !== null
                    || $row->alpha_days_semester_limit !== null
                    || $row->late_minutes_monthly_limit !== null
                    || $row->notify_wali_kelas_on_alpha_limit !== null
                    || $row->notify_kesiswaan_on_alpha_limit !== null;

                DB::table('attendance_settings')
                    ->where('id', (int) $row->id)
                    ->update([
                        'discipline_thresholds_enabled' => $enabled,
                        'semester_total_violation_mode' => 'monitor_only',
                        'semester_alpha_mode' => 'alertable',
                        'monthly_late_mode' => 'monitor_only',
                        'notify_wali_kelas_on_total_violation_limit' => false,
                        'notify_kesiswaan_on_total_violation_limit' => false,
                        'notify_wali_kelas_on_late_limit' => false,
                        'notify_kesiswaan_on_late_limit' => false,
                    ]);
            });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_settings')) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            foreach ([
                'notify_kesiswaan_on_late_limit',
                'notify_wali_kelas_on_late_limit',
                'monthly_late_mode',
                'semester_alpha_mode',
                'notify_kesiswaan_on_total_violation_limit',
                'notify_wali_kelas_on_total_violation_limit',
                'semester_total_violation_mode',
                'discipline_thresholds_enabled',
            ] as $column) {
                if (Schema::hasColumn('attendance_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
