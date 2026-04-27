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
        Schema::table('attendance_schema_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('attendance_schema_assignments', 'assignment_type')) {
                $table->string('assignment_type', 20)->default('manual')->after('is_active')
                    ->comment('Type of assignment: manual, auto, bulk');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance_schema_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_schema_assignments', 'assignment_type')) {
                $table->dropColumn('assignment_type');
            }
        });
    }
};
