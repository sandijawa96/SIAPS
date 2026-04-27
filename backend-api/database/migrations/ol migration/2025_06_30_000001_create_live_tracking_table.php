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
        Schema::create('live_tracking', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->decimal('accuracy', 8, 2)->nullable(); // GPS accuracy in meters
            $table->decimal('speed', 8, 2)->nullable(); // Speed in m/s
            $table->decimal('heading', 8, 2)->nullable(); // Direction in degrees
            $table->boolean('is_in_school_area')->default(false);
            $table->json('device_info')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('tracked_at');
            $table->timestamps();

            // Indexes for performance
            $table->index(['user_id', 'tracked_at']);
            $table->index(['tracked_at']);
            $table->index(['is_in_school_area']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('live_tracking');
    }
};
