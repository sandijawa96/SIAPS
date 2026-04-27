<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('dapodik_sync_batches')) {
            Schema::create('dapodik_sync_batches', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('status', 30)->default('running');
                $table->string('base_url', 2048);
                $table->string('npsn', 20)->nullable();
                $table->unsignedBigInteger('requested_by')->nullable();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->json('totals')->nullable();
                $table->json('errors')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('requested_by')->references('id')->on('users')->nullOnDelete();
                $table->index(['status', 'created_at']);
                $table->index('npsn');
            });
        }

        if (!Schema::hasTable('dapodik_sync_records')) {
            Schema::create('dapodik_sync_records', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id');
                $table->string('source', 50);
                $table->string('dapodik_id')->nullable();
                $table->string('secondary_id')->nullable();
                $table->unsignedInteger('row_index')->nullable();
                $table->string('row_hash', 64);
                $table->json('row_data');
                $table->json('normalized_data')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('batch_id')->references('id')->on('dapodik_sync_batches')->cascadeOnDelete();
                $table->index(['batch_id', 'source']);
                $table->index(['source', 'dapodik_id']);
                $table->index('row_hash');
            });
        }

        if (!Schema::hasTable('dapodik_entity_mappings')) {
            Schema::create('dapodik_entity_mappings', function (Blueprint $table) {
                $table->id();
                $table->string('entity_type', 50);
                $table->string('dapodik_id');
                $table->string('siaps_table')->nullable();
                $table->unsignedBigInteger('siaps_id')->nullable();
                $table->string('confidence', 30)->default('unmatched');
                $table->string('match_key')->nullable();
                $table->unsignedBigInteger('last_seen_batch_id')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->unique(['entity_type', 'dapodik_id'], 'dapodik_entity_type_id_unique');
                $table->foreign('last_seen_batch_id')->references('id')->on('dapodik_sync_batches')->nullOnDelete();
                $table->index(['siaps_table', 'siaps_id']);
                $table->index(['entity_type', 'confidence']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dapodik_entity_mappings');
        Schema::dropIfExists('dapodik_sync_records');
        Schema::dropIfExists('dapodik_sync_batches');
    }
};
