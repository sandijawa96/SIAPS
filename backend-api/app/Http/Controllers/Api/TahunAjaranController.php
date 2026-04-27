<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TahunAjaran;
use App\Services\PeriodeAkademikSetupService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TahunAjaranController extends Controller
{
    public function __construct(private readonly PeriodeAkademikSetupService $periodeAkademikSetupService)
    {
    }

    /**
     * Get all tahun ajaran with optional filters
     */
    public function index(Request $request)
    {
        try {
            $query = TahunAjaran::query();

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by is_active (backward compatibility)
            if ($request->has('is_active')) {
                if ($request->boolean('is_active')) {
                    $query->where('status', TahunAjaran::STATUS_ACTIVE);
                } else {
                    $query->where('status', '!=', TahunAjaran::STATUS_ACTIVE);
                }
            }

            // Filter can manage classes
            if ($request->boolean('can_manage_classes')) {
                $query->canManageClasses();
            }

            // Order by created_at desc
            $query->orderBy('created_at', 'desc');

            // Pagination or all
            if ($request->boolean('no_pagination')) {
                $tahunAjaran = $query->get();
            } else {
                $perPage = $request->get('per_page', 15);
                $tahunAjaran = $query->paginate($perPage);
            }

            // Transform data to include additional info
            $transform = function ($item) {
                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'tanggal_mulai' => $item->tanggal_mulai,
                    'tanggal_selesai' => $item->tanggal_selesai,
                    'semester' => $item->semester,
                    'semester_display' => $this->resolveSemesterDisplay($item->semester),
                    'semester_periods' => $this->resolveSemesterPeriods($item->tanggal_mulai, $item->tanggal_selesai),
                    'status' => $item->status,
                    'status_display' => $item->status_display,
                    'is_active' => $item->status === TahunAjaran::STATUS_ACTIVE, // backward compatibility
                    'preparation_progress' => $item->preparation_progress,
                    'metadata' => $item->metadata,
                    'keterangan' => $item->keterangan,
                    'can_manage_classes' => $item->canManageClasses(),
                    'is_ready_to_activate' => $item->isReadyToActivate(),
                    'jumlah_kelas' => $item->jumlah_kelas,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at
                ];
            };

            if ($request->boolean('no_pagination')) {
                $data = $tahunAjaran->map($transform);
            } else {
                $tahunAjaran->getCollection()->transform($transform);
                $data = $tahunAjaran;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Data tahun ajaran berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TahunAjaranController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data tahun ajaran'
            ], 500);
        }
    }

    /**
     * Store new tahun ajaran
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:100|unique:tahun_ajaran,nama',
                'tanggal_mulai' => 'required|date',
                'tanggal_selesai' => 'required|date|after:tanggal_mulai',
                'semester' => 'nullable|in:full',
                'status' => 'in:draft,preparation,active,completed,archived',
                'keterangan' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $semester = 'full';
            $semesterValidationError = $this->validateSemesterDateRange(
                $semester,
                (string) $request->tanggal_mulai,
                (string) $request->tanggal_selesai
            );

            if ($semesterValidationError !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => [
                        'semester' => [$semesterValidationError]
                    ]
                ], 422);
            }

            $tahunAjaran = TahunAjaran::create([
                'nama' => $request->nama,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'semester' => $semester,
                'status' => $request->status ?? TahunAjaran::STATUS_DRAFT,
                'preparation_progress' => 0,
                'is_active' => false, // backward compatibility
                'keterangan' => $request->keterangan,
                'metadata' => [
                    'created_by' => auth()->id() ?? null,
                    'created_at' => now()->toDateTimeString()
                ]
            ]);

            $setup = null;
            if ($tahunAjaran->status === TahunAjaran::STATUS_ACTIVE) {
                $setup = $this->periodeAkademikSetupService->ensureDefaultForTahunAjaran($tahunAjaran, auth()->id());
            }

            return response()->json([
                'success' => true,
                'data' => $tahunAjaran,
                'setup' => $setup,
                'message' => 'Tahun ajaran berhasil dibuat'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in TahunAjaranController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat tahun ajaran'
            ], 500);
        }
    }

    /**
     * Show specific tahun ajaran
     */
    public function show($id)
    {
        try {
            $tahunAjaran = TahunAjaran::with(['kelas'])->find($id);

            if (!$tahunAjaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $tahunAjaran->id,
                    'nama' => $tahunAjaran->nama,
                    'tanggal_mulai' => $tahunAjaran->tanggal_mulai,
                    'tanggal_selesai' => $tahunAjaran->tanggal_selesai,
                    'semester' => $tahunAjaran->semester,
                    'semester_display' => $this->resolveSemesterDisplay($tahunAjaran->semester),
                    'semester_periods' => $this->resolveSemesterPeriods($tahunAjaran->tanggal_mulai, $tahunAjaran->tanggal_selesai),
                    'status' => $tahunAjaran->status,
                    'status_display' => $tahunAjaran->status_display,
                    'is_active' => $tahunAjaran->status === TahunAjaran::STATUS_ACTIVE,
                    'preparation_progress' => $tahunAjaran->preparation_progress,
                    'metadata' => $tahunAjaran->metadata,
                    'keterangan' => $tahunAjaran->keterangan,
                    'can_manage_classes' => $tahunAjaran->canManageClasses(),
                    'is_ready_to_activate' => $tahunAjaran->isReadyToActivate(),
                    'kelas' => $tahunAjaran->kelas,
                    'created_at' => $tahunAjaran->created_at,
                    'updated_at' => $tahunAjaran->updated_at
                ],
                'message' => 'Detail tahun ajaran berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TahunAjaranController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail tahun ajaran'
            ], 500);
        }
    }

    /**
     * Update tahun ajaran
     */
    public function update(Request $request, $id)
    {
        try {
            $tahunAjaran = TahunAjaran::find($id);

            if (!$tahunAjaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'nama' => 'required|string|max:100|unique:tahun_ajaran,nama,' . $id,
                'tanggal_mulai' => 'required|date',
                'tanggal_selesai' => 'required|date|after:tanggal_mulai',
                'semester' => 'nullable|in:full',
                'status' => 'in:draft,preparation,active,completed,archived',
                'keterangan' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $semester = 'full';
            $semesterValidationError = $this->validateSemesterDateRange(
                $semester,
                (string) $request->tanggal_mulai,
                (string) $request->tanggal_selesai
            );

            if ($semesterValidationError !== null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => [
                        'semester' => [$semesterValidationError]
                    ]
                ], 422);
            }

            // Only allow updates if not active or completed
            if (in_array($tahunAjaran->status, [TahunAjaran::STATUS_COMPLETED, TahunAjaran::STATUS_ARCHIVED])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat mengubah tahun ajaran yang sudah selesai atau diarsipkan'
                ], 422);
            }

            $updateData = [
                'nama' => $request->nama,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'semester' => $semester,
                'keterangan' => $request->keterangan,
                'metadata' => array_merge($tahunAjaran->metadata ?? [], [
                    'updated_by' => auth()->id() ?? null,
                    'updated_at' => now()->toDateTimeString()
                ])
            ];

            // Update status if provided
            if ($request->has('status')) {
                $updateData['status'] = $request->status;
                // Update is_active for backward compatibility
                $updateData['is_active'] = ($request->status === TahunAjaran::STATUS_ACTIVE);
            }

            $tahunAjaran->update($updateData);

            $setup = null;
            if (($updateData['status'] ?? null) === TahunAjaran::STATUS_ACTIVE) {
                $setup = $this->periodeAkademikSetupService->ensureDefaultForTahunAjaran($tahunAjaran->fresh(), auth()->id());
            }

            return response()->json([
                'success' => true,
                'data' => $tahunAjaran,
                'setup' => $setup,
                'message' => 'Tahun ajaran berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TahunAjaranController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui tahun ajaran'
            ], 500);
        }
    }

    /**
     * Activate tahun ajaran and ensure default periode exists
     */
    public function activate($id)
    {
        try {
            $tahunAjaran = TahunAjaran::find($id);

            if (!$tahunAjaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran tidak ditemukan'
                ], 404);
            }

            DB::beginTransaction();

            $tahunAjaran->transitionTo(TahunAjaran::STATUS_ACTIVE, [
                'activated_via' => 'activate_endpoint',
            ]);

            $tahunAjaran->update([
                'is_active' => true
            ]);

            $setup = $this->periodeAkademikSetupService->ensureDefaultForTahunAjaran($tahunAjaran->fresh(), auth()->id());

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $tahunAjaran->fresh(),
                'setup' => $setup,
                'message' => 'Tahun ajaran berhasil diaktifkan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in TahunAjaranController@activate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengaktifkan tahun ajaran'
            ], 500);
        }
    }

    /**
     * Transition tahun ajaran status
     */
    public function transitionStatus(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'status' => 'required|in:draft,preparation,active,completed,archived',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tahunAjaran = TahunAjaran::find($id);

            if (!$tahunAjaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran tidak ditemukan'
                ], 404);
            }

            DB::beginTransaction();

            $tahunAjaran->transitionTo($request->status, $request->metadata ?? []);

            // Update is_active for backward compatibility
            $tahunAjaran->update([
                'is_active' => $request->status === TahunAjaran::STATUS_ACTIVE
            ]);

            $setup = null;
            if ($request->status === TahunAjaran::STATUS_ACTIVE) {
                $setup = $this->periodeAkademikSetupService->ensureDefaultForTahunAjaran($tahunAjaran->fresh(), auth()->id());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $tahunAjaran->fresh(),
                'setup' => $setup,
                'message' => "Status tahun ajaran berhasil diubah ke {$request->status}"
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in TahunAjaranController@transitionStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengubah status tahun ajaran'
            ], 500);
        }
    }

    /**
     * Update preparation progress
     */
    public function updateProgress(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'progress' => 'required|integer|min:0|max:100',
                'metadata' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tahunAjaran = TahunAjaran::find($id);

            if (!$tahunAjaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran tidak ditemukan'
                ], 404);
            }

            $tahunAjaran->updatePreparationProgress($request->progress, $request->metadata ?? []);

            return response()->json([
                'success' => true,
                'data' => $tahunAjaran->fresh(),
                'message' => 'Progress persiapan berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TahunAjaranController@updateProgress: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui progress'
            ], 500);
        }
    }

    /**
     * Delete tahun ajaran
     */
    public function destroy($id)
    {
        try {
            $tahunAjaran = TahunAjaran::find($id);

            if (!$tahunAjaran) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran tidak ditemukan'
                ], 404);
            }

            // Only allow deletion if draft or preparation
            if (!in_array($tahunAjaran->status, [TahunAjaran::STATUS_DRAFT, TahunAjaran::STATUS_PREPARATION])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya tahun ajaran dengan status draft atau preparation yang dapat dihapus'
                ], 422);
            }

            // Check if has classes
            if ($tahunAjaran->kelas()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus tahun ajaran yang sudah memiliki kelas'
                ], 422);
            }

            $tahunAjaran->delete();

            return response()->json([
                'success' => true,
                'message' => 'Tahun ajaran berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in TahunAjaranController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus tahun ajaran'
            ], 500);
        }
    }

    private function resolveSemesterDisplay(?string $semester): string
    {
        return match (strtolower((string) $semester)) {
            'ganjil' => 'Ganjil',
            'genap' => 'Genap',
            'full' => 'Ganjil & Genap',
            default => '-',
        };
    }

    private function validateSemesterDateRange(string $semester, string $tanggalMulai, string $tanggalSelesai): ?string
    {
        try {
            $start = Carbon::parse($tanggalMulai);
            $end = Carbon::parse($tanggalSelesai);
        } catch (\Throwable $e) {
            return 'Tanggal mulai/selesai tidak valid untuk evaluasi semester.';
        }

        $startMonth = (int) $start->month;
        $endMonth = (int) $end->month;
        $startYear = (int) $start->year;
        $endYear = (int) $end->year;
        $normalizedSemester = strtolower(trim($semester));

        if ($normalizedSemester === 'ganjil') {
            $isValidGanjilRange = $startMonth >= 7 && $startMonth <= 12
                && $endMonth >= 7 && $endMonth <= 12
                && $startYear === $endYear;

            return $isValidGanjilRange
                ? null
                : 'Semester ganjil harus berada pada rentang Juli-Desember di tahun kalender yang sama.';
        }

        if ($normalizedSemester === 'genap') {
            $isValidGenapRange = $startMonth >= 1 && $startMonth <= 6
                && $endMonth >= 1 && $endMonth <= 6
                && $startYear === $endYear;

            return $isValidGenapRange
                ? null
                : 'Semester genap harus berada pada rentang Januari-Juni di tahun kalender yang sama.';
        }

        if ($normalizedSemester === 'full') {
            $isValidFullRange = $startMonth >= 7 && $startMonth <= 12
                && $endMonth >= 1 && $endMonth <= 6
                && $endYear === ($startYear + 1);

            return $isValidFullRange
                ? null
                : 'Semester full harus lintas tahun ajaran: mulai Juli-Desember dan selesai Januari-Juni tahun berikutnya.';
        }

        return null;
    }

    /**
     * @return array{
     *     ganjil: array{label:string,tanggal_mulai:string,tanggal_selesai:string}|null,
     *     genap: array{label:string,tanggal_mulai:string,tanggal_selesai:string}|null
     * }
     */
    private function resolveSemesterPeriods(mixed $tanggalMulai, mixed $tanggalSelesai): array
    {
        try {
            $start = Carbon::parse((string) $tanggalMulai)->startOfDay();
            $end = Carbon::parse((string) $tanggalSelesai)->endOfDay();
        } catch (\Throwable $e) {
            return [
                'ganjil' => null,
                'genap' => null,
            ];
        }

        if ($end->lt($start)) {
            return [
                'ganjil' => null,
                'genap' => null,
            ];
        }

        $cursor = $start->copy();
        $periods = [];

        while ($cursor->lte($end)) {
            $semester = $cursor->month >= 7 ? 'ganjil' : 'genap';

            $segmentEnd = $cursor->month >= 7
                ? $cursor->copy()->month(12)->endOfMonth()
                : $cursor->copy()->month(6)->endOfMonth();

            if ($segmentEnd->gt($end)) {
                $segmentEnd = $end->copy();
            }

            if (!isset($periods[$semester])) {
                $periods[$semester] = [
                    'label' => ucfirst($semester),
                    'tanggal_mulai' => $cursor->format('Y-m-d'),
                    'tanggal_selesai' => $segmentEnd->format('Y-m-d'),
                ];
            } else {
                $existingStart = Carbon::parse($periods[$semester]['tanggal_mulai']);
                $existingEnd = Carbon::parse($periods[$semester]['tanggal_selesai']);
                if ($cursor->lt($existingStart)) {
                    $periods[$semester]['tanggal_mulai'] = $cursor->format('Y-m-d');
                }
                if ($segmentEnd->gt($existingEnd)) {
                    $periods[$semester]['tanggal_selesai'] = $segmentEnd->format('Y-m-d');
                }
            }

            $cursor = $segmentEnd->copy()->addDay()->startOfDay();
        }

        return [
            'ganjil' => $periods['ganjil'] ?? null,
            'genap' => $periods['genap'] ?? null,
        ];
    }
}

