<?php

namespace App\Http\Controllers\Api;

use App\Exports\AkademikTableExport;
use App\Exports\MapelTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Models\Tingkat;
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

class MataPelajaranController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = MataPelajaran::with('tingkat');

            $countGuruAssignments = Schema::hasTable('guru_mata_pelajaran')
                && Schema::hasColumn('guru_mata_pelajaran', 'mata_pelajaran_id');
            $countJadwalMengajar = Schema::hasTable('jadwal_mengajar')
                && Schema::hasColumn('jadwal_mengajar', 'mata_pelajaran_id');

            if ($countGuruAssignments) {
                $query->withCount('guruAssignments');
            }
            if ($countJadwalMengajar) {
                $query->withCount('jadwalMengajar');
            }

            if ($request->filled('search')) {
                $keyword = trim((string) $request->search);
                $query->where(function ($builder) use ($keyword): void {
                    $builder->where('kode_mapel', 'ILIKE', '%' . $keyword . '%')
                        ->orWhere('nama_mapel', 'ILIKE', '%' . $keyword . '%')
                        ->orWhere('kelompok', 'ILIKE', '%' . $keyword . '%');
                });
            }

            if ($request->filled('tingkat_id')) {
                $query->where('tingkat_id', (int) $request->tingkat_id);
            }

            if ($request->has('is_active') && $request->input('is_active') !== '') {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            }

            $query->orderBy('kode_mapel')->orderBy('nama_mapel');

            if ($request->boolean('no_pagination')) {
                $rows = $query->get();
                $data = $rows->map(function (MataPelajaran $item): array {
                    return $this->transformItem($item);
                });

                return response()->json([
                    'success' => true,
                    'data' => $data,
                    'message' => 'Data mata pelajaran berhasil diambil',
                ]);
            }

            $perPage = (int) $request->get('per_page', 15);
            $perPage = $perPage > 0 ? min($perPage, 100) : 15;
            $rows = $query->paginate($perPage);
            $rows->getCollection()->transform(function (MataPelajaran $item): array {
                return $this->transformItem($item);
            });

            return response()->json([
                'success' => true,
                'data' => $rows,
                'message' => 'Data mata pelajaran berhasil diambil',
            ]);
        } catch (\Exception $exception) {
            Log::error('MataPelajaranController@index failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data mata pelajaran',
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'kode_mapel' => ['required', 'string', 'max:30', 'alpha_num', 'unique:mata_pelajaran,kode_mapel'],
            'nama_mapel' => ['required', 'string', 'max:150'],
            'kelompok' => ['nullable', 'string', 'max:80'],
            'tingkat_id' => ['nullable', 'exists:tingkat,id'],
            'is_active' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $mapel = DB::transaction(function () use ($request): MataPelajaran {
                return MataPelajaran::create([
                    'kode_mapel' => strtoupper(trim((string) $request->kode_mapel)),
                    'nama_mapel' => trim((string) $request->nama_mapel),
                    'kelompok' => $request->kelompok ? trim((string) $request->kelompok) : null,
                    'tingkat_id' => $request->tingkat_id ? (int) $request->tingkat_id : null,
                    'is_active' => $request->boolean('is_active', true),
                    'keterangan' => $request->keterangan ? trim((string) $request->keterangan) : null,
                ]);
            });

            return response()->json([
                'success' => true,
                'data' => $this->transformItem($mapel->load('tingkat')),
                'message' => 'Mata pelajaran berhasil dibuat',
            ], 201);
        } catch (\Exception $exception) {
            Log::error('MataPelajaranController@store failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat mata pelajaran',
            ], 500);
        }
    }

    public function show(int $id)
    {
        try {
            $mapel = MataPelajaran::with('tingkat')->find($id);
            if (!$mapel) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata pelajaran tidak ditemukan',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->transformItem($mapel),
                'message' => 'Detail mata pelajaran berhasil diambil',
            ]);
        } catch (\Exception $exception) {
            Log::error('MataPelajaranController@show failed', [
                'id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail mata pelajaran',
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $mapel = MataPelajaran::find($id);
        if (!$mapel) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'kode_mapel' => [
                'required',
                'string',
                'max:30',
                'alpha_num',
                Rule::unique('mata_pelajaran', 'kode_mapel')->ignore($mapel->id),
            ],
            'nama_mapel' => ['required', 'string', 'max:150'],
            'kelompok' => ['nullable', 'string', 'max:80'],
            'tingkat_id' => ['nullable', 'exists:tingkat,id'],
            'is_active' => ['nullable', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updated = DB::transaction(function () use ($request, $mapel): MataPelajaran {
                $mapel->update([
                    'kode_mapel' => strtoupper(trim((string) $request->kode_mapel)),
                    'nama_mapel' => trim((string) $request->nama_mapel),
                    'kelompok' => $request->kelompok ? trim((string) $request->kelompok) : null,
                    'tingkat_id' => $request->tingkat_id ? (int) $request->tingkat_id : null,
                    'is_active' => $request->boolean('is_active', true),
                    'keterangan' => $request->keterangan ? trim((string) $request->keterangan) : null,
                ]);

                return $mapel->refresh()->load('tingkat');
            });

            return response()->json([
                'success' => true,
                'data' => $this->transformItem($updated),
                'message' => 'Mata pelajaran berhasil diperbarui',
            ]);
        } catch (\Exception $exception) {
            Log::error('MataPelajaranController@update failed', [
                'id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui mata pelajaran',
            ], 500);
        }
    }

    public function destroy(int $id)
    {
        $mapel = MataPelajaran::find($id);
        if (!$mapel) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran tidak ditemukan',
            ], 404);
        }

        try {
            $hasAssignment = Schema::hasTable('guru_mata_pelajaran')
                && Schema::hasColumn('guru_mata_pelajaran', 'mata_pelajaran_id')
                && DB::table('guru_mata_pelajaran')->where('mata_pelajaran_id', $mapel->id)->exists();

            $hasSchedule = Schema::hasTable('jadwal_mengajar')
                && Schema::hasColumn('jadwal_mengajar', 'mata_pelajaran_id')
                && DB::table('jadwal_mengajar')->where('mata_pelajaran_id', $mapel->id)->exists();

            if ($hasAssignment || $hasSchedule) {
                return response()->json([
                    'success' => false,
                    'message' => 'Mata pelajaran masih dipakai di penugasan/jadwal dan tidak dapat dihapus',
                ], 422);
            }

            $mapel->delete();

            return response()->json([
                'success' => true,
                'message' => 'Mata pelajaran berhasil dihapus',
            ]);
        } catch (\Exception $exception) {
            Log::error('MataPelajaranController@destroy failed', [
                'id' => $id,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus mata pelajaran',
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        try {
            return Excel::download(new MapelTemplateExport(), 'Template_Import_Mata_Pelajaran.xlsx');
        } catch (\Exception $exception) {
            Log::error('MataPelajaranController@downloadTemplate failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunduh template import mata pelajaran',
            ], 500);
        }
    }

    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => ['nullable', Rule::in(['xlsx', 'pdf'])],
            'fields' => ['nullable'],
            'search' => ['nullable', 'string', 'max:255'],
            'kelompok' => ['nullable', 'string', 'max:80'],
            'tingkat_id' => ['nullable', 'integer', 'exists:tingkat,id'],
            'is_active' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter export tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $query = MataPelajaran::query()->with('tingkat');

            if (Schema::hasTable('guru_mata_pelajaran') && Schema::hasColumn('guru_mata_pelajaran', 'mata_pelajaran_id')) {
                $query->withCount('guruAssignments');
            }
            if (Schema::hasTable('jadwal_mengajar') && Schema::hasColumn('jadwal_mengajar', 'mata_pelajaran_id')) {
                $query->withCount('jadwalMengajar');
            }

            if ($request->filled('search')) {
                $keyword = trim((string) $request->search);
                $query->where(function ($builder) use ($keyword): void {
                    $builder->where('kode_mapel', 'ILIKE', '%' . $keyword . '%')
                        ->orWhere('nama_mapel', 'ILIKE', '%' . $keyword . '%')
                        ->orWhere('kelompok', 'ILIKE', '%' . $keyword . '%');
                });
            }

            if ($request->filled('kelompok')) {
                $query->whereRaw('LOWER(kelompok) = ?', [mb_strtolower(trim((string) $request->kelompok))]);
            }

            if ($request->filled('tingkat_id')) {
                $query->where('tingkat_id', (int) $request->tingkat_id);
            }

            if ($request->has('is_active') && $request->input('is_active') !== '') {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            }

            $rows = $query->orderBy('kode_mapel')->orderBy('nama_mapel')->get()->values()->map(
                function (MataPelajaran $item, int $index): array {
                    return [
                        'no' => $index + 1,
                        'kode_mapel' => $item->kode_mapel,
                        'nama_mapel' => $item->nama_mapel,
                        'kelompok' => $item->kelompok,
                        'tingkat' => $item->tingkat?->nama ?? 'Semua Tingkat',
                        'status' => $item->is_active ? 'Aktif' : 'Tidak Aktif',
                        'guru_assignments_count' => (int) ($item->guru_assignments_count ?? 0),
                        'jadwal_mengajar_count' => (int) ($item->jadwal_mengajar_count ?? 0),
                        'keterangan' => $item->keterangan,
                        'updated_at' => optional($item->updated_at)->format('Y-m-d H:i:s'),
                    ];
                }
            );

            $availableColumns = $this->mapelExportColumns();
            $selectedColumns = $this->resolveExportColumns($request->input('fields'), $availableColumns);
            $timestamp = now()->format('Ymd_His');
            $generatedBy = $request->user()?->nama_lengkap ?? $request->user()?->username ?? 'System';

            $meta = [
                'title' => 'Laporan Master Mata Pelajaran',
                'subtitle' => 'Sistem Manajemen Akademik',
                'sheet_title' => 'Master Mapel',
                'generated_by' => $generatedBy,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'filter_summary' => $this->buildMapelFilterSummary($request),
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

                return $pdf->download("Master_Mata_Pelajaran_{$timestamp}.pdf");
            }

            return Excel::download(
                new AkademikTableExport($rows, $selectedColumns, $meta),
                "Master_Mata_Pelajaran_{$timestamp}.xlsx"
            );
        } catch (\Exception $exception) {
            Log::error('MataPelajaranController@export failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data mata pelajaran',
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
                'kode_mapel' => ['kode_mapel', 'kode_mapel*', 'kode', 'kode mapel', 'kode_mapel_wajib'],
                'nama_mapel' => ['nama_mapel', 'nama_mapel*', 'nama mapel', 'mata_pelajaran', 'nama_mata_pelajaran'],
                'kelompok' => ['kelompok'],
                'tingkat' => ['tingkat', 'tingkat_id', 'tingkat_kode', 'tingkat_nama', 'tingkat_id_kode_nama'],
                'status' => ['status', 'status_aktif_tidak_aktif'],
                'keterangan' => ['keterangan', 'catatan', 'deskripsi'],
            ];

            [$headerIndex, $headerMap, $headerScore] = $this->detectImportHeaderRow($rows, $headerAliases);
            if ($headerIndex === null || $headerScore < 2 || !isset($headerMap['kode_mapel'], $headerMap['nama_mapel'])) {
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

                $kodeMapel = strtoupper($this->extractRowString($row, $headerMap['kode_mapel'] ?? null));
                $namaMapel = $this->extractRowString($row, $headerMap['nama_mapel'] ?? null);
                $kelompok = $this->extractRowString($row, $headerMap['kelompok'] ?? null);
                $tingkatRaw = $this->extractRowString($row, $headerMap['tingkat'] ?? null);
                $statusRaw = $this->extractRowString($row, $headerMap['status'] ?? null);
                $keterangan = $this->extractRowString($row, $headerMap['keterangan'] ?? null);

                if (
                    $kodeMapel === ''
                    && $namaMapel === ''
                    && $kelompok === ''
                    && $tingkatRaw === ''
                    && $statusRaw === ''
                    && $keterangan === ''
                ) {
                    continue;
                }

                $summary['total']++;

                if ($kodeMapel === '' || $namaMapel === '') {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: kode_mapel dan nama_mapel wajib diisi.";
                    continue;
                }

                $tingkatId = $this->resolveTingkatIdFromImport($tingkatRaw);
                if ($tingkatRaw !== '' && $tingkatId === null) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: tingkat '{$tingkatRaw}' tidak ditemukan.";
                    continue;
                }

                $isActive = $this->parseBooleanImportValue($statusRaw, true);
                if ($statusRaw !== '' && $isActive === null) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: status '{$statusRaw}' tidak valid (gunakan Aktif/Tidak Aktif).";
                    continue;
                }

                $payload = [
                    'kode_mapel' => $kodeMapel,
                    'nama_mapel' => $namaMapel,
                    'kelompok' => $kelompok !== '' ? $kelompok : null,
                    'tingkat_id' => $tingkatId,
                    'is_active' => $isActive ?? true,
                    'keterangan' => $keterangan !== '' ? $keterangan : null,
                ];

                $existing = MataPelajaran::query()
                    ->whereRaw('LOWER(kode_mapel) = ?', [mb_strtolower($kodeMapel)])
                    ->first();

                if ($existing && $importMode === 'create') {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: kode_mapel {$kodeMapel} sudah ada (mode create).";
                    continue;
                }

                if (!$existing && $importMode === 'update') {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: kode_mapel {$kodeMapel} tidak ditemukan (mode update).";
                    continue;
                }

                if ($existing) {
                    $existing->update($payload);
                    $summary['updated']++;
                } else {
                    MataPelajaran::create($payload);
                    $summary['imported']++;
                }
            }

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('MataPelajaranController@import failed', [
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import mata pelajaran',
                'data' => $summary,
            ], 500);
        }

        $successCount = $summary['imported'] + $summary['updated'];
        if ($successCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data mata pelajaran yang berhasil diimport',
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

    private function resolveTingkatIdFromImport(string $raw): ?int
    {
        if ($raw === '') {
            return null;
        }

        if (is_numeric($raw) && (int) $raw > 0) {
            $tingkat = Tingkat::query()->find((int) $raw);
            if ($tingkat) {
                return (int) $tingkat->id;
            }
        }

        $needle = mb_strtolower($raw);
        $tingkat = Tingkat::query()
            ->whereRaw('LOWER(kode) = ?', [$needle])
            ->orWhereRaw('LOWER(nama) = ?', [$needle])
            ->first();

        return $tingkat ? (int) $tingkat->id : null;
    }

    private function parseBooleanImportValue(string $value, ?bool $default = null): ?bool
    {
        if ($value === '') {
            return $default;
        }

        $normalized = mb_strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'aktif', 'active', 'ya', 'yes'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'tidak aktif', 'tidak_aktif', 'inactive', 'nonaktif', 'tidak', 'no'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @return array<int, array{key:string,label:string,width:int}>
     */
    private function mapelExportColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'width' => 7],
            ['key' => 'kode_mapel', 'label' => 'Kode Mapel', 'width' => 18],
            ['key' => 'nama_mapel', 'label' => 'Nama Mata Pelajaran', 'width' => 36],
            ['key' => 'kelompok', 'label' => 'Kelompok', 'width' => 20],
            ['key' => 'tingkat', 'label' => 'Tingkat', 'width' => 16],
            ['key' => 'status', 'label' => 'Status', 'width' => 14],
            ['key' => 'guru_assignments_count', 'label' => 'Penugasan Guru', 'width' => 18],
            ['key' => 'jadwal_mengajar_count', 'label' => 'Terpakai di Jadwal', 'width' => 20],
            ['key' => 'keterangan', 'label' => 'Keterangan', 'width' => 34],
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

    private function buildMapelFilterSummary(Request $request): string
    {
        $parts = [];

        if ($request->filled('search')) {
            $parts[] = 'Pencarian: ' . trim((string) $request->search);
        }
        if ($request->filled('kelompok')) {
            $parts[] = 'Kelompok: ' . trim((string) $request->kelompok);
        }
        if ($request->filled('tingkat_id')) {
            $tingkat = Tingkat::query()->find((int) $request->tingkat_id);
            $parts[] = 'Tingkat: ' . ($tingkat?->nama ?? ('#' . (int) $request->tingkat_id));
        }
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $parts[] = 'Status: ' . ($isActive ? 'Aktif' : 'Tidak Aktif');
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Semua data';
    }

    private function transformItem(MataPelajaran $item): array
    {
        return [
            'id' => $item->id,
            'kode_mapel' => $item->kode_mapel,
            'nama_mapel' => $item->nama_mapel,
            'kelompok' => $item->kelompok,
            'tingkat_id' => $item->tingkat_id,
            'tingkat' => $item->tingkat ? [
                'id' => $item->tingkat->id,
                'nama' => $item->tingkat->nama,
            ] : null,
            'is_active' => (bool) $item->is_active,
            'keterangan' => $item->keterangan,
            'guru_assignments_count' => $item->guru_assignments_count ?? 0,
            'jadwal_mengajar_count' => $item->jadwal_mengajar_count ?? 0,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }
}
