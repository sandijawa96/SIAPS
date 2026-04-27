<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStatusKepegawaianToUsersTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * NOTE: This migration has been modified to prevent duplicate column creation.
     * The status_kepegawaian column is already created in 2024_01_17_000002_create_simplified_attendance_tables.php
     *
     * @return void
     */
    public function up()
    {
        // Check if column doesn't exist before adding
        if (!Schema::hasColumn('users', 'status_kepegawaian')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('status_kepegawaian', ['ASN', 'Honorer'])->nullable()->after('is_active')->comment('Status kepegawaian ASN atau Honorer');
            });
        }
        // If column exists, it was already created by earlier migration with the correct specification
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasColumn('users', 'status_kepegawaian')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('status_kepegawaian');
            });
        }
    }
}
