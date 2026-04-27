<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualAttendancePendingCheckoutEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_pending_checkout_endpoint_defaults_to_h_plus_one_scope(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-13 08:00:00'));

        $admin = $this->createAuthorizedAdmin();
        $siswa = $this->createSiswaUser();

        $yesterday = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDay()->toDateString(),
            'jam_masuk' => now()->subDay()->setTime(7, 0)->format('H:i:s'),
            'status' => 'hadir',
        ]);

        $older = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDays(3)->toDateString(),
            'jam_masuk' => now()->subDays(3)->setTime(7, 10)->format('H:i:s'),
            'status' => 'hadir',
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/manual-attendance/pending-checkout');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($yesterday->id, $ids);
        $this->assertNotContains($older->id, $ids);
    }

    public function test_pending_checkout_endpoint_blocks_include_overdue_without_override_permission(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-14 08:00:00'));

        $admin = $this->createAuthorizedAdmin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/manual-attendance/pending-checkout?include_overdue=1')
            ->assertStatus(403)
            ->assertJsonPath('success', false);
    }

    public function test_resolve_checkout_endpoint_updates_pending_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-15 08:00:00'));

        $admin = $this->createAuthorizedAdmin();
        $siswa = $this->createSiswaUser();

        $attendance = Absensi::create([
            'user_id' => $siswa->id,
            'tanggal' => now()->subDay()->toDateString(),
            'jam_masuk' => now()->subDay()->setTime(7, 5)->format('H:i:s'),
            'status' => 'hadir',
            'is_manual' => false,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/manual-attendance/{$attendance->id}/resolve-checkout", [
                'jam_pulang' => '15:45',
                'reason' => 'Perbaikan lupa tap-out H+1',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $attendance->refresh();
        $this->assertNotNull($attendance->jam_pulang);
        $this->assertTrue((bool) $attendance->is_manual);
    }

    private function createAuthorizedAdmin(): User
    {
        $manualPermission = Permission::firstOrCreate(
            [
                'name' => 'manual_attendance',
                'guard_name' => 'web',
            ],
            [
                'display_name' => 'Manual Attendance',
                'module' => 'attendance',
            ]
        );

        $role = Role::firstOrCreate(
            ['name' => RoleNames::ADMIN, 'guard_name' => 'web'],
            [
                'display_name' => RoleNames::ADMIN,
                'description' => 'Admin role for manual attendance endpoint test',
                'level' => 1,
                'is_active' => true,
            ]
        );
        $role->givePermissionTo($manualPermission);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createSiswaUser(): User
    {
        $role = Role::firstOrCreate(
            ['name' => RoleNames::SISWA, 'guard_name' => 'web'],
            [
                'display_name' => RoleNames::SISWA,
                'description' => 'Siswa role for manual attendance endpoint test',
                'level' => 2,
                'is_active' => true,
            ]
        );

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
