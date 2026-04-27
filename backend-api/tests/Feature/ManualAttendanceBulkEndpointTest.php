<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualAttendanceBulkEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_bulk_preview_endpoint_flags_existing_rows_for_create_mode(): void
    {
        $admin = $this->createAuthorizedAdmin();
        [$siswaA, $siswaB] = $this->createSiswaUsers(2);

        $existing = Absensi::create([
            'user_id' => $siswaB->id,
            'tanggal' => '2026-04-03',
            'status' => 'hadir',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manual-attendance/bulk-preview', [
                'operation' => 'create_missing',
                'attendance_list' => [
                    [
                        'user_id' => $siswaA->id,
                        'tanggal' => '2026-04-03',
                        'status' => 'hadir',
                        'jam_masuk' => '07:05',
                        'reason' => 'Preview gangguan server massal saat jam masuk',
                    ],
                    [
                        'user_id' => $siswaB->id,
                        'tanggal' => '2026-04-03',
                        'status' => 'hadir',
                        'jam_masuk' => '07:05',
                        'reason' => 'Preview gangguan server massal saat jam masuk',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.blocked_count', 1)
            ->assertJsonPath('data.results.1.attendance_id', $existing->id)
            ->assertJsonPath('data.results.1.success', false);
    }

    public function test_bulk_create_endpoint_returns_partial_success_when_some_students_already_have_attendance(): void
    {
        $admin = $this->createAuthorizedAdmin();
        [$siswaA, $siswaB] = $this->createSiswaUsers(2);

        Absensi::create([
            'user_id' => $siswaB->id,
            'tanggal' => '2026-04-01',
            'status' => 'hadir',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manual-attendance/bulk-create', [
                'attendance_list' => [
                    [
                        'user_id' => $siswaA->id,
                        'tanggal' => '2026-04-01',
                        'status' => 'hadir',
                        'jam_masuk' => '07:10',
                        'reason' => 'Gangguan server massal saat jam masuk',
                    ],
                    [
                        'user_id' => $siswaB->id,
                        'tanggal' => '2026-04-01',
                        'status' => 'hadir',
                        'jam_masuk' => '07:10',
                        'reason' => 'Gangguan server massal saat jam masuk',
                    ],
                ],
            ]);

        $response->assertStatus(207)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.success_count', 1)
            ->assertJsonPath('data.failed_count', 1);

        $this->assertDatabaseHas('absensi', [
            'user_id' => $siswaA->id,
            'tanggal' => '2026-04-01',
            'status' => 'hadir',
            'is_manual' => true,
        ]);
    }

    public function test_bulk_correct_endpoint_updates_existing_rows_and_marks_auto_alpha_as_manual(): void
    {
        $admin = $this->createAuthorizedAdmin();
        [$siswaA, $siswaB] = $this->createSiswaUsers(2);

        $autoAlpha = Absensi::create([
            'user_id' => $siswaA->id,
            'tanggal' => '2026-04-02',
            'status' => 'alpha',
            'metode_absensi' => 'manual',
            'is_manual' => false,
        ]);

        $existing = Absensi::create([
            'user_id' => $siswaB->id,
            'tanggal' => '2026-04-02',
            'status' => 'hadir',
            'jam_masuk' => '07:30:00',
            'metode_absensi' => 'manual',
            'is_manual' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manual-attendance/bulk-correct', [
                'attendance_list' => [
                    [
                        'user_id' => $siswaA->id,
                        'tanggal' => '2026-04-02',
                        'status' => 'sakit',
                        'reason' => 'Koreksi massal setelah verifikasi data',
                    ],
                    [
                        'user_id' => $siswaB->id,
                        'tanggal' => '2026-04-02',
                        'status' => 'terlambat',
                        'jam_masuk' => '07:45',
                        'reason' => 'Koreksi massal setelah verifikasi data',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.success_count', 2)
            ->assertJsonPath('data.failed_count', 0);

        $autoAlpha->refresh();
        $existing->refresh();

        $this->assertSame('sakit', $autoAlpha->status);
        $this->assertTrue((bool) $autoAlpha->is_manual);
        $this->assertSame('terlambat', $existing->status);
        $this->assertStringContainsString('07:45', (string) $existing->jam_masuk);
    }

    public function test_bulk_preview_endpoint_flags_missing_rows_for_correction_mode(): void
    {
        $admin = $this->createAuthorizedAdmin();
        [$siswaA, $siswaB] = $this->createSiswaUsers(2);

        $existing = Absensi::create([
            'user_id' => $siswaA->id,
            'tanggal' => '2026-04-04',
            'status' => 'alpha',
            'metode_absensi' => 'manual',
            'is_manual' => false,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/manual-attendance/bulk-preview', [
                'operation' => 'correct_existing',
                'attendance_list' => [
                    [
                        'user_id' => $siswaA->id,
                        'tanggal' => '2026-04-04',
                        'status' => 'sakit',
                        'reason' => 'Preview koreksi massal setelah verifikasi',
                    ],
                    [
                        'user_id' => $siswaB->id,
                        'tanggal' => '2026-04-04',
                        'status' => 'sakit',
                        'reason' => 'Preview koreksi massal setelah verifikasi',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.ready_count', 1)
            ->assertJsonPath('data.blocked_count', 1)
            ->assertJsonPath('data.results.0.attendance_id', $existing->id)
            ->assertJsonPath('data.results.1.success', false);
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

        $adminRole = Role::firstOrCreate(
            ['name' => RoleNames::ADMIN, 'guard_name' => 'web'],
            [
                'display_name' => RoleNames::ADMIN,
                'description' => 'Admin role for manual attendance bulk tests',
                'level' => 1,
                'is_active' => true,
            ]
        );
        $adminRole->givePermissionTo($manualPermission);

        $studentRole = Role::firstOrCreate(
            ['name' => RoleNames::SISWA, 'guard_name' => 'web'],
            [
                'display_name' => RoleNames::SISWA,
                'description' => 'Student role for manual attendance bulk tests',
                'level' => 2,
                'is_active' => true,
            ]
        );

        $user = User::factory()->create();
        $user->assignRole($adminRole);

        // Ensure the student role exists before creating target users.
        $studentRole->save();

        return $user;
    }

    /**
     * @return array<int, User>
     */
    private function createSiswaUsers(int $count): array
    {
        $studentRole = Role::firstOrCreate(
            ['name' => RoleNames::SISWA, 'guard_name' => 'web'],
            [
                'display_name' => RoleNames::SISWA,
                'description' => 'Student role for manual attendance bulk tests',
                'level' => 2,
                'is_active' => true,
            ]
        );

        return User::factory()->count($count)->create()->each(function (User $user) use ($studentRole): void {
            $user->assignRole($studentRole);
        })->all();
    }
}
