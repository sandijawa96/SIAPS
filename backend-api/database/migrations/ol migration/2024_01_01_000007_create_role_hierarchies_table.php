<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_hierarchies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_role_id')->constrained('roles')->onDelete('cascade');
            $table->foreignId('child_role_id')->constrained('roles')->onDelete('cascade');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Prevent duplicate hierarchies
            $table->unique(['parent_role_id', 'child_role_id']);
            
            // Indexes
            $table->index('parent_role_id');
            $table->index('child_role_id');
            $table->index('sort_order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_hierarchies');
    }
};
