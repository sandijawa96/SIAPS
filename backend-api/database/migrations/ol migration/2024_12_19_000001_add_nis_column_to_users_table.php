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
        Schema::table('users', function (Blueprint $table) {
            // Menambahkan kolom NIS (Nomor Induk Siswa) untuk siswa
            $table->string('nis', 20)->unique()->nullable()->after('nisn');
            
            // Menambahkan index untuk performa query
            $table->index(['nis', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['nis', 'is_active']);
            $table->dropColumn('nis');
        });
    }
};
