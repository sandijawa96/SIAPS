import 'dart:math';
import 'package:geolocator/geolocator.dart';
import '../models/location_validation_result.dart';

class LocationValidationService {
  // Koordinat default sekolah (contoh: Jakarta)
  static const double defaultSchoolLatitude = -6.2088;
  static const double defaultSchoolLongitude = 106.8456;
  static const double defaultAllowedRadius = 100.0; // 100 meter
  static const String defaultLocationName = 'Sekolah';

  /// Validasi lokasi berdasarkan koordinat saat ini
  Future<LocationValidationResult> validateCurrentLocation({
    double? schoolLatitude,
    double? schoolLongitude,
    double? allowedRadius,
    String? locationName,
  }) async {
    try {
      // Gunakan nilai default jika tidak disediakan
      final double schoolLat = schoolLatitude ?? defaultSchoolLatitude;
      final double schoolLng = schoolLongitude ?? defaultSchoolLongitude;
      final double radius = allowedRadius ?? defaultAllowedRadius;
      final String locName = locationName ?? defaultLocationName;

      // Dapatkan lokasi saat ini
      Position currentPosition = await _getCurrentPosition();

      // Hitung jarak
      double distance = _calculateDistance(
        currentPosition.latitude,
        currentPosition.longitude,
        schoolLat,
        schoolLng,
      );

      // Validasi apakah dalam radius
      bool isValid = distance <= radius;

      return LocationValidationResult(
        isValid: isValid,
        currentDistance: distance,
        allowedRadius: radius,
        locationName: locName,
        message: isValid
            ? 'Lokasi valid untuk absensi'
            : 'Anda berada diluar radius absensi yang diizinkan',
        suggestion: isValid
            ? null
            : 'Mendekatlah ke area $locName untuk melakukan absensi',
      );
    } catch (e) {
      return LocationValidationResult(
        isValid: false,
        currentDistance: 0.0,
        allowedRadius: allowedRadius ?? defaultAllowedRadius,
        locationName: locationName ?? defaultLocationName,
        message: 'Gagal mendapatkan lokasi: ${e.toString()}',
        suggestion: 'Pastikan GPS aktif dan izin lokasi telah diberikan',
      );
    }
  }

  /// Mendapatkan posisi saat ini
  Future<Position> _getCurrentPosition() async {
    bool serviceEnabled;
    LocationPermission permission;

    // Cek apakah location service aktif
    serviceEnabled = await Geolocator.isLocationServiceEnabled();
    if (!serviceEnabled) {
      throw Exception('Location service tidak aktif');
    }

    // Cek permission
    permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
      if (permission == LocationPermission.denied) {
        throw Exception('Location permission ditolak');
      }
    }

    if (permission == LocationPermission.deniedForever) {
      throw Exception('Location permission ditolak permanen');
    }

    // Dapatkan posisi saat ini
    return await Geolocator.getCurrentPosition(
      desiredAccuracy: LocationAccuracy.high,
    );
  }

  /// Menghitung jarak antara dua koordinat menggunakan Haversine formula
  double _calculateDistance(
      double lat1, double lng1, double lat2, double lng2) {
    const double earthRadius = 6371000; // Radius bumi dalam meter

    double dLat = _degreesToRadians(lat2 - lat1);
    double dLng = _degreesToRadians(lng2 - lng1);

    double a = sin(dLat / 2) * sin(dLat / 2) +
        cos(_degreesToRadians(lat1)) *
            cos(_degreesToRadians(lat2)) *
            sin(dLng / 2) *
            sin(dLng / 2);

    double c = 2 * atan2(sqrt(a), sqrt(1 - a));

    return earthRadius * c;
  }

  /// Konversi derajat ke radian
  double _degreesToRadians(double degrees) {
    return degrees * (pi / 180);
  }

  /// Validasi dengan koordinat custom
  Future<LocationValidationResult> validateWithCoordinates({
    required double currentLatitude,
    required double currentLongitude,
    required double schoolLatitude,
    required double schoolLongitude,
    double? allowedRadius,
    String? locationName,
  }) async {
    final double radius = allowedRadius ?? defaultAllowedRadius;
    final String locName = locationName ?? defaultLocationName;

    double distance = _calculateDistance(
      currentLatitude,
      currentLongitude,
      schoolLatitude,
      schoolLongitude,
    );

    bool isValid = distance <= radius;

    return LocationValidationResult(
      isValid: isValid,
      currentDistance: distance,
      allowedRadius: radius,
      locationName: locName,
      message: isValid
          ? 'Lokasi valid untuk absensi'
          : 'Anda berada diluar radius absensi yang diizinkan',
      suggestion: isValid
          ? null
          : 'Mendekatlah ke area $locName untuk melakukan absensi',
    );
  }

  /// Format jarak untuk display
  String formatDistance(double distanceInMeters) {
    if (distanceInMeters < 1000) {
      return '${distanceInMeters.toStringAsFixed(1)} m';
    } else {
      double distanceInKm = distanceInMeters / 1000;
      return '${distanceInKm.toStringAsFixed(2)} km';
    }
  }

  /// Cek apakah location service tersedia
  Future<bool> isLocationServiceAvailable() async {
    try {
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      LocationPermission permission = await Geolocator.checkPermission();

      return serviceEnabled &&
          permission != LocationPermission.denied &&
          permission != LocationPermission.deniedForever;
    } catch (e) {
      return false;
    }
  }
}
