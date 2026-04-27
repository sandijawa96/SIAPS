<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use App\Models\User;
use App\Models\AttendanceSchema;
use App\Models\UserFaceTemplate;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Password;
use Illuminate\Http\JsonResponse;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Services\AttendanceTimeService;
use App\Services\AttendanceSchemaService;
use App\Support\RoleNames;
use App\Support\RoleAccessMatrix;

class AuthController extends Controller
{
    private const WEB_TOKEN_NAME = 'web_session';
    private const LEGACY_WEB_TOKEN_NAME = 'auth_token';

    /**
     * Create a new AuthController instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['login', 'loginWeb', 'loginWebSiswa', 'loginMobile', 'loginMobileApp', 'loginSiswa', 'forgotPassword', 'resetPassword']]);
    }

    /**
     * Login for students on website (using Sanctum, without device binding).
     */
    public function loginWebSiswa(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nis' => 'required|string',
                'tanggal_lahir' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('username', $request->nis)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'NIS tidak ditemukan'
                ], 401);
            }

            if (!$user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only students can login through this endpoint.'
                ], 403);
            }

            $birthDateValidationError = $this->validateStudentBirthDate($user, (string) $request->tanggal_lahir);
            if ($birthDateValidationError instanceof JsonResponse) {
                return $birthDateValidationError;
            }

            $user->load([
                'roles',
                'roles.permissions',
                'dataPribadiSiswa',
                'dataKepegawaian',
                'kelas.waliKelas:id,nama_lengkap,nip',
            ]);

            $permissions = $user->getAllPermissions()->pluck('name');

            $userData = $user->toArray();
            $userData['permissions'] = $permissions;
            $userData['roles'] = $user->roles;
            $userData['role'] = $this->resolvePrimaryRole($user);
            $userData['foto_profil_url'] = $user->foto_profil_url;
            $userData = $this->normalizeDeviceBindingPayload($userData, $user);
            $userData = $this->appendMobileProfileContext($userData, $user);

            $token = $this->issueWebToken($user);

            Log::info('Student web login successful', [
                'user_id' => $user->id,
                'username' => $user->username,
                'nis' => $request->nis,
                'client_platform' => $request->header('X-Client-Platform'),
                'client_app' => $request->header('X-Client-App'),
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $userData,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'auth_type' => 'sanctum',
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Student web login error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to login'
            ], 500);
        }
    }

    /**
     * Login for students (using JWT)
     */
    public function loginSiswa(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'nis' => 'required|string',
                'tanggal_lahir' => 'required|string',
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

            // Find user by NIS (username)
            $user = User::where('username', $request->nis)->first();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'NIS tidak ditemukan'
                ], 401);
            }

            // Verify if user is a student
            if (!$user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only students can login through this endpoint.'
                ], 403);
            }

            // CRITICAL: Validate device binding BEFORE authentication (for students too!)
            $deviceValidation = $this->validateDeviceBindingForStudent($request, $user);
            if (!$deviceValidation['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $deviceValidation['message'],
                    'data' => $deviceValidation['data'] ?? null
                ], 403);
            }

            $birthDateValidationError = $this->validateStudentBirthDate($user, (string) $request->tanggal_lahir);
            if ($birthDateValidationError instanceof JsonResponse) {
                return $birthDateValidationError;
            }

            // Handle device binding after successful authentication
            $this->handleDeviceBinding($user, $request);
            $this->upsertMobileDeviceTokenFromLogin($user, $request);

            // Create JWT token manually since we're not using email/password
            $token = JWTAuth::fromUser($user);

            $user->load([
                'roles',
                'roles.permissions',
                'dataPribadiSiswa',
                'dataKepegawaian',
                'kelas.waliKelas:id,nama_lengkap,nip',
            ]);

            // Get all permissions from user's roles
            $permissions = $user->getAllPermissions()->pluck('name');

            $userData = $user->toArray();
            $userData['permissions'] = $permissions;
            $userData['roles'] = $user->roles;
            $userData['role'] = $this->resolvePrimaryRole($user);
            $userData['foto_profil_url'] = $user->foto_profil_url;
            $userData = $this->normalizeDeviceBindingPayload($userData, $user);
            $userData = $this->appendMobileProfileContext($userData, $user);

            $payload = JWTAuth::setToken($token)->getPayload();

            // Log successful login with device info
            Log::info('Student login successful with device binding', [
                'user_id' => $user->id,
                'username' => $user->username,
                'nis' => $request->nis,
                'device_id' => $request->device_id,
                'device_name' => $request->device_name,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $userData,
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'auth_type' => 'jwt',
                    'expires_in' => $payload->get('exp') - time()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Student login error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to login'
            ], 500);
        }
    }

    /**
     * Legacy login method that supports both web and mobile authentication
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
                'client_type' => 'string|in:web,mobile'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Determine client type, default to web
            $clientType = $request->input('client_type', 'web');

            // Route to appropriate login method based on client type
            if ($clientType === 'mobile') {
                return $this->loginMobile($request);
            }

            return $this->loginWeb($request);
        } catch (\Exception $e) {
            Log::error('Login error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to login'
            ], 500);
        }
    }

    /**
     * Login for web application (using Sanctum)
     */
    public function loginWeb(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            /** @var \App\Models\User $user */
            $user = User::where('email', $request->email)->firstOrFail();
            $user->load([
                'roles',
                'roles.permissions',
                'dataPribadiSiswa',
                'dataKepegawaian',
                'kelas.waliKelas:id,nama_lengkap,nip',
            ]);

            // Get all permissions from user's roles
            $permissions = $user->getAllPermissions()->pluck('name');

            $userData = $user->toArray();
            $userData['permissions'] = $permissions;
            $userData['roles'] = $user->roles;
            $userData['role'] = $this->resolvePrimaryRole($user);
            $userData['foto_profil_url'] = $user->foto_profil_url;
            $userData = $this->normalizeDeviceBindingPayload($userData, $user);
            $userData = $this->appendMobileProfileContext($userData, $user);

            $token = $this->issueWebToken($user);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $userData,
                    'access_token' => $token,
                    'token_type' => 'Bearer'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Web login error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to login'
            ], 500);
        }
    }

    /**
     * Login for mobile application (using JWT)
     */
    public function loginMobile(Request $request): JsonResponse
    {
        try {
            $isTestingEnvironment = app()->environment('testing');

            $validator = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required|string',
                'device_id' => $isTestingEnvironment ? 'nullable|string|max:255' : 'required|string|max:255',
                'device_name' => $isTestingEnvironment ? 'nullable|string|max:255' : 'required|string|max:255',
                'device_info' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // CRITICAL: Validate device binding BEFORE authentication
            if ($request->filled('device_id') && $request->filled('device_name')) {
                $deviceValidation = $this->validateDeviceBinding($request);
                if (!$deviceValidation['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $deviceValidation['message'],
                        'data' => $deviceValidation['data'] ?? null
                    ], 403);
                }
            }

            if (!$token = JWTAuth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }

            /** @var \App\Models\User $user */
            $user = JWTAuth::user();

            // Handle device binding after successful authentication
            if ($request->filled('device_id') && $request->filled('device_name')) {
                $this->handleDeviceBinding($user, $request);
            }
            $this->upsertMobileDeviceTokenFromLogin($user, $request);

            $user->load([
                'roles',
                'roles.permissions',
                'dataPribadiSiswa',
                'dataKepegawaian',
                'kelas.waliKelas:id,nama_lengkap,nip',
            ]);

            // Get all permissions from user's roles
            $permissions = $user->getAllPermissions()->pluck('name');

            $userData = $user->toArray();
            $userData['permissions'] = $permissions;
            $userData['roles'] = $user->roles;
            $userData['role'] = $this->resolvePrimaryRole($user);
            $userData['foto_profil_url'] = $user->foto_profil_url;
            $userData = $this->normalizeDeviceBindingPayload($userData, $user);
            $userData = $this->appendMobileProfileContext($userData, $user);

            $payload = JWTAuth::setToken($token)->getPayload();

            // Log successful login with device info
            Log::info('Mobile login successful with device binding', [
                'user_id' => $user->id,
                'email' => $user->email,
                'device_id' => $request->device_id ?? null,
                'device_name' => $request->device_name ?? null,
                'ip_address' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $userData,
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => $payload->get('exp') - time()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile login error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to login'
            ], 500);
        }
    }

    /**
     * Compatibility endpoint for old mobile clients.
     */
    public function loginMobileApp(Request $request): JsonResponse
    {
        return $this->loginMobile($request);
    }

    /**
     * Get authenticated user profile
     */
    public function profile(Request $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Load relations safely with error handling
            try {
                $user->load(['roles', 'roles.permissions']);

                // Load kelas for students
                $user->load([
                    'kelas.waliKelas:id,nama_lengkap,nip',
                    'dataPribadiSiswa',
                    'dataKepegawaian',
                ]);
            } catch (\Exception $relationError) {
                Log::warning('Failed to load user relations: ' . $relationError->getMessage());
                // Continue without relations if they fail to load
            }

            // Get all permissions from user's roles safely
            $permissions = [];
            try {
                $permissions = $user->getAllPermissions()->pluck('name')->toArray();
            } catch (\Exception $permissionError) {
                Log::warning('Failed to get user permissions: ' . $permissionError->getMessage());
                $permissions = [];
            }

            // Build user data safely
            $userData = [
                'id' => $user->id,
                'username' => $user->username,
                'email' => $user->email,
                'nama_lengkap' => $user->nama_lengkap,
                'nisn' => $user->nisn,
                'nis' => $user->nis,
                'nip' => $user->nip,
                'nik' => $user->nik,
                'foto_profil' => $user->foto_profil,
                'foto_profil_url' => $user->foto_profil_url,
                'status_kepegawaian' => $user->status_kepegawaian,
                'nuptk' => $user->nuptk,
                'is_active' => $user->is_active,
                'device_id' => $user->device_id,
                'device_name' => $user->device_name,
                'device_bound_at' => $user->device_bound_at,
                'device_locked' => $user->device_locked,
                'device_info' => $user->device_info,
                'last_device_activity' => $user->last_device_activity,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];
            $userData = $this->normalizeDeviceBindingPayload($userData, $user);

            // Add roles and permissions safely
            $userData['roles'] = $user->roles ? $user->roles->toArray() : [];
            $userData['permissions'] = $permissions;
            $userData['role'] = $this->resolvePrimaryRole($user);

            $userData = $this->appendMobileProfileContext($userData, $user);

            return response()->json([
                'success' => true,
                'message' => 'Profile retrieved successfully',
                'data' => $userData
            ]);
        } catch (\Exception $e) {
            Log::error('Profile endpoint error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get profile'
            ], 500);
        }
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:255',
                'nama_lengkap' => 'nullable|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'avatar' => 'nullable|string',
                'foto_profil' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!$request->filled('name') && !$request->filled('nama_lengkap')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => [
                        'nama_lengkap' => ['Nama lengkap wajib diisi']
                    ]
                ], 422);
            }

            $payload = [
                'email' => $request->email,
                'nama_lengkap' => $request->input('nama_lengkap', $request->input('name', $user->nama_lengkap)),
            ];

            if ($request->filled('foto_profil') || $request->filled('avatar')) {
                $payload['foto_profil'] = $request->input('foto_profil', $request->input('avatar'));
            }

            $user->update($payload);

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Update profile error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile'
            ], 500);
        }
    }

    /**
     * Change user password
     */
    public function changePassword(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            $validator = Validator::make($request->all(), [
                'current_password' => 'required|string',
                'new_password' => 'required|string|min:8|confirmed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect'
                ], 400);
            }

            $user->update([
                'password' => Hash::make($request->new_password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password changed successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Change password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to change password'
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            if ($request->user('sanctum')) {
                $accessToken = $request->user()->currentAccessToken();
                if ($accessToken) {
                    $accessToken->delete();
                }
            } else if ($request->user('api')) {
                // Invalidate token for JWT
                JWTAuth::invalidate(JWTAuth::getToken());
            }

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Logout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout'
            ], 500);
        }
    }

    /**
     * Refresh JWT token
     */
    public function refreshToken(): JsonResponse
    {
        try {
            $token = JWTAuth::refresh();

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => [
                    'token' => $token,
                    'token_type' => 'Bearer',
                    'expires_in' => JWTAuth::getPayload()->get('exp') - time()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Refresh token error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to refresh token'
            ], 500);
        }
    }

    /**
     * Send password reset link
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'email' => 'required|email|exists:users,email'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();
            if ($user && $user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reset password siswa hanya dapat dilakukan oleh admin'
                ], 403);
            }

            $status = Password::sendResetLink(
                $request->only('email')
            );

            if ($status === Password::RESET_LINK_SENT) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset link sent successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to send reset link'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Forgot password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process request'
            ], 500);
        }
    }

    /**
     * Reset password
     */
    public function resetPassword(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'token' => 'required|string',
                'email' => 'required|email',
                'password' => 'required|string|min:8|confirmed'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::where('email', $request->email)->first();
            if ($user && $user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reset password siswa hanya dapat dilakukan oleh admin'
                ], 403);
            }

            $status = Password::reset(
                $request->only('email', 'password', 'password_confirmation', 'token'),
                function ($user, $password) {
                    $user->forceFill([
                        'password' => Hash::make($password)
                    ])->save();
                }
            );

            if ($status === Password::PASSWORD_RESET) {
                return response()->json([
                    'success' => true,
                    'message' => 'Password reset successfully'
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset password'
            ], 400);
        } catch (\Exception $e) {
            Log::error('Reset password error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to process request'
            ], 500);
        }
    }

    /**
     * Check if user has specific permission
     */
    public function checkPermission(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'permission' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = $request->user();
            $hasPermission = $user->hasPermissionTo($request->permission);

            return response()->json([
                'success' => true,
                'message' => 'Permission check completed',
                'data' => [
                    'has_permission' => $hasPermission
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Check permission error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to check permission'
            ], 500);
        }
    }

    /**
     * Validate device binding before authentication
     */
    private function validateDeviceBinding(Request $request): array
    {
        try {
            $deviceId = $request->device_id;
            $email = $request->email;
            $user = User::where('email', $email)->first();
            $isStudent = $this->isStudentUser($user);

            if (!$isStudent) {
                return ['success' => true];
            }

            if ($this->isLegacyAndroidBuildDeviceId($deviceId)) {
                Log::warning('Student login blocked because legacy Android build id requires reinstall', [
                    'device_id' => $deviceId,
                    'attempted_email' => $email,
                    'ip_address' => $request->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Aplikasi versi lama tidak didukung. Hapus aplikasi lalu instal ulang versi terbaru.',
                    'data' => [
                        'requires_reinstall' => true,
                    ],
                ];
            }

            // Device yang sudah terikat ke siswa tidak boleh dipakai login akun lain.
            $existingUser = $this->findStudentUserBoundToDevice($deviceId, $user?->id);

            if ($existingUser) {
                Log::warning('Device binding violation attempt', [
                    'device_id' => $deviceId,
                    'attempted_email' => $email,
                    'bound_to_user' => $existingUser->nama_lengkap,
                    'bound_to_email' => $existingUser->email,
                    'ip_address' => $request->ip()
                ]);

                return [
                    'success' => false,
                    'message' => 'Device ini sudah terikat dengan akun lain. Satu device hanya bisa digunakan untuk satu akun.',
                    'data' => [
                        'device_already_bound_to' => $existingUser->nama_lengkap,
                        'device_name' => $request->device_name
                    ]
                ];
            }

            // Hanya siswa yang memang dibatasi ke satu device tetap.
            if (
                $user?->device_locked &&
                $user->device_id &&
                $user->device_id !== $deviceId &&
                !$this->isLegacyAndroidBuildDeviceId($user->device_id)
            ) {
                Log::warning('User trying to login from different device', [
                    'user_email' => $email,
                    'user_name' => $user->nama_lengkap,
                    'bound_device_id' => $user->device_id,
                    'attempted_device_id' => $deviceId,
                    'ip_address' => $request->ip()
                ]);

                return [
                    'success' => false,
                    'message' => 'Akun Anda sudah terikat dengan perangkat lain. Hubungi admin untuk reset device.',
                    'data' => [
                        'current_device' => $user->device_name,
                        'bound_at' => $user->device_bound_at
                    ]
                ];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Device validation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat validasi device'
            ];
        }
    }

    /**
     * Validate device binding for student login (using NIS instead of email)
     */
    private function validateDeviceBindingForStudent(Request $request, User $user): array
    {
        try {
            $deviceId = $request->device_id;
            $nis = $request->nis;

            if ($this->isLegacyAndroidBuildDeviceId($deviceId)) {
                Log::warning('Student login blocked because legacy Android build id requires reinstall', [
                    'device_id' => $deviceId,
                    'attempted_nis' => $nis,
                    'attempted_user' => $user->nama_lengkap,
                    'ip_address' => $request->ip(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Aplikasi versi lama tidak didukung. Hapus aplikasi lalu instal ulang versi terbaru.',
                    'data' => [
                        'requires_reinstall' => true,
                    ],
                ];
            }

            // Hanya binding siswa lain yang dianggap benturan aktif.
            $existingUser = $this->findStudentUserBoundToDevice($deviceId, $user->id);

            if ($existingUser) {
                Log::warning('Student device binding violation attempt', [
                    'device_id' => $deviceId,
                    'attempted_nis' => $nis,
                    'attempted_user' => $user->nama_lengkap,
                    'bound_to_user' => $existingUser->nama_lengkap,
                    'bound_to_email' => $existingUser->email,
                    'ip_address' => $request->ip()
                ]);

                return [
                    'success' => false,
                    'message' => 'Device ini sudah terikat dengan akun lain. Satu device hanya bisa digunakan untuk satu akun.',
                    'data' => [
                        'device_already_bound_to' => $existingUser->nama_lengkap,
                        'device_name' => $request->device_name
                    ]
                ];
            }

            // Check if the student trying to login already has a different device bound
            if (
                $user->device_locked &&
                $user->device_id &&
                $user->device_id !== $deviceId &&
                !$this->isLegacyAndroidBuildDeviceId($user->device_id)
            ) {
                Log::warning('Student trying to login from different device', [
                    'user_nis' => $nis,
                    'user_name' => $user->nama_lengkap,
                    'bound_device_id' => $user->device_id,
                    'attempted_device_id' => $deviceId,
                    'ip_address' => $request->ip()
                ]);

                return [
                    'success' => false,
                    'message' => 'Akun Anda sudah terikat dengan perangkat lain. Hubungi admin untuk reset device.',
                    'data' => [
                        'current_device' => $user->device_name,
                        'bound_at' => $user->device_bound_at
                    ]
                ];
            }

            return ['success' => true];
        } catch (\Exception $e) {
            Log::error('Student device validation error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan saat validasi device'
            ];
        }
    }

    /**
     * Handle device binding after successful authentication
     */
    private function handleDeviceBinding(User $user, Request $request): void
    {
        try {
            $deviceId = trim((string) $request->input('device_id', ''));
            if ($deviceId === '') {
                return;
            }

            if ($this->isLegacyAndroidBuildDeviceId($deviceId)) {
                Log::warning('Mobile device registration skipped for legacy Android build id', [
                    'user_id' => $user->id,
                    'user_email' => $user->email ?? 'N/A',
                    'user_nis' => $user->username ?? 'N/A',
                    'device_id' => $deviceId,
                    'ip_address' => $request->ip(),
                ]);

                return;
            }

            $deviceName = trim((string) $request->input('device_name', ''));
            $deviceInfo = $request->input('device_info');
            $now = Carbon::now();

            if ($this->isStudentUser($user)) {
                $boundDeviceId = (string) ($user->device_id ?? '');
                $boundDeviceIsLegacy = $this->isLegacyAndroidBuildDeviceId($boundDeviceId);

                if ($user->device_locked && $boundDeviceId !== '' && $boundDeviceId !== $deviceId && !$boundDeviceIsLegacy) {
                    Log::warning('Student device binding update skipped because device mismatch bypassed validation', [
                        'user_id' => $user->id,
                        'bound_device_id' => $user->device_id,
                        'attempted_device_id' => $deviceId,
                        'ip_address' => $request->ip(),
                    ]);
                    return;
                }

                if (!$user->device_locked || !$user->device_id || $boundDeviceIsLegacy) {
                    $user->update([
                        'device_id' => $deviceId,
                        'device_name' => $deviceName !== '' ? $deviceName : $user->device_name,
                        'device_bound_at' => $now,
                        'device_locked' => true,
                        'device_info' => $deviceInfo ?: $user->device_info,
                        'last_device_activity' => $now,
                    ]);

                    Log::info($boundDeviceIsLegacy ? 'Student device binding migrated from legacy Android build id' : 'Device bound to student user', [
                        'user_id' => $user->id,
                        'user_email' => $user->email ?? 'N/A',
                        'user_nis' => $user->username ?? 'N/A',
                        'previous_device_id' => $boundDeviceId !== '' ? $boundDeviceId : null,
                        'device_id' => $deviceId,
                        'device_name' => $deviceName,
                        'ip_address' => $request->ip()
                    ]);
                    return;
                }

                $user->update([
                    'device_name' => $deviceName !== '' ? $deviceName : $user->device_name,
                    'device_info' => $deviceInfo ?: $user->device_info,
                    'last_device_activity' => $now,
                ]);

                Log::info('Student user logged in with bound device', [
                    'user_id' => $user->id,
                    'user_email' => $user->email ?? 'N/A',
                    'user_nis' => $user->username ?? 'N/A',
                    'device_id' => $user->device_id,
                    'ip_address' => $request->ip()
                ]);
                return;
            }

            $deviceChanged = (string) ($user->device_id ?? '') !== $deviceId;

            $user->update([
                'device_id' => $deviceId,
                'device_name' => $deviceName !== '' ? $deviceName : $user->device_name,
                'device_bound_at' => $deviceChanged || !$user->device_bound_at ? $now : $user->device_bound_at,
                'device_locked' => false,
                'device_info' => $deviceInfo ?: $user->device_info,
                'last_device_activity' => $now,
            ]);

            Log::info('Registered current mobile device for non-student account', [
                'user_id' => $user->id,
                'user_email' => $user->email ?? 'N/A',
                'device_id' => $deviceId,
                'device_name' => $deviceName,
                'device_changed' => $deviceChanged,
                'ip_address' => $request->ip(),
            ]);
        } catch (\Exception $e) {
            Log::error('Device binding error: ' . $e->getMessage());
            // Don't throw exception here to avoid breaking login flow
        }
    }

    private function resolvePrimaryRole(User $user): ?string
    {
        $resolved = RoleAccessMatrix::resolvePrimaryRoleForUser($user);
        if (is_string($resolved) && trim($resolved) !== '') {
            return $resolved;
        }

        $fallbackRole = $user->roles && $user->roles->first()
            ? RoleNames::normalize($user->roles->first()->name)
            : null;

        return (is_string($fallbackRole) && trim($fallbackRole) !== '')
            ? $fallbackRole
            : null;
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

    private function validateStudentBirthDate(User $user, string $rawBirthDate): ?JsonResponse
    {
        $dataPribadi = $user->dataPribadiSiswa;
        if (!$dataPribadi || !$dataPribadi->tanggal_lahir) {
            return response()->json([
                'success' => false,
                'message' => 'Data tanggal lahir tidak ditemukan'
            ], 401);
        }

        try {
            $inputDate = Carbon::createFromFormat('d/m/Y', trim($rawBirthDate))->format('Y-m-d');
            $dbDate = Carbon::parse($dataPribadi->tanggal_lahir)->format('Y-m-d');

            if ($dbDate !== $inputDate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tanggal lahir tidak sesuai'
                ], 401);
            }
        } catch (\Exception $dateError) {
            return response()->json([
                'success' => false,
                'message' => 'Format tanggal lahir tidak valid'
            ], 400);
        }

        return null;
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

    private function normalizeDeviceBindingPayload(array $userData, User $user): array
    {
        if (!$this->isStudentUser($user)) {
            $userData['device_locked'] = false;
        }

        return $userData;
    }

    private function appendMobileProfileContext(array $userData, User $user): array
    {
        if ($this->isStudentUser($user)) {
            $activeTemplate = UserFaceTemplate::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->latest('enrolled_at')
                ->latest('id')
                ->first();

            $userData['has_active_face_template'] = $activeTemplate !== null;
            $userData['face_template_enrolled_at'] = $activeTemplate?->enrolled_at?->toIso8601String();

            if ($user->kelas && $user->kelas->isNotEmpty()) {
                $activeClass = $user->kelas->first(function ($kelas) {
                    $pivot = $kelas->pivot;
                    if (!$pivot) {
                        return false;
                    }

                    return ((string) ($pivot->status ?? '') === 'aktif')
                        || ((bool) ($pivot->is_active ?? false));
                }) ?? $user->kelas->first();

                if ($activeClass) {
                    $userData['kelas_nama'] = $activeClass->nama_kelas;
                    $userData['kelasNama'] = $activeClass->nama_kelas;
                    $userData['kelas_id'] = $activeClass->id;
                    $userData['wali_kelas_id'] = $activeClass->waliKelas?->id;
                    $userData['wali_kelas_nama'] = $activeClass->waliKelas?->nama_lengkap;
                    $userData['wali_kelas_nip'] = $activeClass->waliKelas?->nip;
                }
            }
        }

        $detail = $this->isStudentUser($user) ? $user->dataPribadiSiswa : $user->dataKepegawaian;
        $methods = $this->normalizeAttendanceMethods($detail?->metode_absensi ?? null);
        $effectiveSchema = $this->resolveEffectiveAttendanceSchema($user);
        $workingHours = $this->resolveAttendanceWorkingHours($user);

        $userData['attendance_methods'] = $methods;
        $userData['attendance_methods_label'] = $this->resolveAttendanceMethodsLabel(
            $methods,
            $effectiveSchema,
            $workingHours,
        );
        $userData['attendance_location_label'] = $this->resolveAttendanceLocationLabel(
            $detail?->last_tracked_location ?? null,
            $detail?->gps_tracking ?? null,
            $effectiveSchema,
        );

        return $userData;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeAttendanceMethods(mixed $rawValue): array
    {
        if (is_string($rawValue)) {
            $decoded = json_decode($rawValue, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $rawValue = $decoded;
            } else {
                $rawValue = [$rawValue];
            }
        }

        if (!is_array($rawValue)) {
            return [];
        }

        return array_values(array_filter(array_map(function ($item) {
            $value = trim((string) $item);
            return $value !== '' ? $value : null;
        }, $rawValue)));
    }

    private function formatAttendanceMethodsLabel(array $methods): string
    {
        if (empty($methods)) {
            return '-';
        }

        $labels = array_map(function (string $method) {
            return match (strtolower(trim($method))) {
                'selfie' => 'Selfie',
                'qr_code' => 'QR Code',
                'manual' => 'Manual',
                'face_recognition' => 'Face Recognition',
                default => ucwords(str_replace('_', ' ', $method)),
            };
        }, $methods);

        return implode(', ', array_values(array_unique($labels)));
    }

    private function resolveAttendanceMethodsLabel(
        array $methods,
        ?AttendanceSchema $effectiveSchema,
        array $workingHours = []
    ): string
    {
        $schemaName = trim((string) ($effectiveSchema?->schema_name ?? ''));
        $schoolDaysCount = $this->countConfiguredSchoolDays($workingHours['hari_kerja'] ?? null);
        $jamMasuk = $this->formatAttendanceStartClock($workingHours['jam_masuk'] ?? null);
        $scheduleSummary = $this->buildAttendanceScheduleSummary($schoolDaysCount, $jamMasuk);

        if ($schemaName !== '' && $scheduleSummary !== '') {
            return $schemaName . ' | ' . $scheduleSummary;
        }

        if ($schemaName !== '') {
            return $schemaName;
        }

        if ($scheduleSummary !== '') {
            return $scheduleSummary;
        }

        $methodsLabel = $this->formatAttendanceMethodsLabel($methods);
        return $methodsLabel !== '-' ? $methodsLabel : 'Belum dikonfigurasi';
    }

    private function resolveAttendanceLocationLabel(
        mixed $location,
        mixed $gpsTracking,
        ?AttendanceSchema $effectiveSchema = null
    ): string
    {
        if (is_string($location) && trim($location) !== '') {
            return trim($location);
        }

        if (is_array($location)) {
            foreach (['label', 'name', 'nama_lokasi', 'location_name', 'address', 'alamat'] as $key) {
                $value = trim((string) ($location[$key] ?? ''));
                if ($value !== '') {
                    return $value;
                }
            }

            $latitude = $location['latitude'] ?? $location['lat'] ?? $location['lintang'] ?? null;
            $longitude = $location['longitude'] ?? $location['lng'] ?? $location['bujur'] ?? null;
            if ($latitude !== null && $longitude !== null) {
                return sprintf('Lat %s, Lng %s', $latitude, $longitude);
            }
        }

        if (filter_var($gpsTracking, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true) {
            return 'GPS perangkat aktif';
        }

        $schemaLocationLabel = $this->formatAttendanceLocationLabelFromSchema($effectiveSchema);
        if ($schemaLocationLabel !== '-') {
            return $schemaLocationLabel;
        }

        return 'Belum dikonfigurasi';
    }

    private function resolveEffectiveAttendanceSchema(User $user): ?AttendanceSchema
    {
        try {
            /** @var AttendanceSchemaService $attendanceSchemaService */
            $attendanceSchemaService = app(AttendanceSchemaService::class);
            return $attendanceSchemaService->getEffectiveSchema($user);
        } catch (\Throwable $exception) {
            Log::warning('Failed to resolve effective attendance schema for mobile profile context', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAttendanceWorkingHours(User $user): array
    {
        try {
            /** @var AttendanceTimeService $attendanceTimeService */
            $attendanceTimeService = app(AttendanceTimeService::class);
            $workingHours = $attendanceTimeService->getWorkingHours($user);

            return is_array($workingHours) ? $workingHours : [];
        } catch (\Throwable $exception) {
            Log::warning('Failed to resolve attendance working hours for mobile profile context', [
                'user_id' => $user->id,
                'error' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function countConfiguredSchoolDays(mixed $rawDays): ?int
    {
        if (!is_array($rawDays)) {
            return null;
        }

        $days = array_values(array_filter(array_map(function ($item) {
            $value = trim((string) $item);
            return $value !== '' ? $value : null;
        }, $rawDays)));

        return !empty($days) ? count($days) : null;
    }

    private function formatAttendanceStartClock(mixed $rawClock): ?string
    {
        $value = trim((string) ($rawClock ?? ''));
        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->format('H:i');
        } catch (\Throwable $exception) {
            if (preg_match('/^\d{2}:\d{2}/', $value) === 1) {
                return substr($value, 0, 5);
            }

            return null;
        }
    }

    private function buildAttendanceScheduleSummary(?int $schoolDaysCount, ?string $jamMasuk): string
    {
        if ($schoolDaysCount === null && $jamMasuk === null) {
            return '';
        }

        $summary = $schoolDaysCount !== null
            ? $schoolDaysCount . ' Hari Masuk'
            : 'Hari Masuk';

        if ($jamMasuk !== null) {
            $summary .= ' (' . $jamMasuk . ')';
        }

        return $summary;
    }

    private function formatAttendanceLocationLabelFromSchema(?AttendanceSchema $effectiveSchema): string
    {
        if (!$effectiveSchema instanceof AttendanceSchema) {
            return '-';
        }

        if (!$effectiveSchema->wajib_gps) {
            return 'Tanpa batas lokasi khusus';
        }

        try {
            $locations = $effectiveSchema->getAllowedLocations();
        } catch (\Throwable $exception) {
            return 'GPS sesuai schema aktif';
        }

        if ($locations->isEmpty()) {
            return 'GPS sesuai schema aktif';
        }

        $names = $locations
            ->pluck('nama_lokasi')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();

        if ($names->isEmpty()) {
            return 'GPS sesuai schema aktif';
        }

        if ($names->count() === 1) {
            return $names->first();
        }

        if ($names->count() <= 3) {
            return $names->implode(', ');
        }

        return $names->slice(0, 2)->implode(', ') . ' +' . ($names->count() - 2) . ' lokasi';
    }

    private function upsertMobileDeviceTokenFromLogin(User $user, Request $request): void
    {
        $deviceId = trim((string) $request->input('device_id', ''));
        if ($deviceId === '') {
            return;
        }

        if ($this->isLegacyAndroidBuildDeviceId($deviceId)) {
            Log::warning('Mobile login device token upsert skipped for legacy Android build id', [
                'user_id' => (int) $user->id,
                'device_id' => $deviceId,
            ]);

            return;
        }

        try {
            $incomingPushToken = trim((string) $request->input('push_token', ''));
            $resolvedDeviceType = $this->resolveMobileDeviceType($request);
            $tokenDeviceId = $this->resolveMobileDeviceTokenId($user, $resolvedDeviceType, $deviceId);
            $token = DeviceToken::withTrashed()
                ->where('device_id', $tokenDeviceId)
                ->first();

            if (!$token instanceof DeviceToken) {
                $token = new DeviceToken([
                    'device_id' => $tokenDeviceId,
                ]);
            }

            $existingPushToken = trim((string) ($token->push_token ?? ''));
            $resolvedPushToken = $incomingPushToken !== '' ? $incomingPushToken : $existingPushToken;
            $resolvedPushToken = trim($resolvedPushToken);

            $token->fill([
                'user_id' => (int) $user->id,
                'device_name' => (string) ($request->input('device_name') ?: 'Mobile App'),
                'device_type' => $resolvedDeviceType,
                'push_token' => $resolvedPushToken !== '' ? $resolvedPushToken : null,
                'device_info' => $request->input('device_info'),
                'is_active' => true,
                'last_used_at' => now(),
            ]);
            $token->deleted_at = null;
            $token->save();

            DeviceToken::query()
                ->where('user_id', (int) $user->id)
                ->where('device_type', $resolvedDeviceType)
                ->where('id', '!=', (int) $token->id)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            Log::info('Mobile login device token upserted', [
                'user_id' => (int) $user->id,
                'device_token_id' => (int) $token->id,
                'device_id' => $tokenDeviceId,
                'binding_device_id' => $deviceId,
                'device_type' => $resolvedDeviceType,
                'has_push_token' => $resolvedPushToken !== '',
                'token_suffix' => $resolvedPushToken !== '' ? substr($resolvedPushToken, -12) : null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mobile login device token upsert failed', [
                'user_id' => (int) $user->id,
                'device_id' => $deviceId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function issueWebToken(User $user): string
    {
        $user->tokens()
            ->whereIn('name', [self::WEB_TOKEN_NAME, self::LEGACY_WEB_TOKEN_NAME])
            ->delete();

        DeviceToken::query()
            ->where('user_id', (int) $user->id)
            ->where('device_type', 'web')
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return $user->createToken(self::WEB_TOKEN_NAME, ['web'])->plainTextToken;
    }

    private function resolveMobileDeviceType(Request $request): string
    {
        $rawType = strtolower(trim((string) $request->input('device_type', '')));
        if (in_array($rawType, ['android', 'ios'], true)) {
            return $rawType;
        }

        $platform = strtolower(trim((string) data_get($request->input('device_info', []), 'platform', '')));
        if (str_contains($platform, 'ios')) {
            return 'ios';
        }

        return 'android';
    }

    private function resolveMobileDeviceTokenId(User $user, string $deviceType, string $deviceId): string
    {
        $normalized = preg_replace('/\s+/', '-', trim($deviceType . '-u' . (int) $user->id . '-' . $deviceId));
        $normalized = is_string($normalized) ? $normalized : ($deviceType . '-u' . (int) $user->id . '-' . $deviceId);

        return strlen($normalized) <= 255
            ? $normalized
            : substr($normalized, 0, 255);
    }
}

