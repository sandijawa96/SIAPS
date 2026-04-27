<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('mobile_releases')) {
            return;
        }

        Schema::table('mobile_releases', function (Blueprint $table) {
            if (!Schema::hasColumn('mobile_releases', 'app_key')) {
                $table->string('app_key', 60)->default('siaps')->after('id');
            }

            if (!Schema::hasColumn('mobile_releases', 'app_name')) {
                $table->string('app_name', 120)->default('SIAPS Mobile')->after('app_key');
            }

            if (!Schema::hasColumn('mobile_releases', 'app_description')) {
                $table->text('app_description')->nullable()->after('app_name');
            }

            if (!Schema::hasColumn('mobile_releases', 'target_audience')) {
                $table->string('target_audience', 20)->default('all')->after('app_description');
            }

            if (!Schema::hasColumn('mobile_releases', 'asset_disk')) {
                $table->string('asset_disk', 20)->nullable()->after('asset_path');
            }
        });

        DB::table('mobile_releases')
            ->where(function ($query) {
                $query->whereNull('app_key')
                    ->orWhere('app_key', '');
            })
            ->update(['app_key' => 'siaps']);

        DB::table('mobile_releases')
            ->where(function ($query) {
                $query->whereNull('app_name')
                    ->orWhere('app_name', '');
            })
            ->update(['app_name' => 'SIAPS Mobile']);

        DB::table('mobile_releases')
            ->where(function ($query) {
                $query->whereNull('target_audience')
                    ->orWhere('target_audience', '');
            })
            ->update(['target_audience' => 'all']);

        DB::table('mobile_releases')
            ->whereNotNull('asset_path')
            ->where(function ($query) {
                $query->whereNull('asset_disk')
                    ->orWhere('asset_disk', '');
            })
            ->update(['asset_disk' => 'public']);

        Schema::table('mobile_releases', function (Blueprint $table) {
            $table->index(
                ['app_key', 'platform', 'release_channel'],
                'mobile_releases_app_platform_channel_idx'
            );
            $table->index(
                ['app_key', 'target_audience', 'is_active', 'is_published'],
                'mobile_releases_app_audience_active_published_idx'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mobile_releases')) {
            return;
        }

        Schema::table('mobile_releases', function (Blueprint $table) {
            $table->dropIndex('mobile_releases_app_platform_channel_idx');
            $table->dropIndex('mobile_releases_app_audience_active_published_idx');

            $columnsToDrop = [];
            foreach ([
                'app_key',
                'app_name',
                'app_description',
                'target_audience',
                'asset_disk',
            ] as $column) {
                if (Schema::hasColumn('mobile_releases', $column)) {
                    $columnsToDrop[] = $column;
                }
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
