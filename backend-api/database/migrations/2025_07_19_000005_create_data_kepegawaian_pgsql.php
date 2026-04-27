<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataKepegawaianPgsql extends Migration
{
    public function up()
    {
        Schema::create('data_kepegawaian', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->string('no_hp', 15)->nullable();
            $table->string('no_telepon_kantor', 15)->nullable();
            $table->enum('status_kepegawaian', ['ASN', 'Honorer'])->nullable();
            $table->string('nip', 20)->nullable();
            $table->string('nomor_sk')->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->string('golongan')->nullable();
            $table->date('tmt')->nullable();
            $table->date('masa_kontrak_mulai')->nullable();
            $table->date('masa_kontrak_selesai')->nullable();
            $table->string('nuptk', 16)->nullable();
            $table->string('jabatan')->nullable();
            $table->longText('sub_jabatan')->nullable();
            $table->string('pangkat_golongan')->nullable();
            $table->string('pendidikan_terakhir')->nullable();
            $table->string('jurusan')->nullable();
            $table->string('universitas')->nullable();
            $table->string('institusi')->nullable();
            $table->string('tahun_lulus', 4)->nullable();
            $table->string('no_ijazah')->nullable();
            $table->string('gelar_depan')->nullable();
            $table->string('gelar_belakang')->nullable();
            $table->string('bidang_studi')->nullable();
            $table->longText('mata_pelajaran')->nullable();
            $table->integer('jam_mengajar_per_minggu')->nullable();
            $table->longText('kelas_yang_diajar')->nullable();
            $table->string('nama_pasangan')->nullable();
            $table->string('pekerjaan_pasangan')->nullable();
            $table->integer('jumlah_anak')->nullable();
            $table->longText('data_anak')->nullable();
            $table->text('alamat_domisili')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('keterangan')->nullable();
            $table->longText('sertifikat')->nullable();
            $table->longText('pelatihan')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('data_kepegawaian');
    }
}
