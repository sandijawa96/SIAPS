import 'dart:async';
import 'package:flutter/foundation.dart';
import 'api_service.dart';
import 'attendance_settings_service.dart';
import 'location_service.dart';

typedef LiveTrackingNotificationUpdater = Future<void> Function(
  String title,
  String content,
);

class LiveTrackingService {
  static final LiveTrackingService _instance = LiveTrackingService._internal();
  factory LiveTrackingService() => _instance;
  LiveTrackingService._internal();

  static const Duration _movingRealtimeInterval = Duration(seconds: 30);
  static const Duration _stationaryRealtimeInterval = Duration(minutes: 1);
  static const Duration _streamRecoveryDelay = Duration(seconds: 5);
  static const Duration _maxStreamRecoveryDelay = Duration(seconds: 60);
  static const Duration _settingsRefreshInterval = Duration(minutes: 10);
  static const Duration _pausedWindowRecheckInterval = Duration(minutes: 1);
  static const double _movementSpeedThresholdMps = 1.0;
  static const double _stationaryDistanceThresholdMeters = 10.0;
  static const String _trackingStateOnline = 'online';
  static const String _trackingStateGpsDisabled = 'gps_disabled';

  final ApiService _apiService = ApiService();
  final LocationService _locationService = LocationService();
  final AttendanceSettingsService _attendanceSettingsService =
      AttendanceSettingsService();

  StreamSubscription<LocationResult>? _trackingLocationSubscription;
  Timer? _streamRecoveryTimer;
  Timer? _trackingWindowTimer;
  bool _isTracking = false;
  bool _isSending = false;
  bool _trackingWindowPaused = false;
  int? _activeUserId;
  String? _deviceSessionId;
  DateTime? _lastSettingsFetchedAt;
  DateTime? _lastSentAt;
  double? _lastSentLatitude;
  double? _lastSentLongitude;
  AttendanceSettings? _cachedSettings;
  Map<String, dynamic>? _cachedTrackingPolicy;
  LiveTrackingNotificationUpdater? _notificationUpdater;
  String? _lastStreamFailureCode;
  String? _lastReportedTrackingState;
  String? _lastNotificationTitle;
  String? _lastNotificationContent;
  Duration _currentRecoveryDelay = _streamRecoveryDelay;

  bool get isTracking => _isTracking;

  void bindNotificationUpdater(LiveTrackingNotificationUpdater? updater) {
    _notificationUpdater = updater;
  }

  Future<void> startTracking({required int userId}) async {
    if (_isTracking && _activeUserId == userId) {
      return;
    }

    stopTracking();
    _isTracking = true;
    _activeUserId = userId;
    _deviceSessionId = 'mobile-${DateTime.now().millisecondsSinceEpoch}-$userId';
    await _updateNotificationStatus(
      contentOverride: 'Menyiapkan pemantauan kehadiran',
    );

    await _refreshSettingsIfNeeded(force: true);
    final canTrackNow = await _isTrackingWindowOpen(now: DateTime.now());
    if (!canTrackNow) {
      await _enterPausedTrackingWindow();
      return;
    }

    await _sendTrackingUpdate(force: true);
    await _startLocationStream();
  }

  void stopTracking() {
    unawaited(_trackingLocationSubscription?.cancel() ?? Future<void>.value());
    _trackingLocationSubscription = null;
    _streamRecoveryTimer?.cancel();
    _streamRecoveryTimer = null;
    _trackingWindowTimer?.cancel();
    _trackingWindowTimer = null;
    _isTracking = false;
    _isSending = false;
    _trackingWindowPaused = false;
    _activeUserId = null;
    _deviceSessionId = null;
    _lastSettingsFetchedAt = null;
    _lastSentAt = null;
    _lastSentLatitude = null;
    _lastSentLongitude = null;
    _cachedSettings = null;
    _cachedTrackingPolicy = null;
    _lastStreamFailureCode = null;
    _lastReportedTrackingState = null;
    _lastNotificationTitle = null;
    _lastNotificationContent = null;
    _currentRecoveryDelay = _streamRecoveryDelay;
  }

  Future<void> _startLocationStream() async {
    if (!_isTracking || _activeUserId == null) {
      return;
    }

    _trackingWindowTimer?.cancel();
    _trackingWindowTimer = null;
    _trackingWindowPaused = false;
    await _updateNotificationStatus();
    await _trackingLocationSubscription?.cancel();
    _trackingLocationSubscription = _locationService
        .getTrackingLocationStream(
          interval: _movingRealtimeInterval,
        )
        .listen(
          (locationResult) {
            unawaited(_handleLocationUpdate(locationResult));
          },
          onError: (Object error, StackTrace stackTrace) {
            debugPrint('Live tracking stream error: $error');
            _scheduleStreamRecovery();
          },
          onDone: _scheduleStreamRecovery,
          cancelOnError: false,
        );
  }

