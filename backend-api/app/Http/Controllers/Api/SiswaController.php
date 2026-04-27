<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use App\Helpers\AuthHelper;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Support\PhoneNumber;
use App\Support\RoleDataScope;

class SiswaController extends Controller
{
    private const STUDENT_EMAIL_DOMAIN = 'sman1sumbercirebon.sch.id';

    public function index(Request $request)
    {
        $query = User::role('Siswa')
            ->with([
                'roles',
                'kelas' => function ($query) {
                    $query->withPivot('tanggal_masuk', 'tahun_ajaran_id', 'status', 'is_active')
                        ->with(['tingkat', 'tahunAjaran']);
                },
                'dataPribadiSiswa',
            ])
            ->withExists([
                'faceTemplates as has_active_face_template' => function ($query) {
                    $query->where('is_active', true);
                },
            ]);
        RoleDataScope::applySiswaReadScope($query, $request->user());
        $kelasScope = $this->normalizeKelasScope($request->input('kelas_scope'));

        // Filter berdasarkan tahun ajaran
        $tahunAjaranId = $request->input('tahun_ajaran_id');
        if ($tahunAjaranId !== null && $tahunAjaranId !== '') {
            if (filter_var($tahunAjaranId, FILTER_VALIDATE_INT) !== false) {
                $tahunAjaranIdInt = (int) $tahunAjaranId;
                if ($kelasScope === 'awal') {
                    $this->applyInitialTahunAjaranFilter($query, $tahunAjaranIdInt);
                } else {
                    $query->whereHas('kelas', function ($q) use ($tahunAjaranIdInt) {
                        $q->where('kelas_siswa.tahun_ajaran_id', $tahunAjaranIdInt)
                            ->where('kelas_siswa.is_active', true);
                    });
                }
            } else {
                Log::warning('Ignoring invalid tahun_ajaran_id filter on siswa index', [
                    'tahun_ajaran_id' => $tahunAjaranId,
                    'user_id' => optional($request->user())->id,
                ]);
            }
        }

        // Filter berdasarkan kelas (ignore invalid non-numeric input to avoid query errors)
        $kelasId = $request->input('kelas_id');
        if ($kelasId !== null && $kelasId !== '') {
            if (filter_var($kelasId, FILTER_VALIDATE_INT) !== false) {
                $kelasIdInt = (int) $kelasId;
                if ($kelasScope === 'awal') {
                    $this->applyInitialKelasFilter($query, $kelasIdInt);
                } else {
                    $query->whereHas('kelas', function ($q) use ($kelasIdInt) {
                        $q->where('kelas.id', $kelasIdInt)
                            ->where('kelas_siswa.is_active', true);
                    });
                }
            } else {
                Log::warning('Ignoring invalid kelas_id filter on siswa index', [
                    'kelas_id' => $kelasId,
                    'user_id' => optional($request->user())->id,
                ]);
            }
        }

        // Filter berdasarkan tingkat (ignore invalid non-numeric input to avoid query errors)
        $tingkatId = $request->input('tingkat_id');
        if ($tingkatId !== null && $tingkatId !== '') {
            if (filter_var($tingkatId, FILTER_VALIDATE_INT) !== false) {
                $tingkatIdInt = (int) $tingkatId;
                if ($kelasScope === 'awal') {
                    $this->applyInitialTingkatFilter($query, $tingkatIdInt);
                } else {
                    $query->whereHas('kelas', function ($q) use ($tingkatIdInt) {
                        $q->where('kelas.tingkat_id', $tingkatIdInt)
                            ->where('kelas_siswa.is_active', true);
                    });
                }
            } else {
                Log::warning('Ignoring invalid tingkat_id filter on siswa index', [
                    'tingkat_id' => $tingkatId,
                    'user_id' => optional($request->user())->id,
                ]);
            }
        }

        // Filter berdasarkan status
        if ($request->has('is_active') && $request->is_active !== '') {
            $isActive = $this->normalizeBooleanFilter($request->input('is_active'));
            if ($isActive === null) {
                Log::warning('Ignoring invalid is_active filter on siswa index', [
                    'is_active' => $request->input('is_active'),
                    'user_id' => optional($request->user())->id,
                ]);
            } else {
                $query->where('is_active', $isActive);
            }
        }

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('nisn', 'like', "%{$search}%")
                    ->orWhere('nis', 'like', "%{$search}%")
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
                $allowedSortFields = ['nama_lengkap', 'email', 'nis', 'nisn', 'created_at', 'kelas', 'role'];
                if (in_array($sortBy, $allowedSortFields)) {
                    if ($sortBy === 'kelas') {
                        // Join with active kelas only
                        $query->select('users.*')
                            ->addSelect(DB::raw('(SELECT kelas.nama_kelas 
                                                  FROM kelas_siswa 
                                                  JOIN kelas ON kelas_siswa.kelas_id = kelas.id 
                                                  WHERE kelas_siswa.user_id = users.id 
                                                  AND kelas_siswa.is_active = 1 
                                                  LIMIT 1) as kelas_nama'))
                            ->orderBy('kelas_nama', $sortDirection);
                    } else if ($sortBy === 'role') {
                        // Sort by first role name via subquery to avoid duplicates from JOIN.
                        $query->select('users.*')
                            ->selectSub(function ($subQuery) {
                                $subQuery->from('model_has_roles')
                                    ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
                                    ->whereColumn('model_has_roles.model_id', 'users.id')
                                    ->where('model_has_roles.model_type', User::class)
                                    ->select('roles.name')
                                    ->limit(1);
                            }, 'role_nama')
                            ->orderBy('role_nama', $sortDirection);
                    } else {
                        $query->orderBy($sortBy, $sortDirection);
                    }
                }
            }
        } else {
            // Default sorting
            $query->orderBy('nama_lengkap', 'asc');
        }

