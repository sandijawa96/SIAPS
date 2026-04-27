<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_discipline_alerts')) {
            return;
        }

        Schema::create('attendance_discipline_alerts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('recipient_user_id');
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->unsignedBigInteger('whatsapp_notification_id')->nullable();
            $table->string('rule_key', 80);
            $table->string('audience', 40);
            $table->string('semester', 20)->default('');
            $table->unsignedBigInteger('tahun_ajaran_id')->nullable();
            $table->string('tahun_ajaran_ref', 50)->default('');
            $table->timestamp('triggered_at');
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('recipient_user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->foreign('notification_id')
                ->references('id')
                ->on('notifications')
                ->onDelete('set null');
            $table->foreign('whatsapp_notification_id')
                ->references('id')
                ->on('whatsapp_notifications')
                ->onDelete('set null');
            $table->foreign('tahun_ajaran_id')
                ->references('id')
                ->on('tahun_ajaran')
                ->onDelete('set null');

            $table->unique(
                ['user_id', 'recipient_user_id', 'rule_key', 'audience', 'semester', 'tahun_ajaran_ref'],
                'attendance_discipline_alerts_unique_scope'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_discipline_alerts');
    }
};
