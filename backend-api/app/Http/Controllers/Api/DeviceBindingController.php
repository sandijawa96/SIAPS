<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Carbon\Carbon;
use App\Support\RoleNames;

class DeviceBindingController extends Controller
{
    /**
     * Bind device to user account
     */
    public function bindDevice(Request $request)
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'device_id' => 'required|string|max:255',
                'device_name' => 'required|string|max:255',
                'device_info' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $isStudent = $this->isStudentUser($user);
            $deviceId = trim((string) $request->device_id);

            if ($this->isLegacyAndroidBuildDeviceId($deviceId)) {
                Log::warning('Device binding blocked for legacy Android build id', [
                    'user_id' => $user->id,
                    'user_name' => $user->nama_lengkap,
                    'device_id' => $deviceId,
                    'ip_address' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Aplikasi versi lama tidak didukung. Hapus aplikasi lalu instal ulang versi terbaru.',
                    'data' => [
                        'requires_reinstall' => true,
                    ],
                ], 403);
            }

            // Device yang sudah terkunci untuk siswa lain tidak boleh didaftarkan ulang.
            $existingUser = $this->findStudentUserBoundToDevice(
                $deviceId,
                $user->id
            );

            if ($existingUser) {
                return response()->json([
                    'success' => false,
                    'message' => 'Device ini sudah terikat dengan akun lain. Satu device hanya bisa digunakan untuk satu akun.',
                    'data' => [
                        'device_already_bound_to' => $existingUser->nama_lengkap,
                        'device_name' => $request->device_name
                    ]
                ], 403);
            }

            $boundDeviceId = (string) ($user->device_id ?? '');
            $boundDeviceIsLegacy = $this->isLegacyAndroidBuildDeviceId($boundDeviceId);

            if ($isStudent && $user->device_locked && $boundDeviceId !== '' && $boundDeviceId !== $deviceId && !$boundDeviceIsLegacy) {
                return response()->json([
                    'success' => false,
                    'message' => 'Akun Anda sudah terikat dengan perangkat lain. Hubungi admin untuk reset device.',
                    'data' => [
                        'current_device' => $user->device_name,
                        'bound_at' => $user->device_bound_at
                    ]
                ], 403);
            }

            $deviceChanged = (string) ($user->device_id ?? '') !== $deviceId;
            $user->update([
                'device_id' => $deviceId,
                'device_name' => $request->device_name,
                'device_bound_at' => $deviceChanged || !$user->device_bound_at || $boundDeviceIsLegacy
                    ? Carbon::now()
                    : $user->device_bound_at,
                'device_locked' => $isStudent,
                'device_info' => $request->device_info,
                'last_device_activity' => Carbon::now(),
            ]);

