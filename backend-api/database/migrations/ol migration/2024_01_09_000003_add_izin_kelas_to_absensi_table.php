<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->unsignedBigInteger('kelas_id')->nullable()->after('user_id');
            $table->unsignedBigInteger('izin_id')->nullable()->after('verified_by');
            
            // Add foreign key constraints
            $table->foreign('kelas_id')->references('id')->on('kelas')->onDelete('set null');
            $table->foreign('izin_id')->references('id')->on('izin')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $table->dropForeign(['kelas_id']);
            $table->dropForeign(['izin_id']);
            $table->dropColumn(['kelas_id', 'izin_id']);
        });
    }
};
