<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_face_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->text('template_vector')->nullable();
            $table->string('template_path')->nullable();
            $table->string('template_version', 50)->nullable();
            $table->decimal('quality_score', 5, 4)->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->unsignedBigInteger('enrolled_by')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'is_active'], 'face_templates_user_active_idx');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('enrolled_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('user_face_templates', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['enrolled_by']);
        });

        Schema::dropIfExists('user_face_templates');
    }
};

