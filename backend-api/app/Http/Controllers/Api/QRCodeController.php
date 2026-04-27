<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Absensi;
use App\Models\User;
use Illuminate\Http\Request;
use App\Helpers\AuthHelper;
use Illuminate\Support\Facades\Validator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;
use Illuminate\Support\Str;

class QRCodeController extends Controller
{
    public function generate(Request $request)
    {
        if ($response = $this->ensureQrAttendanceEnabled()) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:checkin,checkout',
            'expired_minutes' => 'nullable|integer|min:1|max:60'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate unique code
        $code = Str::random(32);
        $expired_minutes = $request->get('expired_minutes', 5);
        $expired_at = now()->addMinutes($expired_minutes);

        // Store code in cache
        $data = [
            'type' => $request->type,
            'generated_by' => AuthHelper::userId(),
            'expired_at' => $expired_at
        ];
        
        cache()->put("qrcode.{$code}", $data, $expired_at);

        // Generate QR Code
        $qr = QrCode::size(300)
            ->format('png')
            ->generate($code);

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $code,
                'qr_code' => 'data:image/png;base64,' . base64_encode($qr),
                'expired_at' => $expired_at,
                'type' => $request->type
            ]
        ]);
    }

    public function validateQRCode(Request $request)
    {
        if ($response = $this->ensureQrAttendanceEnabled()) {
            return $response;
        }

        $rules = [
            'code' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'foto' => 'required|string' // base64 encoded image
        ];

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get code data from cache
        $data = cache()->get("qrcode.{$request->code}");

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak valid atau sudah kadaluarsa'
            ], 400);
        }

        // Check if code is expired
        if (Carbon::parse($data['expired_at'])->isPast()) {
            cache()->forget("qrcode.{$request->code}");
            return response()->json([
                'success' => false,
                'message' => 'QR Code sudah kadaluarsa'
            ], 400);
        }

        // Validate location
        $location_valid = app(LokasiGpsController::class)->validateLocation($request);
        if (!$location_valid['success']) {
            return response()->json([
                'success' => false,
                'message' => $location_valid['message']
            ], 400);
        }

        // Process attendance
        try {
            if ($data['type'] === 'checkin') {
                $result = app(AbsensiController::class)->checkIn($request);
            } else {
                $result = app(AbsensiController::class)->checkOut($request);
            }

            // Delete used code
            cache()->forget("qrcode.{$request->code}");

            return $result;

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memproses absensi',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    public function attendance(Request $request, $code)
    {
        if ($response = $this->ensureQrAttendanceEnabled()) {
            return $response;
        }

        // Get code data from cache
        $data = cache()->get("qrcode.{$code}");

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code tidak valid atau sudah kadaluarsa'
            ], 400);
        }

        // Check if code is expired
        if (Carbon::parse($data['expired_at'])->isPast()) {
            cache()->forget("qrcode.{$code}");
            return response()->json([
                'success' => false,
                'message' => 'QR Code sudah kadaluarsa'
            ], 400);
        }

        // Return QR code info
        return response()->json([
            'success' => true,
            'data' => [
                'type' => $data['type'],
                'expired_at' => $data['expired_at'],
                'generated_by' => User::find($data['generated_by'])->name
            ]
        ]);
    }

    public function bulk(Request $request)
    {
        if ($response = $this->ensureQrAttendanceEnabled()) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'type' => 'required|in:checkin,checkout',
            'user_ids' => 'required|array',
            'user_ids.*' => 'exists:users,id',
            'expired_minutes' => 'nullable|integer|min:1|max:60'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $expired_minutes = $request->get('expired_minutes', 5);
        $expired_at = now()->addMinutes($expired_minutes);
        $qr_codes = [];

        foreach ($request->user_ids as $user_id) {
            // Generate unique code
            $code = Str::random(32);

            // Store code in cache
            $data = [
                'type' => $request->type,
                'user_id' => $user_id,
                'generated_by' => AuthHelper::userId(),
                'expired_at' => $expired_at
            ];
            
            cache()->put("qrcode.{$code}", $data, $expired_at);

            // Generate QR Code
            $qr = QrCode::size(300)
                ->format('png')
                ->generate($code);

            $qr_codes[] = [
                'user_id' => $user_id,
                'user_name' => User::find($user_id)->name,
                'code' => $code,
                'qr_code' => 'data:image/png;base64,' . base64_encode($qr),
                'expired_at' => $expired_at
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $request->type,
                'expired_at' => $expired_at,
                'qr_codes' => $qr_codes
            ]
        ]);
    }

    private function ensureQrAttendanceEnabled()
    {
        if ((bool) config('attendance.features.qr_code_enabled', false)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Fitur absensi QR Code sementara dinonaktifkan. Gunakan absensi selfie + geolocation.',
        ], 403);
    }
}
