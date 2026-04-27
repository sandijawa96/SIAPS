<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\DapodikController;
use ReflectionMethod;
use Tests\TestCase;

class DapodikClassTargetYearHelperTest extends TestCase
{
    private function invokePrivate(DapodikController $controller, string $method, array $arguments = [])
    {
        $reflection = new ReflectionMethod($controller, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($controller, $arguments);
    }

    public function test_parse_dapodik_semester_info_extracts_academic_year_and_semester(): void
    {
        $controller = new DapodikController();

        $parsed = $this->invokePrivate($controller, 'parseDapodikSemesterInfo', ['20252']);

        $this->assertSame('20252', $parsed['semester_id']);
        $this->assertSame('2025/2026', $parsed['tahun_ajaran']);
        $this->assertSame('Genap', $parsed['semester']);
    }

    public function test_find_class_match_does_not_fallback_to_another_year_when_target_year_is_selected(): void
    {
        $controller = new DapodikController();
        $nameKey = $this->invokePrivate($controller, 'normalizeTextKey', ['X.1']);

        $existingClass = (object) [
            'id' => 88,
            'nama_kelas' => 'X.1',
            'tahun_ajaran_id' => 10,
        ];

        $context = [
            'target_tahun_ajaran_model' => (object) ['id' => 20],
            'class_indexes' => [
                'by_name_year' => [
                    $nameKey . '|10' => [$existingClass],
                ],
                'by_name' => [
                    $nameKey => [$existingClass],
                ],
            ],
        ];

        $match = $this->invokePrivate($controller, 'findClassMatch', [[
            'nama_kelas' => 'X.1',
        ], $context]);

        $this->assertNull($match['class']);
        $this->assertNull($match['key']);
        $this->assertFalse($match['ambiguous']);
    }
}
