<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_settings', 'verification_mode')) {
                $table->string('verification_mode', 20)
                    ->default('async_pending')
                    ->comment('sync_final atau async_pending')
                    ->after('gps_accuracy');
            }

            if (!Schema::hasColumn('attendance_settings', 'attendance_scope')) {
                $table->string('attendance_scope', 30)
                    ->default('siswa_only')
                    ->comment('siswa_only atau siswa_dan_pegawai')
                    ->after('verification_mode');
            }

            if (!Schema::hasColumn('attendance_settings', 'target_tingkat_ids')) {
                $table->json('target_tingkat_ids')
                    ->nullable()
                    ->comment('Daftar ID tingkat target untuk schema ini')
                    ->after('attendance_scope');
            }

            if (!Schema::hasColumn('attendance_settings', 'target_kelas_ids')) {
                $table->json('target_kelas_ids')
                    ->nullable()
                    ->comment('Daftar ID kelas target untuk schema ini')
                    ->after('target_tingkat_ids');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_settings', function (Blueprint $table) {
            $dropColumns = [];

            foreach (['verification_mode', 'attendance_scope', 'target_tingkat_ids', 'target_kelas_ids'] as $column) {
                if (Schema::hasColumn('attendance_settings', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

