import 'dart:async';

import 'package:flutter/foundation.dart';

import '../services/attendance_service.dart';
import '../services/attendance_settings_service.dart';
import '../services/device_security_service.dart';
import '../services/location_service.dart';

enum AttendancePrecheckStage {
  idle,
  loadingSettings,
  checkingTime,
  gettingGps,
  checkingArea,
  ready,
  blocked,
  timeout,
  error,
}

class AttendancePrecheckSnapshot {
  final String actionType;
  final double? latitude;
  final double? longitude;
  final double? accuracy;
  final bool? isMocked;
  final int? locationId;
  final DateTime capturedAt;

  const AttendancePrecheckSnapshot({
    required this.actionType,
    required this.latitude,
    required this.longitude,
    required this.accuracy,
    required this.isMocked,
    required this.locationId,
    required this.capturedAt,
  });
}

class AttendanceSecurityWarningIssue {
  final String eventKey;
  final String label;
  final String message;
  final String severity;
  final String category;
  final Map<String, dynamic> metadata;

  const AttendanceSecurityWarningIssue({
    required this.eventKey,
    required this.label,
    required this.message,
    required this.severity,
    required this.category,
    this.metadata = const <String, dynamic>{},
  });

  Map<String, dynamic> toJson() {
    return <String, dynamic>{
      'event_key': eventKey,
      'label': label,
      'message': message,
      'severity': severity,
      'category': category,
      'metadata': metadata,
    };
  }
}

class AttendancePrecheckProvider extends ChangeNotifier {
  final AttendanceSettingsService _attendanceSettingsService =
      AttendanceSettingsService();
  final AttendanceService _attendanceService = AttendanceService();
  final DeviceSecurityService _deviceSecurityService = DeviceSecurityService();
  final LocationService _locationService = LocationService();

  AttendancePrecheckStage _stage = AttendancePrecheckStage.idle;
  int _requestId = 0;
  bool _isRefreshing = false;
  String _currentActionType = 'checkin';
  String _actionPrimaryMessage = 'Menyiapkan absensi...';
  String _actionSecondaryMessage =
      'Memuat jadwal, lokasi, dan persyaratan absensi';
  String? _failureType;
  double? _distanceToArea;
  double? _distanceToBoundary;
  double? _allowedRadius;
  int? _resolvedLocationId;
  String? _resolvedGeofenceType;
  double? _latitude;
  double? _longitude;
  List<AttendanceSecurityWarningIssue> _securityWarnings =
      const <AttendanceSecurityWarningIssue>[];
  String? _securityWarningFingerprint;
  AttendancePrecheckSnapshot? _submissionSnapshot;

  String _schema = 'Memuat skema...';
  String _schemaType = '';
  int? _schemaVersion;
  String _location = 'Memuat lokasi...';
  String _distance = 'Menunggu pemeriksaan...';
  String _jamMasuk = '--:--';
  String _jamPulang = '--:--';
  int _toleransi = 0;
  int _hariKerjaCount = 0;
  bool _wajibGps = true;
  bool _wajibFoto = true;
  bool _faceVerificationEnabled = true;
  int _gpsAccuracy = 20;
  double _gpsAccuracyGrace = 0.0;
  double? _currentGpsAccuracy;
  bool? _isAccuracyValid;
  bool? _canAttend;

  AttendancePrecheckStage get stage => _stage;
  bool get isRefreshing => _isRefreshing;
  bool get isActionLoading =>
      _stage == AttendancePrecheckStage.loadingSettings ||
      _stage == AttendancePrecheckStage.checkingTime ||
      _stage == AttendancePrecheckStage.gettingGps ||
      _stage == AttendancePrecheckStage.checkingArea;
  bool get isActionReady => _stage == AttendancePrecheckStage.ready;
  bool get hasActionBlocker =>
      _stage == AttendancePrecheckStage.blocked ||
      _stage == AttendancePrecheckStage.timeout ||
      _stage == AttendancePrecheckStage.error;
  String get currentActionType => _currentActionType;
  String get actionPrimaryMessage => _actionPrimaryMessage;
  String get actionSecondaryMessage => _actionSecondaryMessage;
  String? get failureType => _failureType;
  double? get distanceToArea => _distanceToArea;
  double? get distanceToBoundary => _distanceToBoundary;
  double? get allowedRadius => _allowedRadius;
  int? get resolvedLocationId => _resolvedLocationId;
  String? get resolvedGeofenceType => _resolvedGeofenceType;
  double? get latitude => _latitude;
  double? get longitude => _longitude;
  List<AttendanceSecurityWarningIssue> get securityWarnings =>
      List<AttendanceSecurityWarningIssue>.unmodifiable(_securityWarnings);
  bool get hasSecurityWarnings => _securityWarnings.isNotEmpty;
  String? get securityWarningFingerprint => _securityWarningFingerprint;
  AttendancePrecheckSnapshot? get submissionSnapshot => _submissionSnapshot;

