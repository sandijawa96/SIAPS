<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DapodikEntityMapping;
use App\Models\DapodikSyncBatch;
use App\Models\Kelas;
use App\Models\RuntimeSetting;
use App\Models\TahunAjaran;
use App\Models\Tingkat;
use App\Models\User;
use App\Services\WaliKelasRoleService;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Throwable;

class DapodikController extends Controller
{
    private const SETTINGS_NAMESPACE = 'dapodik';
    private const DEFAULT_BASE_URL = 'http://182.253.36.196:5885';
    private const DEFAULT_PROBE_PATH = '/';
    private const STUDENT_EMAIL_DOMAIN = 'sman1sumbercirebon.sch.id';
    private const EMPLOYEE_EMAIL_DOMAIN = 'sman1sumbercirebon.sch.id';
    private const STUDENT_ENDPOINT = '/WebService/getPesertaDidik';
    private const EMPLOYEE_ENDPOINT = '/WebService/getGtk';
    private const CLASS_ENDPOINT = '/WebService/getRombonganBelajar';
    private const USER_ENDPOINT = '/WebService/getPengguna';

    private array $schemaColumnCache = [];

    public function settings(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->settingsPayload(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'base_url' => 'required|url|max:2048',
            'npsn' => 'nullable|string|max:20',
            'api_token' => 'nullable|string|max:2048',
            'clear_api_token' => 'nullable|boolean',
            'probe_path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Pengaturan Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $this->putSetting('base_url', $this->normalizeBaseUrl((string) $validated['base_url']));
        $this->putSetting('npsn', trim((string) ($validated['npsn'] ?? '')));
        $this->putSetting('probe_path', $this->normalizeProbePath($validated['probe_path'] ?? self::DEFAULT_PROBE_PATH));

        $token = trim((string) ($validated['api_token'] ?? ''));
        if (($validated['clear_api_token'] ?? false) === true) {
            $this->putSetting('api_token', null);
        } elseif ($token !== '') {
            $this->putSetting('api_token', $token);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan koneksi Dapodik berhasil disimpan',
            'data' => $this->settingsPayload(),
        ]);
    }

    public function testConnection(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'base_url' => 'nullable|url|max:2048',
            'npsn' => 'nullable|string|max:20',
            'api_token' => 'nullable|string|max:2048',
            'probe_path' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter test koneksi Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $baseUrl = $this->normalizeBaseUrl((string) ($validated['base_url'] ?? $this->getSetting('base_url', self::DEFAULT_BASE_URL)));
        $probePath = $this->normalizeProbePath($validated['probe_path'] ?? $this->getSetting('probe_path', self::DEFAULT_PROBE_PATH));
        $npsn = trim((string) ($validated['npsn'] ?? $this->getSetting('npsn', '')));
        $token = trim((string) ($validated['api_token'] ?? ''));
        if ($token === '') {
            $token = $this->getApiToken();
        }

        $result = $this->probeEndpoint($baseUrl, $probePath, $npsn, $token);
        $this->saveLastTest($result);

        return response()->json([
            'success' => $result['reachable'],
            'message' => $result['message'],
            'data' => $result,
        ], $result['reachable'] ? 200 : 422);
    }

    public function createStagingBatch(Request $request): JsonResponse
    {
        $sourceDefinitions = $this->stagingSourceDefinitions();

        $validator = Validator::make($request->all(), [
            'base_url' => 'nullable|url|max:2048',
            'npsn' => 'nullable|string|max:20',
            'api_token' => 'nullable|string|max:2048',
            'sources' => 'nullable|array|max:' . count($sourceDefinitions),
            'sources.*' => 'required|string|in:' . implode(',', array_keys($sourceDefinitions)),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter batch staging Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $baseUrl = $this->normalizeBaseUrl((string) ($validated['base_url'] ?? $this->getSetting('base_url', self::DEFAULT_BASE_URL)));
        $npsn = trim((string) ($validated['npsn'] ?? $this->getSetting('npsn', '')));
        $token = trim((string) ($validated['api_token'] ?? ''));
        if ($token === '') {
            $token = $this->getApiToken();
        }

        $sources = $this->normalizeStagingSources($validated['sources'] ?? $this->defaultStagingSources());
        if ($sources === []) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal satu sumber Dapodik harus dipilih',
            ], 422);
        }

