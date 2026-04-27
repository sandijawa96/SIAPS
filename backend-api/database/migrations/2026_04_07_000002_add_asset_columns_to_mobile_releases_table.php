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
            if (!Schema::hasColumn('mobile_releases', 'asset_path')) {
                $table->string('asset_path', 2048)->nullable()->after('download_url');
            }

            if (!Schema::hasColumn('mobile_releases', 'asset_original_name')) {
                $table->string('asset_original_name', 255)->nullable()->after('asset_path');
            }

            if (!Schema::hasColumn('mobile_releases', 'asset_mime_type')) {
                $table->string('asset_mime_type', 255)->nullable()->after('asset_original_name');
            }

            if (!Schema::hasColumn('mobile_releases', 'checksum_sha256')) {
                $table->string('checksum_sha256', 64)->nullable()->after('asset_mime_type');
            }

            if (!Schema::hasColumn('mobile_releases', 'file_size_bytes')) {
                $table->unsignedBigInteger('file_size_bytes')->nullable()->after('checksum_sha256');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('mobile_releases')) {
            return;
        }

        Schema::table('mobile_releases', function (Blueprint $table) {
            $columnsToDrop = [];

            foreach ([
                'asset_path',
                'asset_original_name',
                'asset_mime_type',
                'checksum_sha256',
                'file_size_bytes',
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
