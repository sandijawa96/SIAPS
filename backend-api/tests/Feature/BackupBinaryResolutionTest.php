<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BackupController;
use Tests\TestCase;

class BackupBinaryResolutionTest extends TestCase
{
    public function test_backup_binary_resolution_respects_configured_env_path(): void
    {
        $temporaryBinary = tempnam(sys_get_temp_dir(), 'pgdump-test-');
        file_put_contents($temporaryBinary, '#!/bin/sh' . PHP_EOL . 'exit 0');

        if (DIRECTORY_SEPARATOR !== '\\') {
            @chmod($temporaryBinary, 0755);
        }

        putenv('PG_DUMP_BINARY=' . $temporaryBinary);
        $_ENV['PG_DUMP_BINARY'] = $temporaryBinary;
        $_SERVER['PG_DUMP_BINARY'] = $temporaryBinary;

        $controller = new BackupController();
        $method = new \ReflectionMethod($controller, 'resolveDatabaseBinary');
        $method->setAccessible(true);

        $resolved = $method->invoke($controller, 'pg_dump', ['PG_DUMP_BINARY']);

        $this->assertSame($temporaryBinary, $resolved);

        @unlink($temporaryBinary);
        putenv('PG_DUMP_BINARY');
        unset($_ENV['PG_DUMP_BINARY'], $_SERVER['PG_DUMP_BINARY']);
    }

    public function test_backup_binary_resolution_can_resolve_binary_from_system_path(): void
    {
        $controller = new BackupController();
        $method = new \ReflectionMethod($controller, 'resolveDatabaseBinary');
        $method->setAccessible(true);

        $resolved = $method->invoke($controller, 'php', []);

        $this->assertNotSame('', $resolved);
        $this->assertTrue(file_exists($resolved), 'Resolved binary path should exist on the system.');
    }
}
