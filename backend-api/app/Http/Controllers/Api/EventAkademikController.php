<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventAkademik;
use App\Models\TahunAjaran;
use App\Models\PeriodeAkademik;
use App\Services\KalenderAkademikSyncService;
use App\Services\KalenderIndonesiaService;
use App\Services\LiburNasionalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class EventAkademikController extends Controller
{
    public function __construct(
        private readonly KalenderAkademikSyncService $kalenderAkademikSyncService,
        private readonly KalenderIndonesiaService $kalenderIndonesiaService
    )
    {
    }

    private function validateAcademicConsistency(array $payload): ?string
    {
        $tahunAjaran = TahunAjaran::find((int) $payload['tahun_ajaran_id']);
        if (!$tahunAjaran) {
            return 'Tahun ajaran tidak ditemukan';
        }

        $tanggalMulai = (string) $payload['tanggal_mulai'];
        $tanggalSelesai = (string) (($payload['tanggal_selesai'] ?? null) ?: $payload['tanggal_mulai']);

        if ($tanggalMulai < $tahunAjaran->tanggal_mulai->format('Y-m-d') || $tanggalSelesai > $tahunAjaran->tanggal_selesai->format('Y-m-d')) {
            return "Rentang event harus berada dalam Tahun Ajaran {$tahunAjaran->nama} ({$tahunAjaran->tanggal_mulai->format('Y-m-d')} s/d {$tahunAjaran->tanggal_selesai->format('Y-m-d')})";
        }

        if (!empty($payload['periode_akademik_id'])) {
            $periode = PeriodeAkademik::find((int) $payload['periode_akademik_id']);
            if (!$periode) {
                return 'Periode akademik tidak ditemukan';
            }

            if ((int) $periode->tahun_ajaran_id !== (int) $payload['tahun_ajaran_id']) {
                return 'Periode akademik harus berasal dari Tahun Ajaran yang sama';
            }

            if ($tanggalMulai < $periode->tanggal_mulai->format('Y-m-d') || $tanggalSelesai > $periode->tanggal_selesai->format('Y-m-d')) {
                return "Rentang event harus berada dalam Periode {$periode->nama} ({$periode->tanggal_mulai->format('Y-m-d')} s/d {$periode->tanggal_selesai->format('Y-m-d')})";
            }
        }

        return null;
    }

    private function resolveAutoPeriodeAkademikId(array $payload): ?int
    {
        if (!empty($payload['periode_akademik_id'])) {
            return (int) $payload['periode_akademik_id'];
        }

        if (empty($payload['tahun_ajaran_id']) || empty($payload['tanggal_mulai'])) {
            return null;
        }

        $tanggalMulai = (string) $payload['tanggal_mulai'];
        $tanggalSelesai = (string) (($payload['tanggal_selesai'] ?? null) ?: $payload['tanggal_mulai']);

        $periode = PeriodeAkademik::query()
            ->where('tahun_ajaran_id', (int) $payload['tahun_ajaran_id'])
            ->where('is_active', true)
            ->where('tanggal_mulai', '<=', $tanggalMulai)
            ->where('tanggal_selesai', '>=', $tanggalSelesai)
            ->orderBy('tanggal_mulai')
            ->first();

        return $periode ? (int) $periode->id : null;
    }

    private function resolveEffectiveTahunAjaranId(?int $requestedTahunAjaranId = null): ?int
    {
        if ($requestedTahunAjaranId) {
            return $requestedTahunAjaranId;
        }

        return TahunAjaran::query()
            ->where('status', TahunAjaran::STATUS_ACTIVE)
            ->value('id');
    }

    /**
     * Get all event akademik with optional filters
     */
    public function index(Request $request)
    {
        try {
            $query = EventAkademik::with(['tahunAjaran', 'periodeAkademik', 'tingkat', 'kelas']);

            // Filter by tahun ajaran
            if ($request->has('tahun_ajaran_id')) {
                $query->where('tahun_ajaran_id', $request->tahun_ajaran_id);
            }

            // Filter by periode akademik
            if ($request->has('periode_akademik_id')) {
                $query->where('periode_akademik_id', $request->periode_akademik_id);
            }

            // Filter by jenis
            if ($request->has('jenis')) {
                $query->where('jenis', $request->jenis);
            }

            // Filter by tingkat
            if ($request->has('tingkat_id')) {
                $query->byTingkat($request->tingkat_id);
            }

            // Filter by kelas
            if ($request->has('kelas_id')) {
                $query->byKelas($request->kelas_id);
            }

            // Filter by status
            if ($request->has('status')) {
                switch ($request->status) {
                    case 'berjalan':
                        $query->berjalan();
                        break;
                    case 'mendatang':
                        $days = $request->get('days', 7);
                        $query->mendatang($days);
                        break;
                    case 'hari_ini':
                        $query->hariIni();
                        break;
                    case 'active':
                        $query->active();
                        break;
                    case 'wajib':
                        $query->wajib();
                        break;
                }
            }

            // Filter by date range
            if ($request->has('tanggal_mulai') && $request->has('tanggal_selesai')) {
                $startDate = (string) $request->tanggal_mulai;
                $endDate = (string) $request->tanggal_selesai;

                $query->where('tanggal_mulai', '<=', $endDate)
                    ->where(function ($rangeQuery) use ($startDate) {
                        $rangeQuery->whereNull('tanggal_selesai')
                            ->where('tanggal_mulai', '>=', $startDate)
                            ->orWhere('tanggal_selesai', '>=', $startDate);
                    });
            }

            // Order by tanggal mulai
            $query->orderBy('tanggal_mulai', 'asc')->orderBy('waktu_mulai', 'asc');

            // Pagination or all
            if ($request->boolean('no_pagination')) {
                $eventAkademik = $query->get();
            } else {
                $perPage = $request->get('per_page', 15);
                $eventAkademik = $query->paginate($perPage);
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
                    'periode_akademik_id' => $item->periode_akademik_id,
                    'periode_akademik' => $item->periodeAkademik ? [
                        'id' => $item->periodeAkademik->id,
                        'nama' => $item->periodeAkademik->nama,
                        'jenis' => $item->periodeAkademik->jenis
                    ] : null,
                    'nama' => $item->nama,
                    'jenis' => $item->jenis,
                    'jenis_display' => $item->jenis_display,
                    'tanggal_mulai' => $item->tanggal_mulai,
                    'tanggal_selesai' => $item->tanggal_selesai,
                    'tanggal_display' => $item->tanggal_display,
                    'waktu_mulai' => $item->waktu_mulai,
                    'waktu_selesai' => $item->waktu_selesai,
                    'waktu_display' => $item->waktu_display,
                    'tingkat_id' => $item->tingkat_id,
                    'tingkat' => $item->tingkat ? [
                        'id' => $item->tingkat->id,
                        'nama' => $item->tingkat->nama
                    ] : null,
                    'kelas_id' => $item->kelas_id,
                    'kelas' => $item->kelas ? [
                        'id' => $item->kelas->id,
                        'nama' => $item->kelas->nama_kelas ?? $item->kelas->nama
                    ] : null,
                    'scope_display' => $item->scope_display,
                    'is_wajib' => $item->is_wajib,
                    'is_active' => $item->is_active,
                    'status_display' => $item->status_display,
                    'deskripsi' => $item->deskripsi,
                    'lokasi' => $item->lokasi,
                    'durasi_hari' => $item->durasi_hari,
                    'sisa_hari' => $item->sisa_hari,
                    'is_berjalan' => $item->isBerjalan(),
                    'is_selesai' => $item->isSelesai(),
                    'is_belum_mulai' => $item->isBelumMulai(),
                    'metadata' => $item->metadata,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at
                ];
            };

            if ($request->boolean('no_pagination')) {
                $data = $eventAkademik->map($transform);
            } else {
                $eventAkademik->getCollection()->transform($transform);
                $data = $eventAkademik;
            }

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Data event akademik berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@index: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data event akademik'
            ], 500);
        }
    }

    /**
     * Store new event akademik
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'periode_akademik_id' => 'nullable|exists:periode_akademik,id',
                'nama' => 'required|string|max:200',
                'jenis' => 'required|in:ujian,libur,kegiatan,deadline,rapat,pelatihan',
                'tanggal_mulai' => 'required|date',
                'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
                'waktu_mulai' => 'nullable|date_format:H:i',
                'waktu_selesai' => 'nullable|date_format:H:i|after:waktu_mulai',
                'tingkat_id' => 'nullable|exists:tingkat,id',
                'kelas_id' => 'nullable|exists:kelas,id',
                'is_wajib' => 'boolean',
                'is_active' => 'boolean',
                'deskripsi' => 'nullable|string|max:2000',
                'lokasi' => 'nullable|string|max:200'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payload = $request->all();
            $payload['periode_akademik_id'] = $this->resolveAutoPeriodeAkademikId($payload);

            $consistencyError = $this->validateAcademicConsistency($payload);
            if ($consistencyError) {
                return response()->json([
                    'success' => false,
                    'message' => $consistencyError
                ], 422);
            }

            // Validasi kelas harus sesuai dengan tingkat jika keduanya diisi
            if (!empty($payload['tingkat_id']) && !empty($payload['kelas_id'])) {
                $kelas = \App\Models\Kelas::find((int) $payload['kelas_id']);
                if ($kelas && (int) $kelas->tingkat_id !== (int) $payload['tingkat_id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kelas tidak sesuai dengan tingkat yang dipilih'
                    ], 422);
                }
            }

            $eventAkademik = EventAkademik::create([
                'tahun_ajaran_id' => $payload['tahun_ajaran_id'],
                'periode_akademik_id' => $payload['periode_akademik_id'],
                'nama' => $payload['nama'],
                'jenis' => $payload['jenis'],
                'tanggal_mulai' => $payload['tanggal_mulai'],
                'tanggal_selesai' => $payload['tanggal_selesai'] ?? null,
                'waktu_mulai' => $payload['waktu_mulai'] ?? null,
                'waktu_selesai' => $payload['waktu_selesai'] ?? null,
                'tingkat_id' => $payload['tingkat_id'] ?? null,
                'kelas_id' => $payload['kelas_id'] ?? null,
                'is_wajib' => $request->boolean('is_wajib', false),
                'is_active' => $request->boolean('is_active', true),
                'deskripsi' => $payload['deskripsi'] ?? null,
                'lokasi' => $payload['lokasi'] ?? null,
                'metadata' => [
                    'created_by' => auth()->id() ?? null,
                    'created_at' => now()->toDateTimeString()
                ]
            ]);

            $this->kalenderAkademikSyncService->syncEvent($eventAkademik, auth()->id());

            return response()->json([
                'success' => true,
                'data' => $eventAkademik->load(['tahunAjaran', 'periodeAkademik', 'tingkat', 'kelas']),
                'message' => 'Event akademik berhasil dibuat'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@store: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat event akademik'
            ], 500);
        }
    }

    /**
     * Show specific event akademik
     */
    public function show($id)
    {
        try {
            $eventAkademik = EventAkademik::with(['tahunAjaran', 'periodeAkademik', 'tingkat', 'kelas'])->find($id);

            if (!$eventAkademik) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event akademik tidak ditemukan'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $eventAkademik->id,
                    'tahun_ajaran_id' => $eventAkademik->tahun_ajaran_id,
                    'tahun_ajaran' => $eventAkademik->tahunAjaran,
                    'periode_akademik_id' => $eventAkademik->periode_akademik_id,
                    'periode_akademik' => $eventAkademik->periodeAkademik,
                    'nama' => $eventAkademik->nama,
                    'jenis' => $eventAkademik->jenis,
                    'jenis_display' => $eventAkademik->jenis_display,
                    'tanggal_mulai' => $eventAkademik->tanggal_mulai,
                    'tanggal_selesai' => $eventAkademik->tanggal_selesai,
                    'tanggal_display' => $eventAkademik->tanggal_display,
                    'waktu_mulai' => $eventAkademik->waktu_mulai,
                    'waktu_selesai' => $eventAkademik->waktu_selesai,
                    'waktu_display' => $eventAkademik->waktu_display,
                    'tingkat_id' => $eventAkademik->tingkat_id,
                    'tingkat' => $eventAkademik->tingkat,
                    'kelas_id' => $eventAkademik->kelas_id,
                    'kelas' => $eventAkademik->kelas,
                    'scope_display' => $eventAkademik->scope_display,
                    'is_wajib' => $eventAkademik->is_wajib,
                    'is_active' => $eventAkademik->is_active,
                    'status_display' => $eventAkademik->status_display,
                    'deskripsi' => $eventAkademik->deskripsi,
                    'lokasi' => $eventAkademik->lokasi,
                    'durasi_hari' => $eventAkademik->durasi_hari,
                    'sisa_hari' => $eventAkademik->sisa_hari,
                    'is_berjalan' => $eventAkademik->isBerjalan(),
                    'is_selesai' => $eventAkademik->isSelesai(),
                    'is_belum_mulai' => $eventAkademik->isBelumMulai(),
                    'metadata' => $eventAkademik->metadata,
                    'created_at' => $eventAkademik->created_at,
                    'updated_at' => $eventAkademik->updated_at
                ],
                'message' => 'Detail event akademik berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@show: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail event akademik'
            ], 500);
        }
    }

    /**
     * Update event akademik
     */
    public function update(Request $request, $id)
    {
        try {
            $eventAkademik = EventAkademik::find($id);

            if (!$eventAkademik) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event akademik tidak ditemukan'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'periode_akademik_id' => 'nullable|exists:periode_akademik,id',
                'nama' => 'required|string|max:200',
                'jenis' => 'required|in:ujian,libur,kegiatan,deadline,rapat,pelatihan',
                'tanggal_mulai' => 'required|date',
                'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
                'waktu_mulai' => 'nullable|date_format:H:i',
                'waktu_selesai' => 'nullable|date_format:H:i|after:waktu_mulai',
                'tingkat_id' => 'nullable|exists:tingkat,id',
                'kelas_id' => 'nullable|exists:kelas,id',
                'is_wajib' => 'boolean',
                'is_active' => 'boolean',
                'deskripsi' => 'nullable|string|max:2000',
                'lokasi' => 'nullable|string|max:200'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payload = $request->all();
            $payload['periode_akademik_id'] = $this->resolveAutoPeriodeAkademikId($payload);

            $consistencyError = $this->validateAcademicConsistency($payload);
            if ($consistencyError) {
                return response()->json([
                    'success' => false,
                    'message' => $consistencyError
                ], 422);
            }

            // Validasi kelas harus sesuai dengan tingkat jika keduanya diisi
            if (!empty($payload['tingkat_id']) && !empty($payload['kelas_id'])) {
                $kelas = \App\Models\Kelas::find((int) $payload['kelas_id']);
                if ($kelas && (int) $kelas->tingkat_id !== (int) $payload['tingkat_id']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Kelas tidak sesuai dengan tingkat yang dipilih'
                    ], 422);
                }
            }

            $wasLibur = $eventAkademik->jenis === EventAkademik::JENIS_LIBUR;
            $eventId = (int) $eventAkademik->id;

            $eventAkademik->update([
                'tahun_ajaran_id' => $payload['tahun_ajaran_id'],
                'periode_akademik_id' => $payload['periode_akademik_id'],
                'nama' => $payload['nama'],
                'jenis' => $payload['jenis'],
                'tanggal_mulai' => $payload['tanggal_mulai'],
                'tanggal_selesai' => $payload['tanggal_selesai'] ?? null,
                'waktu_mulai' => $payload['waktu_mulai'] ?? null,
                'waktu_selesai' => $payload['waktu_selesai'] ?? null,
                'tingkat_id' => $payload['tingkat_id'] ?? null,
                'kelas_id' => $payload['kelas_id'] ?? null,
                'is_wajib' => $request->boolean('is_wajib', false),
                'is_active' => $request->boolean('is_active', true),
                'deskripsi' => $payload['deskripsi'] ?? null,
                'lokasi' => $payload['lokasi'] ?? null,
                'metadata' => array_merge($eventAkademik->metadata ?? [], [
                    'updated_by' => auth()->id() ?? null,
                    'updated_at' => now()->toDateTimeString()
                ])
            ]);

            if ($eventAkademik->jenis === EventAkademik::JENIS_LIBUR) {
                $this->kalenderAkademikSyncService->syncEvent($eventAkademik, auth()->id());
            } elseif ($wasLibur) {
                $this->kalenderAkademikSyncService->removeEvent($eventId);
            }

            return response()->json([
                'success' => true,
                'data' => $eventAkademik->load(['tahunAjaran', 'periodeAkademik', 'tingkat', 'kelas']),
                'message' => 'Event akademik berhasil diperbarui'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@update: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui event akademik'
            ], 500);
        }
    }

    /**
     * Delete event akademik
     */
    public function destroy($id)
    {
        try {
            $eventAkademik = EventAkademik::find($id);

            if (!$eventAkademik) {
                return response()->json([
                    'success' => false,
                    'message' => 'Event akademik tidak ditemukan'
                ], 404);
            }

            if ($eventAkademik->jenis === EventAkademik::JENIS_LIBUR) {
                $this->kalenderAkademikSyncService->removeEvent((int) $eventAkademik->id);
            }

            $eventAkademik->delete();

            return response()->json([
                'success' => true,
                'message' => 'Event akademik berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@destroy: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus event akademik'
            ], 500);
        }
    }

    /**
     * Get upcoming events for current user
     */
    public function getUpcomingEvents(Request $request)
    {
        try {
            $user = auth()->user();
            $days = max(1, (int) $request->get('days', 7));
            $requestedTahunAjaranId = $request->filled('tahun_ajaran_id') ? (int) $request->tahun_ajaran_id : null;
            $tahunAjaranId = $this->resolveEffectiveTahunAjaranId($requestedTahunAjaranId);

            if (!$tahunAjaranId) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => [
                        'needs_setup' => true,
                        'reason' => 'no_active_tahun_ajaran',
                    ],
                    'message' => 'Belum ada tahun ajaran aktif'
                ]);
            }

            $events = EventAkademik::getUpcomingForUser($user, $days, $tahunAjaranId);

            return response()->json([
                'success' => true,
                'data' => $events->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'nama' => $event->nama,
                        'jenis' => $event->jenis,
                        'jenis_display' => $event->jenis_display,
                        'tanggal_display' => $event->tanggal_display,
                        'waktu_display' => $event->waktu_display,
                        'scope_display' => $event->scope_display,
                        'is_wajib' => $event->is_wajib,
                        'sisa_hari' => $event->sisa_hari,
                        'lokasi' => $event->lokasi,
                        'deskripsi' => $event->deskripsi
                    ];
                }),
                'meta' => [
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
                'message' => 'Event mendatang berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@getUpcomingEvents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil event mendatang'
            ], 500);
        }
    }

    /**
     * Get today's events for current user
     */
    public function getTodayEvents(Request $request)
    {
        try {
            $user = auth()->user();
            $requestedTahunAjaranId = $request->filled('tahun_ajaran_id') ? (int) $request->tahun_ajaran_id : null;
            $tahunAjaranId = $this->resolveEffectiveTahunAjaranId($requestedTahunAjaranId);

            if (!$tahunAjaranId) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => [
                        'needs_setup' => true,
                        'reason' => 'no_active_tahun_ajaran',
                    ],
                    'message' => 'Belum ada tahun ajaran aktif'
                ]);
            }

            $events = EventAkademik::getTodayForUser($user, $tahunAjaranId);

            return response()->json([
                'success' => true,
                'data' => $events->map(function ($event) {
                    return [
                        'id' => $event->id,
                        'nama' => $event->nama,
                        'jenis' => $event->jenis,
                        'jenis_display' => $event->jenis_display,
                        'tanggal_display' => $event->tanggal_display,
                        'waktu_display' => $event->waktu_display,
                        'scope_display' => $event->scope_display,
                        'is_wajib' => $event->is_wajib,
                        'lokasi' => $event->lokasi,
                        'deskripsi' => $event->deskripsi
                    ];
                }),
                'meta' => [
                    'tahun_ajaran_id' => $tahunAjaranId,
                ],
                'message' => 'Event hari ini berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@getTodayEvents: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil event hari ini'
            ], 500);
        }
    }

    /**
     * Preview libur nasional untuk tahun ajaran tertentu
     */
    public function previewLiburNasional(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $liburNasionalService = new LiburNasionalService();
            $preview = $liburNasionalService->previewLiburNasional($request->tahun_ajaran_id);

            return response()->json([
                'success' => true,
                'data' => $preview,
                'message' => 'Preview libur nasional berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@previewLiburNasional: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil preview libur nasional'
            ], 500);
        }
    }

    /**
     * Sync libur nasional untuk tahun ajaran tertentu
     */
    public function syncLiburNasional(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'publish' => 'nullable|boolean',
                'force_update' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $liburNasionalService = new LiburNasionalService();
            $result = $liburNasionalService->syncLiburNasional((int) $request->tahun_ajaran_id, [
                'publish' => $request->boolean('publish', true),
                'force_update' => $request->boolean('force_update', false),
            ]);

            $bridge = $this->kalenderAkademikSyncService->resyncByTahunAjaran((int) $request->tahun_ajaran_id, auth()->id());

            return response()->json([
                'success' => true,
                'data' => array_merge($result, ['bridge' => $bridge]),
                'message' => "Berhasil sync {$result['synced']} libur nasional, {$result['skipped']} sudah ada"
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@syncLiburNasional: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal sync libur nasional'
            ], 500);
        }
    }

    /**
     * Auto sync libur nasional untuk semua tahun ajaran aktif
     */
    public function autoSyncLiburNasional(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'publish' => 'nullable|boolean',
                'force_update' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $liburNasionalService = new LiburNasionalService();
            $results = $liburNasionalService->autoSyncAllActiveTahunAjaran([
                'publish' => $request->boolean('publish', true),
                'force_update' => $request->boolean('force_update', false),
            ]);

            $bridge = $this->kalenderAkademikSyncService->resyncByTahunAjaran(null, auth()->id());

            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $results,
                    'bridge' => $bridge,
                ],
                'message' => 'Auto sync libur nasional berhasil dilakukan'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@autoSyncLiburNasional: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal auto sync libur nasional'
            ], 500);
        }
    }

    /**
     * Preview peringatan kalender Indonesia untuk tahun ajaran tertentu (bukan libur nasional).
     */
    public function previewKalenderIndonesia(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $preview = $this->kalenderIndonesiaService->previewKalenderIndonesia((int) $request->tahun_ajaran_id);

            return response()->json([
                'success' => true,
                'data' => $preview,
                'message' => 'Preview kalender Indonesia berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@previewKalenderIndonesia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil preview kalender Indonesia'
            ], 500);
        }
    }

    /**
     * Sync peringatan kalender Indonesia untuk tahun ajaran tertentu (bukan libur nasional).
     */
    public function syncKalenderIndonesia(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'publish' => 'nullable|boolean',
                'force_update' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $result = $this->kalenderIndonesiaService->syncKalenderIndonesia((int) $request->tahun_ajaran_id, [
                'publish' => $request->boolean('publish', true),
                'force_update' => $request->boolean('force_update', false),
            ]);

            $bridge = $this->kalenderAkademikSyncService->resyncByTahunAjaran((int) $request->tahun_ajaran_id, auth()->id());

            return response()->json([
                'success' => true,
                'data' => array_merge($result, ['bridge' => $bridge]),
                'message' => "Berhasil sync {$result['synced']} kalender Indonesia ({$result['synced_libur']} libur, {$result['synced_kegiatan']} peringatan), {$result['skipped']} dilewati"
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@syncKalenderIndonesia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal sync kalender Indonesia'
            ], 500);
        }
    }

    /**
     * Sinkron kalender Indonesia lengkap: libur nasional + cuti bersama + peringatan hari besar.
     */
    public function syncKalenderIndonesiaLengkap(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'publish' => 'nullable|boolean',
                'force_update' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $options = [
                'publish' => $request->boolean('publish', true),
                'force_update' => $request->boolean('force_update', false),
            ];

            $tahunAjaranId = (int) $request->tahun_ajaran_id;

            $liburNasionalService = new LiburNasionalService();
            $liburResult = $liburNasionalService->syncLiburNasional($tahunAjaranId, $options);
            $kalenderResult = $this->kalenderIndonesiaService->syncKalenderIndonesia($tahunAjaranId, $options);

            $bridge = $this->kalenderAkademikSyncService->resyncByTahunAjaran($tahunAjaranId, auth()->id());

            $totalSynced = (int) ($liburResult['synced'] ?? 0) + (int) ($kalenderResult['synced'] ?? 0);
            $totalSkipped = (int) ($liburResult['skipped'] ?? 0) + (int) ($kalenderResult['skipped'] ?? 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'synced_total' => $totalSynced,
                        'skipped_total' => $totalSkipped,
                        'synced_libur_nasional' => (int) ($liburResult['synced_libur_nasional'] ?? 0),
                        'synced_cuti_bersama' => (int) ($liburResult['synced_cuti_bersama'] ?? 0),
                        'synced_peringatan' => (int) ($kalenderResult['synced_kegiatan'] ?? 0),
                        'synced_hari_besar_libur' => (int) ($kalenderResult['synced_libur'] ?? 0),
                        'had_api_error' => (bool) ($liburResult['had_api_error'] ?? false),
                    ],
                    'libur_nasional' => $liburResult,
                    'kalender_indonesia' => $kalenderResult,
                    'bridge' => $bridge,
                ],
                'message' => "Sinkron kalender Indonesia lengkap selesai: {$totalSynced} tersinkron, {$totalSkipped} dilewati"
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@syncKalenderIndonesiaLengkap: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal sinkron kalender Indonesia lengkap'
            ], 500);
        }
    }

    /**
     * Auto sync peringatan kalender Indonesia untuk semua tahun ajaran aktif/preparation.
     */
    public function autoSyncKalenderIndonesia(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'publish' => 'nullable|boolean',
                'force_update' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $results = $this->kalenderIndonesiaService->autoSyncAllActiveTahunAjaran([
                'publish' => $request->boolean('publish', true),
                'force_update' => $request->boolean('force_update', false),
            ]);

            $bridge = $this->kalenderAkademikSyncService->resyncByTahunAjaran(null, auth()->id());

            return response()->json([
                'success' => true,
                'data' => [
                    'results' => $results,
                    'bridge' => $bridge,
                ],
                'message' => 'Auto sync kalender Indonesia berhasil dilakukan'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@autoSyncKalenderIndonesia: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal auto sync kalender Indonesia'
            ], 500);
        }
    }

    /**
     * Manual resync event libur -> kalender_akademik untuk menjaga konsistensi runtime absensi
     */
    public function syncKalenderAbsensi(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'nullable|exists:tahun_ajaran,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tahunAjaranId = $request->filled('tahun_ajaran_id') ? (int) $request->tahun_ajaran_id : null;
            $result = $this->kalenderAkademikSyncService->resyncByTahunAjaran($tahunAjaranId, auth()->id());

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Sinkronisasi kalender absensi berhasil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@syncKalenderAbsensi: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal sinkronisasi kalender absensi'
            ], 500);
        }
    }

    /**
     * Status sinkronisasi event libur -> kalender_akademik untuk audit/admin observability
     */
    public function syncKalenderAbsensiStatus(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tahun_ajaran_id' => 'nullable|exists:tahun_ajaran,id',
                'limit' => 'nullable|integer|min:1|max:200',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid',
                    'errors' => $validator->errors()
                ], 422);
            }

            $tahunAjaranId = $request->filled('tahun_ajaran_id') ? (int) $request->tahun_ajaran_id : null;
            $limit = (int) $request->get('limit', 50);
            $status = $this->kalenderAkademikSyncService->getSyncStatus($tahunAjaranId, $limit);

            return response()->json([
                'success' => true,
                'data' => $status,
                'message' => 'Status sinkronisasi kalender absensi berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in EventAkademikController@syncKalenderAbsensiStatus: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil status sinkronisasi kalender absensi'
            ], 500);
        }
    }
}

