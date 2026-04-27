<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wali_kelas_promotion_settings', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('kelas_id');
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->boolean('is_enabled')->default(false);
            $table->timestamp('open_at')->nullable();
            $table->timestamp('close_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('kelas_id')->references('id')->on('kelas')->onDelete('cascade');
            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');

            $table->unique(['kelas_id', 'tahun_ajaran_id'], 'wali_promotion_class_year_unique');
            $table->index(['tahun_ajaran_id', 'is_enabled'], 'wali_promotion_year_enabled_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wali_kelas_promotion_settings');
    }
};

