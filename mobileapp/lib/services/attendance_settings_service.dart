import 'api_service.dart';
import 'dart:convert';

class AttendanceSettingsResponse {
  final bool success;
  final Map<String, dynamic>? data;
  final String? message;

  AttendanceSettingsResponse({
    required this.success,
    this.data,
    this.message,
  });

  factory AttendanceSettingsResponse.fromJson(Map<String, dynamic> json) {
    return AttendanceSettingsResponse(
      success: json['success'] ?? false,
      data: json['data'],
      message: json['message'],
    );
  }
}

class AttendanceInfoResponse {
  final bool success;
  final GPSLocation? gpsLocation;
  final AttendanceSettings? settings;
  final String? message;
  final Map<String, dynamic>? trackingPolicy;

  // Tambahan properti untuk schema, location, distance
  final String? schema;
  final String? location;
  final String? distance;
  final String? schemaType;
  final int? schemaVersion;

  AttendanceInfoResponse({
    required this.success,
    this.gpsLocation,
    this.settings,
    this.message,
    this.trackingPolicy,
    this.schema,
    this.location,
    this.distance,
    this.schemaType,
    this.schemaVersion,
  });
}

class GPSLocation {
  final int? id;
  final double latitude;
  final double longitude;
  final double radius;
  final String nama;
  final String geofenceType;
  final Map<String, dynamic>? geofenceGeojson;

  GPSLocation({
    this.id,
    required this.latitude,
    required this.longitude,
    required this.radius,
    required this.nama,
    this.geofenceType = 'circle',
    this.geofenceGeojson,
  });

  factory GPSLocation.fromJson(Map<String, dynamic> json) {
    double parseDouble(dynamic value) {
      if (value is double) return value;
      if (value is int) return value.toDouble();
      if (value is String) return double.tryParse(value) ?? 0.0;
      return 0.0;
    }

    Map<String, dynamic>? parseGeojson(dynamic value) {
      if (value is Map) {
        return Map<String, dynamic>.from(value);
      }

      if (value is String && value.trim().isNotEmpty) {
        try {
          final decoded = jsonDecode(value);
          if (decoded is Map) {
            return Map<String, dynamic>.from(decoded);
          }
        } catch (_) {}
      }

      return null;
    }

    return GPSLocation(
      id: json['id'] is int ? json['id'] as int : int.tryParse('${json['id'] ?? ''}'),
      latitude: parseDouble(json['latitude']),
      longitude: parseDouble(json['longitude']),
      radius: parseDouble(json['radius']),
      nama: json['nama_lokasi'] ?? json['nama'] ?? 'Lokasi Sekolah',
      geofenceType: (json['geofence_type'] ?? 'circle').toString(),
      geofenceGeojson: parseGeojson(json['geofence_geojson']),
    );
  }
}

class AttendanceSettings {
  final bool requireGPS;
  final bool requireSelfie;
  final bool faceVerificationEnabled;
  final int gpsAccuracy;
  final double gpsAccuracyGrace;
  final String jamMasuk;
  final String jamPulang;
  final int toleransi;
  final List<String> hariKerja;

  AttendanceSettings({
    required this.requireGPS,
    required this.requireSelfie,
    required this.faceVerificationEnabled,
    required this.gpsAccuracy,
    required this.gpsAccuracyGrace,
    required this.jamMasuk,
    required this.jamPulang,
    required this.toleransi,
    required this.hariKerja,
  });

