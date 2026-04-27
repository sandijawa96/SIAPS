<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserFaceTemplate;
use App\Support\RoleNames;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PersonalDataController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const STUDENT_USER_EDITABLE_FIELDS = [
        'nama_lengkap',
        'agama',
        'alamat',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kota_kabupaten',
        'provinsi',
        'kode_pos',
    ];

    /**
     * @var array<int, string>
     */
    private const EMPLOYEE_USER_EDITABLE_FIELDS = [
        'nama_lengkap',
        'email',
        'agama',
        'alamat',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kota_kabupaten',
        'provinsi',
        'kode_pos',
    ];

    /**
     * @var array<int, string>
     */
    private const STUDENT_DETAIL_EDITABLE_FIELDS = [
        'dusun',
        'jenis_tinggal',
        'alat_transportasi',
        'skhun',
        'no_hp_siswa',
        'no_telepon_rumah',
        'no_hp_ortu',
        'no_kk',
        'nama_ayah',
        'tahun_lahir_ayah',
        'pekerjaan_ayah',
        'pendidikan_ayah',
        'penghasilan_ayah',
        'nik_ayah',
        'no_hp_ayah',
        'email_ayah',
        'nama_ibu',
        'tahun_lahir_ibu',
        'pekerjaan_ibu',
        'pendidikan_ibu',
        'penghasilan_ibu',
        'nik_ibu',
        'no_hp_ibu',
        'email_ibu',
        'wali_siswa',
        'hubungan_wali',
        'nama_wali',
        'tahun_lahir_wali',
        'pekerjaan_wali',
        'pendidikan_wali',
        'penghasilan_wali',
        'nik_wali',
        'no_hp_wali',
        'email_wali',
        'alamat_wali',
        'anak_ke',
        'jumlah_saudara',
        'golongan_darah',
        'tinggi_badan',
        'berat_badan',
        'lingkar_kepala',
        'jarak_rumah_km',
        'kebutuhan_khusus',
        'lintang',
        'bujur',
        'asal_sekolah',
        'npsn_asal',
        'alamat_sekolah_asal',
        'tahun_lulus_asal',
        'tahun_lulus_sd',
        'nilai_un_sd',
        'no_peserta_ujian_nasional',
        'no_seri_ijazah',
        'penerima_kps',
        'no_kps',
        'penerima_kip',
        'nomor_kip',
        'nama_di_kip',
        'nomor_kks',
        'no_registrasi_akta_lahir',
        'bank',
        'nomor_rekening_bank',
        'rekening_atas_nama',
        'layak_pip',
        'alasan_layak_pip',
    ];

    /**
     * @var array<int, string>
     */
    private const EMPLOYEE_DETAIL_EDITABLE_FIELDS = [
        'alamat_jalan',
        'nama_dusun',
        'no_hp',
        'no_telepon_kantor',
        'email_notifikasi',
        'nama_ibu_kandung',
        'nama_pasangan',
        'status_pernikahan',
        'status_perkawinan',
        'nip_suami_istri',
        'pekerjaan_pasangan',
        'jumlah_anak',
        'no_kk',
        'npwp',
        'nama_wajib_pajak',
        'kewarganegaraan',
        'bank',
        'nomor_rekening_bank',
        'rekening_atas_nama',
        'alamat_domisili',
        'lintang',
        'bujur',
        'data_anak',
    ];

    /**
     * @var array<int, string>
     */
    private const SHARED_PROFILE_FIELDS = [
        'agama',
        'alamat',
        'rt',
        'rw',
        'kelurahan',
        'kecamatan',
        'kota_kabupaten',
        'provinsi',
        'kode_pos',
    ];

    /**
     * @var array<int, string>
     */
    private const BOOLEAN_FIELDS = [
        'penerima_kps',
        'penerima_kip',
        'layak_pip',
        'gps_tracking',
        'sudah_lisensi_kepala_sekolah',
        'pernah_diklat_kepengawasan',
        'keahlian_braille',
        'keahlian_bahasa_isyarat',
        'is_active',
    ];

    /**
     * @var array<int, string>
     */
    private const INTEGER_FIELDS = [
        'tahun_lahir_ayah',
        'tahun_lahir_ibu',
        'tahun_lahir_wali',
        'tahun_lulus_asal',
        'tahun_lulus_sd',
        'tahun_sertifikasi',
        'tahun_lulus',
        'anak_ke',
        'jumlah_saudara',
        'jumlah_anak',
        'tinggi_badan',
        'berat_badan',
        'jam_mengajar_per_minggu',
    ];

    /**
     * @var array<int, string>
     */
    private const NUMERIC_FIELDS = [
        'nilai_un_sd',
        'lingkar_kepala',
        'jarak_rumah_km',
        'lintang',
        'bujur',
    ];

    /**
     * @var array<int, string>
     */
    private const DATE_FIELDS = [
        'tanggal_lahir',
        'tanggal_sk',
        'tanggal_cpns',
        'tmt',
        'tmt_cpns',
        'tmt_pengangkatan',
        'tmt_pns',
        'masa_kontrak_mulai',
        'masa_kontrak_selesai',
    ];

    /**
     * @var array<int, string>
     */
    private const ARRAY_FIELDS = [
        'metode_absensi',
        'last_tracked_location',
        'notifikasi_settings',
        'sub_jabatan',
        'mata_pelajaran',
        'kelas_yang_diajar',
        'data_anak',
        'sertifikat',
        'pelatihan',
        'hari_kerja',
    ];

    /**
     * @var array<int, string>
     */
    private const STUDENT_COMPLETENESS_FIELDS = [
        'nama_lengkap',
        'nis',
        'nisn',
        'jenis_kelamin',
        'tanggal_lahir',
        'agama',
        'alamat',
        'no_hp_siswa',
        'nama_ayah',
        'nama_ibu',
        'no_kk',
        'asal_sekolah',
        'active_class.nama_kelas',
    ];

    /**
     * @var array<string, int>
     */
    private const STUDENT_COMPLETENESS_WEIGHTS = [
        'nama_lengkap' => 5,
        'nis' => 4,
        'nisn' => 4,
        'jenis_kelamin' => 2,
        'tanggal_lahir' => 3,
        'agama' => 1,
        'alamat' => 2,
        'no_hp_siswa' => 3,
        'nama_ayah' => 2,
        'nama_ibu' => 2,
        'no_kk' => 4,
        'asal_sekolah' => 2,
        'active_class.nama_kelas' => 5,
    ];

    /**
     * @var array<int, string>
     */
    private const EMPLOYEE_COMPLETENESS_FIELDS = [
        'nama_lengkap',
        'email',
        'nip',
        'nik',
        'jenis_kelamin',
        'tanggal_lahir',
        'agama',
        'alamat',
        'no_hp',
        'status_kepegawaian',
        'jabatan',
    ];

    /**
     * @var array<string, int>
     */
    private const EMPLOYEE_COMPLETENESS_WEIGHTS = [
        'nama_lengkap' => 5,
        'email' => 4,
        'nip' => 5,
        'nik' => 4,
        'jenis_kelamin' => 2,
        'tanggal_lahir' => 3,
        'agama' => 1,
        'alamat' => 2,
        'no_hp' => 3,
        'status_kepegawaian' => 4,
        'jabatan' => 4,
    ];

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        if ($this->isSuperAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin menggunakan halaman manajemen pengguna',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildPayload($user),
        ]);
    }

    public function showForUser(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User target tidak ditemukan',
            ], 404);
        }

        if ($this->isSuperAdmin($targetUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Data pribadi Super Admin dikelola dari manajemen pengguna inti',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->buildPayload($targetUser),
        ]);
    }

    public function schema(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        if ($this->isSuperAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin menggunakan halaman manajemen pengguna',
            ], 403);
        }

        $isStudent = $this->isStudent($user);
        $sections = $this->buildSchemaSections($isStudent);

        return response()->json([
            'success' => true,
            'data' => [
                'profile_type' => $isStudent ? 'siswa' : 'pegawai',
                'sections' => $sections,
            ],
        ]);
    }

    public function schemaForUser(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User target tidak ditemukan',
            ], 404);
        }

        if ($this->isSuperAdmin($targetUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Data pribadi Super Admin dikelola dari manajemen pengguna inti',
            ], 403);
        }

        $isStudent = $this->isStudent($targetUser);
        $sections = $this->buildSchemaSections($isStudent);

        return response()->json([
            'success' => true,
            'data' => [
                'profile_type' => $isStudent ? 'siswa' : 'pegawai',
                'sections' => $sections,
            ],
        ]);
    }

    public function reviewQueue(Request $request): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        if (!$this->canViewPersonalDataVerification($actor)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke antrean verifikasi data pribadi',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'search' => 'nullable|string|max:100',
            'profile_type' => 'nullable|in:all,siswa,pegawai',
            'status_verifikasi' => 'nullable|in:all,draft,menunggu_verifikasi,terverifikasi,perlu_perbaikan',
            'completion_tier' => 'nullable|in:all,kurang,cukup,lengkap',
            'kelas_id' => 'nullable|integer|exists:kelas,id',
            'tingkat_id' => 'nullable|integer|exists:tingkat,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:nama_lengkap,last_personal_update_at,completion_percentage,status_verifikasi',
            'sort_direction' => 'nullable|in:asc,desc',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data filter tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $requestedProfileType = (string) ($validated['profile_type'] ?? 'all');
        $profileType = $this->resolveReviewQueueProfileTypeForActor($actor, $requestedProfileType);
        $statusFilter = (string) ($validated['status_verifikasi'] ?? 'all');
        $completionTierFilter = (string) ($validated['completion_tier'] ?? 'all');
        $search = trim((string) ($validated['search'] ?? ''));
        $kelasId = $validated['kelas_id'] ?? null;
        $tingkatId = $validated['tingkat_id'] ?? null;

        $usersQuery = User::query()
            ->with([
                'roles:id,name,display_name',
                'dataPribadiSiswa',
                'dataKepegawaian',
                'kelas' => function ($query) {
                    $query->with(['tingkat:id,nama', 'tahunAjaran:id,nama'])
                        ->withPivot('tahun_ajaran_id', 'status', 'tanggal_masuk', 'is_active');
                },
            ])
            ->whereDoesntHave('roles', function ($query) {
                $query->whereIn('name', RoleNames::aliases(RoleNames::SUPER_ADMIN));
            });

        if ($profileType === 'siswa') {
            $usersQuery->whereHas('roles', function ($query) {
                $query->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            });
        } elseif ($profileType === 'pegawai') {
            $usersQuery->whereDoesntHave('roles', function ($query) {
                $query->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            });
        }

        if ($search !== '') {
            $usersQuery->where(function ($query) use ($search) {
                $query->where('nama_lengkap', 'like', '%' . $search . '%')
                    ->orWhere('username', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhere('nis', 'like', '%' . $search . '%')
                    ->orWhere('nip', 'like', '%' . $search . '%')
                    ->orWhere('nik', 'like', '%' . $search . '%');
            });
        }

        if ($kelasId || $tingkatId) {
            $usersQuery->whereHas('kelas', function ($query) use ($kelasId, $tingkatId) {
                if ($kelasId) {
                    $query->where('kelas.id', $kelasId);
                }

                if ($tingkatId) {
                    $query->where('kelas.tingkat_id', $tingkatId);
                }
            });
        }

        $users = $usersQuery->get();
        $userIds = $users->pluck('id')->all();

        $reviewLogsByUser = $this->latestLogsBySubject(
            'personal_data_review',
            [
                'personal_data_review_approve',
                'personal_data_review_needs_revision',
                'personal_data_review_reset',
            ],
            $userIds
        );

        $personalUpdateLogsByUser = $this->latestLogsBySubject(
            'personal_data',
            [
                'personal_data_self_update',
                'personal_data_admin_update',
                'personal_data_self_avatar_update',
                'personal_data_admin_avatar_update',
            ],
            $userIds
        );

        $rows = $users->map(function (User $user) use ($reviewLogsByUser, $personalUpdateLogsByUser) {
            $reviewLog = $reviewLogsByUser[(int) $user->id] ?? null;
            $lastUpdateLog = $personalUpdateLogsByUser[(int) $user->id] ?? null;

            return $this->buildReviewQueueItem($user, $reviewLog, $lastUpdateLog);
        });

        if ($statusFilter !== '' && $statusFilter !== 'all') {
            $rows = $rows->filter(function (array $item) use ($statusFilter) {
                return ($item['status_verifikasi'] ?? '') === $statusFilter;
            })->values();
        }

        if ($completionTierFilter !== '' && $completionTierFilter !== 'all') {
            $rows = $rows->filter(function (array $item) use ($completionTierFilter) {
                return ($item['completion_tier'] ?? '') === $completionTierFilter;
            })->values();
        }

        $sortBy = (string) ($validated['sort_by'] ?? 'last_personal_update_at');
        $sortDirection = strtolower((string) ($validated['sort_direction'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $rows = $this->sortReviewRows($rows, $sortBy, $sortDirection);

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 15);

        $pagination = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $pagination,
        ]);
    }

    public function submitReviewDecision(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        if (!$this->canViewPersonalDataVerification($actor)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke proses verifikasi data pribadi',
            ], 403);
        }

        $targetUser = User::with([
            'roles:id,name,display_name',
            'dataPribadiSiswa',
            'dataKepegawaian',
            'kelas' => function ($query) {
                $query->with(['tingkat:id,nama', 'tahunAjaran:id,nama'])
                    ->withPivot('tahun_ajaran_id', 'status', 'tanggal_masuk', 'is_active');
            },
        ])->find($id);

        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User target tidak ditemukan',
            ], 404);
        }

        if ($this->isSuperAdmin($targetUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Data pribadi Super Admin dikelola dari manajemen pengguna inti',
            ], 403);
        }

        $targetIsStudent = $this->isStudent($targetUser);
        if (!$this->canVerifyPersonalDataByType($actor, $targetIsStudent)) {
            return response()->json([
                'success' => false,
                'message' => $targetIsStudent
                    ? 'Anda tidak memiliki akses verifikasi data pribadi siswa'
                    : 'Anda tidak memiliki akses verifikasi data pribadi pegawai',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|in:approve,needs_revision,reset',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data keputusan verifikasi tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $action = (string) $validated['action'];
        $statusMap = [
            'approve' => 'terverifikasi',
            'needs_revision' => 'perlu_perbaikan',
            'reset' => 'draft',
        ];

        $reviewActionMap = [
            'approve' => 'personal_data_review_approve',
            'needs_revision' => 'personal_data_review_needs_revision',
            'reset' => 'personal_data_review_reset',
        ];

        $reviewLabelMap = [
            'approve' => 'setujui',
            'needs_revision' => 'perlu perbaikan',
            'reset' => 'reset verifikasi',
        ];

        $latestReviewLog = $this->latestLogsBySubject(
            'personal_data_review',
            [
                'personal_data_review_approve',
                'personal_data_review_needs_revision',
                'personal_data_review_reset',
            ],
            [$targetUser->id]
        )[(int) $targetUser->id] ?? null;

        $latestUpdateLog = $this->latestLogsBySubject(
            'personal_data',
            [
                'personal_data_self_update',
                'personal_data_admin_update',
                'personal_data_self_avatar_update',
                'personal_data_admin_avatar_update',
            ],
            [$targetUser->id]
        )[(int) $targetUser->id] ?? null;

        $currentStatus = $this->resolveVerificationStatus(
            $latestReviewLog,
            $this->extractLogCreatedAt($latestUpdateLog),
        );
        $nextStatus = $statusMap[$action];
        $notes = isset($validated['notes']) ? trim((string) $validated['notes']) : '';

        $this->writeActivityLog([
            'actor_id' => $actor->id,
            'action' => $reviewActionMap[$action],
            'module' => 'personal_data_review',
            'subject_type' => User::class,
            'subject_id' => $targetUser->id,
            'old_values' => [
                'status_verifikasi' => $currentStatus,
            ],
            'new_values' => [
                'status_verifikasi' => $nextStatus,
                'notes' => $notes !== '' ? $notes : null,
            ],
            'notes' => sprintf(
                'Review data pribadi: %s (%s)',
                $reviewLabelMap[$action],
                $targetUser->nama_lengkap ?? $targetUser->username ?? ('User #' . $targetUser->id)
            ),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $freshReviewLog = $this->latestLogsBySubject(
            'personal_data_review',
            [
                'personal_data_review_approve',
                'personal_data_review_needs_revision',
                'personal_data_review_reset',
            ],
            [$targetUser->id]
        )[(int) $targetUser->id] ?? null;

        return response()->json([
            'success' => true,
            'message' => 'Keputusan verifikasi berhasil disimpan',
            'data' => $this->buildReviewQueueItem($targetUser, $freshReviewLog, $latestUpdateLog),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        if ($this->isSuperAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin menggunakan halaman manajemen pengguna',
            ], 403);
        }

        $isStudent = $this->isStudent($user);
        $payload = $this->normalizeBooleanPayload($request->all());

        if ($isStudent && array_key_exists('email', $payload) && (string) $payload['email'] !== (string) $user->email) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => [
                    'email' => ['Email siswa ditentukan otomatis oleh sistem dan tidak dapat diubah.'],
                ],
            ], 422);
        }

        $allowedFields = array_values(array_unique(array_merge(
            $isStudent ? self::STUDENT_USER_EDITABLE_FIELDS : self::EMPLOYEE_USER_EDITABLE_FIELDS,
            $this->detailEditableFields($isStudent)
        )));
        $forbiddenFields = array_values(array_diff(array_keys($payload), $allowedFields));

        if (!empty($forbiddenFields)) {
            $errors = [];
            foreach ($forbiddenFields as $field) {
                $errors[$field] = ['Field ini tidak dapat diubah melalui data pribadi.'];
            }

            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $errors,
            ], 422);
        }

        $rules = $this->buildValidationRules($user, $payload, $isStudent);
        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $userPayload = Arr::only(
            $validated,
            $isStudent ? self::STUDENT_USER_EDITABLE_FIELDS : self::EMPLOYEE_USER_EDITABLE_FIELDS
        );

        $detailPayload = Arr::only($validated, $this->detailEditableFields($isStudent));
        $detailPayload = array_merge(Arr::only($validated, self::SHARED_PROFILE_FIELDS), $detailPayload);
        $beforePayload = $this->buildPayload($user);

        DB::beginTransaction();

        try {
            if (!empty($userPayload)) {
                $user->fill($userPayload);
                $user->save();
            }

            if (!empty($detailPayload)) {
                if ($isStudent) {
                    if (!$user->dataPribadiSiswa) {
                        $detailPayload['status'] = $detailPayload['status'] ?? 'aktif';
                        $user->dataPribadiSiswa()->create($detailPayload);
                    } else {
                        $user->dataPribadiSiswa()->update($detailPayload);
                    }
                } else {
                    if (!$user->dataKepegawaian) {
                        $detailPayload['is_active'] = $detailPayload['is_active'] ?? true;
                        $user->dataKepegawaian()->create($detailPayload);
                    } else {
                        $user->dataKepegawaian()->update($detailPayload);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data pribadi',
                'error' => $e->getMessage(),
            ], 500);
        }

        $freshUser = $user->fresh() ?? $user;
        $afterPayload = $this->buildPayload($freshUser);
        $this->logPersonalDataMutation(
            $user,
            $freshUser,
            'personal_data_self_update',
            'Self-service update data pribadi',
            $beforePayload,
            $afterPayload,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Data pribadi berhasil diperbarui',
            'data' => $afterPayload,
        ]);
    }

    public function updateForUser(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User target tidak ditemukan',
            ], 404);
        }

        if ($this->isSuperAdmin($targetUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Data pribadi Super Admin dikelola dari manajemen pengguna inti',
            ], 403);
        }

        $isStudent = $this->isStudent($targetUser);
        $payload = $this->normalizeBooleanPayload($request->all());

        if ($isStudent && array_key_exists('email', $payload) && (string) $payload['email'] !== (string) $targetUser->email) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => [
                    'email' => ['Email siswa ditentukan otomatis oleh sistem dan tidak dapat diubah.'],
                ],
            ], 422);
        }

        $allowedFields = array_values(array_unique(array_merge(
            $isStudent ? self::STUDENT_USER_EDITABLE_FIELDS : self::EMPLOYEE_USER_EDITABLE_FIELDS,
            $this->detailEditableFields($isStudent)
        )));
        $forbiddenFields = array_values(array_diff(array_keys($payload), $allowedFields));

        if (!empty($forbiddenFields)) {
            $errors = [];
            foreach ($forbiddenFields as $field) {
                $errors[$field] = ['Field ini tidak dapat diubah melalui data pribadi.'];
            }

            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $errors,
            ], 422);
        }

        $rules = $this->buildValidationRules($targetUser, $payload, $isStudent);
        $validator = Validator::make($payload, $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $userPayload = Arr::only(
            $validated,
            $isStudent ? self::STUDENT_USER_EDITABLE_FIELDS : self::EMPLOYEE_USER_EDITABLE_FIELDS
        );

        $detailPayload = Arr::only($validated, $this->detailEditableFields($isStudent));
        $detailPayload = array_merge(Arr::only($validated, self::SHARED_PROFILE_FIELDS), $detailPayload);
        $beforePayload = $this->buildPayload($targetUser);

        DB::beginTransaction();

        try {
            if (!empty($userPayload)) {
                $targetUser->fill($userPayload);
                $targetUser->save();
            }

            if (!empty($detailPayload)) {
                if ($isStudent) {
                    if (!$targetUser->dataPribadiSiswa) {
                        $detailPayload['status'] = $detailPayload['status'] ?? 'aktif';
                        $targetUser->dataPribadiSiswa()->create($detailPayload);
                    } else {
                        $targetUser->dataPribadiSiswa()->update($detailPayload);
                    }
                } else {
                    if (!$targetUser->dataKepegawaian) {
                        $detailPayload['is_active'] = $detailPayload['is_active'] ?? true;
                        $targetUser->dataKepegawaian()->create($detailPayload);
                    } else {
                        $targetUser->dataKepegawaian()->update($detailPayload);
                    }
                }
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data pribadi user',
                'error' => $e->getMessage(),
            ], 500);
        }

        $freshTargetUser = $targetUser->fresh() ?? $targetUser;
        $afterPayload = $this->buildPayload($freshTargetUser);
        $this->logPersonalDataMutation(
            $actor,
            $freshTargetUser,
            'personal_data_admin_update',
            'Admin update data pribadi user',
            $beforePayload,
            $afterPayload,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Data pribadi user berhasil diperbarui',
            'data' => $afterPayload,
        ]);
    }

    public function updateAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        if ($this->isSuperAdmin($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin menggunakan halaman manajemen pengguna',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('avatar');
        $oldPhotoPath = $user->foto_profil;
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
        $filename = sprintf(
            'profile_%d_%s_%s.%s',
            $user->id,
            now()->format('YmdHisv'),
            Str::lower(Str::random(6)),
            $extension
        );
        $path = $file->storeAs('foto_profil', $filename, 'public');

        if ($oldPhotoPath && $oldPhotoPath !== $path && Storage::disk('public')->exists($oldPhotoPath)) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $user->update(['foto_profil' => $path]);
        $user->refresh();
        $this->logAvatarMutation(
            $user,
            $user,
            'personal_data_self_avatar_update',
            'Self-service update foto profil',
            $oldPhotoPath,
            $path,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Foto profil berhasil diperbarui',
            'data' => [
                'foto_profil' => $user->foto_profil,
                'foto_profil_url' => $user->foto_profil_url,
            ],
        ]);
    }

    public function updateAvatarForUser(Request $request, int $id): JsonResponse
    {
        $actor = $request->user();
        if (!$actor instanceof User) {
            return response()->json([
                'success' => false,
                'message' => 'User tidak terautentikasi',
            ], 401);
        }

        $targetUser = User::find($id);
        if (!$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'User target tidak ditemukan',
            ], 404);
        }

        if ($this->isSuperAdmin($targetUser)) {
            return response()->json([
                'success' => false,
                'message' => 'Data pribadi Super Admin dikelola dari manajemen pengguna inti',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'avatar' => 'required|image|mimes:jpeg,png,jpg|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('avatar');
        $oldPhotoPath = $targetUser->foto_profil;
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->extension() ?: 'jpg'));
        $filename = sprintf(
            'profile_%d_%s_%s.%s',
            $targetUser->id,
            now()->format('YmdHisv'),
            Str::lower(Str::random(6)),
            $extension
        );
        $path = $file->storeAs('foto_profil', $filename, 'public');

        if ($oldPhotoPath && $oldPhotoPath !== $path && Storage::disk('public')->exists($oldPhotoPath)) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        $targetUser->update(['foto_profil' => $path]);
        $targetUser->refresh();
        $this->logAvatarMutation(
            $actor,
            $targetUser,
            'personal_data_admin_avatar_update',
            'Admin update foto profil user',
            $oldPhotoPath,
            $path,
            $request->ip(),
            $request->userAgent(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Foto profil user berhasil diperbarui',
            'data' => [
                'foto_profil' => $targetUser->foto_profil,
                'foto_profil_url' => $targetUser->foto_profil_url,
            ],
        ]);
    }

    /**
     * @param array<int, int|string> $userIds
     * @param array<int, string> $actions
     * @return array<int, object>
     */
    private function latestLogsBySubject(string $module, array $actions, array $userIds): array
    {
        if (empty($userIds) || !Schema::hasTable('activity_logs')) {
            return [];
        }

        $schemaType = $this->activityLogSchemaType();
        if ($schemaType === 'none') {
            return [];
        }

        $query = DB::table('activity_logs as logs')
            ->where('logs.module', $module)
            ->whereIn('logs.subject_id', $userIds)
            ->where('logs.subject_type', User::class)
            ->orderByDesc('logs.created_at');

        if ($schemaType === 'legacy') {
            $query
                ->whereIn('logs.action', $actions)
                ->leftJoin('users as actors', 'actors.id', '=', 'logs.user_id')
                ->select([
                    'logs.subject_id',
                    'logs.action',
                    'logs.notes',
                    'logs.created_at',
                    'actors.id as actor_id',
                    'actors.nama_lengkap as actor_nama_lengkap',
                    'actors.username as actor_username',
                    'actors.email as actor_email',
                ]);
        } else {
            $query
                ->whereIn('logs.event', $actions)
                ->leftJoin('users as actors', 'actors.id', '=', 'logs.causer_id')
                ->select([
                    'logs.subject_id',
                    'logs.event as action',
                    'logs.description as notes',
                    'logs.created_at',
                    'actors.id as actor_id',
                    'actors.nama_lengkap as actor_nama_lengkap',
                    'actors.username as actor_username',
                    'actors.email as actor_email',
                ]);
        }

        $rows = $query->get();
        $latestByUser = [];

        foreach ($rows as $row) {
            $subjectId = (int) ($row->subject_id ?? 0);
            if ($subjectId <= 0 || isset($latestByUser[$subjectId])) {
                continue;
            }

            $log = new \stdClass();
            $log->subject_id = $subjectId;
            $log->action = (string) ($row->action ?? '');
            $log->notes = $row->notes ?? null;
            $log->created_at = $row->created_at ? \Carbon\Carbon::parse((string) $row->created_at) : null;
            $log->user = null;

            if (!empty($row->actor_id)) {
                $actor = new \stdClass();
                $actor->id = (int) $row->actor_id;
                $actor->nama_lengkap = $row->actor_nama_lengkap ?? null;
                $actor->username = $row->actor_username ?? null;
                $actor->email = $row->actor_email ?? null;
                $log->user = $actor;
            }

            $latestByUser[$subjectId] = $log;
        }

        return $latestByUser;
    }

    private function buildReviewQueueItem(
        User $user,
        ?object $latestReviewLog = null,
        ?object $latestUpdateLog = null
    ): array {
        $payload = $this->buildPayload($user);
        $isStudent = ($payload['profile_type'] ?? '') === 'siswa';

        $completion = $this->calculateCompleteness($payload, $isStudent);
        $lastPersonalUpdateAt = $this->resolveLastPersonalUpdateAt($user, $latestUpdateLog);
        $statusVerifikasi = $this->resolveVerificationStatus($latestReviewLog, $this->extractLogCreatedAt($latestUpdateLog));

        $lastReviewBy = null;
        if ($latestReviewLog?->user) {
            $lastReviewBy = [
                'id' => $latestReviewLog->user->id,
                'nama_lengkap' => $latestReviewLog->user->nama_lengkap ?? null,
                'username' => $latestReviewLog->user->username ?? null,
                'email' => $latestReviewLog->user->email ?? null,
            ];
        }

        $roles = $user->roles
            ? $user->roles->map(function ($role) {
                return [
                    'id' => $role->id ?? null,
                    'name' => $role->name ?? '',
                    'display_name' => $role->display_name ?? $role->name ?? '',
                ];
            })->values()->all()
            : [];

        return [
            'user_id' => $user->id,
            'profile_type' => $payload['profile_type'] ?? ($isStudent ? 'siswa' : 'pegawai'),
            'nama_lengkap' => $payload['common']['nama_lengkap'] ?? $user->nama_lengkap,
            'username' => $payload['common']['username'] ?? $user->username,
            'email' => $payload['common']['email'] ?? $user->email,
            'status_akun' => (bool) $user->is_active,
            'roles' => $roles,
            'kelas_aktif' => $payload['active_class']['nama_kelas'] ?? null,
            'tingkat_aktif' => $payload['active_class']['tingkat'] ?? null,
            'status_verifikasi' => $statusVerifikasi,
            'status_verifikasi_label' => $this->verificationStatusLabel($statusVerifikasi),
            'completion_percentage' => $completion['percentage'],
            'completion_tier' => $completion['tier'],
            'completion' => [
                'filled' => $completion['filled'],
                'total' => $completion['total'],
                'filled_weight' => $completion['filled_weight'],
                'total_weight' => $completion['total_weight'],
                'tier' => $completion['tier'],
                'missing_fields' => $completion['missing_fields'],
            ],
            'last_personal_update_at' => $lastPersonalUpdateAt ? $lastPersonalUpdateAt->toDateTimeString() : null,
            'last_reviewed_at' => $latestReviewLog?->created_at?->toDateTimeString(),
            'last_review_action' => $latestReviewLog ? $this->reviewActionLabel((string) $latestReviewLog->action) : null,
            'last_review_notes' => $latestReviewLog?->notes,
            'last_reviewed_by' => $lastReviewBy,
        ];
    }

    private function activityLogSchemaType(): string
    {
        if (!Schema::hasTable('activity_logs')) {
            return 'none';
        }

        $hasLegacyColumns = Schema::hasColumn('activity_logs', 'action')
            && Schema::hasColumn('activity_logs', 'user_id')
            && Schema::hasColumn('activity_logs', 'subject_id');

        if ($hasLegacyColumns) {
            return 'legacy';
        }

        $hasSpatieColumns = Schema::hasColumn('activity_logs', 'event')
            && Schema::hasColumn('activity_logs', 'causer_id')
            && Schema::hasColumn('activity_logs', 'subject_id');

        return $hasSpatieColumns ? 'spatie' : 'none';
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeActivityLog(array $payload): void
    {
        $schemaType = $this->activityLogSchemaType();
        if ($schemaType === 'none') {
            return;
        }

        $oldValues = $payload['old_values'] ?? null;
        $newValues = $payload['new_values'] ?? null;

        if ($schemaType === 'legacy') {
            DB::table('activity_logs')->insert([
                'user_id' => $payload['actor_id'] ?? null,
                'action' => $payload['action'] ?? null,
                'module' => $payload['module'] ?? 'general',
                'subject_type' => $payload['subject_type'] ?? null,
                'subject_id' => $payload['subject_id'] ?? null,
                'old_values' => $oldValues ? json_encode($oldValues) : null,
                'new_values' => $newValues ? json_encode($newValues) : null,
                'notes' => $payload['notes'] ?? null,
                'ip_address' => $payload['ip_address'] ?? null,
                'user_agent' => $payload['user_agent'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return;
        }

        DB::table('activity_logs')->insert([
            'log_name' => $payload['module'] ?? 'personal_data',
            'description' => (string) ($payload['notes'] ?? $payload['action'] ?? 'personal_data_event'),
            'subject_type' => $payload['subject_type'] ?? null,
            'subject_id' => $payload['subject_id'] ?? null,
            'causer_type' => User::class,
            'causer_id' => $payload['actor_id'] ?? null,
            'properties' => json_encode([
                'old_values' => $oldValues,
                'new_values' => $newValues,
            ]),
            'event' => $payload['action'] ?? null,
            'module' => $payload['module'] ?? 'general',
            'level' => 'info',
            'ip_address' => $payload['ip_address'] ?? null,
            'user_agent' => $payload['user_agent'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function verificationStatusLabel(string $status): string
    {
        return match ($status) {
            'menunggu_verifikasi' => 'Menunggu Verifikasi',
            'terverifikasi' => 'Terverifikasi',
            'perlu_perbaikan' => 'Perlu Perbaikan',
            default => 'Draft',
        };
    }

    private function verificationStatusPriority(string $status): int
    {
        return match ($status) {
            'perlu_perbaikan' => 1,
            'menunggu_verifikasi' => 2,
            'draft' => 3,
            'terverifikasi' => 4,
            default => 99,
        };
    }

    private function reviewActionLabel(string $action): ?string
    {
        return match ($action) {
            'personal_data_review_approve' => 'Setujui',
            'personal_data_review_needs_revision' => 'Perlu Perbaikan',
            'personal_data_review_reset' => 'Reset',
            default => null,
        };
    }

    private function resolveVerificationStatus(?object $latestReviewLog, ?\Carbon\CarbonInterface $lastPersonalUpdateAt): string
    {
        if (!$latestReviewLog) {
            return $lastPersonalUpdateAt ? 'menunggu_verifikasi' : 'draft';
        }

        $baseStatus = match ((string) ($latestReviewLog->action ?? '')) {
            'personal_data_review_approve' => 'terverifikasi',
            'personal_data_review_needs_revision' => 'perlu_perbaikan',
            default => 'draft',
        };

        $reviewedAt = $latestReviewLog->created_at ?? null;
        if ($lastPersonalUpdateAt && $reviewedAt && $lastPersonalUpdateAt->gt($reviewedAt)) {
            return 'menunggu_verifikasi';
        }

        return $baseStatus;
    }

    private function extractLogCreatedAt(?object $log): ?\Carbon\CarbonInterface
    {
        if (($log->created_at ?? null) instanceof \Carbon\CarbonInterface) {
            return $log->created_at;
        }

        return null;
    }

    private function resolveLastPersonalUpdateAt(User $user, ?object $latestUpdateLog): ?\Carbon\CarbonInterface
    {
        $timestamps = [];

        if (($latestUpdateLog->created_at ?? null) instanceof \Carbon\CarbonInterface) {
            $timestamps[] = $latestUpdateLog->created_at;
        }

        if ($user->dataPribadiSiswa?->updated_at) {
            $timestamps[] = $user->dataPribadiSiswa->updated_at;
        }

        if ($user->dataKepegawaian?->updated_at) {
            $timestamps[] = $user->dataKepegawaian->updated_at;
        }

        if ($user->updated_at) {
            $timestamps[] = $user->updated_at;
        }

        if (empty($timestamps)) {
            return null;
        }

        $latest = $timestamps[0];
        foreach ($timestamps as $time) {
            if ($time && $time->gt($latest)) {
                $latest = $time;
            }
        }

        return $latest;
    }

    /**
     * @param \Illuminate\Support\Collection<int, array<string, mixed>> $rows
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function sortReviewRows($rows, string $sortBy, string $sortDirection)
    {
        $sorted = $rows->sortBy(function (array $row) use ($sortBy) {
            return match ($sortBy) {
                'nama_lengkap' => strtolower((string) ($row['nama_lengkap'] ?? '')),
                'completion_percentage' => (int) ($row['completion_percentage'] ?? 0),
                'status_verifikasi' => $this->verificationStatusPriority((string) ($row['status_verifikasi'] ?? '')),
                default => $this->asTimestamp($row['last_personal_update_at'] ?? null),
            };
        });

        return $sortDirection === 'desc'
            ? $sorted->reverse()->values()
            : $sorted->values();
    }

    private function asTimestamp(mixed $value): int
    {
        if (!$value) {
            return 0;
        }

        $timestamp = strtotime((string) $value);
        return $timestamp === false ? 0 : $timestamp;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{filled:int,total:int,filled_weight:int,total_weight:int,percentage:int,tier:string,missing_fields:array<int,string>}
     */
    private function calculateCompleteness(array $payload, bool $isStudent): array
    {
        $fields = $isStudent ? self::STUDENT_COMPLETENESS_FIELDS : self::EMPLOYEE_COMPLETENESS_FIELDS;
        $weights = $isStudent ? self::STUDENT_COMPLETENESS_WEIGHTS : self::EMPLOYEE_COMPLETENESS_WEIGHTS;
        $source = array_merge(
            (array) ($payload['common'] ?? []),
            (array) ($payload['detail'] ?? [])
        );
        $source['active_class.nama_kelas'] = $payload['active_class']['nama_kelas'] ?? null;

        $filled = 0;
        $filledWeight = 0;
        $totalWeight = 0;
        $missing = [];

        foreach ($fields as $field) {
            $value = $source[$field] ?? null;
            $weight = max(1, (int) ($weights[$field] ?? 1));
            $totalWeight += $weight;

            if ($this->isFilledValue($value)) {
                $filled++;
                $filledWeight += $weight;
                continue;
            }

            $missing[] = $this->fieldLabel($field);
        }

        $total = count($fields);
        $percentage = $totalWeight > 0 ? (int) round(($filledWeight / $totalWeight) * 100) : 0;

        return [
            'filled' => $filled,
            'total' => $total,
            'filled_weight' => $filledWeight,
            'total_weight' => $totalWeight,
            'percentage' => $percentage,
            'tier' => $this->completionTier($percentage),
            'missing_fields' => $missing,
        ];
    }

    private function completionTier(int $percentage): string
    {
        if ($percentage >= 90) {
            return 'lengkap';
        }

        if ($percentage >= 60) {
            return 'cukup';
        }

        return 'kurang';
    }

    private function isFilledValue(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '' && trim($value) !== '-';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return true;
    }

    /**
     * @param array<string, mixed> $beforePayload
     * @param array<string, mixed> $afterPayload
     */
    private function logPersonalDataMutation(
        User $actor,
        User $targetUser,
        string $action,
        string $notes,
        array $beforePayload,
        array $afterPayload,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        [$oldValues, $newValues] = $this->extractPayloadDiff($beforePayload, $afterPayload);
        if (empty($oldValues) && empty($newValues)) {
            return;
        }

        $this->writeActivityLog([
            'actor_id' => $actor->id,
            'action' => $action,
            'module' => 'personal_data',
            'subject_type' => User::class,
            'subject_id' => $targetUser->id,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'notes' => $notes,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    private function logAvatarMutation(
        User $actor,
        User $targetUser,
        string $action,
        string $notes,
        ?string $oldPhotoPath,
        ?string $newPhotoPath,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {
        if ((string) $oldPhotoPath === (string) $newPhotoPath) {
            return;
        }

        $this->writeActivityLog([
            'actor_id' => $actor->id,
            'action' => $action,
            'module' => 'personal_data',
            'subject_type' => User::class,
            'subject_id' => $targetUser->id,
            'old_values' => [
                'common.foto_profil' => $oldPhotoPath,
            ],
            'new_values' => [
                'common.foto_profil' => $newPhotoPath,
            ],
            'notes' => $notes,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * @param array<string, mixed> $beforePayload
     * @param array<string, mixed> $afterPayload
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function extractPayloadDiff(array $beforePayload, array $afterPayload): array
    {
        $beforeFlat = $this->flattenPayloadForAudit($beforePayload);
        $afterFlat = $this->flattenPayloadForAudit($afterPayload);
        $allKeys = array_values(array_unique(array_merge(array_keys($beforeFlat), array_keys($afterFlat))));

        $oldValues = [];
        $newValues = [];
        foreach ($allKeys as $key) {
            $oldValue = $beforeFlat[$key] ?? null;
            $newValue = $afterFlat[$key] ?? null;

            if ($this->isSameValue($oldValue, $newValue)) {
                continue;
            }

            $oldValues[$key] = $oldValue;
            $newValues[$key] = $newValue;
        }

        return [$oldValues, $newValues];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function flattenPayloadForAudit(array $payload): array
    {
        $flat = [];
        foreach ((array) ($payload['common'] ?? []) as $key => $value) {
            $flat['common.' . $key] = $value;
        }

        foreach ((array) ($payload['detail'] ?? []) as $key => $value) {
            $flat['detail.' . $key] = $value;
        }

        if (isset($payload['active_class']) && is_array($payload['active_class'])) {
            foreach ($payload['active_class'] as $key => $value) {
                $flat['active_class.' . $key] = $value;
            }
        }

        return $flat;
    }

    private function isSameValue(mixed $oldValue, mixed $newValue): bool
    {
        return json_encode($oldValue) === json_encode($newValue);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function buildValidationRules(User $user, array $payload, bool $isStudent): array
    {
        $rules = [
            'nama_lengkap' => 'sometimes|string|max:100',
            'agama' => 'sometimes|nullable|string|max:50',
            'alamat' => 'sometimes|nullable|string',
            'rt' => 'sometimes|nullable|string|max:3',
            'rw' => 'sometimes|nullable|string|max:3',
            'kelurahan' => 'sometimes|nullable|string|max:255',
            'kecamatan' => 'sometimes|nullable|string|max:255',
            'kota_kabupaten' => 'sometimes|nullable|string|max:255',
            'provinsi' => 'sometimes|nullable|string|max:255',
            'kode_pos' => 'sometimes|nullable|string|max:10',
        ];

        if (!$isStudent) {
            $rules['email'] = 'sometimes|email|max:255|unique:users,email,' . $user->id;
        }

        $detailFields = $this->detailEditableFields($isStudent);
        foreach ($detailFields as $field) {
            if (!array_key_exists($field, $payload) || array_key_exists($field, $rules)) {
                continue;
            }

            $rules[$field] = $this->fieldValidationRule($field);
        }

        return $rules;
    }

    private function fieldValidationRule(string $field): string
    {
        if (in_array($field, self::BOOLEAN_FIELDS, true)) {
            return 'sometimes|nullable|boolean';
        }

        if (in_array($field, self::INTEGER_FIELDS, true)) {
            return 'sometimes|nullable|integer';
        }

        if (in_array($field, self::NUMERIC_FIELDS, true)) {
            return 'sometimes|nullable|numeric';
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            return 'sometimes|nullable|date';
        }

        if (in_array($field, self::ARRAY_FIELDS, true)) {
            return 'sometimes|nullable|array';
        }

        if (str_starts_with($field, 'email_') || $field === 'email_siswa' || $field === 'email_notifikasi') {
            return 'sometimes|nullable|email|max:255';
        }

        if (in_array($field, ['jam_masuk', 'jam_pulang'], true)) {
            return 'sometimes|nullable|date_format:H:i';
        }

        return 'sometimes|nullable|string|max:1000';
    }

    /**
     * @return array<int, string>
     */
    private function detailEditableFields(bool $isStudent): array
    {
        $tableName = $isStudent ? 'data_pribadi_siswa' : 'data_kepegawaian';
        $sourceFields = $isStudent ? self::STUDENT_DETAIL_EDITABLE_FIELDS : self::EMPLOYEE_DETAIL_EDITABLE_FIELDS;

        if (!Schema::hasTable($tableName)) {
            return [];
        }

        return array_values(array_filter($sourceFields, function (string $field) use ($tableName) {
            return Schema::hasColumn($tableName, $field);
        }));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildSchemaSections(bool $isStudent): array
    {
        $sections = $isStudent
            ? $this->studentSchemaSections()
            : $this->employeeSchemaSections();

        return $this->filterUnavailableSchemaFields($sections, $isStudent);
    }

    /**
     * @param array<int, array<string, mixed>> $sections
     * @return array<int, array<string, mixed>>
     */
    private function filterUnavailableSchemaFields(array $sections, bool $isStudent): array
    {
        $detailTable = $isStudent ? 'data_pribadi_siswa' : 'data_kepegawaian';
        $filteredSections = [];

        foreach ($sections as $section) {
            $fields = $section['fields'] ?? [];
            $filteredFields = [];

            foreach ($fields as $field) {
                $key = (string) ($field['key'] ?? '');
                if ($key === '') {
                    continue;
                }

                if (str_starts_with($key, 'active_class.')) {
                    $filteredFields[] = $field;
                    continue;
                }

                if (Schema::hasColumn('users', $key)) {
                    $filteredFields[] = $field;
                    continue;
                }

                if (Schema::hasTable($detailTable) && Schema::hasColumn($detailTable, $key)) {
                    $filteredFields[] = $field;
                }
            }

            if (!empty($filteredFields)) {
                $section['fields'] = $filteredFields;
                $filteredSections[] = $section;
            }
        }

        return $filteredSections;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function studentSchemaSections(): array
    {
        return [
            [
                'key' => 'identitas',
                'label' => 'Identitas',
                'fields' => [
                    $this->makeField('nama_lengkap', 'Nama Lengkap', true, 'text'),
                    $this->makeField('username', 'Username', false, 'text'),
                    $this->makeField('nis', 'NIS', false, 'text'),
                    $this->makeField('nisn', 'NISN', false, 'text'),
                    $this->makeField('email', 'Email', false, 'email'),
                    $this->makeField('jenis_kelamin', 'Jenis Kelamin', false, 'text'),
                    $this->makeField('tempat_lahir', 'Tempat Lahir', false, 'text'),
                    $this->makeField('tanggal_lahir', 'Tanggal Lahir', false, 'date'),
                    $this->makeField('nik', 'NIK', false, 'text'),
                    $this->makeField('agama', 'Agama', true, 'text'),
                ],
            ],
            [
                'key' => 'kontak',
                'label' => 'Kontak',
                'fields' => [
                    $this->makeField('no_hp_siswa', 'No HP Siswa'),
                    $this->makeField('no_telepon_rumah', 'No Telepon Rumah'),
                    $this->makeField('no_hp_ortu', 'No HP Orang Tua'),
                    $this->makeField('email_siswa', 'Email Siswa', false, 'email'),
                    $this->makeField('email_ayah', 'Email Ayah', true, 'email'),
                    $this->makeField('email_ibu', 'Email Ibu', true, 'email'),
                    $this->makeField('email_wali', 'Email Wali', true, 'email'),
                ],
            ],
            [
                'key' => 'alamat',
                'label' => 'Alamat',
                'fields' => [
                    $this->makeField('alamat', 'Alamat', true, 'textarea'),
                    $this->makeField('rt', 'RT'),
                    $this->makeField('rw', 'RW'),
                    $this->makeField('dusun', 'Dusun'),
                    $this->makeField('kelurahan', 'Kelurahan'),
                    $this->makeField('kecamatan', 'Kecamatan'),
                    $this->makeField('kota_kabupaten', 'Kota/Kabupaten'),
                    $this->makeField('provinsi', 'Provinsi'),
                    $this->makeField('kode_pos', 'Kode Pos'),
                    $this->makeField('jenis_tinggal', 'Jenis Tinggal'),
                    $this->makeField('alat_transportasi', 'Alat Transportasi'),
                    $this->makeField('jarak_rumah_km', 'Jarak Rumah (KM)', true, 'number'),
                    $this->makeField('lintang', 'Lintang', true, 'number'),
                    $this->makeField('bujur', 'Bujur', true, 'number'),
                ],
            ],
            [
                'key' => 'keluarga',
                'label' => 'Orang Tua / Wali',
                'fields' => [
                    $this->makeField('nama_ayah', 'Nama Ayah'),
                    $this->makeField('nik_ayah', 'NIK Ayah'),
                    $this->makeField('tahun_lahir_ayah', 'Tahun Lahir Ayah', true, 'number'),
                    $this->makeField('pekerjaan_ayah', 'Pekerjaan Ayah'),
                    $this->makeField('pendidikan_ayah', 'Pendidikan Ayah'),
                    $this->makeField('penghasilan_ayah', 'Penghasilan Ayah'),
                    $this->makeField('no_hp_ayah', 'No HP Ayah'),
                    $this->makeField('nama_ibu', 'Nama Ibu'),
                    $this->makeField('nik_ibu', 'NIK Ibu'),
                    $this->makeField('tahun_lahir_ibu', 'Tahun Lahir Ibu', true, 'number'),
                    $this->makeField('pekerjaan_ibu', 'Pekerjaan Ibu'),
                    $this->makeField('pendidikan_ibu', 'Pendidikan Ibu'),
                    $this->makeField('penghasilan_ibu', 'Penghasilan Ibu'),
                    $this->makeField('no_hp_ibu', 'No HP Ibu'),
                    $this->makeField('wali_siswa', 'Nama Wali'),
                    $this->makeField('nama_wali', 'Nama Wali (Alternatif)'),
                    $this->makeField('hubungan_wali', 'Hubungan Wali'),
                    $this->makeField('nik_wali', 'NIK Wali'),
                    $this->makeField('tahun_lahir_wali', 'Tahun Lahir Wali', true, 'number'),
                    $this->makeField('pekerjaan_wali', 'Pekerjaan Wali'),
                    $this->makeField('pendidikan_wali', 'Pendidikan Wali'),
                    $this->makeField('penghasilan_wali', 'Penghasilan Wali'),
                    $this->makeField('no_hp_wali', 'No HP Wali'),
                    $this->makeField('alamat_wali', 'Alamat Wali', true, 'textarea'),
                ],
            ],
            [
                'key' => 'akademik',
                'label' => 'Akademik',
                'fields' => [
                    $this->makeField('asal_sekolah', 'Asal Sekolah'),
                    $this->makeField('npsn_asal', 'NPSN Asal'),
                    $this->makeField('alamat_sekolah_asal', 'Alamat Sekolah Asal', true, 'textarea'),
                    $this->makeField('tahun_lulus_asal', 'Tahun Lulus Asal', true, 'number'),
                    $this->makeField('tahun_lulus_sd', 'Tahun Lulus SD', true, 'number'),
                    $this->makeField('nilai_un_sd', 'Nilai UN SD', true, 'number'),
                    $this->makeField('no_peserta_ujian_nasional', 'No Peserta Ujian Nasional'),
                    $this->makeField('no_seri_ijazah', 'No Seri Ijazah'),
                    $this->makeField('active_class.nama_kelas', 'Kelas Aktif', false, 'text'),
                    $this->makeField('active_class.tahun_ajaran_nama', 'Tahun Ajaran Aktif', false, 'text'),
                    $this->makeField('active_class.status', 'Status Kelas', false, 'text'),
                ],
            ],
            [
                'key' => 'bantuan',
                'label' => 'Bantuan & Administrasi',
                'fields' => [
                    $this->makeField('penerima_kps', 'Penerima KPS', true, 'boolean'),
                    $this->makeField('no_kps', 'No KPS'),
                    $this->makeField('penerima_kip', 'Penerima KIP', true, 'boolean'),
                    $this->makeField('nomor_kip', 'Nomor KIP'),
                    $this->makeField('nama_di_kip', 'Nama Di KIP'),
                    $this->makeField('nomor_kks', 'Nomor KKS'),
                    $this->makeField('no_registrasi_akta_lahir', 'No Registrasi Akta Lahir'),
                    $this->makeField('bank', 'Bank'),
                    $this->makeField('nomor_rekening_bank', 'Nomor Rekening'),
                    $this->makeField('rekening_atas_nama', 'Rekening Atas Nama'),
                    $this->makeField('layak_pip', 'Layak PIP', true, 'boolean'),
                    $this->makeField('alasan_layak_pip', 'Alasan Layak PIP', true, 'textarea'),
                    $this->makeField('no_kk', 'Nomor KK'),
                    $this->makeField('skhun', 'SKHUN'),
                ],
            ],
            [
                'key' => 'kesehatan',
                'label' => 'Kesehatan',
                'fields' => [
                    $this->makeField('golongan_darah', 'Golongan Darah'),
                    $this->makeField('tinggi_badan', 'Tinggi Badan', true, 'number'),
                    $this->makeField('berat_badan', 'Berat Badan', true, 'number'),
                    $this->makeField('lingkar_kepala', 'Lingkar Kepala', true, 'number'),
                    $this->makeField('kebutuhan_khusus', 'Kebutuhan Khusus'),
                    $this->makeField('anak_ke', 'Anak Ke', true, 'number'),
                    $this->makeField('jumlah_saudara', 'Jumlah Saudara', true, 'number'),
                ],
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function employeeSchemaSections(): array
    {
        return [
            [
                'key' => 'identitas',
                'label' => 'Identitas',
                'fields' => [
                    $this->makeField('nama_lengkap', 'Nama Lengkap', true, 'text'),
                    $this->makeField('username', 'Username', false, 'text'),
                    $this->makeField('email', 'Email', true, 'email'),
                    $this->makeField('nip', 'NIP', false, 'text'),
                    $this->makeField('nik', 'NIK', false, 'text'),
                    $this->makeField('status_kepegawaian', 'Status Kepegawaian', false, 'text'),
                    $this->makeField('jenis_kelamin', 'Jenis Kelamin', false, 'text'),
                    $this->makeField('tempat_lahir', 'Tempat Lahir', false, 'text'),
                    $this->makeField('tanggal_lahir', 'Tanggal Lahir', false, 'date'),
                    $this->makeField('agama', 'Agama', true, 'text'),
                ],
            ],
            [
                'key' => 'kontak',
                'label' => 'Kontak',
                'fields' => [
                    $this->makeField('no_hp', 'No HP'),
                    $this->makeField('no_telepon_kantor', 'No Telepon Kantor'),
                    $this->makeField('email_notifikasi', 'Email Notifikasi', true, 'email'),
                ],
            ],
            [
                'key' => 'alamat',
                'label' => 'Alamat',
                'fields' => [
                    $this->makeField('alamat', 'Alamat', true, 'textarea'),
                    $this->makeField('alamat_jalan', 'Alamat Jalan', true, 'textarea'),
                    $this->makeField('rt', 'RT'),
                    $this->makeField('rw', 'RW'),
                    $this->makeField('nama_dusun', 'Dusun'),
                    $this->makeField('kelurahan', 'Kelurahan'),
                    $this->makeField('kecamatan', 'Kecamatan'),
                    $this->makeField('kota_kabupaten', 'Kota/Kabupaten'),
                    $this->makeField('provinsi', 'Provinsi'),
                    $this->makeField('kode_pos', 'Kode Pos'),
                    $this->makeField('alamat_domisili', 'Alamat Domisili', true, 'textarea'),
                    $this->makeField('lintang', 'Lintang', true, 'number'),
                    $this->makeField('bujur', 'Bujur', true, 'number'),
                ],
            ],
            [
                'key' => 'keluarga',
                'label' => 'Keluarga',
                'fields' => [
                    $this->makeField('nama_ibu_kandung', 'Nama Ibu Kandung'),
                    $this->makeField('nama_pasangan', 'Nama Pasangan'),
                    $this->makeField('status_pernikahan', 'Status Pernikahan'),
                    $this->makeField('status_perkawinan', 'Status Perkawinan'),
                    $this->makeField('nip_suami_istri', 'NIP Suami/Istri'),
                    $this->makeField('pekerjaan_pasangan', 'Pekerjaan Pasangan'),
                    $this->makeField('jumlah_anak', 'Jumlah Anak', true, 'number'),
                    $this->makeField('no_kk', 'Nomor KK'),
                    $this->makeField('data_anak', 'Data Anak', true, 'array'),
                ],
            ],
            [
                'key' => 'kepegawaian',
                'label' => 'Kepegawaian',
                'fields' => [
                    $this->makeField('nuptk', 'NUPTK', false, 'text'),
                    $this->makeField('jenis_ptk', 'Jenis PTK', false, 'text'),
                    $this->makeField('jabatan', 'Jabatan', false, 'text'),
                    $this->makeField('tugas_tambahan', 'Tugas Tambahan', false, 'text'),
                    $this->makeField('golongan', 'Golongan', false, 'text'),
                    $this->makeField('pangkat_golongan', 'Pangkat/Golongan', false, 'text'),
                    $this->makeField('nomor_sk', 'Nomor SK', false, 'text'),
                    $this->makeField('tanggal_sk', 'Tanggal SK', false, 'date'),
                    $this->makeField('sk_cpns', 'SK CPNS', false, 'text'),
                    $this->makeField('tanggal_cpns', 'Tanggal CPNS', false, 'date'),
                    $this->makeField('tmt_cpns', 'TMT CPNS', false, 'date'),
                    $this->makeField('sk_pengangkatan', 'SK Pengangkatan', false, 'text'),
                    $this->makeField('tmt_pengangkatan', 'TMT Pengangkatan', false, 'date'),
                    $this->makeField('tmt', 'TMT', false, 'date'),
                    $this->makeField('tmt_pns', 'TMT PNS', false, 'date'),
                    $this->makeField('sumber_gaji', 'Sumber Gaji', false, 'text'),
                    $this->makeField('lembaga_pengangkatan', 'Lembaga Pengangkatan', false, 'text'),
                ],
            ],
            [
                'key' => 'administrasi',
                'label' => 'Administrasi',
                'fields' => [
                    $this->makeField('npwp', 'NPWP'),
                    $this->makeField('nama_wajib_pajak', 'Nama Wajib Pajak'),
                    $this->makeField('kewarganegaraan', 'Kewarganegaraan'),
                    $this->makeField('bank', 'Bank'),
                    $this->makeField('nomor_rekening_bank', 'Nomor Rekening'),
                    $this->makeField('rekening_atas_nama', 'Rekening Atas Nama'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function makeField(string $key, string $label, bool $editable = true, ?string $type = null): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'type' => $type ?? $this->fieldType($key),
            'editable' => $editable,
        ];
    }

    private function buildPayload(User $user): array
    {
        $isStudent = $this->isStudent($user);

        $user->loadMissing([
            'dataPribadiSiswa',
            'dataKepegawaian',
            'kelas' => function ($query) {
                $query->with(['tingkat', 'tahunAjaran', 'waliKelas:id,nama_lengkap'])
                    ->withPivot('tahun_ajaran_id', 'status', 'tanggal_masuk', 'is_active');
            },
        ]);

        $common = [
            'id' => $user->id,
            'username' => $user->username,
            'nama_lengkap' => $user->nama_lengkap,
            'email' => $user->email,
            'nis' => $user->nis,
            'nisn' => $user->nisn,
            'nip' => $user->nip,
            'nik' => $user->nik,
            'status_kepegawaian' => $user->status_kepegawaian,
            'jenis_kelamin' => $user->jenis_kelamin,
            'tempat_lahir' => $user->tempat_lahir,
            'tanggal_lahir' => $user->tanggal_lahir,
            'agama' => $user->agama,
            'alamat' => $user->alamat,
            'rt' => $user->rt,
            'rw' => $user->rw,
            'kelurahan' => $user->kelurahan,
            'kecamatan' => $user->kecamatan,
            'kota_kabupaten' => $user->kota_kabupaten,
            'provinsi' => $user->provinsi,
            'kode_pos' => $user->kode_pos,
            'foto_profil' => $user->foto_profil,
            'foto_profil_url' => $user->foto_profil_url,
        ];

        $activeTemplate = null;
        if ($isStudent) {
            $activeTemplate = Schema::hasTable('user_face_templates')
                ? UserFaceTemplate::query()
                    ->where('user_id', $user->id)
                    ->where('is_active', true)
                    ->latest('enrolled_at')
                    ->latest('id')
                    ->first()
                : null;

            $common['has_active_face_template'] = $activeTemplate !== null;
            $common['face_template_enrolled_at'] = $activeTemplate?->enrolled_at?->toIso8601String();
        }

        $detailModel = $isStudent ? $user->dataPribadiSiswa : $user->dataKepegawaian;
        $detail = $detailModel
            ? Arr::except($detailModel->toArray(), ['id', 'user_id', 'created_at', 'updated_at', 'deleted_at'])
            : [];

        $activeClassPayload = null;
        if ($isStudent && $user->kelas && $user->kelas->isNotEmpty()) {
            $activeClass = $user->kelas->first(function ($kelas) {
                $pivot = $kelas->pivot;
                if (!$pivot) {
                    return false;
                }

                return ((string) ($pivot->status ?? '') === 'aktif') || ((bool) ($pivot->is_active ?? false));
            }) ?? $user->kelas->first();

            if ($activeClass) {
                $activeClassPayload = [
                    'id' => $activeClass->id,
                    'nama_kelas' => $activeClass->nama_kelas,
                    'tingkat' => $activeClass->tingkat->nama ?? null,
                    'wali_kelas_id' => $activeClass->waliKelas?->id,
                    'wali_kelas_nama' => $activeClass->waliKelas?->nama_lengkap,
                    'wali_kelas_nip' => $activeClass->waliKelas?->nip,
                    'tahun_ajaran_id' => $activeClass->pivot->tahun_ajaran_id ?? null,
                    'tahun_ajaran_nama' => $activeClass->tahunAjaran->nama ?? null,
                    'status' => $activeClass->pivot->status ?? null,
                    'tanggal_masuk' => $activeClass->pivot->tanggal_masuk ?? null,
                ];
            }
        }

        return [
            'profile_type' => $isStudent ? 'siswa' : 'pegawai',
            'common' => $common,
            'detail' => $detail,
            'active_class' => $activeClassPayload,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeBooleanPayload(array $payload): array
    {
        foreach (self::BOOLEAN_FIELDS as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $payload[$field] = $this->normalizeBooleanValue($payload[$field]);
        }

        return $payload;
    }

    private function normalizeBooleanValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int) $value) === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'ya', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'tidak', 'no', 'off'], true)) {
                return false;
            }
        }

        return $value;
    }

    private function fieldType(string $field): string
    {
        if (in_array($field, self::BOOLEAN_FIELDS, true)) {
            return 'boolean';
        }

        if (in_array($field, self::DATE_FIELDS, true)) {
            return 'date';
        }

        if (in_array($field, self::INTEGER_FIELDS, true) || in_array($field, self::NUMERIC_FIELDS, true)) {
            return 'number';
        }

        if (in_array($field, self::ARRAY_FIELDS, true)) {
            return 'array';
        }

        if (str_starts_with($field, 'email_') || $field === 'email' || $field === 'email_siswa') {
            return 'email';
        }

        if (str_contains($field, 'alamat')) {
            return 'textarea';
        }

        return 'text';
    }

    private function fieldLabel(string $field): string
    {
        return ucwords(str_replace('_', ' ', $field));
    }

    private function canViewPersonalDataVerification(User $user): bool
    {
        return $user->hasPermissionTo('view_personal_data_verification');
    }

    private function canVerifyPersonalDataByType(User $user, bool $isStudentTarget): bool
    {
        if ($isStudentTarget) {
            return $user->hasPermissionTo('verify_personal_data_siswa');
        }

        return $user->hasPermissionTo('verify_personal_data_pegawai');
    }

    private function resolveReviewQueueProfileTypeForActor(User $actor, string $requestedProfileType): string
    {
        $hasStudentVerificationAccess = $this->canVerifyPersonalDataByType($actor, true);
        $hasEmployeeVerificationAccess = $this->canVerifyPersonalDataByType($actor, false);

        if ($hasStudentVerificationAccess && !$hasEmployeeVerificationAccess) {
            return 'siswa';
        }

        if (!$hasStudentVerificationAccess && $hasEmployeeVerificationAccess) {
            return 'pegawai';
        }

        if (!in_array($requestedProfileType, ['all', 'siswa', 'pegawai'], true)) {
            return 'all';
        }

        return $requestedProfileType;
    }

    private function isSuperAdmin(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::SUPER_ADMIN));
    }

    private function isStudent(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::SISWA));
    }

    private function resolvePhotoUrl(?string $path): ?string
    {
        return User::resolveStoredPhotoUrl($path);
    }
}
