<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendance_discipline_alerts')) {
            Schema::table('attendance_discipline_alerts', function (Blueprint $table) {
                if (!Schema::hasColumn('attendance_discipline_alerts', 'period_type')) {
                    $table->string('period_type', 30)->default('semester')->after('audience');
                }

                if (!Schema::hasColumn('attendance_discipline_alerts', 'period_key')) {
                    $table->string('period_key', 80)->default('')->after('period_type');
                }

                if (!Schema::hasColumn('attendance_discipline_alerts', 'period_label')) {
                    $table->string('period_label', 120)->default('')->after('period_key');
                }
            });

            DB::table('attendance_discipline_alerts')
                ->orderBy('id')
                ->get(['id', 'semester', 'tahun_ajaran_ref'])
                ->each(function ($row): void {
                    $semester = trim((string) ($row->semester ?? ''));
                    $tahunAjaranRef = trim((string) ($row->tahun_ajaran_ref ?? ''));
                    $periodLabel = trim(($semester !== '' ? ucfirst($semester) : 'Semester') . ' ' . $tahunAjaranRef);
                    $periodKey = strtolower(trim(($semester !== '' ? $semester : 'semester') . '|' . ($tahunAjaranRef !== '' ? $tahunAjaranRef : 'na')));

                    DB::table('attendance_discipline_alerts')
                        ->where('id', (int) $row->id)
                        ->update([
                            'period_type' => 'semester',
                            'period_key' => $periodKey,
                            'period_label' => $periodLabel,
                        ]);
                });

            $this->replaceAlertUniqueScopeWithPeriodScope();
        }

        if (Schema::hasTable('attendance_discipline_cases')) {
            Schema::table('attendance_discipline_cases', function (Blueprint $table) {
                if (!Schema::hasColumn('attendance_discipline_cases', 'period_type')) {
                    $table->string('period_type', 30)->default('semester')->after('status');
                }

                if (!Schema::hasColumn('attendance_discipline_cases', 'period_key')) {
                    $table->string('period_key', 80)->default('')->after('period_type');
                }

                if (!Schema::hasColumn('attendance_discipline_cases', 'period_label')) {
                    $table->string('period_label', 120)->default('')->after('period_key');
                }
            });

            DB::table('attendance_discipline_cases')
                ->orderBy('id')
                ->get(['id', 'semester', 'tahun_ajaran_ref'])
                ->each(function ($row): void {
                    $semester = trim((string) ($row->semester ?? ''));
                    $tahunAjaranRef = trim((string) ($row->tahun_ajaran_ref ?? ''));
                    $periodLabel = trim(($semester !== '' ? ucfirst($semester) : 'Semester') . ' ' . $tahunAjaranRef);
                    $periodKey = strtolower(trim(($semester !== '' ? $semester : 'semester') . '|' . ($tahunAjaranRef !== '' ? $tahunAjaranRef : 'na')));

                    DB::table('attendance_discipline_cases')
                        ->where('id', (int) $row->id)
                        ->update([
                            'period_type' => 'semester',
                            'period_key' => $periodKey,
                            'period_label' => $periodLabel,
                        ]);
                });

            $this->replaceCaseUniqueScopeWithPeriodScope();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendance_discipline_alerts')) {
            $this->restoreAlertLegacyUniqueScope();

            Schema::table('attendance_discipline_alerts', function (Blueprint $table) {
                foreach (['period_label', 'period_key', 'period_type'] as $column) {
                    if (Schema::hasColumn('attendance_discipline_alerts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('attendance_discipline_cases')) {
            $this->restoreCaseLegacyUniqueScope();

            Schema::table('attendance_discipline_cases', function (Blueprint $table) {
                foreach (['period_label', 'period_key', 'period_type'] as $column) {
                    if (Schema::hasColumn('attendance_discipline_cases', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function replaceAlertUniqueScopeWithPeriodScope(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS attendance_discipline_alerts_unique_scope');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS attendance_discipline_alerts_unique_scope_period ON attendance_discipline_alerts (user_id, recipient_user_id, rule_key, audience, period_type, period_key)');
            return;
        }

        Schema::table('attendance_discipline_alerts', function (Blueprint $table) {
            $table->dropUnique('attendance_discipline_alerts_unique_scope');
        });
        Schema::table('attendance_discipline_alerts', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'recipient_user_id', 'rule_key', 'audience', 'period_type', 'period_key'],
                'attendance_discipline_alerts_unique_scope_period'
            );
        });
    }

    private function replaceCaseUniqueScopeWithPeriodScope(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS attendance_discipline_cases_unique_scope');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS attendance_discipline_cases_unique_scope_period ON attendance_discipline_cases (user_id, rule_key, period_type, period_key)');
            return;
        }

        Schema::table('attendance_discipline_cases', function (Blueprint $table) {
            $table->dropUnique('attendance_discipline_cases_unique_scope');
        });
        Schema::table('attendance_discipline_cases', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'rule_key', 'period_type', 'period_key'],
                'attendance_discipline_cases_unique_scope_period'
            );
        });
    }

    private function restoreAlertLegacyUniqueScope(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS attendance_discipline_alerts_unique_scope_period');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS attendance_discipline_alerts_unique_scope ON attendance_discipline_alerts (user_id, recipient_user_id, rule_key, audience, semester, tahun_ajaran_ref)');
            return;
        }

        Schema::table('attendance_discipline_alerts', function (Blueprint $table) {
            $table->dropUnique('attendance_discipline_alerts_unique_scope_period');
        });
        Schema::table('attendance_discipline_alerts', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'recipient_user_id', 'rule_key', 'audience', 'semester', 'tahun_ajaran_ref'],
                'attendance_discipline_alerts_unique_scope'
            );
        });
    }

    private function restoreCaseLegacyUniqueScope(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS attendance_discipline_cases_unique_scope_period');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS attendance_discipline_cases_unique_scope ON attendance_discipline_cases (user_id, rule_key, semester, tahun_ajaran_ref)');
            return;
        }

        Schema::table('attendance_discipline_cases', function (Blueprint $table) {
            $table->dropUnique('attendance_discipline_cases_unique_scope_period');
        });
        Schema::table('attendance_discipline_cases', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'rule_key', 'semester', 'tahun_ajaran_ref'],
                'attendance_discipline_cases_unique_scope'
            );
        });
    }
};
