<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ForgotPasswordRoleRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::updateOrCreate(
            ['name' => RoleNames::SISWA, 'guard_name' => 'web'],
            [
                'display_name' => 'Siswa',
                'description' => 'Role for forgot password restriction test',
                'level' => 1,
                'is_active' => true,
            ]
        );
    }

    public function test_forgot_password_is_rejected_for_student_account(): void
    {
        $student = User::factory()->create([
            'username' => '252610001',
            'nis' => '252610001',
            'nisn' => '0100000001',
            'email' => '252610001@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $student->assignRole(RoleNames::SISWA);

        $response = $this->postJson('/api/web/forgot-password', [
            'email' => $student->email,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Reset password siswa hanya dapat dilakukan oleh admin',
            ]);
    }

    public function test_reset_password_is_rejected_for_student_account(): void
    {
        $student = User::factory()->create([
            'username' => '252610002',
            'nis' => '252610002',
            'nisn' => '0100000002',
            'email' => '252610002@sman1sumbercirebon.sch.id',
            'jenis_kelamin' => 'L',
        ]);
        $student->assignRole(RoleNames::SISWA);

        $response = $this->postJson('/api/web/reset-password', [
            'token' => 'dummy-token',
            'email' => $student->email,
            'password' => 'PasswordBaru123!',
            'password_confirmation' => 'PasswordBaru123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Reset password siswa hanya dapat dilakukan oleh admin',
            ]);
    }

    public function test_forgot_password_is_allowed_for_non_student_account(): void
    {
        Notification::fake();

        $pegawai = User::factory()->create([
            'email' => 'pegawai.reset@sman1sumbercirebon.sch.id',
        ]);

        $response = $this->postJson('/api/web/forgot-password', [
            'email' => $pegawai->email,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password reset link sent successfully',
            ]);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => $pegawai->email,
        ]);

        Notification::assertSentTo($pegawai, ResetPassword::class);
    }
}
