import '../models/user.dart';
import '../models/login_response.dart';
import '../middleware/device_binding_middleware.dart';
import '../utils/constants.dart';
import 'api_service.dart';
import 'dart:convert';
import 'dart:io';
import 'dart:math';
import 'package:flutter/foundation.dart';
import 'package:dio/dio.dart';

class AttendanceGlobalPolicy {
  final String verificationMode;
  final String attendanceScope;
  final List<int> targetTingkatIds;
  final List<int> targetKelasIds;
  final bool faceVerificationEnabled;
  final bool faceTemplateRequired;

  const AttendanceGlobalPolicy({
    required this.verificationMode,
    required this.attendanceScope,
    required this.targetTingkatIds,
    required this.targetKelasIds,
    required this.faceVerificationEnabled,
    required this.faceTemplateRequired,
  });

  factory AttendanceGlobalPolicy.fromJson(Map<String, dynamic>? json) {
    final source = json ?? const <String, dynamic>{};

    bool parseBool(dynamic value) {
      if (value is bool) return value;
      if (value is int) return value == 1;
      if (value is String) {
        final normalized = value.toLowerCase().trim();
        return normalized == 'true' || normalized == '1';
      }
      return true;
    }

    return AttendanceGlobalPolicy(
      verificationMode:
          (source['verification_mode'] ?? 'async_pending').toString(),
      attendanceScope: (source['attendance_scope'] ?? 'siswa_only').toString(),
      targetTingkatIds: _parseIntList(source['target_tingkat_ids']),
      targetKelasIds: _parseIntList(source['target_kelas_ids']),
      faceVerificationEnabled: parseBool(source['face_verification_enabled']),
      faceTemplateRequired: parseBool(source['face_template_required']),
    );
  }

  bool get isSiswaOnly => attendanceScope == 'siswa_only';

  bool allowsUser(User? user) {
    if (!isSiswaOnly) {
      return true;
    }
    if (user == null) {
      return false;
    }
    if (user.isSiswa) {
      return true;
    }

    return user.roles.any((role) {
      final normalized = role.name.toLowerCase();
      return normalized == 'siswa' || normalized.contains('siswa');
    });
  }

  static List<int> _parseIntList(dynamic raw) {
    if (raw is String) {
      try {
        final decoded = jsonDecode(raw);
        return _parseIntList(decoded);
      } catch (_) {
        return const <int>[];
      }
    }

    if (raw is! List) {
      return const <int>[];
    }

    return raw
        .map((item) {
          if (item is int) {
            return item;
          }
          if (item is String) {
            return int.tryParse(item);
          }
          return null;
        })
        .whereType<int>()
        .toList();
  }
}

class AttendanceService {
  static final AttendanceService _instance = AttendanceService._internal();
  factory AttendanceService() => _instance;
  AttendanceService._internal();

  final ApiService _apiService = ApiService();
  static const Duration _settingsCacheTtl = Duration(seconds: 5);
  ApiResponse<WorkingHours>? _workingHoursCache;
  DateTime? _workingHoursFetchedAt;
  Future<ApiResponse<WorkingHours>>? _workingHoursInFlight;
  final Map<String, ApiResponse<AttendanceGlobalPolicy>> _policyCache =
      <String, ApiResponse<AttendanceGlobalPolicy>>{};
  final Map<String, DateTime> _policyFetchedAt = <String, DateTime>{};
  final Map<String, Future<ApiResponse<AttendanceGlobalPolicy>>>
      _policyInFlight =
      <String, Future<ApiResponse<AttendanceGlobalPolicy>>>{};

  // Get user working hours
  Future<ApiResponse<WorkingHours>> getWorkingHours({
    bool forceRefresh = false,
  }) async {
    final now = DateTime.now();
    if (!forceRefresh &&
        _workingHoursCache != null &&
        _workingHoursFetchedAt != null &&
        now.difference(_workingHoursFetchedAt!) < _settingsCacheTtl) {
      return _workingHoursCache!;
    }

    if (!forceRefresh && _workingHoursInFlight != null) {
      return _workingHoursInFlight!;
    }

    final future = _fetchWorkingHours();
    _workingHoursInFlight = future;

    try {
      final result = await future;
      if (result.success) {
        _workingHoursCache = result;
        _workingHoursFetchedAt = DateTime.now();
      }

      return result;
    } finally {
      if (identical(_workingHoursInFlight, future)) {
        _workingHoursInFlight = null;
      }
    }
  }

