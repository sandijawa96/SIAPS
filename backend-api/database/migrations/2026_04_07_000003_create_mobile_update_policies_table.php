<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_update_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mobile_release_id')->constrained('mobile_releases')->cascadeOnDelete();
            $table->string('audience', 20);
            $table->string('update_mode', 20)->default('optional');
            $table->string('minimum_supported_version', 50)->nullable();
            $table->unsignedInteger('minimum_supported_build_number')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['mobile_release_id', 'audience'], 'mobile_update_policies_release_audience_unique');
            $table->index(['audience', 'mobile_release_id'], 'mobile_update_policies_audience_release_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_update_policies');
    }
};
