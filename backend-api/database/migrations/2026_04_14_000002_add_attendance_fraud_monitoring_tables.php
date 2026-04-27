<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttendanceFraudMonitoringTables extends Migration
{
    public function up()
    {
        Schema::table('absensi', function (Blueprint $table) {
            if (!Schema::hasColumn('absensi', 'validation_status')) {
                $table->string('validation_status', 20)->default('valid')->index();
            }

            if (!Schema::hasColumn('absensi', 'risk_level')) {
                $table->string('risk_level', 20)->default('low')->index();
            }

            if (!Schema::hasColumn('absensi', 'risk_score')) {
                $table->unsignedInteger('risk_score')->default(0);
            }

            if (!Schema::hasColumn('absensi', 'fraud_flags_count')) {
                $table->unsignedInteger('fraud_flags_count')->default(0);
            }

            if (!Schema::hasColumn('absensi', 'fraud_decision_reason')) {
                $table->text('fraud_decision_reason')->nullable();
            }

            if (!Schema::hasColumn('absensi', 'fraud_last_assessed_at')) {
                $table->timestamp('fraud_last_assessed_at')->nullable();
            }
        });

        Schema::create('attendance_fraud_assessments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('attendance_id')->nullable()->index();
            $table->unsignedBigInteger('kelas_id')->nullable()->index();
            $table->date('assessment_date')->index();
            $table->string('source', 50)->default('attendance_submit')->index();
            $table->string('attempt_type', 20)->nullable()->index();
            $table->string('rollout_mode', 30)->default('warning_mode')->index();
            $table->string('validation_status', 20)->default('valid')->index();
            $table->string('risk_level', 20)->default('low')->index();
            $table->unsignedInteger('risk_score')->default(0);
            $table->unsignedInteger('fraud_flags_count')->default(0);
            $table->string('decision_code', 80)->nullable()->index();
            $table->text('decision_reason')->nullable();
            $table->text('recommended_action')->nullable();
            $table->boolean('is_blocking')->default(false)->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->string('device_id')->nullable();
            $table->string('device_fingerprint')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('request_nonce', 191)->nullable()->index();
            $table->string('request_signature', 255)->nullable();
            $table->timestamp('request_timestamp')->nullable();
            $table->timestamp('client_timestamp')->nullable();
            $table->json('raw_payload')->nullable();
            $table->json('normalized_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('attendance_id')
                ->references('id')
                ->on('absensi')
                ->onDelete('set null');

            $table->foreign('kelas_id')
                ->references('id')
                ->on('kelas')
                ->onDelete('set null');

            $table->index(['assessment_date', 'validation_status'], 'afa_date_status_idx');
            $table->index(['assessment_date', 'risk_level'], 'afa_date_risk_level_idx');
            $table->index(['user_id', 'assessment_date'], 'afa_user_date_idx');
            $table->index(['kelas_id', 'assessment_date'], 'afa_kelas_date_idx');
        });

        Schema::create('attendance_fraud_flags', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('assessment_id')->index();
            $table->unsignedBigInteger('attendance_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('flag_key', 100)->index();
            $table->string('category', 50)->index();
            $table->string('severity', 20)->index();
            $table->unsignedInteger('score')->default(0);
            $table->boolean('blocking_recommended')->default(false)->index();
            $table->string('label', 150)->nullable();
            $table->text('reason')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->foreign('assessment_id')
                ->references('id')
                ->on('attendance_fraud_assessments')
                ->onDelete('cascade');

            $table->foreign('attendance_id')
                ->references('id')
                ->on('absensi')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['flag_key', 'severity'], 'aff_flag_severity_idx');
            $table->index(['user_id', 'flag_key'], 'aff_user_flag_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_fraud_flags');
        Schema::dropIfExists('attendance_fraud_assessments');

        Schema::table('absensi', function (Blueprint $table) {
            $columns = [
                'validation_status',
                'risk_level',
                'risk_score',
                'fraud_flags_count',
                'fraud_decision_reason',
                'fraud_last_assessed_at',
            ];

            $existingColumns = array_values(array_filter($columns, static fn(string $column): bool => Schema::hasColumn('absensi', $column)));
            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }
}
