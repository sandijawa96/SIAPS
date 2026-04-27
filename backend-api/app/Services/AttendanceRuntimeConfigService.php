<?php

namespace App\Services;

use App\Models\AttendanceSchema;
use Illuminate\Support\Facades\Schema;

class AttendanceRuntimeConfigService
{
    private const FACE_RESULT_ALLOWED = ['verified', 'rejected', 'manual_review'];

    /**
     * @return array{enabled:bool,run_time:string,source:string}
     */
    public function getAutoAlphaConfig(): array
    {
        return $this->resolveDailyAutomationConfig(
            'auto_alpha',
            'auto_alpha_enabled',
            'auto_alpha_run_time',
            true,
            (string) config('attendance.auto_alpha.run_time', '23:50')
        );
    }

    /**
     * @return array{enabled:bool,run_time:string,source:string}
     */
    public function getDisciplineAlertsConfig(): array
    {
        return $this->resolveDailyAutomationConfig(
            'discipline_alerts',
            'discipline_alerts_enabled',
            'discipline_alerts_run_time',
            true,
            (string) config('attendance.discipline_alerts.run_time', '23:57')
        );
    }

    /**
     * @return array{enabled:bool,retention_days:int,cleanup_time:string,current_store_rebuild_time:string,read_current_store_enabled:bool,min_distance_meters:int,persist_idle_seconds:int,source:string}
     */
    public function getLiveTrackingConfig(): array
    {
        $enabled = (bool) config('attendance.live_tracking.enabled', true);
        $retentionDays = max(1, (int) config('attendance.live_tracking.retention_days', 30));
        $cleanupTime = $this->normalizeRunTime(
            (string) config('attendance.live_tracking.cleanup_time', '02:15'),
            '02:15'
        );
        $currentStoreRebuildTime = $this->normalizeRunTime(
            (string) config('attendance.live_tracking.current_store_rebuild_time', '00:10'),
            '00:10'
        );
        $readCurrentStoreEnabled = (bool) config('attendance.live_tracking.read_current_store_enabled', true);
        $minDistanceMeters = max(1, (int) config('attendance.live_tracking.min_distance_meters', 20));
        $persistIdleSeconds = max(60, (int) config('attendance.live_tracking.persist_idle_seconds', 300));
        $source = 'config';

        try {
            if (!Schema::hasTable('attendance_settings')) {
                return [
                    'enabled' => $enabled,
                    'retention_days' => $retentionDays,
                    'cleanup_time' => $cleanupTime,
                    'current_store_rebuild_time' => $currentStoreRebuildTime,
                    'read_current_store_enabled' => $readCurrentStoreEnabled,
                    'min_distance_meters' => $minDistanceMeters,
                    'persist_idle_seconds' => $persistIdleSeconds,
                    'source' => $source,
                ];
            }

            $schema = $this->resolveGlobalSchema();
            if (!$schema instanceof AttendanceSchema) {
                return [
                    'enabled' => $enabled,
                    'retention_days' => $retentionDays,
                    'cleanup_time' => $cleanupTime,
                    'current_store_rebuild_time' => $currentStoreRebuildTime,
                    'read_current_store_enabled' => $readCurrentStoreEnabled,
                    'min_distance_meters' => $minDistanceMeters,
                    'persist_idle_seconds' => $persistIdleSeconds,
                    'source' => $source,
                ];
            }

            if (Schema::hasColumn('attendance_settings', 'live_tracking_enabled') && $schema->live_tracking_enabled !== null) {
                $enabled = (bool) $schema->live_tracking_enabled;
                $source = 'attendance_settings';
            }

            if (Schema::hasColumn('attendance_settings', 'live_tracking_retention_days') && $schema->live_tracking_retention_days !== null) {
                $retentionDays = max(1, (int) $schema->live_tracking_retention_days);
                $source = 'attendance_settings';
            }

            if (Schema::hasColumn('attendance_settings', 'live_tracking_cleanup_time')) {
                $resolvedCleanupTime = $this->normalizeRunTime((string) ($schema->live_tracking_cleanup_time ?? ''), $cleanupTime);
                if ($resolvedCleanupTime !== $cleanupTime || $source === 'attendance_settings') {
                    $cleanupTime = $resolvedCleanupTime;
                    $source = 'attendance_settings';
                }
            }

            if (Schema::hasColumn('attendance_settings', 'live_tracking_min_distance_meters') && $schema->live_tracking_min_distance_meters !== null) {
                $minDistanceMeters = max(1, (int) $schema->live_tracking_min_distance_meters);
                $source = 'attendance_settings';
            }
        } catch (\Throwable $exception) {
            return [
                'enabled' => $enabled,
                'retention_days' => $retentionDays,
                'cleanup_time' => $cleanupTime,
                'current_store_rebuild_time' => $currentStoreRebuildTime,
                'read_current_store_enabled' => $readCurrentStoreEnabled,
                'min_distance_meters' => $minDistanceMeters,
                'persist_idle_seconds' => $persistIdleSeconds,
                'source' => 'config_fallback',
            ];
        }

        return [
            'enabled' => $enabled,
            'retention_days' => $retentionDays,
            'cleanup_time' => $cleanupTime,
            'current_store_rebuild_time' => $currentStoreRebuildTime,
            'read_current_store_enabled' => $readCurrentStoreEnabled,
            'min_distance_meters' => $minDistanceMeters,
            'persist_idle_seconds' => $persistIdleSeconds,
            'source' => $source,
        ];
    }

