<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchAttendanceWhatsappNotification;
use App\Jobs\ProcessAttendanceFaceVerification;
use App\Exceptions\FaceRecognitionServiceException;
use App\Models\Absensi;
use App\Models\AttendanceFraudAssessment;
use App\Models\AttendanceGovernanceLog;
use App\Models\AttendanceSecurityEvent;
use Illuminate\Http\Request;
use App\Models\AttendanceSchema;
use App\Models\UserAttendanceOverride;
use App\Models\User;
use App\Models\LokasiGps;
use App\Services\AttendanceFaceVerificationService;
use App\Services\AttendanceFraudDetectionService;
use App\Services\AttendanceAutomationStateService;
use App\Services\AttendanceRuntimeConfigService;
use App\Services\AttendanceSchemaService;
use App\Services\AttendanceSnapshotService;
use App\Services\AttendanceTimeService;
use App\Services\FaceRecognitionClient;
use App\Support\RoleNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class SimpleAttendanceController extends Controller
{
    protected $attendanceTimeService;
    protected $attendanceSchemaService;
    protected $attendanceFaceVerificationService;
    protected AttendanceSnapshotService $attendanceSnapshotService;
    protected AttendanceAutomationStateService $attendanceAutomationStateService;
    protected AttendanceRuntimeConfigService $attendanceRuntimeConfigService;
    protected AttendanceFraudDetectionService $attendanceFraudDetectionService;
    protected FaceRecognitionClient $faceRecognitionClient;

    public function __construct(
        AttendanceTimeService $attendanceTimeService,
        AttendanceSchemaService $attendanceSchemaService,
        AttendanceFaceVerificationService $attendanceFaceVerificationService,
        AttendanceFraudDetectionService $attendanceFraudDetectionService,
        AttendanceSnapshotService $attendanceSnapshotService,
        AttendanceAutomationStateService $attendanceAutomationStateService,
        AttendanceRuntimeConfigService $attendanceRuntimeConfigService,
        FaceRecognitionClient $faceRecognitionClient
    ) {
        $this->attendanceTimeService = $attendanceTimeService;
        $this->attendanceSchemaService = $attendanceSchemaService;
        $this->attendanceFaceVerificationService = $attendanceFaceVerificationService;
        $this->attendanceFraudDetectionService = $attendanceFraudDetectionService;
        $this->attendanceSnapshotService = $attendanceSnapshotService;
        $this->attendanceAutomationStateService = $attendanceAutomationStateService;
        $this->attendanceRuntimeConfigService = $attendanceRuntimeConfigService;
        $this->faceRecognitionClient = $faceRecognitionClient;
    }
    public function getGlobalSettings()
    {
        $settings = AttendanceSchema::where('schema_type', 'global')
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();

        if (!$settings) {
            $settings = AttendanceSchema::where('schema_type', 'global')
                ->where('is_default', true)
                ->orderByDesc('updated_at')
                ->first();
        }

        if (!$settings) {
            $settings = AttendanceSchema::where('schema_type', 'global')
                ->orderByDesc('updated_at')
                ->first();
        }

        if (!$settings) {
            $settings = AttendanceSchema::create([
                'schema_name' => 'Default Schema',
                'schema_type' => 'global',
                'jam_masuk_default' => '07:00',
                'jam_pulang_default' => '15:00',
                'toleransi_default' => 15,
                'minimal_open_time_staff' => 70,
                'wajib_gps' => true,
                'wajib_foto' => true,
                'face_verification_enabled' => null,
                'face_template_required' => (bool) config('attendance.face.template_required', true),
                'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
                'siswa_jam_masuk' => '07:00',
                'siswa_jam_pulang' => '14:00',
                'siswa_toleransi' => 10,
                'minimal_open_time_siswa' => 70,
                'violation_minutes_threshold' => 480,
                'violation_percentage_threshold' => 10.00,
                'total_violation_minutes_semester_limit' => 1200,
                'alpha_days_semester_limit' => 8,
                'late_minutes_monthly_limit' => 120,
                'discipline_thresholds_enabled' => true,
                'semester_total_violation_mode' => 'monitor_only',
                'notify_wali_kelas_on_total_violation_limit' => false,
                'notify_kesiswaan_on_total_violation_limit' => false,
                'semester_alpha_mode' => 'alertable',
                'monthly_late_mode' => 'monitor_only',
                'notify_wali_kelas_on_late_limit' => false,
                'notify_kesiswaan_on_late_limit' => false,
                'notify_wali_kelas_on_alpha_limit' => true,
                'notify_kesiswaan_on_alpha_limit' => true,
                'auto_alpha_enabled' => (bool) config('attendance.auto_alpha.enabled', true),
                'auto_alpha_run_time' => (string) config('attendance.auto_alpha.run_time', '23:50'),
                'discipline_alerts_enabled' => (bool) config('attendance.discipline_alerts.enabled', true),
                'discipline_alerts_run_time' => (string) config('attendance.discipline_alerts.run_time', '23:57'),
                'live_tracking_retention_days' => (int) config('attendance.live_tracking.retention_days', 30),
                'live_tracking_cleanup_time' => (string) config('attendance.live_tracking.cleanup_time', '02:15'),
                'live_tracking_min_distance_meters' => (int) config('attendance.live_tracking.min_distance_meters', 20),
                'live_tracking_enabled' => (bool) config('attendance.live_tracking.enabled', true),
                'face_result_when_template_missing' => (string) config('attendance.face.result_when_template_missing', 'verified'),
                'face_reject_to_manual_review' => (bool) config('attendance.face.reject_to_manual_review', true),
                'face_skip_when_photo_missing' => (bool) config('attendance.face.skip_when_photo_missing', true),
                'verification_mode' => 'async_pending',
                'attendance_scope' => 'siswa_only',
                'target_tingkat_ids' => null,
                'target_kelas_ids' => null,
                'is_default' => true,
                'is_active' => true
            ]);
        } elseif (!$settings->is_default) {
            // Promote latest global schema as default instead of creating duplicates.
            $settings->is_default = true;
            $settings->save();
        } elseif ($settings->attendance_scope !== 'siswa_only') {
            // Hard lock scope untuk policy sekolah: absensi aplikasi ini khusus siswa.
            $settings->attendance_scope = 'siswa_only';
            $settings->save();
        }

        $settings->total_violation_minutes_semester_limit = $settings->total_violation_minutes_semester_limit ?? 1200;
        $settings->alpha_days_semester_limit = $settings->alpha_days_semester_limit ?? 8;
        $settings->late_minutes_monthly_limit = $settings->late_minutes_monthly_limit ?? 120;
        $settings->discipline_thresholds_enabled = $settings->discipline_thresholds_enabled ?? true;
        $settings->semester_total_violation_mode = $settings->semester_total_violation_mode ?? 'monitor_only';
        $settings->notify_wali_kelas_on_total_violation_limit = $settings->notify_wali_kelas_on_total_violation_limit ?? false;
        $settings->notify_kesiswaan_on_total_violation_limit = $settings->notify_kesiswaan_on_total_violation_limit ?? false;
        $settings->semester_alpha_mode = $settings->semester_alpha_mode ?? 'alertable';
        $settings->monthly_late_mode = $settings->monthly_late_mode ?? 'monitor_only';
        $settings->notify_wali_kelas_on_late_limit = $settings->notify_wali_kelas_on_late_limit ?? false;
        $settings->notify_kesiswaan_on_late_limit = $settings->notify_kesiswaan_on_late_limit ?? false;
        $settings->notify_wali_kelas_on_alpha_limit = $settings->notify_wali_kelas_on_alpha_limit ?? true;
        $settings->notify_kesiswaan_on_alpha_limit = $settings->notify_kesiswaan_on_alpha_limit ?? true;
        $settings->auto_alpha_enabled = $settings->auto_alpha_enabled ?? (bool) config('attendance.auto_alpha.enabled', true);
        $settings->auto_alpha_run_time = $settings->auto_alpha_run_time ?? (string) config('attendance.auto_alpha.run_time', '23:50');
        $settings->discipline_alerts_enabled = $settings->discipline_alerts_enabled ?? (bool) config('attendance.discipline_alerts.enabled', true);
        $settings->discipline_alerts_run_time = $settings->discipline_alerts_run_time ?? (string) config('attendance.discipline_alerts.run_time', '23:57');
        $settings->live_tracking_retention_days = $settings->live_tracking_retention_days ?? (int) config('attendance.live_tracking.retention_days', 30);
        $settings->live_tracking_cleanup_time = $settings->live_tracking_cleanup_time ?? (string) config('attendance.live_tracking.cleanup_time', '02:15');
        $settings->live_tracking_min_distance_meters = $settings->live_tracking_min_distance_meters ?? (int) config('attendance.live_tracking.min_distance_meters', 20);
        $settings->live_tracking_enabled = $settings->live_tracking_enabled ?? (bool) config('attendance.live_tracking.enabled', true);
        $settings->gps_accuracy = $settings->gps_accuracy ?? 20;
        $settings->face_verification_enabled = $settings->face_verification_enabled ?? (bool) config('attendance.face.enabled', true);
        $settings->face_template_required = $settings->face_template_required ?? (bool) config('attendance.face.template_required', true);
        $settings->face_result_when_template_missing = $settings->face_result_when_template_missing ?? (string) config('attendance.face.result_when_template_missing', 'verified');
        $settings->face_reject_to_manual_review = $settings->face_reject_to_manual_review ?? (bool) config('attendance.face.reject_to_manual_review', true);
        $settings->face_skip_when_photo_missing = $settings->face_skip_when_photo_missing ?? (bool) config('attendance.face.skip_when_photo_missing', true);

        return response()->json([
            'status' => 'success',
            'data' => $settings
        ]);
    }

    public function updateGlobalSettings(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'verification_mode' => 'nullable|in:sync_final,async_pending',
                'attendance_scope' => 'nullable|in:siswa_only',
                'gps_accuracy' => 'nullable|integer|min:1|max:200',
                'target_tingkat_ids' => 'nullable|array',
                'target_tingkat_ids.*' => 'integer|exists:tingkat,id',
                'target_kelas_ids' => 'nullable|array',
                'target_kelas_ids.*' => 'integer|exists:kelas,id',
                'violation_minutes_threshold' => 'nullable|integer|min:0|max:100000',
                'violation_percentage_threshold' => 'nullable|numeric|min:0|max:100',
                'total_violation_minutes_semester_limit' => 'nullable|integer|min:0|max:100000',
                'alpha_days_semester_limit' => 'nullable|integer|min:0|max:365',
                'late_minutes_monthly_limit' => 'nullable|integer|min:0|max:100000',
                'discipline_thresholds_enabled' => 'nullable|boolean',
                'semester_total_violation_mode' => 'nullable|in:monitor_only,alertable',
                'notify_wali_kelas_on_total_violation_limit' => 'nullable|boolean',
                'notify_kesiswaan_on_total_violation_limit' => 'nullable|boolean',
                'semester_alpha_mode' => 'nullable|in:monitor_only,alertable',
                'monthly_late_mode' => 'nullable|in:monitor_only,alertable',
                'notify_wali_kelas_on_late_limit' => 'nullable|boolean',
                'notify_kesiswaan_on_late_limit' => 'nullable|boolean',
                'notify_wali_kelas_on_alpha_limit' => 'nullable|boolean',
                'notify_kesiswaan_on_alpha_limit' => 'nullable|boolean',
                'auto_alpha_enabled' => 'nullable|boolean',
                'auto_alpha_run_time' => 'nullable|date_format:H:i',
                'discipline_alerts_enabled' => 'nullable|boolean',
                'discipline_alerts_run_time' => 'nullable|date_format:H:i',
                'live_tracking_retention_days' => 'nullable|integer|min:1|max:3650',
                'live_tracking_cleanup_time' => 'nullable|date_format:H:i',
                'live_tracking_min_distance_meters' => 'nullable|integer|min:1|max:500',
                'live_tracking_enabled' => 'nullable|boolean',
                'face_verification_enabled' => 'nullable|boolean',
                'face_template_required' => 'nullable|boolean',
                'face_result_when_template_missing' => 'nullable|in:verified,rejected,manual_review',
                'face_reject_to_manual_review' => 'nullable|boolean',
                'face_skip_when_photo_missing' => 'nullable|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi pengaturan gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $settings = AttendanceSchema::where('schema_type', 'global')
                ->where('is_default', true)
                ->orderByDesc('updated_at')
                ->first();

            if (!$settings) {
                $settings = AttendanceSchema::where('schema_type', 'global')
                    ->orderByDesc('updated_at')
                    ->first();
            }

            if (!$settings) {
                $settings = new AttendanceSchema();
                $settings->schema_name = 'Default Schema';
                $settings->schema_type = 'global';
                $settings->is_active = true;
                $settings->verification_mode = 'async_pending';
                $settings->attendance_scope = 'siswa_only';
                $settings->gps_accuracy = 20;
                $settings->total_violation_minutes_semester_limit = 1200;
                $settings->alpha_days_semester_limit = 8;
                $settings->late_minutes_monthly_limit = 120;
                $settings->discipline_thresholds_enabled = true;
                $settings->semester_total_violation_mode = 'monitor_only';
                $settings->notify_wali_kelas_on_total_violation_limit = false;
                $settings->notify_kesiswaan_on_total_violation_limit = false;
                $settings->semester_alpha_mode = 'alertable';
                $settings->monthly_late_mode = 'monitor_only';
                $settings->notify_wali_kelas_on_late_limit = false;
                $settings->notify_kesiswaan_on_late_limit = false;
                $settings->notify_wali_kelas_on_alpha_limit = true;
                $settings->notify_kesiswaan_on_alpha_limit = true;
                $settings->auto_alpha_enabled = (bool) config('attendance.auto_alpha.enabled', true);
                $settings->auto_alpha_run_time = (string) config('attendance.auto_alpha.run_time', '23:50');
                $settings->discipline_alerts_enabled = (bool) config('attendance.discipline_alerts.enabled', true);
                $settings->discipline_alerts_run_time = (string) config('attendance.discipline_alerts.run_time', '23:57');
                $settings->live_tracking_retention_days = (int) config('attendance.live_tracking.retention_days', 30);
                $settings->live_tracking_cleanup_time = (string) config('attendance.live_tracking.cleanup_time', '02:15');
                $settings->live_tracking_min_distance_meters = (int) config('attendance.live_tracking.min_distance_meters', 20);
                $settings->live_tracking_enabled = (bool) config('attendance.live_tracking.enabled', true);
                $settings->face_verification_enabled = (bool) config('attendance.face.enabled', true);
                $settings->face_template_required = (bool) config('attendance.face.template_required', true);
                $settings->face_result_when_template_missing = (string) config('attendance.face.result_when_template_missing', 'verified');
                $settings->face_reject_to_manual_review = (bool) config('attendance.face.reject_to_manual_review', true);
                $settings->face_skip_when_photo_missing = (bool) config('attendance.face.skip_when_photo_missing', true);
            }

            // Keep only one global default row.
            AttendanceSchema::where('schema_type', 'global')
                ->where('id', '!=', $settings->id)
                ->where('is_default', true)
                ->update(['is_default' => false]);
            $settings->is_default = true;

            $oldAuditState = [
                'verification_mode' => $settings->verification_mode ?: 'async_pending',
                'attendance_scope' => $settings->attendance_scope ?: 'siswa_only',
                'gps_accuracy' => (int) ($settings->gps_accuracy ?? 20),
                'target_tingkat_ids' => $this->normalizeJsonIdArray($settings->target_tingkat_ids),
                'target_kelas_ids' => $this->normalizeJsonIdArray($settings->target_kelas_ids),
                'violation_minutes_threshold' => (int) ($settings->violation_minutes_threshold ?? 480),
                'violation_percentage_threshold' => (float) ($settings->violation_percentage_threshold ?? 10.0),
                'total_violation_minutes_semester_limit' => (int) ($settings->total_violation_minutes_semester_limit ?? 1200),
                'alpha_days_semester_limit' => (int) ($settings->alpha_days_semester_limit ?? 8),
                'late_minutes_monthly_limit' => (int) ($settings->late_minutes_monthly_limit ?? 120),
                'discipline_thresholds_enabled' => $settings->discipline_thresholds_enabled !== null
                    ? (bool) $settings->discipline_thresholds_enabled
                    : true,
                'semester_total_violation_mode' => (string) ($settings->semester_total_violation_mode ?? 'monitor_only'),
                'notify_wali_kelas_on_total_violation_limit' => $settings->notify_wali_kelas_on_total_violation_limit !== null
                    ? (bool) $settings->notify_wali_kelas_on_total_violation_limit
                    : false,
                'notify_kesiswaan_on_total_violation_limit' => $settings->notify_kesiswaan_on_total_violation_limit !== null
                    ? (bool) $settings->notify_kesiswaan_on_total_violation_limit
                    : false,
                'semester_alpha_mode' => (string) ($settings->semester_alpha_mode ?? 'alertable'),
                'monthly_late_mode' => (string) ($settings->monthly_late_mode ?? 'monitor_only'),
                'notify_wali_kelas_on_late_limit' => $settings->notify_wali_kelas_on_late_limit !== null
                    ? (bool) $settings->notify_wali_kelas_on_late_limit
                    : false,
                'notify_kesiswaan_on_late_limit' => $settings->notify_kesiswaan_on_late_limit !== null
                    ? (bool) $settings->notify_kesiswaan_on_late_limit
                    : false,
                'notify_wali_kelas_on_alpha_limit' => $settings->notify_wali_kelas_on_alpha_limit !== null
                    ? (bool) $settings->notify_wali_kelas_on_alpha_limit
                    : true,
                'notify_kesiswaan_on_alpha_limit' => $settings->notify_kesiswaan_on_alpha_limit !== null
                    ? (bool) $settings->notify_kesiswaan_on_alpha_limit
                    : true,
                'auto_alpha_enabled' => $settings->auto_alpha_enabled !== null
                    ? (bool) $settings->auto_alpha_enabled
                    : (bool) config('attendance.auto_alpha.enabled', true),
                'auto_alpha_run_time' => (string) ($settings->auto_alpha_run_time ?? config('attendance.auto_alpha.run_time', '23:50')),
                'discipline_alerts_enabled' => $settings->discipline_alerts_enabled !== null
                    ? (bool) $settings->discipline_alerts_enabled
                    : (bool) config('attendance.discipline_alerts.enabled', true),
                'discipline_alerts_run_time' => (string) ($settings->discipline_alerts_run_time ?? config('attendance.discipline_alerts.run_time', '23:57')),
                'live_tracking_retention_days' => (int) ($settings->live_tracking_retention_days ?? config('attendance.live_tracking.retention_days', 30)),
                'live_tracking_cleanup_time' => (string) ($settings->live_tracking_cleanup_time ?? config('attendance.live_tracking.cleanup_time', '02:15')),
                'live_tracking_min_distance_meters' => (int) ($settings->live_tracking_min_distance_meters ?? config('attendance.live_tracking.min_distance_meters', 20)),
                'live_tracking_enabled' => $settings->live_tracking_enabled !== null
                    ? (bool) $settings->live_tracking_enabled
                    : (bool) config('attendance.live_tracking.enabled', true),
                'face_verification_enabled' => $settings->face_verification_enabled !== null
                    ? (bool) $settings->face_verification_enabled
                    : (bool) config('attendance.face.enabled', true),
                'face_template_required' => $settings->face_template_required !== null
                    ? (bool) $settings->face_template_required
                    : (bool) config('attendance.face.template_required', true),
                'face_result_when_template_missing' => (string) ($settings->face_result_when_template_missing ?? config('attendance.face.result_when_template_missing', 'verified')),
                'face_reject_to_manual_review' => $settings->face_reject_to_manual_review !== null
                    ? (bool) $settings->face_reject_to_manual_review
                    : (bool) config('attendance.face.reject_to_manual_review', true),
                'face_skip_when_photo_missing' => $settings->face_skip_when_photo_missing !== null
                    ? (bool) $settings->face_skip_when_photo_missing
                    : (bool) config('attendance.face.skip_when_photo_missing', true),
            ];

            $settings->fill($request->all());
            // Policy sekolah: absensi aplikasi ini khusus siswa.
            $settings->attendance_scope = 'siswa_only';
            $settings->save();

            $newAuditState = [
                'verification_mode' => $settings->verification_mode ?: 'async_pending',
                'attendance_scope' => 'siswa_only',
                'gps_accuracy' => (int) ($settings->gps_accuracy ?? 20),
                'target_tingkat_ids' => $this->normalizeJsonIdArray($settings->target_tingkat_ids),
                'target_kelas_ids' => $this->normalizeJsonIdArray($settings->target_kelas_ids),
                'violation_minutes_threshold' => (int) ($settings->violation_minutes_threshold ?? 480),
                'violation_percentage_threshold' => (float) ($settings->violation_percentage_threshold ?? 10.0),
                'total_violation_minutes_semester_limit' => (int) ($settings->total_violation_minutes_semester_limit ?? 1200),
                'alpha_days_semester_limit' => (int) ($settings->alpha_days_semester_limit ?? 8),
                'late_minutes_monthly_limit' => (int) ($settings->late_minutes_monthly_limit ?? 120),
                'discipline_thresholds_enabled' => $settings->discipline_thresholds_enabled !== null
                    ? (bool) $settings->discipline_thresholds_enabled
                    : true,
                'semester_total_violation_mode' => (string) ($settings->semester_total_violation_mode ?? 'monitor_only'),
                'notify_wali_kelas_on_total_violation_limit' => $settings->notify_wali_kelas_on_total_violation_limit !== null
                    ? (bool) $settings->notify_wali_kelas_on_total_violation_limit
                    : false,
                'notify_kesiswaan_on_total_violation_limit' => $settings->notify_kesiswaan_on_total_violation_limit !== null
                    ? (bool) $settings->notify_kesiswaan_on_total_violation_limit
                    : false,
                'semester_alpha_mode' => (string) ($settings->semester_alpha_mode ?? 'alertable'),
                'monthly_late_mode' => (string) ($settings->monthly_late_mode ?? 'monitor_only'),
                'notify_wali_kelas_on_late_limit' => $settings->notify_wali_kelas_on_late_limit !== null
                    ? (bool) $settings->notify_wali_kelas_on_late_limit
                    : false,
                'notify_kesiswaan_on_late_limit' => $settings->notify_kesiswaan_on_late_limit !== null
                    ? (bool) $settings->notify_kesiswaan_on_late_limit
                    : false,
                'notify_wali_kelas_on_alpha_limit' => $settings->notify_wali_kelas_on_alpha_limit !== null
                    ? (bool) $settings->notify_wali_kelas_on_alpha_limit
                    : true,
                'notify_kesiswaan_on_alpha_limit' => $settings->notify_kesiswaan_on_alpha_limit !== null
                    ? (bool) $settings->notify_kesiswaan_on_alpha_limit
                    : true,
                'auto_alpha_enabled' => $settings->auto_alpha_enabled !== null
                    ? (bool) $settings->auto_alpha_enabled
                    : (bool) config('attendance.auto_alpha.enabled', true),
                'auto_alpha_run_time' => (string) ($settings->auto_alpha_run_time ?? config('attendance.auto_alpha.run_time', '23:50')),
                'discipline_alerts_enabled' => $settings->discipline_alerts_enabled !== null
                    ? (bool) $settings->discipline_alerts_enabled
                    : (bool) config('attendance.discipline_alerts.enabled', true),
                'discipline_alerts_run_time' => (string) ($settings->discipline_alerts_run_time ?? config('attendance.discipline_alerts.run_time', '23:57')),
                'live_tracking_retention_days' => (int) ($settings->live_tracking_retention_days ?? config('attendance.live_tracking.retention_days', 30)),
                'live_tracking_cleanup_time' => (string) ($settings->live_tracking_cleanup_time ?? config('attendance.live_tracking.cleanup_time', '02:15')),
                'live_tracking_min_distance_meters' => (int) ($settings->live_tracking_min_distance_meters ?? config('attendance.live_tracking.min_distance_meters', 20)),
                'live_tracking_enabled' => $settings->live_tracking_enabled !== null
                    ? (bool) $settings->live_tracking_enabled
                    : (bool) config('attendance.live_tracking.enabled', true),
                'face_verification_enabled' => $settings->face_verification_enabled !== null
                    ? (bool) $settings->face_verification_enabled
                    : (bool) config('attendance.face.enabled', true),
                'face_template_required' => $settings->face_template_required !== null
                    ? (bool) $settings->face_template_required
                    : (bool) config('attendance.face.template_required', true),
                'face_result_when_template_missing' => (string) ($settings->face_result_when_template_missing ?? config('attendance.face.result_when_template_missing', 'verified')),
                'face_reject_to_manual_review' => $settings->face_reject_to_manual_review !== null
                    ? (bool) $settings->face_reject_to_manual_review
                    : (bool) config('attendance.face.reject_to_manual_review', true),
                'face_skip_when_photo_missing' => $settings->face_skip_when_photo_missing !== null
                    ? (bool) $settings->face_skip_when_photo_missing
                    : (bool) config('attendance.face.skip_when_photo_missing', true),
            ];

            $changedFields = $this->diffAuditState($oldAuditState, $newAuditState);
            if (!empty($changedFields)) {
                Log::info('Attendance global settings updated', [
                    'actor_user_id' => auth()->id(),
                    'changed_fields' => array_keys($changedFields),
                    'old' => $oldAuditState,
                    'new' => $newAuditState,
                ]);

                AttendanceGovernanceLog::record([
                    'category' => 'attendance_global_settings',
                    'action' => 'updated',
                    'actor_user_id' => auth()->id(),
                    'target_type' => 'attendance_schema',
                    'target_id' => $settings->id,
                    'old_values' => $oldAuditState,
                    'new_values' => $newAuditState,
                    'metadata' => [
                        'changed_fields' => array_keys($changedFields),
                    ],
                ]);
            }

            $this->clearGlobalAttendanceCache();

            return response()->json([
                'status' => 'success',
                'message' => 'Pengaturan berhasil disimpan',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan pengaturan'
            ], 500);
        }
    }

    /**
     * Lightweight health-check for attendance governance panel.
     */
    public function getSystemHealth()
    {
        try {
            $defaultSchema = AttendanceSchema::where('schema_type', 'global')
                ->where('is_default', true)
                ->orderByDesc('updated_at')
                ->first();
            if (!$defaultSchema) {
                $defaultSchema = AttendanceSchema::where('schema_type', 'global')
                    ->where('is_active', true)
                    ->orderByDesc('updated_at')
                    ->first();
            }

            $activeLocationsCount = LokasiGps::where('is_active', true)->count();
            $queueName = (string) config('attendance.face.queue', 'face-verification');
            $queueConnection = (string) config('queue.default', 'sync');
            $verificationMode = $defaultSchema?->verification_mode ?: (string) config('attendance.face.default_mode', 'async_pending');
            $attendanceScope = $defaultSchema?->attendance_scope ?: 'siswa_only';
            $faceThreshold = (float) config('attendance.face.threshold', 0.363);
            $autoAlphaRuntime = $this->attendanceRuntimeConfigService->getAutoAlphaConfig();
            $disciplineAlertsRuntime = $this->attendanceRuntimeConfigService->getDisciplineAlertsConfig();
            $liveTrackingRuntime = $this->attendanceRuntimeConfigService->getLiveTrackingConfig();
            $facePolicyRuntime = $this->attendanceRuntimeConfigService->getFaceVerificationPolicyConfig();
            $faceServiceCheck = $this->buildFaceServiceHealthCheck();
            $autoAlphaHealth = $this->attendanceAutomationStateService->buildDailyHealth(
                'auto_alpha',
                (bool) ($autoAlphaRuntime['enabled'] ?? true),
                (string) ($autoAlphaRuntime['run_time'] ?? '23:50'),
                'Auto alpha'
            );
            $disciplineAlertHealth = $this->attendanceAutomationStateService->buildDailyHealth(
                'discipline_alerts',
                (bool) ($disciplineAlertsRuntime['enabled'] ?? true),
                (string) ($disciplineAlertsRuntime['run_time'] ?? '23:57'),
                'Alert threshold'
            );
            $liveTrackingCleanupHealth = $this->attendanceAutomationStateService->buildDailyHealth(
                'live_tracking_cleanup',
                true,
                (string) ($liveTrackingRuntime['cleanup_time'] ?? '02:15'),
                'Cleanup live tracking'
            );

            $pendingJobs = null;
            $failedJobs = null;
            $queueLagSeconds = null;
            $queueStatus = 'unknown';
            $queueMessage = 'Status antrean belum tersedia.';

            if (Schema::hasTable('jobs')) {
                $jobQuery = DB::table('jobs')->where('queue', $queueName);
                $pendingJobs = (int) $jobQuery->count();
                $oldestCreatedAtEpoch = (int) ($jobQuery->min('created_at') ?? 0);
                if ($oldestCreatedAtEpoch > 0) {
                    $queueLagSeconds = max(0, time() - $oldestCreatedAtEpoch);
                }
            }

            if (Schema::hasTable('failed_jobs')) {
                $failedJobs = (int) DB::table('failed_jobs')
                    ->where('queue', 'like', '%' . $queueName . '%')
                    ->count();
            }

            if ($verificationMode === 'sync_final') {
                $queueStatus = 'not_required';
                $queueMessage = 'Mode sinkron aktif; antrean queue tidak wajib.';
            } elseif ($queueConnection === 'sync') {
                $queueStatus = 'warning';
                $queueMessage = 'Mode async aktif tetapi QUEUE_CONNECTION masih sync.';
            } else {
                $pendingValue = $pendingJobs ?? 0;
                $failedValue = $failedJobs ?? 0;
                $lagValue = $queueLagSeconds ?? 0;

                if ($failedValue > 0 || $pendingValue > 500 || $lagValue > 180) {
                    $queueStatus = 'warning';
                    $queueMessage = 'Queue terdeteksi memiliki backlog/failed job yang perlu ditinjau.';
                } else {
                    $queueStatus = 'healthy';
                    $queueMessage = 'Queue verifikasi wajah terpantau normal.';
                }
            }

            $locationStatus = $activeLocationsCount > 0 ? 'healthy' : 'warning';
            $schemaStatus = $defaultSchema ? 'healthy' : 'warning';
            $overallStatus = (
                $locationStatus === 'warning'
                || $schemaStatus === 'warning'
                || $queueStatus === 'warning'
                || ($faceServiceCheck['status'] ?? 'unknown') === 'warning'
                || ($autoAlphaHealth['status'] ?? 'unknown') === 'warning'
                || ($disciplineAlertHealth['status'] ?? 'unknown') === 'warning'
                || ($liveTrackingCleanupHealth['status'] ?? 'unknown') === 'warning'
            )
                ? 'warning'
                : 'healthy';

            return response()->json([
                'status' => 'success',
                'data' => [
                    'overall_status' => $overallStatus,
                    'checks' => [
                        'active_locations' => [
                            'status' => $locationStatus,
                            'count' => $activeLocationsCount,
                            'message' => $activeLocationsCount > 0
                                ? 'Lokasi GPS aktif tersedia.'
                                : 'Belum ada lokasi GPS aktif.',
                        ],
                        'default_schema' => [
                            'status' => $schemaStatus,
                            'schema_id' => $defaultSchema?->id,
                            'schema_name' => $defaultSchema?->schema_name,
                            'verification_mode' => $verificationMode,
                            'attendance_scope' => $attendanceScope,
                            'message' => $defaultSchema
                                ? 'Skema default absensi tersedia.'
                                : 'Skema default absensi belum tersedia.',
                        ],
                        'face_queue' => [
                            'status' => $queueStatus,
                            'connection' => $queueConnection,
                            'queue_name' => $queueName,
                            'pending_jobs' => $pendingJobs,
                            'failed_jobs' => $failedJobs,
                            'lag_seconds' => $queueLagSeconds,
                            'message' => $queueMessage,
                        ],
                        'face_service' => $faceServiceCheck,
                        'auto_alpha' => $autoAlphaHealth,
                        'discipline_alerts' => $disciplineAlertHealth,
                        'live_tracking_cleanup' => $liveTrackingCleanupHealth,
                    ],
                    'summary' => [
                        'face_threshold' => $faceThreshold,
                        'face_engine_version' => (string) config('attendance.face.engine_version', 'unknown'),
                        'face_enabled' => (bool) ($facePolicyRuntime['enabled'] ?? true),
                        'face_template_required' => (bool) ($facePolicyRuntime['template_required'] ?? true),
                        'face_result_when_template_missing' => (string) ($facePolicyRuntime['result_when_template_missing'] ?? 'verified'),
                        'face_reject_to_manual_review' => (bool) ($facePolicyRuntime['reject_to_manual_review'] ?? true),
                        'face_skip_when_photo_missing' => (bool) ($facePolicyRuntime['skip_when_photo_missing'] ?? true),
                        'face_service_url' => (string) config('attendance.face.service_url', 'http://127.0.0.1:9001'),
                        'face_queue_connection' => $queueConnection,
                        'violation_minutes_threshold' => (int) ($defaultSchema?->violation_minutes_threshold ?? 480),
                        'violation_percentage_threshold' => (float) ($defaultSchema?->violation_percentage_threshold ?? 10.0),
                        'total_violation_minutes_semester_limit' => (int) ($defaultSchema?->total_violation_minutes_semester_limit ?? 1200),
                        'alpha_days_semester_limit' => (int) ($defaultSchema?->alpha_days_semester_limit ?? 8),
                        'late_minutes_monthly_limit' => (int) ($defaultSchema?->late_minutes_monthly_limit ?? 120),
                        'discipline_thresholds_enabled' => $defaultSchema?->discipline_thresholds_enabled !== null
                            ? (bool) $defaultSchema->discipline_thresholds_enabled
                            : true,
                        'semester_total_violation_mode' => (string) ($defaultSchema?->semester_total_violation_mode ?? 'monitor_only'),
                        'semester_alpha_mode' => (string) ($defaultSchema?->semester_alpha_mode ?? 'alertable'),
                        'monthly_late_mode' => (string) ($defaultSchema?->monthly_late_mode ?? 'monitor_only'),
                        'notify_wali_kelas_on_total_violation_limit' => $defaultSchema?->notify_wali_kelas_on_total_violation_limit !== null
                            ? (bool) $defaultSchema->notify_wali_kelas_on_total_violation_limit
                            : false,
                        'notify_kesiswaan_on_total_violation_limit' => $defaultSchema?->notify_kesiswaan_on_total_violation_limit !== null
                            ? (bool) $defaultSchema->notify_kesiswaan_on_total_violation_limit
                            : false,
                        'notify_wali_kelas_on_alpha_limit' => $defaultSchema?->notify_wali_kelas_on_alpha_limit !== null
                            ? (bool) $defaultSchema->notify_wali_kelas_on_alpha_limit
                            : true,
                        'notify_kesiswaan_on_alpha_limit' => $defaultSchema?->notify_kesiswaan_on_alpha_limit !== null
                            ? (bool) $defaultSchema->notify_kesiswaan_on_alpha_limit
                            : true,
                        'notify_wali_kelas_on_late_limit' => $defaultSchema?->notify_wali_kelas_on_late_limit !== null
                            ? (bool) $defaultSchema->notify_wali_kelas_on_late_limit
                            : false,
                        'notify_kesiswaan_on_late_limit' => $defaultSchema?->notify_kesiswaan_on_late_limit !== null
                            ? (bool) $defaultSchema->notify_kesiswaan_on_late_limit
                            : false,
                        'auto_alpha_enabled' => (bool) ($autoAlphaRuntime['enabled'] ?? true),
                        'auto_alpha_run_time' => (string) ($autoAlphaRuntime['run_time'] ?? '23:50'),
                        'discipline_alerts_enabled' => (bool) ($disciplineAlertsRuntime['enabled'] ?? true),
                        'discipline_alerts_run_time' => (string) ($disciplineAlertsRuntime['run_time'] ?? '23:57'),
                        'live_tracking_enabled' => (bool) ($liveTrackingRuntime['enabled'] ?? true),
                        'live_tracking_retention_days' => (int) ($liveTrackingRuntime['retention_days'] ?? 30),
                        'live_tracking_cleanup_time' => (string) ($liveTrackingRuntime['cleanup_time'] ?? '02:15'),
                        'live_tracking_min_distance_meters' => (int) ($liveTrackingRuntime['min_distance_meters'] ?? 20),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load attendance system health', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat health-check absensi',
            ], 500);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildFaceServiceHealthCheck(): array
    {
        $cacheKey = 'attendance_face_service_health';

        return Cache::remember($cacheKey, now()->addSeconds(15), function () {
            try {
                $payload = $this->faceRecognitionClient->health();

                return [
                    'status' => 'healthy',
                    'engine' => (string) ($payload['engine'] ?? config('attendance.face.engine_version', 'unknown')),
                    'template_version' => (string) ($payload['template_version'] ?? config('attendance.face.engine_version', 'unknown')),
                    'message' => 'Face service terhubung dan merespons normal.',
                    'url' => (string) config('attendance.face.service_url', 'http://127.0.0.1:9001'),
                ];
            } catch (FaceRecognitionServiceException $exception) {
                return [
                    'status' => 'warning',
                    'engine' => (string) config('attendance.face.engine_version', 'unknown'),
                    'template_version' => null,
                    'message' => $exception->getMessage(),
                    'url' => (string) config('attendance.face.service_url', 'http://127.0.0.1:9001'),
                ];
            }
        });
    }

    public function getUserSettings($userId = null)
    {
        $authUser = auth()->user();
        $targetUserId = $userId !== null ? (int) $userId : (int) $authUser->id;

        if ($targetUserId !== (int) $authUser->id && !$this->canManageAttendanceSettings($authUser)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak memiliki akses untuk melihat pengaturan user lain'
            ], 403);
        }

        $settings = UserAttendanceOverride::where('user_id', $targetUserId)
            ->where('is_active', true)
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => $settings,
            'user_id' => $targetUserId,
        ]);
    }

    public function updateUserSettings(Request $request, $userId)
    {
        try {
            $settings = UserAttendanceOverride::where('user_id', $userId)->first();

            if (!$settings) {
                $settings = new UserAttendanceOverride();
                $settings->user_id = $userId;
                $settings->created_by = auth()->check() ? auth()->id() : 1;
            }

            $settings->fill($request->all());
            $settings->save();

            // Clear user-specific cache
            \Illuminate\Support\Facades\Cache::forget("working_hours_user_{$userId}");

            return response()->json([
                'status' => 'success',
                'message' => 'Pengaturan user berhasil disimpan',
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan pengaturan user'
            ], 500);
        }
    }

    public function deleteUserSettings($userId)
    {
        UserAttendanceOverride::where('user_id', $userId)->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Pengaturan user berhasil dihapus'
        ]);
    }

    /**
     * Get all users with their settings (PEGAWAI ONLY - exclude students)
     */
    public function getAllUsersSettings()
    {
        try {
            // Get PEGAWAI only (exclude ASN status and Siswa role)
            $users = User::with(['userAttendanceOverride', 'roles'])
                ->where('status_kepegawaian', '!=', 'ASN')
                ->whereDoesntHave('roles', function ($roleQuery) {
                    $roleQuery->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
                })
                ->get()
                ->map(function ($user) {
                    $globalSettings = AttendanceSchema::where('schema_type', 'global')
                        ->where('is_default', true)
                        ->orderByDesc('updated_at')
                        ->first();
                    $override = $user->userAttendanceOverride;

                    // Determine status for display
                    $displayStatus = $user->status_kepegawaian ?: 'Staff';

                    $settings = [
                        'jam_masuk' => $override ? $override->jam_masuk : ($globalSettings ? $globalSettings->jam_masuk_default : '07:00'),
                        'jam_pulang' => $override ? $override->jam_pulang : ($globalSettings ? $globalSettings->jam_pulang_default : '15:00'),
                        'toleransi' => $override ? $override->toleransi : ($globalSettings ? $globalSettings->toleransi_default : 15),
                        'wajib_gps' => $override ? $override->wajib_gps : ($globalSettings ? $globalSettings->wajib_gps : true),
                        'wajib_foto' => $override ? $override->wajib_foto : ($globalSettings ? $globalSettings->wajib_foto : true),
                        'alasan' => $override ? $override->keterangan : null
                    ];

                    return [
                        'user_id' => $user->id,
                        'nama_lengkap' => $user->nama_lengkap,
                        'status_kepegawaian' => $displayStatus,
                        'settings' => $settings
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $users
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get users settings'
            ], 500);
        }
    }

    /**
     * Get attendance summary statistics
     */
    public function getAttendanceSummary()
    {
        try {
            $summary = [
                'asn_users' => User::where('status_kepegawaian', 'ASN')->count(),
                'non_asn_users' => User::where(function ($query) {
                    $query->where('status_kepegawaian', '!=', 'ASN')
                        ->orWhereNull('status_kepegawaian');
                })->count(),
                'security_staff' => User::where('status_kepegawaian', 'Keamanan')->count(),
                'with_overrides' => UserAttendanceOverride::where('is_active', true)->count(),
                'total_users' => User::count(),
                'undefined_status' => User::whereNull('status_kepegawaian')->count()
            ];

            return response()->json([
                'status' => 'success',
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get attendance summary'
            ], 500);
        }
    }

    /**
     * Get shift schedule for security staff
     */
    public function getShiftSchedule()
    {
        try {
            // For now, return empty array as shift system is not fully implemented
            return response()->json([
                'status' => 'success',
                'data' => []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get shift schedule'
            ], 500);
        }
    }

    /**
     * Create shift schedule for security staff
     */
    public function createShiftSchedule(Request $request)
    {
        try {
            // For now, return success as shift system is not fully implemented
            return response()->json([
                'status' => 'success',
                'message' => 'Shift schedule created successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create shift schedule'
            ], 500);
        }
    }

    /**
     * Set user override settings
     */
    public function setUserOverride(Request $request, $userId)
    {
        try {
            $override = UserAttendanceOverride::updateOrCreate(
                ['user_id' => $userId],
                [
                    'jam_masuk' => $request->jam_masuk,
                    'jam_pulang' => $request->jam_pulang,
                    'toleransi' => $request->toleransi,
                    'keterangan' => $request->keterangan,
                    'is_active' => true,
                    'created_by' => auth()->user() ? auth()->user()->id : 1
                ]
            );
            $this->clearAttendanceCacheForUser((int) $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'User override settings saved successfully',
                'data' => $override
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to set user override'
            ], 500);
        }
    }

    /**
     * Remove user override settings
     */
    public function removeUserOverride($userId)
    {
        try {
            UserAttendanceOverride::where('user_id', $userId)->delete();
            $this->clearAttendanceCacheForUser((int) $userId);

            return response()->json([
                'status' => 'success',
                'message' => 'User override settings removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove user override'
            ], 500);
        }
    }

    private function clearAttendanceCacheForUser(int $userId): void
    {
        \Illuminate\Support\Facades\Cache::forget("working_hours_user_{$userId}");
        $this->clearGlobalAttendanceCache();
    }

    private function clearGlobalAttendanceCache(): void
    {
        $this->bumpAttendanceRuntimeVersion();
    }

    private function bumpAttendanceRuntimeVersion(): void
    {
        $key = 'attendance_runtime_version';
        $current = \Illuminate\Support\Facades\Cache::get($key);
        if ($current === null) {
            \Illuminate\Support\Facades\Cache::forever($key, 2);
            return;
        }

        \Illuminate\Support\Facades\Cache::increment($key);
    }

    /**
     * Get user working hours using AttendanceTimeService
     */
    public function getUserWorkingHours(Request $request)
    {
        try {
            $user = $request->user();
            $workingHours = $this->attendanceTimeService->getWorkingHours($user);

            if (!$this->isStudentAttendanceUser($user)) {
                return response()->json([
                    'status' => 'success',
                    'data' => array_merge(
                        $workingHours,
                        $this->buildNonStudentAttendanceMetadata($user)
                    ),
                ]);
            }

            return response()->json([
                'status' => 'success',
                'data' => $workingHours
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get working hours'
            ], 500);
        }
    }

    /**
     * Validate attendance time before submission
     */
    public function validateAttendanceTime(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'nullable|in:masuk,pulang',
                'jenis_absensi' => 'nullable|in:masuk,pulang',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $type = $request->input('type') ?: $request->input('jenis_absensi');
            if (!$type) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'errors' => [
                        'type' => ['Field type atau jenis_absensi wajib diisi (masuk/pulang).'],
                    ],
                ], 422);
            }

            $user = $request->user();
            $serverNow = Carbon::now();
            $time = $serverNow;

            if (!$this->isStudentAttendanceUser($user)) {
                return response()->json([
                    'status' => 'success',
                    'data' => [
                        'valid' => false,
                        'message' => 'Absensi hanya tersedia untuk akun siswa melalui mobile app SIAPS.',
                        'status' => 'ditolak',
                        'code' => 'ATTENDANCE_FORBIDDEN',
                        'working_hours' => array_merge(
                            $this->attendanceTimeService->getWorkingHours($user),
                            $this->buildNonStudentAttendanceMetadata($user)
                        ),
                        'window' => null,
                    ],
                    'meta' => [
                        'resolved_type' => $type,
                        'server_now' => $serverNow->toISOString(),
                        'server_epoch_ms' => $serverNow->valueOf(),
                        'server_date' => $serverNow->toDateString(),
                        'timezone' => config('app.timezone'),
                        'client_time_ignored' => $request->filled('waktu'),
                    ],
                ]);
            }

            $validation = $this->attendanceTimeService->validateAttendance($user, $type, $time);

            if ($validation['valid']) {
                $workingHours = $this->attendanceTimeService->getWorkingHours($user);
                $templateRequirement = $this->evaluateFaceTemplateRequirement($user, $workingHours);
                if ($templateRequirement !== null) {
                    $validation = array_merge($validation, [
                        'valid' => false,
                        'status' => 'ditolak',
                        'message' => $templateRequirement['message'],
                        'code' => $templateRequirement['code'],
                        'working_hours' => $workingHours,
                        'face_template_requirement' => $templateRequirement['data'],
                    ]);
                }
            }

            return response()->json([
                'status' => 'success',
                'data' => $validation,
                'meta' => [
                    'resolved_type' => $type,
                    'server_now' => $serverNow->toISOString(),
                    'server_epoch_ms' => $serverNow->valueOf(),
                    'server_date' => $serverNow->toDateString(),
                    'timezone' => config('app.timezone'),
                    'client_time_ignored' => $request->filled('waktu'),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to validate attendance time'
            ], 500);
        }
    }

    /**
     * Submit attendance with strict validation
     */
    public function submitAttendance(Request $request)
    {
        try {
            $user = $request->user();
            $type = $request->input('jenis_absensi'); // 'masuk' or 'pulang'
            $time = Carbon::now();
            $today = $time->format('Y-m-d');
            $effectiveSchema = $this->attendanceSchemaService->getEffectiveSchema($user);
            $allowSubmitWithSecurityWarnings = $this->allowSubmitWithSecurityWarnings();

            $requestValidator = Validator::make($request->all(), [
                'jenis_absensi' => 'required|in:masuk,pulang',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'accuracy' => 'nullable|numeric|min:0',
                'is_mocked' => 'nullable|boolean',
                'foto' => 'nullable|string',
                'foto_file' => 'nullable|file|image|max:5120',
                'lokasi_id' => 'nullable|integer|exists:lokasi_gps,id',
                'kelas_id' => 'nullable|integer',
                'keterangan' => 'nullable|string',
                'device_id' => 'nullable|string|max:191',
                'device_info' => 'nullable',
                'request_nonce' => 'nullable|string|max:191',
                'request_signature' => 'nullable|string|max:255',
                'request_timestamp' => 'nullable|date',
                'anti_fraud_payload' => 'nullable',
                'security_warning_payload' => 'nullable',
            ]);

            if ($requestValidator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Data tidak valid',
                    'code' => 'VALIDATION_ERROR',
                    'errors' => $requestValidator->errors(),
                ], 422);
            }

            if (!$this->canPerformAttendance($user, $effectiveSchema)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anda tidak memiliki akses untuk melakukan absensi',
                    'code' => 'ATTENDANCE_FORBIDDEN',
                ], 403);
            }

            $securityWarningPayload = $this->normalizeSecurityWarningPayload(
                $request->input('security_warning_payload')
            );
            $submitSecurityIssues = $this->extractSecurityIssuesFromPayload($securityWarningPayload);
            $mobileOnlyViolation = $this->ensureMobileAppAttendanceRequest($request);
            if ($mobileOnlyViolation !== null) {
                $submitSecurityIssues[] = $mobileOnlyViolation;
            }

            $deviceGuard = $this->validateDeviceBindingForAttendance(
                $user,
                $request->input('device_id')
            );
            if (!($deviceGuard['valid'] ?? false)) {
                return response()->json([
                    'status' => 'error',
                    'message' => $deviceGuard['message'] ?? 'Device tidak valid untuk absensi',
                    'code' => $deviceGuard['code'] ?? 'DEVICE_LOCK_VIOLATION',
                    'data' => $deviceGuard['data'] ?? null,
                ], (int) ($deviceGuard['http_status'] ?? 403));
            }
            if (is_array($deviceGuard['warning_issue'] ?? null)) {
                $submitSecurityIssues[] = $deviceGuard['warning_issue'];
            }

            $isMocked = $request->boolean('is_mocked');
            if ($isMocked) {
                $shouldBlock = (bool) config('attendance.gps.block_mocked', true)
                    && !$allowSubmitWithSecurityWarnings;
                Log::warning('Attendance submit detected mock location', [
                    'user_id' => $user->id,
                    'device_id' => $request->input('device_id'),
                    'latitude' => $request->input('latitude'),
                    'longitude' => $request->input('longitude'),
                    'ip_address' => $request->ip(),
                    'blocked' => $shouldBlock,
                ]);
                $mockIssue = $this->buildMockLocationSecurityIssue($request, $shouldBlock);

                if ($shouldBlock) {
                    $this->recordSecurityIssuesForStage($user, [$mockIssue], 'attendance_submit', $type, [
                        'status' => 'blocked',
                        'trigger' => 'submit_validation',
                    ]);
                    $fraudAssessment = $this->recordFraudAssessment($user, $request, [
                        'source' => 'attendance_submit',
                        'attempt_type' => $type,
                        'metadata' => [
                            'submit_notice' => $this->buildSecurityNoticeBox(
                                'attendance_submit',
                                [$mockIssue],
                                $type,
                                ['kind' => 'blocking']
                            ),
                        ],
                    ]);

                    return response()->json([
                        'status' => 'error',
                        'message' => 'Mock location terdeteksi. Nonaktifkan Fake GPS lalu coba lagi.',
                        'code' => 'MOCK_LOCATION_DETECTED',
                        'data' => [
                            'fraud_assessment' => $this->fraudAssessmentResponsePayload($fraudAssessment),
                        ],
                    ], 400);
                }

                $submitSecurityIssues[] = $mockIssue;
            }
            $submitSecurityIssues = $this->normalizeSecurityIssues($submitSecurityIssues);

            // Validate attendance attempt
            $validation = $this->attendanceTimeService->validateAttendance($user, $type, $time);

            if (!$validation['valid']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $validation['message'],
                    'code' => $validation['code'],
                    'data' => [
                        'working_hours' => $validation['working_hours'] ?? null,
                        'window' => $validation['window'] ?? null
                    ]
                ], 400);
            }

            // Additional validations for GPS and photo if required
            $workingHours = $this->attendanceTimeService->getWorkingHours($user);
            $templateRequirement = $this->evaluateFaceTemplateRequirement($user, $workingHours);
            if ($templateRequirement !== null) {
                return response()->json([
                    'status' => 'error',
                    'message' => $templateRequirement['message'],
                    'code' => $templateRequirement['code'],
                    'data' => $templateRequirement['data'],
                ], 400);
            }

            $requestedLocationId = $request->filled('lokasi_id')
                ? (int) $request->input('lokasi_id')
                : null;
            $resolvedLocationId = $requestedLocationId;
            $locationValidation = null;

            if ($workingHours['wajib_gps']) {
                if (!$request->has('latitude') || !$request->has('longitude')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'GPS location is required for attendance',
                        'code' => 'GPS_REQUIRED'
                    ], 400);
                }

                $locationValidation = $this->validateLocationWithSchema(
                    (float) $request->input('latitude'),
                    (float) $request->input('longitude'),
                    $user,
                    $request->input('accuracy')
                );

                if (!$locationValidation['valid']) {
                    $locationSecurityEvent = $this->buildSecurityEventFromLocationValidation($locationValidation, $type);
                    if ($locationSecurityEvent !== null) {
                        $locationSecurityEvent['status'] = 'flagged';
                        $this->recordAttendanceSecurityEvent($user, $locationSecurityEvent);
                    }
                    $locationSecurityIssue = $this->buildSecurityIssueFromLocationValidation($locationValidation);
                    if ($locationSecurityIssue !== null) {
                        $submitSecurityIssues[] = $locationSecurityIssue;
                    }
                }

                $validatedLocationId = $this->resolveLocationIdFromValidation($locationValidation);
                if ($validatedLocationId !== null) {
                    $resolvedLocationId = $validatedLocationId;
                }
            }

            if ($workingHours['wajib_foto']) {
                $hasBase64Photo = $request->filled('foto');
                $hasUploadedPhoto = $request->hasFile('foto_file');
                if (!$hasBase64Photo && !$hasUploadedPhoto) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Photo is required for attendance',
                        'code' => 'PHOTO_REQUIRED'
                    ], 400);
                }
            }

            $snapshotContext = $this->attendanceSnapshotService->captureForUser($user, [
                'schema' => $effectiveSchema,
                'working_hours' => $workingHours,
                'attendance_window' => $validation['window'] ?? null,
            ]);
            $attendanceSettingId = $snapshotContext['attendance_setting_id'];
            $settingsSnapshot = $this->normalizeJsonColumnPayload($snapshotContext['settings_snapshot'] ?? null);
            $deviceInfoPayload = $this->normalizeDeviceInfoPayload(
                $request->input('device_info'),
                $request->input('device_id')
            );
            if ($request->has('is_mocked')) {
                $deviceInfoPayload = $this->appendDeviceInfoFlag($deviceInfoPayload, [
                    'gps_mocked' => $isMocked,
                ]);
            }

            if ($submitSecurityIssues !== []) {
                $this->recordSecurityIssuesForStage($user, $submitSecurityIssues, 'attendance_submit', $type, [
                    'trigger' => 'submit_confirmation',
                    'warning_payload' => $securityWarningPayload,
                ]);
            }

            $submitNotice = $this->buildSecurityNoticeBox(
                'attendance_submit',
                $submitSecurityIssues,
                $type,
                [
                    'acknowledged' => (bool) ($securityWarningPayload['confirmed'] ?? false),
                    'confirmed_at' => $securityWarningPayload['confirmed_at'] ?? null,
                    'warning_hash' => $securityWarningPayload['warning_hash'] ?? null,
                ]
            );

            $fraudAssessment = $this->recordFraudAssessment($user, $request, [
                'source' => 'attendance_submit',
                'attempt_type' => $type,
                'gps_accuracy_limit' => $workingHours['gps_accuracy'] ?? null,
                'location_validation' => $locationValidation,
                'mobile_only_violation' => $mobileOnlyViolation !== null,
                'force_non_blocking' => $allowSubmitWithSecurityWarnings,
                'metadata' => array_filter([
                    'submit_notice' => $submitNotice,
                    'security_warning_context' => $submitSecurityIssues !== []
                        ? [
                            'stage' => 'attendance_submit',
                            'issues' => $submitSecurityIssues,
                            'warning_hash' => $securityWarningPayload['warning_hash'] ?? null,
                            'confirmed_at' => $securityWarningPayload['confirmed_at'] ?? null,
                        ]
                        : null,
                ]),
            ]);

            // Get or create attendance record for today
            $attendance = DB::table('absensi')
                ->where('user_id', $user->id)
                ->where('tanggal', $today)
                ->first();
            $attendanceModel = null;
            $verification = null;
            $verificationMode = $this->attendanceFaceVerificationService->determineMode($effectiveSchema);
            $attendanceCreatedDuringSubmit = false;
            $previousAttendanceSnapshot = $attendance ? (array) $attendance : null;

            if ($type === 'masuk') {
                $existingVerificationStatus = strtolower(trim((string) ($attendance->verification_status ?? '')));
                $allowRetryFailedSyncFinal = $verificationMode === 'sync_final'
                    && in_array($existingVerificationStatus, ['rejected', 'manual_review'], true);

                if ($attendance && !empty($attendance->jam_masuk) && !$allowRetryFailedSyncFinal) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Anda sudah melakukan absen masuk hari ini',
                        'code' => 'ALREADY_CHECKED_IN',
                    ], 400);
                }

                // Handle check-in
                $attendanceData = [
                    'user_id' => $user->id,
                    'tanggal' => $today,
                    'jam_masuk' => $time->format('H:i:s'),
                    'status' => $validation['status'] === 'tepat_waktu' ? 'hadir' : 'terlambat',
                    'metode_absensi' => 'selfie',
                    'keterangan' => $request->input('keterangan'),
                    'verification_status' => 'pending',
                    'is_verified' => false,
                    'updated_at' => $time
                ];

                if ($attendanceSettingId !== null) {
                    $attendanceData['attendance_setting_id'] = $attendanceSettingId;
                }
                if ($settingsSnapshot !== null) {
                    $attendanceData['settings_snapshot'] = $settingsSnapshot;
                }
                if ($deviceInfoPayload !== null) {
                    $attendanceData['device_info'] = $deviceInfoPayload;
                }

                // Add GPS and photo data for check-in
                if ($request->has('latitude')) {
                    $attendanceData['latitude_masuk'] = $request->input('latitude');
                }
                if ($request->has('longitude')) {
                    $attendanceData['longitude_masuk'] = $request->input('longitude');
                }
                if ($request->has('accuracy')) {
                    $attendanceData['gps_accuracy_masuk'] = $request->input('accuracy');
                }
                $savedPhotoPath = $this->resolveAttendancePhotoPath($request, 'checkin', (int) $user->id);
                if ($savedPhotoPath) {
                    $attendanceData['foto_masuk'] = $savedPhotoPath;
                }
                if ($resolvedLocationId !== null) {
                    $attendanceData['lokasi_masuk_id'] = $resolvedLocationId;
                }

                // Add kelas_id if present
                if ($request->has('kelas_id')) {
                    $attendanceData['kelas_id'] = $request->input('kelas_id');
                }

                if ($attendance) {
                    // Update existing record
                    DB::table('absensi')
                        ->where('id', $attendance->id)
                        ->update($attendanceData);
                    $attendanceId = (int) $attendance->id;
                } else {
                    // Create new record
                    $attendanceData['created_at'] = $time;
                    $attendanceId = (int) DB::table('absensi')->insertGetId($attendanceData);
                    $attendanceCreatedDuringSubmit = true;
                }

                $attendanceModel = Absensi::find($attendanceId);
                if ($attendanceModel) {
                    $verification = $this->dispatchOrProcessFaceVerification(
                        $attendanceModel,
                        $user,
                        $effectiveSchema,
                        'checkin',
                        (bool) ($workingHours['face_verification_enabled'] ?? true),
                        $attendanceData['foto_masuk'] ?? $attendanceModel->foto_masuk
                    );
                }
            } elseif ($type === 'pulang') {
                // Handle check-out
                if (!$attendance) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Anda belum absen masuk hari ini',
                        'code' => 'NO_CHECK_IN'
                    ], 400);
                }

                if (!empty($attendance->jam_pulang)) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Anda sudah melakukan absen pulang hari ini',
                        'code' => 'ALREADY_CHECKED_OUT',
                    ], 400);
                }

                $attendanceData = [
                    'jam_pulang' => $time->format('H:i:s'),
                    'updated_at' => $time
                ];

                if ($attendanceSettingId !== null && empty($attendance->attendance_setting_id)) {
                    $attendanceData['attendance_setting_id'] = $attendanceSettingId;
                }
                if ($settingsSnapshot !== null && empty($attendance->settings_snapshot)) {
                    $attendanceData['settings_snapshot'] = $settingsSnapshot;
                }
                if ($deviceInfoPayload !== null) {
                    $attendanceData['device_info'] = $deviceInfoPayload;
                }

                // Add GPS and photo data for check-out
                if ($request->has('latitude')) {
                    $attendanceData['latitude_pulang'] = $request->input('latitude');
                }
                if ($request->has('longitude')) {
                    $attendanceData['longitude_pulang'] = $request->input('longitude');
                }
                if ($request->has('accuracy')) {
                    $attendanceData['gps_accuracy_pulang'] = $request->input('accuracy');
                }
                $savedPhotoPath = $this->resolveAttendancePhotoPath($request, 'checkout', (int) $user->id);
                if ($savedPhotoPath) {
                    $attendanceData['foto_pulang'] = $savedPhotoPath;
                }
                if ($resolvedLocationId !== null) {
                    $attendanceData['lokasi_pulang_id'] = $resolvedLocationId;
                }

                DB::table('absensi')
                    ->where('id', $attendance->id)
                    ->update($attendanceData);

                $attendanceModel = Absensi::find((int) $attendance->id);
                if ($attendanceModel) {
                    $verification = $this->dispatchOrProcessFaceVerification(
                        $attendanceModel,
                        $user,
                        $effectiveSchema,
                        'checkout',
                        (bool) ($workingHours['face_verification_enabled'] ?? true),
                        $attendanceData['foto_pulang'] ?? $attendanceModel->foto_pulang
                    );
                }
            }

            if ($attendanceModel && $this->isSyncFinalVerificationFailure($verification)) {
                $this->rollbackAttendanceMutationOnSyncFinalFailure(
                    $attendanceModel,
                    $type,
                    $attendanceCreatedDuringSubmit,
                    $previousAttendanceSnapshot
                );

                return response()->json([
                    'status' => 'error',
                    'message' => $this->buildSyncFinalVerificationFailureMessage($verification),
                    'code' => 'FACE_VERIFICATION_FAILED',
                    'data' => [
                        'verification' => $verification,
                    ],
                ], 422);
            }

            if ($fraudAssessment instanceof AttendanceFraudAssessment && $attendanceModel instanceof Absensi) {
                $this->attendanceFraudDetectionService->attachAssessmentToAttendance($fraudAssessment, $attendanceModel);
                $attendanceModel->refresh();
            }

            // Log attendance for audit
            Log::info('Attendance submitted', [
                'user_id' => $user->id,
                'type' => $type,
                'status' => $validation['status'],
                'time' => $time->format('Y-m-d H:i:s'),
                'working_hours' => $workingHours
            ]);

            $this->queueAttendanceWhatsappNotification($attendanceModel, $type);
            $fraudAssessmentPayload = $this->fraudAssessmentResponsePayload($fraudAssessment);
            $validationStatus = $attendanceModel?->validation_status ?? 'valid';
            $warningSummary = $attendanceModel?->fraud_decision_reason;

            return response()->json([
                'status' => 'success',
                'message' => $this->getSuccessMessage($type, $validation['status']),
                'data' => [
                    'attendance_status' => $validation['status'],
                    'working_hours' => $workingHours,
                    'window' => $validation['window'],
                    'timestamp' => $time->format('Y-m-d H:i:s'),
                    'verification' => $verification,
                    'validation_status' => $validationStatus,
                    'has_warning' => $validationStatus !== 'valid',
                    'warning_summary' => $warningSummary,
                    'risk_level' => $attendanceModel?->risk_level ?? 'low',
                    'risk_score' => (int) ($attendanceModel?->risk_score ?? 0),
                    'fraud_flags_count' => (int) ($attendanceModel?->fraud_flags_count ?? 0),
                    'fraud_flags' => $fraudAssessmentPayload['fraud_flags'] ?? [],
                    'security_notice' => $submitNotice,
                    'fraud_assessment' => $fraudAssessmentPayload,
                ]
            ]);
        } catch (\Exception $e) {
            if ($e instanceof \InvalidArgumentException) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'code' => 'INVALID_PHOTO_FORMAT',
                ], 422);
            }

            Log::error('Failed to submit attendance', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit attendance'
            ], 500);
        }
    }

    private function queueAttendanceWhatsappNotification(?Absensi $attendance, string $type): void
    {
        if (!$attendance instanceof Absensi) {
            return;
        }

        if ((bool) $attendance->is_manual) {
            return;
        }

        $event = match ($type) {
            'masuk' => 'checkin',
            'pulang' => 'checkout',
            default => null,
        };

        if ($event === null) {
            return;
        }

        try {
            $job = new DispatchAttendanceWhatsappNotification((int) $attendance->id, $event);

            Log::info('Queueing WA notification for simple attendance submit', [
                'attendance_id' => (int) $attendance->id,
                'event' => $event,
                'queue' => $job->queue,
                'connection' => config('queue.default'),
            ]);

            Queue::push($job);
        } catch (\Throwable $exception) {
            Log::warning('Failed to queue WA notification for simple attendance submit', [
                'attendance_id' => (int) $attendance->id,
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Get success message based on attendance type and status
     */
    private function getSuccessMessage(string $type, string $status): string
    {
        $messages = [
            'masuk' => [
                'tepat_waktu' => 'Absen masuk berhasil - Tepat waktu',
                'terlambat' => 'Absen masuk berhasil - Terlambat',
                'valid' => 'Absen masuk berhasil'
            ],
            'pulang' => [
                'valid' => 'Absen pulang berhasil',
                'tepat_waktu' => 'Absen pulang berhasil',
                'terlambat' => 'Absen pulang berhasil'
            ]
        ];

        return $messages[$type][$status] ?? "Absen {$type} berhasil";
    }

    private function isSyncFinalVerificationFailure(?array $verification): bool
    {
        if (!is_array($verification)) {
            return false;
        }

        $mode = strtolower(trim((string) ($verification['mode'] ?? '')));
        if ($mode !== 'sync_final') {
            return false;
        }

        $status = strtolower(trim((string) ($verification['status'] ?? $verification['result'] ?? '')));

        return in_array($status, ['rejected', 'manual_review'], true);
    }

    private function buildSyncFinalVerificationFailureMessage(?array $verification): string
    {
        if (!is_array($verification)) {
            return 'Verifikasi wajah gagal. Silakan ulangi proses absensi.';
        }

        $reasonCode = strtolower(trim((string) ($verification['reason_code'] ?? '')));

        return match ($reasonCode) {
            'no_face_detected' => 'Wajah tidak terdeteksi. Pastikan wajah terlihat jelas lalu ulangi selfie.',
            'multiple_faces_detected' => 'Terdeteksi lebih dari satu wajah. Pastikan hanya satu wajah pada kamera.',
            'below_threshold' => 'Wajah tidak cocok dengan template. Silakan ulangi selfie dengan posisi dan cahaya lebih baik.',
            'template_missing' => 'Template wajah belum tersedia untuk akun ini.',
            default => 'Verifikasi wajah gagal pada mode Sync Final. Silakan ulangi absensi.',
        };
    }

    /**
     * Kembalikan perubahan absensi jika mode Sync Final menghasilkan status non-verified.
     *
     * @param array<string, mixed>|null $previousAttendanceSnapshot
     */
    private function rollbackAttendanceMutationOnSyncFinalFailure(
        Absensi $attendance,
        string $type,
        bool $attendanceCreatedDuringSubmit,
        ?array $previousAttendanceSnapshot
    ): void {
        if ($attendanceCreatedDuringSubmit && $type === 'masuk') {
            $attendance->delete();
            return;
        }

        if (!$previousAttendanceSnapshot) {
            if ($type === 'masuk') {
                $attendance->delete();
            }
            return;
        }

        if ($type === 'masuk') {
            $columns = [
                'jam_masuk',
                'status',
                'metode_absensi',
                'keterangan',
                'latitude_masuk',
                'longitude_masuk',
                'gps_accuracy_masuk',
                'foto_masuk',
                'lokasi_masuk_id',
                'attendance_setting_id',
                'settings_snapshot',
                'device_info',
                'verification_status',
                'is_verified',
                'verified_at',
                'verified_by',
                'face_score_checkin',
                'updated_at',
            ];
        } else {
            $columns = [
                'jam_pulang',
                'latitude_pulang',
                'longitude_pulang',
                'gps_accuracy_pulang',
                'foto_pulang',
                'lokasi_pulang_id',
                'attendance_setting_id',
                'settings_snapshot',
                'device_info',
                'verification_status',
                'is_verified',
                'verified_at',
                'verified_by',
                'face_score_checkout',
                'updated_at',
            ];
        }

        $payload = $this->buildRollbackPayloadFromSnapshot($previousAttendanceSnapshot, $columns);
        if ($payload === []) {
            return;
        }

        DB::table('absensi')
            ->where('id', $attendance->id)
            ->update($payload);
    }

    /**
     * @param array<string, mixed> $snapshot
     * @param array<int, string> $columns
     * @return array<string, mixed>
     */
    private function buildRollbackPayloadFromSnapshot(array $snapshot, array $columns): array
    {
        $payload = [];

        foreach ($columns as $column) {
            $value = array_key_exists($column, $snapshot) ? $snapshot[$column] : null;
            if (in_array($column, ['settings_snapshot', 'device_info'], true)) {
                $value = $this->normalizeJsonColumnPayload($value);
            }

            $payload[$column] = $value;
        }

        return $payload;
    }

    /**
     * Get attendance history for user with working hours context
     */
    public function getAttendanceHistory(Request $request)
    {
        try {
            $user = $request->user();
            $limit = max(1, min((int) $request->input('limit', 10), 100));
            $date = $request->input('date');

            $query = DB::table('absensi')
                ->where('user_id', $user->id)
                ->orderByDesc('tanggal')
                ->orderByDesc('jam_masuk');

            if ($date) {
                $query->whereDate('tanggal', $date);
            }

            $attendances = $query->limit($limit)->get();

            // Add working hours context to each attendance record
            $workingHours = $this->attendanceTimeService->getWorkingHours($user);

            $attendancesWithContext = $attendances->map(function ($attendance) use ($workingHours) {
                return [
                    'id' => $attendance->id,
                    'tanggal' => $attendance->tanggal,
                    'jam_masuk' => $attendance->jam_masuk,
                    'jam_pulang' => $attendance->jam_pulang,
                    'status' => $attendance->status ?? 'unknown',
                    'metode_absensi' => $attendance->metode_absensi,
                    'keterangan' => $attendance->keterangan,
                    'has_check_in' => !empty($attendance->jam_masuk),
                    'has_check_out' => !empty($attendance->jam_pulang),
                    'latitude_masuk' => $attendance->latitude_masuk,
                    'longitude_masuk' => $attendance->longitude_masuk,
                    'latitude_pulang' => $attendance->latitude_pulang,
                    'longitude_pulang' => $attendance->longitude_pulang,
                    'foto_masuk' => $attendance->foto_masuk,
                    'foto_pulang' => $attendance->foto_pulang,
                    // Legacy aliases for old clients
                    'jenis_absensi' => !empty($attendance->jam_pulang) ? 'pulang' : 'masuk',
                    'waktu_absensi' => $attendance->jam_masuk,
                    'latitude' => $attendance->latitude_masuk,
                    'longitude' => $attendance->longitude_masuk,
                    'working_hours_context' => $workingHours
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => [
                    'attendances' => $attendancesWithContext,
                    'current_working_hours' => $workingHours
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get attendance history'
            ], 500);
        }
    }

    /**
     * Get governance logs for attendance configuration changes.
     */
    public function getGovernanceLogs(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'category' => 'nullable|string|max:100',
                'action' => 'nullable|string|max:100',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi filter log gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $perPage = (int) $request->input('per_page', 10);
            $query = AttendanceGovernanceLog::query()
                ->with(['actor:id,nama_lengkap,username,email']);

            if ($request->filled('category')) {
                $query->where('category', $request->input('category'));
            }

            if ($request->filled('action')) {
                $query->where('action', $request->input('action'));
            }

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->input('date_from'));
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->input('date_to'));
            }

            $logs = $query->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $logs,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load attendance governance logs', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat governance logs absensi',
            ], 500);
        }
    }

    public function reportPrecheckSecurityWarning(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action_type' => 'nullable|in:checkin,checkout,masuk,pulang',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'accuracy' => 'nullable|numeric|min:0',
                'is_mocked' => 'nullable|boolean',
                'lokasi_id' => 'nullable|integer|exists:lokasi_gps,id',
                'device_id' => 'nullable|string|max:191',
                'device_info' => 'nullable',
                'request_timestamp' => 'nullable|date',
                'anti_fraud_payload' => 'nullable',
                'security_warning_payload' => 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payload warning pra-cek tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $user = $request->user();
            if (!$user instanceof User || !$this->isStudentAttendanceUser($user)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Pra-cek keamanan diabaikan untuk akun non-siswa',
                    'data' => [
                        'logged' => false,
                        'ignored' => true,
                    ],
                ]);
            }

            $warningPayload = $this->normalizeSecurityWarningPayload(
                $request->input('security_warning_payload')
            );
            $issues = $this->extractSecurityIssuesFromPayload($warningPayload);
            if ($request->boolean('is_mocked')) {
                $issues[] = $this->buildMockLocationSecurityIssue($request, false);
            }
            $issues = $this->normalizeSecurityIssues($issues);

            if ($issues === []) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Tidak ada warning keamanan pra-cek yang perlu dicatat',
                    'data' => [
                        'logged' => false,
                        'notice' => null,
                    ],
                ]);
            }

            $attemptType = $this->resolveAttemptTypeFromActionType(
                $request->input('action_type') ?: ($warningPayload['action_type'] ?? null)
            );
            $warningHash = (string) ($warningPayload['warning_hash'] ?? sha1(json_encode($issues) ?: 'security-warning'));
            $cacheKey = $this->makeSecurityWarningCacheKey($user, $warningHash, 'attendance_precheck', $attemptType);

            if (Cache::has($cacheKey)) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Warning pra-cek sudah tercatat sebelumnya',
                    'data' => [
                        'logged' => false,
                        'deduplicated' => true,
                        'warning_hash' => $warningHash,
                        'notice' => $this->buildSecurityNoticeBox(
                            'attendance_precheck',
                            $issues,
                            $attemptType,
                            [
                                'acknowledged' => (bool) ($warningPayload['acknowledged'] ?? true),
                                'acknowledged_at' => $warningPayload['acknowledged_at'] ?? now()->toIso8601String(),
                                'warning_hash' => $warningHash,
                            ]
                        ),
                    ],
                ]);
            }

            Cache::put($cacheKey, true, now()->addMinutes(10));

            $notice = $this->buildSecurityNoticeBox(
                'attendance_precheck',
                $issues,
                $attemptType,
                [
                    'acknowledged' => (bool) ($warningPayload['acknowledged'] ?? true),
                    'acknowledged_at' => $warningPayload['acknowledged_at'] ?? now()->toIso8601String(),
                    'warning_hash' => $warningHash,
                ]
            );

            $this->recordSecurityIssuesForStage($user, $issues, 'attendance_precheck', $attemptType, [
                'trigger' => 'precheck_popup',
                'warning_payload' => $warningPayload,
            ]);

            $fraudAssessment = $this->recordFraudAssessment($user, $request, [
                'source' => 'attendance_precheck',
                'attempt_type' => $attemptType,
                'force_non_blocking' => true,
                'metadata' => [
                    'precheck_notice' => $notice,
                    'security_warning_context' => [
                        'stage' => 'attendance_precheck',
                        'issues' => $issues,
                        'warning_hash' => $warningHash,
                        'acknowledged_at' => $warningPayload['acknowledged_at'] ?? now()->toIso8601String(),
                    ],
                ],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Warning keamanan pra-cek berhasil dicatat',
                'data' => [
                    'logged' => true,
                    'warning_hash' => $warningHash,
                    'notice' => $notice,
                    'fraud_assessment' => $this->fraudAssessmentResponsePayload($fraudAssessment),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to report attendance precheck warning', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mencatat warning keamanan pra-cek',
            ], 500);
        }
    }

    /**
     * Get suspicious attendance security events for evaluation.
     */
    public function getSecurityEvents(Request $request)
    {
        try {
            $validator = $this->makeSecurityEventFilterValidator($request, true);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi filter laporan keamanan gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $perPage = (int) $request->input('per_page', 15);
            $query = $this->buildAttendanceSecurityEventsQuery($request);
            $events = $query->orderByDesc('updated_at')->orderByDesc('created_at')->paginate($perPage);
            $events->getCollection()->transform(
                static fn(AttendanceSecurityEvent $event): array => $event->toReportArray()
            );

            return response()->json([
                'status' => 'success',
                'data' => $events,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load attendance security events', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat laporan keamanan absensi',
            ], 500);
        }
    }

    /**
     * Get compact summary for suspicious attendance events.
     */
    public function getSecurityEventSummary(Request $request)
    {
        try {
            $validator = $this->makeSecurityEventFilterValidator($request, false);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi filter ringkasan keamanan gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $events = $this->buildAttendanceSecurityEventsQuery($request)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $this->buildSecurityEventSummaryPayload($events),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load attendance security events summary', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat ringkasan keamanan absensi',
            ], 500);
        }
    }

    public function exportSecurityEvents(Request $request)
    {
        $validator = $this->makeSecurityEventFilterValidator($request, false);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi export laporan keamanan gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rows = $this->buildAttendanceSecurityEventsQuery($request)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn(AttendanceSecurityEvent $event): array => $event->toReportArray())
            ->values();

        $timestamp = now()->format('Ymd-His');

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'ID',
                'Tahap',
                'Waktu',
                'Tanggal Event',
                'Siswa',
                'Identitas',
                'Kelas',
                'Event',
                'Severity',
                'Status',
                'Jenis Absensi',
                'Pesan',
                'Device ID',
                'IP Address',
                'Latitude',
                'Longitude',
                'Akurasi',
            ]);

            foreach ($rows as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['stage_label'] ?? '-',
                    $row['last_seen_at'] ?? $row['created_at'] ?? '-',
                    $row['event_date'] ?? '-',
                    $row['student']['name'] ?? '-',
                    $row['student']['identifier'] ?? '-',
                    $row['kelas']['name'] ?? '-',
                    $row['event_label'] ?? $row['event_key'] ?? '-',
                    $row['severity_label'] ?? $row['severity'] ?? '-',
                    $row['status_label'] ?? $row['status'] ?? '-',
                    $row['attempt_type'] ?? '-',
                    $row['message'] ?? '-',
                    $row['device_id'] ?? '-',
                    $row['ip_address'] ?? '-',
                    $row['latitude'] ?? '-',
                    $row['longitude'] ?? '-',
                    $row['accuracy'] ?? '-',
                ]);
            }

            fclose($output);
        }, 'attendance-security-events-' . $timestamp . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function getFraudAssessments(Request $request)
    {
        try {
            $validator = $this->makeFraudAssessmentFilterValidator($request, true);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi filter fraud monitoring gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $perPage = (int) $request->input('per_page', 15);
            $query = $this->buildFraudAssessmentsQuery($request);
            $assessments = $query->orderByDesc('updated_at')->orderByDesc('created_at')->paginate($perPage);
            $assessments->getCollection()->transform(
                static fn(AttendanceFraudAssessment $assessment): array => $assessment->toMonitoringArray()
            );

            return response()->json([
                'status' => 'success',
                'data' => $assessments,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load attendance fraud assessments', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat fraud monitoring absensi',
            ], 500);
        }
    }

    public function getFraudAssessmentSummary(Request $request)
    {
        try {
            $validator = $this->makeFraudAssessmentFilterValidator($request, false);
            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validasi ringkasan fraud monitoring gagal',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $assessments = $this->buildFraudAssessmentsQuery($request)
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->get();
            $assessmentRows = $assessments
                ->map(static fn(AttendanceFraudAssessment $assessment): array => $assessment->toMonitoringArray())
                ->values();

            $topFlags = $assessments
                ->flatMap(static fn(AttendanceFraudAssessment $assessment) => $assessment->flags)
                ->groupBy('flag_key')
                ->map(static function ($group, string $flagKey): array {
                    $first = $group->first();

                    return [
                        'flag_key' => $flagKey,
                        'label' => $first?->label,
                        'severity' => $first?->severity,
                        'total' => $group->sum(static fn($flag): int => max(1, (int) (is_array($flag->evidence ?? null) ? ($flag->evidence['occurrence_count'] ?? 1) : 1))),
                    ];
                })
                ->sortByDesc('total')
                ->values()
                ->take(10)
                ->all();

            $recentWarningAssessments = $assessmentRows
                ->where('has_warning', true)
                ->take(5)
                ->values()
                ->all();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'config' => $this->attendanceFraudDetectionService->getConfigSummary(),
                    'overview' => [
                        'total_assessments' => $assessments->count(),
                        'warning_count' => $assessmentRows->where('has_warning', true)->count(),
                        'precheck_warning_count' => $assessmentRows
                            ->where('has_warning', true)
                            ->where('source', 'attendance_precheck')
                            ->count(),
                        'submit_warning_count' => $assessmentRows
                            ->where('has_warning', true)
                            ->where('source', 'attendance_submit')
                            ->count(),
                        'unique_students' => $assessments->pluck('user_id')->filter()->unique()->count(),
                    ],
                    'top_flags' => $topFlags,
                    'recent_warning_assessments' => $recentWarningAssessments,
                    'recent_blocking_attempts' => [],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load attendance fraud summary', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat ringkasan fraud monitoring',
            ], 500);
        }
    }

    public function exportFraudAssessments(Request $request)
    {
        $validator = $this->makeFraudAssessmentFilterValidator($request, false);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi export fraud monitoring gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        $rows = $this->buildFraudAssessmentsQuery($request)
            ->orderByDesc('updated_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(static fn(AttendanceFraudAssessment $assessment): array => $assessment->toMonitoringArray())
            ->values();

        $timestamp = now()->format('Ymd-His');

        return response()->streamDownload(function () use ($rows) {
            $output = fopen('php://output', 'w');
            if ($output === false) {
                return;
            }

            fputcsv($output, [
                'ID',
                'Tahap',
                'Waktu',
                'Tanggal Assessment',
                'Siswa',
                'Identitas',
                'Kelas',
                'Status Validasi',
                'Has Warning',
                'Warning Summary',
                'Jumlah Flag',
                'Recommended Action',
                'Notice',
            ]);

            foreach ($rows as $row) {
                $noticeText = collect($row['notice_boxes'] ?? [])
                    ->map(static function (array $box): string {
                        $title = trim((string) ($box['title'] ?? ''));
                        $message = trim((string) ($box['message'] ?? ''));
                        return trim($title . ': ' . $message, ': ');
                    })
                    ->filter()
                    ->implode(' | ');

                fputcsv($output, [
                    $row['id'],
                    $row['source_label'] ?? '-',
                    $row['last_seen_at'] ?? $row['created_at'] ?? '-',
                    $row['assessment_date'] ?? '-',
                    $row['student']['name'] ?? '-',
                    $row['student']['identifier'] ?? '-',
                    $row['kelas']['name'] ?? '-',
                    $row['validation_status_label'] ?? $row['validation_status'] ?? '-',
                    !empty($row['has_warning']) ? 'Ya' : 'Tidak',
                    $row['warning_summary'] ?? '-',
                    $row['fraud_flags_count'] ?? 0,
                    $row['recommended_action'] ?? '-',
                    $noticeText !== '' ? $noticeText : '-',
                ]);
            }

            fclose($output);
        }, 'attendance-fraud-assessments-' . $timestamp . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function showFraudAssessment(AttendanceFraudAssessment $assessment)
    {
        try {
            $assessment->load([
                'user:id,nama_lengkap,username,nis,nisn',
                'kelas:id,nama_kelas,jurusan,tingkat_id',
                'flags',
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $assessment->toMonitoringArray(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load attendance fraud assessment detail', [
                'assessment_id' => $assessment->id ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat detail fraud assessment',
            ], 500);
        }
    }

    /**
     * Check if user can manage global attendance settings.
     */
    private function canManageAttendanceSettings(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasPermissionTo('manage_attendance_settings');
    }

    /**
     * Expose an explicit non-student policy marker without breaking existing consumers.
     *
     * Some older mobile/web surfaces still read working-hours payloads defensively.
     * We keep the response shape compatible, but mark the attendance module as disabled
     * for non-student users so callers do not treat simple-attendance as an active path.
     *
     * @return array<string, mixed>
     */
    private function buildNonStudentAttendanceMetadata(User $user): array
    {
        return [
            'attendance_enabled' => false,
            'attendance_scope' => 'siswa_only',
            'attendance_channel' => 'mobile_app',
            'attendance_message' => 'Absensi simple-attendance hanya berlaku untuk akun siswa. Akun non-siswa tidak diproses di modul ini.',
            'recommended_channel' => 'pegawai_external_flow',
            'is_student_attendance_user' => false,
            'user_id' => $user->id,
        ];
    }

    /**
     * Detect web/browser submit attempts and downgrade them to warning-only.
     *
     * Device binding remains the only hard block in the submit security layer.
     */
    private function ensureMobileAppAttendanceRequest(Request $request): ?array
    {
        $isJwtApiAuth = Auth::guard('api')->check();
        $clientPlatform = strtolower(trim((string) $request->header('X-Client-Platform', '')));
        $clientApp = strtolower(trim((string) $request->header('X-Client-App', '')));
        $origin = trim((string) $request->header('Origin', ''));
        $referer = trim((string) $request->header('Referer', ''));

        $explicitWebClient = in_array($clientPlatform, ['web', 'dashboard-web', 'browser'], true)
            || str_contains($clientApp, 'web')
            || $origin !== ''
            || $referer !== '';

        if ($isJwtApiAuth && !$explicitWebClient) {
            return null;
        }

        Log::warning('Attendance submit flagged by mobile-only policy', [
            'user_id' => optional($request->user())->id,
            'client_platform' => $clientPlatform !== '' ? $clientPlatform : null,
            'client_app' => $clientApp !== '' ? $clientApp : null,
            'origin' => $origin !== '' ? $origin : null,
            'referer' => $referer !== '' ? $referer : null,
            'ip_address' => $request->ip(),
            'auth_guard_api' => $isJwtApiAuth,
        ]);

        return [
            'event_key' => 'mobile_app_only_violation',
            'label' => 'Absensi dari web/browser',
            'message' => 'Permintaan absensi terdeteksi berasal dari web/browser. Warning dicatat, tetapi submit tetap diproses.',
            'severity' => 'medium',
            'category' => 'client_policy',
            'metadata' => array_filter([
                'client_platform' => $clientPlatform !== '' ? $clientPlatform : null,
                'client_app' => $clientApp !== '' ? $clientApp : null,
                'origin' => $origin !== '' ? $origin : null,
                'referer' => $referer !== '' ? $referer : null,
                'auth_guard_api' => $isJwtApiAuth,
                'stage' => 'attendance_submit',
            ]),
        ];
    }

    private function canPerformAttendance(User $user, ?AttendanceSchema $effectiveSchema = null): bool
    {
        if (!$this->isStudentAttendanceUser($user)) {
            return false;
        }

        $effectiveSchema = $effectiveSchema ?: $this->attendanceSchemaService->getEffectiveSchema($user);
        if ($effectiveSchema) {
            return $effectiveSchema->allowsAttendanceForUser($user);
        }

        return true;
    }

    private function validateDeviceBindingForAttendance(User $user, $deviceIdRaw): array
    {
        if (!$user->isDeviceLocked()) {
            return ['valid' => true];
        }

        $deviceId = is_string($deviceIdRaw) ? trim($deviceIdRaw) : '';
        if ($deviceId === '') {
            Log::warning('Attendance blocked due to missing device_id on locked account', [
                'user_id' => $user->id,
                'bound_device_id' => $user->device_id,
                'ip_address' => request()->ip(),
            ]);

            $this->recordAttendanceSecurityEvent($user, [
                'category' => 'device_integrity',
                'event_key' => 'device_id_missing_on_locked_account',
                'severity' => 'medium',
                'status' => 'blocked',
                'attempt_type' => request()->input('jenis_absensi'),
                'metadata' => [
                    'stage' => 'attendance_submit',
                    'message' => 'Akun terikat perangkat, tetapi device_id tidak terkirim saat submit.',
                    'bound_device_id' => $user->device_id,
                    'bound_device_name' => $user->device_name,
                ],
            ]);

            $fraudAssessment = $this->recordFraudAssessment($user, request(), [
                'source' => 'attendance_submit',
                'attempt_type' => request()->input('jenis_absensi'),
                'device_guard' => [
                    'code' => 'device_id_required',
                    'data' => [
                        'bound_device_id' => $user->device_id,
                    ],
                ],
            ]);

            return [
                'valid' => false,
                'http_status' => 400,
                'code' => 'DEVICE_ID_REQUIRED',
                'message' => 'Akun Anda terikat perangkat. Perbarui aplikasi lalu login ulang untuk sinkronisasi perangkat.',
                'data' => [
                    'current_device' => $user->device_name,
                    'bound_at' => $user->device_bound_at,
                    'bound_device_id' => $user->device_id,
                    'fraud_assessment' => $this->fraudAssessmentResponsePayload($fraudAssessment),
                ],
            ];
        }

        if (!$user->isValidDevice($deviceId)) {
            Log::warning('Attendance blocked due to device lock mismatch', [
                'user_id' => $user->id,
                'bound_device_id' => $user->device_id,
                'attempted_device_id' => $deviceId,
                'ip_address' => request()->ip(),
            ]);

            $this->recordAttendanceSecurityEvent($user, [
                'category' => 'device_integrity',
                'event_key' => 'device_lock_violation',
                'severity' => 'high',
                'status' => 'blocked',
                'attempt_type' => request()->input('jenis_absensi'),
                'device_id' => $deviceId,
                'metadata' => [
                    'stage' => 'attendance_submit',
                    'message' => 'Submit absensi diblokir karena device tidak sesuai dengan binding akun.',
                    'bound_device_id' => $user->device_id,
                    'bound_device_name' => $user->device_name,
                    'attempted_device_id' => $deviceId,
                ],
            ]);

            $fraudAssessment = $this->recordFraudAssessment($user, request(), [
                'source' => 'attendance_submit',
                'attempt_type' => request()->input('jenis_absensi'),
                'device_guard' => [
                    'code' => 'device_lock_violation',
                    'data' => [
                        'bound_device_id' => $user->device_id,
                    ],
                ],
            ]);

            return [
                'valid' => false,
                'http_status' => 403,
                'code' => 'DEVICE_LOCK_VIOLATION',
                'message' => 'Akun Anda sudah terikat dengan perangkat lain. Gunakan perangkat terdaftar atau hubungi admin.',
                'data' => [
                    'current_device' => $user->device_name,
                    'bound_at' => $user->device_bound_at,
                    'bound_device_id' => $user->device_id,
                    'fraud_assessment' => $this->fraudAssessmentResponsePayload($fraudAssessment),
                ],
            ];
        }

        return ['valid' => true];
    }

    private function evaluateFaceTemplateRequirement(User $user, array $workingHours): ?array
    {
        $templateRequired = (bool) ($workingHours['face_template_required'] ?? false);
        if (!$templateRequired || !$this->isStudentAttendanceUser($user)) {
            return null;
        }

        $activeTemplate = \App\Models\UserFaceTemplate::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->latest('enrolled_at')
            ->latest('id')
            ->first();

        if ($activeTemplate) {
            return null;
        }

        return [
            'code' => 'FACE_TEMPLATE_REQUIRED',
            'message' => 'Template wajah siswa wajib tersedia sebelum absensi dapat dilakukan.',
            'data' => [
                'has_active_template' => false,
                'face_template_required' => true,
                'face_verification_enabled' => (bool) ($workingHours['face_verification_enabled'] ?? true),
            ],
        ];
    }

    private function isStudentAttendanceUser(User $user): bool
    {
        if ($user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            return true;
        }

        // Fallback legacy data siswa yang belum rapih role mapping-nya.
        return !empty($user->nis) || !empty($user->nisn);
    }

    private function dispatchOrProcessFaceVerification(
        Absensi $attendance,
        User $user,
        ?AttendanceSchema $effectiveSchema,
        string $checkType,
        bool $faceVerificationEnabled,
        ?string $photoPath = null
    ): array {
        $mode = $this->attendanceFaceVerificationService->determineMode($effectiveSchema);

        if (!$faceVerificationEnabled) {
            $attendance->update([
                'verification_status' => 'verified',
                'is_verified' => true,
                'verified_at' => Carbon::now(),
                'verified_by' => null,
            ]);

            return [
                'mode' => $mode,
                'enabled' => false,
                'status' => 'verified',
                'result' => 'verified',
                'reason_code' => 'face_verification_disabled',
            ];
        }

        if ($mode === 'async_pending') {
            $this->attendanceFaceVerificationService->markPending($attendance, $checkType);
            ProcessAttendanceFaceVerification::dispatch(
                $attendance->id,
                $checkType,
                $effectiveSchema?->id
            );

            return [
                'mode' => $mode,
                'enabled' => true,
                'status' => $attendance->fresh()->verification_status,
            ];
        }

        $result = $this->attendanceFaceVerificationService->processVerification(
            $attendance,
            $user,
            $checkType,
            $effectiveSchema,
            $photoPath
        );

        return [
            'mode' => $mode,
            'enabled' => true,
            ...$result,
        ];
    }

    private function validateLocationWithSchema(float $latitude, float $longitude, User $user, $accuracy = null): array
    {
        $effectiveSchema = $this->attendanceSchemaService->getEffectiveSchema($user);
        if ($effectiveSchema) {
            $validation = $effectiveSchema->canAttendAtLocation($latitude, $longitude);
            if (!($validation['can_attend'] ?? false)) {
                return [
                    'valid' => false,
                    'message' => $validation['reason'] ?? 'Lokasi tidak valid',
                    'details' => $validation,
                ];
            }

            $accuracyValidation = $this->validateGpsAccuracy(
                $accuracy,
                $effectiveSchema->getEffectiveGpsAccuracy()
            );
            if (!$accuracyValidation['valid']) {
                return [
                    'valid' => false,
                    'message' => $accuracyValidation['message'],
                    'details' => [
                        'required_accuracy' => $effectiveSchema->getEffectiveGpsAccuracy(),
                        'actual_accuracy' => $accuracy,
                    ],
                ];
            }

            return [
                'valid' => true,
                'message' => 'Lokasi valid',
                'details' => $validation,
            ];
        }

        $activeLocations = LokasiGps::where('is_active', true)->get();
        if ($activeLocations->isEmpty()) {
            return [
                'valid' => false,
                'message' => 'Belum ada lokasi absensi aktif yang dikonfigurasi',
            ];
        }

        foreach ($activeLocations as $location) {
            $evaluation = $location->evaluateCoordinate($latitude, $longitude);
            $distance = (float) ($evaluation['distance_to_area'] ?? PHP_FLOAT_MAX);

            if ($evaluation['inside'] ?? false) {
                return [
                    'valid' => true,
                    'message' => 'Lokasi valid',
                    'details' => [
                        'location_id' => $location->id,
                        'distance' => round($distance, 2),
                        'distance_to_boundary' => round((float) ($evaluation['distance_to_boundary'] ?? $distance), 2),
                        'allowed_radius' => (float) $location->radius,
                        'geofence_type' => $location->getNormalizedGeofenceType(),
                    ],
                ];
            }
        }

        return [
            'valid' => false,
            'message' => 'Lokasi Anda di luar area yang diizinkan',
        ];
    }

    private function validateGpsAccuracy($accuracy, int $requiredAccuracy): array
    {
        if ($accuracy === null || $accuracy === '') {
            return [
                'valid' => false,
                'message' => 'Akurasi GPS tidak tersedia. Aktifkan mode lokasi akurasi tinggi lalu coba lagi.',
            ];
        }

        $accuracyValue = (float) $accuracy;
        $graceMeters = max(0.0, (float) config('attendance.gps.accuracy_grace_meters', 0));
        $allowedAccuracy = (float) $requiredAccuracy + $graceMeters;
        if ($accuracyValue > $allowedAccuracy) {
            return [
                'valid' => false,
                'message' => sprintf(
                    'Akurasi GPS Anda %.1fm melebihi batas %.1fm. Tunggu sinyal GPS lebih stabil lalu coba lagi.',
                    $accuracyValue,
                    $allowedAccuracy
                ),
            ];
        }

        return [
            'valid' => true,
            'message' => 'Akurasi GPS valid',
        ];
    }

    private function makeSecurityEventFilterValidator(Request $request, bool $includePagination = true)
    {
        $rules = [
            'category' => 'nullable|string|max:50',
            'event_key' => 'nullable|string|max:100',
            'issue_key' => 'nullable|string|max:100',
            'severity' => 'nullable|in:low,medium,high,critical',
            'status' => 'nullable|in:blocked,flagged,allowed',
            'stage' => 'nullable|in:attendance_precheck,attendance_submit',
            'attempt_type' => 'nullable|in:masuk,pulang',
            'user_id' => 'nullable|integer|exists:users,id',
            'kelas_id' => 'nullable|integer|exists:kelas,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ];

        if ($includePagination) {
            $rules['per_page'] = 'nullable|integer|min:1|max:100';
        }

        return Validator::make($request->all(), $rules);
    }

    private function buildAttendanceSecurityEventsQuery(Request $request)
    {
        $query = AttendanceSecurityEvent::query()
            ->with([
                'user:id,nama_lengkap,username,email,nis,nisn',
                'kelas:id,nama_kelas,jurusan,tingkat_id',
                'kelas.tingkat:id,nama',
            ]);

        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('event_key')) {
            $query->where('event_key', $request->input('event_key'));
        }

        if ($request->filled('issue_key')) {
            $query->whereIssueKey($request->input('issue_key'));
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('stage')) {
            $query->whereStage($request->input('stage'));
        }

        if ($request->filled('attempt_type')) {
            $query->where('attempt_type', $request->input('attempt_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', (int) $request->input('kelas_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return $query;
    }

    private function buildSecurityEventSummaryPayload(Collection $events): array
    {
        $totalEvents = $events->count();
        $blockedEvents = $events->where('status', 'blocked')->count();
        $flaggedEvents = $events->where('status', 'flagged')->count();
        $uniqueStudents = $events->pluck('user_id')->filter()->unique()->count();
        $stageBreakdown = $events
            ->groupBy(fn(AttendanceSecurityEvent $event): string => $this->resolveSecurityEventStage($event))
            ->map(static function (Collection $group, string $stage): array {
                return [
                    'stage' => $stage,
                    'stage_label' => AttendanceSecurityEvent::labelForStage($stage),
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $eventBreakdown = $events
            ->flatMap(static function (AttendanceSecurityEvent $event): array {
                return array_map(static function (array $issue): array {
                    $eventKey = (string) ($issue['event_key'] ?? 'unknown_security_event');

                    return [
                        'event_key' => $eventKey,
                        'event_label' => $issue['label'] ?? AttendanceSecurityEvent::labelForEventKey($eventKey),
                    ];
                }, $event->issueRows());
            })
            ->groupBy('event_key')
            ->map(function (Collection $group, string $eventKey): array {
                $first = $group->first();

                return [
                    'event_key' => $eventKey,
                    'event_label' => $first['event_label'] ?? AttendanceSecurityEvent::labelForEventKey($eventKey),
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $severityBreakdown = $events
            ->groupBy('severity')
            ->map(function (Collection $group, string $severity): array {
                return [
                    'severity' => $severity,
                    'severity_label' => AttendanceSecurityEvent::labelForSeverity($severity),
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->all();

        $topStudents = $events
            ->filter(static fn(AttendanceSecurityEvent $event): bool => $event->user_id !== null)
            ->groupBy('user_id')
            ->map(fn(Collection $group): array => $this->buildSecurityStudentSummaryRow($group))
            ->sortByDesc('total_events')
            ->values();

        $followUpCandidates = $topStudents
            ->filter(static function (array $row): bool {
                return $row['blocked_events'] >= 2
                    || $row['mock_location_events'] >= 1
                    || $row['device_events'] >= 1;
            })
            ->take(10)
            ->values()
            ->all();

        $recentEvents = $events
            ->sortByDesc(static fn(AttendanceSecurityEvent $event): int => $event->updated_at?->getTimestamp()
                ?? $event->created_at?->getTimestamp()
                ?? 0)
            ->take(10)
            ->values()
            ->map(static fn(AttendanceSecurityEvent $event): array => $event->toReportArray())
            ->all();

        return [
            'overview' => [
                'total_events' => $totalEvents,
                'blocked_events' => $blockedEvents,
                'flagged_events' => $flaggedEvents,
                'unique_students' => $uniqueStudents,
            ],
            'stage_breakdown' => $stageBreakdown,
            'event_breakdown' => $eventBreakdown,
            'severity_breakdown' => $severityBreakdown,
            'top_students' => $topStudents->take(10)->values()->all(),
            'follow_up_candidates' => $followUpCandidates,
            'recent_events' => $recentEvents,
        ];
    }

    private function buildSecurityStudentSummaryRow(Collection $events): array
    {
        /** @var AttendanceSecurityEvent|null $latest */
        $latest = $events
            ->sortByDesc(static fn(AttendanceSecurityEvent $event): int => $event->updated_at?->getTimestamp()
                ?? $event->created_at?->getTimestamp()
                ?? 0)
            ->first();

        $student = $latest?->toReportArray()['student'] ?? [];
        $kelas = $latest?->toReportArray()['kelas'] ?? [];
        $blockedEvents = $events->where('status', 'blocked')->count();
        $mockLocationEvents = $events
            ->filter(static fn(AttendanceSecurityEvent $event): bool => $event->hasIssueKey('mock_location_detected'))
            ->count();
        $deviceEvents = $events->filter(fn(AttendanceSecurityEvent $event): bool => $this->isDeviceOrAppIntegrityEvent($event))->count();
        $precheckEvents = $events->filter(fn(AttendanceSecurityEvent $event): bool => $this->resolveSecurityEventStage($event) === 'attendance_precheck')->count();
        $submitEvents = $events->filter(fn(AttendanceSecurityEvent $event): bool => $this->resolveSecurityEventStage($event) === 'attendance_submit')->count();

        return [
            'user_id' => $latest?->user_id ? (int) $latest->user_id : null,
            'student_name' => $student['name'] ?? null,
            'student_identifier' => $student['identifier'] ?? null,
            'kelas_id' => $latest?->kelas_id ? (int) $latest->kelas_id : null,
            'kelas_name' => $kelas['name'] ?? null,
            'total_events' => $events->count(),
            'blocked_events' => $blockedEvents,
            'mock_location_events' => $mockLocationEvents,
            'device_events' => $deviceEvents,
            'precheck_events' => $precheckEvents,
            'submit_events' => $submitEvents,
            'last_event_at' => $latest?->updated_at?->toIso8601String() ?? $latest?->created_at?->toIso8601String(),
            'last_event_label' => $latest ? AttendanceSecurityEvent::labelForEventKey((string) $latest->event_key) : null,
            'recommendation' => $this->buildSecurityFollowUpRecommendation($events),
        ];
    }

    private function buildSecurityFollowUpRecommendation(Collection $events): string
    {
        $mockLocationEvents = $events
            ->filter(static fn(AttendanceSecurityEvent $event): bool => $event->hasIssueKey('mock_location_detected'))
            ->count();
        if ($mockLocationEvents > 0) {
            return 'Prioritaskan pemanggilan siswa, cek ponsel, developer options, dan histori lokasi absensi.';
        }

        $deviceEvents = $events->filter(fn(AttendanceSecurityEvent $event): bool => $this->isDeviceOrAppIntegrityEvent($event))->count();
        if ($deviceEvents > 0) {
            return 'Verifikasi kondisi perangkat, binding device, clone app, root, debugging, dan histori login akun.';
        }

        return 'Tinjau kronologi kejadian bersama wali kelas dan cocokkan dengan jadwal serta lokasi sekolah.';
    }

    private function makeFraudAssessmentFilterValidator(Request $request, bool $includePagination = true)
    {
        $rules = [
            'source' => 'nullable|in:attendance_precheck,attendance_submit',
            'validation_status' => 'nullable|in:valid,warning',
            'attempt_type' => 'nullable|in:masuk,pulang',
            'flag_key' => 'nullable|string|max:100',
            'user_id' => 'nullable|integer|exists:users,id',
            'kelas_id' => 'nullable|integer|exists:kelas,id',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ];

        if ($includePagination) {
            $rules['per_page'] = 'nullable|integer|min:1|max:100';
        }

        return Validator::make($request->all(), $rules);
    }

    private function buildFraudAssessmentsQuery(Request $request)
    {
        $query = AttendanceFraudAssessment::query()
            ->with([
                'user:id,nama_lengkap,username,email,nis,nisn',
                'kelas:id,nama_kelas,jurusan,tingkat_id',
                'kelas.tingkat:id,nama',
                'flags',
            ]);

        if ($request->filled('source')) {
            $query->where('source', $request->input('source'));
        }

        if ($request->filled('validation_status')) {
            if ($request->input('validation_status') === 'warning') {
                $query->where('validation_status', '!=', 'valid');
            } else {
                $query->where('validation_status', 'valid');
            }
        }

        if ($request->filled('attempt_type')) {
            $query->where('attempt_type', $request->input('attempt_type'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', (int) $request->input('kelas_id'));
        }

        if ($request->filled('flag_key')) {
            $flagKey = $request->input('flag_key');
            $query->whereHas('flags', static fn($flagQuery) => $flagQuery->where('flag_key', $flagKey));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return $query;
    }

    private function allowSubmitWithSecurityWarnings(): bool
    {
        return true;
    }

    private function normalizeSecurityWarningPayload($raw): array
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function extractSecurityIssuesFromPayload(array $payload): array
    {
        $issues = $payload['issues'] ?? [];
        if (!is_array($issues)) {
            return [];
        }

        return $this->normalizeSecurityIssues($issues);
    }

    private function normalizeSecurityIssues(array $issues): array
    {
        $normalized = [];

        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }

            $resolved = $this->normalizeSecurityIssueArray($issue);
            if ($resolved === null) {
                continue;
            }

            $normalized[$resolved['event_key']] = $resolved;
        }

        return array_values($normalized);
    }

    private function normalizeSecurityIssueArray(array $issue): ?array
    {
        $key = $this->canonicalizeSecurityIssueKey(
            $issue['event_key'] ?? $issue['key'] ?? $issue['flag_key'] ?? null
        );

        if ($key === null) {
            return null;
        }

        $catalog = $this->securityIssueCatalog();
        $defaults = $catalog[$key] ?? null;
        if ($defaults === null) {
            return null;
        }

        return [
            'event_key' => $key,
            'label' => $issue['label'] ?? $defaults['label'],
            'message' => $issue['message'] ?? $defaults['message'],
            'severity' => $issue['severity'] ?? $defaults['severity'],
            'category' => $issue['category'] ?? $defaults['category'],
            'metadata' => is_array($issue['metadata'] ?? null)
                ? $issue['metadata']
                : (is_array($issue['evidence'] ?? null) ? $issue['evidence'] : []),
        ];
    }

    private function canonicalizeSecurityIssueKey($key): ?string
    {
        $normalized = strtolower(trim((string) $key));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'mock_location', 'mock_provider', 'mock_location_detected' => 'mock_location_detected',
            'developer_options', 'developer_options_enabled' => 'developer_options_enabled',
            'gps_accuracy_low' => 'gps_accuracy_low',
            'outside_geofence' => 'outside_geofence',
            'stale_location' => 'stale_location',
            'time_drift' => 'time_drift',
            'root_or_jailbreak', 'root_detected', 'jailbreak_detected', 'root_or_jailbreak_detected' => 'root_or_jailbreak_detected',
            'adb_or_usb_debugging', 'adb_enabled', 'usb_debugging_enabled', 'adb_or_usb_debugging_enabled' => 'adb_or_usb_debugging_enabled',
            'emulator', 'emulator_detected' => 'emulator_detected',
            'app_clone', 'app_clone_risk', 'app_clone_detected' => 'app_clone_detected',
            'app_tampering', 'tampering_detected', 'app_tampering_detected' => 'app_tampering_detected',
            'instrumentation', 'frida_detected', 'hooking_detected', 'xposed_detected', 'instrumentation_detected' => 'instrumentation_detected',
            'signature_mismatch', 'signature_mismatch_detected' => 'signature_mismatch_detected',
            'request_replay' => 'request_replay',
            'duplicate_frequency' => 'duplicate_frequency',
            'forged_metadata' => 'forged_metadata',
            'impossible_travel' => 'impossible_travel',
            'duplicate_coordinate_pattern' => 'duplicate_coordinate_pattern',
            'suspicious_network' => 'suspicious_network',
            'magisk_risk', 'magisk_risk_detected' => 'magisk_risk_detected',
            'suspicious_device_state', 'suspicious_device_state_detected' => 'suspicious_device_state_detected',
            'mobile_app_only', 'mobile_app_only_violation' => 'mobile_app_only_violation',
            'device_lock_violation' => 'device_lock_violation',
            'device_id_required', 'device_id_missing_on_locked_account' => 'device_id_missing_on_locked_account',
            default => null,
        };
    }

    private function securityIssueCatalog(): array
    {
        return [
            'mock_location_detected' => [
                'label' => 'Mock location / Fake GPS',
                'message' => 'Perangkat terdeteksi menggunakan mock location atau Fake GPS.',
                'severity' => 'high',
                'category' => 'gps_integrity',
            ],
            'developer_options_enabled' => [
                'label' => 'Developer options aktif',
                'message' => 'Developer options masih aktif pada perangkat saat proses absensi.',
                'severity' => 'medium',
                'category' => 'device_integrity',
            ],
            'gps_accuracy_low' => [
                'label' => 'Akurasi GPS rendah',
                'message' => 'Akurasi GPS melebihi batas schema absensi.',
                'severity' => 'medium',
                'category' => 'gps_integrity',
            ],
            'outside_geofence' => [
                'label' => 'Di luar geofence',
                'message' => 'Koordinat berada di luar titik absensi yang diizinkan.',
                'severity' => 'medium',
                'category' => 'gps_integrity',
            ],
            'stale_location' => [
                'label' => 'Timestamp lokasi stale',
                'message' => 'Timestamp lokasi terlalu lama dibanding waktu submit.',
                'severity' => 'medium',
                'category' => 'gps_integrity',
            ],
            'time_drift' => [
                'label' => 'Perbedaan waktu perangkat',
                'message' => 'Waktu perangkat berbeda signifikan dari waktu server.',
                'severity' => 'medium',
                'category' => 'request_integrity',
            ],
            'root_or_jailbreak_detected' => [
                'label' => 'Root / jailbreak terdeteksi',
                'message' => 'Perangkat terindikasi root atau jailbreak.',
                'severity' => 'high',
                'category' => 'device_integrity',
            ],
            'adb_or_usb_debugging_enabled' => [
                'label' => 'ADB / USB debugging aktif',
                'message' => 'ADB atau USB debugging masih aktif pada perangkat.',
                'severity' => 'medium',
                'category' => 'device_integrity',
            ],
            'emulator_detected' => [
                'label' => 'Perangkat terindikasi emulator',
                'message' => 'Aplikasi berjalan pada emulator atau non-physical device.',
                'severity' => 'high',
                'category' => 'device_integrity',
            ],
            'app_clone_detected' => [
                'label' => 'Clone / dual app terdeteksi',
                'message' => 'Aplikasi terindikasi berjalan dalam mode clone atau dual app.',
                'severity' => 'high',
                'category' => 'app_integrity',
            ],
            'app_tampering_detected' => [
                'label' => 'Integritas aplikasi bermasalah',
                'message' => 'Aplikasi terindikasi termodifikasi atau gagal verifikasi integritas.',
                'severity' => 'high',
                'category' => 'app_integrity',
            ],
            'instrumentation_detected' => [
                'label' => 'Instrumentation / hooking terdeteksi',
                'message' => 'Frida, Xposed, atau hooking framework terdeteksi pada perangkat.',
                'severity' => 'high',
                'category' => 'app_integrity',
            ],
            'signature_mismatch_detected' => [
                'label' => 'Signature aplikasi tidak sesuai',
                'message' => 'Signature, package name, atau installer source aplikasi tidak sesuai policy.',
                'severity' => 'high',
                'category' => 'app_integrity',
            ],
            'request_replay' => [
                'label' => 'Replay request',
                'message' => 'Nonce request sudah pernah dipakai sebelumnya.',
                'severity' => 'high',
                'category' => 'request_integrity',
            ],
            'duplicate_frequency' => [
                'label' => 'Frekuensi submit tidak wajar',
                'message' => 'Frekuensi submit absensi terlalu rapat untuk user yang sama.',
                'severity' => 'medium',
                'category' => 'request_integrity',
            ],
            'forged_metadata' => [
                'label' => 'Metadata tidak valid',
                'message' => 'Metadata client tidak lengkap, gagal diverifikasi, atau tidak konsisten.',
                'severity' => 'high',
                'category' => 'request_integrity',
            ],
            'impossible_travel' => [
                'label' => 'Impossible travel',
                'message' => 'Perubahan lokasi antar attempt terlalu cepat dan tidak realistis.',
                'severity' => 'high',
                'category' => 'gps_integrity',
            ],
            'duplicate_coordinate_pattern' => [
                'label' => 'Pola koordinat berulang',
                'message' => 'Koordinat yang sama muncul berulang kali pada pola yang tidak wajar.',
                'severity' => 'medium',
                'category' => 'gps_integrity',
            ],
            'suspicious_network' => [
                'label' => 'Jaringan mencurigakan',
                'message' => 'Konteks jaringan ditandai mencurigakan atau tidak sesuai daftar tepercaya.',
                'severity' => 'low',
                'category' => 'network_context',
            ],
            'magisk_risk_detected' => [
                'label' => 'Risiko Magisk terdeteksi',
                'message' => 'Perangkat terindikasi memiliki komponen Magisk atau modul sejenis.',
                'severity' => 'high',
                'category' => 'device_integrity',
            ],
            'suspicious_device_state_detected' => [
                'label' => 'Status perangkat mencurigakan',
                'message' => 'Perangkat berada pada kondisi keamanan yang tidak normal dan perlu klarifikasi.',
                'severity' => 'medium',
                'category' => 'device_integrity',
            ],
            'mobile_app_only_violation' => [
                'label' => 'Absensi dari web/browser',
                'message' => 'Permintaan absensi terdeteksi berasal dari web/browser dan dicatat sebagai warning-only.',
                'severity' => 'medium',
                'category' => 'client_policy',
            ],
            'device_lock_violation' => [
                'label' => 'Perangkat tidak sesuai binding akun',
                'message' => 'Perangkat yang dipakai tidak sesuai dengan binding device akun siswa.',
                'severity' => 'high',
                'category' => 'device_integrity',
            ],
            'device_id_missing_on_locked_account' => [
                'label' => 'Device ID tidak terkirim',
                'message' => 'Akun terikat perangkat, tetapi device ID tidak terkirim dari aplikasi.',
                'severity' => 'medium',
                'category' => 'device_integrity',
            ],
        ];
    }

    private function buildSecurityNoticeBox(string $stage, array $issues, ?string $attemptType = null, array $meta = []): ?array
    {
        $issues = $this->normalizeSecurityIssues($issues);
        if ($issues === []) {
            return null;
        }

        $issueLines = array_map(static function (array $issue): array {
            return [
                'event_key' => $issue['event_key'],
                'label' => $issue['label'],
                'message' => $issue['message'],
                'severity' => $issue['severity'],
            ];
        }, $issues);

        $isPrecheck = $stage === 'attendance_precheck';
        $title = $isPrecheck
            ? 'Warning keamanan saat pra-cek'
            : 'Warning keamanan tetap dicatat saat presensi';
        $message = $isPrecheck
            ? 'Sistem mendeteksi indikator keamanan pada perangkat. Absensi tetap dapat dilanjutkan, tetapi warning ini dicatat untuk monitoring dan klarifikasi.'
            : 'Absensi tetap diproses, tetapi indikator keamanan perangkat dicatat pada riwayat presensi untuk monitoring guru, wali kelas, dan admin.';

        return array_filter([
            'stage' => $stage,
            'stage_label' => AttendanceSecurityEvent::labelForStage($stage),
            'title' => $title,
            'message' => $message,
            'attempt_type' => $attemptType,
            'acknowledged' => $meta['acknowledged'] ?? null,
            'acknowledged_at' => $meta['acknowledged_at'] ?? null,
            'confirmed_at' => $meta['confirmed_at'] ?? null,
            'warning_hash' => $meta['warning_hash'] ?? null,
            'kind' => $meta['kind'] ?? 'warning',
            'issues' => $issueLines,
        ], static fn($value): bool => $value !== null && $value !== '');
    }

    private function recordSecurityIssuesForStage(?User $user, array $issues, string $stage, ?string $attemptType, array $context = []): void
    {
        $normalizedIssues = $this->normalizeSecurityIssues($issues);
        if ($normalizedIssues === []) {
            return;
        }

        $warningPayload = is_array($context['warning_payload'] ?? null) ? $context['warning_payload'] : [];
        $noticeBox = $this->buildSecurityNoticeBox($stage, $normalizedIssues, $attemptType, [
            'acknowledged' => $warningPayload['acknowledged'] ?? null,
            'acknowledged_at' => $warningPayload['acknowledged_at'] ?? null,
            'confirmed_at' => $warningPayload['confirmed_at'] ?? null,
            'warning_hash' => $warningPayload['warning_hash'] ?? null,
        ]);

        $primaryIssue = $this->resolvePrimarySecurityIssue($normalizedIssues);
        $this->recordAttendanceSecurityEvent($user, [
            'category' => $primaryIssue['category'] ?? 'attendance_security',
            'event_key' => $primaryIssue['event_key'] ?? 'unknown_security_event',
            'severity' => $primaryIssue['severity'] ?? 'medium',
            'status' => $context['status'] ?? 'flagged',
            'attempt_type' => $attemptType,
            'latitude' => request()->input('latitude'),
            'longitude' => request()->input('longitude'),
            'accuracy' => request()->input('accuracy'),
            'device_id' => request()->input('device_id'),
            'metadata' => array_filter([
                'stage' => $stage,
                'message' => count($normalizedIssues) > 1
                    ? sprintf('Terdeteksi %d indikator keamanan pada %s.', count($normalizedIssues), AttendanceSecurityEvent::labelForStage($stage))
                    : ($primaryIssue['message'] ?? null),
                'issue_label' => $primaryIssue['label'] ?? null,
                'issues' => $normalizedIssues,
                'notice_box' => $noticeBox,
                'warning_hash' => $warningPayload['warning_hash'] ?? null,
                'trigger' => $context['trigger'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== '' && $value !== []),
        ]);
    }

    private function buildMockLocationSecurityIssue(Request $request, bool $isBlocking): array
    {
        return [
            'event_key' => 'mock_location_detected',
            'label' => 'Mock location / Fake GPS',
            'message' => $isBlocking
                ? 'Mock location terdeteksi dan absensi diblokir oleh policy.'
                : 'Mock location terdeteksi. Absensi tetap dicatat sebagai warning untuk evaluasi.',
            'severity' => 'high',
            'category' => 'gps_integrity',
            'metadata' => [
                'blocked_by_policy' => $isBlocking,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'accuracy' => $request->input('accuracy'),
            ],
        ];
    }

    private function resolveAttemptTypeFromActionType($actionType): ?string
    {
        $normalized = strtolower(trim((string) $actionType));

        return match ($normalized) {
            'checkin', 'masuk' => 'masuk',
            'checkout', 'pulang' => 'pulang',
            default => null,
        };
    }

    private function makeSecurityWarningCacheKey(User $user, string $warningHash, string $stage, ?string $attemptType): string
    {
        return implode(':', [
            'attendance_security_warning',
            $user->id,
            $stage,
            $attemptType ?: 'unknown',
            $warningHash,
        ]);
    }

    private function resolveSecurityEventStage(AttendanceSecurityEvent $event): string
    {
        $metadata = is_array($event->metadata) ? $event->metadata : [];
        return (string) ($metadata['stage'] ?? 'attendance_submit');
    }

    private function isDeviceOrAppIntegrityEvent(AttendanceSecurityEvent $event): bool
    {
        $issueRows = $event->issueRows();
        $issueKeys = $event->issueKeys();
        $issueCategories = collect($issueRows)->pluck('category')->filter()->all();

        return collect($issueCategories)->intersect(['device_integrity', 'app_integrity'])->isNotEmpty()
            || collect($issueKeys)->contains(static function (string $eventKey): bool {
                return str_starts_with($eventKey, 'device_')
                    || in_array($eventKey, [
                        'developer_options_enabled',
                        'root_or_jailbreak_detected',
                        'adb_or_usb_debugging_enabled',
                        'emulator_detected',
                        'app_clone_detected',
                        'app_tampering_detected',
                        'instrumentation_detected',
                        'signature_mismatch_detected',
                        'magisk_risk_detected',
                        'suspicious_device_state_detected',
                    ], true);
            });
    }

    private function recordFraudAssessment(?User $user, Request $request, array $context = []): ?AttendanceFraudAssessment
    {
        if (!$user instanceof User || !$this->isStudentAttendanceUser($user)) {
            return null;
        }

        try {
            $classContext = $this->resolveStudentSecurityClassContext($user);
            $extraMetadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
            unset($context['metadata']);

            return $this->attendanceFraudDetectionService->assessSubmission($user, $request, array_merge([
                'metadata' => [
                    'kelas_id' => $classContext['kelas_id'],
                    'kelas_label' => $classContext['kelas_label'],
                    ...$extraMetadata,
                ],
                'kelas_id' => $classContext['kelas_id'],
            ], $context));
        } catch (\Throwable $e) {
            Log::warning('Failed to persist attendance fraud assessment', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function fraudAssessmentResponsePayload(?AttendanceFraudAssessment $assessment): ?array
    {
        if (!$assessment instanceof AttendanceFraudAssessment) {
            return null;
        }

        $payload = $assessment->toMonitoringArray();
        unset($payload['metadata'], $payload['flags']);

        return $payload;
    }

    private function buildSecurityEventFromLocationValidation(array $locationValidation, string $attemptType): ?array
    {
        $message = trim((string) ($locationValidation['message'] ?? ''));
        $details = is_array($locationValidation['details'] ?? null) ? $locationValidation['details'] : [];
        $normalizedMessage = strtolower($message);

        if (isset($details['required_accuracy']) || str_contains($normalizedMessage, 'akurasi gps')) {
            return [
                'category' => 'gps_integrity',
                'event_key' => 'gps_accuracy_low',
                'severity' => 'medium',
                'status' => 'flagged',
                'attempt_type' => $attemptType,
                'latitude' => request()->input('latitude'),
                'longitude' => request()->input('longitude'),
                'accuracy' => $details['actual_accuracy'] ?? request()->input('accuracy'),
                'device_id' => request()->input('device_id'),
                'metadata' => [
                    'stage' => 'attendance_submit',
                    'message' => $message,
                    'location_validation' => $details,
                ],
            ];
        }

        if (
            str_contains($normalizedMessage, 'di luar area')
            || isset($details['distance'])
            || isset($details['distance_to_area'])
            || isset($details['distance_to_boundary'])
        ) {
            return [
                'category' => 'gps_integrity',
                'event_key' => 'outside_geofence',
                'severity' => 'medium',
                'status' => 'flagged',
                'attempt_type' => $attemptType,
                'latitude' => request()->input('latitude'),
                'longitude' => request()->input('longitude'),
                'accuracy' => request()->input('accuracy'),
                'distance_meters' => $details['distance'] ?? $details['distance_to_area'] ?? null,
                'device_id' => request()->input('device_id'),
                'metadata' => [
                    'stage' => 'attendance_submit',
                    'message' => $message,
                    'location_validation' => $details,
                ],
            ];
        }

        return null;
    }

    private function buildSecurityIssueFromLocationValidation(array $locationValidation): ?array
    {
        $event = $this->buildSecurityEventFromLocationValidation($locationValidation, (string) request()->input('jenis_absensi', 'masuk'));
        if ($event === null) {
            return null;
        }

        $catalog = $this->securityIssueCatalog();
        $eventKey = (string) ($event['event_key'] ?? '');
        $defaults = $catalog[$eventKey] ?? null;
        if ($defaults === null) {
            return null;
        }

        $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];

        return [
            'event_key' => $eventKey,
            'label' => $defaults['label'],
            'message' => trim((string) ($metadata['message'] ?? $defaults['message'] ?? '')),
            'severity' => $defaults['severity'],
            'category' => $defaults['category'],
            'metadata' => array_filter([
                'location_validation' => $metadata['location_validation'] ?? null,
                'distance_meters' => $event['distance_meters'] ?? null,
                'accuracy' => $event['accuracy'] ?? null,
            ], static fn($value): bool => $value !== null && $value !== ''),
        ];
    }

    private function recordAttendanceSecurityEvent(?User $user, array $payload): void
    {
        if (!(bool) config('attendance.security.event_logging_enabled', true)) {
            return;
        }

        if (!$user instanceof User || !$this->isStudentAttendanceUser($user)) {
            return;
        }

        $request = request();
        $classContext = $this->resolveStudentSecurityClassContext($user);
        $deviceInfoRaw = $request->input('device_info');
        $deviceInfo = is_array($deviceInfoRaw) ? $deviceInfoRaw : null;
        if (is_string($deviceInfoRaw) && trim($deviceInfoRaw) !== '') {
            $decoded = json_decode($deviceInfoRaw, true);
            if (is_array($decoded)) {
                $deviceInfo = $decoded;
            }
        }

        $studentIdentifier = trim((string) ($user->nisn ?: $user->nis ?: $user->username ?: ('user-' . $user->id)));
        $inputMetadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $stage = (string) ($inputMetadata['stage'] ?? 'attendance_submit');
        $kelasId = $payload['kelas_id'] ?? $classContext['kelas_id'];
        $attemptType = $payload['attempt_type'] ?? null;
        $incomingIssues = $this->resolveSecurityEventIssuesFromPayload($payload);

        if ($incomingIssues === []) {
            return;
        }

        try {
            DB::transaction(function () use (
                $attemptType,
                $classContext,
                $deviceInfo,
                $incomingIssues,
                $inputMetadata,
                $kelasId,
                $payload,
                $request,
                $stage,
                $studentIdentifier,
                $user
            ) {
                $existing = $this->findDailyAttendanceSecurityEvent($user->id, $kelasId, $stage, $attemptType);
                $existingMetadata = is_array($existing?->metadata) ? $existing->metadata : [];
                $mergedIssues = $this->mergeSecurityIssueRows(
                    $existing instanceof AttendanceSecurityEvent ? $existing->issueRows() : [],
                    $incomingIssues
                );
                $primaryIssue = $this->resolvePrimarySecurityIssue($mergedIssues);

                if ($primaryIssue === null) {
                    return;
                }

                $baseMetadata = [
                    'student_name_snapshot' => $user->nama_lengkap ?: $user->username ?: ('User #' . $user->id),
                    'student_identifier_snapshot' => $studentIdentifier !== '' ? $studentIdentifier : null,
                    'kelas_label_snapshot' => $classContext['kelas_label'],
                    'client_platform' => trim((string) $request->header('X-Client-Platform', '')) ?: null,
                    'client_app' => trim((string) $request->header('X-Client-App', '')) ?: null,
                    'origin' => trim((string) $request->header('Origin', '')) ?: null,
                    'referer' => trim((string) $request->header('Referer', '')) ?: null,
                    'device_info' => $deviceInfo,
                    'stage' => $stage,
                    'issues' => $mergedIssues,
                    'issue_keys' => array_values(array_unique(array_map(
                        static fn(array $issue): string => (string) $issue['event_key'],
                        $mergedIssues
                    ))),
                    'issue_label' => $primaryIssue['label'] ?? null,
                    'message' => $inputMetadata['message']
                        ?? $existingMetadata['message']
                        ?? ($primaryIssue['message'] ?? null),
                    'warning_hash' => $inputMetadata['warning_hash'] ?? ($existingMetadata['warning_hash'] ?? null),
                    'trigger' => $inputMetadata['trigger'] ?? ($existingMetadata['trigger'] ?? null),
                    'notice_box' => $inputMetadata['notice_box'] ?? ($existingMetadata['notice_box'] ?? null),
                    'occurrence_count' => max(1, (int) ($existingMetadata['occurrence_count'] ?? 0)) + 1,
                    'first_seen_at' => $existingMetadata['first_seen_at']
                        ?? $existing?->created_at?->toIso8601String()
                        ?? now()->toIso8601String(),
                    'last_seen_at' => now()->toIso8601String(),
                    'primary_event_key' => $primaryIssue['event_key'],
                    'primary_event_label' => $primaryIssue['label'] ?? AttendanceSecurityEvent::labelForEventKey((string) $primaryIssue['event_key']),
                ];

                $metadata = array_filter(
                    array_merge($existingMetadata, $inputMetadata, $baseMetadata),
                    static fn($value): bool => $value !== null && $value !== '' && $value !== []
                );

                $eventData = [
                    'user_id' => $user->id,
                    'attendance_id' => $payload['attendance_id'] ?? $existing?->attendance_id,
                    'kelas_id' => $kelasId,
                    'category' => $primaryIssue['category'] ?? ($payload['category'] ?? 'attendance_security'),
                    'event_key' => $primaryIssue['event_key'] ?? ($payload['event_key'] ?? 'unknown_security_event'),
                    'severity' => $this->resolveHigherSecuritySeverity(
                        $existing?->severity,
                        $primaryIssue['severity'] ?? ($payload['severity'] ?? 'medium')
                    ),
                    'status' => $this->resolveHigherSecurityStatus($existing?->status, $payload['status'] ?? 'flagged'),
                    'attempt_type' => $attemptType,
                    'event_date' => now()->toDateString(),
                    'latitude' => $payload['latitude'] ?? $existing?->latitude,
                    'longitude' => $payload['longitude'] ?? $existing?->longitude,
                    'accuracy' => $payload['accuracy'] ?? $existing?->accuracy,
                    'distance_meters' => $payload['distance_meters'] ?? $existing?->distance_meters,
                    'device_id' => $payload['device_id'] ?? $existing?->device_id ?? $request->input('device_id'),
                    'ip_address' => $payload['ip_address'] ?? $existing?->ip_address ?? $request->ip(),
                    'metadata' => $metadata,
                ];

                if ($existing instanceof AttendanceSecurityEvent) {
                    $existing->fill($eventData);
                    $existing->save();
                    return;
                }

                $eventData['metadata']['occurrence_count'] = 1;
                AttendanceSecurityEvent::create($eventData);
            });
        } catch (\Throwable $e) {
            Log::warning('Failed to persist attendance security event', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'payload' => $payload,
            ]);
        }
    }

    private function resolveSecurityEventIssuesFromPayload(array $payload): array
    {
        $metadata = is_array($payload['metadata'] ?? null) ? $payload['metadata'] : [];
        $issues = $this->normalizeSecurityIssues($metadata['issues'] ?? []);

        if ($issues !== []) {
            return $issues;
        }

        return $this->normalizeSecurityIssues([[
            'event_key' => $payload['event_key'] ?? null,
            'label' => $metadata['issue_label'] ?? null,
            'message' => $metadata['message'] ?? null,
            'severity' => $payload['severity'] ?? null,
            'category' => $payload['category'] ?? null,
            'metadata' => is_array($metadata['issue_evidence'] ?? null) ? $metadata['issue_evidence'] : [],
        ]]);
    }

    private function findDailyAttendanceSecurityEvent(int $userId, ?int $kelasId, string $stage, ?string $attemptType): ?AttendanceSecurityEvent
    {
        $query = AttendanceSecurityEvent::query()
            ->where('user_id', $userId)
            ->whereDate('event_date', now()->toDateString())
            ->lockForUpdate();

        if ($kelasId !== null) {
            $query->where('kelas_id', $kelasId);
        } else {
            $query->whereNull('kelas_id');
        }

        if ($attemptType !== null) {
            $query->where('attempt_type', $attemptType);
        } else {
            $query->whereNull('attempt_type');
        }

        return $query
            ->orderByDesc('id')
            ->get()
            ->first(fn(AttendanceSecurityEvent $event): bool => $this->resolveSecurityEventStage($event) === $stage);
    }

    private function mergeSecurityIssueRows(array $existingIssues, array $incomingIssues): array
    {
        $merged = [];

        foreach (array_merge($existingIssues, $incomingIssues) as $issue) {
            $eventKey = trim((string) ($issue['event_key'] ?? ''));
            if ($eventKey === '') {
                continue;
            }

            if (!isset($merged[$eventKey])) {
                $merged[$eventKey] = [
                    'event_key' => $eventKey,
                    'label' => $issue['label'] ?? AttendanceSecurityEvent::labelForEventKey($eventKey),
                    'message' => $issue['message'] ?? null,
                    'severity' => strtolower(trim((string) ($issue['severity'] ?? 'medium'))),
                    'category' => $issue['category'] ?? 'attendance_security',
                    'metadata' => is_array($issue['metadata'] ?? null) ? $issue['metadata'] : [],
                ];
                continue;
            }

            $current = $merged[$eventKey];
            $merged[$eventKey] = [
                'event_key' => $eventKey,
                'label' => $issue['label'] ?? $current['label'],
                'message' => $issue['message'] ?? $current['message'],
                'severity' => $this->resolveHigherSecuritySeverity($current['severity'] ?? null, $issue['severity'] ?? null),
                'category' => $issue['category'] ?? $current['category'],
                'metadata' => array_merge(
                    is_array($current['metadata'] ?? null) ? $current['metadata'] : [],
                    is_array($issue['metadata'] ?? null) ? $issue['metadata'] : []
                ),
            ];
        }

        uasort($merged, function (array $left, array $right): int {
            return AttendanceSecurityEvent::severityRank($right['severity'] ?? null)
                <=> AttendanceSecurityEvent::severityRank($left['severity'] ?? null);
        });

        return array_values($merged);
    }

    private function resolvePrimarySecurityIssue(array $issues): ?array
    {
        if ($issues === []) {
            return null;
        }

        usort($issues, fn(array $left, array $right): int => AttendanceSecurityEvent::severityRank($right['severity'] ?? null)
            <=> AttendanceSecurityEvent::severityRank($left['severity'] ?? null));

        return $issues[0] ?? null;
    }

    private function resolveHigherSecuritySeverity(?string $left, ?string $right): string
    {
        return AttendanceSecurityEvent::severityRank($right) > AttendanceSecurityEvent::severityRank($left)
            ? strtolower(trim((string) $right))
            : strtolower(trim((string) ($left ?: 'medium')));
    }

    private function resolveHigherSecurityStatus(?string $left, ?string $right): string
    {
        $order = [
            'allowed' => 1,
            'flagged' => 2,
            'blocked' => 3,
        ];

        $leftNormalized = strtolower(trim((string) ($left ?: 'allowed')));
        $rightNormalized = strtolower(trim((string) ($right ?: 'allowed')));

        return ($order[$rightNormalized] ?? 0) > ($order[$leftNormalized] ?? 0)
            ? $rightNormalized
            : $leftNormalized;
    }

    private function resolveStudentSecurityClassContext(User $user): array
    {
        $classRow = DB::table('kelas_siswa')
            ->join('kelas', 'kelas.id', '=', 'kelas_siswa.kelas_id')
            ->leftJoin('tingkat', 'tingkat.id', '=', 'kelas.tingkat_id')
            ->where('kelas_siswa.siswa_id', $user->id)
            ->where('kelas_siswa.status', 'aktif')
            ->orderByDesc('kelas_siswa.tahun_ajaran_id')
            ->orderByDesc('kelas.id')
            ->select([
                'kelas.id as kelas_id',
                'kelas.nama_kelas',
                'kelas.jurusan',
                'tingkat.nama as tingkat_nama',
            ])
            ->first();

        if (!$classRow) {
            return [
                'kelas_id' => null,
                'kelas_label' => null,
            ];
        }

        $parts = array_values(array_filter([
            $classRow->tingkat_nama ?? null,
            $classRow->jurusan ?? null,
            $classRow->nama_kelas ?? null,
        ], static fn($value): bool => is_string($value) && trim($value) !== ''));

        return [
            'kelas_id' => isset($classRow->kelas_id) ? (int) $classRow->kelas_id : null,
            'kelas_label' => $parts !== [] ? implode(' ', $parts) : ($classRow->nama_kelas ?? null),
        ];
    }

    private function normalizeJsonIdArray($value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_map('intval', array_filter($value, fn($item) => is_numeric($item)))));
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $this->normalizeJsonIdArray($decoded);
            }
        }

        return [];
    }

    private function diffAuditState(array $old, array $new): array
    {
        $diff = [];
        foreach ($new as $key => $newValue) {
            $oldValue = $old[$key] ?? null;
            if ($oldValue !== $newValue) {
                $diff[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $diff;
    }

    private function resolveLocationIdFromValidation(array $locationValidation): ?int
    {
        $details = $locationValidation['details'] ?? null;
        if (!is_array($details)) {
            return null;
        }

        if (isset($details['location_id']) && is_numeric($details['location_id'])) {
            return (int) $details['location_id'];
        }

        $location = $details['location'] ?? null;
        if (is_array($location) && isset($location['id']) && is_numeric($location['id'])) {
            return (int) $location['id'];
        }

        if (is_object($location) && isset($location->id) && is_numeric($location->id)) {
            return (int) $location->id;
        }

        return null;
    }

    private function buildAttendanceSettingsSnapshot(
        ?AttendanceSchema $effectiveSchema,
        array $workingHours,
        array $validation
    ): ?string {
        $snapshot = [
            'schema' => [
                'id' => $effectiveSchema?->id,
                'name' => $effectiveSchema?->schema_name,
                'type' => $effectiveSchema?->schema_type,
                'version' => $effectiveSchema?->version,
            ],
            'working_hours' => [
                'jam_masuk' => $workingHours['jam_masuk'] ?? null,
                'jam_pulang' => $workingHours['jam_pulang'] ?? null,
                'toleransi' => $workingHours['toleransi'] ?? null,
                'minimal_open_time' => $workingHours['minimal_open_time'] ?? null,
                'wajib_gps' => $workingHours['wajib_gps'] ?? null,
                'wajib_foto' => $workingHours['wajib_foto'] ?? null,
                'face_verification_enabled' => $workingHours['face_verification_enabled'] ?? null,
                'hari_kerja' => $workingHours['hari_kerja'] ?? null,
                'source' => $workingHours['source'] ?? null,
            ],
            'attendance_window' => $validation['window'] ?? null,
            'captured_at' => now()->toDateTimeString(),
        ];

        $encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? null : $encoded;
    }

    private function normalizeDeviceInfoPayload($deviceInfoRaw, $deviceIdRaw = null): ?string
    {
        $payload = [];

        if (is_array($deviceInfoRaw)) {
            $payload = $deviceInfoRaw;
        } elseif (is_string($deviceInfoRaw) && trim($deviceInfoRaw) !== '') {
            $decoded = json_decode($deviceInfoRaw, true);
            $payload = is_array($decoded)
                ? $decoded
                : ['raw' => trim($deviceInfoRaw)];
        }

        if (is_string($deviceIdRaw) && trim($deviceIdRaw) !== '') {
            $payload['device_id'] = trim($deviceIdRaw);
        }

        if (empty($payload)) {
            $payload = ['source' => 'attendance_submit'];
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $encoded === false ? null : $encoded;
    }

    private function appendDeviceInfoFlag(?string $payload, array $extra): ?string
    {
        $base = [];
        if (is_string($payload) && trim($payload) !== '') {
            $decoded = json_decode($payload, true);
            if (is_array($decoded)) {
                $base = $decoded;
            }
        }

        $merged = array_merge($base, $extra);
        $encoded = json_encode($merged, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? $payload : $encoded;
    }

    private function normalizeJsonColumnPayload($value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            return trim($value) !== '' ? $value : null;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }

    private function resolveAttendancePhotoPath(Request $request, string $type, int $userId): ?string
    {
        if ($request->hasFile('foto_file')) {
            return $this->saveUploadedAttendancePhoto($request->file('foto_file'), $type, $userId);
        }

        $base64Image = $request->input('foto');
        if (is_string($base64Image) && trim($base64Image) !== '') {
            return $this->saveAttendancePhoto($base64Image, $type, $userId);
        }

        return null;
    }

    private function saveUploadedAttendancePhoto(
        \Illuminate\Http\UploadedFile $uploadedFile,
        string $type,
        int $userId
    ): string {
        $extension = strtolower((string) ($uploadedFile->extension() ?: 'jpg'));
        $filename = 'absensi_' . $userId . '_' . $type . '_' . time() . '.' . $extension;
        Storage::disk('public')->putFileAs('absensi', $uploadedFile, $filename);

        return 'absensi/' . $filename;
    }

    private function saveAttendancePhoto(?string $base64Image, string $type, int $userId): ?string
    {
        if (!$base64Image) {
            return null;
        }

        if (str_contains($base64Image, ';base64,')) {
            $imageParts = explode(';base64,', $base64Image, 2);
            $base64Body = $imageParts[1] ?? '';
        } else {
            $base64Body = $base64Image;
        }

        $decodedImage = base64_decode($base64Body, true);
        if ($decodedImage === false) {
            throw new \InvalidArgumentException('Format foto base64 tidak valid');
        }

        $filename = 'absensi_' . $userId . '_' . $type . '_' . time() . '.jpg';
        Storage::disk('public')->put('absensi/' . $filename, $decodedImage);

        return 'absensi/' . $filename;
    }
}
