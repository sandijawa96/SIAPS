<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'live_tracking';
    private const USER_TRACKED_AT_INDEX = 'live_tracking_user_tracked_at_idx';
    private const TRACKED_AT_INDEX = 'live_tracking_tracked_at_idx';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if (!$this->indexExists(self::TABLE, self::USER_TRACKED_AT_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index(['user_id', 'tracked_at'], self::USER_TRACKED_AT_INDEX);
            });
        }

        if (!$this->indexExists(self::TABLE, self::TRACKED_AT_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->index('tracked_at', self::TRACKED_AT_INDEX);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        if ($this->indexExists(self::TABLE, self::USER_TRACKED_AT_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::USER_TRACKED_AT_INDEX);
            });
        }

        if ($this->indexExists(self::TABLE, self::TRACKED_AT_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex(self::TRACKED_AT_INDEX);
            });
        }
    }

    private function indexExists(string $tableName, string $indexName): bool
    {
        $driver = DB::getDriverName();

        return match ($driver) {
            'mysql' => $this->indexExistsMySql($tableName, $indexName),
            'pgsql' => $this->indexExistsPgSql($tableName, $indexName),
            'sqlite' => $this->indexExistsSqlite($tableName, $indexName),
            'sqlsrv' => $this->indexExistsSqlServer($tableName, $indexName),
            default => false,
        };
    }

    private function indexExistsMySql(string $tableName, string $indexName): bool
    {
        try {
            $result = DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM information_schema.statistics
                 WHERE table_schema = DATABASE()
                   AND table_name = ?
                   AND index_name = ?',
                [$tableName, $indexName]
            );

            return ((int) ($result->total ?? 0)) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function indexExistsPgSql(string $tableName, string $indexName): bool
    {
        try {
            $result = DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM pg_indexes
                 WHERE schemaname = current_schema()
                   AND tablename = ?
                   AND indexname = ?',
                [$tableName, $indexName]
            );

            return ((int) ($result->total ?? 0)) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function indexExistsSqlite(string $tableName, string $indexName): bool
    {
        try {
            $rows = DB::select("PRAGMA index_list('{$tableName}')");

            foreach ($rows as $row) {
                if (($row->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    private function indexExistsSqlServer(string $tableName, string $indexName): bool
    {
        try {
            $result = DB::selectOne(
                'SELECT COUNT(*) AS total
                 FROM sys.indexes
                 WHERE object_id = OBJECT_ID(?)
                   AND name = ?',
                [$tableName, $indexName]
            );

            return ((int) ($result->total ?? 0)) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
