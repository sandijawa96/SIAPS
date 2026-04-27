<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('attendance_settings')) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_settings', 'face_template_required')) {
                $table->boolean('face_template_required')
                    ->default(true)
                    ->after('face_verification_enabled');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('attendance_settings')) {
            return;
        }

        Schema::table('attendance_settings', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_settings', 'face_template_required')) {
                $table->dropColumn('face_template_required');
            }
        });
    }
};
