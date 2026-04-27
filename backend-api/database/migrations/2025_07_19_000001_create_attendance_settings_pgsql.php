<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAttendanceSettingsPgsql extends Migration
{
    public function up()
    {
        Schema::create('attendance_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('schema_name')->default('Default Schema');
            $table->string('schema_type')->default('global')->comment('siswa, honorer, asn, guru_honorer, staff_asn, global, etc');
            $table->string('target_role')->nullable()->comment('Target role: siswa, guru, staff, admin, etc');
            $table->string('target_status')->nullable()->comment('Target status kepegawaian: Honorer, ASN');
            $table->text('schema_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_mandatory')->default(true)->comment('Apakah wajib absen dengan skema ini');
            $table->integer('priority')->default(0)->comment('Priority untuk auto assignment (higher = more priority)');
            $table->integer('version')->default(1);
            $table->time('jam_masuk_default')->default('07:00:00');
            $table->time('jam_pulang_default')->default('15:00:00');
            $table->integer('toleransi_default')->default(15);
            $table->integer('minimal_open_time_staff')->default(70)->comment('Minimal waktu absen dibuka untuk staff/guru sebelum jam masuk (menit)');
            $table->boolean('wajib_gps')->default(true);
            $table->boolean('wajib_foto')->default(true);
            $table->json('hari_kerja')->default(json_encode(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']))->comment('Hari kerja (global)');
            $table->json('lokasi_gps_ids')->nullable();
            $table->integer('radius_absensi')->default(100)->comment('Radius absensi dalam meter (override dari lokasi GPS)');
            $table->integer('gps_accuracy')->default(20)->comment('Akurasi GPS minimum yang diterima dalam meter');
            $table->time('siswa_jam_masuk')->default('07:00:00');
            $table->time('siswa_jam_pulang')->default('14:00:00');
            $table->integer('siswa_toleransi')->default(10);
            $table->integer('minimal_open_time_siswa')->default(70)->comment('Minimal waktu absen dibuka untuk siswa sebelum jam masuk (menit)');
            $table->timestamps();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('attendance_settings');
    }
}
