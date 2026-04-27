<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\AttendanceFraudAssessment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AttendanceFraudDetectionService
{
    public function assessSubmission(?User $user, Request $request, array $context = []): AttendanceFraudAssessment
    {
        $normalized = $this->normalizePayload($request, $context);
        $flags = $this->evaluateFlags($user, $normalized, $context);
        $decision = $this->resolveDecision($flags, $context);

        $classContext = $this->resolveClassContext($user, $context);
        $extraMetadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $assessmentPayload = [
            'user_id' => $user?->id,
            'attendance_id' => $context['attendance_id'] ?? null,
            'kelas_id' => $classContext['kelas_id'],
            'assessment_date' => now()->toDateString(),
            'source' => (string) ($context['source'] ?? 'attendance_submit'),
            'attempt_type' => $context['attempt_type'] ?? $request->input('jenis_absensi'),
            'rollout_mode' => $decision['rollout_mode'],
            'validation_status' => $decision['validation_status'],
            'risk_level' => $decision['risk_level'],
            'risk_score' => $decision['risk_score'],
            'fraud_flags_count' => count($flags),
            'decision_code' => $decision['decision_code'],
            'decision_reason' => $decision['decision_reason'],
            'recommended_action' => $decision['recommended_action'],
            'is_blocking' => $decision['is_blocking'],
            'latitude' => $normalized['latitude'],
            'longitude' => $normalized['longitude'],
            'accuracy' => $normalized['accuracy'],
            'distance_meters' => $normalized['distance_meters'],
            'device_id' => $normalized['device_id'],
            'device_fingerprint' => $normalized['device_fingerprint'],
            'ip_address' => $normalized['ip_address'],
            'request_nonce' => $normalized['request_nonce'],
            'request_signature' => $normalized['request_signature'],
            'request_timestamp' => $normalized['request_timestamp']?->toDateTimeString(),
            'client_timestamp' => $normalized['client_timestamp']?->toDateTimeString(),
            'raw_payload' => (bool) config('attendance.security.store_raw_payload', true)
                ? $normalized['raw_payload']
                : null,
            'normalized_payload' => $normalized['normalized_payload'],
            'metadata' => array_filter(array_merge([
                'student_name_snapshot' => $classContext['student_name'],
                'student_identifier_snapshot' => $classContext['student_identifier'],
                'kelas_label_snapshot' => $classContext['kelas_label'],
                'client_platform' => $normalized['client_platform'],
                'client_app' => $normalized['client_app'],
                'package_name' => $normalized['package_name'],
                'installer_source' => $normalized['installer_source'],
                'network_type' => $normalized['network_type'],
                'wifi_ssid' => $normalized['wifi_ssid'],
                'wifi_bssid' => $normalized['wifi_bssid'],
                'anti_fraud_payload' => $normalized['anti_fraud_payload'],
                'device_info' => $normalized['device_info'],
                'signal_keys' => array_map(static fn(array $flag): string => (string) $flag['flag_key'], $flags),
            ], $extraMetadata), static fn($value): bool => $value !== null && $value !== '' && $value !== []),
        ];

        /** @var AttendanceFraudAssessment $assessment */
        $assessment = DB::transaction(function () use ($assessmentPayload, $flags, $normalized, $user) {
            $assessment = $this->findDailyAssessmentForUpdate($assessmentPayload);

            if ($assessment instanceof AttendanceFraudAssessment) {
                $mergedFlags = $this->mergeFraudFlags($assessment, $flags);
                $assessment->fill($this->mergeFraudAssessmentPayload($assessment, $assessmentPayload, $mergedFlags));
                $assessment->save();
                $assessment->flags()->delete();
                $flagsToPersist = $mergedFlags;
            } else {
                $assessmentPayload['metadata'] = $this->prepareInitialAssessmentMetadata(
                    is_array($assessmentPayload['metadata'] ?? null) ? $assessmentPayload['metadata'] : [],
                    $flags
                );
                $assessment = AttendanceFraudAssessment::create($assessmentPayload);
                $flagsToPersist = $flags;
            }

            foreach ($flagsToPersist as $flag) {
                $assessment->flags()->create([
                    'attendance_id' => $assessment->attendance_id,
                    'user_id' => $user?->id,
                    'flag_key' => $flag['flag_key'],
                    'category' => $flag['category'],
                    'severity' => $flag['severity'],
                    'score' => $flag['score'],
                    'blocking_recommended' => $flag['blocking_recommended'],
                    'label' => $flag['label'],
                    'reason' => $flag['reason'],
                    'evidence' => $flag['evidence'],
                ]);
            }

            if (is_string($normalized['request_nonce']) && $normalized['request_nonce'] !== '') {
                Cache::put(
                    $this->nonceCacheKey($normalized['request_nonce'], $user?->id),
                    true,
                    max(1, (int) config('attendance.security.nonce_ttl_seconds', 300))
                );
            }

            return $assessment->load('flags', 'user', 'kelas');
        });

        return $assessment;
    }

    private function findDailyAssessmentForUpdate(array $payload): ?AttendanceFraudAssessment
    {
        $query = AttendanceFraudAssessment::query()
            ->with('flags')
            ->where('user_id', $payload['user_id'] ?? null)
            ->where('assessment_date', $payload['assessment_date'] ?? now()->toDateString())
            ->where('source', (string) ($payload['source'] ?? 'attendance_submit'))
            ->lockForUpdate();

        if (($payload['attempt_type'] ?? null) !== null) {
            $query->where('attempt_type', $payload['attempt_type']);
        } else {
            $query->whereNull('attempt_type');
        }

        if (($payload['kelas_id'] ?? null) !== null) {
            $query->where('kelas_id', $payload['kelas_id']);
        } else {
            $query->whereNull('kelas_id');
        }

        return $query->first();
    }

    private function prepareInitialAssessmentMetadata(array $metadata, array $flags): array
    {
        $nowIso = now()->toIso8601String();

        return array_filter(array_merge($metadata, [
            'signal_keys' => $this->extractFlagKeys($flags),
            'occurrence_count' => 1,
            'first_seen_at' => $nowIso,
            'last_seen_at' => $nowIso,
        ]), static fn($value): bool => $value !== null && $value !== '');
    }

    private function mergeFraudAssessmentPayload(
        AttendanceFraudAssessment $existing,
        array $incoming,
        array $mergedFlags
    ): array {
        $preferred = $this->preferIncomingAssessmentPayload($existing, $incoming);

        return [
            'attendance_id' => $incoming['attendance_id'] ?? $existing->attendance_id,
            'kelas_id' => $incoming['kelas_id'] ?? $existing->kelas_id,
            'assessment_date' => $existing->assessment_date?->toDateString() ?? ($incoming['assessment_date'] ?? now()->toDateString()),
            'source' => $incoming['source'] ?? $existing->source,
            'attempt_type' => $incoming['attempt_type'] ?? $existing->attempt_type,
            'rollout_mode' => $incoming['rollout_mode'] ?? $existing->rollout_mode,
            'validation_status' => $this->hasWarningStatus($existing->validation_status)
                || $this->hasWarningStatus($incoming['validation_status'] ?? null)
                ? 'warning'
                : 'valid',
            'risk_level' => 'low',
            'risk_score' => 0,
            'fraud_flags_count' => count($mergedFlags),
            'decision_code' => $preferred['decision_code'],
            'decision_reason' => $preferred['decision_reason'],
            'recommended_action' => $preferred['recommended_action'],
            'is_blocking' => false,
            'latitude' => $incoming['latitude'] ?? $existing->latitude,
            'longitude' => $incoming['longitude'] ?? $existing->longitude,
            'accuracy' => $incoming['accuracy'] ?? $existing->accuracy,
            'distance_meters' => $incoming['distance_meters'] ?? $existing->distance_meters,
            'device_id' => $incoming['device_id'] ?? $existing->device_id,
            'device_fingerprint' => $incoming['device_fingerprint'] ?? $existing->device_fingerprint,
            'ip_address' => $incoming['ip_address'] ?? $existing->ip_address,
            'request_nonce' => $incoming['request_nonce'] ?? $existing->request_nonce,
            'request_signature' => $incoming['request_signature'] ?? $existing->request_signature,
            'request_timestamp' => $incoming['request_timestamp'] ?? $existing->request_timestamp,
            'client_timestamp' => $incoming['client_timestamp'] ?? $existing->client_timestamp,
            'raw_payload' => $incoming['raw_payload'] ?? $existing->raw_payload,
            'normalized_payload' => $incoming['normalized_payload'] ?? $existing->normalized_payload,
            'metadata' => $this->mergeFraudAssessmentMetadata(
                is_array($existing->metadata) ? $existing->metadata : [],
                is_array($incoming['metadata'] ?? null) ? $incoming['metadata'] : [],
                $mergedFlags,
                $existing
            ),
        ];
    }

    private function mergeFraudAssessmentMetadata(
        array $existing,
        array $incoming,
        array $mergedFlags,
        AttendanceFraudAssessment $assessment
    ): array {
        $firstSeenAt = $existing['first_seen_at'] ?? $assessment->created_at?->toIso8601String() ?? now()->toIso8601String();
        $occurrenceCount = max(1, (int) ($existing['occurrence_count'] ?? 1)) + 1;
        $signalKeys = array_values(array_unique(array_filter(array_merge(
            is_array($existing['signal_keys'] ?? null) ? $existing['signal_keys'] : [],
            is_array($incoming['signal_keys'] ?? null) ? $incoming['signal_keys'] : [],
            $this->extractFlagKeys($mergedFlags)
        ))));

        $merged = array_merge($existing, $incoming, [
            'signal_keys' => $signalKeys,
            'occurrence_count' => $occurrenceCount,
            'first_seen_at' => $firstSeenAt,
            'last_seen_at' => now()->toIso8601String(),
        ]);

        return array_filter($merged, static fn($value): bool => $value !== null && $value !== '');
    }

    private function mergeFraudFlags(AttendanceFraudAssessment $assessment, array $incomingFlags): array
    {
        $merged = [];

        foreach ($assessment->flags as $flag) {
            $merged[(string) $flag->flag_key] = [
                'flag_key' => (string) $flag->flag_key,
                'category' => (string) $flag->category,
                'severity' => (string) $flag->severity,
                'score' => (int) $flag->score,
                'blocking_recommended' => (bool) $flag->blocking_recommended,
                'label' => $flag->label,
                'reason' => $flag->reason,
                'evidence' => is_array($flag->evidence) ? $flag->evidence : [],
            ];
        }

        foreach ($incomingFlags as $flag) {
            $flagKey = (string) ($flag['flag_key'] ?? '');
            if ($flagKey === '') {
                continue;
            }

            if (!isset($merged[$flagKey])) {
                $incomingEvidence = is_array($flag['evidence'] ?? null) ? $flag['evidence'] : [];
                $incomingEvidence['occurrence_count'] = max(1, (int) ($incomingEvidence['occurrence_count'] ?? 1));
                $flag['evidence'] = $incomingEvidence;
                $merged[$flagKey] = $flag;
                continue;
            }

            $existing = $merged[$flagKey];
            $existingEvidence = is_array($existing['evidence'] ?? null) ? $existing['evidence'] : [];
            $incomingEvidence = is_array($flag['evidence'] ?? null) ? $flag['evidence'] : [];
            $existingOccurrence = max(1, (int) ($existingEvidence['occurrence_count'] ?? 1));
            $incomingOccurrence = max(1, (int) ($incomingEvidence['occurrence_count'] ?? 1));
            $mergedEvidence = array_merge($existingEvidence, $incomingEvidence);
            $mergedEvidence['occurrence_count'] = $existingOccurrence + $incomingOccurrence;

            $merged[$flagKey] = [
                'flag_key' => $flagKey,
                'category' => $flag['category'] ?? $existing['category'],
                'severity' => $this->worseSeverity($existing['severity'] ?? null, $flag['severity'] ?? null),
                'score' => max((int) ($existing['score'] ?? 0), (int) ($flag['score'] ?? 0)),
                'blocking_recommended' => (bool) ($existing['blocking_recommended'] ?? false) || (bool) ($flag['blocking_recommended'] ?? false),
                'label' => $flag['label'] ?? $existing['label'] ?? null,
                'reason' => $flag['reason'] ?? $existing['reason'] ?? null,
                'evidence' => $mergedEvidence,
            ];
        }

        uasort($merged, static function (array $left, array $right): int {
            return ((int) ($right['score'] ?? 0) <=> (int) ($left['score'] ?? 0))
                ?: (self::severityOrder($right['severity'] ?? null) <=> self::severityOrder($left['severity'] ?? null))
                ?: strcmp((string) ($left['flag_key'] ?? ''), (string) ($right['flag_key'] ?? ''));
        });

        return array_values($merged);
    }

    private function preferIncomingAssessmentPayload(AttendanceFraudAssessment $existing, array $incoming): array
    {
        if (
            $this->hasWarningStatus($incoming['validation_status'] ?? null)
            || !$this->hasWarningStatus($existing->validation_status)
        ) {
            return [
                'decision_code' => $incoming['decision_code'] ?? $existing->decision_code,
                'decision_reason' => $incoming['decision_reason'] ?? $existing->decision_reason,
                'recommended_action' => $incoming['recommended_action'] ?? $existing->recommended_action,
            ];
        }

        return [
            'decision_code' => $existing->decision_code,
            'decision_reason' => $existing->decision_reason,
            'recommended_action' => $existing->recommended_action,
        ];
    }

    private function hasWarningStatus(?string $status): bool
    {
        return $this->normalizeValidationStatus($status) === 'warning';
    }

    private function normalizeValidationStatus(?string $status): string
    {
        return strtolower(trim((string) $status)) === 'valid' ? 'valid' : 'warning';
    }

    private function assessmentDecisionWeight(string $validationStatus, string $riskLevel, int $riskScore): int
    {
        return ($this->validationStatusOrder($validationStatus) * 10_000)
            + ($this->riskLevelOrder($riskLevel) * 1_000)
            + $riskScore;
    }

    private function worseValidationStatus(?string $left, ?string $right): string
    {
        return $this->validationStatusOrder($right) > $this->validationStatusOrder($left)
            ? (string) $right
            : (string) ($left ?: 'valid');
    }

    private function worseRiskLevel(?string $left, ?string $right): string
    {
        return $this->riskLevelOrder($right) > $this->riskLevelOrder($left)
            ? (string) $right
            : (string) ($left ?: 'low');
    }

    private function worseSeverity(?string $left, ?string $right): string
    {
        return self::severityOrder($right) > self::severityOrder($left)
            ? (string) $right
            : (string) ($left ?: 'low');
    }

    private function extractFlagKeys(array $flags): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn(array $flag): ?string => isset($flag['flag_key']) ? (string) $flag['flag_key'] : null,
            $flags
        ))));
    }

    private function validationStatusOrder(?string $status): int
    {
        return match (strtolower(trim((string) $status))) {
            'rejected' => 4,
            'manual_review' => 3,
            'warning' => 2,
            default => 1,
        };
    }

    private function riskLevelOrder(?string $level): int
    {
        return match (strtolower(trim((string) $level))) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            default => 1,
        };
    }

    private static function severityOrder(?string $severity): int
    {
        return match (strtolower(trim((string) $severity))) {
            'critical' => 4,
            'high' => 3,
            'medium' => 2,
            'low' => 1,
            default => 0,
        };
    }

    public function attachAssessmentToAttendance(AttendanceFraudAssessment $assessment, ?Absensi $attendance): void
    {
        if (!$attendance instanceof Absensi) {
            return;
        }

        DB::transaction(function () use ($assessment, $attendance) {
            $assessment->attendance_id = $attendance->id;
            $assessment->save();

            $assessment->flags()->update([
                'attendance_id' => $attendance->id,
            ]);

            $attendance->fill([
                'validation_status' => $this->normalizeValidationStatus($assessment->validation_status),
                'risk_level' => 'low',
                'risk_score' => 0,
                'fraud_flags_count' => (int) $assessment->fraud_flags_count,
                'fraud_decision_reason' => $assessment->decision_reason,
                'fraud_last_assessed_at' => now(),
            ]);
            $attendance->save();
        });
    }

    public function getConfigSummary(): array
    {
        $signals = (array) config('attendance.security.signals', []);
        $enabledSignals = collect($signals)
            ->filter(static fn(array $signal): bool => (bool) ($signal['enabled'] ?? false))
            ->map(function (array $signal, string $key): array {
                return [
                    'key' => $key,
                    'score' => (int) ($signal['score'] ?? 0),
                    'severity' => (string) ($signal['severity'] ?? 'medium'),
                    'block_in_strict' => (bool) ($signal['block_in_strict'] ?? false),
                ];
            })
            ->values()
            ->all();

        return [
            'rollout_mode' => 'warning_mode',
            'warn_user' => (bool) config('attendance.security.warn_user', true),
            'allow_submit_with_security_warnings' => true,
            'warning_only' => true,
            'validation_statuses' => ['valid', 'warning'],
            'sources' => ['attendance_precheck', 'attendance_submit'],
            'signals_enabled' => count($enabledSignals),
            'signals' => $enabledSignals,
        ];
    }

    private function normalizePayload(Request $request, array $context): array
    {
        $antiFraudPayloadRaw = $request->input('anti_fraud_payload');
        $antiFraudPayload = $this->decodeArrayPayload($antiFraudPayloadRaw);
        $deviceInfo = $this->decodeArrayPayload($request->input('device_info'));
        $requestTimestamp = $this->parseTimestamp($request->input('request_timestamp') ?: ($antiFraudPayload['request_timestamp'] ?? null));
        $clientTimestamp = $this->parseTimestamp($antiFraudPayload['client_timestamp'] ?? $antiFraudPayload['captured_at'] ?? null);
        $locationCapturedAt = $this->parseTimestamp($antiFraudPayload['location_captured_at'] ?? $antiFraudPayload['location_timestamp'] ?? null);

        $latitude = $this->toFloat($request->input('latitude'));
        $longitude = $this->toFloat($request->input('longitude'));
        $accuracy = $this->toFloat($request->input('accuracy'));
        $deviceId = trim((string) $request->input('device_id', ''));
        $packageName = trim((string) ($antiFraudPayload['package_name'] ?? $deviceInfo['package_name'] ?? ''));
        $platform = strtolower(trim((string) ($antiFraudPayload['platform'] ?? $deviceInfo['platform'] ?? '')));
        $signatureSha256 = strtolower(trim((string) ($antiFraudPayload['signature_sha256'] ?? $deviceInfo['signature_sha256'] ?? '')));

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
            'distance_meters' => $this->extractDistanceMeters($context),
            'device_id' => $deviceId !== '' ? $deviceId : null,
            'device_fingerprint' => $this->buildDeviceFingerprint($deviceId, $deviceInfo, $packageName),
            'ip_address' => $request->ip(),
            'request_nonce' => trim((string) $request->input('request_nonce', '')) ?: null,
            'request_signature' => trim((string) $request->input('request_signature', '')) ?: null,
            'request_timestamp' => $requestTimestamp,
            'client_timestamp' => $clientTimestamp,
            'location_captured_at' => $locationCapturedAt,
            'anti_fraud_payload' => $antiFraudPayload,
            'device_info' => $deviceInfo,
            'is_mock_location' => (bool) ($request->boolean('is_mocked') || ($antiFraudPayload['is_mock_location'] ?? false)),
            'is_from_mock_provider' => $this->toBool($antiFraudPayload['is_from_mock_provider'] ?? null),
            'developer_options_enabled' => $this->toBool($antiFraudPayload['developer_options_enabled'] ?? $antiFraudPayload['developer_mode_enabled'] ?? null),
            'root_detected' => $this->toBool($antiFraudPayload['root_detected'] ?? null),
            'jailbreak_detected' => $this->toBool($antiFraudPayload['jailbreak_detected'] ?? null),
            'emulator_detected' => $this->toBool($antiFraudPayload['emulator_detected'] ?? null),
            'is_physical_device' => $this->toBool($antiFraudPayload['is_physical_device'] ?? $deviceInfo['is_physical_device'] ?? null),
            'adb_enabled' => $this->toBool($antiFraudPayload['adb_enabled'] ?? null),
            'usb_debugging_enabled' => $this->toBool($antiFraudPayload['usb_debugging_enabled'] ?? null),
            'app_clone_risk' => $this->toBool($antiFraudPayload['app_clone_risk'] ?? $antiFraudPayload['dual_app_detected'] ?? null),
            'tampering_detected' => $this->toBool($antiFraudPayload['tampering_detected'] ?? $antiFraudPayload['app_integrity_failed'] ?? $antiFraudPayload['modified_apk_detected'] ?? null),
            'instrumentation_detected' => $this->toBool($antiFraudPayload['instrumentation_detected'] ?? $antiFraudPayload['frida_detected'] ?? $antiFraudPayload['hooking_detected'] ?? $antiFraudPayload['xposed_detected'] ?? null),
            'signature_mismatch' => $this->toBool($antiFraudPayload['signature_mismatch'] ?? $antiFraudPayload['package_name_mismatch'] ?? null),
            'signature_sha256' => $signatureSha256 !== '' ? $signatureSha256 : null,
            'network_type' => trim((string) ($antiFraudPayload['network_type'] ?? '')) ?: null,
            'wifi_ssid' => trim((string) ($antiFraudPayload['wifi_ssid'] ?? '')) ?: null,
            'wifi_bssid' => trim((string) ($antiFraudPayload['wifi_bssid'] ?? '')) ?: null,
            'suspicious_network' => $this->toBool($antiFraudPayload['suspicious_network'] ?? null),
            'installer_source' => trim((string) ($antiFraudPayload['installer_source'] ?? $deviceInfo['installer_source'] ?? '')) ?: null,
            'package_name' => $packageName !== '' ? $packageName : null,
            'client_platform' => trim((string) $request->header('X-Client-Platform', '')) ?: null,
            'client_app' => trim((string) $request->header('X-Client-App', '')) ?: null,
            'payload_parse_failed' => $antiFraudPayloadRaw !== null && $antiFraudPayload === [],
            'normalized_payload' => array_filter([
                'jenis_absensi' => $request->input('jenis_absensi'),
                'latitude' => $latitude,
                'longitude' => $longitude,
                'accuracy' => $accuracy,
                'device_id' => $deviceId !== '' ? $deviceId : null,
                'package_name' => $packageName !== '' ? $packageName : null,
                'request_nonce' => trim((string) $request->input('request_nonce', '')) ?: null,
                'request_timestamp' => $requestTimestamp?->toIso8601String(),
                'anti_fraud_payload' => $antiFraudPayload,
            ], static fn($value): bool => $value !== null && $value !== ''),
            'raw_payload' => $request->except(['foto', 'foto_file']),
            'platform' => $platform !== '' ? $platform : null,
        ];
    }

    private function evaluateFlags(?User $user, array $normalized, array $context): array
    {
        $flags = [];
        $now = now();
        $source = (string) ($context['source'] ?? 'attendance_submit');
        $gpsAccuracyLimit = $this->toFloat($context['gps_accuracy_limit'] ?? null);
        $locationValidation = is_array($context['location_validation'] ?? null) ? $context['location_validation'] : [];
        $requestTimestamp = $normalized['request_timestamp'];
        $clientTimestamp = $normalized['client_timestamp'];
        $locationCapturedAt = $normalized['location_captured_at'];
        $this->applySecurityIssuesAsFlags($flags, $context);

        if (!empty($context['mobile_only_violation'])) {
            $this->pushFlag($flags, 'mobile_policy_violation', 'client_policy', 'Permintaan absensi berasal dari web/browser, bukan mobile app.', [
                'client_platform' => $normalized['client_platform'],
                'client_app' => $normalized['client_app'],
            ]);
        }

        $deviceGuardCode = strtolower(trim((string) ($context['device_guard']['code'] ?? '')));
        if (in_array($deviceGuardCode, ['device_lock_violation', 'device_id_required'], true)) {
            $this->pushFlag($flags, 'device_spoofing', 'device_integrity', 'Identitas perangkat tidak sesuai dengan binding akun siswa.', [
                'device_guard_code' => $deviceGuardCode,
                'device_id' => $normalized['device_id'],
                'bound_device_id' => $context['device_guard']['data']['bound_device_id'] ?? null,
            ]);
        }

        if ($normalized['is_mock_location']) {
            $this->pushFlag($flags, 'mock_location', 'gps_integrity', 'Mock location atau Fake GPS terdeteksi pada perangkat.', [
                'is_mock_location' => true,
            ]);
        }

        if ($normalized['is_from_mock_provider'] === true) {
            $this->pushFlag($flags, 'mock_provider', 'gps_integrity', 'Lokasi berasal dari mock provider pada sisi client.', [
                'is_from_mock_provider' => true,
            ]);
        }

        if ($normalized['developer_options_enabled'] === true) {
            $this->pushFlag($flags, 'developer_options', 'device_integrity', 'Developer options aktif saat absensi dikirim.', [
                'developer_options_enabled' => true,
            ]);
        }

        if ($gpsAccuracyLimit !== null && $normalized['accuracy'] !== null && $normalized['accuracy'] > $gpsAccuracyLimit) {
            $this->pushFlag($flags, 'gps_accuracy_low', 'gps_integrity', 'Akurasi GPS melebihi batas schema absensi.', [
                'accuracy' => $normalized['accuracy'],
                'required_accuracy' => $gpsAccuracyLimit,
            ]);
        }

        if ($this->isOutsideGeofence($locationValidation, $context)) {
            $this->pushFlag($flags, 'outside_geofence', 'gps_integrity', 'Koordinat absensi berada di luar area yang diizinkan.', [
                'distance_meters' => $normalized['distance_meters'],
                'location_validation' => $locationValidation,
            ]);
        }

        if ($locationCapturedAt instanceof Carbon) {
            $ageSeconds = abs($locationCapturedAt->diffInSeconds($now, false));
            if ($ageSeconds > (int) config('attendance.security.stale_location_seconds', 120)) {
                $this->pushFlag($flags, 'stale_location', 'gps_integrity', 'Timestamp lokasi terlalu lama dibanding waktu submit.', [
                    'location_captured_at' => $locationCapturedAt->toIso8601String(),
                    'age_seconds' => $ageSeconds,
                ]);
            }
        }

        $timeDriftSeconds = $source === 'attendance_submit'
            ? $this->resolveLargestTimeDriftSeconds($now, $requestTimestamp, $clientTimestamp)
            : null;
        if ($timeDriftSeconds !== null && $timeDriftSeconds > (int) config('attendance.security.time_drift_warning_seconds', 90)) {
            $this->pushFlag($flags, 'time_drift', 'request_integrity', 'Waktu perangkat berbeda signifikan dari waktu server.', [
                'time_drift_seconds' => $timeDriftSeconds,
                'request_timestamp' => $requestTimestamp?->toIso8601String(),
                'client_timestamp' => $clientTimestamp?->toIso8601String(),
            ]);
        }

        $isEmulator = $normalized['emulator_detected'] === true
            || $normalized['is_physical_device'] === false;
        if ($isEmulator) {
            $this->pushFlag($flags, 'emulator', 'device_integrity', 'Perangkat terindikasi emulator atau bukan physical device.', [
                'emulator_detected' => $normalized['emulator_detected'],
                'is_physical_device' => $normalized['is_physical_device'],
            ]);
        }

        if ($normalized['root_detected'] === true || $normalized['jailbreak_detected'] === true) {
            $this->pushFlag($flags, 'root_or_jailbreak', 'device_integrity', 'Perangkat terdeteksi root/jailbreak.', [
                'root_detected' => $normalized['root_detected'],
                'jailbreak_detected' => $normalized['jailbreak_detected'],
            ]);
        }

        if ($normalized['adb_enabled'] === true || $normalized['usb_debugging_enabled'] === true) {
            $this->pushFlag($flags, 'adb_or_usb_debugging', 'device_integrity', 'ADB atau USB debugging aktif saat absensi dilakukan.', [
                'adb_enabled' => $normalized['adb_enabled'],
                'usb_debugging_enabled' => $normalized['usb_debugging_enabled'],
            ]);
        }

        if ($normalized['app_clone_risk'] === true) {
            $this->pushFlag($flags, 'app_clone', 'app_integrity', 'Aplikasi terindikasi berjalan dalam mode clone/dual app.', [
                'app_clone_risk' => true,
            ]);
        }

        if ($normalized['tampering_detected'] === true) {
            $this->pushFlag($flags, 'app_tampering', 'app_integrity', 'Integritas aplikasi gagal atau APK termodifikasi.', [
                'tampering_detected' => true,
            ]);
        }

        if ($normalized['instrumentation_detected'] === true) {
            $this->pushFlag($flags, 'instrumentation', 'app_integrity', 'Frida/Xposed/hooking framework terdeteksi.', [
                'instrumentation_detected' => true,
            ]);
        }

        if ($this->hasSignatureMismatch($normalized)) {
            $this->pushFlag($flags, 'signature_mismatch', 'app_integrity', 'Signature aplikasi, package name, atau installer source tidak sesuai policy.', [
                'package_name' => $normalized['package_name'],
                'installer_source' => $normalized['installer_source'],
            ]);
        }

        if ($source === 'attendance_submit' && $this->isReplayRequest($user, $normalized['request_nonce'])) {
            $this->pushFlag($flags, 'request_replay', 'request_integrity', 'Nonce request sudah pernah dipakai sebelumnya.', [
                'request_nonce' => $normalized['request_nonce'],
            ]);
        }

        if ($source === 'attendance_submit' && $this->isDuplicateFrequency($user, $context['attempt_type'] ?? null)) {
            $this->pushFlag($flags, 'duplicate_frequency', 'request_integrity', 'Frekuensi submit absensi terlalu rapat untuk user yang sama.', [
                'attempt_type' => $context['attempt_type'] ?? null,
                'window_seconds' => (int) config('attendance.security.duplicate_submit_window_seconds', 10),
            ]);
        }

        if ($source === 'attendance_submit' && $this->hasForgedMetadataRisk($user, $normalized, $context)) {
            $this->pushFlag($flags, 'forged_metadata', 'request_integrity', 'Metadata client tidak lengkap, gagal diverifikasi, atau tidak konsisten.', [
                'payload_parse_failed' => $normalized['payload_parse_failed'],
                'request_signature_present' => $normalized['request_signature'] !== null,
            ]);
        }

        $impossibleTravel = $source === 'attendance_submit'
            ? $this->detectImpossibleTravel($user, $normalized, $requestTimestamp ?? $now)
            : null;
        if ($impossibleTravel !== null) {
            $this->pushFlag($flags, 'impossible_travel', 'gps_integrity', 'Perubahan lokasi antar attempt terlalu cepat dan tidak realistis.', $impossibleTravel);
        }

        $duplicateCoordinateEvidence = $source === 'attendance_submit'
            ? $this->detectDuplicateCoordinatePattern($user, $normalized)
            : null;
        if ($duplicateCoordinateEvidence !== null) {
            $this->pushFlag($flags, 'duplicate_coordinate_pattern', 'gps_integrity', 'Koordinat yang sama muncul berulang kali pada pola yang tidak wajar.', $duplicateCoordinateEvidence);
        }

        $suspiciousNetworkEvidence = $this->detectSuspiciousNetwork($normalized);
        if ($suspiciousNetworkEvidence !== null) {
            $this->pushFlag($flags, 'suspicious_network', 'network_context', 'Konteks jaringan ditandai mencurigakan atau tidak sesuai daftar tepercaya.', $suspiciousNetworkEvidence);
        }

        return array_values($flags);
    }

    private function resolveDecision(array $flags, array $context = []): array
    {
        $rolloutMode = 'warning_mode';
        $topFlag = collect($flags)->sortByDesc('score')->first();
        $validationStatus = $flags === [] ? 'valid' : 'warning';
        $warningSummary = $this->buildWarningSummary($flags);

        return [
            'rollout_mode' => $rolloutMode,
            'validation_status' => $validationStatus,
            'risk_level' => 'low',
            'risk_score' => 0,
            'decision_code' => $topFlag['flag_key'] ?? null,
            'decision_reason' => $warningSummary ?? ($flags === [] ? 'Tidak ada warning keamanan yang terdeteksi.' : 'Warning keamanan terdeteksi pada absensi ini.'),
            'recommended_action' => $this->buildRecommendedAction($flags, $validationStatus),
            'is_blocking' => false,
        ];
    }

    private function pushFlag(array &$flags, string $flagKey, string $category, string $reason, array $evidence = [], ?bool $blockingRecommended = null): void
    {
        $signalConfig = (array) config("attendance.security.signals.{$flagKey}", []);
        if (!(bool) ($signalConfig['enabled'] ?? false)) {
            return;
        }

        $flags[$flagKey] = [
            'flag_key' => $flagKey,
            'category' => $category,
            'severity' => (string) ($signalConfig['severity'] ?? 'medium'),
            'score' => 0,
            'blocking_recommended' => false,
            'label' => $this->flagLabel($flagKey),
            'reason' => $reason,
            'evidence' => array_filter(
                array_merge($evidence, ['occurrence_count' => max(1, (int) ($evidence['occurrence_count'] ?? 1))]),
                static fn($value): bool => $value !== null && $value !== ''
            ),
        ];
    }

    private function flagLabel(string $flagKey): string
    {
        return match ($flagKey) {
            'mock_location' => 'Mock Location',
            'mock_provider' => 'Mock Provider',
            'developer_options' => 'Developer Options Aktif',
            'gps_accuracy_low' => 'Akurasi GPS Rendah',
            'outside_geofence' => 'Di Luar Geofence',
            'stale_location' => 'Lokasi Stale',
            'time_drift' => 'Perbedaan Waktu Perangkat',
            'emulator' => 'Emulator Terdeteksi',
            'root_or_jailbreak' => 'Root / Jailbreak',
            'adb_or_usb_debugging' => 'ADB / USB Debugging',
            'device_spoofing' => 'Risiko Device Spoofing',
            'app_clone' => 'Clone / Dual App',
            'app_tampering' => 'App Tampering',
            'instrumentation' => 'Instrumentation / Hooking',
            'signature_mismatch' => 'Signature / Package Mismatch',
            'request_replay' => 'Replay Request',
            'duplicate_frequency' => 'Frekuensi Submit Tidak Wajar',
            'forged_metadata' => 'Metadata Tidak Valid',
            'mobile_policy_violation' => 'Bypass Mobile Policy',
            'impossible_travel' => 'Impossible Travel',
            'duplicate_coordinate_pattern' => 'Pola Koordinat Berulang',
            'suspicious_network' => 'Jaringan Mencurigakan',
            default => 'Fraud Flag',
        };
    }

    private function applySecurityIssuesAsFlags(array &$flags, array $context): void
    {
        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];
        $warningContext = is_array($metadata['security_warning_context'] ?? null)
            ? $metadata['security_warning_context']
            : [];
        $issues = $warningContext['issues'] ?? [];

        if (!is_array($issues)) {
            return;
        }

        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }

            $eventKey = strtolower(trim((string) ($issue['event_key'] ?? $issue['key'] ?? '')));
            if ($eventKey === '') {
                continue;
            }

            [$flagKey, $category] = match ($eventKey) {
                'mock_location_detected' => ['mock_location', 'gps_integrity'],
                'mock_provider_detected' => ['mock_provider', 'gps_integrity'],
                'developer_options_enabled' => ['developer_options', 'device_integrity'],
                'gps_accuracy_low' => ['gps_accuracy_low', 'gps_integrity'],
                'outside_geofence' => ['outside_geofence', 'gps_integrity'],
                'stale_location' => ['stale_location', 'gps_integrity'],
                'time_drift' => ['time_drift', 'request_integrity'],
                'root_or_jailbreak_detected' => ['root_or_jailbreak', 'device_integrity'],
                'emulator_detected' => ['emulator', 'device_integrity'],
                'adb_or_usb_debugging_enabled' => ['adb_or_usb_debugging', 'device_integrity'],
                'app_clone_detected' => ['app_clone', 'app_integrity'],
                'app_tampering_detected' => ['app_tampering', 'app_integrity'],
                'instrumentation_detected' => ['instrumentation', 'app_integrity'],
                'signature_mismatch_detected' => ['signature_mismatch', 'app_integrity'],
                'request_replay' => ['request_replay', 'request_integrity'],
                'duplicate_frequency' => ['duplicate_frequency', 'request_integrity'],
                'forged_metadata' => ['forged_metadata', 'request_integrity'],
                'impossible_travel' => ['impossible_travel', 'gps_integrity'],
                'duplicate_coordinate_pattern' => ['duplicate_coordinate_pattern', 'gps_integrity'],
                'suspicious_network' => ['suspicious_network', 'network_context'],
                default => [null, null],
            };

            if ($flagKey === null || $category === null) {
                continue;
            }

            $message = trim((string) ($issue['message'] ?? $issue['label'] ?? $this->flagLabel($flagKey)));
            $evidence = is_array($issue['metadata'] ?? null) ? $issue['metadata'] : [];
            $this->pushFlag($flags, $flagKey, $category, $message, $evidence);
        }
    }

    private function buildWarningSummary(array $flags): ?string
    {
        if ($flags === []) {
            return null;
        }

        $labels = array_values(array_filter(array_map(
            static fn(array $flag): ?string => isset($flag['label']) ? trim((string) $flag['label']) : null,
            $flags
        )));

        if ($labels === []) {
            return 'Warning keamanan terdeteksi pada absensi ini.';
        }

        $preview = implode(', ', array_slice($labels, 0, 3));
        $remaining = count($labels) - min(3, count($labels));

        if ($remaining > 0) {
            return $preview . ' dan ' . $remaining . ' indikator lain.';
        }

        return $preview;
    }

    private function resolveClassContext(?User $user, array $context): array
    {
        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : [];

        return [
            'kelas_id' => $context['kelas_id'] ?? $metadata['kelas_id'] ?? null,
            'kelas_label' => $metadata['kelas_label'] ?? null,
            'student_name' => $user?->nama_lengkap ?: $user?->username ?: null,
            'student_identifier' => $user?->nisn ?: $user?->nis ?: $user?->username ?: null,
        ];
    }

    private function buildRecommendedAction(array $flags, string $validationStatus): string
    {
        $keys = collect($flags)->pluck('flag_key')->all();

        if (in_array('mock_location', $keys, true) || in_array('mock_provider', $keys, true)) {
            return 'Warning dicatat. Klarifikasi Fake GPS/developer options dan cocokkan histori lokasi serta perangkat.';
        }

        if (in_array('device_spoofing', $keys, true) || in_array('emulator', $keys, true)) {
            return 'Warning dicatat. Verifikasi perangkat fisik siswa, binding device, dan histori login.';
        }

        if (in_array('app_tampering', $keys, true) || in_array('instrumentation', $keys, true)) {
            return 'Warning dicatat. Minta reinstall aplikasi resmi dan periksa integritas aplikasi pada perangkat siswa.';
        }

        if ($validationStatus === 'warning') {
            return 'Warning dicatat untuk monitoring admin, wali kelas, dan pihak berwenang tanpa memblokir presensi.';
        }

        return 'Tidak ada warning. Data tetap disimpan untuk monitoring rutin.';
    }

    private function decodeArrayPayload($raw): array
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

    private function parseTimestamp($value): ?Carbon
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function toFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function toBool($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
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

    private function extractDistanceMeters(array $context): ?float
    {
        $details = is_array($context['location_validation']['details'] ?? null)
            ? $context['location_validation']['details']
            : [];

        return $this->toFloat(
            $context['distance_meters']
            ?? $details['distance']
            ?? $details['distance_to_area']
            ?? $details['distance_to_boundary']
            ?? null
        );
    }

    private function buildDeviceFingerprint(string $deviceId, array $deviceInfo, string $packageName): ?string
    {
        $parts = array_values(array_filter([
            $deviceId,
            $packageName,
            $deviceInfo['platform'] ?? null,
            $deviceInfo['brand'] ?? null,
            $deviceInfo['manufacturer'] ?? null,
            $deviceInfo['model'] ?? null,
            $deviceInfo['sdk_int'] ?? null,
            $deviceInfo['system_version'] ?? null,
        ], static fn($value): bool => $value !== null && $value !== ''));

        if ($parts === []) {
            return null;
        }

        return hash('sha256', implode('|', array_map(static fn($value): string => (string) $value, $parts)));
    }

    private function isOutsideGeofence(array $locationValidation, array $context): bool
    {
        if (($context['location_invalid_reason'] ?? null) === 'outside_geofence') {
            return true;
        }

        if (($locationValidation['valid'] ?? true) !== false) {
            return false;
        }

        $details = is_array($locationValidation['details'] ?? null) ? $locationValidation['details'] : [];

        return isset($details['distance'])
            || isset($details['distance_to_area'])
            || isset($details['distance_to_boundary']);
    }

    private function resolveLargestTimeDriftSeconds(Carbon $now, ?Carbon $requestTimestamp, ?Carbon $clientTimestamp): ?int
    {
        $drifts = array_values(array_filter([
            $requestTimestamp?->diffInSeconds($now, false),
            $clientTimestamp?->diffInSeconds($now, false),
        ], static fn($value): bool => $value !== null));

        if ($drifts === []) {
            return null;
        }

        return max(array_map(static fn($value): int => abs((int) $value), $drifts));
    }

    private function hasSignatureMismatch(array $normalized): bool
    {
        if ($normalized['signature_mismatch'] === true) {
            return true;
        }

        $packageName = strtolower(trim((string) ($normalized['package_name'] ?? '')));
        $platform = strtolower(trim((string) ($normalized['platform'] ?? '')));
        $installerSource = strtolower(trim((string) ($normalized['installer_source'] ?? '')));
        $signatureSha256 = strtolower(trim((string) ($normalized['signature_sha256'] ?? '')));
        $appConfig = (array) config('attendance.security.app', []);
        $expectedAndroidPackage = strtolower(trim((string) ($appConfig['expected_android_package'] ?? '')));
        $expectedIosBundle = strtolower(trim((string) ($appConfig['expected_ios_bundle'] ?? '')));
        $expectedAndroidSignatures = array_values(array_filter(array_map(
            static fn($value): string => strtolower(trim((string) $value)),
            (array) ($appConfig['expected_android_signatures'] ?? [])
        )));
        $allowedInstallers = array_map(
            static fn($value): string => strtolower(trim((string) $value)),
            (array) ($appConfig['allowed_installers'] ?? [])
        );

        if ($packageName !== '') {
            if ($platform === 'android' && $expectedAndroidPackage !== '' && $packageName !== $expectedAndroidPackage) {
                return true;
            }

            if ($platform === 'ios' && $expectedIosBundle !== '' && $packageName !== $expectedIosBundle) {
                return true;
            }
        }

        if ($installerSource !== '' && $allowedInstallers !== [] && !in_array($installerSource, $allowedInstallers, true)) {
            return true;
        }

        if (
            $platform === 'android'
            && $signatureSha256 !== ''
            && $expectedAndroidSignatures !== []
            && !in_array($signatureSha256, $expectedAndroidSignatures, true)
        ) {
            return true;
        }

        return false;
    }

    private function isReplayRequest(?User $user, ?string $requestNonce): bool
    {
        if (!is_string($requestNonce) || trim($requestNonce) === '') {
            return false;
        }

        $requestNonce = trim($requestNonce);
        if (Cache::has($this->nonceCacheKey($requestNonce, $user?->id))) {
            return true;
        }

        return AttendanceFraudAssessment::query()
            ->when($user?->id, static fn($query, $userId) => $query->where('user_id', $userId))
            ->where('request_nonce', $requestNonce)
            ->exists();
    }

    private function hasForgedMetadataRisk(?User $user, array $normalized, array $context): bool
    {
        if ($normalized['payload_parse_failed']) {
            return true;
        }

        $requestSigning = (array) config('attendance.security.request_signing', []);
        if ((bool) ($requestSigning['enabled'] ?? false)) {
            $signature = $normalized['request_signature'];
            $requestNonce = $normalized['request_nonce'];
            $requestTimestamp = $normalized['request_timestamp'];
            $signingKey = (string) ($requestSigning['key'] ?? '');

            if ($signature === null || $requestNonce === null || !$requestTimestamp instanceof Carbon || $signingKey === '') {
                return true;
            }

            $payload = implode('|', [
                $requestNonce,
                $requestTimestamp->toIso8601String(),
                (string) ($user?->id ?? 0),
                (string) ($context['attempt_type'] ?? ''),
                (string) ($normalized['latitude'] ?? ''),
                (string) ($normalized['longitude'] ?? ''),
                (string) ($normalized['device_id'] ?? ''),
            ]);
            $expectedSignature = hash_hmac('sha256', $payload, $signingKey);
            if (!hash_equals($expectedSignature, $signature)) {
                return true;
            }
        }

        $deviceInfo = is_array($normalized['device_info']) ? $normalized['device_info'] : [];
        $requiredFields = [
            $normalized['device_id'] ?? null,
            $deviceInfo['platform'] ?? null,
            $deviceInfo['app_version'] ?? null,
        ];

        return collect($requiredFields)->filter(static fn($value): bool => is_string($value) && trim($value) !== '')->count() < 2;
    }

    private function isDuplicateFrequency(?User $user, ?string $attemptType): bool
    {
        if (!$user instanceof User) {
            return false;
        }

        $windowSeconds = max(1, (int) config('attendance.security.duplicate_submit_window_seconds', 10));

        return AttendanceFraudAssessment::query()
            ->where('user_id', $user->id)
            ->where('source', 'attendance_submit')
            ->when($attemptType, static fn($query, $type) => $query->where('attempt_type', $type))
            ->where('created_at', '>=', now()->subSeconds($windowSeconds))
            ->exists();
    }

    private function detectImpossibleTravel(?User $user, array $normalized, Carbon $referenceTime): ?array
    {
        if (!$user instanceof User || $normalized['latitude'] === null || $normalized['longitude'] === null) {
            return null;
        }

        $previous = AttendanceFraudAssessment::query()
            ->where('user_id', $user->id)
            ->where('source', 'attendance_submit')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderByDesc('created_at')
            ->first();

        if (!$previous instanceof AttendanceFraudAssessment) {
            return null;
        }

        $secondsDiff = max(1, $previous->created_at?->diffInSeconds($referenceTime, false) ?? 0);
        if ($secondsDiff <= 0) {
            return null;
        }

        $distanceMeters = $this->haversineDistance(
            (float) $previous->latitude,
            (float) $previous->longitude,
            (float) $normalized['latitude'],
            (float) $normalized['longitude']
        );
        $speedKmh = ($distanceMeters / $secondsDiff) * 3.6;

        if ($speedKmh <= (float) config('attendance.security.impossible_travel_speed_kmh', 300)) {
            return null;
        }

        return [
            'previous_assessment_id' => (int) $previous->id,
            'previous_created_at' => $previous->created_at?->toIso8601String(),
            'distance_meters' => round($distanceMeters, 2),
            'seconds_diff' => $secondsDiff,
            'speed_kmh' => round($speedKmh, 2),
        ];
    }

    private function detectDuplicateCoordinatePattern(?User $user, array $normalized): ?array
    {
        if (!$user instanceof User || $normalized['latitude'] === null || $normalized['longitude'] === null) {
            return null;
        }

        $latitude = round((float) $normalized['latitude'], 5);
        $longitude = round((float) $normalized['longitude'], 5);
        $tolerance = 0.00001;
        $windowDays = max(1, (int) config('attendance.security.duplicate_coordinate_window_days', 7));
        $limit = max(1, (int) config('attendance.security.duplicate_coordinate_limit', 5));

        $count = AttendanceFraudAssessment::query()
            ->where('user_id', $user->id)
            ->where('source', 'attendance_submit')
            ->whereBetween('created_at', [now()->subDays($windowDays), now()])
            ->whereBetween('latitude', [$latitude - $tolerance, $latitude + $tolerance])
            ->whereBetween('longitude', [$longitude - $tolerance, $longitude + $tolerance])
            ->count();

        if ($count < $limit) {
            return null;
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'matching_attempts' => $count,
            'window_days' => $windowDays,
        ];
    }

    private function detectSuspiciousNetwork(array $normalized): ?array
    {
        $trustedSsids = array_map(
            static fn($value): string => strtolower(trim((string) $value)),
            (array) config('attendance.security.trusted_wifi_ssids', [])
        );
        $trustedBssids = array_map(
            static fn($value): string => strtolower(trim((string) $value)),
            (array) config('attendance.security.trusted_wifi_bssids', [])
        );

        $networkType = strtolower(trim((string) ($normalized['network_type'] ?? '')));
        $wifiSsid = strtolower(trim((string) ($normalized['wifi_ssid'] ?? '')));
        $wifiBssid = strtolower(trim((string) ($normalized['wifi_bssid'] ?? '')));

        if ($normalized['suspicious_network'] === true) {
            return [
                'network_type' => $networkType !== '' ? $networkType : null,
                'wifi_ssid' => $wifiSsid !== '' ? $wifiSsid : null,
                'wifi_bssid' => $wifiBssid !== '' ? $wifiBssid : null,
                'client_flagged' => true,
            ];
        }

        if ($networkType === 'wifi' && ($trustedSsids !== [] || $trustedBssids !== [])) {
            $ssidMismatch = $wifiSsid !== '' && $trustedSsids !== [] && !in_array($wifiSsid, $trustedSsids, true);
            $bssidMismatch = $wifiBssid !== '' && $trustedBssids !== [] && !in_array($wifiBssid, $trustedBssids, true);
            if ($ssidMismatch || $bssidMismatch) {
                return [
                    'network_type' => 'wifi',
                    'wifi_ssid' => $wifiSsid !== '' ? $wifiSsid : null,
                    'wifi_bssid' => $wifiBssid !== '' ? $wifiBssid : null,
                    'trusted_wifi_configured' => true,
                ];
            }
        }

        return null;
    }

    private function nonceCacheKey(string $requestNonce, ?int $userId): string
    {
        return 'attendance:fraud:nonce:' . ($userId ?: 0) . ':' . $requestNonce;
    }

    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