  String get schema => _schema;
  String get schemaType => _schemaType;
  int? get schemaVersion => _schemaVersion;
  String get location => _location;
  String get distance => _distance;
  String get jamMasuk => _jamMasuk;
  String get jamPulang => _jamPulang;
  int get toleransi => _toleransi;
  int get hariKerjaCount => _hariKerjaCount;
  bool get wajibGps => _wajibGps;
  bool get wajibFoto => _wajibFoto;
  bool get faceVerificationEnabled => _faceVerificationEnabled;
  int get gpsAccuracy => _gpsAccuracy;
  double get gpsAccuracyGrace => _gpsAccuracyGrace;
  double? get currentGpsAccuracy => _currentGpsAccuracy;
  bool? get isAccuracyValid => _isAccuracyValid;
  bool? get canAttend => _canAttend;

  bool canReuseSnapshotForAction(String actionType) {
    return _submissionSnapshot != null &&
        _stage == AttendancePrecheckStage.ready &&
        _submissionSnapshot!.actionType == actionType;
  }

  Map<String, dynamic>? buildSecurityWarningPayload({
    required String trigger,
    bool acknowledged = true,
    bool includeConfirmedAt = false,
  }) {
    if (!hasSecurityWarnings) {
      return null;
    }

    final now = DateTime.now().toUtc().toIso8601String();
    return <String, dynamic>{
      'action_type': _currentActionType,
      'trigger': trigger,
      'acknowledged': acknowledged,
      'acknowledged_at': now,
      if (includeConfirmedAt) 'confirmed_at': now,
      'issues': _securityWarnings
          .map((issue) => issue.toJson())
          .toList(growable: false),
    };
  }

  Future<AttendancePrecheckSnapshot?> ensureReadyForAction({
    required int userId,
    required bool isCheckedIn,
  }) async {
    final actionType = isCheckedIn ? 'checkout' : 'checkin';
    if (canReuseSnapshotForAction(actionType)) {
      return _submissionSnapshot;
    }

    await refresh(userId: userId, isCheckedIn: isCheckedIn);
    if (canReuseSnapshotForAction(actionType)) {
      return _submissionSnapshot;
    }

    return null;
  }

