<?php

namespace App\Services;

use App\Models\AttendanceSchema;
use App\Models\TahunAjaran;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class AttendanceTimeService
{
    private const RUNTIME_CACHE_VERSION_KEY = 'attendance_runtime_version';
    private static ?bool $kalenderHasTahunAjaranColumn = null;
    private AttendanceSchemaService $attendanceSchemaService;
    private AttendanceRuntimeConfigService $attendanceRuntimeConfigService;

    public function __construct(
        AttendanceSchemaService $attendanceSchemaService,
        AttendanceRuntimeConfigService $attendanceRuntimeConfigService
    )
    {
        $this->attendanceSchemaService = $attendanceSchemaService;
        $this->attendanceRuntimeConfigService = $attendanceRuntimeConfigService;
    }

    /**
     * Get working hours with proper hierarchy:
     * 1. user_attendance_overrides
     * 2. effective attendance schema
     * 3. global attendance schema fallback
     * 4. hardcoded safe defaults
     */
    public function getWorkingHours(User $user): array
    {
        $version = (int) Cache::get(self::RUNTIME_CACHE_VERSION_KEY, 1);
        $cacheKey = "working_hours_user_{$user->id}_v{$version}";

        return Cache::remember($cacheKey, 3600, function () use ($user) {
            $schemaSettings = $this->getSettingsFromEffectiveSchema($user);
            $fallbackSettings = $schemaSettings
                ?? $this->getSettingsFromGlobalSchemaFallback($user)
                ?? $this->getHardcodedFallbackSettings($user);

            // 1. Check user-specific overrides first (highest priority)
            $userOverride = DB::table('user_attendance_overrides')
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if ($userOverride) {
                return $this->normalizeFacePolicyByRequirements([
                    'jam_masuk' => $userOverride->jam_masuk ?: ($fallbackSettings['jam_masuk'] ?? '07:00'),
                    'jam_pulang' => $userOverride->jam_pulang ?: ($fallbackSettings['jam_pulang'] ?? '15:00'),
                    'toleransi' => $userOverride->toleransi ?: ($fallbackSettings['toleransi'] ?? 15),
                    'minimal_open_time' => (int) ($fallbackSettings['minimal_open_time'] ?? 70),
                    'wajib_gps' => $userOverride->wajib_gps !== null ? $userOverride->wajib_gps : ($fallbackSettings['wajib_gps'] ?? true),
                    'wajib_foto' => $userOverride->wajib_foto !== null ? $userOverride->wajib_foto : ($fallbackSettings['wajib_foto'] ?? true),
                    'face_verification_enabled' => (bool) ($fallbackSettings['face_verification_enabled'] ?? $this->getDefaultFaceVerificationEnabled()),
                    'face_template_required' => (bool) ($fallbackSettings['face_template_required'] ?? $this->getDefaultFaceTemplateRequired()),
                    'hari_kerja' => $this->normalizeWorkingDays(
                        $userOverride->hari_kerja ?? ($fallbackSettings['hari_kerja'] ?? null)
                    ),
                    'source' => 'user_override',
                    'schema_id' => $fallbackSettings['schema_id'] ?? null,
                    'schema_name' => $fallbackSettings['schema_name'] ?? null,
                    'keterangan' => $userOverride->keterangan
                ]);
            }

            // 2. Prefer effective schema settings, fallback ke default aman.
            return $this->normalizeFacePolicyByRequirements($fallbackSettings);
        });
    }

    /**
     * Resolve working hours against the effective schema for a specific date.
     * This is used for backfill/approval flows where today's schema may differ
     * from the schema that applied on the target attendance date.
     */
    public function getWorkingHoursForDate(User $user, Carbon $date): array
    {
        $schemaSettings = $this->getSettingsFromEffectiveSchemaForDate($user, $date);
        $fallbackSettings = $schemaSettings
            ?? $this->getSettingsFromGlobalSchemaFallback($user)
            ?? $this->getHardcodedFallbackSettings($user);

        return $this->normalizeFacePolicyByRequirements($fallbackSettings);
    }

    /**
     * Check if the effective schema requires attendance on the target date.
     */
    public function isAttendanceRequiredOnDate(User $user, Carbon $date): bool
    {
        $schema = $this->attendanceSchemaService->getEffectiveSchema($user, $date->toDateString());

        if (!$schema instanceof AttendanceSchema) {
            // Backward-compatible fallback: installations that still rely on
            // legacy defaults should continue treating siswa attendance as required.
            return $this->isStudent($user);
        }

        return $schema->allowsAttendanceForUser($user);
    }

    /**
     * Check working day against the schema that applies on the target date.
     */
    public function isWorkingDayForDate(User $user, Carbon $date): bool
    {
        $workingHours = $this->getWorkingHoursForDate($user, $date);

        return $this->evaluateWorkingDayFromSettings($workingHours, $date);
    }

    /**
     * Sinkronkan requirement foto dengan kebijakan verifikasi wajah.
     *
     * Saat wajib_foto dimatikan, face verification tidak boleh aktif karena
     * request absensi tidak akan membawa foto.
     *
     * @param array<string, mixed> $settings
     * @return array<string, mixed>
     */
    private function normalizeFacePolicyByRequirements(array $settings): array
    {
        $wajibFoto = (bool) ($settings['wajib_foto'] ?? true);
        $faceEnabled = (bool) ($settings['face_verification_enabled'] ?? $this->getDefaultFaceVerificationEnabled());
        $faceTemplateRequired = (bool) ($settings['face_template_required'] ?? $this->getDefaultFaceTemplateRequired());

        if (!$wajibFoto) {
            $faceEnabled = false;
            $faceTemplateRequired = false;
        } elseif (!$faceEnabled) {
            $faceTemplateRequired = false;
        }

        $settings['face_verification_enabled'] = $faceEnabled;
        $settings['face_template_required'] = $faceTemplateRequired;

        return $settings;
    }

    /**
     * Resolve working hours from effective attendance schema (includes manual assignment).
     */
    private function getSettingsFromEffectiveSchema(User $user): ?array
    {
        try {
            $schema = $this->attendanceSchemaService->getEffectiveSchema($user);
            if (!$schema) {
                return null;
            }

            return $this->buildSettingsFromSchema($schema, $user, 'schema_effective');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getSettingsFromEffectiveSchemaForDate(User $user, Carbon $date): ?array
    {
        try {
            $schema = $this->attendanceSchemaService->getEffectiveSchema($user, $date->toDateString());
            if (!$schema) {
                return null;
            }

            return $this->buildSettingsFromSchema($schema, $user, 'schema_effective_date');
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildSettingsFromSchema(AttendanceSchema $schema, User $user, string $source): array
    {
        $effective = $schema->getEffectiveWorkingHours($user);

        return [
            'jam_masuk' => (string) ($effective['jam_masuk'] ?? '07:00'),
            'jam_pulang' => (string) ($effective['jam_pulang'] ?? '15:00'),
            'toleransi' => (int) ($effective['toleransi'] ?? 15),
            'minimal_open_time' => (int) ($effective['minimal_open_time'] ?? 70),
            'wajib_gps' => (bool) ($schema->wajib_gps ?? true),
            'wajib_foto' => (bool) ($schema->wajib_foto ?? true),
            'face_verification_enabled' => $schema->isFaceVerificationEnabled(),
            'face_template_required' => $schema->face_template_required !== null
                ? (bool) $schema->face_template_required
                : $this->getDefaultFaceTemplateRequired(),
            'hari_kerja' => $this->normalizeWorkingDays($schema->hari_kerja ?? null),
            'source' => $source,
            'schema_id' => (int) $schema->id,
            'schema_name' => $schema->schema_name,
            'keterangan' => null,
        ];
    }

    /**
     * Fallback to the latest active/default global schema through the schema model,
     * not the raw legacy table helper.
     */
    private function getSettingsFromGlobalSchemaFallback(User $user): ?array
    {
        $schema = AttendanceSchema::query()
            ->where('schema_type', 'global')
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();

        if (!$schema instanceof AttendanceSchema) {
            $schema = AttendanceSchema::query()
                ->where('schema_type', 'global')
                ->where('is_active', true)
                ->orderByDesc('updated_at')
                ->first();
        }

        if (!$schema instanceof AttendanceSchema) {
            return null;
        }

        $effective = $schema->getEffectiveWorkingHours($user);

        return [
            'jam_masuk' => (string) ($effective['jam_masuk'] ?? '07:00'),
            'jam_pulang' => (string) ($effective['jam_pulang'] ?? '15:00'),
            'toleransi' => (int) ($effective['toleransi'] ?? 15),
            'minimal_open_time' => (int) ($effective['minimal_open_time'] ?? 70),
            'wajib_gps' => (bool) ($schema->wajib_gps ?? true),
            'wajib_foto' => (bool) ($schema->wajib_foto ?? true),
            'face_verification_enabled' => $schema->isFaceVerificationEnabled(),
            'face_template_required' => $schema->face_template_required !== null
                ? (bool) $schema->face_template_required
                : $this->getDefaultFaceTemplateRequired(),
            'hari_kerja' => $this->normalizeWorkingDays($schema->hari_kerja ?? null),
            'source' => 'schema_global_fallback',
            'schema_id' => (int) $schema->id,
            'schema_name' => $schema->schema_name,
            'keterangan' => null,
        ];
    }

    /**
     * Final fallback when no schema data is available.
     */
    private function getHardcodedFallbackSettings(User $user): array
    {
        if ($this->isStudent($user)) {
            return [
                'jam_masuk' => '07:00',
                'jam_pulang' => '14:00',
                'toleransi' => 10,
                'minimal_open_time' => 70,
                'wajib_gps' => true,
                'wajib_foto' => true,
                'face_verification_enabled' => $this->getDefaultFaceVerificationEnabled(),
                'face_template_required' => $this->getDefaultFaceTemplateRequired(),
                'hari_kerja' => ['senin', 'selasa', 'rabu', 'kamis', 'jumat'],
                'source' => 'default_siswa',
                'schema_id' => null,
                'schema_name' => null,
                'keterangan' => 'Fallback default tanpa schema',
            ];
        }

        return [
            'jam_masuk' => '07:00',
            'jam_pulang' => '15:00',
            'toleransi' => 15,
            'minimal_open_time' => 70,
            'wajib_gps' => true,
            'wajib_foto' => true,
            'face_verification_enabled' => $this->getDefaultFaceVerificationEnabled(),
            'face_template_required' => $this->getDefaultFaceTemplateRequired(),
            'hari_kerja' => ['senin', 'selasa', 'rabu', 'kamis', 'jumat'],
            'source' => 'default_staff',
            'schema_id' => null,
            'schema_name' => null,
            'keterangan' => 'Fallback default tanpa schema',
        ];
    }

    /**
     * Check if user is student
     */
    private function isStudent(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::SISWA)) ||
            $user->status_kepegawaian === 'Siswa';
    }

    private function getDefaultFaceVerificationEnabled(): bool
    {
        return (bool) data_get(
            $this->attendanceRuntimeConfigService->getFaceVerificationPolicyConfig(),
            'enabled',
            true
        );
    }

    private function getDefaultFaceTemplateRequired(): bool
    {
        return (bool) data_get(
            $this->attendanceRuntimeConfigService->getFaceVerificationPolicyConfig(),
            'template_required',
            true
        );
    }

    /**
     * Check if current time is within working hours for specific action
     */
    public function isWithinWorkingHours(User $user, Carbon $time, string $type = 'masuk'): array
    {
        $workingHours = $this->getWorkingHours($user);

        // Try H:i:s first, then fallback to H:i
        try {
            $jamMasuk = Carbon::createFromFormat('H:i:s', $workingHours['jam_masuk']);
        } catch (\Exception $e) {
            $jamMasuk = Carbon::createFromFormat('H:i', $workingHours['jam_masuk']);
        }

        try {
            $jamPulang = Carbon::createFromFormat('H:i:s', $workingHours['jam_pulang']);
        } catch (\Exception $e) {
            $jamPulang = Carbon::createFromFormat('H:i', $workingHours['jam_pulang']);
        }

        $toleransi = (int) $workingHours['toleransi'];
        $minimalOpenTime = (int) ($workingHours['minimal_open_time'] ?? 70);

        if ($type === 'masuk') {
            // Window absen masuk: minimal_open_time menit sebelum jam masuk s/d jam masuk + toleransi
            $earliest = $jamMasuk->copy()->subMinutes($minimalOpenTime);
            $latest = $jamMasuk->copy()->addMinutes($toleransi);

            $currentTime = Carbon::createFromFormat('H:i', $time->format('H:i'));
            $isWithin = $currentTime->between($earliest, $latest);

            $status = 'ditolak';
            if ($isWithin) {
                if ($currentTime->lte($jamMasuk)) {
                    $status = 'tepat_waktu';
                } else {
                    $minutesLate = $currentTime->diffInMinutes($jamMasuk);
                    $status = $minutesLate <= $toleransi ? 'terlambat' : 'ditolak';
                }
            }

            return [
                'valid' => $isWithin,
                'status' => $status,
                'message' => $this->getStatusMessage(
                    $status,
                    $currentTime,
                    $jamMasuk,
                    $toleransi,
                    $minimalOpenTime
                ),
                'window' => [
                    'earliest' => $earliest->format('H:i'),
                    'latest' => $latest->format('H:i')
                ],
                'working_hours' => $workingHours
            ];
        }

        if ($type === 'pulang') {
            // Absen pulang: minimal setelah jam pulang resmi
            $currentTime = Carbon::createFromFormat('H:i', $time->format('H:i'));
            $isValid = $currentTime->gte($jamPulang);

            return [
                'valid' => $isValid,
                'status' => $isValid ? 'valid' : 'terlalu_awal',
                'message' => $isValid ? 'Waktu pulang valid' : "Belum waktunya pulang. Jam pulang: {$jamPulang->format('H:i')}",
                'window' => [
                    'earliest' => $jamPulang->format('H:i'),
                    'latest' => '23:59'
                ],
                'working_hours' => $workingHours
            ];
        }

        return ['valid' => false, 'status' => 'invalid_type', 'message' => 'Tipe absensi tidak valid'];
    }

    /**
     * Check if user is late
     */
    public function isLate(User $user, Carbon $checkInTime): bool
    {
        $workingHours = $this->getWorkingHours($user);
        $normalizedCheckIn = Carbon::createFromFormat('H:i', $checkInTime->format('H:i'));

        try {
            $jamMasuk = Carbon::createFromFormat('H:i:s', (string) $workingHours['jam_masuk']);
        } catch (\Exception $e) {
            $jamMasuk = Carbon::createFromFormat('H:i', substr((string) $workingHours['jam_masuk'], 0, 5));
        }

        return $normalizedCheckIn->gt($jamMasuk);
    }

    /**
     * Check if it's a working day for user
     */
    public function isWorkingDay(User $user, Carbon $date): bool
    {
        $workingHours = $this->getWorkingHours($user);

        return $this->evaluateWorkingDayFromSettings($workingHours, $date);
    }

    private function kalenderSupportsTahunAjaran(): bool
    {
        if (self::$kalenderHasTahunAjaranColumn === null) {
            self::$kalenderHasTahunAjaranColumn = Schema::hasColumn('kalender_akademik', 'tahun_ajaran_id');
        }

        return self::$kalenderHasTahunAjaranColumn;
    }

    /**
     * Evaluate working-day status from a resolved settings payload.
     *
     * @param array<string, mixed> $workingHours
     */
    private function evaluateWorkingDayFromSettings(array $workingHours, Carbon $date): bool
    {
        $hariKerja = $this->normalizeWorkingDays($workingHours['hari_kerja'] ?? null);
        $dayName = $this->normalizeDayToken($this->getDayNameInIndonesian($date));
        $normalizedWorkingDays = array_values(array_filter(array_map(
            fn($day) => $this->normalizeDayToken((string) $day),
            $hariKerja
        )));

        if (!in_array($dayName, $normalizedWorkingDays, true)) {
            return false;
        }

        $holidayQuery = DB::table('kalender_akademik')
            ->where('tanggal_mulai', '<=', $date->format('Y-m-d'))
            ->where('tanggal_selesai', '>=', $date->format('Y-m-d'))
            ->where('status_absensi', 'libur')
            ->where('is_active', true);

        if ($this->kalenderSupportsTahunAjaran()) {
            $activeTahunAjaranId = TahunAjaran::query()
                ->where('status', TahunAjaran::STATUS_ACTIVE)
                ->value('id');

            if ($activeTahunAjaranId) {
                $holidayQuery->where(function ($query) use ($activeTahunAjaranId) {
                    $query->whereNull('tahun_ajaran_id')
                        ->orWhere('tahun_ajaran_id', (int) $activeTahunAjaranId);
                });
            }
        }

        return !$holidayQuery->exists();
    }

    /**
     * Normalize day token for case-insensitive / punctuation-insensitive compare.
     * Examples:
     * - "Jumat"  -> "jumat"
     * - "jum'at" -> "jumat"
     */
    private function normalizeDayToken(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(["'", '’', '`', ' '], '', $normalized);

        return $normalized;
    }

    /**
     * Normalize hari_kerja value from legacy DB payloads.
     *
     * Accepts:
     * - array
     * - JSON string
     * - comma-separated string
     */
    private function normalizeWorkingDays($value): array
    {
        $default = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        if (is_array($value)) {
            $days = array_values(array_filter(array_map(static fn($item) => trim((string) $item), $value)));
            return !empty($days) ? $days : $default;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return $default;
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $this->normalizeWorkingDays($decoded);
            }

            $split = array_values(array_filter(array_map('trim', explode(',', $trimmed))));
            return !empty($split) ? $split : $default;
        }

        return $default;
    }

    /**
     * Check if user already has attendance record for today
     */
    public function hasAttendanceToday(User $user, string $type): bool
    {
        $today = Carbon::today()->format('Y-m-d');

        $attendance = DB::table('absensi')
            ->where('user_id', $user->id)
            ->where('tanggal', $today)
            ->first();

        if (!$attendance) {
            return false;
        }

        if ($type === 'masuk') {
            return !is_null($attendance->jam_masuk);
        }

        if ($type === 'pulang') {
            return !is_null($attendance->jam_pulang);
        }

        return false;
    }

    /**
     * Validate attendance attempt
     */
    public function validateAttendance(User $user, string $type, Carbon $time): array
    {
        // Check if it's a working day
        if (!$this->isWorkingDay($user, $time)) {
            return [
                'valid' => false,
                'message' => 'Hari ini bukan hari kerja',
                'code' => 'NOT_WORKING_DAY'
            ];
        }

        // Check for duplicate attendance
        if ($this->hasAttendanceToday($user, $type)) {
            return [
                'valid' => false,
                'message' => "Anda sudah absen {$type} hari ini",
                'code' => 'DUPLICATE_ATTENDANCE'
            ];
        }

        // For pulang, check if user has checked in today
        if ($type === 'pulang') {
            if (!$this->hasAttendanceToday($user, 'masuk')) {
                return [
                    'valid' => false,
                    'message' => 'Anda belum absen masuk hari ini',
                    'code' => 'NO_CHECK_IN'
                ];
            }
        }

        // Check working hours
        $timeValidation = $this->isWithinWorkingHours($user, $time, $type);

        return [
            'valid' => $timeValidation['valid'],
            'message' => $timeValidation['message'],
            'status' => $timeValidation['status'],
            'code' => $timeValidation['valid'] ? 'VALID' : 'INVALID_TIME',
            'working_hours' => $timeValidation['working_hours'],
            'window' => $timeValidation['window']
        ];
    }

    /**
     * Get status message for attendance
     */
    private function getStatusMessage(
        string $status,
        Carbon $currentTime,
        Carbon $jamMasuk,
        int $toleransi,
        int $minimalOpenTime
    ): string
    {
        switch ($status) {
            case 'tepat_waktu':
                return 'Absen tepat waktu';
            case 'terlambat':
                $minutesLate = $currentTime->diffInMinutes($jamMasuk);
                return "Terlambat {$minutesLate} menit";
            case 'ditolak':
                if ($currentTime->lt($jamMasuk->copy()->subMinutes($minimalOpenTime))) {
                    return "Terlalu awal. Absen masuk bisa dilakukan {$minimalOpenTime} menit sebelum jam kerja";
                } else {
                    return "Terlalu terlambat. Maksimal keterlambatan {$toleransi} menit";
                }
            default:
                return 'Status tidak dikenal';
        }
    }

    /**
     * Get Indonesian day name
     */
    private function getDayNameInIndonesian(Carbon $date): string
    {
        $days = [
            'Monday' => 'Senin',
            'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu',
            'Thursday' => 'Kamis',
            'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
            'Sunday' => 'Minggu'
        ];

        return $days[$date->format('l')] ?? $date->format('l');
    }

    /**
     * Clear cache for user working hours
     */
    public function clearUserCache(int $userId): void
    {
        Cache::forget("working_hours_user_{$userId}");
        $this->bumpAttendanceRuntimeVersion();
    }

    /**
     * Clear schema-driven working-hours cache
     */
    public function clearGlobalCache(): void
    {
        $this->bumpAttendanceRuntimeVersion();
    }

    private function bumpAttendanceRuntimeVersion(): void
    {
        $current = Cache::get(self::RUNTIME_CACHE_VERSION_KEY);
        if ($current === null) {
            Cache::forever(self::RUNTIME_CACHE_VERSION_KEY, 2);
            return;
        }

        Cache::increment(self::RUNTIME_CACHE_VERSION_KEY);
    }
}