  void _scheduleStreamRecovery() {
    if (!_isTracking || _activeUserId == null || _trackingWindowPaused) {
      return;
    }

    final recoveryDelay = _currentRecoveryDelay;
    _streamRecoveryTimer?.cancel();
    _streamRecoveryTimer = Timer(recoveryDelay, () {
      if (!_isTracking || _activeUserId == null) {
        return;
      }

      unawaited(_startLocationStream());
    });
    _currentRecoveryDelay = _resolveNextRecoveryDelay(recoveryDelay);
  }

  Future<void> _sendTrackingUpdate({bool force = false}) async {
    final locationResult =
        await _locationService.getCurrentLocation(includeAddress: false);
    await _handleLocationUpdate(locationResult, force: force);
  }

  Future<void> _handleLocationUpdate(
    LocationResult locationResult, {
    bool force = false,
  }) async {
    if (!_isTracking || _isSending || _activeUserId == null) {
      return;
    }

    final now = DateTime.now();
    final canTrackNow = await _isTrackingWindowOpen(now: now);
    if (!canTrackNow) {
      await _enterPausedTrackingWindow();
      return;
    }

    if (!locationResult.success ||
        locationResult.latitude == null ||
        locationResult.longitude == null) {
      _lastStreamFailureCode = locationResult.failureCode;
      unawaited(_reportTrackingStateForFailure(locationResult));
      if (locationResult.message.isNotEmpty) {
        debugPrint('Live tracking skipped: ${locationResult.message}');
      }
      return;
    }

    _lastStreamFailureCode = null;
    _currentRecoveryDelay = _streamRecoveryDelay;
    final minimumSendInterval = _resolveRealtimeSendInterval(
      locationResult,
      force: force,
    );
    if (!force &&
        _lastSentAt != null &&
        now.difference(_lastSentAt!) < minimumSendInterval) {
      return;
    }

    _isSending = true;
    try {
      final payload = <String, dynamic>{
        'latitude': locationResult.latitude,
        'longitude': locationResult.longitude,
        if (locationResult.accuracy != null)
          'accuracy': locationResult.accuracy,
        if (locationResult.speed != null) 'speed': locationResult.speed,
        if (locationResult.heading != null) 'heading': locationResult.heading,
        'device_source': 'mobile',
        if (_deviceSessionId != null) 'device_session_id': _deviceSessionId,
        'platform': defaultTargetPlatform.name,
        'app_version': 'mobileapp',
      };

      final sent = await _sendRealtimeUpdate(payload);
      if (sent) {
        _lastSentAt = now;
        _lastSentLatitude = locationResult.latitude;
        _lastSentLongitude = locationResult.longitude;
        if (_lastReportedTrackingState == _trackingStateGpsDisabled) {
          debugPrint('Live tracking recovered: online');
        }
        _lastReportedTrackingState = _trackingStateOnline;
        await _updateNotificationStatus();
      }
    } catch (e) {
      debugPrint('Live tracking update error: $e');
    } finally {
      _isSending = false;
    }
  }

  Future<bool> _sendRealtimeUpdate(Map<String, dynamic> payload) async {
    try {
      await _apiService.post('/lokasi-gps/update-location', data: payload);
      return true;
    } on ApiException catch (e) {
      // 403/422 bukan fatal untuk loop; backend policy menentukan akses realtime.
      if (e.statusCode != 403 && e.statusCode != 422) {
        debugPrint('Realtime tracking update failed: ${e.message}');
      } else {
        await _refreshSettingsIfNeeded(force: true);
        await _updateNotificationStatus();
      }
      return false;
    } catch (e) {
      debugPrint('Realtime tracking update error: $e');
      return false;
    }
  }

  Future<void> _reportTrackingStateForFailure(LocationResult locationResult) async {
    if (!_isTracking || _activeUserId == null || _trackingWindowPaused) {
      return;
    }

    final trackingState = switch (locationResult.failureCode) {
      LocationService.failureCodeLocationServiceDisabled =>
        _trackingStateGpsDisabled,
      _ => null,
    };

    if (trackingState == null || trackingState == _lastReportedTrackingState) {
      return;
    }

    final payload = <String, dynamic>{
      'state': trackingState,
      'device_source': 'mobile',
      if (_deviceSessionId != null) 'device_session_id': _deviceSessionId,
      'platform': defaultTargetPlatform.name,
      'app_version': 'mobileapp',
    };

    try {
      await _apiService.post('/lokasi-gps/update-tracking-state', data: payload);
      debugPrint('Live tracking state reported: $trackingState');
      _lastReportedTrackingState = trackingState;
      await _updateNotificationStatus();
    } on ApiException catch (e) {
      if (e.statusCode != 403 && e.statusCode != 422) {
        debugPrint('Realtime tracking state update failed: ${e.message}');
      } else {
        await _refreshSettingsIfNeeded(force: true);
        await _updateNotificationStatus();
      }
    } catch (e) {
      debugPrint('Realtime tracking state update error: $e');
    }
  }

