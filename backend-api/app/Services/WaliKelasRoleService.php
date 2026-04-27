<?php

namespace App\Services;

use App\Models\User;
use App\Support\RoleNames;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class WaliKelasRoleService
{
    public static function ensureAssigned(?int $userId): bool
    {
        if (!$userId || $userId < 1) {
            return false;
        }

        $user = User::query()->find($userId);
        if (!$user) {
            return false;
        }

        if ($user->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS))) {
            return false;
        }

        $role = self::waliKelasRole();
        if (!$role) {
            Log::warning('Role Wali Kelas tidak ditemukan saat auto-assign wali kelas', [
                'user_id' => $userId,
                'aliases' => RoleNames::aliases(RoleNames::WALI_KELAS),
            ]);

            return false;
        }

        $user->assignRole($role);

        return true;
    }

    private static function waliKelasRole(): ?Role
    {
        return Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', RoleNames::aliases(RoleNames::WALI_KELAS))
            ->first()
            ?: Role::query()
                ->whereIn('name', RoleNames::aliases(RoleNames::WALI_KELAS))
                ->first();
    }
}
