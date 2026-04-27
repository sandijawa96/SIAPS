<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('siswa_transfer_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('siswa_id');
            $table->unsignedBigInteger('kelas_asal_id');
            $table->unsignedBigInteger('kelas_tujuan_id');
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->date('tanggal_rencana');
            $table->text('keterangan')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->unsignedBigInteger('requested_by');
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('approval_note')->nullable();
            $table->unsignedBigInteger('executed_transisi_id')->nullable();
            $table->timestamps();

            $table->foreign('siswa_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('kelas_asal_id')->references('id')->on('kelas')->onDelete('cascade');
            $table->foreign('kelas_tujuan_id')->references('id')->on('kelas')->onDelete('cascade');
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->onDelete('cascade');
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('executed_transisi_id')->references('id')->on('siswa_transisi')->onDelete('set null');

            $table->index(['status', 'created_at'], 'siswa_transfer_status_created_idx');
            $table->index(['siswa_id', 'status'], 'siswa_transfer_siswa_status_idx');
            $table->index('requested_by', 'siswa_transfer_requested_by_idx');
            $table->index('processed_by', 'siswa_transfer_processed_by_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_transfer_requests');
    }
};

