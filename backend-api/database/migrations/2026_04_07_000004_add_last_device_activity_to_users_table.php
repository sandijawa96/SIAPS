<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'last_device_activity')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_device_activity')->nullable();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'last_device_activity')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('last_device_activity');
        });
    }
};
