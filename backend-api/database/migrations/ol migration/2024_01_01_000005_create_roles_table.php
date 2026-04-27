<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('display_name')->nullable(false);
            $table->string('description')->nullable();
            $table->integer('level')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('guard_name');
            $table->timestamps();

            // Indexes
            $table->index(['name', 'guard_name']);
            $table->index('level');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
