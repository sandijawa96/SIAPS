import '../services/location_service.dart';
import '../services/attendance_settings_service.dart';

class AttendanceValidationService {
  static final AttendanceValidationService _instance =
      AttendanceValidationService._internal();
  factory AttendanceValidationService() => _instance;
  AttendanceValidationService._internal();

  final LocationService _locationService = LocationService();
  final AttendanceSettingsService _attendanceService =
      AttendanceSettingsService();

  /// Validate attendance area using backend geofence evaluation.
  Future<AttendanceValidationResult> validateAttendanceLocation(
      int userId) async {
    try {
      // Ensure attendance settings can still be resolved for the active user.
      final attendanceInfo = await _attendanceService.getAttendanceInfo(userId);

      if (!attendanceInfo.success) {
        return AttendanceValidationResult(
          isValid: false,
          message: 'Tidak dapat memuat pengaturan lokasi absensi',
          canProceed: false,
          failureType: 'system',
        );
      }

      // Get current user location
      final locationResult =
          await _locationService.getCurrentLocation(includeAddress: false);

      if (!locationResult.success ||
          locationResult.latitude == null ||
          locationResult.longitude == null) {
        return AttendanceValidationResult(
          isValid: false,
          message:
              'Tidak dapat mengakses lokasi GPS. Pastikan GPS aktif dan izin lokasi diberikan.',
          canProceed: false,
          failureType: 'system',
        );
      }

      final distanceCheck = await _attendanceService.checkDistanceToAttendanceLocation(
        locationResult.latitude!,
        locationResult.longitude!,
      );

      if (distanceCheck['success'] != true) {
        return AttendanceValidationResult(
          isValid: false,
          message: 'Tidak dapat memvalidasi area absensi dari server',
          canProceed: false,
          failureType: 'system',
          latitude: locationResult.latitude,
          longitude: locationResult.longitude,
          accuracy: locationResult.accuracy,
        );
      }

      final Map<String, dynamic>? matchingLocation =
          distanceCheck['matching_location'] is Map
              ? Map<String, dynamic>.from(distanceCheck['matching_location'])
              : null;
      final Map<String, dynamic>? nearestLocation =
          distanceCheck['nearest_location'] is Map
              ? Map<String, dynamic>.from(distanceCheck['nearest_location'])
              : null;
      final targetLocation = matchingLocation ?? nearestLocation;

      final distanceToArea = _parseDouble(
        targetLocation?['distance'] ?? distanceCheck['nearest_distance'],
      );
      final distanceToBoundary = _parseDouble(
        targetLocation?['distance_to_boundary'] ?? distanceToArea,
      );
      final allowedRadius = _parseDouble(targetLocation?['radius']);
      final geofenceType =
          (targetLocation?['geofence_type'] ?? 'circle').toString();
      final canAttend = distanceCheck['can_attend'] == true;
      final locationName =
          (targetLocation?['nama_lokasi'] ?? attendanceInfo.location ?? 'Lokasi Sekolah')
              .toString();

      if (canAttend) {
        final minimumAccuracy = attendanceInfo.settings?.gpsAccuracy ?? 20;
        final graceAccuracy = attendanceInfo.settings?.gpsAccuracyGrace ?? 0.0;
        final allowedAccuracy = minimumAccuracy.toDouble() + graceAccuracy;
        final currentAccuracy = locationResult.accuracy;

        if (currentAccuracy == null) {
          return AttendanceValidationResult(
            isValid: false,
            message:
                'Akurasi GPS tidak terbaca. Aktifkan mode lokasi akurasi tinggi lalu coba lagi.',
            canProceed: false,
            failureType: 'accuracy',
            distance: distanceToArea,
            distanceToBoundary: distanceToBoundary,
            allowedRadius: allowedRadius,
            locationName: locationName,
            locationId: _parseInt(targetLocation?['id']),
            geofenceType: geofenceType,
            latitude: locationResult.latitude,
            longitude: locationResult.longitude,
            accuracy: locationResult.accuracy,
          );
        }

        if (currentAccuracy > allowedAccuracy) {
          return AttendanceValidationResult(
            isValid: false,
            message:
                'Akurasi GPS ${currentAccuracy.toStringAsFixed(1)}m melebihi batas ${allowedAccuracy.toStringAsFixed(1)}m. Tunggu sinyal GPS stabil lalu coba lagi.',
            canProceed: false,
            failureType: 'accuracy',
            distance: distanceToArea,
            distanceToBoundary: distanceToBoundary,
            allowedRadius: allowedRadius,
            locationName: locationName,
            locationId: _parseInt(targetLocation?['id']),
            geofenceType: geofenceType,
            latitude: locationResult.latitude,
            longitude: locationResult.longitude,
            accuracy: locationResult.accuracy,
          );
        }

        return AttendanceValidationResult(
          isValid: true,
          message: 'Lokasi valid untuk absensi',
          canProceed: true,
          failureType: null,
          distance: distanceToArea,
          distanceToBoundary: distanceToBoundary,
          allowedRadius: allowedRadius,
          locationName: locationName,
          locationId: _parseInt(targetLocation?['id']),
          geofenceType: geofenceType,
          latitude: locationResult.latitude,
          longitude: locationResult.longitude,
          accuracy: locationResult.accuracy,
        );
      } else {
        return AttendanceValidationResult(
          isValid: false,
          message: 'Anda berada di luar area absensi yang diizinkan.\n'
              'Jarak ke area terdekat: ${distanceToArea.toStringAsFixed(0)}m\n'
              'Silakan mendekati lokasi $locationName',
          canProceed: false,
          failureType: 'location',
          distance: distanceToArea,
          distanceToBoundary: distanceToBoundary,
          allowedRadius: allowedRadius,
          locationName: locationName,
          locationId: _parseInt(targetLocation?['id']),
          geofenceType: geofenceType,
          latitude: locationResult.latitude,
          longitude: locationResult.longitude,
          accuracy: locationResult.accuracy,
        );
      }
    } catch (e) {
      return AttendanceValidationResult(
        isValid: false,
        message: 'Terjadi kesalahan saat memvalidasi lokasi: $e',
        canProceed: false,
        failureType: 'system',
      );
    }
  }

