<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbsensiSchemaPgsql extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('attendance_setting_id')->nullable();
            $table->text('settings_snapshot')->nullable(); // longtext with json check
            $table->unsignedBigInteger('kelas_id')->nullable();
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpha', 'terlambat'])->default('hadir');
            $table->enum('metode_absensi', ['selfie', 'qr_code', 'manual'])->default('selfie');
            $table->decimal('latitude_masuk', 10, 8)->nullable();
            $table->decimal('longitude_masuk', 11, 8)->nullable();
            $table->text('foto_masuk')->nullable();
            $table->unsignedBigInteger('lokasi_masuk_id')->nullable();
            $table->decimal('latitude_pulang', 10, 8)->nullable();
            $table->decimal('longitude_pulang', 11, 8)->nullable();
            $table->text('foto_pulang')->nullable();
            $table->unsignedBigInteger('lokasi_pulang_id')->nullable();
            $table->string('qr_code_masuk')->nullable();
            $table->string('qr_code_pulang')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('device_info')->nullable(); // longtext with json check
            $table->string('ip_address')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->unsignedBigInteger('verified_by')->nullable();
            $table->unsignedBigInteger('izin_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->unique(['user_id', 'tanggal'], 'absensi_user_id_tanggal_unique');
            $table->index('lokasi_masuk_id');
            $table->index('lokasi_pulang_id');
            $table->index('verified_by');
            $table->index(['user_id', 'tanggal'], 'absensi_user_id_tanggal_index');
            $table->index(['tanggal', 'status'], 'absensi_tanggal_status_index');
            $table->index('status', 'absensi_status_index');
            $table->index('metode_absensi', 'absensi_metode_absensi_index');
            $table->index('created_at', 'absensi_created_at_index');
            $table->index('kelas_id', 'absensi_kelas_id_foreign');
            $table->index('izin_id', 'absensi_izin_id_foreign');
            $table->index('attendance_setting_id', 'absensi_attendance_setting_id_index');
        });

        // Foreign keys
        Schema::table('absensi', function (Blueprint $table) {});

        // Foreign keys
        Schema::table('absensi', function (Blueprint $table) {
            $table->foreign('attendance_setting_id')->references('id')->on('attendance_settings')->onDelete('set null');
            $table->foreign('izin_id')->references('id')->on('izin')->onDelete('set null');
            $table->foreign('kelas_id')->references('id')->on('kelas')->onDelete('set null');
            $table->foreign('lokasi_masuk_id')->references('id')->on('lokasi_gps')->onDelete('set null');
            $table->foreign('lokasi_pulang_id')->references('id')->on('lokasi_gps')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('verified_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropForeign(['attendance_setting_id']);
            $table->dropForeign(['izin_id']);
            $table->dropForeign(['kelas_id']);
            $table->dropForeign(['lokasi_masuk_id']);
            $table->dropForeign(['lokasi_pulang_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['verified_by']);
        });
        Schema::dropIfExists('absensi');
    }
}
