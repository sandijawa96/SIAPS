<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_attendance_incident_batch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('manual_attendance_incident_batches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('tingkat_id')->nullable()->constrained('tingkat')->nullOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('absensi')->nullOnDelete();
            $table->string('nama_lengkap');
            $table->string('email')->nullable();
            $table->string('kelas_label')->nullable();
            $table->string('tingkat_label')->nullable();
            $table->string('result_code', 50);
            $table->string('result_label', 100);
            $table->text('message')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'user_id']);
            $table->index(['batch_id', 'result_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_attendance_incident_batch_items');
    }
};
