<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notification_skips', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable()->index();
            $table->string('reason')->index();
            $table->unsignedBigInteger('target_user_id')->nullable();
            $table->string('phone_candidate')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('target_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notification_skips');
    }
};
