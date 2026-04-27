<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_type')->nullable()->index();
            $table->string('status')->nullable()->index();
            $table->string('message_id')->nullable()->index();
            $table->string('device')->nullable()->index();
            $table->string('from_number')->nullable()->index();
            $table->unsignedBigInteger('matched_notification_id')->nullable();
            $table->boolean('delivery_marked')->default(false);
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();
            $table->timestamps();

            $table->foreign('matched_notification_id')
                ->references('id')
                ->on('whatsapp_notifications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_webhook_events');
    }
};
