<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShiftTemplatesPgsql extends Migration
{
    public function up()
    {
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_shift');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->integer('durasi_jam');
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('shift_templates');
    }
}
