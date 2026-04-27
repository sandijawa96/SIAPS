<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            
            // Data Akun
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            
            // Data Pribadi Dasar
            $table->string('nama_lengkap');
            $table->string('nik', 16)->unique()->nullable();
            $table->string('nisn', 10)->unique()->nullable(); // Untuk siswa
            $table->string('nip', 18)->unique()->nullable(); // Untuk pegawai
            $table->enum('jenis_kelamin', ['L', 'P']);
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            
            // Data Alamat
            $table->text('alamat')->nullable();
            $table->string('rt', 3)->nullable();
            $table->string('rw', 3)->nullable();
            $table->string('kelurahan')->nullable();
            $table->string('kecamatan')->nullable();
            $table->string('kota_kabupaten')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kode_pos', 5)->nullable();
            
            // Data Sistem
            $table->string('foto_profil')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('wajib_absen')->default(true);
            $table->json('metode_absensi')->nullable();
            $table->boolean('gps_tracking')->default(true);
            $table->json('notifikasi_settings')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index(['email', 'is_active']);
            $table->index(['nisn', 'is_active']);
            $table->index(['nip', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
