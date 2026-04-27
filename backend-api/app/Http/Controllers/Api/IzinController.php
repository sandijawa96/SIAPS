<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\DispatchIzinApproverNotifications;
use App\Jobs\DispatchIzinDecisionNotification;
use App\Jobs\DispatchIzinWhatsappNotification;
use App\Models\Absensi;
use App\Models\AttendanceAuditLog;
use App\Models\Izin;
use App\Models\Notification;
use App\Models\User;
use App\Models\WhatsappGateway;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;
use App\Support\RoleAccessMatrix;
use App\Support\RoleDataScope;
use App\Support\RoleNames;
use App\Services\AttendanceTimeService;

class IzinController extends Controller
{
    private const SUBMIT_IDEMPOTENCY_CACHE_PREFIX = 'izin:submit:idempotency:';
    private const SUBMIT_IDEMPOTENCY_TTL_MINUTES = 1440;
    private const OBSERVABILITY_DEFAULT_HOURS = 24;
    private const OBSERVABILITY_DEFAULT_FAILURE_LIMIT = 5;

    public function __construct(
        private readonly AttendanceTimeService $attendanceTimeService
    ) {
        // Endpoint izin dipakai oleh web (Sanctum) dan mobile (JWT via guard api).
        $this->middleware('auth:sanctum,api');
    }

