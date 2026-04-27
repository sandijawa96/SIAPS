<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backup data yang ada
        $data = DB::table('data_kepegawaian')->get();
        
        // Update enum values - hanya ASN dan Honorer
        DB::statement("ALTER TABLE data_kepegawaian MODIFY COLUMN status_kepegawaian ENUM('ASN', 'Honorer') NULL");
        
        // Update data PNS, PPPK, dan Kontrak menjadi ASN
        DB::table('data_kepegawaian')
            ->whereIn('status_kepegawaian', ['PNS', 'PPPK', 'Kontrak'])
            ->update(['status_kepegawaian' => 'ASN']);
            
        // Update data 'Tidak Ada' menjadi null
        DB::table('data_kepegawaian')
            ->where('status_kepegawaian', 'Tidak Ada')
            ->update(['status_kepegawaian' => null]);
    }

    public function down(): void
    {
        // Backup data yang ada
        $data = DB::table('data_kepegawaian')->get();
        
        // Restore enum values
        DB::statement("ALTER TABLE data_kepegawaian MODIFY COLUMN status_kepegawaian ENUM('PNS', 'PPPK', 'Honorer', 'Kontrak', 'Tidak Ada') NULL");
        
        // Update data ASN menjadi PNS (default rollback)
        DB::table('data_kepegawaian')
            ->where('status_kepegawaian', 'ASN')
            ->update(['status_kepegawaian' => 'PNS']);
    }
};
