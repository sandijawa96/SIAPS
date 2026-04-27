<?php

namespace App\Services;

use App\Models\AttendanceSchema;
use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class SimpleAttendanceService
{
    /**
     * Get effective attendance settings for a user
     */
    public function getEffectiveSettings(User $user, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::today();

        // Get global settings
        $global = $this->getGlobalSettings();

        // ASN tidak wajib absen (menggunakan KMOB Jabar)
        if ($user->status_kepegawaian === 'ASN') {
            return [
                'wajib_absen' => false,
                'alasan' => 'Menggunakan JSA Jabar',
                'jam_masuk' => null,
                'jam_pulang' => null,
                'toleransi' => 0,
                'wajib_gps' => false,
                'wajib_foto' => false,
                'face_verification_enabled' => false,
                'face_template_required' => false,
                'show_in_reports' => false
            ];
        }

        // Check user override first
        $override = $this->getUserOverride($user->id);
        if ($override && $override->is_active) {
            return $this->mergeSettings($global, $override, $user);
        }

        // Check shift schedule for security staff
        if ($user->hasRole('Keamanan') || $user->status_kepegawaian === 'Keamanan') {
            $shiftSettings = $this->getShiftSettings($user, $date);
            if ($shiftSettings) {
                return $shiftSettings;
            }
        }

        // Special settings for students (role-based, not status_kepegawaian)
        if ($user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            return [
                'wajib_absen' => true,
                'alasan' => 'Siswa wajib absen',
                'jam_masuk' => $global['siswa_jam_masuk'],
                'jam_pulang' => $global['siswa_jam_pulang'],
                'toleransi' => $global['siswa_toleransi'],
                'minimal_open_time' => $global['minimal_open_time_siswa'],
                'wajib_gps' => $global['wajib_gps'],
                'wajib_foto' => $global['wajib_foto'],
                'face_verification_enabled' => $global['face_verification_enabled'],
                'face_template_required' => $global['face_template_required'],
                'hari_kerja' => $global['hari_kerja'],
                'lokasi_gps_ids' => $global['lokasi_gps_ids'],
                'show_in_reports' => true
            ];
        }

        // Default settings for staff/teachers
        return [
            'wajib_absen' => true,
            'alasan' => 'Staff/Guru wajib absen',
            'jam_masuk' => $global['jam_masuk_default'],
            'jam_pulang' => $global['jam_pulang_default'],
            'toleransi' => $global['toleransi_default'],
            'minimal_open_time' => $global['minimal_open_time_staff'],
            'wajib_gps' => $global['wajib_gps'],
            'wajib_foto' => $global['wajib_foto'],
            'face_verification_enabled' => $global['face_verification_enabled'],
            'face_template_required' => $global['face_template_required'],
            'hari_kerja' => $global['hari_kerja'],
            'lokasi_gps_ids' => $global['lokasi_gps_ids'],
            'show_in_reports' => true
        ];
    }

    /**
     * Get global attendance settings
     */
    public function getGlobalSettings(): array
    {
        $settings = $this->resolveGlobalSettingsRow();

        if (!$settings) {
            // Create default settings if not exists
            $settings = AttendanceSchema::create([
                'schema_name' => 'Default Schema',
                'schema_type' => 'global',
                'is_active' => true,
                'is_default' => true,
                'jam_masuk_default' => '07:00',
                'jam_pulang_default' => '15:00',
                'toleransi_default' => 15,
                'minimal_open_time_staff' => 70,
                'wajib_gps' => true,
                'wajib_foto' => true,
                'face_verification_enabled' => null,
                'face_template_required' => (bool) config('attendance.face.template_required', true),
                'hari_kerja' => json_encode(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']),
                'siswa_jam_masuk' => '07:00',
                'siswa_jam_pulang' => '14:00',
                'siswa_toleransi' => 10,
                'minimal_open_time_siswa' => 70,
                'lokasi_gps_ids' => null,
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
            ]);
        }

        return [
            'jam_masuk_default' => $settings->jam_masuk_default ?: '07:00',
            'jam_pulang_default' => $settings->jam_pulang_default ?: '15:00',
            'toleransi_default' => $settings->toleransi_default ?: 15,
            'minimal_open_time_staff' => $settings->minimal_open_time_staff ?? 70,
            'wajib_gps' => (bool) ($settings->wajib_gps ?? true),
            'wajib_foto' => (bool) ($settings->wajib_foto ?? true),
                'face_verification_enabled' => $settings->face_verification_enabled !== null
                    ? (bool) $settings->face_verification_enabled
                    : (bool) config('attendance.face.enabled', true),
            'face_template_required' => $settings->face_template_required !== null
                ? (bool) $settings->face_template_required
                : (bool) config('attendance.face.template_required', true),
            'hari_kerja' => $this->decodeJsonField(
                $settings->hari_kerja ?? null,
                ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']
            ),
            'siswa_jam_masuk' => $settings->siswa_jam_masuk ?: '07:00',
            'siswa_jam_pulang' => $settings->siswa_jam_pulang ?: '14:00',
            'siswa_toleransi' => $settings->siswa_toleransi ?: 10,
            'minimal_open_time_siswa' => $settings->minimal_open_time_siswa ?? 70,
            'lokasi_gps_ids' => $this->decodeJsonField($settings->lokasi_gps_ids ?? null, []),
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
            'face_result_when_template_missing' => (string) ($settings->face_result_when_template_missing ?? config('attendance.face.result_when_template_missing', 'verified')),
            'face_reject_to_manual_review' => $settings->face_reject_to_manual_review !== null
                ? (bool) $settings->face_reject_to_manual_review
                : (bool) config('attendance.face.reject_to_manual_review', true),
            'face_skip_when_photo_missing' => $settings->face_skip_when_photo_missing !== null
                ? (bool) $settings->face_skip_when_photo_missing
                : (bool) config('attendance.face.skip_when_photo_missing', true),
        ];
    }

    /**
     * Update global settings
     */
    public function updateGlobalSettings(array $data, int $userId): bool
    {
        try {
            $updateData = array_merge($data, [
                'updated_at' => now(),
                'updated_by' => $userId
            ]);

            // Ensure JSON fields are properly encoded
            if (isset($updateData['hari_kerja']) && is_array($updateData['hari_kerja'])) {
                $updateData['hari_kerja'] = json_encode($updateData['hari_kerja']);
            }

            if (isset($updateData['lokasi_gps_ids']) && is_array($updateData['lokasi_gps_ids'])) {
                $updateData['lokasi_gps_ids'] = json_encode($updateData['lokasi_gps_ids']);
            }

            $settings = $this->resolveGlobalSettingsRow();

            if ($settings instanceof AttendanceSchema) {
                $settings->fill($updateData);
                $settings->save();
            } else {
                $settings = AttendanceSchema::create(array_merge([
                    'schema_name' => 'Default Schema',
                    'schema_type' => 'global',
                    'is_active' => true,
                    'is_default' => true,
                ], $updateData));
            }

            Log::info('Global attendance settings updated', [
                'user_id' => $userId,
                'data' => $data
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update global attendance settings', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'data' => $data
            ]);

            return false;
        }
    }

    /**
     * Get user override settings
     */
    public function getUserOverride(int $userId)
    {
        return DB::table('user_attendance_overrides')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Create or update user override
     */
    public function setUserOverride(int $userId, array $data, int $createdBy): bool
    {
        try {
            $overrideData = array_merge($data, [
                'user_id' => $userId,
                'updated_at' => now()
            ]);

            // Handle JSON fields
            if (isset($overrideData['hari_kerja']) && is_array($overrideData['hari_kerja'])) {
                $overrideData['hari_kerja'] = json_encode($overrideData['hari_kerja']);
            }

            if (isset($overrideData['lokasi_gps_ids']) && is_array($overrideData['lokasi_gps_ids'])) {
                $overrideData['lokasi_gps_ids'] = json_encode($overrideData['lokasi_gps_ids']);
            }

            $exists = DB::table('user_attendance_overrides')
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                DB::table('user_attendance_overrides')
                    ->where('user_id', $userId)
                    ->update($overrideData);
            } else {
                $overrideData['created_at'] = now();
                $overrideData['created_by'] = $createdBy;
                DB::table('user_attendance_overrides')->insert($overrideData);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to set user override', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'data' => $data
            ]);

            return false;
        }
    }

    /**
     * Get shift settings for security staff
     */
    private function getShiftSettings(User $user, Carbon $date): ?array
    {
        $shift = DB::table('shift_schedules')
            ->where('user_id', $user->id)
            ->where('tanggal', $date->format('Y-m-d'))
            ->where('is_active', true)
            ->first();

        if (!$shift) {
            return null;
        }

        $global = $this->getGlobalSettings();

        return [
            'wajib_absen' => true,
            'alasan' => 'Shift ' . ucfirst($shift->shift_type),
            'jam_masuk' => $shift->jam_mulai,
            'jam_pulang' => $shift->jam_selesai,
            'toleransi' => 15, // Default for shift
            'wajib_gps' => true,
            'wajib_foto' => true,
            'face_verification_enabled' => $global['face_verification_enabled'] ?? true,
            'face_template_required' => $global['face_template_required'] ?? true,
            'shift_type' => $shift->shift_type,
            'show_in_reports' => true
        ];
    }

    /**
     * Merge global settings with user override
     */
    private function mergeSettings(array $global, $override, User $user): array
    {
        // Determine default minimal_open_time based on user role
        $defaultMinimalOpenTime = $user->hasRole(RoleNames::aliases(RoleNames::SISWA))
            ? $global['minimal_open_time_siswa']
            : $global['minimal_open_time_staff'];

        return [
            'wajib_absen' => true,
            'alasan' => $override->keterangan ?? 'Pengaturan khusus',
            'jam_masuk' => $override->jam_masuk ?? $global['jam_masuk_default'],
            'jam_pulang' => $override->jam_pulang ?? $global['jam_pulang_default'],
            'toleransi' => $override->toleransi ?? $global['toleransi_default'],
            'minimal_open_time' => $override->minimal_open_time ?? $defaultMinimalOpenTime,
            'wajib_gps' => $override->wajib_gps ?? $global['wajib_gps'],
            'wajib_foto' => $override->wajib_foto ?? $global['wajib_foto'],
            'face_verification_enabled' => $global['face_verification_enabled'],
            'face_template_required' => $global['face_template_required'],
            'hari_kerja' => $override->hari_kerja ? json_decode($override->hari_kerja, true) : $global['hari_kerja'],
            'lokasi_gps_ids' => $override->lokasi_gps_ids ? json_decode($override->lokasi_gps_ids, true) : $global['lokasi_gps_ids'],
            'show_in_reports' => true
        ];
    }

    /**
     * Get all users with their effective settings
     */
    public function getAllUsersWithSettings(): array
    {
        $users = User::where('is_active', true)->get();
        $result = [];

        foreach ($users as $user) {
            $settings = $this->getEffectiveSettings($user);

            $result[] = [
                'user_id' => $user->id,
                'nama_lengkap' => $user->nama_lengkap,
                'email' => $user->email,
                'status_kepegawaian' => $user->status_kepegawaian,
                'roles' => $user->roles->pluck('name')->toArray(),
                'settings' => $settings
            ];
        }

        return $result;
    }

    /**
     * Get available GPS locations
     */
    public function getAvailableGpsLocations(): array
    {
        return DB::table('lokasi_gps')
            ->where('is_active', true)
            ->select('id', 'nama_lokasi', 'latitude', 'longitude', 'radius', 'geofence_type', 'geofence_geojson')
            ->get()
            ->toArray();
    }

    /**
     * Check if user should attend on specific date
     */
    public function shouldAttendOnDate(User $user, Carbon $date): bool
    {
        $settings = $this->getEffectiveSettings($user, $date);

        if (!$settings['wajib_absen']) {
            return false;
        }

        // Check if it's a working day
        $dayName = $this->getDayNameInIndonesian($date);
        $hariKerja = $settings['hari_kerja'] ?? ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

        if (!in_array($dayName, $hariKerja)) {
            return false;
        }

        // Check holidays
        $dateString = $date->format('Y-m-d');
        $isHoliday = false;
        if (Schema::hasTable('event_akademik')) {
            $isHoliday = DB::table('event_akademik')
                ->where('tanggal_mulai', '<=', $dateString)
                ->where(function ($query) use ($dateString) {
                    $query->whereNull('tanggal_selesai')
                        ->orWhere('tanggal_selesai', '>=', $dateString);
                })
                ->where('jenis', 'libur')
                ->where('is_active', true)
                ->exists();
        }

        return !$isHoliday;
    }

    /**
     * Get day name in Indonesian
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
     * Resolve the active default global settings row.
     */
    private function resolveGlobalSettingsRow(): ?AttendanceSchema
    {
        $settings = AttendanceSchema::query()
            ->where('schema_type', 'global')
            ->where('is_default', true)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();

        if ($settings) {
            return $settings;
        }

        return AttendanceSchema::query()
            ->where('schema_type', 'global')
            ->whereNull('target_role')
            ->whereNull('target_status')
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Safely decode JSON field to array with default fallback.
     */
    private function decodeJsonField($value, array $default): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return $default;
        }

        $decoded = json_decode((string) $value, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return $default;
        }

        return $decoded;
    }
}
