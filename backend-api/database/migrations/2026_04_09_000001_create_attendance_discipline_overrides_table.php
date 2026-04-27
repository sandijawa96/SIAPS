<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_discipline_overrides')) {
            return;
        }

        Schema::create('attendance_discipline_overrides', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type', 20);
            $table->unsignedBigInteger('target_tingkat_id')->nullable();
            $table->unsignedBigInteger('target_kelas_id')->nullable();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('discipline_thresholds_enabled')->default(true);
            $table->integer('total_violation_minutes_semester_limit')->default(1200);
            $table->integer('alpha_days_semester_limit')->default(8);
            $table->integer('late_minutes_monthly_limit')->default(120);
            $table->string('semester_total_violation_mode', 20)->default('monitor_only');
            $table->boolean('notify_wali_kelas_on_total_violation_limit')->default(false);
            $table->boolean('notify_kesiswaan_on_total_violation_limit')->default(false);
            $table->string('semester_alpha_mode', 20)->default('alertable');
            $table->string('monthly_late_mode', 20)->default('monitor_only');
            $table->boolean('notify_wali_kelas_on_late_limit')->default(false);
            $table->boolean('notify_kesiswaan_on_late_limit')->default(false);
            $table->boolean('notify_wali_kelas_on_alpha_limit')->default(true);
            $table->boolean('notify_kesiswaan_on_alpha_limit')->default(true);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'is_active'], 'attendance_discipline_overrides_scope_active_idx');
            $table->index(['target_tingkat_id', 'is_active'], 'attendance_discipline_overrides_tingkat_idx');
            $table->index(['target_kelas_id', 'is_active'], 'attendance_discipline_overrides_kelas_idx');
            $table->index(['target_user_id', 'is_active'], 'attendance_discipline_overrides_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_discipline_overrides');
    }
};
