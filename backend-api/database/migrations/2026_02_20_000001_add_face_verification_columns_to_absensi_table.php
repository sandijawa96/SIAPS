<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            if (!Schema::hasColumn('absensi', 'verification_status')) {
                $table->string('verification_status', 20)
                    ->default('pending')
                    ->comment('pending, verified, rejected, manual_review')
                    ->after('is_verified');
            }

            if (!Schema::hasColumn('absensi', 'face_score_checkin')) {
                $table->decimal('face_score_checkin', 5, 4)
                    ->nullable()
                    ->after('verification_status');
            }

            if (!Schema::hasColumn('absensi', 'face_score_checkout')) {
                $table->decimal('face_score_checkout', 5, 4)
                    ->nullable()
                    ->after('face_score_checkin');
            }

            if (!Schema::hasColumn('absensi', 'gps_accuracy_masuk')) {
                $table->decimal('gps_accuracy_masuk', 8, 2)
                    ->nullable()
                    ->after('longitude_masuk');
            }

            if (!Schema::hasColumn('absensi', 'gps_accuracy_pulang')) {
                $table->decimal('gps_accuracy_pulang', 8, 2)
                    ->nullable()
                    ->after('longitude_pulang');
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensi', function (Blueprint $table) {
            $dropColumns = [];
            foreach ([
                'verification_status',
                'face_score_checkin',
                'face_score_checkout',
                'gps_accuracy_masuk',
                'gps_accuracy_pulang',
            ] as $column) {
                if (Schema::hasColumn('absensi', $column)) {
                    $dropColumns[] = $column;
                }
            }

            if (!empty($dropColumns)) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};

