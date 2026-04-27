<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->time('jam_pulang')->nullable();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpha', 'terlambat'])->default('hadir');
            $table->enum('metode_absensi', ['selfie', 'qr_code', 'manual'])->default('selfie');
            
            // Data lokasi masuk
            $table->decimal('latitude_masuk', 10, 8)->nullable();
            $table->decimal('longitude_masuk', 11, 8)->nullable();
            $table->string('foto_masuk')->nullable();
            $table->foreignId('lokasi_masuk_id')->nullable()->constrained('lokasi_gps')->onDelete('set null');
            
            // Data lokasi pulang
            $table->decimal('latitude_pulang', 10, 8)->nullable();
            $table->decimal('longitude_pulang', 11, 8)->nullable();
            $table->string('foto_pulang')->nullable();
            $table->foreignId('lokasi_pulang_id')->nullable()->constrained('lokasi_gps')->onDelete('set null');
            
            // QR Code data
            $table->string('qr_code_masuk')->nullable();
            $table->string('qr_code_pulang')->nullable();
            
            // Additional data
            $table->text('keterangan')->nullable();
            $table->json('device_info')->nullable(); // Device information
            $table->string('ip_address')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            $table->softDeletes();

            // Indexes for performance
            $table->index(['user_id', 'tanggal']);
            $table->index(['tanggal', 'status']);
            $table->index('status');
            $table->index('metode_absensi');
            $table->index('created_at');
            
            // Unique constraint to prevent duplicate attendance per day
            $table->unique(['user_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
