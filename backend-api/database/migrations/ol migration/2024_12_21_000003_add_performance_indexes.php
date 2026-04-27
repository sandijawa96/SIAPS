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
        // Add indexes for better performance on frequently queried columns

        // Users table indexes
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                // Index for status_kepegawaian filtering (ASN exclusion)
                if (!$this->indexExists('users', 'users_status_kepegawaian_index')) {
                    $table->index('status_kepegawaian', 'users_status_kepegawaian_index');
                }

                // Composite index for common queries
                if (!$this->indexExists('users', 'users_status_created_index')) {
                    $table->index(['status_kepegawaian', 'created_at'], 'users_status_created_index');
                }

                // Index for role-based queries
                if (Schema::hasColumn('users', 'role') && !$this->indexExists('users', 'users_role_index')) {
                    $table->index('role', 'users_role_index');
                }
            });
        }

        // Attendance schema assignments table indexes
        if (Schema::hasTable('attendance_schema_assignments')) {
            Schema::table('attendance_schema_assignments', function (Blueprint $table) {
                // Composite index for active assignments lookup
                if (!$this->indexExists('attendance_schema_assignments', 'asa_user_active_dates_index')) {
                    $table->index(['user_id', 'is_active', 'start_date', 'end_date'], 'asa_user_active_dates_index');
                }

                // Index for assignment type filtering
                if (!$this->indexExists('attendance_schema_assignments', 'asa_assignment_type_index')) {
                    $table->index('assignment_type', 'asa_assignment_type_index');
                }

                // Index for schema lookups
                if (!$this->indexExists('attendance_schema_assignments', 'asa_schema_active_index')) {
                    $table->index(['attendance_setting_id', 'is_active'], 'asa_schema_active_index');
                }
            });
        }

        // Attendance schemas table indexes
        if (Schema::hasTable('attendance_schemas')) {
            Schema::table('attendance_schemas', function (Blueprint $table) {
                // Composite index for active default schemas
                if (!$this->indexExists('attendance_schemas', 'as_type_active_default_index')) {
                    $table->index(['schema_type', 'is_active', 'is_default'], 'as_type_active_default_index');
                }

                // Index for target filtering
                if (!$this->indexExists('attendance_schemas', 'as_target_role_status_index')) {
                    $table->index(['target_role', 'target_status'], 'as_target_role_status_index');
                }
            });
        }

        // Absensi table indexes for historical data queries
        if (Schema::hasTable('absensi')) {
            Schema::table('absensi', function (Blueprint $table) {
                // Composite index for user date queries
                if (!$this->indexExists('absensi', 'absensi_user_date_index')) {
                    $table->index(['user_id', 'tanggal'], 'absensi_user_date_index');
                }

                // Index for date range queries
                if (!$this->indexExists('absensi', 'absensi_tanggal_status_index')) {
                    $table->index(['tanggal', 'status'], 'absensi_tanggal_status_index');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes in reverse order

        if (Schema::hasTable('absensi')) {
            Schema::table('absensi', function (Blueprint $table) {
                $table->dropIndex('absensi_user_date_index');
                $table->dropIndex('absensi_tanggal_status_index');
            });
        }

        if (Schema::hasTable('attendance_schemas')) {
            Schema::table('attendance_schemas', function (Blueprint $table) {
                $table->dropIndex('as_type_active_default_index');
                $table->dropIndex('as_target_role_status_index');
            });
        }

        if (Schema::hasTable('attendance_schema_assignments')) {
            Schema::table('attendance_schema_assignments', function (Blueprint $table) {
                $table->dropIndex('asa_user_active_dates_index');
                $table->dropIndex('asa_assignment_type_index');
                $table->dropIndex('asa_schema_active_index');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_status_kepegawaian_index');
                $table->dropIndex('users_status_created_index');
                if (Schema::hasColumn('users', 'role')) {
                    $table->dropIndex('users_role_index');
                }
            });
        }
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $schemaBuilder = $connection->getSchemaBuilder();

            // Get all indexes for the table
            $indexes = $connection->select("SHOW INDEX FROM `{$table}`");

            foreach ($indexes as $indexInfo) {
                if ($indexInfo->Key_name === $index) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            // If we can't check, assume it doesn't exist
            return false;
        }
    }
};
