<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->time('jam_masuk')->default('07:30')->after('gps_tracking');
            $table->time('jam_pulang')->default('15:30')->after('jam_masuk');
            $table->boolean('mengikuti_kaldik')->default(false)->after('jam_pulang');
            $table->boolean('lokasi_default')->default(true)->after('mengikuti_kaldik');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'jam_masuk',
                'jam_pulang',
                'mengikuti_kaldik',
                'lokasi_default'
            ]);
        });
    }
};
