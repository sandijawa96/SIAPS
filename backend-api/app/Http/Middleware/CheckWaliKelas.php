<?php

namespace App\Http\Middleware;

use App\Support\RoleNames;
use Closure;
use Illuminate\Http\Request;
use App\Models\Kelas;
use Symfony\Component\HttpFoundation\Response;

class CheckWaliKelas
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $kelasId = $request->route('id')
            ?? $request->route('kelasId')
            ?? $request->input('kelas_id');

        if (!$kelasId) {
            return response()->json([
                'message' => 'ID Kelas tidak ditemukan dalam request'
            ], 400);
        }

        // Cek apakah user adalah super admin
        if ($user->hasRole(RoleNames::aliases(RoleNames::SUPER_ADMIN))) {
            return $next($request);
        }

        // Cek apakah user adalah wali kelas dari kelas tersebut
        $kelas = Kelas::find($kelasId);
        
        if (!$kelas) {
            return response()->json([
                'message' => 'Kelas tidak ditemukan'
            ], 404);
        }

        if ($kelas->wali_kelas_id !== $user->id) {
            return response()->json([
                'message' => 'Anda tidak memiliki akses ke kelas ini'
            ], 403);
        }

        // Tambahkan data kelas ke request untuk digunakan di controller
        $request->merge(['kelas' => $kelas]);

        return $next($request);
    }
}
