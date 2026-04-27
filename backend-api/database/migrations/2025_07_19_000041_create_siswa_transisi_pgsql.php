<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSiswaTransisiPgsql extends Migration
{
    public function up()
    {
        Schema::create('siswa_transisi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('siswa_id');
            $table->enum('type', ['naik_kelas', 'pindah_kelas', 'lulus', 'keluar', 'aktif_kembali']);
            $table->unsignedBigInteger('kelas_asal_id')->nullable();
            $table->unsignedBigInteger('kelas_tujuan_id')->nullable();
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->date('tanggal_transisi');
            $table->text('keterangan')->nullable();
            $table->unsignedBigInteger('processed_by');
            $table->boolean('is_undone')->default(false);
            $table->boolean('can_undo')->default(true);
            $table->unsignedBigInteger('undone_by')->nullable();
            $table->timestamp('undone_at')->nullable();
            $table->text('undo_reason')->nullable();
            $table->timestamps();

            $table->foreign('siswa_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('kelas_asal_id')->references('id')->on('kelas')->onDelete('set null');
            $table->foreign('kelas_tujuan_id')->references('id')->on('kelas')->onDelete('set null');
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->onDelete('cascade');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('undone_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('siswa_transisi');
    }
}
