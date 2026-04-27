<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('tahun_ajaran')
            ->where(function ($query): void {
                $query->whereNull('semester')
                    ->orWhere('semester', '!=', 'full');
            })
            ->update([
                'semester' => 'full',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally left blank: previous semester value (ganjil/genap) cannot be restored safely.
    }
};
