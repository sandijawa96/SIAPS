import 'dart:async';
import 'package:flutter/foundation.dart';
import 'package:geolocator/geolocator.dart';
import 'package:geocoding/geocoding.dart';
import 'package:permission_handler/permission_handler.dart'
    as permission_handler;

class LocationService {
  static const String failureCodeLocationServiceDisabled =
      'location_service_disabled';
  static const String failureCodePermissionDenied = 'permission_denied';
  static const String failureCodePermissionDeniedForever =
      'permission_denied_forever';
  static const String failureCodePermissionUnknown = 'permission_unknown';
  static const String failureCodeLocationError = 'location_error';

  static final LocationService _instance = LocationService._internal();
  factory LocationService() => _instance;
  LocationService._internal();

  /// Check and request location permissions
  Future<LocationPermissionResult> checkLocationPermission() async {
    try {
      // Check if location services are enabled
      bool serviceEnabled = await Geolocator.isLocationServiceEnabled();
      if (!serviceEnabled) {
        return LocationPermissionResult(
          isGranted: false,
          message: 'Layanan lokasi tidak aktif. Silakan aktifkan GPS.',
          canRequest: false,
          failureCode: failureCodeLocationServiceDisabled,
        );
      }

      // Check location permission
      LocationPermission permission = await Geolocator.checkPermission();

      switch (permission) {
        case LocationPermission.denied:
          return LocationPermissionResult(
            isGranted: false,
            message: 'Izin lokasi diperlukan untuk absensi.',
            canRequest: true,
            failureCode: failureCodePermissionDenied,
          );
        case LocationPermission.deniedForever:
          return LocationPermissionResult(
            isGranted: false,
            message:
                'Izin lokasi ditolak permanen. Silakan aktifkan di pengaturan.',
            canRequest: false,
            failureCode: failureCodePermissionDeniedForever,
          );
        case LocationPermission.whileInUse:
        case LocationPermission.always:
          return LocationPermissionResult(
            isGranted: true,
            message: 'Izin lokasi sudah diberikan.',
            canRequest: false,
          );
        default:
          return LocationPermissionResult(
            isGranted: false,
            message: 'Status izin lokasi tidak diketahui.',
            canRequest: true,
            failureCode: failureCodePermissionUnknown,
          );
      }
    } catch (e) {
      return LocationPermissionResult(
        isGranted: false,
        message: 'Error checking location permission: $e',
        canRequest: false,
        failureCode: failureCodeLocationError,
      );
    }
  }

  /// Request location permission
  Future<LocationPermissionResult> requestLocationPermission() async {
    try {
      LocationPermission permission = await Geolocator.requestPermission();

      switch (permission) {
        case LocationPermission.denied:
          return LocationPermissionResult(
            isGranted: false,
            message: 'Izin lokasi ditolak.',
            canRequest: true,
            failureCode: failureCodePermissionDenied,
          );
        case LocationPermission.deniedForever:
          return LocationPermissionResult(
            isGranted: false,
            message:
                'Izin lokasi ditolak permanen. Silakan aktifkan di pengaturan.',
            canRequest: false,
            failureCode: failureCodePermissionDeniedForever,
          );
        case LocationPermission.whileInUse:
        case LocationPermission.always:
          return LocationPermissionResult(
            isGranted: true,
            message: 'Izin lokasi berhasil diberikan.',
            canRequest: false,
          );
        default:
          return LocationPermissionResult(
            isGranted: false,
            message: 'Gagal mendapatkan izin lokasi.',
            canRequest: true,
            failureCode: failureCodePermissionUnknown,
          );
      }
    } catch (e) {
      return LocationPermissionResult(
        isGranted: false,
        message: 'Error requesting location permission: $e',
        canRequest: false,
        failureCode: failureCodeLocationError,
      );
    }
  }