    /**
     * Siswa/Pegawai mengajukan izin (Mobile App & Web)
     */
    public function store(Request $request)
    {
        $startedAt = microtime(true);
        $user = Auth::user();
        $isSiswa = $user->hasRole(RoleNames::aliases(RoleNames::SISWA));

        if (!$isSiswa) {
            return response()->json([
                'success' => false,
                'message' => 'Fitur pengajuan izin hanya tersedia untuk siswa'
            ], 403);
        }

        // Determine allowed jenis_izin based on user role
        $allowedJenisIzin = $this->getAllowedJenisIzin($user);

        $validator = Validator::make($request->all(), [
            'jenis_izin' => 'required|in:' . implode(',', $allowedJenisIzin),
            'tanggal_mulai' => 'required|date|after_or_equal:today',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'alasan' => 'required|string|max:500',
            'client_request_id' => 'nullable|string|max:100',
            // Lampiran dibuat ringan: sakit multi-hari wajib, lainnya opsional.
            'dokumen_pendukung' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:5120' // 5MB
        ], [
            'dokumen_pendukung.required' => 'Lampiran pendukung wajib diunggah untuk jenis izin ini',
            'dokumen_pendukung.file' => 'Lampiran pendukung tidak valid',
            'dokumen_pendukung.mimes' => 'Format lampiran harus JPG, JPEG, PNG, WEBP, atau PDF',
            'dokumen_pendukung.max' => 'Ukuran lampiran maksimal 5MB',
        ]);
        $validator->sometimes(
            'dokumen_pendukung',
            'required',
            fn ($input): bool => $this->shouldRequireEvidence(
                (string) ($input->jenis_izin ?? ''),
                $input->tanggal_mulai ?? null,
                $input->tanggal_selesai ?? null
            )
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        if (!$user->hasPermissionTo('submit_izin')) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses untuk mengajukan izin'
            ], 403);
        }

        $clientRequestId = trim((string) $request->input('client_request_id', ''));
        if ($clientRequestId !== '') {
            $existingIdempotentResponse = $this->resolveExistingSubmitResponse((int) $user->id, $clientRequestId);
            if ($existingIdempotentResponse) {
                return $existingIdempotentResponse;
            }
        }

        // Cek apakah user sudah memiliki izin aktif (pending/approved) untuk tanggal yang overlap.
        // Rejected boleh ajukan ulang.
        $existingIzin = Izin::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function($query) use ($request) {
                $query->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
                    ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
                    ->orWhere(function($q) use ($request) {
                        $q->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                            ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
                    });
            })
            ->exists();

        if ($existingIzin) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah memiliki pengajuan izin aktif pada rentang tanggal tersebut'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $kelasId = null;
            if ($isSiswa) {
                $kelasId = $this->resolveCurrentStudentClassId($user);
                if (!$kelasId) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Siswa belum terdaftar pada kelas aktif'
                    ], 422);
                }
            }

            $data = [
                'user_id' => $user->id,
                'kelas_id' => $kelasId,
                'jenis_izin' => $request->jenis_izin,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'alasan' => $request->alasan,
                'status' => 'pending'
            ];

            // Handle file upload
            if ($request->hasFile('dokumen_pendukung')) {
                $file = $request->file('dokumen_pendukung');
                $filename = time() . '_' . $user->id . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('izin_documents', $filename, 'public');
                $data['dokumen_pendukung'] = $path;
            }

            $izin = Izin::create($data);

            DB::commit();

            $this->rememberSubmitRequestId((int) $user->id, $clientRequestId, (int) $izin->id);
            $this->notifyApproversForNewIzin($izin);
            $this->logIzinActionPerformance('submit', $startedAt, [
                'user_id' => (int) $user->id,
                'izin_id' => (int) $izin->id,
                'status' => 'success',
                'jenis_izin' => (string) $izin->jenis_izin,
                'has_document' => !empty($data['dokumen_pendukung']),
                'client_request_id_present' => $clientRequestId !== '',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil dikirim dan sedang ditinjau',
                'data' => $this->serializeIzin(
                    $izin->load(['user:id,nama_lengkap,nisn', 'kelas:id,nama_kelas'])
                )
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Gagal mengajukan izin', [
                'user_id' => $user->id ?? null,
                'jenis_izin' => $request->jenis_izin,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan izin'
            ], 500);
        }
    }

    /**
     * Get izin siswa (Mobile App)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        
        $query = Izin::with(['user:id,nama_lengkap,nisn', 'kelas:id,nama_kelas'])
            ->where('user_id', $user->id);

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->whereDate('tanggal_mulai', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('tanggal_selesai', '<=', $request->end_date);
        }

        $izinList = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 10))
            ->through(fn (Izin $izin) => $this->serializeIzin($izin, $user));

        return response()->json([
            'success' => true,
            'data' => $izinList
        ]);
    }

    /**
     * Get detail izin
     */
    public function show($id)
    {
        $user = Auth::user();
        
        $izin = Izin::with([
            'user:id,nama_lengkap,nisn',
            'kelas:id,nama_kelas',
            'approvedBy:id,nama_lengkap',
            'rejectedBy:id,nama_lengkap'
        ])->find($id);

        if (!$izin) {
            return response()->json([
                'success' => false,
                'message' => 'Izin tidak ditemukan'
            ], 404);
        }

        if (!$this->canAccessIzinDetail($user, $izin)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke izin ini'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->serializeIzin($izin, $user)
        ]);
    }

    /**
     * Cancel izin (hanya jika status pending)
     */
    public function cancel($id)
    {
        $user = Auth::user();
        
        $izin = Izin::where('id', $id)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if (!$izin) {
            return response()->json([
                'success' => false,
                'message' => 'Izin tidak ditemukan atau tidak dapat dibatalkan'
            ], 404);
        }

        try {
            // Delete document if exists
            if ($izin->dokumen_pendukung) {
                Storage::disk('public')->delete($izin->dokumen_pendukung);
            }

            $izin->delete();

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin berhasil dibatalkan'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan izin'
            ], 500);
        }
    }

    /**
     * Get izin untuk approval (Web Admin & Mobile App untuk Wali Kelas/Staff)
     */
    public function getForApproval(Request $request)
    {
        $user = Auth::user();
        $type = $request->get('type', 'siswa'); // Default to siswa

        if ($type !== 'siswa') {
            return response()->json([
                'success' => false,
                'message' => 'Approval izin pegawai dinonaktifkan. Gunakan type=siswa.'
            ], 422);
        }

        // Explicit role gate for student leave approval:
        // Super Admin, Admin, Wakasek Kesiswaan, and Wali Kelas.
        if (!$this->canAccessStudentIzinApproval($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Approval izin siswa hanya untuk Super Admin, Admin, Wakasek Kesiswaan, atau Wali Kelas'
            ], 403);
        }

        $query = Izin::with([
            'user:id,nama_lengkap,nisn,email',
            'kelas:id,nama_kelas,tingkat_id',
            'kelas.tingkat:id,nama'
        ]);

        $query->whereHas('user', function($q) {
            $q->whereHas('roles', function($roleQuery) {
                $roleQuery->whereIn('name', RoleAccessMatrix::roleQueryNames(RoleNames::SISWA));
            });
        });

        // Filter data scope
        if ($user->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS))) {
            // Wali kelas can only see their own class
            $kelasIds = $user->kelasWali()->pluck('id');
            $query->whereIn('kelas_id', $kelasIds);
        }

        $summaryQuery = clone $query;
        $this->applyApprovalListFilters($summaryQuery, $request, includeStatus: false);
        $pendingReviewSummary = $this->buildPendingReviewSummary($summaryQuery);

        $this->applyApprovalListFilters($query, $request, includeStatus: true);

        $izinList = $query
            ->orderBy('tanggal_mulai', 'asc')
            ->orderBy('created_at', 'asc')
            ->paginate($request->get('per_page', 15))
            ->through(fn (Izin $izin) => $this->serializeIzin($izin, $user));

        return response()->json([
            'success' => true,
            'data' => $izinList,
            'meta' => [
                'pending_review_summary' => $pendingReviewSummary,
            ],
        ]);
    }

    /**
     * Approve izin
     */
    public function approve(Request $request, $id)
    {
        $startedAt = microtime(true);
        $user = Auth::user();

        $izin = Izin::with('user')->find($id);

        if (!$izin) {
            return response()->json([
                'success' => false,
                'message' => 'Izin tidak ditemukan'
            ], 404);
        }

        if ($izin->status !== 'pending') {
            return $this->alreadyProcessedResponse($izin);
        }

        // Explicit student-leave approval rule.
        if (!$this->isStudentRoleUser($izin->user)) {
            return response()->json([
                'success' => false,
                'message' => 'Approval izin pegawai dinonaktifkan'
            ], 422);
        }

        if (!$this->canApproveStudentIzin($user, $izin)) {
            return response()->json([
                'success' => false,
                'message' => 'Approval izin siswa hanya untuk Super Admin, Admin, Wakasek Kesiswaan, atau Wali Kelas pada kelas terkait'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'catatan_approval' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $lockedIzin = Izin::with('user')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$lockedIzin) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Izin tidak ditemukan'
                ], 404);
            }

            if ($lockedIzin->status !== 'pending') {
                DB::rollBack();

                return $this->alreadyProcessedResponse($lockedIzin);
            }

            // Approve izin
            $lockedIzin->approve($user->id, $request->catatan_approval);
            $attendanceStatus = $lockedIzin->jenis_izin === 'sakit' ? 'sakit' : 'izin';
            $resolvedKelasId = $lockedIzin->kelas_id ?: $this->resolveCurrentStudentClassId($lockedIzin->user);

            $approvalSummary = [
                'processed_dates' => [],
                'created_attendance_dates' => [],
                'updated_attendance_dates' => [],
                'skipped_attendance_not_required_dates' => [],
                'skipped_non_working_days' => [],
                'skipped_existing_attendance_dates' => [],
            ];

            // Update absensi for each day in the izin period
            $startDate = Carbon::parse($lockedIzin->tanggal_mulai)->startOfDay();
            $endDate = Carbon::parse($lockedIzin->tanggal_selesai)->startOfDay();

            for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
                $dateKey = $date->toDateString();

                if (!$this->attendanceTimeService->isAttendanceRequiredOnDate($lockedIzin->user, $date->copy())) {
                    $approvalSummary['skipped_attendance_not_required_dates'][] = $dateKey;
                    continue;
                }

                if (!$this->attendanceTimeService->isWorkingDayForDate($lockedIzin->user, $date->copy())) {
                    $approvalSummary['skipped_non_working_days'][] = $dateKey;
                    continue;
                }

                $result = $this->applyApprovedLeaveAttendance(
                    $lockedIzin,
                    $date->copy(),
                    $attendanceStatus,
                    $resolvedKelasId,
                    (int) $user->id
                );

                $approvalSummary['processed_dates'][] = $dateKey;

                if (($result['action'] ?? null) === 'created') {
                    $approvalSummary['created_attendance_dates'][] = $dateKey;
                } elseif (($result['action'] ?? null) === 'updated') {
                    $approvalSummary['updated_attendance_dates'][] = $dateKey;
                } elseif (($result['action'] ?? null) === 'skipped_existing_attendance') {
                    $approvalSummary['skipped_existing_attendance_dates'][] = $dateKey;
                }
            }

            $notificationCleanup = $this->closePendingApproverNotificationsForIzin(
                $lockedIzin,
                'approved',
                (int) $user->id
            );

            DB::commit();

            $this->notifyStudentApprovalResult($lockedIzin, 'approved');
            $this->logIzinActionPerformance('approve', $startedAt, [
                'izin_id' => (int) $lockedIzin->id,
                'approver_id' => (int) $user->id,
                'status' => 'success',
                'processed_count' => count($approvalSummary['processed_dates']),
                'created_count' => count($approvalSummary['created_attendance_dates']),
                'updated_count' => count($approvalSummary['updated_attendance_dates']),
                'skipped_attendance_not_required_count' => count($approvalSummary['skipped_attendance_not_required_dates']),
                'skipped_non_working_count' => count($approvalSummary['skipped_non_working_days']),
                'skipped_existing_attendance_count' => count($approvalSummary['skipped_existing_attendance_dates']),
            ]);

            $refreshedIzin = $lockedIzin->fresh(['user:id,nama_lengkap,nisn', 'approvedBy:id,nama_lengkap']);
            $responseData = $refreshedIzin ? $this->serializeIzin($refreshedIzin, $user) : [];
            $responseData['approval_summary'] = [
                ...$approvalSummary,
                'processed_count' => count($approvalSummary['processed_dates']),
                'created_count' => count($approvalSummary['created_attendance_dates']),
                'updated_count' => count($approvalSummary['updated_attendance_dates']),
                'skipped_attendance_not_required_count' => count($approvalSummary['skipped_attendance_not_required_dates']),
                'skipped_non_working_count' => count($approvalSummary['skipped_non_working_days']),
                'skipped_existing_attendance_count' => count($approvalSummary['skipped_existing_attendance_dates']),
            ];
            $responseData['notification_cleanup'] = $notificationCleanup;

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin disetujui',
                'data' => $responseData,
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Gagal menyetujui izin', [
                'izin_id' => $id,
                'approver_id' => $user->id ?? null,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui izin'
            ], 500);
        }
    }

    /**
     * Reject izin
     */
    public function reject(Request $request, $id)
    {
        $startedAt = microtime(true);
        $user = Auth::user();

        $izin = Izin::with('user')->find($id);

        if (!$izin) {
            return response()->json([
                'success' => false,
                'message' => 'Izin tidak ditemukan'
            ], 404);
        }

        if ($izin->status !== 'pending') {
            return $this->alreadyProcessedResponse($izin);
        }

        // Explicit student-leave approval rule.
        if (!$this->isStudentRoleUser($izin->user)) {
            return response()->json([
                'success' => false,
                'message' => 'Approval izin pegawai dinonaktifkan'
            ], 422);
        }

        if (!$this->canApproveStudentIzin($user, $izin)) {
            return response()->json([
                'success' => false,
                'message' => 'Approval izin siswa hanya untuk Super Admin, Admin, Wakasek Kesiswaan, atau Wali Kelas pada kelas terkait'
            ], 403);
        }

        try {
            $validator = Validator::make($request->all(), [
                'catatan_approval' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Alasan penolakan harus diisi',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $lockedIzin = Izin::with('user')
                ->where('id', $id)
                ->lockForUpdate()
                ->first();

            if (!$lockedIzin) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Izin tidak ditemukan'
                ], 404);
            }

            if ($lockedIzin->status !== 'pending') {
                DB::rollBack();

                return $this->alreadyProcessedResponse($lockedIzin);
            }

            // Reject izin
            $lockedIzin->reject($user->id, $request->catatan_approval);
            $notificationCleanup = $this->closePendingApproverNotificationsForIzin(
                $lockedIzin,
                'rejected',
                (int) $user->id
            );

            DB::commit();

            $this->notifyStudentApprovalResult($lockedIzin, 'rejected');
            $this->logIzinActionPerformance('reject', $startedAt, [
                'izin_id' => (int) $lockedIzin->id,
                'approver_id' => (int) $user->id,
                'status' => 'success',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengajuan izin ditolak',
                'data' => [
                    ...($lockedIzin->fresh(['user:id,nama_lengkap,nisn', 'rejectedBy:id,nama_lengkap'])
                        ? $this->serializeIzin(
                            $lockedIzin->fresh(['user:id,nama_lengkap,nisn', 'rejectedBy:id,nama_lengkap']),
                            $user
                        )
                        : []),
                    'notification_cleanup' => $notificationCleanup,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Gagal menolak izin', [
                'izin_id' => $id,
                'approver_id' => $user->id ?? null,
                'duration_ms' => $this->elapsedMilliseconds($startedAt),
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Gagal menolak izin'
            ], 500);
        }
    }

    /**
     * Download dokumen pendukung
     */
    public function downloadDocument($id)
    {
        $user = Auth::user();
        
        $izin = Izin::with('user')->find($id);

        if (!$izin) {
            return response()->json([
                'success' => false,
                'message' => 'Izin tidak ditemukan'
            ], 404);
        }

        if (!$this->canAccessIzinDetail($user, $izin)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke dokumen ini'
            ], 403);
        }

        if (!$izin->dokumen_pendukung || !Storage::disk('public')->exists($izin->dokumen_pendukung)) {
            return response()->json([
                'success' => false,
                'message' => 'Dokumen tidak ditemukan'
            ], 404);
        }

        return Storage::disk('public')->download($izin->dokumen_pendukung);
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics(Request $request)
    {
        $user = Auth::user();
        $type = $request->get('type', 'siswa');
        if ($type !== 'siswa') {
            return response()->json([
                'success' => false,
                'message' => 'Statistik izin pegawai dinonaktifkan. Gunakan type=siswa.'
            ], 422);
        }

        if ($this->canAccessStudentIzinApproval($user)) {
            $query = $this->buildLeaveTypeQuery(true);

            if ($user->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS))) {
                $kelasIds = $user->kelasWali()->pluck('id')->all();
                $query->whereIn('kelas_id', $kelasIds);
            }

            $stats = $this->buildIzinStats($query);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        }

        if ($user->hasPermissionTo('view_all_izin')) {
            $stats = $this->buildIzinStats($this->buildLeaveTypeQuery(true));

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        }

        if ($user->hasPermissionTo('view_kelas_izin')) {
            $query = $this->buildLeaveTypeQuery(true);
            $this->applyClassScopeToIzinQuery($query, $user);

            $stats = $this->buildIzinStats($query);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        }

        $stats = $this->buildIzinStats(
            $this->buildLeaveTypeQuery(true)->where('user_id', $user->id)
        );

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    public function getObservability(Request $request)
    {
        $user = Auth::user();
        if (
            !$this->canAccessStudentIzinApproval($user)
            && !$user->hasPermissionTo('view_all_izin')
            && !$user->hasPermissionTo('view_kelas_izin')
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke observability izin',
            ], 403);
        }

        $validated = $request->validate([
            'hours' => 'nullable|integer|min:1|max:168',
            'limit' => 'nullable|integer|min:1|max:10',
        ]);

        $hours = (int) ($validated['hours'] ?? self::OBSERVABILITY_DEFAULT_HOURS);
        $limit = (int) ($validated['limit'] ?? self::OBSERVABILITY_DEFAULT_FAILURE_LIMIT);
        $windowStart = now()->subHours($hours);

        $queueMetrics = $this->collectIzinQueueMetrics();
        $failureMetrics = $this->collectIzinFailureMetrics($windowStart, $limit);
        $deliveryMetrics = $this->collectIzinDeliveryMetrics($windowStart, $limit);

        $issues = [];
        if (($failureMetrics['summary']['failed_jobs_window_count'] ?? 0) > 0) {
            $issues[] = 'Ada job izin gagal pada window observasi';
        }
        if (($deliveryMetrics['whatsapp']['failed_window'] ?? 0) > 0) {
            $issues[] = 'Ada pengiriman WhatsApp izin yang gagal';
        }
        if (($queueMetrics['notifications']['pending'] ?? 0) > 25 || ($queueMetrics['whatsapp']['pending'] ?? 0) > 25) {
            $issues[] = 'Antrian job izin mulai menumpuk';
        }

        $healthStatus = $issues === []
            ? 'healthy'
            : ((($failureMetrics['summary']['failed_jobs_window_count'] ?? 0) > 0 || ($deliveryMetrics['whatsapp']['failed_window'] ?? 0) > 0)
                ? 'degraded'
                : 'warning');

        return response()->json([
            'success' => true,
            'data' => [
                'generated_at' => now()->toISOString(),
                'window_hours' => $hours,
                'health' => [
                    'status' => $healthStatus,
                    'issues' => $issues,
                ],
                'queue' => $queueMetrics,
                'delivery' => $deliveryMetrics,
                'failures' => $failureMetrics,
            ],
        ]);
    }

    /**
     * Get daftar jenis izin berdasarkan target pengguna.
     */
    public function getJenisOptions(Request $request, ?string $type = null)
    {
        $user = Auth::user();
        $targetType = strtolower((string) ($type ?? $request->get('type', 'auto')));

        if ($targetType === 'siswa') {
            $jenis = Izin::studentJenisIzin();
        } elseif ($targetType === 'pegawai') {
            return response()->json([
                'success' => false,
                'message' => 'Jenis izin pegawai dinonaktifkan'
            ], 422);
        } else {
            $jenis = $this->getAllowedJenisIzin($user);
        }

        $options = collect($jenis)->map(function (string $value) {
            return $this->buildJenisIzinOption($value);
        })->values();

        return response()->json([
            'success' => true,
            'data' => $options,
        ]);
    }

    /**
     * Get allowed jenis izin based on user role
     */
    private function serializeIzin(Izin $izin, ?User $viewer = null): array
    {
        if (!$izin->relationLoaded('user')) {
            $izin->load('user');
        }

        $impact = $this->buildLeaveImpactSummary($izin);
        $evidencePolicy = $this->buildEvidencePolicy(
            (string) $izin->jenis_izin,
            (int) ($impact['requested_day_count'] ?? 1)
        );
        $pendingReviewState = $this->buildPendingReviewState($izin);

        return array_merge($izin->toArray(), [
            'requested_day_count' => (int) ($impact['requested_day_count'] ?? 1),
            'school_day_count' => (int) ($impact['school_day_count'] ?? 0),
            'non_working_day_count' => (int) ($impact['non_working_day_count'] ?? 0),
            'school_days_affected' => (int) ($impact['school_day_count'] ?? 0),
            'non_working_days_skipped' => (int) ($impact['non_working_day_count'] ?? 0),
            'school_day_dates' => $impact['school_day_dates'] ?? [],
            'non_working_day_dates' => $impact['non_working_day_dates'] ?? [],
            'impact_summary' => $impact,
            'evidence_required' => (bool) ($evidencePolicy['required'] ?? false),
            'evidence_rule' => $evidencePolicy['rule'] ?? 'optional',
            'evidence_hint' => $evidencePolicy['hint'] ?? '',
            'evidence_policy' => $evidencePolicy,
            'pending_review_state' => $pendingReviewState['state'],
            'pending_review_label' => $pendingReviewState['label'],
            'is_pending_overdue' => $pendingReviewState['state'] === 'overdue',
            'pending_overdue_days' => (int) ($pendingReviewState['overdue_days'] ?? 0),
        ]);
    }

    private function buildLeaveImpactSummary(Izin $izin): array
    {
        $user = $izin->relationLoaded('user') ? $izin->user : null;
        if (!$user instanceof User) {
            $user = User::query()->find($izin->user_id);
        }

        $start = Carbon::parse($izin->tanggal_mulai)->startOfDay();
        $end = Carbon::parse($izin->tanggal_selesai)->startOfDay();

        $schoolDayDates = [];
        $nonWorkingDates = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $dateKey = $date->toDateString();

            if ($user instanceof User && $this->attendanceTimeService->isWorkingDay($user, $date->copy())) {
                $schoolDayDates[] = $dateKey;
                continue;
            }

            $nonWorkingDates[] = $dateKey;
        }

        return [
            'requested_day_count' => $start->diffInDays($end) + 1,
            'school_day_count' => count($schoolDayDates),
            'non_working_day_count' => count($nonWorkingDates),
            'school_day_dates' => $schoolDayDates,
            'non_working_day_dates' => $nonWorkingDates,
            'summary_text' => count($schoolDayDates) > 0
                ? sprintf(
                    '%d hari sekolah terdampak, %d hari non-sekolah dilewati',
                    count($schoolDayDates),
                    count($nonWorkingDates)
                )
                : 'Tidak ada hari sekolah yang terdampak pada rentang ini',
        ];
    }

    private function shouldRequireEvidence(string $jenisIzin, mixed $tanggalMulai, mixed $tanggalSelesai): bool
    {
        $normalizedJenis = strtolower(trim($jenisIzin));
        if ($normalizedJenis !== 'sakit') {
            return false;
        }

        try {
            $start = Carbon::parse((string) $tanggalMulai)->startOfDay();
            $end = Carbon::parse((string) $tanggalSelesai)->startOfDay();
        } catch (\Throwable) {
            return false;
        }

        return $start->diffInDays($end) + 1 > 1;
    }

    private function buildEvidencePolicy(string $jenisIzin, int $requestedDayCount): array
    {
        $normalizedJenis = strtolower(trim($jenisIzin));
        $required = $normalizedJenis === 'sakit' && $requestedDayCount > 1;

        $hint = match ($normalizedJenis) {
            'sakit' => $required
                ? 'Lampiran wajib untuk sakit lebih dari 1 hari. File dapat berupa foto atau PDF.'
                : 'Lampiran opsional untuk sakit 1 hari. Jika ada surat/nota pemeriksaan, unggah agar review lebih cepat.',
            'dispensasi', 'tugas_sekolah' => 'Lampiran opsional. Unggah jika ada surat tugas, memo, atau bukti kegiatan.',
            default => 'Lampiran opsional. Unggah jika diperlukan untuk memperjelas alasan pengajuan.',
        };

        return [
            'required' => $required,
            'rule' => $required ? 'required_if_multi_day' : 'optional',
            'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            'allowed_label' => 'JPG, JPEG, PNG, WEBP, atau PDF',
            'hint' => $hint,
        ];
    }

    /**
     * @return array{state:string|null,label:string|null,overdue_days:int}
     */
    private function buildPendingReviewState(Izin $izin): array
    {
        $status = strtolower(trim((string) $izin->status));
        if ($status !== 'pending') {
            return [
                'state' => null,
                'label' => null,
                'overdue_days' => 0,
            ];
        }

        $startDate = Carbon::parse((string) $izin->tanggal_mulai)->startOfDay();
        $today = now()->startOfDay();

        if ($startDate->lt($today)) {
            $overdueDays = $startDate->diffInDays($today);

            return [
                'state' => 'overdue',
                'label' => $overdueDays === 1
                    ? 'Terlambat 1 hari'
                    : "Terlambat {$overdueDays} hari",
                'overdue_days' => $overdueDays,
            ];
        }

        if ($startDate->equalTo($today)) {
            return [
                'state' => 'due_today',
                'label' => 'Mulai hari ini',
                'overdue_days' => 0,
            ];
        }

        return [
            'state' => 'upcoming',
            'label' => null,
            'overdue_days' => 0,
        ];
    }

    private function buildJenisIzinOption(string $value): array
    {
        $normalizedValue = strtolower(trim($value));
        $evidencePolicy = $this->buildEvidencePolicy($value, $normalizedValue === 'sakit' ? 2 : 1);
        if ($normalizedValue === 'sakit') {
            $evidencePolicy['required'] = false;
            $evidencePolicy['rule'] = 'required_if_multi_day';
        }
        $meta = match ($normalizedValue) {
            'sakit' => [
                'description' => 'Tidak masuk karena kondisi kesehatan.',
                'group' => 'kesehatan',
                'group_label' => 'Kesehatan',
            ],
            'izin' => [
                'description' => 'Keperluan pribadi mendesak di luar sekolah.',
                'group' => 'pribadi',
                'group_label' => 'Pribadi & Keluarga',
            ],
            'keperluan_keluarga' => [
                'description' => 'Mendampingi atau menghadiri kebutuhan keluarga inti.',
                'group' => 'pribadi',
                'group_label' => 'Pribadi & Keluarga',
            ],
            'dispensasi' => [
                'description' => 'Kegiatan resmi dengan persetujuan atau penugasan sekolah.',
                'group' => 'sekolah',
                'group_label' => 'Kegiatan Sekolah',
            ],
            'tugas_sekolah' => [
                'description' => 'Penugasan sekolah di luar kelas atau lokasi belajar biasa.',
                'group' => 'sekolah',
                'group_label' => 'Kegiatan Sekolah',
            ],
            default => [
                'description' => null,
                'group' => 'lainnya',
                'group_label' => 'Lainnya',
            ],
        };

        return [
            'value' => $value,
            'label' => Izin::getJenisIzinLabel($value),
            'description' => $meta['description'],
            'group' => $meta['group'],
            'group_label' => $meta['group_label'],
            'evidence_policy' => $evidencePolicy,
        ];
    }

    private function logIzinActionPerformance(string $action, float $startedAt, array $context = []): void
    {
        Log::info('Izin action completed', array_merge($context, [
            'action' => $action,
            'duration_ms' => $this->elapsedMilliseconds($startedAt),
        ]));
    }

    private function elapsedMilliseconds(float $startedAt): int
    {
        return max(0, (int) round((microtime(true) - $startedAt) * 1000));
    }

    private function collectIzinQueueMetrics(): array
    {
        $connection = (string) config('queue.default', 'sync');
        $notificationQueue = DispatchIzinApproverNotifications::QUEUE_NAME;
        $whatsappQueue = DispatchIzinWhatsappNotification::QUEUE_NAME;

        return [
            'connection' => $connection,
            'notifications' => $this->inspectQueueDepth($connection, $notificationQueue),
            'whatsapp' => $this->inspectQueueDepth($connection, $whatsappQueue),
        ];
    }

    private function inspectQueueDepth(string $connection, string $queueName): array
    {
        $metrics = [
            'queue' => $queueName,
            'pending' => 0,
            'reserved' => 0,
            'delayed' => 0,
            'available' => false,
            'source' => $connection,
        ];

        try {
            if ($connection === 'database') {
                $metrics['pending'] = (int) DB::table('jobs')->where('queue', $queueName)->count();
                $metrics['available'] = true;
                return $metrics;
            }

            if ($connection === 'redis') {
                if (!$this->safeRedisIsAvailable()) {
                    return $metrics;
                }

                $metrics['pending'] = $this->safeRedisLength("queues:{$queueName}");
                $metrics['reserved'] = $this->safeRedisZcard("queues:{$queueName}:reserved");
                $metrics['delayed'] = $this->safeRedisZcard("queues:{$queueName}:delayed");
                $metrics['available'] = true;
                return $metrics;
            }
        } catch (\Throwable $exception) {
            Log::warning('Failed to inspect izin queue depth', [
                'queue' => $queueName,
                'connection' => $connection,
                'error' => $exception->getMessage(),
            ]);
        }

        return $metrics;
    }

    private function safeRedisIsAvailable(): bool
    {
        try {
            Redis::ping();
            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function safeRedisLength(string $key): int
    {
        try {
            return max(0, (int) Redis::llen($key));
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private function safeRedisZcard(string $key): int
    {
        try {
            return max(0, (int) Redis::zcard($key));
        } catch (\Throwable $exception) {
            return 0;
        }
    }

    private function collectIzinFailureMetrics(Carbon $windowStart, int $limit): array
    {
        $jobIdentifiers = [
            class_basename(DispatchIzinApproverNotifications::class),
            class_basename(DispatchIzinDecisionNotification::class),
            class_basename(DispatchIzinWhatsappNotification::class),
        ];

        $failedJobsQuery = DB::table('failed_jobs')
            ->where(function ($query) use ($jobIdentifiers) {
                foreach ($jobIdentifiers as $index => $jobIdentifier) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}('payload', 'like', '%' . $jobIdentifier . '%');
                }
            });

        $failedJobsWindowCount = (clone $failedJobsQuery)
            ->where('failed_at', '>=', $windowStart)
            ->count();

        $recentFailedJobs = (clone $failedJobsQuery)
            ->orderByDesc('failed_at')
            ->limit($limit)
            ->get(['id', 'queue', 'exception', 'failed_at'])
            ->map(function ($row): array {
                $message = trim((string) $row->exception);
                if (strlen($message) > 220) {
                    $message = substr($message, 0, 220) . '...';
                }

                return [
                    'id' => (int) $row->id,
                    'queue' => (string) $row->queue,
                    'failed_at' => Carbon::parse((string) $row->failed_at)->toISOString(),
                    'message' => $message,
                ];
            })
            ->values()
            ->all();

        return [
            'summary' => [
                'failed_jobs_window_count' => (int) $failedJobsWindowCount,
                'latest_failed_job_at' => $recentFailedJobs[0]['failed_at'] ?? null,
            ],
            'recent_jobs' => $recentFailedJobs,
        ];
    }

    private function collectIzinDeliveryMetrics(Carbon $windowStart, int $limit): array
    {
        $notificationQuery = Notification::query();
        $this->applyIzinWorkflowNotificationSourceFilter($notificationQuery);

        $whatsappQuery = WhatsappGateway::query()
            ->where('type', WhatsappGateway::TYPE_IZIN);
        $this->applyIzinWorkflowWhatsappFilter($whatsappQuery);

        $recentWhatsappFailures = (clone $whatsappQuery)
            ->where('status', WhatsappGateway::STATUS_FAILED)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get(['id', 'phone_number', 'status', 'error_message', 'created_at'])
            ->map(function (WhatsappGateway $row): array {
                return [
                    'id' => (int) $row->id,
                    'phone_number' => (string) $row->phone_number,
                    'status' => (string) $row->status,
                    'created_at' => optional($row->created_at)?->toISOString(),
                    'error_message' => $row->error_message,
                ];
            })
            ->values()
            ->all();

        return [
            'in_app' => [
                'created_window' => (int) (clone $notificationQuery)->where('created_at', '>=', $windowStart)->count(),
                'approval_requests_window' => (int) (clone $notificationQuery)->where('created_at', '>=', $windowStart)->whereIn('title', ['Pengajuan izin menunggu review', 'Pengajuan izin pegawai menunggu review', 'Pengajuan izin baru'])->count(),
                'decision_results_window' => (int) (clone $notificationQuery)->where('created_at', '>=', $windowStart)->whereIn('title', ['Pengajuan izin disetujui', 'Pengajuan izin ditolak', 'Pengajuan izin pegawai disetujui', 'Pengajuan izin pegawai ditolak', 'Izin disetujui', 'Izin ditolak'])->count(),
                'unread_current' => (int) (clone $notificationQuery)->where('is_read', false)->count(),
                'latest_created_at' => optional((clone $notificationQuery)->latest('created_at')->first())?->created_at?->toISOString(),
            ],
            'whatsapp' => [
                'pending_current' => (int) (clone $whatsappQuery)->where('status', WhatsappGateway::STATUS_PENDING)->count(),
                'sent_window' => (int) (clone $whatsappQuery)->where('created_at', '>=', $windowStart)->whereIn('status', [WhatsappGateway::STATUS_SENT, WhatsappGateway::STATUS_DELIVERED])->count(),
                'failed_window' => (int) (clone $whatsappQuery)->where('created_at', '>=', $windowStart)->where('status', WhatsappGateway::STATUS_FAILED)->count(),
                'latest_created_at' => optional((clone $whatsappQuery)->latest('created_at')->first())?->created_at?->toISOString(),
                'recent_failures' => $recentWhatsappFailures,
            ],
        ];
    }

    private function applyIzinWorkflowNotificationSourceFilter($query): void
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        $sourceExpression = $this->buildJsonStringExtractExpression($driver, 'data', 'source');

        $query->whereRaw("LOWER(COALESCE({$sourceExpression}, '')) = 'izin_workflow'");
    }

    private function applyIzinWorkflowWhatsappFilter($query): void
    {
        $driver = $query->getModel()->getConnection()->getDriverName();
        $sourceExpression = $this->buildJsonStringExtractExpression($driver, 'metadata', 'source', true);

        $query->where(function ($scopedQuery) use ($sourceExpression) {
            $scopedQuery
                ->whereRaw("LOWER(COALESCE({$sourceExpression}, '')) = 'izin_submitted'")
                ->orWhereRaw("LOWER(COALESCE({$sourceExpression}, '')) = 'izin_decision'");
        });
    }

    private function buildJsonStringExtractExpression(
        string $driver,
        string $column,
        string $key,
        bool $treatColumnAsTextJson = false
    ): string {
        if ($driver === 'sqlite') {
            return "json_extract({$column}, '$.{$key}')";
        }

        if ($driver === 'pgsql') {
            if ($treatColumnAsTextJson) {
                return "(CAST(COALESCE(NULLIF({$column}, ''), '{}') AS jsonb)->>'{$key}')";
            }

            return "({$column}->>'{$key}')";
        }

        if ($treatColumnAsTextJson) {
            return "JSON_UNQUOTE(JSON_EXTRACT(COALESCE(NULLIF({$column}, ''), '{}'), '$.{$key}'))";
        }

        return "JSON_UNQUOTE(JSON_EXTRACT({$column}, '$.{$key}'))";
    }

    private function getAllowedJenisIzin(User $user): array
    {
        if ($user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
            return Izin::studentJenisIzin();
        }

        return [];
    }

    /**
     * Check whether the target user is a student role.
     */
    private function isStudentRoleUser(User $targetUser): bool
    {
        return $targetUser->hasRole(RoleNames::aliases(RoleNames::SISWA));
    }

    /**
     * Explicitly allow student leave approval list only for the allowed approver roles.
     */
    private function canAccessStudentIzinApproval(User $user): bool
    {
        return RoleAccessMatrix::isStudentLeaveApprover($user);
    }

    /**
     * Explicit approval policy for student leave.
     */
    private function canApproveStudentIzin(User $approver, Izin $izin): bool
    {
        if (!RoleAccessMatrix::isStudentLeaveApprover($approver)) {
            return false;
        }

        if (
            $approver->hasRole(RoleNames::aliases(RoleNames::SUPER_ADMIN)) ||
            $approver->hasRole(RoleNames::aliases(RoleNames::ADMIN))
        ) {
            return true;
        }

        if ($approver->hasRole(RoleNames::aliases(RoleNames::WAKASEK_KESISWAAN))) {
            return true;
        }

        if ($approver->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS))) {
            return $this->isWaliForKelas($approver, (int) $izin->kelas_id);
        }

        return false;
    }

    /**
     * Check if a wali kelas is assigned to a specific class.
     */
    private function isWaliForKelas(User $user, int $kelasId): bool
    {
        if ($kelasId <= 0) {
            return false;
        }

        return $user->kelasWali()->where('id', $kelasId)->exists();
    }

    /**
     * Resolve active class assignment for a student.
     */
    private function resolveCurrentStudentClassId(User $user): ?int
    {
        return DB::table('kelas_siswa')
            ->where('siswa_id', $user->id)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->value('kelas_id');
    }

    /**
     * Determine whether current user can access detail/document of a leave request.
     */
    private function canAccessIzinDetail(User $viewer, Izin $izin): bool
    {
        if ((int) $izin->user_id === (int) $viewer->id) {
            return true;
        }

        if (!$izin->relationLoaded('user')) {
            $izin->load('user');
        }

        /** @var User|null $targetUser */
        $targetUser = $izin->user;
        if (!$targetUser) {
            return false;
        }

        if ($this->isStudentRoleUser($targetUser)) {
            if ($this->canApproveStudentIzin($viewer, $izin)) {
                return true;
            }

            if ($viewer->hasPermissionTo('view_all_izin')) {
                return true;
            }

            if ($viewer->hasPermissionTo('view_kelas_izin')) {
                return $this->canViewByClassScope($viewer, (int) $izin->kelas_id);
            }

            return false;
        }

        if ($viewer->hasPermissionTo('view_all_izin') || $viewer->hasPermissionTo('approve_izin')) {
            return true;
        }

        if ($viewer->hasPermissionTo('view_kelas_izin')) {
            return $this->canViewByClassScope($viewer, (int) $izin->kelas_id);
        }

        return false;
    }

    /**
     * Check whether a user can view records under class-scoped izin access.
     */
    private function canViewByClassScope(User $viewer, int $kelasId): bool
    {
        if ($kelasId <= 0) {
            return false;
        }

        return in_array($kelasId, RoleDataScope::accessibleClassIds($viewer), true);
    }

    /**
     * Build leave query by subject type.
     */
    private function buildLeaveTypeQuery(bool $studentType)
    {
        $query = Izin::query();

        if ($studentType) {
            $query->whereHas('user', function($q) {
                $q->whereHas('roles', function($roleQuery) {
                    $roleQuery->whereIn('name', RoleAccessMatrix::roleQueryNames(RoleNames::SISWA));
                });
            });
        } else {
            $query->whereHas('user', function($q) {
                $q->whereHas('roles', function($roleQuery) {
                    $roleQuery->whereIn('name', RoleAccessMatrix::employeeLeaveSubjectRoleQueryNames());
                });
            });
        }

        return $query;
    }

    /**
     * Apply class-scope restriction to an izin query.
     */
    private function applyClassScopeToIzinQuery($query, User $user): void
    {
        $classIds = RoleDataScope::accessibleClassIds($user);
        if ($classIds === []) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->whereIn('kelas_id', $classIds);
    }

    /**
     * Build statistics payload from an izin query.
     */
    private function buildIzinStats($query): array
    {
        return [
            'total' => (clone $query)->count(),
            'pending' => (clone $query)->where('status', 'pending')->count(),
            'approved' => (clone $query)->where('status', 'approved')->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];
    }

    private function applyApprovalListFilters($query, Request $request, bool $includeStatus = true): void
    {
        if ($includeStatus) {
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            } else {
                $query->where('status', 'pending');
            }
        }

        if ($request->filled('kelas_id')) {
            $query->where('kelas_id', $request->kelas_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->whereHas('user', function($q) use ($search) {
                $q->where('nama_lengkap', 'like', "%{$search}%")
                  ->orWhere('nisn', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('jenis_izin')) {
            $query->where('jenis_izin', $request->jenis_izin);
        }

        if ($request->filled('tanggal_mulai')) {
            $query->whereDate('tanggal_mulai', '>=', $request->tanggal_mulai);
        }

        if ($request->filled('tanggal_selesai')) {
            $query->whereDate('tanggal_selesai', '<=', $request->tanggal_selesai);
        }
    }

    private function buildPendingReviewSummary($query): array
    {
        $today = Carbon::today()->toDateString();

        $pendingBaseQuery = (clone $query)->where('status', 'pending');

        $totalPending = (int) (clone $pendingBaseQuery)->count();
        $dueToday = (int) (clone $pendingBaseQuery)->whereDate('tanggal_mulai', $today)->count();
        $overdue = (int) (clone $pendingBaseQuery)->whereDate('tanggal_mulai', '<', $today)->count();
        $upcoming = (int) (clone $pendingBaseQuery)->whereDate('tanggal_mulai', '>', $today)->count();

        return [
            'total_pending' => $totalPending,
            'due_today' => $dueToday,
            'overdue' => $overdue,
            'upcoming' => $upcoming,
            'urgent' => $dueToday + $overdue,
        ];
    }

    /**
     * Apply leave approval to attendance row without violating the unique user-date constraint.
     */
    private function applyApprovedLeaveAttendance(Izin $izin, Carbon $date, string $attendanceStatus, ?int $kelasId, ?int $performedBy = null): array
    {
        $existingAttendance = Absensi::query()
            ->where('user_id', (int) $izin->user_id)
            ->whereDate('tanggal', $date->toDateString())
            ->first();

        if ($existingAttendance && !$this->canOverwriteAttendanceWithLeave($existingAttendance, $izin)) {
            return [
                'action' => 'skipped_existing_attendance',
                'date' => $date->toDateString(),
            ];
        }

        $payload = [
            'kelas_id' => $kelasId,
            'status' => $attendanceStatus,
            'metode_absensi' => 'manual',
            'keterangan' => $izin->alasan,
            'izin_id' => $izin->id,
            'is_manual' => true,
            'jam_masuk' => null,
            'jam_pulang' => null,
        ];

        if ($existingAttendance) {
            $oldValues = $existingAttendance->toArray();
            $existingAttendance->fill($payload)->save();
            $existingAttendance->refresh();
            $this->logLeaveApprovalAttendanceAudit(
                $existingAttendance,
                'updated',
                $performedBy,
                $oldValues,
                $existingAttendance->toArray(),
                $izin,
                $date
            );
            return [
                'action' => 'updated',
                'date' => $date->toDateString(),
            ];
        }

        $attendance = Absensi::query()->create([
            'tanggal' => $date->toDateString(),
            'user_id' => (int) $izin->user_id,
            ...$payload,
        ]);

        $this->logLeaveApprovalAttendanceAudit(
            $attendance,
            'created',
            $performedBy,
            null,
            $attendance->toArray(),
            $izin,
            $date
        );

        return [
            'action' => 'created',
            'date' => $date->toDateString(),
        ];
    }

    /**
     * Only replace synthesized or non-attended rows when approval is applied.
     */
    private function canOverwriteAttendanceWithLeave(Absensi $attendance, Izin $izin): bool
    {
        if ((int) $attendance->izin_id === (int) $izin->id) {
            return true;
        }

        $status = strtolower(trim((string) $attendance->status));
        if ($status === 'alpa') {
            $status = 'alpha';
        }

        if (in_array($status, ['alpha', 'izin', 'sakit'], true)) {
            return true;
        }

        return !$attendance->jam_masuk && !$attendance->jam_pulang;
    }

    private function logLeaveApprovalAttendanceAudit(
        Absensi $attendance,
        string $actionType,
        ?int $performedBy,
        ?array $oldValues,
        ?array $newValues,
        Izin $izin,
        Carbon $date
    ): void {
        if (!$performedBy || $attendance->id <= 0) {
            return;
        }

        AttendanceAuditLog::createLog(
            (int) $attendance->id,
            $actionType,
            $performedBy,
            'Absensi diterapkan dari approval izin yang disetujui',
            $oldValues,
            $newValues,
            [
                'source' => 'leave_approval',
                'izin_id' => (int) $izin->id,
                'izin_status' => (string) $izin->status,
                'izin_type' => (string) $izin->jenis_izin,
                'attendance_date' => $date->toDateString(),
                'target_user_id' => (int) $izin->user_id,
                'target_kelas_id' => $attendance->kelas_id !== null ? (int) $attendance->kelas_id : null,
                'replaced_existing_attendance' => $oldValues !== null,
                'previous_status' => $oldValues['status'] ?? null,
                'previous_izin_id' => $oldValues['izin_id'] ?? null,
                'previous_is_manual' => $oldValues['is_manual'] ?? null,
                'resolved_status' => $newValues['status'] ?? null,
                'resolved_izin_id' => $newValues['izin_id'] ?? null,
            ]
        );
    }

    /**
     * Send in-app notification to relevant approvers when new izin is submitted.
     */
    private function notifyApproversForNewIzin(Izin $izin): void
    {
        try {
            DispatchIzinApproverNotifications::dispatch((int) $izin->id)->afterCommit();
        } catch (\Throwable $exception) {
            Log::warning('Failed to queue approver notification for izin', [
                'izin_id' => $izin->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Send in-app notification to applicant after approval/rejection.
     */
    private function notifyStudentApprovalResult(Izin $izin, string $status): void
    {
        try {
            DispatchIzinDecisionNotification::dispatch(
                (int) $izin->id,
                $status,
                Auth::id() ? (int) Auth::id() : null
            )->afterCommit();
        } catch (\Throwable $exception) {
            Log::warning('Failed to queue student notification for izin decision', [
                'izin_id' => $izin->id,
                'status' => $status,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveExistingSubmitResponse(int $userId, string $clientRequestId): ?\Illuminate\Http\JsonResponse
    {
        $izinId = $this->resolveSubmitRequestId($userId, $clientRequestId);
        if (!$izinId) {
            return null;
        }

        $izin = Izin::query()
            ->with(['user:id,nama_lengkap,nisn', 'kelas:id,nama_kelas'])
            ->where('id', $izinId)
            ->where('user_id', $userId)
            ->first();

        if (!$izin) {
            return null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengajuan izin sebelumnya sudah diterima dan sedang ditinjau',
            'data' => $this->serializeIzin($izin),
        ]);
    }

    private function resolveSubmitRequestId(int $userId, string $clientRequestId): ?int
    {
        if ($clientRequestId === '') {
            return null;
        }

        try {
            $izinId = Cache::get($this->submitIdempotencyCacheKey($userId, $clientRequestId));
        } catch (\Throwable $exception) {
            Log::warning('Failed to read izin submit idempotency cache', [
                'user_id' => $userId,
                'client_request_id' => $clientRequestId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }

        if (!is_numeric($izinId)) {
            return null;
        }

        $normalizedId = (int) $izinId;

        return $normalizedId > 0 ? $normalizedId : null;
    }

    private function rememberSubmitRequestId(int $userId, string $clientRequestId, int $izinId): void
    {
        if ($clientRequestId === '' || $izinId <= 0) {
            return;
        }

        try {
            Cache::put(
                $this->submitIdempotencyCacheKey($userId, $clientRequestId),
                $izinId,
                now()->addMinutes(self::SUBMIT_IDEMPOTENCY_TTL_MINUTES)
            );
        } catch (\Throwable $exception) {
            Log::warning('Failed to write izin submit idempotency cache', [
                'user_id' => $userId,
                'izin_id' => $izinId,
                'client_request_id' => $clientRequestId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function submitIdempotencyCacheKey(int $userId, string $clientRequestId): string
    {
        return self::SUBMIT_IDEMPOTENCY_CACHE_PREFIX . $userId . ':' . sha1($clientRequestId);
    }

    private function alreadyProcessedResponse(Izin $izin)
    {
        return response()->json([
            'success' => false,
            'message' => 'Izin sudah diproses sebelumnya',
            'data' => [
                'izin_id' => (int) $izin->id,
                'current_status' => (string) $izin->status,
                'current_status_label' => Izin::getStatusLabel((string) $izin->status),
                'approved_by' => $izin->approved_by,
                'approved_at' => $izin->approved_at,
                'rejected_by' => $izin->rejected_by,
                'rejected_at' => $izin->rejected_at,
                'catatan_approval' => $izin->catatan_approval,
            ],
        ], 422);
    }

    /**
     * Menutup notifikasi approver (wali/wakasek/staff) untuk izin yang sudah diproses.
     *
     * @return array{izin_id:int,status:string,marked_as_read:int}
     */
    private function closePendingApproverNotificationsForIzin(Izin $izin, string $status, int $actorUserId): array
    {
        try {
            $query = Notification::query()
                ->where('is_read', false)
                ->where('user_id', '!=', (int) $izin->user_id);

            $this->applyIzinIdNotificationFilter($query, (int) $izin->id);
            $this->applyApproverRequestNotificationFilter($query);

            $markedAsRead = (int) $query->update([
                'is_read' => true,
                'updated_at' => now(),
            ]);

            Log::info('Approver izin notifications auto-closed', [
                'izin_id' => (int) $izin->id,
                'status' => $status,
                'marked_as_read' => $markedAsRead,
                'actor_user_id' => $actorUserId,
            ]);

            return [
                'izin_id' => (int) $izin->id,
                'status' => $status,
                'marked_as_read' => $markedAsRead,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Failed to auto-close approver izin notifications', [
                'izin_id' => (int) $izin->id,
                'status' => $status,
                'actor_user_id' => $actorUserId,
                'error' => $exception->getMessage(),
            ]);

            return [
                'izin_id' => (int) $izin->id,
                'status' => $status,
                'marked_as_read' => 0,
            ];
        }
    }

    private function applyIzinIdNotificationFilter($query, int $izinId): void
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $query->whereRaw(
                "CAST(COALESCE(json_extract(data, '$.izin_id'), 0) AS INTEGER) = ?",
                [$izinId]
            );
            return;
        }

        $query->whereRaw(
            "CAST(COALESCE(data->>'izin_id', '0') AS BIGINT) = ?",
            [$izinId]
        );
    }

    private function applyApproverRequestNotificationFilter($query): void
    {
        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $query->where(function ($scopedQuery) {
                $scopedQuery
                    ->whereRaw(
                        "LOWER(COALESCE(json_extract(data, '$.message_category'), '')) = 'izin_approval_request'"
                    )
                    ->orWhere(function ($legacyQuery) {
                        $legacyQuery
                            ->whereRaw("json_extract(data, '$.message_category') IS NULL")
                            ->whereRaw("LOWER(COALESCE(title, '')) IN ('pengajuan izin baru', 'pengajuan izin menunggu review', 'pengajuan izin pegawai menunggu review')");
                    });
            });
            return;
        }

        $query->where(function ($scopedQuery) {
            $scopedQuery
                ->whereRaw("LOWER(COALESCE(data->>'message_category', '')) = 'izin_approval_request'")
                ->orWhere(function ($legacyQuery) {
                    $legacyQuery
                        ->whereRaw("(data->>'message_category') IS NULL")
                        ->whereRaw("LOWER(COALESCE(title, '')) IN ('pengajuan izin baru', 'pengajuan izin menunggu review', 'pengajuan izin pegawai menunggu review')");
                });
        });
    }
}

