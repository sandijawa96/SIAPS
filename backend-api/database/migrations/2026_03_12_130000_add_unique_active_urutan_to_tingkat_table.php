<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tingkat')) {
            return;
        }

        // Partial unique index is supported in PostgreSQL and fits soft-delete semantics.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $duplicates = DB::table('tingkat')
            ->select('urutan', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->whereNotNull('urutan')
            ->groupBy('urutan')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('urutan')
            ->all();

        if (!empty($duplicates)) {
            throw new RuntimeException(
                'Tidak dapat membuat unique index urutan tingkat. Duplikat urutan aktif ditemukan: ' . implode(', ', $duplicates)
            );
        }

        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS tingkat_urutan_active_unique ON tingkat (urutan) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        if (!Schema::hasTable('tingkat')) {
            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS tingkat_urutan_active_unique');
    }
};