  /// Get current location.
  ///
  /// Set [includeAddress] to false for latency-sensitive flows (attendance submit)
  /// to skip reverse-geocoding network call.
  Future<LocationResult> getCurrentLocation(
      {bool includeAddress = true}) async {
    try {
      // Check permission first
      final permissionResult = await checkLocationPermission();
      if (!permissionResult.isGranted) {
        return LocationResult(
          success: false,
          message: permissionResult.message,
          failureCode: permissionResult.failureCode,
        );
      }

      // Get current position
      final locationSettings = _buildTrackingLocationSettings(
        interval: const Duration(seconds: 10),
        distanceFilter: 0,
      );
      Position position = await Geolocator.getCurrentPosition(
        locationSettings: locationSettings,
      );

      String? address;
      if (includeAddress) {
        address = 'Alamat tidak tersedia';
        try {
          List<Placemark> placemarks = await placemarkFromCoordinates(
            position.latitude,
            position.longitude,
          );

          if (placemarks.isNotEmpty) {
            Placemark place = placemarks.first;
            address =
                '${place.street ?? ''}, ${place.subLocality ?? ''}, ${place.locality ?? ''}, ${place.administrativeArea ?? ''}';
          }
        } catch (e) {
          debugPrint('Error getting address: $e');
        }
      }

      return LocationResult(
        success: true,
        message: 'Lokasi berhasil didapatkan',
        latitude: position.latitude,
        longitude: position.longitude,
        accuracy: position.accuracy,
        speed: position.speed,
        heading: position.heading,
        isMocked: position.isMocked,
        address: address,
        timestamp: position.timestamp,
      );
    } catch (e) {
      return LocationResult(
        success: false,
        message: 'Gagal mendapatkan lokasi: $e',
        failureCode: failureCodeLocationError,
      );
    }
  }

  /// Stream lokasi yang dioptimalkan untuk live tracking.
  ///
  /// Tidak melakukan reverse-geocoding agar tetap ringan saat berjalan di
  /// foreground service/background isolate.
  Stream<LocationResult> getTrackingLocationStream({
    Duration interval = const Duration(seconds: 30),
    int distanceFilter = 0,
  }) async* {
    final permissionResult = await checkLocationPermission();
    if (!permissionResult.isGranted) {
      yield LocationResult(
        success: false,
        message: permissionResult.message,
        failureCode: permissionResult.failureCode,
      );
      return;
    }

    final locationSettings = _buildTrackingLocationSettings(
      interval: interval,
      distanceFilter: distanceFilter,
    );

    try {
      await for (final position
          in Geolocator.getPositionStream(locationSettings: locationSettings)) {
        yield _buildLocationResult(position);
      }
    } catch (e) {
      yield LocationResult(
        success: false,
        message: 'Error in tracking location stream: $e',
        failureCode: failureCodeLocationError,
      );
    }
  }

  /// Validate if current location is within allowed area
  Future<LocationValidationResult> validateLocation({
    required double allowedLatitude,
    required double allowedLongitude,
    required double radiusInMeters,
  }) async {
    try {
      final locationResult = await getCurrentLocation();

      if (!locationResult.success) {
        return LocationValidationResult(
          isValid: false,
          message: locationResult.message,
        );
      }

      // Calculate distance between current location and allowed location
      double distance = Geolocator.distanceBetween(
        locationResult.latitude!,
        locationResult.longitude!,
        allowedLatitude,
        allowedLongitude,
      );

      bool isWithinRadius = distance <= radiusInMeters;

      return LocationValidationResult(
        isValid: isWithinRadius,
        message: isWithinRadius
            ? 'Lokasi valid untuk absensi'
            : 'Anda berada di luar area yang diizinkan (${distance.toInt()}m dari lokasi)',
        currentLatitude: locationResult.latitude,
        currentLongitude: locationResult.longitude,
        allowedLatitude: allowedLatitude,
        allowedLongitude: allowedLongitude,
        distance: distance,
        accuracy: locationResult.accuracy,
        address: locationResult.address,
      );
    } catch (e) {
      return LocationValidationResult(
        isValid: false,
        message: 'Error validating location: $e',
      );
    }
  }

  /// Get location continuously for tracking
  Stream<LocationResult> getLocationStream() async* {
    final permissionResult = await checkLocationPermission();
    if (!permissionResult.isGranted) {
      yield LocationResult(
        success: false,
        message: permissionResult.message,
        failureCode: permissionResult.failureCode,
      );
      return;
    }

    final locationSettings = _buildTrackingLocationSettings(
      interval: const Duration(seconds: 10),
      distanceFilter: 10,
    );

    try {
      await for (final position
          in Geolocator.getPositionStream(locationSettings: locationSettings)) {
        yield _buildLocationResult(
          position,
          address: await _resolveAddress(position),
        );
      }
    } catch (e) {
      yield LocationResult(
        success: false,
        message: 'Error in location stream: $e',
        failureCode: failureCodeLocationError,
      );
    }
  }

