<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class DeviceTokenController extends Controller
{
    public function index(Request $request)
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn (DeviceToken $token) => $this->serializeToken($token));

        return response()->json([
            'success' => true,
            'data' => $tokens,
        ]);
    }

    public function register(Request $request)
    {
        $incomingPushToken = trim((string) $request->input('push_token', ''));

        Log::info('Device token register request received', [
            'user_id' => $request->user()?->id,
            'device_id' => (string) $request->input('device_id', ''),
            'device_type' => (string) $request->input('device_type', ''),
            'has_push_token' => $incomingPushToken !== '',
            'token_suffix' => $incomingPushToken !== '' ? substr($incomingPushToken, -12) : null,
        ]);

        $validator = Validator::make($request->all(), [
            'device_id' => 'required|string|max:255',
            'device_name' => 'nullable|string|max:255',
            'device_type' => 'required|string|in:web,android,ios',
            'push_token' => 'nullable|string|max:2048',
            'device_info' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            Log::warning('Device token register validation failed', [
                'user_id' => $request->user()?->id,
                'errors' => $validator->errors()->toArray(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Data token device tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $deviceId = (string) $request->device_id;
        $currentUserId = (int) $request->user()->id;
        $token = DeviceToken::withTrashed()
            ->where('device_id', $deviceId)
            ->first();

        $previousUserId = null;
        if (!$token instanceof DeviceToken) {
            $token = new DeviceToken([
                'device_id' => $deviceId,
            ]);
        } else {
            $previousUserId = (int) ($token->user_id ?? 0);
        }
        $existingPushToken = trim((string) ($token->push_token ?? ''));
        $resolvedPushToken = $incomingPushToken !== '' ? $incomingPushToken : $existingPushToken;
        $resolvedPushToken = trim($resolvedPushToken);

        $token->fill([
            'user_id' => $currentUserId,
            'device_name' => $request->input('device_name'),
            'device_type' => (string) $request->device_type,
            'push_token' => $resolvedPushToken !== '' ? $resolvedPushToken : null,
            'device_info' => $request->input('device_info'),
            'is_active' => true,
            'last_used_at' => now(),
        ]);
        $token->deleted_at = null;
        $token->save();

        DeviceToken::query()
            ->where('user_id', $currentUserId)
            ->where('device_type', (string) $token->device_type)
            ->where('id', '!=', (int) $token->id)
            ->where('is_active', true)
            ->update(['is_active' => false]);

        if ($previousUserId !== null && $previousUserId > 0 && $previousUserId !== $currentUserId) {
            Log::info('Device token reassigned to another user', [
                'device_token_id' => (int) $token->id,
                'device_id' => $deviceId,
                'previous_user_id' => $previousUserId,
                'current_user_id' => $currentUserId,
            ]);
        }

        Log::info('Device token registered', [
            'device_token_id' => (int) $token->id,
            'user_id' => $currentUserId,
            'device_id' => $deviceId,
            'device_type' => (string) $token->device_type,
            'has_push_token' => is_string($token->push_token) && trim((string) $token->push_token) !== '',
            'token_suffix' => is_string($token->push_token) && trim((string) $token->push_token) !== ''
                ? substr((string) $token->push_token, -12)
                : null,
            'is_active' => (bool) $token->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token push berhasil diregistrasikan',
            'data' => $this->serializeToken($token),
        ]);
    }

    public function deactivate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'device_id' => 'required_without:id|string|max:255',
            'id' => 'required_without:device_id|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Target token tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = DeviceToken::query()->where('user_id', $request->user()->id);

        if ($request->filled('id')) {
            $query->where('id', (int) $request->id);
        } else {
            $query->where('device_id', (string) $request->device_id);
        }

        $deviceToken = $query->first();
        if (!$deviceToken) {
            return response()->json([
                'success' => false,
                'message' => 'Token device tidak ditemukan',
            ], 404);
        }

        $deviceToken->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token push berhasil dinonaktifkan',
        ]);
    }

    public function webConfig()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'enabled' => (bool) config('push.enabled'),
                'provider' => config('push.provider'),
                'firebase' => [
                    'apiKey' => config('push.firebase.api_key'),
                    'authDomain' => config('push.firebase.auth_domain'),
                    'projectId' => config('push.firebase.project_id'),
                    'storageBucket' => config('push.firebase.storage_bucket'),
                    'messagingSenderId' => config('push.firebase.messaging_sender_id'),
                    'appId' => config('push.firebase.app_id'),
                    'measurementId' => config('push.firebase.measurement_id'),
                    'vapidKey' => config('push.firebase.vapid_key'),
                ],
            ],
        ]);
    }

    private function serializeToken(DeviceToken $token): array
    {
        $payload = $token->toArray();
        $rawToken = trim((string) ($payload['push_token'] ?? ''));

        $payload['has_push_token'] = $rawToken !== '';
        $payload['token_suffix'] = $rawToken !== '' ? substr($rawToken, -12) : null;
        unset($payload['push_token']);

        return $payload;
    }
}
