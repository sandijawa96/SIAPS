<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateKelasPgsql extends Migration
{
    public function up()
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_kelas');
            $table->unsignedBigInteger('tingkat_id');
            $table->string('jurusan')->nullable();
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->unsignedBigInteger('wali_kelas_id')->nullable();
            $table->integer('kapasitas')->default(0);
            $table->integer('jumlah_siswa')->default(0);
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tingkat_id')->references('id')->on('tingkat')->onDelete('cascade');
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->onDelete('cascade');
            $table->foreign('wali_kelas_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('kelas');
    }
}
