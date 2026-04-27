<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['masuk', 'pulang']);
            $table->foreignId('lokasi_id')->nullable()->constrained('lokasi_gps')->onDelete('set null');
            $table->dateTime('valid_from');
            $table->dateTime('valid_until');
            $table->boolean('is_active')->default(true);
            $table->integer('max_scans')->nullable();
            $table->integer('scan_count')->default(0);
            $table->json('allowed_roles')->nullable(); // Role yang diizinkan scan
            $table->json('scan_history')->nullable(); // Riwayat scan
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index('code');
            $table->index('type');
            $table->index('is_active');
            $table->index(['valid_from', 'valid_until']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
