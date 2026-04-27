<?php

/**
 * Script untuk memperbaiki konflik migrasi database
 * Jalankan dengan: php fix-migration-conflicts.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Setup database connection
$capsule = new Capsule;
$capsule->addConnection([
    'driver' => 'mysql',
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'database' => $_ENV['DB_DATABASE'] ?? 'absensi_db',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

echo "🔧 Memperbaiki konflik migrasi database...\n\n";

try {
    // 1. Check if lokasi_gps table exists
    if (!Schema::hasTable('lokasi_gps')) {
        echo "❌ Tabel lokasi_gps tidak ditemukan. Jalankan migrasi terlebih dahulu.\n";
        exit(1);
    }

    echo "✅ Tabel lokasi_gps ditemukan\n";

    // 2. Fix alamat column to be nullable
    Schema::table('lokasi_gps', function (Blueprint $table) {
        if (Schema::hasColumn('lokasi_gps', 'alamat')) {
            $table->text('alamat')->nullable()->change();
            echo "✅ Kolom alamat berhasil diubah menjadi nullable\n";
        }
    });

    // 3. Fix hari_aktif column type
    Schema::table('lokasi_gps', function (Blueprint $table) {
        if (Schema::hasColumn('lokasi_gps', 'hari_aktif')) {
            $table->text('hari_aktif')->nullable()->change();
            echo "✅ Kolom hari_aktif berhasil diubah ke text\n";
        }
    });

    // 4. Add missing columns if they don't exist
    Schema::table('lokasi_gps', function (Blueprint $table) {
        if (!Schema::hasColumn('lokasi_gps', 'warna_marker')) {
            $table->string('warna_marker', 7)->default('#2196F3');
            echo "✅ Kolom warna_marker berhasil ditambahkan\n";
        }

        if (!Schema::hasColumn('lokasi_gps', 'roles')) {
            $table->text('roles')->nullable();
            echo "✅ Kolom roles berhasil ditambahkan\n";
        }

        if (!Schema::hasColumn('lokasi_gps', 'waktu_mulai')) {
            $table->string('waktu_mulai', 5)->default('06:00');
            echo "✅ Kolom waktu_mulai berhasil ditambahkan\n";
        }

        if (!Schema::hasColumn('lokasi_gps', 'waktu_selesai')) {
            $table->string('waktu_selesai', 5)->default('18:00');
            echo "✅ Kolom waktu_selesai berhasil ditambahkan\n";
        }
    });

    // 5. Check other problematic tables
    $problematicTables = [
        'data_pribadi_siswa',
        'users',
        'settings'
    ];

    foreach ($problematicTables as $tableName) {
        if (Schema::hasTable($tableName)) {
            echo "✅ Tabel {$tableName} OK\n";
        } else {
            echo "⚠️  Tabel {$tableName} tidak ditemukan\n";
        }
    }

    echo "\n🎉 Perbaikan migrasi selesai!\n";
    echo "💡 Sekarang Anda dapat menjalankan:\n";
    echo "   php artisan migrate:status\n";
    echo "   php artisan migrate --force\n\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "📋 Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
