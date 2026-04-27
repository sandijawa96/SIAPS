<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PeriodeAkademik;
use App\Models\TahunAjaran;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicContextController extends Controller
{
    public function current(Request $request): JsonResponse
    {
        $requestedTahunAjaranId = $request->filled('tahun_ajaran_id')
            ? (int) $request->tahun_ajaran_id
            : null;

        $tahunAjaran = $requestedTahunAjaranId
            ? TahunAjaran::query()->find($requestedTahunAjaranId)
            : null;

        if (!$tahunAjaran) {
            $tahunAjaran = TahunAjaran::query()
                ->where('status', TahunAjaran::STATUS_ACTIVE)
                ->orderByDesc('id')
                ->first();
        }

        if (!$tahunAjaran) {
            return response()->json([
                'success' => true,
                'data' => null,
                'meta' => [
                    'needs_setup' => true,
                    'reason' => 'no_active_tahun_ajaran',
                ],
                'message' => 'Belum ada tahun ajaran aktif',
            ]);
        }

        $today = now()->toDateString();
        $periodeAktif = PeriodeAkademik::query()
            ->where('tahun_ajaran_id', (int) $tahunAjaran->id)
            ->where('is_active', true)
            ->whereDate('tanggal_mulai', '<=', $today)
            ->whereDate('tanggal_selesai', '>=', $today)
            ->orderBy('tanggal_mulai')
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'tahun_ajaran' => [
                    'id' => (int) $tahunAjaran->id,
                    'nama' => (string) $tahunAjaran->nama,
                    'status' => (string) $tahunAjaran->status,
                    'tanggal_mulai' => optional($tahunAjaran->tanggal_mulai)->format('Y-m-d'),
                    'tanggal_selesai' => optional($tahunAjaran->tanggal_selesai)->format('Y-m-d'),
                ],
                'periode_aktif' => $periodeAktif ? [
                    'id' => (int) $periodeAktif->id,
                    'nama' => (string) $periodeAktif->nama,
                    'jenis' => (string) $periodeAktif->jenis,
                    'semester' => (string) $periodeAktif->semester,
                    'tanggal_mulai' => optional($periodeAktif->tanggal_mulai)->format('Y-m-d'),
                    'tanggal_selesai' => optional($periodeAktif->tanggal_selesai)->format('Y-m-d'),
                ] : null,
                'effective_date_range' => [
                    'start_date' => optional($tahunAjaran->tanggal_mulai)->format('Y-m-d'),
                    'end_date' => optional($tahunAjaran->tanggal_selesai)->format('Y-m-d'),
                ],
            ],
            'meta' => [
                'needs_setup' => false,
                'tahun_ajaran_id' => (int) $tahunAjaran->id,
                'periode_aktif_id' => $periodeAktif ? (int) $periodeAktif->id : null,
            ],
            'message' => 'Konteks akademik aktif berhasil diambil',
        ]);
    }
}
