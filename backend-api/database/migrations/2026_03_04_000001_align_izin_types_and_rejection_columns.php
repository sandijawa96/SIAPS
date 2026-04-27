<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, string>
     */
    private array $expandedJenisIzin = [
        'sakit',
        'izin',
        'keperluan_keluarga',
        'dispensasi',
        'tugas_sekolah',
        'dinas_luar',
        'cuti',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('izin')) {
            return;
        }

        Schema::table('izin', function (Blueprint $table) {
            if (!Schema::hasColumn('izin', 'rejected_by')) {
                $table->unsignedBigInteger('rejected_by')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('izin', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            }
        });

        $this->ensureRejectedByForeignKey();
        $this->syncJenisIzinStorage($this->expandedJenisIzin);
    }

    public function down(): void
    {
        if (!Schema::hasTable('izin')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE izin DROP CONSTRAINT IF EXISTS izin_jenis_izin_check');
        }

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE izin DROP FOREIGN KEY izin_rejected_by_foreign');
            } catch (\Throwable $e) {
                // Ignore if key does not exist.
            }
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE izin DROP CONSTRAINT IF EXISTS izin_rejected_by_foreign');
        }

        Schema::table('izin', function (Blueprint $table) {
            if (Schema::hasColumn('izin', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('izin', 'rejected_by')) {
                $table->dropColumn('rejected_by');
            }
        });
    }

    private function ensureRejectedByForeignKey(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE izin DROP CONSTRAINT IF EXISTS izin_rejected_by_foreign');
            DB::statement(
                'ALTER TABLE izin ADD CONSTRAINT izin_rejected_by_foreign FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL'
            );
            return;
        }

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE izin DROP FOREIGN KEY izin_rejected_by_foreign');
            } catch (\Throwable $e) {
                // Ignore if key does not exist.
            }

            DB::statement(
                'ALTER TABLE izin ADD CONSTRAINT izin_rejected_by_foreign FOREIGN KEY (rejected_by) REFERENCES users(id) ON DELETE SET NULL'
            );
        }
    }

    /**
     * @param array<int, string> $allowedJenis
     */
    private function syncJenisIzinStorage(array $allowedJenis): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'pgsql') {
            $this->syncPostgresJenisIzinConstraint($allowedJenis);
            return;
        }

        if ($driver === 'mysql') {
            $enumValues = implode("','", $allowedJenis);
            DB::statement(
                "ALTER TABLE izin MODIFY COLUMN jenis_izin ENUM('{$enumValues}') NOT NULL"
            );
        }
    }

    /**
     * @param array<int, string> $allowedJenis
     */
    private function syncPostgresJenisIzinConstraint(array $allowedJenis): void
    {
        $constraints = DB::select(
            "SELECT c.conname
             FROM pg_constraint c
             JOIN pg_class t ON c.conrelid = t.oid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE t.relname = 'izin'
               AND n.nspname = current_schema()
               AND c.contype = 'c'
               AND pg_get_constraintdef(c.oid) ILIKE '%jenis_izin%'"
        );

        foreach ($constraints as $constraint) {
            $name = str_replace('"', '', (string) $constraint->conname);
            DB::statement("ALTER TABLE izin DROP CONSTRAINT IF EXISTS \"{$name}\"");
        }

        DB::statement('ALTER TABLE izin ALTER COLUMN jenis_izin TYPE VARCHAR(50) USING jenis_izin::varchar');

        $quotedValues = implode("','", $allowedJenis);
        DB::statement(
            "ALTER TABLE izin ADD CONSTRAINT izin_jenis_izin_check CHECK (jenis_izin IN ('{$quotedValues}'))"
        );
    }
};
