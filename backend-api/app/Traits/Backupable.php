<?php

namespace App\Traits;

use App\Models\BackupLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;

trait Backupable
{
    /**
     * Get backup configuration.
     */
    protected function getBackupConfig(): array
    {
        return [
            'disk' => env('BACKUP_DISK', 'local'),
            'path' => env('BACKUP_PATH', 'backups'),
            'retention_days' => env('BACKUP_RETENTION_DAYS', 30)
        ];
    }

    /**
     * Create a backup of the model's data.
     */
    public function backup(array $options = []): array
    {
        $config = $this->getBackupConfig();
        $timestamp = Carbon::now()->format('Y_m_d_His');
        $table = $this->getTable();
        $filename = "{$table}_{$timestamp}.json";

        try {
            // Get data
            $data = $this->getBackupData($options);

            // Create JSON file
            $jsonData = json_encode($data, JSON_PRETTY_PRINT);
            Storage::disk($config['disk'])->put(
                "{$config['path']}/{$filename}", 
                $jsonData
            );

            // Log backup
            $backupLog = BackupLog::create([
                'type' => 'model',
                'name' => class_basename($this),
                'file_name' => $filename,
                'file_size' => Storage::disk($config['disk'])->size("{$config['path']}/{$filename}"),
                'status' => 'completed',
                'metadata' => [
                    'table' => $table,
                    'records' => count($data),
                    'options' => $options
                ]
            ]);

            return [
                'success' => true,
                'message' => 'Backup created successfully',
                'backup' => $backupLog
            ];

        } catch (\Exception $e) {
            // Log error
            BackupLog::create([
                'type' => 'model',
                'name' => class_basename($this),
                'status' => 'failed',
                'error' => $e->getMessage(),
                'metadata' => [
                    'table' => $table,
                    'options' => $options
                ]
            ]);

            return [
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get data for backup.
     */
    protected function getBackupData(array $options = []): array
    {
        $query = $this->query();

        // Apply filters
        if (isset($options['where'])) {
            $query->where($options['where']);
        }

        if (isset($options['whereIn'])) {
            foreach ($options['whereIn'] as $column => $values) {
                $query->whereIn($column, $values);
            }
        }

        // Get relationships to include
        $relations = $options['relations'] ?? [];
        if (!empty($relations)) {
            $query->with($relations);
        }

        return $query->get()->toArray();
    }

    /**
     * Clean up old backups.
     */
    public function cleanupBackups(): array
    {
        $config = $this->getBackupConfig();
        $disk = Storage::disk($config['disk']);
        $path = $config['path'];
        $table = $this->getTable();
        $pattern = "{$table}_*.json";
        $retentionDays = $config['retention_days'];

        try {
            $deleted = 0;
            $cutoffDate = Carbon::now()->subDays($retentionDays);

            // Get all backup files for this model
            $files = $disk->files($path);
            foreach ($files as $file) {
                if (!fnmatch($pattern, basename($file))) {
                    continue;
                }

                // Check file age
                $lastModified = Carbon::createFromTimestamp($disk->lastModified($file));
                if ($lastModified->lt($cutoffDate)) {
                    $disk->delete($file);
                    $deleted++;
                }
            }

            // Log cleanup
            BackupLog::create([
                'type' => 'cleanup',
                'name' => class_basename($this),
                'status' => 'completed',
                'metadata' => [
                    'files_deleted' => $deleted,
                    'retention_days' => $retentionDays,
                    'cutoff_date' => $cutoffDate->toDateTimeString()
                ]
            ]);

            return [
                'success' => true,
                'message' => "Cleanup completed. Deleted {$deleted} old backup(s)."
            ];

        } catch (\Exception $e) {
            // Log error
            BackupLog::create([
                'type' => 'cleanup',
                'name' => class_basename($this),
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => 'Cleanup failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get list of available backups.
     */
    public function listBackups(): array
    {
        $config = $this->getBackupConfig();
        $disk = Storage::disk($config['disk']);
        $path = $config['path'];
        $table = $this->getTable();
        $pattern = "{$table}_*.json";

        try {
            $backups = [];
            $files = $disk->files($path);

            foreach ($files as $file) {
                if (!fnmatch($pattern, basename($file))) {
                    continue;
                }

                $backups[] = [
                    'filename' => basename($file),
                    'size' => $disk->size($file),
                    'last_modified' => Carbon::createFromTimestamp($disk->lastModified($file)),
                    'path' => $file
                ];
            }

            // Sort by last modified (newest first)
            usort($backups, function ($a, $b) {
                return $b['last_modified']->timestamp - $a['last_modified']->timestamp;
            });

            return [
                'success' => true,
                'backups' => $backups
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to list backups: ' . $e->getMessage()
            ];
        }
    }
}
