<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceSecurityEventsTable extends Migration
{
    public function up()
    {
        Schema::create('attendance_security_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->unsignedBigInteger('attendance_id')->nullable()->index();
            $table->unsignedBigInteger('kelas_id')->nullable()->index();
            $table->string('category', 50)->index();
            $table->string('event_key', 100)->index();
            $table->string('severity', 20)->index();
            $table->string('status', 20)->index();
            $table->string('attempt_type', 20)->nullable()->index();
            $table->date('event_date')->index();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('accuracy', 8, 2)->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->string('device_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');

            $table->foreign('attendance_id')
                ->references('id')
                ->on('absensi')
                ->onDelete('set null');

            $table->foreign('kelas_id')
                ->references('id')
                ->on('kelas')
                ->onDelete('set null');

            $table->index(['category', 'event_key', 'event_date'], 'ase_category_event_date_idx');
            $table->index(['kelas_id', 'event_date'], 'ase_kelas_event_date_idx');
            $table->index(['user_id', 'event_date'], 'ase_user_event_date_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_security_events');
    }
}
