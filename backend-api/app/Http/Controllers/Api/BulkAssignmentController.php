<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BulkAssignmentService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class BulkAssignmentController extends Controller
{
    private BulkAssignmentService $bulkAssignmentService;

    public function __construct(BulkAssignmentService $bulkAssignmentService)
    {
        $this->bulkAssignmentService = $bulkAssignmentService;
        $this->middleware('permission:manage_attendance_settings');
    }

    /**
     * Perform bulk assignment with optimized batch processing
     */
    public function bulkAssign(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'schema_id' => 'required|integer|exists:attendance_settings,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'notes' => 'nullable|string|max:1000',
            'assignment_type' => 'nullable|string|in:manual,bulk,auto'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $options = [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'notes' => $request->notes,
                'assignment_type' => $request->assignment_type ?? 'bulk'
            ];

            $results = $this->bulkAssignmentService->bulkAssign(
                $request->user_ids,
                $request->schema_id,
                $options
            );

            $statusCode = empty($results['errors']) ? 200 : 207; // 207 = Multi-Status

            return response()->json([
                'success' => empty($results['errors']),
                'message' => 'Bulk assignment completed',
                'data' => $results
            ], $statusCode);
        } catch (\Exception $e) {
            Log::error('Bulk assignment controller error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Bulk assignment failed'
            ], 500);
        }
    }

    /**
     * Get paginated users for assignment UI with performance optimization
     */
    public function getPaginatedUsers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 50);
            $search = $request->get('search', '');

            $result = $this->bulkAssignmentService->getPaginatedUsers($page, $perPage, $search);

            return response()->json([
                'success' => true,
                'message' => 'Users retrieved successfully',
                'data' => $result['data'],
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'has_more' => $result['has_more']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get paginated users error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users'
            ], 500);
        }
    }

    /**
     * Get users with their current schema assignments (optimized)
     */
    public function getUsersWithSchemas(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'search' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $page = $request->get('page', 1);
            $perPage = $request->get('per_page', 50);
            $search = $request->get('search', '');

            $result = $this->bulkAssignmentService->getUsersWithSchemas($page, $perPage, $search);

            return response()->json([
                'success' => true,
                'message' => 'Users with schemas retrieved successfully',
                'data' => $result['data'],
                'pagination' => [
                    'total' => $result['total'],
                    'per_page' => $result['per_page'],
                    'current_page' => $result['current_page'],
                    'last_page' => $result['last_page'],
                    'has_more' => $result['has_more']
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get users with schemas error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve users with schemas'
            ], 500);
        }
    }

    /**
     * Get assignment progress (for future queue implementation)
     */
    public function getAssignmentProgress(Request $request, string $jobId): JsonResponse
    {
        try {
            $progress = $this->bulkAssignmentService->getAssignmentProgress($jobId);

            return response()->json([
                'success' => true,
                'message' => 'Assignment progress retrieved successfully',
                'data' => $progress
            ]);
        } catch (\Exception $e) {
            Log::error('Get assignment progress error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve assignment progress'
            ], 500);
        }
    }

    /**
     * Get bulk assignment statistics
     */
    public function getBulkStats(): JsonResponse
    {
        try {
            // Get some basic stats for bulk operations
            $stats = [
                'total_assignments' => \App\Models\AttendanceSchemaAssignment::whereIn('assignment_type', ['manual', 'bulk', 'auto'])->count(),
                'active_assignments' => \App\Models\AttendanceSchemaAssignment::whereIn('assignment_type', ['manual', 'bulk', 'auto'])
                    ->where('is_active', true)
                    ->count(),
                'recent_assignments' => \App\Models\AttendanceSchemaAssignment::whereIn('assignment_type', ['manual', 'bulk', 'auto'])
                    ->where('created_at', '>=', now()->subDays(7))
                    ->count(),
                'batch_size' => 100,
                'recommended_per_page' => 50
            ];

            return response()->json([
                'success' => true,
                'message' => 'Bulk assignment statistics retrieved successfully',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Get bulk stats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bulk statistics'
            ], 500);
        }
    }
}
