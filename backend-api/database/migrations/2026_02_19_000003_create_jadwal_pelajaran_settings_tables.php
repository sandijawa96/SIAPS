<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jadwal_pelajaran_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tahun_ajaran_id');
            $table->enum('semester', ['ganjil', 'genap', 'full'])->default('full');
            $table->unsignedTinyInteger('default_jp_minutes')->default(45);
            $table->time('default_start_time')->default('07:00:00');
            $table->boolean('is_active')->default(true);
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->foreign('tahun_ajaran_id')->references('id')->on('tahun_ajaran')->cascadeOnDelete();
            $table->unique(['tahun_ajaran_id', 'semester'], 'jadwal_setting_unique_tahun_semester');
        });

        Schema::create('jadwal_pelajaran_setting_days', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('setting_id');
            $table->enum('hari', ['senin', 'selasa', 'rabu', 'kamis', 'jumat', 'sabtu', 'minggu']);
            $table->boolean('is_school_day')->default(true);
            $table->unsignedTinyInteger('jp_count')->default(10);
            $table->unsignedTinyInteger('jp_minutes')->nullable();
            $table->time('start_time')->nullable();
            $table->string('notes', 255)->nullable();
            $table->timestamps();

            $table->foreign('setting_id')->references('id')->on('jadwal_pelajaran_settings')->cascadeOnDelete();
            $table->unique(['setting_id', 'hari'], 'jadwal_setting_day_unique');
            $table->index(['setting_id', 'hari']);
        });

        Schema::create('jadwal_pelajaran_setting_breaks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('day_setting_id');
            $table->unsignedTinyInteger('after_jp');
            $table->unsignedTinyInteger('break_minutes');
            $table->string('label', 80)->nullable();
            $table->timestamps();

            $table->foreign('day_setting_id')->references('id')->on('jadwal_pelajaran_setting_days')->cascadeOnDelete();
            $table->unique(['day_setting_id', 'after_jp'], 'jadwal_setting_break_unique');
            $table->index(['day_setting_id', 'after_jp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jadwal_pelajaran_setting_breaks');
        Schema::dropIfExists('jadwal_pelajaran_setting_days');
        Schema::dropIfExists('jadwal_pelajaran_settings');
    }
};
