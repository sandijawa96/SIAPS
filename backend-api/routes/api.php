<?php

use App\Http\Controllers\Api\AbsensiController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\KelasController;
use App\Http\Controllers\Api\SiswaController;
use App\Http\Controllers\Api\PegawaiControllerExtended;
use App\Http\Controllers\Api\IzinController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\BroadcastCampaignController;
use App\Http\Controllers\Api\WhatsappController;
use App\Http\Controllers\Api\TahunAjaranController;
use App\Http\Controllers\Api\LokasiGpsController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\MobileReleaseController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\QRCodeController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AcademicContextController;
use App\Http\Controllers\Api\DeviceTokenController;
use App\Http\Controllers\Api\DapodikController;
use App\Http\Controllers\Api\AttendanceSchemaController;
use App\Http\Controllers\Api\AttendanceDisciplineCaseController;
use App\Http\Controllers\Api\FaceTemplateController;
use App\Http\Controllers\Api\SimpleAttendanceController;
use App\Http\Controllers\Api\WaliKelasController;
use App\Http\Controllers\Api\SbtController;
use App\Support\RoleNames;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Api\TingkatController;
use App\Http\Controllers\Api\ManualAttendanceController;
use App\Http\Controllers\Api\PersonalDataController;
use App\Http\Controllers\Api\PersonalDataDocumentController;
use App\Http\Controllers\Api\MataPelajaranController;
use App\Http\Controllers\Api\GuruMapelController;
use App\Http\Controllers\Api\JadwalPelajaranController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Web routes (Sanctum)
Route::prefix('web')->group(function () {
    Route::post('/login', [AuthController::class, 'loginWeb'])->name('web.login');
    Route::post('/login-siswa', [AuthController::class, 'loginWebSiswa'])->name('web.login.siswa');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

// Mobile routes (JWT)
Route::prefix('mobile')->group(function () {
    Route::post('/login', [AuthController::class, 'loginMobile'])->name('mobile.login');
    Route::post('/login-siswa', [AuthController::class, 'loginSiswa'])->name('mobile.login.siswa');
    Route::post('/login-app', [AuthController::class, 'loginMobileApp'])->name('mobile.login.app');

    Route::middleware('auth:api')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

        // Dashboard routes
        Route::get('/dashboard', [\App\Http\Controllers\Mobile\DashboardController::class, 'index']);
    });
});

// Health check endpoint
Route::get('/health-check', function () {
    try {
        $dbStatus = DB::connection()->getPdo() ? 'connected' : 'disconnected';
        $serverNow = now()->setTimezone(config('app.timezone'));
        $response = [
            'status' => 'ok',
            'database' => $dbStatus,
            'timestamp' => $serverNow->toISOString(),
            'server_time' => $serverNow->format('Y-m-d H:i:s'),
            'server_now' => $serverNow->toISOString(),
            'server_epoch_ms' => $serverNow->valueOf(),
            'server_date' => $serverNow->toDateString(),
            'timezone' => config('app.timezone'),
        ];

        if (app()->environment('local') || config('app.debug')) {
            $response['data'] = [
                'users' => \App\Models\User::count(),
                'kelas' => \App\Models\Kelas::count(),
            ];
        }

        return response()->json($response);
    } catch (\Exception $e) {
        Log::error('Health check failed', [
            'error' => $e->getMessage(),
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Health check failed',
            'timestamp' => now()->toISOString(),
            'timezone' => config('app.timezone'),
        ], 500);
    }
});

Route::get('/push/config/web', [DeviceTokenController::class, 'webConfig']);
Route::post('/whatsapp/webhook', [WhatsappController::class, 'webhook']);
Route::middleware('throttle:120,1')->prefix('sbt/mobile')->group(function () {
    Route::get('/config', [SbtController::class, 'mobileConfig']);
    Route::get('/version-check', [SbtController::class, 'versionCheck']);
    Route::post('/sessions', [SbtController::class, 'startSession']);
    Route::post('/heartbeat', [SbtController::class, 'heartbeat']);
    Route::post('/events', [SbtController::class, 'reportEvent']);
    Route::post('/unlock', [SbtController::class, 'unlock']);
    Route::post('/finish', [SbtController::class, 'finishSession']);
});
Route::get('/mobile-releases/{mobileRelease}/signed-download', [MobileReleaseController::class, 'signedDownload'])
    ->middleware('signed')
    ->whereNumber('mobileRelease')
    ->name('mobile-releases.signed-download');
Route::get('/mobile-releases/{mobileRelease}/ios-manifest', [MobileReleaseController::class, 'iosManifest'])
    ->middleware('signed')
    ->whereNumber('mobileRelease')
    ->name('mobile-releases.ios-manifest');
Route::get('/backups/{filename}/signed-download', [BackupController::class, 'signedDownload'])
    ->middleware('signed')
    ->where('filename', '.*\.zip')
    ->name('backups.signed-download');
// Legacy routes (backward compatibility)
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/login-siswa', [AuthController::class, 'loginSiswa'])->name('login.siswa');

