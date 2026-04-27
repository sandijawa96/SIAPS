<?php

/**
 * Safe Migration Script for AA Panel
 * Menjalankan migrate:fresh dengan backup dan error handling
 */

// Set time limit untuk operasi database yang lama
set_time_limit(300);

echo "🚀 Starting Safe Migration Process...\n";
echo "📅 " . date('Y-m-d H:i:s') . "\n\n";

// Function to run artisan command
function runArtisan($command, $description = '')
{
    echo "🔄 " . ($description ?: $command) . "\n";

    $output = [];
    $returnCode = 0;

    exec("php artisan $command 2>&1", $output, $returnCode);

    foreach ($output as $line) {
        echo "   " . $line . "\n";
    }

    if ($returnCode !== 0) {
        echo "❌ Command failed with code: $returnCode\n";
        return false;
    }

    echo "✅ Success!\n\n";
    return true;
}

// Function to backup database
function backupDatabase()
{
    echo "💾 Creating database backup...\n";

    $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
    $dbName = $_ENV['DB_DATABASE'] ?? 'absensi_db';
    $dbUser = $_ENV['DB_USERNAME'] ?? 'root';
    $dbPass = $_ENV['DB_PASSWORD'] ?? '';

    $backupFile = "backup_" . date('Y_m_d_H_i_s') . ".sql";

    $command = "mysqldump -h$dbHost -u$dbUser";
    if ($dbPass) {
        $command .= " -p$dbPass";
    }
    $command .= " $dbName > $backupFile 2>&1";

    $output = [];
    $returnCode = 0;
    exec($command, $output, $returnCode);

    if ($returnCode === 0 && file_exists($backupFile)) {
        echo "✅ Backup created: $backupFile\n\n";
        return $backupFile;
    } else {
        echo "⚠️  Backup failed, continuing without backup...\n\n";
        return false;
    }
}

try {
    // Load environment
    if (file_exists('.env')) {
        $lines = file('.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value, '"\'');
            }
        }
    }

    // Step 1: Create backup
    $backupFile = backupDatabase();

    // Step 2: Clear caches
    runArtisan('config:clear', 'Clearing configuration cache');
    runArtisan('cache:clear', 'Clearing application cache');
    runArtisan('route:clear', 'Clearing route cache');
    runArtisan('view:clear', 'Clearing view cache');

    // Step 3: Check migration status
    echo "📋 Checking current migration status...\n";
    runArtisan('migrate:status', 'Migration status check');

    // Step 4: Run migrate:fresh with seeding
    echo "🔥 Running migrate:fresh (this will drop all tables)...\n";
    echo "⚠️  This operation cannot be undone!\n";
    echo "💾 Backup file: " . ($backupFile ?: 'No backup created') . "\n\n";

    // Wait 3 seconds for user to cancel if needed
    echo "⏳ Starting in 3 seconds... (Ctrl+C to cancel)\n";
    sleep(1);
    echo "⏳ 2...\n";
    sleep(1);
    echo "⏳ 1...\n";
    sleep(1);

    if (!runArtisan('migrate:fresh --seed --force', 'Fresh migration with seeding')) {
        throw new Exception('Migration failed');
    }

    // Step 5: Verify migration
    runArtisan('migrate:status', 'Verifying migration status');

    // Step 6: Clear caches again
    runArtisan('config:cache', 'Rebuilding configuration cache');
    runArtisan('route:cache', 'Rebuilding route cache');

    // Step 7: Test database connection
    echo "🔍 Testing database connection...\n";
    if (runArtisan('tinker --execute="echo \'Database connection: \' . DB::connection()->getPdo() ? \'OK\' : \'Failed\';"', 'Database connection test')) {
        echo "✅ Database connection successful\n\n";
    }

    echo "🎉 Migration completed successfully!\n";
    echo "📊 Summary:\n";
    echo "   - Backup: " . ($backupFile ?: 'Not created') . "\n";
    echo "   - Migration: Success\n";
    echo "   - Seeding: Success\n";
    echo "   - Cache: Rebuilt\n\n";

    echo "🔗 Next steps:\n";
    echo "   1. Test your application\n";
    echo "   2. Check all functionalities\n";
    echo "   3. If issues occur, restore from backup\n\n";
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";

    if ($backupFile && file_exists($backupFile)) {
        echo "💾 Backup available: $backupFile\n";
        echo "🔄 To restore:\n";
        echo "   mysql -h{$_ENV['DB_HOST']} -u{$_ENV['DB_USERNAME']} -p {$_ENV['DB_DATABASE']} < $backupFile\n";
    }

    exit(1);
}

echo "✨ Process completed at " . date('Y-m-d H:i:s') . "\n";
