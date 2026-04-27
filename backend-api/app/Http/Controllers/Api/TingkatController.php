<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tingkat;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class TingkatController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Tingkat::query();

        // Search functionality
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'like', "%{$search}%")
                    ->orWhere('kode', 'like', "%{$search}%")
                    ->orWhere('deskripsi', 'like', "%{$search}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Order by urutan
        $query->orderBy('urutan', 'asc');

        $tingkat = $query->with(['kelas.siswa'])->get();

        // Format response with additional info
        $formattedTingkat = $tingkat->map(function ($item) {
            // Hitung jumlah siswa dari semua kelas di tingkat ini
            $jumlahSiswa = 0;
            foreach ($item->kelas as $kelas) {
                // Count siswa with status 'aktif' from the loaded relationship
                $jumlahSiswa += $kelas->siswa->where('pivot.status', 'aktif')->count();
            }

            return [
                'id' => $item->id,
                'nama' => $item->nama,
                'kode' => $item->kode,
                'deskripsi' => $item->deskripsi,
                'urutan' => $item->urutan,
                'is_active' => $item->is_active,
                'jumlah_kelas' => $item->kelas->count(),
                'jumlah_siswa' => $jumlahSiswa,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedTingkat
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:50|unique:tingkat,nama',
            'kode' => 'required|string|max:10|unique:tingkat,kode',
            'deskripsi' => 'nullable|string|max:255',
            'urutan' => [
                'nullable',
                'integer',
                'min:1',
                Rule::unique('tingkat', 'urutan')->whereNull('deleted_at')
            ],
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->all();

        // Auto-generate urutan if not provided
        if (!isset($data['urutan'])) {
            $maxUrutan = Tingkat::max('urutan') ?? 0;
            $data['urutan'] = $maxUrutan + 1;
        }

        // Set default is_active if not provided
        if (!isset($data['is_active'])) {
            $data['is_active'] = true;
        }

        $tingkat = Tingkat::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Tingkat berhasil dibuat',
            'data' => $tingkat
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $tingkat = Tingkat::with(['kelas' => function ($query) {
            $query->with(['waliKelas', 'tahunAjaran']);
        }])->find($id);

        if (!$tingkat) {
            return response()->json([
                'success' => false,
                'message' => 'Tingkat tidak ditemukan'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $tingkat
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $tingkat = Tingkat::find($id);

        if (!$tingkat) {
            return response()->json([
                'success' => false,
                'message' => 'Tingkat tidak ditemukan'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama' => 'required|string|max:50|unique:tingkat,nama,' . $id,
            'kode' => 'required|string|max:10|unique:tingkat,kode,' . $id,
            'deskripsi' => 'nullable|string|max:255',
            'urutan' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('tingkat', 'urutan')->ignore($id)->whereNull('deleted_at')
            ],
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $tingkat->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Tingkat berhasil diupdate',
            'data' => $tingkat
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $tingkat = Tingkat::find($id);

        if (!$tingkat) {
            return response()->json([
                'success' => false,
                'message' => 'Tingkat tidak ditemukan'
            ], 404);
        }

        // Check if tingkat has associated kelas
        if ($tingkat->kelas()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak dapat menghapus tingkat yang masih memiliki kelas'
            ], 422);
        }

        $tingkat->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tingkat berhasil dihapus'
        ]);
    }

    /**
     * Toggle active status of tingkat
     */
    public function toggleStatus($id)
    {
        $tingkat = Tingkat::find($id);

        if (!$tingkat) {
            return response()->json([
                'success' => false,
                'message' => 'Tingkat tidak ditemukan'
            ], 404);
        }

        $tingkat->update(['is_active' => !$tingkat->is_active]);

        return response()->json([
            'success' => true,
            'message' => 'Status tingkat berhasil diubah',
            'data' => $tingkat
        ]);
    }

    /**
     * Get active tingkat only
     */
    public function getActive()
    {
        $tingkat = Tingkat::where('is_active', true)
            ->orderBy('urutan', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tingkat
        ]);
    }
}