  double _parseDouble(dynamic value) {
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  int? _parseInt(dynamic value) {
    if (value is int) return value;
    if (value is String) return int.tryParse(value);
    return null;
  }

  /// Check if GPS-based attendance is required
  Future<bool> isGPSRequired(int userId) async {
    try {
      final attendanceInfo = await _attendanceService.getAttendanceInfo(userId);
      return attendanceInfo.success &&
          attendanceInfo.settings != null &&
          attendanceInfo.settings!.requireGPS;
    } catch (e) {
      return false;
    }
  }

  /// Check if selfie is required for attendance
  Future<bool> isSelfieRequired(int userId) async {
    try {
      final attendanceInfo = await _attendanceService.getAttendanceInfo(userId);
      return attendanceInfo.success &&
          attendanceInfo.settings != null &&
          attendanceInfo.settings!.requireSelfie;
    } catch (e) {
      return false;
    }
  }
}

class AttendanceValidationResult {
  final bool isValid;
  final String message;
  final bool canProceed;
  final String? failureType;
  final double? distance;
  final double? distanceToBoundary;
  final double? allowedRadius;
  final String? locationName;
  final int? locationId;
  final String? geofenceType;
  final double? latitude;
  final double? longitude;
  final double? accuracy;

  AttendanceValidationResult({
    required this.isValid,
    required this.message,
    required this.canProceed,
    this.failureType,
    this.distance,
    this.distanceToBoundary,
    this.allowedRadius,
    this.locationName,
    this.locationId,
    this.geofenceType,
    this.latitude,
    this.longitude,
    this.accuracy,
  });

  Map<String, dynamic> toJson() {
    return {
      'isValid': isValid,
      'message': message,
      'canProceed': canProceed,
      'failureType': failureType,
      'distance': distance,
      'distanceToBoundary': distanceToBoundary,
      'allowedRadius': allowedRadius,
      'locationName': locationName,
      'locationId': locationId,
      'geofenceType': geofenceType,
      'latitude': latitude,
      'longitude': longitude,
      'accuracy': accuracy,
    };
  }
}
