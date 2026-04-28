<?php

namespace App\Http\Controllers\Api;

use App\Models\SbtExamSession;
use App\Models\SbtSecurityEvent;
use App\Models\SbtSetting;
use App\Services\MobileReleaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class SbtController extends BaseController
{
    public function mobileConfig(): JsonResponse
    {
        return $this->sendSuccess(
            $this->serializeMobileConfig(SbtSetting::current()),
            'Konfigurasi SBT berhasil diambil'
        );
    }

    public function versionCheck(Request $request, MobileReleaseService $mobileReleaseService): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'platform' => 'nullable|in:android,ios',
            'app_version' => 'nullable|string|max:50',
            'build_number' => 'nullable|integer|min:0|max:2147483647',
            'release_channel' => 'nullable|string|max:30',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Parameter cek versi SBT tidak valid', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();

        try {
            $payload = $mobileReleaseService->buildPublicVersionCheck(
                (string) ($validated['platform'] ?? 'android'),
                $validated['app_version'] ?? null,
                $validated['build_number'] ?? null,
                (string) ($validated['release_channel'] ?? 'stable'),
                'sbt-smanis',
                'siswa'
            );
        } catch (Throwable $exception) {
            Log::warning('SBT version check unavailable', [
                'message' => $exception->getMessage(),
                'exception' => $exception::class,
                'platform' => $validated['platform'] ?? 'android',
                'release_channel' => $validated['release_channel'] ?? 'stable',
            ]);

            return $this->sendSuccess([
                'available' => false,
                'app_key' => 'sbt-smanis',
                'has_update' => false,
                'must_update' => false,
                'is_supported' => true,
                'latest' => null,
                'message' => 'Cek update SBT belum bisa diproses server. Hubungi admin SIAPS.',
            ], 'Cek update SBT belum tersedia');
        }

        if ($payload === null) {
            return $this->sendSuccess([
                'available' => false,
                'app_key' => 'sbt-smanis',
                'has_update' => false,
                'must_update' => false,
                'is_supported' => true,
                'latest' => null,
                'message' => 'Release SBT belum tersedia di Pusat Download.',
            ], 'Release SBT belum tersedia');
        }

        $payload['available'] = true;
        $payload['catalog_app_key'] = 'sbt-smanis';
        $payload = $this->attachPublicSbtDownloadUrl($payload);

        return $this->sendSuccess($payload, 'Status versi SBT berhasil dicek');
    }

    public function startSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app_session_id' => 'required|string|max:100',
            'participant_identifier' => 'nullable|string|max:120',
            'student_name' => 'nullable|string|max:150',
            'device_id' => 'nullable|string|max:160',
            'device_name' => 'nullable|string|max:160',
            'app_version' => 'nullable|string|max:50',
            'platform' => 'nullable|string|max:40',
            'exam_url' => 'nullable|url|max:2048',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Data sesi SBT tidak valid', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $session = $this->resolveSession($validated['app_session_id']);
        $session->fill([
            'participant_identifier' => $validated['participant_identifier'] ?? $session->participant_identifier,
            'student_name' => $validated['student_name'] ?? $session->student_name,
            'device_id' => $validated['device_id'] ?? $session->device_id,
            'device_name' => $validated['device_name'] ?? $session->device_name,
            'app_version' => $validated['app_version'] ?? $session->app_version,
            'platform' => $validated['platform'] ?? $session->platform ?? 'android',
            'exam_url' => $validated['exam_url'] ?? $session->exam_url,
            'status' => 'active',
            'last_heartbeat_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => array_merge($session->metadata ?? [], $validated['metadata'] ?? []),
        ]);

        if (!$session->started_at) {
            $session->started_at = now();
        }

        $session->save();

        return $this->sendSuccess([
            'session' => $this->serializeSession($session),
            'config' => $this->serializeMobileConfig(SbtSetting::current()),
        ], 'Sesi SBT aktif');
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app_session_id' => 'required|string|max:100',
            'battery_level' => 'nullable|integer|min:0|max:100',
            'current_url' => 'nullable|string|max:2048',
            'focus_state' => 'nullable|string|max:80',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Data heartbeat SBT tidak valid', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $session = $this->resolveSession($validated['app_session_id']);
        $metadata = array_merge($session->metadata ?? [], $validated['metadata'] ?? []);
        $metadata['last_heartbeat'] = array_filter([
            'battery_level' => $validated['battery_level'] ?? null,
            'current_url' => $validated['current_url'] ?? null,
            'focus_state' => $validated['focus_state'] ?? null,
            'received_at' => now()->toISOString(),
        ], static fn ($value) => $value !== null);

        $session->fill([
            'status' => 'active',
            'last_heartbeat_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => $metadata,
        ])->save();

        return $this->sendSuccess([
            'session' => $this->serializeSession($session),
            'config_version' => SbtSetting::current()->config_version,
        ], 'Heartbeat SBT diterima');
    }

    public function finishSession(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app_session_id' => 'required|string|max:100',
            'reason' => 'nullable|string|max:200',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Data penutupan sesi SBT tidak valid', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $session = $this->resolveSession($validated['app_session_id']);
        $metadata = array_merge($session->metadata ?? [], $validated['metadata'] ?? []);
        $metadata['finish'] = array_filter([
            'reason' => $validated['reason'] ?? null,
            'received_at' => now()->toISOString(),
        ], static fn ($value) => $value !== null);

        $session->fill([
            'status' => 'finished',
            'finished_at' => now(),
            'last_heartbeat_at' => now(),
            'metadata' => $metadata,
        ])->save();

        return $this->sendSuccess([
            'session' => $this->serializeSession($session),
        ], 'Sesi SBT ditutup');
    }

    public function reportEvent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app_session_id' => 'required|string|max:100',
            'event_type' => 'required|string|max:100',
            'severity' => 'nullable|string|max:20',
            'message' => 'nullable|string|max:2000',
            'occurred_at' => 'nullable|date',
            'app_version' => 'nullable|string|max:50',
            'device_id' => 'nullable|string|max:160',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Data event SBT tidak valid', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $session = $this->resolveSession($validated['app_session_id']);
        $event = $this->recordSecurityEvent(
            $request,
            $session,
            $validated['event_type'],
            $validated['severity'] ?? $this->severityForEventType($validated['event_type']),
            $validated['message'] ?? null,
            [
                'app_version' => $validated['app_version'] ?? $session->app_version,
                'device_id' => $validated['device_id'] ?? $session->device_id,
                'occurred_at' => $validated['occurred_at'] ?? null,
                'metadata' => $validated['metadata'] ?? [],
            ]
        );

        return $this->sendSuccess([
            'event' => $this->serializeEvent($event),
        ], 'Event SBT tercatat', 201);
    }

    public function unlock(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'app_session_id' => 'required|string|max:100',
            'supervisor_code' => 'required|string|max:64',
            'event_type' => 'nullable|string|max:100',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Data kode pengawas tidak valid', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $setting = SbtSetting::current();
        $session = $this->resolveSession($validated['app_session_id']);

        if (!$setting->requiresSupervisorCode()) {
            $this->recordSecurityEvent(
                $request,
                $session,
                'SUPERVISOR_UNLOCK_BYPASSED',
                'low',
                'Mode keamanan tidak mewajibkan kode pengawas.',
                ['metadata' => $validated['metadata'] ?? []]
            );

            return $this->sendSuccess([
                'allowed' => true,
                'security_mode' => $setting->security_mode,
            ], 'Mode SBT mengizinkan lanjut tanpa kode pengawas');
        }

        if (!$setting->hasSupervisorCode()) {
            return response()->json([
                'success' => false,
                'message' => 'Kode pengawas belum dikonfigurasi di SIAPS.',
                'data' => ['allowed' => false],
            ], 423);
        }

        $codeIsValid = Hash::check((string) $validated['supervisor_code'], (string) $setting->supervisor_code_hash);
        $this->recordSecurityEvent(
            $request,
            $session,
            $codeIsValid ? 'SUPERVISOR_UNLOCK_SUCCESS' : 'SUPERVISOR_UNLOCK_FAILED',
            $codeIsValid ? 'low' : 'high',
            $codeIsValid ? 'Kode pengawas valid, siswa diizinkan lanjut.' : 'Kode pengawas tidak sesuai.',
            [
                'metadata' => array_merge($validated['metadata'] ?? [], [
                    'source_event_type' => $validated['event_type'] ?? null,
                ]),
            ]
        );

        if (!$codeIsValid) {
            return response()->json([
                'success' => false,
                'message' => 'Kode pengawas tidak sesuai.',
                'data' => ['allowed' => false],
            ], 403);
        }

        return $this->sendSuccess([
            'allowed' => true,
            'security_mode' => $setting->security_mode,
        ], 'Kode pengawas valid');
    }

    public function adminSettings(): JsonResponse
    {
        return $this->sendSuccess(
            $this->serializeAdminSettings(SbtSetting::current()),
            'Pengaturan SBT berhasil diambil'
        );
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'exam_url' => 'required|url|max:2048',
            'webview_user_agent' => 'nullable|string|max:255',
            'security_mode' => ['required', Rule::in(['warning', 'supervisor_code', 'locked'])],
            'supervisor_code' => 'nullable|string|min:4|max:64',
            'clear_supervisor_code' => 'nullable|boolean',
            'minimum_app_version' => 'nullable|string|max:50',
            'require_dnd' => 'required|boolean',
            'require_screen_pinning' => 'required|boolean',
            'require_overlay_protection' => 'required|boolean',
            'ios_lock_on_background' => 'nullable|boolean',
            'minimum_battery_level' => 'required|integer|min:0|max:100',
            'heartbeat_interval_seconds' => 'required|integer|min:10|max:300',
            'maintenance_enabled' => 'required|boolean',
            'maintenance_message' => 'nullable|string|max:1000',
            'announcement' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Pengaturan SBT tidak valid', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $examUrl = rtrim((string) $validated['exam_url'], '/');
        $examHost = parse_url($examUrl, PHP_URL_HOST);

        if (!is_string($examHost) || trim($examHost) === '') {
            return $this->sendError('URL CBT tidak valid', [
                'exam_url' => ['Host URL CBT tidak terbaca.'],
            ], 422);
        }

        $setting = SbtSetting::current();
        $setting->fill([
            'enabled' => (bool) $validated['enabled'],
            'exam_url' => $examUrl,
            'exam_host' => strtolower($examHost),
            'webview_user_agent' => $this->nullableString($validated['webview_user_agent'] ?? null) ?? 'SBT-SMANIS/1.0',
            'security_mode' => $validated['security_mode'],
            'minimum_app_version' => $this->nullableString($validated['minimum_app_version'] ?? null),
            'require_dnd' => (bool) $validated['require_dnd'],
            'require_screen_pinning' => (bool) $validated['require_screen_pinning'],
            'require_overlay_protection' => (bool) $validated['require_overlay_protection'],
            'ios_lock_on_background' => (bool) ($validated['ios_lock_on_background'] ?? $setting->ios_lock_on_background ?? true),
            'minimum_battery_level' => (int) $validated['minimum_battery_level'],
            'heartbeat_interval_seconds' => (int) $validated['heartbeat_interval_seconds'],
            'maintenance_enabled' => (bool) $validated['maintenance_enabled'],
            'maintenance_message' => $this->nullableString($validated['maintenance_message'] ?? null),
            'announcement' => $this->nullableString($validated['announcement'] ?? null),
            'updated_by' => $request->user()?->id,
        ]);

        if (($validated['clear_supervisor_code'] ?? false) === true) {
            $setting->supervisor_code_hash = null;
            $setting->supervisor_code_updated_at = null;
        }

        $supervisorCode = trim((string) ($validated['supervisor_code'] ?? ''));
        if ($supervisorCode !== '') {
            $setting->supervisor_code_hash = Hash::make($supervisorCode);
            $setting->supervisor_code_updated_at = now();
        }

        if ($setting->requiresSupervisorCode() && !$setting->hasSupervisorCode()) {
            return $this->sendError('Mode keamanan ini membutuhkan kode pengawas.', [
                'supervisor_code' => ['Isi kode pengawas atau pilih mode peringatan.'],
            ], 422);
        }

        $setting->config_version = max(1, (int) $setting->config_version + 1);
        $setting->save();

        return $this->sendSuccess(
            $this->serializeAdminSettings($setting->fresh()),
            'Pengaturan SBT berhasil disimpan'
        );
    }

    public function adminSummary(): JsonResponse
    {
        $setting = SbtSetting::current();
        $cutoff = now()->subSeconds(max(90, (int) $setting->heartbeat_interval_seconds * 3));
        $lockEventTypes = [
            'APP_PAUSED',
            'APP_STOPPED',
            'IOS_APP_BACKGROUND',
            'IOS_APP_HIDDEN',
            'LOCK_TASK_NOT_ACTIVE',
            'LOCK_TASK_UNAVAILABLE',
        ];

        $summary = [
            'settings' => $this->serializeAdminSettings($setting),
            'active_sessions' => SbtExamSession::query()
                ->where('status', 'active')
                ->where('last_heartbeat_at', '>=', $cutoff)
                ->count(),
            'sessions_today' => SbtExamSession::query()
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'events_today' => SbtSecurityEvent::query()
                ->whereDate('created_at', now()->toDateString())
                ->count(),
            'high_risk_events_today' => SbtSecurityEvent::query()
                ->whereDate('created_at', now()->toDateString())
                ->whereIn('severity', ['high', 'critical'])
                ->count(),
            'lock_events_today' => SbtSecurityEvent::query()
                ->whereDate('created_at', now()->toDateString())
                ->whereIn('event_type', $lockEventTypes)
                ->count(),
            'supervisor_unlock_events_today' => SbtSecurityEvent::query()
                ->whereDate('created_at', now()->toDateString())
                ->whereIn('event_type', ['SUPERVISOR_UNLOCK_SUCCESS', 'SUPERVISOR_UNLOCK_FAILED'])
                ->count(),
            'latest_events' => SbtSecurityEvent::query()
                ->with('session:id,session_code,app_session_id,student_name,device_name,status')
                ->latest()
                ->limit(8)
                ->get()
                ->map(fn (SbtSecurityEvent $event) => $this->serializeEvent($event))
                ->values(),
        ];

        return $this->sendSuccess($summary, 'Ringkasan SBT berhasil diambil');
    }

    public function adminSessions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|max:30',
            'search' => 'nullable|string|max:120',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Filter sesi SBT tidak valid', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $query = SbtExamSession::query()->withCount('events');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['search'])) {
            $search = '%' . $validated['search'] . '%';
            $query->where(function ($item) use ($search): void {
                $item->where('student_name', 'like', $search)
                    ->orWhere('participant_identifier', 'like', $search)
                    ->orWhere('device_id', 'like', $search)
                    ->orWhere('device_name', 'like', $search)
                    ->orWhere('app_session_id', 'like', $search);
            });
        }

        $paginator = $query
            ->orderByDesc('last_heartbeat_at')
            ->orderByDesc('created_at')
            ->paginate((int) ($validated['per_page'] ?? 20));

        return $this->sendSuccess([
            'items' => collect($paginator->items())
                ->map(fn (SbtExamSession $session) => $this->serializeSession($session))
                ->values(),
            'pagination' => $this->serializePaginator($paginator),
        ], 'Daftar sesi SBT berhasil diambil');
    }

    public function adminEvents(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_type' => 'nullable|string|max:100',
            'severity' => 'nullable|string|max:20',
            'app_session_id' => 'nullable|string|max:100',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Filter event SBT tidak valid', $validator->errors()->toArray(), 422);
        }

        $validated = $validator->validated();
        $query = SbtSecurityEvent::query()
            ->with('session:id,session_code,app_session_id,student_name,device_name,status');

        if (!empty($validated['event_type'])) {
            $query->where('event_type', $validated['event_type']);
        }

        if (!empty($validated['severity'])) {
            $query->where('severity', $validated['severity']);
        }

        if (!empty($validated['app_session_id'])) {
            $query->where('app_session_id', $validated['app_session_id']);
        }

        $paginator = $query
            ->latest()
            ->paginate((int) ($validated['per_page'] ?? 30));

        return $this->sendSuccess([
            'items' => collect($paginator->items())
                ->map(fn (SbtSecurityEvent $event) => $this->serializeEvent($event))
                ->values(),
            'pagination' => $this->serializePaginator($paginator),
        ], 'Daftar event SBT berhasil diambil');
    }

    private function resolveSession(string $appSessionId): SbtExamSession
    {
        $session = SbtExamSession::query()->firstOrNew(['app_session_id' => $appSessionId]);

        if (!$session->exists) {
            $session->session_code = (string) Str::uuid();
            $session->status = 'started';
            $session->started_at = now();
            $session->platform = 'android';
        }

        return $session;
    }

    private function recordSecurityEvent(
        Request $request,
        SbtExamSession $session,
        string $eventType,
        string $severity,
        ?string $message,
        array $context = []
    ): SbtSecurityEvent {
        if (!$session->exists) {
            $session->last_heartbeat_at = now();
            $session->ip_address = $request->ip();
            $session->user_agent = $request->userAgent();
            $session->save();
        }

        $occurredAt = $context['occurred_at'] ?? null;

        return SbtSecurityEvent::query()->create([
            'sbt_exam_session_id' => $session->id,
            'app_session_id' => $session->app_session_id,
            'event_type' => strtoupper(trim($eventType)),
            'severity' => $this->normalizeSeverity($severity),
            'message' => $message,
            'occurred_at' => $occurredAt ? Carbon::parse($occurredAt) : now(),
            'app_version' => $context['app_version'] ?? $session->app_version,
            'device_id' => $context['device_id'] ?? $session->device_id,
            'ip_address' => $request->ip(),
            'metadata' => $context['metadata'] ?? [],
        ]);
    }

    private function severityForEventType(string $eventType): string
    {
        return match (strtoupper(trim($eventType))) {
            'APP_PAUSED', 'APP_STOPPED', 'IOS_APP_BACKGROUND', 'IOS_APP_HIDDEN', 'MULTI_WINDOW', 'PIP_MODE', 'LOCK_TASK_NOT_ACTIVE', 'LOCK_TASK_UNAVAILABLE', 'SUPERVISOR_UNLOCK_FAILED' => 'high',
            'IOS_APP_INACTIVE' => 'medium',
            'NAVIGATION_BLOCKED', 'DND_DISABLED', 'OVERLAY_UNSUPPORTED' => 'medium',
            default => 'low',
        };
    }

    private function normalizeSeverity(string $severity): string
    {
        $normalized = strtolower(trim($severity));

        return in_array($normalized, ['low', 'medium', 'high', 'critical'], true)
            ? $normalized
            : 'medium';
    }

    private function serializeMobileConfig(SbtSetting $setting): array
    {
        return [
            'enabled' => (bool) $setting->enabled,
            'exam_url' => $setting->exam_url,
            'exam_host' => $setting->exam_host,
            'webview_user_agent' => $setting->webview_user_agent ?: 'SBT-SMANIS/1.0',
            'security_mode' => $setting->security_mode,
            'requires_supervisor_code' => $setting->requiresSupervisorCode(),
            'has_supervisor_code' => $setting->hasSupervisorCode(),
            'minimum_app_version' => $setting->minimum_app_version,
            'minimum_battery_level' => (int) $setting->minimum_battery_level,
            'require_dnd' => (bool) $setting->require_dnd,
            'require_screen_pinning' => (bool) $setting->require_screen_pinning,
            'require_overlay_protection' => (bool) $setting->require_overlay_protection,
            'ios_lock_on_background' => (bool) $setting->ios_lock_on_background,
            'heartbeat_interval_seconds' => (int) $setting->heartbeat_interval_seconds,
            'maintenance_enabled' => (bool) $setting->maintenance_enabled,
            'maintenance_message' => $setting->maintenance_message,
            'announcement' => $setting->announcement,
            'config_version' => (int) $setting->config_version,
            'server_now' => now()->toISOString(),
            'server_epoch_ms' => now()->valueOf(),
            'timezone' => config('app.timezone'),
        ];
    }

    private function serializeAdminSettings(SbtSetting $setting): array
    {
        return array_merge($this->serializeMobileConfig($setting), [
            'id' => $setting->id,
            'supervisor_code_updated_at' => $setting->supervisor_code_updated_at?->toISOString(),
            'updated_by' => $setting->updated_by,
            'updated_at' => $setting->updated_at?->toISOString(),
        ]);
    }

    private function serializeSession(SbtExamSession $session): array
    {
        return [
            'id' => $session->id,
            'session_code' => $session->session_code,
            'app_session_id' => $session->app_session_id,
            'participant_identifier' => $session->participant_identifier,
            'student_name' => $session->student_name,
            'device_id' => $session->device_id,
            'device_name' => $session->device_name,
            'app_version' => $session->app_version,
            'platform' => $session->platform,
            'exam_url' => $session->exam_url,
            'status' => $session->status,
            'started_at' => $session->started_at?->toISOString(),
            'last_heartbeat_at' => $session->last_heartbeat_at?->toISOString(),
            'finished_at' => $session->finished_at?->toISOString(),
            'ip_address' => $session->ip_address,
            'events_count' => (int) ($session->events_count ?? 0),
            'metadata' => $session->metadata ?? [],
            'created_at' => $session->created_at?->toISOString(),
            'updated_at' => $session->updated_at?->toISOString(),
        ];
    }

    private function serializeEvent(SbtSecurityEvent $event): array
    {
        return [
            'id' => $event->id,
            'sbt_exam_session_id' => $event->sbt_exam_session_id,
            'app_session_id' => $event->app_session_id,
            'event_type' => $event->event_type,
            'severity' => $event->severity,
            'message' => $event->message,
            'occurred_at' => $event->occurred_at?->toISOString(),
            'app_version' => $event->app_version,
            'device_id' => $event->device_id,
            'ip_address' => $event->ip_address,
            'metadata' => $event->metadata ?? [],
            'session' => $event->relationLoaded('session') && $event->session
                ? $this->serializeSession($event->session)
                : null,
            'created_at' => $event->created_at?->toISOString(),
        ];
    }

    private function serializePaginator($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function attachPublicSbtDownloadUrl(array $payload): array
    {
        $latest = $payload['latest'] ?? null;
        if (!is_array($latest) || ($latest['download_kind'] ?? null) !== 'managed_asset') {
            return $payload;
        }

        $releaseId = $latest['id'] ?? null;
        if (!is_numeric($releaseId)) {
            return $payload;
        }

        $expiresAt = now()->addHours(12);
        $payload['latest']['download_url'] = URL::temporarySignedRoute(
            'mobile-releases.signed-download',
            $expiresAt,
            ['mobileRelease' => (int) $releaseId]
        );
        $payload['latest']['download_url_expires_at'] = $expiresAt->toISOString();

        return $payload;
    }
}
