<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_kepegawaian', function (Blueprint $table) {
            // Tambah kolom baru
            $table->string('nip', 20)->nullable()->after('status_kepegawaian');
            $table->string('nomor_sk')->nullable()->after('nip');
            $table->date('tanggal_sk')->nullable()->after('nomor_sk');
            $table->string('golongan')->nullable()->after('tanggal_sk');
            $table->date('tmt')->nullable()->after('golongan');
            $table->date('masa_kontrak_mulai')->nullable()->after('tmt');
            $table->date('masa_kontrak_selesai')->nullable()->after('masa_kontrak_mulai');
            $table->string('institusi')->nullable()->after('universitas');

            // Hapus kolom lama yang tidak digunakan
            $table->dropColumn('nip_lama');
            $table->dropColumn('tmt_pangkat');
            $table->dropColumn('tanggal_mulai_kerja');
            $table->dropColumn('masa_kerja');
        });
    }

    public function down(): void
    {
        Schema::table('data_kepegawaian', function (Blueprint $table) {
            // Hapus kolom baru
            $table->dropColumn([
                'nip',
                'nomor_sk',
                'tanggal_sk',
                'golongan',
                'tmt',
                'masa_kontrak_mulai',
                'masa_kontrak_selesai',
                'institusi'
            ]);

            // Kembalikan kolom lama
            $table->string('nip_lama', 18)->nullable()->after('status_kepegawaian');
            $table->string('tmt_pangkat')->nullable()->after('pangkat_golongan');
            $table->date('tanggal_mulai_kerja')->nullable()->after('tmt_pangkat');
            $table->string('masa_kerja')->nullable()->after('tanggal_mulai_kerja');
        });
    }
};