  Duration _resolveNextRecoveryDelay(Duration currentDelay) {
    if (_lastStreamFailureCode !=
        LocationService.failureCodeLocationServiceDisabled) {
      return _streamRecoveryDelay;
    }

    final doubledSeconds = currentDelay.inSeconds * 2;
    final nextSeconds = doubledSeconds.clamp(
      _streamRecoveryDelay.inSeconds,
      _maxStreamRecoveryDelay.inSeconds,
    );

    return Duration(seconds: nextSeconds);
  }

  Duration _resolveRealtimeSendInterval(
    LocationResult locationResult, {
    required bool force,
  }) {
    if (force || _cachedTrackingPolicy?['force_session_active'] == true) {
      return _movingRealtimeInterval;
    }

    final speed = locationResult.speed;
    if (speed != null && speed.isFinite && speed >= _movementSpeedThresholdMps) {
      return _movingRealtimeInterval;
    }

    final distanceSinceLastSent = _calculateDistanceSinceLastSent(locationResult);
    if (distanceSinceLastSent == null ||
        distanceSinceLastSent >= _stationaryDistanceThresholdMeters) {
      return _movingRealtimeInterval;
    }

    return _stationaryRealtimeInterval;
  }

  double? _calculateDistanceSinceLastSent(LocationResult locationResult) {
    if (_lastSentLatitude == null ||
        _lastSentLongitude == null ||
        locationResult.latitude == null ||
        locationResult.longitude == null) {
      return null;
    }

    return _locationService.calculateDistance(
      lat1: _lastSentLatitude!,
      lon1: _lastSentLongitude!,
      lat2: locationResult.latitude!,
      lon2: locationResult.longitude!,
    );
  }

  Future<bool> _isTrackingWindowOpen({
    DateTime? now,
    bool forceRefresh = false,
  }) async {
    final currentTime = now ?? DateTime.now();
    await _refreshSettingsIfNeeded(force: forceRefresh);

    final policy = _cachedTrackingPolicy;
    if (policy?['force_session_active'] == true) {
      return true;
    }

    if (policy?['window_open'] is bool) {
      return policy?['window_open'] == true;
    }

    if (_cachedSettings == null) {
      // Jika settings tidak terbaca, backend tetap jadi source of truth policy.
      return true;
    }

    return _isWithinWorkingWindow(currentTime, _cachedSettings!);
  }

  Future<void> _refreshSettingsIfNeeded({bool force = false}) async {
    final userId = _activeUserId;
    if (userId == null) {
      return;
    }

    final now = DateTime.now();
    if (!force &&
        _cachedSettings != null &&
        _lastSettingsFetchedAt != null &&
        now.difference(_lastSettingsFetchedAt!) < _settingsRefreshInterval) {
      return;
    }

    try {
      final response =
          await _attendanceSettingsService.getAttendanceInfo(userId);
      if (response.success) {
        _cachedTrackingPolicy = response.trackingPolicy;
        await _updateNotificationStatus();
      }

      if (response.success && response.settings != null) {
        _cachedSettings = response.settings;
        _lastSettingsFetchedAt = now;
      }
    } catch (e) {
      debugPrint('Failed to refresh tracking settings: $e');
    }
  }

  Future<void> _enterPausedTrackingWindow() async {
    if (!_isTracking || _activeUserId == null) {
      return;
    }

    _streamRecoveryTimer?.cancel();
    _streamRecoveryTimer = null;
    _trackingWindowTimer?.cancel();
    _trackingWindowTimer = null;
    _currentRecoveryDelay = _streamRecoveryDelay;

    await _trackingLocationSubscription?.cancel();
    _trackingLocationSubscription = null;

    _trackingWindowPaused = true;
    _isSending = false;
    await _updateNotificationStatus();
    _scheduleTrackingWindowRecheck();
  }

  void _scheduleTrackingWindowRecheck() {
    if (!_isTracking || _activeUserId == null) {
      return;
    }

    _trackingWindowTimer?.cancel();
    _trackingWindowTimer = Timer(_pausedWindowRecheckInterval, () {
      unawaited(_resumeTrackingWindowIfOpen());
    });
  }

