<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Fix lokasi_gps table conflicts
        if (Schema::hasTable('lokasi_gps')) {
            Schema::table('lokasi_gps', function (Blueprint $table) {
                // Make alamat nullable to prevent migration errors
                if (Schema::hasColumn('lokasi_gps', 'alamat')) {
                    $table->text('alamat')->nullable()->change();
                }

                // Ensure hari_aktif is text type
                if (Schema::hasColumn('lokasi_gps', 'hari_aktif')) {
                    $table->text('hari_aktif')->nullable()->change();
                }

                // Add missing columns safely
                if (!Schema::hasColumn('lokasi_gps', 'warna_marker')) {
                    $table->string('warna_marker', 7)->default('#2196F3');
                }

                if (!Schema::hasColumn('lokasi_gps', 'roles')) {
                    $table->text('roles')->nullable();
                }

                if (!Schema::hasColumn('lokasi_gps', 'waktu_mulai')) {
                    $table->string('waktu_mulai', 5)->default('06:00');
                }

                if (!Schema::hasColumn('lokasi_gps', 'waktu_selesai')) {
                    $table->string('waktu_selesai', 5)->default('18:00');
                }
            });
        }

        // Fix data_pribadi_siswa table if needed
        if (Schema::hasTable('data_pribadi_siswa')) {
            Schema::table('data_pribadi_siswa', function (Blueprint $table) {
                // Ensure status column exists
                if (!Schema::hasColumn('data_pribadi_siswa', 'status')) {
                    $table->enum('status', ['aktif', 'tidak_aktif', 'lulus', 'pindah', 'keluar'])
                        ->default('aktif');
                }

                // Make contact fields nullable if they exist
                $contactFields = ['no_hp_siswa', 'email_siswa', 'no_hp_ortu', 'email_ortu'];
                foreach ($contactFields as $field) {
                    if (Schema::hasColumn('data_pribadi_siswa', $field)) {
                        $table->string($field)->nullable()->change();
                    }
                }
            });
        }

        // Fix users table conflicts
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Add status_kepegawaian if not exists
                if (!Schema::hasColumn('users', 'status_kepegawaian')) {
                    $table->enum('status_kepegawaian', ['ASN', 'Honorer'])->nullable();
                }

                // Add device_binding columns if not exist
                if (!Schema::hasColumn('users', 'device_id')) {
                    $table->string('device_id')->nullable();
                }

                if (!Schema::hasColumn('users', 'device_name')) {
                    $table->string('device_name')->nullable();
                }

                if (!Schema::hasColumn('users', 'last_device_check')) {
                    $table->timestamp('last_device_check')->nullable();
                }

                // Add NIS column if not exists
                if (!Schema::hasColumn('users', 'nis')) {
                    $table->string('nis')->nullable()->unique();
                }
            });
        }

        // Fix settings table
        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                // Add meta column if not exists
                if (!Schema::hasColumn('settings', 'meta')) {
                    $table->json('meta')->nullable();
                }
            });
        }

        // Ensure permissions table has description column
        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                if (!Schema::hasColumn('permissions', 'description')) {
                    $table->text('description')->nullable();
                }
            });
        }

        // Fix tahun_ajaran table
        if (Schema::hasTable('tahun_ajaran')) {
            Schema::table('tahun_ajaran', function (Blueprint $table) {
                if (!Schema::hasColumn('tahun_ajaran', 'status')) {
                    $table->enum('status', ['aktif', 'tidak_aktif'])->default('tidak_aktif');
                }
            });
        }
    }

    public function down(): void
    {
        // Rollback changes if needed
        if (Schema::hasTable('lokasi_gps')) {
            Schema::table('lokasi_gps', function (Blueprint $table) {
                $table->dropColumn([
                    'warna_marker',
                    'roles',
                    'waktu_mulai',
                    'waktu_selesai'
                ]);
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn([
                    'status_kepegawaian',
                    'device_id',
                    'device_name',
                    'last_device_check',
                    'nis'
                ]);
            });
        }

        if (Schema::hasTable('settings')) {
            Schema::table('settings', function (Blueprint $table) {
                $table->dropColumn('meta');
            });
        }

        if (Schema::hasTable('permissions')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }

        if (Schema::hasTable('tahun_ajaran')) {
            Schema::table('tahun_ajaran', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
