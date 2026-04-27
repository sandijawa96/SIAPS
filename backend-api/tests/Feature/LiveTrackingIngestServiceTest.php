<?php

namespace Tests\Feature;

use App\Models\LiveTracking;
use App\Models\User;
use App\Services\LiveTrackingIngestService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class LiveTrackingIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Cache::flush();
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_persist_snapshot_records_first_point_and_next_point_after_twenty_meter_movement(): void
    {
        $user = User::factory()->create();
        $service = app(LiveTrackingIngestService::class);

        Carbon::setTestNow(Carbon::parse('2026-03-28 08:00:00'));
        $service->persistSnapshot($this->makeSnapshot($user, -6.20000000, 106.81666600));

        Carbon::setTestNow(Carbon::parse('2026-03-28 08:01:00'));
        $service->persistSnapshot($this->makeSnapshot($user, -6.20005000, 106.81666600));

        $this->assertDatabaseCount('live_tracking', 1);

        Carbon::setTestNow(Carbon::parse('2026-03-28 08:02:00'));
        $service->persistSnapshot($this->makeSnapshot($user, -6.20022000, 106.81666600));

        $this->assertDatabaseCount('live_tracking', 2);
    }

    public function test_persist_snapshot_records_state_change_even_when_distance_is_small(): void
    {
        $user = User::factory()->create();
        $service = app(LiveTrackingIngestService::class);

        Carbon::setTestNow(Carbon::parse('2026-03-28 08:00:00'));
        $service->persistSnapshot($this->makeSnapshot($user, -6.20000000, 106.81666600, [
            'is_in_school_area' => true,
            'gps_quality_status' => 'good',
        ]));

        Carbon::setTestNow(Carbon::parse('2026-03-28 08:01:00'));
        $service->persistSnapshot($this->makeSnapshot($user, -6.20001000, 106.81666600, [
            'is_in_school_area' => false,
            'gps_quality_status' => 'poor',
        ]));

        $this->assertDatabaseCount('live_tracking', 2);
        $this->assertDatabaseHas('live_tracking', [
            'user_id' => $user->id,
            'is_in_school_area' => 0,
            'gps_quality_status' => 'poor',
        ]);
    }

    public function test_persist_snapshot_records_idle_heartbeat_after_five_minutes(): void
    {
        $user = User::factory()->create();
        $service = app(LiveTrackingIngestService::class);

        Carbon::setTestNow(Carbon::parse('2026-03-28 08:00:00'));
        $service->persistSnapshot($this->makeSnapshot($user, -6.20000000, 106.81666600));

        Carbon::setTestNow(Carbon::parse('2026-03-28 08:04:00'));
        $service->persistSnapshot($this->makeSnapshot($user, -6.20000000, 106.81666600));

        $this->assertDatabaseCount('live_tracking', 1);

        Carbon::setTestNow(Carbon::parse('2026-03-28 08:05:00'));
        $service->persistSnapshot($this->makeSnapshot($user, -6.20000000, 106.81666600));

        $this->assertDatabaseCount('live_tracking', 2);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function makeSnapshot(User $user, float $latitude, float $longitude, array $overrides = []): array
    {
        return array_merge([
            'user_id' => $user->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => 10.0,
            'speed' => 0.0,
            'heading' => null,
            'location_id' => null,
            'location_name' => 'Sekolah',
            'device_source' => 'mobile',
            'gps_quality_status' => 'good',
            'is_in_school_area' => true,
            'device_info' => ['platform' => 'android'],
            'ip_address' => '127.0.0.1',
            'tracked_at' => now()->toISOString(),
        ], $overrides);
    }
}
