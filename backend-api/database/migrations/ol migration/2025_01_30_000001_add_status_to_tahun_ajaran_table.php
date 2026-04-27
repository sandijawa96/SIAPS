<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            // Tambah kolom status
            $table->enum('status', ['draft', 'preparation', 'active', 'completed', 'archived'])
                ->default('draft')
                ->after('is_active');

            // Tambah kolom untuk tracking progress persiapan
            $table->integer('preparation_progress')->default(0)->after('status');

            // Tambah kolom untuk metadata tambahan
            $table->json('metadata')->nullable()->after('preparation_progress');
        });

        // Migrate existing data: convert is_active to status
        DB::statement("
            UPDATE tahun_ajaran 
            SET status = CASE 
                WHEN is_active = 1 THEN 'active'
                WHEN tanggal_selesai < CURDATE() THEN 'completed'
                ELSE 'draft'
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tahun_ajaran', function (Blueprint $table) {
            $table->dropColumn(['status', 'preparation_progress', 'metadata']);
        });
    }
};
