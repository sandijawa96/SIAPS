<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAttendanceOverridesPgsql extends Migration
{
    public function up()
    {
        Schema::create('user_attendance_overrides', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->integer('toleransi')->nullable();
            $table->integer('minimal_open_time')->nullable()->comment('Override minimal waktu absen dibuka sebelum jam masuk (menit)');
            $table->boolean('wajib_gps')->nullable();
            $table->boolean('wajib_foto')->nullable();
            $table->text('hari_kerja')->nullable(); // JSON stored as text
            $table->text('lokasi_gps_ids')->nullable(); // JSON stored as text
            $table->string('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unsignedBigInteger('created_by');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_attendance_overrides');
    }
}
