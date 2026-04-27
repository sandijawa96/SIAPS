<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('data_pribadi_siswa', function (Blueprint $table) {
            // Add missing columns that are being used in the code
            $table->string('tempat_lahir')->nullable()->after('user_id');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable()->after('tanggal_lahir');
            $table->string('agama')->nullable()->after('jenis_kelamin');
            $table->text('alamat')->nullable()->after('agama');
            $table->string('email_siswa')->nullable()->after('no_hp_siswa');
            
            // Add missing columns for complete address
            $table->string('rt', 3)->nullable()->after('alamat');
            $table->string('rw', 3)->nullable()->after('rt');
            $table->string('kelurahan')->nullable()->after('rw');
            $table->string('kecamatan')->nullable()->after('kelurahan');
            $table->string('kota_kabupaten')->nullable()->after('kecamatan');
            $table->string('provinsi')->nullable()->after('kota_kabupaten');
            $table->string('kode_pos', 5)->nullable()->after('provinsi');
        });
    }

    public function down(): void
    {
        Schema::table('data_pribadi_siswa', function (Blueprint $table) {
            $table->dropColumn([
                'tempat_lahir',
                'tanggal_lahir', 
                'jenis_kelamin',
                'agama',
                'alamat',
                'email_siswa',
                'rt',
                'rw',
                'kelurahan',
                'kecamatan',
                'kota_kabupaten',
                'provinsi',
                'kode_pos'
            ]);
        });
    }
};