  factory AttendanceSettings.fromJson(Map<String, dynamic> json) {
    bool parseBool(dynamic value) {
      if (value is bool) return value;
      if (value is int) return value == 1;
      if (value is String) return value.toLowerCase() == 'true' || value == '1';
      return false;
    }

    String resolveTime(Map<String, dynamic> source, List<String> keys, String fallback) {
      for (final key in keys) {
        final raw = source[key];
        if (raw == null) {
          continue;
        }

        final value = raw.toString().trim();
        if (value.isEmpty) {
          continue;
        }

        if (value.length >= 5 && value.contains(':')) {
          return value.substring(0, 5);
        }

        return value;
      }

      return fallback;
    }

    List<String> parseStringList(dynamic raw) {
      if (raw is List) {
        return raw
            .map((item) => item.toString().trim())
            .where((item) => item.isNotEmpty)
            .toList();
      }

      if (raw is String) {
        final trimmed = raw.trim();
        if (trimmed.isEmpty) {
          return const <String>[];
        }

        try {
          final decoded = jsonDecode(trimmed);
          return parseStringList(decoded);
        } catch (_) {
          return trimmed
              .split(',')
              .map((item) => item.trim())
              .where((item) => item.isNotEmpty)
              .toList();
        }
      }

      return const <String>[];
    }

    int parseInt(dynamic raw, int fallback) {
      if (raw is int) return raw;
      if (raw is double) return raw.round();
      if (raw is String) return int.tryParse(raw) ?? fallback;
      return fallback;
    }

    double parseDouble(dynamic raw, double fallback) {
      if (raw is double) return raw;
      if (raw is int) return raw.toDouble();
      if (raw is String) return double.tryParse(raw) ?? fallback;
      return fallback;
    }

    return AttendanceSettings(
      requireGPS: parseBool(json['wajib_gps']),
      requireSelfie: parseBool(json['wajib_foto'] ?? json['require_photo']),
      faceVerificationEnabled: parseBool(
        json['face_verification_enabled'] ??
            json['require_face_verification'],
      ),
      gpsAccuracy: parseInt(
        json['gps_accuracy'] ?? json['gps_accuracy_minimum'],
        20,
      ),
      gpsAccuracyGrace: parseDouble(
        json['gps_accuracy_grace'],
        0.0,
      ),
      jamMasuk: resolveTime(
        json,
        ['siswa_jam_masuk', 'jam_masuk_default', 'jam_masuk'],
        '08:00',
      ),
      jamPulang: resolveTime(
        json,
        ['siswa_jam_pulang', 'jam_pulang_default', 'jam_pulang'],
        '16:00',
      ),
      toleransi: parseInt(
        json['siswa_toleransi'] ?? json['toleransi'] ?? json['toleransi_default'],
        10,
      ),
      hariKerja: parseStringList(json['hari_kerja']),
    );
  }
}

class AttendanceSettingsService {
  final ApiService _apiService = ApiService();
  static const Duration _attendanceInfoCacheTtl = Duration(seconds: 5);
  final Map<int, AttendanceInfoResponse> _attendanceInfoCache =
      <int, AttendanceInfoResponse>{};
  final Map<int, DateTime> _attendanceInfoFetchedAt = <int, DateTime>{};
  final Map<int, Future<AttendanceInfoResponse>> _attendanceInfoInFlight =
      <int, Future<AttendanceInfoResponse>>{};

