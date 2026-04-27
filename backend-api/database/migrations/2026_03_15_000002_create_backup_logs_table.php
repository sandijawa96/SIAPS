<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('backup_logs')) {
            return;
        }

        Schema::create('backup_logs', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50);
            $table->string('filename')->nullable();
            $table->string('type', 50)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->string('status', 30)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('action');
            $table->index('type');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};

