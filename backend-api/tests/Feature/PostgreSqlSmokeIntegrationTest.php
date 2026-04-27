<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PostgreSqlSmokeIntegrationTest extends TestCase
{
    public function test_postgresql_connection_and_core_tables_are_available(): void
    {
        if (config('database.default') !== 'pgsql') {
            $this->markTestSkipped('Smoke test ini khusus dijalankan pada DB PostgreSQL.');
        }

        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $this->assertTrue(Schema::hasTable('users'));
        $this->assertTrue(Schema::hasTable('absensi'));

        $result = DB::select('SELECT 1 AS ping');
        $this->assertSame(1, (int) ($result[0]->ping ?? 0));
    }
}