  /// Get effective attendance settings for a specific user
  Future<AttendanceSettingsResponse> getUserAttendanceSettings(
      int userId) async {
    try {
      // Use the new, correct endpoint for effective schema
      final response =
          await _apiService.get('/attendance-schemas/user/$userId/effective');

      if (response.statusCode == 200 && response.data != null) {
        final data = response.data;
        if (data['success'] == true && data['data'] != null) {
          return AttendanceSettingsResponse(
            success: true,
            data: data['data'],
            message: 'Pengaturan berhasil dimuat dari skema efektif.',
          );
        } else {
          return AttendanceSettingsResponse(
            success: false,
            message:
                data['message'] ?? 'Gagal memuat informasi skema pengguna.',
          );
        }
      }

      // If the response is not successful, return a generic error
      return AttendanceSettingsResponse(
        success: false,
        message: 'Gagal mengambil pengaturan absensi dari server.',
      );
    } on ApiException catch (e) {
      return AttendanceSettingsResponse(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return AttendanceSettingsResponse(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  /// Get attendance info including GPS location and settings for a specific user
  Future<AttendanceInfoResponse> getAttendanceInfo(
    int userId, {
    bool forceRefresh = false,
  }) async {
    final now = DateTime.now();
    final cached = _attendanceInfoCache[userId];
    final cachedAt = _attendanceInfoFetchedAt[userId];
    if (!forceRefresh &&
        cached != null &&
        cachedAt != null &&
        now.difference(cachedAt) < _attendanceInfoCacheTtl) {
      return cached;
    }

    final inFlight = _attendanceInfoInFlight[userId];
    if (!forceRefresh && inFlight != null) {
      return inFlight;
    }

    final future = _fetchAttendanceInfo(userId);
    _attendanceInfoInFlight[userId] = future;

    try {
      final result = await future;
      if (result.success) {
        _attendanceInfoCache[userId] = result;
        _attendanceInfoFetchedAt[userId] = DateTime.now();
      }

      return result;
    } finally {
      if (identical(_attendanceInfoInFlight[userId], future)) {
        _attendanceInfoInFlight.remove(userId);
      }
    }
  }

  Future<AttendanceInfoResponse> _fetchAttendanceInfo(int userId) async {
    try {
      // Primary source: mobile-oriented schema endpoint (contains effective locations).
      final schemaResponse = await _apiService.get('/lokasi-gps/attendance-schema');
      if (schemaResponse.statusCode == 200 &&
          schemaResponse.data != null &&
          schemaResponse.data['success'] == true &&
          schemaResponse.data['data'] != null) {
        final payload = Map<String, dynamic>.from(schemaResponse.data['data']);
        final settingsMap = _extractSettingsMap(payload);
        final locations = _extractLocations(payload);
        final settings = AttendanceSettings.fromJson(settingsMap);
        final gpsLocation =
            locations.isNotEmpty ? GPSLocation.fromJson(locations.first) : null;

        return AttendanceInfoResponse(
          success: true,
          gpsLocation: gpsLocation,
          settings: settings,
          trackingPolicy: payload['tracking_policy'] is Map<String, dynamic>
              ? Map<String, dynamic>.from(payload['tracking_policy'])
              : (payload['tracking_policy'] is Map
                  ? Map<String, dynamic>.from(payload['tracking_policy'])
                  : null),
          schema: (payload['schema_name'] ?? 'Skema Default').toString(),
          location: _buildLocationLabel(locations),
          distance: 'Menghitung...',
          schemaType: (payload['schema_type'] ?? '').toString(),
          schemaVersion: payload['version'] is int
              ? payload['version'] as int
              : int.tryParse((payload['version'] ?? '').toString()),
          message: 'Data berhasil dimuat',
        );
      }

      // Fallback source: effective schema endpoint.
      final settingsResponse = await getUserAttendanceSettings(userId);
      if (settingsResponse.success && settingsResponse.data != null) {
        final data = settingsResponse.data!;
        final settings = AttendanceSettings.fromJson(data);
        final location = await _resolveLocationFromSchemaData(data);

        return AttendanceInfoResponse(
          success: true,
          gpsLocation: null,
          settings: settings,
          trackingPolicy: data['tracking_policy'] is Map<String, dynamic>
              ? Map<String, dynamic>.from(data['tracking_policy'])
              : (data['tracking_policy'] is Map
                  ? Map<String, dynamic>.from(data['tracking_policy'])
                  : null),
          schema: (data['schema_name'] ?? 'Skema Default').toString(),
          location: location,
          distance: 'Menghitung...',
          schemaType: (data['schema_type'] ?? '').toString(),
          schemaVersion: data['version'] is int
              ? data['version'] as int
              : int.tryParse((data['version'] ?? '').toString()),
          message: 'Data berhasil dimuat',
        );
      }

      return AttendanceInfoResponse(
        success: false,
        message: settingsResponse.message ?? 'Gagal memuat data.',
        schema: 'Tidak tersedia',
        location: 'Tidak ada lokasi',
        distance: 'Tidak tersedia',
      );
    } catch (e) {
      return AttendanceInfoResponse(
        success: false,
        message: 'Error: $e',
        schema: 'Tidak tersedia',
        location: 'Tidak ada lokasi',
        distance: 'Tidak tersedia',
      );
    }
  }

  Map<String, dynamic> _extractSettingsMap(Map<String, dynamic> payload) {
    final workingHours = payload['working_hours'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(payload['working_hours'])
        : <String, dynamic>{};
    final requirements = payload['requirements'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(payload['requirements'])
        : <String, dynamic>{};

    if (workingHours.isNotEmpty) {
      return {
        ...workingHours,
        'wajib_gps': requirements['wajib_gps'],
        'wajib_foto': requirements['wajib_foto'],
        'face_verification_enabled': requirements['face_verification_enabled'],
        'gps_accuracy': requirements['gps_accuracy'],
        'gps_accuracy_grace': requirements['gps_accuracy_grace'],
      };
    }

    if (payload['settings'] is Map<String, dynamic>) {
      return Map<String, dynamic>.from(payload['settings']);
    }

    return payload;
  }

  List<Map<String, dynamic>> _extractLocations(Map<String, dynamic> payload) {
    if (payload['locations'] is! List) {
      return const <Map<String, dynamic>>[];
    }

    return (payload['locations'] as List)
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  String _buildLocationLabel(List<Map<String, dynamic>> locations) {
    if (locations.isEmpty) {
      return 'Tidak ada lokasi aktif';
    }

    final names = locations
        .map((item) => (item['nama_lokasi'] ?? '').toString().trim())
        .where((name) => name.isNotEmpty)
        .toList();

    if (names.isEmpty) {
      return 'Tidak ada lokasi aktif';
    }

    if (names.length == 1) {
      return names.first;
    }

    return '${names.first} +${names.length - 1}';
  }

  List<int> _parseIntList(dynamic raw) {
    if (raw is List) {
      return raw
          .map((item) {
            if (item is int) return item;
            if (item is String) return int.tryParse(item);
            return null;
          })
          .whereType<int>()
          .toList();
    }

    if (raw is String) {
      final trimmed = raw.trim();
      if (trimmed.isEmpty) return const <int>[];
      try {
        final decoded = jsonDecode(trimmed);
        return _parseIntList(decoded);
      } catch (_) {
        return const <int>[];
      }
    }

    return const <int>[];
  }

  Future<List<Map<String, dynamic>>> _getActiveLocations() async {
    try {
      final response = await _apiService.get('/lokasi-gps/active');
      if (response.statusCode == 200 &&
          response.data != null &&
          response.data['success'] == true &&
          response.data['data'] is List) {
        return (response.data['data'] as List)
            .whereType<Map>()
            .map((item) => Map<String, dynamic>.from(item))
            .toList();
      }
    } catch (_) {}

    return const <Map<String, dynamic>>[];
  }

  Future<String> _resolveLocationFromSchemaData(Map<String, dynamic> data) async {
    final targetLocationIds = _parseIntList(data['lokasi_gps_ids']);
    if (targetLocationIds.isEmpty) {
      return 'Semua lokasi aktif';
    }

    final activeLocations = await _getActiveLocations();
    final selectedNames = activeLocations
        .where((item) => targetLocationIds.contains(item['id']))
        .map((item) => (item['nama_lokasi'] ?? '').toString().trim())
        .where((name) => name.isNotEmpty)
        .toList();

    if (selectedNames.isEmpty) {
      return 'Lokasi skema tidak ditemukan';
    }

    if (selectedNames.length == 1) {
      return selectedNames.first;
    }

    return '${selectedNames.first} +${selectedNames.length - 1}';
  }

  /// Check distance from current location to attendance locations
  Future<Map<String, dynamic>> checkDistanceToAttendanceLocation(
      double latitude, double longitude) async {
    try {
      final response =
          await _apiService.post('/lokasi-gps/check-distance', data: {
        'latitude': latitude,
        'longitude': longitude,
      });

      if (response.statusCode == 200 && response.data != null) {
        final data = response.data;
        if (data['success'] == true && data['data'] != null) {
          final distanceData = data['data'];
          final locations = distanceData['locations'] is List
              ? (distanceData['locations'] as List)
                  .whereType<Map>()
                  .map((item) => Map<String, dynamic>.from(item))
                  .toList()
              : <Map<String, dynamic>>[];
          Map<String, dynamic>? matchingLocation;
          for (final item in locations) {
            if (item['is_within_area'] == true) {
              matchingLocation = item;
              break;
            }
          }
          final nearestLocation = locations.isNotEmpty ? locations.first : null;

          return {
            'success': true,
            'can_attend': distanceData['can_attend'] ?? false,
            'nearest_distance': distanceData['nearest_distance'] ?? 0.0,
            'nearest_distance_formatted':
                distanceData['nearest_distance_formatted'] ?? '0 m',
            'locations': locations,
            'matching_location': matchingLocation,
            'nearest_location': nearestLocation,
          };
        }
      }

      return {
        'success': false,
        'can_attend': false,
        'nearest_distance': 0.0,
        'nearest_distance_formatted': '0 m',
        'locations': [],
      };
    } catch (e) {
      return {
        'success': false,
        'can_attend': false,
        'nearest_distance': 0.0,
        'nearest_distance_formatted': '0 m',
        'locations': [],
        'error': e.toString(),
      };
    }
  }

  /// Extract work hours from settings
  Map<String, String> extractWorkHours(Map<String, dynamic>? settings) {
    if (settings == null) {
      return {
        'jam_masuk': '08:00',
        'jam_pulang': '16:00',
      };
    }

    // Format time from HH:MM:SS to HH:MM if needed
    String formatTime(String? timeString) {
      if (timeString == null) return '08:00';
      final time = timeString.toString();
      if (time.length > 5 && time.contains(':')) {
        // If format is HH:MM:SS, convert to HH:MM
        return time.substring(0, 5);
      }
      return time;
    }

    String resolve(Map<String, dynamic> source, List<String> keys, String fallback) {
      for (final key in keys) {
        final raw = source[key];
        if (raw == null) continue;
        final value = formatTime(raw.toString());
        if (value.isNotEmpty) return value;
      }
      return fallback;
    }

    return {
      'jam_masuk': resolve(settings, ['siswa_jam_masuk', 'jam_masuk_default', 'jam_masuk'], '08:00'),
      'jam_pulang': resolve(settings, ['siswa_jam_pulang', 'jam_pulang_default', 'jam_pulang'], '16:00'),
    };
  }

  /// Get effective work hours for a specific user
  Future<Map<String, String>> getEffectiveWorkHours(int userId) async {
    try {
      final response = await getUserAttendanceSettings(userId);

      if (response.success && response.data != null) {
        return extractWorkHours(response.data);
      } else {
        // Fallback to default hours
        return {
          'jam_masuk': '08:00',
          'jam_pulang': '16:00',
        };
      }
    } catch (e) {
      // Fallback to default hours on error
      return {
        'jam_masuk': '08:00',
        'jam_pulang': '16:00',
      };
    }
  }
}
