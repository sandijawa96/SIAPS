<?php

namespace App\Support;

final class RoleNames
{
    public const SUPER_ADMIN = 'Super_Admin';
    public const ADMIN = 'Admin';
    public const KEPALA_SEKOLAH = 'Kepala_Sekolah';
    public const WAKASEK_KURIKULUM = 'Wakasek_Kurikulum';
    public const WAKASEK_KESISWAAN = 'Wakasek_Kesiswaan';
    public const WAKASEK_HUMAS = 'Wakasek_Humas';
    public const WAKASEK_SARPRAS = 'Wakasek_Sarpras';
    public const WALI_KELAS = 'Wali Kelas';
    public const GURU = 'Guru';
    public const GURU_BK = 'Guru_BK';
    public const SISWA = 'Siswa';
    public const PEGAWAI = 'Pegawai';

    /**
     * Canonical role names and their accepted aliases.
     *
     * @var array<string, array<int, string>>
     */
    private const ALIASES = [
        self::SUPER_ADMIN => ['Super_Admin', 'Super Admin', 'Super_Admin_web', 'Super_Admin_api', 'super_admin', 'super admin', 'super_admin_web', 'super_admin_api'],
        self::ADMIN => ['Admin', 'Admin_web', 'Admin_api', 'admin', 'admin_web', 'admin_api'],
        self::KEPALA_SEKOLAH => ['Kepala_Sekolah', 'Kepala_Sekolah_web', 'Kepala_Sekolah_api', 'kepala_sekolah', 'kepala_sekolah_web', 'kepala_sekolah_api'],
        self::WAKASEK_KURIKULUM => ['Wakasek_Kurikulum', 'Wakasek Kurikulum', 'Wakasek_Kurikulum_web', 'Wakasek_Kurikulum_api', 'wakasek_kurikulum', 'wakasek kurikulum', 'wakasek_kurikulum_web', 'wakasek_kurikulum_api'],
        self::WAKASEK_KESISWAAN => ['Wakasek_Kesiswaan', 'Wakasek Kesiswaan', 'Wakasek_Kesiswaan_web', 'Wakasek_Kesiswaan_api', 'wakasek_kesiswaan', 'wakasek kesiswaan', 'wakasek_kesiswaan_web', 'wakasek_kesiswaan_api'],
        self::WAKASEK_HUMAS => ['Wakasek_Humas', 'Wakasek Humas', 'Wakasek_Humas_web', 'Wakasek_Humas_api', 'wakasek_humas', 'wakasek humas', 'wakasek_humas_web', 'wakasek_humas_api'],
        self::WAKASEK_SARPRAS => ['Wakasek_Sarpras', 'Wakasek Sarpras', 'Wakasek_Sarpras_web', 'Wakasek_Sarpras_api', 'wakasek_sarpras', 'wakasek sarpras', 'wakasek_sarpras_web', 'wakasek_sarpras_api'],
        self::WALI_KELAS => ['Wali Kelas', 'Wali_Kelas', 'Wali_Kelas_web', 'Wali_Kelas_api', 'wali kelas', 'wali_kelas', 'wali_kelas_web', 'wali_kelas_api'],
        self::GURU => ['Guru', 'Guru_web', 'Guru_api', 'guru', 'guru_web', 'guru_api'],
        self::GURU_BK => ['Guru_BK', 'Guru_BK_web', 'Guru_BK_api', 'guru_bk', 'guru_bk_web', 'guru_bk_api'],
        self::SISWA => ['Siswa', 'siswa', 'Siswa_web', 'Siswa_api'],
        self::PEGAWAI => ['Pegawai', 'pegawai', 'Pegawai_web', 'Pegawai_api', 'Staff', 'Staff_TU', 'staff', 'staff_tu'],
    ];

    /**
     * Get accepted aliases for a canonical role name.
     *
     * @return array<int, string>
     */
    public static function aliases(string $canonicalRole): array
    {
        return self::ALIASES[$canonicalRole] ?? [$canonicalRole];
    }

    /**
     * Normalize role name to canonical value when alias exists.
     */
    public static function normalize(?string $roleName): ?string
    {
        if ($roleName === null || $roleName === '') {
            return null;
        }

        foreach (self::ALIASES as $canonical => $aliases) {
            if (in_array($roleName, $aliases, true)) {
                return $canonical;
            }
        }

        return $roleName;
    }

    /**
     * Resolve aliases using an input role that may already be an alias.
     *
     * @return array<int, string>
     */
    public static function aliasesFor(?string $roleName): array
    {
        $normalized = self::normalize($roleName);
        if ($normalized === null) {
            return [];
        }

        return self::aliases($normalized);
    }

    /**
     * Flatten aliases for multiple canonical roles.
     *
     * @param array<int, string> $canonicalRoles
     * @return array<int, string>
     */
    public static function flattenAliases(array $canonicalRoles): array
    {
        $aliases = [];
        foreach ($canonicalRoles as $canonicalRole) {
            foreach (self::aliases($canonicalRole) as $roleAlias) {
                if (!in_array($roleAlias, $aliases, true)) {
                    $aliases[] = $roleAlias;
                }
            }
        }

        return $aliases;
    }
}
