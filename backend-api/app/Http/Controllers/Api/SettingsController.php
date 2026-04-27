<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Helpers\AuthHelper;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class SettingsController extends Controller
{
    private $settings_keys = [
        'school_profile' => [
            'nama_sekolah',
            'alamat_sekolah',
            'telepon_sekolah',
            'email_sekolah',
            'website_sekolah',
            'logo_sekolah',
            'kepala_sekolah',
            'nip_kepala_sekolah'
        ],
        'absensi' => [
            'jam_masuk',
            'jam_pulang',
            'toleransi_keterlambatan',
            'radius_maksimal',
            'wajib_foto',
            'wajib_gps'
        ],
        'whatsapp' => [
            'api_url',
            'api_key',
            'device_id',
            'auto_reply_message',
            'notification_enabled'
        ],
        'notification' => [
            'push_enabled',
            'email_enabled',
            'whatsapp_enabled',
            'reminder_enabled',
            'reminder_time'
        ]
    ];

    public function index()
    {
        $settings = [];
        foreach ($this->settings_keys as $group => $keys) {
            $settings[$group] = $this->getSettingsByGroup($group);
        }

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'group' => 'required|string|in:' . implode(',', array_keys($this->settings_keys)),
            'settings' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        $group = $request->group;
        $settings = $request->settings;

        // Validate settings keys
        $valid_keys = $this->settings_keys[$group];
        $invalid_keys = array_diff(array_keys($settings), $valid_keys);

        if (count($invalid_keys) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Terdapat key setting yang tidak valid',
                'invalid_keys' => $invalid_keys
            ], 422);
        }

        // Update settings
        foreach ($settings as $key => $value) {
            $this->setSetting("{$group}.{$key}", $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan berhasil diupdate',
            'data' => $this->getSettingsByGroup($group)
        ]);
    }

    public function getSchoolProfile()
    {
        $profile = $this->getSettingsByGroup('school_profile');

        return response()->json([
            'success' => true,
            'data' => $profile
        ]);
    }

    public function updateSchoolProfile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_sekolah' => 'required|string|max:100',
            'alamat_sekolah' => 'required|string',
            'telepon_sekolah' => 'required|string|max:20',
            'email_sekolah' => 'required|email',
            'website_sekolah' => 'nullable|url',
            'logo_sekolah' => 'nullable|string', // base64 encoded image
            'kepala_sekolah' => 'required|string|max:100',
            'nip_kepala_sekolah' => 'required|string|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        // Handle logo upload if provided
        if ($request->has('logo_sekolah') && $request->logo_sekolah) {
            $logo_path = $this->saveLogo($request->logo_sekolah);
            $request->merge(['logo_sekolah' => $logo_path]);
        }

        // Update settings
        foreach ($request->all() as $key => $value) {
            $this->setSetting("school_profile.{$key}", $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profil sekolah berhasil diupdate',
            'data' => $this->getSettingsByGroup('school_profile')
        ]);
    }

    public function getAbsensiSettings()
    {
        $settings = $this->getSettingsByGroup('absensi');

        return response()->json([
            'success' => true,
            'data' => $settings
        ]);
    }

    public function updateAbsensiSettings(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'jam_masuk' => 'required|date_format:H:i',
            'jam_pulang' => 'required|date_format:H:i|after:jam_masuk',
            'toleransi_keterlambatan' => 'required|integer|min:0|max:120',
            'radius_maksimal' => 'required|integer|min:1|max:1000',
            'wajib_foto' => 'required|boolean',
            'wajib_gps' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak valid',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update settings
        foreach ($request->all() as $key => $value) {
            $this->setSetting("absensi.{$key}", $value);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengaturan absensi berhasil diupdate',
            'data' => $this->getSettingsByGroup('absensi')
        ]);
    }

    private function getSettingsByGroup($group)
    {
        $settings = [];
        foreach ($this->settings_keys[$group] as $key) {
            $settings[$key] = $this->getSetting("{$group}.{$key}");
        }
        return $settings;
    }

    private function getSetting($key, $default = null)
    {
        return Cache::rememberForever("settings.{$key}", function() use ($default) {
            return $default;
        });
    }

    private function setSetting($key, $value)
    {
        Cache::forever("settings.{$key}", $value);
        return $value;
    }

    private function saveLogo($base64_image)
    {
        $image_parts = explode(";base64,", $base64_image);
        $image_base64 = base64_decode($image_parts[1]);
        $filename = 'logo_' . time() . '.png';
        
        Storage::disk('public')->put('images/' . $filename, $image_base64);

        return 'images/' . $filename;
    }
}
