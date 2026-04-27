<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_settings', 'face_verification_enabled')) {
                $table->boolean('face_verification_enabled')
                    ->nullable()
                    ->after('wajib_foto');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_settings', 'face_verification_enabled')) {
                $table->dropColumn('face_verification_enabled');
            }
        });
    }
};
