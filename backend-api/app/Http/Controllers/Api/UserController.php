<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use App\Models\Kelas;
use App\Models\ActivityLog;
use App\Models\DataPribadiSiswa;
use App\Models\DataKepegawaian;
use App\Models\TahunAjaran;
use App\Support\RoleNames;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserController extends Controller
{
    /**
     * Display a listing of users
     */
    public function index(Request $request)
    {
        try {
            $this->authorize('view_users');

            $query = User::with(['dataPribadiSiswa', 'dataKepegawaian']);

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search functionality
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('nip', 'like', "%{$search}%")
                      ->orWhere('nisn', 'like', "%{$search}%")
                      ->orWhere('nis', 'like', "%{$search}%");
                });
            }

            $users = $query->paginate($request->get('per_page', 15));

            // Add role information to each user
            $users->getCollection()->transform(function ($user) {
                $user->role_names = $user->getRoleNames();
                $user->permissions = $user->getAllPermissions()->pluck('name');
                
                // Add roles array for frontend compatibility
                $roles = $user->roles()->get();
                $user->roles = $roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'display_name' => $role->display_name ?? ucfirst($role->name)
                    ];
                });
                
                return $user;
            });

            return response()->json([
                'success' => true,
                'data' => $users,
                'meta' => [
                    'total' => $users->total(),
                    'per_page' => $users->perPage(),
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading users',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created user
     */
    public function store(Request $request)
    {
        $this->authorize('create_users');

        $validator = Validator::make($request->all(), [
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|string',
            'nama_lengkap' => 'required|string|max:255',
            'nip' => 'nullable|string|unique:users',
            'nisn' => 'nullable|string|unique:users',
            'nis' => 'required|string|unique:users',
            'jenis_kelamin' => 'required|in:L,P',
            'tempat_lahir' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string',
            'status_pernikahan' => 'nullable|in:belum_menikah,menikah,cerai_hidup,cerai_mati',
            'alamat' => 'nullable|string',
            'no_telepon' => 'nullable|string|max:15',
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'kelas_id' => 'nullable|exists:kelas,id',
            'data_pribadi' => 'nullable|array',
            'data_kepegawaian' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $resolvedRole = $this->resolveRoleName($request->input('role'));
        if (!$resolvedRole) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => [
                    'role' => ['Role tidak valid atau tidak ditemukan']
                ]
            ], 422);
        }

        // Handle photo upload
        $photoPath = null;
        if ($request->hasFile('foto_profil')) {
            $photoPath = $request->file('foto_profil')->store('users/photos', 'public');
        }

        // Create user
        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nama_lengkap' => $request->nama_lengkap,
            'nip' => $request->nip,
            'nisn' => $request->nisn,
            'nis' => $request->nis,
            'jenis_kelamin' => $request->jenis_kelamin,
            'tempat_lahir' => $request->tempat_lahir,
            'tanggal_lahir' => $request->tanggal_lahir,
            'agama' => $request->agama,
            'status_pernikahan' => $request->status_pernikahan,
            'alamat' => $request->alamat,
            'no_telepon' => $request->no_telepon,
            'foto_profil' => $photoPath,
            'is_active' => true
        ]);

        // Assign to class if provided
        if ($request->kelas_id) {
            $tahunAjaran = $this->getActiveTahunAjaran();
            if ($tahunAjaran) {
                $user->kelas()->attach($request->kelas_id, [
                    'tahun_ajaran_id' => $tahunAjaran->id,
                    'is_active' => true
                ]);
            }
        }

        // Create additional data based on role
        if ($this->isRoleInAliases($resolvedRole, RoleNames::SISWA) && $request->data_pribadi) {
            $dataPribadi = $request->data_pribadi;
            $dataPribadi['user_id'] = $user->id;
            DataPribadiSiswa::create($dataPribadi);
        }

        if (
            $request->data_kepegawaian &&
            (
                $this->isRoleInAliases($resolvedRole, RoleNames::GURU) ||
                $this->isRoleInAliases($resolvedRole, RoleNames::ADMIN) ||
                $this->isRoleInAliases($resolvedRole, RoleNames::PEGAWAI)
            )
        ) {
            $dataKepegawaian = $request->data_kepegawaian;
            $dataKepegawaian['user_id'] = $user->id;
            DataKepegawaian::create($dataKepegawaian);
        }

        // Assign default role using Spatie Permission
        $user->syncRoles([$resolvedRole]);

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'create_user',
            'description' => "Created user: {$user->nama_lengkap}",
            'ip_address' => $request->ip(),
            'additional_data' => [
                'created_user_id' => $user->id,
                'role' => $resolvedRole
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $user->load(['roles', 'dataPribadiSiswa', 'dataKepegawaian'])
        ], 201);
    }

    /**
     * Display the specified user
     */
    public function show(Request $request, $id)
    {
        $this->authorize('view_users');

        $user = User::with(['roles', 'dataPribadiSiswa', 'dataKepegawaian', 'absensi' => function ($query) {
            $query->latest()->limit(10);
        }])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $user
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(Request $request, $id)
    {
        $this->authorize('update_users');

        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'username' => 'sometimes|string|max:255|unique:users,username,' . $id,
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'role' => 'sometimes|string',
            'nama_lengkap' => 'sometimes|string|max:255',
            'nip' => 'nullable|string|unique:users,nip,' . $id,
            'nisn' => 'nullable|string|unique:users,nisn,' . $id,
            'nis' => 'nullable|string|unique:users,nis,' . $id,
            'jenis_kelamin' => 'sometimes|in:L,P',
            'tempat_lahir' => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'agama' => 'nullable|string',
            'status_pernikahan' => 'nullable|in:belum_menikah,menikah,cerai_hidup,cerai_mati',
            'alamat' => 'nullable|string',
            'no_telepon' => 'nullable|string|max:15',
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'is_active' => 'sometimes|boolean',
            'kelas_id' => 'nullable|exists:kelas,id',
            'data_pribadi' => 'nullable|array',
            'data_kepegawaian' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $resolvedRole = null;
        if ($request->filled('role')) {
            $resolvedRole = $this->resolveRoleName($request->input('role'));
            if (!$resolvedRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => [
                        'role' => ['Role tidak valid atau tidak ditemukan']
                    ]
                ], 422);
            }
        }

        // Handle photo upload
        if ($request->hasFile('foto_profil')) {
            // Delete old photo
            if ($user->foto_profil) {
                Storage::disk('public')->delete($user->foto_profil);
            }
            $user->foto_profil = $request->file('foto_profil')->store('users/photos', 'public');
        }

        // Update user data
        $user->update($request->only([
            'username', 'email', 'nama_lengkap', 'nip', 'nisn',
            'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'agama',
            'status_pernikahan', 'alamat', 'no_telepon', 'is_active'
        ]));

        // Update class assignment
        if ($request->has('kelas_id')) {
            $tahunAjaranId = null;
            if ($request->kelas_id) {
                $tahunAjaran = $this->getActiveTahunAjaran();
                if (!$tahunAjaran) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Tidak ada tahun ajaran aktif untuk assign kelas'
                    ], 422);
                }
                $tahunAjaranId = $tahunAjaran->id;
            }

            $user->kelas()->detach();
            if ($request->kelas_id) {
                $user->kelas()->attach($request->kelas_id, [
                    'tahun_ajaran_id' => $tahunAjaranId,
                    'is_active' => true
                ]);
            }
        }

        // Update additional data based on role
        $roleForDataUpdate = $resolvedRole ?? $user->getRoleNames()->first();

        if ($request->data_pribadi && $roleForDataUpdate && $this->isRoleInAliases($roleForDataUpdate, RoleNames::SISWA)) {
            $user->dataPribadiSiswa()->updateOrCreate(
                ['user_id' => $user->id],
                $request->data_pribadi
            );
        }

        if (
            $request->data_kepegawaian &&
            $roleForDataUpdate &&
            (
                $this->isRoleInAliases($roleForDataUpdate, RoleNames::GURU) ||
                $this->isRoleInAliases($roleForDataUpdate, RoleNames::ADMIN) ||
                $this->isRoleInAliases($roleForDataUpdate, RoleNames::PEGAWAI)
            )
        ) {
            $user->dataKepegawaian()->updateOrCreate(
                ['user_id' => $user->id],
                $request->data_kepegawaian
            );
        }

        // Update role if changed
        if ($resolvedRole && !$user->hasRole(RoleNames::aliasesFor($resolvedRole))) {
            $user->syncRoles([$resolvedRole]);
        }

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'update_user',
            'description' => "Updated user: {$user->nama_lengkap}",
            'ip_address' => $request->ip(),
            'additional_data' => [
                'updated_user_id' => $user->id,
                'changes' => $user->getChanges()
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $user->fresh(['roles', 'kelas', 'dataPribadiSiswa', 'dataKepegawaian'])
        ]);
    }

    /**
     * Remove the specified user
     */
    public function destroy(Request $request, $id)
    {
        $this->authorize('delete_users');

        $user = User::findOrFail($id);

        // Prevent deleting super admin
        if ($user->hasRole(RoleNames::aliases(RoleNames::SUPER_ADMIN))) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete super admin user'
            ], 403);
        }

        // Soft delete
        $user->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'delete_user',
            'description' => "Deleted user: {$user->nama_lengkap}",
            'ip_address' => $request->ip(),
            'additional_data' => [
                'deleted_user_id' => $user->id,
                'roles' => $user->getRoleNames()->values()
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Bulk operations
     */
    public function bulkAction(Request $request)
    {
        $this->authorize('manage_users');

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete,assign_class',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'kelas_id' => 'required_if:action,assign_class|exists:kelas,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $users = User::whereIn('id', $request->user_ids)->get();
        $results = [];
        $tahunAjaranId = null;

        if ($request->action === 'assign_class') {
            $tahunAjaran = $this->getActiveTahunAjaran();
            if (!$tahunAjaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada tahun ajaran aktif untuk assign kelas'
                ], 422);
            }
            $tahunAjaranId = $tahunAjaran->id;
        }

        foreach ($users as $user) {
            switch ($request->action) {
                case 'activate':
                    $user->update(['is_active' => true]);
                    $results[] = "Activated: {$user->nama_lengkap}";
                    break;
                    
                case 'deactivate':
                    $user->update(['is_active' => false]);
                    $results[] = "Deactivated: {$user->nama_lengkap}";
                    break;
                    
                case 'delete':
                    if (!$user->hasRole(RoleNames::aliases(RoleNames::SUPER_ADMIN))) {
                        $user->delete();
                        $results[] = "Deleted: {$user->nama_lengkap}";
                    }
                    break;
                    
                case 'assign_class':
                    $user->kelas()->detach();
                    $user->kelas()->attach($request->kelas_id, [
                        'tahun_ajaran_id' => $tahunAjaranId,
                        'is_active' => true
                    ]);
                    $results[] = "Assigned to class: {$user->nama_lengkap}";
                    break;
            }
        }

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'bulk_user_action',
            'description' => "Bulk action: {$request->action}",
            'ip_address' => $request->ip(),
            'additional_data' => [
                'action' => $request->action,
                'user_ids' => $request->user_ids,
                'results' => $results
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Bulk action completed successfully',
            'data' => $results
        ]);
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, $id)
    {
        $this->authorize('reset_user_passwords');

        $validator = Validator::make($request->all(), [
            'new_password' => 'required|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $user->update([
            'password' => Hash::make($request->new_password)
        ]);

        // Revoke all user tokens
        $user->tokens()->delete();

        // Log activity
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => 'reset_password',
            'description' => "Reset password for user: {$user->nama_lengkap}",
            'ip_address' => $request->ip(),
            'additional_data' => [
                'target_user_id' => $user->id
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }

    /**
     * Get user statistics
     */
    public function statistics(Request $request)
    {
        $this->authorize('view_users');

        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'by_role' => DB::table('roles')
                ->leftJoin('model_has_roles', function ($join) {
                    $join->on('roles.id', '=', 'model_has_roles.role_id')
                        ->where('model_has_roles.model_type', User::class);
                })
                ->select('roles.name', DB::raw('COUNT(model_has_roles.model_id) as count'))
                ->groupBy('roles.name')
                ->pluck('count', 'roles.name'),
            'recent_logins' => User::whereNotNull('last_login_at')
                                 ->where('last_login_at', '>=', now()->subDays(7))
                                 ->count(),
            'new_users_this_month' => User::whereMonth('created_at', now()->month)
                                        ->whereYear('created_at', now()->year)
                                        ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Resolve active tahun ajaran with backward compatibility for legacy is_active.
     */
    private function getActiveTahunAjaran(): ?TahunAjaran
    {
        return TahunAjaran::where('status', TahunAjaran::STATUS_ACTIVE)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first()
            ?? TahunAjaran::where('is_active', true)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
    }

    /**
     * Resolve incoming role identifier (slug/canonical/alias) to existing role name.
     */
    private function resolveRoleName(?string $incomingRole): ?string
    {
        if (!$incomingRole) {
            return null;
        }

        $trimmed = trim($incomingRole);
        if ($trimmed === '') {
            return null;
        }

        $normalizedInput = str_replace('-', '_', strtolower($trimmed));

        $roleMap = [
            'super_admin' => RoleNames::SUPER_ADMIN,
            'admin' => RoleNames::ADMIN,
            'kepala_sekolah' => RoleNames::KEPALA_SEKOLAH,
            'wali_kelas' => RoleNames::WALI_KELAS,
            'guru' => RoleNames::GURU,
            'guru_bk' => RoleNames::GURU_BK,
            'siswa' => RoleNames::SISWA,
            'pegawai' => RoleNames::PEGAWAI,
            'staff' => RoleNames::PEGAWAI,
            'staff_tu' => RoleNames::PEGAWAI,
        ];

        $canonicalRole = $roleMap[$normalizedInput] ?? RoleNames::normalize($trimmed);

        $candidates = [$trimmed];
        if ($canonicalRole) {
            $candidates[] = $canonicalRole;
            $candidates = array_merge($candidates, RoleNames::aliasesFor($canonicalRole));
        }

        $orderedCandidates = [];
        foreach ($candidates as $candidate) {
            if (!in_array($candidate, $orderedCandidates, true)) {
                $orderedCandidates[] = $candidate;
            }
        }

        $existingRoleNames = Role::whereIn('name', $orderedCandidates)
            ->pluck('name')
            ->all();

        foreach ($orderedCandidates as $candidate) {
            if (in_array($candidate, $existingRoleNames, true)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Check whether a concrete role name belongs to canonical role aliases.
     */
    private function isRoleInAliases(string $roleName, string $canonicalRole): bool
    {
        return in_array($roleName, RoleNames::aliases($canonicalRole), true);
    }
}

