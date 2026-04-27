<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_face_template_submission_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('self_submit_count')->default(0);
            $table->unsignedTinyInteger('unlock_allowance_remaining')->default(0);
            $table->timestamp('last_submitted_at')->nullable();
            $table->timestamp('last_unlocked_at')->nullable();
            $table->foreignId('last_unlocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('user_id', 'face_template_submission_states_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_face_template_submission_states');
    }
};