  Future<void> _resumeTrackingWindowIfOpen() async {
    if (!_isTracking || _activeUserId == null) {
      return;
    }

    final canTrackNow = await _isTrackingWindowOpen(
      now: DateTime.now(),
      forceRefresh: true,
    );

    if (!canTrackNow) {
      _scheduleTrackingWindowRecheck();
      return;
    }

    _trackingWindowPaused = false;
    _trackingWindowTimer?.cancel();
    _trackingWindowTimer = null;

    await _sendTrackingUpdate(force: true);
    await _startLocationStream();
  }

  Future<void> _updateNotificationStatus({
    String? contentOverride,
  }) async {
    final updater = _notificationUpdater;
    if (updater == null) {
      return;
    }

    const title = 'SIAP Absensi';
    final content = contentOverride ?? _resolveNotificationContent();
    if (_lastNotificationTitle == title && _lastNotificationContent == content) {
      return;
    }

    _lastNotificationTitle = title;
    _lastNotificationContent = content;

    try {
      await updater(title, content);
    } catch (e) {
      debugPrint('Failed to update tracking notification: $e');
    }
  }

  String _resolveNotificationContent() {
    final policy = _cachedTrackingPolicy;
    final enabled = policy?['enabled'] != false;
    final windowOpen = policy?['window_open'] == true;
    final forceSessionActive = policy?['force_session_active'] == true;
    final reason = policy?['reason']?.toString();

    if (!enabled || reason == 'globally_disabled') {
      return 'Pemantauan kehadiran dinonaktifkan admin';
    }

    if (_lastReportedTrackingState == _trackingStateGpsDisabled ||
        _lastStreamFailureCode ==
            LocationService.failureCodeLocationServiceDisabled) {
      return 'GPS perangkat nonaktif';
    }

    if (forceSessionActive) {
      return 'Pemantauan kehadiran aktif';
    }

    if (!windowOpen &&
        (reason == 'outside_working_day' ||
            reason == 'outside_working_hours' ||
            _trackingWindowPaused)) {
      return 'Pemantauan kehadiran standby di luar jadwal';
    }

    if (_trackingWindowPaused) {
      return 'Pemantauan kehadiran standby';
    }

    if (_isTracking) {
      return 'Pemantauan kehadiran aktif';
    }

    return 'Pemantauan kehadiran standby';
  }

  bool _isWithinWorkingWindow(DateTime now, AttendanceSettings settings) {
    if (!_isWorkingDay(now, settings.hariKerja)) {
      return false;
    }

    final startMinute = _parseMinuteOfDay(settings.jamMasuk);
    final endMinute = _parseMinuteOfDay(settings.jamPulang);
    if (startMinute == null || endMinute == null) {
      return true;
    }

    final currentMinute = now.hour * 60 + now.minute;
    if (endMinute < startMinute) {
      return currentMinute >= startMinute || currentMinute <= endMinute;
    }

    return currentMinute >= startMinute && currentMinute <= endMinute;
  }

  bool _isWorkingDay(DateTime now, List<String> hariKerja) {
    if (hariKerja.isEmpty) {
      return true;
    }

    final normalizedDays = hariKerja
        .map((day) => day.toLowerCase().trim())
        .where((day) => day.isNotEmpty)
        .toSet();

    final dayAliases = _dayAliases(now.weekday);
    return dayAliases.any(normalizedDays.contains);
  }

  List<String> _dayAliases(int weekday) {
    switch (weekday) {
      case DateTime.monday:
        return ['senin', 'monday'];
      case DateTime.tuesday:
        return ['selasa', 'tuesday'];
      case DateTime.wednesday:
        return ['rabu', 'wednesday'];
      case DateTime.thursday:
        return ['kamis', 'thursday'];
      case DateTime.friday:
        return ['jumat', "jum'at", 'friday'];
      case DateTime.saturday:
        return ['sabtu', 'saturday'];
      case DateTime.sunday:
        return ['minggu', 'sunday'];
      default:
        return const <String>[];
    }
  }

  int? _parseMinuteOfDay(String value) {
    final raw = value.trim();
    final match = RegExp(r'^(\d{1,2}):(\d{2})').firstMatch(raw);
    if (match == null) {
      return null;
    }

    final hour = int.tryParse(match.group(1)!);
    final minute = int.tryParse(match.group(2)!);
    if (hour == null ||
        minute == null ||
        hour < 0 ||
        hour > 23 ||
        minute < 0 ||
        minute > 59) {
      return null;
    }

    return hour * 60 + minute;
  }
}
