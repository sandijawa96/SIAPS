<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('device_tokens')) {
            Schema::create('device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('device_id')->unique();
                $table->string('device_name')->nullable();
                $table->string('device_type')->nullable();
                $table->string('push_token', 2048)->nullable();
                $table->json('device_info')->nullable();
                $table->timestamp('last_used_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->index(['user_id', 'is_active']);
            });
            return;
        }

        Schema::table('device_tokens', function (Blueprint $table) {
            if (!Schema::hasColumn('device_tokens', 'device_type')) {
                $table->string('device_type')->nullable()->after('device_name');
            }
            if (!Schema::hasColumn('device_tokens', 'push_token')) {
                $table->string('push_token', 2048)->nullable()->after('device_type');
            }
            if (!Schema::hasColumn('device_tokens', 'device_info')) {
                $table->json('device_info')->nullable()->after('push_token');
            }
            if (!Schema::hasColumn('device_tokens', 'last_used_at')) {
                $table->timestamp('last_used_at')->nullable()->after('device_info');
            }
            if (!Schema::hasColumn('device_tokens', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('last_used_at');
            }
        });
    }

    public function down(): void
    {
        // no-op to avoid destructive rollback on existing installations
    }
};
