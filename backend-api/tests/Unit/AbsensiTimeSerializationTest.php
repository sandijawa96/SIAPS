<?php

namespace Tests\Unit;

use App\Models\Absensi;
use Tests\TestCase;

class AbsensiTimeSerializationTest extends TestCase
{
    public function test_time_fields_serialize_as_time_only_without_timezone_shift(): void
    {
        $absensi = new Absensi([
            'jam_masuk' => '15:00:00',
            'jam_pulang' => '16:30:00',
        ]);

        $payload = $absensi->toArray();

        $this->assertSame('15:00:00', $payload['jam_masuk'] ?? null);
        $this->assertSame('16:30:00', $payload['jam_pulang'] ?? null);
    }
}