  Future<ApiResponse<WorkingHours>> _fetchWorkingHours() async {
    try {
      final response = await _apiService.get('/simple-attendance/working-hours');

      if (response.data['status'] == 'success' &&
          response.data['data'] != null) {
        final workingHours = WorkingHours.fromJson(response.data['data']);
        return ApiResponse<WorkingHours>(
          success: true,
          message: 'Jam kerja berhasil diambil',
          data: workingHours,
        );
      }

      return ApiResponse<WorkingHours>(
        success: false,
        message: response.data['message'] ?? 'Gagal mengambil jam kerja',
      );
    } on ApiException catch (e) {
      return ApiResponse<WorkingHours>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<WorkingHours>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  // Get global attendance settings
  Future<ApiResponse<Map<String, dynamic>>> getGlobalSettings() async {
    try {
      final response = await _apiService.get('/simple-attendance/global');

      if (response.data['status'] == 'success' &&
          response.data['data'] != null) {
        return ApiResponse<Map<String, dynamic>>(
          success: true,
          message: 'Pengaturan global berhasil diambil',
          data: Map<String, dynamic>.from(response.data['data']),
        );
      } else {
        return ApiResponse<Map<String, dynamic>>(
          success: false,
          message:
              response.data['message'] ?? 'Gagal mengambil pengaturan global',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<AttendanceGlobalPolicy>> getAttendancePolicy(
    int? userId, {
    bool forceRefresh = false,
  }) async {
    final cacheKey = userId == null ? 'global' : 'user:$userId';
    final now = DateTime.now();
    final cached = _policyCache[cacheKey];
    final cachedAt = _policyFetchedAt[cacheKey];
    if (!forceRefresh &&
        cached != null &&
        cachedAt != null &&
        now.difference(cachedAt) < _settingsCacheTtl) {
      return cached;
    }

    final inFlight = _policyInFlight[cacheKey];
    if (!forceRefresh && inFlight != null) {
      return inFlight;
    }

    final future = _fetchAttendancePolicy(userId);
    _policyInFlight[cacheKey] = future;

    try {
      final result = await future;
      if (result.success) {
        _policyCache[cacheKey] = result;
        _policyFetchedAt[cacheKey] = DateTime.now();
      }

      return result;
    } finally {
      if (identical(_policyInFlight[cacheKey], future)) {
        _policyInFlight.remove(cacheKey);
      }
    }
  }

  Future<ApiResponse<AttendanceGlobalPolicy>> _fetchAttendancePolicy(
      int? userId) async {
    if (userId != null) {
      try {
        final response =
            await _apiService.get('/attendance-schemas/user/$userId/effective');
        if (response.data['success'] == true && response.data['data'] != null) {
          return ApiResponse<AttendanceGlobalPolicy>(
            success: true,
            message: 'Policy schema efektif berhasil diambil',
            data: AttendanceGlobalPolicy.fromJson(
              Map<String, dynamic>.from(response.data['data']),
            ),
          );
        }
      } catch (_) {
        // fallback to global policy below
      }
    }

    final settingsResponse = await getGlobalSettings();
    if (!settingsResponse.success || settingsResponse.data == null) {
      return ApiResponse<AttendanceGlobalPolicy>(
        success: false,
        message: settingsResponse.message,
      );
    }

    return ApiResponse<AttendanceGlobalPolicy>(
      success: true,
      message: settingsResponse.message,
      data: AttendanceGlobalPolicy.fromJson(settingsResponse.data),
    );
  }

  // Validate attendance time before submission
  Future<ApiResponse<AttendanceValidation>> validateAttendanceTime({
    required String type, // 'masuk' or 'pulang'
    DateTime? waktu,
  }) async {
    try {
      final data = {
        'type': type,
        'waktu': (waktu ?? DateTime.now()).toIso8601String(),
      };

      final response = await _apiService
          .post('/simple-attendance/validate-time', data: data);

      if (response.data['status'] == 'success' &&
          response.data['data'] != null) {
        final validation = AttendanceValidation.fromJson(response.data['data']);
        return ApiResponse<AttendanceValidation>(
          success: true,
          message: 'Validasi berhasil',
          data: validation,
        );
      } else {
        return ApiResponse<AttendanceValidation>(
          success: false,
          message: response.data['message'] ?? 'Validasi gagal',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<AttendanceValidation>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<AttendanceValidation>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  // Check in with strict validation
  Future<ApiResponse<AttendanceRecord>> checkIn({
    double? latitude,
    double? longitude,
    double? accuracy,
    bool? isMocked,
    Map<String, dynamic>? securityWarningPayload,
    String? keterangan,
    String? fotoPath,
    String? kelasNama,
    int? idKelas,
    int? lokasiId,
  }) async {
    try {
      final deviceMeta = await _getDeviceInfo();
      final devicePayload = deviceMeta['device_info'] is Map
          ? Map<String, dynamic>.from(deviceMeta['device_info'])
          : <String, dynamic>{};
      devicePayload['device_name'] =
          deviceMeta['device_name']?.toString() ?? 'Flutter Mobile App';
      devicePayload.putIfAbsent('platform', () => Platform.operatingSystem);
      final requestTimestamp = DateTime.now().toUtc().toIso8601String();

      final data = <String, dynamic>{
        'jenis_absensi': 'masuk',
        'keterangan': keterangan ?? 'Check-in via mobile app',
        'metode': (fotoPath != null && fotoPath.isNotEmpty) ? 'selfie' : 'mobile',
        'device_id': deviceMeta['device_id']?.toString(),
        'device_info': jsonEncode(devicePayload),
        'request_nonce': _generateRequestNonce(),
        'request_timestamp': requestTimestamp,
        'anti_fraud_payload': jsonEncode(_buildAntiFraudPayload(
          devicePayload,
          requestTimestamp: requestTimestamp,
          isMocked: isMocked,
        )),
      };

      if (latitude != null) {
        data['latitude'] = latitude;
      }
      if (longitude != null) {
        data['longitude'] = longitude;
      }
      if (accuracy != null) {
        data['accuracy'] = accuracy;
      }
      if (isMocked != null) {
        data['is_mocked'] = isMocked ? 1 : 0;
      }
      if (securityWarningPayload != null && securityWarningPayload.isNotEmpty) {
        data['security_warning_payload'] = jsonEncode(securityWarningPayload);
      }

      // Add kelas information if provided (for students)
      if (idKelas != null) {
        data['kelas_id'] = idKelas.toString();
      }
      if (lokasiId != null) {
        data['lokasi_id'] = lokasiId.toString();
      }
      // Note: Backend expects kelas_id (integer), not kelas name
      // If only kelas name is available, it should be converted to ID first

      if (fotoPath != null && fotoPath.isNotEmpty) {
        final filename = fotoPath.split(Platform.pathSeparator).last;
        data['foto_file'] =
            await MultipartFile.fromFile(fotoPath, filename: filename);
      }

      final formData = FormData.fromMap(data);
      final response = await _apiService.post('/simple-attendance/submit',
          data: formData, options: Options(contentType: 'multipart/form-data'));

      if (response.data['status'] == 'success' &&
          response.data['data'] != null) {
        final payload = Map<String, dynamic>.from(response.data['data']);
        if (response.data['verification'] is Map &&
            payload['verification'] == null) {
          payload['verification'] =
              Map<String, dynamic>.from(response.data['verification']);
        }

        final record = AttendanceRecord.fromNewJson(
          payload,
          fallbackType: 'check-in',
        );
        return ApiResponse<AttendanceRecord>(
          success: true,
          message: response.data['message'] ?? 'Check-in berhasil',
          data: record,
        );
      } else {
        return ApiResponse<AttendanceRecord>(
          success: false,
          message: response.data['message'] ?? 'Check-in gagal',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<AttendanceRecord>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<AttendanceRecord>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  // Get device information
  Future<Map<String, dynamic>> _getDeviceInfo() async {
    try {
      final deviceId = (await DeviceBindingMiddleware.getDeviceId()).trim();
      final deviceName = (await DeviceBindingMiddleware.getDeviceName()).trim();
      final deviceInfo = Map<String, dynamic>.from(
        await DeviceBindingMiddleware.getDeviceInfo(),
      );

      if (deviceId.isEmpty) {
        deviceInfo['device_id_resolution_error'] = true;
      }

      return {
        'device_id': deviceId,
        'device_name':
            deviceName.isNotEmpty ? deviceName : 'Flutter Mobile App',
        'device_info': deviceInfo,
      };
    } catch (e) {
      debugPrint('Error getting device info: $e');
      return {
        'device_id': '',
        'device_name': 'Flutter Mobile App',
        'device_info': {
          'platform': 'Unknown',
          'device_id_resolution_error': true,
          'error': e.toString(),
        },
      };
    }
  }

  String _generateRequestNonce() {
    final random = Random.secure();
    final values = List<int>.generate(16, (_) => random.nextInt(256));
    return base64UrlEncode(values).replaceAll('=', '');
  }

  Map<String, dynamic> _buildAntiFraudPayload(
    Map<String, dynamic> devicePayload, {
    required String requestTimestamp,
    bool? isMocked,
  }) {
    return {
      'request_timestamp': requestTimestamp,
      'client_timestamp': requestTimestamp,
      'location_captured_at': requestTimestamp,
      'platform': devicePayload['platform'],
      'package_name': devicePayload['package_name'],
      'app_version': devicePayload['app_version'],
      'app_build_number': devicePayload['app_build_number'],
      'brand': devicePayload['brand'],
      'model': devicePayload['model'],
      'manufacturer': devicePayload['manufacturer'],
      'sdk_int': devicePayload['sdk_int'],
      'is_physical_device': devicePayload['is_physical_device'],
      'emulator_detected': devicePayload['emulator_detected'],
      'developer_options_enabled': devicePayload['developer_options_enabled'],
      'root_detected': devicePayload['root_detected'],
      'magisk_risk': devicePayload['magisk_risk'],
      'adb_enabled': devicePayload['adb_enabled'],
      'usb_debugging_enabled': devicePayload['usb_debugging_enabled'],
      'app_clone_risk': devicePayload['app_clone_risk'],
      'instrumentation_detected': devicePayload['instrumentation_detected'],
      'hooking_detected': devicePayload['hooking_detected'],
      'frida_detected': devicePayload['frida_detected'],
      'xposed_detected': devicePayload['xposed_detected'],
      'debugger_connected': devicePayload['debugger_connected'],
      'suspicious_device_state': devicePayload['suspicious_device_state'],
      'installer_source': devicePayload['installer_source'],
      'signature_sha256': devicePayload['signature_sha256'],
      'build_tags': devicePayload['build_tags'],
      'build_fingerprint': devicePayload['build_fingerprint'],
      'is_debuggable_build': devicePayload['is_debuggable_build'],
      'security_detector_version': devicePayload['security_detector_version'],
      'native_detector_timestamp': devicePayload['detected_at'],
      'is_mock_location': isMocked,
    };
  }

  // Check out
  Future<ApiResponse<AttendanceRecord>> checkOut({
    double? latitude,
    double? longitude,
    double? accuracy,
    bool? isMocked,
    Map<String, dynamic>? securityWarningPayload,
    String? keterangan,
    String? fotoPath,
    int? lokasiId,
  }) async {
    try {
      // Get device info
      final deviceMeta = await _getDeviceInfo();
      final devicePayload = deviceMeta['device_info'] is Map
          ? Map<String, dynamic>.from(deviceMeta['device_info'])
          : <String, dynamic>{};
      devicePayload['device_name'] =
          deviceMeta['device_name']?.toString() ?? 'Flutter Mobile App';
      devicePayload.putIfAbsent('platform', () => Platform.operatingSystem);
      final requestTimestamp = DateTime.now().toUtc().toIso8601String();

      final data = <String, dynamic>{
        'jenis_absensi': 'pulang',
        'keterangan': keterangan ?? 'Check-out via mobile app',
        'metode': (fotoPath != null && fotoPath.isNotEmpty) ? 'selfie' : 'mobile',
        'device_id': deviceMeta['device_id']?.toString(),
        'device_info': jsonEncode(devicePayload),
        'request_nonce': _generateRequestNonce(),
        'request_timestamp': requestTimestamp,
        'anti_fraud_payload': jsonEncode(_buildAntiFraudPayload(
          devicePayload,
          requestTimestamp: requestTimestamp,
          isMocked: isMocked,
        )),
      };

      if (latitude != null) {
        data['latitude'] = latitude;
      }
      if (longitude != null) {
        data['longitude'] = longitude;
      }
      if (accuracy != null) {
        data['accuracy'] = accuracy;
      }
      if (isMocked != null) {
        data['is_mocked'] = isMocked ? 1 : 0;
      }
      if (securityWarningPayload != null && securityWarningPayload.isNotEmpty) {
        data['security_warning_payload'] = jsonEncode(securityWarningPayload);
      }
      if (lokasiId != null) {
        data['lokasi_id'] = lokasiId.toString();
      }

      if (fotoPath != null && fotoPath.isNotEmpty) {
        final filename = fotoPath.split(Platform.pathSeparator).last;
        data['foto_file'] =
            await MultipartFile.fromFile(fotoPath, filename: filename);
      }

      final formData = FormData.fromMap(data);
      final response = await _apiService.post('/simple-attendance/submit',
          data: formData, options: Options(contentType: 'multipart/form-data'));

      if (response.data['status'] == 'success' &&
          response.data['data'] != null) {
        final payload = Map<String, dynamic>.from(response.data['data']);
        if (response.data['verification'] is Map &&
            payload['verification'] == null) {
          payload['verification'] =
              Map<String, dynamic>.from(response.data['verification']);
        }
        final record = AttendanceRecord.fromNewJson(
          payload,
          fallbackType: 'check-out',
        );
        return ApiResponse<AttendanceRecord>(
          success: true,
          message: response.data['message'] ?? 'Check-out berhasil',
          data: record,
        );
      } else {
        return ApiResponse<AttendanceRecord>(
          success: false,
          message: response.data['message'] ?? 'Check-out gagal',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<AttendanceRecord>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<AttendanceRecord>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<Map<String, dynamic>>> reportPrecheckSecurityWarning({
    required Map<String, dynamic> securityWarningPayload,
  }) async {
    try {
      final deviceMeta = await _getDeviceInfo();
      final devicePayload = deviceMeta['device_info'] is Map
          ? Map<String, dynamic>.from(deviceMeta['device_info'])
          : <String, dynamic>{};
      devicePayload['device_name'] =
          deviceMeta['device_name']?.toString() ?? 'Flutter Mobile App';
      devicePayload.putIfAbsent('platform', () => Platform.operatingSystem);
      final requestTimestamp = DateTime.now().toUtc().toIso8601String();

      final response = await _apiService.post(
        '/simple-attendance/precheck/security-warning',
        data: <String, dynamic>{
          'action_type': securityWarningPayload['action_type']?.toString(),
          'device_id': deviceMeta['device_id']?.toString(),
          'device_info': jsonEncode(devicePayload),
          'request_timestamp': requestTimestamp,
          'anti_fraud_payload': jsonEncode(_buildAntiFraudPayload(
            devicePayload,
            requestTimestamp: requestTimestamp,
          )),
          'security_warning_payload': jsonEncode(securityWarningPayload),
        },
      );

      if (response.data['status'] == 'success') {
        final data = response.data['data'] is Map<String, dynamic>
            ? Map<String, dynamic>.from(response.data['data'])
            : <String, dynamic>{};
        return ApiResponse<Map<String, dynamic>>(
          success: true,
          message: response.data['message'] ?? 'Warning keamanan pra-cek berhasil dicatat',
          data: data,
        );
      }

      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: response.data['message'] ?? 'Gagal mencatat warning keamanan pra-cek',
      );
    } on ApiException catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<Map<String, dynamic>>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  // Get today's attendance status
  Future<ApiResponse<TodayAttendance>> getTodayStatus() async {
    try {
      final response = await _apiService.get('/dashboard/my-attendance-status');

      final payload = response.data['data'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(response.data['data'])
          : Map<String, dynamic>.from(response.data);
      final todayData = TodayAttendance.fromBackendJson(payload);

      return ApiResponse<TodayAttendance>(
        success: true,
        message: 'Status hari ini berhasil diambil',
        data: todayData,
      );
    } on ApiException catch (e) {
      return ApiResponse<TodayAttendance>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<TodayAttendance>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  // Get attendance history
  Future<ApiResponse<List<AttendanceRecord>>> getHistory({
    int page = 1,
    int limit = 20,
    String? startDate,
    String? endDate,
    int? tahunAjaranId,
  }) async {
    try {
      final queryParams = {
        'page': page.toString(),
        // Backend paginated endpoint uses `per_page`.
        'per_page': limit.toString(),
        // Keep legacy key for backward compatibility (ignored if not used).
        'limit': limit.toString(),
      };

      if (startDate != null) queryParams['start_date'] = startDate;
      if (endDate != null) queryParams['end_date'] = endDate;
      if (tahunAjaranId != null && tahunAjaranId > 0) {
        queryParams['tahun_ajaran_id'] = tahunAjaranId.toString();
      }

      final response = await _apiService.get('/absensi/history',
          queryParameters: queryParams);

      if (response.data['success'] == true && response.data['data'] != null) {
        final rawData = response.data['data'];
        final List<dynamic> recordsPayload;
        if (rawData is List) {
          recordsPayload = rawData;
        } else if (rawData is Map<String, dynamic> && rawData['data'] is List) {
          recordsPayload = rawData['data'] as List;
        } else {
          recordsPayload = [];
        }

        final records = recordsPayload
            .map((item) => AttendanceRecord.fromJson(item))
            .toList();

        return ApiResponse<List<AttendanceRecord>>(
          success: true,
          message:
              response.data['message'] ?? 'Riwayat absensi berhasil diambil',
          data: records,
        );
      } else {
        return ApiResponse<List<AttendanceRecord>>(
          success: false,
          message:
              response.data['message'] ?? 'Gagal mengambil riwayat absensi',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<List<AttendanceRecord>>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<List<AttendanceRecord>>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<AttendanceRecord>> getAttendanceDetail(
    String attendanceId,
  ) async {
    try {
      final response = await _apiService.get('/absensi/$attendanceId');

      if (response.data['success'] == true && response.data['data'] != null) {
        return ApiResponse<AttendanceRecord>(
          success: true,
          message:
              response.data['message'] ?? 'Detail presensi berhasil diambil',
          data: AttendanceRecord.fromJson(
            Map<String, dynamic>.from(response.data['data']),
          ),
        );
      }

      return ApiResponse<AttendanceRecord>(
        success: false,
        message: response.data['message'] ?? 'Gagal mengambil detail presensi',
      );
    } on ApiException catch (e) {
      return ApiResponse<AttendanceRecord>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<AttendanceRecord>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  // Get attendance statistics
  Future<ApiResponse<AttendanceStatistics>> getStatistics({
    int? month,
    int? year,
    int? tahunAjaranId,
  }) async {
    try {
      final queryParams = <String, String>{};
      if (month != null) queryParams['month'] = month.toString();
      if (year != null) queryParams['year'] = year.toString();
      if (tahunAjaranId != null && tahunAjaranId > 0) {
        queryParams['tahun_ajaran_id'] = tahunAjaranId.toString();
      }

      final response = await _apiService.get('/absensi/statistics',
          queryParameters: queryParams);

      if (response.data['success'] == true && response.data['data'] != null) {
        final stats = AttendanceStatistics.fromJson(
          Map<String, dynamic>.from(response.data['data']),
        );
        return ApiResponse<AttendanceStatistics>(
          success: true,
          message:
              response.data['message'] ?? 'Statistik absensi berhasil diambil',
          data: stats,
        );
      } else {
        return ApiResponse<AttendanceStatistics>(
          success: false,
          message:
              response.data['message'] ?? 'Gagal mengambil statistik absensi',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<AttendanceStatistics>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<AttendanceStatistics>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }
}

// Attendance Record Model
class AttendanceRecord {
  final String id;
  final String userId;
  final String type; // 'check-in' or 'check-out'
  final DateTime timestamp;
  final DateTime? attendanceDate;
  final double? latitude;
  final double? longitude;
  final String? location;
  final String? keterangan;
  final String? fotoUrl;
  final String? fotoMasukUrl;
  final String? fotoPulangUrl;
  final String? checkInTime;
  final String? checkOutTime;
  final bool hasCheckIn;
  final bool hasCheckOut;
  final String? status;
  final String? statusLabel;
  final String? metodeAbsensi;
  final String? lokasiMasukNama;
  final String? lokasiPulangNama;
  final double? latitudeMasuk;
  final double? longitudeMasuk;
  final double? latitudePulang;
  final double? longitudePulang;
  final String? durationText;
  final bool isVerified;
  final DateTime? verifiedAt;
  final double? faceScoreCheckIn;
  final double? faceScoreCheckOut;
  final double? gpsAccuracyMasuk;
  final double? gpsAccuracyPulang;
  final bool isLate;
  final String? verificationStatus;
  final String? verificationMode;
  final double? verificationScore;
  final Map<String, dynamic>? securityNotice;
  final String? validationStatus;
  final bool hasWarning;
  final String? warningSummary;
  final List<Map<String, dynamic>> fraudFlags;

  AttendanceRecord({
    required this.id,
    required this.userId,
    required this.type,
    required this.timestamp,
    this.attendanceDate,
    this.latitude,
    this.longitude,
    this.location,
    this.keterangan,
    this.fotoUrl,
    this.fotoMasukUrl,
    this.fotoPulangUrl,
    this.checkInTime,
    this.checkOutTime,
    required this.hasCheckIn,
    required this.hasCheckOut,
    this.status,
    this.statusLabel,
    this.metodeAbsensi,
    this.lokasiMasukNama,
    this.lokasiPulangNama,
    this.latitudeMasuk,
    this.longitudeMasuk,
    this.latitudePulang,
    this.longitudePulang,
    this.durationText,
    required this.isVerified,
    this.verifiedAt,
    this.faceScoreCheckIn,
    this.faceScoreCheckOut,
    this.gpsAccuracyMasuk,
    this.gpsAccuracyPulang,
    required this.isLate,
    this.verificationStatus,
    this.verificationMode,
    this.verificationScore,
    this.securityNotice,
    this.validationStatus,
    this.hasWarning = false,
    this.warningSummary,
    this.fraudFlags = const <Map<String, dynamic>>[],
  });

  factory AttendanceRecord.fromJson(Map<String, dynamic> json) {
    final verificationData = json['verification'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['verification'])
        : null;
    final attendanceDate = _safeParseDate(json['tanggal']);
    final checkInTime =
        _normalizeTime(json['jam_masuk_format'] ?? json['jam_masuk']);
    final checkOutTime =
        _normalizeTime(json['jam_pulang_format'] ?? json['jam_pulang']);
    final resolvedType = json['type']?.toString() ??
        json['jenis_absensi']?.toString() ??
        (json['jam_pulang'] != null ? 'check-out' : 'check-in');
    final resolvedStatus = json['status']?.toString();
    final resolvedStatusLabel =
        json['status_label']?.toString() ?? _formatStatusLabel(resolvedStatus);
    final resolvedScore = _toDouble(verificationData?['score']) ??
        _toDouble(
          resolvedType == 'check-out'
              ? json['face_score_checkout']
              : json['face_score_checkin'],
        );
    final parsedTimestamp = _resolveTimestamp(
      timestampRaw: json['timestamp'] ?? json['created_at'],
      attendanceDate: attendanceDate,
      checkInTime: checkInTime,
      checkOutTime: checkOutTime,
    );
    final fotoMasukUrl =
        _resolveMediaUrl(json['foto_masuk_url'], json['foto_masuk']);
    final fotoPulangUrl =
        _resolveMediaUrl(json['foto_pulang_url'], json['foto_pulang']);
    final latitudeMasuk = _toDouble(json['latitude_masuk'] ?? json['latitude']);
    final longitudeMasuk =
        _toDouble(json['longitude_masuk'] ?? json['longitude']);
    final latitudePulang = _toDouble(json['latitude_pulang']);
    final longitudePulang = _toDouble(json['longitude_pulang']);

    return AttendanceRecord(
      id: json['id'].toString(),
      userId: json['user_id'].toString(),
      type: resolvedType,
      timestamp: parsedTimestamp,
      attendanceDate: attendanceDate,
      latitude: latitudeMasuk,
      longitude: longitudeMasuk,
      location:
          json['location']?.toString() ?? json['lokasi_masuk_nama']?.toString(),
      keterangan: json['keterangan'],
      fotoUrl: fotoMasukUrl ?? fotoPulangUrl,
      fotoMasukUrl: fotoMasukUrl,
      fotoPulangUrl: fotoPulangUrl,
      checkInTime: checkInTime,
      checkOutTime: checkOutTime,
      hasCheckIn: _toBool(json['has_check_in']) ?? (checkInTime != null),
      hasCheckOut: _toBool(json['has_check_out']) ?? (checkOutTime != null),
      status: resolvedStatus,
      statusLabel: resolvedStatusLabel,
      metodeAbsensi: json['metode_absensi']?.toString(),
      lokasiMasukNama: json['lokasi_masuk_nama']?.toString(),
      lokasiPulangNama: json['lokasi_pulang_nama']?.toString(),
      latitudeMasuk: latitudeMasuk,
      longitudeMasuk: longitudeMasuk,
      latitudePulang: latitudePulang,
      longitudePulang: longitudePulang,
      durationText: json['durasi_kerja_format']?.toString() ??
          json['durasi_sekolah_format']?.toString() ??
          json['durasi_kerja']?.toString(),
      isVerified: _toBool(json['is_verified']) ?? false,
      verifiedAt: _safeParseDateTimeOrNull(json['verified_at']),
      faceScoreCheckIn: _toDouble(json['face_score_checkin']),
      faceScoreCheckOut: _toDouble(json['face_score_checkout']),
      gpsAccuracyMasuk: _toDouble(json['gps_accuracy_masuk']),
      gpsAccuracyPulang: _toDouble(json['gps_accuracy_pulang']),
      isLate: _toBool(json['is_late']) ?? resolvedStatus == 'terlambat',
      verificationStatus: json['verification_status']?.toString() ??
          verificationData?['status']?.toString() ??
          verificationData?['result']?.toString(),
      verificationMode: verificationData?['mode']?.toString(),
      verificationScore: resolvedScore,
      securityNotice: json['security_notice'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['security_notice'])
          : null,
      validationStatus: json['validation_status']?.toString(),
      hasWarning: _toBool(json['has_warning']) ??
          (json['validation_status']?.toString() == 'warning'),
      warningSummary: json['warning_summary']?.toString(),
      fraudFlags: _mapFraudFlags(json['fraud_flags']),
    );
  }

  // Factory for new API response format from simple-attendance/submit
  factory AttendanceRecord.fromNewJson(
    Map<String, dynamic> json, {
    String fallbackType = 'check-in',
  }) {
    final verificationData = json['verification'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['verification'])
        : null;

    return AttendanceRecord(
      id: DateTime.now()
          .millisecondsSinceEpoch
          .toString(), // Generate temporary ID
      userId: json['user_id']?.toString() ?? '',
      type: json['jenis_absensi']?.toString() ??
          json['type']?.toString() ??
          fallbackType,
      timestamp: _safeParseDateTime(json['timestamp']),
      attendanceDate: _safeParseDate(json['tanggal']),
      latitude: _toDouble(json['latitude']),
      longitude: _toDouble(json['longitude']),
      location: json['location'],
      keterangan: json['keterangan'],
      fotoUrl: json['foto_url'],
      fotoMasukUrl: fallbackType == 'check-in'
          ? _resolveMediaUrl(json['foto_url'], json['foto_masuk'])
          : null,
      fotoPulangUrl: fallbackType == 'check-out'
          ? _resolveMediaUrl(json['foto_url'], json['foto_pulang'])
          : null,
      checkInTime:
          fallbackType == 'check-in' ? _normalizeTime(json['timestamp']) : null,
      checkOutTime: fallbackType == 'check-out'
          ? _normalizeTime(json['timestamp'])
          : null,
      hasCheckIn: fallbackType == 'check-in',
      hasCheckOut: fallbackType == 'check-out',
      status: json['attendance_status']?.toString(),
      statusLabel: _formatStatusLabel(json['attendance_status']?.toString()),
      metodeAbsensi: json['metode']?.toString(),
      lokasiMasukNama:
          fallbackType == 'check-in' ? json['location']?.toString() : null,
      lokasiPulangNama:
          fallbackType == 'check-out' ? json['location']?.toString() : null,
      latitudeMasuk:
          fallbackType == 'check-in' ? _toDouble(json['latitude']) : null,
      longitudeMasuk:
          fallbackType == 'check-in' ? _toDouble(json['longitude']) : null,
      latitudePulang:
          fallbackType == 'check-out' ? _toDouble(json['latitude']) : null,
      longitudePulang:
          fallbackType == 'check-out' ? _toDouble(json['longitude']) : null,
      durationText: null,
      isVerified: false,
      verifiedAt: null,
      faceScoreCheckIn: fallbackType == 'check-in'
          ? _toDouble(verificationData?['score'])
          : null,
      faceScoreCheckOut: fallbackType == 'check-out'
          ? _toDouble(verificationData?['score'])
          : null,
      gpsAccuracyMasuk: null,
      gpsAccuracyPulang: null,
      isLate: json['attendance_status'] == 'terlambat',
      verificationStatus: verificationData?['status']?.toString() ??
          verificationData?['result']?.toString(),
      verificationMode: verificationData?['mode']?.toString(),
      verificationScore: _toDouble(verificationData?['score']),
      securityNotice: json['security_notice'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['security_notice'])
          : null,
      validationStatus: json['validation_status']?.toString(),
      hasWarning: _toBool(json['has_warning']) ??
          (json['validation_status']?.toString() == 'warning'),
      warningSummary: json['warning_summary']?.toString(),
      fraudFlags: _mapFraudFlags(json['fraud_flags']),
    );
  }

  String get formattedTime {
    return '${timestamp.hour.toString().padLeft(2, '0')}:${timestamp.minute.toString().padLeft(2, '0')}';
  }

  String get formattedDate {
    final source = attendanceDate ?? timestamp;
    return '${source.day}/${source.month}/${source.year}';
  }

  String get formattedCheckInTime => checkInTime ?? '-';

  String get formattedCheckOutTime => checkOutTime ?? '-';

  String get displayStatusLabel {
    return statusLabel ??
        (isLate
            ? 'Terlambat'
            : (hasCheckOut
                ? 'Pulang'
                : (hasCheckIn ? 'Hadir' : 'Belum Absen')));
  }

  static double? _toDouble(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is double) {
      return value;
    }
    if (value is int) {
      return value.toDouble();
    }
    if (value is String) {
      return double.tryParse(value);
    }
    return null;
  }

  static bool? _toBool(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is bool) {
      return value;
    }
    if (value is int) {
      return value == 1;
    }
    if (value is String) {
      final normalized = value.trim().toLowerCase();
      if (normalized == 'true' || normalized == '1') {
        return true;
      }
      if (normalized == 'false' || normalized == '0') {
        return false;
      }
    }
    return null;
  }

  static List<Map<String, dynamic>> _mapFraudFlags(dynamic value) {
    if (value is! List) {
      return const <Map<String, dynamic>>[];
    }

    return value
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList(growable: false);
  }

  static DateTime? _safeParseDate(dynamic value) {
    final raw = value?.toString();
    if (raw == null || raw.isEmpty) {
      return null;
    }

    return DateTime.tryParse(raw);
  }

  static DateTime _safeParseDateTime(dynamic value) {
    final raw = value?.toString();
    if (raw == null || raw.isEmpty) {
      return DateTime.now();
    }

    final direct = DateTime.tryParse(raw);
    if (direct != null) {
      return direct;
    }

    final normalized = raw.contains(' ') ? raw.replaceFirst(' ', 'T') : raw;
    return DateTime.tryParse(normalized) ?? DateTime.now();
  }

  static DateTime? _safeParseDateTimeOrNull(dynamic value) {
    final raw = value?.toString();
    if (raw == null || raw.isEmpty) {
      return null;
    }

    final direct = DateTime.tryParse(raw);
    if (direct != null) {
      return direct;
    }

    final normalized = raw.contains(' ') ? raw.replaceFirst(' ', 'T') : raw;
    return DateTime.tryParse(normalized);
  }

  static DateTime _resolveTimestamp({
    dynamic timestampRaw,
    required DateTime? attendanceDate,
    required String? checkInTime,
    required String? checkOutTime,
  }) {
    if (timestampRaw != null && timestampRaw.toString().isNotEmpty) {
      return _safeParseDateTime(timestampRaw);
    }

    final fromCheckIn = _combineDateAndTime(attendanceDate, checkInTime);
    if (fromCheckIn != null) {
      return fromCheckIn;
    }

    final fromCheckOut = _combineDateAndTime(attendanceDate, checkOutTime);
    if (fromCheckOut != null) {
      return fromCheckOut;
    }

    return DateTime.now();
  }

  static DateTime? _combineDateAndTime(DateTime? date, String? time) {
    if (date == null || time == null || time.isEmpty) {
      return null;
    }

    final parts = time.split(':');
    if (parts.length < 2) {
      return null;
    }

    final hour = int.tryParse(parts[0]);
    final minute = int.tryParse(parts[1]);
    final second = parts.length > 2 ? int.tryParse(parts[2]) ?? 0 : 0;
    if (hour == null || minute == null) {
      return null;
    }

    return DateTime(date.year, date.month, date.day, hour, minute, second);
  }

  static String? _normalizeTime(dynamic value) {
    final raw = value?.toString().trim();
    if (raw == null || raw.isEmpty) {
      return null;
    }

    final timePart = raw.contains('T')
        ? raw.split('T').last
        : (raw.contains(' ') ? raw.split(' ').last : raw);
    final parts = timePart.split(':');
    if (parts.length < 2) {
      return raw;
    }

    final hour = parts[0].padLeft(2, '0');
    final minute = parts[1].padLeft(2, '0');
    return '$hour:$minute';
  }

  static String? _resolveMediaUrl(
      dynamic preferredValue, dynamic fallbackValue) {
    final raw = preferredValue?.toString().trim().isNotEmpty == true
        ? preferredValue.toString().trim()
        : fallbackValue?.toString().trim();

    if (raw == null || raw.isEmpty) {
      return null;
    }

    if (raw.startsWith('http://') || raw.startsWith('https://')) {
      return raw;
    }

    final apiBase = AppConstants.baseUrl.replaceFirst(RegExp(r'/api/?$'), '');
    var path = raw.replaceFirst(RegExp(r'^/+'), '');

    if (path.startsWith('api/storage/')) {
      path = path.substring(4);
    }
    if (path.startsWith('storage/')) {
      return '$apiBase/$path';
    }
    if (path.startsWith('public/')) {
      path = path.substring('public/'.length);
    }

    return '$apiBase/storage/$path';
  }

  static String? _formatStatusLabel(String? value) {
    final normalized = (value ?? '').trim().toLowerCase();
    switch (normalized) {
      case 'hadir':
        return 'Hadir';
      case 'terlambat':
        return 'Terlambat';
      case 'izin':
        return 'Izin';
      case 'sakit':
        return 'Sakit';
      case 'alpha':
      case 'alpa':
        return 'Alpha';
      default:
        if (normalized.isEmpty) {
          return null;
        }
        return normalized[0].toUpperCase() + normalized.substring(1);
    }
  }
}

// Today Attendance Model
class TodayAttendance {
  final bool hasCheckedIn;
  final bool hasCheckedOut;
  final AttendanceRecord? checkinRecord;
  final AttendanceRecord? checkoutRecord;
  final String status;

  TodayAttendance({
    required this.hasCheckedIn,
    required this.hasCheckedOut,
    this.checkinRecord,
    this.checkoutRecord,
    required this.status,
  });

  factory TodayAttendance.fromJson(Map<String, dynamic> json) {
    return TodayAttendance(
      hasCheckedIn: json['has_checked_in'] ?? false,
      hasCheckedOut: json['has_checked_out'] ?? false,
      checkinRecord: json['checkin_record'] != null
          ? AttendanceRecord.fromJson(json['checkin_record'])
          : null,
      checkoutRecord: json['checkout_record'] != null
          ? AttendanceRecord.fromJson(json['checkout_record'])
          : null,
      status: json['status'] ?? 'Belum Absen',
    );
  }

  // Factory for backend response format
  factory TodayAttendance.fromBackendJson(Map<String, dynamic> json) {
    final payload = json['data'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['data'])
        : json;
    final hasAttendance = payload['has_attendance'] ?? false;
    final checkinData = payload['check_in'];
    final checkoutData = payload['check_out'];
    final hasCheckedIn =
        payload['has_checked_in'] ?? (hasAttendance && checkinData != null);
    final hasCheckedOut =
        payload['has_checked_out'] ?? (hasAttendance && checkoutData != null);

    return TodayAttendance(
      hasCheckedIn: hasCheckedIn == true,
      hasCheckedOut: hasCheckedOut == true,
      status: payload['status'] ?? 'Belum Absen',
    );
  }

  factory TodayAttendance.empty() {
    return TodayAttendance(
      hasCheckedIn: false,
      hasCheckedOut: false,
      status: 'Belum Absen',
    );
  }

  String? get checkinTime {
    return checkinRecord?.formattedTime;
  }

  String? get checkoutTime {
    return checkoutRecord?.formattedTime;
  }
}

// Attendance Statistics Model
class AttendanceStatistics {
  final int totalDays;
  final int presentDays;
  final int absentDays;
  final int lateDays;
  final int lateMinutes;
  final int permissionDays;
  final double attendancePercentage;

  AttendanceStatistics({
    required this.totalDays,
    required this.presentDays,
    required this.absentDays,
    required this.lateDays,
    required this.lateMinutes,
    required this.permissionDays,
    required this.attendancePercentage,
  });

  factory AttendanceStatistics.fromJson(Map<String, dynamic> json) {
    int parseInt(dynamic value) {
      if (value is int) return value;
      if (value is double) return value.toInt();
      if (value is String) return int.tryParse(value) ?? 0;
      return 0;
    }

    double parseDouble(dynamic value) {
      if (value is double) return value;
      if (value is int) return value.toDouble();
      if (value is String) return double.tryParse(value) ?? 0.0;
      return 0.0;
    }

    return AttendanceStatistics(
      // Support legacy keys and backend keys terbaru (Indonesia naming).
      totalDays: parseInt(
        json['total_hari_sekolah_berjalan'] ??
            json['elapsed_school_days'] ??
            json['total_days'] ??
            json['total_hari_kerja'],
      ),
      presentDays: parseInt(
        json['present_days'] ??
            (parseInt(json['total_hadir']) +
                parseInt(json['late_days'] ?? json['total_terlambat'])),
      ),
      absentDays: parseInt(json['absent_days'] ?? json['total_alpha']),
      lateDays: parseInt(json['late_days'] ?? json['total_terlambat']),
      lateMinutes: parseInt(
        json['late_minutes'] ?? json['total_terlambat_menit'],
      ),
      permissionDays: json['permission_days'] != null
          ? parseInt(json['permission_days'])
          : (parseInt(json['total_izin']) + parseInt(json['total_sakit'])),
      attendancePercentage: parseDouble(
        json['attendance_percentage'] ?? json['persentase_kehadiran'],
      ),
    );
  }
}

// Working Hours Model
class WorkingHours {
  final String jamMasuk;
  final String jamPulang;
  final int toleransi;
  final int minimalOpenTime;
  final bool wajibGps;
  final bool wajibFoto;
  final bool faceVerificationEnabled;
  final bool faceTemplateRequired;
  final List<String> hariKerja;
  final String source;
  final int? schemaId;
  final String? schemaName;
  final String? keterangan;

  WorkingHours({
    required this.jamMasuk,
    required this.jamPulang,
    required this.toleransi,
    required this.minimalOpenTime,
    required this.wajibGps,
    required this.wajibFoto,
    required this.faceVerificationEnabled,
    required this.faceTemplateRequired,
    required this.hariKerja,
    required this.source,
    this.schemaId,
    this.schemaName,
    this.keterangan,
  });

  factory WorkingHours.fromJson(Map<String, dynamic> json) {
    // Helper function to safely parse boolean values
    bool parseBool(dynamic value) {
      if (value is bool) return value;
      if (value is int) return value == 1;
      if (value is String) return value.toLowerCase() == 'true' || value == '1';
      return true; // Default to true for safety
    }

    int parseInt(dynamic value, int fallback) {
      if (value is int) return value;
      if (value is String) return int.tryParse(value) ?? fallback;
      return fallback;
    }

    return WorkingHours(
      jamMasuk: json['jam_masuk'] ?? '07:00',
      jamPulang: json['jam_pulang'] ?? '15:00',
      toleransi: parseInt(json['toleransi'], 15),
      minimalOpenTime: parseInt(json['minimal_open_time'], 70),
      wajibGps: parseBool(json['wajib_gps']),
      wajibFoto: parseBool(json['wajib_foto']),
      faceVerificationEnabled: parseBool(json['face_verification_enabled']),
      faceTemplateRequired: parseBool(json['face_template_required']),
      hariKerja: parseWorkingDays(json['hari_kerja']),
      source: json['source'] ?? 'global',
      schemaId: json['schema_id'] is int
          ? json['schema_id']
          : int.tryParse('${json['schema_id'] ?? ''}'),
      schemaName: json['schema_name']?.toString(),
      keterangan: json['keterangan'],
    );
  }

  static List<String> parseWorkingDays(dynamic raw) {
    if (raw is String) {
      try {
        final decoded = jsonDecode(raw);
        return parseWorkingDays(decoded);
      } catch (_) {
        return const <String>[
          'Senin',
          'Selasa',
          'Rabu',
          'Kamis',
          'Jumat',
        ];
      }
    }

    if (raw is List) {
      return raw
          .map((item) => item.toString().trim())
          .where((item) => item.isNotEmpty)
          .toList();
    }

    return const <String>['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];
  }

  String get displaySource {
    switch (source) {
      case 'user_override':
        return 'Pengaturan Khusus';
      case 'status_siswa':
        return 'Pengaturan Siswa';
      case 'status_staff':
        return 'Pengaturan Staff';
      case 'schema_effective':
        return 'Schema Efektif';
      default:
        return 'Pengaturan Global';
    }
  }
}

// Attendance Validation Model
class AttendanceValidation {
  final bool valid;
  final String message;
  final String? status;
  final String? code;
  final WorkingHours? workingHours;
  final AttendanceWindow? window;

  AttendanceValidation({
    required this.valid,
    required this.message,
    this.status,
    this.code,
    this.workingHours,
    this.window,
  });

  factory AttendanceValidation.fromJson(Map<String, dynamic> json) {
    return AttendanceValidation(
      valid: json['valid'] ?? false,
      message: json['message'] ?? '',
      status: json['status'],
      code: json['code'],
      workingHours: json['working_hours'] != null
          ? WorkingHours.fromJson(json['working_hours'])
          : null,
      window: json['window'] != null
          ? AttendanceWindow.fromJson(json['window'])
          : null,
    );
  }
}

// Attendance Window Model
class AttendanceWindow {
  final String earliest;
  final String latest;

  AttendanceWindow({
    required this.earliest,
    required this.latest,
  });

  factory AttendanceWindow.fromJson(Map<String, dynamic> json) {
    return AttendanceWindow(
      earliest: json['earliest'] ?? '',
      latest: json['latest'] ?? '',
    );
  }

  String get displayWindow {
    return '$earliest - $latest';
  }
}