    /**
     * @return array{enabled:bool,template_required:bool,result_when_template_missing:string,reject_to_manual_review:bool,skip_when_photo_missing:bool,source:string}
     */
    public function getFaceVerificationPolicyConfig(): array
    {
        $enabled = (bool) config('attendance.face.enabled', true);
        $templateRequired = (bool) config('attendance.face.template_required', true);
        $resultWhenTemplateMissing = $this->normalizeFaceResult(
            (string) config('attendance.face.result_when_template_missing', 'verified'),
            'verified'
        );
        $rejectToManualReview = (bool) config('attendance.face.reject_to_manual_review', true);
        $skipWhenPhotoMissing = (bool) config('attendance.face.skip_when_photo_missing', true);
        $source = 'config';

        try {
            if (!Schema::hasTable('attendance_settings')) {
                return [
                    'enabled' => $enabled,
                    'template_required' => $templateRequired,
                    'result_when_template_missing' => $resultWhenTemplateMissing,
                    'reject_to_manual_review' => $rejectToManualReview,
                    'skip_when_photo_missing' => $skipWhenPhotoMissing,
                    'source' => $source,
                ];
            }

            $schema = $this->resolveGlobalSchema();
            if (!$schema instanceof AttendanceSchema) {
                return [
                    'enabled' => $enabled,
                    'template_required' => $templateRequired,
                    'result_when_template_missing' => $resultWhenTemplateMissing,
                    'reject_to_manual_review' => $rejectToManualReview,
                    'skip_when_photo_missing' => $skipWhenPhotoMissing,
                    'source' => $source,
                ];
            }

            if (Schema::hasColumn('attendance_settings', 'face_verification_enabled') && $schema->face_verification_enabled !== null) {
                $enabled = (bool) $schema->face_verification_enabled;
                $source = 'attendance_settings';
            }

            if (Schema::hasColumn('attendance_settings', 'face_template_required') && $schema->face_template_required !== null) {
                $templateRequired = (bool) $schema->face_template_required;
                $source = 'attendance_settings';
            }

            if (Schema::hasColumn('attendance_settings', 'face_result_when_template_missing') && $schema->face_result_when_template_missing !== null) {
                $resultWhenTemplateMissing = $this->normalizeFaceResult((string) $schema->face_result_when_template_missing, $resultWhenTemplateMissing);
                $source = 'attendance_settings';
            }

            if (Schema::hasColumn('attendance_settings', 'face_reject_to_manual_review') && $schema->face_reject_to_manual_review !== null) {
                $rejectToManualReview = (bool) $schema->face_reject_to_manual_review;
                $source = 'attendance_settings';
            }

            if (Schema::hasColumn('attendance_settings', 'face_skip_when_photo_missing') && $schema->face_skip_when_photo_missing !== null) {
                $skipWhenPhotoMissing = (bool) $schema->face_skip_when_photo_missing;
                $source = 'attendance_settings';
            }
        } catch (\Throwable $exception) {
            return [
                'enabled' => $enabled,
                'template_required' => $templateRequired,
                'result_when_template_missing' => $resultWhenTemplateMissing,
                'reject_to_manual_review' => $rejectToManualReview,
                'skip_when_photo_missing' => $skipWhenPhotoMissing,
                'source' => 'config_fallback',
            ];
        }

        return [
            'enabled' => $enabled,
            'template_required' => $templateRequired,
            'result_when_template_missing' => $resultWhenTemplateMissing,
            'reject_to_manual_review' => $rejectToManualReview,
            'skip_when_photo_missing' => $skipWhenPhotoMissing,
            'source' => $source,
        ];
    }

