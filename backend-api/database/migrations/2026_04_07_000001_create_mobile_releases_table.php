<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_releases', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 20);
            $table->string('release_channel', 30)->default('stable');
            $table->string('public_version', 50);
            $table->unsignedInteger('build_number');
            $table->string('download_url', 2048)->nullable();
            $table->string('asset_path', 2048)->nullable();
            $table->string('asset_original_name', 255)->nullable();
            $table->string('asset_mime_type', 255)->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->text('release_notes')->nullable();
            $table->text('distribution_notes')->nullable();
            $table->string('update_mode', 20)->default('optional');
            $table->string('minimum_supported_version', 50)->nullable();
            $table->unsignedInteger('minimum_supported_build_number')->nullable();
            $table->boolean('is_active')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['platform', 'release_channel']);
            $table->index(['platform', 'release_channel', 'is_active', 'is_published'], 'mobile_releases_platform_channel_active_published_idx');
            $table->index(['published_at', 'platform']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_releases');
    }
};
