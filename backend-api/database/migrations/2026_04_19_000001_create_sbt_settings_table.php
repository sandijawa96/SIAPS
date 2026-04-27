<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sbt_settings')) {
            return;
        }

        Schema::create('sbt_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->string('exam_url', 2048)->default('https://res.sman1sumbercirebon.sch.id');
            $table->string('exam_host')->default('res.sman1sumbercirebon.sch.id');
            $table->string('security_mode', 40)->default('warning');
            $table->string('supervisor_code_hash')->nullable();
            $table->timestamp('supervisor_code_updated_at')->nullable();
            $table->string('minimum_app_version', 50)->nullable();
            $table->boolean('require_dnd')->default(false);
            $table->boolean('require_screen_pinning')->default(true);
            $table->boolean('require_overlay_protection')->default(true);
            $table->unsignedTinyInteger('minimum_battery_level')->default(20);
            $table->unsignedSmallInteger('heartbeat_interval_seconds')->default(30);
            $table->boolean('maintenance_enabled')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->text('announcement')->nullable();
            $table->unsignedInteger('config_version')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('updated_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sbt_settings');
    }
};