  /// Calculate distance between two coordinates
  double calculateDistance({
    required double lat1,
    required double lon1,
    required double lat2,
    required double lon2,
  }) {
    return Geolocator.distanceBetween(lat1, lon1, lat2, lon2);
  }

  /// Open device location settings
  Future<void> openLocationSettings() async {
    await Geolocator.openLocationSettings();
  }

  /// Open app settings for permission management
  Future<void> openAppSettings() async {
    await permission_handler.openAppSettings();
  }

  LocationSettings _buildTrackingLocationSettings({
    required Duration interval,
    required int distanceFilter,
  }) {
    if (kIsWeb) {
      return LocationSettings(
        accuracy: LocationAccuracy.high,
        distanceFilter: distanceFilter,
      );
    }

    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return AndroidSettings(
          accuracy: LocationAccuracy.high,
          distanceFilter: distanceFilter,
          intervalDuration: interval,
        );
      case TargetPlatform.iOS:
      case TargetPlatform.macOS:
        return AppleSettings(
          accuracy: LocationAccuracy.best,
          distanceFilter: distanceFilter,
          activityType: ActivityType.otherNavigation,
          pauseLocationUpdatesAutomatically: false,
          showBackgroundLocationIndicator: false,
        );
      default:
        return LocationSettings(
          accuracy: LocationAccuracy.high,
          distanceFilter: distanceFilter,
        );
    }
  }

  Future<String?> _resolveAddress(Position position) async {
    try {
      List<Placemark> placemarks = await placemarkFromCoordinates(
        position.latitude,
        position.longitude,
      );

      if (placemarks.isEmpty) {
        return 'Alamat tidak tersedia';
      }

      final place = placemarks.first;
      return '${place.street ?? ''}, ${place.subLocality ?? ''}, ${place.locality ?? ''}, ${place.administrativeArea ?? ''}';
    } catch (e) {
      debugPrint('Error getting address in stream: $e');
      return 'Alamat tidak tersedia';
    }
  }

  LocationResult _buildLocationResult(Position position, {String? address}) {
    return LocationResult(
      success: true,
      message: 'Location updated',
      latitude: position.latitude,
      longitude: position.longitude,
      accuracy: position.accuracy,
      speed: position.speed,
      heading: position.heading,
      isMocked: position.isMocked,
      address: address,
      timestamp: position.timestamp,
    );
  }
}

class LocationPermissionResult {
  final bool isGranted;
  final String message;
  final bool canRequest;
  final String? failureCode;

  LocationPermissionResult({
    required this.isGranted,
    required this.message,
    required this.canRequest,
    this.failureCode,
  });

  Map<String, dynamic> toJson() {
    return {
      'isGranted': isGranted,
      'message': message,
      'canRequest': canRequest,
      'failureCode': failureCode,
    };
  }
}

class LocationResult {
  final bool success;
  final String message;
  final double? latitude;
  final double? longitude;
  final double? accuracy;
  final double? speed;
  final double? heading;
  final bool? isMocked;
  final String? address;
  final DateTime? timestamp;
  final String? failureCode;

  LocationResult({
    required this.success,
    required this.message,
    this.latitude,
    this.longitude,
    this.accuracy,
    this.speed,
    this.heading,
    this.isMocked,
    this.address,
    this.timestamp,
    this.failureCode,
  });

  Map<String, dynamic> toJson() {
    return {
      'success': success,
      'message': message,
      'latitude': latitude,
      'longitude': longitude,
      'accuracy': accuracy,
      'speed': speed,
      'heading': heading,
      'is_mocked': isMocked,
      'address': address,
      'timestamp': timestamp?.toIso8601String(),
      'failureCode': failureCode,
    };
  }
}

class LocationValidationResult {
  final bool isValid;
  final String message;
  final double? currentLatitude;
  final double? currentLongitude;
  final double? allowedLatitude;
  final double? allowedLongitude;
  final double? distance;
  final double? accuracy;
  final String? address;

  LocationValidationResult({
    required this.isValid,
    required this.message,
    this.currentLatitude,
    this.currentLongitude,
    this.allowedLatitude,
    this.allowedLongitude,
    this.distance,
    this.accuracy,
    this.address,
  });

  Map<String, dynamic> toJson() {
    return {
      'isValid': isValid,
      'message': message,
      'currentLatitude': currentLatitude,
      'currentLongitude': currentLongitude,
      'allowedLatitude': allowedLatitude,
      'allowedLongitude': allowedLongitude,
      'distance': distance,
      'accuracy': accuracy,
      'address': address,
    };
  }
}
