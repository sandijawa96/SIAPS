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
            if (!Schema::hasColumn('kalender_akademik', 'source_system')) {
                $table->string('source_system', 50)->nullable()->after('created_by');
            }
            if (!Schema::hasColumn('kalender_akademik', 'source_event_id')) {
                $table->unsignedBigInteger('source_event_id')->nullable()->after('source_system');
            }
            if (!Schema::hasColumn('kalender_akademik', 'source_hash')) {
                $table->string('source_hash', 64)->nullable()->after('source_event_id');
            }
        });

        Schema::table('kalender_akademik', function (Blueprint $table) {
            $table->index(['source_system', 'source_event_id'], 'kalender_akademik_source_event_idx');
            $table->index(['source_system', 'source_hash'], 'kalender_akademik_source_hash_idx');
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
            $table->dropIndex('kalender_akademik_source_event_idx');
            $table->dropIndex('kalender_akademik_source_hash_idx');
        });

        Schema::table('kalender_akademik', function (Blueprint $table) {
            if (Schema::hasColumn('kalender_akademik', 'source_hash')) {
                $table->dropColumn('source_hash');
            }
            if (Schema::hasColumn('kalender_akademik', 'source_event_id')) {
                $table->dropColumn('source_event_id');
            }
            if (Schema::hasColumn('kalender_akademik', 'source_system')) {
                $table->dropColumn('source_system');
            }
        });
    }
};
