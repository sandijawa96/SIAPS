<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataPribadiSiswaPgsql extends Migration
{
    public function up()
    {
        Schema::create('data_pribadi_siswa', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable();
            $table->string('agama')->nullable();
            $table->text('alamat')->nullable();
            $table->string('rt', 3)->nullable();
            $table->string('rw', 3)->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kota_kabupaten')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos', 5)->nullable();
            $table->string('no_hp_siswa')->nullable();
            $table->string('email_siswa')->nullable();
            $table->string('no_telepon_rumah', 15)->nullable();
            $table->string('no_hp_ortu')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('pekerjaan_ayah')->nullable();
            $table->string('no_hp_ayah', 15)->nullable();
            $table->string('email_ayah')->nullable();
            $table->string('pekerjaan_ibu')->nullable();
            $table->string('no_hp_ibu', 15)->nullable();
            $table->string('email_ibu')->nullable();
            $table->string('penghasilan_ortu')->nullable();
            $table->integer('anak_ke')->nullable();
            $table->integer('jumlah_saudara')->nullable();
            $table->string('golongan_darah')->nullable();
            $table->integer('tinggi_badan')->nullable();
            $table->integer('berat_badan')->nullable();
            $table->text('riwayat_penyakit')->nullable();
            $table->string('asal_sekolah')->nullable();
            $table->string('tahun_lulus_sd')->nullable();
            $table->decimal('nilai_un_sd', 5, 2)->nullable();
            $table->text('prestasi')->nullable();
            $table->text('hobi')->nullable();
            $table->text('cita_cita')->nullable();
            $table->enum('status_pernikahan_ortu', ['menikah', 'cerai_hidup', 'cerai_mati'])->default('menikah');
            $table->string('wali_siswa')->nullable();
            $table->string('hubungan_wali')->nullable();
            $table->string('no_hp_wali', 15)->nullable();
            $table->text('alamat_wali')->nullable();
            $table->year('tahun_masuk')->nullable();
            $table->string('status')->default('aktif');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('data_pribadi_siswa');
    }
}
