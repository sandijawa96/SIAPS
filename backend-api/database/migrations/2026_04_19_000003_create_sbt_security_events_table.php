<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sbt_security_events')) {
            return;
        }

        Schema::create('sbt_security_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sbt_exam_session_id')->nullable()->index();
            $table->string('app_session_id', 100)->nullable()->index();
            $table->string('event_type', 100)->index();
            $table->string('severity', 20)->default('medium')->index();
            $table->text('message')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->string('app_version', 50)->nullable();
            $table->string('device_id', 160)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('sbt_exam_session_id')
                ->references('id')
                ->on('sbt_exam_sessions')
                ->onDelete('set null');

            $table->index(['event_type', 'created_at'], 'sbt_events_type_created_idx');
            $table->index(['severity', 'created_at'], 'sbt_events_severity_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sbt_security_events');
    }
};
