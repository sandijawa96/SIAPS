<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateShiftSwapsPgsql extends Migration
{
    public function up()
    {
        Schema::create('shift_swaps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('jadwal_shift_id_1');
            $table->unsignedBigInteger('jadwal_shift_id_2');
            $table->unsignedBigInteger('user_requester_id');
            $table->unsignedBigInteger('user_target_id');
            $table->date('tanggal_request');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->text('alasan');
            $table->timestamps();

            $table->foreign('jadwal_shift_id_1')->references('id')->on('jadwal_shift')->onDelete('cascade');
            $table->foreign('jadwal_shift_id_2')->references('id')->on('jadwal_shift')->onDelete('cascade');
            $table->foreign('user_requester_id')->references('id')->on('users');
            $table->foreign('user_target_id')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('shift_swaps');
    }
}
