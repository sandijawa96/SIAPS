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
        // Enhance attendance_settings table with schema support
        Schema::table('attendance_settings', function (Blueprint $table) {
            // Check if columns don't exist and add them (for existing databases)
            if (!Schema::hasColumn('attendance_settings', 'schema_name')) {
                $table->string('schema_name', 100)->default('Default Schema')->after('id');
            }
            if (!Schema::hasColumn('attendance_settings', 'schema_type')) {
                $table->string('schema_type', 50)->default('global')->after('schema_name')
                    ->comment('siswa, honorer, asn, guru_honorer, staff_asn, global, etc');
            }
            if (!Schema::hasColumn('attendance_settings', 'target_role')) {
                $table->string('target_role', 50)->nullable()->after('schema_type')
                    ->comment('Target role: siswa, guru, staff, admin, etc');
            }
            if (!Schema::hasColumn('attendance_settings', 'target_status')) {
                $table->string('target_status', 50)->nullable()->after('target_role')
                    ->comment('Target status kepegawaian: Honorer, ASN');
            }
            if (!Schema::hasColumn('attendance_settings', 'schema_description')) {
                $table->text('schema_description')->nullable()->after('target_status');
            }
            if (!Schema::hasColumn('attendance_settings', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('schema_description');
            }
            if (!Schema::hasColumn('attendance_settings', 'is_default')) {
                $table->boolean('is_default')->default(false)->after('is_active');
            }
            if (!Schema::hasColumn('attendance_settings', 'is_mandatory')) {
                $table->boolean('is_mandatory')->default(true)->after('is_default')
                    ->comment('Apakah wajib absen dengan skema ini');
            }
            if (!Schema::hasColumn('attendance_settings', 'priority')) {
                $table->integer('priority')->default(0)->after('is_mandatory')
                    ->comment('Priority untuk auto assignment (higher = more priority)');
            }
            if (!Schema::hasColumn('attendance_settings', 'version')) {
                $table->integer('version')->default(1)->after('priority');
            }
        });

        // Add indexes for better performance (only if they don't exist)
        Schema::table('attendance_settings', function (Blueprint $table) {
            try {
                $table->index(['schema_type', 'is_active'], 'as_schema_type_active_idx');
            } catch (\Exception $e) {
                // Index might already exist, continue
            }

            try {
                $table->index(['target_role', 'target_status', 'is_active'], 'as_role_status_active_idx');
            } catch (\Exception $e) {
                // Index might already exist, continue
            }

            try {
                $table->index(['is_default', 'is_active'], 'as_default_active_idx');
            } catch (\Exception $e) {
                // Index might already exist, continue
            }

            try {
                $table->index(['priority', 'is_active'], 'as_priority_active_idx');
            } catch (\Exception $e) {
                // Index might already exist, continue
            }
        });

        // Enhance absensi table with schema tracking and snapshot
        Schema::table('absensi', function (Blueprint $table) {
            // Check if columns don't exist and add them
            if (!Schema::hasColumn('absensi', 'attendance_setting_id')) {
                $table->foreignId('attendance_setting_id')->nullable()
                    ->after('user_id')
                    ->constrained('attendance_settings')
                    ->onDelete('set null');
            }
            if (!Schema::hasColumn('absensi', 'settings_snapshot')) {
                $table->json('settings_snapshot')->nullable()->after('attendance_setting_id');
            }
        });

        // Add index for better performance (only if it doesn't exist)
        Schema::table('absensi', function (Blueprint $table) {
            try {
                $table->index('attendance_setting_id', 'absensi_setting_id_idx');
            } catch (\Exception $e) {
                // Index might already exist, continue
            }
        });

        // Create attendance_schema_assignments table for user-schema mapping
        if (!Schema::hasTable('attendance_schema_assignments')) {
            Schema::create('attendance_schema_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->onDelete('cascade');
                $table->foreignId('attendance_setting_id')->constrained('attendance_settings')->onDelete('cascade');
                $table->date('start_date')->default(now());
                $table->date('end_date')->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('notes')->nullable();
                $table->foreignId('assigned_by')->constrained('users');
                $table->timestamps();

                // Indexes
                $table->index(['user_id', 'is_active', 'start_date', 'end_date'], 'asa_user_active_dates_idx');
                $table->index(['attendance_setting_id', 'is_active'], 'asa_setting_active_idx');

                // Unique constraint to prevent overlapping assignments
                $table->unique(['user_id', 'start_date', 'end_date'], 'asa_user_period_unique');
            });
        }

        // Create attendance_schema_change_logs table for audit trail
        if (!Schema::hasTable('attendance_schema_change_logs')) {
            Schema::create('attendance_schema_change_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('attendance_setting_id')->constrained('attendance_settings')->onDelete('cascade');
                $table->string('action', 50); // 'created', 'updated', 'activated', 'deactivated'
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->foreignId('changed_by')->constrained('users');
                $table->timestamp('changed_at')->useCurrent();
                $table->text('reason')->nullable();

                // Indexes
                $table->index(['attendance_setting_id', 'changed_at'], 'ascl_setting_changed_idx');
                $table->index(['changed_by', 'changed_at'], 'ascl_user_changed_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new tables
        Schema::dropIfExists('attendance_schema_change_logs');
        Schema::dropIfExists('attendance_schema_assignments');

        // Remove columns from absensi table
        Schema::table('absensi', function (Blueprint $table) {
            if (Schema::hasColumn('absensi', 'attendance_setting_id')) {
                $table->dropForeign(['attendance_setting_id']);
                $table->dropColumn('attendance_setting_id');
            }
            if (Schema::hasColumn('absensi', 'settings_snapshot')) {
                $table->dropColumn('settings_snapshot');
            }
        });

        // Remove columns from attendance_settings table
        Schema::table('attendance_settings', function (Blueprint $table) {
            $columnsToRemove = [
                'schema_name',
                'schema_type',
                'target_role',
                'target_status',
                'schema_description',
                'is_active',
                'is_default',
                'is_mandatory',
                'priority',
                'version'
            ];

            foreach ($columnsToRemove as $column) {
                if (Schema::hasColumn('attendance_settings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
