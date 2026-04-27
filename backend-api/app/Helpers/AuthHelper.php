<?php

namespace App\Helpers;

use App\Support\RoleNames;
use Illuminate\Support\Facades\Auth;

class AuthHelper
{
    /**
     * Get authenticated user ID
     */
    public static function userId(): ?int
    {
        return Auth::id();
    }

    /**
     * Get authenticated user
     */
    public static function user()
    {
        return Auth::user();
    }

    /**
     * Check if user is authenticated
     */
    public static function check(): bool
    {
        return Auth::check();
    }

    /**
     * Get user with specific role
     */
    public static function hasRole(string $role): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }

        return $user->roles()
            ->whereIn('name', RoleNames::aliasesFor($role))
            ->exists();
    }

    /**
     * Get user with specific permission
     */
    public static function hasPermission(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        
        // Check if user has permission through roles
        return $user->roles()
            ->whereHas('permissions', function($query) use ($permission) {
                $query->where('name', $permission);
            })
            ->exists();
    }
}
