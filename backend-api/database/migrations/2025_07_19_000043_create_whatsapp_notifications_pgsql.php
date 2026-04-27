<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWhatsappNotificationsPgsql extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('phone_number');
            $table->text('message');
            $table->enum('type', ['absensi', 'izin', 'pengumuman', 'reminder', 'laporan']);
            $table->enum('status', ['pending', 'sent', 'failed', 'delivered'])->default('pending');
            $table->text('metadata')->nullable(); // JSON stored as text
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->integer('max_retries')->default(3);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
}
