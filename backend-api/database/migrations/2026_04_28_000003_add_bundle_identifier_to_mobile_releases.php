<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mobile_releases')) {
            return;
        }

        Schema::table('mobile_releases', function (Blueprint $table) {
            if (!Schema::hasColumn('mobile_releases', 'bundle_identifier')) {
                $table->string('bundle_identifier', 255)
                    ->nullable()
                    ->after('target_audience');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mobile_releases') || !Schema::hasColumn('mobile_releases', 'bundle_identifier')) {
            return;
        }

        Schema::table('mobile_releases', function (Blueprint $table) {
            $table->dropColumn('bundle_identifier');
        });
    }
};
