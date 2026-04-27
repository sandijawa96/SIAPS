<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false);
            $table->string('type')->default('string'); // string, number, boolean, json, array, time
            $table->json('options')->nullable(); // Untuk tipe select/radio/checkbox
            $table->string('target_type')->default('global'); // global, role, user
            $table->unsignedBigInteger('target_id')->nullable();
            $table->integer('priority')->default(0);
            $table->timestamps();

            // Indexes
            $table->index('group');
            $table->index('is_public');
            $table->index(['target_type', 'target_id']);
            $table->index('priority');

            // Unique constraint untuk kombinasi key dan target
            $table->unique(['key', 'target_type', 'target_id'], 'settings_unique_key_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