            // Log the binding action for security audit
            Log::info($isStudent ? 'Student device binding successful' : 'Non-student device registration successful', [
                'user_id' => $user->id,
                'user_name' => $user->nama_lengkap,
                'device_id' => $deviceId,
                'device_name' => $request->device_name,
                'device_locked' => $isStudent,
                'timestamp' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => $isStudent
                    ? 'Device berhasil terikat dengan akun Anda'
                    : 'Perangkat mobile berhasil diregistrasikan untuk akun Anda',
                'data' => [
                    'binding_enabled' => $isStudent,
                    'device_name' => $user->device_name,
                    'bound_at' => $user->device_bound_at,
                    'device_locked' => (bool) $user->device_locked,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengikat device',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check device binding status
     */
    public function checkDeviceBinding(Request $request)
    {
        try {
            $user = $request->user();
            $isStudent = $this->isStudentUser($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'binding_enabled' => $isStudent,
                    'is_bound' => $isStudent
                        ? (bool) $user->device_locked
                        : !empty($user->device_id),
                    'device_id' => $user->device_id,
                    'device_name' => $user->device_name,
                    'bound_at' => $user->device_bound_at,
                    'device_locked' => $isStudent ? (bool) $user->device_locked : false,
                    'can_bind' => $isStudent ? !$user->device_locked : true,
                    'security_mode' => $isStudent ? 'strict_binding' : 'tracking_only',
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengecek device binding',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validate device access (middleware helper)
     */
    public function validateDeviceAccess(Request $request)
    {
        try {
            $user = $request->user();
            $currentDeviceId = trim((string) $request->input('device_id', $request->header('X-Device-ID')));

            if (!$this->isStudentUser($user)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Perangkat mobile tercatat tanpa pembatasan binding untuk akun ini',
                    'requires_binding' => false,
                    'binding_enabled' => false,
                    'security_mode' => 'tracking_only',
                ]);
            }

            if ($this->isLegacyAndroidBuildDeviceId($currentDeviceId)) {
                Log::warning('Device access blocked for legacy Android build id', [
                    'user_id' => $user->id,
                    'device_id' => $currentDeviceId,
                    'ip_address' => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Aplikasi versi lama tidak didukung. Hapus aplikasi lalu instal ulang versi terbaru.',
                    'data' => [
                        'requires_reinstall' => true,
                    ],
                ], 403);
            }

            // If user has no device bound, allow access
            if (!$user->device_locked || !$user->device_id) {
                return response()->json([
                    'success' => true,
                    'message' => 'Device access allowed - no binding',
                    'requires_binding' => true,
                    'binding_enabled' => true
                ]);
            }

            // If device ID matches, allow access
            if ($user->device_id === $currentDeviceId) {
                return response()->json([
                    'success' => true,
                    'message' => 'Device access allowed - device matched',
                    'requires_binding' => false,
                    'binding_enabled' => true
                ]);
            }

            // Device mismatch - deny access
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Akun Anda terikat dengan perangkat lain.',
                'data' => [
                    'bound_device' => $user->device_name,
                    'bound_at' => $user->device_bound_at
                ]
            ], 403);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat validasi device',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Reset device binding (Admin only)
     */
    public function resetDeviceBinding(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|exists:users,id',
                'reason' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($request->user_id);
            $oldDeviceName = $user->device_name;

            // Reset device binding
            $user->update([
                'device_id' => null,
                'device_name' => null,
                'device_bound_at' => null,
                'device_locked' => false,
                'device_info' => null,
                'last_device_activity' => null,
            ]);

            // Log the reset action
            Log::info('Device binding reset', [
                'admin_id' => $request->user()->id,
                'admin_name' => $request->user()->nama_lengkap,
                'user_id' => $user->id,
                'user_name' => $user->nama_lengkap,
                'old_device' => $oldDeviceName,
                'reason' => $request->reason,
                'timestamp' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Device binding berhasil direset',
                'data' => [
                    'user_name' => $user->nama_lengkap,
                    'old_device' => $oldDeviceName,
                    'reset_by' => $request->user()->nama_lengkap,
                    'reset_at' => Carbon::now()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat reset device binding',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all users with device binding info (Admin only)
     * Menampilkan perangkat mobile terakhir yang tercatat untuk setiap akun.
     */
    public function getUsersWithDeviceBinding(Request $request)
    {
        try {
            $users = User::select([
                'id',
                'username',
                'nama_lengkap',
                'email',
                'status_kepegawaian',
                'device_id',
                'device_name',
                'device_info',
                'device_bound_at',
                'device_locked',
                'last_device_activity',
            ])
                ->with('roles:id,name,display_name')
                ->whereNotNull('device_id')
                ->orderBy('device_bound_at', 'desc')
                ->get()
                ->map(function (User $user) {
                    $user->jenis_pengguna = $this->getJenisPengguna($user);
                    $user->app_version = data_get($user->device_info, 'app_version');
                    $user->app_build_number = data_get($user->device_info, 'app_build_number');
                    $user->app_version_label = data_get($user->device_info, 'app_version_label');
                    $user->security_mode = $this->isStudentUser($user) ? 'strict_binding' : 'tracking_only';
                    return $user;
                });

            return response()->json([
                'success' => true,
                'users' => $users,
                'summary' => [
                    'total_registered_devices' => User::whereNotNull('device_id')->count(),
                    'total_bound_devices' => User::whereNotNull('device_id')->count(),
                    'locked_devices' => User::whereNotNull('device_id')
                        ->where('device_locked', true)
                        ->count(),
                    'tracking_only_devices' => User::whereNotNull('device_id')
                        ->where('device_locked', false)
                        ->count(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data device binding',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Helper function untuk menentukan jenis pengguna
     */
    private function getJenisPengguna($user)
    {
        if ($user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            return 'Siswa';
        }

        $primaryRole = collect($user->roles ?? [])
            ->pluck('name')
            ->map(fn ($name) => RoleNames::normalize((string) $name))
            ->first();

        $roleLabel = match ($primaryRole) {
            RoleNames::SUPER_ADMIN => 'Super Admin',
            RoleNames::ADMIN => 'Admin',
            RoleNames::KEPALA_SEKOLAH => 'Kepala Sekolah',
            RoleNames::WAKASEK_KURIKULUM => 'Wakasek Kurikulum',
            RoleNames::WAKASEK_KESISWAAN => 'Wakasek Kesiswaan',
            RoleNames::WAKASEK_HUMAS => 'Wakasek Humas',
            RoleNames::WAKASEK_SARPRAS => 'Wakasek Sarpras',
            RoleNames::WALI_KELAS => 'Wali Kelas',
            RoleNames::GURU => 'Guru',
            RoleNames::GURU_BK => 'Guru BK',
            RoleNames::PEGAWAI => 'Pegawai',
            default => null,
        };

        if (is_string($roleLabel) && $roleLabel !== '') {
            return $roleLabel;
        }

        if ($user->status_kepegawaian === 'Honorer') {
            return 'Honorer';
        }

        return $user->status_kepegawaian ?: 'Pegawai';
    }

    private function isStudentUser(?User $user): bool
    {
        return $user?->hasRole(RoleNames::aliases(RoleNames::SISWA)) ?? false;
    }

    private function isLegacyAndroidBuildDeviceId(?string $deviceId): bool
    {
        $normalized = trim((string) $deviceId);

        return preg_match('/^[A-Z0-9]{4}\.\d{6}\.\d{3}$/', $normalized) === 1;
    }

    private function findStudentUserBoundToDevice(string $deviceId, ?int $ignoreUserId = null): ?User
    {
        $query = User::query()
            ->where('device_id', $deviceId)
            ->where('device_locked', true)
            ->whereHas('roles', function ($roleQuery) {
                $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            });

        if ($ignoreUserId !== null) {
            $query->where('id', '!=', $ignoreUserId);
        }

        return $query->first();
    }
}
