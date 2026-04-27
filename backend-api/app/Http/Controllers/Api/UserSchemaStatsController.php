<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\AttendanceSchema;
use App\Models\AttendanceSchemaAssignment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UserSchemaStatsController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:manage_attendance_settings');
    }

    /**
     * Get global user schema statistics
     */
    public function getGlobalStats(): JsonResponse
    {
        try {
            // Get total users count (excluding ASN users)
            $totalUsers = User::where('status_kepegawaian', '!=', 'ASN')
                ->orWhereNull('status_kepegawaian')
                ->count();

            // Get total ASN users (excluded from schema system)
            $asnUsers = User::where('status_kepegawaian', 'ASN')->count();

            // Get all active assignments for non-ASN users
            $activeAssignments = AttendanceSchemaAssignment::whereHas('user', function ($query) {
                $query->where('status_kepegawaian', '!=', 'ASN')
                    ->orWhereNull('status_kepegawaian');
            })
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', now()->toDateString());
                })
                ->where('start_date', '<=', now()->toDateString())
                ->distinct('user_id')
                ->count('user_id');

            // Get manual assignments count for non-ASN users
            $manualAssignments = AttendanceSchemaAssignment::whereHas('user', function ($query) {
                $query->where('status_kepegawaian', '!=', 'ASN')
                    ->orWhereNull('status_kepegawaian');
            })
                ->where('is_active', true)
                ->where('assignment_type', 'manual')
                ->where(function ($query) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', now()->toDateString());
                })
                ->where('start_date', '<=', now()->toDateString())
                ->distinct('user_id')
                ->count('user_id');

            // Check if there's a default global schema
            $defaultSchema = AttendanceSchema::where('schema_type', 'global')
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();

            if (!$defaultSchema) {
                $defaultSchema = AttendanceSchema::where('is_active', true)->first();
            }

            // Calculate users with schema (only non-ASN users)
            $usersWithSchema = $activeAssignments;
            $autoAssignments = 0;

            // If there's a default schema, non-ASN users without explicit assignment also have schema
            if ($defaultSchema) {
                $usersWithSchema = $totalUsers; // All non-ASN users have schema through default
                $autoAssignments = $totalUsers - $manualAssignments;
            }

            $usersWithoutSchema = $totalUsers - $usersWithSchema;

            $stats = [
                'total_users' => $totalUsers,
                'users_with_schema' => $usersWithSchema,
                'manual_assignments' => $manualAssignments,
                'auto_assignments' => $autoAssignments,
                'users_without_schema' => $usersWithoutSchema,
                'asn_users_excluded' => $asnUsers,
                'has_default_schema' => $defaultSchema !== null,
                'default_schema_name' => $defaultSchema ? $defaultSchema->schema_name : null
            ];

            Log::info('Global user schema stats calculated (ASN excluded):', $stats);

            return response()->json([
                'success' => true,
                'data' => $stats,
                'message' => 'Global statistics retrieved successfully (ASN users excluded)'
            ]);
        } catch (\Exception $e) {
            Log::error('Error calculating global user schema stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve global statistics',
                'error' => 'Internal server error'
            ], 500);
        }
    }
}
