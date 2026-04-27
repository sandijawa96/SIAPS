<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\Request;
use App\Helpers\AuthHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\KelasExport;
use App\Exports\KelasNaikKelasTemplateExport;
use App\Exports\KelasTemplateExport;
use App\Imports\KelasImport;
use App\Imports\KelasNaikKelasImport;
use App\Services\WaliKelasRoleService;
use App\Support\RoleDataScope;
use App\Support\RoleNames;

class KelasController extends Controller
{
    public function downloadTemplate()
    {
        try {
            return Excel::download(new KelasTemplateExport, 'template-import-kelas.xlsx');
        } catch (\Exception $e) {
            Log::error('Error downloading template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunduh template'
            ], 500);
        }
    }

    public function downloadNaikKelasTemplate()
    {
        try {
            return Excel::download(new KelasNaikKelasTemplateExport, 'template-import-siswa-baru-naik-kelas.xlsx');
        } catch (\Exception $e) {
            Log::error('Error downloading naik kelas template: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunduh template naik kelas'
            ], 500);
        }
    }

    public function import(Request $request)
    {
        try {
            // Validate request
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls|max:5120', // Max 5MB
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak valid',
                    'errors' => $validator->errors()->all(),
                    'summary' => [
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => $validator->errors()->all(),
                        'total_processed' => 0
                    ]
                ], 422);
            }

            // Check file size
            $file = $request->file('file');
            if ($file->getSize() > 5 * 1024 * 1024) { // 5MB
                return response()->json([
                    'success' => false,
                    'message' => 'Ukuran file terlalu besar (maksimal 5MB)',
                    'summary' => [
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => ['Ukuran file terlalu besar (maksimal 5MB)'],
                        'total_processed' => 0
                    ]
                ], 422);
            }

            // Check if file is readable
            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak dapat dibaca atau rusak',
                    'summary' => [
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => ['File tidak dapat dibaca atau rusak'],
                        'total_processed' => 0
                    ]
                ], 422);
            }

            // Perform import
            $import = new KelasImport;

            try {
                Excel::import($import, $file);
            } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                $failures = $e->failures();
                $errors = [];
                foreach ($failures as $failure) {
                    $errors[] = "Baris {$failure->row()}: " . implode(', ', $failure->errors());
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Validasi data gagal',
                    'summary' => [
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => $errors,
                        'total_processed' => 0
                    ]
                ], 422);
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format file tidak didukung atau file rusak',
                    'summary' => [
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => ['Format file tidak didukung atau file rusak'],
                        'total_processed' => 0
                    ]
                ], 422);
            }

            $summary = $import->getSummary();

            // Check if any data was processed
            if ($summary['total_processed'] === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data yang dapat diproses. Pastikan file menggunakan template yang benar.',
                    'summary' => $summary
                ], 422);
            }

            // Return response based on results
            if ($import->hasErrors()) {
                return response()->json([
                    'success' => false,
                    'message' => $summary['imported'] > 0
                        ? 'Import selesai dengan beberapa error'
                        : 'Import gagal, tidak ada data yang berhasil diimport',
                    'summary' => $summary
                ], $summary['imported'] > 0 ? 200 : 422);
            }

            return response()->json([
                'success' => true,
                'message' => "Data kelas berhasil diimport. {$summary['imported']} data berhasil ditambahkan.",
                'summary' => $summary
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error during kelas import: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan database saat mengimport data',
                'summary' => [
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => ['Kesalahan database: Data mungkin sudah ada atau tidak valid'],
                    'total_processed' => 0
                ]
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error importing kelas: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan tidak terduga saat mengimport data',
                'summary' => [
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => ['Terjadi kesalahan sistem saat memproses import'],
                    'total_processed' => 0
                ]
            ], 500);
        }
    }

    public function importNaikKelas(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls|max:5120',
                'target_tahun_ajaran_id' => 'nullable|exists:tahun_ajaran,id',
                'tanggal_transisi' => 'nullable|date|after_or_equal:2000-01-01',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak valid',
                    'errors' => $validator->errors()->all(),
                    'summary' => [
                        'promoted' => 0,
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => $validator->errors()->all(),
                        'total_processed' => 0,
                    ],
                ], 422);
            }

            $file = $request->file('file');
            if (!$file || !$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File tidak dapat dibaca atau rusak',
                    'summary' => [
                        'promoted' => 0,
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => ['File tidak dapat dibaca atau rusak'],
                        'total_processed' => 0,
                    ],
                ], 422);
            }

            if ($file->getSize() > 5 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ukuran file terlalu besar (maksimal 5MB)',
                    'summary' => [
                        'promoted' => 0,
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => ['Ukuran file terlalu besar (maksimal 5MB)'],
                        'total_processed' => 0,
                    ],
                ], 422);
            }

            $actor = $request->user();
            if (!$actor) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak terautentikasi',
                ], 401);
            }

            $import = new KelasNaikKelasImport(
                processedBy: (int) $actor->id,
                targetTahunAjaranId: $request->filled('target_tahun_ajaran_id') ? (int) $request->target_tahun_ajaran_id : null,
                tanggalTransisi: $request->input('tanggal_transisi')
            );

            try {
                Excel::import($import, $file);
            } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
                $failures = $e->failures();
                $errors = [];
                foreach ($failures as $failure) {
                    $errors[] = "Baris {$failure->row()}: " . implode(', ', $failure->errors());
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Validasi data gagal',
                    'summary' => [
                        'promoted' => 0,
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => $errors,
                        'total_processed' => 0,
                    ],
                ], 422);
            } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Format file tidak didukung atau file rusak',
                    'summary' => [
                        'promoted' => 0,
                        'imported' => 0,
                        'skipped' => 0,
                        'errors' => ['Format file tidak didukung atau file rusak'],
                        'total_processed' => 0,
                    ],
                ], 422);
            }

            $summary = $import->getSummary();

            if (($summary['total_processed'] ?? 0) === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada data yang dapat diproses. Pastikan file menggunakan template yang benar.',
                    'summary' => $summary,
                ], 422);
            }

            if ($import->hasErrors()) {
                return response()->json([
                    'success' => false,
                    'message' => (($summary['promoted'] ?? 0) + ($summary['assigned_new'] ?? 0)) > 0
                        ? 'Import siswa baru/naik kelas selesai dengan beberapa error'
                        : 'Import siswa baru/naik kelas gagal, tidak ada data yang berhasil diproses',
                    'summary' => $summary,
                ], (($summary['promoted'] ?? 0) + ($summary['assigned_new'] ?? 0)) > 0 ? 200 : 422);
            }

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Import siswa baru/naik kelas berhasil. Siswa baru: %d, naik kelas: %d.',
                    (int) ($summary['assigned_new'] ?? 0),
                    (int) ($summary['promoted'] ?? 0),
                ),
                'summary' => $summary,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Database error during import siswa baru/naik kelas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan database saat import siswa baru/naik kelas',
                'summary' => [
                    'promoted' => 0,
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => ['Kesalahan database: Data mungkin tidak valid'],
                    'total_processed' => 0,
                ],
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error importing siswa baru/naik kelas: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan tidak terduga saat import siswa baru/naik kelas',
                'summary' => [
                    'promoted' => 0,
                    'imported' => 0,
                    'skipped' => 0,
                    'errors' => ['Terjadi kesalahan sistem saat memproses import naik kelas'],
                    'total_processed' => 0,
                ],
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $tahunAjaranId = $request->input('tahun_ajaran_id');
            $filename = 'data-kelas';

            if ($tahunAjaranId) {
                $tahunAjaran = \App\Models\TahunAjaran::find($tahunAjaranId);
                if ($tahunAjaran) {
                    $filename .= '-' . str_replace('/', '-', $tahunAjaran->nama);
                }
            }

            $filename .= '.xlsx';

            return Excel::download(new KelasExport($tahunAjaranId), $filename);
        } catch (\Exception $e) {
            Log::error('Error exporting kelas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengexport data kelas'
            ], 500);
        }
    }

    public function index(Request $request)
    {
        $query = Kelas::with(['waliKelas', 'tahunAjaran', 'tingkat', 'siswa' => function ($q) {
            $q->where('kelas_siswa.status', 'aktif')
                ->where('kelas_siswa.is_active', true);
        }]);

        RoleDataScope::applyKelasReadScope($query, $request->user());

        // Filter berdasarkan tahun ajaran
        if ($request->has('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
        }

        // Filter berdasarkan status tahun ajaran
        if ($request->has('tahun_ajaran_status')) {
            $query->whereHas('tahunAjaran', function ($q) use ($request) {
                $q->where('status', $request->tahun_ajaran_status);
            });
        }

        // Filter untuk tahun ajaran yang bisa dikelola
        if ($request->boolean('can_manage_classes')) {
            $query->whereHas('tahunAjaran', function ($q) {
                $q->canManageClasses();
            });
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama_kelas', 'like', "%{$search}%")
                    ->orWhereHas('tingkat', function ($q) use ($search) {
                        $q->where('nama', 'like', "%{$search}%")
                            ->orWhere('kode', 'like', "%{$search}%");
                    });
            });
        }

        $kelas = $query
            ->orderByRaw('COALESCE((SELECT urutan FROM tingkat WHERE tingkat.id = kelas.tingkat_id), 999999) asc')
            ->orderBy('nama_kelas', 'asc')
            ->get();

        $formattedKelas = $kelas->map(function ($item) {
            return [
                'id' => $item->id,
                'namaKelas' => $item->nama_kelas,
                'tingkat' => optional($item->tingkat)->nama,
                'tingkat_id' => $item->tingkat_id,
                'tingkatKode' => optional($item->tingkat)->kode,
                'wali_kelas_id' => $item->wali_kelas_id,
                'waliKelas' => optional($item->waliKelas)->nama_lengkap ?? 'Belum ditentukan',
                'kapasitas' => $item->kapasitas,
                'jumlahSiswa' => $item->siswa->count(), // Hitung siswa aktif
                'tahun_ajaran_id' => $item->tahun_ajaran_id,
                'tahunAjaran' => optional($item->tahunAjaran)->nama ?? '-',
                'tahunAjaranSemester' => optional($item->tahunAjaran)->semester,
                'tahunAjaranSemesterLabel' => $this->resolveSemesterLabel(optional($item->tahunAjaran)->semester),
                'tahunAjaranStatus' => optional($item->tahunAjaran)->status ?? null,
                'canManageClasses' => optional($item->tahunAjaran)->canManageClasses() ?? false
            ];
        });

        return response()->json($formattedKelas);
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama_kelas' => 'required|string|max:50',
                'tingkat_id' => 'required|exists:tingkat,id',
                'kapasitas' => 'required|integer|min:1',
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'wali_kelas_id' => 'nullable|exists:users,id',
                'keterangan' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Validate tahun ajaran status
            $tahunAjaran = \App\Models\TahunAjaran::find($request->tahun_ajaran_id);
            if (!$tahunAjaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran tidak ditemukan'
                ], 404);
            }

            if (!$tahunAjaran->canManageClasses()) {
                return response()->json([
                    'success' => false,
                    'message' => "Tidak dapat membuat kelas untuk tahun ajaran dengan status {$tahunAjaran->status}"
                ], 422);
            }

            $data = $request->all();
            // Handle wali_kelas_id as string (name) instead of foreign key
            if (isset($data['wali_kelas_id'])) {
                $data['wali_kelas_id'] = $data['wali_kelas_id'];
            }

            $kelas = Kelas::create($data);
            WaliKelasRoleService::ensureAssigned((int) ($kelas->wali_kelas_id ?? 0));

            return response()->json([
                'success' => true,
                'message' => 'Kelas berhasil dibuat',
                'data' => $kelas->load(['waliKelas', 'tahunAjaran', 'tingkat'])
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating kelas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat kelas'
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $query = Kelas::with(['waliKelas', 'tahunAjaran', 'tingkat', 'siswa'])
            ->where('id', $id);
        RoleDataScope::applyKelasReadScope($query, $request->user());
        $kelas = $query->first();

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $kelas
        ]);
    }

    public function update(Request $request, $id)
    {
        $kelas = Kelas::find($id);

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_kelas' => 'required|string|max:50',
            'tingkat_id' => 'required|exists:tingkat,id',
            'kapasitas' => 'required|integer|min:1',
            'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
            'wali_kelas_id' => 'nullable|exists:users,id',
            'keterangan' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $tahunAjaran = \App\Models\TahunAjaran::find($request->tahun_ajaran_id);
        if (!$tahunAjaran) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun ajaran tidak ditemukan'
            ], 404);
        }

        if (!$tahunAjaran->canManageClasses()) {
            return response()->json([
                'success' => false,
                'message' => "Tidak dapat mengubah kelas ke tahun ajaran dengan status {$tahunAjaran->status}"
            ], 422);
        }

        $kelas->update($validator->validated());
        WaliKelasRoleService::ensureAssigned((int) ($kelas->wali_kelas_id ?? 0));

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil diupdate',
            'data' => $kelas->load(['waliKelas', 'tahunAjaran', 'tingkat'])
        ]);
    }

    public function destroy($id)
    {
        $kelas = Kelas::find($id);

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        }

        $kelas->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kelas berhasil dihapus'
        ]);
    }

    public function assignWaliKelas(Request $request, $id)
    {
        $kelas = Kelas::find($id);

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'wali_kelas_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $kelas->update(['wali_kelas_id' => $request->wali_kelas_id]);
        WaliKelasRoleService::ensureAssigned((int) $request->wali_kelas_id);

        return response()->json([
            'success' => true,
            'message' => 'Wali kelas berhasil ditugaskan',
            'data' => $kelas->load('waliKelas')
        ]);
    }

    public function assignSiswa(Request $request, $id)
    {
        $kelas = Kelas::find($id);

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'siswa_ids' => 'required|array',
            'siswa_ids.*' => 'exists:users,id',
            'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $tahunAjaranId = (int) $request->tahun_ajaran_id;
        if ((int) $kelas->tahun_ajaran_id !== $tahunAjaranId) {
            return response()->json([
                'success' => false,
                'message' => 'Tahun ajaran siswa harus sama dengan tahun ajaran kelas',
            ], 422);
        }

        $siswaIds = collect($request->siswa_ids)
            ->map(fn ($item) => (int) $item)
            ->filter(fn ($item) => $item > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($siswaIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Data siswa tidak valid',
            ], 422);
        }

        $activeAssignments = DB::table('kelas_siswa')
            ->whereIn('siswa_id', $siswaIds)
            ->where('is_active', true)
            ->get(['siswa_id', 'kelas_id', 'tahun_ajaran_id']);

        $blockedSiswaIds = $activeAssignments
            ->filter(function ($row) use ($kelas, $tahunAjaranId) {
                return (int) $row->kelas_id !== (int) $kelas->id
                    || (int) $row->tahun_ajaran_id !== (int) $tahunAjaranId;
            })
            ->pluck('siswa_id')
            ->map(fn ($item) => (int) $item)
            ->unique()
            ->values()
            ->all();

        if (!empty($blockedSiswaIds)) {
            $blockedNames = User::query()
                ->whereIn('id', $blockedSiswaIds)
                ->pluck('nama_lengkap')
                ->filter()
                ->values()
                ->all();

            return response()->json([
                'success' => false,
                'message' => 'Sebagian siswa sudah memiliki kelas aktif. Gunakan transisi siswa (naik/pindah/lulus/keluar).',
                'blocked_siswa_ids' => $blockedSiswaIds,
                'blocked_siswa_nama' => $blockedNames,
            ], 422);
        }

        $assignedCount = 0;
        $reactivatedCount = 0;
        $skippedCount = 0;

        foreach ($siswaIds as $siswaId) {
            $existing = DB::table('kelas_siswa')
                ->where('kelas_id', $kelas->id)
                ->where('siswa_id', $siswaId)
                ->where('tahun_ajaran_id', $tahunAjaranId)
                ->first();

            if ($existing && (bool) $existing->is_active && (string) $existing->status === 'aktif') {
                $skippedCount++;
                continue;
            }

            if ($existing) {
                DB::table('kelas_siswa')
                    ->where('id', $existing->id)
                    ->update([
                        'status' => 'aktif',
                        'is_active' => true,
                        'tanggal_keluar' => null,
                        'tanggal_masuk' => $existing->tanggal_masuk ?: now()->toDateString(),
                        'keterangan' => 'Penugasan ulang siswa melalui manajemen kelas',
                        'updated_at' => now(),
                    ]);
                $reactivatedCount++;
                continue;
            }

            DB::table('kelas_siswa')->insert([
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswaId,
                'tahun_ajaran_id' => $tahunAjaranId,
                'status' => 'aktif',
                'is_active' => true,
                'tanggal_masuk' => now()->toDateString(),
                'tanggal_keluar' => null,
                'keterangan' => 'Penugasan siswa melalui manajemen kelas',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $assignedCount++;
        }

        return response()->json([
            'success' => true,
            'message' => "Penugasan siswa selesai. Ditambahkan: {$assignedCount}, diaktifkan ulang: {$reactivatedCount}, dilewati: {$skippedCount}.",
            'summary' => [
                'assigned' => $assignedCount,
                'reactivated' => $reactivatedCount,
                'skipped' => $skippedCount,
            ],
        ]);
    }

    public function getSiswa(Request $request, $id)
    {
        Log::info('Mengambil data siswa untuk kelas ID: ' . $id);

        try {
            $query = Kelas::query()->where('id', $id);
            RoleDataScope::applyKelasReadScope($query, $request->user());
            $kelas = $query->first();

            if (!$kelas) {
                Log::warning('Kelas dengan ID ' . $id . ' tidak ditemukan');
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tidak ditemukan'
                ], 404);
            }

            $includeHistory = $request->boolean('include_history', false);
            $statusFilter = trim((string) $request->input('status', ''));

            $siswaQuery = DB::table('kelas_siswa as ks')
                ->join('users', 'users.id', '=', 'ks.siswa_id')
                ->where('ks.kelas_id', $id);

            if (!$includeHistory) {
                $siswaQuery->where('ks.status', 'aktif');
            } elseif ($statusFilter !== '') {
                $siswaQuery->where('ks.status', $statusFilter);
            }

            $siswaRows = $siswaQuery
                ->orderByRaw('CASE WHEN ks.is_active = true THEN 0 ELSE 1 END')
                ->orderBy('ks.updated_at', 'desc')
                ->orderBy('users.nama_lengkap', 'asc')
                ->get([
                    'users.id',
                    'users.nama_lengkap',
                    'users.nis',
                    'users.nisn',
                    'ks.status',
                    'ks.is_active',
                    'ks.tanggal_masuk',
                    'ks.tanggal_keluar',
                    'ks.tahun_ajaran_id',
                    'ks.keterangan',
                    'ks.created_at',
                    'ks.updated_at',
                ]);

            // Map siswa data to include pivot information
            $siswaData = $siswaRows->map(function ($siswa) {
                $statusRaw = (string) ($siswa->status ?? '');
                $statusResolved = $this->resolveKelasMembershipStatus($statusRaw, $siswa->keterangan ?? null);

                return [
                    'id' => $siswa->id,
                    'nama' => $siswa->nama_lengkap,
                    'nis' => $siswa->nis,
                    'nisn' => $siswa->nisn,
                    'status' => $statusResolved,
                    'status_raw' => $statusRaw,
                    'is_active' => (bool) $siswa->is_active,
                    'tanggal_masuk' => $siswa->tanggal_masuk,
                    'tanggal_keluar' => $siswa->tanggal_keluar,
                    'tahun_ajaran_id' => $siswa->tahun_ajaran_id,
                    'keterangan' => $siswa->keterangan,
                    'created_at' => $siswa->created_at,
                    'updated_at' => $siswa->updated_at,
                ];
            });

            // Log jumlah siswa yang ditemukan
            Log::info('Jumlah siswa yang ditemukan: ' . $siswaData->count());

            return response()->json([
                'success' => true,
                'data' => $siswaData,
                'total_siswa' => $siswaData->count(),
                'kelas_info' => [
                    'id' => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                    'tahun_ajaran_id' => $kelas->tahun_ajaran_id
                ],
                'meta' => [
                    'include_history' => $includeHistory,
                    'status_filter' => $statusFilter !== '' ? $statusFilter : null,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat mengambil data siswa: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data siswa',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function getAvailableSiswa(Request $request, $id)
    {
        try {
            $kelas = Kelas::find($id);
            if (!$kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tidak ditemukan',
                ], 404);
            }

            $limit = (int) $request->input('limit', 50);
            $limit = max(1, min($limit, 100));
            $search = trim((string) $request->input('search', ''));

            $query = User::query()
                ->select(['id', 'nama_lengkap', 'nis', 'nisn', 'email', 'is_active'])
                ->whereHas('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
                })
                ->where('is_active', true)
                ->whereDoesntHave('kelas', function ($q) {
                    $q->where('kelas_siswa.is_active', true);
                });

            RoleDataScope::applySiswaReadScope($query, $request->user());

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', '%' . $search . '%')
                        ->orWhere('nis', 'like', '%' . $search . '%')
                        ->orWhere('nisn', 'like', '%' . $search . '%')
                        ->orWhere('email', 'like', '%' . $search . '%');
                });
            }

            $items = $query
                ->orderBy('nama_lengkap', 'asc')
                ->limit($limit)
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'nama' => $user->nama_lengkap,
                        'nis' => $user->nis,
                        'nisn' => $user->nisn,
                        'email' => $user->email,
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $items,
                'meta' => [
                    'count' => $items->count(),
                    'limit' => $limit,
                    'search' => $search,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat mengambil daftar siswa available: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar siswa',
                'error' => 'Internal server error',
            ], 500);
        }
    }

    public function debugUserKelasPivot($id)
    {
        try {
            $kelas = Kelas::with(['siswa' => function ($query) {
                $query->withPivot(['tahun_ajaran_id', 'status', 'tanggal_masuk', 'tanggal_keluar', 'created_at', 'updated_at']);
            }])->find($id);

            if (!$kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tidak ditemukan'
                ], 404);
            }

            Log::info('Debug data pivot kelas_siswa untuk kelas ID: ' . $id);

            $result = $kelas->siswa->map(function ($siswa) {
                Log::info('Data pivot untuk siswa ID ' . $siswa->id . ':', [
                    'pivot_data' => $siswa->pivot->toArray()
                ]);

                return [
                    'siswa_id' => $siswa->id,
                    'nama' => $siswa->nama_lengkap,
                    'nis' => $siswa->nis,
                    'nisn' => $siswa->nisn,
                    'pivot' => [
                        'tahun_ajaran_id' => $siswa->pivot->tahun_ajaran_id,
                        'status' => $siswa->pivot->status,
                        'tanggal_masuk' => $siswa->pivot->tanggal_masuk,
                        'tanggal_keluar' => $siswa->pivot->tanggal_keluar,
                        'created_at' => $siswa->pivot->created_at,
                        'updated_at' => $siswa->pivot->updated_at
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'kelas' => [
                    'id' => $kelas->id,
                    'nama_kelas' => $kelas->nama_kelas,
                    'tingkat' => optional($kelas->tingkat)->nama
                ],
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat debugging kelas_siswa: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data debug',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function getByTingkat(Request $request, $tingkatId)
    {
        try {
            $query = Kelas::with(['waliKelas', 'tahunAjaran', 'tingkat', 'siswa' => function ($q) {
                $q->where('kelas_siswa.status', 'aktif');
            }])->where('tingkat_id', $tingkatId);
            RoleDataScope::applyKelasReadScope($query, $request->user());

            $kelas = $query->orderBy('nama_kelas', 'asc')->get();

            $formattedKelas = $kelas->map(function ($item) {
                return [
                    'id' => $item->id,
                    'namaKelas' => $item->nama_kelas,
                    'tingkat' => optional($item->tingkat)->nama,
                    'tingkat_id' => $item->tingkat_id,
                    'tingkatKode' => optional($item->tingkat)->kode,
                    'wali_kelas_id' => $item->wali_kelas_id,
                    'waliKelas' => optional($item->waliKelas)->nama_lengkap ?? 'Belum ditentukan',
                    'kapasitas' => $item->kapasitas,
                    'jumlahSiswa' => $item->siswa->count(),
                    'tahun_ajaran_id' => $item->tahun_ajaran_id,
                    'tahunAjaran' => optional($item->tahunAjaran)->nama ?? '-',
                    'tahunAjaranSemester' => optional($item->tahunAjaran)->semester,
                    'tahunAjaranSemesterLabel' => $this->resolveSemesterLabel(optional($item->tahunAjaran)->semester)
                ];
            });

            return response()->json($formattedKelas);
        } catch (\Exception $e) {
            Log::error('Error saat mengambil data kelas berdasarkan tingkat: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data kelas',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function addSiswa(Request $request, $id)
    {
        $kelas = Kelas::find($id);

        if (!$kelas) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'siswa_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $siswaId = (int) $request->siswa_id;

        $activeAssignment = DB::table('kelas_siswa')
            ->where('siswa_id', $siswaId)
            ->where('is_active', true)
            ->first();

        if (
            $activeAssignment
            && (int) $activeAssignment->kelas_id === (int) $kelas->id
            && (int) $activeAssignment->tahun_ajaran_id === (int) $kelas->tahun_ajaran_id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa sudah terdaftar di kelas ini'
            ], 422);
        }

        if (
            $activeAssignment
            && (
                (int) $activeAssignment->kelas_id !== (int) $kelas->id
                || (int) $activeAssignment->tahun_ajaran_id !== (int) $kelas->tahun_ajaran_id
            )
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa sudah memiliki kelas aktif. Gunakan transisi siswa untuk pindah/naik kelas.'
            ], 422);
        }

        $existing = DB::table('kelas_siswa')
            ->where('kelas_id', $kelas->id)
            ->where('siswa_id', $siswaId)
            ->where('tahun_ajaran_id', $kelas->tahun_ajaran_id)
            ->first();

        if ($existing) {
            DB::table('kelas_siswa')
                ->where('id', $existing->id)
                ->update([
                    'status' => 'aktif',
                    'is_active' => true,
                    'tanggal_keluar' => null,
                    'tanggal_masuk' => $existing->tanggal_masuk ?: now()->toDateString(),
                    'keterangan' => 'Aktivasi ulang siswa melalui manajemen kelas',
                    'updated_at' => now()
                ]);
        } else {
            DB::table('kelas_siswa')->insert([
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswaId,
                'tahun_ajaran_id' => $kelas->tahun_ajaran_id,
                'status' => 'aktif',
                'is_active' => true,
                'tanggal_masuk' => now()->toDateString(),
                'tanggal_keluar' => null,
                'keterangan' => 'Penugasan siswa melalui manajemen kelas',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Siswa berhasil ditambahkan ke kelas'
        ]);
    }

    public function removeSiswa($kelasId, $siswaId)
    {
        try {
            $kelas = Kelas::find($kelasId);

            if (!$kelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tidak ditemukan'
                ], 404);
            }

            $activeRelation = DB::table('kelas_siswa')
                ->where('kelas_id', $kelasId)
                ->where('siswa_id', $siswaId)
                ->where('is_active', true)
                ->first();

            if (!$activeRelation) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan di kelas ini'
                ], 404);
            }

            DB::table('kelas_siswa')
                ->where('id', $activeRelation->id)
                ->update([
                    'is_active' => false,
                    'status' => 'keluar',
                    'tanggal_keluar' => now()->toDateString(),
                    'keterangan' => 'Dinonaktifkan dari kelas melalui manajemen kelas',
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil dinonaktifkan dari kelas'
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat menghapus siswa dari kelas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus siswa dari kelas',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function resetWaliKelas(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'kelas_ids' => 'required|array',
                'kelas_ids.*' => 'exists:kelas,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $kelasIds = $request->kelas_ids;
            $updatedCount = 0;

            foreach ($kelasIds as $kelasId) {
                $kelas = Kelas::find($kelasId);
                if ($kelas) {
                    $kelas->update(['wali_kelas_id' => null]);
                    $updatedCount++;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Wali kelas berhasil dihapus dari {$updatedCount} kelas",
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            Log::error('Error saat reset wali kelas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat reset wali kelas',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    private function resolveSemesterLabel(?string $semester): string
    {
        return match (strtolower((string) $semester)) {
            'ganjil' => 'Ganjil',
            'genap' => 'Genap',
            'full' => 'Ganjil & Genap',
            default => '-',
        };
    }

    private function resolveKelasMembershipStatus(string $status, ?string $keterangan = null): string
    {
        $normalizedStatus = strtolower(trim($status));
        if ($normalizedStatus !== 'pindah') {
            return $normalizedStatus;
        }

        $normalizedNotes = strtolower(trim((string) $keterangan));
        if ($normalizedNotes !== '' && str_contains($normalizedNotes, 'naik kelas')) {
            return 'naik_kelas';
        }

        return 'pindah';
    }
}

