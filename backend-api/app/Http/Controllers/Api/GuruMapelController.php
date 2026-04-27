<?php

namespace App\Http\Controllers\Api;

use App\Exports\AkademikTableExport;
use App\Exports\GuruMapelTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalMengajar;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\RoleNames;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class GuruMapelController extends Controller
{
    public function index(Request $request)
    {
        try {
            $hasMapelId = $this->hasGuruMapelColumn('mata_pelajaran_id');
            $query = GuruMataPelajaran::query()
                ->with([
                    'guru:id,nama_lengkap,nip,email',
                    'kelas:id,nama_kelas,tingkat_id,tahun_ajaran_id',
                    'kelas.tingkat:id,nama,kode',
                    'tahunAjaran:id,nama,status',
                ]);

            if ($hasMapelId) {
                $query->with('mataPelajaran:id,kode_mapel,nama_mapel,tingkat_id');
            }

            if ($request->filled('search')) {
                $keyword = trim((string) $request->search);
                $query->where(function ($builder) use ($hasMapelId, $keyword): void {
                    $builder->whereHas('guru', function ($guruQuery) use ($keyword): void {
                        $guruQuery->where('nama_lengkap', 'ILIKE', '%' . $keyword . '%')
                            ->orWhere('nip', 'ILIKE', '%' . $keyword . '%')
                            ->orWhere('email', 'ILIKE', '%' . $keyword . '%');
                    })->orWhereHas('kelas', function ($kelasQuery) use ($keyword): void {
                        $kelasQuery->where('nama_kelas', 'ILIKE', '%' . $keyword . '%');
                    });

                    if ($hasMapelId) {
                        $builder->orWhereHas('mataPelajaran', function ($mapelQuery) use ($keyword): void {
                            $mapelQuery->where('kode_mapel', 'ILIKE', '%' . $keyword . '%')
                                ->orWhere('nama_mapel', 'ILIKE', '%' . $keyword . '%');
                        });
                    } elseif ($this->hasGuruMapelColumn('mata_pelajaran')) {
                        $builder->orWhere('mata_pelajaran', 'ILIKE', '%' . $keyword . '%');
                    }
                });
            }

            foreach (['guru_id', 'kelas_id', 'tahun_ajaran_id'] as $field) {
                if ($request->filled($field)) {
                    $query->where($field, (int) $request->input($field));
                }
            }

            if ($request->filled('status') && $this->hasGuruMapelColumn('status')) {
                $query->where('status', (string) $request->status);
            }

            if ($request->filled('mata_pelajaran_id')) {
                if ($hasMapelId) {
                    $query->where('mata_pelajaran_id', (int) $request->mata_pelajaran_id);
                } else {
                    $mapel = MataPelajaran::find((int) $request->mata_pelajaran_id);
                    if (!$mapel || !$this->hasGuruMapelColumn('mata_pelajaran')) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->where('mata_pelajaran', $mapel->nama_mapel);
                    }
                }
            }

            $query->orderByDesc('id');

            if ($request->boolean('no_pagination')) {
                return response()->json([
                    'success' => true,
                    'data' => $query->get()->map(fn (GuruMataPelajaran $item): array => $this->transformItem($item)),
                    'message' => 'Data penugasan guru-mapel berhasil diambil',
                ]);
            }

            $perPage = (int) $request->input('per_page', 15);
            $perPage = $perPage > 0 ? min($perPage, 100) : 15;
            $rows = $query->paginate($perPage);
            $rows->getCollection()->transform(fn (GuruMataPelajaran $item): array => $this->transformItem($item));

            return response()->json([
                'success' => true,
                'data' => $rows,
                'message' => 'Data penugasan guru-mapel berhasil diambil',
            ]);
        } catch (\Exception $exception) {
            Log::error('GuruMapelController@index failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data penugasan guru-mapel',
            ], 500);
        }
    }

    public function options(Request $request)
    {
        try {
            $tahunAjaranId = $request->filled('tahun_ajaran_id') ? (int) $request->tahun_ajaran_id : null;

            $guruRoleNames = RoleNames::flattenAliases([
                RoleNames::GURU,
                RoleNames::WALI_KELAS,
            ]);

            $guru = User::query()
                ->select(['id', 'nama_lengkap', 'nip', 'email'])
                ->where('is_active', true)
                ->whereHas('roles', function ($roleQuery) use ($guruRoleNames): void {
                    $roleQuery->whereIn('name', $guruRoleNames);
                })
                ->orderBy('nama_lengkap')
                ->get()
                ->map(static function (User $item): array {
                    return [
                        'id' => $item->id,
                        'nama_lengkap' => $item->nama_lengkap,
                        'nip' => $item->nip,
                        'email' => $item->email,
                    ];
                });

            $kelasQuery = Kelas::query()
                ->select(['id', 'nama_kelas', 'tingkat_id', 'tahun_ajaran_id'])
                ->with('tingkat:id,nama,kode')
                ->orderBy('tingkat_id')
                ->orderBy('nama_kelas');

            if ($tahunAjaranId) {
                $kelasQuery->where('tahun_ajaran_id', $tahunAjaranId);
            }

            $kelas = $kelasQuery->get()->map(static function (Kelas $item): array {
                return [
                    'id' => $item->id,
                    'nama_kelas' => $item->nama_kelas,
                    'tingkat_id' => $item->tingkat_id,
                    'tingkat_nama' => optional($item->tingkat)->nama,
                    'tahun_ajaran_id' => $item->tahun_ajaran_id,
                ];
            });

            $mapel = MataPelajaran::query()
                ->select(['id', 'kode_mapel', 'nama_mapel', 'tingkat_id'])
                ->where('is_active', true)
                ->orderBy('kode_mapel')
                ->orderBy('nama_mapel')
                ->get();

            $tahunAjaran = TahunAjaran::query()
                ->select(['id', 'nama', 'status', 'semester', 'is_active'])
                ->orderByDesc('is_active')
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'guru' => $guru,
                    'kelas' => $kelas,
                    'mata_pelajaran' => $mapel,
                    'tahun_ajaran' => $tahunAjaran,
                    'status_options' => [
                        ['value' => 'aktif', 'label' => 'Aktif'],
                        ['value' => 'tidak_aktif', 'label' => 'Tidak Aktif'],
                    ],
                ],
                'message' => 'Opsi penugasan guru-mapel berhasil diambil',
            ]);
        } catch (\Exception $exception) {
            Log::error('GuruMapelController@options failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data referensi penugasan guru-mapel',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        [$valid, $message, $meta] = $this->validateBusinessRules($request, null);
        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $meta,
            ], 422);
        }

        try {
            $assignment = DB::transaction(function () use ($request): GuruMataPelajaran {
                $data = $this->buildPayload($request);

                /** @var GuruMataPelajaran $created */
                $created = GuruMataPelajaran::create($data);
                return $created;
            });

            return response()->json([
                'success' => true,
                'data' => $this->transformItem($this->loadRelations($assignment)),
                'message' => 'Penugasan guru-mapel berhasil dibuat',
            ], 201);
        } catch (\Exception $exception) {
            Log::error('GuruMapelController@store failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat penugasan guru-mapel',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $assignment = GuruMataPelajaran::find($id);
        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Data penugasan tidak ditemukan',
            ], 404);
        }

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        [$valid, $message, $meta] = $this->validateBusinessRules($request, $assignment->id);
        if (!$valid) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $meta,
            ], 422);
        }

        try {
            $updated = DB::transaction(function () use ($assignment, $request): GuruMataPelajaran {
                $assignment->update($this->buildPayload($request));
                return $assignment->refresh();
            });

            return response()->json([
                'success' => true,
                'data' => $this->transformItem($this->loadRelations($updated)),
                'message' => 'Penugasan guru-mapel berhasil diperbarui',
            ]);
        } catch (\Exception $exception) {
            Log::error('GuruMapelController@update failed', [
                'id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui penugasan guru-mapel',
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        $assignment = GuruMataPelajaran::find($id);
        if (!$assignment) {
            return response()->json([
                'success' => false,
                'message' => 'Data penugasan tidak ditemukan',
            ], 404);
        }

        try {
            if ($this->isUsedBySchedule($assignment)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Penugasan masih dipakai di jadwal pelajaran dan tidak dapat dihapus',
                ], 422);
            }

            $assignment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Penugasan guru-mapel berhasil dihapus',
            ]);
        } catch (\Exception $exception) {
            Log::error('GuruMapelController@destroy failed', [
                'id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus penugasan guru-mapel',
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        try {
            return Excel::download(new GuruMapelTemplateExport(), 'Template_Import_Penugasan_Guru_Mapel.xlsx');
        } catch (\Exception $exception) {
            Log::error('GuruMapelController@downloadTemplate failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunduh template import penugasan guru-mapel',
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => ['nullable', Rule::in(['xlsx', 'pdf'])],
            'fields' => ['nullable'],
            'search' => ['nullable', 'string', 'max:255'],
            'guru_id' => ['nullable', 'integer', 'exists:users,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'tahun_ajaran_id' => ['nullable', 'integer', 'exists:tahun_ajaran,id'],
            'mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'status' => ['nullable', Rule::in(['aktif', 'tidak_aktif'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter export tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $hasMapelId = $this->hasGuruMapelColumn('mata_pelajaran_id');
            $query = GuruMataPelajaran::query()
                ->with([
                    'guru:id,nama_lengkap,nip,email',
                    'kelas:id,nama_kelas,tingkat_id,tahun_ajaran_id',
                    'kelas.tingkat:id,nama,kode',
                    'tahunAjaran:id,nama,status',
                ]);

            if ($hasMapelId) {
                $query->with('mataPelajaran:id,kode_mapel,nama_mapel,tingkat_id');
            }

            if ($request->filled('search')) {
                $keyword = trim((string) $request->search);
                $query->where(function ($builder) use ($hasMapelId, $keyword): void {
                    $builder->whereHas('guru', function ($guruQuery) use ($keyword): void {
                        $guruQuery->where('nama_lengkap', 'ILIKE', '%' . $keyword . '%')
                            ->orWhere('nip', 'ILIKE', '%' . $keyword . '%')
                            ->orWhere('email', 'ILIKE', '%' . $keyword . '%');
                    })->orWhereHas('kelas', function ($kelasQuery) use ($keyword): void {
                        $kelasQuery->where('nama_kelas', 'ILIKE', '%' . $keyword . '%');
                    });

                    if ($hasMapelId) {
                        $builder->orWhereHas('mataPelajaran', function ($mapelQuery) use ($keyword): void {
                            $mapelQuery->where('kode_mapel', 'ILIKE', '%' . $keyword . '%')
                                ->orWhere('nama_mapel', 'ILIKE', '%' . $keyword . '%');
                        });
                    } elseif ($this->hasGuruMapelColumn('mata_pelajaran')) {
                        $builder->orWhere('mata_pelajaran', 'ILIKE', '%' . $keyword . '%');
                    }
                });
            }

            foreach (['guru_id', 'kelas_id', 'tahun_ajaran_id'] as $field) {
                if ($request->filled($field)) {
                    $query->where($field, (int) $request->input($field));
                }
            }

            if ($request->filled('status') && $this->hasGuruMapelColumn('status')) {
                $query->where('status', (string) $request->status);
            }

            if ($request->filled('mata_pelajaran_id')) {
                if ($hasMapelId) {
                    $query->where('mata_pelajaran_id', (int) $request->mata_pelajaran_id);
                } else {
                    $mapel = MataPelajaran::find((int) $request->mata_pelajaran_id);
                    if (!$mapel || !$this->hasGuruMapelColumn('mata_pelajaran')) {
                        $query->whereRaw('1 = 0');
                    } else {
                        $query->where('mata_pelajaran', $mapel->nama_mapel);
                    }
                }
            }

            $rows = $query->orderByDesc('id')->get()->values()->map(
                function (GuruMataPelajaran $item, int $index): array {
                    return [
                        'no' => $index + 1,
                        'guru_nama' => $item->guru?->nama_lengkap,
                        'guru_nip' => $item->guru?->nip,
                        'guru_email' => $item->guru?->email,
                        'mapel_kode' => $item->mataPelajaran?->kode_mapel,
                        'mapel_nama' => $item->mataPelajaran?->nama_mapel ?? $item->mata_pelajaran,
                        'kelas' => $item->kelas?->nama_kelas,
                        'tingkat' => $item->kelas?->tingkat?->nama,
                        'tahun_ajaran' => $item->tahunAjaran?->nama,
                        'jam_per_minggu' => (int) ($item->jam_per_minggu ?? 0),
                        'status' => $item->status === 'aktif' ? 'Aktif' : 'Tidak Aktif',
                        'updated_at' => optional($item->updated_at)->format('Y-m-d H:i:s'),
                    ];
                }
            );

            $availableColumns = $this->guruMapelExportColumns();
            $selectedColumns = $this->resolveExportColumns($request->input('fields'), $availableColumns);
            $timestamp = now()->format('Ymd_His');
            $generatedBy = $request->user()?->nama_lengkap ?? $request->user()?->username ?? 'System';

            $meta = [
                'title' => 'Laporan Penugasan Guru-Mapel',
                'subtitle' => 'Sistem Manajemen Akademik',
                'sheet_title' => 'Penugasan Guru',
                'generated_by' => $generatedBy,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'filter_summary' => $this->buildGuruMapelFilterSummary($request),
            ];

            $format = (string) $request->input('format', 'xlsx');
            if ($format === 'pdf') {
                $pdf = Pdf::loadView('exports.akademik-table', [
                    'title' => $meta['title'],
                    'subtitle' => $meta['subtitle'],
                    'generatedBy' => $meta['generated_by'],
                    'generatedAt' => $meta['generated_at'],
                    'filterSummary' => $meta['filter_summary'],
                    'columns' => $selectedColumns,
                    'rows' => $rows->all(),
                ])->setPaper('a4', 'landscape');

                return $pdf->download("Penugasan_Guru_Mapel_{$timestamp}.pdf");
            }

            return Excel::download(
                new AkademikTableExport($rows, $selectedColumns, $meta),
                "Penugasan_Guru_Mapel_{$timestamp}.xlsx"
            );
        } catch (\Exception $exception) {
            Log::error('GuruMapelController@export failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data penugasan guru-mapel',
            ], 500);
        }
    }

    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
            'import_mode' => ['nullable', Rule::in(['auto', 'create', 'update'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File import tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $importMode = (string) $request->input('import_mode', 'auto');
        $summary = [
            'total' => 0,
            'imported' => 0,
            'updated' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        try {
            $sheets = Excel::toArray([], $request->file('file'));
            $rows = Arr::first($sheets, []);

            if (!is_array($rows) || count($rows) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'File import kosong atau tidak memiliki data',
                    'data' => $summary,
                ], 422);
            }

            $headerAliases = [
                'guru_ref' => ['guru_nip_email_username', 'guru_nip_email_username*', 'guru', 'guru_ref'],
                'mapel_ref' => ['mata_pelajaran_kode_nama', 'mata_pelajaran_kode_nama*', 'mata_pelajaran', 'mapel', 'mapel_ref'],
                'kelas_ref' => ['kelas', 'kelas*', 'kelas_ref'],
                'tahun_ajaran_ref' => ['tahun_ajaran', 'tahun_ajaran*', 'tahun_ajaran_ref'],
                'jam_per_minggu' => ['jam_per_minggu', 'jam_per_minggu_jp', 'jp_per_minggu'],
                'status' => ['status', 'status_aktif_tidak_aktif'],
                'guru_id' => ['guru_id'],
                'mata_pelajaran_id' => ['mata_pelajaran_id', 'mapel_id'],
                'kelas_id' => ['kelas_id'],
                'tahun_ajaran_id' => ['tahun_ajaran_id'],
            ];

            [$headerIndex, $headerMap, $headerScore] = $this->detectImportHeaderRow($rows, $headerAliases);
            if (
                $headerIndex === null
                || $headerScore < 4
                || !isset($headerMap['guru_ref'], $headerMap['mapel_ref'], $headerMap['kelas_ref'], $headerMap['tahun_ajaran_ref'])
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Header template import tidak dikenali. Gunakan template resmi.',
                    'data' => $summary,
                ], 422);
            }

            DB::beginTransaction();

            foreach ($rows as $rowIndex => $rowValues) {
                if ($rowIndex <= $headerIndex) {
                    continue;
                }

                $excelRow = $rowIndex + 1;
                $row = is_array($rowValues) ? array_values($rowValues) : [];

                $guruRef = $this->extractRowString($row, $headerMap['guru_ref'] ?? null);
                $mapelRef = $this->extractRowString($row, $headerMap['mapel_ref'] ?? null);
                $kelasRef = $this->extractRowString($row, $headerMap['kelas_ref'] ?? null);
                $tahunAjaranRef = $this->extractRowString($row, $headerMap['tahun_ajaran_ref'] ?? null);
                $jamPerMingguRaw = $this->extractRowString($row, $headerMap['jam_per_minggu'] ?? null);
                $statusRaw = $this->extractRowString($row, $headerMap['status'] ?? null);

                $guruIdRaw = $this->extractRowString($row, $headerMap['guru_id'] ?? null);
                $mapelIdRaw = $this->extractRowString($row, $headerMap['mata_pelajaran_id'] ?? null);
                $kelasIdRaw = $this->extractRowString($row, $headerMap['kelas_id'] ?? null);
                $tahunAjaranIdRaw = $this->extractRowString($row, $headerMap['tahun_ajaran_id'] ?? null);

                if (
                    $guruRef === ''
                    && $mapelRef === ''
                    && $kelasRef === ''
                    && $tahunAjaranRef === ''
                    && $guruIdRaw === ''
                    && $mapelIdRaw === ''
                    && $kelasIdRaw === ''
                    && $tahunAjaranIdRaw === ''
                    && $jamPerMingguRaw === ''
                    && $statusRaw === ''
                ) {
                    continue;
                }

                $summary['total']++;

                $guru = $this->resolveGuruFromImport($guruIdRaw, $guruRef);
                if (!$guru) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: guru '{$guruRef}' tidak ditemukan.";
                    continue;
                }

                $mapel = $this->resolveMapelFromImport($mapelIdRaw, $mapelRef);
                if (!$mapel) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: mapel '{$mapelRef}' tidak ditemukan.";
                    continue;
                }

                $kelas = $this->resolveKelasFromImport($kelasIdRaw, $kelasRef);
                if (!$kelas) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: kelas '{$kelasRef}' tidak ditemukan.";
                    continue;
                }

                $tahunAjaran = $this->resolveTahunAjaranFromImport($tahunAjaranIdRaw, $tahunAjaranRef);
                if (!$tahunAjaran) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: tahun ajaran '{$tahunAjaranRef}' tidak ditemukan.";
                    continue;
                }

                $statusNormalized = $this->normalizeAssignmentStatus($statusRaw);
                if ($statusRaw !== '' && $statusNormalized === null) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: status '{$statusRaw}' tidak valid (gunakan aktif/tidak_aktif).";
                    continue;
                }

                $payload = [
                    'guru_id' => (int) $guru->id,
                    'mata_pelajaran_id' => (int) $mapel->id,
                    'kelas_id' => (int) $kelas->id,
                    'tahun_ajaran_id' => (int) $tahunAjaran->id,
                    'jam_per_minggu' => $jamPerMingguRaw !== '' ? (int) $jamPerMingguRaw : 0,
                    'status' => $statusNormalized ?? 'aktif',
                ];

                $rowRequest = Request::create('/api/guru-mapel/import', 'POST', $payload);
                $rowValidator = $this->validator($rowRequest);
                if ($rowValidator->fails()) {
                    $summary['failed']++;
                    $summary['errors'][] = 'Baris ' . $excelRow . ': ' . implode(', ', $rowValidator->errors()->all());
                    continue;
                }

                $existing = $this->findExistingAssignment($payload);

                if ($existing && $importMode === 'create') {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: penugasan sudah ada (mode create).";
                    continue;
                }
                if (!$existing && $importMode === 'update') {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: penugasan tidak ditemukan (mode update).";
                    continue;
                }

                [$valid, $message, $meta] = $this->validateBusinessRules($rowRequest, $existing?->id);
                if (!$valid) {
                    $summary['failed']++;
                    $metaMessage = collect($meta)->flatten()->implode(', ');
                    $summary['errors'][] = "Baris {$excelRow}: {$message}" . ($metaMessage !== '' ? " ({$metaMessage})" : '');
                    continue;
                }

                if ($existing) {
                    $existing->update($this->buildPayload($rowRequest));
                    $summary['updated']++;
                } else {
                    GuruMataPelajaran::create($this->buildPayload($rowRequest));
                    $summary['imported']++;
                }
            }

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('GuruMapelController@import failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import penugasan guru-mapel',
                'data' => $summary,
            ], 500);
        }

        $successCount = $summary['imported'] + $summary['updated'];
        if ($successCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data penugasan yang berhasil diimport',
                'data' => $summary,
            ], 422);
        }

        $message = "Import selesai: {$summary['imported']} ditambahkan, {$summary['updated']} diperbarui.";
        if ($summary['failed'] > 0) {
            $message .= " {$summary['failed']} baris gagal.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $summary,
        ]);
    }

    private function validator(Request $request)
    {
        return Validator::make($request->all(), [
            'guru_id' => ['required', 'exists:users,id'],
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'kelas_id' => ['required', 'exists:kelas,id'],
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajaran,id'],
            'jam_per_minggu' => ['nullable', 'integer', 'min:0', 'max:60'],
            'status' => ['nullable', Rule::in(['aktif', 'tidak_aktif'])],
        ]);
    }

    /**
     * @return array{0:bool,1:string,2:array<string,mixed>}
     */
    private function validateBusinessRules(Request $request, ?int $ignoreId): array
    {
        /** @var User|null $guru */
        $guru = User::find((int) $request->guru_id);
        if (!$guru) {
            return [false, 'Guru tidak ditemukan', []];
        }

        $guruAliases = RoleNames::flattenAliases([
            RoleNames::GURU,
            RoleNames::WALI_KELAS,
        ]);

        if (!$guru->hasRole($guruAliases)) {
            return [false, 'User yang dipilih bukan guru/wali kelas', ['guru_id' => ['User tidak memiliki role guru/wali kelas']]];
        }

        /** @var Kelas|null $kelas */
        $kelas = Kelas::find((int) $request->kelas_id);
        /** @var MataPelajaran|null $mapel */
        $mapel = MataPelajaran::find((int) $request->mata_pelajaran_id);
        if (!$kelas || !$mapel) {
            return [false, 'Data kelas atau mata pelajaran tidak valid', []];
        }

        if ((int) $kelas->tahun_ajaran_id !== (int) $request->tahun_ajaran_id) {
            return [false, 'Kelas tidak berada pada tahun ajaran yang dipilih', ['tahun_ajaran_id' => ['Tahun ajaran kelas tidak sesuai']]];
        }

        if ($mapel->tingkat_id && (int) $mapel->tingkat_id !== (int) $kelas->tingkat_id) {
            return [false, 'Mata pelajaran tidak sesuai dengan tingkat kelas', ['mata_pelajaran_id' => ['Mapel tidak sesuai tingkat kelas']]];
        }

        $duplicateQuery = GuruMataPelajaran::query()
            ->where('guru_id', (int) $request->guru_id)
            ->where('kelas_id', (int) $request->kelas_id)
            ->where('tahun_ajaran_id', (int) $request->tahun_ajaran_id);

        if ($this->hasGuruMapelColumn('mata_pelajaran_id')) {
            $duplicateQuery->where('mata_pelajaran_id', (int) $request->mata_pelajaran_id);
        } elseif ($this->hasGuruMapelColumn('mata_pelajaran')) {
            $duplicateQuery->where('mata_pelajaran', $mapel->nama_mapel);
        }

        if ($ignoreId) {
            $duplicateQuery->where('id', '!=', $ignoreId);
        }

        if ($duplicateQuery->exists()) {
            return [false, 'Penugasan guru-mapel untuk kombinasi ini sudah ada', [
                'duplicate' => ['Guru, mapel, kelas, dan tahun ajaran sudah terdaftar'],
            ]];
        }

        return [true, '', []];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Request $request): array
    {
        /** @var MataPelajaran|null $mapel */
        $mapel = MataPelajaran::find((int) $request->mata_pelajaran_id);

        $data = [
            'guru_id' => (int) $request->guru_id,
            'kelas_id' => (int) $request->kelas_id,
            'tahun_ajaran_id' => (int) $request->tahun_ajaran_id,
            'jam_per_minggu' => (int) ($request->input('jam_per_minggu', 0)),
            'status' => (string) $request->input('status', 'aktif'),
        ];

        if ($this->hasGuruMapelColumn('mata_pelajaran_id')) {
            $data['mata_pelajaran_id'] = (int) $request->mata_pelajaran_id;
        }

        if ($this->hasGuruMapelColumn('mata_pelajaran')) {
            $data['mata_pelajaran'] = $mapel?->nama_mapel;
        }

        return $data;
    }

    private function loadRelations(GuruMataPelajaran $item): GuruMataPelajaran
    {
        $relations = [
            'guru:id,nama_lengkap,nip,email',
            'kelas:id,nama_kelas,tingkat_id,tahun_ajaran_id',
            'kelas.tingkat:id,nama,kode',
            'tahunAjaran:id,nama,status',
        ];

        if ($this->hasGuruMapelColumn('mata_pelajaran_id')) {
            $relations[] = 'mataPelajaran:id,kode_mapel,nama_mapel,tingkat_id';
        }

        return $item->load($relations);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformItem(GuruMataPelajaran $item): array
    {
        $mapelName = $item->mataPelajaran?->nama_mapel ?? $item->mata_pelajaran;
        $mapelCode = $item->mataPelajaran?->kode_mapel;

        return [
            'id' => $item->id,
            'guru_id' => $item->guru_id,
            'guru' => $item->guru ? [
                'id' => $item->guru->id,
                'nama_lengkap' => $item->guru->nama_lengkap,
                'nip' => $item->guru->nip,
                'email' => $item->guru->email,
            ] : null,
            'mata_pelajaran_id' => $item->mata_pelajaran_id,
            'mata_pelajaran' => [
                'id' => $item->mata_pelajaran_id,
                'kode_mapel' => $mapelCode,
                'nama_mapel' => $mapelName,
            ],
            'kelas_id' => $item->kelas_id,
            'kelas' => $item->kelas ? [
                'id' => $item->kelas->id,
                'nama_kelas' => $item->kelas->nama_kelas,
                'tingkat_id' => $item->kelas->tingkat_id,
                'tingkat_nama' => optional($item->kelas->tingkat)->nama,
            ] : null,
            'tahun_ajaran_id' => $item->tahun_ajaran_id,
            'tahun_ajaran' => $item->tahunAjaran ? [
                'id' => $item->tahunAjaran->id,
                'nama' => $item->tahunAjaran->nama,
                'status' => $item->tahunAjaran->status,
            ] : null,
            'jam_per_minggu' => (int) ($item->jam_per_minggu ?? 0),
            'status' => $item->status,
            'status_label' => $item->status === 'aktif' ? 'Aktif' : 'Tidak Aktif',
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }

    private function isUsedBySchedule(GuruMataPelajaran $assignment): bool
    {
        if (!Schema::hasTable('jadwal_mengajar')) {
            return false;
        }

        $query = JadwalMengajar::query()
            ->where('guru_id', $assignment->guru_id)
            ->where('kelas_id', $assignment->kelas_id);

        if (Schema::hasColumn('jadwal_mengajar', 'tahun_ajaran_id') && $assignment->tahun_ajaran_id) {
            $query->where('tahun_ajaran_id', $assignment->tahun_ajaran_id);
        }

        if (Schema::hasColumn('jadwal_mengajar', 'mata_pelajaran_id') && $assignment->mata_pelajaran_id) {
            $query->where('mata_pelajaran_id', $assignment->mata_pelajaran_id);
        } elseif (Schema::hasColumn('jadwal_mengajar', 'mata_pelajaran') && $assignment->mata_pelajaran) {
            $query->where('mata_pelajaran', $assignment->mata_pelajaran);
        }

        if (Schema::hasColumn('jadwal_mengajar', 'status')) {
            $query->where('status', '!=', 'archived');
        }

        if (Schema::hasColumn('jadwal_mengajar', 'is_active')) {
            $query->where('is_active', true);
        }

        return $query->exists();
    }

    /**
     * @param array<int, array<int, mixed>> $rows
     * @param array<string, array<int, string>> $aliases
     * @return array{0:int|null,1:array<string,int>,2:int}
     */
    private function detectImportHeaderRow(array $rows, array $aliases): array
    {
        $bestIndex = null;
        $bestMap = [];
        $bestScore = 0;
        $normalizedAliases = [];

        foreach ($aliases as $key => $values) {
            $normalizedAliases[$key] = array_map(
                fn ($alias): string => $this->normalizeImportHeader($alias),
                $values
            );
        }

        foreach ($rows as $rowIndex => $rowValues) {
            if ($rowIndex > 25 || !is_array($rowValues)) {
                continue;
            }

            $normalizedRow = array_map(
                fn ($value): string => $this->normalizeImportHeader(is_scalar($value) ? (string) $value : ''),
                array_values($rowValues)
            );

            $rowMap = [];
            $score = 0;

            foreach ($normalizedAliases as $key => $values) {
                foreach ($values as $alias) {
                    $columnIndex = array_search($alias, $normalizedRow, true);
                    if ($columnIndex !== false) {
                        $rowMap[$key] = (int) $columnIndex;
                        $score++;
                        break;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestIndex = $rowIndex;
                $bestMap = $rowMap;
                $bestScore = $score;
            }
        }

        return [$bestIndex, $bestMap, $bestScore];
    }

    private function normalizeImportHeader(string $value): string
    {
        $ascii = Str::ascii(mb_strtolower(trim($value)));
        $normalized = preg_replace('/[^a-z0-9]+/i', '_', $ascii);

        return trim((string) $normalized, '_');
    }

    private function extractRowString(array $row, ?int $columnIndex): string
    {
        if ($columnIndex === null || !array_key_exists($columnIndex, $row)) {
            return '';
        }

        $value = $row[$columnIndex];
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }

    private function resolveGuruFromImport(string $idRaw, string $ref): ?User
    {
        if ($idRaw !== '' && is_numeric($idRaw)) {
            $user = User::query()->find((int) $idRaw);
            if ($user) {
                return $user;
            }
        }

        if ($ref === '') {
            return null;
        }

        $needle = mb_strtolower($ref);

        return User::query()
            ->whereRaw('LOWER(nip) = ?', [$needle])
            ->orWhereRaw('LOWER(email) = ?', [$needle])
            ->orWhereRaw('LOWER(username) = ?', [$needle])
            ->orWhereRaw('LOWER(nama_lengkap) = ?', [$needle])
            ->first();
    }

    private function resolveMapelFromImport(string $idRaw, string $ref): ?MataPelajaran
    {
        if ($idRaw !== '' && is_numeric($idRaw)) {
            $mapel = MataPelajaran::query()->find((int) $idRaw);
            if ($mapel) {
                return $mapel;
            }
        }

        if ($ref === '') {
            return null;
        }

        $needle = mb_strtolower($ref);

        return MataPelajaran::query()
            ->whereRaw('LOWER(kode_mapel) = ?', [$needle])
            ->orWhereRaw('LOWER(nama_mapel) = ?', [$needle])
            ->first();
    }

    private function resolveKelasFromImport(string $idRaw, string $ref): ?Kelas
    {
        if ($idRaw !== '' && is_numeric($idRaw)) {
            $kelas = Kelas::query()->find((int) $idRaw);
            if ($kelas) {
                return $kelas;
            }
        }

        if ($ref === '') {
            return null;
        }

        return Kelas::query()
            ->whereRaw('LOWER(nama_kelas) = ?', [mb_strtolower($ref)])
            ->first();
    }

    private function resolveTahunAjaranFromImport(string $idRaw, string $ref): ?TahunAjaran
    {
        if ($idRaw !== '' && is_numeric($idRaw)) {
            $tahun = TahunAjaran::query()->find((int) $idRaw);
            if ($tahun) {
                return $tahun;
            }
        }

        if ($ref === '') {
            return null;
        }

        return TahunAjaran::query()
            ->whereRaw('LOWER(nama) = ?', [mb_strtolower($ref)])
            ->first();
    }

    private function normalizeAssignmentStatus(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        if (in_array($normalized, ['aktif', 'active', '1', 'true', 'ya', 'yes'], true)) {
            return 'aktif';
        }
        if (in_array($normalized, ['tidak_aktif', 'tidak aktif', 'inactive', '0', 'false', 'no', 'tidak'], true)) {
            return 'tidak_aktif';
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function findExistingAssignment(array $payload): ?GuruMataPelajaran
    {
        $query = GuruMataPelajaran::query()
            ->where('guru_id', (int) $payload['guru_id'])
            ->where('kelas_id', (int) $payload['kelas_id'])
            ->where('tahun_ajaran_id', (int) $payload['tahun_ajaran_id']);

        if ($this->hasGuruMapelColumn('mata_pelajaran_id')) {
            $query->where('mata_pelajaran_id', (int) $payload['mata_pelajaran_id']);
        } elseif ($this->hasGuruMapelColumn('mata_pelajaran')) {
            $mapel = MataPelajaran::query()->find((int) $payload['mata_pelajaran_id']);
            if (!$mapel) {
                return null;
            }
            $query->where('mata_pelajaran', $mapel->nama_mapel);
        }

        return $query->first();
    }

    private function hasGuruMapelColumn(string $column): bool
    {
        return Schema::hasColumn('guru_mata_pelajaran', $column);
    }

    /**
     * @return array<int, array{key:string,label:string,width:int}>
     */
    private function guruMapelExportColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'width' => 7],
            ['key' => 'guru_nama', 'label' => 'Nama Guru', 'width' => 28],
            ['key' => 'guru_nip', 'label' => 'NIP', 'width' => 22],
            ['key' => 'guru_email', 'label' => 'Email', 'width' => 30],
            ['key' => 'mapel_kode', 'label' => 'Kode Mapel', 'width' => 16],
            ['key' => 'mapel_nama', 'label' => 'Mata Pelajaran', 'width' => 28],
            ['key' => 'kelas', 'label' => 'Kelas', 'width' => 14],
            ['key' => 'tingkat', 'label' => 'Tingkat', 'width' => 14],
            ['key' => 'tahun_ajaran', 'label' => 'Tahun Ajaran', 'width' => 18],
            ['key' => 'jam_per_minggu', 'label' => 'Jam/Minggu', 'width' => 13],
            ['key' => 'status', 'label' => 'Status', 'width' => 14],
            ['key' => 'updated_at', 'label' => 'Terakhir Diubah', 'width' => 21],
        ];
    }

    /**
     * @param mixed $rawFields
     * @param array<int, array{key:string,label:string,width:int}> $availableColumns
     * @return array<int, array{key:string,label:string,width:int}>
     */
    private function resolveExportColumns($rawFields, array $availableColumns): array
    {
        $requestedFields = [];
        if (is_string($rawFields)) {
            $requestedFields = array_values(array_filter(array_map('trim', explode(',', $rawFields))));
        } elseif (is_array($rawFields)) {
            $requestedFields = array_values(array_filter(array_map(
                static fn ($item): string => trim((string) $item),
                $rawFields
            )));
        }

        if ($requestedFields === []) {
            return $availableColumns;
        }

        $allowedLookup = collect($availableColumns)->keyBy('key');
        $selected = [];
        foreach ($requestedFields as $field) {
            if ($allowedLookup->has($field)) {
                $selected[] = $allowedLookup->get($field);
            }
        }

        return $selected !== [] ? $selected : $availableColumns;
    }

    private function buildGuruMapelFilterSummary(Request $request): string
    {
        $parts = [];

        if ($request->filled('search')) {
            $parts[] = 'Pencarian: ' . trim((string) $request->search);
        }
        if ($request->filled('tahun_ajaran_id')) {
            $tahun = TahunAjaran::query()->find((int) $request->tahun_ajaran_id);
            $parts[] = 'Tahun Ajaran: ' . ($tahun?->nama ?? ('#' . (int) $request->tahun_ajaran_id));
        }
        if ($request->filled('kelas_id')) {
            $kelas = Kelas::query()->find((int) $request->kelas_id);
            $parts[] = 'Kelas: ' . ($kelas?->nama_kelas ?? ('#' . (int) $request->kelas_id));
        }
        if ($request->filled('mata_pelajaran_id')) {
            $mapel = MataPelajaran::query()->find((int) $request->mata_pelajaran_id);
            $parts[] = 'Mapel: ' . ($mapel?->nama_mapel ?? ('#' . (int) $request->mata_pelajaran_id));
        }
        if ($request->filled('status')) {
            $parts[] = 'Status: ' . ((string) $request->status === 'aktif' ? 'Aktif' : 'Tidak Aktif');
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Semua data';
    }
}
