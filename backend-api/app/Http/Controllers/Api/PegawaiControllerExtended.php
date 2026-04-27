<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\PegawaiExport;
use App\Exports\PegawaiLengkapExport;
use App\Imports\PegawaiImport;


class PegawaiControllerExtended extends Controller
{
    public function index(Request $request)
    {
        // Get available roles from database dynamically (exclude Super_Admin and Siswa)
        $availableRoles = Role::where('name', '!=', 'Super_Admin')
            ->where('name', '!=', 'Siswa')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        // If no roles found, return empty result
        if (empty($availableRoles)) {
            return response()->json([
                'success' => true,
                'data' => [
                    'data' => [],
                    'total' => 0,
                    'per_page' => $request->get('per_page', 15),
                    'current_page' => 1,
                    'last_page' => 1
                ]
            ]);
        }

        $query = User::role($availableRoles, 'web')->with(['roles', 'dataKepegawaian']);

        $roleFilters = $this->normalizeStringFilter($request->input('role'));
        if (!empty($roleFilters)) {
            $query->whereHas('roles', function ($roleQuery) use ($roleFilters) {
                $roleQuery->whereIn('name', $roleFilters);
            });
        }

        $subRoleFilters = $this->normalizeStringFilter($request->input('sub_role'));
        if (!empty($subRoleFilters)) {
            $query->whereHas('roles', function ($roleQuery) use ($subRoleFilters) {
                $roleQuery->whereIn('name', $subRoleFilters);
            });
        }

        $statusFilters = $this->normalizeStatusKepegawaianFilter($request->input('status_kepegawaian'));
        if (!empty($statusFilters)) {
            $query->whereIn('status_kepegawaian', $statusFilters);
        }

        $isActive = $this->normalizeBooleanFilter($request->input('is_active'));
        if ($isActive !== null) {
            $query->where('is_active', $isActive);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Handle sorting
        if ($request->filled('sort_by') && $request->filled('sort_direction')) {
            $sortBy = $request->sort_by;
            $sortDirection = $request->sort_direction;

            // Validate sort direction
            if (in_array($sortDirection, ['asc', 'desc'])) {
                // Validate sort field
                $allowedSortFields = ['nama_lengkap', 'email', 'nip', 'created_at', 'role'];
                if (in_array($sortBy, $allowedSortFields)) {
                    if ($sortBy === 'role') {
                        // Sort by role name through relationship
                        $query->leftJoin('model_has_roles', 'users.id', '=', 'model_has_roles.model_id')
                            ->leftJoin('roles', 'model_has_roles.role_id', '=', 'roles.id')
                            ->orderBy('roles.name', $sortDirection)
                            ->select('users.*');
                    } else {
                        $query->orderBy($sortBy, $sortDirection);
                    }
                }
            }
        } else {
            // Default sorting
            $query->orderBy('nama_lengkap', 'asc');
        }

        $perPage = $request->get('per_page', 15);
        $pegawai = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $pegawai
        ]);
    }

    public function store(Request $request)
    {
        // Debug: Log incoming request data
        Log::info('Pegawai Store Request Data:', $this->sanitizeRequestForLog($request));

        // Get available roles from database dynamically (exclude Super_Admin and Siswa)
        $availableRoles = Role::where('name', '!=', 'Super_Admin')
            ->where('name', '!=', 'Siswa')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        Log::info('Available Roles:', $availableRoles);

        // If no roles available, return error
        if (empty($availableRoles)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada role yang tersedia untuk pegawai'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            // Data Akun (Wajib)
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'roles' => 'required|array|min:1',
            'roles.*' => 'required|string|in:' . implode(',', $availableRoles),
            'is_active' => 'boolean',

            // Data Pribadi
            'nama_lengkap' => 'required|string|max:100',

            // Data Kepegawaian
            'status_kepegawaian' => 'required|in:ASN,Honorer',
            'nip' => 'nullable|string|max:20|unique:users',
            'nuptk' => 'nullable|string|max:20|unique:users',
            'jenis_kelamin' => 'required|in:L,P',

            // File
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Pisahkan data untuk tabel users dan data_kepegawaian
            $userData = $request->only([
                'username',
                'email',
                'password',
                'nama_lengkap',
                'is_active',
                'notifikasi',
                'status_kepegawaian',
                'nip',
                'jenis_kelamin'
            ]);

            $kepegawaianData = $request->only([
                'nomor_sk',
                'tanggal_sk',
                'golongan',
                'tmt',
                'masa_mk_mulai',
                'masa_kontrak_selesai',
                'jabatan',
                'bidang_studi',
                'pendidikan_terakhir',
                'jurusan',
                'institusi',
                'tahun_lulus',
                'no_telepon_kantor',
                'tanggal_lahir',
                'alamat',
            ]);

            // Map no_telepon dari frontend ke no_hp di database
            if ($request->filled('no_telepon')) {
                $kepegawaianData['no_hp'] = $request->no_telepon;
            }

            $userData['password'] = Hash::make($request->password);

            // Set default values
            $userData['is_active'] = $request->get('is_active', true);
            $userData['device_locked'] = false; // Default device not locked

            // Attendance settings akan otomatis mengikuti hierarki:
            // User → Status → Role → Global melalui AttendanceSettingsService
            // Tidak perlu set manual di sini

            // Handle file upload
            if ($request->hasFile('foto_profil')) {
                $file = $request->file('foto_profil');
                $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
                $username = $request->input('username', 'user_' . time());
                // Sanitize username to unix style: lowercase, alphanumeric, underscore
                $username = preg_replace('/[^a-z0-9_]/', '_', strtolower($username));
                $filename = sprintf(
                    'profile_%s_%s_%s.%s',
                    $username,
                    now()->format('YmdHisv'),
                    Str::lower(Str::random(6)),
                    $extension
                );
                $path = $file->storeAs('foto_profil', $filename, 'public');
                $userData['foto_profil'] = $path;
            }

            Log::info('Creating user with data:', $this->sanitizeDataForLog($userData));
            $user = User::create($userData);
            Log::info('User created successfully with ID: ' . $user->id);

            // Create data kepegawaian
            Log::info('Creating kepegawaian data:', $kepegawaianData);
            $user->dataKepegawaian()->create($kepegawaianData);
            Log::info('Kepegawaian data created successfully');

            // Assign roles from the roles array
            if ($request->filled('roles') && is_array($request->roles)) {
                foreach ($request->roles as $roleName) {
                    $user->assignRole($roleName);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Pegawai berhasil ditambahkan',
                'data' => $user->load('roles')
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error creating pegawai:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $this->sanitizeRequestForLog($request)
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan pegawai'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            // Get available roles from database dynamically (exclude Super_Admin and Siswa)
            $availableRoles = Role::where('name', '!=', 'Super_Admin')
                ->where('name', '!=', 'Siswa')
                ->where('guard_name', 'web')
                ->pluck('name')
                ->toArray();

            // If no roles available, try to find user without role filter
            if (empty($availableRoles)) {
                $pegawai = User::with(['roles', 'dataKepegawaian'])->find($id);
            } else {
                $pegawai = User::role($availableRoles, 'web')
                    ->with(['roles', 'dataKepegawaian'])
                    ->find($id);
            }

            if (!$pegawai) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pegawai tidak ditemukan'
                ], 404);
            }

            // Safely get roles
            $roles = $pegawai->roles ? $pegawai->roles->pluck('name')->toArray() : [];

            // Tentukan role utama berdasarkan prioritas
            $mainRole = null;
            if (in_array('Guru', $roles)) {
                $mainRole = 'Guru';
            } elseif (in_array('Staff', $roles)) {
                $mainRole = 'Staff';
            } elseif (in_array('Wali Kelas', $roles)) {
                $mainRole = 'Wali Kelas';
            }

            // Cari sub role yang bukan role utama
            $subRoles = array_diff($roles, [$mainRole]);
            $subRole = !empty($subRoles) ? array_values($subRoles)[0] : null;

            // Convert ke array dengan safe handling
            $pegawaiData = $pegawai->toArray();
            $pegawaiData['role'] = $mainRole;
            $pegawaiData['sub_role'] = $subRole;

            // Merge data kepegawaian jika ada
            if (isset($pegawaiData['data_kepegawaian']) && is_array($pegawaiData['data_kepegawaian'])) {
                $kepegawaianData = $pegawaiData['data_kepegawaian'];
                unset($pegawaiData['data_kepegawaian']);
                $pegawaiData = array_merge($pegawaiData, $kepegawaianData);
            }

            return response()->json([
                'success' => true,
                'data' => $pegawaiData
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PegawaiControllerExtended show method:', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data pegawai'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        // Debug: Log incoming request data
        Log::info('Pegawai Update Request Data:', $this->sanitizeRequestForLog($request));
        Log::info('Pegawai Update ID:', ['id' => $id]);

        // Get available roles from database dynamically (exclude Super_Admin and Siswa)
        $availableRoles = Role::where('name', '!=', 'Super_Admin')
            ->where('name', '!=', 'Siswa')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        // Find pegawai with or without role filter based on available roles
        if (empty($availableRoles)) {
            $pegawai = User::with(['roles', 'dataKepegawaian'])->find($id);
        } else {
            $pegawai = User::role($availableRoles, 'web')->find($id);
        }

        if (!$pegawai) {
            Log::error('Pegawai not found:', ['id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan'
            ], 404);
        }

        // Validasi untuk field yang ada di tabel users dan data_kepegawaian
        $validator = Validator::make($request->all(), [
            // Fields untuk tabel users
            'is_active' => 'nullable|boolean',
            'nama_lengkap' => 'nullable|string|max:100',
            'email' => 'nullable|email|unique:users,email,' . $id,
            'username' => 'nullable|string|unique:users,username,' . $id,
            'nip' => 'nullable|string|max:20|unique:users,nip,' . $id,
            'status_kepegawaian' => 'nullable|in:ASN,Honorer',
            'jenis_kelamin' => 'nullable|in:L,P',

            // Roles validation
            'roles' => 'nullable|array',
            'roles.*' => 'nullable|string|in:' . implode(',', $availableRoles),

            // Fields untuk tabel data_kepegawaian
            'no_hp' => 'nullable|string|max:15',
            'no_telepon_kantor' => 'nullable|string|max:15',
            'nomor_sk' => 'nullable|string',
            'tanggal_sk' => 'nullable|date',
            'golongan' => 'nullable|string',
            'tmt' => 'nullable|string',
            'masa_kontrak_mulai' => 'nullable|date',
            'masa_kontrak_selesai' => 'nullable|date',
            'nuptk' => 'nullable|string|max:16',
            'jabatan' => 'nullable|string',
            'sub_jabatan' => 'nullable|json',
            'pangkat_golongan' => 'nullable|string',
            'pendidikan_terakhir' => 'nullable|string',
            'jurusan' => 'nullable|string',
            'universitas' => 'nullable|string',
            'institusi' => 'nullable|string',
            'tahun_lulus' => 'nullable|string|max:4',
            'no_ijazah' => 'nullable|string',
            'gelar_depan' => 'nullable|string',
            'gelar_belakang' => 'nullable|string',
            'bidang_studi' => 'nullable|string',
            'mata_pelajaran' => 'nullable|json',
            'jam_mengajar_per_minggu' => 'nullable|integer',
            'kelas_yang_diajar' => 'nullable|json',
            'nama_pasangan' => 'nullable|string',
            'pekerjaan_pasangan' => 'nullable|string',
            'jumlah_anak' => 'nullable|integer',
            'data_anak' => 'nullable|json',
            'alamat_domisili' => 'nullable|string',
            'keterangan' => 'nullable|string',
            'sertifikat' => 'nullable|json',
            'pelatihan' => 'nullable|json',
            'tanggal_lahir' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update data di tabel users jika ada
            $userData = $request->only([
                'is_active',
                'nama_lengkap',
                'email',
                'username',
                'nip',
                'status_kepegawaian',
                'jenis_kelamin'
            ]);

            // Filter out null values untuk users table
            $userData = array_filter($userData, function ($value) {
                return $value !== null;
            });

            // Handle file upload update
            if ($request->hasFile('foto_profil')) {
                $file = $request->file('foto_profil');
                $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
                $username = $request->input('username', $pegawai->username ?? 'user_' . time());
                // Sanitize username to unix style: lowercase, alphanumeric, underscore
                $username = preg_replace('/[^a-z0-9_]/', '_', strtolower($username));
                $filename = sprintf(
                    'profile_%s_%s_%s.%s',
                    $username,
                    now()->format('YmdHisv'),
                    Str::lower(Str::random(6)),
                    $extension
                );
                $oldPhotoPath = $pegawai->foto_profil;
                $newPhotoPath = $file->storeAs('foto_profil', $filename, 'public');

                if (
                    !empty($oldPhotoPath) &&
                    $oldPhotoPath !== $newPhotoPath &&
                    Storage::disk('public')->exists($oldPhotoPath)
                ) {
                    Storage::disk('public')->delete($oldPhotoPath);
                }

                $userData['foto_profil'] = $newPhotoPath;
            }

            if (!empty($userData)) {
                $pegawai->update($userData);
                Log::info('Updated users table data:', $this->sanitizeDataForLog($userData));
            }

            // Update data di tabel data_kepegawaian
            $kepegawaianData = $request->only([
                'no_hp',
                'no_telepon_kantor',
                'nomor_sk',
                'tanggal_sk',
                'golongan',
                'tmt',
                'masa_kontrak_mulai',
                'masa_kontrak_selesai',
                'nuptk',
                'jabatan',
                'sub_jabatan',
                'pangkat_golongan',
                'pendidikan_terakhir',
                'jurusan',
                'universitas',
                'institusi',
                'tahun_lulus',
                'no_ijazah',
                'gelar_depan',
                'gelar_belakang',
                'bidang_studi',
                'mata_pelajaran',
                'jam_mengajar_per_minggu',
                'kelas_yang_diajar',
                'nama_pasangan',
                'pekerjaan_pasangan',
                'jumlah_anak',
                'data_anak',
                'alamat_domisili',
                'keterangan',
                'sertifikat',
                'pelatihan',
                'tanggal_lahir'
            ]);

            // Filter out null values untuk kepegawaian table
            $kepegawaianData = array_filter($kepegawaianData, function ($value) {
                return $value !== null;
            });

            if (!empty($kepegawaianData)) {
                // Update atau create data kepegawaian
                if ($pegawai->dataKepegawaian) {
                    $pegawai->dataKepegawaian->update($kepegawaianData);
                } else {
                    $pegawai->dataKepegawaian()->create($kepegawaianData);
                }
                Log::info('Updated kepegawaian table data:', $kepegawaianData);
            }

            // Update roles if provided
            if ($request->filled('roles') && is_array($request->roles)) {
                Log::info('Updating roles for pegawai ID: ' . $id, ['roles' => $request->roles]);

                // Remove all current roles first
                $pegawai->syncRoles([]);

                // Assign new roles
                foreach ($request->roles as $roleName) {
                    $pegawai->assignRole($roleName);
                }

                Log::info('Roles updated successfully for pegawai ID: ' . $id);
            }

            DB::commit();

            Log::info('Update successful for pegawai ID:', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Data pegawai berhasil diupdate',
                'data' => $pegawai->fresh(['roles', 'dataKepegawaian'])
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Update failed:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate pegawai'
            ], 500);
        }
    }

    public function destroy($id)
    {
        // Get available roles from database dynamically (exclude Super_Admin and Siswa)
        $availableRoles = Role::where('name', '!=', 'Super_Admin')
            ->where('name', '!=', 'Siswa')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        // Find pegawai with or without role filter based on available roles
        if (empty($availableRoles)) {
            $pegawai = User::find($id);
        } else {
            $pegawai = User::role($availableRoles, 'web')->find($id);
        }

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan'
            ], 404);
        }

        // Delete foto profil if exists
        if (!empty($pegawai->foto_profil) && Storage::disk('public')->exists($pegawai->foto_profil)) {
            Storage::disk('public')->delete($pegawai->foto_profil);
        }

        $pegawai->delete();

        return response()->json([
            'success' => true,
            'message' => 'Pegawai berhasil dihapus'
        ]);
    }

    public function export()
    {
        try {
            return Excel::download(new PegawaiExport, 'data_pegawai.xlsx');
        } catch (\Exception $e) {
            Log::error('Export error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data'
            ], 500);
        }
    }

    public function exportLengkap(Request $request)
    {
        try {
            $filename = 'data-pegawai-lengkap-' . now()->format('Y-m-d-H-i-s') . '.xlsx';
            $actor = $request->user();
            $actorName = $actor->nama_lengkap ?? $actor->name ?? 'System';
            $actorEmail = $actor->email ?? '-';
            $meta = [
                'school_name' => 'SMAN 1 SUMBER',
                'school_region' => 'Kecamatan Kec. Sumber, Kabupaten Kab. Cirebon, Provinsi Prov. Jawa Barat',
                'downloaded_by' => trim($actorName . ' (' . $actorEmail . ')'),
                'downloaded_at' => now()->format('Y-m-d H:i:s'),
            ];

            return Excel::download(
                new PegawaiLengkapExport($request->all(), $meta),
                $filename
            );
        } catch (\Exception $e) {
            Log::error('Export lengkap error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data pegawai lengkap'
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Generate unique job ID for tracking progress
            $jobId = uniqid('import_pegawai_');

            // Store initial progress
            Cache::put("import_progress_{$jobId}", [
                'progress' => 0,
                'status' => 'starting',
                'message' => 'Memulai import...',
                'total' => 0,
                'processed' => 0
            ], 300); // 5 minutes

            $file = $request->file('file');
            $import = new PegawaiImport($jobId);
            Excel::import($import, $file);

            $imported = $import->getRowCount();
            $errors = $import->getErrors();

            // Update final progress
            Cache::put("import_progress_{$jobId}", [
                'progress' => 100,
                'status' => 'completed',
                'message' => 'Import selesai',
                'total' => $imported + count($errors),
                'processed' => $imported,
                'summary' => [
                    'imported' => $imported,
                    'errors' => $errors
                ]
            ], 300);

            // Determine success based on whether any data was imported
            $success = $imported > 0;

            $message = $success
                ? "Berhasil mengimpor {$imported} data pegawai"
                : 'Tidak ada data yang berhasil diimpor';

            if (!empty($errors)) {
                $message .= '. Terdapat ' . count($errors) . ' error.';
            }

            return response()->json([
                'success' => $success,
                'message' => $message,
                'data' => [
                    'imported' => $imported,
                    'errors' => $errors
                ],
                'job_id' => $jobId
            ]);
        } catch (\Exception $e) {
            Log::error('Import error: ' . $e->getMessage());

            if (isset($jobId)) {
                Cache::put("import_progress_{$jobId}", [
                    'progress' => 100,
                    'status' => 'error',
                    'message' => 'Terjadi kesalahan saat proses import',
                    'total' => 0,
                    'processed' => 0
                ], 300);
            }

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengimpor data',
                'data' => [
                    'imported' => 0,
                    'errors' => []
                ],
                'job_id' => $jobId ?? null
            ], 500);
        }
    }

    public function importProgress($jobId)
    {
        $progress = Cache::get("import_progress_{$jobId}");

        if (!$progress) {
            return response()->json([
                'success' => false,
                'message' => 'Job tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $progress
        ]);
    }

    public function downloadTemplate()
    {
        try {
            return Excel::download(new \App\Exports\PegawaiTemplateExport, 'Template_Import_Pegawai.xlsx');
        } catch (\Exception $e) {
            Log::error('Template download error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendownload template'
            ], 500);
        }
    }

    public function getAvailableSubRoles($roleId)
    {
        try {
            $role = Role::findById($roleId);
            if (!$role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Role tidak ditemukan'
                ], 404);
            }

            $subRoles = Role::where('parent_role_id', $roleId)
                ->get(['id', 'name', 'display_name']);

            return response()->json([
                'success' => true,
                'data' => $subRoles
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting sub roles: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mendapatkan sub role'
            ], 500);
        }
    }

    public function resetPassword(Request $request, $id)
    {
        // Get available roles from database dynamically (exclude Super_Admin and Siswa)
        $availableRoles = Role::where('name', '!=', 'Super_Admin')
            ->where('name', '!=', 'Siswa')
            ->where('guard_name', 'web')
            ->pluck('name')
            ->toArray();

        // Find pegawai with or without role filter based on available roles
        if (empty($availableRoles)) {
            $pegawai = User::find($id);
        } else {
            $pegawai = User::role($availableRoles, 'web')->find($id);
        }

        if (!$pegawai) {
            return response()->json([
                'success' => false,
                'message' => 'Pegawai tidak ditemukan'
            ], 404);
        }

        try {
            $validator = Validator::make($request->all(), [
                'password' => 'required|string|min:6'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Password tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update password
            $pegawai->update([
                'password' => Hash::make($request->password)
            ]);

            Log::info('Password reset successful for pegawai ID: ' . $id);

            return response()->json([
                'success' => true,
                'message' => 'Password pegawai berhasil direset',
                'data' => [
                    'user_id' => $pegawai->id,
                    'nama_lengkap' => $pegawai->nama_lengkap
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat reset password pegawai: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal reset password'
            ], 500);
        }
    }

    /**
     * Normalize request value to an array of strings.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeStringFilter($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $rawValues = is_array($value) ? $value : [(string) $value];

        $normalized = array_map(static function ($item): string {
            return trim((string) $item);
        }, $rawValues);

        return array_values(array_unique(array_filter($normalized, static function ($item): bool {
            return $item !== '';
        })));
    }

    /**
     * Normalize status_kepegawaian values from UI aliases to persisted values.
     *
     * @param mixed $value
     * @return array<int, string>
     */
    private function normalizeStatusKepegawaianFilter($value): array
    {
        $statusValues = $this->normalizeStringFilter($value);
        if (empty($statusValues)) {
            return [];
        }

        $normalized = [];
        foreach ($statusValues as $status) {
            $statusUpper = strtoupper($status);

            if (in_array($statusUpper, ['PNS', 'PPPK', 'ASN'], true)) {
                $normalized[] = 'ASN';
                continue;
            }

            if (strcasecmp($status, 'Honorer') === 0) {
                $normalized[] = 'Honorer';
                continue;
            }

            $normalized[] = $status;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Normalize boolean filter values from request string/int/bool representation.
     *
     * @param mixed $value
     */
    private function normalizeBooleanFilter($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }
            if ($value === 0) {
                return false;
            }
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Remove sensitive request fields before writing to logs.
     */
    private function sanitizeRequestForLog(Request $request): array
    {
        return $request->except([
            'password',
            'password_confirmation',
            'new_password',
            'current_password'
        ]);
    }

    /**
     * Remove sensitive fields from arbitrary data arrays before logging.
     */
    private function sanitizeDataForLog(array $data): array
    {
        unset(
            $data['password'],
            $data['password_confirmation'],
            $data['new_password'],
            $data['current_password']
        );

        return $data;
    }
}
