<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendanceSchema;
use App\Models\AttendanceGovernanceLog;
use App\Models\LokasiGps;
use App\Models\User;
use App\Services\LiveTrackingIngestService;
use App\Services\LiveTrackingCurrentStoreService;
use App\Services\LiveTrackingContextService;
use App\Services\LiveTrackingSnapshotService;
use App\Services\AttendanceTimeService;
use App\Support\Geofence;
use App\Support\RoleDataScope;
use App\Support\RoleNames;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LokasiGpsController extends Controller
{
    private const FORCE_SESSION_CACHE_PREFIX = 'live_tracking:force_session:';
    private AttendanceTimeService $attendanceTimeService;
    private LiveTrackingIngestService $liveTrackingIngestService;
    private LiveTrackingCurrentStoreService $liveTrackingCurrentStoreService;
    private LiveTrackingContextService $liveTrackingContextService;
    private LiveTrackingSnapshotService $liveTrackingSnapshotService;

    public function __construct(
        AttendanceTimeService $attendanceTimeService,
        LiveTrackingIngestService $liveTrackingIngestService,
        LiveTrackingCurrentStoreService $liveTrackingCurrentStoreService,
        LiveTrackingContextService $liveTrackingContextService,
        LiveTrackingSnapshotService $liveTrackingSnapshotService
    )
    {
        $this->attendanceTimeService = $attendanceTimeService;
        $this->liveTrackingIngestService = $liveTrackingIngestService;
        $this->liveTrackingCurrentStoreService = $liveTrackingCurrentStoreService;
        $this->liveTrackingContextService = $liveTrackingContextService;
        $this->liveTrackingSnapshotService = $liveTrackingSnapshotService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $lokasi = LokasiGps::orderBy('created_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $lokasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch locations',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = $this->makeLocationValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lokasi = LokasiGps::create($this->extractLocationPayload($request));

            Log::info('Attendance GPS location created', [
                'actor_user_id' => auth()->id(),
                'location_id' => $lokasi->id,
                'nama_lokasi' => $lokasi->nama_lokasi,
                'radius' => $lokasi->radius,
                'geofence_type' => $lokasi->getNormalizedGeofenceType(),
                'is_active' => (bool) $lokasi->is_active,
            ]);

            AttendanceGovernanceLog::record([
                'category' => 'attendance_location',
                'action' => 'created',
                'actor_user_id' => auth()->id(),
                'target_type' => 'lokasi_gps',
                'target_id' => $lokasi->id,
                'new_values' => $lokasi->only([
                    'nama_lokasi',
                    'radius',
                    'geofence_type',
                    'is_active',
                    'latitude',
                    'longitude',
                    'waktu_mulai',
                    'waktu_selesai',
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location created successfully',
                'data' => $lokasi
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create location',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        try {
            $lokasi = LokasiGps::findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $lokasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Location not found',
                'error' => 'Internal server error'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $validator = $this->makeLocationValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $lokasi = LokasiGps::findOrFail($id);
            $oldValues = $lokasi->only([
                'nama_lokasi',
                'radius',
                'geofence_type',
                'is_active',
                'latitude',
                'longitude',
                'waktu_mulai',
                'waktu_selesai',
            ]);

            $lokasi->update($this->extractLocationPayload($request));

            $newValues = $lokasi->fresh()->only([
                'nama_lokasi',
                'radius',
                'geofence_type',
                'is_active',
                'latitude',
                'longitude',
                'waktu_mulai',
                'waktu_selesai',
            ]);

            $changedKeys = [];
            foreach ($newValues as $key => $newValue) {
                if (($oldValues[$key] ?? null) != $newValue) {
                    $changedKeys[] = $key;
                }
            }

            if (!empty($changedKeys)) {
                Log::info('Attendance GPS location updated', [
                    'actor_user_id' => auth()->id(),
                    'location_id' => $lokasi->id,
                    'changed_fields' => $changedKeys,
                    'old' => $oldValues,
                    'new' => $newValues,
                ]);

                AttendanceGovernanceLog::record([
                    'category' => 'attendance_location',
                    'action' => 'updated',
                    'actor_user_id' => auth()->id(),
                    'target_type' => 'lokasi_gps',
                    'target_id' => $lokasi->id,
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'metadata' => [
                        'changed_fields' => $changedKeys,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => $lokasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        try {
            $lokasi = LokasiGps::findOrFail($id);
            $snapshot = $lokasi->only(['nama_lokasi', 'radius', 'geofence_type', 'is_active', 'latitude', 'longitude']);
            $lokasi->delete();

            Log::info('Attendance GPS location deleted', [
                'actor_user_id' => auth()->id(),
                'location_id' => $id,
                'snapshot' => $snapshot,
            ]);

            AttendanceGovernanceLog::record([
                'category' => 'attendance_location',
                'action' => 'deleted',
                'actor_user_id' => auth()->id(),
                'target_type' => 'lokasi_gps',
                'target_id' => (int) $id,
                'old_values' => $snapshot,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete location',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Import locations from file
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xlsx,json|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('file');
            $extension = $file->getClientOriginalExtension();
            $imported = 0;
            $details = [];

            if ($extension === 'json') {
                $content = file_get_contents($file->getRealPath());
                $data = json_decode($content, true);

                if (!is_array($data)) {
                    throw new \Exception('Invalid JSON format');
                }

                foreach ($data as $item) {
                    $type = Geofence::normalizeType((string) ($item['geofence_type'] ?? Geofence::TYPE_CIRCLE));
                    $geofenceValidation = Geofence::validatePayload($type, $item['geofence_geojson'] ?? null);

                    $lokasi = LokasiGps::create([
                        'nama_lokasi' => $item['nama_lokasi'] ?? $item['nama'] ?? 'Unknown',
                        'deskripsi' => $item['deskripsi'] ?? '',
                        'latitude' => $item['latitude'] ?? 0,
                        'longitude' => $item['longitude'] ?? 0,
                        'radius' => $item['radius'] ?? 100,
                        'geofence_type' => $type,
                        'geofence_geojson' => $geofenceValidation['normalized'],
                        'is_active' => $item['is_active'] ?? true,
                        'warna_marker' => $item['warna_marker'] ?? '#3B82F6',
                        'roles' => json_encode($item['roles'] ?? []),
                        'waktu_mulai' => $item['waktu_mulai'] ?? '06:00',
                        'waktu_selesai' => $item['waktu_selesai'] ?? '18:00',
                        'hari_aktif' => json_encode($item['hari_aktif'] ?? ['senin', 'selasa', 'rabu', 'kamis', 'jumat'])
                    ]);

                    $imported++;
                    $details[] = "Imported: {$lokasi->nama_lokasi}";
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$imported} locations",
                'imported' => $imported,
                'details' => $details
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Export locations to file
     */
    public function export(Request $request)
    {
        try {
            $format = $request->get('format', 'json');
            $lokasi = LokasiGps::all();

            if ($format === 'json') {
                $data = $lokasi->map(function ($item) {
                    return [
                        'nama_lokasi' => $item->nama_lokasi,
                        'deskripsi' => $item->deskripsi,
                        'latitude' => $item->latitude,
                        'longitude' => $item->longitude,
                        'radius' => $item->radius,
                        'geofence_type' => $item->getNormalizedGeofenceType(),
                        'geofence_geojson' => $item->geofence_geojson,
                        'is_active' => $item->is_active,
                        'warna_marker' => $item->warna_marker,
                        'roles' => json_decode($item->roles, true),
                        'waktu_mulai' => $item->waktu_mulai,
                        'waktu_selesai' => $item->waktu_selesai,
                        'hari_aktif' => json_decode($item->hari_aktif, true)
                    ];
                });

                return response()->json($data)
                    ->header('Content-Type', 'application/json')
                    ->header('Content-Disposition', 'attachment; filename="lokasi-gps.json"');
            }

            return response()->json([
                'success' => false,
                'message' => 'Unsupported export format'
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Export failed',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get active locations for attendance
     */
    public function getActiveLocations()
    {
        try {
            $lokasi = LokasiGps::where('is_active', true)->get();

            return response()->json([
                'success' => true,
                'data' => $lokasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active locations',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Check distance from user location to attendance locations
     */
    public function checkDistance(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coordinates',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userLat = (float) $request->latitude;
            $userLon = (float) $request->longitude;

            $evaluationSource = 'active_locations';
            $gpsRequired = true;
            $schemaId = null;

            // Default fallback: semua lokasi aktif.
            $locations = LokasiGps::where('is_active', true)->get();

            // Prioritas utama: schema efektif user + lokasi terpilih schema.
            $authUser = $request->user();
            if ($authUser) {
                $attendanceService = app(\App\Services\AttendanceSchemaService::class);
                $effectiveSchema = $attendanceService->getEffectiveSchema($authUser);

                if ($effectiveSchema instanceof AttendanceSchema) {
                    $schemaId = (int) $effectiveSchema->id;
                    $gpsRequired = (bool) $effectiveSchema->wajib_gps;

                    if (!$gpsRequired) {
                        return response()->json([
                            'success' => true,
                            'data' => [
                                'can_attend' => true,
                                'nearest_distance' => 0.0,
                                'nearest_distance_formatted' => '0 m',
                                'locations' => [],
                                'user_coordinates' => [
                                    'latitude' => $userLat,
                                    'longitude' => $userLon,
                                ],
                                'gps_required' => false,
                                'evaluation_source' => 'schema_effective_no_gps',
                                'schema_id' => $schemaId,
                            ]
                        ]);
                    }

                    $locations = $effectiveSchema->getAllowedLocations();
                    $evaluationSource = 'schema_effective';
                }
            }

            if ($locations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tidak ada lokasi absensi yang dapat digunakan untuk schema saat ini',
                    'data' => [
                        'can_attend' => false,
                        'nearest_distance' => null,
                        'locations' => [],
                        'gps_required' => $gpsRequired,
                        'evaluation_source' => $evaluationSource,
                        'schema_id' => $schemaId,
                    ]
                ]);
            }

            $locationData = [];
            $nearestDistance = PHP_FLOAT_MAX;
            $canAttend = false;

            foreach ($locations as $location) {
                $evaluation = $location->evaluateCoordinate($userLat, $userLon);
                $distance = (float) ($evaluation['distance_to_area'] ?? PHP_FLOAT_MAX);
                $isWithinArea = (bool) ($evaluation['inside'] ?? false);

                if ($isWithinArea) {
                    $canAttend = true;
                }

                if ($distance < $nearestDistance) {
                    $nearestDistance = $distance;
                }

                $locationData[] = [
                    'id' => $location->id,
                    'nama_lokasi' => $location->nama_lokasi,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'radius' => $location->radius,
                    'geofence_type' => $location->getNormalizedGeofenceType(),
                    'geofence_geojson' => $location->geofence_geojson,
                    'distance' => round($distance, 2),
                    'distance_to_boundary' => round((float) ($evaluation['distance_to_boundary'] ?? $distance), 2),
                    'distance_to_center' => $evaluation['distance_to_center'] ?? null,
                    'is_within_area' => $isWithinArea,
                    'is_within_radius' => $isWithinArea,
                    'distance_formatted' => $distance >= 1000
                        ? round($distance / 1000, 2) . ' km'
                        : round($distance, 1) . ' m'
                ];
            }

            // Sort by distance
            usort($locationData, function ($a, $b) {
                return $a['distance'] <=> $b['distance'];
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'can_attend' => $canAttend,
                    'nearest_distance' => round($nearestDistance, 2),
                    'nearest_distance_formatted' => $nearestDistance >= 1000
                        ? round($nearestDistance / 1000, 2) . ' km'
                        : round($nearestDistance, 1) . ' m',
                    'locations' => $locationData,
                    'user_coordinates' => [
                        'latitude' => $userLat,
                        'longitude' => $userLon
                    ],
                    'gps_required' => $gpsRequired,
                    'evaluation_source' => $evaluationSource,
                    'schema_id' => $schemaId,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to check distance',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get attendance schema/settings for mobile app
     */
    public function getAttendanceSchema(Request $request)
    {
        try {
            $user = $request->user();
            $context = strtolower(trim((string) $request->query('context', '')));
            $isTrackingMonitorContext = in_array($context, ['live_tracking_monitor', 'tracking_monitor'], true);
            $isStudentUser = $user && $user->hasRole(RoleNames::aliases(RoleNames::SISWA));

            // Get active locations
            $locations = LokasiGps::where('is_active', true)->get();

            // Get user's effective attendance schema
            $attendanceService = app(\App\Services\AttendanceSchemaService::class);
            $effectiveSchema = $attendanceService->getEffectiveSchema($user);

            // Monitoring live tracking harus sinkron dengan jadwal siswa,
            // bukan jadwal akun admin/guru yang sedang membuka dashboard.
            if ($isTrackingMonitorContext && !$isStudentUser) {
                $effectiveSchema = $this->resolveStudentTrackingSchema() ?? $effectiveSchema;
            }

            if ($effectiveSchema) {
                // Use schema's mobile config
                $configUser = ($isTrackingMonitorContext && !$isStudentUser) ? null : $user;
                $mobileConfig = $effectiveSchema->getMobileConfig($configUser);

                return response()->json([
                    'success' => true,
                    'data' => array_merge($mobileConfig, [
                        'tracking_policy' => $user && $isStudentUser
                            ? $this->buildTrackingPolicy($user)
                            : null,
                    ]),
                    'meta' => $this->serverTimeMeta(),
                ]);
            }

            $fallbackHours = ['jam_masuk' => '07:00', 'jam_pulang' => '15:00', 'toleransi' => 15];
            if ($isTrackingMonitorContext && !$isStudentUser) {
                $studentSchema = $this->resolveStudentTrackingSchema();
                if ($studentSchema) {
                    $candidateHours = $studentSchema->getEffectiveWorkingHours();
                    $fallbackHours['jam_masuk'] = (string) ($candidateHours['jam_masuk'] ?? $fallbackHours['jam_masuk']);
                    $fallbackHours['jam_pulang'] = (string) ($candidateHours['jam_pulang'] ?? $fallbackHours['jam_pulang']);
                    $fallbackHours['toleransi'] = (int) ($candidateHours['toleransi'] ?? $fallbackHours['toleransi']);
                }
            } elseif ($user) {
                $candidateHours = $this->attendanceTimeService->getWorkingHours($user);
                $fallbackHours['jam_masuk'] = (string) ($candidateHours['jam_masuk'] ?? $fallbackHours['jam_masuk']);
                $fallbackHours['jam_pulang'] = (string) ($candidateHours['jam_pulang'] ?? $fallbackHours['jam_pulang']);
                $fallbackHours['toleransi'] = (int) ($candidateHours['toleransi'] ?? $fallbackHours['toleransi']);
            }

            // Fallback if no schema found
            $schema = [
                'locations' => $locations->map(function ($location) {
                    return $this->serializeLocationForClient($location);
                }),
                'settings' => [
                    'wajib_gps' => true,
                    'wajib_foto' => true,
                    'face_verification_enabled' => (bool) data_get(
                        app(\App\Services\AttendanceRuntimeConfigService::class)->getFaceVerificationPolicyConfig(),
                        'enabled',
                        true
                    ),
                    'gps_accuracy' => 20,
                    'gps_accuracy_grace' => (float) config('attendance.gps.accuracy_grace_meters', 0),
                    'jam_masuk' => $fallbackHours['jam_masuk'],
                    'jam_pulang' => $fallbackHours['jam_pulang'],
                    'toleransi_keterlambatan' => $fallbackHours['toleransi'],
                    'radius_maksimal' => 100,
                    'hari_kerja' => ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat']
                ],
                'user_info' => [
                    'can_attend' => true,
                    'status_kepegawaian' => $user->status_kepegawaian ?? 'Unknown',
                    'roles' => $user->roles->pluck('name')->toArray()
                ],
                'tracking_policy' => $user && $isStudentUser
                    ? $this->buildTrackingPolicy($user)
                    : null,
            ];

            return response()->json([
                'success' => true,
                'data' => $schema,
                'meta' => $this->serverTimeMeta(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get attendance schema',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update user's current location for real-time tracking
     */
    public function updateUserLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy' => 'nullable|numeric|min:0',
            'speed' => 'nullable|numeric|min:0',
            'heading' => 'nullable|numeric|min:0|max:360',
            'device_source' => 'nullable|string|in:web,mobile,unknown',
            'device_session_id' => 'nullable|string|max:120',
            'platform' => 'nullable|string|max:120',
            'app_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid location data',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            if (!$user || !$user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fitur update lokasi realtime hanya untuk siswa'
                ], 403);
            }

            if (!$this->isLiveTrackingGloballyEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Live tracking sedang dinonaktifkan oleh admin',
                ], 403);
            }

            $forceSession = $this->getForceTrackingSession((int) $user->id);

            $now = Carbon::now();
            if (!$forceSession && !$this->attendanceTimeService->isWorkingDay($user, $now)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tracking realtime hanya aktif pada hari efektif sesuai jadwal absensi'
                ], 403);
            }

            $workingHours = $this->attendanceTimeService->getWorkingHours($user);
            $jamMulai = $this->parseWorkTime((string) ($workingHours['jam_masuk'] ?? '07:00'));
            $jamSelesai = $this->parseWorkTime((string) ($workingHours['jam_pulang'] ?? '15:00'));

            if ($jamSelesai->lt($jamMulai)) {
                // Guard konfigurasi invalid agar tidak menolak tracking valid.
                $jamSelesai = $jamMulai->copy()->addHours(8);
            }

            $currentTime = Carbon::createFromFormat('H:i:s', $now->format('H:i:s'));
            if (!$forceSession && ($currentTime->lt($jamMulai) || $currentTime->gt($jamSelesai))) {
                return response()->json([
                    'success' => false,
                    'message' => "Tracking realtime hanya aktif saat jam absensi ({$jamMulai->format('H:i')}-{$jamSelesai->format('H:i')})"
                ], 403);
            }

            $trackedAt = now();
            $accuracy = $request->accuracy !== null ? (float) $request->accuracy : null;
            $context = $this->liveTrackingContextService->resolve(
                (float) $request->latitude,
                (float) $request->longitude,
                $accuracy
            );
            $deviceSource = $this->liveTrackingContextService->normalizeDeviceSource(
                (string) $request->input('device_source', LiveTrackingContextService::DEVICE_SOURCE_UNKNOWN)
            );
            $deviceInfo = array_filter([
                'source' => 'lokasi_gps_update_location',
                'session_id' => $request->input('device_session_id'),
                'platform' => $request->input('platform'),
                'app_version' => $request->input('app_version'),
                'force_session_active' => !empty($forceSession),
            ], static fn ($value) => $value !== null && $value !== '');

            $snapshot = array_merge($context, [
                'user_id' => $user->id,
                'user_name' => $user->nama_lengkap ?: ($user->username ?: $user->email),
                'latitude' => (float) $request->latitude,
                'longitude' => (float) $request->longitude,
                'accuracy' => $accuracy,
                'speed' => $request->speed !== null ? (float) $request->speed : null,
                'heading' => $request->heading !== null ? (float) $request->heading : null,
                'tracked_at' => $trackedAt->toISOString(),
                'status' => 'online',
                'device_source' => $deviceSource,
                'device_info' => $deviceInfo,
                'ip_address' => $request->ip(),
                'tracking_session_active' => !empty($forceSession),
                'tracking_session_expires_at' => $forceSession['expires_at'] ?? null
            ]);

            $snapshot = $this->liveTrackingSnapshotService->put($snapshot);
            $this->liveTrackingIngestService->persistSnapshot($snapshot);
            $this->liveTrackingCurrentStoreService->upsertSnapshot($user, $snapshot);

            return response()->json([
                'success' => true,
                'message' => 'Location updated successfully',
                'data' => $this->liveTrackingSnapshotService->appendRealtimeStatus($snapshot, $trackedAt),
                'meta' => $this->serverTimeMeta(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Update explicit tracking state without requiring a fresh coordinate fix.
     */
    public function updateTrackingState(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'state' => 'required|string|in:gps_disabled',
            'device_source' => 'nullable|string|in:web,mobile,unknown',
            'device_session_id' => 'nullable|string|max:120',
            'platform' => 'nullable|string|max:120',
            'app_version' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tracking state data',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $user = $request->user();
            if (!$user || !$user->hasRole(RoleNames::aliases(RoleNames::SISWA))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Fitur update status tracking realtime hanya untuk siswa'
                ], 403);
            }

            if (!$this->isLiveTrackingGloballyEnabled()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Live tracking sedang dinonaktifkan oleh admin',
                ], 403);
            }

            $forceSession = $this->getForceTrackingSession((int) $user->id);

            $now = Carbon::now();
            if (!$forceSession && !$this->attendanceTimeService->isWorkingDay($user, $now)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tracking realtime hanya aktif pada hari efektif sesuai jadwal absensi'
                ], 403);
            }

            $workingHours = $this->attendanceTimeService->getWorkingHours($user);
            $jamMulai = $this->parseWorkTime((string) ($workingHours['jam_masuk'] ?? '07:00'));
            $jamSelesai = $this->parseWorkTime((string) ($workingHours['jam_pulang'] ?? '15:00'));

            if ($jamSelesai->lt($jamMulai)) {
                $jamSelesai = $jamMulai->copy()->addHours(8);
            }

            $currentTime = Carbon::createFromFormat('H:i:s', $now->format('H:i:s'));
            if (!$forceSession && ($currentTime->lt($jamMulai) || $currentTime->gt($jamSelesai))) {
                return response()->json([
                    'success' => false,
                    'message' => "Tracking realtime hanya aktif saat jam absensi ({$jamMulai->format('H:i')}-{$jamSelesai->format('H:i')})"
                ], 403);
            }

            $trackedAt = now();
            $deviceSource = $this->liveTrackingContextService->normalizeDeviceSource(
                (string) $request->input('device_source', LiveTrackingContextService::DEVICE_SOURCE_UNKNOWN)
            );
            $deviceInfo = array_filter([
                'source' => 'lokasi_gps_update_tracking_state',
                'session_id' => $request->input('device_session_id'),
                'platform' => $request->input('platform'),
                'app_version' => $request->input('app_version'),
                'force_session_active' => !empty($forceSession),
            ], static fn ($value) => $value !== null && $value !== '');

            $previousSnapshot = $this->liveTrackingSnapshotService->get((int) $user->id);
            $snapshot = array_merge(is_array($previousSnapshot) ? $previousSnapshot : [], [
                'user_id' => $user->id,
                'user_name' => $user->nama_lengkap ?: ($user->username ?: $user->email),
                'tracked_at' => $trackedAt->toISOString(),
                'status' => (string) $request->input('state'),
                'device_source' => $deviceSource,
                'device_info' => $deviceInfo,
                'ip_address' => $request->ip(),
                'tracking_session_active' => !empty($forceSession),
                'tracking_session_expires_at' => $forceSession['expires_at'] ?? null,
                'gps_quality_status' => $previousSnapshot['gps_quality_status'] ?? LiveTrackingContextService::GPS_QUALITY_UNKNOWN,
                'is_in_school_area' => (bool) ($previousSnapshot['is_in_school_area'] ?? false),
                'within_gps_area' => (bool) ($previousSnapshot['within_gps_area'] ?? false),
                'location_id' => $previousSnapshot['location_id'] ?? null,
                'location_name' => $previousSnapshot['location_name'] ?? null,
                'current_location' => is_array($previousSnapshot['current_location'] ?? null) ? $previousSnapshot['current_location'] : null,
                'nearest_location' => is_array($previousSnapshot['nearest_location'] ?? null) ? $previousSnapshot['nearest_location'] : null,
                'distance_to_nearest' => isset($previousSnapshot['distance_to_nearest']) ? (float) $previousSnapshot['distance_to_nearest'] : null,
                'accuracy' => $previousSnapshot['accuracy'] ?? null,
                'speed' => $previousSnapshot['speed'] ?? null,
                'heading' => $previousSnapshot['heading'] ?? null,
                'latitude' => $previousSnapshot['latitude'] ?? null,
                'longitude' => $previousSnapshot['longitude'] ?? null,
            ]);

            $snapshot = $this->liveTrackingSnapshotService->put($snapshot);
            $this->liveTrackingCurrentStoreService->upsertSnapshot($user, $snapshot);

            return response()->json([
                'success' => true,
                'message' => 'Tracking state updated successfully',
                'data' => $this->liveTrackingSnapshotService->appendRealtimeStatus($snapshot, $trackedAt),
                'meta' => $this->serverTimeMeta(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update tracking state',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get all active users' locations for real-time tracking
     */
    public function getActiveUsersLocations(Request $request)
    {
        try {
            if (!$this->isLiveTrackingGloballyEnabled()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'meta' => array_merge($this->serverTimeMeta(), [
                        'tracking_enabled' => false,
                    ]),
                ]);
            }

            $snapshots = $this->liveTrackingSnapshotService->getMany();
            $snapshots = $this->filterSnapshotsByActorAccess($snapshots, $request->user());
            $activeUsers = array_values(array_filter(array_map(function (array $snapshot): ?array {
                $enriched = $this->liveTrackingSnapshotService->appendRealtimeStatus($snapshot);
                return ($enriched['is_tracking_active'] ?? false) ? $enriched : null;
            }, $snapshots)));

            $gpsLocations = LokasiGps::where('is_active', true)->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'active_users' => $activeUsers,
                    'gps_locations' => $gpsLocations->map(function ($location) {
                        return $this->serializeLocationForClient($location);
                    }),
                    'total_active_users' => count($activeUsers),
                    'users_in_gps_area' => count(array_filter($activeUsers, function ($user) {
                        return $user['within_gps_area'];
                    })),
                    'last_updated' => now()->toISOString(),
                ],
                'meta' => $this->serverTimeMeta(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get active users locations',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get users within specific GPS location
     */
    public function getUsersInLocation(Request $request, $locationId)
    {
        try {
            $location = LokasiGps::findOrFail($locationId);
            $usersInLocation = [];

            $snapshots = $this->liveTrackingSnapshotService->getMany();
            $snapshots = $this->filterSnapshotsByActorAccess($snapshots, $request->user());

            foreach ($snapshots as $snapshot) {
                $enriched = $this->liveTrackingSnapshotService->appendRealtimeStatus($snapshot);
                if (!($enriched['is_tracking_active'] ?? false)) {
                    continue;
                }

                $evaluation = $location->evaluateCoordinate(
                    (float) $enriched['latitude'],
                    (float) $enriched['longitude']
                );

                if (($evaluation['inside'] ?? false) === true) {
                    $enriched['distance'] = round((float) ($evaluation['distance_to_area'] ?? 0.0), 2);
                    $usersInLocation[] = $enriched;
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'location' => [
                        'id' => $location->id,
                        'nama_lokasi' => $location->nama_lokasi,
                        'latitude' => $location->latitude,
                        'longitude' => $location->longitude,
                        'radius' => $location->radius,
                        'geofence_type' => $location->getNormalizedGeofenceType(),
                        'geofence_geojson' => $location->geofence_geojson,
                    ],
                    'users_in_location' => $usersInLocation,
                    'total_users' => count($usersInLocation)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get users in location',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get active force tracking session for a student (used as override).
     */
    private function getForceTrackingSession(int $userId): ?array
    {
        $session = Cache::get(self::FORCE_SESSION_CACHE_PREFIX . $userId);
        if (!is_array($session) || empty($session['user_id']) || (int) $session['user_id'] !== $userId) {
            return null;
        }

        $expiresAt = isset($session['expires_at']) ? Carbon::parse($session['expires_at']) : null;
        if (!$expiresAt || $expiresAt->lte(now())) {
            Cache::forget(self::FORCE_SESSION_CACHE_PREFIX . $userId);
            return null;
        }

        return $session;
    }

    private function resolveStudentTrackingSchema(): ?AttendanceSchema
    {
        $studentRoleAliases = RoleNames::aliases(RoleNames::SISWA);

        $studentSchema = AttendanceSchema::query()
            ->where('is_active', true)
            ->whereIn('target_role', $studentRoleAliases)
            ->orderByDesc('is_default')
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->first();

        if ($studentSchema instanceof AttendanceSchema) {
            return $studentSchema;
        }

        return AttendanceSchema::query()
            ->where('is_active', true)
            ->where('is_default', true)
            ->orderByDesc('priority')
            ->orderByDesc('updated_at')
            ->first();
    }

    /**
     * Toggle location status
     */
    public function toggle($id)
    {
        try {
            $lokasi = LokasiGps::findOrFail($id);
            $oldState = (bool) $lokasi->is_active;
            $lokasi->is_active = !$lokasi->is_active;
            $lokasi->save();

            Log::info('Attendance GPS location toggled', [
                'actor_user_id' => auth()->id(),
                'location_id' => $lokasi->id,
                'old_is_active' => $oldState,
                'new_is_active' => (bool) $lokasi->is_active,
                'radius' => $lokasi->radius,
                'geofence_type' => $lokasi->getNormalizedGeofenceType(),
            ]);

            AttendanceGovernanceLog::record([
                'category' => 'attendance_location',
                'action' => 'toggled_active',
                'actor_user_id' => auth()->id(),
                'target_type' => 'lokasi_gps',
                'target_id' => $lokasi->id,
                'old_values' => [
                    'is_active' => $oldState,
                ],
                'new_values' => [
                    'is_active' => (bool) $lokasi->is_active,
                ],
                'metadata' => [
                    'radius' => $lokasi->radius,
                    'geofence_type' => $lokasi->getNormalizedGeofenceType(),
                ],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location status updated successfully',
                'data' => $lokasi
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle location status',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    /**
     * Validate user location against GPS areas
     */
    public function validateLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid coordinates',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = LokasiGps::checkValidLocation($request->latitude, $request->longitude);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate location',
                'error' => 'Internal server error'
            ], 500);
        }
    }

    private function makeLocationValidator(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_lokasi' => 'required|string|max:255',
            'deskripsi' => 'nullable|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'radius' => 'nullable|integer|min:10|max:1000',
            'geofence_type' => 'nullable|string|in:circle,polygon',
            'geofence_geojson' => 'nullable',
            'is_active' => 'boolean',
            'warna_marker' => 'nullable|string|max:7',
            'roles' => 'nullable|string',
            'waktu_mulai' => 'nullable|string',
            'waktu_selesai' => 'nullable|string',
            'hari_aktif' => 'nullable|string',
        ]);

        $validator->after(function ($validator) use ($request) {
            $type = Geofence::normalizeType((string) $request->input('geofence_type', Geofence::TYPE_CIRCLE));

            if ($type === Geofence::TYPE_CIRCLE && !$request->filled('radius')) {
                $validator->errors()->add('radius', 'Radius wajib diisi untuk tipe area circle.');
            }

            $geofenceValidation = Geofence::validatePayload($type, $request->input('geofence_geojson'));
            if (!$geofenceValidation['valid']) {
                $validator->errors()->add('geofence_geojson', (string) $geofenceValidation['message']);
            }
        });

        return $validator;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractLocationPayload(Request $request): array
    {
        $type = Geofence::normalizeType((string) $request->input('geofence_type', Geofence::TYPE_CIRCLE));
        $geofenceValidation = Geofence::validatePayload($type, $request->input('geofence_geojson'));

        return [
            'nama_lokasi' => $request->nama_lokasi,
            'deskripsi' => $request->deskripsi,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'radius' => $request->input('radius', 100),
            'geofence_type' => $type,
            'geofence_geojson' => $geofenceValidation['normalized'],
            'is_active' => $request->is_active ?? true,
            'warna_marker' => $request->warna_marker ?? '#2196F3',
            'roles' => $request->roles ?? '[]',
            'waktu_mulai' => $request->waktu_mulai ?? '06:00',
            'waktu_selesai' => $request->waktu_selesai ?? '18:00',
            'hari_aktif' => $request->hari_aktif ?? '["senin","selasa","rabu","kamis","jumat"]',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLocationForClient(LokasiGps $location): array
    {
        return [
            'id' => $location->id,
            'nama_lokasi' => $location->nama_lokasi,
            'deskripsi' => $location->deskripsi,
            'latitude' => $location->latitude,
            'longitude' => $location->longitude,
            'radius' => $location->radius,
            'geofence_type' => $location->getNormalizedGeofenceType(),
            'geofence_geojson' => $location->geofence_geojson,
            'warna_marker' => $location->warna_marker,
            'waktu_mulai' => $location->waktu_mulai,
            'waktu_selesai' => $location->waktu_selesai,
            'hari_aktif' => $location->hari_aktif,
            'is_active' => (bool) $location->is_active,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $snapshots
     * @return array<int, array<string, mixed>>
     */
    private function filterSnapshotsByActorAccess(array $snapshots, ?User $actor): array
    {
        if (!$actor || RoleDataScope::canViewAllSiswa($actor) || $snapshots === []) {
            return $snapshots;
        }

        $snapshotUserIds = array_values(array_unique(array_filter(array_map(
            static fn (array $snapshot): int => (int) ($snapshot['user_id'] ?? 0),
            $snapshots
        ), static fn (int $userId): bool => $userId > 0)));

        if ($snapshotUserIds === []) {
            return [];
        }

        $accessibleUserIds = User::query()
            ->whereIn('id', $snapshotUserIds)
            ->whereHas('roles', function ($query) {
                $query->whereIn('name', RoleNames::aliases(RoleNames::SISWA));
            });
        RoleDataScope::applySiswaReadScope($accessibleUserIds, $actor);
        $allowedIds = $accessibleUserIds->pluck('id')->map(static fn ($id): int => (int) $id)->all();

        return array_values(array_filter($snapshots, static function (array $snapshot) use ($allowedIds): bool {
            return in_array((int) ($snapshot['user_id'] ?? 0), $allowedIds, true);
        }));
    }

    private function parseWorkTime(string $time): Carbon
    {
        $normalized = trim($time);
        if ($normalized === '') {
            $normalized = '07:00:00';
        }

        try {
            return Carbon::createFromFormat('H:i:s', $normalized);
        } catch (\Throwable $e) {
            return Carbon::createFromFormat('H:i', substr($normalized, 0, 5));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTrackingPolicy(User $user): array
    {
        $forceSession = $this->getForceTrackingSession((int) $user->id);
        $forceSessionActive = !empty($forceSession);
        $globallyEnabled = $this->isLiveTrackingGloballyEnabled();
        $now = Carbon::now();
        $workingHours = $this->attendanceTimeService->getWorkingHours($user);
        $isWorkingDay = $forceSessionActive || $this->attendanceTimeService->isWorkingDay($user, $now);
        $withinWorkingHours = $forceSessionActive || $this->isWithinWorkingHoursWindow($workingHours, $now);
        $windowOpen = $globallyEnabled && ($forceSessionActive || ($isWorkingDay && $withinWorkingHours));

        $reason = 'schedule_open';
        if (!$globallyEnabled) {
            $reason = 'globally_disabled';
        } elseif ($forceSessionActive) {
            $reason = 'force_session_active';
        } elseif (!$isWorkingDay) {
            $reason = 'outside_working_day';
        } elseif (!$withinWorkingHours) {
            $reason = 'outside_working_hours';
        }

        return [
            'enabled' => $globallyEnabled,
            'window_open' => $windowOpen,
            'is_working_day' => $isWorkingDay,
            'within_working_hours' => $withinWorkingHours,
            'force_session_active' => $forceSessionActive,
            'force_session_expires_at' => $forceSession['expires_at'] ?? null,
            'reason' => $reason,
            'jam_masuk' => (string) ($workingHours['jam_masuk'] ?? '07:00'),
            'jam_pulang' => (string) ($workingHours['jam_pulang'] ?? '15:00'),
            'hari_kerja' => is_array($workingHours['hari_kerja'] ?? null)
                ? $workingHours['hari_kerja']
                : ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'],
        ];
    }

    private function isLiveTrackingGloballyEnabled(): bool
    {
        $runtimeConfig = app(\App\Services\AttendanceRuntimeConfigService::class)->getLiveTrackingConfig();

        return (bool) ($runtimeConfig['enabled'] ?? true);
    }

    /**
     * @param array<string, mixed> $workingHours
     */
    private function isWithinWorkingHoursWindow(array $workingHours, Carbon $currentTime): bool
    {
        $jamMulai = $this->parseWorkTime((string) ($workingHours['jam_masuk'] ?? '07:00'));
        $jamSelesai = $this->parseWorkTime((string) ($workingHours['jam_pulang'] ?? '15:00'));

        if ($jamSelesai->lt($jamMulai)) {
            $jamSelesai = $jamMulai->copy()->addHours(8);
        }

        $currentClock = Carbon::createFromFormat('H:i:s', $currentTime->format('H:i:s'));

        return !$currentClock->lt($jamMulai) && !$currentClock->gt($jamSelesai);
    }

    private function serverTimeMeta(): array
    {
        $serverNow = now()->setTimezone(config('app.timezone'));

        return [
            'server_now' => $serverNow->toISOString(),
            'server_epoch_ms' => $serverNow->valueOf(),
            'server_date' => $serverNow->toDateString(),
            'timezone' => config('app.timezone'),
        ];
    }
}
