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
        // Template shift (pagi, siang, malam)
        Schema::create('shift_templates', function (Blueprint $table) {
            $table->id();
            $table->string('nama_shift');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->integer('durasi_jam');
            $table->text('keterangan')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Jadwal shift mingguan
        Schema::create('jadwal_shift', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shift_template_id')->constrained('shift_templates');
            $table->date('tanggal');
            $table->tinyInteger('hari_ke'); // 1=Senin, 7=Minggu
            $table->tinyInteger('minggu_ke');
            $table->integer('tahun');
            $table->enum('status', ['scheduled', 'completed', 'absent', 'swapped'])->default('scheduled');
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['user_id', 'tanggal', 'shift_template_id']);
        });

        // Request tukar shift
        Schema::create('shift_swaps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_shift_id_1')->constrained('jadwal_shift')->onDelete('cascade');
            $table->foreignId('jadwal_shift_id_2')->constrained('jadwal_shift')->onDelete('cascade');
            $table->foreignId('user_requester_id')->constrained('users');
            $table->foreignId('user_target_id')->constrained('users');
            $table->date('tanggal_request');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('alasan');
            $table->timestamps();
        });

        // Tambah kolom status_kepegawaian di users jika belum ada
        if (!Schema::hasColumn('users', 'status_kepegawaian')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('status_kepegawaian')->nullable()->after('email');
                $table->index('status_kepegawaian');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shift_swaps');
        Schema::dropIfExists('jadwal_shift');
        Schema::dropIfExists('shift_templates');

        if (Schema::hasColumn('users', 'status_kepegawaian')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('status_kepegawaian');
            });
        }
    }
};
