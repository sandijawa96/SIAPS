<?php

namespace App\Http\Controllers\Api;

use App\Exports\ActivityLogExport;
use App\Helpers\AuthHelper;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|exists:users,id',
                'action' => 'nullable|string',
                'module' => 'nullable|string',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'per_page' => 'nullable|integer|min:1|max:100',
                'sort_by' => 'nullable|in:created_at,event,module,causer_id,level',
                'sort_order' => 'nullable|in:asc,desc',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $sortBy = (string) $request->get('sort_by', 'created_at');
            $sortOrder = (string) $request->get('sort_order', 'desc');
            $perPage = (int) $request->get('per_page', 15);

            $logs = $this->buildFilteredQuery($request)
                ->with(['user:id,nama_lengkap,email'])
                ->orderBy($sortBy, $sortOrder)
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Activity logs retrieved successfully',
                'data' => $logs,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve activity logs', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity logs',
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        try {
            $log = ActivityLog::with(['user:id,nama_lengkap,email'])->find($id);
            if (!$log) {
                return response()->json([
                    'success' => false,
                    'message' => 'Activity log not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Activity log retrieved successfully',
                'data' => $log,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve activity log', [
                'error' => $e->getMessage(),
                'activity_log_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity log',
            ], 500);
        }
    }

    public function statistics(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'period' => 'nullable|in:today,week,month,year',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            [$dateFrom, $dateTo, $period] = $this->resolveDateRange($request);
            $baseQuery = ActivityLog::query()->whereBetween('created_at', [$dateFrom, $dateTo]);

            $totalActivities = (clone $baseQuery)->count();
            $activitiesByModule = (clone $baseQuery)
                ->selectRaw('module, count(*) as count')
                ->groupBy('module')
                ->orderByDesc('count')
                ->get();
            $activitiesByAction = (clone $baseQuery)
                ->selectRaw('event as action, count(*) as count')
                ->whereNotNull('event')
                ->groupBy('event')
                ->orderByDesc('count')
                ->get();
            $topUsers = (clone $baseQuery)
                ->selectRaw('causer_id, count(*) as count')
                ->whereNotNull('causer_id')
                ->with(['user:id,nama_lengkap,email'])
                ->groupBy('causer_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(function (ActivityLog $item) {
                    return [
                        'user_id' => $item->causer_id,
                        'count' => (int) $item->count,
                        'user' => $item->user,
                    ];
                })
                ->values();

            $dailyDateExpression = $this->dateExpression('created_at');
            $hourExpression = $this->hourExpression('created_at');

            $dailyTrend = (clone $baseQuery)
                ->selectRaw("{$dailyDateExpression} as date, count(*) as count")
                ->groupByRaw($dailyDateExpression)
                ->orderBy('date')
                ->get();
            $hourlyPattern = (clone $baseQuery)
                ->selectRaw("{$hourExpression} as hour, count(*) as count")
                ->groupByRaw($hourExpression)
                ->orderBy('hour')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Activity statistics retrieved successfully',
                'data' => [
                    'period' => $period,
                    'date_range' => [
                        'from' => $dateFrom->format('Y-m-d'),
                        'to' => $dateTo->format('Y-m-d'),
                    ],
                    'total_activities' => $totalActivities,
                    'activities_by_module' => $activitiesByModule,
                    'activities_by_action' => $activitiesByAction,
                    'top_users' => $topUsers,
                    'daily_trend' => $dailyTrend,
                    'hourly_pattern' => $hourlyPattern,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve activity statistics', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve activity statistics',
            ], 500);
        }
    }

    public function userTimeline(Request $request, $userId): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $perPage = (int) $request->get('per_page', 20);
            $query = ActivityLog::query()
                ->where('causer_id', $userId)
                ->with(['user:id,nama_lengkap,email']);

            if ($request->filled('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->filled('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            $timeline = $query->orderByDesc('created_at')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'User activity timeline retrieved successfully',
                'data' => $timeline,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve user timeline', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user timeline',
            ], 500);
        }
    }

    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|in:csv,excel,pdf',
                'user_id' => 'nullable|exists:users,id',
                'action' => 'nullable|string',
                'module' => 'nullable|string',
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date|after_or_equal:date_from',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $logs = $this->buildFilteredQuery($request)
                ->with(['user:id,nama_lengkap,email'])
                ->orderByDesc('created_at')
                ->get();

            $rows = $logs->map(function (ActivityLog $log, int $index): array {
                return [
                    'no' => $index + 1,
                    'waktu' => $log->created_at?->format('Y-m-d H:i:s') ?? '-',
                    'pengguna' => $log->user?->nama_lengkap ?? $log->user?->email ?? 'Sistem',
                    'aksi' => $log->event ?? '-',
                    'modul' => $log->module ?? '-',
                    'deskripsi' => $log->description ?? '-',
                    'ip_address' => $log->ip_address ?? '-',
                    'level' => $log->level ?? 'info',
                ];
            })->values();

            $columns = [
                ['key' => 'no', 'label' => 'No'],
                ['key' => 'waktu', 'label' => 'Waktu'],
                ['key' => 'pengguna', 'label' => 'Pengguna'],
                ['key' => 'aksi', 'label' => 'Aksi'],
                ['key' => 'modul', 'label' => 'Modul'],
                ['key' => 'deskripsi', 'label' => 'Deskripsi'],
                ['key' => 'ip_address', 'label' => 'IP Address'],
                ['key' => 'level', 'label' => 'Level'],
            ];

            $meta = [
                'title' => 'Activity Logs Report',
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'generated_by' => $request->user()?->nama_lengkap ?? $request->user()?->username ?? 'System',
                'filter_summary' => $this->buildFilterSummary($request),
            ];

            $timestamp = now()->format('Y-m-d_H-i-s');
            $format = (string) $request->input('format');
            if ($format === 'csv') {
                return $this->exportToCsv($rows, "activity_logs_{$timestamp}.csv");
            }

            if ($format === 'excel') {
                return Excel::download(
                    new ActivityLogExport($rows, $columns, $meta),
                    "activity_logs_{$timestamp}.xlsx",
                    ExcelFormat::XLSX
                );
            }

            $pdf = Pdf::loadView('exports.activity-logs', [
                'title' => $meta['title'],
                'generatedAt' => $meta['generated_at'],
                'generatedBy' => $meta['generated_by'],
                'filterSummary' => $meta['filter_summary'],
                'columns' => $columns,
                'rows' => $rows->all(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download("activity_logs_{$timestamp}.pdf");
        } catch (\Throwable $e) {
            Log::error('Failed to export activity logs', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export activity logs',
            ], 500);
        }
    }

    public function cleanup(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'retention_days' => 'required|integer|min:1|max:3650',
                'confirm' => 'required|boolean|accepted',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $retentionDays = (int) $request->retention_days;
            $cutoffDate = Carbon::now()->subDays($retentionDays);
            $deletedCount = ActivityLog::query()->where('created_at', '<', $cutoffDate)->delete();

            ActivityLog::create([
                'causer_id' => AuthHelper::userId(),
                'causer_type' => AuthHelper::userId() ? User::class : null,
                'event' => 'cleanup_activity_logs',
                'log_name' => 'system',
                'module' => 'system',
                'description' => "Cleaned up {$deletedCount} activity logs older than {$retentionDays} days",
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'properties' => [
                    'retention_days' => $retentionDays,
                    'deleted_count' => $deletedCount,
                    'cutoff_date' => $cutoffDate->toISOString(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Activity logs cleanup completed',
                'data' => [
                    'deleted_count' => $deletedCount,
                    'retention_days' => $retentionDays,
                    'cutoff_date' => $cutoffDate->format('Y-m-d H:i:s'),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to cleanup activity logs', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to cleanup activity logs',
            ], 500);
        }
    }

    public function getFilters(): JsonResponse
    {
        try {
            $modules = ActivityLog::query()
                ->select('module')
                ->distinct()
                ->whereNotNull('module')
                ->orderBy('module')
                ->pluck('module');

            $actions = ActivityLog::query()
                ->select('event')
                ->distinct()
                ->whereNotNull('event')
                ->orderBy('event')
                ->pluck('event');

            return response()->json([
                'success' => true,
                'message' => 'Activity log filters retrieved successfully',
                'data' => [
                    'modules' => $modules,
                    'actions' => $actions,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to retrieve activity log filters', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve filters',
            ], 500);
        }
    }

    private function buildFilteredQuery(Request $request)
    {
        $query = ActivityLog::query();

        if ($request->filled('user_id')) {
            $query->where('causer_id', (int) $request->user_id);
        }

        if ($request->filled('action')) {
            $query->where('event', 'like', '%' . $request->action . '%');
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return $query;
    }

    private function resolveDateRange(Request $request): array
    {
        $period = (string) $request->get('period', 'month');
        $dateFrom = $request->filled('date_from') ? Carbon::parse((string) $request->date_from)->startOfDay() : null;
        $dateTo = $request->filled('date_to') ? Carbon::parse((string) $request->date_to)->endOfDay() : null;

        if ($dateFrom && $dateTo) {
            return [$dateFrom, $dateTo, $period];
        }

        return match ($period) {
            'today' => [Carbon::today(), Carbon::today()->endOfDay(), $period],
            'week' => [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek(), $period],
            'year' => [Carbon::now()->startOfYear(), Carbon::now()->endOfYear(), $period],
            default => [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth(), 'month'],
        };
    }

    private function buildFilterSummary(Request $request): string
    {
        $parts = [];
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $parts[] = 'Periode: ' . (($request->input('date_from')) ?: '-') . ' s/d ' . (($request->input('date_to')) ?: '-');
        }

        if ($request->filled('user_id')) {
            $user = User::query()->find((int) $request->user_id);
            $parts[] = 'Pengguna: ' . ($user?->nama_lengkap ?? $user?->email ?? ('#' . (int) $request->user_id));
        }

        if ($request->filled('action')) {
            $parts[] = 'Aksi: ' . (string) $request->action;
        }

        if ($request->filled('module')) {
            $parts[] = 'Modul: ' . (string) $request->module;
        }

        return $parts === [] ? 'Tanpa filter tambahan' : implode(' | ', $parts);
    }

    private function exportToCsv($rows, string $filename)
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['No', 'Waktu', 'Pengguna', 'Aksi', 'Modul', 'Deskripsi', 'IP Address', 'Level']);

            foreach ($rows as $row) {
                fputcsv($file, [
                    $row['no'],
                    $row['waktu'],
                    $row['pengguna'],
                    $row['aksi'],
                    $row['modul'],
                    $row['deskripsi'],
                    $row['ip_address'],
                    $row['level'],
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    private function dateExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%Y-%m-%d', {$column})",
            default => "DATE({$column})",
        };
    }

    private function hourExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        return match ($driver) {
            'sqlite' => "strftime('%H', {$column})",
            default => "EXTRACT(HOUR FROM {$column})",
        };
    }
}
