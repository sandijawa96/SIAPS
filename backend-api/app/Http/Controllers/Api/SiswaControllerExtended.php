<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\SiswaTransisi;
use App\Models\SiswaTransferRequest;
use App\Models\WaliKelasPromotionSetting;
use App\Models\Kelas;
use App\Models\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Support\RoleDataScope;
use App\Support\RoleNames;
use Carbon\Carbon;

class SiswaControllerExtended extends Controller
{
    public function export(Request $request)
    {
        try {
            $filename = 'data-siswa-lengkap-' . now()->format('Y-m-d-H-i-s') . '.xlsx';
            $actor = $request->user();
            $actorName = $actor->nama_lengkap ?? $actor->name ?? 'System';
            $actorEmail = $actor->email ?? '-';

            $meta = [
                'school_name' => 'SMAN 1 SUMBER',
                'school_region' => 'Kecamatan Kec. Sumber, Kabupaten Kab. Cirebon, Provinsi Prov. Jawa Barat',
                'downloaded_by' => trim($actorName . ' (' . $actorEmail . ')'),
                'downloaded_at' => now()->format('Y-m-d H:i:s'),
            ];

            return \Maatwebsite\Excel\Facades\Excel::download(
                new \App\Exports\SiswaLengkapExport($request->all(), $meta),
                $filename
            );
        } catch (\Exception $e) {
            Log::error('Error in SiswaControllerExtended@export: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengekspor data siswa lengkap',
            ], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $query = User::role('Siswa')
                ->with([
                    'roles',
                    'kelas.tahunAjaran',
                    'kelas.tingkat',
                    'dataPribadiSiswa',
                    'absensi' => function ($query) {
                        $query->latest()->limit(5);
                    }
                ]);
            RoleDataScope::applySiswaReadScope($query, $request->user());

            // Filter berdasarkan kelas (ignore invalid non-numeric input to avoid query errors)
            $kelasId = $request->input('kelas_id');
            if ($kelasId !== null && $kelasId !== '') {
                if (filter_var($kelasId, FILTER_VALIDATE_INT) !== false) {
                    $query->whereHas('kelas', function ($q) use ($kelasId) {
                        $q->where('kelas.id', (int) $kelasId)
                            ->where('kelas_siswa.is_active', true);
                    });
                } else {
                    Log::warning('Ignoring invalid kelas_id filter on siswa-extended index', [
                        'kelas_id' => $kelasId,
                        'user_id' => optional($request->user())->id,
                    ]);
                }
            }

            // Filter berdasarkan status
            if ($request->has('is_active') && $request->is_active !== '') {
                $isActive = $this->normalizeBooleanFilter($request->input('is_active'));
                if ($isActive === null) {
                    Log::warning('Ignoring invalid is_active filter on siswa-extended index', [
                        'is_active' => $request->input('is_active'),
                        'user_id' => optional($request->user())->id,
                    ]);
                } else {
                    $query->where('is_active', $isActive);
                }
            }

            // Filter berdasarkan tahun ajaran (ignore invalid non-numeric input)
            $tahunAjaranId = $request->input('tahun_ajaran_id');
            if ($tahunAjaranId !== null && $tahunAjaranId !== '') {
                if (filter_var($tahunAjaranId, FILTER_VALIDATE_INT) !== false) {
                    $query->whereHas('kelas', function ($q) use ($tahunAjaranId) {
                        $q->where('kelas_siswa.tahun_ajaran_id', (int) $tahunAjaranId)
                            ->where('kelas_siswa.is_active', true);
                    });
                } else {
                    Log::warning('Ignoring invalid tahun_ajaran_id filter on siswa-extended index', [
                        'tahun_ajaran_id' => $tahunAjaranId,
                        'user_id' => optional($request->user())->id,
                    ]);
                }
            }

            // Filter berdasarkan tingkat (ignore invalid non-numeric input)
            $tingkatId = $request->input('tingkat_id');
            if ($tingkatId !== null && $tingkatId !== '') {
                if (filter_var($tingkatId, FILTER_VALIDATE_INT) !== false) {
                    $query->whereHas('kelas', function ($q) use ($tingkatId) {
                        $q->where('kelas.tingkat_id', (int) $tingkatId)
                            ->where('kelas_siswa.is_active', true);
                    });
                } else {
                    Log::warning('Ignoring invalid tingkat_id filter on siswa-extended index', [
                        'tingkat_id' => $tingkatId,
                        'user_id' => optional($request->user())->id,
                    ]);
                }
            }

            // Search
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('nama_lengkap', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('dataPribadiSiswa', function ($subQ) use ($search) {
                            $subQ->where('nis', 'like', "%{$search}%")
                                ->orWhere('nisn', 'like', "%{$search}%");
                        });
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $siswa = $query->paginate($perPage);

            // Transform data untuk menambahkan informasi tambahan
            $siswa->getCollection()->transform(function ($item) {
                return $this->decorateSiswaPayload($item);
            });

            return response()->json([
                'success' => true,
                'data' => $siswa,
                'message' => 'Data siswa lengkap berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SiswaControllerExtended@index: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data siswa'
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $query = User::role('Siswa')
                ->with([
                    'roles',
                    'kelas.tahunAjaran',
                    'kelas.tingkat',
                    'dataPribadiSiswa',
                    'absensi' => function ($query) {
                        $query->latest()->limit(10);
                    }
                ]);
            RoleDataScope::applySiswaReadScope($query, $request->user());
            $siswa = $query->find($id);

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            $siswa = $this->decorateSiswaPayload($siswa);

            return response()->json([
                'success' => true,
                'data' => $siswa,
                'message' => 'Detail siswa berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SiswaControllerExtended@show: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil detail siswa'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $siswa = User::role('Siswa')->with('dataPribadiSiswa')->find($id);

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            // Update data pribadi siswa jika ada
            if ($request->has('data_pribadi_siswa') && $siswa->dataPribadiSiswa) {
                $dataPribadi = $request->data_pribadi_siswa;
                $siswa->dataPribadiSiswa->update($dataPribadi);
            }

            // Update data user utama jika ada
            if ($request->has('user_data')) {
                $userData = $request->user_data;
                $siswa->update($userData);
            }

            return response()->json([
                'success' => true,
                'message' => 'Data siswa berhasil diperbarui',
                'data' => $siswa->load(['roles', 'kelas', 'dataPribadiSiswa'])
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SiswaControllerExtended@update: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui data siswa'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $siswa = User::role('Siswa')->find($id);

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            // Hapus relasi dengan kelas
            $siswa->kelas()->detach();

            // Hapus data pribadi siswa
            if ($siswa->dataPribadiSiswa) {
                $siswa->dataPribadiSiswa->delete();
            }

            // Hapus user
            $siswa->delete();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil dihapus'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in SiswaControllerExtended@destroy: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus siswa'
            ], 500);
        }
    }

    /**
     * Get riwayat transisi siswa
     */
    public function getRiwayatTransisi(Request $request, $siswaId)
    {
        try {
            $query = User::role('Siswa');
            RoleDataScope::applySiswaReadScope($query, $request->user());
            $siswa = $query->find($siswaId);

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            // Get riwayat transisi from siswa_transisi table
            $riwayatTransisi = SiswaTransisi::with([
                'kelasAsal',
                'kelasTujuan',
                'tahunAjaran',
                'processedBy',
                'undoneBy'
            ])
                ->where('siswa_id', $siswaId)
                ->where('is_undone', false)
                ->orderBy('tanggal_transisi', 'desc')
                ->get()
                ->map(function ($transisi) {
                    return [
                        'id' => $transisi->id,
                        'type' => $transisi->type,
                        'tanggal_transisi' => $transisi->tanggal_transisi,
                        'kelas_asal' => $transisi->kelasAsal ? [
                            'id' => $transisi->kelasAsal->id,
                            'nama' => $transisi->kelasAsal->nama_kelas
                        ] : null,
                        'kelas_tujuan' => $transisi->kelasTujuan ? [
                            'id' => $transisi->kelasTujuan->id,
                            'nama' => $transisi->kelasTujuan->nama_kelas
                        ] : null,
                        'keterangan' => $transisi->keterangan,
                        'processed_by' => $transisi->processedBy ? [
                            'id' => $transisi->processedBy->id,
                            'nama' => $transisi->processedBy->nama_lengkap
                        ] : null,
                        'is_undone' => $transisi->is_undone,
                        'can_undo' => $transisi->canBeUndone(),
                        'undone_by' => $transisi->undoneBy ? [
                            'id' => $transisi->undoneBy->id,
                            'nama' => $transisi->undoneBy->nama_lengkap
                        ] : null,
                        'undone_at' => $transisi->undone_at,
                        'undo_reason' => $transisi->undo_reason
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $riwayatTransisi,
                'message' => 'Riwayat transisi siswa berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getRiwayatTransisi: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil riwayat transisi siswa'
            ], 500);
        }
    }

    /**
     * Get riwayat kelas siswa
     */
    public function getRiwayatKelas(Request $request, $siswaId)
    {
        try {
            $query = User::role('Siswa');
            RoleDataScope::applySiswaReadScope($query, $request->user());
            $siswa = $query->find($siswaId);

            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            $rolledBackKelasRowIds = $this->resolveRolledBackKelasSiswaIds((int) $siswaId);

            $riwayatQuery = DB::table('kelas_siswa')
                ->join('kelas', 'kelas_siswa.kelas_id', '=', 'kelas.id')
                ->join('tahun_ajaran', 'kelas_siswa.tahun_ajaran_id', '=', 'tahun_ajaran.id')
                ->leftJoin('tingkat', 'kelas.tingkat_id', '=', 'tingkat.id')
                ->where('kelas_siswa.siswa_id', $siswaId)
                // Sembunyikan entry rollback sementara agar riwayat hanya menampilkan kelas yang benar-benar menetap.
                ->whereRaw("NOT (LOWER(COALESCE(kelas_siswa.keterangan, '')) LIKE 'rollback:%' AND kelas_siswa.status = 'pindah')")
                ->orderBy('kelas_siswa.created_at', 'desc')
                ->select([
                    'kelas_siswa.id',
                    'kelas_siswa.tanggal_masuk',
                    'kelas_siswa.tanggal_keluar',
                    'kelas_siswa.status',
                    'kelas_siswa.keterangan',
                    'kelas_siswa.is_active',
                    'kelas.nama_kelas',
                    'tingkat.nama as tingkat_nama',
                    'tahun_ajaran.nama as tahun_ajaran',
                    'tahun_ajaran.tanggal_mulai',
                    'tahun_ajaran.tanggal_selesai',
                    'kelas_siswa.created_at as tanggal_dibuat'
                ]);

            if (!empty($rolledBackKelasRowIds)) {
                $riwayatQuery->whereNotIn('kelas_siswa.id', $rolledBackKelasRowIds);
            }

            $riwayat = $riwayatQuery->get()->map(function ($item) {
                $statusRaw = (string) ($item->status ?? '');
                $item->status_raw = $statusRaw;
                $item->status = $this->resolveKelasMembershipStatus($statusRaw, $item->keterangan ?? null);

                return $item;
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'siswa' => [
                        'id' => $siswa->id,
                        'nama_lengkap' => $siswa->nama_lengkap,
                        'nis' => $siswa->nis,
                        'nisn' => $siswa->nisn
                    ],
                    'riwayat' => $riwayat
                ],
                'message' => 'Riwayat kelas siswa berhasil diambil'
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getRiwayatKelas: ' . $e->getMessage());
            Log::error($e->getTraceAsString());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil riwayat kelas siswa'
            ], 500);
        }
    }

    /**
     * Rollback to previous kelas
     */
    public function rollbackToKelas(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'transisi_id' => 'required|exists:siswa_transisi,id',
                'keterangan' => 'nullable|string|max:500'
            ]);

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            $transisi = SiswaTransisi::find($request->transisi_id);
            if (!$transisi || !$transisi->canBeUndone()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transisi tidak dapat dibatalkan'
                ], 400);
            }

            if (!$transisi->kelas_asal_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada kelas asal untuk dikembalikan'
                ], 400);
            }

