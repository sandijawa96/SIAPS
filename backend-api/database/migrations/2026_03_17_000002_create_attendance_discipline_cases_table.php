<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_discipline_cases')) {
            return;
        }

        Schema::create('attendance_discipline_cases', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('kelas_id')->nullable();
            $table->string('rule_key', 80);
            $table->string('status', 50)->default('ready_for_parent_broadcast');
            $table->string('semester', 20)->default('');
            $table->unsignedBigInteger('tahun_ajaran_id')->nullable();
            $table->string('tahun_ajaran_ref', 50)->default('');
            $table->integer('metric_value')->default(0);
            $table->integer('metric_limit')->default(0);
            $table->unsignedBigInteger('broadcast_campaign_id')->nullable();
            $table->timestamp('first_triggered_at');
            $table->timestamp('last_triggered_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('kelas_id')
                ->references('id')
                ->on('kelas')
                ->onDelete('set null');
            $table->foreign('tahun_ajaran_id')
                ->references('id')
                ->on('tahun_ajaran')
                ->onDelete('set null');
            $table->foreign('broadcast_campaign_id')
                ->references('id')
                ->on('broadcast_campaigns')
                ->onDelete('set null');

            $table->unique(
                ['user_id', 'rule_key', 'semester', 'tahun_ajaran_ref'],
                'attendance_discipline_cases_unique_scope'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_discipline_cases');
    }
};
