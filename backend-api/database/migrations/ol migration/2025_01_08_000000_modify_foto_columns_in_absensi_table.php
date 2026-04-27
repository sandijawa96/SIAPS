<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            // Change foto_masuk and foto_pulang columns from string to longText
            // to accommodate base64 encoded images which can be very large
            $table->longText('foto_masuk')->nullable()->change();
            $table->longText('foto_pulang')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            // Revert back to string type
            $table->string('foto_masuk')->nullable()->change();
            $table->string('foto_pulang')->nullable()->change();
        });
    }
};
