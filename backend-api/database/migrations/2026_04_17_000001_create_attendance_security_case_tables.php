<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceSecurityCaseTables extends Migration
{
    public function up()
    {
        Schema::create('attendance_security_cases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('case_number', 40)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('kelas_id')->nullable()->index();
            $table->unsignedBigInteger('opened_by')->nullable()->index();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->unsignedBigInteger('resolved_by')->nullable()->index();
            $table->date('case_date')->index();
            $table->string('status', 30)->default('open')->index();
            $table->string('priority', 20)->default('medium')->index();
            $table->text('summary')->nullable();
            $table->longText('student_statement')->nullable();
            $table->longText('staff_notes')->nullable();
            $table->string('resolution', 80)->nullable()->index();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('kelas_id')
                ->references('id')
                ->on('kelas')
                ->onDelete('set null');

            $table->foreign('opened_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('assigned_to')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('resolved_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->index(['kelas_id', 'status', 'case_date'], 'asc_kelas_status_date_idx');
            $table->index(['user_id', 'status', 'case_date'], 'asc_user_status_date_idx');
        });

        Schema::create('attendance_security_case_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('case_id')->index();
            $table->string('item_type', 40)->index();
            $table->unsignedBigInteger('item_id')->index();
            $table->json('item_snapshot')->nullable();
            $table->timestamps();

            $table->foreign('case_id')
                ->references('id')
                ->on('attendance_security_cases')
                ->onDelete('cascade');

            $table->unique(['case_id', 'item_type', 'item_id'], 'asci_case_type_item_unique');
            $table->index(['item_type', 'item_id'], 'asci_type_item_idx');
        });

        Schema::create('attendance_security_case_evidence', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('case_id')->index();
            $table->unsignedBigInteger('uploaded_by')->nullable()->index();
            $table->string('evidence_type', 40)->default('system_snapshot')->index();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('file_disk', 40)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->string('file_mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('case_id')
                ->references('id')
                ->on('attendance_security_cases')
                ->onDelete('cascade');

            $table->foreign('uploaded_by')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });

        Schema::create('attendance_security_case_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('case_id')->index();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('activity_type', 50)->index();
            $table->text('description')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('case_id')
                ->references('id')
                ->on('attendance_security_cases')
                ->onDelete('cascade');

            $table->foreign('actor_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_security_case_activities');
        Schema::dropIfExists('attendance_security_case_evidence');
        Schema::dropIfExists('attendance_security_case_items');
        Schema::dropIfExists('attendance_security_cases');
    }
}
