<?php

namespace App\Http\Controllers\Api;

use App\Helpers\AuthHelper;
use App\Http\Controllers\Controller;
use App\Models\BroadcastCampaign;
use App\Services\BroadcastCampaignService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;

class BroadcastCampaignController extends Controller
{
    public function __construct(
        private readonly BroadcastCampaignService $broadcastCampaignService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|max:30',
            'message_category' => 'nullable|string|in:announcement,system',
            'created_from' => 'nullable|date',
            'created_to' => 'nullable|date|after_or_equal:created_from',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Filter tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $query = BroadcastCampaign::query()->with('creator:id,nama_lengkap,username');
        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }
        if ($request->filled('message_category')) {
            $query->where('message_category', (string) $request->input('message_category'));
        }
        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', (string) $request->input('created_from'));
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', (string) $request->input('created_to'));
        }

        $campaigns = $query
            ->orderByDesc('created_at')
            ->paginate((int) $request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => $campaigns,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'message' => 'required|string',
            'type' => 'required|string|in:info,warning,success,error',
            'message_category' => 'nullable|string|in:announcement,system',
            'display_start_at' => 'nullable|date',
            'display_end_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'priority' => 'nullable|integer|min:0|max:100',
            'pinned' => 'nullable|boolean',
            'channels' => 'required|array',
            'channels.in_app' => 'nullable|boolean',
            'channels.popup' => 'nullable|boolean',
            'channels.whatsapp' => 'nullable|boolean',
            'channels.email' => 'nullable|boolean',
            'audience' => 'required|array',
            'audience.mode' => 'required|string|in:all,role,class,user,manual',
            'audience.role' => 'nullable|string|exists:roles,name',
            'audience.kelas_id' => 'nullable|integer|exists:kelas,id',
            'audience.user_id' => 'nullable|integer|exists:users,id',
            'audience.manual_recipients' => 'nullable|array',
            'audience.manual_recipients.*' => 'string',
            'data' => 'nullable|array',
            'data.discipline_case_id' => 'nullable|integer|exists:attendance_discipline_cases,id',
            'popup' => 'nullable|array',
            'popup.variant' => 'nullable|string|in:info,flyer',
            'popup.title' => 'nullable|string|max:255',
            'popup.image_url' => 'nullable|url|max:1000',
            'popup.dismiss_label' => 'nullable|string|max:100',
            'popup.cta_label' => 'nullable|string|max:100',
            'popup.cta_url' => 'nullable|url|max:1000',
            'popup.sticky' => 'nullable|boolean',
            'whatsapp' => 'nullable|array',
            'whatsapp.footer' => 'nullable|string|max:255',
            'whatsapp.reply_to_message_id' => 'nullable|string|max:255',
            'email' => 'nullable|array',
            'email.subject' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Data broadcast tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();
        $channels = (array) ($validated['channels'] ?? []);
        $messageCategory = (string) ($validated['message_category'] ?? 'announcement');
        if (!in_array(true, array_map('boolval', $channels), true)) {
            return response()->json([
                'success' => false,
                'message' => 'Pilih minimal satu kanal broadcast',
            ], 422);
        }

        $audience = (array) ($validated['audience'] ?? []);
        $audienceMode = (string) ($audience['mode'] ?? 'all');
        if ($audienceMode === 'role' && empty($audience['role'])) {
            return response()->json([
                'success' => false,
                'message' => 'Role target wajib diisi',
            ], 422);
        }

        if ($audienceMode === 'class' && empty($audience['kelas_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'Kelas target wajib diisi',
            ], 422);
        }

        if ($audienceMode === 'user' && empty($audience['user_id'])) {
            return response()->json([
                'success' => false,
                'message' => 'User target wajib diisi',
            ], 422);
        }

        if ($audienceMode === 'manual') {
            $manualRecipients = array_values(array_filter(
                (array) ($audience['manual_recipients'] ?? []),
                static fn($item): bool => is_string($item) && trim($item) !== ''
            ));

            if ($manualRecipients === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor manual wajib diisi',
                ], 422);
            }

            if (!(bool) ($channels['whatsapp'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nomor manual hanya berlaku untuk kanal WhatsApp',
                ], 422);
            }

            $validated['audience']['manual_recipients'] = $manualRecipients;
        }

        $lifecycle = $this->resolveLifecycleWindow($request, $messageCategory, $channels);
        if (
            $lifecycle['display_start_at'] instanceof Carbon
            && $lifecycle['display_end_at'] instanceof Carbon
            && $lifecycle['display_end_at']->lt($lifecycle['display_start_at'])
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Akhir masa tayang tidak boleh lebih awal dari awal masa tayang',
            ], 422);
        }

        $campaign = BroadcastCampaign::create([
            'title' => (string) $validated['title'],
            'message' => (string) $validated['message'],
            'type' => (string) $validated['type'],
            'message_category' => $messageCategory,
            'channels' => $this->normalizeChannels($channels),
            'audience' => [
                'mode' => $audienceMode,
                'role' => $audience['role'] ?? null,
                'kelas_id' => $audience['kelas_id'] ?? null,
                'user_id' => $audience['user_id'] ?? null,
                'manual_recipients' => array_values((array) ($validated['audience']['manual_recipients'] ?? [])),
            ],
            'popup' => $validated['popup'] ?? null,
            'whatsapp' => $validated['whatsapp'] ?? null,
            'email' => $validated['email'] ?? null,
            'status' => 'processing',
            'display_start_at' => $lifecycle['display_start_at'],
            'display_end_at' => $lifecycle['display_end_at'],
            'expires_at' => $lifecycle['expires_at'],
            'pinned_at' => $lifecycle['pinned_at'],
            'priority' => $lifecycle['priority'],
            'created_by' => AuthHelper::userId(),
        ]);

        $dispatchedCampaign = $this->broadcastCampaignService->dispatch($campaign, [
            'title' => $validated['title'],
            'message' => $validated['message'],
            'type' => $validated['type'],
            'message_category' => $campaign->message_category,
            'channels' => $campaign->channels,
            'audience' => $campaign->audience,
            'data' => $validated['data'] ?? [],
            'popup' => $validated['popup'] ?? [],
            'whatsapp' => $validated['whatsapp'] ?? [],
            'email' => $validated['email'] ?? [],
            'lifecycle' => $this->serializeLifecycleForQueue($campaign),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Broadcast campaign berhasil diantrikan',
            'data' => $dispatchedCampaign,
        ], 201);
    }

    public function uploadFlyer(Request $request): JsonResponse
    {
        $validator = Validator::make(
            $request->all(),
            [
                'flyer' => 'required|file|mimes:jpg,jpeg,png,webp|max:10240',
            ],
            [
                'flyer.required' => 'File flyer wajib dipilih.',
                'flyer.file' => 'File flyer tidak terbaca.',
                'flyer.mimes' => 'Format flyer harus JPG, JPEG, PNG, atau WEBP.',
                'flyer.max' => 'Ukuran flyer maksimal 10MB.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'File flyer tidak valid',
                'errors' => $validator->errors(),
            ], 422);
        }

        $file = $request->file('flyer');
        if ($file === null) {
            return response()->json([
                'success' => false,
                'message' => 'File flyer tidak ditemukan',
            ], 422);
        }

        $filename = sprintf(
            '%s-%s.%s',
            now()->format('YmdHis'),
            Str::random(16),
            $file->getClientOriginalExtension()
        );
        $path = $file->storeAs('broadcast/flyers', $filename, 'public');

        return response()->json([
            'success' => true,
            'message' => 'Flyer berhasil diupload',
            'data' => [
                'path' => $path,
                'url' => asset('storage/' . $path),
                'name' => $file->getClientOriginalName(),
                'type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
            ],
        ], 201);
    }

    /**
     * @param array<string, mixed> $channels
     * @return array<string, bool>
     */
    private function normalizeChannels(array $channels): array
    {
        return [
            'in_app' => (bool) ($channels['in_app'] ?? false),
            'popup' => (bool) ($channels['popup'] ?? false),
            'whatsapp' => (bool) ($channels['whatsapp'] ?? false),
            'email' => (bool) ($channels['email'] ?? false),
        ];
    }

    /**
     * @param array<string, mixed> $channels
     * @return array{display_start_at:?Carbon,display_end_at:?Carbon,expires_at:?Carbon,pinned_at:?Carbon,priority:int}
     */
    private function resolveLifecycleWindow(Request $request, string $messageCategory, array $channels): array
    {
        $displayStart = $this->parseLifecycleDate($request->input('display_start_at'), false) ?? now();
        $displayEnd = $this->parseLifecycleDate($request->input('display_end_at'), true);
        $expiresAt = $this->parseLifecycleDate($request->input('expires_at'), true);
        $isInAppAnnouncement = $messageCategory === 'announcement'
            && ((bool) ($channels['in_app'] ?? false) || (bool) ($channels['popup'] ?? false));

        if ($displayEnd === null && $expiresAt instanceof Carbon) {
            $displayEnd = $expiresAt->copy();
        }

        if ($displayEnd === null && $isInAppAnnouncement) {
            $displayEnd = $displayStart->copy()->addDays(14)->endOfDay();
        }

        if ($expiresAt === null) {
            $expiresAt = $displayEnd?->copy();
        }

        return [
            'display_start_at' => $displayStart,
            'display_end_at' => $displayEnd,
            'expires_at' => $expiresAt,
            'pinned_at' => $request->boolean('pinned') ? now() : null,
            'priority' => max(0, min((int) $request->input('priority', 0), 100)),
        ];
    }

    private function parseLifecycleDate(mixed $value, bool $endOfDayWhenDateOnly): ?Carbon
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $date = Carbon::parse($raw);
        if ($endOfDayWhenDateOnly && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $date->endOfDay();
        }

        return $date;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLifecycleForQueue(BroadcastCampaign $campaign): array
    {
        return [
            'display_start_at' => $campaign->display_start_at?->toIso8601String(),
            'display_end_at' => $campaign->display_end_at?->toIso8601String(),
            'expires_at' => $campaign->expires_at?->toIso8601String(),
            'pinned_at' => $campaign->pinned_at?->toIso8601String(),
            'priority' => (int) $campaign->priority,
        ];
    }
}
