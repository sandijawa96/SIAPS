<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceDisciplineOverride;
use App\Services\AttendanceDisciplineOverrideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AttendanceDisciplineOverrideController extends Controller
{
    public function __construct(
        private readonly AttendanceDisciplineOverrideService $disciplineOverrideService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'include_inactive' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null) {
                        $fail('Filter include_inactive harus bernilai true, false, 1, atau 0.');
                    }
                },
            ],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi filter override gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $includeInactive = $request->boolean('include_inactive', true);
            $rows = $this->disciplineOverrideService
                ->listOverrides($includeInactive)
                ->map(fn (AttendanceDisciplineOverride $override) => $this->disciplineOverrideService->serializeOverride($override))
                ->values();

            return response()->json([
                'status' => 'success',
                'data' => $rows,
                'meta' => [
                    'total' => $rows->count(),
                    'active' => $rows->where('is_active', true)->count(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to load attendance discipline overrides', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat override disiplin siswa',
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->makeValidator($request);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi override disiplin gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $override = $this->disciplineOverrideService->createOverride(
                $validator->validated(),
                Auth::id()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Override disiplin berhasil disimpan',
                'data' => $this->disciplineOverrideService->serializeOverride($override),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi override disiplin gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to create attendance discipline override', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan override disiplin siswa',
            ], 500);
        }
    }

    public function update(Request $request, AttendanceDisciplineOverride $disciplineOverride): JsonResponse
    {
        $validator = $this->makeValidator($request);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi override disiplin gagal',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updated = $this->disciplineOverrideService->updateOverride(
                $disciplineOverride,
                $validator->validated(),
                Auth::id()
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Override disiplin berhasil diperbarui',
                'data' => $this->disciplineOverrideService->serializeOverride($updated),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validasi override disiplin gagal',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Failed to update attendance discipline override', [
                'override_id' => $disciplineOverride->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memperbarui override disiplin siswa',
            ], 500);
        }
    }

    public function destroy(AttendanceDisciplineOverride $disciplineOverride): JsonResponse
    {
        try {
            $this->disciplineOverrideService->deleteOverride($disciplineOverride, Auth::id());

            return response()->json([
                'status' => 'success',
                'message' => 'Override disiplin berhasil dihapus',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to delete attendance discipline override', [
                'override_id' => $disciplineOverride->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus override disiplin siswa',
            ], 500);
        }
    }

    private function makeValidator(Request $request)
    {
        return Validator::make($request->all(), [
            'scope_type' => 'required|in:tingkat,kelas,user',
            'target_tingkat_id' => 'nullable|required_if:scope_type,tingkat|integer|exists:tingkat,id',
            'target_kelas_id' => 'nullable|required_if:scope_type,kelas|integer|exists:kelas,id',
            'target_user_id' => 'nullable|required_if:scope_type,user|integer|exists:users,id',
            'is_active' => 'nullable|boolean',
            'discipline_thresholds_enabled' => 'nullable|boolean',
            'total_violation_minutes_semester_limit' => 'required|integer|min:0|max:100000',
            'alpha_days_semester_limit' => 'required|integer|min:0|max:365',
            'late_minutes_monthly_limit' => 'required|integer|min:0|max:100000',
            'semester_total_violation_mode' => 'required|in:monitor_only,alertable',
            'notify_wali_kelas_on_total_violation_limit' => 'nullable|boolean',
            'notify_kesiswaan_on_total_violation_limit' => 'nullable|boolean',
            'semester_alpha_mode' => 'required|in:monitor_only,alertable',
            'monthly_late_mode' => 'required|in:monitor_only,alertable',
            'notify_wali_kelas_on_late_limit' => 'nullable|boolean',
            'notify_kesiswaan_on_late_limit' => 'nullable|boolean',
            'notify_wali_kelas_on_alpha_limit' => 'nullable|boolean',
            'notify_kesiswaan_on_alpha_limit' => 'nullable|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);
    }
}
