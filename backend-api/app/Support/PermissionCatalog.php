<?php

namespace App\Support;

final class PermissionCatalog
{
    /**
     * Canonical permission catalog used by seeders and maintenance commands.
     *
     * @return array<int, array{name:string,display_name:string,description:string,module:string}>
     */
    public static function definitions(): array
    {
        return [
            // User management
            ['name' => 'view_users', 'display_name' => 'View Users', 'description' => 'View user list', 'module' => 'users'],
            ['name' => 'create_users', 'display_name' => 'Create Users', 'description' => 'Create new users', 'module' => 'users'],
            ['name' => 'update_users', 'display_name' => 'Update Users', 'description' => 'Update user data', 'module' => 'users'],
            ['name' => 'delete_users', 'display_name' => 'Delete Users', 'description' => 'Delete users', 'module' => 'users'],
            ['name' => 'manage_users', 'display_name' => 'Manage Users', 'description' => 'Manage users module', 'module' => 'users'],
            ['name' => 'reset_user_passwords', 'display_name' => 'Reset User Passwords', 'description' => 'Reset user passwords', 'module' => 'users'],
            ['name' => 'view_personal_data_verification', 'display_name' => 'View Personal Data Verification', 'description' => 'View personal data verification queue', 'module' => 'users'],
            ['name' => 'verify_personal_data_siswa', 'display_name' => 'Verify Personal Data Siswa', 'description' => 'Verify student personal data submissions', 'module' => 'users'],
            ['name' => 'verify_personal_data_pegawai', 'display_name' => 'Verify Personal Data Pegawai', 'description' => 'Verify employee personal data submissions', 'module' => 'users'],

            // Role and permission management
            ['name' => 'view_roles', 'display_name' => 'View Roles', 'description' => 'View roles', 'module' => 'roles'],
            ['name' => 'manage_roles', 'display_name' => 'Manage Roles', 'description' => 'Manage roles and assignments', 'module' => 'roles'],
            ['name' => 'manage_permissions', 'display_name' => 'Manage Permissions', 'description' => 'Manage permission catalog', 'module' => 'permissions'],

            // Core academic entities
            ['name' => 'view_siswa', 'display_name' => 'View Siswa', 'description' => 'View student data', 'module' => 'students'],
            ['name' => 'manage_students', 'display_name' => 'Manage Students', 'description' => 'Manage student module', 'module' => 'students'],
            ['name' => 'request_student_transfer', 'display_name' => 'Request Student Transfer', 'description' => 'Create student class-transfer request', 'module' => 'students'],
            ['name' => 'approve_student_transfer', 'display_name' => 'Approve Student Transfer', 'description' => 'Approve/reject student class-transfer request', 'module' => 'students'],
            ['name' => 'execute_wali_class_promotion', 'display_name' => 'Execute Wali Class Promotion', 'description' => 'Promote students by wali-class flow', 'module' => 'students'],
            ['name' => 'manage_wali_promotion_window', 'display_name' => 'Manage Wali Promotion Window', 'description' => 'Manage on/off promotion window for wali kelas', 'module' => 'students'],
            ['name' => 'view_pegawai', 'display_name' => 'View Pegawai', 'description' => 'View employee data', 'module' => 'employees'],
            ['name' => 'manage_pegawai', 'display_name' => 'Manage Pegawai', 'description' => 'Manage employee data', 'module' => 'employees'],
            ['name' => 'view_kelas', 'display_name' => 'View Kelas', 'description' => 'View class data', 'module' => 'classes'],
            ['name' => 'manage_kelas', 'display_name' => 'Manage Kelas', 'description' => 'Manage class data', 'module' => 'classes'],
            ['name' => 'view_tahun_ajaran', 'display_name' => 'View Tahun Ajaran', 'description' => 'View academic years', 'module' => 'academic'],
            ['name' => 'manage_tahun_ajaran', 'display_name' => 'Manage Tahun Ajaran', 'description' => 'Manage academic years', 'module' => 'academic'],
            ['name' => 'manage_periode_akademik', 'display_name' => 'Manage Periode Akademik', 'description' => 'Manage academic periods', 'module' => 'academic'],
            ['name' => 'manage_event_akademik', 'display_name' => 'Manage Event Akademik', 'description' => 'Manage academic events', 'module' => 'academic'],
            ['name' => 'view_mapel', 'display_name' => 'View Mata Pelajaran', 'description' => 'View subject master data', 'module' => 'academic'],
            ['name' => 'manage_mapel', 'display_name' => 'Manage Mata Pelajaran', 'description' => 'Manage subject master data', 'module' => 'academic'],
            ['name' => 'assign_guru_mapel', 'display_name' => 'Assign Guru Mapel', 'description' => 'Assign teachers to subjects and classes', 'module' => 'academic'],
            ['name' => 'view_jadwal_pelajaran', 'display_name' => 'View Jadwal Pelajaran', 'description' => 'View class schedule data', 'module' => 'academic'],
            ['name' => 'manage_jadwal_pelajaran', 'display_name' => 'Manage Jadwal Pelajaran', 'description' => 'Manage class schedule data', 'module' => 'academic'],

            // Attendance and leave
            ['name' => 'view_absensi', 'display_name' => 'View Absensi', 'description' => 'View attendance data', 'module' => 'attendance'],
            ['name' => 'manage_absensi', 'display_name' => 'Manage Absensi', 'description' => 'Manage attendance system', 'module' => 'attendance'],
            ['name' => 'manual_attendance', 'display_name' => 'Manual Attendance', 'description' => 'Create and manage manual attendance', 'module' => 'attendance'],
            ['name' => 'manual_attendance_backdate_override', 'display_name' => 'Manual Attendance Backdate Override', 'description' => 'Override H+1 limit for manual checkout correction', 'module' => 'attendance'],
            ['name' => 'submit_izin', 'display_name' => 'Submit Izin', 'description' => 'Submit own leave request', 'module' => 'leave'],
            ['name' => 'approve_izin', 'display_name' => 'Approve Izin', 'description' => 'Approve leave request', 'module' => 'leave'],
            ['name' => 'view_all_izin', 'display_name' => 'View All Izin', 'description' => 'View all leave requests', 'module' => 'leave'],
            ['name' => 'view_kelas_izin', 'display_name' => 'View Kelas Izin', 'description' => 'View leave requests by class scope', 'module' => 'leave'],
            ['name' => 'manage_attendance_settings', 'display_name' => 'Manage Attendance Settings', 'description' => 'Manage attendance settings', 'module' => 'attendance'],
            ['name' => 'unlock_face_template_submit_quota', 'display_name' => 'Unlock Face Template Submit Quota', 'description' => 'Unlock one additional self-submit slot for student face templates', 'module' => 'attendance'],

            // Settings, reports, communication
            ['name' => 'view_settings', 'display_name' => 'View Settings', 'description' => 'View system settings', 'module' => 'settings'],
            ['name' => 'manage_settings', 'display_name' => 'Manage Settings', 'description' => 'Manage system settings', 'module' => 'settings'],
            ['name' => 'manage_notifications', 'display_name' => 'Manage Notifications', 'description' => 'Manage notifications', 'module' => 'notifications'],
            ['name' => 'view_broadcast_campaigns', 'display_name' => 'View Broadcast Campaigns', 'description' => 'View broadcast message campaigns', 'module' => 'communication'],
            ['name' => 'manage_broadcast_campaigns', 'display_name' => 'Manage Broadcast Campaigns', 'description' => 'Manage broadcast message campaigns', 'module' => 'communication'],
            ['name' => 'send_broadcast_campaigns', 'display_name' => 'Send Broadcast Campaigns', 'description' => 'Send broadcast message campaigns', 'module' => 'communication'],
            ['name' => 'retry_broadcast_campaigns', 'display_name' => 'Retry Broadcast Campaigns', 'description' => 'Retry failed broadcast campaign deliveries', 'module' => 'communication'],
            ['name' => 'view_reports', 'display_name' => 'View Reports', 'description' => 'View reports', 'module' => 'reports'],
            ['name' => 'manage_whatsapp', 'display_name' => 'Manage WhatsApp', 'description' => 'Manage WhatsApp gateway', 'module' => 'communication'],
            ['name' => 'manage_qrcode', 'display_name' => 'Manage QR Code', 'description' => 'Manage QR Code system', 'module' => 'communication'],

            // Tracking and system
            ['name' => 'view_live_tracking', 'display_name' => 'View Live Tracking', 'description' => 'View student live tracking', 'module' => 'tracking'],
            ['name' => 'manage_live_tracking', 'display_name' => 'Manage Live Tracking', 'description' => 'Manage live tracking data', 'module' => 'tracking'],
            ['name' => 'manage_backups', 'display_name' => 'Manage Backups', 'description' => 'Manage backup and restore', 'module' => 'system'],
            ['name' => 'view_activity_logs', 'display_name' => 'View Activity Logs', 'description' => 'View activity logs', 'module' => 'system'],
            ['name' => 'manage_activity_logs', 'display_name' => 'Manage Activity Logs', 'description' => 'Manage activity logs', 'module' => 'system'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(
            static fn (array $definition): string => $definition['name'],
            self::definitions()
        );
    }
}
