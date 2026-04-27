<?php

namespace App\Support;

use App\Models\User;

final class RoleAccessMatrix
{
    public const WILDCARD_ALL = '*';

    /**
     * Canonical role matrix:
     * - role metadata
     * - default permission bundle
     * - feature labels for UI/docs
     *
     * @return array<string, array{
     *     display_name:string,
     *     description:string,
     *     level:int,
     *     is_active:bool,
     *     permissions:array<int,string>,
     *     features:array<int,string>
     * }>
     */
    public static function definitions(): array
    {
        return [
            RoleNames::SUPER_ADMIN => [
                'display_name' => 'Super Administrator',
                'description' => 'Akses penuh seluruh modul sistem',
                'level' => 1,
                'is_active' => true,
                'permissions' => [self::WILDCARD_ALL],
                'features' => [
                    'Akses semua modul dan data',
                    'Kelola role dan permission',
                    'Kelola pengaturan inti sistem',
                    'Kelola backup dan audit',
                ],
            ],
            RoleNames::ADMIN => [
                'display_name' => 'Administrator',
                'description' => 'Administrator operasional sistem',
                'level' => 2,
                'is_active' => true,
                'permissions' => [
                    'view_users',
                    'create_users',
                    'update_users',
                    'delete_users',
                    'manage_users',
                    'reset_user_passwords',
                    'view_personal_data_verification',
                    'verify_personal_data_siswa',
                    'verify_personal_data_pegawai',
                    'view_roles',
                    'manage_roles',
                    'manage_permissions',
                    'view_siswa',
                    'manage_students',
                    'request_student_transfer',
                    'approve_student_transfer',
                    'execute_wali_class_promotion',
                    'manage_wali_promotion_window',
                    'view_pegawai',
                    'manage_pegawai',
                    'view_kelas',
                    'manage_kelas',
                    'view_tahun_ajaran',
                    'manage_tahun_ajaran',
                    'manage_periode_akademik',
                    'manage_event_akademik',
                    'view_mapel',
                    'manage_mapel',
                    'assign_guru_mapel',
                    'view_jadwal_pelajaran',
                    'manage_jadwal_pelajaran',
                    'view_absensi',
                    'manage_absensi',
                    'manual_attendance',
                    'manual_attendance_backdate_override',
                    'submit_izin',
                    'approve_izin',
                    'view_all_izin',
                    'view_kelas_izin',
                    'manage_attendance_settings',
                    'unlock_face_template_submit_quota',
                    'view_settings',
                    'manage_settings',
                    'manage_notifications',
                    'view_broadcast_campaigns',
                    'manage_broadcast_campaigns',
                    'send_broadcast_campaigns',
                    'retry_broadcast_campaigns',
                    'view_reports',
                    'manage_whatsapp',
                    'manage_qrcode',
                    'view_live_tracking',
                    'manage_live_tracking',
                    'manage_backups',
                    'view_activity_logs',
                    'manage_activity_logs',
                ],
                'features' => [
                    'Manajemen pengguna, siswa, pegawai, kelas',
                    'Manajemen absensi dan izin global',
                    'Manajemen konfigurasi operasional',
                    'Monitoring pelaporan dan tracking',
                ],
            ],
            RoleNames::KEPALA_SEKOLAH => [
                'display_name' => 'Kepala Sekolah',
                'description' => 'Akses monitoring strategis sekolah',
                'level' => 3,
                'is_active' => true,
                'permissions' => [
                    'view_siswa',
                    'view_pegawai',
                    'view_kelas',
                    'view_absensi',
                    'approve_izin',
                    'view_all_izin',
                    'view_reports',
                    'view_live_tracking',
                    'view_personal_data_verification',
                    'view_mapel',
                    'view_jadwal_pelajaran',
                ],
                'features' => [
                    'Monitoring siswa, pegawai, kelas',
                    'Monitoring absensi dan izin',
                    'Akses laporan strategis',
                    'Monitoring live tracking',
                ],
            ],
            RoleNames::WAKASEK_KURIKULUM => [
                'display_name' => 'Wakil Kepala Sekolah Kurikulum',
                'description' => 'Manajemen kurikulum dan periode akademik',
                'level' => 4,
                'is_active' => true,
                'permissions' => [
                    'view_tahun_ajaran',
                    'manage_tahun_ajaran',
                    'manage_periode_akademik',
                    'manage_event_akademik',
                    'view_mapel',
                    'manage_mapel',
                    'assign_guru_mapel',
                    'view_jadwal_pelajaran',
                    'manage_jadwal_pelajaran',
                    'view_reports',
                    'approve_student_transfer',
                    'manage_wali_promotion_window',
                ],
                'features' => [
                    'Kelola tahun ajaran',
                    'Kelola periode akademik',
                    'Kelola event akademik',
                    'Approval pindah kelas siswa',
                    'Atur window naik kelas wali',
                    'Akses laporan akademik',
                ],
            ],
            RoleNames::WAKASEK_KESISWAAN => [
                'display_name' => 'Wakil Kepala Sekolah Kesiswaan',
                'description' => 'Manajemen kesiswaan dan disiplin siswa',
                'level' => 5,
                'is_active' => true,
                'permissions' => [
                    'view_siswa',
                    'manage_students',
                    'view_kelas',
                    'view_absensi',
                    'manage_absensi',
                    'manual_attendance',
                    'approve_izin',
                    'view_all_izin',
                    'view_kelas_izin',
                    'view_reports',
                    'view_live_tracking',
                    'manage_live_tracking',
                    'view_jadwal_pelajaran',
                    'view_personal_data_verification',
                    'verify_personal_data_siswa',
                ],
                'features' => [
                    'Kelola data siswa',
                    'Kelola absensi siswa',
                    'Approval izin siswa lintas kelas',
                    'Monitoring tracking siswa',
                ],
            ],
            RoleNames::WAKASEK_HUMAS => [
                'display_name' => 'Wakil Kepala Sekolah Humas',
                'description' => 'Komunikasi dan publikasi',
                'level' => 6,
                'is_active' => true,
                'permissions' => [
                    'manage_notifications',
                    'view_reports',
                ],
                'features' => [
                    'Kelola notifikasi',
                    'Akses laporan kebutuhan humas',
                ],
            ],
            RoleNames::WAKASEK_SARPRAS => [
                'display_name' => 'Wakil Kepala Sekolah Sarpras',
                'description' => 'Monitoring sarana prasarana',
                'level' => 7,
                'is_active' => true,
                'permissions' => [
                    'view_reports',
                ],
                'features' => [
                    'Akses laporan operasional',
                ],
            ],
            RoleNames::GURU_BK => [
                'display_name' => 'Guru BK',
                'description' => 'Bimbingan konseling siswa',
                'level' => 8,
                'is_active' => true,
                'permissions' => [
                    'view_siswa',
                    'view_kelas',
                    'view_absensi',
                    'approve_izin',
                    'view_kelas_izin',
                ],
                'features' => [
                    'Monitoring siswa dan kelas',
                    'Monitoring absensi',
                    'Approval izin sesuai kewenangan',
                ],
            ],
            RoleNames::WALI_KELAS => [
                'display_name' => 'Wali Kelas',
                'description' => 'Pengelolaan siswa di kelas binaan',
                'level' => 9,
                'is_active' => true,
                'permissions' => [
                    'view_siswa',
                    'view_kelas',
                    'view_absensi',
                    'manual_attendance',
                    'submit_izin',
                    'approve_izin',
                    'request_student_transfer',
                    'execute_wali_class_promotion',
                    'view_kelas_izin',
                    'view_reports',
                    'view_jadwal_pelajaran',
                ],
                'features' => [
                    'Lihat siswa kelas binaan',
                    'Approval izin siswa kelas sendiri',
                    'Input absensi manual sesuai scope',
                    'Ajukan request pindah kelas siswa',
                    'Naik kelas siswa saat window aktif',
                    'Akses laporan kelas',
                ],
            ],
            RoleNames::GURU => [
                'display_name' => 'Guru',
                'description' => 'Pengajar mata pelajaran',
                'level' => 10,
                'is_active' => true,
                'permissions' => [
                    'view_siswa',
                    'view_kelas',
                    'view_absensi',
                    'submit_izin',
                    'view_jadwal_pelajaran',
                ],
                'features' => [
                    'Lihat data siswa dan kelas',
                    'Lihat absensi',
                    'Ajukan izin pribadi',
                ],
            ],
            'Staff_TU' => [
                'display_name' => 'Staff Tata Usaha',
                'description' => 'Administrasi sekolah',
                'level' => 11,
                'is_active' => true,
                'permissions' => [
                    'view_pegawai',
                    'view_absensi',
                    'submit_izin',
                    'view_personal_data_verification',
                    'verify_personal_data_pegawai',
                ],
                'features' => [
                    'Lihat data pegawai',
                    'Lihat absensi',
                    'Ajukan izin pribadi',
                    'Verifikasi data pribadi pegawai',
                ],
            ],
            'Staff' => [
                'display_name' => 'Staff',
                'description' => 'Staff operasional sekolah',
                'level' => 12,
                'is_active' => true,
                'permissions' => [
                    'view_absensi',
                    'submit_izin',
                ],
                'features' => [
                    'Lihat absensi',
                    'Ajukan izin pribadi',
                ],
            ],
            RoleNames::PEGAWAI => [
                'display_name' => 'Pegawai',
                'description' => 'Pegawai umum sekolah',
                'level' => 13,
                'is_active' => true,
                'permissions' => [
                    'view_absensi',
                    'submit_izin',
                ],
                'features' => [
                    'Lihat absensi',
                    'Ajukan izin pribadi',
                ],
            ],
            RoleNames::SISWA => [
                'display_name' => 'Siswa',
                'description' => 'Peserta didik',
                'level' => 14,
                'is_active' => true,
                'permissions' => [
                    'submit_izin',
                    'view_jadwal_pelajaran',
                ],
                'features' => [
                    'Ajukan izin siswa',
                    'Akses fitur aplikasi siswa',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{name:string,display_name:string,description:string,level:int,is_active:bool}>
     */
    public static function baselineRoles(): array
    {
        $roles = [];
        foreach (self::definitions() as $name => $definition) {
            $roles[] = [
                'name' => $name,
                'display_name' => $definition['display_name'],
                'description' => $definition['description'],
                'level' => $definition['level'],
                'is_active' => $definition['is_active'],
            ];
        }

        return $roles;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function permissionMap(): array
    {
        $map = [];
        foreach (self::definitions() as $roleName => $definition) {
            $map[$roleName] = $definition['permissions'];
        }

        return $map;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function featureMap(): array
    {
        $map = [];
        foreach (self::definitions() as $roleName => $definition) {
            $map[$roleName] = $definition['features'];
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    public static function roleNames(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Returns a role name list that is safe for DB role-name filtering.
     *
     * @return array<int, string>
     */
    public static function roleQueryNames(string $roleName): array
    {
        $names = [$roleName];
        foreach (RoleNames::aliasesFor($roleName) as $alias) {
            $names[] = $alias;
        }

        return array_values(array_unique(array_filter(
            $names,
            static fn ($name): bool => is_string($name) && $name !== ''
        )));
    }

    /**
     * @return array<int, string>
     */
    public static function studentLeaveApproverRoles(): array
    {
        return [
            RoleNames::SUPER_ADMIN,
            RoleNames::ADMIN,
            RoleNames::WAKASEK_KESISWAAN,
            RoleNames::WALI_KELAS,
        ];
    }

    /**
     * @return array<int, string>
     */
    public static function studentLeaveApproverQueryNames(): array
    {
        $names = [];
        foreach (self::studentLeaveApproverRoles() as $roleName) {
            $names = array_merge($names, self::roleQueryNames($roleName));
        }

        return array_values(array_unique($names));
    }

    public static function isStudentLeaveApprover(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        return $user->hasRole(self::studentLeaveApproverQueryNames());
    }

    /**
     * Resolve a primary role from assigned role names using matrix level priority.
     * Lower `level` means higher priority.
     *
     * @param array<int, string> $assignedRoleNames
     */
    public static function resolvePrimaryRoleName(array $assignedRoleNames): ?string
    {
        $resolvedCanonicalRoles = [];
        foreach ($assignedRoleNames as $roleName) {
            if (!is_string($roleName) || trim($roleName) === '') {
                continue;
            }

            $normalized = RoleNames::normalize($roleName);
            if ($normalized === null || trim($normalized) === '') {
                continue;
            }

            $resolvedCanonicalRoles[] = $normalized;
        }

        $resolvedCanonicalRoles = array_values(array_unique($resolvedCanonicalRoles));
        if ($resolvedCanonicalRoles === []) {
            return null;
        }

        $definitions = self::definitions();
        usort(
            $resolvedCanonicalRoles,
            static function (string $left, string $right) use ($definitions): int {
                $leftLevel = isset($definitions[$left]['level']) ? (int) $definitions[$left]['level'] : PHP_INT_MAX;
                $rightLevel = isset($definitions[$right]['level']) ? (int) $definitions[$right]['level'] : PHP_INT_MAX;

                if ($leftLevel === $rightLevel) {
                    return strcmp($left, $right);
                }

                return $leftLevel <=> $rightLevel;
            }
        );

        return $resolvedCanonicalRoles[0] ?? null;
    }

    /**
     * Resolve primary role directly from user role assignments.
     */
    public static function resolvePrimaryRoleForUser(?User $user): ?string
    {
        if (!$user) {
            return null;
        }

        $assignedRoleNames = [];

        if ($user->relationLoaded('roles') && $user->roles) {
            $assignedRoleNames = $user->roles
                ->pluck('name')
                ->filter(fn ($roleName): bool => is_string($roleName) && trim($roleName) !== '')
                ->values()
                ->all();
        }

        if ($assignedRoleNames === []) {
            $assignedRoleNames = $user->getRoleNames()
                ->filter(fn ($roleName): bool => is_string($roleName) && trim($roleName) !== '')
                ->values()
                ->all();
        }

        return self::resolvePrimaryRoleName($assignedRoleNames);
    }

    /**
     * Roles considered "non-student leave submitters" for pegawai leave views.
     *
     * @return array<int, string>
     */
    public static function employeeLeaveSubjectRoles(): array
    {
        return array_values(array_filter(
            self::roleNames(),
            static fn (string $roleName): bool => $roleName !== RoleNames::SISWA
        ));
    }

    /**
     * @return array<int, string>
     */
    public static function employeeLeaveSubjectRoleQueryNames(): array
    {
        $names = [];
        foreach (self::employeeLeaveSubjectRoles() as $roleName) {
            $names = array_merge($names, self::roleQueryNames($roleName));
        }

        return array_values(array_unique($names));
    }
}
