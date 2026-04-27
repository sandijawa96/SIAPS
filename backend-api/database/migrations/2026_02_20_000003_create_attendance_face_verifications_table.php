<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_face_verifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('absensi_id');
            $table->unsignedBigInteger('user_id');
            $table->string('check_type', 20)->comment('checkin atau checkout');
            $table->decimal('score', 5, 4)->nullable();
            $table->decimal('threshold', 5, 4)->nullable();
            $table->string('result', 20)->comment('verified, rejected, manual_review');
            $table->string('reason_code', 100)->nullable();
            $table->string('engine_version', 100)->nullable();
            $table->integer('processing_ms')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();

            $table->index(['absensi_id', 'check_type'], 'face_verif_absensi_check_type_idx');
            $table->index(['user_id', 'result'], 'face_verif_user_result_idx');
            $table->foreign('absensi_id')->references('id')->on('absensi')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_face_verifications', function (Blueprint $table) {
            $table->dropForeign(['absensi_id']);
            $table->dropForeign(['user_id']);
        });

        Schema::dropIfExists('attendance_face_verifications');
    }
};