// Protected routes (supports both auth methods)
Route::middleware('auth:sanctum,api')->group(function () {
    // Auth routes
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/update-profile', [AuthController::class, 'updateProfile']);
    Route::post('/change-password', [AuthController::class, 'changePassword']);
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
    Route::get('/mobile-releases/check-authenticated', [MobileReleaseController::class, 'checkAuthenticated']);
    Route::get('/mobile-releases/catalog', [MobileReleaseController::class, 'catalog']);
    Route::get('/mobile-releases/{mobileRelease}/download-link', [MobileReleaseController::class, 'downloadLink'])
        ->whereNumber('mobileRelease');
    Route::get('/mobile-releases/{mobileRelease}/download', [MobileReleaseController::class, 'download'])
        ->whereNumber('mobileRelease')
        ->name('mobile-releases.download');

    Route::post('/check-permission', [AuthController::class, 'checkPermission']);

    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_settings')->prefix('mobile-releases')->group(function () {
        Route::get('/', [MobileReleaseController::class, 'index']);
        Route::post('/', [MobileReleaseController::class, 'store']);
        Route::get('/{mobileRelease}', [MobileReleaseController::class, 'show'])->whereNumber('mobileRelease');
        Route::put('/{mobileRelease}', [MobileReleaseController::class, 'update'])->whereNumber('mobileRelease');
        Route::delete('/{mobileRelease}', [MobileReleaseController::class, 'destroy'])->whereNumber('mobileRelease');
    });

    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_settings')->prefix('sbt/admin')->group(function () {
        Route::get('/settings', [SbtController::class, 'adminSettings']);
        Route::put('/settings', [SbtController::class, 'updateSettings']);
        Route::get('/summary', [SbtController::class, 'adminSummary']);
        Route::get('/sessions', [SbtController::class, 'adminSessions']);
        Route::get('/events', [SbtController::class, 'adminEvents']);
    });

    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_settings')->prefix('dapodik')->group(function () {
        Route::get('/settings', [DapodikController::class, 'settings']);
        Route::put('/settings', [DapodikController::class, 'updateSettings']);
        Route::post('/test-connection', [DapodikController::class, 'testConnection']);
        Route::post('/staging-batches', [DapodikController::class, 'createStagingBatch']);
        Route::get('/staging-batches/{batch}', [DapodikController::class, 'showStagingBatch'])->whereNumber('batch');
        Route::get('/staging-batches/{batch}/review', [DapodikController::class, 'reviewStagingBatch'])->whereNumber('batch');
        Route::get('/staging-batches/{batch}/apply-preview', [DapodikController::class, 'previewApplyStagingBatch'])->whereNumber('batch');
        Route::post('/staging-batches/{batch}/apply', [DapodikController::class, 'applyStagingBatch'])->whereNumber('batch');
        Route::get('/staging-batches/{batch}/input-preview', [DapodikController::class, 'previewInputStagingBatch'])->whereNumber('batch');
        Route::post('/staging-batches/{batch}/input', [DapodikController::class, 'inputStagingBatch'])->whereNumber('batch');
        Route::get('/staging-batches/{batch}/class-preview', [DapodikController::class, 'previewClassStagingBatch'])->whereNumber('batch');
        Route::post('/staging-batches/{batch}/class-sync', [DapodikController::class, 'syncClassStagingBatch'])->whereNumber('batch');
        Route::get('/staging-batches/{batch}/class-membership-preview', [DapodikController::class, 'previewClassMembershipStagingBatch'])->whereNumber('batch');
        Route::post('/staging-batches/{batch}/class-membership-sync', [DapodikController::class, 'syncClassMembershipStagingBatch'])->whereNumber('batch');
        Route::post('/staging-batches/{batch}/sources', [DapodikController::class, 'fetchStagingBatchSource'])->whereNumber('batch');
        Route::post('/staging-batches/{batch}/finalize', [DapodikController::class, 'finalizeStagingBatch'])->whereNumber('batch');
    });

    // Personal data self-service (all authenticated users except Super Admin, checked in controller)
    Route::prefix('me')->group(function () {
        Route::get('/personal-data', [PersonalDataController::class, 'show']);
        Route::get('/personal-data/schema', [PersonalDataController::class, 'schema']);
        Route::patch('/personal-data', [PersonalDataController::class, 'update']);
        Route::post('/personal-data/avatar', [PersonalDataController::class, 'updateAvatar']);
        Route::get('/personal-data/documents', [PersonalDataDocumentController::class, 'index']);
        Route::post('/personal-data/documents', [PersonalDataDocumentController::class, 'store']);
        Route::delete('/personal-data/documents/{document}', [PersonalDataDocumentController::class, 'destroy'])
            ->whereNumber('document');
    });

    // Dashboard routes
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/system-status', [DashboardController::class, 'systemStatus']);
    Route::get('/dashboard/recent-activities', [DashboardController::class, 'recentActivities']);
    Route::get('/dashboard/today-attendance', [DashboardController::class, 'todayAttendance']);
    Route::get('/dashboard/my-attendance-status', [DashboardController::class, 'myAttendanceStatus']);
    Route::get('/dashboard/live-class-report', [DashboardController::class, 'liveClassReport']);
    Route::get('/academic-context/current', [AcademicContextController::class, 'current']);

    // User Management routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_users')->group(function () {
        Route::get('/users/{id}/personal-data', [PersonalDataController::class, 'showForUser'])->where('id', '[0-9]+');
        Route::get('/users/{id}/personal-data/schema', [PersonalDataController::class, 'schemaForUser'])->where('id', '[0-9]+');
        Route::patch('/users/{id}/personal-data', [PersonalDataController::class, 'updateForUser'])->where('id', '[0-9]+');
        Route::post('/users/{id}/personal-data/avatar', [PersonalDataController::class, 'updateAvatarForUser'])->where('id', '[0-9]+');
        Route::get('/users/{id}/personal-data/documents', [PersonalDataDocumentController::class, 'indexForUser'])->where('id', '[0-9]+');
        Route::post('/users/{id}/personal-data/documents', [PersonalDataDocumentController::class, 'storeForUser'])->where('id', '[0-9]+');
        Route::delete('/users/{id}/personal-data/documents/{document}', [PersonalDataDocumentController::class, 'destroyForUser'])
            ->whereNumber('id')
            ->whereNumber('document');

        Route::apiResource('users', UserController::class);
        Route::post('/users/{id}/activate', [UserController::class, 'activate']);
        Route::post('/users/{id}/deactivate', [UserController::class, 'deactivate']);
        Route::post('/users/import', [UserController::class, 'import']);
        Route::get('/users/export', [UserController::class, 'export']);
    });

    // Personal data verification routes (permission-based, separated by verification scope)
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_personal_data_verification')->group(function () {
        Route::get('/personal-data/review-queue', [PersonalDataController::class, 'reviewQueue']);
    });
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':verify_personal_data_siswa|verify_personal_data_pegawai')->group(function () {
        Route::post('/personal-data/review-queue/{id}/decision', [PersonalDataController::class, 'submitReviewDecision'])->where('id', '[0-9]+');
    });

    // Kelas read routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_kelas|manage_kelas')->group(function () {
        Route::get('/kelas/tingkat/{tingkatId}', [KelasController::class, 'getByTingkat']);
        Route::get('/kelas/{id}/siswa', [KelasController::class, 'getSiswa'])->where('id', '[0-9]+');
        Route::get('/kelas/{id}', [KelasController::class, 'show'])->where('id', '[0-9]+');
        Route::get('/kelas', [KelasController::class, 'index']);
    });

    // Kelas write routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_kelas')->group(function () {
        Route::prefix('kelas')->group(function () {
            Route::get('/download-template', [KelasController::class, 'downloadTemplate']);
            Route::post('/import', [KelasController::class, 'import']);
            Route::get('/download-template-naik-kelas', [KelasController::class, 'downloadNaikKelasTemplate']);
            Route::post('/import-naik-kelas', [KelasController::class, 'importNaikKelas']);
            Route::get('/export', [KelasController::class, 'export']);
        });
        Route::post('/kelas', [KelasController::class, 'store']);
        Route::put('/kelas/{id}', [KelasController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/kelas/{id}', [KelasController::class, 'destroy'])->where('id', '[0-9]+');
        Route::get('/kelas/{id}/available-siswa', [KelasController::class, 'getAvailableSiswa'])->where('id', '[0-9]+');
        Route::post('/kelas/{id}/assign-wali', [KelasController::class, 'assignWaliKelas'])->where('id', '[0-9]+');
        Route::post('/kelas/{id}/assign-siswa', [KelasController::class, 'assignSiswa'])->where('id', '[0-9]+');
        if (app()->environment('local') || config('app.debug')) {
            Route::get('/kelas/{id}/debug-pivot', [KelasController::class, 'debugUserKelasPivot'])->where('id', '[0-9]+');
        }
        Route::post('/kelas/{id}/add-siswa', [KelasController::class, 'addSiswa'])->where('id', '[0-9]+');
        Route::delete('/kelas/{kelasId}/siswa/{siswaId}', [KelasController::class, 'removeSiswa'])
            ->where('kelasId', '[0-9]+')
            ->where('siswaId', '[0-9]+');
        Route::post('/kelas/reset-wali', [KelasController::class, 'resetWaliKelas']);
    });

    // Siswa read routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_siswa|manage_students')->group(function () {
        Route::get('/siswa/{id}', [SiswaController::class, 'show'])->where('id', '[0-9]+');
        Route::get('/siswa', [SiswaController::class, 'index']);

        // Siswa Extended read routes
        Route::get('/siswa-extended/{id}/riwayat-kelas', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'getRiwayatKelas'])->where('id', '[0-9]+');
        Route::get('/siswa-extended/{id}/riwayat-transisi', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'getRiwayatTransisi'])->where('id', '[0-9]+');
        Route::get('/siswa-extended/{id}', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'show'])->where('id', '[0-9]+');
        Route::get('/siswa-extended', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'index']);
    });

    // Shared read flow for wali + kurikulum/admin on transition settings/requests
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_siswa|manage_students|manage_tahun_ajaran')->group(function () {
        Route::get('/siswa-extended/transfer-requests', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'getTransferRequests']);
        Route::get('/siswa-extended/wali-promotion-settings', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'getWaliPromotionSettings']);
    });

    // Siswa write routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_students')->group(function () {
        Route::get('/siswa/export', [SiswaController::class, 'export']);
        Route::get('/siswa/template', [SiswaController::class, 'downloadTemplate']);
        Route::post('/siswa/import', [SiswaController::class, 'import']);
        Route::get('/siswa/import-progress/{jobId}', [SiswaController::class, 'importProgress']);
        Route::post('/siswa', [SiswaController::class, 'store']);
        Route::put('/siswa/{id}', [SiswaController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/siswa/{id}', [SiswaController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/siswa/{id}/reset-password', [SiswaController::class, 'resetPassword'])->where('id', '[0-9]+');

        // Siswa Extended write routes
        Route::get('/siswa-extended/export', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'export']);
        Route::get('/siswa-extended/template', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'downloadTemplate']);
        Route::post('/siswa-extended/import', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'import']);
        Route::post('/siswa-extended', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'store']);
        Route::put('/siswa-extended/{id}', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/siswa-extended/{id}', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/{id}/reset-password', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'resetPassword'])->where('id', '[0-9]+');

        // Student Transition Routes
        Route::post('/siswa-extended/{id}/naik-kelas', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'naikKelas'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/{id}/pindah-kelas', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'pindahKelas'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/{id}/lulus', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'lulusSiswa'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/{id}/keluar', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'keluarSiswa'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/{id}/aktifkan-kembali', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'aktifkanKembali'])->where('id', '[0-9]+');

        // Rollback Routes
        Route::post('/siswa-extended/{id}/rollback-to-kelas', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'rollbackToKelas'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/{id}/batalkan-kelulusan', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'batalkanKelulusan'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/{id}/kembalikan-siswa', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'kembalikanSiswa'])->where('id', '[0-9]+');
    });

    // Wali kelas request flow (request/cancel + naik kelas by window)
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_siswa|manage_students')->group(function () {
        Route::post('/siswa-extended/{id}/pindah-kelas/request', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'requestPindahKelas'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/transfer-requests/{id}/cancel', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'cancelTransferRequest'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/{id}/naik-kelas/wali', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'naikKelasWali'])->where('id', '[0-9]+');
    });

    // Kurikulum/admin approval + promotion window setting
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_tahun_ajaran|manage_students')->group(function () {
        Route::post('/siswa-extended/transfer-requests/{id}/approve', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'approveTransferRequest'])->where('id', '[0-9]+');
        Route::post('/siswa-extended/transfer-requests/{id}/reject', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'rejectTransferRequest'])->where('id', '[0-9]+');
        Route::put('/siswa-extended/wali-promotion-settings', [\App\Http\Controllers\Api\SiswaControllerExtended::class, 'upsertWaliPromotionSetting']);
    });

    // Izin routes
    Route::prefix('izin')->group(function () {
        // Routes untuk mobile app siswa
        Route::post('/', [IzinController::class, 'store']);
        Route::get('/', [IzinController::class, 'index']);
        Route::get('/statistics', [IzinController::class, 'getStatistics']);
        Route::get('/observability', [IzinController::class, 'getObservability']);
        Route::get('/approval/list', [IzinController::class, 'getForApproval']);
        Route::get('/jenis/{type?}', [IzinController::class, 'getJenisOptions']);
        Route::get('/{id}', [IzinController::class, 'show']);
        Route::delete('/{id}', [IzinController::class, 'cancel']);
        Route::get('/{id}/document', [IzinController::class, 'downloadDocument']);

        // Routes untuk approval (web admin & mobile app wali kelas/staff)
        Route::post('/{id}/approve', [IzinController::class, 'approve']);
        Route::post('/{id}/reject', [IzinController::class, 'reject']);
    });

    // Pegawai read routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_pegawai|manage_pegawai')->group(function () {
        Route::get('/pegawai/roles/{roleId}/sub-roles', [PegawaiControllerExtended::class, 'getAvailableSubRoles']);
        Route::get('/pegawai/{id}', [PegawaiControllerExtended::class, 'show'])->where('id', '[0-9]+');
        Route::get('/pegawai', [PegawaiControllerExtended::class, 'index']);

        Route::get('/pegawai-extended/{id}', [PegawaiControllerExtended::class, 'show'])->where('id', '[0-9]+');
        Route::get('/pegawai-extended', [PegawaiControllerExtended::class, 'index']);
    });

    // Pegawai write routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_pegawai')->group(function () {
        Route::get('/pegawai/export', [PegawaiControllerExtended::class, 'export']);
        Route::get('/pegawai/template', [PegawaiControllerExtended::class, 'downloadTemplate']);
        Route::post('/pegawai/import', [PegawaiControllerExtended::class, 'import']);
        Route::get('/pegawai/import-progress/{jobId}', [PegawaiControllerExtended::class, 'importProgress']);
        Route::post('/pegawai', [PegawaiControllerExtended::class, 'store']);
        Route::put('/pegawai/{id}', [PegawaiControllerExtended::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/pegawai/{id}', [PegawaiControllerExtended::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/pegawai/{id}/reset-password', [PegawaiControllerExtended::class, 'resetPassword'])->where('id', '[0-9]+');

        // Pegawai Extended routes (for comprehensive data management)
        Route::get('/pegawai-extended/export', [PegawaiControllerExtended::class, 'exportLengkap']);
        Route::get('/pegawai-extended/template', [PegawaiControllerExtended::class, 'downloadTemplate']);
        Route::post('/pegawai-extended/import', [PegawaiControllerExtended::class, 'import']);
        Route::post('/pegawai-extended', [PegawaiControllerExtended::class, 'store']);
        Route::put('/pegawai-extended/{id}', [PegawaiControllerExtended::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/pegawai-extended/{id}', [PegawaiControllerExtended::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/pegawai-extended/{id}/reset-password', [PegawaiControllerExtended::class, 'resetPassword'])->where('id', '[0-9]+');
    });

    // Tahun Ajaran routes
    // Allow read access to tahun ajaran for all authenticated users
    Route::get('/tahun-ajaran', [TahunAjaranController::class, 'index']);
    Route::get('/tahun-ajaran/{id}', [TahunAjaranController::class, 'show']);

    // Require permission for write operations
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_tahun_ajaran')->group(function () {
        Route::post('/tahun-ajaran', [TahunAjaranController::class, 'store']);
        Route::put('/tahun-ajaran/{id}', [TahunAjaranController::class, 'update']);
        Route::delete('/tahun-ajaran/{id}', [TahunAjaranController::class, 'destroy']);
        Route::post('/tahun-ajaran/{id}/activate', [TahunAjaranController::class, 'activate']);
        Route::post('/tahun-ajaran/{id}/transition-status', [TahunAjaranController::class, 'transitionStatus']);
        Route::post('/tahun-ajaran/{id}/update-progress', [TahunAjaranController::class, 'updateProgress']);
    });

    // Periode Akademik routes
    // Allow read access to periode akademik for all authenticated users
    Route::get('/periode-akademik', [\App\Http\Controllers\Api\PeriodeAkademikController::class, 'index']);
    Route::get('/periode-akademik/current/periode', [\App\Http\Controllers\Api\PeriodeAkademikController::class, 'getCurrentPeriode']);
    Route::get('/periode-akademik/{id}', [\App\Http\Controllers\Api\PeriodeAkademikController::class, 'show'])->whereNumber('id');
    Route::post('/periode-akademik/check/absensi-validity', [\App\Http\Controllers\Api\PeriodeAkademikController::class, 'checkAbsensiValidity']);

    // Require permission for write operations
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_periode_akademik')->group(function () {
        Route::post('/periode-akademik', [\App\Http\Controllers\Api\PeriodeAkademikController::class, 'store']);
        Route::put('/periode-akademik/{id}', [\App\Http\Controllers\Api\PeriodeAkademikController::class, 'update']);
        Route::delete('/periode-akademik/{id}', [\App\Http\Controllers\Api\PeriodeAkademikController::class, 'destroy']);
    });

    // Event Akademik routes
    // Allow read access to event akademik for all authenticated users
    Route::get('/event-akademik/user/upcoming', [\App\Http\Controllers\Api\EventAkademikController::class, 'getUpcomingEvents']);
    Route::get('/event-akademik/user/today', [\App\Http\Controllers\Api\EventAkademikController::class, 'getTodayEvents']);
    Route::get('/event-akademik', [\App\Http\Controllers\Api\EventAkademikController::class, 'index']);
    Route::get('/event-akademik/{id}', [\App\Http\Controllers\Api\EventAkademikController::class, 'show'])->whereNumber('id');

    // Require permission for write operations
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_event_akademik')->group(function () {
        // Libur Nasional sync routes
        Route::post('/event-akademik/preview-libur-nasional', [\App\Http\Controllers\Api\EventAkademikController::class, 'previewLiburNasional']);
        Route::post('/event-akademik/sync-libur-nasional', [\App\Http\Controllers\Api\EventAkademikController::class, 'syncLiburNasional']);
        Route::post('/event-akademik/auto-sync-libur-nasional', [\App\Http\Controllers\Api\EventAkademikController::class, 'autoSyncLiburNasional']);
        Route::post('/event-akademik/preview-kalender-indonesia', [\App\Http\Controllers\Api\EventAkademikController::class, 'previewKalenderIndonesia']);
        Route::post('/event-akademik/sync-kalender-indonesia', [\App\Http\Controllers\Api\EventAkademikController::class, 'syncKalenderIndonesia']);
        Route::post('/event-akademik/sync-kalender-indonesia-lengkap', [\App\Http\Controllers\Api\EventAkademikController::class, 'syncKalenderIndonesiaLengkap']);
        Route::post('/event-akademik/auto-sync-kalender-indonesia', [\App\Http\Controllers\Api\EventAkademikController::class, 'autoSyncKalenderIndonesia']);
        Route::post('/event-akademik/sync-kalender-absensi', [\App\Http\Controllers\Api\EventAkademikController::class, 'syncKalenderAbsensi']);
        Route::get('/event-akademik/sync-kalender-absensi/status', [\App\Http\Controllers\Api\EventAkademikController::class, 'syncKalenderAbsensiStatus']);

        Route::post('/event-akademik', [\App\Http\Controllers\Api\EventAkademikController::class, 'store']);
        Route::put('/event-akademik/{id}', [\App\Http\Controllers\Api\EventAkademikController::class, 'update'])->whereNumber('id');
        Route::delete('/event-akademik/{id}', [\App\Http\Controllers\Api\EventAkademikController::class, 'destroy'])->whereNumber('id');
    });

    // Mata Pelajaran routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_mapel|manage_mapel')->group(function () {
        Route::get('/mata-pelajaran', [MataPelajaranController::class, 'index']);
        Route::get('/mata-pelajaran/export', [MataPelajaranController::class, 'export']);
        Route::get('/mata-pelajaran/{id}', [MataPelajaranController::class, 'show'])->where('id', '[0-9]+');
    });

    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_mapel')->group(function () {
        Route::get('/mata-pelajaran/template', [MataPelajaranController::class, 'downloadTemplate']);
        Route::post('/mata-pelajaran/import', [MataPelajaranController::class, 'import']);
        Route::post('/mata-pelajaran', [MataPelajaranController::class, 'store']);
        Route::put('/mata-pelajaran/{id}', [MataPelajaranController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/mata-pelajaran/{id}', [MataPelajaranController::class, 'destroy'])->where('id', '[0-9]+');
    });

    // Penugasan Guru-Mapel routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':assign_guru_mapel')->group(function () {
        Route::get('/guru-mapel/export', [GuruMapelController::class, 'export']);
        Route::get('/guru-mapel/template', [GuruMapelController::class, 'downloadTemplate']);
        Route::post('/guru-mapel/import', [GuruMapelController::class, 'import']);
        Route::get('/guru-mapel/options', [GuruMapelController::class, 'options']);
        Route::get('/guru-mapel', [GuruMapelController::class, 'index']);
        Route::post('/guru-mapel', [GuruMapelController::class, 'store']);
        Route::put('/guru-mapel/{id}', [GuruMapelController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/guru-mapel/{id}', [GuruMapelController::class, 'destroy'])->where('id', '[0-9]+');
    });

    // Jadwal Pelajaran personal route (guru/wali/siswa scope validated in controller)
    Route::get('/jadwal-pelajaran/my-schedule', [JadwalPelajaranController::class, 'mySchedule']);

    // Jadwal Pelajaran routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_jadwal_pelajaran|manage_jadwal_pelajaran')->group(function () {
        Route::get('/jadwal-pelajaran/export', [JadwalPelajaranController::class, 'export']);
        Route::get('/jadwal-pelajaran/options', [JadwalPelajaranController::class, 'options']);
        Route::get('/jadwal-pelajaran/settings', [JadwalPelajaranController::class, 'settings']);
        Route::get('/jadwal-pelajaran', [JadwalPelajaranController::class, 'index']);
        Route::post('/jadwal-pelajaran/check-conflict', [JadwalPelajaranController::class, 'checkConflict']);
    });

    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_jadwal_pelajaran')->group(function () {
        Route::get('/jadwal-pelajaran/template', [JadwalPelajaranController::class, 'downloadTemplate']);
        Route::post('/jadwal-pelajaran/import', [JadwalPelajaranController::class, 'import']);
        Route::put('/jadwal-pelajaran/settings', [JadwalPelajaranController::class, 'updateSettings']);
        Route::post('/jadwal-pelajaran', [JadwalPelajaranController::class, 'store']);
        Route::put('/jadwal-pelajaran/{id}', [JadwalPelajaranController::class, 'update'])->where('id', '[0-9]+');
        Route::delete('/jadwal-pelajaran/{id}', [JadwalPelajaranController::class, 'destroy'])->where('id', '[0-9]+');
        Route::post('/jadwal-pelajaran/publish', [JadwalPelajaranController::class, 'publish']);
    });

    // Live Tracking Routes
    Route::prefix('live-tracking')->group(function () {
        Route::get('/history', [App\Http\Controllers\Api\LiveTrackingController::class, 'getHistory']);
        Route::get('/history-map', [App\Http\Controllers\Api\LiveTrackingController::class, 'getHistoryMap']);
        Route::get('/history-map/export-pdf', [App\Http\Controllers\Api\LiveTrackingController::class, 'exportHistoryMapPdf']);
        Route::get('/history-map/students', [App\Http\Controllers\Api\LiveTrackingController::class, 'searchHistoryMapStudents']);
        Route::get('/current', [App\Http\Controllers\Api\LiveTrackingController::class, 'getCurrentTracking']);
        Route::get('/current-location', [App\Http\Controllers\Api\LiveTrackingController::class, 'getCurrentLocation']);
        Route::post('/users-in-radius', [App\Http\Controllers\Api\LiveTrackingController::class, 'getUsersInRadius']);
        Route::get('/export', [App\Http\Controllers\Api\LiveTrackingController::class, 'export']);

        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_live_tracking')->group(function () {
            Route::post('/session/start', [App\Http\Controllers\Api\LiveTrackingController::class, 'startTrackingSession']);
            Route::post('/session/stop', [App\Http\Controllers\Api\LiveTrackingController::class, 'stopTrackingSession']);
            Route::get('/session/active', [App\Http\Controllers\Api\LiveTrackingController::class, 'getActiveTrackingSessions']);
        });
    });

    // Lokasi GPS Management Routes
    Route::prefix('lokasi-gps')->group(function () {
        // Attendance usage routes (available for all authenticated users)
        Route::get('/active', [\App\Http\Controllers\Api\LokasiGpsController::class, 'getActiveLocations']);
        Route::post('/check-distance', [\App\Http\Controllers\Api\LokasiGpsController::class, 'checkDistance']);
        Route::get('/attendance-schema', [\App\Http\Controllers\Api\LokasiGpsController::class, 'getAttendanceSchema']);
        // Real-time location update is restricted to siswa in controller policy.
        Route::post('/update-tracking-state', [\App\Http\Controllers\Api\LokasiGpsController::class, 'updateTrackingState']);
        Route::post('/update-location', [\App\Http\Controllers\Api\LokasiGpsController::class, 'updateUserLocation']);

        // Real-time GPS monitoring routes
        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_live_tracking')->group(function () {
            Route::get('/active-users', [\App\Http\Controllers\Api\LokasiGpsController::class, 'getActiveUsersLocations']);
            Route::get('/{id}/users', [\App\Http\Controllers\Api\LokasiGpsController::class, 'getUsersInLocation']);
        });

        // Read-only management routes
        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_settings')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\LokasiGpsController::class, 'index']);
            Route::get('/export', [\App\Http\Controllers\Api\LokasiGpsController::class, 'export']);
            Route::get('/{id}', [\App\Http\Controllers\Api\LokasiGpsController::class, 'show']);
        });

        // Write management routes
        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_settings')->group(function () {
            Route::post('/', [\App\Http\Controllers\Api\LokasiGpsController::class, 'store']);
            Route::post('/import', [\App\Http\Controllers\Api\LokasiGpsController::class, 'import']);
            Route::put('/{id}', [\App\Http\Controllers\Api\LokasiGpsController::class, 'update']);
            Route::delete('/{id}', [\App\Http\Controllers\Api\LokasiGpsController::class, 'destroy']);
            Route::post('/{id}/toggle', [LokasiGpsController::class, 'toggle']);
            Route::post('/validate', [LokasiGpsController::class, 'validateLocation']);
        });
    });

    // Settings routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::post('/settings', [SettingsController::class, 'update']);
        Route::get('/settings/school-profile', [SettingsController::class, 'getSchoolProfile']);
        Route::post('/settings/school-profile', [SettingsController::class, 'updateSchoolProfile']);
        Route::get('/settings/absensi', [SettingsController::class, 'getAbsensiSettings']);
        Route::post('/settings/absensi', [SettingsController::class, 'updateAbsensiSettings']);
    });

    // Simple Attendance Settings (New Simplified System)
    // Device Binding Routes
    Route::prefix('device-binding')->group(function () {
        Route::post('/bind', [App\Http\Controllers\Api\DeviceBindingController::class, 'bindDevice']);
        Route::get('/status', [App\Http\Controllers\Api\DeviceBindingController::class, 'checkDeviceBinding']);
        Route::post('/validate', [App\Http\Controllers\Api\DeviceBindingController::class, 'validateDeviceAccess']);

        // Admin only routes (require manage_settings permission)
        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_settings')->group(function () {
            Route::post('/reset', [App\Http\Controllers\Api\DeviceBindingController::class, 'resetDeviceBinding']);
            Route::get('/users', [App\Http\Controllers\Api\DeviceBindingController::class, 'getUsersWithDeviceBinding']);
        });
    });

    Route::prefix('simple-attendance')->group(function () {
        // Public routes (read access for all authenticated users)
        Route::get('/global', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getGlobalSettings']);
        Route::get('/user/{userId?}', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getUserSettings']);

        // Working hours and validation routes (for all authenticated users)
        Route::get('/working-hours', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getUserWorkingHours']);
        Route::post('/validate-time', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'validateAttendanceTime']);
        Route::get('/attendance-history', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getAttendanceHistory']);
        Route::post('/precheck/security-warning', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'reportPrecheckSecurityWarning']);

        // Attendance submission with strict validation
        Route::post('/submit', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'submitAttendance']);

        // Shift management for security staff
        Route::get('/shift/schedule', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getShiftSchedule']);

        // Admin routes (require manage_attendance_settings permission)
        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_attendance_settings')->group(function () {
            Route::get('/users/all', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getAllUsersSettings']);
            Route::get('/summary', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getAttendanceSummary']);
            Route::get('/health-check', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getSystemHealth']);
            Route::get('/governance-logs', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getGovernanceLogs']);
            Route::get('/security-events', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getSecurityEvents']);
            Route::get('/security-events/summary', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getSecurityEventSummary']);
            Route::get('/security-events/export', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'exportSecurityEvents']);
            Route::get('/fraud-assessments', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getFraudAssessments']);
            Route::get('/fraud-assessments/summary', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'getFraudAssessmentSummary']);
            Route::get('/fraud-assessments/export', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'exportFraudAssessments']);
            Route::get('/fraud-assessments/{assessment}', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'showFraudAssessment'])
                ->whereNumber('assessment');
            Route::put('/global', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'updateGlobalSettings']);
            Route::get('/discipline-overrides', [\App\Http\Controllers\Api\AttendanceDisciplineOverrideController::class, 'index']);
            Route::post('/discipline-overrides', [\App\Http\Controllers\Api\AttendanceDisciplineOverrideController::class, 'store']);
            Route::put('/discipline-overrides/{disciplineOverride}', [\App\Http\Controllers\Api\AttendanceDisciplineOverrideController::class, 'update'])
                ->whereNumber('disciplineOverride');
            Route::delete('/discipline-overrides/{disciplineOverride}', [\App\Http\Controllers\Api\AttendanceDisciplineOverrideController::class, 'destroy'])
                ->whereNumber('disciplineOverride');
            Route::post('/user/{userId}/override', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'setUserOverride']);
            Route::delete('/user/{userId}/override', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'removeUserOverride']);
            Route::post('/shift/schedule', [\App\Http\Controllers\Api\SimpleAttendanceController::class, 'createShiftSchedule']);
        });
    });

    Route::prefix('face-templates')->group(function () {
        Route::get('/me', [FaceTemplateController::class, 'showMine']);
        Route::post('/self-submit', [FaceTemplateController::class, 'selfSubmit']);

        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_attendance_settings|unlock_face_template_submit_quota')
            ->group(function () {
                Route::get('/users/{user}', [FaceTemplateController::class, 'show'])
                    ->whereNumber('user');
                Route::post('/users/{user}/unlock-self-submit', [FaceTemplateController::class, 'unlockSelfSubmit'])
                    ->whereNumber('user');
            });

        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_attendance_settings')
            ->group(function () {
                Route::post('/enroll', [FaceTemplateController::class, 'enroll']);
                Route::delete('/{template}', [FaceTemplateController::class, 'deactivate'])
                    ->whereNumber('template');
            });
    });


    // Attendance Schema Routes (New Schema-based System)
    Route::prefix('attendance-schemas')->group(function () {
        // Public routes (read access for all authenticated users)
        Route::get('/', [AttendanceSchemaController::class, 'index']);
        Route::get('/{schema}', [AttendanceSchemaController::class, 'show']);
        Route::get('/user/{user}/effective', [AttendanceSchemaController::class, 'getEffectiveSchema']);
        Route::post('/effective/bulk', [AttendanceSchemaController::class, 'getEffectiveSchemasBulk']);

        // Admin routes (require manage_attendance_settings permission)
        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_attendance_settings')->group(function () {
            // Schema CRUD
            Route::post('/', [AttendanceSchemaController::class, 'store']);
            Route::put('/{schema}', [AttendanceSchemaController::class, 'update']);
            Route::delete('/{schema}', [AttendanceSchemaController::class, 'destroy']);

            // Schema management
            Route::patch('/{schema}/toggle-active', [AttendanceSchemaController::class, 'toggleActive']);
            Route::patch('/{schema}/set-default', [AttendanceSchemaController::class, 'setDefault']);
            Route::get('/{schema}/change-logs', [AttendanceSchemaController::class, 'getChangeLogs']);

            // Schema assignment
            Route::post('/{schema}/assign-user', [AttendanceSchemaController::class, 'assignToUser']);
            Route::post('/{schema}/bulk-assign', [AttendanceSchemaController::class, 'bulkAssign']);
            Route::post('/auto-assign', [AttendanceSchemaController::class, 'autoAssign']);
        });
    });

    // Notifications routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/{id}', [NotificationController::class, 'show']);
        Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::get('/unread/count', [NotificationController::class, 'getUnreadCount']);

        // Admin only
        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_notifications')->group(function () {
            Route::post('/', [NotificationController::class, 'store']);
            Route::post('/broadcast', [NotificationController::class, 'broadcast']);
        });
    });

    // Reports routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_reports')->group(function () {
        Route::prefix('reports')->group(function () {
            Route::get('/attendance/daily', [ReportController::class, 'daily']);
            Route::get('/attendance/range', [ReportController::class, 'range']);
            Route::get('/attendance/monthly', [ReportController::class, 'monthly']);
            Route::get('/attendance/semester', [ReportController::class, 'semester']);
            Route::get('/attendance/yearly', [ReportController::class, 'yearly']);
            Route::get('/export/excel', [ReportController::class, 'exportExcel']);
            Route::get('/export/pdf', [ReportController::class, 'exportPdf']);
        });
    });

    // Absensi routes
    Route::prefix('absensi')->group(function () {
        // Check-in/out dipusatkan ke /simple-attendance/submit.
        Route::get('/history', [AbsensiController::class, 'history']);
        Route::get('/statistics', [AbsensiController::class, 'statistics']);
        Route::get('/today', [AbsensiController::class, 'todayStatus']);
        Route::get('/{id}', [AbsensiController::class, 'show']);
    });

    Route::prefix('device-tokens')->group(function () {
        Route::get('/', [DeviceTokenController::class, 'index']);
        Route::post('/register', [DeviceTokenController::class, 'register']);
        Route::post('/deactivate', [DeviceTokenController::class, 'deactivate']);
    });

    // Manual Attendance routes
    Route::prefix('manual-attendance')->group(function () {
        Route::get('/users', [ManualAttendanceController::class, 'getManageableUsers']);
        Route::get('/users/search', [ManualAttendanceController::class, 'searchManageableUsers']);
        Route::get('/mobile-summary', [ManualAttendanceController::class, 'mobileSummary']);
        Route::get('/incident-options', [ManualAttendanceController::class, 'getIncidentOptions']);
        Route::get('/incidents', [ManualAttendanceController::class, 'indexIncident']);
        Route::post('/create', [ManualAttendanceController::class, 'store']);
        Route::get('/history', [ManualAttendanceController::class, 'history']);
        Route::get('/summary', [ManualAttendanceController::class, 'summary']);
        Route::get('/pending-checkout', [ManualAttendanceController::class, 'pendingCheckout']);
        Route::post('/check-duplicate', [ManualAttendanceController::class, 'checkDuplicate']);
        Route::post('/bulk-preview', [ManualAttendanceController::class, 'bulkPreview']);
        Route::post('/bulk-create', [ManualAttendanceController::class, 'bulkCreate']);
        Route::post('/bulk-correct', [ManualAttendanceController::class, 'bulkCorrect']);
        Route::post('/incidents/preview', [ManualAttendanceController::class, 'previewIncident']);
        Route::post('/incidents', [ManualAttendanceController::class, 'storeIncident']);
        Route::get('/incidents/{id}/export', [ManualAttendanceController::class, 'exportIncident'])->where('id', '[0-9]+');
        Route::get('/incidents/{id}', [ManualAttendanceController::class, 'showIncident'])->where('id', '[0-9]+');
        Route::get('/date-range', [ManualAttendanceController::class, 'getByDateRange']);
        Route::get('/export', [ManualAttendanceController::class, 'export']);
        Route::post('/{id}/resolve-checkout', [ManualAttendanceController::class, 'resolveCheckout']);
        Route::get('/{id}/audit-logs', [ManualAttendanceController::class, 'auditLogs']);
        Route::put('/{id}', [ManualAttendanceController::class, 'update']);
        Route::delete('/{id}', [ManualAttendanceController::class, 'destroy']);
        Route::get('/statistics', [ManualAttendanceController::class, 'statistics']);
    });


    // WhatsApp Gateway routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_whatsapp')->group(function () {
        Route::prefix('whatsapp')->group(function () {
            Route::post('/send', [WhatsappController::class, 'send']);
            Route::post('/broadcast', [WhatsappController::class, 'broadcast']);
            Route::post('/check-number', [WhatsappController::class, 'checkNumber']);
            Route::post('/generate-qr', [WhatsappController::class, 'generateQr']);
            Route::post('/logout-device', [WhatsappController::class, 'logoutDevice']);
            Route::post('/delete-device', [WhatsappController::class, 'deleteDevice']);
            Route::get('/status', [WhatsappController::class, 'status']);
            Route::get('/webhook-events', [WhatsappController::class, 'webhookEvents']);
            Route::get('/skip-events', [WhatsappController::class, 'skipEvents']);
            Route::post('/settings', [WhatsappController::class, 'updateSettings']);
            Route::get('/automations', [WhatsappController::class, 'automations']);
            Route::post('/automations', [WhatsappController::class, 'updateAutomations']);
        });
    });

    // QR Code routes
    Route::prefix('qr-code')->group(function () {
        Route::get('/attendance/{code}', [QRCodeController::class, 'attendance']);
        Route::post('/validate', [QRCodeController::class, 'validateQRCode']);

        // Admin only routes
        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_qrcode')->group(function () {
            Route::post('/generate', [QRCodeController::class, 'generate']);
            Route::post('/bulk', [QRCodeController::class, 'bulk']);
        });
    });

    // Role Management Routes
    Route::prefix('roles')->group(function () {
        // Public routes (no permission required, but still need auth)
        Route::get('/available', [RoleController::class, 'getAvailableRoles']);
        Route::get('/primary', [RoleController::class, 'getPrimaryRoles']);
        Route::get('/my-feature-profile', [RoleController::class, 'myFeatureProfile']);
        Route::get('/{id}/sub-roles', [RoleController::class, 'getSubRoles']);

        // Protected routes (require view_roles permission)
        Route::middleware([\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_roles'])->group(function () {
            Route::get('/', [RoleController::class, 'index']);
            Route::get('/hierarchy', [RoleController::class, 'hierarchy']);
            Route::get('/feature-matrix', [RoleController::class, 'featureMatrix']);
            Route::get('/{id}', [RoleController::class, 'show'])->where('id', '[0-9]+');
            Route::post('/effective-permissions', [RoleController::class, 'getEffectivePermissions']);
        });

        // Admin routes (require manage_roles permission)
        Route::middleware([\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_roles'])->group(function () {
            Route::post('/', [RoleController::class, 'store']);
            Route::put('/{id}', [RoleController::class, 'update']);
            Route::delete('/{id}', [RoleController::class, 'destroy']);
            Route::post('/{id}/assign-permissions', [RoleController::class, 'assignPermissions']);
            Route::post('/{id}/toggle-status', [RoleController::class, 'toggleStatus']);
        });
    });

    // Permission Management Routes
    Route::group(['middleware' => [\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_permissions']], function () {
        // Place specific routes before wildcard routes to avoid conflicts
        Route::get('/permissions/by-module', [PermissionController::class, 'getByModule']);
        Route::get('/permissions/modules', [PermissionController::class, 'getModules']);

        Route::get('/permissions', [PermissionController::class, 'index']);
        Route::post('/permissions', [PermissionController::class, 'store']);
        Route::get('/permissions/{id}', [PermissionController::class, 'show']);
        Route::put('/permissions/{id}', [PermissionController::class, 'update']);
        Route::delete('/permissions/{id}', [PermissionController::class, 'destroy']);
    });

    // Backup & Restore Routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_backups')->group(function () {
        Route::prefix('backups')->group(function () {
            Route::get('/', [BackupController::class, 'index']);
            Route::post('/', [BackupController::class, 'create']);
            Route::get('/settings', [BackupController::class, 'getSettings']);
            Route::post('/settings', [BackupController::class, 'updateSettings']);
            Route::post('/cleanup', [BackupController::class, 'cleanup']);
            Route::get('/{filename}/download-link', [BackupController::class, 'downloadLink'])->where('filename', '.*\.zip');
            Route::get('/{filename}', [BackupController::class, 'download'])->where('filename', '.*\.zip');
            Route::delete('/{filename}', [BackupController::class, 'delete'])->where('filename', '.*\.zip');
            Route::post('/{filename}/restore', [BackupController::class, 'restore'])->where('filename', '.*\.zip');
        });
    });

    Route::prefix('broadcast-campaigns')->group(function () {
        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_broadcast_campaigns|manage_broadcast_campaigns|send_broadcast_campaigns|retry_broadcast_campaigns')
            ->get('/', [BroadcastCampaignController::class, 'index']);

        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':send_broadcast_campaigns')
            ->post('/upload-flyer', [BroadcastCampaignController::class, 'uploadFlyer']);

        Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':send_broadcast_campaigns')
            ->post('/', [BroadcastCampaignController::class, 'store']);
    });

    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_attendance_settings|send_broadcast_campaigns')
        ->get('/attendance-discipline-cases', [AttendanceDisciplineCaseController::class, 'index']);

    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_attendance_settings|send_broadcast_campaigns')
        ->get('/attendance-discipline-cases/export', [AttendanceDisciplineCaseController::class, 'export']);

    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_attendance_settings|send_broadcast_campaigns')
        ->get('/attendance-discipline-cases/{id}', [AttendanceDisciplineCaseController::class, 'show']);

    // Activity Logs Routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':view_activity_logs')->group(function () {
        Route::prefix('activity-logs')->group(function () {
            Route::get('/', [ActivityLogController::class, 'index']);
            Route::get('/filters', [ActivityLogController::class, 'getFilters']);
            Route::get('/statistics', [ActivityLogController::class, 'statistics']);
            Route::get('/export', [ActivityLogController::class, 'export']);
            Route::get('/{id}', [ActivityLogController::class, 'show']);
            Route::get('/user/{userId}/timeline', [ActivityLogController::class, 'userTimeline']);

            // Admin only routes
            Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_activity_logs')->group(function () {
                Route::post('/cleanup', [ActivityLogController::class, 'cleanup']);
            });
        });
    });

    // Tingkat routes
    Route::get('/tingkat', [TingkatController::class, 'index']); // Allow read access for all authenticated users
    Route::get('/tingkat/active', [TingkatController::class, 'getActive']); // Get active tingkat only
    Route::get('/tingkat/{id}', [TingkatController::class, 'show']);

    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_kelas')->group(function () {
        Route::post('/tingkat', [TingkatController::class, 'store']);
        Route::put('/tingkat/{id}', [TingkatController::class, 'update']);
        Route::delete('/tingkat/{id}', [TingkatController::class, 'destroy']);
        Route::post('/tingkat/{id}/toggle-status', [TingkatController::class, 'toggleStatus']);
    });

    // Status Kepegawaian routes
    Route::get('/status-kepegawaian', [\App\Http\Controllers\Api\StatusKepegawaianController::class, 'index']);
    Route::get('/status-kepegawaian/enum', [\App\Http\Controllers\Api\StatusKepegawaianController::class, 'getEnumValues']);


    // Wali Kelas routes
    Route::middleware(
        \Spatie\Permission\Middleware\RoleMiddleware::class . ':' . implode('|', RoleNames::aliases(RoleNames::WALI_KELAS))
    )->group(function () {
        // Get kelas yang diwalikelasi
        Route::get('/wali-kelas/kelas', [WaliKelasController::class, 'getMyKelas']);

        // Routes yang memerlukan verifikasi wali kelas
        Route::middleware(['check.walikelas'])->group(function () {
            // Detail kelas
            Route::get('/wali-kelas/kelas/{id}', [WaliKelasController::class, 'getKelasDetail']);

            // Absensi
            Route::get('/wali-kelas/kelas/{id}/absensi', [WaliKelasController::class, 'getKelasAbsensi']);
            Route::get('/wali-kelas/kelas/{id}/statistik', [WaliKelasController::class, 'getKelasStatistik']);
            Route::get('/wali-kelas/kelas/{id}/security-events', [WaliKelasController::class, 'getKelasSecurityEvents']);
            Route::get('/wali-kelas/kelas/{id}/security-students', [WaliKelasController::class, 'getKelasSecurityStudents']);
            Route::get('/wali-kelas/kelas/{id}/security-students/{userId}', [WaliKelasController::class, 'getKelasSecurityStudent'])
                ->whereNumber('userId');
            Route::get('/wali-kelas/kelas/{id}/security-cases', [WaliKelasController::class, 'getKelasSecurityCases']);
            Route::post('/wali-kelas/kelas/{id}/security-cases', [WaliKelasController::class, 'storeKelasSecurityCase']);
            Route::get('/wali-kelas/kelas/{id}/security-cases/{case}', [WaliKelasController::class, 'showKelasSecurityCase'])
                ->whereNumber('case');
            Route::patch('/wali-kelas/kelas/{id}/security-cases/{case}', [WaliKelasController::class, 'updateKelasSecurityCase'])
                ->whereNumber('case');
            Route::post('/wali-kelas/kelas/{id}/security-cases/{case}/resolve', [WaliKelasController::class, 'resolveKelasSecurityCase'])
                ->whereNumber('case');
            Route::post('/wali-kelas/kelas/{id}/security-cases/{case}/reopen', [WaliKelasController::class, 'reopenKelasSecurityCase'])
                ->whereNumber('case');
            Route::post('/wali-kelas/kelas/{id}/security-cases/{case}/notes', [WaliKelasController::class, 'addKelasSecurityCaseNote'])
                ->whereNumber('case');
            Route::post('/wali-kelas/kelas/{id}/security-cases/{case}/evidence', [WaliKelasController::class, 'uploadKelasSecurityCaseEvidence'])
                ->whereNumber('case');
            Route::get('/wali-kelas/kelas/{id}/fraud-assessments', [WaliKelasController::class, 'getKelasFraudAssessments']);
            Route::get('/wali-kelas/kelas/{id}/fraud-assessments/summary', [WaliKelasController::class, 'getKelasFraudAssessmentSummary']);
            Route::get('/wali-kelas/kelas/{id}/fraud-assessments/{assessment}', [WaliKelasController::class, 'showKelasFraudAssessment'])
                ->whereNumber('assessment');

            // Izin
            Route::get('/wali-kelas/kelas/{id}/izin', [WaliKelasController::class, 'getKelasIzin']);
        });
    });

    Route::middleware(
        \Spatie\Permission\Middleware\RoleMiddleware::class . ':'
        . implode('|', RoleNames::flattenAliases([
            RoleNames::SUPER_ADMIN,
            RoleNames::WALI_KELAS,
            RoleNames::WAKASEK_KESISWAAN,
        ]))
    )->group(function () {
        Route::get('/monitoring-kelas/kelas', [WaliKelasController::class, 'getMyKelas']);
        Route::get('/monitoring-kelas/kelas/{id}', [WaliKelasController::class, 'getKelasDetail']);
        Route::get('/monitoring-kelas/kelas/{id}/absensi', [WaliKelasController::class, 'getKelasAbsensi']);
        Route::get('/monitoring-kelas/kelas/{id}/statistik', [WaliKelasController::class, 'getKelasStatistik']);
        Route::get('/monitoring-kelas/kelas/{id}/izin', [WaliKelasController::class, 'getKelasIzin']);
        Route::get('/monitoring-kelas/kelas/{id}/security-events', [WaliKelasController::class, 'getKelasSecurityEvents']);
        Route::get('/monitoring-kelas/kelas/{id}/security-events/export', [WaliKelasController::class, 'exportKelasSecurityEvents']);
        Route::get('/monitoring-kelas/kelas/{id}/security-students', [WaliKelasController::class, 'getKelasSecurityStudents']);
        Route::get('/monitoring-kelas/kelas/{id}/security-students/{userId}', [WaliKelasController::class, 'getKelasSecurityStudent'])
            ->whereNumber('userId');
        Route::get('/monitoring-kelas/kelas/{id}/security-cases', [WaliKelasController::class, 'getKelasSecurityCases']);
        Route::post('/monitoring-kelas/kelas/{id}/security-cases', [WaliKelasController::class, 'storeKelasSecurityCase']);
        Route::get('/monitoring-kelas/kelas/{id}/security-cases/{case}', [WaliKelasController::class, 'showKelasSecurityCase'])
            ->whereNumber('case');
        Route::patch('/monitoring-kelas/kelas/{id}/security-cases/{case}', [WaliKelasController::class, 'updateKelasSecurityCase'])
            ->whereNumber('case');
        Route::post('/monitoring-kelas/kelas/{id}/security-cases/{case}/resolve', [WaliKelasController::class, 'resolveKelasSecurityCase'])
            ->whereNumber('case');
        Route::post('/monitoring-kelas/kelas/{id}/security-cases/{case}/reopen', [WaliKelasController::class, 'reopenKelasSecurityCase'])
            ->whereNumber('case');
        Route::post('/monitoring-kelas/kelas/{id}/security-cases/{case}/notes', [WaliKelasController::class, 'addKelasSecurityCaseNote'])
            ->whereNumber('case');
        Route::post('/monitoring-kelas/kelas/{id}/security-cases/{case}/evidence', [WaliKelasController::class, 'uploadKelasSecurityCaseEvidence'])
            ->whereNumber('case');
        Route::get('/monitoring-kelas/kelas/{id}/fraud-assessments', [WaliKelasController::class, 'getKelasFraudAssessments']);
        Route::get('/monitoring-kelas/kelas/{id}/fraud-assessments/summary', [WaliKelasController::class, 'getKelasFraudAssessmentSummary']);
        Route::get('/monitoring-kelas/kelas/{id}/fraud-assessments/export', [WaliKelasController::class, 'exportKelasFraudAssessments']);
        Route::get('/monitoring-kelas/kelas/{id}/fraud-assessments/{assessment}', [WaliKelasController::class, 'showKelasFraudAssessment'])
            ->whereNumber('assessment');
    });

    // Monthly Recap routes
    Route::prefix('monthly-recap')->group(function () {
        Route::get('/current', [\App\Http\Controllers\Api\MonthlyRecapController::class, 'getCurrentMonth']);
        Route::get('/previous', [\App\Http\Controllers\Api\MonthlyRecapController::class, 'getPreviousMonth']);
        Route::get('/specific', [\App\Http\Controllers\Api\MonthlyRecapController::class, 'getSpecificMonth']);
    });

    // User Schema Statistics
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_attendance_settings')->group(function () {
        Route::get('/user-schema-stats/global', [\App\Http\Controllers\Api\UserSchemaStatsController::class, 'getGlobalStats']);
    });

    // Optimized Bulk Assignment Routes
    Route::middleware(\Spatie\Permission\Middleware\PermissionMiddleware::class . ':manage_attendance_settings')->group(function () {
        Route::prefix('bulk-assignment')->group(function () {
            Route::post('/assign', [\App\Http\Controllers\Api\BulkAssignmentController::class, 'bulkAssign']);
            Route::get('/users', [\App\Http\Controllers\Api\BulkAssignmentController::class, 'getPaginatedUsers']);
            Route::get('/users-with-schemas', [\App\Http\Controllers\Api\BulkAssignmentController::class, 'getUsersWithSchemas']);
            Route::get('/progress/{jobId}', [\App\Http\Controllers\Api\BulkAssignmentController::class, 'getAssignmentProgress']);
            Route::get('/stats', [\App\Http\Controllers\Api\BulkAssignmentController::class, 'getBulkStats']);
        });
    });
});
