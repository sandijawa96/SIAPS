<?php

namespace App\Http\Controllers\Api;

use App\Exports\ManualAttendanceIncidentBatchExport;
use App\Http\Controllers\Controller;
use App\Jobs\DispatchAttendanceWhatsappNotification;
use App\Jobs\ProcessManualAttendanceIncidentBatch;
use App\Models\Absensi;
use App\Services\ManualAttendanceIncidentService;
use App\Models\User;
use App\Services\ManualAttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;

class ManualAttendanceController extends Controller
{
    protected $manualAttendanceService;
    protected $manualAttendanceIncidentService;

    public function __construct(
        ManualAttendanceService $manualAttendanceService,
        ManualAttendanceIncidentService $manualAttendanceIncidentService
    )
    {
        $this->manualAttendanceService = $manualAttendanceService;
        $this->manualAttendanceIncidentService = $manualAttendanceIncidentService;

        // Apply permission middleware
        $this->middleware('permission:manual_attendance');
    }

    /**
     * Create manual attendance record
     */
    public function store(Request $request)
    {
        try {
            // Validate request data
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'tanggal' => 'required|date|before_or_equal:today',
                'jam_masuk' => 'nullable|date_format:H:i|required_if:status,terlambat',
                'jam_pulang' => 'nullable|date_format:H:i|after:jam_masuk',
                'status' => 'required|in:hadir,terlambat,izin,sakit,alpha',
                'keterangan' => 'nullable|string|max:500',
                'reason' => 'required|string|max:255',
                'latitude_masuk' => 'nullable|numeric|between:-90,90',
                'longitude_masuk' => 'nullable|numeric|between:-180,180',
                'latitude_pulang' => 'nullable|numeric|between:-90,90',
                'longitude_pulang' => 'nullable|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Additional validation using service
            $serviceValidation = $this->manualAttendanceService->validateAttendanceData($request->all());
            if (!empty($serviceValidation)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $serviceValidation
                ], 422);
            }

            // Create manual attendance
            $result = $this->manualAttendanceService->createManualAttendance(
                $request->all(),
                auth()->id()
            );

