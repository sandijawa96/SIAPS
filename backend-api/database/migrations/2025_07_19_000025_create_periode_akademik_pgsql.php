<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePeriodeAkademikPgsql extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('periode_akademik', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->string('nama', 100);
            $table->enum('jenis', ['pembelajaran', 'ujian', 'libur', 'orientasi']);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->enum('semester', ['ganjil', 'genap', 'both']);
            $table->boolean('is_active')->default(true);
            $table->text('keterangan')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->onDelete('cascade');
            $table->index(['tahun_ajaran_id', 'jenis']);
            $table->index(['tanggal_mulai', 'tanggal_selesai']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('periode_akademik');
    }
}
