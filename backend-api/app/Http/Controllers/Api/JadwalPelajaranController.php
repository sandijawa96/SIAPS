<?php

namespace App\Http\Controllers\Api;

use App\Exports\AkademikTableExport;
use App\Exports\JadwalPelajaranTemplateExport;
use App\Http\Controllers\Controller;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalMengajar;
use App\Models\JadwalPelajaranSetting;
use App\Models\JadwalPelajaranSettingDay;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\RoleNames;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class JadwalPelajaranController extends Controller
{
    private const DAY_LABELS = [
        'senin' => 'Senin',
        'selasa' => 'Selasa',
        'rabu' => 'Rabu',
        'kamis' => 'Kamis',
        'jumat' => 'Jumat',
        'sabtu' => 'Sabtu',
        'minggu' => 'Minggu',
    ];

    public function index(Request $request)
    {
        try {
            $query = $this->newQueryWithRelations();
            $this->applyAccessScope($query, $request->user(), false);
            $this->applyFilters($query, $request);
            $this->applySorting($query);

            if ($request->boolean('no_pagination')) {
                return response()->json([
                    'success' => true,
                    'data' => $query->get()->map(fn (JadwalMengajar $item): array => $this->transformItem($item)),
                    'message' => 'Data jadwal pelajaran berhasil diambil',
                ]);
            }

            $perPage = (int) $request->input('per_page', 15);
            $perPage = $perPage > 0 ? min($perPage, 100) : 15;
            $rows = $query->paginate($perPage);
            $rows->getCollection()->transform(fn (JadwalMengajar $item): array => $this->transformItem($item));

            return response()->json([
                'success' => true,
                'data' => $rows,
                'message' => 'Data jadwal pelajaran berhasil diambil',
            ]);
        } catch (\Exception $exception) {
            Log::error('JadwalPelajaranController@index failed', ['error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data jadwal pelajaran'], 500);
        }
    }

    public function mySchedule(Request $request)
    {
        try {
            $query = $this->newQueryWithRelations();
            if (!$this->applyAccessScope($query, $request->user(), true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Endpoint ini hanya untuk guru, wali kelas, atau siswa',
                ], 403);
            }

            $this->applyFilters($query, $request);
            $this->applySorting($query);

            if ($request->boolean('no_pagination')) {
                return response()->json([
                    'success' => true,
                    'data' => $query->get()->map(fn (JadwalMengajar $item): array => $this->transformItem($item)),
                    'message' => 'Data jadwal pribadi berhasil diambil',
                ]);
            }

            $perPage = (int) $request->input('per_page', 15);
            $perPage = $perPage > 0 ? min($perPage, 100) : 15;
            $rows = $query->paginate($perPage);
            $rows->getCollection()->transform(fn (JadwalMengajar $item): array => $this->transformItem($item));

            return response()->json(['success' => true, 'data' => $rows, 'message' => 'Data jadwal pribadi berhasil diambil']);
        } catch (\Exception $exception) {
            Log::error('JadwalPelajaranController@mySchedule failed', ['error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal mengambil jadwal pribadi'], 500);
        }
    }

    public function options(Request $request)
    {
        try {
            $tahunAjaranId = $request->filled('tahun_ajaran_id') ? (int) $request->tahun_ajaran_id : null;
            $semester = (string) $request->input('semester', 'full');

            $kelasQuery = Kelas::query()->select(['id', 'nama_kelas', 'tingkat_id', 'tahun_ajaran_id'])
                ->with('tingkat:id,nama,kode')->orderBy('tingkat_id')->orderBy('nama_kelas');
            if ($tahunAjaranId) {
                $kelasQuery->where('tahun_ajaran_id', $tahunAjaranId);
            }

            $guruRoles = RoleNames::flattenAliases([RoleNames::GURU, RoleNames::WALI_KELAS]);
            $guru = User::query()->select(['id', 'nama_lengkap', 'nip', 'email'])->where('is_active', true)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', $guruRoles))->orderBy('nama_lengkap')->get();

            $penugasan = collect();
            if (Schema::hasTable('guru_mata_pelajaran')) {
                $penugasan = GuruMataPelajaran::query()
                    ->when(Schema::hasColumn('guru_mata_pelajaran', 'status'), fn ($q) => $q->where('status', 'aktif'))
                    ->when($tahunAjaranId, fn ($q) => $q->where('tahun_ajaran_id', $tahunAjaranId))
                    ->get();
            }

            $effectiveTahunAjaranId = $tahunAjaranId ?: $this->resolveActiveTahunAjaranId();
            $scheduleSetting = $this->getScheduleSettingPayload($effectiveTahunAjaranId, $semester);

            return response()->json([
                'success' => true,
                'data' => [
                    'guru' => $guru,
                    'kelas' => $kelasQuery->get(),
                    'mata_pelajaran' => MataPelajaran::query()->select(['id', 'kode_mapel', 'nama_mapel', 'tingkat_id'])->where('is_active', true)->orderBy('kode_mapel')->get(),
                    'tahun_ajaran' => TahunAjaran::query()->select(['id', 'nama', 'status', 'semester', 'is_active'])->orderByDesc('is_active')->orderByDesc('id')->get(),
                    'penugasan_guru_mapel' => $penugasan,
                    'hari_options' => collect(self::DAY_LABELS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
                    'semester_options' => [['value' => 'ganjil', 'label' => 'Ganjil'], ['value' => 'genap', 'label' => 'Genap'], ['value' => 'full', 'label' => 'Full']],
                    'status_options' => [['value' => 'draft', 'label' => 'Draft'], ['value' => 'published', 'label' => 'Published'], ['value' => 'archived', 'label' => 'Archived']],
                    'schedule_setting' => $scheduleSetting,
                    'slot_templates' => $scheduleSetting['slot_templates'] ?? [],
                ],
                'message' => 'Opsi jadwal pelajaran berhasil diambil',
            ]);
        } catch (\Exception $exception) {
            Log::error('JadwalPelajaranController@options failed', ['error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal mengambil data referensi jadwal pelajaran'], 500);
        }
    }

    public function settings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tahun_ajaran_id' => ['nullable', 'exists:tahun_ajaran,id'],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap', 'full'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter pengaturan jadwal tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $tahunAjaranId = $request->filled('tahun_ajaran_id')
            ? (int) $request->tahun_ajaran_id
            : $this->resolveActiveTahunAjaranId();
        $semester = (string) $request->input('semester', 'full');

        $setting = $this->getScheduleSettingPayload($tahunAjaranId, $semester);

        return response()->json([
            'success' => true,
            'data' => $setting,
            'message' => 'Pengaturan jadwal berhasil diambil',
        ]);
    }

    public function updateSettings(Request $request)
    {
        if (!$this->canUseScheduleSettingTables()) {
            return response()->json([
                'success' => false,
                'message' => 'Tabel pengaturan jadwal belum tersedia. Jalankan migration terlebih dahulu.',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'tahun_ajaran_id' => ['required', 'exists:tahun_ajaran,id'],
            'semester' => ['required', Rule::in(['ganjil', 'genap', 'full'])],
            'default_jp_minutes' => ['required', 'integer', 'min:20', 'max:90'],
            'default_start_time' => ['required', 'date_format:H:i'],
            'is_active' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
            'days' => ['required', 'array', 'min:1'],
            'days.*.hari' => ['required', Rule::in(array_keys(self::DAY_LABELS))],
            'days.*.is_school_day' => ['nullable', 'boolean'],
            'days.*.jp_count' => ['nullable', 'integer', 'min:0', 'max:16'],
            'days.*.jp_minutes' => ['nullable', 'integer', 'min:20', 'max:90'],
            'days.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.notes' => ['nullable', 'string', 'max:255'],
            'days.*.breaks' => ['nullable', 'array'],
            'days.*.breaks.*.after_jp' => ['required_with:days.*.breaks', 'integer', 'min:1', 'max:16'],
            'days.*.breaks.*.break_minutes' => ['required_with:days.*.breaks', 'integer', 'min:1', 'max:120'],
            'days.*.breaks.*.label' => ['nullable', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data pengaturan jadwal tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $daysInput = collect($request->input('days', []));
        $duplicateDay = $daysInput
            ->pluck('hari')
            ->filter(fn ($day) => is_string($day) && $day !== '')
            ->duplicates()
            ->first();

        if ($duplicateDay) {
            return response()->json([
                'success' => false,
                'message' => 'Hari pada pengaturan jadwal tidak boleh duplikat',
                'errors' => ['days' => ["Hari {$duplicateDay} diinput lebih dari sekali"]],
            ], 422);
        }

        try {
            $setting = DB::transaction(function () use ($request, $daysInput): JadwalPelajaranSetting {
                $setting = JadwalPelajaranSetting::query()->updateOrCreate(
                    [
                        'tahun_ajaran_id' => (int) $request->tahun_ajaran_id,
                        'semester' => (string) $request->semester,
                    ],
                    [
                        'default_jp_minutes' => (int) $request->default_jp_minutes,
                        'default_start_time' => (string) $request->default_start_time,
                        'is_active' => $request->has('is_active') ? (bool) $request->boolean('is_active') : true,
                        'notes' => $request->filled('notes') ? trim((string) $request->notes) : null,
                    ]
                );

                $setting->days()->delete();

                foreach ($daysInput as $dayInput) {
                    $day = $setting->days()->create([
                        'hari' => (string) $dayInput['hari'],
                        'is_school_day' => array_key_exists('is_school_day', $dayInput) ? (bool) $dayInput['is_school_day'] : true,
                        'jp_count' => array_key_exists('jp_count', $dayInput) ? (int) $dayInput['jp_count'] : 0,
                        'jp_minutes' => array_key_exists('jp_minutes', $dayInput) && $dayInput['jp_minutes'] !== null
                            ? (int) $dayInput['jp_minutes']
                            : null,
                        'start_time' => !empty($dayInput['start_time']) ? (string) $dayInput['start_time'] : null,
                        'notes' => !empty($dayInput['notes']) ? trim((string) $dayInput['notes']) : null,
                    ]);

                    $breakRows = collect($dayInput['breaks'] ?? [])
                        ->filter(fn ($breakInput) => is_array($breakInput) && isset($breakInput['after_jp'], $breakInput['break_minutes']))
                        ->sortBy('after_jp')
                        ->unique('after_jp')
                        ->values()
                        ->all();

                    foreach ($breakRows as $breakInput) {
                        $day->breaks()->create([
                            'after_jp' => (int) $breakInput['after_jp'],
                            'break_minutes' => (int) $breakInput['break_minutes'],
                            'label' => !empty($breakInput['label']) ? trim((string) $breakInput['label']) : null,
                        ]);
                    }
                }

                return $setting->refresh()->load(['days.breaks']);
            });

            $payload = $this->transformScheduleSetting($setting);

            return response()->json([
                'success' => true,
                'data' => $payload,
                'message' => 'Pengaturan jadwal berhasil disimpan',
            ]);
        } catch (\Exception $exception) {
            Log::error('JadwalPelajaranController@updateSettings failed', ['error' => $exception->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pengaturan jadwal',
            ], 500);
        }
    }

    public function checkConflict(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }

        $payload = $this->normalizedRequestPayload($request);
        [$payloadReady, $payloadError] = $this->hydratePayloadTimeFromSetting($payload);
        if (!$payloadReady) {
            return response()->json([
                'success' => false,
                'message' => $payloadError ?: 'Pilih JP dari slot pengaturan jadwal',
                'errors' => ['time' => ['Pilih JP dari slot pengaturan jadwal']],
            ], 422);
        }

        $result = $this->detectConflicts($payload, null);
        return response()->json([
            'success' => true,
            'data' => $result,
            'resolved_time' => [
                'jam_ke' => $payload['jam_ke'],
                'jam_mulai' => $payload['jam_mulai'],
                'jam_selesai' => $payload['jam_selesai'],
            ],
            'message' => $result['has_conflict'] ? 'Konflik jadwal ditemukan' : 'Tidak ada konflik jadwal',
        ]);
    }

    public function store(Request $request)
    {
        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }

        $payload = $this->normalizedRequestPayload($request);
        [$payloadReady, $payloadError] = $this->hydratePayloadTimeFromSetting($payload);
        if (!$payloadReady) {
            return response()->json([
                'success' => false,
                'message' => $payloadError ?: 'Pilih JP dari slot pengaturan jadwal',
                'errors' => ['time' => ['Pilih JP dari slot pengaturan jadwal']],
            ], 422);
        }

        [$payloadsReady, $payloadsError, $payloadRows] = $this->expandBlockPayloadsFromStart($payload);
        if (!$payloadsReady) {
            return response()->json([
                'success' => false,
                'message' => $payloadsError ?: 'Rentang JP berurutan tidak valid',
                'errors' => ['jp_count' => [$payloadsError ?: 'Rentang JP berurutan tidak valid']],
            ], 422);
        }

        foreach ($payloadRows as $candidatePayload) {
            [$valid, $message, $errors, $conflicts] = $this->validateBusinessRules($candidatePayload, null);
            if (!$valid) {
                $jpLabel = !empty($candidatePayload['jam_ke']) ? ' pada JP ke-' . (int) $candidatePayload['jam_ke'] : '';
                return response()->json([
                    'success' => false,
                    'message' => $message . $jpLabel,
                    'errors' => $errors,
                    'conflicts' => $conflicts,
                ], 422);
            }
        }

        try {
            $createdRows = DB::transaction(function () use ($payloadRows): array {
                $rows = [];
                foreach ($payloadRows as $candidatePayload) {
                    $rows[] = JadwalMengajar::create($this->buildPayloadFromNormalized($candidatePayload));
                }
                return $rows;
            });

            $createdCount = count($createdRows);
            $transformedRows = collect($createdRows)
                ->map(fn (JadwalMengajar $item): array => $this->transformItem($this->loadRelations($item)))
                ->values();

            return response()->json([
                'success' => true,
                'data' => $createdCount === 1 ? $transformedRows->first() : $transformedRows,
                'created_count' => $createdCount,
                'message' => $createdCount === 1
                    ? 'Jadwal pelajaran berhasil dibuat'
                    : "Berhasil membuat {$createdCount} jadwal berurutan",
            ], 201);
        } catch (\Exception $exception) {
            Log::error('JadwalPelajaranController@store failed', ['error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal membuat jadwal pelajaran'], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        $jadwal = JadwalMengajar::find($id);
        if (!$jadwal) {
            return response()->json(['success' => false, 'message' => 'Data jadwal tidak ditemukan'], 404);
        }

        $validator = $this->validator($request);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Data tidak valid', 'errors' => $validator->errors()], 422);
        }

        $payload = $this->normalizedRequestPayload($request);
        [$payloadReady, $payloadError] = $this->hydratePayloadTimeFromSetting($payload);
        if (!$payloadReady) {
            return response()->json([
                'success' => false,
                'message' => $payloadError ?: 'Pilih JP dari slot pengaturan jadwal',
                'errors' => ['time' => ['Pilih JP dari slot pengaturan jadwal']],
            ], 422);
        }

        [$valid, $message, $errors, $conflicts] = $this->validateBusinessRules($payload, $jadwal->id);
        if (!$valid) {
            return response()->json(['success' => false, 'message' => $message, 'errors' => $errors, 'conflicts' => $conflicts], 422);
        }

        try {
            $updated = DB::transaction(function () use ($jadwal, $payload): JadwalMengajar {
                $jadwal->update($this->buildPayloadFromNormalized($payload));
                return $jadwal->refresh();
            });

            return response()->json(['success' => true, 'data' => $this->transformItem($this->loadRelations($updated)), 'message' => 'Jadwal pelajaran berhasil diperbarui']);
        } catch (\Exception $exception) {
            Log::error('JadwalPelajaranController@update failed', ['id' => $id, 'error' => $exception->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Gagal memperbarui jadwal pelajaran'], 500);
        }
    }

    public function destroy(int $id)
    {
        $jadwal = JadwalMengajar::find($id);
        if (!$jadwal) {
            return response()->json(['success' => false, 'message' => 'Data jadwal tidak ditemukan'], 404);
        }

        $jadwal->delete();
        return response()->json(['success' => true, 'message' => 'Jadwal pelajaran berhasil dihapus']);
    }

    public function publish(Request $request)
    {
        if (!$this->hasJadwalColumn('status')) {
            return response()->json(['success' => false, 'message' => 'Kolom status jadwal belum tersedia pada database'], 422);
        }

        $rules = ['kelas_id' => ['nullable', 'exists:kelas,id'], 'semester' => ['nullable', Rule::in(['ganjil', 'genap', 'full'])]];
        if ($this->hasJadwalColumn('tahun_ajaran_id')) {
            $rules['tahun_ajaran_id'] = ['required', 'exists:tahun_ajaran,id'];
        }

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Data publish tidak valid', 'errors' => $validator->errors()], 422);
        }

        $query = JadwalMengajar::query()->where('status', 'draft');
        if ($this->hasJadwalColumn('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', (int) $request->tahun_ajaran_id);
        }
        if ($this->hasJadwalColumn('semester') && $request->filled('semester')) {
            $query->where('semester', (string) $request->semester);
        }
        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', (int) $request->kelas_id);
        }

        $draftRows = $query->get();
        if ($draftRows->isEmpty()) {
            return response()->json([
                'success' => true,
                'data' => ['updated_count' => 0],
                'message' => 'Tidak ada jadwal draft yang dipublish',
            ]);
        }

        $publishConflicts = $this->collectPublishConflicts($draftRows);
        if ($publishConflicts['has_conflict']) {
            return response()->json([
                'success' => false,
                'message' => 'Publish ditolak karena masih ada konflik jadwal pada data draft',
                'conflicts' => $publishConflicts,
            ], 422);
        }

        $updateData = ['status' => 'published', 'updated_at' => now()];
        if ($this->hasJadwalColumn('is_active')) {
            $updateData['is_active'] = true;
        }

        $updatedCount = $query->update($updateData);
        return response()->json(['success' => true, 'data' => ['updated_count' => $updatedCount], 'message' => $updatedCount > 0 ? "Berhasil publish {$updatedCount} jadwal" : 'Tidak ada jadwal draft yang dipublish']);
    }

    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'format' => ['nullable', Rule::in(['xlsx', 'pdf'])],
            'fields' => ['nullable'],
            'search' => ['nullable', 'string', 'max:255'],
            'guru_id' => ['nullable', 'integer', 'exists:users,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['nullable', 'integer', 'exists:mata_pelajaran,id'],
            'tahun_ajaran_id' => ['nullable', 'integer', 'exists:tahun_ajaran,id'],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap', 'full'])],
            'hari' => ['nullable', Rule::in(array_keys(self::DAY_LABELS))],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
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
            $query = $this->newQueryWithRelations();
            $this->applyAccessScope($query, $request->user(), false);
            $this->applyFilters($query, $request);
            $this->applySorting($query);

            $rows = $query->get()
                ->map(fn (JadwalMengajar $item): array => $this->transformItem($item))
                ->values()
                ->map(function (array $item, int $index): array {
                    return [
                        'no' => $index + 1,
                        'hari' => $item['hari_label'] ?? ucfirst((string) ($item['hari'] ?? '-')),
                        'jam_ke' => $item['jam_ke'] ?? '-',
                        'jam_mulai' => $item['jam_mulai'] ?? '-',
                        'jam_selesai' => $item['jam_selesai'] ?? '-',
                        'kelas' => $item['kelas']['nama_kelas'] ?? '-',
                        'mapel_kode' => $item['mata_pelajaran']['kode_mapel'] ?? '-',
                        'mapel_nama' => $item['mata_pelajaran']['nama_mapel'] ?? '-',
                        'guru_nama' => $item['guru']['nama_lengkap'] ?? '-',
                        'guru_nip' => $item['guru']['nip'] ?? '-',
                        'ruangan' => $item['ruangan'] ?? '-',
                        'tahun_ajaran' => $item['tahun_ajaran']['nama'] ?? '-',
                        'semester' => ucfirst((string) ($item['semester'] ?? 'full')),
                        'status' => $item['status_label'] ?? ucfirst((string) ($item['status'] ?? 'published')),
                        'is_active' => !empty($item['is_active']) ? 'Aktif' : 'Nonaktif',
                        'updated_at' => !empty($item['updated_at'])
                            ? optional(Carbon::parse((string) $item['updated_at']))->format('Y-m-d H:i:s')
                            : '-',
                    ];
                });

            $availableColumns = $this->jadwalExportColumns();
            $selectedColumns = $this->resolveExportColumns($request->input('fields'), $availableColumns);
            $timestamp = now()->format('Ymd_His');
            $generatedBy = $request->user()?->nama_lengkap ?? $request->user()?->username ?? 'System';

            $meta = [
                'title' => 'Laporan Jadwal Pelajaran',
                'subtitle' => 'Sistem Manajemen Akademik',
                'sheet_title' => 'Jadwal Pelajaran',
                'generated_by' => $generatedBy,
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'filter_summary' => $this->buildJadwalFilterSummary($request),
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

                return $pdf->download("Jadwal_Pelajaran_{$timestamp}.pdf");
            }

            return Excel::download(
                new AkademikTableExport($rows, $selectedColumns, $meta),
                "Jadwal_Pelajaran_{$timestamp}.xlsx"
            );
        } catch (\Exception $exception) {
            Log::error('JadwalPelajaranController@export failed', ['error' => $exception->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data jadwal pelajaran',
            ], 500);
        }
    }

    public function downloadTemplate()
    {
        try {
            return Excel::download(new JadwalPelajaranTemplateExport(), 'Template_Import_Jadwal_Pelajaran.xlsx');
        } catch (\Exception $exception) {
            Log::error('JadwalPelajaranController@downloadTemplate failed', ['error' => $exception->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunduh template import jadwal pelajaran',
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
                'id' => ['id_opsional_untuk_update', 'id'],
                'guru_ref' => ['guru_nip_email_username', 'guru_nip_email_username*', 'guru', 'guru_ref'],
                'mapel_ref' => ['mata_pelajaran_kode_nama', 'mata_pelajaran_kode_nama*', 'mata_pelajaran', 'mapel', 'mapel_ref'],
                'kelas_ref' => ['kelas', 'kelas*', 'kelas_ref'],
                'tahun_ajaran_ref' => ['tahun_ajaran', 'tahun_ajaran*', 'tahun_ajaran_ref'],
                'semester' => ['semester_ganjil_genap_full', 'semester'],
                'hari' => ['hari', 'hari*'],
                'jam_ke' => ['jam_ke_jp', 'jam_ke_jp*', 'jam_ke', 'jp_ke'],
                'jp_count' => ['jumlah_jp', 'jumlah_jp_berurutan', 'jp_count'],
                'ruangan' => ['ruangan'],
                'status' => ['status_draft_published_archived', 'status'],
                'is_active' => ['aktif_ya_tidak', 'aktif', 'is_active'],
                'guru_id' => ['guru_id'],
                'mata_pelajaran_id' => ['mata_pelajaran_id', 'mapel_id'],
                'kelas_id' => ['kelas_id'],
                'tahun_ajaran_id' => ['tahun_ajaran_id'],
            ];

            [$headerIndex, $headerMap, $headerScore] = $this->detectImportHeaderRow($rows, $headerAliases);
            if (
                $headerIndex === null
                || $headerScore < 6
                || !isset($headerMap['guru_ref'], $headerMap['mapel_ref'], $headerMap['kelas_ref'], $headerMap['tahun_ajaran_ref'], $headerMap['hari'], $headerMap['jam_ke'])
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

                $idRaw = $this->extractRowString($row, $headerMap['id'] ?? null);
                $guruRef = $this->extractRowString($row, $headerMap['guru_ref'] ?? null);
                $mapelRef = $this->extractRowString($row, $headerMap['mapel_ref'] ?? null);
                $kelasRef = $this->extractRowString($row, $headerMap['kelas_ref'] ?? null);
                $tahunAjaranRef = $this->extractRowString($row, $headerMap['tahun_ajaran_ref'] ?? null);
                $semesterRaw = $this->extractRowString($row, $headerMap['semester'] ?? null);
                $hariRaw = $this->extractRowString($row, $headerMap['hari'] ?? null);
                $jamKeRaw = $this->extractRowString($row, $headerMap['jam_ke'] ?? null);
                $jpCountRaw = $this->extractRowString($row, $headerMap['jp_count'] ?? null);
                $ruangan = $this->extractRowString($row, $headerMap['ruangan'] ?? null);
                $statusRaw = $this->extractRowString($row, $headerMap['status'] ?? null);
                $isActiveRaw = $this->extractRowString($row, $headerMap['is_active'] ?? null);

                $guruIdRaw = $this->extractRowString($row, $headerMap['guru_id'] ?? null);
                $mapelIdRaw = $this->extractRowString($row, $headerMap['mata_pelajaran_id'] ?? null);
                $kelasIdRaw = $this->extractRowString($row, $headerMap['kelas_id'] ?? null);
                $tahunAjaranIdRaw = $this->extractRowString($row, $headerMap['tahun_ajaran_id'] ?? null);

                if (
                    $idRaw === ''
                    && $guruRef === ''
                    && $mapelRef === ''
                    && $kelasRef === ''
                    && $tahunAjaranRef === ''
                    && $semesterRaw === ''
                    && $hariRaw === ''
                    && $jamKeRaw === ''
                    && $jpCountRaw === ''
                    && $ruangan === ''
                    && $statusRaw === ''
                    && $isActiveRaw === ''
                    && $guruIdRaw === ''
                    && $mapelIdRaw === ''
                    && $kelasIdRaw === ''
                    && $tahunAjaranIdRaw === ''
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

                $hari = $this->normalizeHariFromImport($hariRaw);
                if (!$hari) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: hari '{$hariRaw}' tidak valid.";
                    continue;
                }

                if ($jamKeRaw === '' || !is_numeric($jamKeRaw) || (int) $jamKeRaw <= 0) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: jam_ke wajib diisi dengan angka >= 1.";
                    continue;
                }

                $semester = $this->normalizeSemesterFromImport($semesterRaw) ?? 'full';
                $statusNormalized = $this->normalizeJadwalStatusFromImport($statusRaw);
                if ($statusRaw !== '' && $statusNormalized === null) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: status '{$statusRaw}' tidak valid.";
                    continue;
                }
                $status = $statusNormalized ?? 'draft';

                $isActive = $this->parseBooleanImportValue($isActiveRaw, true);
                if ($isActiveRaw !== '' && $isActive === null) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: nilai aktif '{$isActiveRaw}' tidak valid (gunakan ya/tidak).";
                    continue;
                }

                $payload = [
                    'guru_id' => (int) $guru->id,
                    'kelas_id' => (int) $kelas->id,
                    'mata_pelajaran_id' => (int) $mapel->id,
                    'tahun_ajaran_id' => (int) $tahunAjaran->id,
                    'semester' => $semester,
                    'hari' => $hari,
                    'jam_ke' => (int) $jamKeRaw,
                    'jp_count' => $jpCountRaw !== '' ? max(1, (int) $jpCountRaw) : 1,
                    'ruangan' => $ruangan !== '' ? $ruangan : null,
                    'status' => $status,
                    'is_active' => $isActive ?? true,
                    'jam_mulai' => null,
                    'jam_selesai' => null,
                ];

                $existing = null;
                if ($idRaw !== '' && is_numeric($idRaw)) {
                    $existing = JadwalMengajar::query()->find((int) $idRaw);
                } elseif ($importMode !== 'create') {
                    $existing = $this->findExistingScheduleForImport($payload);
                }

                if ($existing && $importMode === 'create') {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: jadwal sudah ada (mode create).";
                    continue;
                }
                if (!$existing && $importMode === 'update') {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: jadwal tidak ditemukan (mode update).";
                    continue;
                }

                [$ready, $timeMessage] = $this->hydratePayloadTimeFromSetting($payload);
                if (!$ready) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: {$timeMessage}";
                    continue;
                }

                if ($existing) {
                    [$valid, $message, $errors, $conflicts] = $this->validateBusinessRules($payload, (int) $existing->id);
                    if (!$valid) {
                        $summary['failed']++;
                        $errorText = collect($errors)->flatten()->implode(', ');
                        if (!empty($conflicts['summary'])) {
                            $errorText .= ($errorText !== '' ? ', ' : '') . 'konflik: ' . json_encode($conflicts['summary']);
                        }
                        $summary['errors'][] = "Baris {$excelRow}: {$message}" . ($errorText !== '' ? " ({$errorText})" : '');
                        continue;
                    }

                    $existing->update($this->buildPayloadFromNormalized($payload));
                    $summary['updated']++;
                    continue;
                }

                [$payloadsReady, $payloadsError, $payloadRows] = $this->expandBlockPayloadsFromStart($payload);
                if (!$payloadsReady) {
                    $summary['failed']++;
                    $summary['errors'][] = "Baris {$excelRow}: {$payloadsError}";
                    continue;
                }

                $rowValid = true;
                foreach ($payloadRows as $candidatePayload) {
                    [$valid, $message, $errors, $conflicts] = $this->validateBusinessRules($candidatePayload, null);
                    if (!$valid) {
                        $rowValid = false;
                        $errorText = collect($errors)->flatten()->implode(', ');
                        if (!empty($conflicts['summary'])) {
                            $errorText .= ($errorText !== '' ? ', ' : '') . 'konflik: ' . json_encode($conflicts['summary']);
                        }
                        $summary['errors'][] = "Baris {$excelRow}: {$message}" . ($errorText !== '' ? " ({$errorText})" : '');
                        $summary['failed']++;
                        break;
                    }
                }

                if (!$rowValid) {
                    continue;
                }

                foreach ($payloadRows as $candidatePayload) {
                    JadwalMengajar::create($this->buildPayloadFromNormalized($candidatePayload));
                    $summary['imported']++;
                }
            }

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
            Log::error('JadwalPelajaranController@import failed', ['error' => $exception->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat import jadwal pelajaran',
                'data' => $summary,
            ], 500);
        }

        $successCount = $summary['imported'] + $summary['updated'];
        if ($successCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada data jadwal yang berhasil diimport',
                'data' => $summary,
            ], 422);
        }

        $message = "Import selesai: {$summary['imported']} jadwal dibuat, {$summary['updated']} diperbarui.";
        if ($summary['failed'] > 0) {
            $message .= " {$summary['failed']} baris gagal.";
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $summary,
        ]);
    }

    private function newQueryWithRelations(): Builder
    {
        $query = JadwalMengajar::query()->with([
            'guru:id,nama_lengkap,nip,email',
            'kelas:id,nama_kelas,tingkat_id,tahun_ajaran_id',
            'kelas.tingkat:id,nama,kode',
            'tahunAjaran:id,nama,status',
        ]);

        if ($this->hasJadwalColumn('mata_pelajaran_id')) {
            $query->with('mataPelajaran:id,kode_mapel,nama_mapel,tingkat_id');
        }

        return $query;
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $keyword = trim((string) $request->search);
            $query->where(function ($builder) use ($keyword): void {
                $builder->whereHas('guru', function ($guruQuery) use ($keyword): void {
                    $guruQuery->where('nama_lengkap', 'ILIKE', '%' . $keyword . '%')
                        ->orWhere('nip', 'ILIKE', '%' . $keyword . '%');
                })->orWhereHas('kelas', function ($kelasQuery) use ($keyword): void {
                    $kelasQuery->where('nama_kelas', 'ILIKE', '%' . $keyword . '%');
                });

                if ($this->hasJadwalColumn('mata_pelajaran_id')) {
                    $builder->orWhereHas('mataPelajaran', function ($mapelQuery) use ($keyword): void {
                        $mapelQuery->where('kode_mapel', 'ILIKE', '%' . $keyword . '%')
                            ->orWhere('nama_mapel', 'ILIKE', '%' . $keyword . '%');
                    });
                } elseif ($this->hasJadwalColumn('mata_pelajaran')) {
                    $builder->orWhere('mata_pelajaran', 'ILIKE', '%' . $keyword . '%');
                }

                if ($this->hasJadwalColumn('ruangan')) {
                    $builder->orWhere('ruangan', 'ILIKE', '%' . $keyword . '%');
                }
            });
        }

        foreach (['guru_id', 'kelas_id', 'hari'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }

        if ($request->filled('mata_pelajaran_id')) {
            if ($this->hasJadwalColumn('mata_pelajaran_id')) {
                $query->where('mata_pelajaran_id', (int) $request->mata_pelajaran_id);
            } else {
                $mapel = MataPelajaran::find((int) $request->mata_pelajaran_id);
                if ($mapel && $this->hasJadwalColumn('mata_pelajaran')) {
                    $query->where('mata_pelajaran', $mapel->nama_mapel);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
        }

        if ($request->filled('tahun_ajaran_id') && $this->hasJadwalColumn('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', (int) $request->tahun_ajaran_id);
        }

        if ($request->filled('semester') && $this->hasJadwalColumn('semester')) {
            $query->where('semester', (string) $request->semester);
        }

        if ($request->filled('status') && $this->hasJadwalColumn('status')) {
            $query->where('status', (string) $request->status);
        }

        if ($request->has('is_active') && $request->input('is_active') !== '' && $this->hasJadwalColumn('is_active')) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }
    }

    private function applySorting(Builder $query): void
    {
        $query->orderByRaw("
            CASE hari
                WHEN 'senin' THEN 1
                WHEN 'selasa' THEN 2
                WHEN 'rabu' THEN 3
                WHEN 'kamis' THEN 4
                WHEN 'jumat' THEN 5
                WHEN 'sabtu' THEN 6
                WHEN 'minggu' THEN 7
                ELSE 8
            END
        ")->orderBy('jam_mulai')->orderBy('kelas_id');
    }

    private function validator(Request $request)
    {
        $rules = [
            'guru_id' => ['required', 'exists:users,id'],
            'kelas_id' => ['required', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['required', 'exists:mata_pelajaran,id'],
            'hari' => ['required', Rule::in(array_keys(self::DAY_LABELS))],
            'jam_mulai' => ['nullable', 'date_format:H:i'],
            'jam_selesai' => ['nullable', 'date_format:H:i', 'after:jam_mulai'],
            'ruangan' => ['nullable', 'string', 'max:100'],
            'jam_ke' => ['nullable', 'integer', 'min:1', 'max:12'],
            'jp_count' => ['nullable', 'integer', 'min:1', 'max:12'],
            'is_active' => ['nullable', 'boolean'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap', 'full'])],
        ];

        if ($this->hasJadwalColumn('tahun_ajaran_id')) {
            $rules['tahun_ajaran_id'] = ['required', 'exists:tahun_ajaran,id'];
        } else {
            $rules['tahun_ajaran_id'] = ['nullable', 'exists:tahun_ajaran,id'];
        }

        return Validator::make($request->all(), $rules);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedRequestPayload(Request $request): array
    {
        return [
            'guru_id' => (int) $request->guru_id,
            'kelas_id' => (int) $request->kelas_id,
            'mata_pelajaran_id' => (int) $request->mata_pelajaran_id,
            'tahun_ajaran_id' => $request->filled('tahun_ajaran_id') ? (int) $request->tahun_ajaran_id : null,
            'semester' => $request->filled('semester') ? (string) $request->semester : 'full',
            'hari' => (string) $request->hari,
            'jam_mulai' => $request->filled('jam_mulai') ? (string) $request->jam_mulai : null,
            'jam_selesai' => $request->filled('jam_selesai') ? (string) $request->jam_selesai : null,
            'jam_ke' => $request->filled('jam_ke') ? (int) $request->jam_ke : null,
            'jp_count' => $request->filled('jp_count') ? (int) $request->jp_count : 1,
            'ruangan' => $request->filled('ruangan') ? trim((string) $request->ruangan) : null,
            'status' => $request->filled('status') ? (string) $request->status : 'draft',
            'is_active' => $request->has('is_active') ? (bool) $request->boolean('is_active') : true,
        ];
    }

    /**
     * @return array{0:bool,1:string,2:array<string,mixed>,3:array<string,mixed>|null}
     */
    private function validateBusinessRules(array $payload, ?int $ignoreId): array
    {
        /** @var User|null $guru */
        $guru = User::find($payload['guru_id']);
        if (!$guru) {
            return [false, 'Guru tidak ditemukan', ['guru_id' => ['Guru tidak valid']], null];
        }

        $guruAliases = RoleNames::flattenAliases([RoleNames::GURU, RoleNames::WALI_KELAS]);
        if (!$guru->hasRole($guruAliases)) {
            return [false, 'User yang dipilih bukan guru/wali kelas', ['guru_id' => ['User tidak memiliki role guru/wali kelas']], null];
        }

        /** @var Kelas|null $kelas */
        $kelas = Kelas::find($payload['kelas_id']);
        /** @var MataPelajaran|null $mapel */
        $mapel = MataPelajaran::find($payload['mata_pelajaran_id']);
        if (!$kelas || !$mapel) {
            return [false, 'Data kelas atau mata pelajaran tidak valid', [], null];
        }

        if ($payload['tahun_ajaran_id'] && (int) $kelas->tahun_ajaran_id !== (int) $payload['tahun_ajaran_id']) {
            return [false, 'Kelas tidak berada pada tahun ajaran yang dipilih', ['tahun_ajaran_id' => ['Tahun ajaran kelas tidak sesuai']], null];
        }

        if ($mapel->tingkat_id && (int) $mapel->tingkat_id !== (int) $kelas->tingkat_id) {
            return [false, 'Mata pelajaran tidak sesuai dengan tingkat kelas', ['mata_pelajaran_id' => ['Mapel tidak sesuai tingkat kelas']], null];
        }

        if (!$this->hasAssignment($payload, $mapel->nama_mapel)) {
            return [
                false,
                'Guru belum memiliki penugasan mapel pada kelas/tahun ajaran ini',
                ['penugasan' => ['Buat penugasan guru-mapel terlebih dahulu']],
                null,
            ];
        }

        $conflicts = $this->detectConflicts($payload, $ignoreId);
        if ($conflicts['has_conflict']) {
            return [
                false,
                'Konflik jadwal terdeteksi. Periksa guru/kelas/ruangan pada slot waktu yang sama',
                ['conflict' => ['Slot waktu bentrok dengan jadwal lain']],
                $conflicts,
            ];
        }

        return [true, '', [], null];
    }

    private function hasAssignment(array $payload, string $mapelName): bool
    {
        if (!Schema::hasTable('guru_mata_pelajaran')) {
            return true;
        }

        $query = GuruMataPelajaran::query()
            ->where('guru_id', $payload['guru_id'])
            ->where('kelas_id', $payload['kelas_id']);

        if (Schema::hasColumn('guru_mata_pelajaran', 'tahun_ajaran_id') && $payload['tahun_ajaran_id']) {
            $query->where('tahun_ajaran_id', $payload['tahun_ajaran_id']);
        }

        if (Schema::hasColumn('guru_mata_pelajaran', 'mata_pelajaran_id')) {
            $query->where('mata_pelajaran_id', $payload['mata_pelajaran_id']);
        } elseif (Schema::hasColumn('guru_mata_pelajaran', 'mata_pelajaran')) {
            $query->where('mata_pelajaran', $mapelName);
        }

        if (Schema::hasColumn('guru_mata_pelajaran', 'status')) {
            $query->where('status', 'aktif');
        }

        return $query->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function detectConflicts(array $payload, ?int $ignoreId): array
    {
        $query = $this->newQueryWithRelations()
            ->where('hari', $payload['hari'])
            ->where('jam_mulai', '<', $payload['jam_selesai'])
            ->where('jam_selesai', '>', $payload['jam_mulai']);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }
        if ($this->hasJadwalColumn('tahun_ajaran_id') && $payload['tahun_ajaran_id']) {
            $query->where('tahun_ajaran_id', $payload['tahun_ajaran_id']);
        }
        if ($this->hasJadwalColumn('semester') && !empty($payload['semester'])) {
            $this->applySemesterConflictScope($query, (string) $payload['semester']);
        }
        if ($this->hasJadwalColumn('status')) {
            $query->where('status', '!=', 'archived');
        }
        if ($this->hasJadwalColumn('is_active')) {
            $query->where('is_active', true);
        }

        $rows = $query->get();
        $guruConflicts = $rows->where('guru_id', $payload['guru_id'])->values();
        $kelasConflicts = $rows->where('kelas_id', $payload['kelas_id'])->values();
        $ruanganConflicts = collect();
        if (!empty($payload['ruangan'])) {
            $ruanganConflicts = $rows->filter(function (JadwalMengajar $row) use ($payload): bool {
                return trim((string) ($row->ruangan ?? '')) !== ''
                    && mb_strtolower(trim((string) $row->ruangan)) === mb_strtolower(trim((string) $payload['ruangan']));
            })->values();
        }

        $summary = [
            'guru' => $guruConflicts->count(),
            'kelas' => $kelasConflicts->count(),
            'ruangan' => $ruanganConflicts->count(),
        ];
        $summary['total'] = $summary['guru'] + $summary['kelas'] + $summary['ruangan'];

        return [
            'has_conflict' => $summary['total'] > 0,
            'summary' => $summary,
            'conflicts' => [
                'guru' => $guruConflicts->map(fn (JadwalMengajar $item): array => $this->transformConflictItem($item))->values(),
                'kelas' => $kelasConflicts->map(fn (JadwalMengajar $item): array => $this->transformConflictItem($item))->values(),
                'ruangan' => $ruanganConflicts->map(fn (JadwalMengajar $item): array => $this->transformConflictItem($item))->values(),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{0: bool, 1: string|null}
     */
    private function hydratePayloadTimeFromSetting(array &$payload): array
    {
        $hari = mb_strtolower(trim((string) ($payload['hari'] ?? '')));
        if (!array_key_exists($hari, self::DAY_LABELS)) {
            return [false, 'Hari jadwal tidak valid'];
        }
        $payload['hari'] = $hari;

        $semester = (string) ($payload['semester'] ?? 'full');
        if (!in_array($semester, ['ganjil', 'genap', 'full'], true)) {
            $semester = 'full';
        }
        $payload['semester'] = $semester;

        $payload['tahun_ajaran_id'] = $this->resolvePayloadTahunAjaranId($payload);
        $setting = $this->getScheduleSettingPayload(
            $payload['tahun_ajaran_id'] ? (int) $payload['tahun_ajaran_id'] : null,
            $semester
        );

        /** @var array<string, mixed>|null $dayConfig */
        $dayConfig = collect($setting['days'] ?? [])
            ->first(fn (array $row): bool => (string) ($row['hari'] ?? '') === $hari);

        $isSchoolDay = (bool) ($dayConfig['is_school_day'] ?? false);
        if (!$isSchoolDay) {
            return [false, self::DAY_LABELS[$hari] . ' bukan hari sekolah pada pengaturan jadwal'];
        }

        $daySlots = collect($setting['slot_templates'][$hari] ?? []);
        if ($daySlots->isEmpty()) {
            return [false, 'Pengaturan hari ini belum memiliki slot JP aktif'];
        }

        $hasJamKe = isset($payload['jam_ke']) && $payload['jam_ke'] !== null && (int) $payload['jam_ke'] > 0;
        if (!$hasJamKe) {
            return [false, 'Jam ke wajib dipilih sesuai slot pengaturan jadwal'];
        }

        $jamKe = (int) $payload['jam_ke'];
        /** @var array<string, mixed>|null $slot */
        $slot = $daySlots->first(fn (array $row): bool => (int) ($row['jp_ke'] ?? 0) === $jamKe);
        if (!$slot) {
            $maxJp = (int) $daySlots->max(fn (array $row): int => (int) ($row['jp_ke'] ?? 0));
            return [false, "JP ke-{$jamKe} tidak tersedia untuk " . self::DAY_LABELS[$hari] . ($maxJp > 0 ? " (maks JP {$maxJp})" : '')];
        }

        $payload['jam_mulai'] = (string) ($slot['jam_mulai'] ?? '');
        $payload['jam_selesai'] = (string) ($slot['jam_selesai'] ?? '');

        if ($payload['jam_mulai'] === '' || $payload['jam_selesai'] === '') {
            return [false, 'Slot JP tidak memiliki rentang waktu yang valid'];
        }

        return [true, null];
    }

    /**
     * Expand 1 input jadwal menjadi beberapa payload JP berurutan (blok).
     *
     * @param array<string, mixed> $payload
     * @return array{0: bool, 1: string|null, 2: array<int, array<string, mixed>>}
     */
    private function expandBlockPayloadsFromStart(array $payload): array
    {
        $jpCount = max(1, (int) ($payload['jp_count'] ?? 1));
        if ($jpCount === 1) {
            return [true, null, [$payload]];
        }

        $hari = (string) ($payload['hari'] ?? '');
        if (!array_key_exists($hari, self::DAY_LABELS)) {
            return [false, 'Hari jadwal tidak valid', []];
        }

        $semester = (string) ($payload['semester'] ?? 'full');
        $tahunAjaranId = !empty($payload['tahun_ajaran_id']) ? (int) $payload['tahun_ajaran_id'] : null;
        $setting = $this->getScheduleSettingPayload($tahunAjaranId, $semester);

        $daySlots = collect($setting['slot_templates'][$hari] ?? [])
            ->map(static function (array $row): array {
                return [
                    'jp_ke' => (int) ($row['jp_ke'] ?? 0),
                    'jam_mulai' => (string) ($row['jam_mulai'] ?? ''),
                    'jam_selesai' => (string) ($row['jam_selesai'] ?? ''),
                ];
            })
            ->filter(static fn (array $row): bool => $row['jp_ke'] > 0 && $row['jam_mulai'] !== '' && $row['jam_selesai'] !== '')
            ->sortBy('jp_ke')
            ->values();

        if ($daySlots->isEmpty()) {
            return [false, 'Pengaturan hari ini belum memiliki slot JP aktif', []];
        }

        $startJp = (int) ($payload['jam_ke'] ?? 0);
        if ($startJp <= 0) {
            return [false, 'Jam ke wajib dipilih sesuai slot pengaturan jadwal', []];
        }

        $maxJp = (int) $daySlots->max(fn (array $row): int => (int) ($row['jp_ke'] ?? 0));
        $expandedPayloads = [];

        for ($offset = 0; $offset < $jpCount; $offset++) {
            $targetJp = $startJp + $offset;
            /** @var array<string, mixed>|null $slot */
            $slot = $daySlots->first(fn (array $row): bool => (int) ($row['jp_ke'] ?? 0) === $targetJp);

            if (!$slot) {
                return [
                    false,
                    "Blok {$jpCount} JP dari JP ke-{$startJp} tidak tersedia untuk " . self::DAY_LABELS[$hari] . ($maxJp > 0 ? " (maks JP {$maxJp})" : ''),
                    [],
                ];
            }

            $nextPayload = $payload;
            $nextPayload['jam_ke'] = $targetJp;
            $nextPayload['jam_mulai'] = (string) ($slot['jam_mulai'] ?? '');
            $nextPayload['jam_selesai'] = (string) ($slot['jam_selesai'] ?? '');
            $expandedPayloads[] = $nextPayload;
        }

        return [true, null, $expandedPayloads];
    }

    private function applySemesterConflictScope(Builder $query, string $semester): void
    {
        if (!$this->hasJadwalColumn('semester')) {
            return;
        }

        $query->where(function ($builder) use ($semester): void {
            if ($semester === 'full') {
                $builder->whereIn('semester', ['ganjil', 'genap', 'full'])->orWhereNull('semester');
                return;
            }

            $builder->whereIn('semester', [$semester, 'full'])->orWhereNull('semester');
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayloadFromNormalized(array $payload): array
    {
        /** @var MataPelajaran|null $mapel */
        $mapel = MataPelajaran::find((int) $payload['mata_pelajaran_id']);
        /** @var Kelas|null $kelas */
        $kelas = Kelas::find((int) $payload['kelas_id']);
        $tahunAjaranId = $this->resolvePayloadTahunAjaranId($payload, $kelas);

        $data = [
            'guru_id' => (int) $payload['guru_id'],
            'kelas_id' => (int) $payload['kelas_id'],
            'hari' => (string) $payload['hari'],
            'jam_mulai' => (string) $payload['jam_mulai'],
            'jam_selesai' => (string) $payload['jam_selesai'],
        ];

        if ($this->hasJadwalColumn('mata_pelajaran_id')) {
            $data['mata_pelajaran_id'] = (int) $payload['mata_pelajaran_id'];
        }
        if ($this->hasJadwalColumn('mata_pelajaran')) {
            $data['mata_pelajaran'] = $mapel?->nama_mapel;
        }
        if ($this->hasJadwalColumn('tahun_ajaran_id')) {
            $data['tahun_ajaran_id'] = $tahunAjaranId ?: (int) ($kelas?->tahun_ajaran_id ?? 0);
        }
        if ($this->hasJadwalColumn('semester')) {
            $data['semester'] = (string) ($payload['semester'] ?? 'full');
        }
        if ($this->hasJadwalColumn('jam_ke')) {
            $data['jam_ke'] = !empty($payload['jam_ke']) ? (int) $payload['jam_ke'] : null;
        }
        if ($this->hasJadwalColumn('ruangan')) {
            $data['ruangan'] = !empty($payload['ruangan']) ? trim((string) $payload['ruangan']) : null;
        }
        if ($this->hasJadwalColumn('status')) {
            $data['status'] = (string) ($payload['status'] ?? 'draft');
        }
        if ($this->hasJadwalColumn('is_active')) {
            $data['is_active'] = array_key_exists('is_active', $payload) ? (bool) $payload['is_active'] : true;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolvePayloadTahunAjaranId(array $payload, ?Kelas $kelas = null): ?int
    {
        if (!empty($payload['tahun_ajaran_id']) && (int) $payload['tahun_ajaran_id'] > 0) {
            return (int) $payload['tahun_ajaran_id'];
        }

        if (!$kelas && !empty($payload['kelas_id']) && (int) $payload['kelas_id'] > 0) {
            /** @var Kelas|null $kelas */
            $kelas = Kelas::query()->select(['id', 'tahun_ajaran_id'])->find((int) $payload['kelas_id']);
        }

        if ($kelas && !empty($kelas->tahun_ajaran_id)) {
            return (int) $kelas->tahun_ajaran_id;
        }

        return $this->resolveActiveTahunAjaranId();
    }

    private function loadRelations(JadwalMengajar $item): JadwalMengajar
    {
        $relations = [
            'guru:id,nama_lengkap,nip,email',
            'kelas:id,nama_kelas,tingkat_id,tahun_ajaran_id',
            'kelas.tingkat:id,nama,kode',
            'tahunAjaran:id,nama,status',
        ];

        if ($this->hasJadwalColumn('mata_pelajaran_id')) {
            $relations[] = 'mataPelajaran:id,kode_mapel,nama_mapel,tingkat_id';
        }

        return $item->load($relations);
    }

    /**
     * @return array<string, mixed>
     */
    private function transformItem(JadwalMengajar $item): array
    {
        $mapelName = $item->mataPelajaran?->nama_mapel ?? $item->mata_pelajaran;
        $mapelCode = $item->mataPelajaran?->kode_mapel;
        $hari = strtolower((string) $item->hari);
        $status = $this->hasJadwalColumn('status') ? (string) $item->status : 'published';

        return [
            'id' => $item->id,
            'guru_id' => $item->guru_id,
            'guru' => $item->guru ? [
                'id' => $item->guru->id,
                'nama_lengkap' => $item->guru->nama_lengkap,
                'nip' => $item->guru->nip,
                'email' => $item->guru->email,
            ] : null,
            'kelas_id' => $item->kelas_id,
            'kelas' => $item->kelas ? [
                'id' => $item->kelas->id,
                'nama_kelas' => $item->kelas->nama_kelas,
                'tingkat_id' => $item->kelas->tingkat_id,
                'tingkat_nama' => optional($item->kelas->tingkat)->nama,
            ] : null,
            'mata_pelajaran_id' => $item->mata_pelajaran_id,
            'mata_pelajaran' => [
                'id' => $item->mata_pelajaran_id,
                'kode_mapel' => $mapelCode,
                'nama_mapel' => $mapelName,
            ],
            'tahun_ajaran_id' => $item->tahun_ajaran_id,
            'tahun_ajaran' => $item->tahunAjaran ? [
                'id' => $item->tahunAjaran->id,
                'nama' => $item->tahunAjaran->nama,
                'status' => $item->tahunAjaran->status,
            ] : null,
            'semester' => $this->hasJadwalColumn('semester') ? $item->semester : 'full',
            'hari' => $hari,
            'hari_label' => self::DAY_LABELS[$hari] ?? ucfirst($hari),
            'jam_mulai' => substr((string) $item->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $item->jam_selesai, 0, 5),
            'time_range' => substr((string) $item->jam_mulai, 0, 5) . ' - ' . substr((string) $item->jam_selesai, 0, 5),
            'jam_ke' => $this->hasJadwalColumn('jam_ke') ? $item->jam_ke : null,
            'ruangan' => $this->hasJadwalColumn('ruangan') ? $item->ruangan : null,
            'status' => $status,
            'status_label' => match ($status) {
                'draft' => 'Draft',
                'archived' => 'Archived',
                default => 'Published',
            },
            'is_active' => $this->hasJadwalColumn('is_active') ? (bool) $item->is_active : true,
            'created_at' => $item->created_at,
            'updated_at' => $item->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformConflictItem(JadwalMengajar $item): array
    {
        $hari = strtolower((string) $item->hari);

        return [
            'id' => $item->id,
            'hari' => $hari,
            'hari_label' => self::DAY_LABELS[$hari] ?? ucfirst($hari),
            'jam_mulai' => substr((string) $item->jam_mulai, 0, 5),
            'jam_selesai' => substr((string) $item->jam_selesai, 0, 5),
            'time_range' => substr((string) $item->jam_mulai, 0, 5) . ' - ' . substr((string) $item->jam_selesai, 0, 5),
            'guru' => $item->guru?->nama_lengkap,
            'kelas' => $item->kelas?->nama_kelas,
            'mapel' => $item->mataPelajaran?->nama_mapel ?? $item->mata_pelajaran,
            'ruangan' => $item->ruangan,
        ];
    }

    private function applyAccessScope(Builder $query, ?User $user, bool $forceSelfScope): bool
    {
        if (!$user) {
            $query->whereRaw('1 = 0');
            return false;
        }

        if (!$forceSelfScope && ($user->hasPermissionTo('manage_jadwal_pelajaran') || $this->hasGlobalJadwalView($user))) {
            return true;
        }

        $isGuru = $user->hasRole(RoleNames::aliases(RoleNames::GURU));
        $waliClassIds = $user->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS)) ? $this->normalizeIds($user->kelasWali()->pluck('id')->all()) : [];
        $siswaClassIds = $user->hasRole(RoleNames::aliases(RoleNames::SISWA)) ? $this->resolveStudentClassIds($user) : [];

        if (!$isGuru && $waliClassIds === [] && $siswaClassIds === []) {
            $query->whereRaw('1 = 0');
            return false;
        }

        $query->where(function ($builder) use ($isGuru, $siswaClassIds, $user, $waliClassIds): void {
            if ($isGuru) {
                $builder->orWhere('guru_id', $user->id);
            }
            if ($waliClassIds !== []) {
                $builder->orWhereIn('kelas_id', $waliClassIds);
            }
            if ($siswaClassIds !== []) {
                $builder->orWhereIn('kelas_id', $siswaClassIds);
            }
        });

        return true;
    }

    private function hasGlobalJadwalView(User $user): bool
    {
        return $user->hasRole(RoleNames::flattenAliases([
            RoleNames::SUPER_ADMIN,
            RoleNames::ADMIN,
            RoleNames::KEPALA_SEKOLAH,
            RoleNames::WAKASEK_KURIKULUM,
            RoleNames::WAKASEK_KESISWAAN,
            RoleNames::WAKASEK_HUMAS,
            RoleNames::WAKASEK_SARPRAS,
            RoleNames::GURU_BK,
        ]));
    }

    /**
     * @return array<int, int>
     */
    private function resolveStudentClassIds(User $user): array
    {
        if (!Schema::hasTable('kelas_siswa')) {
            return [];
        }

        $query = DB::table('kelas_siswa')->where('siswa_id', $user->id);
        if (Schema::hasColumn('kelas_siswa', 'status')) {
            $query->where('status', 'aktif');
        } elseif (Schema::hasColumn('kelas_siswa', 'is_active')) {
            $query->where('is_active', true);
        }

        return $this->normalizeIds($query->pluck('kelas_id')->all());
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private function normalizeIds(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            if (is_numeric($id) && (int) $id > 0) {
                $normalized[] = (int) $id;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param \Illuminate\Support\Collection<int, JadwalMengajar> $draftRows
     * @return array<string, mixed>
     */
    private function collectPublishConflicts($draftRows): array
    {
        $summary = ['guru' => 0, 'kelas' => 0, 'ruangan' => 0, 'total' => 0];
        $samples = ['guru' => [], 'kelas' => [], 'ruangan' => []];

        foreach ($draftRows as $item) {
            $payload = [
                'guru_id' => (int) $item->guru_id,
                'mata_pelajaran_id' => (int) $item->mata_pelajaran_id,
                'kelas_id' => (int) $item->kelas_id,
                'tahun_ajaran_id' => $this->hasJadwalColumn('tahun_ajaran_id') ? (int) $item->tahun_ajaran_id : null,
                'semester' => $this->hasJadwalColumn('semester') ? (string) ($item->semester ?: 'full') : 'full',
                'hari' => mb_strtolower((string) $item->hari),
                'jam_mulai' => substr((string) $item->jam_mulai, 0, 8),
                'jam_selesai' => substr((string) $item->jam_selesai, 0, 8),
                'ruangan' => $this->hasJadwalColumn('ruangan') ? (string) ($item->ruangan ?? '') : '',
            ];

            $conflicts = $this->detectConflicts($payload, (int) $item->id);
            if (!$conflicts['has_conflict']) {
                continue;
            }

            foreach (['guru', 'kelas', 'ruangan'] as $bucket) {
                $bucketItems = collect($conflicts['conflicts'][$bucket] ?? [])->values();
                if ($bucketItems->isEmpty()) {
                    continue;
                }

                $summary[$bucket] += $bucketItems->count();
                foreach ($bucketItems->take(3) as $conflictItem) {
                    $samples[$bucket][] = [
                        'draft_id' => (int) $item->id,
                        'hari' => mb_strtolower((string) $item->hari),
                        'jam_mulai' => substr((string) $item->jam_mulai, 0, 5),
                        'jam_selesai' => substr((string) $item->jam_selesai, 0, 5),
                        'kelas' => $item->kelas?->nama_kelas,
                        'guru' => $item->guru?->nama_lengkap,
                        'conflict' => $conflictItem,
                    ];
                }
            }
        }

        $summary['total'] = $summary['guru'] + $summary['kelas'] + $summary['ruangan'];

        return [
            'has_conflict' => $summary['total'] > 0,
            'summary' => $summary,
            'samples' => $samples,
        ];
    }

    private function resolveActiveTahunAjaranId(): ?int
    {
        /** @var TahunAjaran|null $active */
        $active = TahunAjaran::query()
            ->where('status', TahunAjaran::STATUS_ACTIVE)
            ->orderByDesc('id')
            ->first();

        if ($active instanceof TahunAjaran) {
            return $active->id;
        }

        /** @var TahunAjaran|null $active */
        $active = TahunAjaran::query()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();

        return $active?->id;
    }

    private function canUseScheduleSettingTables(): bool
    {
        return Schema::hasTable('jadwal_pelajaran_settings')
            && Schema::hasTable('jadwal_pelajaran_setting_days')
            && Schema::hasTable('jadwal_pelajaran_setting_breaks');
    }

    /**
     * @return array<string, mixed>
     */
    private function getScheduleSettingPayload(?int $tahunAjaranId, string $semester): array
    {
        if (!$tahunAjaranId) {
            return $this->defaultScheduleSettingPayload(null, $semester);
        }

        if (!$this->canUseScheduleSettingTables()) {
            return $this->defaultScheduleSettingPayload($tahunAjaranId, $semester);
        }

        /** @var JadwalPelajaranSetting|null $setting */
        $setting = JadwalPelajaranSetting::query()
            ->with(['days.breaks'])
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('semester', $semester)
            ->first();

        if (!$setting) {
            return $this->defaultScheduleSettingPayload($tahunAjaranId, $semester);
        }

        return $this->transformScheduleSetting($setting);
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultScheduleSettingPayload(?int $tahunAjaranId, string $semester): array
    {
        $default = [
            'id' => null,
            'tahun_ajaran_id' => $tahunAjaranId,
            'semester' => $semester,
            'default_jp_minutes' => 45,
            'default_start_time' => '07:00',
            'is_active' => true,
            'notes' => null,
            'days' => $this->defaultDaySettings(),
        ];

        $default['slot_templates'] = $this->buildSlotTemplates($default['default_jp_minutes'], $default['default_start_time'], $default['days']);

        return $default;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function defaultDaySettings(): array
    {
        return [
            [
                'id' => null,
                'hari' => 'senin',
                'hari_label' => 'Senin',
                'is_school_day' => true,
                'jp_count' => 10,
                'jp_minutes' => null,
                'start_time' => null,
                'notes' => null,
                'breaks' => [
                    ['id' => null, 'after_jp' => 2, 'break_minutes' => 15, 'label' => 'Istirahat 1'],
                    ['id' => null, 'after_jp' => 5, 'break_minutes' => 25, 'label' => 'Istirahat 2'],
                ],
            ],
            [
                'id' => null,
                'hari' => 'selasa',
                'hari_label' => 'Selasa',
                'is_school_day' => true,
                'jp_count' => 10,
                'jp_minutes' => null,
                'start_time' => null,
                'notes' => null,
                'breaks' => [
                    ['id' => null, 'after_jp' => 2, 'break_minutes' => 15, 'label' => 'Istirahat 1'],
                    ['id' => null, 'after_jp' => 5, 'break_minutes' => 25, 'label' => 'Istirahat 2'],
                ],
            ],
            [
                'id' => null,
                'hari' => 'rabu',
                'hari_label' => 'Rabu',
                'is_school_day' => true,
                'jp_count' => 10,
                'jp_minutes' => null,
                'start_time' => null,
                'notes' => null,
                'breaks' => [
                    ['id' => null, 'after_jp' => 2, 'break_minutes' => 15, 'label' => 'Istirahat 1'],
                    ['id' => null, 'after_jp' => 5, 'break_minutes' => 25, 'label' => 'Istirahat 2'],
                ],
            ],
            [
                'id' => null,
                'hari' => 'kamis',
                'hari_label' => 'Kamis',
                'is_school_day' => true,
                'jp_count' => 10,
                'jp_minutes' => null,
                'start_time' => null,
                'notes' => null,
                'breaks' => [
                    ['id' => null, 'after_jp' => 2, 'break_minutes' => 15, 'label' => 'Istirahat 1'],
                    ['id' => null, 'after_jp' => 5, 'break_minutes' => 25, 'label' => 'Istirahat 2'],
                ],
            ],
            [
                'id' => null,
                'hari' => 'jumat',
                'hari_label' => 'Jumat',
                'is_school_day' => true,
                'jp_count' => 8,
                'jp_minutes' => 40,
                'start_time' => null,
                'notes' => 'Hari khusus Jumat',
                'breaks' => [
                    ['id' => null, 'after_jp' => 2, 'break_minutes' => 20, 'label' => 'Istirahat 1'],
                    ['id' => null, 'after_jp' => 4, 'break_minutes' => 30, 'label' => 'Jeda Jumat'],
                ],
            ],
            [
                'id' => null,
                'hari' => 'sabtu',
                'hari_label' => 'Sabtu',
                'is_school_day' => false,
                'jp_count' => 0,
                'jp_minutes' => null,
                'start_time' => null,
                'notes' => null,
                'breaks' => [],
            ],
            [
                'id' => null,
                'hari' => 'minggu',
                'hari_label' => 'Minggu',
                'is_school_day' => false,
                'jp_count' => 0,
                'jp_minutes' => null,
                'start_time' => null,
                'notes' => null,
                'breaks' => [],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transformScheduleSetting(JadwalPelajaranSetting $setting): array
    {
        $days = $setting->days
            ->map(fn (JadwalPelajaranSettingDay $day): array => [
                'id' => $day->id,
                'hari' => $day->hari,
                'hari_label' => self::DAY_LABELS[$day->hari] ?? ucfirst((string) $day->hari),
                'is_school_day' => (bool) $day->is_school_day,
                'jp_count' => (int) $day->jp_count,
                'jp_minutes' => $day->jp_minutes !== null ? (int) $day->jp_minutes : null,
                'start_time' => $day->start_time ? substr((string) $day->start_time, 0, 5) : null,
                'notes' => $day->notes,
                'breaks' => $day->breaks->map(fn ($break): array => [
                    'id' => $break->id,
                    'after_jp' => (int) $break->after_jp,
                    'break_minutes' => (int) $break->break_minutes,
                    'label' => $break->label,
                ])->values()->all(),
            ])
            ->keyBy('hari');

        foreach (array_keys(self::DAY_LABELS) as $hari) {
            if (!$days->has($hari)) {
                $days->put($hari, [
                    'id' => null,
                    'hari' => $hari,
                    'hari_label' => self::DAY_LABELS[$hari],
                    'is_school_day' => false,
                    'jp_count' => 0,
                    'jp_minutes' => null,
                    'start_time' => null,
                    'notes' => null,
                    'breaks' => [],
                ]);
            }
        }

        $dayValues = $days->sortBy(function (array $day): int {
            return array_search($day['hari'], array_keys(self::DAY_LABELS), true);
        })->values()->all();

        return [
            'id' => $setting->id,
            'tahun_ajaran_id' => (int) $setting->tahun_ajaran_id,
            'semester' => $setting->semester,
            'default_jp_minutes' => (int) $setting->default_jp_minutes,
            'default_start_time' => substr((string) $setting->default_start_time, 0, 5),
            'is_active' => (bool) $setting->is_active,
            'notes' => $setting->notes,
            'days' => $dayValues,
            'slot_templates' => $this->buildSlotTemplates(
                (int) $setting->default_jp_minutes,
                substr((string) $setting->default_start_time, 0, 5),
                $dayValues
            ),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $days
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function buildSlotTemplates(int $defaultJpMinutes, string $defaultStartTime, array $days): array
    {
        $templates = [];

        foreach ($days as $day) {
            $hari = (string) ($day['hari'] ?? '');
            if ($hari === '') {
                continue;
            }

            $isSchoolDay = (bool) ($day['is_school_day'] ?? false);
            if (!$isSchoolDay) {
                $templates[$hari] = [];
                continue;
            }

            $jpCount = max(0, (int) ($day['jp_count'] ?? 0));
            $jpMinutes = (int) ($day['jp_minutes'] ?? $defaultJpMinutes);
            if ($jpMinutes <= 0 || $jpCount <= 0) {
                $templates[$hari] = [];
                continue;
            }

            $startTime = (string) ($day['start_time'] ?: $defaultStartTime);
            $cursor = Carbon::createFromFormat('H:i', $startTime);
            $breakMap = [];
            foreach (($day['breaks'] ?? []) as $breakRow) {
                $afterJp = (int) ($breakRow['after_jp'] ?? 0);
                $duration = (int) ($breakRow['break_minutes'] ?? 0);
                if ($afterJp > 0 && $duration > 0) {
                    $breakMap[$afterJp] = ($breakMap[$afterJp] ?? 0) + $duration;
                }
            }

            $slots = [];
            for ($jp = 1; $jp <= $jpCount; $jp++) {
                $slotStart = $cursor->copy();
                $slotEnd = $cursor->copy()->addMinutes($jpMinutes);

                $slots[] = [
                    'jp_ke' => $jp,
                    'jam_mulai' => $slotStart->format('H:i'),
                    'jam_selesai' => $slotEnd->format('H:i'),
                    'label' => sprintf('JP %d (%s - %s)', $jp, $slotStart->format('H:i'), $slotEnd->format('H:i')),
                ];

                $cursor = $slotEnd;
                if (isset($breakMap[$jp])) {
                    $cursor->addMinutes((int) $breakMap[$jp]);
                }
            }

            $templates[$hari] = $slots;
        }

        return $templates;
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
            if ($rowIndex > 30 || !is_array($rowValues)) {
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

    private function normalizeHariFromImport(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return array_key_exists($normalized, self::DAY_LABELS) ? $normalized : null;
    }

    private function normalizeSemesterFromImport(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        if (in_array($normalized, ['ganjil', '1'], true)) {
            return 'ganjil';
        }
        if (in_array($normalized, ['genap', '2'], true)) {
            return 'genap';
        }
        if (in_array($normalized, ['full', 'semua', 'all'], true)) {
            return 'full';
        }

        return null;
    }

    private function normalizeJadwalStatusFromImport(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $normalized = mb_strtolower(trim($value));
        if (in_array($normalized, ['draft'], true)) {
            return 'draft';
        }
        if (in_array($normalized, ['published', 'publish', 'aktif'], true)) {
            return 'published';
        }
        if (in_array($normalized, ['archived', 'arsip'], true)) {
            return 'archived';
        }

        return null;
    }

    private function parseBooleanImportValue(string $value, ?bool $default = null): ?bool
    {
        if ($value === '') {
            return $default;
        }

        $normalized = mb_strtolower(trim($value));
        if (in_array($normalized, ['1', 'true', 'ya', 'yes', 'aktif'], true)) {
            return true;
        }
        if (in_array($normalized, ['0', 'false', 'tidak', 'no', 'nonaktif', 'tidak_aktif'], true)) {
            return false;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function findExistingScheduleForImport(array $payload): ?JadwalMengajar
    {
        $query = JadwalMengajar::query()
            ->where('guru_id', (int) $payload['guru_id'])
            ->where('kelas_id', (int) $payload['kelas_id'])
            ->where('hari', (string) $payload['hari']);

        if ($this->hasJadwalColumn('tahun_ajaran_id')) {
            $query->where('tahun_ajaran_id', (int) $payload['tahun_ajaran_id']);
        }
        if ($this->hasJadwalColumn('semester')) {
            $query->where('semester', (string) $payload['semester']);
        }
        if ($this->hasJadwalColumn('jam_ke')) {
            $query->where('jam_ke', (int) $payload['jam_ke']);
        } else {
            $query->where('jam_mulai', (string) $payload['jam_mulai'])
                ->where('jam_selesai', (string) $payload['jam_selesai']);
        }

        if ($this->hasJadwalColumn('mata_pelajaran_id')) {
            $query->where('mata_pelajaran_id', (int) $payload['mata_pelajaran_id']);
        } elseif ($this->hasJadwalColumn('mata_pelajaran')) {
            $mapel = MataPelajaran::query()->find((int) $payload['mata_pelajaran_id']);
            if (!$mapel) {
                return null;
            }
            $query->where('mata_pelajaran', $mapel->nama_mapel);
        }

        return $query->first();
    }

    /**
     * @return array<int, array{key:string,label:string,width:int}>
     */
    private function jadwalExportColumns(): array
    {
        return [
            ['key' => 'no', 'label' => 'No', 'width' => 7],
            ['key' => 'hari', 'label' => 'Hari', 'width' => 12],
            ['key' => 'jam_ke', 'label' => 'JP Ke', 'width' => 10],
            ['key' => 'jam_mulai', 'label' => 'Jam Mulai', 'width' => 12],
            ['key' => 'jam_selesai', 'label' => 'Jam Selesai', 'width' => 12],
            ['key' => 'kelas', 'label' => 'Kelas', 'width' => 14],
            ['key' => 'mapel_kode', 'label' => 'Kode Mapel', 'width' => 14],
            ['key' => 'mapel_nama', 'label' => 'Mata Pelajaran', 'width' => 28],
            ['key' => 'guru_nama', 'label' => 'Guru', 'width' => 26],
            ['key' => 'guru_nip', 'label' => 'NIP Guru', 'width' => 22],
            ['key' => 'ruangan', 'label' => 'Ruangan', 'width' => 14],
            ['key' => 'tahun_ajaran', 'label' => 'Tahun Ajaran', 'width' => 18],
            ['key' => 'semester', 'label' => 'Semester', 'width' => 12],
            ['key' => 'status', 'label' => 'Status', 'width' => 14],
            ['key' => 'is_active', 'label' => 'Aktif', 'width' => 12],
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

    private function buildJadwalFilterSummary(Request $request): string
    {
        $parts = [];

        if ($request->filled('search')) {
            $parts[] = 'Pencarian: ' . trim((string) $request->search);
        }
        if ($request->filled('tahun_ajaran_id')) {
            $tahun = TahunAjaran::query()->find((int) $request->tahun_ajaran_id);
            $parts[] = 'Tahun Ajaran: ' . ($tahun?->nama ?? ('#' . (int) $request->tahun_ajaran_id));
        }
        if ($request->filled('semester')) {
            $parts[] = 'Semester: ' . ucfirst((string) $request->semester);
        }
        if ($request->filled('kelas_id')) {
            $kelas = Kelas::query()->find((int) $request->kelas_id);
            $parts[] = 'Kelas: ' . ($kelas?->nama_kelas ?? ('#' . (int) $request->kelas_id));
        }
        if ($request->filled('guru_id')) {
            $guru = User::query()->find((int) $request->guru_id);
            $parts[] = 'Guru: ' . ($guru?->nama_lengkap ?? ('#' . (int) $request->guru_id));
        }
        if ($request->filled('mata_pelajaran_id')) {
            $mapel = MataPelajaran::query()->find((int) $request->mata_pelajaran_id);
            $parts[] = 'Mapel: ' . ($mapel?->nama_mapel ?? ('#' . (int) $request->mata_pelajaran_id));
        }
        if ($request->filled('hari')) {
            $day = mb_strtolower((string) $request->hari);
            $parts[] = 'Hari: ' . (self::DAY_LABELS[$day] ?? ucfirst($day));
        }
        if ($request->filled('status')) {
            $parts[] = 'Status: ' . match ((string) $request->status) {
                'draft' => 'Draft',
                'archived' => 'Archived',
                default => 'Published',
            };
        }
        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $parts[] = 'Aktif: ' . ($isActive ? 'Ya' : 'Tidak');
            }
        }

        return $parts !== [] ? implode(' | ', $parts) : 'Semua data';
    }

    private function hasJadwalColumn(string $column): bool
    {
        return Schema::hasColumn('jadwal_mengajar', $column);
    }
}