            DB::beginTransaction();

            // Undo the transisi
            $transisi->undo(Auth::id(), $request->keterangan);

            $activeRow = $this->getActiveKelasSiswaRow((int) $siswaId);
            if (!$activeRow) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada kelas aktif untuk dibatalkan',
                ], 422);
            }

            $asalTahunAjaranId = (int) (DB::table('kelas_siswa')
                ->where('siswa_id', $siswaId)
                ->where('kelas_id', $transisi->kelas_asal_id)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->value('tahun_ajaran_id') ?? $transisi->tahun_ajaran_id);

            $rollbackDate = now()->toDateString();
            $rollbackNote = 'Rollback: ' . ($request->keterangan ?? 'Dibatalkan');

            $this->deactivateActiveKelasRows((int) $siswaId, 'pindah', $rollbackDate, $rollbackNote);
            $this->activateOrCreateKelasRow(
                siswaId: (int) $siswaId,
                kelasId: (int) $transisi->kelas_asal_id,
                tahunAjaranId: $asalTahunAjaranId,
                tanggalMasuk: $rollbackDate,
                keterangan: null,
                preserveExistingMetadata: true
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transisi berhasil dibatalkan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in rollbackToKelas: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan transisi'
            ], 500);
        }
    }

    /**
     * Batalkan kelulusan siswa
     */
    public function batalkanKelulusan(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'transisi_id' => 'required|exists:siswa_transisi,id',
                'keterangan' => 'nullable|string|max:500'
            ]);

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            $transisi = SiswaTransisi::find($request->transisi_id);
            if (!$transisi || $transisi->type !== 'lulus' || !$transisi->canBeUndone()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelulusan tidak dapat dibatalkan'
                ], 400);
            }

            DB::beginTransaction();

            // Undo the transisi
            $transisi->undo(Auth::id(), $request->keterangan);

            // Aktifkan kembali siswa
            $siswa->update(['is_active' => true]);

            // Restore to previous kelas
            if ($transisi->kelas_asal_id) {
                $tanggalMasuk = now()->toDateString();
                $this->activateOrCreateKelasRow(
                    siswaId: (int) $siswaId,
                    kelasId: (int) $transisi->kelas_asal_id,
                    tahunAjaranId: (int) $transisi->tahun_ajaran_id,
                    tanggalMasuk: $tanggalMasuk,
                    keterangan: null,
                    preserveExistingMetadata: true
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Kelulusan berhasil dibatalkan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in batalkanKelulusan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan kelulusan'
            ], 500);
        }
    }

    /**
     * Kembalikan siswa yang keluar
     */
    public function kembalikanSiswa(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'transisi_id' => 'required|exists:siswa_transisi,id',
                'keterangan' => 'nullable|string|max:500'
            ]);

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            $transisi = SiswaTransisi::find($request->transisi_id);
            if (!$transisi || $transisi->type !== 'keluar' || !$transisi->canBeUndone()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pengeluaran siswa tidak dapat dibatalkan'
                ], 400);
            }

            DB::beginTransaction();

            // Undo the transisi
            $transisi->undo(Auth::id(), $request->keterangan);

            // Aktifkan kembali siswa
            $siswa->update(['is_active' => true]);

            // Restore to previous kelas
            if ($transisi->kelas_asal_id) {
                $tanggalMasuk = now()->toDateString();
                $this->activateOrCreateKelasRow(
                    siswaId: (int) $siswaId,
                    kelasId: (int) $transisi->kelas_asal_id,
                    tahunAjaranId: (int) $transisi->tahun_ajaran_id,
                    tanggalMasuk: $tanggalMasuk,
                    keterangan: null,
                    preserveExistingMetadata: true
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil dikembalikan'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in kembalikanSiswa: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengembalikan siswa'
            ], 500);
        }
    }

    /**
     * Naik kelas siswa
     */
    public function naikKelas(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'kelas_id' => 'required|exists:kelas,id',
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'tanggal' => 'required|date|after_or_equal:2000-01-01',
                'keterangan' => 'nullable|string|max:500'
            ]);

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            // Get current active kelas
            $currentKelas = DB::table('kelas_siswa')
                ->where('siswa_id', $siswaId)
                ->where('is_active', true)
                ->first();

            if (!$currentKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak memiliki kelas aktif'
                ], 422);
            }

            // Validasi tahun ajaran harus berbeda untuk naik kelas
            if ($currentKelas->tahun_ajaran_id == $request->tahun_ajaran_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Naik kelas harus ke tahun ajaran yang berbeda'
                ], 422);
            }

            // Get kelas tujuan + asal beserta tingkat dan tahun ajaran
            $kelasTujuan = Kelas::with(['tingkat', 'tahunAjaran'])->find($request->kelas_id);
            $kelasAsal = Kelas::with(['tingkat', 'tahunAjaran'])->find($currentKelas->kelas_id);
            if (
                !$kelasAsal || !$kelasTujuan
                || !$kelasAsal->tingkat || !$kelasTujuan->tingkat
                || !$kelasAsal->tahunAjaran || !$kelasTujuan->tahunAjaran
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas asal/tujuan tidak valid'
                ], 422);
            }

            // Validasi kelas tujuan harus berasal dari tahun ajaran yang dipilih
            if ((int) $kelasTujuan->tahun_ajaran_id !== (int) $request->tahun_ajaran_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tujuan tidak berada pada tahun ajaran yang dipilih'
                ], 422);
            }

            // Validasi urutan tingkat harus tersedia
            if ($kelasAsal->tingkat->urutan === null || $kelasTujuan->tingkat->urutan === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Urutan tingkat belum dikonfigurasi. Hubungi admin untuk melengkapi data tingkat.'
                ], 422);
            }

            // Validasi tingkat kelas tujuan harus lebih tinggi berdasarkan urutan
            if ((int) $kelasTujuan->tingkat->urutan <= (int) $kelasAsal->tingkat->urutan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tujuan harus memiliki tingkat lebih tinggi berdasarkan urutan tingkat'
                ], 422);
            }

            // Validasi tahun ajaran kelas tujuan harus benar-benar lebih tinggi dari asal
            $tahunMulaiAsal = Carbon::parse((string) $kelasAsal->tahunAjaran->tanggal_mulai);
            $tahunMulaiTujuan = Carbon::parse((string) $kelasTujuan->tahunAjaran->tanggal_mulai);
            if ($tahunMulaiTujuan->lte($tahunMulaiAsal)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran kelas tujuan harus lebih tinggi dari kelas asal'
                ], 422);
            }

            // Validasi tidak naik ke kelas yang sama
            if ($currentKelas->kelas_id == $request->kelas_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa sudah berada di kelas tersebut'
                ], 422);
            }

            DB::beginTransaction();

            $this->performNaikKelasTransition(
                siswaId: (int) $siswaId,
                currentKelas: $currentKelas,
                kelasTujuanId: (int) $request->kelas_id,
                tahunAjaranId: (int) $request->tahun_ajaran_id,
                tanggal: (string) $request->tanggal,
                keterangan: $request->keterangan ?? 'Naik kelas',
                processedBy: (int) Auth::id()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil naik kelas'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in naikKelas: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses naik kelas'
            ], 500);
        }
    }

    /**
     * Pindah kelas siswa
     */
    public function pindahKelas(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'kelas_id' => 'required|exists:kelas,id',
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'tanggal' => 'required|date|after_or_equal:2000-01-01',
                'keterangan' => 'nullable|string|max:500'
            ]);

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            // Get current active kelas
            $currentKelas = DB::table('kelas_siswa')
                ->where('siswa_id', $siswaId)
                ->where('is_active', true)
                ->first();

            if (!$currentKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak memiliki kelas aktif'
                ], 422);
            }

            // Validasi tahun ajaran harus sama untuk pindah kelas
            if ($currentKelas->tahun_ajaran_id != $request->tahun_ajaran_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pindah kelas harus dalam tahun ajaran yang sama'
                ], 422);
            }

            // Get kelas tujuan info + tingkat
            $kelasTujuan = Kelas::with('tingkat')->find($request->kelas_id);
            $kelasAsal = Kelas::with('tingkat')->find($currentKelas->kelas_id);
            if (!$kelasAsal || !$kelasTujuan || !$kelasAsal->tingkat || !$kelasTujuan->tingkat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas asal/tujuan tidak valid'
                ], 422);
            }

            // Validasi kelas tujuan harus berada pada tahun ajaran yang sama
            if ((int) $kelasTujuan->tahun_ajaran_id !== (int) $request->tahun_ajaran_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tujuan tidak berada pada tahun ajaran yang dipilih'
                ], 422);
            }

            // Validasi urutan tingkat harus tersedia
            if ($kelasAsal->tingkat->urutan === null || $kelasTujuan->tingkat->urutan === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Urutan tingkat belum dikonfigurasi. Hubungi admin untuk melengkapi data tingkat.'
                ], 422);
            }

            // Validasi tingkat kelas tujuan harus sama untuk pindah kelas berdasarkan urutan
            if ((int) $kelasTujuan->tingkat->urutan !== (int) $kelasAsal->tingkat->urutan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pindah kelas harus ke tingkat dengan urutan yang sama'
                ], 422);
            }

            // Validasi tidak pindah ke kelas yang sama
            if ($currentKelas->kelas_id == $request->kelas_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa sudah berada di kelas tersebut'
                ], 422);
            }

            DB::beginTransaction();

            $this->performPindahKelasTransition(
                siswaId: (int) $siswaId,
                currentKelas: $currentKelas,
                kelasTujuanId: (int) $request->kelas_id,
                tanggal: (string) $request->tanggal,
                keterangan: $request->keterangan ?? 'Pindah kelas',
                processedBy: (int) Auth::id()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil pindah kelas'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in pindahKelas: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pindah kelas'
            ], 500);
        }
    }

    /**
     * Wali kelas mengajukan request pindah kelas siswa (harus approval kurikulum).
     */
    public function requestPindahKelas(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'kelas_id' => 'required|exists:kelas,id',
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'tanggal' => 'required|date|after_or_equal:2000-01-01',
                'keterangan' => 'nullable|string|max:500',
            ]);

            $actor = $request->user();
            if (!$actor || !$this->isWaliKelas($actor)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya wali kelas yang dapat mengajukan pindah kelas.',
                ], 403);
            }

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan',
                ], 404);
            }

            $currentKelas = $this->getActiveKelasSiswaRow((int) $siswaId);
            if (!$currentKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak memiliki kelas aktif',
                ], 422);
            }

            if (!$this->isWaliForKelas((int) $actor->id, (int) $currentKelas->kelas_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa bukan bagian dari kelas yang Anda walikan.',
                ], 403);
            }

            if ((int) $currentKelas->kelas_id === (int) $request->kelas_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa sudah berada di kelas tersebut',
                ], 422);
            }

            if ((int) $currentKelas->tahun_ajaran_id !== (int) $request->tahun_ajaran_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pindah kelas harus dalam tahun ajaran yang sama',
                ], 422);
            }

            $kelasAsal = Kelas::with('tingkat')->find((int) $currentKelas->kelas_id);
            $kelasTujuan = Kelas::with('tingkat')->find((int) $request->kelas_id);
            if (!$kelasAsal || !$kelasTujuan || !$kelasAsal->tingkat || !$kelasTujuan->tingkat) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas asal/tujuan tidak valid',
                ], 422);
            }

            if ((int) $kelasTujuan->tahun_ajaran_id !== (int) $request->tahun_ajaran_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tujuan tidak berada pada tahun ajaran yang dipilih',
                ], 422);
            }

            if ($kelasAsal->tingkat->urutan === null || $kelasTujuan->tingkat->urutan === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Urutan tingkat belum dikonfigurasi. Hubungi admin untuk melengkapi data tingkat.',
                ], 422);
            }

            if ((int) $kelasTujuan->tingkat->urutan !== (int) $kelasAsal->tingkat->urutan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pindah kelas harus ke tingkat dengan urutan yang sama',
                ], 422);
            }

            $hasPendingRequest = SiswaTransferRequest::query()
                ->where('siswa_id', $siswaId)
                ->where('status', SiswaTransferRequest::STATUS_PENDING)
                ->exists();
            if ($hasPendingRequest) {
                return response()->json([
                    'success' => false,
                    'message' => 'Masih ada request pindah kelas yang menunggu approval.',
                ], 422);
            }

            $transferRequest = SiswaTransferRequest::query()->create([
                'siswa_id' => $siswaId,
                'kelas_asal_id' => (int) $currentKelas->kelas_id,
                'kelas_tujuan_id' => (int) $request->kelas_id,
                'tahun_ajaran_id' => (int) $currentKelas->tahun_ajaran_id,
                'tanggal_rencana' => $request->tanggal,
                'keterangan' => $request->keterangan,
                'status' => SiswaTransferRequest::STATUS_PENDING,
                'requested_by' => (int) $actor->id,
            ]);

            $transferRequest->load([
                'siswa:id,nama_lengkap,email',
                'kelasAsal:id,nama_kelas',
                'kelasTujuan:id,nama_kelas',
                'tahunAjaran:id,nama',
                'requester:id,nama_lengkap,email',
                'processor:id,nama_lengkap,email',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request pindah kelas berhasil diajukan dan menunggu approval kurikulum.',
                'data' => $this->formatTransferRequest($transferRequest),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error in requestPindahKelas: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengajukan request pindah kelas',
            ], 500);
        }
    }

    /**
     * List request pindah kelas.
     */
    public function getTransferRequests(Request $request)
    {
        try {
            $actor = $request->user();
            if (!$actor) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                ], 401);
            }

            $query = SiswaTransferRequest::query()
                ->with([
                    'siswa:id,nama_lengkap,email',
                    'kelasAsal:id,nama_kelas',
                    'kelasTujuan:id,nama_kelas',
                    'tahunAjaran:id,nama',
                    'requester:id,nama_lengkap,email',
                    'processor:id,nama_lengkap,email',
                    'executedTransisi:id,type,tanggal_transisi',
                ])
                ->orderByDesc('created_at');

            $statusFilter = strtolower(trim((string) $request->get('status', '')));
            $allowedStatuses = [
                SiswaTransferRequest::STATUS_PENDING,
                SiswaTransferRequest::STATUS_APPROVED,
                SiswaTransferRequest::STATUS_REJECTED,
                SiswaTransferRequest::STATUS_CANCELLED,
            ];
            if ($statusFilter !== '' && in_array($statusFilter, $allowedStatuses, true)) {
                $query->where('status', $statusFilter);
            }

            if ($this->canProcessTransferRequests($actor)) {
                if ($request->filled('requested_by') && is_numeric($request->requested_by)) {
                    $query->where('requested_by', (int) $request->requested_by);
                }
                if ($request->filled('siswa_id') && is_numeric($request->siswa_id)) {
                    $query->where('siswa_id', (int) $request->siswa_id);
                }
            } elseif ($this->isWaliKelas($actor)) {
                $query->where('requested_by', (int) $actor->id);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses ke data request pindah kelas.',
                ], 403);
            }

            $page = max(1, (int) $request->get('page', 1));
            $perPage = (int) $request->get('per_page', 15);
            $perPage = max(5, min($perPage, 100));

            $result = $query->paginate($perPage, ['*'], 'page', $page);
            $rows = $result->getCollection()->map(
                fn (SiswaTransferRequest $item): array => $this->formatTransferRequest($item)
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'items' => $rows->values(),
                    'pagination' => [
                        'current_page' => $result->currentPage(),
                        'last_page' => $result->lastPage(),
                        'per_page' => $result->perPage(),
                        'total' => $result->total(),
                        'from' => $result->firstItem() ?? 0,
                        'to' => $result->lastItem() ?? 0,
                    ],
                ],
                'message' => 'Data request pindah kelas berhasil diambil',
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getTransferRequests: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data request pindah kelas',
            ], 500);
        }
    }

    /**
     * Approval request pindah kelas oleh kurikulum/admin.
     */
    public function approveTransferRequest(Request $request, $requestId)
    {
        try {
            $request->validate([
                'approval_note' => 'nullable|string|max:500',
            ]);

            $actor = $request->user();
            if (!$actor || !$this->canProcessTransferRequests($actor)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya kurikulum/admin yang dapat menyetujui request pindah kelas.',
                ], 403);
            }

            DB::beginTransaction();

            $transferRequest = SiswaTransferRequest::query()
                ->lockForUpdate()
                ->find($requestId);
            if (!$transferRequest) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Request pindah kelas tidak ditemukan',
                ], 404);
            }

            if ($transferRequest->status !== SiswaTransferRequest::STATUS_PENDING) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Request ini tidak lagi berstatus pending.',
                ], 422);
            }

            $siswa = User::role('Siswa')->find($transferRequest->siswa_id);
            if (!$siswa) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan',
                ], 404);
            }

            $currentKelas = $this->getActiveKelasSiswaRow((int) $transferRequest->siswa_id);
            if (!$currentKelas) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak memiliki kelas aktif',
                ], 422);
            }

            if (
                (int) $currentKelas->kelas_id !== (int) $transferRequest->kelas_asal_id ||
                (int) $currentKelas->tahun_ajaran_id !== (int) $transferRequest->tahun_ajaran_id
            ) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas aktif siswa sudah berubah. Silakan review ulang request.',
                ], 409);
            }

            $kelasAsal = Kelas::with('tingkat')->find((int) $transferRequest->kelas_asal_id);
            $kelasTujuan = Kelas::with('tingkat')->find((int) $transferRequest->kelas_tujuan_id);
            if (!$kelasAsal || !$kelasTujuan || !$kelasAsal->tingkat || !$kelasTujuan->tingkat) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas asal/tujuan tidak valid',
                ], 422);
            }

            if ($kelasAsal->tingkat->urutan === null || $kelasTujuan->tingkat->urutan === null) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Urutan tingkat belum dikonfigurasi. Hubungi admin untuk melengkapi data tingkat.',
                ], 422);
            }

            if ((int) $kelasTujuan->tingkat->urutan !== (int) $kelasAsal->tingkat->urutan) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Pindah kelas hanya boleh pada tingkat dengan urutan yang sama.',
                ], 422);
            }

            if ((int) $transferRequest->kelas_asal_id === (int) $transferRequest->kelas_tujuan_id) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas asal dan tujuan tidak boleh sama.',
                ], 422);
            }

            $transisi = $this->performPindahKelasTransition(
                siswaId: (int) $transferRequest->siswa_id,
                currentKelas: $currentKelas,
                kelasTujuanId: (int) $transferRequest->kelas_tujuan_id,
                tanggal: $transferRequest->tanggal_rencana?->toDateString() ?? now()->toDateString(),
                keterangan: $transferRequest->keterangan ?? 'Pindah kelas (approval kurikulum)',
                processedBy: (int) $actor->id
            );

            $transferRequest->update([
                'status' => SiswaTransferRequest::STATUS_APPROVED,
                'processed_by' => (int) $actor->id,
                'processed_at' => now(),
                'approval_note' => $request->approval_note,
                'executed_transisi_id' => (int) $transisi->id,
            ]);

            DB::commit();

            $transferRequest->refresh()->load([
                'siswa:id,nama_lengkap,email',
                'kelasAsal:id,nama_kelas',
                'kelasTujuan:id,nama_kelas',
                'tahunAjaran:id,nama',
                'requester:id,nama_lengkap,email',
                'processor:id,nama_lengkap,email',
                'executedTransisi:id,type,tanggal_transisi',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request pindah kelas berhasil disetujui.',
                'data' => $this->formatTransferRequest($transferRequest),
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Error in approveTransferRequest: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyetujui request pindah kelas',
            ], 500);
        }
    }

    /**
     * Reject request pindah kelas oleh kurikulum/admin.
     */
    public function rejectTransferRequest(Request $request, $requestId)
    {
        try {
            $request->validate([
                'approval_note' => 'required|string|max:500',
            ]);

            $actor = $request->user();
            if (!$actor || !$this->canProcessTransferRequests($actor)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya kurikulum/admin yang dapat menolak request pindah kelas.',
                ], 403);
            }

            DB::beginTransaction();

            $transferRequest = SiswaTransferRequest::query()
                ->lockForUpdate()
                ->find($requestId);
            if (!$transferRequest) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Request pindah kelas tidak ditemukan',
                ], 404);
            }

            if ($transferRequest->status !== SiswaTransferRequest::STATUS_PENDING) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Request ini tidak lagi berstatus pending.',
                ], 422);
            }

            $transferRequest->update([
                'status' => SiswaTransferRequest::STATUS_REJECTED,
                'processed_by' => (int) $actor->id,
                'processed_at' => now(),
                'approval_note' => $request->approval_note,
            ]);

            DB::commit();

            $transferRequest->refresh()->load([
                'siswa:id,nama_lengkap,email',
                'kelasAsal:id,nama_kelas',
                'kelasTujuan:id,nama_kelas',
                'tahunAjaran:id,nama',
                'requester:id,nama_lengkap,email',
                'processor:id,nama_lengkap,email',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request pindah kelas berhasil ditolak.',
                'data' => $this->formatTransferRequest($transferRequest),
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Error in rejectTransferRequest: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menolak request pindah kelas',
            ], 500);
        }
    }

    /**
     * Cancel request pindah kelas (oleh pengaju atau kurikulum/admin).
     */
    public function cancelTransferRequest(Request $request, $requestId)
    {
        try {
            $request->validate([
                'approval_note' => 'nullable|string|max:500',
            ]);

            $actor = $request->user();
            if (!$actor) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                ], 401);
            }

            DB::beginTransaction();

            $transferRequest = SiswaTransferRequest::query()
                ->lockForUpdate()
                ->find($requestId);
            if (!$transferRequest) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Request pindah kelas tidak ditemukan',
                ], 404);
            }

            if ($transferRequest->status !== SiswaTransferRequest::STATUS_PENDING) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Request ini tidak lagi berstatus pending.',
                ], 422);
            }

            $canCancel = $this->canProcessTransferRequests($actor)
                || (int) $transferRequest->requested_by === (int) $actor->id;
            if (!$canCancel) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk membatalkan request ini.',
                ], 403);
            }

            $transferRequest->update([
                'status' => SiswaTransferRequest::STATUS_CANCELLED,
                'processed_by' => (int) $actor->id,
                'processed_at' => now(),
                'approval_note' => $request->approval_note,
            ]);

            DB::commit();

            $transferRequest->refresh()->load([
                'siswa:id,nama_lengkap,email',
                'kelasAsal:id,nama_kelas',
                'kelasTujuan:id,nama_kelas',
                'tahunAjaran:id,nama',
                'requester:id,nama_lengkap,email',
                'processor:id,nama_lengkap,email',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Request pindah kelas berhasil dibatalkan.',
                'data' => $this->formatTransferRequest($transferRequest),
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Error in cancelTransferRequest: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal membatalkan request pindah kelas',
            ], 500);
        }
    }

    /**
     * Naik kelas oleh wali kelas (hanya saat window on/off dibuka).
     */
    public function naikKelasWali(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'kelas_id' => 'required|exists:kelas,id',
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'tanggal' => 'required|date|after_or_equal:2000-01-01',
                'keterangan' => 'nullable|string|max:500',
            ]);

            $actor = $request->user();
            if (!$actor || !$this->isWaliKelas($actor)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya wali kelas yang dapat memproses naik kelas pada endpoint ini.',
                ], 403);
            }

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan',
                ], 404);
            }

            $currentKelas = $this->getActiveKelasSiswaRow((int) $siswaId);
            if (!$currentKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak memiliki kelas aktif',
                ], 422);
            }

            if (!$this->isWaliForKelas((int) $actor->id, (int) $currentKelas->kelas_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa bukan bagian dari kelas yang Anda walikan.',
                ], 403);
            }

            if ((int) $currentKelas->kelas_id === (int) $request->kelas_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa sudah berada di kelas tersebut',
                ], 422);
            }

            if ((int) $currentKelas->tahun_ajaran_id === (int) $request->tahun_ajaran_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Naik kelas harus ke tahun ajaran yang berbeda',
                ], 422);
            }

            $kelasAsal = Kelas::with(['tingkat', 'tahunAjaran'])->find((int) $currentKelas->kelas_id);
            $kelasTujuan = Kelas::with(['tingkat', 'tahunAjaran'])->find((int) $request->kelas_id);
            if (
                !$kelasAsal || !$kelasTujuan
                || !$kelasAsal->tingkat || !$kelasTujuan->tingkat
                || !$kelasAsal->tahunAjaran || !$kelasTujuan->tahunAjaran
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data kelas asal/tujuan tidak valid',
                ], 422);
            }

            if ((int) $kelasTujuan->tahun_ajaran_id !== (int) $request->tahun_ajaran_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tujuan tidak berada pada tahun ajaran yang dipilih',
                ], 422);
            }

            if ($kelasAsal->tingkat->urutan === null || $kelasTujuan->tingkat->urutan === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Urutan tingkat belum dikonfigurasi. Hubungi admin untuk melengkapi data tingkat.',
                ], 422);
            }

            if ((int) $kelasTujuan->tingkat->urutan <= (int) $kelasAsal->tingkat->urutan) {
                return response()->json([
                    'success' => false,
                    'message' => 'Kelas tujuan harus memiliki tingkat lebih tinggi berdasarkan urutan tingkat',
                ], 422);
            }

            $tahunMulaiAsal = Carbon::parse((string) $kelasAsal->tahunAjaran->tanggal_mulai);
            $tahunMulaiTujuan = Carbon::parse((string) $kelasTujuan->tahunAjaran->tanggal_mulai);
            if ($tahunMulaiTujuan->lte($tahunMulaiAsal)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tahun ajaran kelas tujuan harus lebih tinggi dari kelas asal',
                ], 422);
            }

            $windowState = $this->resolvePromotionWindowState(
                kelasAsalId: (int) $currentKelas->kelas_id,
                targetTahunAjaranId: (int) $request->tahun_ajaran_id
            );
            if (!$windowState['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $windowState['message'],
                    'data' => [
                        'window' => $windowState['window'],
                    ],
                ], 422);
            }

            DB::beginTransaction();

            $transisi = $this->performNaikKelasTransition(
                siswaId: (int) $siswaId,
                currentKelas: $currentKelas,
                kelasTujuanId: (int) $request->kelas_id,
                tahunAjaranId: (int) $request->tahun_ajaran_id,
                tanggal: (string) $request->tanggal,
                keterangan: $request->keterangan ?? 'Naik kelas oleh wali kelas',
                processedBy: (int) $actor->id
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil naik kelas.',
                'data' => [
                    'transisi_id' => $transisi->id,
                    'window' => $windowState['window'],
                ],
            ]);
        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Error in naikKelasWali: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses naik kelas wali',
            ], 500);
        }
    }

    /**
     * List setting window naik kelas wali.
     */
    public function getWaliPromotionSettings(Request $request)
    {
        try {
            $actor = $request->user();
            if (!$actor) {
                return response()->json([
                    'success' => false,
                    'message' => 'User tidak ditemukan',
                ], 401);
            }

            $query = WaliKelasPromotionSetting::query()
                ->with([
                    'kelas:id,nama_kelas,wali_kelas_id,tingkat_id,tahun_ajaran_id',
                    'tahunAjaran:id,nama,status,is_active',
                    'updatedBy:id,nama_lengkap,email',
                ])
                ->orderByDesc('updated_at');

            if ($this->canManagePromotionSettings($actor)) {
                if ($request->filled('kelas_id') && is_numeric($request->kelas_id)) {
                    $query->where('kelas_id', (int) $request->kelas_id);
                }
            } elseif ($this->isWaliKelas($actor)) {
                $query->whereHas('kelas', function ($kelasQuery) use ($actor) {
                    $kelasQuery->where('wali_kelas_id', (int) $actor->id);
                });
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak memiliki akses ke pengaturan window naik kelas.',
                ], 403);
            }

            if ($request->filled('tahun_ajaran_id') && is_numeric($request->tahun_ajaran_id)) {
                $query->where('tahun_ajaran_id', (int) $request->tahun_ajaran_id);
            }

            $rows = $query->get()->map(
                fn (WaliKelasPromotionSetting $setting): array => $this->formatPromotionSetting($setting)
            );

            return response()->json([
                'success' => true,
                'data' => $rows->values(),
                'message' => 'Pengaturan window naik kelas berhasil diambil',
            ]);
        } catch (\Exception $e) {
            Log::error('Error in getWaliPromotionSettings: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil pengaturan window naik kelas',
            ], 500);
        }
    }

    /**
     * Upsert setting window naik kelas wali (kurikulum/admin).
     */
    public function upsertWaliPromotionSetting(Request $request)
    {
        try {
            $request->validate([
                'kelas_id' => 'required|exists:kelas,id',
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'is_enabled' => 'required|boolean',
                'open_at' => 'nullable|date',
                'close_at' => 'nullable|date|after_or_equal:open_at',
                'notes' => 'nullable|string|max:1000',
            ]);

            $actor = $request->user();
            if (!$actor || !$this->canManagePromotionSettings($actor)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya kurikulum/admin yang dapat mengubah pengaturan window naik kelas.',
                ], 403);
            }

            $timezone = config('app.timezone');
            $openAt = $request->open_at
                ? Carbon::parse((string) $request->open_at, $timezone)->toDateTimeString()
                : null;
            $closeAt = $request->close_at
                ? Carbon::parse((string) $request->close_at, $timezone)->toDateTimeString()
                : null;

            $setting = WaliKelasPromotionSetting::query()->updateOrCreate(
                [
                    'kelas_id' => (int) $request->kelas_id,
                    'tahun_ajaran_id' => (int) $request->tahun_ajaran_id,
                ],
                [
                    'is_enabled' => (bool) $request->is_enabled,
                    'open_at' => $openAt,
                    'close_at' => $closeAt,
                    'notes' => $request->notes,
                    'updated_by' => (int) $actor->id,
                ]
            );

            $setting->load([
                'kelas:id,nama_kelas,wali_kelas_id,tingkat_id,tahun_ajaran_id',
                'tahunAjaran:id,nama,status,is_active',
                'updatedBy:id,nama_lengkap,email',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Pengaturan window naik kelas berhasil disimpan.',
                'data' => $this->formatPromotionSetting($setting),
            ]);
        } catch (\Exception $e) {
            Log::error('Error in upsertWaliPromotionSetting: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal menyimpan pengaturan window naik kelas',
            ], 500);
        }
    }

    /**
     * Lulus siswa
     */
    public function lulusSiswa(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'tanggal_lulus' => 'required|date|after_or_equal:2000-01-01',
                'keterangan' => 'nullable|string|max:500'
            ]);

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            // Get current active kelas
            $currentKelas = DB::table('kelas_siswa')
                ->join('kelas', 'kelas_siswa.kelas_id', '=', 'kelas.id')
                ->join('tingkat', 'kelas.tingkat_id', '=', 'tingkat.id')
                ->where('kelas_siswa.siswa_id', $siswaId)
                ->where('kelas_siswa.is_active', true)
                ->first();

            if (!$currentKelas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak memiliki kelas aktif'
                ], 422);
            }

            // Validasi tingkat kelas harus tingkat akhir untuk lulus
            $tingkatTertinggi = DB::table('tingkat')
                ->orderBy('urutan', 'desc')
                ->first();

            if ($currentKelas->tingkat_id != $tingkatTertinggi->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa hanya dapat lulus dari tingkat akhir'
                ], 422);
            }

            DB::beginTransaction();

            // Get current active kelas
            $currentKelas = DB::table('kelas_siswa')
                ->where('siswa_id', $siswaId)
                ->where('is_active', true)
                ->first();

            // Set kelas aktif menjadi lulus
            if ($currentKelas) {
                DB::table('kelas_siswa')
                    ->where('siswa_id', $siswaId)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'tanggal_keluar' => $request->tanggal_lulus,
                        'status' => 'lulus',
                        'keterangan' => $request->keterangan ?? 'Lulus',
                        'updated_at' => now()
                    ]);
            }

            // Update status siswa menjadi tidak aktif
            $siswa->update(['is_active' => false]);

            // Create transisi record
            SiswaTransisi::create([
                'siswa_id' => $siswaId,
                'type' => 'lulus',
                'kelas_asal_id' => $currentKelas ? $currentKelas->kelas_id : null,
                'kelas_tujuan_id' => null,
                'tahun_ajaran_id' => $currentKelas ? $currentKelas->tahun_ajaran_id : null,
                'tanggal_transisi' => $request->tanggal_lulus,
                'keterangan' => $request->keterangan ?? 'Lulus',
                'processed_by' => Auth::id(),
                'is_undone' => false,
                'can_undo' => true
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil dinyatakan lulus'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in lulusSiswa: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses kelulusan siswa'
            ], 500);
        }
    }

    /**
     * Keluar siswa (drop out)
     */
    public function keluarSiswa(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'tanggal_keluar' => 'required|date|after_or_equal:2000-01-01',
                'alasan_keluar' => 'required|string|max:500'
            ]);

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            DB::beginTransaction();

            // Get current active kelas
            $currentKelas = DB::table('kelas_siswa')
                ->where('siswa_id', $siswaId)
                ->where('is_active', true)
                ->first();

            // Set kelas aktif menjadi keluar
            if ($currentKelas) {
                DB::table('kelas_siswa')
                    ->where('siswa_id', $siswaId)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'tanggal_keluar' => $request->tanggal_keluar,
                        'status' => 'keluar',
                        'keterangan' => $request->alasan_keluar,
                        'updated_at' => now()
                    ]);
            }

            // Update status siswa menjadi tidak aktif
            $siswa->update(['is_active' => false]);

            // Create transisi record
            SiswaTransisi::create([
                'siswa_id' => $siswaId,
                'type' => 'keluar',
                'kelas_asal_id' => $currentKelas ? $currentKelas->kelas_id : null,
                'kelas_tujuan_id' => null,
                'tahun_ajaran_id' => $currentKelas ? $currentKelas->tahun_ajaran_id : null,
                'tanggal_transisi' => $request->tanggal_keluar,
                'keterangan' => $request->alasan_keluar,
                'processed_by' => Auth::id(),
                'is_undone' => false,
                'can_undo' => true
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil dikeluarkan dari sekolah'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in keluarSiswa: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses pengeluaran siswa'
            ], 500);
        }
    }

    /**
     * Aktifkan kembali siswa
     */
    public function aktifkanKembali(Request $request, $siswaId)
    {
        try {
            $request->validate([
                'kelas_id' => 'required|exists:kelas,id',
                'tahun_ajaran_id' => 'required|exists:tahun_ajaran,id',
                'tanggal_aktif' => 'required|date|after_or_equal:2000-01-01',
                'keterangan' => 'nullable|string|max:500'
            ]);

            $siswa = User::role('Siswa')->find($siswaId);
            if (!$siswa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Siswa tidak ditemukan'
                ], 404);
            }

            DB::beginTransaction();

            // Aktifkan kembali siswa
            $siswa->update(['is_active' => true]);

            $this->activateOrCreateKelasRow(
                siswaId: (int) $siswaId,
                kelasId: (int) $request->kelas_id,
                tahunAjaranId: (int) $request->tahun_ajaran_id,
                tanggalMasuk: (string) $request->tanggal_aktif,
                keterangan: $request->keterangan ?? 'Diaktifkan kembali'
            );

            // Create transisi record
            SiswaTransisi::create([
                'siswa_id' => $siswaId,
                'type' => 'aktif_kembali',
                'kelas_asal_id' => null,
                'kelas_tujuan_id' => $request->kelas_id,
                'tahun_ajaran_id' => $request->tahun_ajaran_id,
                'tanggal_transisi' => $request->tanggal_aktif,
                'keterangan' => $request->keterangan ?? 'Diaktifkan kembali',
                'processed_by' => Auth::id(),
                'is_undone' => false,
                'can_undo' => true
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Siswa berhasil diaktifkan kembali'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error in aktifkanKembali: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Gagal mengaktifkan kembali siswa'
            ], 500);
        }
    }

    private function isWaliKelas(User $user): bool
    {
        return $user->hasRole(RoleNames::aliases(RoleNames::WALI_KELAS));
    }

    private function canProcessTransferRequests(User $user): bool
    {
        return $user->hasRole(RoleNames::flattenAliases([
            RoleNames::SUPER_ADMIN,
            RoleNames::ADMIN,
            RoleNames::WAKASEK_KURIKULUM,
        ]));
    }

    private function canManagePromotionSettings(User $user): bool
    {
        return $user->hasRole(RoleNames::flattenAliases([
            RoleNames::SUPER_ADMIN,
            RoleNames::ADMIN,
            RoleNames::WAKASEK_KURIKULUM,
        ]));
    }

    private function isWaliForKelas(int $waliId, int $kelasId): bool
    {
        return Kelas::query()
            ->where('id', $kelasId)
            ->where('wali_kelas_id', $waliId)
            ->exists();
    }

    private function getActiveKelasSiswaRow(int $siswaId): ?object
    {
        return DB::table('kelas_siswa')
            ->where('siswa_id', $siswaId)
            ->where('is_active', true)
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    private function performPindahKelasTransition(
        int $siswaId,
        object $currentKelas,
        int $kelasTujuanId,
        string $tanggal,
        ?string $keterangan,
        int $processedBy
    ): SiswaTransisi {
        $transisi = SiswaTransisi::create([
            'siswa_id' => $siswaId,
            'type' => 'pindah_kelas',
            'kelas_asal_id' => (int) $currentKelas->kelas_id,
            'kelas_tujuan_id' => $kelasTujuanId,
            'tahun_ajaran_id' => (int) $currentKelas->tahun_ajaran_id,
            'tanggal_transisi' => $tanggal,
            'keterangan' => $keterangan ?? 'Pindah kelas',
            'processed_by' => $processedBy,
            'is_undone' => false,
            'can_undo' => true,
        ]);

        $note = 'Pindah kelas: ' . ($keterangan ?? 'Pindah kelas');
        $this->deactivateActiveKelasRows($siswaId, 'pindah', $tanggal, $note);
        $this->activateOrCreateKelasRow(
            siswaId: $siswaId,
            kelasId: $kelasTujuanId,
            tahunAjaranId: (int) $currentKelas->tahun_ajaran_id,
            tanggalMasuk: $tanggal,
            keterangan: $note
        );

        return $transisi;
    }

    private function performNaikKelasTransition(
        int $siswaId,
        object $currentKelas,
        int $kelasTujuanId,
        int $tahunAjaranId,
        string $tanggal,
        ?string $keterangan,
        int $processedBy
    ): SiswaTransisi {
        $transisi = SiswaTransisi::create([
            'siswa_id' => $siswaId,
            'type' => 'naik_kelas',
            'kelas_asal_id' => (int) $currentKelas->kelas_id,
            'kelas_tujuan_id' => $kelasTujuanId,
            'tahun_ajaran_id' => $tahunAjaranId,
            'tanggal_transisi' => $tanggal,
            'keterangan' => $keterangan ?? 'Naik kelas',
            'processed_by' => $processedBy,
            'is_undone' => false,
            'can_undo' => true,
        ]);

        $note = 'Naik kelas: ' . ($keterangan ?? 'Naik kelas');
        $this->deactivateActiveKelasRows($siswaId, 'pindah', $tanggal, $note);
        $this->activateOrCreateKelasRow(
            siswaId: $siswaId,
            kelasId: $kelasTujuanId,
            tahunAjaranId: $tahunAjaranId,
            tanggalMasuk: $tanggal,
            keterangan: $note
        );

        return $transisi;
    }

    private function deactivateActiveKelasRows(
        int $siswaId,
        string $status,
        string $tanggalKeluar,
        ?string $keterangan = null
    ): void {
        DB::table('kelas_siswa')
            ->where('siswa_id', $siswaId)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'status' => $status,
                'tanggal_keluar' => $tanggalKeluar,
                'keterangan' => $keterangan,
                'updated_at' => now(),
            ]);
    }

    private function activateOrCreateKelasRow(
        int $siswaId,
        int $kelasId,
        int $tahunAjaranId,
        string $tanggalMasuk,
        ?string $keterangan = null,
        bool $preserveExistingMetadata = false
    ): void {
        $existingRow = DB::table('kelas_siswa')
            ->where('siswa_id', $siswaId)
            ->where('kelas_id', $kelasId)
            ->where('tahun_ajaran_id', $tahunAjaranId)
            ->first();

        if ($existingRow) {
            $tanggalMasukToStore = $tanggalMasuk;
            if (
                $preserveExistingMetadata
                && !empty($existingRow->tanggal_masuk)
            ) {
                $tanggalMasukToStore = (string) $existingRow->tanggal_masuk;
            }

            $keteranganToStore = $keterangan;
            if ($preserveExistingMetadata && $keterangan === null) {
                $keteranganToStore = $existingRow->keterangan;
            }

            DB::table('kelas_siswa')
                ->where('id', $existingRow->id)
                ->update([
                    'status' => 'aktif',
                    'is_active' => true,
                    'tanggal_masuk' => $tanggalMasukToStore,
                    'tanggal_keluar' => null,
                    'keterangan' => $keteranganToStore,
                    'updated_at' => now(),
                ]);
            return;
        }

        DB::table('kelas_siswa')->insert([
            'siswa_id' => $siswaId,
            'kelas_id' => $kelasId,
            'tahun_ajaran_id' => $tahunAjaranId,
            'tanggal_masuk' => $tanggalMasuk,
            'tanggal_keluar' => null,
            'status' => 'aktif',
            'is_active' => true,
            'keterangan' => $keterangan,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function resolveRolledBackKelasSiswaIds(int $siswaId): array
    {
        $undoneTransitions = SiswaTransisi::query()
            ->where('siswa_id', $siswaId)
            ->where('is_undone', true)
            ->whereIn('type', ['pindah_kelas', 'naik_kelas'])
            ->whereNotNull('kelas_tujuan_id')
            ->get([
                'kelas_tujuan_id',
                'tahun_ajaran_id',
                'tanggal_transisi',
            ]);

        $excludedIds = [];
        foreach ($undoneTransitions as $transition) {
            $rowId = DB::table('kelas_siswa')
                ->where('siswa_id', $siswaId)
                ->where('kelas_id', (int) $transition->kelas_tujuan_id)
                ->where('tahun_ajaran_id', (int) $transition->tahun_ajaran_id)
                ->whereDate('tanggal_masuk', $transition->tanggal_transisi)
                ->orderByDesc('id')
                ->value('id');

            if ($rowId !== null) {
                $excludedIds[] = (int) $rowId;
            }
        }

        return array_values(array_unique($excludedIds));
    }

    /**
     * @return array{allowed:bool,message:string,window:array<string,mixed>}
     */
    private function resolvePromotionWindowState(int $kelasAsalId, int $targetTahunAjaranId): array
    {
        $setting = WaliKelasPromotionSetting::query()
            ->where('kelas_id', $kelasAsalId)
            ->where('tahun_ajaran_id', $targetTahunAjaranId)
            ->first();

        $window = [
            'kelas_id' => $kelasAsalId,
            'tahun_ajaran_id' => $targetTahunAjaranId,
            'is_enabled' => false,
            'open_at' => null,
            'close_at' => null,
            'is_open_now' => false,
        ];

        if (!$setting) {
            return [
                'allowed' => false,
                'message' => 'Periode naik kelas belum dibuka oleh kurikulum.',
                'window' => $window,
            ];
        }

        $window = [
            'kelas_id' => (int) $setting->kelas_id,
            'tahun_ajaran_id' => (int) $setting->tahun_ajaran_id,
            'is_enabled' => (bool) $setting->is_enabled,
            'open_at' => $setting->open_at?->toISOString(),
            'close_at' => $setting->close_at?->toISOString(),
            'is_open_now' => $setting->isOpenAt(),
        ];

        if (!$setting->is_enabled) {
            return [
                'allowed' => false,
                'message' => 'Periode naik kelas sedang dinonaktifkan oleh kurikulum.',
                'window' => $window,
            ];
        }

        $now = now()->setTimezone(config('app.timezone'));
        if ($setting->open_at && $now->lt($setting->open_at->copy()->setTimezone(config('app.timezone')))) {
            return [
                'allowed' => false,
                'message' => 'Periode naik kelas belum dimulai sesuai jadwal.',
                'window' => $window,
            ];
        }

        if ($setting->close_at && $now->gt($setting->close_at->copy()->setTimezone(config('app.timezone')))) {
            return [
                'allowed' => false,
                'message' => 'Periode naik kelas sudah ditutup.',
                'window' => $window,
            ];
        }

        return [
            'allowed' => true,
            'message' => 'Periode naik kelas aktif.',
            'window' => $window,
        ];
    }

    private function resolveKelasMembershipStatus(string $status, ?string $keterangan = null): string
    {
        $normalizedStatus = strtolower(trim($status));
        if ($normalizedStatus !== 'pindah') {
            return $normalizedStatus;
        }

        $normalizedNotes = strtolower(trim((string) $keterangan));
        if ($normalizedNotes !== '' && str_contains($normalizedNotes, 'naik kelas')) {
            return 'naik_kelas';
        }

        return 'pindah';
    }

    /**
     * @return array<string,mixed>
     */
    private function formatTransferRequest(SiswaTransferRequest $transferRequest): array
    {
        return [
            'id' => (int) $transferRequest->id,
            'status' => (string) $transferRequest->status,
            'siswa' => [
                'id' => (int) $transferRequest->siswa_id,
                'nama' => $transferRequest->siswa?->nama_lengkap ?? '-',
                'email' => $transferRequest->siswa?->email,
            ],
            'kelas_asal' => [
                'id' => (int) $transferRequest->kelas_asal_id,
                'nama' => $transferRequest->kelasAsal?->nama_kelas ?? '-',
            ],
            'kelas_tujuan' => [
                'id' => (int) $transferRequest->kelas_tujuan_id,
                'nama' => $transferRequest->kelasTujuan?->nama_kelas ?? '-',
            ],
            'tahun_ajaran' => [
                'id' => (int) $transferRequest->tahun_ajaran_id,
                'nama' => $transferRequest->tahunAjaran?->nama ?? '-',
            ],
            'tanggal_rencana' => $transferRequest->tanggal_rencana?->toDateString(),
            'keterangan' => $transferRequest->keterangan,
            'approval_note' => $transferRequest->approval_note,
            'requested_by' => [
                'id' => (int) $transferRequest->requested_by,
                'nama' => $transferRequest->requester?->nama_lengkap ?? '-',
                'email' => $transferRequest->requester?->email,
            ],
            'processed_by' => $transferRequest->processed_by
                ? [
                    'id' => (int) $transferRequest->processed_by,
                    'nama' => $transferRequest->processor?->nama_lengkap ?? '-',
                    'email' => $transferRequest->processor?->email,
                ]
                : null,
            'processed_at' => $transferRequest->processed_at?->toISOString(),
            'executed_transisi_id' => $transferRequest->executed_transisi_id !== null
                ? (int) $transferRequest->executed_transisi_id
                : null,
            'created_at' => $transferRequest->created_at?->toISOString(),
            'updated_at' => $transferRequest->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function formatPromotionSetting(WaliKelasPromotionSetting $setting): array
    {
        return [
            'id' => (int) $setting->id,
            'kelas' => [
                'id' => (int) $setting->kelas_id,
                'nama' => $setting->kelas?->nama_kelas ?? '-',
                'wali_kelas_id' => $setting->kelas?->wali_kelas_id !== null
                    ? (int) $setting->kelas->wali_kelas_id
                    : null,
            ],
            'tahun_ajaran' => [
                'id' => (int) $setting->tahun_ajaran_id,
                'nama' => $setting->tahunAjaran?->nama ?? '-',
            ],
            'is_enabled' => (bool) $setting->is_enabled,
            'open_at' => $setting->open_at?->toISOString(),
            'close_at' => $setting->close_at?->toISOString(),
            'is_open_now' => $setting->isOpenAt(),
            'notes' => $setting->notes,
            'updated_by' => $setting->updated_by
                ? [
                    'id' => (int) $setting->updated_by,
                    'nama' => $setting->updatedBy?->nama_lengkap ?? '-',
                    'email' => $setting->updatedBy?->email,
                ]
                : null,
            'updated_at' => $setting->updated_at?->toISOString(),
        ];
    }

    /**
     * Normalize boolean-ish filter input.
     */
    private function normalizeBooleanFilter($value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            if ($value === 1) {
                return true;
            }
            if ($value === 0) {
                return false;
            }

            return null;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    private function decorateSiswaPayload(User $siswa): User
    {
        if ($siswa->dataPribadiSiswa) {
            $siswa->nis = $siswa->dataPribadiSiswa->nis ?? $siswa->nis;
            $siswa->nisn = $siswa->dataPribadiSiswa->nisn ?? $siswa->nisn;
            $siswa->tempat_lahir = $siswa->dataPribadiSiswa->tempat_lahir;
            $siswa->tanggal_lahir = $siswa->dataPribadiSiswa->tanggal_lahir;
            $siswa->jenis_kelamin = $siswa->dataPribadiSiswa->jenis_kelamin;
            $siswa->agama = $siswa->dataPribadiSiswa->agama;
            $siswa->alamat = $siswa->dataPribadiSiswa->alamat;
            $siswa->no_hp_siswa = $siswa->dataPribadiSiswa->no_hp_siswa;
            $siswa->nama_ayah = $siswa->dataPribadiSiswa->nama_ayah;
            $siswa->pekerjaan_ayah = $siswa->dataPribadiSiswa->pekerjaan_ayah;
            $siswa->no_hp_ayah = $siswa->dataPribadiSiswa->no_hp_ayah;
            $siswa->nama_ibu = $siswa->dataPribadiSiswa->nama_ibu;
            $siswa->pekerjaan_ibu = $siswa->dataPribadiSiswa->pekerjaan_ibu;
            $siswa->no_hp_ibu = $siswa->dataPribadiSiswa->no_hp_ibu;
            $siswa->asal_sekolah = $siswa->dataPribadiSiswa->asal_sekolah;
            $siswa->status_siswa = $siswa->dataPribadiSiswa->status;
            $siswa->tahun_masuk = $siswa->dataPribadiSiswa->tahun_masuk;
            $siswa->tanggal_masuk_sekolah = $siswa->dataPribadiSiswa->tanggal_masuk_sekolah;
        }

        $siswa->kelas_awal = $this->formatInitialAcademicSnapshot($siswa)
            ?: $this->formatKelasMembership($this->resolveInitialKelasMembership($siswa->kelas));
        $siswa->kelas_aktif = $this->formatKelasMembership(
            $this->resolveActiveKelasMembership($siswa->kelas)
        );

        $siswa->statistik_absensi = [
            'total_hadir' => $siswa->absensi->where('status', 'hadir')->count(),
            'total_izin' => $siswa->absensi->where('status', 'izin')->count(),
            'total_sakit' => $siswa->absensi->where('status', 'sakit')->count(),
            'total_alpha' => $siswa->absensi->where('status', 'alpha')->count(),
        ];

        return $siswa;
    }

    private function formatInitialAcademicSnapshot(User $siswa): ?array
    {
        $detail = $siswa->dataPribadiSiswa;
        $kelasAwalId = (int) ($detail?->kelas_awal_id ?? 0);
        $tahunAjaranAwalId = (int) ($detail?->tahun_ajaran_awal_id ?? 0);

        if ($kelasAwalId < 1 || $tahunAjaranAwalId < 1) {
            return null;
        }

        $kelasAwal = collect($siswa->kelas ?? [])->first(fn ($kelasItem) => (int) ($kelasItem->id ?? 0) === $kelasAwalId);
        if (!$kelasAwal) {
            $kelasAwal = Kelas::query()->with(['tingkat'])->find($kelasAwalId);
        }

        if (!$kelasAwal) {
            return null;
        }

        $tahunAjaran = TahunAjaran::query()->find($tahunAjaranAwalId);

        return [
            'id' => $kelasAwal->id,
            'nama_kelas' => $kelasAwal->nama_kelas,
            'tingkat' => $kelasAwal->tingkat?->nama,
            'tahun_ajaran_id' => $tahunAjaranAwalId,
            'tahun_ajaran' => $tahunAjaran ? [
                'id' => $tahunAjaran->id,
                'nama' => $tahunAjaran->nama,
                'tanggal_mulai' => $tahunAjaran->tanggal_mulai,
                'tanggal_selesai' => $tahunAjaran->tanggal_selesai,
            ] : null,
            'status' => 'awal',
            'is_active' => null,
            'tanggal_masuk' => $detail?->tanggal_masuk_kelas_awal,
            'tanggal_keluar' => null,
            'keterangan' => 'Snapshot akademik awal',
        ];
    }

    private function resolveInitialKelasMembership($kelasCollection): ?Kelas
    {
        $kelasItems = collect($kelasCollection ?? []);

        if ($kelasItems->isEmpty()) {
            return null;
        }

        return $kelasItems
            ->sort(function ($left, $right) {
                $leftTimestamp = $this->getKelasMembershipStartTimestamp($left);
                $rightTimestamp = $this->getKelasMembershipStartTimestamp($right);

                if ($leftTimestamp === $rightTimestamp) {
                    return (int) ($left->id ?? 0) <=> (int) ($right->id ?? 0);
                }

                return $leftTimestamp <=> $rightTimestamp;
            })
            ->first();
    }

    private function resolveActiveKelasMembership($kelasCollection): ?Kelas
    {
        $kelasItems = collect($kelasCollection ?? []);

        if ($kelasItems->isEmpty()) {
            return null;
        }

        return $kelasItems->first(function ($kelasItem) {
            return (bool) data_get($kelasItem, 'pivot.is_active');
        });
    }

    private function getKelasMembershipStartTimestamp($kelasItem): int
    {
        $rawDate = data_get($kelasItem, 'pivot.tanggal_masuk')
            ?? data_get($kelasItem, 'pivot.created_at');

        if (!$rawDate) {
            return PHP_INT_MAX;
        }

        try {
            return Carbon::parse($rawDate)->getTimestamp();
        } catch (\Throwable $e) {
            return PHP_INT_MAX;
        }
    }

    private function formatKelasMembership(?Kelas $kelasItem): ?array
    {
        if (!$kelasItem) {
            return null;
        }

        return [
            'id' => $kelasItem->id,
            'nama_kelas' => $kelasItem->nama_kelas,
            'tingkat' => $kelasItem->tingkat?->nama,
            'tahun_ajaran_id' => data_get($kelasItem, 'pivot.tahun_ajaran_id'),
            'tahun_ajaran' => $kelasItem->tahunAjaran ? [
                'id' => $kelasItem->tahunAjaran->id,
                'nama' => $kelasItem->tahunAjaran->nama,
                'tanggal_mulai' => $kelasItem->tahunAjaran->tanggal_mulai,
                'tanggal_selesai' => $kelasItem->tahunAjaran->tanggal_selesai,
            ] : null,
            'status' => data_get($kelasItem, 'pivot.status'),
            'is_active' => (bool) data_get($kelasItem, 'pivot.is_active'),
            'tanggal_masuk' => data_get($kelasItem, 'pivot.tanggal_masuk'),
            'tanggal_keluar' => data_get($kelasItem, 'pivot.tanggal_keluar'),
            'keterangan' => data_get($kelasItem, 'pivot.keterangan'),
        ];
    }
}

