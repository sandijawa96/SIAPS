<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Helpers\AuthHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;
use Symfony\Component\Process\Process;

class BackupController extends Controller
{
    /**
     * Get all backups
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $backupPath = storage_path('app/backups');
            
            if (!file_exists($backupPath)) {
                mkdir($backupPath, 0755, true);
            }

            $backups = [];
            $files = glob($backupPath . '/*.zip');
            
            foreach ($files as $file) {
                $filename = basename($file);
                $size = filesize($file);
                $created = filemtime($file);
                
                // Extract backup info from filename
                preg_match('/backup_(\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2})_(.+)\.zip/', $filename, $matches);
                
                $backups[] = [
                    'id' => md5($filename),
                    'filename' => $filename,
                    'path' => $file,
                    'size' => $this->formatBytes($size),
                    'size_bytes' => $size,
                    'created_at' => Carbon::createFromTimestamp($created)->format('Y-m-d H:i:s'),
                    'type' => isset($matches[2]) ? $matches[2] : 'unknown',
                    'date' => isset($matches[1]) ? $matches[1] : 'unknown'
                ];
            }

            // Sort by creation date (newest first)
            usort($backups, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Backups retrieved successfully',
                'data' => $backups
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve backups', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve backups'
            ], 500);
        }
    }

    /**
     * Create new backup
     */
    public function create(Request $request): JsonResponse
    {
        $type = (string) $request->input('type', 'database');
        $description = (string) ($request->input('description') ?: 'Manual backup');

        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:full,database,files',
                'description' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->runBackupProcess($type, $description, AuthHelper::userId());

            return response()->json([
                'success' => true,
                'message' => 'Backup created successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to create backup', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            $message = 'Failed to create backup';
            if (config('app.debug')) {
                $message .= ': ' . $e->getMessage();
            }
            return response()->json([
                'success' => false,
                'message' => $message
            ], 500);
        }
    }

    /**
     * Download backup file
     */
    public function download($filename): BinaryFileResponse|JsonResponse
    {
        return $this->streamBackupDownload($filename);
    }

    /**
     * Get temporary signed download link for backup file.
     */
    public function downloadLink(Request $request, $filename): JsonResponse
    {
        try {
            $backupPath = $this->resolveBackupFilePath($filename);

            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            $safeFilename = basename(trim((string) $filename));
            $expiresAt = now()->addMinutes(5);

            return response()->json([
                'success' => true,
                'data' => [
                    'download_url' => URL::temporarySignedRoute(
                        'backups.signed-download',
                        $expiresAt,
                        ['filename' => $safeFilename]
                    ),
                    'expires_at' => $expiresAt->toISOString(),
                    'filename' => $safeFilename,
                    'file_size_bytes' => (int) filesize($backupPath),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate backup download link', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to prepare backup download'
            ], 500);
        }
    }

    /**
     * Download backup file through temporary signed link.
     */
    public function signedDownload(Request $request, $filename): BinaryFileResponse|JsonResponse
    {
        return $this->streamBackupDownload($filename);
    }

    private function streamBackupDownload($filename): BinaryFileResponse|JsonResponse
    {
        try {
            $backupPath = $this->resolveBackupFilePath($filename);

            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            $safeFilename = basename(trim((string) $filename));

            return response()->download($backupPath, $safeFilename, [
                'Content-Type' => 'application/zip',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to download backup', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to download backup'
            ], 500);
        }
    }

    /**
     * Delete backup file
     */
    public function delete($filename): JsonResponse
    {
        try {
            $backupPath = $this->resolveBackupFilePath($filename);
            
            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            unlink($backupPath);

            // Log backup deletion
            $this->logBackupActivity('delete', $filename, 'unknown', 'Manual deletion');

            return response()->json([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete backup', [
                'error' => $e->getMessage(),
                'filename' => $filename
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete backup'
            ], 500);
        }
    }

    /**
     * Restore from backup
     */
    public function restore(Request $request, $filename): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'confirm' => 'required|boolean|accepted'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Confirmation required for restore operation'
                ], 422);
            }

            $backupPath = $this->resolveBackupFilePath($filename);
            
            if (!file_exists($backupPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Backup file not found'
                ], 404);
            }

            // Extract backup type from filename
            preg_match('/backup_\d{4}-\d{2}-\d{2}_\d{2}-\d{2}-\d{2}_(.+)\.zip/', $filename, $matches);
            $type = isset($matches[1]) ? $matches[1] : 'unknown';

            // Create restore point before restoring
            $restorePointFilename = "restore_point_" . Carbon::now()->format('Y-m-d_H-i-s') . "_before_{$type}.zip";
            $this->createFullBackup(storage_path('app/backups/' . $restorePointFilename), 'Auto restore point');

            // Perform restore based on type
            switch ($type) {
                case 'full':
                    $this->restoreFullBackup($backupPath);
                    break;
                case 'database':
                    $this->restoreDatabaseBackup($backupPath);
                    break;
                case 'files':
                    $this->restoreFilesBackup($backupPath);
                    break;
                default:
                    throw new \Exception('Unknown backup type');
            }

            // Log restore activity
            $this->logBackupActivity('restore', $filename, $type, 'Manual restore');

            return response()->json([
                'success' => true,
                'message' => 'Backup restored successfully',
                'data' => [
                    'restored_from' => $filename,
                    'restore_point' => $restorePointFilename,
                    'restored_at' => Carbon::now()->format('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to restore backup', [
                'error' => $e->getMessage(),
                'filename' => $filename,
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore backup'
            ], 500);
        }
    }

    /**
     * Get backup settings
     */
    public function getSettings(): JsonResponse
    {
        try {
            $settings = $this->readBackupSettings();
            $settings['storage_path'] = storage_path('app/backups');

            return response()->json([
                'success' => true,
                'message' => 'Backup settings retrieved successfully',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve backup settings', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve backup settings'
            ], 500);
        }
    }

    /**
     * Update backup settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'auto_backup_enabled' => 'boolean',
                'backup_frequency' => 'in:hourly,daily,weekly,monthly',
                'retention_days' => 'integer|min:1|max:365',
                'backup_types' => 'array',
                'backup_types.*' => 'in:full,database,files',
                'backup_run_time' => 'nullable|date_format:H:i',
                'backup_weekly_day' => 'nullable|integer|min:1|max:7',
                'backup_monthly_day' => 'nullable|integer|min:1|max:31',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $existingSettings = $this->readBackupSettings();
            $settings = array_merge($existingSettings, [
                'auto_backup_enabled' => $request->get('auto_backup_enabled', false),
                'backup_frequency' => $request->get('backup_frequency', 'daily'),
                'retention_days' => $request->get('retention_days', 30),
                'backup_types' => $request->get('backup_types', ['database']),
                'backup_run_time' => $request->get('backup_run_time', $existingSettings['backup_run_time'] ?? '01:00'),
                'backup_weekly_day' => (int) $request->get('backup_weekly_day', $existingSettings['backup_weekly_day'] ?? 1),
                'backup_monthly_day' => (int) $request->get('backup_monthly_day', $existingSettings['backup_monthly_day'] ?? 1),
                'max_backup_size' => $existingSettings['max_backup_size'] ?? '100MB',
            ]);

            $this->writeBackupSettings($settings);

            return response()->json([
                'success' => true,
                'message' => 'Backup settings updated successfully',
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update backup settings', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update backup settings'
            ], 500);
        }
    }

    /**
     * Clean old backups
     */
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $retentionDays = (int) $request->get('retention_days', 30);
            $result = $this->cleanupExpiredBackups($retentionDays, AuthHelper::userId());

            return response()->json([
                'success' => true,
                'message' => 'Backup cleanup completed',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cleanup backups', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup backups'
            ], 500);
        }
    }

    /**
     * Run backup process for a specific type.
     *
     * @return array<string, mixed>
     */
    public function runBackupProcess(string $type, string $description = 'Manual backup', ?int $actorUserId = null): array
    {
        $normalizedType = strtolower(trim($type));
        if (!in_array($normalizedType, ['full', 'database', 'files'], true)) {
            throw new \InvalidArgumentException("Invalid backup type: {$type}");
        }

        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        $filename = "backup_{$timestamp}_{$normalizedType}.zip";
        $backupPath = storage_path('app/backups');

        if (!file_exists($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        $fullPath = $backupPath . '/' . $filename;

        try {
            switch ($normalizedType) {
                case 'full':
                    $this->createFullBackup($fullPath, $description);
                    break;
                case 'database':
                    $this->createDatabaseBackup($fullPath, $description);
                    break;
                case 'files':
                    $this->createFilesBackup($fullPath, $description);
                    break;
            }

            $sizeBytes = file_exists($fullPath) ? (int) filesize($fullPath) : 0;
            $createdAt = Carbon::now();

            $this->logBackupActivity(
                'create',
                $filename,
                $normalizedType,
                $description,
                'success',
                [
                    'size' => $sizeBytes,
                    'created_at' => $createdAt->toISOString(),
                    'is_automatic' => $actorUserId === null,
                ],
                null,
                $actorUserId
            );

            return [
                'filename' => $filename,
                'type' => $normalizedType,
                'size' => $this->formatBytes($sizeBytes),
                'size_bytes' => $sizeBytes,
                'created_at' => $createdAt->format('Y-m-d H:i:s'),
            ];
        } catch (\Throwable $e) {
            $this->logBackupActivity(
                'create',
                $filename,
                $normalizedType,
                $description,
                'failed',
                null,
                $e->getMessage(),
                $actorUserId
            );

            throw $e;
        }
    }

    /**
     * Cleanup backup files older than retention days.
     *
     * @return array{deleted_files: array<int, string>, deleted_count: int, retention_days: int}
     */
    public function cleanupExpiredBackups(int $retentionDays = 30, ?int $actorUserId = null): array
    {
        $retentionDays = max(1, $retentionDays);
        $backupPath = storage_path('app/backups');
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        $deletedFiles = [];

        if (is_dir($backupPath)) {
            $files = glob($backupPath . '/*.zip') ?: [];
            foreach ($files as $file) {
                $fileDate = Carbon::createFromTimestamp(filemtime($file));

                if ($fileDate->lt($cutoffDate)) {
                    $filename = basename($file);
                    unlink($file);
                    $deletedFiles[] = $filename;
                }
            }
        }

        $this->logBackupActivity(
            'cleanup',
            'cleanup_' . Carbon::now()->format('Y-m-d_H-i-s') . '.zip',
            'system',
            "Cleanup backup lebih lama dari {$retentionDays} hari",
            'success',
            [
                'deleted_files' => $deletedFiles,
                'deleted_count' => count($deletedFiles),
            ],
            null,
            $actorUserId
        );

        return [
            'deleted_files' => $deletedFiles,
            'deleted_count' => count($deletedFiles),
            'retention_days' => $retentionDays,
        ];
    }

    /**
     * Create full backup (database + files)
     */
    private function createFullBackup($path, $description)
    {
        $zip = new ZipArchive();
        
        if ($zip->open($path, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create backup file');
        }

        // Add database dump
        $dbDumpPath = $this->createDatabaseDump();
        $zip->addFile($dbDumpPath, 'database.sql');

        // Add important files
        $this->addFilesToZip($zip, storage_path('app'), 'storage/');
        $this->addFilesToZip($zip, public_path('uploads'), 'uploads/');

        // Add backup info
        $backupInfo = [
            'created_at' => Carbon::now()->toISOString(),
            'type' => 'full',
            'description' => $description,
            'version' => config('app.version', '1.0.0')
        ];
        $zip->addFromString('backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));

        $zip->close();

        // Clean up temporary database dump
        unlink($dbDumpPath);
    }

    /**
     * Create database backup
     */
    private function createDatabaseBackup($path, $description)
    {
        $zip = new ZipArchive();
        
        if ($zip->open($path, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create backup file');
        }

        // Add database dump
        $dbDumpPath = $this->createDatabaseDump();
        $zip->addFile($dbDumpPath, 'database.sql');

        // Add backup info
        $backupInfo = [
            'created_at' => Carbon::now()->toISOString(),
            'type' => 'database',
            'description' => $description,
            'version' => config('app.version', '1.0.0')
        ];
        $zip->addFromString('backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));

        $zip->close();

        // Clean up temporary database dump
        unlink($dbDumpPath);
    }

    /**
     * Create files backup
     */
    private function createFilesBackup($path, $description)
    {
        $zip = new ZipArchive();
        
        if ($zip->open($path, ZipArchive::CREATE) !== TRUE) {
            throw new \Exception('Cannot create backup file');
        }

        // Add important files
        $this->addFilesToZip($zip, storage_path('app'), 'storage/');
        $this->addFilesToZip($zip, public_path('uploads'), 'uploads/');

        // Add backup info
        $backupInfo = [
            'created_at' => Carbon::now()->toISOString(),
            'type' => 'files',
            'description' => $description,
            'version' => config('app.version', '1.0.0')
        ];
        $zip->addFromString('backup_info.json', json_encode($backupInfo, JSON_PRETTY_PRINT));

        $zip->close();
    }

    /**
     * Create database dump
     */
    private function createDatabaseDump()
    {
        $dumpPath = storage_path('app/temp_db_dump_' . Carbon::now()->format('Ymd_His_u') . '.sql');
        $dbConfig = config('database.connections.' . config('database.default'));

        $driver = (string) ($dbConfig['driver'] ?? 'mysql');
        if ($driver === 'sqlite') {
            $databasePath = (string) ($dbConfig['database'] ?? '');
            if ($databasePath === '' || !file_exists($databasePath)) {
                throw new \Exception('Database sqlite tidak ditemukan untuk backup');
            }

            if (!copy($databasePath, $dumpPath)) {
                throw new \Exception('Gagal membuat salinan database sqlite');
            }

            return $dumpPath;
        }

        if ($driver === 'pgsql' && DIRECTORY_SEPARATOR === '\\') {
            return $this->createPostgresDumpViaExec($dbConfig, $dumpPath);
        }

        $process = $this->buildDatabaseDumpProcess($dbConfig, $dumpPath);
        $process->setTimeout(300);
        $process->run();

        $stdout = $process->getOutput();
        if (!$process->isSuccessful()) {
            Log::error('Database dump process failed', [
                'driver' => $driver,
                'dump_path' => $dumpPath,
                'command' => $process->getCommandLine(),
                'exit_code' => $process->getExitCode(),
                'stdout' => $stdout,
                'stderr' => $process->getErrorOutput(),
                'file_exists' => file_exists($dumpPath),
            ]);
            throw new \Exception('Database dump failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        if (!file_exists($dumpPath)) {
            if ($stdout === '') {
                Log::error('Database dump completed without output file', [
                    'driver' => $driver,
                    'dump_path' => $dumpPath,
                    'command' => $process->getCommandLine(),
                    'exit_code' => $process->getExitCode(),
                    'stdout' => $stdout,
                    'stderr' => $process->getErrorOutput(),
                ]);

                throw new \Exception('Database dump completed without output');
            }

            file_put_contents($dumpPath, $stdout);
        }

        return $dumpPath;
    }

    private function createPostgresDumpViaExec(array $dbConfig, string $dumpPath): string
    {
        $binary = $this->resolveDatabaseBinary('pg_dump', [
            'PG_DUMP_BINARY',
            'POSTGRES_DUMP_BINARY',
        ]);

        $command = implode(' ', [
            '"' . str_replace('\\', '/', $binary) . '"',
            '-h ' . escapeshellarg((string) ($dbConfig['host'] ?? '127.0.0.1')),
            '-p ' . escapeshellarg((string) ($dbConfig['port'] ?? 5432)),
            '-U ' . escapeshellarg((string) ($dbConfig['username'] ?? '')),
            '--no-password',
            '--file=' . escapeshellarg(str_replace('\\', '/', $dumpPath)),
            '--dbname=' . escapeshellarg((string) ($dbConfig['database'] ?? '')),
            '2>&1',
        ]);

        $previousPassword = getenv('PGPASSWORD');
        $hasPassword = !empty($dbConfig['password']);
        if ($hasPassword) {
            putenv('PGPASSWORD=' . (string) $dbConfig['password']);
        }

        try {
            $output = [];
            $exitCode = 1;
            exec($command, $output, $exitCode);

            if ($exitCode !== 0 || !file_exists($dumpPath)) {
                $stderr = trim(implode(PHP_EOL, $output));

                Log::error('Database dump process failed', [
                    'driver' => 'pgsql',
                    'dump_path' => $dumpPath,
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'stdout' => '',
                    'stderr' => $stderr,
                    'file_exists' => file_exists($dumpPath),
                    'strategy' => 'exec',
                ]);

                throw new \Exception('Database dump failed: ' . ($stderr !== '' ? $stderr : 'pg_dump exited with code ' . $exitCode));
            }
        } finally {
            if ($hasPassword) {
                if ($previousPassword === false) {
                    putenv('PGPASSWORD');
                } else {
                    putenv('PGPASSWORD=' . $previousPassword);
                }
            }
        }

        return $dumpPath;
    }

    /**
     * Add files to zip recursively
     */
    private function addFilesToZip($zip, $sourcePath, $zipPath = '')
    {
        if (!is_dir($sourcePath)) {
            return;
        }

        $sourceRoot = rtrim((string) realpath($sourcePath), DIRECTORY_SEPARATOR);
        if ($sourceRoot === '') {
            return;
        }

        $excludedDirectories = [
            storage_path('app/backups'),
            storage_path('app/temp_restore'),
        ];
        $excludedDirectories = array_filter(array_map(function ($path) {
            $resolved = realpath($path);
            return $resolved !== false ? rtrim($resolved, DIRECTORY_SEPARATOR) : null;
        }, $excludedDirectories));

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourcePath, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }

            $filePath = $file->getRealPath();
            if ($filePath === false) {
                continue;
            }

            $normalizedFilePath = rtrim($filePath, DIRECTORY_SEPARATOR);
            $shouldSkip = false;
            foreach ($excludedDirectories as $excludedDirectory) {
                if (str_starts_with($normalizedFilePath, $excludedDirectory . DIRECTORY_SEPARATOR)
                    || $normalizedFilePath === $excludedDirectory) {
                    $shouldSkip = true;
                    break;
                }
            }

            if ($shouldSkip) {
                continue;
            }

            $relativeSegment = ltrim(substr($filePath, strlen($sourceRoot)), DIRECTORY_SEPARATOR);
            $relativePath = trim($zipPath . str_replace(DIRECTORY_SEPARATOR, '/', $relativeSegment), '/');
            
            if ($relativePath !== '') {
                $zip->addFile($filePath, $relativePath);
            }
        }
    }

    /**
     * Restore full backup
     */
    private function restoreFullBackup($backupPath)
    {
        $extractPath = $this->createTemporaryRestoreDirectory();
        
        // Extract backup
        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== TRUE) {
            throw new \Exception('Cannot open backup file');
        }
        
        $zip->extractTo($extractPath);
        $zip->close();

        // Restore database
        if (file_exists($extractPath . '/database.sql')) {
            $this->restoreDatabase($extractPath . '/database.sql');
        }

        // Restore files
        if (is_dir($extractPath . '/storage')) {
            $this->copyDirectory($extractPath . '/storage', storage_path('app'));
        }
        
        if (is_dir($extractPath . '/uploads')) {
            $this->copyDirectory($extractPath . '/uploads', public_path('uploads'));
        }

        // Clean up
        $this->deleteDirectory($extractPath);
    }

    /**
     * Restore database backup
     */
    private function restoreDatabaseBackup($backupPath)
    {
        $extractPath = $this->createTemporaryRestoreDirectory();
        
        // Extract backup
        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== TRUE) {
            throw new \Exception('Cannot open backup file');
        }
        
        $zip->extractTo($extractPath);
        $zip->close();

        // Restore database
        if (file_exists($extractPath . '/database.sql')) {
            $this->restoreDatabase($extractPath . '/database.sql');
        }

        // Clean up
        $this->deleteDirectory($extractPath);
    }

    /**
     * Restore files backup
     */
    private function restoreFilesBackup($backupPath)
    {
        $extractPath = $this->createTemporaryRestoreDirectory();
        
        // Extract backup
        $zip = new ZipArchive();
        if ($zip->open($backupPath) !== TRUE) {
            throw new \Exception('Cannot open backup file');
        }
        
        $zip->extractTo($extractPath);
        $zip->close();

        // Restore files
        if (is_dir($extractPath . '/storage')) {
            $this->copyDirectory($extractPath . '/storage', storage_path('app'));
        }
        
        if (is_dir($extractPath . '/uploads')) {
            $this->copyDirectory($extractPath . '/uploads', public_path('uploads'));
        }

        // Clean up
        $this->deleteDirectory($extractPath);
    }

    /**
     * Restore database from SQL file
     */
    private function restoreDatabase($sqlFile)
    {
        $dbConfig = config('database.connections.' . config('database.default'));
        $driver = (string) ($dbConfig['driver'] ?? 'mysql');

        if ($driver === 'sqlite') {
            $databasePath = (string) ($dbConfig['database'] ?? '');
            if ($databasePath === '' || !file_exists($sqlFile)) {
                throw new \Exception('File restore sqlite tidak ditemukan');
            }

            if (!copy($sqlFile, $databasePath)) {
                throw new \Exception('Gagal me-restore database sqlite');
            }

            return;
        }

        if ($driver === 'pgsql' && DIRECTORY_SEPARATOR === '\\') {
            $this->restorePostgresDumpViaExec($dbConfig, $sqlFile);
            return;
        }

        $process = $this->buildDatabaseRestoreProcess($dbConfig, $sqlFile);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('Database restore process failed', [
                'driver' => $driver,
                'sql_file' => $sqlFile,
                'command' => $process->getCommandLine(),
                'exit_code' => $process->getExitCode(),
                'stdout' => $process->getOutput(),
                'stderr' => $process->getErrorOutput(),
            ]);
            throw new \Exception('Database restore failed: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function restorePostgresDumpViaExec(array $dbConfig, string $sqlFile): void
    {
        $binary = $this->resolveDatabaseBinary('psql', [
            'PSQL_BINARY',
            'POSTGRES_PSQL_BINARY',
        ]);

        $command = implode(' ', [
            '"' . str_replace('\\', '/', $binary) . '"',
            '-h ' . escapeshellarg((string) ($dbConfig['host'] ?? '127.0.0.1')),
            '-p ' . escapeshellarg((string) ($dbConfig['port'] ?? 5432)),
            '-U ' . escapeshellarg((string) ($dbConfig['username'] ?? '')),
            '--no-password',
            '--dbname=' . escapeshellarg((string) ($dbConfig['database'] ?? '')),
            '--file=' . escapeshellarg(str_replace('\\', '/', $sqlFile)),
            '2>&1',
        ]);

        $previousPassword = getenv('PGPASSWORD');
        $hasPassword = !empty($dbConfig['password']);
        if ($hasPassword) {
            putenv('PGPASSWORD=' . (string) $dbConfig['password']);
        }

        try {
            $output = [];
            $exitCode = 1;
            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                $stderr = trim(implode(PHP_EOL, $output));

                Log::error('Database restore process failed', [
                    'driver' => 'pgsql',
                    'sql_file' => $sqlFile,
                    'command' => $command,
                    'exit_code' => $exitCode,
                    'stdout' => '',
                    'stderr' => $stderr,
                    'strategy' => 'exec',
                ]);

                throw new \Exception('Database restore failed: ' . ($stderr !== '' ? $stderr : 'psql exited with code ' . $exitCode));
            }
        } finally {
            if ($hasPassword) {
                if ($previousPassword === false) {
                    putenv('PGPASSWORD');
                } else {
                    putenv('PGPASSWORD=' . $previousPassword);
                }
            }
        }
    }

    /**
     * Copy directory recursively
     */
    private function copyDirectory($source, $destination)
    {
        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $sourceRoot = rtrim((string) realpath($source), DIRECTORY_SEPARATOR);
        if ($sourceRoot === '') {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $pathname = $file->getPathname();
            $relativePath = ltrim(substr($pathname, strlen($sourceRoot)), DIRECTORY_SEPARATOR);
            $targetPath = $destination . DIRECTORY_SEPARATOR . $relativePath;

            if ($file->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0755, true);
                }
            } else {
                $targetDirectory = dirname($targetPath);
                if (!is_dir($targetDirectory)) {
                    mkdir($targetDirectory, 0755, true);
                }

                copy($pathname, $targetPath);
            }
        }
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Log backup activity
     */
    private function logBackupActivity(
        $action,
        $filename,
        $type,
        $description,
        ?string $status = null,
        ?array $metadata = null,
        ?string $errorMessage = null,
        ?int $actorUserId = null
    )
    {
        try {
            DB::table('backup_logs')->insert([
                'action' => $action,
                'filename' => $filename,
                'type' => $type,
                'description' => $description,
                'user_id' => $actorUserId ?? AuthHelper::userId(),
                'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
                'status' => $status,
                'error_message' => $errorMessage,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);
        } catch (\Exception $e) {
            // Log to file if database logging fails
            \Log::info("Backup activity: {$action} - {$filename} - {$type} - {$description}");
        }
    }

    private function readBackupSettings(): array
    {
        $defaults = [
            'auto_backup_enabled' => false,
            'backup_frequency' => 'daily',
            'retention_days' => 30,
            'backup_types' => ['database'],
            'backup_run_time' => '01:00',
            'backup_weekly_day' => 1,
            'backup_monthly_day' => 1,
            'max_backup_size' => '100MB',
        ];

        $configPath = storage_path('app/backup_settings.json');
        if (!file_exists($configPath)) {
            return $defaults;
        }

        $decoded = json_decode((string) file_get_contents($configPath), true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        return array_merge($defaults, $decoded);
    }

    private function writeBackupSettings(array $settings): void
    {
        $configPath = storage_path('app/backup_settings.json');
        file_put_contents($configPath, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function resolveBackupFilePath(string $filename): string
    {
        $safeFilename = basename(trim($filename));
        return storage_path('app/backups/' . $safeFilename);
    }

    private function createTemporaryRestoreDirectory(): string
    {
        $path = storage_path('app/temp_restore_' . Carbon::now()->format('Ymd_His_u'));
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    private function buildDatabaseDumpProcess(array $dbConfig, string $dumpPath): Process
    {
        $driver = (string) ($dbConfig['driver'] ?? 'mysql');

        if ($driver === 'pgsql') {
            $binary = $this->resolveDatabaseBinary('pg_dump', [
                'PG_DUMP_BINARY',
                'POSTGRES_DUMP_BINARY',
            ]);
            $command = [
                $binary,
                '-h', (string) ($dbConfig['host'] ?? '127.0.0.1'),
                '-p', (string) ($dbConfig['port'] ?? 5432),
                '-U', (string) ($dbConfig['username'] ?? ''),
                '--no-password',
                '--dbname=' . (string) ($dbConfig['database'] ?? ''),
            ];

            return $this->buildDatabaseProcess($command, !empty($dbConfig['password'])
                ? ['PGPASSWORD' => (string) $dbConfig['password']]
                : []);
        }

        $binary = $this->resolveDatabaseBinary('mysqldump', [
            'MYSQLDUMP_BINARY',
            'MYSQL_DUMP_BINARY',
        ]);
        $command = [
            $binary,
            '-h' . (string) ($dbConfig['host'] ?? '127.0.0.1'),
            '-P' . (string) ($dbConfig['port'] ?? 3306),
            '-u' . (string) ($dbConfig['username'] ?? ''),
            (string) ($dbConfig['database'] ?? ''),
        ];

        if (!empty($dbConfig['password'])) {
            $command[] = '--password=' . (string) $dbConfig['password'];
        }

        return $this->buildDatabaseProcess($command);
    }

    private function buildDatabaseRestoreProcess(array $dbConfig, string $sqlFile): Process
    {
        $driver = (string) ($dbConfig['driver'] ?? 'mysql');
        $binarySafeSqlPath = str_replace('\\', '/', $sqlFile);

        if ($driver === 'pgsql') {
            $binary = $this->resolveDatabaseBinary('psql', [
                'PSQL_BINARY',
                'POSTGRES_PSQL_BINARY',
            ]);
            $command = [
                $binary,
                '-h', (string) ($dbConfig['host'] ?? '127.0.0.1'),
                '-p', (string) ($dbConfig['port'] ?? 5432),
                '-U', (string) ($dbConfig['username'] ?? ''),
                '--no-password',
                '--dbname=' . (string) ($dbConfig['database'] ?? ''),
                '--file=' . $binarySafeSqlPath,
            ];

            return $this->buildDatabaseProcess($command, !empty($dbConfig['password'])
                ? ['PGPASSWORD' => (string) $dbConfig['password']]
                : []);
        }

        $binary = $this->resolveDatabaseBinary('mysql', [
            'MYSQL_BINARY',
        ]);
        $command = [
            $binary,
            '-h' . (string) ($dbConfig['host'] ?? '127.0.0.1'),
            '-P' . (string) ($dbConfig['port'] ?? 3306),
            '-u' . (string) ($dbConfig['username'] ?? ''),
            (string) ($dbConfig['database'] ?? ''),
            '-e',
            'source ' . $binarySafeSqlPath,
        ];

        if (!empty($dbConfig['password'])) {
            $command[] = '--password=' . (string) $dbConfig['password'];
        }

        return $this->buildDatabaseProcess($command);
    }

    private function buildDatabaseProcess(array $command, array $env = []): Process
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $quoted = array_map(function (string $segment): string {
                if ($segment === '') {
                    return '""';
                }

                if (!preg_match('/[\s"\^&|<>]/', $segment)) {
                    return $segment;
                }

                return '"' . str_replace('"', '\"', $segment) . '"';
            }, $command);

            return Process::fromShellCommandline(implode(' ', $quoted), null, $env);
        }

        return new Process($command, null, $env);
    }

    private function resolveDatabaseBinary(string $binary, array $envKeys = []): string
    {
        foreach ($envKeys as $envKey) {
            $configured = trim((string) env($envKey, ''));
            if ($configured !== '' && file_exists($configured)) {
                return $configured;
            }
        }

        $candidates = DIRECTORY_SEPARATOR === '\\'
            ? match ($binary) {
                'pg_dump' => [
                    'C:/laragon/bin/postgresql/*/bin/pg_dump.exe',
                    'C:/Program Files/PostgreSQL/*/bin/pg_dump.exe',
                    'C:/Program Files (x86)/PostgreSQL/*/bin/pg_dump.exe',
                ],
                'psql' => [
                    'C:/laragon/bin/postgresql/*/bin/psql.exe',
                    'C:/Program Files/PostgreSQL/*/bin/psql.exe',
                    'C:/Program Files (x86)/PostgreSQL/*/bin/psql.exe',
                ],
                'mysqldump' => [
                    'C:/laragon/bin/mysql/*/bin/mysqldump.exe',
                    'C:/Program Files/MySQL/*/bin/mysqldump.exe',
                ],
                'mysql' => [
                    'C:/laragon/bin/mysql/*/bin/mysql.exe',
                    'C:/Program Files/MySQL/*/bin/mysql.exe',
                ],
                default => [],
            }
            : match ($binary) {
                'pg_dump' => [
                    '/usr/bin/pg_dump',
                    '/usr/local/bin/pg_dump',
                    '/bin/pg_dump',
                ],
                'psql' => [
                    '/usr/bin/psql',
                    '/usr/local/bin/psql',
                    '/bin/psql',
                ],
                'mysqldump' => [
                    '/usr/bin/mysqldump',
                    '/usr/local/bin/mysqldump',
                    '/bin/mysqldump',
                ],
                'mysql' => [
                    '/usr/bin/mysql',
                    '/usr/local/bin/mysql',
                    '/bin/mysql',
                ],
                default => [],
            };

        foreach ($candidates as $pattern) {
            if (str_contains($pattern, '*')) {
                $matches = glob($pattern);
                if (is_array($matches) && $matches !== []) {
                    rsort($matches, SORT_NATURAL);
                    return $matches[0];
                }
                continue;
            }

            if (file_exists($pattern)) {
                return $pattern;
            }
        }

        $resolvedFromPath = DIRECTORY_SEPARATOR === '\\'
            ? trim((string) shell_exec('where ' . escapeshellcmd($binary) . ' 2>NUL'))
            : trim((string) shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null'));

        if ($resolvedFromPath !== '') {
            $first = preg_split('/\r\n|\r|\n/', $resolvedFromPath)[0] ?? '';
            if ($first !== '') {
                return $first;
            }
        }

        throw new \RuntimeException("Executable {$binary} tidak ditemukan. Set PATH atau env binary terkait.");
    }
}