            if ($result['success']) {
                $attendance = $result['data'] instanceof Absensi ? $result['data'] : null;
                $this->queueManualCreateWhatsappNotifications($attendance);

                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data']
                ], 201);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error creating manual attendance', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat absensi manual'
            ], 500);
        }
    }

    /**
     * Update existing attendance record manually
     */
    public function update(Request $request, $id)
    {
        try {
            // Validate request data
            $validator = Validator::make($request->all(), [
                'jam_masuk' => 'nullable|date_format:H:i|required_if:status,terlambat',
                'jam_pulang' => 'nullable|date_format:H:i|after:jam_masuk',
                'status' => 'nullable|in:hadir,terlambat,izin,sakit,alpha',
                'keterangan' => 'nullable|string|max:500',
                'reason' => 'required|string|max:255',
                'latitude_masuk' => 'nullable|numeric|between:-90,90',
                'longitude_masuk' => 'nullable|numeric|between:-180,180',
                'latitude_pulang' => 'nullable|numeric|between:-90,90',
                'longitude_pulang' => 'nullable|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Update manual attendance
            $result = $this->manualAttendanceService->updateManualAttendance(
                $id,
                $request->all(),
                auth()->id()
            );

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'message' => $result['message'],
                    'data' => $result['data']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Error updating manual attendance', [
                'error' => $e->getMessage(),
                'attendance_id' => $id,
                'user_id' => auth()->id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memperbarui absensi'
            ], 500);
        }
    }

    /**
     * Get users that can be managed by current user
     */
    public function getManageableUsers(Request $request)
    {
        try {
            $user = auth()->user();
            $users = $this->manualAttendanceService->getManageableUsers($user);

            return response()->json([
                'success' => true,
                'data' => $users
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting manageable users', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data user'
            ], 500);
        }
    }

    /**
     * Get manual attendance history
     */
    public function history(Request $request)
    {
        try {
            // Validate filters
            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|integer|exists:users,id',
                'date' => 'nullable|date',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'bucket' => 'nullable|in:manual,correction,auto_alpha',
                'status' => 'nullable|in:hadir,terlambat,izin,sakit,alpha',
                'search' => 'nullable|string|max:100',
                'per_page' => 'nullable|integer|min:1|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filter tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get history with filters
            $history = $this->manualAttendanceService->getAttendanceHistory(
                $request->all(),
                auth()->user()
            );

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting attendance history', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil riwayat absensi'
            ], 500);
        }
    }

    /**
     * Search manageable users with lightweight payload for mobile flows.
     */
    public function searchManageableUsers(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'q' => 'nullable|string|max:100',
                'limit' => 'nullable|integer|min:1|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter pencarian tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $this->manualAttendanceService->searchManageableUsers(
                    auth()->user(),
                    (string) $request->input('q', ''),
                    (int) $request->input('limit', 20)
                ),
            ]);
        } catch (\Exception $e) {
            Log::error('Error searching manageable users', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mencari siswa yang dapat dikelola',
            ], 500);
        }
    }

    /**
     * Lightweight summary for mobile attendance management hub.
     */
    public function mobileSummary(Request $request)
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $this->manualAttendanceService->getMobileSummary(auth()->user()),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting mobile manual attendance summary', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil ringkasan pengelolaan absensi',
            ], 500);
        }
    }

    /**
     * Summary endpoint alias for frontend compatibility.
     */
    public function summary(Request $request)
    {
        return $this->statistics($request);
    }

    /**
     * Get pending checkout (lupa tap-out) list for H+1 follow-up.
     */
    public function pendingCheckout(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|integer|exists:users,id',
                'date' => 'nullable|date',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'include_overdue' => 'nullable|boolean',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filter tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $performer = auth()->user();
            $includeOverdue = filter_var($request->input('include_overdue', false), FILTER_VALIDATE_BOOLEAN);
            if ($includeOverdue && !$this->manualAttendanceService->hasBackdateOverrideAccess($performer)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk menampilkan backlog di atas H+1',
                ], 403);
            }

            $result = $this->manualAttendanceService->getPendingCheckoutHistory(
                $performer,
                $request->all()
            );

            return response()->json([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting pending checkout list', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil daftar lupa tap-out'
            ], 500);
        }
    }

    /**
     * Resolve pending checkout (lupa tap-out) manually.
     */
    public function resolveCheckout(Request $request, int $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'jam_pulang' => 'required|date_format:H:i',
                'reason' => 'required|string|max:255',
                'override_reason' => 'nullable|string|max:255',
                'status' => 'nullable|in:hadir,terlambat,izin,sakit,alpha',
                'keterangan' => 'nullable|string|max:500',
                'latitude_pulang' => 'nullable|numeric|between:-90,90',
                'longitude_pulang' => 'nullable|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $this->manualAttendanceService->resolvePendingCheckout(
                $id,
                $request->all(),
                auth()->id()
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'data' => $result['data'],
            ]);
        } catch (\Exception $e) {
            Log::error('Error resolving pending checkout', [
                'error' => $e->getMessage(),
                'attendance_id' => $id,
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menyelesaikan lupa tap-out'
            ], 500);
        }
    }

    /**
     * Check duplicate attendance data before create.
     */
    public function checkDuplicate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'required|integer|exists:users,id',
                'tanggal' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $targetUser = User::find($request->user_id);
            if (!$targetUser || !$this->manualAttendanceService->canManageAttendanceForUser(auth()->user(), $targetUser)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki izin untuk memeriksa data user ini',
                ], 403);
            }

            $existingAttendance = Absensi::where('user_id', $request->user_id)
                ->whereDate('tanggal', $request->tanggal)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'is_duplicate' => $existingAttendance !== null,
                    'attendance_id' => $existingAttendance?->id,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error checking duplicate manual attendance', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat memeriksa duplikasi absensi'
            ], 500);
        }
    }

    /**
     * Preview bulk attendance processing without changing data.
     */
    public function bulkPreview(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'operation' => 'required|in:create_missing,correct_existing',
                'attendance_list' => 'required|array|min:1|max:100',
                'attendance_list.*.user_id' => 'required|integer|exists:users,id',
                'attendance_list.*.tanggal' => 'required|date|before_or_equal:today',
                'attendance_list.*.jam_masuk' => 'nullable|date_format:H:i',
                'attendance_list.*.jam_pulang' => 'nullable|date_format:H:i',
                'attendance_list.*.status' => 'required|in:hadir,terlambat,izin,sakit,alpha',
                'attendance_list.*.keterangan' => 'nullable|string|max:500',
                'attendance_list.*.reason' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $operation = (string) $request->input('operation');
            $results = [];
            $readyCount = 0;
            $blockedCount = 0;

            foreach ($request->attendance_list as $index => $item) {
                $targetUser = User::find($item['user_id']);
                $message = null;
                $attendanceId = null;

                if (!$targetUser || !$this->manualAttendanceService->canManageAttendanceForUser(auth()->user(), $targetUser)) {
                    $message = 'Anda tidak memiliki izin untuk mengelola absensi user ini';
                } else {
                    $serviceValidation = $this->manualAttendanceService->validateAttendanceData($item);
                    if (!empty($serviceValidation)) {
                        $message = collect($serviceValidation)->flatten()->first();
                    } else {
                        $existingAttendance = Absensi::query()
                            ->where('user_id', $item['user_id'])
                            ->whereDate('tanggal', $item['tanggal'])
                            ->first();

                        if ($operation === 'create_missing') {
                            if ($existingAttendance) {
                                $message = 'Sudah ada data absensi pada tanggal tersebut';
                                $attendanceId = $existingAttendance->id;
                            }
                        } else {
                            if (!$existingAttendance) {
                                $message = 'Belum ada data absensi pada tanggal tersebut untuk dikoreksi';
                            } else {
                                $attendanceId = $existingAttendance->id;

                                if (!empty($item['jam_masuk']) || !empty($item['jam_pulang'])) {
                                    $previewData = [
                                        'jam_masuk' => $item['jam_masuk'] ?? null,
                                        'jam_pulang' => $item['jam_pulang'] ?? null,
                                    ];

                                    $checkoutError = $this->getCheckoutPreviewError($existingAttendance, $previewData);
                                    if ($checkoutError) {
                                        $message = $checkoutError;
                                    }
                                }
                            }
                        }
                    }
                }

                $isReady = $message === null;
                if ($isReady) {
                    $readyCount++;
                } else {
                    $blockedCount++;
                }

                $results[] = [
                    'index' => $index,
                    'success' => $isReady,
                    'attendance_id' => $attendanceId,
                    'message' => $isReady ? 'Siap diproses' : $message,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Pratinjau batch berhasil dibuat',
                'data' => [
                    'operation' => $operation,
                    'total' => count($request->attendance_list),
                    'ready_count' => $readyCount,
                    'blocked_count' => $blockedCount,
                    'results' => $results,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error previewing manual attendance batch', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat pratinjau batch absensi',
            ], 500);
        }
    }

    /**
     * Bulk create manual attendance records.
     */
    public function bulkCreate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'attendance_list' => 'required|array|min:1|max:100',
                'attendance_list.*.user_id' => 'required|integer|exists:users,id',
                'attendance_list.*.tanggal' => 'required|date|before_or_equal:today',
                'attendance_list.*.jam_masuk' => 'nullable|date_format:H:i',
                'attendance_list.*.jam_pulang' => 'nullable|date_format:H:i',
                'attendance_list.*.status' => 'required|in:hadir,terlambat,izin,sakit,alpha',
                'attendance_list.*.keterangan' => 'nullable|string|max:500',
                'attendance_list.*.reason' => 'nullable|string|max:255',
                'attendance_list.*.latitude_masuk' => 'nullable|numeric|between:-90,90',
                'attendance_list.*.longitude_masuk' => 'nullable|numeric|between:-180,180',
                'attendance_list.*.latitude_pulang' => 'nullable|numeric|between:-90,90',
                'attendance_list.*.longitude_pulang' => 'nullable|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($request->attendance_list as $index => $item) {
                if (empty($item['reason'])) {
                    $item['reason'] = 'Bulk create manual attendance';
                }

                $serviceValidation = $this->manualAttendanceService->validateAttendanceData($item);
                if (!empty($serviceValidation)) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'message' => 'Data tidak valid',
                        'errors' => $serviceValidation,
                    ];
                    $failedCount++;
                    continue;
                }

                $result = $this->manualAttendanceService->createManualAttendance($item, auth()->id());
                if ($result['success']) {
                    $attendance = $result['data'] instanceof Absensi ? $result['data'] : null;
                    $this->queueManualCreateWhatsappNotifications($attendance);

                    $successCount++;
                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'data' => $result['data'],
                    ];
                } else {
                    $failedCount++;
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'message' => $result['message'],
                    ];
                }
            }

            return response()->json([
                'success' => $failedCount === 0,
                'message' => $failedCount === 0
                    ? 'Semua data absensi manual berhasil dibuat'
                    : 'Sebagian data absensi gagal diproses',
                'data' => [
                    'total' => count($request->attendance_list),
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'results' => $results,
                ]
            ], $failedCount === 0 ? 201 : 207);
        } catch (\Exception $e) {
            Log::error('Error bulk creating manual attendance', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat data absensi manual secara massal'
            ], 500);
        }
    }

    /**
     * Bulk correct existing attendance records.
     */
    public function bulkCorrect(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'attendance_list' => 'required|array|min:1|max:100',
                'attendance_list.*.user_id' => 'required|integer|exists:users,id',
                'attendance_list.*.tanggal' => 'required|date|before_or_equal:today',
                'attendance_list.*.jam_masuk' => 'nullable|date_format:H:i',
                'attendance_list.*.jam_pulang' => 'nullable|date_format:H:i',
                'attendance_list.*.status' => 'required|in:hadir,terlambat,izin,sakit,alpha',
                'attendance_list.*.keterangan' => 'nullable|string|max:500',
                'attendance_list.*.reason' => 'nullable|string|max:255',
                'attendance_list.*.latitude_masuk' => 'nullable|numeric|between:-90,90',
                'attendance_list.*.longitude_masuk' => 'nullable|numeric|between:-180,180',
                'attendance_list.*.latitude_pulang' => 'nullable|numeric|between:-90,90',
                'attendance_list.*.longitude_pulang' => 'nullable|numeric|between:-180,180',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $results = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($request->attendance_list as $index => $item) {
                if (empty($item['reason'])) {
                    $item['reason'] = 'Bulk correction manual attendance';
                }

                $targetUser = User::find($item['user_id']);
                $serviceValidation = $this->manualAttendanceService->validateAttendanceData($item);
                if (!empty($serviceValidation)) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'message' => 'Data tidak valid',
                        'errors' => $serviceValidation,
                    ];
                    $failedCount++;
                    continue;
                }

                if (!$targetUser || !$this->manualAttendanceService->canManageAttendanceForUser(auth()->user(), $targetUser)) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'message' => 'Anda tidak memiliki izin untuk mengelola absensi user ini',
                    ];
                    $failedCount++;
                    continue;
                }

                $existingAttendance = Absensi::query()
                    ->where('user_id', $item['user_id'])
                    ->whereDate('tanggal', $item['tanggal'])
                    ->first();

                if (!$existingAttendance) {
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'message' => 'Belum ada data absensi pada tanggal tersebut untuk dikoreksi',
                    ];
                    $failedCount++;
                    continue;
                }

                $result = $this->manualAttendanceService->updateManualAttendance(
                    $existingAttendance->id,
                    $item,
                    auth()->id()
                );

                if ($result['success']) {
                    $successCount++;
                    $results[] = [
                        'index' => $index,
                        'success' => true,
                        'data' => $result['data'],
                    ];
                } else {
                    $failedCount++;
                    $results[] = [
                        'index' => $index,
                        'success' => false,
                        'message' => $result['message'],
                    ];
                }
            }

            return response()->json([
                'success' => $failedCount === 0,
                'message' => $failedCount === 0
                    ? 'Semua data absensi berhasil dikoreksi'
                    : 'Sebagian koreksi absensi gagal diproses',
                'data' => [
                    'total' => count($request->attendance_list),
                    'success_count' => $successCount,
                    'failed_count' => $failedCount,
                    'results' => $results,
                ],
            ], $failedCount === 0 ? 200 : 207);
        } catch (\Exception $e) {
            Log::error('Error bulk correcting manual attendance', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengoreksi data absensi secara massal',
            ], 500);
        }
    }

    /**
     * Get scope options for server incident attendance.
     */
    public function getIncidentOptions(Request $request)
    {
        try {
            if ($response = $this->rejectMobileIncidentRequest($request)) {
                return $response;
            }

            return response()->json([
                'success' => true,
                'data' => $this->manualAttendanceIncidentService->getScopeOptions(auth()->user()),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting manual attendance incident options', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil opsi insiden server',
            ], 500);
        }
    }

    /**
     * List recent server incident batches.
     */
    public function indexIncident(Request $request)
    {
        try {
            if ($response = $this->rejectMobileIncidentRequest($request)) {
                return $response;
            }

            $validator = Validator::make($request->all(), [
                'limit' => 'nullable|integer|min:1|max:20',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $this->manualAttendanceIncidentService->getRecentBatchesForUser(
                    auth()->user(),
                    (int) $request->input('limit', 8)
                ),
            ]);
        } catch (\Exception $e) {
            Log::error('Error listing manual attendance incident batches', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil riwayat batch insiden server',
            ], 500);
        }
    }

    /**
     * Preview server incident attendance processing.
     */
    public function previewIncident(Request $request)
    {
        try {
            if ($response = $this->rejectMobileIncidentRequest($request)) {
                return $response;
            }

            $validator = Validator::make($request->all(), [
                'tanggal' => 'required|date|before_or_equal:today',
                'scope_type' => 'required|in:all_manageable,classes,levels',
                'kelas_ids' => 'nullable|array|max:100',
                'kelas_ids.*' => 'integer|exists:kelas,id',
                'tingkat_ids' => 'nullable|array|max:50',
                'tingkat_ids.*' => 'integer|exists:tingkat,id',
                'status' => 'required|in:hadir,terlambat,izin,sakit,alpha',
                'jam_masuk' => 'nullable|date_format:H:i|required_if:status,terlambat',
                'jam_pulang' => 'nullable|date_format:H:i|after:jam_masuk',
                'keterangan' => 'nullable|string|max:500',
                'reason' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ($request->input('scope_type') === 'classes' && empty($request->input('kelas_ids', []))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih minimal satu kelas untuk scope kelas',
                    'errors' => [
                        'kelas_ids' => ['Pilih minimal satu kelas'],
                    ],
                ], 422);
            }

            if ($request->input('scope_type') === 'levels' && empty($request->input('tingkat_ids', []))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih minimal satu tingkat untuk scope tingkat',
                    'errors' => [
                        'tingkat_ids' => ['Pilih minimal satu tingkat'],
                    ],
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $this->manualAttendanceIncidentService->preview(auth()->user(), $request->all()),
            ]);
        } catch (\Exception $e) {
            Log::error('Error previewing manual attendance incident', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat pratinjau insiden server',
            ], 500);
        }
    }

    /**
     * Create and dispatch server incident attendance batch.
     */
    public function storeIncident(Request $request)
    {
        try {
            if ($response = $this->rejectMobileIncidentRequest($request)) {
                return $response;
            }

            $validator = Validator::make($request->all(), [
                'tanggal' => 'required|date|before_or_equal:today',
                'scope_type' => 'required|in:all_manageable,classes,levels',
                'kelas_ids' => 'nullable|array|max:100',
                'kelas_ids.*' => 'integer|exists:kelas,id',
                'tingkat_ids' => 'nullable|array|max:50',
                'tingkat_ids.*' => 'integer|exists:tingkat,id',
                'status' => 'required|in:hadir,terlambat,izin,sakit,alpha',
                'jam_masuk' => 'nullable|date_format:H:i|required_if:status,terlambat',
                'jam_pulang' => 'nullable|date_format:H:i|after:jam_masuk',
                'keterangan' => 'nullable|string|max:500',
                'reason' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            if ($request->input('scope_type') === 'classes' && empty($request->input('kelas_ids', []))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih minimal satu kelas untuk scope kelas',
                    'errors' => [
                        'kelas_ids' => ['Pilih minimal satu kelas'],
                    ],
                ], 422);
            }

            if ($request->input('scope_type') === 'levels' && empty($request->input('tingkat_ids', []))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pilih minimal satu tingkat untuk scope tingkat',
                    'errors' => [
                        'tingkat_ids' => ['Pilih minimal satu tingkat'],
                    ],
                ], 422);
            }

            $batch = $this->manualAttendanceIncidentService->createBatch(auth()->user(), $request->all());

            if (app()->environment('local') || config('queue.default') === 'sync') {
                ProcessManualAttendanceIncidentBatch::dispatchSync($batch->id);
                $batch->refresh();
            } else {
                ProcessManualAttendanceIncidentBatch::dispatch($batch->id);
            }

            return response()->json([
                'success' => true,
                'message' => 'Insiden server berhasil dijadwalkan',
                'data' => $this->manualAttendanceIncidentService->serializeBatch($batch),
            ], 202);
        } catch (\Exception $e) {
            Log::error('Error dispatching manual attendance incident batch', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menjadwalkan insiden server',
            ], 500);
        }
    }

    /**
     * Get server incident batch status.
     */
    public function showIncident(Request $request, int $id)
    {
        try {
            if ($response = $this->rejectMobileIncidentRequest($request)) {
                return $response;
            }

            $batch = $this->manualAttendanceIncidentService->getBatchForUser(auth()->user(), $id);

            return response()->json([
                'success' => true,
                'data' => $this->manualAttendanceIncidentService->serializeBatch($batch),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch insiden server tidak ditemukan',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error getting manual attendance incident batch', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'batch_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil status insiden server',
            ], 500);
        }
    }

    /**
     * Export server incident batch results for operator audit.
     */
    public function exportIncident(Request $request, int $id)
    {
        try {
            if ($response = $this->rejectMobileIncidentRequest($request)) {
                return $response;
            }

            $validator = Validator::make($request->all(), [
                'format' => 'nullable|in:xlsx,csv',
                'result_group' => 'nullable|in:all,created,skipped,failed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter export tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $batch = $this->manualAttendanceIncidentService->getBatchForUser(auth()->user(), $id);

            $format = strtolower((string) $request->input('format', 'xlsx'));
            $extension = $format === 'csv' ? 'csv' : 'xlsx';
            $writerType = $format === 'csv' ? ExcelFormat::CSV : ExcelFormat::XLSX;
            $resultGroup = strtolower((string) $request->input('result_group', 'all'));
            $resultCodes = $this->manualAttendanceIncidentService->resolveExportResultCodes($resultGroup);

            return Excel::download(
                new ManualAttendanceIncidentBatchExport($batch, $resultGroup, $resultCodes),
                $this->manualAttendanceIncidentService->getExportFilename($batch, $extension),
                $writerType
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Batch insiden server tidak ditemukan',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error exporting manual attendance incident batch', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'batch_id' => $id,
                'format' => $request->input('format', 'xlsx'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengekspor hasil batch insiden server',
            ], 500);
        }
    }

    private function queueManualCreateWhatsappNotifications(?Absensi $attendance): void
    {
        if (!$attendance instanceof Absensi) {
            return;
        }

        $events = [];
        if ($attendance->jam_masuk !== null) {
            $events[] = 'checkin';
        }
        if ($attendance->jam_pulang !== null) {
            $events[] = 'checkout';
        }

        foreach ($events as $event) {
            try {
                $job = new DispatchAttendanceWhatsappNotification((int) $attendance->id, $event, true);

                Log::info('Queueing WA notification for manual attendance create', [
                    'attendance_id' => (int) $attendance->id,
                    'event' => $event,
                    'queue' => $job->queue,
                    'connection' => config('queue.default'),
                ]);

                Queue::push($job);
            } catch (\Throwable $exception) {
                Log::warning('Failed to queue WA notification for manual attendance create', [
                    'attendance_id' => (int) $attendance->id,
                    'event' => $event,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function getCheckoutPreviewError(Absensi $attendance, array $updateData): ?string
    {
        try {
            $attendanceDate = $attendance->tanggal instanceof \Carbon\Carbon
                ? $attendance->tanggal->copy()->startOfDay()
                : \Carbon\Carbon::parse((string) $attendance->tanggal)->startOfDay();

            $jamMasukRaw = array_key_exists('jam_masuk', $updateData)
                ? $updateData['jam_masuk']
                : $attendance->jam_masuk;
            $jamPulangRaw = array_key_exists('jam_pulang', $updateData)
                ? $updateData['jam_pulang']
                : $attendance->jam_pulang;

            $jamMasuk = $jamMasukRaw
                ? \Carbon\Carbon::parse($attendanceDate->toDateString() . ' ' . trim((string) $jamMasukRaw))
                : null;
            $jamPulang = $jamPulangRaw
                ? \Carbon\Carbon::parse($attendanceDate->toDateString() . ' ' . trim((string) $jamPulangRaw))
                : null;

            if ($jamMasuk && $jamPulang && $jamPulang->lte($jamMasuk)) {
                return 'Jam pulang harus setelah jam masuk';
            }

            return null;
        } catch (\Exception) {
            return 'Format waktu tidak valid';
        }
    }

    private function rejectMobileIncidentRequest(Request $request): ?JsonResponse
    {
        $clientApp = strtolower(trim((string) $request->header('X-Client-App', '')));
        $clientPlatform = strtolower(trim((string) $request->header('X-Client-Platform', '')));

        if ($clientApp === 'mobileapp' || $clientPlatform === 'mobile') {
            return response()->json([
                'success' => false,
                'message' => 'Insiden Server hanya tersedia di aplikasi web.',
            ], 403);
        }

        return null;
    }

    /**
     * Get manual attendance by date range without pagination.
     */
    public function getByDateRange(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'user_id' => 'nullable|integer|exists:users,id',
                'status' => 'nullable|in:hadir,terlambat,izin,sakit,alpha',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filter tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $rows = $this->manualAttendanceService->getAttendanceExportData([
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'user_id' => $request->user_id,
                'status' => $request->status,
            ], auth()->user());

            return response()->json([
                'success' => true,
                'data' => $rows,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting manual attendance by date range', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil data berdasarkan rentang tanggal'
            ], 500);
        }
    }

    /**
     * Get attendance statistics for manual entries
     */
    public function statistics(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'date' => 'nullable|date',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'user_id' => 'nullable|integer|exists:users,id',
                'bucket' => 'nullable|in:manual,correction,auto_alpha',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $singleDate = $request->get('date');
            $startDate = $singleDate ?: $request->get('start_date', now()->startOfMonth()->toDateString());
            $endDate = $singleDate ?: $request->get('end_date', now()->endOfMonth()->toDateString());
            $userId = $request->get('user_id');

            $baseQuery = $this->manualAttendanceService->buildManualAttendanceQuery(
                auth()->user(),
                [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'user_id' => $userId,
                    'bucket' => $request->get('bucket', 'manual'),
                ]
            );

            // Get total count
            $totalManualEntries = (clone $baseQuery)->count();

            // Get statistics by status
            $byStatus = (clone $baseQuery)
                ->selectRaw('status, COUNT(*) as count')
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            $byStatus['alpha'] = (int) ($byStatus['alpha'] ?? 0) + (int) ($byStatus['alpa'] ?? 0);
            unset($byStatus['alpa']);

            // Get statistics by user
            $byUser = (clone $baseQuery)
                ->with('user:id,nama_lengkap')
                ->selectRaw('user_id, COUNT(*) as count')
                ->groupBy('user_id')
                ->get()
                ->map(function ($item) {
                    return [
                        'user_id' => $item->user_id,
                        'user_name' => $item->user->nama_lengkap ?? 'Unknown',
                        'count' => $item->count
                    ];
                })
                ->toArray();

            // Get recent entries (separate query without GROUP BY)
            $recentEntries = (clone $baseQuery)
                ->with(['user:id,nama_lengkap', 'auditLogs.performer:id,nama_lengkap'])
                ->orderByDesc('created_at')
                ->limit(10)
                ->get()
                ->toArray();

            $alphaMinutes = $this->manualAttendanceService->calculateAlphaMinutesFromQuery($baseQuery);
            $bySource = [
                'manual' => (clone $baseQuery)
                    ->where('is_manual', true)
                    ->whereNull('izin_id')
                    ->count(),
                'realtime' => (clone $baseQuery)
                    ->where('is_manual', false)
                    ->whereNotIn('status', ['alpha', 'alpa'])
                    ->whereNull('izin_id')
                    ->count(),
                'auto_alpha' => (clone $baseQuery)
                    ->where('is_manual', false)
                    ->whereIn('status', ['alpha', 'alpa'])
                    ->count(),
                'leave_approval' => (clone $baseQuery)
                    ->whereNotNull('izin_id')
                    ->count(),
            ];

            $stats = [
                'bucket' => $request->get('bucket', 'manual'),
                'total_entries' => $totalManualEntries,
                'total_manual_entries' => $totalManualEntries,
                'by_status' => $byStatus,
                'by_source' => $bySource,
                'alpa_menit' => $alphaMinutes,
                'by_user' => $byUser,
                'recent_entries' => $recentEntries,
                'date_range' => [
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting manual attendance statistics', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil statistik'
            ], 500);
        }
    }

    /**
     * Delete manual attendance record.
     */
    public function destroy(Request $request, int $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $result = $this->manualAttendanceService->deleteManualAttendance(
                $id,
                $request->input('reason'),
                auth()->id()
            );

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message']
                ], 400);
            }

            return response()->json([
                'success' => true,
                'message' => $result['message']
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting manual attendance', [
                'error' => $e->getMessage(),
                'attendance_id' => $id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat menghapus absensi'
            ], 500);
        }
    }

    /**
     * Export manual attendance history.
     */
    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'user_id' => 'nullable|integer|exists:users,id',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'bucket' => 'nullable|in:manual,correction,auto_alpha',
                'status' => 'nullable|in:hadir,terlambat,izin,sakit,alpha',
                'format' => 'nullable|in:excel,csv,json',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Filter tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $format = $request->input('format', 'excel');
            $rows = $this->manualAttendanceService->getAttendanceExportData($request->all(), auth()->user());

            if ($format === 'json') {
                return response()->json([
                    'success' => true,
                    'data' => $rows
                ]);
            }

            $extension = $format === 'csv' ? 'csv' : 'xls';
            $contentType = $format === 'csv'
                ? 'text/csv; charset=UTF-8'
                : 'application/vnd.ms-excel; charset=UTF-8';
            $filename = 'manual-attendance-' . now()->format('Y-m-d') . '.' . $extension;

            return response()->streamDownload(function () use ($rows) {
                $handle = fopen('php://output', 'w');
                fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

                fputcsv($handle, [
                    'ID',
                    'Tanggal',
                    'User ID',
                    'Nama Lengkap',
                    'Status',
                    'Jam Masuk',
                    'Jam Pulang',
                    'Keterangan',
                    'Dibuat Pada',
                ]);

                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row['id'] ?? null,
                        $row['tanggal'] ?? null,
                        $row['user_id'] ?? null,
                        $row['user']['nama_lengkap'] ?? null,
                        $row['status'] ?? null,
                        $row['jam_masuk'] ?? null,
                        $row['jam_pulang'] ?? null,
                        $row['keterangan'] ?? null,
                        $row['created_at'] ?? null,
                    ]);
                }

                fclose($handle);
            }, $filename, [
                'Content-Type' => $contentType,
            ]);
        } catch (\Exception $e) {
            Log::error('Error exporting manual attendance', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'filters' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengekspor data absensi manual'
            ], 500);
        }
    }

    /**
     * Get audit logs for specific attendance
     */
    public function auditLogs(Request $request, $attendanceId)
    {
        try {
            $auditLogs = \App\Models\AttendanceAuditLog::with('performer:id,nama_lengkap')
                ->where('attendance_id', $attendanceId)
                ->orderBy('performed_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $auditLogs
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting audit logs', [
                'error' => $e->getMessage(),
                'attendance_id' => $attendanceId,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengambil log audit'
            ], 500);
        }
    }
}