        $perPage = (int) $request->get('per_page', 15);
        $perPage = max(1, min($perPage, 100));
        $siswa = $query->paginate($perPage);
        $siswa->getCollection()->transform(fn (User $item) => $this->decorateSiswaAcademicSnapshot($item));

        return response()->json([
            'success' => true,
            'data' => $siswa
        ]);
    }

    public function store(Request $request)
    {
        Log::info('Menambahkan siswa baru', $this->sanitizeRequestForLog($request));

        // Kelas bersifat opsional. Jika kelas kosong, abaikan field assignment kelas.
        if (!$request->filled('kelas_id')) {
            $request->merge([
                'tahun_ajaran_id' => null,
                'tanggal_masuk' => null,
            ]);
        }

        $validator = Validator::make($request->all(), [
            'username' => 'nullable|string|max:50',
            'email' => 'nullable|string|max:255',
            'password' => 'required|string',
            'nama_lengkap' => 'required|string|max:100',
            'nisn' => 'required|string|max:20|unique:users',
            'nis' => 'required|string|max:20|unique:users',
            'jenis_kelamin' => 'required|in:L,P',
            'tanggal_lahir' => 'required|date',
            'tanggal_masuk' => 'nullable|date',
            'tahun_masuk' => 'nullable|integer|min:1900|max:2100',
            'no_telepon_ortu' => 'required|string|max:15',
            'kelas_id' => 'nullable|exists:kelas,id',
            'tahun_ajaran_id' => 'nullable|exists:tahun_ajaran,id',
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            // Data pribadi siswa tambahan
            'no_telepon' => 'nullable|string|max:15',
            'alamat' => 'nullable|string',
            'tempat_lahir' => 'nullable|string',
            'agama' => 'nullable|string',
            'nama_ayah' => 'nullable|string',
            'nama_ibu' => 'nullable|string',
            'pekerjaan_ayah' => 'nullable|string',
            'pekerjaan_ibu' => 'nullable|string',
            'asal_sekolah' => 'nullable|string'
        ]);

        // Validasi tambahan untuk blok kelas (opsional, tapi jika diisi harus lengkap dan konsisten)
        if (!$validator->fails()) {
            if ($request->filled('kelas_id')) {
                if (!$request->filled('tahun_ajaran_id')) {
                    $validator->after(function ($validator) {
                        $validator->errors()->add('tahun_ajaran_id', 'Tahun ajaran wajib diisi jika ingin menetapkan kelas');
                    });
                }

                if (!$request->filled('tanggal_masuk')) {
                    $validator->after(function ($validator) {
                        $validator->errors()->add('tanggal_masuk', 'Tanggal masuk wajib diisi jika ingin menetapkan kelas');
                    });
                }
            }

            if ($request->filled('kelas_id') && $request->filled('tahun_ajaran_id') && $request->filled('tanggal_masuk')) {
                $kelas = \App\Models\Kelas::find($request->kelas_id);
                if ($kelas && (int) $kelas->tahun_ajaran_id !== (int) $request->tahun_ajaran_id) {
                    $validator->after(function ($validator) {
                        $validator->errors()->add('kelas_id', 'Kelas tidak sesuai dengan tahun ajaran yang dipilih');
                    });
                }

                $tahunAjaran = \App\Models\TahunAjaran::find($request->tahun_ajaran_id);
                if ($tahunAjaran) {
                    $tanggalMasuk = \Carbon\Carbon::parse($request->tanggal_masuk);
                    $tahunMulai = \Carbon\Carbon::parse($tahunAjaran->tanggal_mulai);
                    $tahunSelesai = \Carbon\Carbon::parse($tahunAjaran->tanggal_selesai);

                    if ($tanggalMasuk->lt($tahunMulai) || $tanggalMasuk->gt($tahunSelesai)) {
                        $validator->after(function ($validator) use ($tahunAjaran) {
                            $validator->errors()->add(
                                'tanggal_masuk',
                                "Tanggal masuk harus berada dalam rentang tahun ajaran {$tahunAjaran->nama} ({$tahunAjaran->tanggal_mulai} s/d {$tahunAjaran->tanggal_selesai})"
                            );
                        });
                    }
                }
            }
        }

        if ($validator->fails()) {
            Log::warning('Validasi gagal saat menambahkan siswa', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $normalizedParentPhone = PhoneNumber::normalizeIndonesianWa((string) $request->input('no_telepon_ortu'));
        if ($normalizedParentPhone === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => [
                    'no_telepon_ortu' => ['No. Telepon Orang Tua harus nomor Indonesia valid (contoh: 628xxxxxxxxx).'],
                ],
            ], 422);
        }

        $normalizedStudentPhone = null;
        if ($request->filled('no_telepon')) {
            $normalizedStudentPhone = PhoneNumber::normalizeIndonesianWa((string) $request->input('no_telepon'));
            if ($normalizedStudentPhone === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => [
                        'no_telepon' => ['No. Telepon siswa harus nomor Indonesia valid (contoh: 628xxxxxxxxx).'],
                    ],
                ], 422);
            }
        }

        $studentEmail = $this->buildStudentEmailFromNis($request->nis);
        if ($this->studentEmailTakenByAnother($studentEmail)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => [
                    'email' => ["Email siswa {$studentEmail} sudah digunakan oleh user lain"]
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Buat user terlebih dahulu
            $createdBy = AuthHelper::userId();

            // Jika AuthHelper::userId() mengembalikan null atau user tidak ada, set ke null
            if ($createdBy && !User::find($createdBy)) {
                $createdBy = null;
            }

            $siswa = User::create([
                'username' => $request->nis,
                'email' => $studentEmail,
                'password' => Hash::make($request->password),
                'nama_lengkap' => $request->nama_lengkap,
                'nisn' => $request->nisn,
                'nis' => $request->nis,
                'jenis_kelamin' => $request->jenis_kelamin,
                'is_active' => true,
                'created_by' => $createdBy
            ]);

            if ($request->hasFile('foto_profil')) {
                $file = $request->file('foto_profil');
                $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
                $filename = sprintf(
                    'profile_%d_%s_%s.%s',
                    $siswa->id,
                    now()->format('YmdHisv'),
                    Str::lower(Str::random(6)),
                    $extension
                );
                $path = $file->storeAs('foto_profil', $filename, 'public');
                $siswa->update(['foto_profil' => $path]);
            }

            Log::info('Siswa berhasil dibuat dengan ID: ' . $siswa->id);

            // Buat data pribadi siswa
            $siswa->dataPribadiSiswa()->create([
                'tempat_lahir' => $request->tempat_lahir,
                'tanggal_lahir' => $request->tanggal_lahir,
                'jenis_kelamin' => $request->jenis_kelamin,
                'agama' => $request->agama,
                'alamat' => $request->alamat,
                'no_hp_siswa' => $normalizedStudentPhone,
                'nama_ayah' => $request->nama_ayah,
                'pekerjaan_ayah' => $request->pekerjaan_ayah,
                'no_hp_ayah' => $normalizedParentPhone,
                'nama_ibu' => $request->nama_ibu,
                'pekerjaan_ibu' => $request->pekerjaan_ibu,
                'tahun_masuk' => $request->tahun_masuk,
                'status' => 'aktif'
            ]);

            // Assign role siswa
            $siswa->assignRole('Siswa');
            Log::info('Role siswa berhasil di-assign ke user ID: ' . $siswa->id);

            // Assign ke kelas hanya jika payload kelas diisi lengkap
            if ($request->filled('kelas_id') && $request->filled('tahun_ajaran_id') && $request->filled('tanggal_masuk')) {
                $siswa->kelas()->attach($request->kelas_id, [
                    'tahun_ajaran_id' => $request->tahun_ajaran_id,
                    'tanggal_masuk' => $request->tanggal_masuk,
                    'status' => 'aktif',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $this->freezeInitialAcademicSnapshotForStudent((int) $siswa->id);
                Log::info('Siswa ID ' . $siswa->id . ' berhasil di-assign ke kelas ID ' . $request->kelas_id . ' dengan tahun ajaran ID ' . $request->tahun_ajaran_id);
            } else {
                Log::info('Siswa ID ' . $siswa->id . ' dibuat tanpa kelas awal');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil ditambahkan',
                'data' => $siswa->load(['roles', 'kelas', 'dataPribadiSiswa'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Error saat menambahkan siswa: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan siswa'
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $query = User::role('Siswa')
            ->with(['roles', 'kelas' => function ($query) {
                $query->withPivot('tanggal_masuk', 'tahun_ajaran_id', 'status', 'is_active')
                    ->with(['tingkat', 'tahunAjaran']);
            }, 'dataPribadiSiswa', 'absensi']);
        RoleDataScope::applySiswaReadScope($query, $request->user());
        $siswa = $query->find($id);

        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->decorateSiswaAcademicSnapshot($siswa)
        ]);
    }

    public function update(Request $request, $id)
    {
        // Debug: Log incoming request data
        Log::info('Siswa Update Request Data:', $this->sanitizeRequestForLog($request));
        Log::info('Siswa Update ID:', ['id' => $id]);

        $siswa = User::role('Siswa')->find($id);

        if (!$siswa) {
            Log::error('Siswa not found:', ['id' => $id]);
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan'
            ], 404);
        }

        // Validasi yang lebih fleksibel untuk mendukung update partial
        $validator = Validator::make($request->all(), [
            'nama_lengkap' => 'nullable|string|max:100',
            'nisn' => 'nullable|string|max:20|unique:users,nisn,' . $id,
            'nis' => 'nullable|string|max:20|unique:users,nis,' . $id,
            'is_active' => 'nullable|boolean',
            'jenis_kelamin' => 'nullable|in:L,P',
            'tanggal_lahir' => 'nullable|date',
            'tanggal_masuk' => 'nullable|date',
            'tahun_masuk' => 'nullable|integer|min:1900|max:2100',
            'no_telepon_ortu' => 'nullable|string|max:15',
            'kelas_id' => 'nullable|exists:kelas,id',
            'tahun_ajaran_id' => 'nullable|exists:tahun_ajaran,id',
            'password' => 'nullable|string|min:6',
            'reset_password_to_birthdate' => 'nullable|boolean',
            'foto_profil' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
        ]);

        // Validasi tambahan untuk blok kelas:
        // - Jika payload kelas diisi, wajib lengkap
        // - Jika siswa sudah pernah punya kelas, kelas terkunci dari manajemen pengguna
        if (!$validator->fails()) {
            $hasAnyClassPayload = $request->filled('kelas_id')
                || $request->filled('tahun_ajaran_id')
                || $request->filled('tanggal_masuk');

            if ($hasAnyClassPayload) {
                if (!$request->filled('kelas_id')) {
                    $validator->after(function ($validator) {
                        $validator->errors()->add('kelas_id', 'Kelas wajib diisi jika ingin menetapkan kelas');
                    });
                }

                if (!$request->filled('tahun_ajaran_id')) {
                    $validator->after(function ($validator) {
                        $validator->errors()->add('tahun_ajaran_id', 'Tahun ajaran wajib diisi jika ingin menetapkan kelas');
                    });
                }

                if (!$request->filled('tanggal_masuk')) {
                    $validator->after(function ($validator) {
                        $validator->errors()->add('tanggal_masuk', 'Tanggal masuk wajib diisi jika ingin menetapkan kelas');
                    });
                }

                $alreadyHasClass = DB::table('kelas_siswa')
                    ->where('siswa_id', $siswa->id)
                    ->exists();

                if ($alreadyHasClass) {
                    $validator->after(function ($validator) {
                        $validator->errors()->add('kelas_id', 'Kelas sudah terinput dan dikunci. Perubahan kelas dilakukan melalui manajemen kelas/transisi');
                    });
                }
            }

            if ($request->filled('kelas_id') && $request->filled('tahun_ajaran_id') && $request->filled('tanggal_masuk')) {
                $kelas = \App\Models\Kelas::find($request->kelas_id);
                if ($kelas && (int) $kelas->tahun_ajaran_id !== (int) $request->tahun_ajaran_id) {
                    $validator->after(function ($validator) {
                        $validator->errors()->add('kelas_id', 'Kelas tidak sesuai dengan tahun ajaran yang dipilih');
                    });
                }

                $tahunAjaran = \App\Models\TahunAjaran::find($request->tahun_ajaran_id);
                if ($tahunAjaran) {
                    $tanggalMasuk = \Carbon\Carbon::parse($request->tanggal_masuk);
                    $tahunMulai = \Carbon\Carbon::parse($tahunAjaran->tanggal_mulai);
                    $tahunSelesai = \Carbon\Carbon::parse($tahunAjaran->tanggal_selesai);

                    if ($tanggalMasuk->lt($tahunMulai) || $tanggalMasuk->gt($tahunSelesai)) {
                        $validator->after(function ($validator) use ($tahunAjaran) {
                            $validator->errors()->add(
                                'tanggal_masuk',
                                "Tanggal masuk harus berada dalam rentang tahun ajaran {$tahunAjaran->nama}"
                            );
                        });
                    }
                }
            }
        }

        if ($validator->fails()) {
            Log::error('Validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $normalizedParentPhone = null;
        if ($request->has('no_telepon_ortu')) {
            $rawParentPhone = (string) $request->input('no_telepon_ortu');
            if (trim($rawParentPhone) !== '') {
                $normalizedParentPhone = PhoneNumber::normalizeIndonesianWa($rawParentPhone);
                if ($normalizedParentPhone === '') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data tidak valid',
                        'errors' => [
                            'no_telepon_ortu' => ['No. Telepon Orang Tua harus nomor Indonesia valid (contoh: 628xxxxxxxxx).'],
                        ],
                    ], 422);
                }
            }
        }

        $effectiveNis = trim((string) ($request->filled('nis') ? $request->nis : $siswa->nis));
        if ($effectiveNis === '') {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => [
                    'nis' => ['NIS siswa tidak boleh kosong']
                ]
            ], 422);
        }

        $studentEmail = $this->buildStudentEmailFromNis($effectiveNis);
        if ($this->studentEmailTakenByAnother($studentEmail, (int) $siswa->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => [
                    'email' => ["Email siswa {$studentEmail} sudah digunakan oleh user lain"]
                ]
            ], 422);
        }

        $usernameConflict = User::where('username', $effectiveNis)
            ->where('id', '<>', $siswa->id)
            ->exists();
        if ($usernameConflict) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => [
                    'username' => ["Username {$effectiveNis} sudah digunakan oleh user lain"]
                ]
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Update data di tabel users - hanya field yang dikirim
            $userData = $request->only([
                'nama_lengkap',
                'nisn',
                'nis',
                'jenis_kelamin',
                'is_active'
            ]);

            // Filter out null values
            $userData = array_filter($userData, function ($value) {
                return $value !== null;
            });

            // Enforce canonical siswa identity fields.
            $userData['username'] = $effectiveNis;
            $userData['email'] = $studentEmail;

            if ($request->hasFile('foto_profil')) {
                $file = $request->file('foto_profil');
                $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
                $filename = sprintf(
                    'profile_%d_%s_%s.%s',
                    $siswa->id,
                    now()->format('YmdHisv'),
                    Str::lower(Str::random(6)),
                    $extension
                );
                $oldPhotoPath = $siswa->foto_profil;
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

            // Handle password update
            if ($request->has('reset_password_to_birthdate') && $request->reset_password_to_birthdate) {
                // Reset password to birthdate format (DDMMYYYY)
                $tanggalLahir = null;

                // Cek apakah tanggal lahir ada di request (untuk update baru)
                if ($request->filled('tanggal_lahir')) {
                    $tanggalLahir = $request->tanggal_lahir;
                } else {
                    // Jika tidak ada di request, ambil dari database
                    if ($siswa->dataPribadiSiswa && $siswa->dataPribadiSiswa->tanggal_lahir) {
                        $tanggalLahir = $siswa->dataPribadiSiswa->tanggal_lahir;
                    }
                }

                if ($tanggalLahir) {
                    // Parse tanggal dengan aman (timezone safe)
                    if (is_string($tanggalLahir)) {
                        // Jika format YYYY-MM-DD, parse langsung
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggalLahir)) {
                            $parts = explode('-', $tanggalLahir);
                            $password = $parts[2] . $parts[1] . $parts[0]; // DDMMYYYY
                        } else {
                            // Untuk format lain, gunakan DateTime dengan timezone safe
                            $date = new \DateTime($tanggalLahir . ' 00:00:00');
                            $password = $date->format('dmY');
                        }
                    } else {
                        // Jika sudah object DateTime/Carbon
                        $password = $tanggalLahir->format('dmY');
                    }

                    $userData['password'] = Hash::make($password);
                    Log::info('Password direset ke tanggal lahir untuk siswa ID: ' . $id);
                } else {
                    Log::warning('Tidak dapat mereset password ke tanggal lahir - tanggal lahir tidak ditemukan untuk siswa ID: ' . $id);
                }
            } elseif ($request->filled('password')) {
                // Update with custom password
                $userData['password'] = Hash::make($request->password);
                Log::info('Password diupdate dengan password custom untuk siswa ID: ' . $id);
            }

            if (!empty($userData)) {
                $siswa->update($userData);
                $loggedUserData = $userData;
                unset($loggedUserData['password']);
                Log::info('Updated users table data:', $loggedUserData);
            }

            // Update data pribadi siswa if any related fields are provided
            $dataPribadiFields = $request->only([
                'jenis_kelamin',
                'tanggal_lahir',
                'no_telepon_ortu',
                'tahun_masuk',
            ]);

            $dataPribadiFields = array_filter($dataPribadiFields, function ($value) {
                return $value !== null;
            });

            if (!empty($dataPribadiFields)) {
                // Map no_telepon_ortu to no_hp_ayah
                if (isset($dataPribadiFields['no_telepon_ortu'])) {
                    $dataPribadiFields['no_hp_ayah'] = $normalizedParentPhone;
                    unset($dataPribadiFields['no_telepon_ortu']);
                }

                if ($siswa->dataPribadiSiswa) {
                    $siswa->dataPribadiSiswa->update($dataPribadiFields);
                } else {
                    // Create new data pribadi siswa if doesn't exist
                    $dataPribadiFields['status'] = 'aktif';
                    $siswa->dataPribadiSiswa()->create($dataPribadiFields);
                }
                Log::info('Updated data pribadi siswa:', $dataPribadiFields);
            }

            // Set kelas awal hanya jika siswa belum pernah punya kelas
            if ($request->filled('kelas_id') && $request->filled('tahun_ajaran_id') && $request->filled('tanggal_masuk')) {
                $alreadyHasClass = DB::table('kelas_siswa')
                    ->where('siswa_id', $siswa->id)
                    ->exists();

                if (!$alreadyHasClass) {
                    $siswa->kelas()->attach($request->kelas_id, [
                        'tahun_ajaran_id' => $request->tahun_ajaran_id,
                        'tanggal_masuk' => $request->tanggal_masuk,
                        'status' => 'aktif',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $this->freezeInitialAcademicSnapshotForStudent((int) $siswa->id);
                    Log::info('Set initial kelas assignment for siswa ID: ' . $id);
                }
            }

            DB::commit();

            Log::info('Update successful for siswa ID:', ['id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diupdate',
                'data' => $siswa->fresh(['roles', 'kelas', 'dataPribadiSiswa'])
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Update failed:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate siswa'
            ], 500);
        }
    }

    public function destroy($id)
    {
        $siswa = User::role('Siswa')->find($id);

        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan'
            ], 404);
        }

        // Hapus relasi dengan kelas
        $siswa->kelas()->detach();

        $siswa->delete();

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil dihapus'
        ]);
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx,xls|max:2048',
            'importMode' => 'nullable|string|in:auto,create,create-only,update,update-only',
            'import_mode' => 'nullable|string|in:auto,create,create-only,update,update-only',
            'updateMode' => 'nullable|string|in:partial,full',
            'update_mode' => 'nullable|string|in:partial,full'
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
            $jobId = uniqid('import_siswa_');

            // Get import options (support camelCase + snake_case + alias mode values)
            $rawImportMode = (string) $request->get('importMode', $request->get('import_mode', 'auto'));
            $importMode = match (strtolower(trim($rawImportMode))) {
                'create', 'create-only' => 'create-only',
                'update', 'update-only' => 'update-only',
                default => 'auto',
            };

            $rawUpdateMode = (string) $request->get('updateMode', $request->get('update_mode', 'partial'));
            $updateMode = strtolower(trim($rawUpdateMode)) === 'full' ? 'full' : 'partial';

            // Store initial progress
            Cache::put("import_progress_{$jobId}", [
                'progress' => 0,
                'status' => 'starting',
                'message' => 'Memulai import...',
                'total' => 0,
                'processed' => 0,
                'import_mode' => $importMode,
                'update_mode' => $updateMode
            ], 300); // 5 minutes

            $import = new \App\Imports\SiswaImport($jobId, $importMode, $updateMode);
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

            $summary = $import->getSummary();

            // Update final progress
            Cache::put("import_progress_{$jobId}", [
                'progress' => 100,
                'status' => 'completed',
                'message' => 'Import selesai',
                'total' => $summary['total'] ?? 0,
                'processed' => $summary['imported'] ?? 0,
                'summary' => $summary
            ], 300);

            // Jika ada data yang berhasil diimport, anggap sebagai sukses meskipun ada error
            if ($summary['imported'] > 0 || $summary['updated'] > 0) {
                $message = 'Import siswa berhasil';
                if ($import->hasErrors()) {
                    $message .= ' dengan beberapa error';
                }

                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => $summary,
                    'job_id' => $jobId
                ]);
            }

            // Jika tidak ada data yang berhasil diimport sama sekali
            if ($import->hasErrors()) {
                Cache::put("import_progress_{$jobId}", [
                    'progress' => 100,
                    'status' => 'failed',
                    'message' => 'Import gagal',
                    'total' => $summary['total'] ?? 0,
                    'processed' => 0,
                    'summary' => $summary
                ], 300);

                return response()->json([
                    'success' => false,
                    'message' => 'Import gagal - tidak ada data yang berhasil diproses',
                    'data' => $summary,
                    'job_id' => $jobId
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => 'Import siswa berhasil',
                'data' => $summary,
                'job_id' => $jobId
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat import siswa: ' . $e->getMessage());

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
                'message' => 'Gagal import siswa',
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

    public function export(Request $request)
    {
        try {
            $filename = 'data-siswa-' . date('Y-m-d-H-i-s') . '.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\SiswaExport(),
                $filename
            );
        } catch (\Exception $e) {
            Log::error('Error saat export siswa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal export siswa'
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        try {
            $filename = 'template-import-siswa.xlsx';

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\SiswaTemplateExport(),
                $filename
            );
        } catch (\Exception $e) {
            Log::error('Error saat download template siswa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal download template'
            ], 500);
        }
    }

    public function resetPassword(Request $request, $id)
    {
        $siswa = User::role('Siswa')->with('dataPribadiSiswa')->find($id);

        if (!$siswa) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa tidak ditemukan'
            ], 404);
        }

        try {
            // Check if we should reset to birthdate or use custom password
            if ($request->has('reset_to_birthdate') && $request->reset_to_birthdate) {
                // Get birthdate from data pribadi siswa
                if (!$siswa->dataPribadiSiswa || !$siswa->dataPribadiSiswa->tanggal_lahir) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Data tanggal lahir siswa tidak ditemukan'
                    ], 400);
                }

                // Format birthdate to DDMMYYYY
                $date = new \DateTime($siswa->dataPribadiSiswa->tanggal_lahir);
                $password = $date->format('dmY');

                Log::info('Resetting password to birthdate for siswa ID: ' . $id);
            } else {
                // Use custom password
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

                $password = $request->password;
                Log::info('Resetting password with custom password for siswa ID: ' . $id);
            }

            // Update password
            $siswa->update([
                'password' => Hash::make($password)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Password siswa berhasil direset',
                'data' => [
                    'user_id' => $siswa->id,
                    'nama_lengkap' => $siswa->nama_lengkap,
                    'reset_to_birthdate' => $request->has('reset_to_birthdate') && $request->reset_to_birthdate
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat reset password siswa: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal reset password'
            ], 500);
        }
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

    private function buildStudentEmailFromNis(string $nis): string
    {
        return strtolower(trim($nis)) . '@' . self::STUDENT_EMAIL_DOMAIN;
    }

    private function studentEmailTakenByAnother(string $email, ?int $exceptUserId = null): bool
    {
        $query = User::where('email', $email);
        if ($exceptUserId !== null) {
            $query->where('id', '<>', $exceptUserId);
        }

        return $query->exists();
    }

    private function normalizeBooleanFilter($value): ?bool
    {
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

            return null;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    private function normalizeKelasScope($value): string
    {
        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['awal', 'initial', 'first'], true) ? 'awal' : 'aktif';
    }

    private function applyInitialKelasFilter($query, int $kelasId): void
    {
        $query->where(function ($outer) use ($kelasId) {
            if (Schema::hasColumn('data_pribadi_siswa', 'kelas_awal_id')) {
                $outer->whereHas('dataPribadiSiswa', fn ($q) => $q->where('kelas_awal_id', $kelasId));
            }

            $outer->orWhereExists(function ($subQuery) use ($kelasId) {
                $subQuery->select(DB::raw(1))
                    ->from('kelas_siswa as ks0')
                    ->whereColumn('ks0.siswa_id', 'users.id')
                    ->where('ks0.kelas_id', $kelasId)
                    ->whereRaw(
                        "ks0.id = (
                            SELECT ks1.id
                            FROM kelas_siswa ks1
                            WHERE ks1.siswa_id = users.id
                            ORDER BY COALESCE(ks1.tanggal_masuk, ks1.created_at) ASC, ks1.id ASC
                            LIMIT 1
                        )"
                    );
            });
        });
    }

    private function applyInitialTingkatFilter($query, int $tingkatId): void
    {
        $query->where(function ($outer) use ($tingkatId) {
            if (Schema::hasColumn('data_pribadi_siswa', 'kelas_awal_id')) {
                $outer->whereHas('dataPribadiSiswa', function ($q) use ($tingkatId) {
                    $q->whereExists(function ($subQuery) use ($tingkatId) {
                        $subQuery->select(DB::raw(1))
                            ->from('kelas as k_snapshot')
                            ->whereColumn('k_snapshot.id', 'data_pribadi_siswa.kelas_awal_id')
                            ->where('k_snapshot.tingkat_id', $tingkatId);
                    });
                });
            }

            $outer->orWhereExists(function ($subQuery) use ($tingkatId) {
                $subQuery->select(DB::raw(1))
                    ->from('kelas_siswa as ks0')
                    ->join('kelas as k0', 'k0.id', '=', 'ks0.kelas_id')
                    ->whereColumn('ks0.siswa_id', 'users.id')
                    ->where('k0.tingkat_id', $tingkatId)
                    ->whereRaw(
                        "ks0.id = (
                            SELECT ks1.id
                            FROM kelas_siswa ks1
                            WHERE ks1.siswa_id = users.id
                            ORDER BY COALESCE(ks1.tanggal_masuk, ks1.created_at) ASC, ks1.id ASC
                            LIMIT 1
                        )"
                    );
            });
        });
    }

    private function applyInitialTahunAjaranFilter($query, int $tahunAjaranId): void
    {
        $query->where(function ($outer) use ($tahunAjaranId) {
            if (Schema::hasColumn('data_pribadi_siswa', 'tahun_ajaran_awal_id')) {
                $outer->whereHas('dataPribadiSiswa', fn ($q) => $q->where('tahun_ajaran_awal_id', $tahunAjaranId));
            }

            $outer->orWhereExists(function ($subQuery) use ($tahunAjaranId) {
                $subQuery->select(DB::raw(1))
                    ->from('kelas_siswa as ks0')
                    ->whereColumn('ks0.siswa_id', 'users.id')
                    ->where('ks0.tahun_ajaran_id', $tahunAjaranId)
                    ->whereRaw(
                        "ks0.id = (
                            SELECT ks1.id
                            FROM kelas_siswa ks1
                            WHERE ks1.siswa_id = users.id
                            ORDER BY COALESCE(ks1.tanggal_masuk, ks1.created_at) ASC, ks1.id ASC
                            LIMIT 1
                        )"
                    );
            });
        });
    }

    private function decorateSiswaAcademicSnapshot(User $siswa): User
    {
        $siswa->kelas_awal = $this->formatInitialAcademicSnapshot($siswa)
            ?: $this->formatKelasMembership($this->resolveInitialKelasMembership($siswa->kelas));
        $siswa->kelas_aktif = $this->formatKelasMembership($this->resolveActiveKelasMembership($siswa->kelas));

        return $siswa;
    }

    private function formatInitialAcademicSnapshot(User $siswa): ?array
    {
        $detail = $siswa->dataPribadiSiswa;
        $kelasAwalId = (int) ($detail?->kelas_awal_id ?? 0);
        $tahunAjaranAwalId = (int) ($detail?->tahun_ajaran_awal_id ?? 0);

        if ($kelasAwalId < 1 || $tahunAjaranAwalId < 1) {
            return null;
        }

        $kelasAwal = collect($siswa->kelas ?? [])->first(fn ($kelasItem) => (int) ($kelasItem->id ?? 0) === $kelasAwalId);
        if (!$kelasAwal) {
            $kelasAwal = Kelas::query()->with(['tingkat'])->find($kelasAwalId);
        }

        if (!$kelasAwal) {
            return null;
        }

        $tahunAjaran = TahunAjaran::query()->find($tahunAjaranAwalId);

        return [
            'id' => $kelasAwal->id,
            'nama_kelas' => $kelasAwal->nama_kelas,
            'tingkat' => $kelasAwal->tingkat?->nama,
            'tahun_ajaran_id' => $tahunAjaranAwalId,
            'tahun_ajaran' => $tahunAjaran ? [
                'id' => $tahunAjaran->id,
                'nama' => $tahunAjaran->nama,
                'tanggal_mulai' => $tahunAjaran->tanggal_mulai,
                'tanggal_selesai' => $tahunAjaran->tanggal_selesai,
            ] : null,
            'status' => 'awal',
            'is_active' => null,
            'tanggal_masuk' => $detail?->tanggal_masuk_kelas_awal,
            'tanggal_keluar' => null,
            'keterangan' => 'Snapshot akademik awal',
        ];
    }

    private function resolveInitialKelasMembership($kelasCollection): ?Kelas
    {
        return collect($kelasCollection ?? [])
            ->sort(function ($left, $right) {
                $leftDate = $this->kelasMembershipStartTimestamp($left);
                $rightDate = $this->kelasMembershipStartTimestamp($right);

                if ($leftDate === $rightDate) {
                    return (int) ($left->id ?? 0) <=> (int) ($right->id ?? 0);
                }

                return $leftDate <=> $rightDate;
            })
            ->first();
    }

    private function resolveActiveKelasMembership($kelasCollection): ?Kelas
    {
        return collect($kelasCollection ?? [])->first(fn ($kelasItem) => (bool) data_get($kelasItem, 'pivot.is_active'));
    }

    private function kelasMembershipStartTimestamp($kelasItem): int
    {
        $rawDate = data_get($kelasItem, 'pivot.tanggal_masuk') ?? data_get($kelasItem, 'pivot.created_at');

        if (!$rawDate) {
            return PHP_INT_MAX;
        }

        try {
            return \Carbon\Carbon::parse($rawDate)->getTimestamp();
        } catch (\Throwable $e) {
            return PHP_INT_MAX;
        }
    }

    private function formatKelasMembership(?Kelas $kelasItem): ?array
    {
        if (!$kelasItem) {
            return null;
        }

        return [
            'id' => $kelasItem->id,
            'nama_kelas' => $kelasItem->nama_kelas,
            'tingkat' => $kelasItem->tingkat?->nama,
            'tahun_ajaran_id' => data_get($kelasItem, 'pivot.tahun_ajaran_id'),
            'tahun_ajaran' => $kelasItem->tahunAjaran ? [
                'id' => $kelasItem->tahunAjaran->id,
                'nama' => $kelasItem->tahunAjaran->nama,
                'tanggal_mulai' => $kelasItem->tahunAjaran->tanggal_mulai,
                'tanggal_selesai' => $kelasItem->tahunAjaran->tanggal_selesai,
            ] : null,
            'status' => data_get($kelasItem, 'pivot.status'),
            'is_active' => (bool) data_get($kelasItem, 'pivot.is_active'),
            'tanggal_masuk' => data_get($kelasItem, 'pivot.tanggal_masuk'),
            'tanggal_keluar' => data_get($kelasItem, 'pivot.tanggal_keluar'),
            'keterangan' => data_get($kelasItem, 'pivot.keterangan'),
        ];
    }

    private function freezeInitialAcademicSnapshotForStudent(int $siswaId): void
    {
        if (
            $siswaId < 1
            || !Schema::hasColumn('data_pribadi_siswa', 'kelas_awal_id')
            || !Schema::hasColumn('data_pribadi_siswa', 'tahun_ajaran_awal_id')
            || !Schema::hasColumn('data_pribadi_siswa', 'tanggal_masuk_kelas_awal')
        ) {
            return;
        }

        $detail = DB::table('data_pribadi_siswa')
            ->where('user_id', $siswaId)
            ->first(['id', 'kelas_awal_id', 'tahun_ajaran_awal_id']);

        if (!$detail || ((int) ($detail->kelas_awal_id ?? 0) > 0 && (int) ($detail->tahun_ajaran_awal_id ?? 0) > 0)) {
            return;
        }

        $firstMembership = DB::table('kelas_siswa')
            ->where('siswa_id', $siswaId)
            ->orderByRaw('CASE WHEN tanggal_masuk IS NULL THEN 1 ELSE 0 END')
            ->orderBy('tanggal_masuk')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first(['kelas_id', 'tahun_ajaran_id', 'tanggal_masuk', 'created_at']);

        if (!$firstMembership || (int) ($firstMembership->kelas_id ?? 0) < 1 || (int) ($firstMembership->tahun_ajaran_id ?? 0) < 1) {
            return;
        }

        $tanggalMasuk = $firstMembership->tanggal_masuk;
        if (!$tanggalMasuk && !empty($firstMembership->created_at)) {
            $tanggalMasuk = \Carbon\Carbon::parse($firstMembership->created_at)->toDateString();
        }

        DB::table('data_pribadi_siswa')
            ->where('id', (int) $detail->id)
            ->update([
                'kelas_awal_id' => (int) $firstMembership->kelas_id,
                'tahun_ajaran_awal_id' => (int) $firstMembership->tahun_ajaran_id,
                'tanggal_masuk_kelas_awal' => $tanggalMasuk,
                'updated_at' => now(),
            ]);
    }
}
