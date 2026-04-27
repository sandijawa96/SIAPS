<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PeriodeAkademik;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class PeriodeAkademikController extends Controller
{
    private function validateTahunAjaranRange(int $tahunAjaranId, string $tanggalMulai, string $tanggalSelesai): ?string
    {
        $tahunAjaran = TahunAjaran::find($tahunAjaranId);
        if (!$tahunAjaran) {
            return 'Tahun ajaran tidak ditemukan';
        }

        if ($tanggalMulai < $tahunAjaran->tanggal_mulai->format('Y-m-d') || $tanggalSelesai > $tahunAjaran->tanggal_selesai->format('Y-m-d')) {
            return "Rentang periode harus berada dalam Tahun Ajaran {$tahunAjaran->nama} ({$tahunAjaran->tanggal_mulai->format('Y-m-d')} s/d {$tahunAjaran->tanggal_selesai->format('Y-m-d')})";
        }

        return null;
    }

    /**
     * Get all periode akademik with optional filters
     */
    public function index(Request $request)
    {
        try {
            $query = PeriodeAkademik::with(['tahunAjaran']);

            // Filter by tahun ajaran
            if ($request->has('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            }

            // Filter by jenis
            if ($request->has('jenis')) {
                $query->where('jenis', $request->jenis);
            }

            // Filter by semester
            if ($request->has('semester')) {
                $query->where('semester', $request->semester);
            }

            // Filter by status
            if ($request->has('status')) {
                switch ($request->status) {
                    case 'berjalan':
                        $query->berjalan();
                        break;
                    case 'active':
                        $query->active();
                        break;
                }
            }

            // Order by tanggal mulai
            $query->orderBy('tanggal_mulai', 'asc');

            // Pagination or all
            if ($request->boolean('no_pagination')) {
                $periodeAkademik = $query->get();
            } else {
                $perPage = $request->get('per_page', 15);
                $periodeAkademik = $query->paginate($perPage);
            }

            // Transform data
            $transform = function ($item) {
                return [
                    'id' => $item->id,
                    'tahun_ajaran_id' => $item->tahun_ajaran_id,
                    'tahun_ajaran' => $item->tahunAjaran ? [
                        'id' => $item->tahunAjaran->id,
                        'nama' => $item->tahunAjaran->nama,
                        'status' => $item->tahunAjaran->status
                    ] : null,
                    'nama' => $item->nama,
                    'jenis' => $item->jenis,
                    'jenis_display' => $item->jenis_display,
                    'tanggal_mulai' => $item->tanggal_mulai,
                    'tanggal_selesai' => $item->tanggal_selesai,
                    'semester' => $item->semester,
                    'semester_display' => $item->semester_display,
                    'is_active' => $item->is_active,
                    'status_display' => $item->status_display,
                    'keterangan' => $item->keterangan,
                    'durasi_hari' => $item->durasi_hari,
                    'sisa_hari' => $item->sisa_hari,
                    'progress_persentase' => $item->progress_persentase,
                    'is_berjalan' => $item->isBerjalan(),
                    'is_selesai' => $item->isSelesai(),
                    'is_belum_mulai' => $item->isBelumMulai(),
                    'metadata' => $item->metadata,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at
                ];
            };

            if ($request->boolean('no_pagination')) {
                $data = $periodeAkademik->map($transform);
            } else {
                $periodeAkademik->getCollection()->transform($transform);
                $data = $periodeAkademik;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Data periode akademik berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PeriodeAkademikController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data periode akademik'
            ], 500);
        }
    }

    /**
     * Store new periode akademik
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'nama' => 'required|string|max:100',
                'jenis' => 'required|in:pembelajaran,ujian,libur,orientasi',
                'tanggal_mulai' => 'required|date',
                'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
                'semester' => 'required|in:ganjil,genap,both',
                'is_active' => 'boolean',
                'keterangan' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $rangeError = $this->validateTahunAjaranRange(
                (int) $request->tahun_ajaran_id,
                (string) $request->tanggal_mulai,
                (string) $request->tanggal_selesai
            );

            if ($rangeError) {
                return response()->json([
                    'success' => false,
                    'message' => $rangeError
                ], 422);
            }

            // Cek overlap periode dalam tahun ajaran yang sama
            $overlap = PeriodeAkademik::where('tahun_ajaran_id', $request->tahun_ajaran_id)
                ->where('jenis', $request->jenis)
                ->where('semester', $request->semester)
                ->where(function ($query) use ($request) {
                    $query->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
                        ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                                ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
                        });
                })
                ->exists();

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode akademik bertabrakan dengan periode yang sudah ada'
                ], 422);
            }

            $periodeAkademik = PeriodeAkademik::create([
                'tahun_ajaran_id' => $request->tahun_ajaran_id,
                'nama' => $request->nama,
                'jenis' => $request->jenis,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'semester' => $request->semester,
                'is_active' => $request->boolean('is_active', true),
                'keterangan' => $request->keterangan,
                'metadata' => [
                    'created_by' => auth()->id() ?? null,
                    'created_at' => now()->toDateTimeString()
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $periodeAkademik->load('tahunAjaran'),
                'message' => 'Periode akademik berhasil dibuat'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in PeriodeAkademikController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat periode akademik'
            ], 500);
        }
    }

    /**
     * Show specific periode akademik
     */
    public function show($id)
    {
        try {
            $periodeAkademik = PeriodeAkademik::with(['tahunAjaran', 'eventAkademik'])->find($id);

            if (!$periodeAkademik) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode akademik tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $periodeAkademik->id,
                    'tahun_ajaran_id' => $periodeAkademik->tahun_ajaran_id,
                    'tahun_ajaran' => $periodeAkademik->tahunAjaran,
                    'nama' => $periodeAkademik->nama,
                    'jenis' => $periodeAkademik->jenis,
                    'jenis_display' => $periodeAkademik->jenis_display,
                    'tanggal_mulai' => $periodeAkademik->tanggal_mulai,
                    'tanggal_selesai' => $periodeAkademik->tanggal_selesai,
                    'semester' => $periodeAkademik->semester,
                    'semester_display' => $periodeAkademik->semester_display,
                    'is_active' => $periodeAkademik->is_active,
                    'status_display' => $periodeAkademik->status_display,
                    'keterangan' => $periodeAkademik->keterangan,
                    'durasi_hari' => $periodeAkademik->durasi_hari,
                    'sisa_hari' => $periodeAkademik->sisa_hari,
                    'progress_persentase' => $periodeAkademik->progress_persentase,
                    'is_berjalan' => $periodeAkademik->isBerjalan(),
                    'is_selesai' => $periodeAkademik->isSelesai(),
                    'is_belum_mulai' => $periodeAkademik->isBelumMulai(),
                    'event_akademik' => $periodeAkademik->eventAkademik,
                    'metadata' => $periodeAkademik->metadata,
                    'created_at' => $periodeAkademik->created_at,
                    'updated_at' => $periodeAkademik->updated_at
                ],
                'message' => 'Detail periode akademik berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PeriodeAkademikController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail periode akademik'
            ], 500);
        }
    }

    /**
     * Update periode akademik
     */
    public function update(Request $request, $id)
    {
        try {
            $periodeAkademik = PeriodeAkademik::find($id);

            if (!$periodeAkademik) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode akademik tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'nama' => 'required|string|max:100',
                'jenis' => 'required|in:pembelajaran,ujian,libur,orientasi',
                'tanggal_mulai' => 'required|date',
                'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
                'semester' => 'required|in:ganjil,genap,both',
                'is_active' => 'boolean',
                'keterangan' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $rangeError = $this->validateTahunAjaranRange(
                (int) $request->tahun_ajaran_id,
                (string) $request->tanggal_mulai,
                (string) $request->tanggal_selesai
            );

            if ($rangeError) {
                return response()->json([
                    'success' => false,
                    'message' => $rangeError
                ], 422);
            }

            // Cek overlap periode (exclude current record)
            $overlap = PeriodeAkademik::where('tahun_ajaran_id', $request->tahun_ajaran_id)
                ->where('jenis', $request->jenis)
                ->where('semester', $request->semester)
                ->where('id', '!=', $id)
                ->where(function ($query) use ($request) {
                    $query->whereBetween('tanggal_mulai', [$request->tanggal_mulai, $request->tanggal_selesai])
                        ->orWhereBetween('tanggal_selesai', [$request->tanggal_mulai, $request->tanggal_selesai])
                        ->orWhere(function ($q) use ($request) {
                            $q->where('tanggal_mulai', '<=', $request->tanggal_mulai)
                                ->where('tanggal_selesai', '>=', $request->tanggal_selesai);
                        });
                })
                ->exists();

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode akademik bertabrakan dengan periode yang sudah ada'
                ], 422);
            }

            $periodeAkademik->update([
                'tahun_ajaran_id' => $request->tahun_ajaran_id,
                'nama' => $request->nama,
                'jenis' => $request->jenis,
                'tanggal_mulai' => $request->tanggal_mulai,
                'tanggal_selesai' => $request->tanggal_selesai,
                'semester' => $request->semester,
                'is_active' => $request->boolean('is_active', true),
                'keterangan' => $request->keterangan,
                'metadata' => array_merge($periodeAkademik->metadata ?? [], [
                    'updated_by' => auth()->id() ?? null,
                    'updated_at' => now()->toDateTimeString()
                ])
            ]);

            return response()->json([
                'success' => true,
                'data' => $periodeAkademik->load('tahunAjaran'),
                'message' => 'Periode akademik berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PeriodeAkademikController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui periode akademik'
            ], 500);
        }
    }

    /**
     * Delete periode akademik
     */
    public function destroy($id)
    {
        try {
            $periodeAkademik = PeriodeAkademik::find($id);

            if (!$periodeAkademik) {
                return response()->json([
                    'success' => false,
                    'message' => 'Periode akademik tidak ditemukan'
                ], 404);
            }

            // Check if has related events
            if ($periodeAkademik->eventAkademik()->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak dapat menghapus periode akademik yang memiliki event'
                ], 422);
            }

            $periodeAkademik->delete();

            return response()->json([
                'success' => true,
                'message' => 'Periode akademik berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PeriodeAkademikController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus periode akademik'
            ], 500);
        }
    }

    /**
     * Get current running periode
     */
    public function getCurrentPeriode(Request $request)
    {
        try {
            $tahunAjaranId = $request->filled('tahun_ajaran_id')
                ? (int) $request->tahun_ajaran_id
                : null;

            if (!$tahunAjaranId) {
                $tahunAjaranId = TahunAjaran::query()
                    ->where('status', TahunAjaran::STATUS_ACTIVE)
                    ->value('id');
            }

            if (!$tahunAjaranId) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'meta' => [
                        'needs_setup' => true,
                        'reason' => 'no_active_tahun_ajaran',
                    ],
                    'message' => 'Belum ada tahun ajaran aktif'
                ]);
            }

            $periode = PeriodeAkademik::getCurrentPeriod($tahunAjaranId);

            if (!$periode) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'meta' => [
                        'needs_setup' => true,
                        'reason' => 'no_running_periode',
                        'tahun_ajaran_id' => $tahunAjaranId,
                    ],
                    'message' => 'Tidak ada periode akademik yang sedang berjalan'
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $periode->load('tahunAjaran'),
                'meta' => [
                    'needs_setup' => false,
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
                'message' => 'Periode akademik saat ini berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PeriodeAkademikController@getCurrentPeriode: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil periode akademik saat ini'
            ], 500);
        }
    }

    /**
     * Check if absensi is valid for specific date
     */
    public function checkAbsensiValidity(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tanggal' => 'required|date',
                'tahun_ajaran_id' => 'nullable|exists:tahun_ajaran,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $isValid = PeriodeAkademik::isValidAbsensiDate(
                $request->tanggal,
                $request->tahun_ajaran_id
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                    'tanggal' => $request->tanggal,
                    'tahun_ajaran_id' => $request->tahun_ajaran_id
                ],
                'message' => $isValid ? 'Absensi valid untuk tanggal ini' : 'Absensi tidak valid untuk tanggal ini'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in PeriodeAkademikController@checkAbsensiValidity: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa validitas absensi'
            ], 500);
        }
    }
}

