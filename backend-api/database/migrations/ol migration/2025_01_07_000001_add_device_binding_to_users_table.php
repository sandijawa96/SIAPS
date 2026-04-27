<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('device_id')->nullable()->after('updated_at');
            $table->string('device_name')->nullable()->after('device_id');
            $table->timestamp('device_bound_at')->nullable()->after('device_name');
            $table->boolean('device_locked')->default(false)->after('device_bound_at');
            $table->json('device_info')->nullable()->after('device_locked');

            // Index untuk performance
            $table->index('device_id');
            $table->index('device_locked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['device_id']);
            $table->dropIndex(['device_locked']);
            $table->dropColumn([
                'device_id',
                'device_name',
                'device_bound_at',
                'device_locked',
                'device_info'
            ]);
        });
    }
};
