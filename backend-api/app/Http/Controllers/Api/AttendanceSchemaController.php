<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceGovernanceLog;
use App\Models\AttendanceSchema;
use App\Models\AttendanceSchemaChangeLog;
use App\Services\AttendanceSchemaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AttendanceSchemaController extends Controller
{
    private AttendanceSchemaService $attendanceSchemaService;

    public function __construct(AttendanceSchemaService $attendanceSchemaService)
    {
        $this->attendanceSchemaService = $attendanceSchemaService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        try {
            $schemas = AttendanceSchema::orderBy('priority', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $schemas,
                'message' => 'Attendance schemas retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching attendance schemas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve attendance schemas'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schema_name' => 'required|string|max:100',
            'schema_type' => 'required|string|max:50',
            'jam_masuk_default' => 'required|string',
            'jam_pulang_default' => 'required|string',
            'siswa_jam_masuk' => 'nullable|string',
            'siswa_jam_pulang' => 'nullable|string',
            'gps_accuracy' => 'nullable|integer|min:1|max:200',
            'face_verification_enabled' => 'nullable|boolean',
            'verification_mode' => 'nullable|in:sync_final,async_pending',
            'attendance_scope' => 'nullable|in:siswa_only',
            'target_tingkat_ids' => 'nullable|array',
            'target_tingkat_ids.*' => 'integer|exists:tingkat,id',
            'target_kelas_ids' => 'nullable|array',
            'target_kelas_ids.*' => 'integer|exists:kelas,id',
            'total_violation_minutes_semester_limit' => 'nullable|integer|min:0|max:100000',
            'alpha_days_semester_limit' => 'nullable|integer|min:0|max:365',
            'late_minutes_monthly_limit' => 'nullable|integer|min:0|max:100000',
            'discipline_thresholds_enabled' => 'nullable|boolean',
            'semester_total_violation_mode' => 'nullable|string|in:monitor_only,alertable',
            'notify_wali_kelas_on_total_violation_limit' => 'nullable|boolean',
            'notify_kesiswaan_on_total_violation_limit' => 'nullable|boolean',
            'semester_alpha_mode' => 'nullable|string|in:monitor_only,alertable',
            'monthly_late_mode' => 'nullable|string|in:monitor_only,alertable',
            'notify_wali_kelas_on_late_limit' => 'nullable|boolean',
            'notify_kesiswaan_on_late_limit' => 'nullable|boolean',
            'notify_wali_kelas_on_alpha_limit' => 'nullable|boolean',
            'notify_kesiswaan_on_alpha_limit' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = Auth::user() ? Auth::user()->id : null;

            $schema = AttendanceSchema::create([
                'schema_name' => $request->schema_name,
                'schema_type' => $request->schema_type,
                'target_role' => $request->target_role,
                'target_status' => $request->target_status,
                'schema_description' => $request->schema_description,
                'is_active' => $request->is_active ?? true,
                'is_default' => $request->is_default ?? false,
                'is_mandatory' => $request->is_mandatory ?? true,
                'priority' => $request->priority ?? 0,
                'version' => 1,
                'jam_masuk_default' => $request->jam_masuk_default,
                'jam_pulang_default' => $request->jam_pulang_default,
                'toleransi_default' => $request->toleransi_default ?? 15,
                'minimal_open_time_staff' => $request->minimal_open_time_staff ?? 70,
                'minimal_open_time_siswa' => $request->minimal_open_time_siswa ?? 70,
                'wajib_gps' => $request->wajib_gps ?? true,
                'wajib_foto' => $request->wajib_foto ?? true,
                'face_verification_enabled' => $request->has('face_verification_enabled')
                    ? $request->boolean('face_verification_enabled')
                    : null,
                'hari_kerja' => $this->normalizeStringArray(
                    $request->input('hari_kerja'),
                    ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']
                ),
                'lokasi_gps_ids' => $this->normalizeIntArray($request->input('lokasi_gps_ids')),
                'siswa_jam_masuk' => $request->siswa_jam_masuk,
                'siswa_jam_pulang' => $request->siswa_jam_pulang,
                'siswa_toleransi' => $request->siswa_toleransi ?? 15,
                'gps_accuracy' => (int) $request->input('gps_accuracy', 20),
                'verification_mode' => $request->verification_mode ?? 'async_pending',
                // Policy sekolah: absensi aplikasi ini khusus siswa.
                'attendance_scope' => 'siswa_only',
                'target_tingkat_ids' => $this->normalizeNullableIntArray($request, 'target_tingkat_ids'),
                'target_kelas_ids' => $this->normalizeNullableIntArray($request, 'target_kelas_ids'),
                'total_violation_minutes_semester_limit' => $request->input('total_violation_minutes_semester_limit'),
                'alpha_days_semester_limit' => $request->input('alpha_days_semester_limit'),
                'late_minutes_monthly_limit' => $request->input('late_minutes_monthly_limit'),
                'discipline_thresholds_enabled' => $request->boolean('discipline_thresholds_enabled', true),
                'semester_total_violation_mode' => $request->input('semester_total_violation_mode', 'monitor_only'),
                'notify_wali_kelas_on_total_violation_limit' => $request->boolean('notify_wali_kelas_on_total_violation_limit', false),
                'notify_kesiswaan_on_total_violation_limit' => $request->boolean('notify_kesiswaan_on_total_violation_limit', false),
                'semester_alpha_mode' => $request->input('semester_alpha_mode', 'alertable'),
                'monthly_late_mode' => $request->input('monthly_late_mode', 'monitor_only'),
                'notify_wali_kelas_on_late_limit' => $request->boolean('notify_wali_kelas_on_late_limit', false),
                'notify_kesiswaan_on_late_limit' => $request->boolean('notify_kesiswaan_on_late_limit', false),
                'notify_wali_kelas_on_alpha_limit' => $request->input('notify_wali_kelas_on_alpha_limit'),
                'notify_kesiswaan_on_alpha_limit' => $request->input('notify_kesiswaan_on_alpha_limit'),
                'updated_by' => $userId
            ]);

            $this->logSchemaChangeSafe(
                (int) $schema->id,
                'created',
                null,
                $schema->toArray(),
                $userId,
                'Schema created via API'
            );
            $this->invalidateAttendanceCaches(clearAllSchemas: true);

            return response()->json([
                'success' => true,
                'data' => $schema,
                'message' => 'Attendance schema created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating attendance schema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create attendance schema'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $schema = AttendanceSchema::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $schema,
                'message' => 'Attendance schema retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching attendance schema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Attendance schema not found'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'schema_name' => 'required|string|max:100',
            'schema_type' => 'required|string|max:50',
            'jam_masuk_default' => 'required|string',
            'jam_pulang_default' => 'required|string',
            'siswa_jam_masuk' => 'nullable|string',
            'siswa_jam_pulang' => 'nullable|string',
            'gps_accuracy' => 'nullable|integer|min:1|max:200',
            'face_verification_enabled' => 'nullable|boolean',
            'verification_mode' => 'nullable|in:sync_final,async_pending',
            'attendance_scope' => 'nullable|in:siswa_only',
            'target_tingkat_ids' => 'nullable|array',
            'target_tingkat_ids.*' => 'integer|exists:tingkat,id',
            'target_kelas_ids' => 'nullable|array',
            'target_kelas_ids.*' => 'integer|exists:kelas,id',
            'total_violation_minutes_semester_limit' => 'nullable|integer|min:0|max:100000',
            'alpha_days_semester_limit' => 'nullable|integer|min:0|max:365',
            'late_minutes_monthly_limit' => 'nullable|integer|min:0|max:100000',
            'discipline_thresholds_enabled' => 'nullable|boolean',
            'semester_total_violation_mode' => 'nullable|string|in:monitor_only,alertable',
            'notify_wali_kelas_on_total_violation_limit' => 'nullable|boolean',
            'notify_kesiswaan_on_total_violation_limit' => 'nullable|boolean',
            'semester_alpha_mode' => 'nullable|string|in:monitor_only,alertable',
            'monthly_late_mode' => 'nullable|string|in:monitor_only,alertable',
            'notify_wali_kelas_on_late_limit' => 'nullable|boolean',
            'notify_kesiswaan_on_late_limit' => 'nullable|boolean',
            'notify_wali_kelas_on_alpha_limit' => 'nullable|boolean',
            'notify_kesiswaan_on_alpha_limit' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $schema = AttendanceSchema::findOrFail($id);
            $userId = Auth::user() ? Auth::user()->id : null;
            $oldValues = $schema->toArray();

            $schema->update([
                'schema_name' => $request->schema_name,
                'schema_type' => $request->schema_type,
                'target_role' => $request->target_role,
                'target_status' => $request->target_status,
                'schema_description' => $request->schema_description,
                'is_active' => $request->is_active ?? $schema->is_active,
                'is_default' => $request->is_default ?? $schema->is_default,
                'is_mandatory' => $request->is_mandatory ?? $schema->is_mandatory,
                'priority' => $request->priority ?? $schema->priority,
                'version' => $schema->version + 1,
                'jam_masuk_default' => $request->jam_masuk_default,
                'jam_pulang_default' => $request->jam_pulang_default,
                'toleransi_default' => $request->toleransi_default ?? $schema->toleransi_default,
                'minimal_open_time_staff' => $request->minimal_open_time_staff ?? $schema->minimal_open_time_staff,
                'minimal_open_time_siswa' => $request->minimal_open_time_siswa ?? $schema->minimal_open_time_siswa,
                'wajib_gps' => $request->wajib_gps ?? $schema->wajib_gps,
                'wajib_foto' => $request->wajib_foto ?? $schema->wajib_foto,
                'face_verification_enabled' => $request->has('face_verification_enabled')
                    ? $request->boolean('face_verification_enabled')
                    : $schema->face_verification_enabled,
                'hari_kerja' => $request->has('hari_kerja')
                    ? $this->normalizeStringArray(
                        $request->input('hari_kerja'),
                        ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']
                    )
                    : $this->normalizeStringArray(
                        $schema->hari_kerja,
                        ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']
                    ),
                'lokasi_gps_ids' => $request->has('lokasi_gps_ids')
                    ? $this->normalizeIntArray($request->input('lokasi_gps_ids'))
                    : $this->normalizeIntArray($schema->lokasi_gps_ids),
                'siswa_jam_masuk' => $request->siswa_jam_masuk,
                'siswa_jam_pulang' => $request->siswa_jam_pulang,
                'siswa_toleransi' => $request->siswa_toleransi ?? $schema->siswa_toleransi,
                'gps_accuracy' => $request->has('gps_accuracy')
                    ? (int) $request->input('gps_accuracy')
                    : $schema->gps_accuracy,
                'verification_mode' => $request->verification_mode ?? $schema->verification_mode ?? 'async_pending',
                // Policy sekolah: absensi aplikasi ini khusus siswa.
                'attendance_scope' => 'siswa_only',
                'target_tingkat_ids' => $request->has('target_tingkat_ids')
                    ? $this->normalizeNullableIntArray($request, 'target_tingkat_ids')
                    : $schema->target_tingkat_ids,
                'target_kelas_ids' => $request->has('target_kelas_ids')
                    ? $this->normalizeNullableIntArray($request, 'target_kelas_ids')
                    : $schema->target_kelas_ids,
                'total_violation_minutes_semester_limit' => $request->has('total_violation_minutes_semester_limit')
                    ? $request->input('total_violation_minutes_semester_limit')
                    : $schema->total_violation_minutes_semester_limit,
                'alpha_days_semester_limit' => $request->has('alpha_days_semester_limit')
                    ? $request->input('alpha_days_semester_limit')
                    : $schema->alpha_days_semester_limit,
                'late_minutes_monthly_limit' => $request->has('late_minutes_monthly_limit')
                    ? $request->input('late_minutes_monthly_limit')
                    : $schema->late_minutes_monthly_limit,
                'discipline_thresholds_enabled' => $request->has('discipline_thresholds_enabled')
                    ? $request->boolean('discipline_thresholds_enabled')
                    : $schema->discipline_thresholds_enabled,
                'semester_total_violation_mode' => $request->has('semester_total_violation_mode')
                    ? $request->input('semester_total_violation_mode')
                    : $schema->semester_total_violation_mode,
                'notify_wali_kelas_on_total_violation_limit' => $request->has('notify_wali_kelas_on_total_violation_limit')
                    ? $request->boolean('notify_wali_kelas_on_total_violation_limit')
                    : $schema->notify_wali_kelas_on_total_violation_limit,
                'notify_kesiswaan_on_total_violation_limit' => $request->has('notify_kesiswaan_on_total_violation_limit')
                    ? $request->boolean('notify_kesiswaan_on_total_violation_limit')
                    : $schema->notify_kesiswaan_on_total_violation_limit,
                'semester_alpha_mode' => $request->has('semester_alpha_mode')
                    ? $request->input('semester_alpha_mode')
                    : $schema->semester_alpha_mode,
                'monthly_late_mode' => $request->has('monthly_late_mode')
                    ? $request->input('monthly_late_mode')
                    : $schema->monthly_late_mode,
                'notify_wali_kelas_on_late_limit' => $request->has('notify_wali_kelas_on_late_limit')
                    ? $request->boolean('notify_wali_kelas_on_late_limit')
                    : $schema->notify_wali_kelas_on_late_limit,
                'notify_kesiswaan_on_late_limit' => $request->has('notify_kesiswaan_on_late_limit')
                    ? $request->boolean('notify_kesiswaan_on_late_limit')
                    : $schema->notify_kesiswaan_on_late_limit,
                'notify_wali_kelas_on_alpha_limit' => $request->has('notify_wali_kelas_on_alpha_limit')
                    ? $request->boolean('notify_wali_kelas_on_alpha_limit')
                    : $schema->notify_wali_kelas_on_alpha_limit,
                'notify_kesiswaan_on_alpha_limit' => $request->has('notify_kesiswaan_on_alpha_limit')
                    ? $request->boolean('notify_kesiswaan_on_alpha_limit')
                    : $schema->notify_kesiswaan_on_alpha_limit,
                'updated_by' => $userId
            ]);

            $this->logSchemaChangeSafe(
                (int) $schema->id,
                'updated',
                $oldValues,
                $schema->fresh()->toArray(),
                $userId,
                'Schema updated via API'
            );
            $this->invalidateAttendanceCaches(clearAllSchemas: true);

            return response()->json([
                'success' => true,
                'data' => $schema,
                'message' => 'Attendance schema updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating attendance schema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update attendance schema'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $schema = AttendanceSchema::findOrFail($id);
            $schema->delete();
            $this->invalidateAttendanceCaches(clearAllSchemas: true);

            return response()->json([
                'success' => true,
                'message' => 'Attendance schema deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting attendance schema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attendance schema'
            ], 500);
        }
    }

    /**
     * Get effective schema for a user
     */
    public function getEffectiveSchema(Request $request, string $userId): JsonResponse
    {
        try {
            $user = \App\Models\User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            if ($user->status_kepegawaian === 'ASN') {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'ASN users are excluded from attendance schema system',
                    'assignment_type' => 'asn_excluded',
                    'note' => 'ASN users use government attendance system'
                ]);
            }

            $context = $this->attendanceSchemaService->getEffectiveSchemaContext($user);
            $schema = $context['schema'] ?? null;

            if ($schema instanceof AttendanceSchema) {
                $schemaData = $schema->toArray();
                $schemaData['face_verification_enabled'] = $schema->isFaceVerificationEnabled();
                $schemaData['assignment_type'] = $context['assignment_type'] ?? 'none';
                $schemaData['assignment_id'] = $context['assignment_id'] ?? null;
                $schemaData['start_date'] = $context['start_date'] ?? null;
                $schemaData['end_date'] = $context['end_date'] ?? null;

                return response()->json([
                    'success' => true,
                    'data' => $schemaData,
                    'message' => 'Effective schema retrieved successfully',
                    'assignment_type' => $context['assignment_type'] ?? 'none',
                    'assignment_id' => $context['assignment_id'] ?? null,
                    'assignment_reason' => $context['assignment_reason'] ?? null,
                ]);
            }

            // No schema available
            return response()->json([
                'success' => true,
                'data' => null,
                'message' => 'No schema available for this user',
                'assignment_type' => 'none'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching effective schema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve effective schema'
            ], 500);
        }
    }

    /**
     * Get effective schemas for multiple users (bulk operation)
     */
    public function getEffectiveSchemasBulk(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userIds = $request->user_ids;
            $results = [];

            // Get users data to check ASN status
            $users = \App\Models\User::whereIn('id', $userIds)
                ->select('id', 'status_kepegawaian')
                ->get()
                ->keyBy('id');

            // Process each user
            foreach ($userIds as $userId) {
                $user = $users->get($userId);

                // Skip ASN users - they don't get schemas
                if ($user && $user->status_kepegawaian === 'ASN') {
                    $results[$userId] = [
                        'user_id' => $userId,
                        'schema' => null,
                        'assignment_type' => 'asn_excluded',
                        'assignment_id' => null,
                        'start_date' => null,
                        'end_date' => null,
                        'note' => 'ASN users are excluded from attendance schema system'
                    ];
                    continue;
                }

                $context = $this->attendanceSchemaService->getEffectiveSchemaContext($user);
                $schema = $context['schema'] ?? null;

                if ($schema instanceof AttendanceSchema) {
                    $results[$userId] = [
                        'user_id' => $userId,
                        'schema' => $schema,
                        'assignment_type' => $context['assignment_type'] ?? 'none',
                        'assignment_id' => $context['assignment_id'] ?? null,
                        'start_date' => $context['start_date'] ?? null,
                        'end_date' => $context['end_date'] ?? null,
                        'assignment_reason' => $context['assignment_reason'] ?? null,
                    ];
                } else {
                    // No schema available
                    $results[$userId] = [
                        'user_id' => $userId,
                        'schema' => null,
                        'assignment_type' => 'none',
                        'assignment_id' => null,
                        'start_date' => null,
                        'end_date' => null
                    ];
                }
            }

            $schemasFound = count(array_filter($results, function ($r) {
                return $r['schema'] !== null;
            }));

            $adminAssignments = count(array_filter($results, function ($r) {
                return in_array($r['assignment_type'], ['manual', 'bulk'], true);
            }));
            $autoAssignments = count(array_filter($results, function ($r) {
                return $r['assignment_type'] === 'auto';
            }));
            $defaultAssignments = count(array_filter($results, function ($r) {
                return $r['assignment_type'] === 'default';
            }));

            $asnExcluded = count(array_filter($results, function ($r) {
                return $r['assignment_type'] === 'asn_excluded';
            }));

            return response()->json([
                'success' => true,
                'data' => $results,
                'message' => 'Effective schemas retrieved successfully (ASN users excluded)',
                'total_users' => count($userIds),
                'schemas_found' => $schemasFound,
                'admin_assignments' => $adminAssignments,
                'auto_assignments' => $autoAssignments,
                'default_assignments' => $defaultAssignments,
                'without_schema' => count($userIds) - $schemasFound - $asnExcluded,
                'asn_excluded' => $asnExcluded
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching bulk effective schemas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve effective schemas'
            ], 500);
        }
    }

    /**
     * Toggle schema active status
     */
    public function toggleActive(Request $request, string $id): JsonResponse
    {
        try {
            $schema = AttendanceSchema::findOrFail($id);
            $userId = Auth::user() ? Auth::user()->id : null;
            $oldValues = $schema->toArray();

            $schema->is_active = !$schema->is_active;
            $schema->updated_by = $userId;
            $schema->save();

            $this->logSchemaChangeSafe(
                (int) $schema->id,
                'toggled_active',
                $oldValues,
                $schema->fresh()->toArray(),
                $userId,
                'Schema active status toggled'
            );
            $this->invalidateAttendanceCaches(clearAllSchemas: true);

            return response()->json([
                'success' => true,
                'data' => $schema,
                'message' => 'Schema status toggled successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error toggling schema status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle schema status'
            ], 500);
        }
    }

    /**
     * Set schema as default
     */
    public function setDefault(Request $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $schema = AttendanceSchema::findOrFail($id);
            $userId = Auth::user() ? Auth::user()->id : null;
            $oldValues = $schema->toArray();

            // Remove default from all schemas of the same type
            AttendanceSchema::where('schema_type', $schema->schema_type)
                ->where('target_role', $schema->target_role)
                ->where('target_status', $schema->target_status)
                ->update(['is_default' => false]);

            // Set this schema as default
            $schema->is_default = true;
            $schema->updated_by = $userId;
            $schema->save();

            $this->logSchemaChangeSafe(
                (int) $schema->id,
                'set_default',
                $oldValues,
                $schema->fresh()->toArray(),
                $userId,
                'Schema set as default'
            );

            DB::commit();
            $this->invalidateAttendanceCaches(clearAllSchemas: true);

            return response()->json([
                'success' => true,
                'data' => $schema,
                'message' => 'Schema set as default successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error setting default schema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to set default schema'
            ], 500);
        }
    }

    /**
     * Get change logs for a schema
     */
    public function getChangeLogs(Request $request, string $id): JsonResponse
    {
        try {
            $changeLogs = AttendanceSchemaChangeLog::where('attendance_setting_id', $id)
                ->orderBy('changed_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $changeLogs,
                'message' => 'Change logs retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching change logs: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve change logs'
            ], 500);
        }
    }

    /**
     * Assign schema to user
     */
    public function assignToUser(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string|max:1000',
            'assignment_type' => 'nullable|string|in:manual,bulk,auto',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if schema exists
            $schema = AttendanceSchema::findOrFail($id);
            $user = \App\Models\User::findOrFail((int) $request->user_id);
            $userId = Auth::user() ? Auth::user()->id : 1; // Default to user ID 1 if no auth

            $assignment = $this->attendanceSchemaService->assignSchemaToUser(
                $user,
                $schema,
                $request->start_date,
                $request->end_date,
                $request->notes,
                $userId,
                $request->input('assignment_type', 'manual')
            );
            $this->invalidateAttendanceCaches(userIds: [(int) $request->user_id]);

            return response()->json([
                'success' => true,
                'data' => $assignment,
                'message' => 'Schema assigned to user successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error assigning schema to user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign schema to user'
            ], 500);
        }
    }

    /**
     * Bulk assign schema
     */
    public function bulkAssign(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string|max:1000',
            'assignment_type' => 'nullable|string|in:manual,bulk,auto',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $schema = AttendanceSchema::findOrFail($id);
            $assignedBy = Auth::user() ? Auth::user()->id : 1; // Default to user ID 1 if no auth
            $assignments = $this->attendanceSchemaService->bulkAssignSchema(
                $request->user_ids,
                $schema,
                $request->start_date,
                $request->end_date,
                $request->notes,
                $assignedBy,
                $request->input('assignment_type', 'bulk')
            );
            $this->invalidateAttendanceCaches(userIds: array_map('intval', $request->user_ids));

            return response()->json([
                'success' => true,
                'data' => $assignments,
                'message' => 'Schema bulk assigned successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error bulk assigning schema: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to bulk assign schema'
            ], 500);
        }
    }

    /**
     * Auto assign schemas
     */
    public function autoAssign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'schema_id' => 'nullable|integer|exists:attendance_settings,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $schema = $request->filled('schema_id')
                ? AttendanceSchema::find((int) $request->schema_id)
                : null;
            $results = $this->attendanceSchemaService->autoAssignSchemas(
                $request->input('user_ids'),
                $schema
            );

            $summary = [
                'total_users' => count($results),
                'assigned_count' => count(array_filter($results, fn ($item) => !empty($item['success']))),
                'failed_count' => count(array_filter($results, fn ($item) => empty($item['success']))),
                'manual_skipped_count' => count(array_filter($results, fn ($item) => ($item['message'] ?? null) === 'User has manual assignment')),
                'default_resolved_count' => count(array_filter($results, fn ($item) => ($item['assignment_type'] ?? null) === 'default')),
                'auto_assigned_count' => count(array_filter($results, fn ($item) => ($item['assignment_type'] ?? null) === 'auto')),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'results' => $results,
                ],
                'message' => 'Schemas auto assigned successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error auto assigning schemas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to auto assign schemas'
            ], 500);
        }
    }

    /**
     * Normalize JSON/text/array payload into flat string array.
     *
     * @param mixed $value
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private function normalizeStringArray($value, array $default = []): array
    {
        $normalized = $this->decodeToArray($value);
        if ($normalized === null) {
            return $default;
        }

        $items = array_map(static fn($item) => trim((string) $item), $normalized);
        $items = array_values(array_filter($items, static fn($item) => $item !== ''));

        return empty($items) ? $default : array_values(array_unique($items));
    }

    /**
     * Normalize JSON/text/array payload into positive unique int array.
     *
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizeIntArray($value): array
    {
        $normalized = $this->decodeToArray($value);
        if ($normalized === null) {
            return [];
        }

        $items = array_map(static fn($item) => (int) $item, $normalized);
        $items = array_values(array_filter($items, static fn($item) => $item > 0));

        return array_values(array_unique($items));
    }

    /**
     * Normalize nullable int array field from request payload.
     */
    private function normalizeNullableIntArray(Request $request, string $field): ?array
    {
        if (!$request->has($field)) {
            return null;
        }

        $normalized = $this->normalizeIntArray($request->input($field));
        return empty($normalized) ? null : $normalized;
    }

    /**
     * Decode nested JSON arrays that may be saved as stringified JSON.
     *
     * @param mixed $value
     * @return array<int, mixed>|null
     */
    private function decodeToArray($value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (is_array($decoded)) {
                    return $decoded;
                }

                if (is_string($decoded)) {
                    return $this->decodeToArray($decoded);
                }
            }
        }

        return null;
    }

    private function logSchemaChangeSafe(
        int $schemaId,
        string $action,
        ?array $oldValues,
        ?array $newValues,
        ?int $changedBy,
        ?string $reason = null
    ): void {
        try {
            AttendanceSchemaChangeLog::logChange(
                $schemaId,
                $action,
                $oldValues,
                $newValues,
                $changedBy,
                $reason
            );

            AttendanceGovernanceLog::record([
                'category' => 'attendance_schema',
                'action' => $action,
                'actor_user_id' => $changedBy ?: auth()->id(),
                'target_type' => 'attendance_schema',
                'target_id' => $schemaId,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'metadata' => [
                    'reason' => $reason,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to persist attendance schema change log', [
                'schema_id' => $schemaId,
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Keep attendance cache in sync after schema mutations.
     *
     * @param array<int, int> $userIds
     */
    private function invalidateAttendanceCaches(array $userIds = [], bool $clearAllSchemas = false): void
    {
        try {
            if ($clearAllSchemas || empty($userIds)) {
                $this->attendanceSchemaService->clearSchemaCache();
            } else {
                foreach (array_values(array_unique($userIds)) as $userId) {
                    $this->attendanceSchemaService->clearUserSchemaCache((int) $userId);
                }
            }

            $this->bumpAttendanceRuntimeVersion();
        } catch (\Throwable $e) {
            Log::warning('Failed to invalidate attendance caches after schema mutation', [
                'user_ids' => $userIds,
                'clear_all' => $clearAllSchemas,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function bumpAttendanceRuntimeVersion(): void
    {
        $key = 'attendance_runtime_version';
        $current = Cache::get($key);
        if ($current === null) {
            Cache::forever($key, 2);
            return;
        }

        Cache::increment($key);
    }
}
