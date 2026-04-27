<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLokasiGpsPgsql extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('lokasi_gps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_lokasi');
            $table->text('alamat')->nullable();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->integer('radius')->default(100);
            $table->text('deskripsi')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('warna_marker', 7)->default('#2196F3');
            $table->text('roles')->nullable();
            $table->string('waktu_mulai', 5)->default('06:00');
            $table->string('waktu_selesai', 5)->default('18:00');
            $table->json('jam_operasional')->nullable();
            $table->text('hari_aktif')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('created_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('lokasi_gps');
    }
}
