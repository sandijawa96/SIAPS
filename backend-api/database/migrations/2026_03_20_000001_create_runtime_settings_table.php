<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('runtime_settings')) {
            return;
        }

        Schema::create('runtime_settings', function (Blueprint $table) {
            $table->id();
            $table->string('namespace');
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['namespace', 'key'], 'runtime_settings_namespace_key_unique');
            $table->index('namespace');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runtime_settings');
    }
};
