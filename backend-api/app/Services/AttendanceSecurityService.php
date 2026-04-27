<?php

namespace App\Services;

use App\Models\Absensi;
use App\Models\AttendanceSettingsLog;
use App\Models\AttendanceSetting;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AttendanceSecurityService
{
    /**
     * Check if attendance data can be modified
     */
    public function canModifyAttendance($attendanceId, $userId = null)
    {
        $attendance = Absensi::findOrFail($attendanceId);
        $user = $userId ? \App\Models\User::find($userId) : Auth::user();

        // Check if it's historical data (older than 24 hours)
        $isHistorical = $attendance->created_at->diffInHours(now()) > 24;

        if ($isHistorical) {
            // Only allow modification if user has special permission
            if (!$user || !$this->hasPermission($user, 'edit_historical_attendance')) {
                return [
                    'allowed' => false,
                    'reason' => 'Historical attendance data cannot be modified without special permission'
                ];
            }
        }

        // Check if it's the same user or user has admin permission
        if ($attendance->user_id !== ($user->id ?? null) && !$this->hasPermission($user, 'edit_any_attendance')) {
            return [
                'allowed' => false,
                'reason' => 'You can only modify your own attendance data'
            ];
        }

        return [
            'allowed' => true,
            'reason' => null
        ];
    }

    /**
     * Log attendance settings change with audit trail
     */
    public function logSettingsChange($settingsType, $targetId, $targetType, $oldSettings, $newSettings, $reason = null)
    {
        try {
            $log = AttendanceSettingsLog::logChange(
                $settingsType,
                $targetId,
                $targetType,
                $oldSettings,
                $newSettings,
                $reason
            );

            Log::info('Attendance settings changed', [
                'log_id' => $log->id,
                'settings_type' => $settingsType,
                'target_id' => $targetId,
                'target_type' => $targetType,
                'changed_by' => Auth::id(),
                'changes_count' => count($this->getChangedFields($oldSettings, $newSettings))
            ]);

            return $log;
        } catch (\Exception $e) {
            Log::error('Failed to log attendance settings change', [
                'error' => $e->getMessage(),
                'settings_type' => $settingsType,
                'target_id' => $targetId
            ]);

            throw $e;
        }
    }

    /**
     * Get impact analysis of settings change
     */
    public function getSettingsImpactAnalysis($settingsType, $targetId, $newSettings, $dateRange = null)
    {
        $dateRange = $dateRange ?? [now()->subDays(30), now()];

        $analysis = [
            'affected_users' => [],
            'potential_status_changes' => [],
            'recommendations' => []
        ];

        switch ($settingsType) {
            case 'global':
                $analysis['affected_users'] = $this->getGloballyAffectedUsers($dateRange);
                break;

            case 'user':
                $analysis['affected_users'] = [\App\Models\User::find($targetId)];
                break;

            case 'role':
                $analysis['affected_users'] = $this->getUsersByRole($targetId);
                break;

            case 'status':
                $analysis['affected_users'] = $this->getUsersByStatus($targetId);
                break;
        }

        // Analyze potential status changes
        foreach ($analysis['affected_users'] as $user) {
            $statusChanges = $this->analyzeUserStatusChanges($user, $newSettings, $dateRange);
            if (!empty($statusChanges)) {
                $analysis['potential_status_changes'][$user->id] = $statusChanges;
            }
        }

        // Generate recommendations
        $analysis['recommendations'] = $this->generateRecommendations($analysis);

        return $analysis;
    }

    /**
     * Create attendance snapshot for historical consistency
     */
    public function createAttendanceSnapshot($attendanceId)
    {
        $attendance = Absensi::findOrFail($attendanceId);

        // Get current effective settings for the user
        $currentSettings = $this->getCurrentEffectiveSettings($attendance->user_id);

        // Add snapshot to attendance record
        $attendance->update([
            'settings_snapshot' => $currentSettings
        ]);

        return $attendance;
    }

    /**
     * Validate attendance data integrity
     */
    public function validateAttendanceIntegrity($attendanceId)
    {
        $attendance = Absensi::findOrFail($attendanceId);

        $issues = [];

        // Check if timestamps are logical
        if ($attendance->jam_masuk && $attendance->jam_pulang) {
            if ($attendance->jam_pulang <= $attendance->jam_masuk) {
                $issues[] = 'Jam pulang tidak boleh lebih awal dari jam masuk';
            }
        }

        // Check if date is not in the future
        if ($attendance->tanggal > now()->toDateString()) {
            $issues[] = 'Tanggal absensi tidak boleh di masa depan';
        }

        // Check if attendance is not too old without proper authorization
        if ($attendance->created_at->diffInDays(now()) > 7) {
            $user = Auth::user();
            if (!$this->hasPermission($user, 'create_historical_attendance')) {
                $issues[] = 'Tidak diizinkan membuat data absensi yang terlalu lama';
            }
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues
        ];
    }

    /**
     * Get attendance history with security context
     */
    public function getSecureAttendanceHistory($userId, $dateRange, $includeSettings = false)
    {
        $user = Auth::user();

        // Check permission to view other user's data
        if ($userId !== $user->id && !$this->hasPermission($user, 'view_any_attendance')) {
            throw new \Exception('Unauthorized to view attendance data');
        }

        $query = Absensi::where('user_id', $userId)
            ->whereBetween('tanggal', $dateRange)
            ->orderBy('tanggal', 'desc');

        if ($includeSettings) {
            // Include settings snapshot if available
            $query->with(['settingsSnapshot']);
        }

        return $query->get();
    }

    /**
     * Check if user has permission (safe method)
     */
    private function hasPermission($user, $permission)
    {
        if (!$user) {
            return false;
        }

        // Check if user has the can method (Spatie Permission package)
        if (method_exists($user, 'can')) {
            return $user->can($permission);
        }

        // Fallback: check if user has roles with permissions
        if (method_exists($user, 'hasPermissionTo')) {
            return $user->hasPermissionTo($permission);
        }

        // Basic role-based check for admin users
        if (method_exists($user, 'hasRole')) {
            return $user->hasRole(RoleNames::flattenAliases([
                RoleNames::ADMIN,
                RoleNames::SUPER_ADMIN,
            ]));
        }

        return false;
    }

    /**
     * Private helper methods
     */
    private function getChangedFields($oldSettings, $newSettings)
    {
        $changes = [];

        foreach ($newSettings as $key => $newValue) {
            $oldValue = $oldSettings[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'from' => $oldValue,
                    'to' => $newValue
                ];
            }
        }

        return $changes;
    }

    private function getGloballyAffectedUsers($dateRange)
    {
        return \App\Models\User::whereHas('absensi', function ($query) use ($dateRange) {
            $query->whereBetween('tanggal', $dateRange);
        })->get();
    }

    private function getUsersByRole($roleName)
    {
        return \App\Models\User::whereHas('roles', function ($query) use ($roleName) {
            $query->where('name', $roleName);
        })->get();
    }

    private function getUsersByStatus($status)
    {
        return \App\Models\User::where('status_kepegawaian', $status)->get();
    }

    private function analyzeUserStatusChanges($user, $newSettings, $dateRange)
    {
        // This would analyze how the new settings would affect
        // the user's attendance status for the given date range
        // Implementation depends on specific business logic

        return [];
    }

    private function generateRecommendations($analysis)
    {
        $recommendations = [];

        if (count($analysis['affected_users']) > 100) {
            $recommendations[] = 'Perubahan ini akan mempengaruhi banyak user. Pertimbangkan untuk melakukan perubahan secara bertahap.';
        }

        if (!empty($analysis['potential_status_changes'])) {
            $recommendations[] = 'Beberapa status absensi mungkin akan berubah interpretasi. Lakukan review manual untuk data penting.';
        }

        return $recommendations;
    }

    private function getCurrentEffectiveSettings($userId)
    {
        // Get current effective settings for user
        // This would integrate with your existing settings service

        return [
            'jam_masuk_default' => '08:00',
            'jam_pulang_default' => '17:00',
            'toleransi_default' => 15,
            'wajib_gps' => true,
            'wajib_foto' => false,
            'captured_at' => now()->toISOString()
        ];
    }
}
