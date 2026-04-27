<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_attendance_incident_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('status', 30)->default('queued');
            $table->date('tanggal');
            $table->string('scope_type', 30);
            $table->json('scope_payload')->nullable();
            $table->string('attendance_status', 20);
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->text('keterangan')->nullable();
            $table->string('reason', 255);
            $table->unsignedTinyInteger('progress_percentage')->default(0);
            $table->unsignedInteger('total_scope_users')->default(0);
            $table->unsignedInteger('total_candidates')->default(0);
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('skipped_existing_count')->default(0);
            $table->unsignedInteger('skipped_leave_count')->default(0);
            $table->unsignedInteger('skipped_non_required_count')->default(0);
            $table->unsignedInteger('skipped_non_working_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->json('preview_summary')->nullable();
            $table->json('sample_failures')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_attendance_incident_batches');
    }
};
