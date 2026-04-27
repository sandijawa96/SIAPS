<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number');
            $table->text('message');
            $table->enum('type', ['absensi', 'izin', 'pengumuman', 'reminder', 'laporan']);
            $table->enum('status', ['pending', 'sent', 'failed', 'delivered'])->default('pending');
            $table->json('metadata')->nullable(); // data tambahan seperti user_id, absensi_id, dll
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('phone_number');
            $table->index('type');
            $table->index('status');
            $table->index('scheduled_at');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