    /**
     * @return array{enabled:bool,run_time:string,source:string}
     */
    private function resolveDailyAutomationConfig(
        string $configKey,
        string $enabledColumn,
        string $timeColumn,
        bool $defaultEnabled,
        string $defaultRunTime
    ): array {
        $enabled = (bool) data_get(config("attendance.{$configKey}", []), 'enabled', $defaultEnabled);
        $runTime = $this->normalizeRunTime(
            (string) data_get(config("attendance.{$configKey}", []), 'run_time', $defaultRunTime),
            $defaultRunTime
        );
        $source = 'config';

        try {
            if (!Schema::hasTable('attendance_settings')) {
                return [
                    'enabled' => $enabled,
                    'run_time' => $runTime,
                    'source' => $source,
                ];
            }

            $schema = $this->resolveGlobalSchema();
            if (!$schema instanceof AttendanceSchema) {
                return [
                    'enabled' => $enabled,
                    'run_time' => $runTime,
                    'source' => $source,
                ];
            }

            if (Schema::hasColumn('attendance_settings', $enabledColumn) && $schema->{$enabledColumn} !== null) {
                $enabled = (bool) $schema->{$enabledColumn};
                $source = 'attendance_settings';
            }

            if (Schema::hasColumn('attendance_settings', $timeColumn)) {
                $resolvedRunTime = $this->normalizeRunTime((string) ($schema->{$timeColumn} ?? ''), $runTime);
                if ($resolvedRunTime !== $runTime || $source === 'attendance_settings') {
                    $runTime = $resolvedRunTime;
                    $source = 'attendance_settings';
                }
            }
        } catch (\Throwable $exception) {
            return [
                'enabled' => $enabled,
                'run_time' => $runTime,
                'source' => 'config_fallback',
            ];
        }

        return [
            'enabled' => $enabled,
            'run_time' => $runTime,
            'source' => $source,
        ];
    }

    private function resolveGlobalSchema(): ?AttendanceSchema
    {
        $schema = AttendanceSchema::query()
            ->where('schema_type', 'global')
            ->where('is_default', true)
            ->orderByDesc('updated_at')
            ->first();

        if ($schema instanceof AttendanceSchema) {
            return $schema;
        }

        return AttendanceSchema::query()
            ->where('schema_type', 'global')
            ->orderByDesc('updated_at')
            ->first();
    }

    private function normalizeRunTime(?string $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return preg_match('/^\d{2}:\d{2}$/', $normalized) === 1
            ? $normalized
            : $fallback;
    }

    private function normalizeFaceResult(?string $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return in_array($normalized, self::FACE_RESULT_ALLOWED, true)
            ? $normalized
            : $fallback;
    }
}
