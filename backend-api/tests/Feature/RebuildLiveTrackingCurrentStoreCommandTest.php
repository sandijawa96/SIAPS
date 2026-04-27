<?php

namespace Tests\Feature;

use App\Services\LiveTrackingCurrentStoreService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class RebuildLiveTrackingCurrentStoreCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_command_can_clear_current_store_keys(): void
    {
        $currentStore = Mockery::mock(LiveTrackingCurrentStoreService::class);
        $this->app->instance(LiveTrackingCurrentStoreService::class, $currentStore);

        $currentStore->shouldReceive('clearAll')->once()->andReturn(4);

        $this->artisan('live-tracking:rebuild-current-store --flush-only')
            ->assertExitCode(0);
    }
}
