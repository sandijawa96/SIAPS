<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sbt_exam_sessions')) {
            return;
        }

        Schema::create('sbt_exam_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_code')->unique();
            $table->string('app_session_id', 100)->unique();
            $table->string('participant_identifier', 120)->nullable()->index();
            $table->string('student_name', 150)->nullable();
            $table->string('device_id', 160)->nullable()->index();
            $table->string('device_name', 160)->nullable();
            $table->string('app_version', 50)->nullable();
            $table->string('platform', 40)->default('android');
            $table->string('exam_url', 2048)->nullable();
            $table->string('status', 30)->default('started')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'last_heartbeat_at'], 'sbt_sessions_status_heartbeat_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sbt_exam_sessions');
    }
};
