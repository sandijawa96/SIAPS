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
        Schema::create('attendance_settings_logs', function (Blueprint $table) {
            $table->id();
            $table->string('settings_type')->index(); // 'global', 'user', 'role', 'status'
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('target_type')->nullable()->index(); // Model class name
            $table->json('old_settings')->nullable();
            $table->json('new_settings')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->text('change_reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for better performance (with shorter names)
            $table->index(['settings_type', 'target_id', 'target_type'], 'asl_settings_target_idx');
            $table->index(['changed_by', 'created_at'], 'asl_user_created_idx');
            $table->index('created_at', 'asl_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attendance_settings_logs');
    }
};
