<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sbt_settings')) {
            return;
        }

        Schema::table('sbt_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('sbt_settings', 'ios_lock_on_background')) {
                $table->boolean('ios_lock_on_background')
                    ->default(true)
                    ->after('require_overlay_protection');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sbt_settings') || !Schema::hasColumn('sbt_settings', 'ios_lock_on_background')) {
            return;
        }

        Schema::table('sbt_settings', function (Blueprint $table) {
            $table->dropColumn('ios_lock_on_background');
        });
    }
};
