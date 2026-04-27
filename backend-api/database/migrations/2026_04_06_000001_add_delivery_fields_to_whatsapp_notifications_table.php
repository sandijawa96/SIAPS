<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('whatsapp_notifications', 'gateway_message_id')) {
                $table->string('gateway_message_id')->nullable()->index();
            }

            if (!Schema::hasColumn('whatsapp_notifications', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('whatsapp_notifications', 'gateway_message_id')) {
                $table->dropIndex(['gateway_message_id']);
                $table->dropColumn('gateway_message_id');
            }

            if (Schema::hasColumn('whatsapp_notifications', 'delivered_at')) {
                $table->dropColumn('delivered_at');
            }
        });
    }
};
