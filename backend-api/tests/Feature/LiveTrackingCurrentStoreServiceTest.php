<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\LiveTrackingCurrentStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class LiveTrackingCurrentStoreServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_upsert_snapshot_writes_current_user_hash_and_indices(): void
    {
        $user = User::factory()->create([
            'nama_lengkap' => 'Siswa Current Store',
        ]);

        $hashKey = 'live_tracking:current:user:' . $user->id;
        $pipe = Mockery::mock();
        $pipe->shouldReceive('hset')->with($hashKey, 'snapshot_status', 'online')->once()->andReturnNull();
        $pipe->shouldReceive('hset')->with($hashKey, 'has_tracking_data', '1')->once()->andReturnNull();
        $pipe->shouldReceive('hset')->withAnyArgs()->andReturnNull();
        $pipe->shouldReceive('sadd')->withAnyArgs()->andReturnNull();
        $pipe->shouldReceive('zadd')->withAnyArgs()->andReturnNull();
        $pipe->shouldReceive('expireat')->withAnyArgs()->andReturnNull();

        $connection = Mockery::mock();
        $connection->shouldReceive('hgetall')->once()->with($hashKey)->andReturn([]);
        $connection->shouldReceive('pipeline')->once()->andReturnUsing(function ($callback) use ($pipe) {
            $callback($pipe);
            return [];
        });

        Redis::shouldReceive('connection')->once()->with('cache')->andReturn($connection);

        app(LiveTrackingCurrentStoreService::class)->upsertSnapshot($user, [
            'user_id' => $user->id,
            'latitude' => -6.2,
            'longitude' => 106.816666,
            'tracked_at' => now()->toISOString(),
            'status' => 'online',
            'is_in_school_area' => true,
            'within_gps_area' => true,
            'gps_quality_status' => 'good',
            'device_source' => 'mobile',
            'tracking_session_active' => false,
        ]);
    }

    public function test_upsert_baseline_user_writes_no_data_shape_without_coordinates(): void
    {
        $user = User::factory()->create([
            'nama_lengkap' => 'Siswa Baseline Current Store',
        ]);

        $hashKey = 'live_tracking:current:user:' . $user->id;
        $pipe = Mockery::mock();
        $pipe->shouldReceive('hset')->with($hashKey, 'snapshot_status', 'no_data')->once()->andReturnNull();
        $pipe->shouldReceive('hset')->with($hashKey, 'has_tracking_data', '0')->once()->andReturnNull();
        $pipe->shouldReceive('hset')->with($hashKey, 'latitude', '')->once()->andReturnNull();
        $pipe->shouldReceive('hset')->with($hashKey, 'longitude', '')->once()->andReturnNull();
        $pipe->shouldReceive('hset')->withAnyArgs()->andReturnNull();
        $pipe->shouldReceive('sadd')->withAnyArgs()->andReturnNull();
        $pipe->shouldReceive('zadd')->withAnyArgs()->andReturnNull();
        $pipe->shouldReceive('expireat')->withAnyArgs()->andReturnNull();

        $connection = Mockery::mock();
        $connection->shouldReceive('hgetall')->once()->with($hashKey)->andReturn([]);
        $connection->shouldReceive('pipeline')->once()->andReturnUsing(function ($callback) use ($pipe) {
            $callback($pipe);
            return [];
        });

        Redis::shouldReceive('connection')->once()->with('cache')->andReturn($connection);

        app(LiveTrackingCurrentStoreService::class)->upsertBaselineUser($user);
    }

    public function test_read_records_returns_normalized_current_store_rows(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('smembers')
            ->once()
            ->with('live_tracking:current:users')
            ->andReturn(['42']);
        $connection->shouldReceive('pipeline')
            ->once()
            ->andReturnUsing(function ($callback) {
                $pipe = Mockery::mock();
                $pipe->shouldReceive('hgetall')
                    ->once()
                    ->with('live_tracking:current:user:42')
                    ->andReturnNull();

                $callback($pipe);

                return [[
                    'user_id' => '42',
                    'nama_lengkap' => 'Siswa Redis',
                    'email' => 'redis@example.test',
                    'nis' => '12345',
                    'username' => 'siswa.redis',
                    'kelas' => 'XI IPA 1',
                    'tingkat' => 'XI',
                    'wali_kelas_id' => '99',
                    'wali_kelas' => 'Wali Redis',
                    'latitude' => '-6.2',
                    'longitude' => '106.8',
                    'accuracy' => '5.5',
                    'speed' => '1.2',
                    'heading' => '180',
                    'tracked_at' => now()->toISOString(),
                    'snapshot_status' => 'online',
                    'device_source' => 'mobile',
                    'gps_quality_status' => 'good',
                    'is_in_school_area' => '1',
                    'within_gps_area' => '1',
                    'has_tracking_data' => '1',
                    'location_id' => '7',
                    'location_name' => 'Gerbang Utama',
                    'current_location' => '{"name":"Gerbang Utama","distance_meters":0}',
                    'nearest_location' => '{"name":"Lapangan","distance_meters":35.5}',
                    'distance_to_nearest' => '35.5',
                    'device_info' => '{"platform":"android","session_id":"sess-42"}',
                    'ip_address' => '10.0.0.7',
                    'tracking_session_active' => '1',
                    'tracking_session_expires_at' => now()->addMinutes(10)->toISOString(),
                ]];
            });

        Redis::shouldReceive('connection')->once()->with('cache')->andReturn($connection);

        $records = app(LiveTrackingCurrentStoreService::class)->readRecords();

        $this->assertCount(1, $records);
        $this->assertSame(42, $records[0]['user_id']);
        $this->assertSame('Siswa Redis', $records[0]['nama_lengkap']);
        $this->assertSame('12345', $records[0]['nis']);
        $this->assertSame('XI IPA 1', $records[0]['kelas']);
        $this->assertSame(99, $records[0]['wali_kelas_id']);
        $this->assertTrue($records[0]['has_tracking_data']);
        $this->assertTrue($records[0]['tracking_session_active']);
        $this->assertSame('Gerbang Utama', $records[0]['location_name']);
        $this->assertSame('Gerbang Utama', $records[0]['current_location']['name'] ?? null);
        $this->assertSame('Lapangan', $records[0]['nearest_location']['name'] ?? null);
        $this->assertSame(35.5, $records[0]['distance_to_nearest']);
        $this->assertSame('android', $records[0]['device_info']['platform'] ?? null);
        $this->assertIsString($records[0]['tracked_at']);
        $this->assertIsString($records[0]['tracking_session_expires_at']);
    }
}