  Future<void> refresh({
    required int userId,
    required bool isCheckedIn,
  }) async {
    final requestId = ++_requestId;
    _currentActionType = isCheckedIn ? 'checkout' : 'checkin';
    _submissionSnapshot = null;
    _isRefreshing = true;
    _failureType = null;
    _distanceToArea = null;
    _distanceToBoundary = null;
    _allowedRadius = null;
    _resolvedLocationId = null;
    _resolvedGeofenceType = null;
    _latitude = null;
    _longitude = null;
    _securityWarnings = const <AttendanceSecurityWarningIssue>[];
    _securityWarningFingerprint = null;
    _setStage(
      requestId,
      AttendancePrecheckStage.loadingSettings,
      primary: 'Menyiapkan absensi...',
      secondary: 'Memuat skema, lokasi, dan persyaratan aktif',
    );

    try {
      final attendanceInfo = await _attendanceSettingsService
          .getAttendanceInfo(userId)
          .timeout(const Duration(seconds: 12));

      if (!_isRequestActive(requestId)) return;

      if (!attendanceInfo.success || attendanceInfo.settings == null) {
        _setError(
          requestId,
          primary: 'Gagal memuat status absensi',
          secondary: attendanceInfo.message ??
              'Tidak dapat memuat pengaturan lokasi absensi',
        );
        return;
      }

      _applyAttendanceInfo(attendanceInfo);
      _setStage(
        requestId,
        AttendancePrecheckStage.checkingTime,
        primary: 'Memeriksa jadwal absensi...',
        secondary: 'Mencocokkan jadwal dengan data server',
      );

      final backendType = _currentActionType == 'checkin' ? 'masuk' : 'pulang';
      final timeValidation = await _attendanceService
          .validateAttendanceTime(type: backendType)
          .timeout(const Duration(seconds: 12));

      if (!_isRequestActive(requestId)) return;

      if (!timeValidation.success || timeValidation.data == null) {
        _setError(
          requestId,
          primary: 'Jadwal belum bisa diverifikasi',
          secondary: timeValidation.message.isNotEmpty
              ? timeValidation.message
              : 'Server belum merespons validasi jadwal',
        );
        return;
      }

      final validation = timeValidation.data!;
      await _collectSecurityWarnings(requestId);
      if (!_isRequestActive(requestId)) return;

      if (!validation.valid) {
        final window = validation.window?.displayWindow;
        _setBlocked(
          requestId,
          failureType: 'time',
          primary: validation.message,
          secondary: window == null || window.trim().isEmpty
              ? 'Jadwal absensi untuk saat ini belum dibuka'
              : 'Jendela absensi server: $window',
        );
        return;
      }

      if (!_wajibGps) {
        _distance = 'GPS tidak diwajibkan';
        _canAttend = true;
        _currentGpsAccuracy = null;
        _isAccuracyValid = true;
        _latitude = null;
        _longitude = null;
        _resolvedLocationId = null;
        _submissionSnapshot = AttendancePrecheckSnapshot(
          actionType: _currentActionType,
          latitude: null,
          longitude: null,
          accuracy: null,
          isMocked: null,
          locationId: null,
          capturedAt: DateTime.now(),
        );
        _setReady(
          requestId,
          primary: _currentActionType == 'checkin'
              ? 'Tap untuk absen masuk'
              : 'Tap untuk absen pulang',
          secondary: _buildReadySecondaryMessage(
            'Jadwal sudah valid. GPS tidak diwajibkan pada skema ini.',
          ),
        );
        return;
      }

      _setStage(
        requestId,
        AttendancePrecheckStage.gettingGps,
        primary: 'Mengambil lokasi Anda...',
        secondary: 'Aktifkan GPS dan tunggu akurasi membaik',
      );

      final locationResult = await _locationService
          .getCurrentLocation(includeAddress: false)
          .timeout(const Duration(seconds: 12));

      if (!_isRequestActive(requestId)) return;

      if (!locationResult.success ||
          locationResult.latitude == null ||
          locationResult.longitude == null) {
        final message = locationResult.message;
        if (_looksLikeTimeout(message)) {
          _setTimeout(
            requestId,
            primary: 'Lokasi belum berhasil didapatkan',
            secondary: 'Coba lagi setelah sinyal GPS lebih stabil',
          );
        } else {
          _distance = 'GPS belum tersedia';
          _currentGpsAccuracy = null;
          _isAccuracyValid = null;
          _canAttend = null;
          _setBlocked(
            requestId,
            failureType: 'system',
            primary: 'Lokasi belum tersedia',
            secondary: message.isNotEmpty
                ? message
                : 'Pastikan GPS aktif dan izin lokasi diberikan',
          );
        }
        return;
      }

      if (locationResult.isMocked == true) {
        _latitude = locationResult.latitude;
        _longitude = locationResult.longitude;
        _currentGpsAccuracy = locationResult.accuracy;
        _isAccuracyValid = _currentGpsAccuracy == null
            ? null
            : _currentGpsAccuracy! <= _allowedAccuracy;
        _distance = 'Mock location terdeteksi';
        _addSecurityWarning(_buildMockLocationWarning(locationResult));
      }

      _latitude = locationResult.latitude;
      _longitude = locationResult.longitude;
      _currentGpsAccuracy = locationResult.accuracy;
      _isAccuracyValid = _currentGpsAccuracy == null
          ? null
          : _currentGpsAccuracy! <= _allowedAccuracy;

      _setStage(
        requestId,
        AttendancePrecheckStage.checkingArea,
        primary: 'Memeriksa area absensi...',
        secondary: 'Menghitung jarak Anda ke titik absensi',
      );

      final distanceResult = await _attendanceSettingsService
          .checkDistanceToAttendanceLocation(
            locationResult.latitude!,
            locationResult.longitude!,
          )
          .timeout(const Duration(seconds: 12));

      if (!_isRequestActive(requestId)) return;

      if (distanceResult['success'] != true) {
        _setError(
          requestId,
          primary: 'Area absensi belum bisa diverifikasi',
          secondary: 'Server belum dapat memeriksa lokasi absensi',
        );
        return;
      }

      final matchingLocation = distanceResult['matching_location'] is Map
          ? Map<String, dynamic>.from(distanceResult['matching_location'])
          : null;
      final nearestLocation = distanceResult['nearest_location'] is Map
          ? Map<String, dynamic>.from(distanceResult['nearest_location'])
          : null;
      final targetLocation = matchingLocation ?? nearestLocation;

      final resolvedLocationName = _resolveLocationLabel(targetLocation);
      if (resolvedLocationName.isNotEmpty) {
        _location = resolvedLocationName;
      }

      _distanceToArea = _parseDouble(
        targetLocation?['distance'] ?? distanceResult['nearest_distance'],
      );
      _distanceToBoundary = _parseDouble(
        targetLocation?['distance_to_boundary'] ?? _distanceToArea,
      );
      _allowedRadius = _parseDouble(targetLocation?['radius']);
      _resolvedLocationId = _parseInt(targetLocation?['id']);
      _resolvedGeofenceType =
          (targetLocation?['geofence_type'] ?? 'circle').toString();
      _distance =
          (distanceResult['nearest_distance_formatted'] ?? '').toString().trim();
      if (_distance.isEmpty) {
        _distance = 'Jarak belum tersedia';
      }
      _canAttend = distanceResult['can_attend'] == true;

      if (_currentGpsAccuracy == null) {
        _setBlocked(
          requestId,
          failureType: 'accuracy',
          primary: 'Akurasi GPS belum terbaca',
          secondary: 'Aktifkan mode lokasi akurasi tinggi lalu coba lagi',
        );
        return;
      }

      if (_currentGpsAccuracy! > _allowedAccuracy) {
        _setBlocked(
          requestId,
          failureType: 'accuracy',
          primary:
              'Akurasi GPS ${_currentGpsAccuracy!.toStringAsFixed(1)}m belum memenuhi batas ${_allowedAccuracy.toStringAsFixed(1)}m',
          secondary: 'Tunggu sinyal GPS lebih stabil lalu coba lagi',
        );
        return;
      }

      if (_canAttend != true) {
        _setBlocked(
          requestId,
          failureType: 'location',
          primary: 'Anda masih di luar area absensi',
          secondary: _distanceToArea == null
              ? 'Silakan dekati titik absensi yang diizinkan'
              : 'Jarak ke area absensi terdekat ${_distanceToArea!.toStringAsFixed(0)}m',
        );
        return;
      }

      _submissionSnapshot = AttendancePrecheckSnapshot(
        actionType: _currentActionType,
        latitude: _latitude,
        longitude: _longitude,
        accuracy: _currentGpsAccuracy,
        isMocked: locationResult.isMocked,
        locationId: _resolvedLocationId,
        capturedAt: DateTime.now(),
      );

      _setReady(
        requestId,
        primary: _currentActionType == 'checkin'
            ? 'Tap untuk absen masuk'
            : 'Tap untuk absen pulang',
        secondary: _buildReadySecondaryMessage(
          'Jadwal dan lokasi sudah valid. Lanjutkan absensi.',
        ),
      );
    } on TimeoutException {
      _setTimeout(
        requestId,
        primary: 'Pra-cek absensi terlalu lama',
        secondary: 'Tarik layar untuk memuat ulang atau coba lagi sebentar',
      );
    } catch (_) {
      _setError(
        requestId,
        primary: 'Pra-cek absensi gagal',
        secondary: 'Terjadi kendala saat menyiapkan absensi. Coba lagi.',
      );
    } finally {
      if (_isRequestActive(requestId)) {
        _isRefreshing = false;
        notifyListeners();
      }
    }
  }

