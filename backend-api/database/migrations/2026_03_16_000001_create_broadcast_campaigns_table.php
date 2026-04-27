<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('broadcast_campaigns')) {
            return;
        }

        Schema::create('broadcast_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->text('message');
            $table->string('type', 20)->default('info');
            $table->json('channels');
            $table->json('audience');
            $table->json('popup')->nullable();
            $table->json('whatsapp')->nullable();
            $table->json('email')->nullable();
            $table->string('status', 30)->default('processing')->index();
            $table->unsignedInteger('total_target')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('summary')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['created_by', 'created_at'], 'broadcast_campaigns_creator_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_campaigns');
    }
};