        $batch = DapodikSyncBatch::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => 'running',
            'base_url' => $baseUrl,
            'npsn' => $npsn,
            'requested_by' => $request->user()?->id,
            'started_at' => now(),
            'totals' => $this->stagingTotalsFromSourceStatuses($sources, []),
            'meta' => [
                'sources' => $sources,
                'mode' => 'staging_only_progress',
                'token_stored' => $token !== '',
                'source_statuses' => $this->queuedSourceStatuses($sources),
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch staging Dapodik dibuat.',
            'data' => [
                'batch' => $this->stagingBatchPayload($batch),
                'safeguards' => $this->stagingSafeguards(),
            ],
        ]);
    }

    public function showStagingBatch(DapodikSyncBatch $batch): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Status batch staging Dapodik berhasil dimuat.',
            'data' => [
                'batch' => $this->stagingBatchPayload($batch),
                'safeguards' => $this->stagingSafeguards(),
            ],
        ]);
    }

    public function fetchStagingBatchSource(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $sourceDefinitions = $this->stagingSourceDefinitions();

        $validator = Validator::make($request->all(), [
            'source' => 'required|string|in:' . implode(',', array_keys($sourceDefinitions)),
            'base_url' => 'nullable|url|max:2048',
            'npsn' => 'nullable|string|max:20',
            'api_token' => 'nullable|string|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter sumber staging Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $source = (string) $validated['source'];
        $definition = $sourceDefinitions[$source];
        $baseUrl = $this->normalizeBaseUrl((string) ($validated['base_url'] ?? $batch->base_url ?? $this->getSetting('base_url', self::DEFAULT_BASE_URL)));
        $npsn = trim((string) ($validated['npsn'] ?? $batch->npsn ?? $this->getSetting('npsn', '')));
        $token = trim((string) ($validated['api_token'] ?? ''));
        if ($token === '') {
            $token = $this->getApiToken();
        }

        $batch = $this->markStagingSource($batch, $source, [
            'status' => 'running',
            'started_at' => now()->toISOString(),
            'finished_at' => null,
            'endpoint' => $definition['endpoint'],
            'message' => 'Mengambil data dari Dapodik.',
            'error' => null,
        ], [
            'status' => 'running',
            'base_url' => $baseUrl,
            'npsn' => $npsn,
            'finished_at' => null,
        ]);

        DB::table('dapodik_sync_records')
            ->where('batch_id', $batch->id)
            ->where('source', $source)
            ->delete();

        $fetch = $this->fetchDapodikRows($baseUrl, $definition['endpoint'], $npsn, $token);
        $recordsStored = 0;

        if ($fetch['success']) {
            $dapodikUserRows = $source === 'dapodik_users'
                ? $fetch['rows']
                : ($this->loadStagedRowsForBatch($batch, ['dapodik_users'])['dapodik_users'] ?? []);

            $recordsStored = $this->storeStagingRecords(
                $batch,
                $source,
                $fetch['rows'],
                $definition,
                $dapodikUserRows
            );
        }

        $batch = $this->markStagingSource($batch, $source, [
            'status' => $fetch['success'] ? 'completed' : 'failed',
            'finished_at' => now()->toISOString(),
            'endpoint' => $definition['endpoint'],
            'status_code' => $fetch['status_code'] ?? null,
            'duration_ms' => $fetch['duration_ms'] ?? null,
            'row_count' => $fetch['row_count'] ?? 0,
            'records_stored' => $recordsStored,
            'message' => $fetch['message'] ?? null,
            'error' => $fetch['success'] ? null : ($fetch['error'] ?? $fetch['message'] ?? 'Endpoint Dapodik belum dapat dibaca.'),
        ]);

        return response()->json([
            'success' => (bool) $fetch['success'],
            'message' => $fetch['success']
                ? 'Sumber Dapodik berhasil disimpan ke staging.'
                : 'Sumber Dapodik gagal disimpan ke staging.',
            'data' => [
                'batch' => $this->stagingBatchPayload($batch),
                'source' => $source,
                'fetch' => collect($fetch)->except('rows')->all(),
                'records_stored' => $recordsStored,
                'safeguards' => $this->stagingSafeguards(),
            ],
        ]);
    }

    public function finalizeStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'base_url' => 'nullable|url|max:2048',
            'npsn' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter finalisasi staging Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $sourceRows = $this->loadStagedRowsForBatch($batch);
        $recordsStored = collect($sourceRows)->sum(fn (array $rows) => count($rows));
        $errors = $batch->errors ?: [];
        $mappingSummary = [
            'enabled' => false,
            'message' => 'Mapping lokal belum dijalankan.',
            'counts' => [],
        ];

        if ($recordsStored === 0) {
            $batch->update([
                'status' => 'failed',
                'finished_at' => now(),
                'errors' => ['staging' => 'Batch belum memiliki record staging.'],
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Finalisasi gagal karena batch belum memiliki record staging.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch->fresh()),
                    'mapping' => $mappingSummary,
                    'safeguards' => $this->stagingSafeguards(),
                ],
            ], 422);
        }

        try {
            $context = $this->buildLocalMappingContext();
            $dapodikUsers = $this->buildDapodikUserIndexes($sourceRows['dapodik_users'] ?? []);
            $mappingSummary = [
                'enabled' => true,
                'message' => 'Mapping kandidat Dapodik ke SIAPS selesai.',
                'counts' => $this->refreshStagingMappings($batch, $sourceRows, $context, $dapodikUsers),
            ];
        } catch (Throwable $exception) {
            $errors['mapping'] = $exception->getMessage();
            $mappingSummary['message'] = 'Mapping lokal belum dapat dijalankan.';
        }

        $meta = $batch->meta ?: [];
        $meta['mapping'] = $mappingSummary;
        $sources = $this->normalizeStagingSources($meta['sources'] ?? $this->defaultStagingSources());
        $sourceStatuses = is_array($meta['source_statuses'] ?? null) ? $meta['source_statuses'] : [];
        $failedSources = collect($sourceStatuses)->where('status', 'failed')->count();
        $completedSources = collect($sourceStatuses)->where('status', 'completed')->count();
        $status = $errors !== []
            ? 'partial'
            : ($completedSources >= count($sources) && $failedSources === 0 ? 'completed' : 'partial');

        $batch->update([
            'status' => $status,
            'finished_at' => now(),
            'totals' => $this->stagingTotalsFromSourceStatuses($sources, $sourceStatuses),
            'errors' => $errors ?: null,
            'meta' => $meta,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Batch staging Dapodik selesai difinalisasi. Belum ada data final SIAPS yang diubah.',
            'data' => [
                'batch' => $this->stagingBatchPayload($batch->fresh()),
                'mapping' => $mappingSummary,
                'safeguards' => $this->stagingSafeguards(),
            ],
        ]);
    }

    public function reviewStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'nullable|string|in:student,employee,class',
            'confidence' => 'nullable|string|in:problem,exact,probable,conflict,unmatched',
            'limit' => 'nullable|integer|min:5|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter review staging Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $limit = (int) ($validated['limit'] ?? 30);
        $summary = $this->stagingReviewSummary($batch);
        $review = $this->stagingReviewItems(
            $batch,
            $validated['entity_type'] ?? null,
            $validated['confidence'] ?? null,
            $limit
        );

        return response()->json([
            'success' => true,
            'message' => 'Review mapping staging Dapodik berhasil dimuat.',
            'data' => [
                'batch' => $this->stagingBatchPayload($batch),
                'filters' => [
                    'entity_type' => $validated['entity_type'] ?? null,
                    'confidence' => $validated['confidence'] ?? null,
                    'limit' => $limit,
                ],
                'summary' => $summary,
                'items' => $review['items'],
                'has_more' => $review['has_more'],
                'safeguards' => $this->stagingSafeguards(),
                'apply_policy' => [
                    'exact' => 'Boleh dipakai untuk kandidat apply otomatis setelah preview apply dibuat.',
                    'probable' => 'Wajib direview manual sebelum apply.',
                    'conflict' => 'Tidak boleh apply sebelum konflik diselesaikan.',
                    'unmatched' => 'Tidak boleh update data existing; hanya kandidat create setelah validasi.',
                    'class_membership' => 'Relasi kelas siswa tetap wajib review terpisah.',
                ],
            ],
        ]);
    }

    public function previewApplyStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'nullable|string|in:student,employee',
            'limit' => 'nullable|integer|min:5|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter preview apply Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $entityTypes = isset($validated['entity_type'])
            ? [(string) $validated['entity_type']]
            : ['student', 'employee'];
        $limit = (int) ($validated['limit'] ?? 30);

        $allMappings = $this->stagingApplyMappings($batch, $entityTypes);

        $allItems = $this->stagingApplyPreviewItems($batch, $allMappings);
        $items = $allItems->take($limit)->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Preview apply Dapodik berhasil dihitung. Belum ada data final SIAPS yang diubah.',
            'data' => [
                'batch' => $this->stagingBatchPayload($batch),
                'filters' => [
                    'entity_types' => $entityTypes,
                    'confidence' => 'exact',
                    'limit' => $limit,
                ],
                'summary' => $this->stagingApplyPreviewSummary($batch, $entityTypes, $allItems),
                'items' => $items,
                'has_more' => $allItems->count() > $limit,
                'policy' => [
                    'mode' => 'read_only_preview',
                    'included' => ['student exact existing user', 'employee exact existing user'],
                    'excluded' => ['probable', 'conflict', 'unmatched', 'class', 'kelas_siswa', 'new user creation'],
                    'final_tables_untouched' => ['users', 'data_pribadi_siswa', 'data_kepegawaian', 'kelas', 'kelas_siswa'],
                ],
                'safeguards' => $this->stagingSafeguards(),
            ],
        ]);
    }

    public function applyStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'nullable|string|in:student,employee',
            'confirm_apply' => 'required|accepted',
            'mapping_ids' => 'nullable|array|min:1',
            'mapping_ids.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter apply Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!in_array($batch->status, ['completed', 'partial'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Batch staging belum selesai. Jalankan finalisasi staging sebelum apply.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch),
                ],
            ], 409);
        }

        $validated = $validator->validated();
        $entityTypes = isset($validated['entity_type'])
            ? [(string) $validated['entity_type']]
            : ['student', 'employee'];
        $mappingIds = collect($validated['mapping_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        try {
            $mappings = $this->filterMappingsByIds(
                $this->stagingApplyMappings($batch, $entityTypes),
                $mappingIds
            );
            $items = $this->stagingApplyPreviewItems($batch, $mappings);
            $apply = DB::transaction(function () use ($batch, $items, $entityTypes, $request) {
                $result = $this->applyPreviewItems($items);
                $meta = is_array($batch->meta) ? $batch->meta : [];
                $meta['last_apply'] = [
                    'applied_at' => now()->toISOString(),
                    'applied_by' => $request->user()?->id,
                    'entity_types' => $entityTypes,
                    'summary' => $result['summary'],
                ];

                $batch->forceFill(['meta' => $meta])->save();

                return $result;
            });

            return response()->json([
                'success' => true,
                'message' => 'Apply Dapodik selesai. Hanya field aman yang diubah.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch->refresh()),
                    'filters' => [
                        'entity_types' => $entityTypes,
                        'confidence' => 'exact',
                        'mapping_ids_count' => count($mappingIds),
                    ],
                    'summary' => $apply['summary'],
                    'items' => $apply['items'],
                    'has_more' => $apply['has_more'],
                    'policy' => [
                        'mode' => 'safe_apply',
                        'included' => ['student exact existing user', 'employee exact existing user'],
                        'excluded' => ['probable', 'conflict', 'unmatched', 'class', 'kelas_siswa', 'new user creation', 'unsafe fields'],
                        'unsafe_fields_skipped' => ['users.email'],
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            $errorContext = $this->exceptionContextPayload($e, 'apply_staging_batch');

            Log::error('Dapodik apply failed', [
                'batch_id' => $batch->id,
                'entity_types' => $entityTypes ?? [],
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'error_context' => $errorContext,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Apply Dapodik gagal. Tidak ada perubahan parsial yang disimpan.',
                'error' => $e->getMessage(),
                'error_context' => $errorContext,
            ], 500);
        }
    }

    public function previewInputStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'nullable|string|in:student,employee',
            'limit' => 'nullable|integer|min:5|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter preview input Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!in_array($batch->status, ['completed', 'partial'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Batch staging belum selesai. Jalankan pengambilan data Dapodik sampai finalisasi selesai.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch),
                ],
            ], 409);
        }

        $validated = $validator->validated();
        $entityTypes = isset($validated['entity_type'])
            ? [(string) $validated['entity_type']]
            : ['student', 'employee'];
        $limit = (int) ($validated['limit'] ?? 30);

        $mappings = $this->stagingInputMappings($batch, $entityTypes);
        $allItems = $this->stagingInputPreviewItems($batch, $mappings);
        $items = $allItems->take($limit)->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Preview input data baru Dapodik berhasil dihitung. Belum ada user baru yang dibuat.',
            'data' => [
                'batch' => $this->stagingBatchPayload($batch),
                'filters' => [
                    'entity_types' => $entityTypes,
                    'confidence' => 'unmatched',
                    'limit' => $limit,
                ],
                'summary' => $this->stagingInputPreviewSummary($batch, $entityTypes, $allItems),
                'items' => $items,
                'has_more' => $allItems->count() > $limit,
                'policy' => [
                    'mode' => 'input_preview',
                    'included' => ['student unmatched', 'employee unmatched'],
                    'excluded' => ['exact existing user', 'probable', 'conflict', 'class', 'kelas_siswa'],
                    'password_policy' => [
                        'student' => 'Tanggal lahir siswa format DDMMYYYY.',
                        'employee' => 'Password default pegawai mengikuti standar import pegawai SIAPS.',
                    ],
                    'employee_roles' => 'Role pegawai berisiko tinggi tidak diberikan otomatis; kandidat default turun ke Pegawai.',
                ],
            ],
        ]);
    }

    public function inputStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'entity_type' => 'nullable|string|in:student,employee',
            'confirm_input' => 'required|accepted',
            'mapping_ids' => 'nullable|array|min:1',
            'mapping_ids.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter input Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!in_array($batch->status, ['completed', 'partial'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Batch staging belum selesai. Jalankan pengambilan data Dapodik sampai finalisasi selesai.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch),
                ],
            ], 409);
        }

        $validated = $validator->validated();
        $entityTypes = isset($validated['entity_type'])
            ? [(string) $validated['entity_type']]
            : ['student', 'employee'];
        $mappingIds = collect($validated['mapping_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        try {
            $input = DB::transaction(function () use ($batch, $entityTypes, $request, $mappingIds) {
                $mappings = $this->filterMappingsByIds(
                    $this->stagingInputMappings($batch, $entityTypes),
                    $mappingIds
                );
                $items = $this->stagingInputPreviewItems($batch, $mappings);
                $result = $this->createInputItems($items, $request->user()?->id);
                $meta = is_array($batch->meta) ? $batch->meta : [];
                $meta['last_input'] = [
                    'input_at' => now()->toISOString(),
                    'input_by' => $request->user()?->id,
                    'entity_types' => $entityTypes,
                    'summary' => $result['summary'],
                ];

                $batch->forceFill(['meta' => $meta])->save();

                return $result;
            });

            return response()->json([
                'success' => true,
                'message' => 'Input data baru Dapodik selesai.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch->refresh()),
                    'filters' => [
                        'entity_types' => $entityTypes,
                        'confidence' => 'unmatched',
                        'mapping_ids_count' => count($mappingIds),
                    ],
                    'summary' => $input['summary'],
                    'items' => $input['items'],
                    'has_more' => $input['has_more'],
                    'policy' => [
                        'mode' => 'create_users_from_unmatched',
                        'included' => ['student unmatched', 'employee unmatched'],
                        'excluded' => ['exact existing user', 'probable', 'conflict', 'class', 'kelas_siswa'],
                    ],
                ],
            ], 201);
        } catch (Throwable $e) {
            $errorContext = $this->exceptionContextPayload($e, 'input_staging_batch');

            Log::error('Dapodik input failed', [
                'batch_id' => $batch->id,
                'entity_types' => $entityTypes ?? [],
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'error_context' => $errorContext,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Input data baru Dapodik gagal. Tidak ada perubahan parsial yang disimpan.',
                'error' => $e->getMessage(),
                'error_context' => $errorContext,
            ], 500);
        }
    }

    public function previewClassStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'action' => 'nullable|string|in:update_candidate,create_candidate,manual_review,blocked,no_change',
            'limit' => 'nullable|integer|min:5|max:5000',
            'target_tahun_ajaran_id' => 'nullable|integer|exists:tahun_ajaran,id',
            'mapping_ids' => 'nullable|array|min:1',
            'mapping_ids.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter preview kelas Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!in_array($batch->status, ['completed', 'partial'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Batch staging belum selesai. Jalankan finalisasi staging sebelum preview kelas.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch),
                ],
            ], 409);
        }

        $validated = $validator->validated();
        $limit = (int) ($validated['limit'] ?? 100);
        $targetTahunAjaranId = isset($validated['target_tahun_ajaran_id']) ? (int) $validated['target_tahun_ajaran_id'] : null;
        $mappingIds = collect($validated['mapping_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $mappings = $this->filterMappingsByIds(
            $this->stagingClassMappings($batch),
            $mappingIds
        );

        $allItems = $this->stagingClassPreviewItems($batch, $mappings, $targetTahunAjaranId);

        if (!empty($validated['action'])) {
            $allItems = $allItems
                ->filter(fn (array $item) => ($item['action'] ?? null) === $validated['action'])
                ->values();
        }

        return response()->json([
            'success' => true,
            'message' => 'Preview master kelas Dapodik berhasil dihitung.',
            'data' => [
                'batch' => $this->stagingBatchPayload($batch),
                'filters' => [
                    'action' => $validated['action'] ?? null,
                    'limit' => $limit,
                    'mapping_ids_count' => count($mappingIds),
                    'target_tahun_ajaran_id' => $targetTahunAjaranId,
                ],
                'target_tahun_ajaran' => $this->targetTahunAjaranResponsePayload($targetTahunAjaranId),
                'summary' => $this->stagingClassPreviewSummary($batch, $allItems),
                'items' => $allItems->take($limit)->values()->all(),
                'has_more' => $allItems->count() > $limit,
                'policy' => [
                    'mode' => 'class_preview',
                    'selection_required_for_sync' => true,
                    'included' => ['class exact existing class', 'class unmatched candidate'],
                    'excluded' => ['probable auto sync', 'conflict auto sync'],
                ],
            ],
        ]);
    }

    public function syncClassStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'mode' => 'required|string|in:update,input',
            'confirm_sync' => 'required|accepted',
            'target_tahun_ajaran_id' => 'nullable|integer|exists:tahun_ajaran,id',
            'mapping_ids' => 'required|array|min:1',
            'mapping_ids.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter sinkronisasi kelas Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!in_array($batch->status, ['completed', 'partial'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Batch staging belum selesai. Jalankan finalisasi staging sebelum sinkronisasi kelas.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch),
                ],
            ], 409);
        }

        $validated = $validator->validated();
        $mode = (string) $validated['mode'];
        $targetTahunAjaranId = isset($validated['target_tahun_ajaran_id']) ? (int) $validated['target_tahun_ajaran_id'] : null;
        $mappingIds = collect($validated['mapping_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        try {
            $mappings = $this->filterMappingsByIds($this->stagingClassMappings($batch), $mappingIds);
            if ($mappings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada kelas terpilih yang valid untuk diproses.',
                ], 422);
            }

            $items = $this->stagingClassPreviewItems($batch, $mappings, $targetTahunAjaranId);
            $result = DB::transaction(function () use ($batch, $items, $mode, $request, $mappingIds, $targetTahunAjaranId) {
                $sync = $this->syncClassPreviewItems($items, $mode, $batch, $request->user()?->id);
                $meta = is_array($batch->meta) ? $batch->meta : [];
                $meta['last_class_sync'] = [
                    'synced_at' => now()->toISOString(),
                    'synced_by' => $request->user()?->id,
                    'mode' => $mode,
                    'target_tahun_ajaran_id' => $targetTahunAjaranId,
                    'mapping_ids_count' => count($mappingIds),
                    'summary' => $sync['summary'],
                ];

                $batch->forceFill(['meta' => $meta])->save();

                return $sync;
            });

            return response()->json([
                'success' => true,
                'message' => $mode === 'update'
                    ? 'Sinkronisasi update master kelas selesai.'
                    : 'Sinkronisasi input master kelas selesai.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch->refresh()),
                    'filters' => [
                        'mode' => $mode,
                        'mapping_ids_count' => count($mappingIds),
                        'target_tahun_ajaran_id' => $targetTahunAjaranId,
                    ],
                    'target_tahun_ajaran' => $this->targetTahunAjaranResponsePayload($targetTahunAjaranId),
                    'summary' => $result['summary'],
                    'items' => $result['items'],
                    'has_more' => $result['has_more'],
                ],
            ]);
        } catch (Throwable $e) {
            $errorContext = $this->exceptionContextPayload($e, 'class_sync');

            Log::error('Dapodik class sync failed', [
                'batch_id' => $batch->id,
                'mode' => $mode,
                'mapping_ids' => $mappingIds,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'error_context' => $errorContext,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sinkronisasi master kelas gagal. Tidak ada perubahan parsial yang disimpan.',
                'error' => $e->getMessage(),
                'error_context' => $errorContext,
            ], 500);
        }
    }

    public function previewClassMembershipStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'limit' => 'nullable|integer|min:5|max:5000',
            'target_tahun_ajaran_id' => 'nullable|integer|exists:tahun_ajaran,id',
            'mapping_ids' => 'required|array|min:1',
            'mapping_ids.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter preview anggota kelas Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!in_array($batch->status, ['completed', 'partial'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Batch staging belum selesai. Jalankan finalisasi staging sebelum preview anggota kelas.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch),
                ],
            ], 409);
        }

        $validated = $validator->validated();
        $limit = (int) ($validated['limit'] ?? 500);
        $targetTahunAjaranId = isset($validated['target_tahun_ajaran_id']) ? (int) $validated['target_tahun_ajaran_id'] : null;
        $mappingIds = collect($validated['mapping_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $mappings = $this->filterMappingsByIds($this->stagingClassMappings($batch), $mappingIds);
        if ($mappings->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada kelas terpilih yang valid untuk preview anggota.',
            ], 422);
        }

        $allItems = $this->stagingClassMembershipPreviewItems($batch, $mappings, $targetTahunAjaranId);

        return response()->json([
            'success' => true,
            'message' => 'Preview anggota kelas Dapodik berhasil dihitung.',
            'data' => [
                'batch' => $this->stagingBatchPayload($batch),
                'filters' => [
                    'limit' => $limit,
                    'mapping_ids_count' => count($mappingIds),
                    'target_tahun_ajaran_id' => $targetTahunAjaranId,
                ],
                'target_tahun_ajaran' => $this->targetTahunAjaranResponsePayload($targetTahunAjaranId),
                'summary' => $this->stagingClassMembershipPreviewSummary($allItems),
                'reconciliation' => $this->classMembershipReconciliationSummary($batch, $allItems),
                'items' => $allItems->take($limit)->values()->all(),
                'has_more' => $allItems->count() > $limit,
                'policy' => [
                    'mode' => 'class_membership_preview',
                    'selection_required_for_sync' => true,
                    'blocked_when' => [
                        'master class belum tersinkron',
                        'siswa belum tersinkron',
                        'siswa punya kelas aktif lain pada tahun ajaran target',
                    ],
                ],
            ],
        ]);
    }

    public function syncClassMembershipStagingBatch(Request $request, DapodikSyncBatch $batch): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'confirm_sync' => 'required|accepted',
            'target_tahun_ajaran_id' => 'nullable|integer|exists:tahun_ajaran,id',
            'mapping_ids' => 'required|array|min:1',
            'mapping_ids.*' => 'integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter sinkronisasi anggota kelas Dapodik tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!in_array($batch->status, ['completed', 'partial'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Batch staging belum selesai. Jalankan finalisasi staging sebelum sinkronisasi anggota kelas.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch),
                ],
            ], 409);
        }

        $validated = $validator->validated();
        $targetTahunAjaranId = isset($validated['target_tahun_ajaran_id']) ? (int) $validated['target_tahun_ajaran_id'] : null;
        $mappingIds = collect($request->input('mapping_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        try {
            $mappings = $this->filterMappingsByIds($this->stagingClassMappings($batch), $mappingIds);
            if ($mappings->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada kelas terpilih yang valid untuk sinkronisasi anggota.',
                ], 422);
            }

            $items = $this->stagingClassMembershipPreviewItems($batch, $mappings, $targetTahunAjaranId);
            $result = DB::transaction(function () use ($items, $batch, $request, $mappingIds, $targetTahunAjaranId) {
                $sync = $this->syncClassMembershipPreviewItems($items, $batch, $request->user()?->id);
                $meta = is_array($batch->meta) ? $batch->meta : [];
                $meta['last_class_membership_sync'] = [
                    'synced_at' => now()->toISOString(),
                    'synced_by' => $request->user()?->id,
                    'target_tahun_ajaran_id' => $targetTahunAjaranId,
                    'mapping_ids_count' => count($mappingIds),
                    'summary' => $sync['summary'],
                ];

                $batch->forceFill(['meta' => $meta])->save();

                return $sync;
            });

            return response()->json([
                'success' => true,
                'message' => 'Sinkronisasi anggota kelas selesai.',
                'data' => [
                    'batch' => $this->stagingBatchPayload($batch->refresh()),
                    'filters' => [
                        'mapping_ids_count' => count($mappingIds),
                        'target_tahun_ajaran_id' => $targetTahunAjaranId,
                    ],
                    'target_tahun_ajaran' => $this->targetTahunAjaranResponsePayload($targetTahunAjaranId),
                    'summary' => $result['summary'],
                    'items' => $result['items'],
                    'has_more' => $result['has_more'],
                ],
            ]);
        } catch (Throwable $e) {
            $errorContext = $this->exceptionContextPayload($e, 'class_membership_sync');

            Log::error('Dapodik class membership sync failed', [
                'batch_id' => $batch->id,
                'mapping_ids' => $mappingIds,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'error_context' => $errorContext,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sinkronisasi anggota kelas gagal. Tidak ada perubahan parsial yang disimpan.',
                'error' => $e->getMessage(),
                'error_context' => $errorContext,
            ], 500);
        }
    }

    private function settingsPayload(): array
    {
        $lastTest = $this->decodeJsonSetting('last_test');

        return [
            'base_url' => $this->getSetting('base_url', self::DEFAULT_BASE_URL),
            'npsn' => $this->getSetting('npsn', ''),
            'probe_path' => $this->getSetting('probe_path', self::DEFAULT_PROBE_PATH),
            'api_token' => $this->getApiToken(),
            'has_api_token' => $this->getApiToken() !== '',
            'last_test' => $lastTest,
        ];
    }

    private function stagingSourceDefinitions(): array
    {
        return [
            'school' => [
                'endpoint' => '/WebService/getSekolah',
                'entity_type' => 'school',
                'id_field' => 'sekolah_id',
                'secondary_fields' => ['npsn'],
            ],
            'students' => [
                'endpoint' => self::STUDENT_ENDPOINT,
                'entity_type' => 'student',
                'id_field' => 'peserta_didik_id',
                'secondary_fields' => ['registrasi_id', 'nisn', 'nipd'],
            ],
            'employees' => [
                'endpoint' => self::EMPLOYEE_ENDPOINT,
                'entity_type' => 'employee',
                'id_field' => 'ptk_id',
                'secondary_fields' => ['ptk_terdaftar_id', 'nip', 'nuptk'],
            ],
            'classes' => [
                'endpoint' => self::CLASS_ENDPOINT,
                'entity_type' => 'class',
                'id_field' => 'rombongan_belajar_id',
                'secondary_fields' => ['nama', 'semester_id'],
            ],
            'dapodik_users' => [
                'endpoint' => self::USER_ENDPOINT,
                'entity_type' => 'dapodik_user',
                'id_field' => 'pengguna_id',
                'secondary_fields' => ['ptk_id', 'peserta_didik_id', 'username'],
            ],
        ];
    }

    private function defaultStagingSources(): array
    {
        return ['school', 'dapodik_users', 'students', 'employees', 'classes'];
    }

    private function normalizeStagingSources(array $sources): array
    {
        $sourceDefinitions = $this->stagingSourceDefinitions();

        return collect($sources)
            ->filter(fn ($source) => is_string($source) && isset($sourceDefinitions[$source]))
            ->unique()
            ->values()
            ->all();
    }

    private function queuedSourceStatuses(array $sources): array
    {
        $statuses = [];

        foreach ($sources as $source) {
            $definition = $this->stagingSourceDefinitions()[$source] ?? [];
            $statuses[$source] = [
                'status' => 'queued',
                'endpoint' => $definition['endpoint'] ?? null,
                'row_count' => 0,
                'records_stored' => 0,
                'message' => 'Menunggu giliran.',
                'error' => null,
            ];
        }

        return $statuses;
    }

    private function markStagingSource(DapodikSyncBatch $batch, string $source, array $sourceStatus, array $batchAttributes = []): DapodikSyncBatch
    {
        $meta = $batch->meta ?: [];
        $sources = $this->normalizeStagingSources($meta['sources'] ?? $this->defaultStagingSources());
        if (!in_array($source, $sources, true)) {
            $sources[] = $source;
        }

        $sourceStatuses = is_array($meta['source_statuses'] ?? null)
            ? $meta['source_statuses']
            : $this->queuedSourceStatuses($sources);
        $sourceStatuses[$source] = array_merge($sourceStatuses[$source] ?? [], $sourceStatus);
        $meta['sources'] = $sources;
        $meta['source_statuses'] = $sourceStatuses;

        $errors = $this->stagingErrorsFromSourceStatuses($sourceStatuses);
        $attributes = array_merge([
            'meta' => $meta,
            'totals' => $this->stagingTotalsFromSourceStatuses($sources, $sourceStatuses),
            'errors' => $errors ?: null,
        ], $batchAttributes);

        $batch->update($attributes);

        return $batch->fresh();
    }

    private function stagingBatchPayload(DapodikSyncBatch $batch): array
    {
        $meta = $batch->meta ?: [];
        $sources = $this->normalizeStagingSources($meta['sources'] ?? $this->defaultStagingSources());
        $sourceStatuses = is_array($meta['source_statuses'] ?? null)
            ? array_replace($this->queuedSourceStatuses($sources), $meta['source_statuses'])
            : $this->queuedSourceStatuses($sources);
        $completedSources = collect($sourceStatuses)->where('status', 'completed')->count();
        $terminal = in_array($batch->status, ['completed', 'partial', 'failed'], true) && $batch->finished_at !== null;
        $totalSteps = count($sources) + 1;
        $doneSteps = $completedSources + ($terminal ? 1 : 0);

        return [
            'id' => (int) $batch->id,
            'uuid' => $batch->uuid,
            'status' => $batch->status,
            'base_url' => $batch->base_url,
            'npsn' => $batch->npsn,
            'started_at' => $batch->started_at?->toISOString(),
            'finished_at' => $batch->finished_at?->toISOString(),
            'sources' => $sources,
            'source_statuses' => $sourceStatuses,
            'totals' => $batch->totals ?: $this->stagingTotalsFromSourceStatuses($sources, $sourceStatuses),
            'errors' => $batch->errors ?: [],
            'progress' => [
                'done_steps' => $doneSteps,
                'total_steps' => $totalSteps,
                'percentage' => $totalSteps > 0 ? (int) floor(($doneSteps / $totalSteps) * 100) : 0,
            ],
        ];
    }

    private function stagingTotalsFromSourceStatuses(array $sources, array $sourceStatuses): array
    {
        $recordsBySource = [];
        $successful = 0;
        $failed = 0;
        $running = 0;

        foreach ($sources as $source) {
            $status = $sourceStatuses[$source]['status'] ?? 'queued';
            $recordsBySource[$source] = (int) ($sourceStatuses[$source]['records_stored'] ?? 0);

            if ($status === 'completed') {
                $successful++;
            } elseif ($status === 'failed') {
                $failed++;
            } elseif ($status === 'running') {
                $running++;
            }
        }

        return [
            'sources_requested' => count($sources),
            'sources_successful' => $successful,
            'sources_failed' => $failed,
            'sources_running' => $running,
            'records_stored' => array_sum($recordsBySource),
            'records_by_source' => $recordsBySource,
        ];
    }

    private function stagingErrorsFromSourceStatuses(array $sourceStatuses): array
    {
        $errors = [];

        foreach ($sourceStatuses as $source => $status) {
            if (($status['status'] ?? null) === 'failed') {
                $errors[$source] = $status['error'] ?? $status['message'] ?? 'Sumber Dapodik gagal diproses.';
            }
        }

        return $errors;
    }

    private function loadStagedRowsForBatch(DapodikSyncBatch $batch, ?array $sources = null): array
    {
        $sources = $this->normalizeStagingSources($sources ?? (($batch->meta ?: [])['sources'] ?? $this->defaultStagingSources()));
        $rows = array_fill_keys($sources, []);

        DB::table('dapodik_sync_records')
            ->where('batch_id', $batch->id)
            ->whereIn('source', $sources)
            ->orderBy('source')
            ->orderBy('row_index')
            ->get(['source', 'row_data'])
            ->each(function ($record) use (&$rows) {
                $row = is_string($record->row_data)
                    ? json_decode($record->row_data, true)
                    : $record->row_data;

                if (is_array($row)) {
                    $rows[$record->source] ??= [];
                    $rows[$record->source][] = $row;
                }
            });

        return $rows;
    }

    private function stagingSafeguards(): array
    {
        return [
            'staging_only' => true,
            'final_tables_untouched' => ['users', 'data_pribadi_siswa', 'data_kepegawaian', 'kelas', 'kelas_siswa'],
            'rombel_requires_review' => true,
        ];
    }

    private function stagingReviewSummary(DapodikSyncBatch $batch): array
    {
        $entityTypes = ['student', 'employee', 'class'];
        $confidences = ['exact', 'probable', 'conflict', 'unmatched'];
        $byEntity = [];
        $byConfidence = array_fill_keys($confidences, 0);

        foreach ($entityTypes as $entityType) {
            $byEntity[$entityType] = ['total' => 0] + array_fill_keys($confidences, 0);
        }

        DB::table('dapodik_entity_mappings')
            ->where('last_seen_batch_id', $batch->id)
            ->select('entity_type', 'confidence', DB::raw('count(*) as total'))
            ->groupBy('entity_type', 'confidence')
            ->get()
            ->each(function ($row) use (&$byEntity, &$byConfidence) {
                $entityType = (string) $row->entity_type;
                $confidence = (string) $row->confidence;
                $total = (int) $row->total;

                $byEntity[$entityType] ??= ['total' => 0];
                $byEntity[$entityType]['total'] = ($byEntity[$entityType]['total'] ?? 0) + $total;
                $byEntity[$entityType][$confidence] = ($byEntity[$entityType][$confidence] ?? 0) + $total;
                $byConfidence[$confidence] = ($byConfidence[$confidence] ?? 0) + $total;
            });

        $recordCounts = DB::table('dapodik_sync_records')
            ->where('batch_id', $batch->id)
            ->select('source', DB::raw('count(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source')
            ->map(fn ($value) => (int) $value)
            ->all();

        return [
            'total_mappings' => array_sum($byConfidence),
            'by_entity' => $byEntity,
            'by_confidence' => $byConfidence,
            'record_counts' => $recordCounts,
            'safe_auto_candidates' => ($byEntity['student']['exact'] ?? 0) + ($byEntity['employee']['exact'] ?? 0),
            'needs_manual_review' => array_sum([
                $byConfidence['probable'] ?? 0,
                $byConfidence['conflict'] ?? 0,
                $byConfidence['unmatched'] ?? 0,
                $byEntity['class']['total'] ?? 0,
            ]),
        ];
    }

    private function stagingReviewItems(
        DapodikSyncBatch $batch,
        ?string $entityType,
        ?string $confidence,
        int $limit
    ): array {
        $query = DapodikEntityMapping::query()
            ->where('last_seen_batch_id', $batch->id)
            ->whereIn('entity_type', ['student', 'employee', 'class'])
            ->orderByRaw("case confidence when 'conflict' then 1 when 'probable' then 2 when 'unmatched' then 3 when 'exact' then 4 else 5 end")
            ->orderBy('entity_type')
            ->orderBy('id');

        if ($entityType) {
            $query->where('entity_type', $entityType);
        }

        if ($confidence === 'problem') {
            $query->where(function ($query) {
                $query->whereIn('confidence', ['probable', 'conflict', 'unmatched'])
                    ->orWhere('entity_type', 'class');
            });
        } elseif ($confidence) {
            $query->where('confidence', $confidence);
        }

        $mappings = $query->limit($limit + 1)->get();
        $hasMore = $mappings->count() > $limit;
        $mappings = $mappings->take($limit)->values();
        $records = $this->stagingRecordsForMappings($batch, $mappings);
        $locals = $this->localReviewEntitiesForMappings($mappings);

        return [
            'has_more' => $hasMore,
            'items' => $mappings
                ->map(fn (DapodikEntityMapping $mapping) => $this->stagingReviewItemPayload($mapping, $records, $locals))
                ->values()
                ->all(),
        ];
    }

    private function stagingRecordsForMappings(DapodikSyncBatch $batch, Collection $mappings): array
    {
        $idsBySource = [];

        foreach ($mappings as $mapping) {
            $source = $this->stagingSourceForEntityType($mapping->entity_type);
            if (!$source || trim((string) $mapping->dapodik_id) === '') {
                continue;
            }

            $idsBySource[$source] ??= [];
            $idsBySource[$source][] = $mapping->dapodik_id;
        }

        $records = [];
        foreach ($idsBySource as $source => $ids) {
            DB::table('dapodik_sync_records')
                ->where('batch_id', $batch->id)
                ->where('source', $source)
                ->whereIn('dapodik_id', array_values(array_unique($ids)))
                ->get(['source', 'dapodik_id', 'row_data', 'normalized_data'])
                ->each(function ($record) use (&$records) {
                    $records[$record->source . '|' . $record->dapodik_id] = [
                        'row_data' => $this->decodeDbJson($record->row_data),
                        'normalized_data' => $this->decodeDbJson($record->normalized_data),
                    ];
                });
        }

        return $records;
    }

    private function localReviewEntitiesForMappings(Collection $mappings): array
    {
        $userIds = $mappings
            ->filter(fn (DapodikEntityMapping $mapping) => $mapping->siaps_table === 'users' && $mapping->siaps_id)
            ->pluck('siaps_id')
            ->unique()
            ->values();
        $classIds = $mappings
            ->filter(fn (DapodikEntityMapping $mapping) => $mapping->siaps_table === 'kelas' && $mapping->siaps_id)
            ->pluck('siaps_id')
            ->unique()
            ->values();

        return [
            'users' => $userIds->isEmpty()
                ? collect()
                : User::query()
                    ->with([
                        'roles:id,name',
                        'dataPribadiSiswa:id,user_id,tempat_lahir,tanggal_lahir,jenis_kelamin,agama,no_telepon_rumah,no_hp_siswa,email_siswa,asal_sekolah,nama_ayah,pekerjaan_ayah,nama_ibu,pekerjaan_ibu,nama_wali,pekerjaan_wali,anak_ke,tinggi_badan,berat_badan,kebutuhan_khusus,tahun_masuk,tanggal_masuk_sekolah,kelas_awal_id,tahun_ajaran_awal_id,tanggal_masuk_kelas_awal',
                        'dataKepegawaian:id,user_id,nip,nuptk,tempat_lahir,tanggal_lahir,jenis_kelamin,agama,status_kepegawaian,jenis_ptk,jabatan,pendidikan_terakhir,bidang_studi,pangkat_golongan',
                    ])
                    ->whereIn('id', $userIds)
                    ->get()
                    ->keyBy('id'),
            'classes' => $classIds->isEmpty()
                ? collect()
                : Kelas::query()
                    ->with(['tingkat:id,nama,kode', 'tahunAjaran:id,nama,status,is_active', 'waliKelas:id,nama_lengkap'])
                    ->whereIn('id', $classIds)
                    ->get()
                    ->keyBy('id'),
        ];
    }

    private function stagingReviewItemPayload(DapodikEntityMapping $mapping, array $records, array $locals): array
    {
        $source = $this->stagingSourceForEntityType($mapping->entity_type);
        $record = $records[$source . '|' . $mapping->dapodik_id] ?? [];
        $normalized = is_array($record['normalized_data'] ?? null) ? $record['normalized_data'] : [];
        $local = $this->localReviewPayload($mapping, $locals);
        $changes = $this->reviewChangesForMapping($mapping, $normalized, $locals);

        return [
            'mapping_id' => (int) $mapping->id,
            'entity_type' => $mapping->entity_type,
            'source' => $source,
            'dapodik_id' => $mapping->dapodik_id,
            'siaps_table' => $mapping->siaps_table,
            'siaps_id' => $mapping->siaps_id ? (int) $mapping->siaps_id : null,
            'confidence' => $mapping->confidence,
            'match_key' => $mapping->match_key,
            'name' => $this->reviewNameFromNormalized($mapping->entity_type, $normalized),
            'identifiers' => $this->reviewIdentifiersFromNormalized($mapping->entity_type, $normalized),
            'local' => $local,
            'changes' => $changes,
            'change_count' => count($changes),
            'recommended_action' => $this->reviewRecommendedAction($mapping, $normalized, $changes),
            'meta' => $mapping->meta ?: [],
        ];
    }

    private function localReviewPayload(DapodikEntityMapping $mapping, array $locals): ?array
    {
        if ($mapping->siaps_table === 'users' && $mapping->siaps_id) {
            $user = $locals['users']->get($mapping->siaps_id);
            if (!$user) {
                return null;
            }

            return [
                'id' => (int) $user->id,
                'type' => 'user',
                'name' => $user->nama_lengkap,
                'username' => $user->username,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values()->all(),
            ];
        }

        if ($mapping->siaps_table === 'kelas' && $mapping->siaps_id) {
            $class = $locals['classes']->get($mapping->siaps_id);
            if (!$class) {
                return null;
            }

            return [
                'id' => (int) $class->id,
                'type' => 'kelas',
                'name' => $class->nama_kelas,
                'tingkat' => $class->tingkat?->nama,
                'tahun_ajaran' => $class->tahunAjaran?->nama,
                'wali_kelas' => $class->waliKelas?->nama_lengkap,
            ];
        }

        return null;
    }

    private function classLocalPayload(Kelas $class): array
    {
        return [
            'id' => (int) $class->id,
            'type' => 'kelas',
            'name' => $class->nama_kelas,
            'tingkat' => $class->tingkat?->nama,
            'tahun_ajaran' => $class->tahunAjaran?->nama,
            'wali_kelas' => $class->waliKelas?->nama_lengkap,
        ];
    }

    private function reviewChangesForMapping(DapodikEntityMapping $mapping, array $normalized, array $locals): array
    {
        if ($mapping->confidence === 'conflict' || !$mapping->siaps_id) {
            return [];
        }

        if ($mapping->entity_type === 'student') {
            $user = $locals['users']->get($mapping->siaps_id);
            return $user ? $this->studentChanges($user, $normalized) : [];
        }

        if ($mapping->entity_type === 'employee') {
            $user = $locals['users']->get($mapping->siaps_id);
            return $user ? $this->employeeChanges($user, $normalized) : [];
        }

        if ($mapping->entity_type === 'class') {
            $class = $locals['classes']->get($mapping->siaps_id);
            if (!$class) {
                return [];
            }

            return $this->changedFields([
                'nama_kelas' => [$class->nama_kelas, $normalized['nama_kelas'] ?? ''],
                'jurusan' => [$class->jurusan, $normalized['jurusan'] ?? ''],
            ]);
        }

        return [];
    }

    private function reviewRecommendedAction(DapodikEntityMapping $mapping, array $normalized, array $changes): string
    {
        if ($mapping->confidence === 'conflict') {
            return 'resolve_conflict';
        }

        if ($mapping->confidence === 'probable') {
            return 'manual_review';
        }

        if ($mapping->confidence === 'unmatched') {
            return $this->reviewBlockingIssues($mapping->entity_type, $normalized) === []
                ? 'create_candidate'
                : 'blocked';
        }

        if ($mapping->entity_type === 'class') {
            return 'review_class_mapping';
        }

        return $changes === [] ? 'no_change' : 'update_candidate';
    }

    private function reviewBlockingIssues(string $entityType, array $normalized): array
    {
        return match ($entityType) {
            'student' => $this->studentBlockingIssues($normalized),
            'employee' => $this->employeeBlockingIssues($normalized),
            'class' => $this->requiredIssues($normalized, [
                'nama_kelas' => 'Nama kelas kosong',
            ]),
            default => [],
        };
    }

    private function reviewNameFromNormalized(string $entityType, array $normalized): string
    {
        return match ($entityType) {
            'student', 'employee' => (string) ($normalized['nama_lengkap'] ?? ''),
            'class' => (string) ($normalized['nama_kelas'] ?? ''),
            default => '',
        };
    }

    private function reviewIdentifiersFromNormalized(string $entityType, array $normalized): array
    {
        return match ($entityType) {
            'student' => [
                'nis' => $normalized['nis'] ?? null,
                'nisn' => $normalized['nisn'] ?? null,
                'nik' => $normalized['nik'] ?? null,
                'rombel' => $normalized['nama_rombel'] ?? null,
            ],
            'employee' => [
                'nip' => $normalized['nip'] ?? null,
                'nuptk' => $normalized['nuptk'] ?? null,
                'nik' => $normalized['nik'] ?? null,
                'role' => $normalized['suggested_role'] ?? null,
            ],
            'class' => [
                'tingkat' => $normalized['tingkat_label'] ?? null,
                'jurusan' => $normalized['jurusan'] ?? null,
                'anggota' => $normalized['member_count'] ?? null,
            ],
            default => [],
        };
    }

    private function stagingSourceForEntityType(string $entityType): ?string
    {
        return match ($entityType) {
            'student' => 'students',
            'employee' => 'employees',
            'class' => 'classes',
            default => null,
        };
    }

    private function decodeDbJson(mixed $value): mixed
    {
        if (is_array($value) || $value === null) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return null;
    }

    private function stagingApplyPreviewItems(DapodikSyncBatch $batch, Collection $mappings): Collection
    {
        if ($mappings->isEmpty()) {
            return collect();
        }

        $records = $this->stagingRecordsForMappings($batch, $mappings);
        $locals = $this->localReviewEntitiesForMappings($mappings);

        return $mappings
            ->map(fn (DapodikEntityMapping $mapping) => $this->stagingApplyPreviewItemPayload($mapping, $records, $locals))
            ->values();
    }

    private function stagingApplyMappings(DapodikSyncBatch $batch, array $entityTypes): Collection
    {
        return DapodikEntityMapping::query()
            ->where('last_seen_batch_id', $batch->id)
            ->whereIn('entity_type', $entityTypes)
            ->where('confidence', 'exact')
            ->where('siaps_table', 'users')
            ->whereNotNull('siaps_id')
            ->orderBy('entity_type')
            ->orderBy('id')
            ->get();
    }

    private function stagingApplyPreviewItemPayload(DapodikEntityMapping $mapping, array $records, array $locals): array
    {
        $source = $this->stagingSourceForEntityType($mapping->entity_type);
        $record = $records[$source . '|' . $mapping->dapodik_id] ?? [];
        $normalized = is_array($record['normalized_data'] ?? null) ? $record['normalized_data'] : [];
        $user = $mapping->siaps_id ? $locals['users']->get($mapping->siaps_id) : null;
        $blockers = [];

        if ($normalized === []) {
            $blockers[] = 'Normalized staging data tidak ditemukan.';
        }

        if (!$user) {
            $blockers[] = 'User lokal tidak ditemukan.';
        } elseif ($mapping->entity_type === 'student' && !$this->userHasRole($user, RoleNames::SISWA)) {
            $blockers[] = 'User lokal bukan role Siswa.';
        } elseif ($mapping->entity_type === 'employee' && $this->userHasRole($user, RoleNames::SISWA)) {
            $blockers[] = 'User lokal masih role Siswa.';
        }

        $changes = [];
        if ($blockers === [] && $user) {
            $changes = match ($mapping->entity_type) {
                'student' => $this->studentApplyChangeDetails($user, $normalized),
                'employee' => $this->employeeApplyChangeDetails($user, $normalized),
                default => [],
            };
        }

        return [
            'mapping_id' => (int) $mapping->id,
            'entity_type' => $mapping->entity_type,
            'source' => $source,
            'dapodik_id' => $mapping->dapodik_id,
            'confidence' => $mapping->confidence,
            'match_key' => $mapping->match_key,
            'siaps_user_id' => $user ? (int) $user->id : null,
            'name' => $this->reviewNameFromNormalized($mapping->entity_type, $normalized),
            'identifiers' => $this->reviewIdentifiersFromNormalized($mapping->entity_type, $normalized),
            'local' => $user ? [
                'id' => (int) $user->id,
                'name' => $user->nama_lengkap,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name')->values()->all(),
            ] : null,
            'blockers' => $blockers,
            'changes' => $changes,
            'change_count' => count($changes),
            'changes_by_table' => collect($changes)
                ->groupBy('table')
                ->map(fn (Collection $items) => $items->count())
                ->all(),
            'action' => $blockers !== []
                ? 'blocked'
                : (count($changes) > 0 ? 'update_candidate' : 'no_change'),
        ];
    }

    private function stagingApplyPreviewSummary(DapodikSyncBatch $batch, array $entityTypes, Collection $items): array
    {
        $byEntity = [];
        foreach ($entityTypes as $entityType) {
            $entityItems = $items->where('entity_type', $entityType);
            $byEntity[$entityType] = [
                'eligible' => $entityItems->count(),
                'update_candidates' => $entityItems->where('action', 'update_candidate')->count(),
                'no_change' => $entityItems->where('action', 'no_change')->count(),
                'blocked' => $entityItems->where('action', 'blocked')->count(),
                'field_changes' => $entityItems->sum('change_count'),
            ];
        }

        $changes = $items->flatMap(fn (array $item) => $item['changes'] ?? []);
        $skipped = [
            'non_exact' => DapodikEntityMapping::query()
                ->where('last_seen_batch_id', $batch->id)
                ->whereIn('entity_type', $entityTypes)
                ->where('confidence', '!=', 'exact')
                ->count(),
            'without_existing_user' => DapodikEntityMapping::query()
                ->where('last_seen_batch_id', $batch->id)
                ->whereIn('entity_type', $entityTypes)
                ->where('confidence', 'exact')
                ->where(function ($query) {
                    $query->whereNull('siaps_id')->orWhere('siaps_table', '!=', 'users');
                })
                ->count(),
            'classes' => DapodikEntityMapping::query()
                ->where('last_seen_batch_id', $batch->id)
                ->where('entity_type', 'class')
                ->count(),
        ];

        return [
            'eligible' => $items->count(),
            'update_candidates' => $items->where('action', 'update_candidate')->count(),
            'no_change' => $items->where('action', 'no_change')->count(),
            'blocked' => $items->where('action', 'blocked')->count(),
            'create_candidates' => 0,
            'field_changes' => $items->sum('change_count'),
            'by_entity' => $byEntity,
            'by_table' => $changes
                ->groupBy('table')
                ->map(fn (Collection $tableChanges) => $tableChanges->count())
                ->all(),
            'skipped' => $skipped,
        ];
    }

    private function stagingInputMappings(DapodikSyncBatch $batch, array $entityTypes): Collection
    {
        return DapodikEntityMapping::query()
            ->where('last_seen_batch_id', $batch->id)
            ->whereIn('entity_type', $entityTypes)
            ->where('confidence', 'unmatched')
            ->where(function ($query) {
                $query->whereNull('siaps_id')->orWhere('siaps_table', 'users');
            })
            ->orderBy('entity_type')
            ->orderBy('id')
            ->get();
    }

    private function filterMappingsByIds(Collection $mappings, array $mappingIds): Collection
    {
        if ($mappingIds === []) {
            return $mappings->values();
        }

        $lookup = array_fill_keys(array_map('intval', $mappingIds), true);

        return $mappings
            ->filter(fn (DapodikEntityMapping $mapping) => isset($lookup[(int) $mapping->id]))
            ->values();
    }

    private function stagingClassMappings(DapodikSyncBatch $batch): Collection
    {
        return DapodikEntityMapping::query()
            ->where('last_seen_batch_id', $batch->id)
            ->where('entity_type', 'class')
            ->orderBy('id')
            ->get();
    }

    private function stagingClassPreviewItems(DapodikSyncBatch $batch, Collection $mappings, ?int $targetTahunAjaranId = null): Collection
    {
        if ($mappings->isEmpty()) {
            return collect();
        }

        $records = $this->stagingRecordsForMappings($batch, $mappings);
        $locals = $this->localReviewEntitiesForMappings($mappings);
        $context = $this->buildLocalMappingContext($targetTahunAjaranId);
        $waliIds = collect($records)
            ->map(fn (array $record) => $record['normalized_data']['wali_ptk_id'] ?? null)
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->unique()
            ->values()
            ->all();
        $waliUsersByDapodikId = $this->resolveExactMappedUserEntriesByDapodikIds('employee', $waliIds, $context['users_by_id']);

        return $mappings
            ->map(fn (DapodikEntityMapping $mapping) => $this->stagingClassPreviewItemPayload($mapping, $records, $locals, $context, $waliUsersByDapodikId))
            ->values();
    }

    private function stagingClassPreviewItemPayload(
        DapodikEntityMapping $mapping,
        array $records,
        array $locals,
        array $context,
        array $waliUsersByDapodikId
    ): array {
        $source = $this->stagingSourceForEntityType($mapping->entity_type);
        $record = $records[$source . '|' . $mapping->dapodik_id] ?? [];
        $normalized = is_array($record['normalized_data'] ?? null) ? $record['normalized_data'] : [];
        $match = $normalized !== [] ? $this->findClassMatch($normalized, $context) : ['class' => null, 'key' => null, 'ambiguous' => false];
        $localClass = $match['class'] ?? null;
        $waliEntry = $waliUsersByDapodikId[(string) ($normalized['wali_ptk_id'] ?? '')] ?? null;
        $target = $this->classTargetPayload($normalized, $context, $waliEntry);
        $blockers = [];

        if ($normalized === []) {
            $blockers[] = 'Normalized staging data kelas tidak ditemukan.';
        }

        if ($match['ambiguous']) {
            $blockers[] = 'Nama kelas cocok ke lebih dari satu kelas lokal pada tahun ajaran target.';
        }

        if ($match['class'] && !$localClass) {
            $blockers[] = 'Kelas lokal tidak ditemukan saat preview.';
        }

        $blockers = array_values(array_unique(array_merge($blockers, $target['blockers'])));
        $changes = $localClass ? $this->classChangeDetails($localClass, $target['update_payload']) : [];

        $confidence = 'unmatched';
        if ($match['ambiguous']) {
            $confidence = 'conflict';
        } elseif ($localClass) {
            $confidence = ($match['key'] ?? null) === 'nama+tahun_ajaran_target' ? 'exact' : 'probable';
        }

        $action = match ($confidence) {
            'conflict', 'probable' => 'manual_review',
            'unmatched' => $blockers === [] ? 'create_candidate' : 'blocked',
            'exact' => $blockers !== [] ? 'blocked' : ($changes === [] ? 'no_change' : 'update_candidate'),
            default => 'blocked',
        };

        return [
            'mapping_id' => (int) $mapping->id,
            'entity_type' => 'class',
            'source' => $source,
            'dapodik_id' => $mapping->dapodik_id,
            'siaps_class_id' => $localClass ? (int) $localClass->id : null,
            'confidence' => $confidence,
            'match_key' => $match['key'] ?? $mapping->match_key,
            'name' => $this->reviewNameFromNormalized('class', $normalized),
            'identifiers' => $this->reviewIdentifiersFromNormalized('class', $normalized),
            'local' => $localClass ? $this->classLocalPayload($localClass) : null,
            'target' => $target['display'],
            'update_payload' => $target['update_payload'],
            'create_payload' => $target['create_payload'],
            'blockers' => $blockers,
            'notes' => $target['notes'],
            'changes' => $changes,
            'change_count' => count($changes),
            'action' => $action,
        ];
    }

    private function stagingClassPreviewSummary(DapodikSyncBatch $batch, Collection $items): array
    {
        $byAction = [
            'update_candidate' => $items->where('action', 'update_candidate')->count(),
            'create_candidate' => $items->where('action', 'create_candidate')->count(),
            'manual_review' => $items->where('action', 'manual_review')->count(),
            'blocked' => $items->where('action', 'blocked')->count(),
            'no_change' => $items->where('action', 'no_change')->count(),
        ];

        $byConfidence = [
            'exact' => $items->where('confidence', 'exact')->count(),
            'probable' => $items->where('confidence', 'probable')->count(),
            'conflict' => $items->where('confidence', 'conflict')->count(),
            'unmatched' => $items->where('confidence', 'unmatched')->count(),
        ];

        return [
            'eligible' => $items->count(),
            'update_candidates' => $byAction['update_candidate'],
            'create_candidates' => $byAction['create_candidate'],
            'manual_review' => $byAction['manual_review'],
            'blocked' => $byAction['blocked'],
            'no_change' => $byAction['no_change'],
            'field_changes' => $items->sum('change_count'),
            'by_action' => $byAction,
            'by_confidence' => $byConfidence,
            'skipped' => [
                'users' => DapodikEntityMapping::query()
                    ->where('last_seen_batch_id', $batch->id)
                    ->whereIn('entity_type', ['student', 'employee'])
                    ->count(),
            ],
        ];
    }

    private function classTargetPayload(array $normalized, array $context, ?array $waliEntry): array
    {
        $blockers = $this->reviewBlockingIssues('class', $normalized);
        $notes = [];
        $namaKelas = trim((string) ($normalized['nama_kelas'] ?? ''));
        $jurusan = trim((string) ($normalized['jurusan'] ?? ''));
        $targetYear = $context['target_tahun_ajaran_model'];
        $tingkat = $this->findTingkatMatch(
            (string) ($normalized['tingkat_label'] ?? ''),
            (string) ($normalized['tingkat_id'] ?? ''),
            $context['tingkat']
        );

        if (!$targetYear) {
            $blockers[] = 'Tahun ajaran target lokal belum tersedia.';
        } elseif (!$targetYear->canManageClasses()) {
            $blockers[] = "Tahun ajaran target '{$targetYear->nama}' belum bisa dikelola untuk kelas.";
        }

        if (!$tingkat) {
            $label = trim((string) ($normalized['tingkat_label'] ?? $normalized['tingkat_id'] ?? ''));
            $blockers[] = $label !== ''
                ? "Tingkat '{$label}' belum cocok dengan data tingkat lokal."
                : 'Tingkat kelas belum bisa dipetakan.';
        }

        $waliUser = $waliEntry['user'] ?? null;
        if (!$waliUser && trim((string) ($normalized['wali_ptk_id'] ?? '')) !== '') {
            $waliName = trim((string) ($normalized['wali_ptk_name'] ?? ''));
            $notes[] = $waliName !== ''
                ? "Wali kelas Dapodik '{$waliName}' belum punya mapping exact lokal."
                : 'Wali kelas Dapodik belum punya mapping exact lokal.';
        }

        $dapodikTahunAjaran = trim((string) ($normalized['dapodik_tahun_ajaran'] ?? ''));
        if ($dapodikTahunAjaran !== '' && $targetYear && !$this->academicYearLabelsMatch($dapodikTahunAjaran, $targetYear->nama)) {
            $blockers[] = "Tahun ajaran Dapodik '{$dapodikTahunAjaran}' tidak cocok dengan target SIAPS '{$targetYear->nama}'.";
        }

        $basePayload = $this->filledInputPayload([
            'nama_kelas' => $namaKelas,
            'tingkat_id' => $tingkat?->id,
            'jurusan' => $jurusan !== '' ? $jurusan : null,
            'wali_kelas_id' => $waliUser?->id,
        ]);

        $createPayload = $this->filledInputPayload([
            ...$basePayload,
            'tahun_ajaran_id' => $targetYear?->id,
            'kapasitas' => max((int) ($normalized['member_count'] ?? 0), 1),
            'jumlah_siswa' => 0,
            'keterangan' => 'Dibuat dari sinkronisasi Dapodik',
            'is_active' => true,
        ]);

        return [
            'update_payload' => $basePayload,
            'create_payload' => $createPayload,
            'blockers' => array_values(array_unique($blockers)),
            'notes' => array_values(array_unique($notes)),
            'display' => [
                'nama_kelas' => $namaKelas,
                'jurusan' => $jurusan !== '' ? $jurusan : null,
                'tingkat' => $tingkat ? [
                    'id' => (int) $tingkat->id,
                    'nama' => $tingkat->nama,
                ] : null,
                'tahun_ajaran' => $targetYear ? [
                    'id' => (int) $targetYear->id,
                    'nama' => $targetYear->nama,
                ] : null,
                'dapodik_tahun_ajaran' => $dapodikTahunAjaran !== '' ? $dapodikTahunAjaran : null,
                'dapodik_semester' => trim((string) ($normalized['dapodik_semester'] ?? '')) ?: null,
                'wali_kelas' => $waliUser ? [
                    'id' => (int) $waliUser->id,
                    'nama' => $waliUser->nama_lengkap,
                ] : null,
                'member_count' => (int) ($normalized['member_count'] ?? 0),
            ],
        ];
    }

    private function classChangeDetails(Kelas $class, array $payload): array
    {
        return $this->detailedChangedFields([
            'kelas' => [
                'nama_kelas' => [$class->nama_kelas, $payload['nama_kelas'] ?? ''],
                'tingkat_id' => [$class->tingkat_id, $payload['tingkat_id'] ?? ''],
                'jurusan' => [$class->jurusan, $payload['jurusan'] ?? ''],
                'wali_kelas_id' => [$class->wali_kelas_id, $payload['wali_kelas_id'] ?? ''],
            ],
        ]);
    }

    private function resolveExactMappedUserEntriesByDapodikIds(string $entityType, array $dapodikIds, Collection $usersById): array
    {
        if ($dapodikIds === []) {
            return [];
        }

        $entries = [];

        DapodikEntityMapping::query()
            ->where('entity_type', $entityType)
            ->where('confidence', 'exact')
            ->where('siaps_table', 'users')
            ->whereNotNull('siaps_id')
            ->whereIn('dapodik_id', $dapodikIds)
            ->get(['id', 'dapodik_id', 'siaps_id'])
            ->each(function (DapodikEntityMapping $mapping) use (&$entries, $usersById) {
                $user = $usersById->get((int) $mapping->siaps_id);
                if (!$user) {
                    return;
                }

                $entries[(string) $mapping->dapodik_id] = [
                    'mapping_id' => (int) $mapping->id,
                    'user' => $user,
                ];
            });

        return $entries;
    }

    private function syncClassPreviewItems(Collection $items, string $mode, DapodikSyncBatch $batch, ?int $actorId): array
    {
        $summary = [
            'eligible' => $items->count(),
            'selected_items' => $items->count(),
            'update_candidates' => $items->where('action', 'update_candidate')->count(),
            'create_candidates' => $items->where('action', 'create_candidate')->count(),
            'applied_items' => 0,
            'created_items' => 0,
            'no_change' => 0,
            'blocked' => 0,
            'skipped_items' => 0,
            'applied_fields' => 0,
            'by_status' => [],
        ];
        $results = [];

        foreach ($items as $item) {
            $result = $this->syncClassPreviewItem($item, $mode, $batch, $actorId);
            $status = $result['status'];

            $summary['by_status'][$status] = ($summary['by_status'][$status] ?? 0) + 1;
            $summary['applied_fields'] += (int) ($result['applied_field_count'] ?? 0);

            if ($status === 'applied') {
                $summary['applied_items']++;
            } elseif ($status === 'created') {
                $summary['created_items']++;
            } elseif ($status === 'no_change') {
                $summary['no_change']++;
            } elseif ($status === 'blocked') {
                $summary['blocked']++;
            } else {
                $summary['skipped_items']++;
            }

            $results[] = $result;
        }

        return [
            'summary' => $summary,
            'items' => $results,
            'has_more' => false,
        ];
    }

    private function syncClassPreviewItem(array $item, string $mode, DapodikSyncBatch $batch, ?int $actorId): array
    {
        if ($mode === 'update') {
            if (($item['action'] ?? null) === 'blocked') {
                return $this->classSyncResult($item, 'blocked', [], $item['blockers'] ?? []);
            }

            if (($item['action'] ?? null) === 'manual_review') {
                return $this->classSyncResult($item, 'blocked', [], ['Kelas masih perlu review manual sebelum update.']);
            }

            if (($item['action'] ?? null) === 'no_change') {
                $roleSync = $this->applyWaliKelasRoleFromPayload($item['update_payload'] ?? []);

                return $this->classSyncResult(
                    $item,
                    $roleSync['applied_by_table'] !== [] ? 'applied' : 'no_change',
                    $roleSync['applied_by_table'],
                    array_values(array_unique(array_merge($item['notes'] ?? [], $roleSync['notes'])))
                );
            }

            if (($item['action'] ?? null) !== 'update_candidate') {
                return $this->classSyncResult($item, 'skipped', [], ['Kelas ini tidak masuk jalur update master kelas.']);
            }

            $class = Kelas::query()->find($item['siaps_class_id'] ?? null);
            if (!$class) {
                return $this->classSyncResult($item, 'blocked', [], ['Kelas lokal tidak ditemukan saat update.']);
            }

            try {
                $changedCount = $this->applyModelPayload($class, $item['update_payload'] ?? []);
                $roleSync = $this->applyWaliKelasRoleFromPayload($item['update_payload'] ?? []);
            } catch (Throwable $e) {
                throw $this->processItemException('update_class', $item, $e);
            }

            $appliedByTable = $changedCount > 0 ? ['kelas' => $changedCount] : [];
            $appliedByTable = array_merge($appliedByTable, $roleSync['applied_by_table']);

            return $this->classSyncResult(
                $item,
                $appliedByTable !== [] ? 'applied' : 'no_change',
                $appliedByTable,
                array_values(array_unique(array_merge($item['notes'] ?? [], $roleSync['notes'])))
            );
        }

        if (($item['action'] ?? null) === 'blocked') {
            return $this->classSyncResult($item, 'blocked', [], $item['blockers'] ?? []);
        }

        if (($item['action'] ?? null) === 'manual_review') {
            return $this->classSyncResult($item, 'blocked', [], ['Kelas masih perlu review manual sebelum input.']);
        }

        if (($item['action'] ?? null) !== 'create_candidate') {
            return $this->classSyncResult($item, 'skipped', [], ['Kelas ini tidak masuk jalur input master kelas.']);
        }

        $createPayload = $item['create_payload'] ?? [];
        $existing = $this->findRuntimeClassByNameAndYear(
            (string) ($createPayload['nama_kelas'] ?? ''),
            (int) ($createPayload['tahun_ajaran_id'] ?? 0)
        );

        if ($existing) {
            $mapping = DapodikEntityMapping::query()->find($item['mapping_id'] ?? null);
            if ($mapping) {
                $mapping->forceFill([
                    'siaps_table' => 'kelas',
                    'siaps_id' => $existing->id,
                    'confidence' => 'exact',
                    'match_key' => 'existing_runtime_match',
                    'last_seen_batch_id' => $batch->id,
                    'meta' => array_merge($mapping->meta ?: [], [
                        'runtime_matched_at' => now()->toISOString(),
                    ]),
                ])->save();
            }

            return $this->classSyncResult($item, 'skipped', [], ['Kelas dengan nama yang sama sudah ada pada tahun ajaran target. Mapping diarahkan ke kelas existing.'], [
                'class_id' => (int) $existing->id,
                'nama_kelas' => $existing->nama_kelas,
            ]);
        }

        try {
            $class = Kelas::query()->create(
                $this->filterPayloadForExistingColumns('kelas', $createPayload + ['created_by' => $actorId])
            );
            $roleSync = $this->applyWaliKelasRoleFromPayload($createPayload);
        } catch (Throwable $e) {
            throw $this->processItemException('create_class', $item, $e);
        }

        $mapping = DapodikEntityMapping::query()->find($item['mapping_id'] ?? null);
        if ($mapping) {
            try {
                $mapping->forceFill([
                    'siaps_table' => 'kelas',
                    'siaps_id' => $class->id,
                    'confidence' => 'exact',
                    'match_key' => 'created_from_dapodik_class',
                    'last_seen_batch_id' => $batch->id,
                    'meta' => array_merge($mapping->meta ?: [], [
                        'created_from_dapodik_class' => true,
                        'created_at' => now()->toISOString(),
                    ]),
                ])->save();
            } catch (Throwable $e) {
                throw $this->processItemException('save_class_mapping', $item, $e);
            }
        }

        return $this->classSyncResult($item, 'created', array_merge(['kelas' => 1], $roleSync['applied_by_table']), array_values(array_unique(array_merge($item['notes'] ?? [], $roleSync['notes']))), [
            'class_id' => (int) $class->id,
            'nama_kelas' => $class->nama_kelas,
        ]);
    }

    private function applyWaliKelasRoleFromPayload(array $payload): array
    {
        $userId = (int) ($payload['wali_kelas_id'] ?? 0);

        if (!WaliKelasRoleService::ensureAssigned($userId)) {
            return [
                'applied_by_table' => [],
                'notes' => [],
            ];
        }

        return [
            'applied_by_table' => ['roles' => 1],
            'notes' => ['Role Wali Kelas otomatis ditambahkan ke user wali kelas.'],
        ];
    }

    private function classSyncResult(
        array $item,
        string $status,
        array $appliedByTable,
        array $notes,
        ?array $created = null
    ): array {
        return [
            'mapping_id' => $item['mapping_id'] ?? null,
            'entity_type' => 'class',
            'dapodik_id' => $item['dapodik_id'] ?? null,
            'siaps_class_id' => $item['siaps_class_id'] ?? ($created['class_id'] ?? null),
            'name' => $item['name'] ?? null,
            'identifiers' => $item['identifiers'] ?? [],
            'local' => $item['local'] ?? null,
            'target' => $item['target'] ?? null,
            'status' => $status,
            'applied_by_table' => $appliedByTable,
            'applied_field_count' => array_sum($appliedByTable),
            'created' => $created,
            'notes' => $notes,
        ];
    }

    private function findRuntimeClassByNameAndYear(string $name, int $tahunAjaranId): ?Kelas
    {
        $name = trim($name);
        if ($name === '' || $tahunAjaranId < 1) {
            return null;
        }

        return Kelas::query()
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->whereRaw('LOWER(nama_kelas) = ?', [Str::lower($name)])
            ->first();
    }

    private function stagingClassMembershipPreviewItems(DapodikSyncBatch $batch, Collection $classMappings, ?int $targetTahunAjaranId = null): Collection
    {
        if ($classMappings->isEmpty()) {
            return collect();
        }

        $classPreviewItems = $this->stagingClassPreviewItems($batch, $classMappings, $targetTahunAjaranId)->keyBy('mapping_id');
        $records = $this->stagingRecordsForMappings($batch, $classMappings);
        $context = $this->buildLocalMappingContext($targetTahunAjaranId);
        $memberIds = collect($records)
            ->flatMap(function (array $record) {
                $members = is_array($record['normalized_data']['members'] ?? null)
                    ? $record['normalized_data']['members']
                    : [];

                return collect($members)
                    ->map(fn (array $member) => $this->extractClassMemberDapodikId($member))
                    ->filter();
            })
            ->unique()
            ->values()
            ->all();
        $studentEntries = $this->resolveExactMappedUserEntriesByDapodikIds('student', $memberIds, $context['users_by_id']);

        $items = collect();

        foreach ($classMappings as $mapping) {
            $classPreview = $classPreviewItems->get((int) $mapping->id);
            if (!$classPreview) {
                continue;
            }

            $record = $records['classes|' . $mapping->dapodik_id] ?? [];
            $normalized = is_array($record['normalized_data'] ?? null) ? $record['normalized_data'] : [];
            $members = is_array($normalized['members'] ?? null) ? $normalized['members'] : [];

            if (($classPreview['siaps_class_id'] ?? null) === null || in_array($classPreview['action'] ?? null, ['manual_review', 'blocked', 'create_candidate'], true)) {
                $items->push($this->classMembershipPlaceholderItem(
                    $classPreview,
                    'Master kelas belum siap dipakai untuk anggota kelas. Selesaikan sinkronisasi atau review master kelas lebih dulu.',
                    'blocked'
                ));
                continue;
            }

            if ($members === []) {
                $items->push($this->classMembershipPlaceholderItem(
                    $classPreview,
                    'Data Dapodik tidak memiliki anggota rombel untuk kelas ini.',
                    'no_change'
                ));
                continue;
            }

            foreach ($members as $member) {
                if (!is_array($member)) {
                    continue;
                }

                $memberId = $this->extractClassMemberDapodikId($member);
                $studentEntry = $studentEntries[$memberId] ?? null;
                $items->push($this->stagingClassMembershipPreviewItemPayload($classPreview, $member, $studentEntry, $context));
            }
        }

        return $items->values();
    }

    private function classMembershipPlaceholderItem(array $classPreview, string $message, string $action): array
    {
        return [
            'key' => 'class-placeholder-' . ($classPreview['mapping_id'] ?? '0') . '-' . $action,
            'entity_type' => 'class_membership',
            'is_placeholder' => true,
            'class_mapping_id' => $classPreview['mapping_id'] ?? null,
            'student_mapping_id' => null,
            'dapodik_id' => null,
            'name' => 'Ringkasan kelas tanpa anggota',
            'identifiers' => [
                'rombel' => $classPreview['name'] ?? null,
            ],
            'class' => [
                'mapping_id' => $classPreview['mapping_id'] ?? null,
                'dapodik_id' => $classPreview['dapodik_id'] ?? null,
                'name' => $classPreview['name'] ?? null,
                'local_id' => $classPreview['siaps_class_id'] ?? null,
                'local_name' => $classPreview['local']['name'] ?? null,
                'tahun_ajaran_id' => $classPreview['target']['tahun_ajaran']['id'] ?? null,
                'tahun_ajaran' => $classPreview['target']['tahun_ajaran']['nama'] ?? null,
            ],
            'local' => null,
            'current_assignment' => null,
            'action' => $action,
            'blockers' => $action === 'blocked' ? [$message] : [],
            'notes' => $action === 'no_change' ? [$message] : [],
        ];
    }

    private function stagingClassMembershipPreviewItemPayload(
        array $classPreview,
        array $member,
        ?array $studentEntry,
        array $context
    ): array {
        $classId = (int) ($classPreview['siaps_class_id'] ?? 0);
        $classModel = $context['classes_by_id']->get($classId);
        $memberDapodikId = $this->extractClassMemberDapodikId($member);
        $memberName = $this->extractClassMemberName($member);
        $targetYearId = (int) ($context['target_tahun_ajaran']['id'] ?? 0);
        $blockers = [];
        $notes = [];
        $user = $studentEntry['user'] ?? null;
        $studentMappingId = $studentEntry['mapping_id'] ?? null;
        $currentAssignment = null;
        $action = 'blocked';

        if (!$classModel) {
            $blockers[] = 'Kelas lokal target tidak ditemukan.';
        }

        if ($memberDapodikId === '') {
            $blockers[] = 'peserta_didik_id kosong pada anggota rombel. Data ini tidak bisa dipetakan sebagai siswa.';
        }

        if ($targetYearId < 1) {
            $blockers[] = 'Tahun ajaran target lokal belum tersedia.';
        }

        if (!$user && $memberDapodikId !== '') {
            $blockers[] = 'Siswa anggota belum punya mapping exact lokal. Sinkronkan user siswa lebih dulu.';
        } elseif ($user && !$this->userHasRole($user, RoleNames::SISWA)) {
            $blockers[] = 'User lokal anggota bukan role Siswa.';
        }

        if ($blockers === [] && $classModel && $user) {
            $sameMembership = $context['class_memberships'][$classId . '|' . (int) $user->id . '|' . $targetYearId] ?? null;
            $activeMembership = $context['student_active_class_memberships'][(int) $user->id . '|' . $targetYearId] ?? null;

            if ($activeMembership) {
                $activeClass = $context['classes_by_id']->get((int) $activeMembership['kelas_id']);
                $currentAssignment = [
                    'class_id' => (int) $activeMembership['kelas_id'],
                    'class_name' => $activeClass?->nama_kelas,
                    'status' => $activeMembership['status'] ?? null,
                    'is_active' => (bool) ($activeMembership['is_active'] ?? false),
                ];
            } elseif ($sameMembership) {
                $currentAssignment = [
                    'class_id' => $classId,
                    'class_name' => $classModel->nama_kelas,
                    'status' => $sameMembership['status'] ?? null,
                    'is_active' => (bool) ($sameMembership['is_active'] ?? false),
                ];
            }

            if ($sameMembership && (bool) ($sameMembership['is_active'] ?? false) && (string) ($sameMembership['status'] ?? '') === 'aktif') {
                $action = 'no_change';
                $notes[] = 'Siswa sudah aktif pada kelas ini.';
            } elseif ($activeMembership && (int) $activeMembership['kelas_id'] !== $classId) {
                $activeClass = $context['classes_by_id']->get((int) $activeMembership['kelas_id']);
                $blockers[] = 'Siswa masih aktif di kelas ' . ($activeClass?->nama_kelas ?: '#' . (int) $activeMembership['kelas_id']) . ' pada tahun ajaran target.';
                $action = 'blocked';
            } elseif ($sameMembership) {
                $action = 'reactivate_candidate';
            } else {
                $action = 'assign_candidate';
            }
        }

        if ($blockers !== []) {
            $action = 'blocked';
        }

        return [
            'key' => 'class-member-' . ($classPreview['mapping_id'] ?? '0') . '-' . ($studentMappingId ?: $memberDapodikId ?: Str::uuid()->toString()),
            'entity_type' => 'class_membership',
            'is_placeholder' => false,
            'class_mapping_id' => $classPreview['mapping_id'] ?? null,
            'student_mapping_id' => $studentMappingId,
            'dapodik_id' => $memberDapodikId ?: null,
            'name' => $user?->nama_lengkap ?: $memberName ?: 'Anggota tanpa nama',
            'identifiers' => $this->extractClassMemberIdentifiers($member, $user, $classPreview),
            'class' => [
                'mapping_id' => $classPreview['mapping_id'] ?? null,
                'dapodik_id' => $classPreview['dapodik_id'] ?? null,
                'name' => $classPreview['name'] ?? null,
                'local_id' => $classId > 0 ? $classId : null,
                'local_name' => $classModel?->nama_kelas,
                'tahun_ajaran_id' => $classModel?->tahun_ajaran_id ?? ($classPreview['target']['tahun_ajaran']['id'] ?? null),
                'tahun_ajaran' => $classModel?->tahunAjaran?->nama ?? ($classPreview['target']['tahun_ajaran']['nama'] ?? null),
            ],
            'local' => $user ? [
                'user_id' => (int) $user->id,
                'name' => $user->nama_lengkap,
                'nis' => $user->nis,
                'nisn' => $user->nisn,
            ] : null,
            'current_assignment' => $currentAssignment,
            'action' => $action,
            'blockers' => array_values(array_unique($blockers)),
            'notes' => array_values(array_unique($notes)),
        ];
    }

    private function stagingClassMembershipPreviewSummary(Collection $items): array
    {
        return [
            'eligible' => $items->count(),
            'selected_classes' => $items->pluck('class.mapping_id')->filter()->unique()->count(),
            'assign_candidates' => $items->where('action', 'assign_candidate')->count(),
            'reactivate_candidates' => $items->where('action', 'reactivate_candidate')->count(),
            'no_change' => $items->where('action', 'no_change')->count(),
            'blocked' => $items->where('action', 'blocked')->count(),
            'blocked_missing_mapping' => $this->countClassMembershipItemsWithBlocker($items, 'belum punya mapping exact'),
            'blocked_empty_student_id' => $this->countClassMembershipItemsWithBlocker($items, 'peserta_didik_id kosong'),
        ];
    }

    private function classMembershipReconciliationSummary(DapodikSyncBatch $batch, Collection $items): array
    {
        $memberItems = $items
            ->filter(fn (array $item) => ($item['is_placeholder'] ?? false) !== true)
            ->values();

        $exactStudentMappings = DapodikEntityMapping::query()
            ->where('last_seen_batch_id', $batch->id)
            ->where('entity_type', 'student')
            ->where('confidence', 'exact')
            ->get(['id', 'entity_type', 'dapodik_id', 'siaps_table', 'siaps_id', 'confidence', 'match_key', 'last_seen_batch_id']);

        $memberDapodikIds = $memberItems
            ->pluck('dapodik_id')
            ->filter(fn ($value) => trim((string) $value) !== '')
            ->map(fn ($value) => trim((string) $value))
            ->unique()
            ->values();

        $missingFromMembers = $exactStudentMappings
            ->filter(fn (DapodikEntityMapping $mapping) => !$memberDapodikIds->contains((string) $mapping->dapodik_id))
            ->values();

        $missingMappingItems = $this->classMembershipItemsWithBlocker($items, 'belum punya mapping exact');
        $emptyStudentIdItems = $this->classMembershipItemsWithBlocker($items, 'peserta_didik_id kosong');

        return [
            'exact_student_mappings' => $exactStudentMappings->count(),
            'member_rows' => $memberItems->count(),
            'unique_member_student_ids' => $memberDapodikIds->count(),
            'mapped_member_rows' => $memberItems->filter(fn (array $item) => (int) ($item['local']['user_id'] ?? 0) > 0)->count(),
            'blocked_missing_mapping' => $missingMappingItems->count(),
            'blocked_empty_student_id' => $emptyStudentIdItems->count(),
            'students_not_in_selected_members' => $missingFromMembers->count(),
            'students_not_in_selected_members_sample' => $this->studentMappingSamplePayload($batch, $missingFromMembers),
            'blocked_missing_mapping_sample' => $this->classMembershipItemSamplePayload($missingMappingItems),
            'blocked_empty_student_id_sample' => $this->classMembershipItemSamplePayload($emptyStudentIdItems),
        ];
    }

    private function classMembershipItemsWithBlocker(Collection $items, string $needle): Collection
    {
        return $items
            ->filter(function (array $item) use ($needle) {
                foreach (($item['blockers'] ?? []) as $blocker) {
                    if (Str::contains((string) $blocker, $needle)) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    private function countClassMembershipItemsWithBlocker(Collection $items, string $needle): int
    {
        return $this->classMembershipItemsWithBlocker($items, $needle)->count();
    }

    private function studentMappingSamplePayload(DapodikSyncBatch $batch, Collection $mappings): array
    {
        $sample = $mappings->take(25)->values();
        if ($sample->isEmpty()) {
            return [];
        }

        $records = $this->stagingRecordsForMappings($batch, $sample);

        return $sample
            ->map(function (DapodikEntityMapping $mapping) use ($records) {
                $record = $records['students|' . $mapping->dapodik_id] ?? [];
                $normalized = is_array($record['normalized_data'] ?? null) ? $record['normalized_data'] : [];

                return [
                    'dapodik_id' => $mapping->dapodik_id,
                    'name' => $this->reviewNameFromNormalized('student', $normalized) ?: 'Siswa tanpa nama',
                    'identifiers' => $this->reviewIdentifiersFromNormalized('student', $normalized),
                    'siaps_id' => $mapping->siaps_id ? (int) $mapping->siaps_id : null,
                ];
            })
            ->values()
            ->all();
    }

    private function classMembershipItemSamplePayload(Collection $items): array
    {
        return $items
            ->take(25)
            ->map(fn (array $item) => [
                'dapodik_id' => $item['dapodik_id'] ?? null,
                'name' => $item['name'] ?? null,
                'class_name' => $item['class']['name'] ?? null,
                'identifiers' => $item['identifiers'] ?? [],
                'blockers' => $item['blockers'] ?? [],
            ])
            ->values()
            ->all();
    }

    private function syncClassMembershipPreviewItems(Collection $items, DapodikSyncBatch $batch, ?int $actorId): array
    {
        $summary = [
            'eligible' => $items->count(),
            'assign_candidates' => $items->where('action', 'assign_candidate')->count(),
            'reactivate_candidates' => $items->where('action', 'reactivate_candidate')->count(),
            'assigned_items' => 0,
            'reactivated_items' => 0,
            'no_change' => 0,
            'blocked' => 0,
            'skipped_items' => 0,
            'by_status' => [],
        ];
        $results = [];

        foreach ($items as $item) {
            $result = $this->syncClassMembershipPreviewItem($item, $batch, $actorId);
            $status = $result['status'];
            $summary['by_status'][$status] = ($summary['by_status'][$status] ?? 0) + 1;

            if ($status === 'assigned') {
                $summary['assigned_items']++;
            } elseif ($status === 'reactivated') {
                $summary['reactivated_items']++;
            } elseif ($status === 'no_change') {
                $summary['no_change']++;
            } elseif ($status === 'blocked') {
                $summary['blocked']++;
            } else {
                $summary['skipped_items']++;
            }

            $results[] = $result;
        }

        return [
            'summary' => $summary,
            'items' => $results,
            'has_more' => false,
        ];
    }

    private function syncClassMembershipPreviewItem(array $item, DapodikSyncBatch $batch, ?int $actorId): array
    {
        if (($item['action'] ?? null) === 'blocked') {
            return $this->classMembershipResult($item, 'blocked', $item['blockers'] ?? []);
        }

        if (($item['action'] ?? null) === 'no_change') {
            $siswaId = (int) ($item['local']['user_id'] ?? 0);
            if ($siswaId > 0) {
                $this->freezeInitialAcademicSnapshotForStudent($siswaId);
            }

            return $this->classMembershipResult($item, 'no_change', $item['notes'] ?? []);
        }

        if (!in_array($item['action'] ?? null, ['assign_candidate', 'reactivate_candidate'], true)) {
            return $this->classMembershipResult($item, 'skipped', ['Item ini tidak masuk jalur sinkronisasi anggota kelas.']);
        }

        $classId = (int) ($item['class']['local_id'] ?? 0);
        $siswaId = (int) ($item['local']['user_id'] ?? 0);
        $tahunAjaranId = (int) ($item['class']['tahun_ajaran_id'] ?? 0);

        if ($classId < 1 || $siswaId < 1 || $tahunAjaranId < 1) {
            return $this->classMembershipResult($item, 'blocked', ['Target kelas, siswa, atau tahun ajaran target tidak valid saat sinkronisasi.']);
        }

        $activeAssignment = DB::table('kelas_siswa')
            ->where('siswa_id', $siswaId)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->where('is_active', true)
            ->first(['id', 'kelas_id', 'status', 'is_active']);

        if ($activeAssignment && (int) $activeAssignment->kelas_id !== $classId) {
            $activeClass = Kelas::query()->find((int) $activeAssignment->kelas_id);

            return $this->classMembershipResult(
                $item,
                'blocked',
                ['Siswa masih aktif di kelas ' . ($activeClass?->nama_kelas ?: '#' . (int) $activeAssignment->kelas_id) . '.']
            );
        }

        $existing = DB::table('kelas_siswa')
            ->where('kelas_id', $classId)
            ->where('siswa_id', $siswaId)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->first(['id', 'is_active', 'status', 'tanggal_masuk']);

        if ($existing && (bool) $existing->is_active && (string) $existing->status === 'aktif') {
            $this->freezeInitialAcademicSnapshotForStudent($siswaId);

            return $this->classMembershipResult($item, 'no_change', ['Siswa sudah aktif pada kelas ini.']);
        }

        try {
            if ($existing) {
                DB::table('kelas_siswa')
                    ->where('id', $existing->id)
                    ->update([
                        'status' => 'aktif',
                        'is_active' => true,
                        'tanggal_keluar' => null,
                        'tanggal_masuk' => $existing->tanggal_masuk ?: now()->toDateString(),
                        'keterangan' => 'Reaktivasi anggota kelas dari sinkronisasi Dapodik',
                        'updated_at' => now(),
                    ]);

                $this->freezeInitialAcademicSnapshotForStudent($siswaId);

                return $this->classMembershipResult($item, 'reactivated', ['Anggota kelas diaktifkan ulang.']);
            }

            DB::table('kelas_siswa')->insert([
                'kelas_id' => $classId,
                'siswa_id' => $siswaId,
                'tahun_ajaran_id' => $tahunAjaranId,
                'status' => 'aktif',
                'is_active' => true,
                'tanggal_masuk' => now()->toDateString(),
                'tanggal_keluar' => null,
                'keterangan' => 'Penugasan anggota kelas dari sinkronisasi Dapodik',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->freezeInitialAcademicSnapshotForStudent($siswaId);

            return $this->classMembershipResult($item, 'assigned', ['Siswa ditambahkan ke kelas.']);
        } catch (Throwable $e) {
            $exceptionItem = [
                'mapping_id' => $item['class_mapping_id'] ?? null,
                'entity_type' => 'class_membership',
                'dapodik_id' => $item['dapodik_id'] ?? null,
                'name' => ($item['name'] ?? 'Tanpa Nama') . ' @ ' . ($item['class']['name'] ?? 'Kelas'),
            ];

            throw $this->processItemException(
                ($existing ? 'reactivate_class_member' : 'assign_class_member'),
                $exceptionItem,
                $e
            );
        }
    }

    private function freezeInitialAcademicSnapshotForStudent(int $siswaId): void
    {
        if (
            $siswaId < 1
            || !$this->tableColumnExists('data_pribadi_siswa', 'kelas_awal_id')
            || !$this->tableColumnExists('data_pribadi_siswa', 'tahun_ajaran_awal_id')
            || !$this->tableColumnExists('data_pribadi_siswa', 'tanggal_masuk_kelas_awal')
        ) {
            return;
        }

        $detail = DB::table('data_pribadi_siswa')
            ->where('user_id', $siswaId)
            ->first([
                'id',
                'kelas_awal_id',
                'tahun_ajaran_awal_id',
                'tanggal_masuk_kelas_awal',
            ]);

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

        $tanggalMasuk = $this->normalizeDate($firstMembership->tanggal_masuk ?? null);
        if ($tanggalMasuk === '' && !empty($firstMembership->created_at)) {
            $tanggalMasuk = Carbon::parse($firstMembership->created_at)->toDateString();
        }

        $payload = array_filter([
            'kelas_awal_id' => (int) $firstMembership->kelas_id,
            'tahun_ajaran_awal_id' => (int) $firstMembership->tahun_ajaran_id,
            'tanggal_masuk_kelas_awal' => $tanggalMasuk !== '' ? $tanggalMasuk : null,
            'updated_at' => now(),
        ], fn ($value) => $value !== null && $value !== '');

        DB::table('data_pribadi_siswa')
            ->where('id', (int) $detail->id)
            ->update($payload);
    }

    private function classMembershipResult(array $item, string $status, array $notes): array
    {
        return [
            'key' => $item['key'] ?? null,
            'entity_type' => 'class_membership',
            'class_mapping_id' => $item['class_mapping_id'] ?? null,
            'student_mapping_id' => $item['student_mapping_id'] ?? null,
            'dapodik_id' => $item['dapodik_id'] ?? null,
            'name' => $item['name'] ?? null,
            'identifiers' => $item['identifiers'] ?? [],
            'class' => $item['class'] ?? null,
            'local' => $item['local'] ?? null,
            'current_assignment' => $item['current_assignment'] ?? null,
            'status' => $status,
            'notes' => $notes,
        ];
    }

    private function extractClassMemberDapodikId(array $member): string
    {
        return trim((string) ($member['peserta_didik_id'] ?? ''));
    }

    private function extractClassMemberName(array $member): string
    {
        return $this->cleanString(
            $member['nama']
            ?? $member['nama_siswa']
            ?? $member['nama_peserta_didik']
            ?? $member['peserta_didik_nama']
            ?? $member['nama_pd']
            ?? $member['peserta_didik_id_str']
            ?? ''
        );
    }

    private function extractClassMemberIdentifiers(array $member, ?User $user, array $classPreview): array
    {
        return [
            'nis' => $member['nipd'] ?? $user?->nis,
            'nisn' => $member['nisn'] ?? $user?->nisn,
            'peserta_didik_id' => $member['peserta_didik_id'] ?? null,
            'anggota_rombel_id' => $member['anggota_rombel_id'] ?? null,
            'registrasi_id' => $member['registrasi_id'] ?? null,
            'rombel' => $classPreview['name'] ?? null,
        ];
    }

    private function stagingInputPreviewItems(DapodikSyncBatch $batch, Collection $mappings): Collection
    {
        if ($mappings->isEmpty()) {
            return collect();
        }

        $records = $this->stagingRecordsForMappings($batch, $mappings);
        $context = $this->buildLocalMappingContext();

        return $this->withInputBatchDuplicateBlockers(
            $mappings
                ->map(fn (DapodikEntityMapping $mapping) => $this->stagingInputPreviewItemPayload($mapping, $records, $context))
                ->values()
        );
    }

    private function stagingInputPreviewItemPayload(DapodikEntityMapping $mapping, array $records, array $context): array
    {
        $source = $this->stagingSourceForEntityType($mapping->entity_type);
        $record = $records[$source . '|' . $mapping->dapodik_id] ?? [];
        $normalized = is_array($record['normalized_data'] ?? null) ? $record['normalized_data'] : [];
        $entityType = (string) $mapping->entity_type;
        $blockers = [];

        if ($normalized === []) {
            $blockers[] = 'Normalized staging data tidak ditemukan.';
        }

        if (!in_array($entityType, ['student', 'employee'], true)) {
            $blockers[] = 'Jenis data ini tidak masuk jalur input user baru.';
        }

        if ($blockers === []) {
            $blockers = $this->reviewBlockingIssues($entityType, $normalized);
            $match = $entityType === 'student'
                ? $this->findStudentMatch($normalized, $context['user_indexes'])
                : $this->findEmployeeMatch($normalized, $context['user_indexes']);

            if ($match['ambiguous']) {
                $blockers[] = 'Data masih ambigu dengan user lokal: ' . implode(', ', $match['ambiguous']);
            } elseif ($match['user']) {
                $blockers[] = 'Data sudah cocok dengan user lokal #' . $match['user']->id . ' melalui ' . ($match['key'] ?: '-');
            }

            $accountConflict = $this->candidateAccountConflict(
                (string) ($normalized['username_candidate'] ?? ''),
                (string) ($normalized['email_candidate'] ?? ''),
                $context['user_indexes']
            );
            if ($accountConflict !== null) {
                $blockers[] = $accountConflict;
            }
        }

        $roleName = $this->inputRoleForEntity($entityType, $normalized);
        if ($roleName === null) {
            $blockers[] = 'Role target belum dapat ditentukan.';
        } elseif (!$this->roleExists($roleName)) {
            $blockers[] = "Role target '{$roleName}' belum tersedia.";
        }

        return [
            'mapping_id' => (int) $mapping->id,
            'entity_type' => $entityType,
            'source' => $source,
            'dapodik_id' => $mapping->dapodik_id,
            'confidence' => $mapping->confidence,
            'match_key' => $mapping->match_key,
            'name' => $this->reviewNameFromNormalized($entityType, $normalized),
            'identifiers' => $this->reviewIdentifiersFromNormalized($entityType, $normalized),
            'account' => $this->inputAccountPayload($entityType, $normalized),
            'role' => [
                'name' => $roleName,
                'suggested' => $normalized['suggested_role'] ?? null,
            ],
            'normalized' => $normalized,
            'blockers' => $blockers,
            'action' => $blockers === [] ? 'create_candidate' : 'blocked',
        ];
    }

    private function withInputBatchDuplicateBlockers(Collection $items): Collection
    {
        $usernames = $items
            ->filter(fn (array $item) => ($item['action'] ?? null) === 'create_candidate')
            ->map(fn (array $item) => $this->identifierKey($item['account']['username'] ?? ''))
            ->filter()
            ->countBy()
            ->all();

        $emails = $items
            ->filter(fn (array $item) => ($item['action'] ?? null) === 'create_candidate')
            ->map(fn (array $item) => strtolower(trim((string) ($item['account']['email'] ?? ''))))
            ->filter()
            ->countBy()
            ->all();

        return $items->map(function (array $item) use ($usernames, $emails) {
            if (($item['action'] ?? null) !== 'create_candidate') {
                return $item;
            }

            $blockers = $item['blockers'] ?? [];
            $username = $this->identifierKey($item['account']['username'] ?? '');
            $email = strtolower(trim((string) ($item['account']['email'] ?? '')));

            if ($username !== '' && ($usernames[$username] ?? 0) > 1) {
                $blockers[] = "Username kandidat '{$item['account']['username']}' muncul lebih dari sekali dalam batch ini.";
            }

            if ($email !== '' && ($emails[$email] ?? 0) > 1) {
                $blockers[] = "Email kandidat '{$item['account']['email']}' muncul lebih dari sekali dalam batch ini.";
            }

            if ($blockers !== []) {
                $item['blockers'] = array_values(array_unique($blockers));
                $item['action'] = 'blocked';
            }

            return $item;
        })->values();
    }

    private function stagingInputPreviewSummary(DapodikSyncBatch $batch, array $entityTypes, Collection $items): array
    {
        $byEntity = [];
        foreach ($entityTypes as $entityType) {
            $entityItems = $items->where('entity_type', $entityType);
            $byEntity[$entityType] = [
                'eligible' => $entityItems->count(),
                'create_candidates' => $entityItems->where('action', 'create_candidate')->count(),
                'blocked' => $entityItems->where('action', 'blocked')->count(),
            ];
        }

        return [
            'eligible' => $items->count(),
            'create_candidates' => $items->where('action', 'create_candidate')->count(),
            'blocked' => $items->where('action', 'blocked')->count(),
            'by_entity' => $byEntity,
            'skipped' => [
                'existing_exact' => DapodikEntityMapping::query()
                    ->where('last_seen_batch_id', $batch->id)
                    ->whereIn('entity_type', $entityTypes)
                    ->where('confidence', 'exact')
                    ->count(),
                'needs_review' => DapodikEntityMapping::query()
                    ->where('last_seen_batch_id', $batch->id)
                    ->whereIn('entity_type', $entityTypes)
                    ->whereIn('confidence', ['probable', 'conflict'])
                    ->count(),
                'classes' => DapodikEntityMapping::query()
                    ->where('last_seen_batch_id', $batch->id)
                    ->where('entity_type', 'class')
                    ->count(),
            ],
        ];
    }

    private function createInputItems(Collection $items, ?int $actorId): array
    {
        $summary = [
            'eligible' => $items->count(),
            'create_candidates' => $items->where('action', 'create_candidate')->count(),
            'created_items' => 0,
            'blocked' => 0,
            'skipped_items' => 0,
            'by_entity' => [],
            'by_role' => [],
        ];
        $results = [];

        foreach ($items as $item) {
            $result = $this->createInputItem($item, $actorId);
            $entityType = $item['entity_type'] ?? 'unknown';
            $roleName = $item['role']['name'] ?? 'unknown';

            $summary['by_entity'][$entityType] ??= [
                'eligible' => 0,
                'created_items' => 0,
                'blocked' => 0,
                'skipped_items' => 0,
            ];
            $summary['by_entity'][$entityType]['eligible']++;

            if ($result['status'] === 'created') {
                $summary['created_items']++;
                $summary['by_entity'][$entityType]['created_items']++;
                $summary['by_role'][$roleName] = ($summary['by_role'][$roleName] ?? 0) + 1;
            } elseif ($result['status'] === 'blocked') {
                $summary['blocked']++;
                $summary['by_entity'][$entityType]['blocked']++;
            } else {
                $summary['skipped_items']++;
                $summary['by_entity'][$entityType]['skipped_items']++;
            }

            $results[] = $result;
        }

        return [
            'summary' => $summary,
            'items' => $results,
            'has_more' => false,
        ];
    }

    private function createInputItem(array $item, ?int $actorId): array
    {
        if (($item['action'] ?? null) !== 'create_candidate') {
            return $this->inputItemResult($item, 'blocked', null, $item['blockers'] ?? ['Data tidak memenuhi syarat input.']);
        }

        $mapped = is_array($item['normalized'] ?? null) ? $item['normalized'] : [];
        $entityType = (string) ($item['entity_type'] ?? '');
        $roleName = (string) ($item['role']['name'] ?? '');
        $existingRole = $this->roleNameForAssignment($roleName);

        if ($existingRole === null) {
            return $this->inputItemResult($item, 'blocked', null, ["Role target '{$roleName}' belum tersedia saat input."]);
        }

        $runtimeConflict = $this->inputRuntimeConflict($item);
        if ($runtimeConflict !== null) {
            return $this->inputItemResult($item, 'blocked', null, [$runtimeConflict]);
        }

        $userPayload = $entityType === 'student'
            ? $this->studentInputUserPayload($mapped, $actorId)
            : $this->employeeInputUserPayload($mapped, $actorId);

        try {
            $user = User::query()->create($this->filterPayloadForExistingColumns('users', $userPayload));
        } catch (Throwable $e) {
            throw $this->processItemException('create_user', $item, $e);
        }

        if ($entityType === 'student') {
            try {
                $user->dataPribadiSiswa()->create(
                    $this->filterPayloadForExistingColumns('data_pribadi_siswa', $this->studentInputDetailPayload($mapped))
                );
            } catch (Throwable $e) {
                throw $this->processItemException('create_student_detail', $item, $e);
            }
        } elseif ($entityType === 'employee') {
            try {
                $user->dataKepegawaian()->create(
                    $this->filterPayloadForExistingColumns('data_kepegawaian', $this->employeeInputDetailPayload($mapped))
                );
            } catch (Throwable $e) {
                throw $this->processItemException('create_employee_detail', $item, $e);
            }
        }

        try {
            $user->assignRole($existingRole);
        } catch (Throwable $e) {
            throw $this->processItemException('assign_role', $item, $e);
        }

        $mapping = DapodikEntityMapping::query()->find($item['mapping_id']);
        if ($mapping) {
            try {
                $mapping->forceFill([
                    'siaps_table' => 'users',
                    'siaps_id' => $user->id,
                    'confidence' => 'exact',
                    'match_key' => 'created_from_dapodik',
                    'meta' => [
                        'created_from_dapodik' => true,
                        'created_at' => now()->toISOString(),
                        'role_assigned' => $existingRole,
                        'suggested_role' => $item['role']['suggested'] ?? null,
                    ],
                ])->save();
            } catch (Throwable $e) {
                throw $this->processItemException('save_mapping', $item, $e);
            }
        }

        return $this->inputItemResult($item, 'created', [
            'user_id' => (int) $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'role' => $existingRole,
        ], []);
    }

    private function inputItemResult(array $item, string $status, ?array $created, array $notes): array
    {
        return [
            'mapping_id' => $item['mapping_id'] ?? null,
            'entity_type' => $item['entity_type'] ?? null,
            'dapodik_id' => $item['dapodik_id'] ?? null,
            'name' => $item['name'] ?? null,
            'identifiers' => $item['identifiers'] ?? [],
            'status' => $status,
            'account' => $item['account'] ?? null,
            'role' => $item['role'] ?? null,
            'created' => $created,
            'notes' => $notes,
        ];
    }

    private function processItemException(string $stage, array $item, Throwable $e): DapodikProcessException
    {
        $entityType = (string) ($item['entity_type'] ?? 'unknown');
        $name = trim((string) ($item['name'] ?? '')) ?: 'Tanpa Nama';

        return new DapodikProcessException(
            "Gagal memproses {$entityType} '{$name}' pada tahap {$stage}. " . $e->getMessage(),
            [
                'stage' => $stage,
                'entity_type' => $entityType,
                'name' => $item['name'] ?? null,
                'dapodik_id' => $item['dapodik_id'] ?? null,
                'mapping_id' => $item['mapping_id'] ?? null,
                'location' => basename($e->getFile()) . ':' . $e->getLine(),
                'detail' => $e->getMessage(),
            ],
            $e
        );
    }

    private function exceptionContextPayload(Throwable $e, string $defaultStage): array
    {
        $context = $e instanceof DapodikProcessException ? $e->context() : [];
        $previous = $e->getPrevious();

        return array_filter([
            'stage' => $context['stage'] ?? $defaultStage,
            'entity_type' => $context['entity_type'] ?? null,
            'name' => $context['name'] ?? null,
            'dapodik_id' => $context['dapodik_id'] ?? null,
            'mapping_id' => $context['mapping_id'] ?? null,
            'location' => $context['location'] ?? (basename($e->getFile()) . ':' . $e->getLine()),
            'root_location' => $previous ? basename($previous->getFile()) . ':' . $previous->getLine() : null,
            'detail' => $context['detail'] ?? $e->getMessage(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function inputRoleForEntity(string $entityType, array $mapped): ?string
    {
        if ($entityType === 'student') {
            return RoleNames::SISWA;
        }

        if ($entityType !== 'employee') {
            return null;
        }

        $suggested = RoleNames::normalize((string) ($mapped['suggested_role'] ?? '')) ?? RoleNames::PEGAWAI;

        return in_array($suggested, [RoleNames::GURU, RoleNames::GURU_BK, RoleNames::PEGAWAI], true)
            ? $suggested
            : RoleNames::PEGAWAI;
    }

    private function inputAccountPayload(string $entityType, array $mapped): array
    {
        return [
            'username' => (string) ($mapped['username_candidate'] ?? ''),
            'email' => (string) ($mapped['email_candidate'] ?? ''),
            'password_policy' => $entityType === 'student'
                ? 'Tanggal lahir siswa (DDMMYYYY)'
                : 'Default pegawai SIAPS',
        ];
    }

    private function studentInputUserPayload(array $mapped, ?int $actorId): array
    {
        return [
            'username' => $mapped['username_candidate'] ?? '',
            'email' => $mapped['email_candidate'] ?? '',
            'password' => Hash::make($this->inputDefaultPassword('student', $mapped)),
            'nama_lengkap' => $mapped['nama_lengkap'] ?? '',
            'nisn' => $mapped['nisn'] ?? '',
            'nis' => $mapped['nis'] ?? '',
            'nik' => $mapped['nik'] ?? '',
            'jenis_kelamin' => $mapped['jenis_kelamin'] ?? '',
            'tempat_lahir' => $mapped['tempat_lahir'] ?? '',
            'tanggal_lahir' => $mapped['tanggal_lahir'] ?? '',
            'agama' => $mapped['agama'] ?? '',
            'is_active' => true,
            'created_by' => $actorId,
        ];
    }

    private function studentInputDetailPayload(array $mapped): array
    {
        return [
            'tempat_lahir' => $mapped['tempat_lahir'] ?? '',
            'tanggal_lahir' => $mapped['tanggal_lahir'] ?? '',
            'jenis_kelamin' => $mapped['jenis_kelamin'] ?? '',
            'agama' => $mapped['agama'] ?? '',
            'no_telepon_rumah' => $mapped['no_telepon_rumah'] ?? '',
            'no_hp_siswa' => $mapped['no_hp_siswa'] ?? '',
            'email_siswa' => $mapped['email_siswa'] ?? '',
            'asal_sekolah' => $mapped['asal_sekolah'] ?? '',
            'nama_ayah' => $mapped['nama_ayah'] ?? '',
            'pekerjaan_ayah' => $mapped['pekerjaan_ayah'] ?? '',
            'nama_ibu' => $mapped['nama_ibu'] ?? '',
            'pekerjaan_ibu' => $mapped['pekerjaan_ibu'] ?? '',
            'nama_wali' => $mapped['nama_wali'] ?? '',
            'pekerjaan_wali' => $mapped['pekerjaan_wali'] ?? '',
            'anak_ke' => $mapped['anak_ke'] ?? '',
            'tinggi_badan' => $mapped['tinggi_badan'] ?? '',
            'berat_badan' => $mapped['berat_badan'] ?? '',
            'kebutuhan_khusus' => $mapped['kebutuhan_khusus'] ?? '',
            'tahun_masuk' => $mapped['tahun_masuk'] ?? '',
            'tanggal_masuk_sekolah' => $mapped['tanggal_masuk'] ?? null,
            'status' => 'aktif',
        ];
    }

    private function employeeInputUserPayload(array $mapped, ?int $actorId): array
    {
        return [
            'username' => $mapped['username_candidate'] ?? '',
            'email' => $mapped['email_candidate'] ?? '',
            'password' => Hash::make($this->inputDefaultPassword('employee', $mapped)),
            'nama_lengkap' => $mapped['nama_lengkap'] ?? '',
            'nip' => $mapped['nip'] ?? '',
            'nik' => $mapped['nik'] ?? '',
            'jenis_kelamin' => $mapped['jenis_kelamin'] ?? '',
            'tempat_lahir' => $mapped['tempat_lahir'] ?? '',
            'tanggal_lahir' => $mapped['tanggal_lahir'] ?? '',
            'agama' => $mapped['agama'] ?? '',
            'status_kepegawaian' => $mapped['status_kepegawaian'] ?? '',
            'is_active' => true,
            'created_by' => $actorId,
        ];
    }

    private function employeeInputDetailPayload(array $mapped): array
    {
        return [
            'nip' => $mapped['nip'] ?? '',
            'nuptk' => $mapped['nuptk'] ?? '',
            'tempat_lahir' => $mapped['tempat_lahir'] ?? '',
            'tanggal_lahir' => $mapped['tanggal_lahir'] ?? '',
            'jenis_kelamin' => $mapped['jenis_kelamin'] ?? '',
            'agama' => $mapped['agama'] ?? '',
            'status_kepegawaian' => $mapped['status_kepegawaian'] ?? '',
            'jenis_ptk' => $mapped['jenis_ptk'] ?? '',
            'jabatan' => $mapped['jabatan'] ?? '',
            'pendidikan_terakhir' => $mapped['pendidikan_terakhir'] ?? '',
            'bidang_studi' => $mapped['bidang_studi'] ?? '',
            'pangkat_golongan' => $mapped['pangkat_golongan'] ?? '',
            'is_active' => true,
        ];
    }

    private function inputDefaultPassword(string $entityType, array $mapped): string
    {
        if ($entityType === 'student') {
            try {
                return Carbon::parse((string) ($mapped['tanggal_lahir'] ?? ''))->format('dmY');
            } catch (Throwable) {
                return 'password123';
            }
        }

        return 'ICTsmanis2025$';
    }

    private function filledInputPayload(array $payload): array
    {
        return collect($payload)
            ->reject(fn ($value) => $value === '' || $value === null)
            ->all();
    }

    private function filterPayloadForExistingColumns(string $table, array $payload): array
    {
        $filled = $this->filledInputPayload($payload);
        $columns = $this->resolvedTableColumns($table, $this->defaultColumnsForTable($table));

        if ($columns === []) {
            return $filled;
        }

        return collect($filled)
            ->only($columns)
            ->all();
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        return in_array($column, $this->resolvedTableColumns($table, $this->defaultColumnsForTable($table)), true);
    }

    private function resolvedTableColumns(string $table, array $fallbackColumns = []): array
    {
        if (array_key_exists($table, $this->schemaColumnCache)) {
            $columns = $this->schemaColumnCache[$table];

            return $columns === [] && $fallbackColumns !== [] ? $fallbackColumns : $columns;
        }

        try {
            $columns = Schema::hasTable($table)
                ? array_values(array_unique(Schema::getColumnListing($table)))
                : [];
        } catch (Throwable $e) {
            Log::warning('Dapodik schema inspection failed', [
                'table' => $table,
                'error' => $e->getMessage(),
            ]);

            $columns = [];
        }

        $this->schemaColumnCache[$table] = $columns;

        return $columns === [] && $fallbackColumns !== [] ? $fallbackColumns : $columns;
    }

    private function defaultColumnsForTable(string $table): array
    {
        return match ($table) {
            'users' => [
                'username',
                'email',
                'password',
                'nama_lengkap',
                'nisn',
                'nis',
                'nip',
                'nik',
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'agama',
                'status_kepegawaian',
                'is_active',
                'created_by',
            ],
            'data_pribadi_siswa' => [
                'tempat_lahir',
                'tanggal_lahir',
                'jenis_kelamin',
                'agama',
                'no_telepon_rumah',
                'no_hp_siswa',
                'email_siswa',
                'asal_sekolah',
                'nama_ayah',
                'pekerjaan_ayah',
                'nama_ibu',
                'pekerjaan_ibu',
                'nama_wali',
                'pekerjaan_wali',
                'anak_ke',
                'tinggi_badan',
                'berat_badan',
                'kebutuhan_khusus',
                'tahun_masuk',
                'tanggal_masuk_sekolah',
                'status',
            ],
            'data_kepegawaian' => [
                'nip',
                'nuptk',
                'tempat_lahir',
                'tanggal_lahir',
                'jenis_kelamin',
                'agama',
                'status_kepegawaian',
                'is_active',
            ],
            default => [],
        };
    }

    private function inputRuntimeConflict(array $item): ?string
    {
        $mapped = is_array($item['normalized'] ?? null) ? $item['normalized'] : [];
        $username = (string) ($item['account']['username'] ?? '');
        $email = (string) ($item['account']['email'] ?? '');
        $entityType = (string) ($item['entity_type'] ?? '');

        if ($username !== '' && User::query()->where('username', $username)->exists()) {
            return "Username kandidat '{$username}' sudah dipakai saat input.";
        }

        if ($email !== '' && User::query()->where('email', $email)->exists()) {
            return "Email kandidat '{$email}' sudah dipakai saat input.";
        }

        if ($entityType === 'student') {
            foreach (['nisn', 'nis', 'nik'] as $field) {
                $value = trim((string) ($mapped[$field] ?? ''));
                if ($value !== '' && $this->tableColumnExists('users', $field) && User::query()->where($field, $value)->exists()) {
                    return strtoupper($field) . " '{$value}' sudah ada di user lokal saat input.";
                }
            }
        }

        if ($entityType === 'employee') {
            $nip = trim((string) ($mapped['nip'] ?? ''));
            if ($nip !== '') {
                $exists = ($this->tableColumnExists('users', 'nip') && User::query()->where('nip', $nip)->exists())
                    || (
                        $this->tableColumnExists('data_kepegawaian', 'nip')
                        && User::query()->whereHas('dataKepegawaian', fn ($query) => $query->where('nip', $nip))->exists()
                    );

                if ($exists) {
                    return "NIP '{$nip}' sudah ada di user lokal saat input.";
                }
            }

            $nik = trim((string) ($mapped['nik'] ?? ''));
            if ($nik !== '' && $this->tableColumnExists('users', 'nik') && User::query()->where('nik', $nik)->exists()) {
                return "NIK '{$nik}' sudah ada di user lokal saat input.";
            }

            $nuptk = trim((string) ($mapped['nuptk'] ?? ''));
            if (
                $nuptk !== ''
                && $this->tableColumnExists('data_kepegawaian', 'nuptk')
                && User::query()->whereHas('dataKepegawaian', fn ($query) => $query->where('nuptk', $nuptk))->exists()
            ) {
                return "NUPTK '{$nuptk}' sudah ada di user lokal saat input.";
            }
        }

        $name = trim((string) ($mapped['nama_lengkap'] ?? ''));
        $birthDate = trim((string) ($mapped['tanggal_lahir'] ?? ''));
        if (
            $name !== ''
            && $birthDate !== ''
            && $this->tableColumnExists('users', 'tanggal_lahir')
            && User::query()->where('nama_lengkap', $name)->whereDate('tanggal_lahir', $birthDate)->exists()
        ) {
            return 'Nama dan tanggal lahir sudah ada di user lokal saat input.';
        }

        return null;
    }

    private function roleExists(string $canonicalRole): bool
    {
        return Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->exists();
    }

    private function roleNameForAssignment(string $canonicalRole): ?string
    {
        return Role::query()
            ->whereIn('name', RoleNames::aliases($canonicalRole))
            ->value('name');
    }

    private function applyPreviewItems(Collection $items): array
    {
        $summary = [
            'eligible' => $items->count(),
            'update_candidates' => $items->where('action', 'update_candidate')->count(),
            'applied_items' => 0,
            'no_change' => 0,
            'blocked' => 0,
            'skipped_items' => 0,
            'applied_fields' => 0,
            'skipped_unsafe_fields' => 0,
            'by_entity' => [],
            'by_table' => [],
        ];
        $results = [];

        foreach ($items as $item) {
            $result = $this->applyPreviewItem($item);
            $entityType = $item['entity_type'] ?? 'unknown';

            $summary['by_entity'][$entityType] ??= [
                'eligible' => 0,
                'applied_items' => 0,
                'no_change' => 0,
                'blocked' => 0,
                'skipped_items' => 0,
                'applied_fields' => 0,
                'skipped_unsafe_fields' => 0,
            ];
            $summary['by_entity'][$entityType]['eligible']++;
            $summary['by_entity'][$entityType]['applied_fields'] += $result['applied_field_count'];
            $summary['by_entity'][$entityType]['skipped_unsafe_fields'] += $result['skipped_unsafe_field_count'];

            if ($result['status'] === 'applied') {
                $summary['applied_items']++;
                $summary['by_entity'][$entityType]['applied_items']++;
            } elseif ($result['status'] === 'no_change') {
                $summary['no_change']++;
                $summary['by_entity'][$entityType]['no_change']++;
            } elseif ($result['status'] === 'blocked') {
                $summary['blocked']++;
                $summary['by_entity'][$entityType]['blocked']++;
            } else {
                $summary['skipped_items']++;
                $summary['by_entity'][$entityType]['skipped_items']++;
            }

            $summary['applied_fields'] += $result['applied_field_count'];
            $summary['skipped_unsafe_fields'] += $result['skipped_unsafe_field_count'];

            foreach ($result['applied_by_table'] as $table => $count) {
                $summary['by_table'][$table] = ($summary['by_table'][$table] ?? 0) + $count;
            }

            $results[] = $result;
        }

        return [
            'summary' => $summary,
            'items' => $results,
            'has_more' => false,
        ];
    }

    private function applyPreviewItem(array $item): array
    {
        $changes = $item['changes'] ?? [];
        $unsafeChanges = collect($changes)->filter(fn (array $change) => !($change['safe_auto_apply'] ?? false))->values();
        $safePayloads = $this->safeApplyPayloads($changes);
        $appliedByTable = [];
        $notes = [];

        if (($item['action'] ?? null) === 'blocked') {
            return $this->applyItemResult($item, 'blocked', [], $unsafeChanges->count(), $item['blockers'] ?? []);
        }

        if (($item['action'] ?? null) !== 'update_candidate') {
            return $this->applyItemResult($item, 'no_change', [], $unsafeChanges->count(), []);
        }

        if ($safePayloads === []) {
            $notes[] = $unsafeChanges->isNotEmpty()
                ? 'Hanya ada field yang wajib review manual.'
                : 'Tidak ada field aman untuk di-apply.';

            return $this->applyItemResult($item, 'skipped', [], $unsafeChanges->count(), $notes);
        }

        $user = User::query()
            ->with(['roles', 'dataPribadiSiswa', 'dataKepegawaian'])
            ->find($item['siaps_user_id'] ?? null);

        if (!$user) {
            return $this->applyItemResult($item, 'blocked', [], $unsafeChanges->count(), ['User lokal tidak ditemukan saat apply.']);
        }

        if (($item['entity_type'] ?? null) === 'student' && !$this->userHasRole($user, RoleNames::SISWA)) {
            return $this->applyItemResult($item, 'blocked', [], $unsafeChanges->count(), ['User lokal bukan role Siswa saat apply.']);
        }

        if (($item['entity_type'] ?? null) === 'employee' && $this->userHasRole($user, RoleNames::SISWA)) {
            return $this->applyItemResult($item, 'blocked', [], $unsafeChanges->count(), ['User lokal masih role Siswa saat apply.']);
        }

        if (isset($safePayloads['users'])) {
            $appliedByTable['users'] = $this->applyModelPayload($user, $safePayloads['users']);
        }

        if (isset($safePayloads['data_pribadi_siswa'])) {
            $detail = $user->dataPribadiSiswa ?: $user->dataPribadiSiswa()->make();
            $appliedByTable['data_pribadi_siswa'] = $this->applyModelPayload($detail, $safePayloads['data_pribadi_siswa']);
        }

        if (isset($safePayloads['data_kepegawaian'])) {
            $detail = $user->dataKepegawaian ?: $user->dataKepegawaian()->make();
            $appliedByTable['data_kepegawaian'] = $this->applyModelPayload($detail, $safePayloads['data_kepegawaian']);
        }

        $appliedByTable = array_filter($appliedByTable, fn (int $count) => $count > 0);
        $status = $appliedByTable === [] ? 'no_change' : 'applied';

        return $this->applyItemResult($item, $status, $appliedByTable, $unsafeChanges->count(), $notes);
    }

    private function applyItemResult(
        array $item,
        string $status,
        array $appliedByTable,
        int $skippedUnsafeFieldCount,
        array $notes
    ): array {
        return [
            'mapping_id' => $item['mapping_id'] ?? null,
            'entity_type' => $item['entity_type'] ?? null,
            'dapodik_id' => $item['dapodik_id'] ?? null,
            'siaps_user_id' => $item['siaps_user_id'] ?? null,
            'name' => $item['name'] ?? $item['local']['name'] ?? null,
            'identifiers' => $item['identifiers'] ?? [],
            'local' => $item['local'] ?? null,
            'status' => $status,
            'applied_by_table' => $appliedByTable,
            'applied_field_count' => array_sum($appliedByTable),
            'skipped_unsafe_field_count' => $skippedUnsafeFieldCount,
            'notes' => $notes,
        ];
    }

    private function safeApplyPayloads(array $changes): array
    {
        $payloads = [];

        foreach ($changes as $change) {
            $table = (string) ($change['table'] ?? '');
            $field = (string) ($change['field'] ?? '');

            if (!($change['safe_auto_apply'] ?? false) || !$this->isSafeApplyField($table, $field)) {
                continue;
            }

            $incoming = $change['incoming'] ?? null;
            if ($incoming === null || trim((string) $incoming) === '') {
                continue;
            }

            $payloads[$table][$field] = $incoming;
        }

        return array_filter($payloads);
    }

    private function isSafeApplyField(string $table, string $field): bool
    {
        $fields = [
            'users' => [
                'nama_lengkap',
                'nis',
                'nisn',
                'nik',
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'agama',
                'nip',
                'status_kepegawaian',
            ],
            'data_pribadi_siswa' => [
                'tempat_lahir',
                'tanggal_lahir',
                'jenis_kelamin',
                'agama',
                'no_telepon_rumah',
                'no_hp_siswa',
                'email_siswa',
                'asal_sekolah',
                'nama_ayah',
                'pekerjaan_ayah',
                'nama_ibu',
                'pekerjaan_ibu',
                'nama_wali',
                'pekerjaan_wali',
                'anak_ke',
                'tinggi_badan',
                'berat_badan',
                'kebutuhan_khusus',
                'tahun_masuk',
                'tanggal_masuk_sekolah',
            ],
            'data_kepegawaian' => [
                'nip',
                'nuptk',
                'tempat_lahir',
                'tanggal_lahir',
                'jenis_kelamin',
                'agama',
                'status_kepegawaian',
                'jenis_ptk',
                'jabatan',
                'pendidikan_terakhir',
                'bidang_studi',
                'pangkat_golongan',
            ],
        ];

        return in_array($field, $fields[$table] ?? [], true);
    }

    private function applyModelPayload(mixed $model, array $payload): int
    {
        $table = method_exists($model, 'getTable') ? $model->getTable() : null;
        $filteredPayload = is_string($table)
            ? $this->filterPayloadForExistingColumns($table, $payload)
            : $this->filledInputPayload($payload);

        $model->fill($filteredPayload);

        if (!$model->isDirty()) {
            return 0;
        }

        $dirty = array_keys($model->getDirty());
        $model->save();

        return count($dirty);
    }

    private function studentApplyChangeDetails(User $user, array $mapped): array
    {
        return $this->detailedChangedFields([
            'users' => [
                'nama_lengkap' => [$user->nama_lengkap, $mapped['nama_lengkap'] ?? ''],
                'nis' => [$user->nis, $mapped['nis'] ?? ''],
                'nisn' => [$user->nisn, $mapped['nisn'] ?? ''],
                'nik' => [$user->nik, $mapped['nik'] ?? ''],
                'jenis_kelamin' => [$user->jenis_kelamin, $mapped['jenis_kelamin'] ?? ''],
                'tempat_lahir' => [$user->tempat_lahir, $mapped['tempat_lahir'] ?? ''],
                'tanggal_lahir' => [$this->normalizeDate($user->tanggal_lahir), $mapped['tanggal_lahir'] ?? ''],
                'agama' => [$user->agama, $mapped['agama'] ?? ''],
            ],
            'data_pribadi_siswa' => [
                'tempat_lahir' => [$user->dataPribadiSiswa?->tempat_lahir, $mapped['tempat_lahir'] ?? ''],
                'tanggal_lahir' => [$this->normalizeDate($user->dataPribadiSiswa?->tanggal_lahir), $mapped['tanggal_lahir'] ?? ''],
                'jenis_kelamin' => [$user->dataPribadiSiswa?->jenis_kelamin, $mapped['jenis_kelamin'] ?? ''],
                'agama' => [$user->dataPribadiSiswa?->agama, $mapped['agama'] ?? ''],
                'no_telepon_rumah' => [$user->dataPribadiSiswa?->no_telepon_rumah, $mapped['no_telepon_rumah'] ?? ''],
                'no_hp_siswa' => [$user->dataPribadiSiswa?->no_hp_siswa, $mapped['no_hp_siswa'] ?? ''],
                'email_siswa' => [$user->dataPribadiSiswa?->email_siswa, $mapped['email_siswa'] ?? ''],
                'asal_sekolah' => [$user->dataPribadiSiswa?->asal_sekolah, $mapped['asal_sekolah'] ?? ''],
                'nama_ayah' => [$user->dataPribadiSiswa?->nama_ayah, $mapped['nama_ayah'] ?? ''],
                'pekerjaan_ayah' => [$user->dataPribadiSiswa?->pekerjaan_ayah, $mapped['pekerjaan_ayah'] ?? ''],
                'nama_ibu' => [$user->dataPribadiSiswa?->nama_ibu, $mapped['nama_ibu'] ?? ''],
                'pekerjaan_ibu' => [$user->dataPribadiSiswa?->pekerjaan_ibu, $mapped['pekerjaan_ibu'] ?? ''],
                'nama_wali' => [$user->dataPribadiSiswa?->nama_wali, $mapped['nama_wali'] ?? ''],
                'pekerjaan_wali' => [$user->dataPribadiSiswa?->pekerjaan_wali, $mapped['pekerjaan_wali'] ?? ''],
                'anak_ke' => [$user->dataPribadiSiswa?->anak_ke, $mapped['anak_ke'] ?? ''],
                'tinggi_badan' => [$user->dataPribadiSiswa?->tinggi_badan, $mapped['tinggi_badan'] ?? ''],
                'berat_badan' => [$user->dataPribadiSiswa?->berat_badan, $mapped['berat_badan'] ?? ''],
                'kebutuhan_khusus' => [$user->dataPribadiSiswa?->kebutuhan_khusus, $mapped['kebutuhan_khusus'] ?? ''],
                'tahun_masuk' => [$user->dataPribadiSiswa?->tahun_masuk, $mapped['tahun_masuk'] ?? ''],
                'tanggal_masuk_sekolah' => [$this->normalizeDate($user->dataPribadiSiswa?->tanggal_masuk_sekolah), $mapped['tanggal_masuk'] ?? ''],
            ],
        ]);
    }

    private function employeeApplyChangeDetails(User $user, array $mapped): array
    {
        return $this->detailedChangedFields([
            'users' => [
                'nama_lengkap' => [$user->nama_lengkap, $mapped['nama_lengkap'] ?? ''],
                'nip' => [$user->nip, $mapped['nip'] ?? ''],
                'nik' => [$user->nik, $mapped['nik'] ?? ''],
                'jenis_kelamin' => [$user->jenis_kelamin, $mapped['jenis_kelamin'] ?? ''],
                'tempat_lahir' => [$user->tempat_lahir, $mapped['tempat_lahir'] ?? ''],
                'tanggal_lahir' => [$this->normalizeDate($user->tanggal_lahir), $mapped['tanggal_lahir'] ?? ''],
                'agama' => [$user->agama, $mapped['agama'] ?? ''],
                'status_kepegawaian' => [$user->status_kepegawaian, $mapped['status_kepegawaian'] ?? ''],
                'email' => [$user->email, $mapped['email_candidate'] ?? '', false],
            ],
            'data_kepegawaian' => [
                'nip' => [$user->dataKepegawaian?->nip, $mapped['nip'] ?? ''],
                'nuptk' => [$user->dataKepegawaian?->nuptk, $mapped['nuptk'] ?? ''],
                'tempat_lahir' => [$user->dataKepegawaian?->tempat_lahir, $mapped['tempat_lahir'] ?? ''],
                'tanggal_lahir' => [$this->normalizeDate($user->dataKepegawaian?->tanggal_lahir), $mapped['tanggal_lahir'] ?? ''],
                'jenis_kelamin' => [$user->dataKepegawaian?->jenis_kelamin, $mapped['jenis_kelamin'] ?? ''],
                'agama' => [$user->dataKepegawaian?->agama, $mapped['agama'] ?? ''],
                'status_kepegawaian' => [$user->dataKepegawaian?->status_kepegawaian, $mapped['status_kepegawaian'] ?? ''],
                'jenis_ptk' => [$user->dataKepegawaian?->jenis_ptk, $mapped['jenis_ptk'] ?? ''],
                'jabatan' => [$user->dataKepegawaian?->jabatan, $mapped['jabatan'] ?? ''],
                'pendidikan_terakhir' => [$user->dataKepegawaian?->pendidikan_terakhir, $mapped['pendidikan_terakhir'] ?? ''],
                'bidang_studi' => [$user->dataKepegawaian?->bidang_studi, $mapped['bidang_studi'] ?? ''],
                'pangkat_golongan' => [$user->dataKepegawaian?->pangkat_golongan, $mapped['pangkat_golongan'] ?? ''],
            ],
        ]);
    }

    private function detailedChangedFields(array $tables): array
    {
        $changes = [];

        foreach ($tables as $table => $fields) {
            foreach ($fields as $field => $values) {
                [$local, $incoming] = $values;
                $safeAutoApply = $values[2] ?? true;
                $localComparable = $this->normalizeComparable($local);
                $incomingComparable = $this->normalizeComparable($incoming);

                if ($incomingComparable === '' || $localComparable === $incomingComparable) {
                    continue;
                }

                $changes[] = [
                    'table' => $table,
                    'field' => $field,
                    'current' => $this->displayChangeValue($local),
                    'incoming' => $this->displayChangeValue($incoming),
                    'safe_auto_apply' => (bool) $safeAutoApply,
                ];
            }
        }

        return $changes;
    }

    private function displayChangeValue(mixed $value): ?string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function storeStagingRecords(
        DapodikSyncBatch $batch,
        string $source,
        array $rows,
        array $definition,
        array $dapodikUserRows
    ): int {
        $stored = 0;
        $records = [];
        $now = now();
        $dapodikUsers = $this->buildDapodikUserIndexes($dapodikUserRows);

        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $normalized = $this->normalizeStagingRow($source, $row, $dapodikUsers);
            $records[] = [
                'batch_id' => $batch->id,
                'source' => $source,
                'dapodik_id' => $this->valueFromFirstField($row, [$definition['id_field'] ?? '']),
                'secondary_id' => $this->valueFromFirstField($row, $definition['secondary_fields'] ?? []),
                'row_index' => (int) $index,
                'row_hash' => hash('sha256', $this->encodeJson($row)),
                'row_data' => $this->encodeJson($row),
                'normalized_data' => $normalized !== [] ? $this->encodeJson($normalized) : null,
                'meta' => $this->encodeJson([
                    'endpoint' => $definition['endpoint'] ?? null,
                    'entity_type' => $definition['entity_type'] ?? null,
                ]),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($records) >= 500) {
                DB::table('dapodik_sync_records')->insert($records);
                $stored += count($records);
                $records = [];
            }
        }

        if ($records !== []) {
            DB::table('dapodik_sync_records')->insert($records);
            $stored += count($records);
        }

        return $stored;
    }

    private function normalizeStagingRow(string $source, array $row, array $dapodikUsers): array
    {
        return match ($source) {
            'school' => [
                'sekolah_id' => trim((string) ($row['sekolah_id'] ?? '')),
                'npsn' => trim((string) ($row['npsn'] ?? '')),
                'nama' => $this->cleanString($row['nama'] ?? ''),
                'email' => $this->normalizeEmail($row['email'] ?? ''),
            ],
            'students' => $this->mapDapodikStudent(
                $row,
                $dapodikUsers['by_student_id'][$row['peserta_didik_id'] ?? ''] ?? null
            ),
            'employees' => $this->mapDapodikEmployee(
                $row,
                $dapodikUsers['by_employee_id'][$row['ptk_id'] ?? ''] ?? null
            ),
            'classes' => $this->mapDapodikClass($row),
            'dapodik_users' => [
                'pengguna_id' => trim((string) ($row['pengguna_id'] ?? '')),
                'username' => $this->cleanString($row['username'] ?? ''),
                'email' => $this->normalizeEmail($row['username'] ?? ''),
                'nama' => $this->cleanString($row['nama'] ?? ''),
                'peran' => $this->cleanString($row['peran_id_str'] ?? ''),
                'ptk_id' => trim((string) ($row['ptk_id'] ?? '')),
                'peserta_didik_id' => trim((string) ($row['peserta_didik_id'] ?? '')),
            ],
            default => [],
        };
    }

    private function refreshStagingMappings(
        DapodikSyncBatch $batch,
        array $sourceRows,
        array $context,
        array $dapodikUsers
    ): array {
        $counts = [];

        foreach ($sourceRows['students'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mapped = $this->mapDapodikStudent($row, $dapodikUsers['by_student_id'][$row['peserta_didik_id'] ?? ''] ?? null);
            $match = $this->findStudentMatch($mapped, $context['user_indexes']);
            $confidence = 'unmatched';
            $siapsId = null;
            $matchKey = $match['key'];
            $meta = [
                'match_key' => $matchKey,
                'ambiguous' => $match['ambiguous'],
                'role_conflict' => false,
            ];

            if ($match['ambiguous']) {
                $confidence = 'conflict';
            } elseif ($match['user']) {
                $siapsId = (int) $match['user']->id;
                $confidence = $matchKey === 'nama+tanggal_lahir' ? 'probable' : 'exact';
                if (!$this->userHasRole($match['user'], RoleNames::SISWA)) {
                    $confidence = 'conflict';
                    $meta['role_conflict'] = true;
                }
            }

            $this->upsertDapodikMapping(
                'student',
                $mapped['dapodik_id'],
                'users',
                $siapsId,
                $confidence,
                $matchKey,
                $batch,
                $meta
            );
            $counts['student'][$confidence] = ($counts['student'][$confidence] ?? 0) + 1;
        }

        foreach ($sourceRows['employees'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mapped = $this->mapDapodikEmployee($row, $dapodikUsers['by_employee_id'][$row['ptk_id'] ?? ''] ?? null);
            $match = $this->findEmployeeMatch($mapped, $context['user_indexes']);
            $confidence = 'unmatched';
            $siapsId = null;
            $matchKey = $match['key'];
            $meta = [
                'match_key' => $matchKey,
                'ambiguous' => $match['ambiguous'],
                'role_conflict' => false,
                'suggested_role' => $mapped['suggested_role'],
            ];

            if ($match['ambiguous']) {
                $confidence = 'conflict';
            } elseif ($match['user']) {
                $siapsId = (int) $match['user']->id;
                $confidence = $matchKey === 'nama+tanggal_lahir' ? 'probable' : 'exact';
                if ($this->userHasRole($match['user'], RoleNames::SISWA)) {
                    $confidence = 'conflict';
                    $meta['role_conflict'] = true;
                }
            }

            $this->upsertDapodikMapping(
                'employee',
                $mapped['dapodik_id'],
                'users',
                $siapsId,
                $confidence,
                $matchKey,
                $batch,
                $meta
            );
            $counts['employee'][$confidence] = ($counts['employee'][$confidence] ?? 0) + 1;
        }

        foreach ($sourceRows['classes'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }

            $mapped = $this->mapDapodikClass($row);
            $match = $this->findClassMatch($mapped, $context);
            $confidence = 'unmatched';
            $siapsId = null;
            $matchKey = $match['key'];
            $meta = [
                'match_key' => $matchKey,
                'ambiguous' => $match['ambiguous'],
                'member_count' => $mapped['member_count'],
                'tingkat' => $mapped['tingkat_label'],
            ];

            if ($match['ambiguous']) {
                $confidence = 'conflict';
            } elseif ($match['class']) {
                $siapsId = (int) $match['class']->id;
                $confidence = $matchKey === 'nama+tahun_ajaran_target' ? 'exact' : 'probable';
            }

            $this->upsertDapodikMapping(
                'class',
                $mapped['dapodik_id'],
                'kelas',
                $siapsId,
                $confidence,
                $matchKey,
                $batch,
                $meta
            );
            $counts['class'][$confidence] = ($counts['class'][$confidence] ?? 0) + 1;
        }

        return $counts;
    }

    private function upsertDapodikMapping(
        string $entityType,
        string $dapodikId,
        ?string $siapsTable,
        ?int $siapsId,
        string $confidence,
        ?string $matchKey,
        DapodikSyncBatch $batch,
        array $meta = []
    ): void {
        if (trim($dapodikId) === '') {
            return;
        }

        DapodikEntityMapping::query()->updateOrCreate(
            [
                'entity_type' => $entityType,
                'dapodik_id' => $dapodikId,
            ],
            [
                'siaps_table' => $siapsTable,
                'siaps_id' => $siapsId,
                'confidence' => $confidence,
                'match_key' => $matchKey,
                'last_seen_batch_id' => $batch->id,
                'meta' => $meta,
            ]
        );
    }

    private function valueFromFirstField(array $row, array $fields): ?string
    {
        foreach ($fields as $field) {
            $value = trim((string) ($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function encodeJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function getSetting(string $key, ?string $default = null): ?string
    {
        $setting = RuntimeSetting::query()
            ->where('namespace', self::SETTINGS_NAMESPACE)
            ->where('key', $key)
            ->first();

        return $setting?->value ?? $default;
    }

    private function putSetting(string $key, mixed $value, string $type = 'string'): void
    {
        RuntimeSetting::query()->updateOrCreate(
            ['namespace' => self::SETTINGS_NAMESPACE, 'key' => $key],
            ['value' => $value, 'type' => $type]
        );
    }

    private function getApiToken(): string
    {
        $setting = RuntimeSetting::query()
            ->where('namespace', self::SETTINGS_NAMESPACE)
            ->where('key', 'api_token')
            ->first();

        if (!$setting?->value) {
            return '';
        }

        if ($setting->type !== 'encrypted') {
            return (string) $setting->value;
        }

        try {
            return Crypt::decryptString((string) $setting->value);
        } catch (Throwable) {
            return '';
        }
    }

    private function decodeJsonSetting(string $key): ?array
    {
        $value = $this->getSetting($key);
        if (!$value) {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function saveLastTest(array $result): void
    {
        $this->putSetting('last_test', json_encode($result), 'json');
    }

    private function probeEndpoint(string $baseUrl, string $probePath, string $npsn, string $token, int $previewLimit = 500): array
    {
        $probeUrl = $this->buildProbeUrl($baseUrl, $probePath, $npsn);
        $startedAt = microtime(true);

        try {
            $requestBuilder = Http::timeout(20)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'SIAPS-Dapodik-Test/1.0',
                ]);

            if ($token !== '') {
                $requestBuilder = $requestBuilder->withToken($token);
            }

            $response = $requestBuilder->get($probeUrl);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $contentType = $response->header('content-type') ?? '';
            $body = trim($response->body());
            $isJson = Str::contains(strtolower($contentType), 'json') || $this->looksLikeJson($body);
            $jsonBody = $isJson ? $response->json() : null;
            $dapodikAccepted = !is_array($jsonBody) || ($jsonBody['success'] ?? true) !== false;
            $reachable = $response->status() >= 200 && $response->status() < 500;
            $webServiceReady = $response->successful() && $isJson && $dapodikAccepted;

            return [
                'reachable' => $reachable,
                'web_service_ready' => $webServiceReady,
                'dapodik_accepted' => $dapodikAccepted,
                'status_code' => $response->status(),
                'dapodik_status_code' => is_array($jsonBody) ? ($jsonBody['status_code'] ?? null) : null,
                'content_type' => $contentType,
                'duration_ms' => $durationMs,
                'base_url' => $baseUrl,
                'probe_path' => $probePath,
                'npsn' => $npsn,
                'request_url' => $probeUrl,
                'json_summary' => $this->summarizeJsonBody(is_array($jsonBody) ? $jsonBody : null),
                'response_preview' => Str::limit($body, $previewLimit, '...'),
                'checked_at' => now()->toISOString(),
                'message' => $this->buildTestMessage($response->status(), $isJson, $dapodikAccepted, is_array($jsonBody) ? ($jsonBody['message'] ?? null) : null),
            ];
        } catch (Throwable $exception) {
            return [
                'reachable' => false,
                'web_service_ready' => false,
                'dapodik_accepted' => false,
                'status_code' => null,
                'dapodik_status_code' => null,
                'content_type' => null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'base_url' => $baseUrl,
                'probe_path' => $probePath,
                'npsn' => $npsn,
                'request_url' => $probeUrl,
                'json_summary' => null,
                'response_preview' => null,
                'checked_at' => now()->toISOString(),
                'message' => 'Server Dapodik belum dapat dijangkau.',
                'error' => $exception->getMessage(),
            ];
        }
    }

    private function summarizeJsonBody(?array $body): ?array
    {
        if ($body === null) {
            return null;
        }

        $rows = isset($body['rows']) && is_array($body['rows']) ? $body['rows'] : [];
        $firstRow = is_array($rows[0] ?? null) ? $rows[0] : [];

        return [
            'id' => $body['id'] ?? null,
            'results' => $body['results'] ?? null,
            'start' => $body['start'] ?? null,
            'limit' => $body['limit'] ?? null,
            'row_count' => count($rows),
            'first_row_keys' => array_slice(array_keys($firstRow), 0, 20),
        ];
    }

    private function fetchDapodikRows(string $baseUrl, string $path, string $npsn, string $token): array
    {
        $probePath = $this->normalizeProbePath($path);
        $probeUrl = $this->buildProbeUrl($baseUrl, $probePath, $npsn);
        $startedAt = microtime(true);

        try {
            $requestBuilder = Http::timeout(30)
                ->connectTimeout(5)
                ->acceptJson()
                ->withHeaders([
                    'User-Agent' => 'SIAPS-Dapodik-Preview/1.0',
                ]);

            if ($token !== '') {
                $requestBuilder = $requestBuilder->withToken($token);
            }

            $response = $requestBuilder->get($probeUrl);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $body = trim($response->body());
            $contentType = $response->header('content-type') ?? '';
            $isJson = Str::contains(strtolower($contentType), 'json') || $this->looksLikeJson($body);
            $jsonBody = $isJson ? $response->json() : null;
            $rows = $this->extractRowsFromDapodikBody(is_array($jsonBody) ? $jsonBody : null);
            $dapodikAccepted = !is_array($jsonBody) || ($jsonBody['success'] ?? true) !== false;
            $success = $response->successful() && $isJson && $dapodikAccepted && is_array($rows);

            return [
                'success' => $success,
                'endpoint' => $probePath,
                'request_url' => $probeUrl,
                'status_code' => $response->status(),
                'duration_ms' => $durationMs,
                'row_count' => count($rows),
                'results' => is_array($jsonBody) ? ($jsonBody['results'] ?? null) : null,
                'id' => is_array($jsonBody) ? ($jsonBody['id'] ?? null) : null,
                'message' => $this->buildTestMessage($response->status(), $isJson, $dapodikAccepted, is_array($jsonBody) ? ($jsonBody['message'] ?? null) : null),
                'rows' => $rows,
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'endpoint' => $probePath,
                'request_url' => $probeUrl,
                'status_code' => null,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                'row_count' => 0,
                'results' => null,
                'id' => null,
                'message' => 'Endpoint Dapodik belum dapat dibaca.',
                'error' => $exception->getMessage(),
                'rows' => [],
            ];
        }
    }

    private function extractRowsFromDapodikBody(?array $body): array
    {
        if ($body === null) {
            return [];
        }

        $rows = $body['rows'] ?? $body['data'] ?? [];
        if (is_array($rows) && $rows !== [] && !array_is_list($rows)) {
            return [$rows];
        }

        return is_array($rows) ? array_values($rows) : [];
    }

    private function buildLocalMappingContext(?int $targetTahunAjaranId = null): array
    {
        $users = User::query()
            ->select([
                'id',
                'username',
                'email',
                'nama_lengkap',
                'nik',
                'nisn',
                'nis',
                'nip',
                'jenis_kelamin',
                'tempat_lahir',
                'tanggal_lahir',
                'agama',
                'status_kepegawaian',
                'is_active',
            ])
            ->with([
                'roles:id,name',
                'dataPribadiSiswa:id,user_id,tempat_lahir,tanggal_lahir,jenis_kelamin,agama,asal_sekolah,nama_ayah,pekerjaan_ayah,nama_ibu,pekerjaan_ibu,nama_wali,pekerjaan_wali,no_telepon_rumah,no_hp_siswa,email_siswa,no_hp_ayah,anak_ke,tinggi_badan,berat_badan,kebutuhan_khusus,tahun_masuk,tanggal_masuk_sekolah,kelas_awal_id,tahun_ajaran_awal_id,tanggal_masuk_kelas_awal,status',
                'dataKepegawaian:id,user_id,nip,nuptk,tempat_lahir,tanggal_lahir,jenis_kelamin,agama,status_kepegawaian,jenis_ptk,jabatan,no_hp,pendidikan_terakhir,bidang_studi,pangkat_golongan,is_active',
            ])
            ->get();

        $activeTahunAjaran = $this->resolveActiveTahunAjaranModel();
        $targetTahunAjaran = $this->resolveTargetTahunAjaranModel($targetTahunAjaranId, $activeTahunAjaran);

        $classes = Kelas::query()
            ->select(['id', 'nama_kelas', 'tingkat_id', 'jurusan', 'tahun_ajaran_id', 'wali_kelas_id', 'is_active'])
            ->with(['tingkat:id,nama,kode,urutan', 'tahunAjaran:id,nama,status,is_active', 'waliKelas:id,nama_lengkap'])
            ->get();

        $pivots = DB::table('kelas_siswa')
            ->select(['kelas_id', 'siswa_id', 'tahun_ajaran_id', 'is_active', 'status'])
            ->get();

        return [
            'users' => $users,
            'users_by_id' => $users->keyBy('id'),
            'user_indexes' => $this->buildUserIndexes($users),
            'classes' => $classes,
            'classes_by_id' => $classes->keyBy('id'),
            'class_indexes' => $this->buildClassIndexes($classes),
            'tingkat' => Tingkat::query()->select(['id', 'nama', 'kode', 'urutan', 'is_active'])->get(),
            'active_tahun_ajaran_model' => $activeTahunAjaran,
            'active_tahun_ajaran' => $activeTahunAjaran ? [
                'id' => (int) $activeTahunAjaran->id,
                'nama' => $activeTahunAjaran->nama,
                'status' => $activeTahunAjaran->status,
                'is_active' => (bool) $activeTahunAjaran->is_active,
            ] : null,
            'target_tahun_ajaran_model' => $targetTahunAjaran,
            'target_tahun_ajaran' => $targetTahunAjaran ? [
                'id' => (int) $targetTahunAjaran->id,
                'nama' => $targetTahunAjaran->nama,
                'status' => $targetTahunAjaran->status,
                'is_active' => (bool) $targetTahunAjaran->is_active,
            ] : null,
            'class_memberships' => $this->buildClassMembershipIndex($pivots),
            'student_active_class_memberships' => $this->buildStudentActiveClassMembershipIndex($pivots),
        ];
    }

    private function targetTahunAjaranResponsePayload(?int $targetTahunAjaranId): ?array
    {
        $tahunAjaran = $this->resolveTargetTahunAjaranModel($targetTahunAjaranId);

        return $tahunAjaran ? [
            'id' => (int) $tahunAjaran->id,
            'nama' => $tahunAjaran->nama,
            'status' => $tahunAjaran->status,
            'is_active' => (bool) $tahunAjaran->is_active,
        ] : null;
    }

    private function resolveActiveTahunAjaranModel(): ?TahunAjaran
    {
        return TahunAjaran::query()
            ->where('status', TahunAjaran::STATUS_ACTIVE)
            ->orWhere('is_active', true)
            ->orderByDesc('is_active')
            ->orderByDesc('id')
            ->first();
    }

    private function resolveTargetTahunAjaranModel(?int $targetTahunAjaranId, ?TahunAjaran $fallback = null): ?TahunAjaran
    {
        if ($targetTahunAjaranId && $targetTahunAjaranId > 0) {
            return TahunAjaran::query()->find($targetTahunAjaranId);
        }

        return $fallback ?: $this->resolveActiveTahunAjaranModel();
    }

    private function buildUserIndexes(Collection $users): array
    {
        return [
            'nisn' => $this->indexUsersBy($users, fn (User $user) => $this->digitsOnly($user->nisn)),
            'nis' => $this->indexUsersBy($users, fn (User $user) => $this->identifierKey($user->nis)),
            'nik' => $this->indexUsersBy($users, fn (User $user) => $this->digitsOnly($user->nik)),
            'nip' => $this->indexUsersBy($users, fn (User $user) => $this->digitsOnly($user->nip ?: $user->dataKepegawaian?->nip)),
            'nuptk' => $this->indexUsersBy($users, fn (User $user) => $this->digitsOnly($user->dataKepegawaian?->nuptk)),
            'username' => $this->indexUsersBy($users, fn (User $user) => $this->identifierKey($user->username)),
            'email' => $this->indexUsersBy($users, fn (User $user) => strtolower(trim((string) $user->email))),
            'name_birth' => $this->indexUsersBy($users, fn (User $user) => $this->nameBirthKey($user->nama_lengkap, $user->tanggal_lahir)),
        ];
    }

    private function indexUsersBy(Collection $users, callable $resolver): array
    {
        $index = [];

        foreach ($users as $user) {
            $key = trim((string) $resolver($user));
            if ($key === '') {
                continue;
            }

            $index[$key] ??= [];
            $index[$key][] = $user;
        }

        return $index;
    }

    private function buildClassIndexes(Collection $classes): array
    {
        $byName = [];
        $byNameYear = [];

        foreach ($classes as $class) {
            $nameKey = $this->normalizeTextKey($class->nama_kelas);
            if ($nameKey === '') {
                continue;
            }

            $byName[$nameKey] ??= [];
            $byName[$nameKey][] = $class;

            $yearKey = $nameKey . '|' . (int) $class->tahun_ajaran_id;
            $byNameYear[$yearKey] ??= [];
            $byNameYear[$yearKey][] = $class;
        }

        return [
            'by_name' => $byName,
            'by_name_year' => $byNameYear,
        ];
    }

    private function buildClassMembershipIndex(Collection $pivots): array
    {
        $index = [];

        foreach ($pivots as $pivot) {
            $key = (int) $pivot->kelas_id . '|' . (int) $pivot->siswa_id . '|' . (int) $pivot->tahun_ajaran_id;
            $index[$key] = [
                'is_active' => (bool) $pivot->is_active,
                'status' => $pivot->status,
            ];
        }

        return $index;
    }

    private function buildStudentActiveClassMembershipIndex(Collection $pivots): array
    {
        $index = [];

        foreach ($pivots as $pivot) {
            if (!(bool) $pivot->is_active || (string) $pivot->status !== 'aktif') {
                continue;
            }

            $key = (int) $pivot->siswa_id . '|' . (int) $pivot->tahun_ajaran_id;
            $index[$key] = [
                'kelas_id' => (int) $pivot->kelas_id,
                'tahun_ajaran_id' => (int) $pivot->tahun_ajaran_id,
                'status' => (string) $pivot->status,
                'is_active' => (bool) $pivot->is_active,
            ];
        }

        return $index;
    }

    private function buildDapodikUserIndexes(array $rows): array
    {
        $byStudentId = [];
        $byEmployeeId = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $studentId = trim((string) ($row['peserta_didik_id'] ?? ''));
            if ($studentId !== '') {
                $byStudentId[$studentId] = $row;
            }

            $employeeId = trim((string) ($row['ptk_id'] ?? ''));
            if ($employeeId !== '') {
                $byEmployeeId[$employeeId] = $row;
            }
        }

        return [
            'by_student_id' => $byStudentId,
            'by_employee_id' => $byEmployeeId,
        ];
    }

    private function mapDapodikStudent(array $row, ?array $dapodikUser): array
    {
        $nis = $this->identifierKey($row['nipd'] ?? '');
        $nisn = $this->digitsOnly($row['nisn'] ?? '');
        $nik = $this->digitsOnly($row['nik'] ?? '');
        $dapodikId = trim((string) ($row['peserta_didik_id'] ?? ''));
        $usernameBase = $nis ?: ($nisn ?: ($dapodikUser['username'] ?? ($dapodikId ? 'pd_' . $dapodikId : '')));
        $username = $this->accountIdentifier($usernameBase);

        return [
            'dapodik_id' => $dapodikId,
            'registrasi_id' => trim((string) ($row['registrasi_id'] ?? '')),
            'nama_lengkap' => $this->cleanString($row['nama'] ?? ''),
            'nis' => $nis,
            'nisn' => $nisn,
            'nik' => $nik,
            'jenis_kelamin' => $this->normalizeGender($row['jenis_kelamin'] ?? ''),
            'tempat_lahir' => $this->cleanString($row['tempat_lahir'] ?? ''),
            'tanggal_lahir' => $this->normalizeDate($row['tanggal_lahir'] ?? null),
            'agama' => $this->cleanString($row['agama_id_str'] ?? ''),
            'no_telepon_rumah' => $this->cleanString($row['nomor_telepon_rumah'] ?? ''),
            'no_hp_siswa' => $this->cleanString($row['nomor_telepon_seluler'] ?? ''),
            'email_siswa' => $this->normalizeEmail($row['email'] ?? ''),
            'nama_ayah' => $this->cleanString($row['nama_ayah'] ?? ''),
            'pekerjaan_ayah' => $this->cleanString($row['pekerjaan_ayah_id_str'] ?? ''),
            'nama_ibu' => $this->cleanString($row['nama_ibu'] ?? ''),
            'pekerjaan_ibu' => $this->cleanString($row['pekerjaan_ibu_id_str'] ?? ''),
            'nama_wali' => $this->cleanString($row['nama_wali'] ?? ''),
            'pekerjaan_wali' => $this->cleanString($row['pekerjaan_wali_id_str'] ?? ''),
            'anak_ke' => $this->nullableInteger($row['anak_keberapa'] ?? null),
            'tinggi_badan' => $this->nullableInteger($row['tinggi_badan'] ?? null),
            'berat_badan' => $this->nullableInteger($row['berat_badan'] ?? null),
            'kebutuhan_khusus' => $this->cleanString($row['kebutuhan_khusus'] ?? ''),
            'asal_sekolah' => $this->cleanString($row['sekolah_asal'] ?? ''),
            'tanggal_masuk' => $this->normalizeDate($row['tanggal_masuk_sekolah'] ?? null),
            'tahun_masuk' => $this->yearFromDate($row['tanggal_masuk_sekolah'] ?? null),
            'rombongan_belajar_id' => trim((string) ($row['rombongan_belajar_id'] ?? '')),
            'nama_rombel' => $this->cleanString($row['nama_rombel'] ?? ''),
            'tingkat_pendidikan_id' => trim((string) ($row['tingkat_pendidikan_id'] ?? '')),
            'kurikulum' => $this->cleanString($row['kurikulum_id_str'] ?? ''),
            'username_candidate' => $username,
            'email_candidate' => $username !== '' ? $username . '@' . self::STUDENT_EMAIL_DOMAIN : '',
        ];
    }

    private function mapDapodikEmployee(array $row, ?array $dapodikUser): array
    {
        $nip = $this->digitsOnly($row['nip'] ?? '');
        $nuptk = $this->digitsOnly($row['nuptk'] ?? '');
        $nik = $this->digitsOnly($row['nik'] ?? '');
        $ptkId = trim((string) ($row['ptk_id'] ?? ''));
        $usernameBase = $nip ?: ($nuptk ?: ($dapodikUser['username'] ?? ($ptkId ? 'gtk_' . $ptkId : '')));
        $username = $this->accountIdentifier($usernameBase);
        $dapodikEmail = $this->normalizeEmail($dapodikUser['username'] ?? '');
        $jenisPtk = $this->cleanString($row['jenis_ptk_id_str'] ?? '');
        $jabatan = $this->cleanString($row['jabatan_ptk_id_str'] ?? '');

        return [
            'dapodik_id' => $ptkId,
            'ptk_terdaftar_id' => trim((string) ($row['ptk_terdaftar_id'] ?? '')),
            'nama_lengkap' => $this->cleanString($row['nama'] ?? ''),
            'nip' => $nip,
            'nuptk' => $nuptk,
            'nik' => $nik,
            'jenis_kelamin' => $this->normalizeGender($row['jenis_kelamin'] ?? ''),
            'tempat_lahir' => $this->cleanString($row['tempat_lahir'] ?? ''),
            'tanggal_lahir' => $this->normalizeDate($row['tanggal_lahir'] ?? null),
            'agama' => $this->cleanString($row['agama_id_str'] ?? ''),
            'status_kepegawaian' => $this->mapEmploymentStatus($row['status_kepegawaian_id_str'] ?? ''),
            'status_kepegawaian_raw' => $this->cleanString($row['status_kepegawaian_id_str'] ?? ''),
            'jenis_ptk' => $jenisPtk,
            'jabatan' => $jabatan,
            'pendidikan_terakhir' => $this->cleanString($row['pendidikan_terakhir'] ?? ''),
            'bidang_studi' => $this->cleanString($row['bidang_studi_terakhir'] ?? ''),
            'pangkat_golongan' => $this->cleanString($row['pangkat_golongan_terakhir'] ?? ''),
            'suggested_role' => $this->suggestEmployeeRole($jenisPtk, $jabatan),
            'username_candidate' => $username,
            'email_candidate' => $dapodikEmail !== '' ? $dapodikEmail : ($username !== '' ? $username . '@' . self::EMPLOYEE_EMAIL_DOMAIN : ''),
        ];
    }

    private function mapDapodikClass(array $row): array
    {
        $members = $this->extractClassMembers($row['anggota_rombel'] ?? []);
        $semesterInfo = $this->parseDapodikSemesterInfo($row['semester_id'] ?? null);

        return [
            'dapodik_id' => trim((string) ($row['rombongan_belajar_id'] ?? '')),
            'nama_kelas' => $this->cleanString($row['nama'] ?? ''),
            'tingkat_id' => trim((string) ($row['tingkat_pendidikan_id'] ?? '')),
            'tingkat_label' => $this->cleanString($row['tingkat_pendidikan_id_str'] ?? ''),
            'jurusan' => $this->cleanString($row['jurusan_id_str'] ?? ''),
            'wali_ptk_id' => trim((string) ($row['ptk_id'] ?? '')),
            'wali_ptk_name' => $this->cleanString($row['ptk_id_str'] ?? ''),
            'semester_id' => $semesterInfo['semester_id'],
            'dapodik_tahun_ajaran' => $semesterInfo['tahun_ajaran'],
            'dapodik_semester' => $semesterInfo['semester'],
            'members' => $members,
            'member_count' => count($members),
        ];
    }

    private function parseDapodikSemesterInfo($value): array
    {
        $semesterId = preg_replace('/\D+/', '', trim((string) $value));
        if ($semesterId === '' || strlen($semesterId) < 5) {
            return [
                'semester_id' => $semesterId !== '' ? $semesterId : null,
                'tahun_ajaran' => null,
                'semester' => null,
            ];
        }

        $startYear = (int) substr($semesterId, 0, 4);
        $semesterCode = (int) substr($semesterId, -1);
        if ($startYear < 2000 || !in_array($semesterCode, [1, 2], true)) {
            return [
                'semester_id' => $semesterId,
                'tahun_ajaran' => null,
                'semester' => null,
            ];
        }

        return [
            'semester_id' => $semesterId,
            'tahun_ajaran' => $startYear . '/' . ($startYear + 1),
            'semester' => $semesterCode === 1 ? 'Ganjil' : 'Genap',
        ];
    }

    private function academicYearLabelsMatch(?string $left, ?string $right): bool
    {
        $leftKey = $this->normalizeAcademicYearKey($left);
        $rightKey = $this->normalizeAcademicYearKey($right);

        return $leftKey !== '' && $rightKey !== '' && $leftKey === $rightKey;
    }

    private function normalizeAcademicYearKey(?string $value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }

        if (preg_match('/(20\d{2})\D+(20\d{2})/', $text, $matches)) {
            return $matches[1] . '/' . $matches[2];
        }

        return strtolower(preg_replace('/\s+/', ' ', $text));
    }

    private function findStudentMatch(array $mapped, array $indexes): array
    {
        return $this->findIndexedUserMatch([
            'nisn' => $mapped['nisn'],
            'nis' => $mapped['nis'],
            'nik' => $mapped['nik'],
            'nama+tanggal_lahir' => $this->nameBirthKey($mapped['nama_lengkap'], $mapped['tanggal_lahir']),
        ], $indexes);
    }

    private function findEmployeeMatch(array $mapped, array $indexes): array
    {
        return $this->findIndexedUserMatch([
            'nip' => $mapped['nip'],
            'nuptk' => $mapped['nuptk'],
            'nik' => $mapped['nik'],
            'nama+tanggal_lahir' => $this->nameBirthKey($mapped['nama_lengkap'], $mapped['tanggal_lahir']),
        ], $indexes);
    }

    private function findIndexedUserMatch(array $candidates, array $indexes): array
    {
        $ambiguous = [];

        foreach ($candidates as $key => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $indexKey = match ($key) {
                'nama+tanggal_lahir' => 'name_birth',
                default => $key,
            };
            $matches = $indexes[$indexKey][$value] ?? [];

            if (count($matches) === 1) {
                return ['user' => $matches[0], 'key' => $key, 'ambiguous' => []];
            }

            if (count($matches) > 1) {
                $ambiguous[] = $key;
            }
        }

        return ['user' => null, 'key' => null, 'ambiguous' => $ambiguous];
    }

    private function findClassMatch(array $mapped, array $context): array
    {
        $nameKey = $this->normalizeTextKey($mapped['nama_kelas']);
        if ($nameKey === '') {
            return ['class' => null, 'key' => null, 'ambiguous' => false];
        }

        $targetYear = $context['target_tahun_ajaran_model'] ?? null;
        if ($targetYear) {
            $yearKey = $nameKey . '|' . (int) $targetYear->id;
            $matches = $context['class_indexes']['by_name_year'][$yearKey] ?? [];
            if (count($matches) === 1) {
                return ['class' => $matches[0], 'key' => 'nama+tahun_ajaran_target', 'ambiguous' => false];
            }
            if (count($matches) > 1) {
                return ['class' => null, 'key' => null, 'ambiguous' => true];
            }

            return ['class' => null, 'key' => null, 'ambiguous' => false];
        }

        $matches = $context['class_indexes']['by_name'][$nameKey] ?? [];
        if (count($matches) === 1) {
            return ['class' => $matches[0], 'key' => 'nama', 'ambiguous' => false];
        }

        return ['class' => null, 'key' => null, 'ambiguous' => count($matches) > 1];
    }

    private function findTingkatMatch(string $label, string $id, Collection $levels): mixed
    {
        $candidates = array_filter([
            $this->normalizeTingkatKey($label),
            $this->normalizeTingkatKey($id),
            $this->normalizeTingkatKey($this->numericTingkatToRoman($id)),
        ]);

        foreach ($levels as $level) {
            $levelKeys = array_filter([
                $this->normalizeTingkatKey($level->nama),
                $this->normalizeTingkatKey($level->kode),
                $this->normalizeTingkatKey((string) $level->urutan),
                $this->normalizeTingkatKey($this->numericTingkatToRoman((string) $level->urutan)),
            ]);

            if (array_intersect($candidates, $levelKeys) !== []) {
                return $level;
            }
        }

        return null;
    }

    private function studentChanges(User $user, array $mapped): array
    {
        return $this->changedFields([
            'nama_lengkap' => [$user->nama_lengkap, $mapped['nama_lengkap']],
            'nis' => [$user->nis, $mapped['nis']],
            'nisn' => [$user->nisn, $mapped['nisn']],
            'nik' => [$user->nik, $mapped['nik']],
            'jenis_kelamin' => [$user->jenis_kelamin, $mapped['jenis_kelamin']],
            'tanggal_lahir' => [$this->normalizeDate($user->tanggal_lahir), $mapped['tanggal_lahir']],
            'agama' => [$user->agama ?: $user->dataPribadiSiswa?->agama, $mapped['agama']],
            'email_siswa' => [$user->dataPribadiSiswa?->email_siswa, $mapped['email_siswa']],
            'asal_sekolah' => [$user->dataPribadiSiswa?->asal_sekolah, $mapped['asal_sekolah']],
            'nama_ayah' => [$user->dataPribadiSiswa?->nama_ayah, $mapped['nama_ayah']],
            'pekerjaan_ayah' => [$user->dataPribadiSiswa?->pekerjaan_ayah, $mapped['pekerjaan_ayah']],
            'nama_ibu' => [$user->dataPribadiSiswa?->nama_ibu, $mapped['nama_ibu']],
            'pekerjaan_ibu' => [$user->dataPribadiSiswa?->pekerjaan_ibu, $mapped['pekerjaan_ibu']],
            'nama_wali' => [$user->dataPribadiSiswa?->nama_wali, $mapped['nama_wali']],
            'pekerjaan_wali' => [$user->dataPribadiSiswa?->pekerjaan_wali, $mapped['pekerjaan_wali']],
            'anak_ke' => [$user->dataPribadiSiswa?->anak_ke, $mapped['anak_ke']],
            'tinggi_badan' => [$user->dataPribadiSiswa?->tinggi_badan, $mapped['tinggi_badan']],
            'berat_badan' => [$user->dataPribadiSiswa?->berat_badan, $mapped['berat_badan']],
            'kebutuhan_khusus' => [$user->dataPribadiSiswa?->kebutuhan_khusus, $mapped['kebutuhan_khusus']],
            'tahun_masuk' => [$user->dataPribadiSiswa?->tahun_masuk, $mapped['tahun_masuk']],
        ]);
    }

    private function employeeChanges(User $user, array $mapped): array
    {
        return $this->changedFields([
            'nama_lengkap' => [$user->nama_lengkap, $mapped['nama_lengkap']],
            'nip' => [$user->nip ?: $user->dataKepegawaian?->nip, $mapped['nip']],
            'nuptk' => [$user->dataKepegawaian?->nuptk, $mapped['nuptk']],
            'nik' => [$user->nik, $mapped['nik']],
            'jenis_kelamin' => [$user->jenis_kelamin, $mapped['jenis_kelamin']],
            'tanggal_lahir' => [$this->normalizeDate($user->tanggal_lahir ?: $user->dataKepegawaian?->tanggal_lahir), $mapped['tanggal_lahir']],
            'status_kepegawaian' => [$user->status_kepegawaian ?: $user->dataKepegawaian?->status_kepegawaian, $mapped['status_kepegawaian']],
            'jabatan' => [$user->dataKepegawaian?->jabatan, $mapped['jabatan']],
            'pendidikan_terakhir' => [$user->dataKepegawaian?->pendidikan_terakhir, $mapped['pendidikan_terakhir']],
            'bidang_studi' => [$user->dataKepegawaian?->bidang_studi, $mapped['bidang_studi']],
            'pangkat_golongan' => [$user->dataKepegawaian?->pangkat_golongan, $mapped['pangkat_golongan']],
        ]);
    }

    private function changedFields(array $pairs): array
    {
        $changes = [];

        foreach ($pairs as $field => [$local, $incoming]) {
            $local = $this->normalizeComparable($local);
            $incoming = $this->normalizeComparable($incoming);
            if ($incoming !== '' && $local !== $incoming) {
                $changes[] = $field;
            }
        }

        return $changes;
    }

    private function studentBlockingIssues(array $mapped): array
    {
        return $this->requiredIssues($mapped, [
            'nama_lengkap' => 'Nama kosong',
            'jenis_kelamin' => 'Jenis kelamin kosong/tidak valid',
            'tanggal_lahir' => 'Tanggal lahir kosong/tidak valid',
            'username_candidate' => 'Identitas untuk username belum ada',
        ]);
    }

    private function employeeBlockingIssues(array $mapped): array
    {
        return $this->requiredIssues($mapped, [
            'nama_lengkap' => 'Nama kosong',
            'jenis_kelamin' => 'Jenis kelamin kosong/tidak valid',
            'tanggal_lahir' => 'Tanggal lahir kosong/tidak valid',
            'status_kepegawaian' => 'Status kepegawaian belum bisa dipetakan ke ASN/Honorer',
            'username_candidate' => 'Identitas untuk username belum ada',
        ]);
    }

    private function requiredIssues(array $mapped, array $rules): array
    {
        $issues = [];

        foreach ($rules as $key => $message) {
            if (trim((string) ($mapped[$key] ?? '')) === '') {
                $issues[] = $message;
            }
        }

        return $issues;
    }

    private function candidateAccountConflict(string $username, string $email, array $indexes): ?string
    {
        if ($username !== '' && !empty($indexes['username'][$this->identifierKey($username)] ?? [])) {
            return "Username kandidat '{$username}' sudah dipakai user lokal lain";
        }

        if ($email !== '' && !empty($indexes['email'][strtolower(trim($email))] ?? [])) {
            return "Email kandidat '{$email}' sudah dipakai user lokal lain";
        }

        return null;
    }

    private function userHasRole(User $user, string $canonicalRole): bool
    {
        $aliases = RoleNames::aliases($canonicalRole);

        return $user->roles->pluck('name')->intersect($aliases)->isNotEmpty();
    }

    private function extractClassMembers(mixed $members): array
    {
        if (is_string($members)) {
            $decoded = json_decode($members, true);
            $members = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($members)) {
            return [];
        }

        return array_values(array_filter($members, fn ($member) => is_array($member)));
    }

    private function mapEmploymentStatus(mixed $value): string
    {
        $normalized = $this->normalizeTextKey($value);

        if (Str::contains($normalized, ['honor', 'gtt', 'ptt', 'non asn', 'nonasn', 'non pns', 'nonpns'])) {
            return 'Honorer';
        }

        if (Str::contains($normalized, ['pns', 'cpns', 'pppk', 'asn'])) {
            return 'ASN';
        }

        return '';
    }

    private function suggestEmployeeRole(string $jenisPtk, string $jabatan): string
    {
        $text = $this->normalizeTextKey($jenisPtk . ' ' . $jabatan);

        if (Str::contains($text, ['kepala sekolah'])) {
            return RoleNames::KEPALA_SEKOLAH;
        }

        if (Str::contains($text, ['bk', 'bimbingan konseling'])) {
            return RoleNames::GURU_BK;
        }

        if (Str::contains($text, ['guru'])) {
            return RoleNames::GURU;
        }

        return RoleNames::PEGAWAI;
    }

    private function normalizeGender(mixed $value): string
    {
        $normalized = $this->normalizeTextKey($value);

        if (in_array($normalized, ['l', 'laki laki', 'pria', 'male'], true)) {
            return 'L';
        }

        if (in_array($normalized, ['p', 'perempuan', 'wanita', 'female'], true)) {
            return 'P';
        }

        return '';
    }

    private function normalizeDate(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (Throwable) {
            return '';
        }
    }

    private function yearFromDate(mixed $value): string
    {
        $date = $this->normalizeDate($value);
        if ($date === '') {
            return '';
        }

        return substr($date, 0, 4);
    }

    private function cleanString(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }

    private function normalizeEmail(mixed $value): string
    {
        $value = strtolower($this->cleanString($value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : '';
    }

    private function nullableInteger(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        return preg_match('/^-?\d+$/', $value) === 1 ? $value : '';
    }

    private function digitsOnly(mixed $value): string
    {
        return preg_replace('/\D+/', '', (string) $value) ?? '';
    }

    private function identifierKey(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    private function accountIdentifier(mixed $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9._-]+/', '_', $value) ?? '';

        return trim($value, '._-');
    }

    private function nameBirthKey(mixed $name, mixed $date): string
    {
        $nameKey = $this->normalizeTextKey($name);
        $dateKey = $this->normalizeDate($date);

        if ($nameKey === '' || $dateKey === '') {
            return '';
        }

        return $nameKey . '|' . $dateKey;
    }

    private function normalizeComparable(mixed $value): string
    {
        if ($value instanceof Carbon) {
            return $value->toDateString();
        }

        return $this->normalizeTextKey($value);
    }

    private function normalizeTextKey(mixed $value): string
    {
        $value = Str::ascii(Str::lower(trim((string) $value)));
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    private function normalizeTingkatKey(mixed $value): string
    {
        $key = $this->normalizeTextKey($value);
        $key = trim(str_replace('kelas ', '', $key));

        return match ($key) {
            '10', 'x' => 'x',
            '11', 'xi' => 'xi',
            '12', 'xii' => 'xii',
            default => $key,
        };
    }

    private function numericTingkatToRoman(string $value): string
    {
        return match (trim($value)) {
            '10' => 'X',
            '11' => 'XI',
            '12' => 'XII',
            default => '',
        };
    }

    private function normalizeBaseUrl(string $url): string
    {
        return rtrim(trim($url), '/');
    }

    private function normalizeProbePath(mixed $path): string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return self::DEFAULT_PROBE_PATH;
        }

        return Str::startsWith($path, '/') ? $path : '/' . $path;
    }

    private function joinUrl(string $baseUrl, string $path): string
    {
        return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    }

    private function buildProbeUrl(string $baseUrl, string $path, string $npsn): string
    {
        $url = $this->joinUrl($baseUrl, $path);
        if ($npsn === '' || Str::contains($url, ['?npsn=', '&npsn='])) {
            return $url;
        }

        $separator = Str::contains($url, '?') ? '&' : '?';

        return $url . $separator . 'npsn=' . rawurlencode($npsn);
    }

    private function buildTestMessage(int $statusCode, bool $isJson, bool $dapodikAccepted, ?string $dapodikMessage): string
    {
        if (!$isJson) {
            return 'Server Dapodik terhubung, tetapi endpoint test belum mengarah ke web service JSON.';
        }

        if (!$dapodikAccepted) {
            return $dapodikMessage ?: 'Web service Dapodik menolak request SIAPS.';
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            return 'Web service Dapodik merespons JSON, tetapi status HTTP belum sukses.';
        }

        return 'Web service Dapodik merespons JSON.';
    }

    private function looksLikeJson(string $body): bool
    {
        if ($body === '') {
            return false;
        }

        $first = $body[0];

        return $first === '{' || $first === '[';
    }
}

final class DapodikProcessException extends \RuntimeException
{
    public function __construct(
        string $message,
        private array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function context(): array
    {
        return $this->context;
    }
}
