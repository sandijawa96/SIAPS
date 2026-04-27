<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dateTime('display_start_at')->nullable()->after('status')->index();
            $table->dateTime('display_end_at')->nullable()->after('display_start_at')->index();
            $table->dateTime('expires_at')->nullable()->after('display_end_at')->index();
            $table->dateTime('pinned_at')->nullable()->after('expires_at')->index();
            $table->unsignedSmallInteger('priority')->default(0)->after('pinned_at')->index();
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dateTime('display_start_at')->nullable()->after('is_read')->index();
            $table->dateTime('display_end_at')->nullable()->after('display_start_at')->index();
            $table->dateTime('expires_at')->nullable()->after('display_end_at')->index();
            $table->dateTime('pinned_at')->nullable()->after('expires_at')->index();
            $table->unsignedSmallInteger('priority')->default(0)->after('pinned_at')->index();
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn([
                'display_start_at',
                'display_end_at',
                'expires_at',
                'pinned_at',
                'priority',
            ]);
        });

        Schema::table('broadcast_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'display_start_at',
                'display_end_at',
                'expires_at',
                'pinned_at',
                'priority',
            ]);
        });
    }
};