  void reset() {
    _requestId++;
    _stage = AttendancePrecheckStage.idle;
    _isRefreshing = false;
    _currentActionType = 'checkin';
    _actionPrimaryMessage = 'Menyiapkan absensi...';
    _actionSecondaryMessage =
        'Memuat jadwal, lokasi, dan persyaratan absensi';
    _failureType = null;
    _distanceToArea = null;
    _distanceToBoundary = null;
    _allowedRadius = null;
    _resolvedLocationId = null;
    _resolvedGeofenceType = null;
    _latitude = null;
    _longitude = null;
    _securityWarnings = const <AttendanceSecurityWarningIssue>[];
    _securityWarningFingerprint = null;
    _submissionSnapshot = null;
    _schema = 'Memuat skema...';
    _schemaType = '';
    _schemaVersion = null;
    _location = 'Memuat lokasi...';
    _distance = 'Menunggu pemeriksaan...';
    _jamMasuk = '--:--';
    _jamPulang = '--:--';
    _toleransi = 0;
    _hariKerjaCount = 0;
    _wajibGps = true;
    _wajibFoto = true;
    _faceVerificationEnabled = true;
    _gpsAccuracy = 20;
    _gpsAccuracyGrace = 0.0;
    _currentGpsAccuracy = null;
    _isAccuracyValid = null;
    _canAttend = null;
    notifyListeners();
  }

