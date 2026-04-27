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
        if (!Schema::hasTable('kalender_akademik')) {
            return;
        }

        Schema::table('kalender_akademik', function (Blueprint $table) {
            if (!Schema::hasColumn('kalender_akademik', 'tahun_ajaran_id')) {
                $table->unsignedBigInteger('tahun_ajaran_id')->nullable()->after('source_hash');
            }
        });

        Schema::table('kalender_akademik', function (Blueprint $table) {
            $table->index('tahun_ajaran_id', 'kalender_akademik_tahun_ajaran_idx');
            $table->foreign('tahun_ajaran_id', 'kalender_akademik_tahun_ajaran_fk')
                ->references('id')
                ->on('tahun_ajaran')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('kalender_akademik')) {
            return;
        }

        Schema::table('kalender_akademik', function (Blueprint $table) {
            $table->dropForeign('kalender_akademik_tahun_ajaran_fk');
            $table->dropIndex('kalender_akademik_tahun_ajaran_idx');
        });

        Schema::table('kalender_akademik', function (Blueprint $table) {
            if (Schema::hasColumn('kalender_akademik', 'tahun_ajaran_id')) {
                $table->dropColumn('tahun_ajaran_id');
            }
        });
    }
};
