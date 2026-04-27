<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatusKepegawaianController extends Controller
{
    /**
     * Get all available status kepegawaian from ENUM
     */
    public function getEnumValues()
    {
        try {
            // Query untuk mendapatkan nilai ENUM dari kolom status_kepegawaian
            $result = DB::select("SHOW COLUMNS FROM users LIKE 'status_kepegawaian'");

            if (empty($result)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Column status_kepegawaian not found'
                ], 404);
            }

            // Parse ENUM values dari Type column
            $enumString = $result[0]->Type;
            preg_match('/^enum\((.*)\)$/', $enumString, $matches);

            if (!isset($matches[1])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to parse ENUM values'
                ], 500);
            }

            // Extract values dan remove quotes
            $enumValues = array_map(function ($value) {
                return trim($value, "'\"");
            }, str_getcsv($matches[1]));

            // Format response
            $statusList = array_map(function ($status) {
                return [
                    'nama' => $status,
                    'label' => $status,
                    'description' => $this->getStatusDescription($status)
                ];
            }, $enumValues);

            return response()->json([
                'success' => true,
                'data' => $statusList,
                'message' => 'Status kepegawaian ENUM values retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get status kepegawaian ENUM values'
            ], 500);
        }
    }

    /**
     * Get description for status kepegawaian
     */
    private function getStatusDescription($status)
    {
        $descriptions = [
            'ASN' => 'Aparatur Sipil Negara',
            'Honorer' => 'Pegawai Honorer',
            'PPPK' => 'Pegawai Pemerintah dengan Perjanjian Kerja',
            'GTT/PTT' => 'Guru/Pegawai Tidak Tetap',
            'Kontrak' => 'Pegawai Kontrak'
        ];

        return $descriptions[$status] ?? $status;
    }

    /**
     * Get all status kepegawaian (alias for getEnumValues for compatibility)
     */
    public function index()
    {
        return $this->getEnumValues();
    }
}