  bool _isRequestActive(int requestId) => requestId == _requestId;

  double get _allowedAccuracy => _gpsAccuracy.toDouble() + _gpsAccuracyGrace;

  void _applyAttendanceInfo(AttendanceInfoResponse attendanceInfo) {
    _schema = attendanceInfo.schema ?? 'Manual';
    _schemaType = attendanceInfo.schemaType ?? '';
    _schemaVersion = attendanceInfo.schemaVersion;
    _location = attendanceInfo.location ?? 'Lokasi belum tersedia';
    _distance = attendanceInfo.distance ?? 'Menunggu pemeriksaan...';
    _jamMasuk = attendanceInfo.settings?.jamMasuk ?? '--:--';
    _jamPulang = attendanceInfo.settings?.jamPulang ?? '--:--';
    _toleransi = attendanceInfo.settings?.toleransi ?? 0;
    _hariKerjaCount = attendanceInfo.settings?.hariKerja.length ?? 0;
    _wajibGps = attendanceInfo.settings?.requireGPS ?? true;
    _wajibFoto = attendanceInfo.settings?.requireSelfie ?? true;
    _faceVerificationEnabled =
        attendanceInfo.settings?.faceVerificationEnabled ?? true;
    _gpsAccuracy = attendanceInfo.settings?.gpsAccuracy ?? 20;
    _gpsAccuracyGrace = attendanceInfo.settings?.gpsAccuracyGrace ?? 0.0;
    _currentGpsAccuracy = null;
    _isAccuracyValid = null;
    _canAttend = null;

    if (!_wajibGps) {
      _location = 'Lokasi fleksibel';
      _distance = 'GPS tidak diwajibkan';
      _canAttend = true;
      _isAccuracyValid = true;
    }

    notifyListeners();
  }

  Future<void> _collectSecurityWarnings(int requestId) async {
    try {
      final signals = await _deviceSecurityService.collectSignals();
      if (!_isRequestActive(requestId)) return;

      _securityWarnings = _buildSecurityWarningsFromSignals(signals);
      _recomputeSecurityWarningFingerprint();
    } catch (_) {
      if (!_isRequestActive(requestId)) return;
      _securityWarnings = const <AttendanceSecurityWarningIssue>[];
      _securityWarningFingerprint = null;
    }
  }

