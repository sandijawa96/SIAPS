<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RoleDataScope
{
    /**
     * Apply class read scope based on current user role.
     */
    public static function applyKelasReadScope(Builder $query, ?User $user): Builder
    {
        if (!$user || self::canViewAllKelas($user)) {
            return $query;
        }

        $classIds = self::accessibleClassIds($user);
        if ($classIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('kelas.id', $classIds);
    }

    /**
     * Apply student read scope based on current user role.
     */
    public static function applySiswaReadScope(Builder $query, ?User $user): Builder
    {
        if (!$user || self::canViewAllSiswa($user)) {
            return $query;
        }

        $classIds = self::accessibleStudentClassIds($user);
        if ($classIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas('kelas', function ($kelasQuery) use ($classIds) {
            $kelasQuery
                ->whereIn('kelas.id', $classIds)
                ->where('kelas.is_active', true)
                ->where('kelas_siswa.is_active', true)
                ->where('kelas_siswa.status', 'aktif');
        });
    }

    /**
     * Resolve class IDs for student-read scope.
     *
     * If user has Wali Kelas role, prioritize wali-owned classes only.
     *
     * @return array<int, int>
     */
    private static function accessibleStudentClassIds(User $user): array
    {
        if ($user->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS))) {
            return self::normalizeIds(
                $user->kelasWali()
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all()
            );
        }

        return self::accessibleClassIds($user);
    }

    /**
     * Resolve class IDs that can be read by restricted roles.
     *
     * Current restricted roles:
     * - Wali Kelas: own classes
     * - Guru: classes from active jadwal_mengajar
     *
     * @return array<int, int>
     */
    public static function accessibleClassIds(User $user): array
    {
        $classIds = [];

        if ($user->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS))) {
            $classIds = array_merge(
                $classIds,
                $user->kelasWali()
                    ->where('is_active', true)
                    ->pluck('id')
                    ->all()
            );
        }

        if ($user->hasRole(RoleNames::aliases(RoleNames::GURU))) {
            $classIds = array_merge(
                $classIds,
                self::teachingClassIds($user)
            );
        }

        return self::normalizeIds($classIds);
    }

    /**
     * Determine if a user should have unrestricted class read access.
     */
    public static function canViewAllKelas(User $user): bool
    {
        if ($user->hasPermissionTo('manage_kelas')) {
            return true;
        }

        return $user->hasRole(RoleNames::flattenAliases([
            RoleNames::SUPER_ADMIN,
            RoleNames::ADMIN,
            RoleNames::KEPALA_SEKOLAH,
            RoleNames::WAKASEK_KURIKULUM,
            RoleNames::WAKASEK_KESISWAAN,
            RoleNames::WAKASEK_HUMAS,
            RoleNames::WAKASEK_SARPRAS,
            RoleNames::GURU_BK,
        ]));
    }

    /**
     * Determine if a user should have unrestricted student read access.
     */
    public static function canViewAllSiswa(User $user): bool
    {
        if ($user->hasPermissionTo('manage_students')) {
            return true;
        }

        return $user->hasRole(RoleNames::flattenAliases([
            RoleNames::SUPER_ADMIN,
            RoleNames::ADMIN,
            RoleNames::KEPALA_SEKOLAH,
            RoleNames::WAKASEK_KESISWAAN,
            RoleNames::GURU_BK,
        ]));
    }

    /**
     * @return array<int, int>
     */
    private static function teachingClassIds(User $user): array
    {
        if (!Schema::hasTable('jadwal_mengajar')) {
            return [];
        }

        return self::normalizeIds(
            DB::table('jadwal_mengajar')
                ->where('guru_id', $user->id)
                ->where('is_active', true)
                ->pluck('kelas_id')
                ->all()
        );
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<int, int>
     */
    private static function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                continue;
            }

            $value = (int) $id;
            if ($value > 0) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }
}
