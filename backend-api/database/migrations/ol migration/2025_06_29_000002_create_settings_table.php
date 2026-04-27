<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     * 
     * NOTE: This migration has been DISABLED to prevent duplicate table creation.
     * The settings table is already created in 2024_01_01_000009_create_settings_table.php
     * 
     * This migration is now a NO-OP to avoid conflicts during fresh migrations.
     *
     * @return void
     */
    public function up()
    {
        // NO-OP: Table is already created in 2024_01_01_000009_create_settings_table.php
        // This prevents duplicate table creation errors during migrate:fresh

        // If you need to add the 'meta' column, it should be done in a separate
        // ALTER TABLE migration, not in a CREATE TABLE migration.
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // NO-OP: This migration doesn't create anything, so nothing to drop
    }
}