  List<AttendanceSecurityWarningIssue> _buildSecurityWarningsFromSignals(
    Map<String, dynamic> signals,
  ) {
    final warnings = <AttendanceSecurityWarningIssue>[];

    void addIf(
      bool condition,
      AttendanceSecurityWarningIssue issue,
    ) {
      if (!condition) return;
      warnings.add(issue);
    }

    final adbEnabled = _isFlagEnabled(signals['adb_enabled']) ||
        _isFlagEnabled(signals['usb_debugging_enabled']);
    final emulatorDetected = _isFlagEnabled(signals['emulator_detected']) ||
        signals['is_physical_device'] == false;
    final instrumentationDetected =
        _isFlagEnabled(signals['instrumentation_detected']) ||
            _isFlagEnabled(signals['frida_detected']) ||
            _isFlagEnabled(signals['xposed_detected']) ||
            _isFlagEnabled(signals['hooking_detected']);

    addIf(
      _isFlagEnabled(signals['developer_options_enabled']),
      const AttendanceSecurityWarningIssue(
        eventKey: 'developer_options_enabled',
        label: 'Developer options aktif',
        message:
            'Developer options masih aktif pada perangkat saat proses absensi.',
        severity: 'medium',
        category: 'device_integrity',
      ),
    );
    addIf(
      _isFlagEnabled(signals['root_detected']),
      const AttendanceSecurityWarningIssue(
        eventKey: 'root_or_jailbreak_detected',
        label: 'Root / jailbreak terdeteksi',
        message: 'Perangkat terindikasi root atau jailbreak.',
        severity: 'high',
        category: 'device_integrity',
      ),
    );
    addIf(
      adbEnabled,
      const AttendanceSecurityWarningIssue(
        eventKey: 'adb_or_usb_debugging_enabled',
        label: 'ADB / USB debugging aktif',
        message: 'ADB atau USB debugging masih aktif pada perangkat.',
        severity: 'medium',
        category: 'device_integrity',
      ),
    );
    addIf(
      emulatorDetected,
      const AttendanceSecurityWarningIssue(
        eventKey: 'emulator_detected',
        label: 'Perangkat terindikasi emulator',
        message: 'Aplikasi terdeteksi berjalan pada emulator atau non-physical device.',
        severity: 'high',
        category: 'device_integrity',
      ),
    );
    addIf(
      _isFlagEnabled(signals['app_clone_risk']),
      const AttendanceSecurityWarningIssue(
        eventKey: 'app_clone_detected',
        label: 'Clone / dual app terdeteksi',
        message: 'Aplikasi terindikasi berjalan dalam mode clone atau dual app.',
        severity: 'high',
        category: 'app_integrity',
      ),
    );
    addIf(
      instrumentationDetected,
      const AttendanceSecurityWarningIssue(
        eventKey: 'instrumentation_detected',
        label: 'Instrumentation / hooking terdeteksi',
        message: 'Frida, Xposed, atau hooking framework terdeteksi pada perangkat.',
        severity: 'high',
        category: 'app_integrity',
      ),
    );
    addIf(
      _isFlagEnabled(signals['magisk_risk']),
      const AttendanceSecurityWarningIssue(
        eventKey: 'magisk_risk_detected',
        label: 'Risiko Magisk terdeteksi',
        message:
            'Perangkat terindikasi memiliki komponen Magisk atau modul sejenis.',
        severity: 'high',
        category: 'device_integrity',
      ),
    );
    addIf(
      _isFlagEnabled(signals['suspicious_device_state']),
      const AttendanceSecurityWarningIssue(
        eventKey: 'suspicious_device_state_detected',
        label: 'Status perangkat mencurigakan',
        message:
            'Perangkat berada pada kondisi keamanan yang tidak normal dan perlu klarifikasi.',
        severity: 'medium',
        category: 'device_integrity',
      ),
    );

    if (!kDebugMode && _isFlagEnabled(signals['is_debuggable_build'])) {
      warnings.add(
        const AttendanceSecurityWarningIssue(
          eventKey: 'app_tampering_detected',
          label: 'Integritas aplikasi bermasalah',
          message:
              'Aplikasi terindikasi termodifikasi atau gagal verifikasi integritas.',
          severity: 'high',
          category: 'app_integrity',
        ),
      );
    }

    final deduplicated = <String, AttendanceSecurityWarningIssue>{};
    for (final issue in warnings) {
      deduplicated[issue.eventKey] = issue;
    }

    return deduplicated.values.toList(growable: false);
  }

  AttendanceSecurityWarningIssue _buildMockLocationWarning(
    dynamic locationResult,
  ) {
    return AttendanceSecurityWarningIssue(
      eventKey: 'mock_location_detected',
      label: 'Mock location / Fake GPS',
      message: 'Perangkat terdeteksi menggunakan mock location atau Fake GPS.',
      severity: 'high',
      category: 'gps_integrity',
      metadata: <String, dynamic>{
        'latitude': locationResult.latitude,
        'longitude': locationResult.longitude,
        'accuracy': locationResult.accuracy,
      },
    );
  }

  void _addSecurityWarning(AttendanceSecurityWarningIssue issue) {
    final map = <String, AttendanceSecurityWarningIssue>{
      for (final existing in _securityWarnings) existing.eventKey: existing,
    };
    map[issue.eventKey] = issue;
    _securityWarnings = map.values.toList(growable: false);
    _recomputeSecurityWarningFingerprint();
  }

  void _recomputeSecurityWarningFingerprint() {
    if (_securityWarnings.isEmpty) {
      _securityWarningFingerprint = null;
      return;
    }

    final keys = _securityWarnings
        .map((issue) => issue.eventKey)
        .toList(growable: false)
      ..sort();
    _securityWarningFingerprint = '${_currentActionType}|${keys.join('|')}';
  }

  String _buildReadySecondaryMessage(String fallback) {
    if (_securityWarnings.isEmpty) {
      return fallback;
    }

    final count = _securityWarnings.length;
    return '$fallback Terdapat $count warning keamanan yang tetap dicatat untuk monitoring.';
  }

  void _setStage(
    int requestId,
    AttendancePrecheckStage stage, {
    required String primary,
    required String secondary,
  }) {
    if (!_isRequestActive(requestId)) return;
    _stage = stage;
    _actionPrimaryMessage = primary;
    _actionSecondaryMessage = secondary;
    notifyListeners();
  }

  void _setBlocked(
    int requestId, {
    required String failureType,
    required String primary,
    required String secondary,
  }) {
    if (!_isRequestActive(requestId)) return;
    _failureType = failureType;
    _setStage(
      requestId,
      AttendancePrecheckStage.blocked,
      primary: primary,
      secondary: secondary,
    );
  }

  void _setTimeout(
    int requestId, {
    required String primary,
    required String secondary,
  }) {
    if (!_isRequestActive(requestId)) return;
    _failureType = 'timeout';
    _setStage(
      requestId,
      AttendancePrecheckStage.timeout,
      primary: primary,
      secondary: secondary,
    );
  }

  void _setError(
    int requestId, {
    required String primary,
    required String secondary,
  }) {
    if (!_isRequestActive(requestId)) return;
    _failureType = 'system';
    _setStage(
      requestId,
      AttendancePrecheckStage.error,
      primary: primary,
      secondary: secondary,
    );
  }

  void _setReady(
    int requestId, {
    required String primary,
    required String secondary,
  }) {
    if (!_isRequestActive(requestId)) return;
    _failureType = null;
    _setStage(
      requestId,
      AttendancePrecheckStage.ready,
      primary: primary,
      secondary: secondary,
    );
  }

  bool _looksLikeTimeout(String? value) {
    final message = (value ?? '').toLowerCase();
    return message.contains('timeout') || message.contains('time out');
  }

  String _resolveLocationLabel(Map<String, dynamic>? location) {
    if (location == null) {
      return _location;
    }

    final fromLocation = (location['nama_lokasi'] ?? '').toString().trim();
    if (fromLocation.isNotEmpty) {
      return fromLocation;
    }

    return _location;
  }

  double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value);
    return null;
  }

  int? _parseInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is String) return int.tryParse(value);
    return null;
  }

  bool _isFlagEnabled(dynamic value) {
    if (value is bool) return value;
    if (value is int) return value == 1;
    if (value is String) {
      final normalized = value.trim().toLowerCase();
      return normalized == 'true' || normalized == '1';
    }
    return false;
  }
}
