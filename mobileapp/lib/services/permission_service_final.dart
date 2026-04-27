import 'dart:io' show Platform;
import 'package:permission_handler/permission_handler.dart';
import 'package:permission_handler/permission_handler.dart'
    as permission_handler;
import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter/foundation.dart';

class PermissionService {
  static const String _tag = 'PermissionService';

  /// Check and request camera permission
  static Future<PermissionResult> checkCameraPermission() async {
    try {
      final status = await Permission.camera.status;
      debugPrint('$_tag: Camera permission status: $status');

      return PermissionResult(
        isGranted: status.isGranted,
        isDenied: status.isDenied,
        isPermanentlyDenied: status.isPermanentlyDenied,
        canRequest: !status.isPermanentlyDenied,
        message: _getPermissionMessage('Camera', status),
      );
    } catch (e) {
      debugPrint('$_tag: Error checking camera permission: $e');
      return PermissionResult(
        isGranted: false,
        isDenied: true,
        isPermanentlyDenied: false,
        canRequest: false,
        message: 'Error checking camera permission: $e',
      );
    }
  }

  /// Request camera permission
  static Future<PermissionResult> requestCameraPermission() async {
    try {
      final status = await Permission.camera.request();
      debugPrint('$_tag: Camera permission request result: $status');

      return PermissionResult(
        isGranted: status.isGranted,
        isDenied: status.isDenied,
        isPermanentlyDenied: status.isPermanentlyDenied,
        canRequest: !status.isPermanentlyDenied,
        message: _getPermissionMessage('Camera', status),
      );
    } catch (e) {
      debugPrint('$_tag: Error requesting camera permission: $e');
      return PermissionResult(
        isGranted: false,
        isDenied: true,
        isPermanentlyDenied: false,
        canRequest: false,
        message: 'Error requesting camera permission: $e',
      );
    }
  }

  /// Check and request location permission
  static Future<PermissionResult> checkLocationPermission() async {
    try {
      final status = await Permission.location.status;
      debugPrint('$_tag: Location permission status: $status');

      return PermissionResult(
        isGranted: status.isGranted,
        isDenied: status.isDenied,
        isPermanentlyDenied: status.isPermanentlyDenied,
        canRequest: !status.isPermanentlyDenied,
        message: _getPermissionMessage('Location', status),
      );
    } catch (e) {
      debugPrint('$_tag: Error checking location permission: $e');
      return PermissionResult(
        isGranted: false,
        isDenied: true,
        isPermanentlyDenied: false,
        canRequest: false,
        message: 'Error checking location permission: $e',
      );
    }
  }

  /// Request location permission
  static Future<PermissionResult> requestLocationPermission() async {
    try {
      final status = await Permission.location.request();
      debugPrint('$_tag: Location permission request result: $status');

      return PermissionResult(
        isGranted: status.isGranted,
        isDenied: status.isDenied,
        isPermanentlyDenied: status.isPermanentlyDenied,
        canRequest: !status.isPermanentlyDenied,
        message: _getPermissionMessage('Location', status),
      );
    } catch (e) {
      debugPrint('$_tag: Error requesting location permission: $e');
      return PermissionResult(
        isGranted: false,
        isDenied: true,
        isPermanentlyDenied: false,
        canRequest: false,
        message: 'Error requesting location permission: $e',
      );
    }
  }

  /// Check Android background location permission.
  static Future<PermissionResult> checkBackgroundLocationPermission() async {
    try {
      if (kIsWeb || !Platform.isAndroid) {
        return const PermissionResult(
          isGranted: true,
          isDenied: false,
          isPermanentlyDenied: false,
          canRequest: false,
          message: 'Background location tidak diperlukan pada platform ini.',
        );
      }

      final status = await Permission.locationAlways.status;
      debugPrint('$_tag: Background location permission status: $status');

      return PermissionResult(
        isGranted: status.isGranted,
        isDenied: status.isDenied,
        isPermanentlyDenied: status.isPermanentlyDenied,
        canRequest: !status.isPermanentlyDenied,
        message: _getPermissionMessage('Background location', status),
      );
    } catch (e) {
      debugPrint('$_tag: Error checking background location permission: $e');
      return PermissionResult(
        isGranted: false,
        isDenied: true,
        isPermanentlyDenied: false,
        canRequest: false,
        message: 'Error checking background location permission: $e',
      );
    }
  }

  /// Request Android background location permission.
  static Future<PermissionResult> requestBackgroundLocationPermission() async {
    try {
      if (kIsWeb || !Platform.isAndroid) {
        return const PermissionResult(
          isGranted: true,
          isDenied: false,
          isPermanentlyDenied: false,
          canRequest: false,
          message: 'Background location tidak diperlukan pada platform ini.',
        );
      }

      final status = await Permission.locationAlways.request();
      debugPrint('$_tag: Background location permission request result: $status');

      return PermissionResult(
        isGranted: status.isGranted,
        isDenied: status.isDenied,
        isPermanentlyDenied: status.isPermanentlyDenied,
        canRequest: !status.isPermanentlyDenied,
        message: _getPermissionMessage('Background location', status),
      );
    } catch (e) {
      debugPrint('$_tag: Error requesting background location permission: $e');
      return PermissionResult(
        isGranted: false,
        isDenied: true,
        isPermanentlyDenied: false,
        canRequest: false,
        message: 'Error requesting background location permission: $e',
      );
    }
  }

  /// Check media/storage permission.
  static Future<PermissionResult> checkStoragePermission() async {
    try {
      if (kIsWeb) {
        return const PermissionResult(
          isGranted: true,
          isDenied: false,
          isPermanentlyDenied: false,
          canRequest: false,
          message: 'Storage permission tidak diperlukan pada web.',
        );
      }

      if (Platform.isIOS) {
        final status = await Permission.photos.status;
        final isGranted = status.isGranted || status.isLimited;
        debugPrint('$_tag: Photos permission status (iOS): $status');

        return PermissionResult(
          isGranted: isGranted,
          isDenied: !isGranted,
          isPermanentlyDenied: status.isPermanentlyDenied,
          canRequest: !status.isPermanentlyDenied,
          message: _getPermissionMessage('Photos', status),
        );
      }

      if (!Platform.isAndroid) {
        return const PermissionResult(
          isGranted: true,
          isDenied: false,
          isPermanentlyDenied: false,
          canRequest: false,
          message: 'Storage permission tidak diperlukan pada platform ini.',
        );
      }

      final DeviceInfoPlugin info = DeviceInfoPlugin();
      final AndroidDeviceInfo androidInfo = await info.androidInfo;
      final int sdkInt = androidInfo.version.sdkInt;

      if (sdkInt >= 33) {
        final photosStatus = await Permission.photos.status;
        final videosStatus = await Permission.videos.status;
        debugPrint(
          '$_tag: Storage permission status (Android 13+): photos=$photosStatus, videos=$videosStatus',
        );

        final isGranted = photosStatus.isGranted || videosStatus.isGranted;
        final isPermanentlyDenied = photosStatus.isPermanentlyDenied &&
            videosStatus.isPermanentlyDenied;

        return PermissionResult(
          isGranted: isGranted,
          isDenied: !isGranted,
          isPermanentlyDenied: isPermanentlyDenied,
          canRequest: !isPermanentlyDenied,
          message: isGranted
              ? 'Media permission granted for Android 13+'
              : 'Media permission denied for Android 13+',
        );
      }

      final status = await Permission.storage.status;
      debugPrint('$_tag: Storage permission status: $status');

      if (status.isGranted) {
        return PermissionResult(
          isGranted: true,
          isDenied: false,
          isPermanentlyDenied: false,
          canRequest: true,
          message: _getPermissionMessage('Storage', status),
        );
      }

      return PermissionResult(
        isGranted: status.isGranted,
        isDenied: status.isDenied,
        isPermanentlyDenied: status.isPermanentlyDenied,
        canRequest: !status.isPermanentlyDenied,
        message: _getPermissionMessage('Storage', status),
      );
    } catch (e) {
      debugPrint('$_tag: Error checking storage permission: $e');
      return PermissionResult(
        isGranted: false,
        isDenied: true,
        isPermanentlyDenied: false,
        canRequest: false,
        message: 'Error checking storage permission: $e',
      );
    }
  }

  /// Request media/storage permission.
  static Future<PermissionResult> requestStoragePermission() async {
    try {
      if (kIsWeb) {
        return const PermissionResult(
          isGranted: true,
          isDenied: false,
          isPermanentlyDenied: false,
          canRequest: false,
          message: 'Storage permission tidak diperlukan pada web.',
        );
      }

      if (Platform.isIOS) {
        final status = await Permission.photos.request();
        final isGranted = status.isGranted || status.isLimited;
        debugPrint('$_tag: Photos permission request result (iOS): $status');

        return PermissionResult(
          isGranted: isGranted,
          isDenied: !isGranted,
          isPermanentlyDenied: status.isPermanentlyDenied,
          canRequest: !status.isPermanentlyDenied,
          message: _getPermissionMessage('Photos', status),
        );
      }

      if (!Platform.isAndroid) {
        return const PermissionResult(
          isGranted: true,
          isDenied: false,
          isPermanentlyDenied: false,
          canRequest: false,
          message: 'Storage permission tidak diperlukan pada platform ini.',
        );
      }

      final DeviceInfoPlugin info = DeviceInfoPlugin();
      final AndroidDeviceInfo androidInfo = await info.androidInfo;
      final int sdkInt = androidInfo.version.sdkInt;

      debugPrint('$_tag: Android SDK: $sdkInt, Model: ${androidInfo.model}');

      bool isGranted = false;
      String message = '';
      PermissionStatus finalStatus = PermissionStatus.denied;

      if (sdkInt >= 33) {
        // Android 13+ (API 33+) - Use granular media permissions
        debugPrint('$_tag: Requesting Android 13+ media permissions...');

        // Try photos permission first (most critical for camera)
        final photosStatus = await Permission.photos.request();
        debugPrint('$_tag: Photos permission: $photosStatus');

        if (photosStatus.isGranted) {
          isGranted = true;
          finalStatus = photosStatus;
          message = 'Photos permission granted for Android 13+';
        } else {
          // Try videos as fallback
          final videosStatus = await Permission.videos.request();
          debugPrint('$_tag: Videos permission: $videosStatus');

          if (videosStatus.isGranted) {
            isGranted = true;
            finalStatus = videosStatus;
            message = 'Videos permission granted for Android 13+';
          } else {
            finalStatus = photosStatus;
            message =
                'Media permissions denied for Android 13+. Please enable Photos/Videos permission in app settings.';
          }
        }
      } else if (sdkInt >= 30) {
        // Android 11-12 (API 30-32) - Try storage + manage external storage
        debugPrint('$_tag: Requesting Android 11-12 permissions...');

        final storageStatus = await Permission.storage.request();
        if (storageStatus.isGranted) {
          isGranted = true;
          finalStatus = storageStatus;
          message = 'Storage permission granted for Android 11-12';
        } else {
          // Try manage external storage as fallback
          try {
            final manageStatus =
                await Permission.manageExternalStorage.request();
            if (manageStatus.isGranted) {
              isGranted = true;
              finalStatus = manageStatus;
              message = 'Manage external storage granted for Android 11-12';
            } else {
              finalStatus = storageStatus;
              message =
                  'Storage permissions denied for Android 11-12. Please enable Storage permission in app settings.';
            }
          } catch (e) {
            finalStatus = storageStatus;
            message = 'Storage permission denied for Android 11-12';
          }
        }
      } else {
        // Android 10 and below (API 29-) - Legacy storage
        debugPrint('$_tag: Requesting legacy storage permission...');
        final status = await Permission.storage.request();
        isGranted = status.isGranted;
        finalStatus = status;
        message = _getPermissionMessage('Storage', status);
      }

      // If no permission granted, guide user to app settings
      if (!isGranted) {
        debugPrint(
            '$_tag: ⚠️ No storage permissions granted, user needs manual setup');
        message +=
            ' Please go to App Settings → Permissions and enable storage/media access.';
      }

      return PermissionResult(
        isGranted: isGranted,
        isDenied: !isGranted,
        isPermanentlyDenied: finalStatus.isPermanentlyDenied,
        canRequest: !finalStatus.isPermanentlyDenied,
        message: message,
      );
    } catch (e) {
      debugPrint('$_tag: ❌ Error requesting storage permission: $e');

      if (!Platform.isAndroid) {
        return PermissionResult(
          isGranted: false,
          isDenied: true,
          isPermanentlyDenied: false,
          canRequest: false,
          message: 'Critical error requesting media permission: $e',
        );
      }

      // Emergency fallback - try basic storage permission
      try {
        final fallbackStatus = await Permission.storage.request();
        return PermissionResult(
          isGranted: fallbackStatus.isGranted,
          isDenied: !fallbackStatus.isGranted,
          isPermanentlyDenied: fallbackStatus.isPermanentlyDenied,
          canRequest: !fallbackStatus.isPermanentlyDenied,
          message:
              'Fallback storage permission: ${_getPermissionMessage('Storage', fallbackStatus)}',
        );
      } catch (fallbackError) {
        return PermissionResult(
          isGranted: false,
          isDenied: true,
          isPermanentlyDenied: false,
          canRequest: false,
          message: 'Critical error requesting storage permission: $e',
        );
      }
    }
  }

  /// Check all required permissions for attendance
  static Future<Map<String, PermissionResult>>
      checkAllAttendancePermissions() async {
    final results = <String, PermissionResult>{};

    results['camera'] = await checkCameraPermission();
    results['location'] = await checkLocationPermission();
    results['storage'] = await checkStoragePermission();

    return results;
  }

  /// Request all required permissions for attendance
  static Future<Map<String, PermissionResult>>
      requestAllAttendancePermissions() async {
    final results = <String, PermissionResult>{};

    // Request permissions one by one
    results['camera'] = await requestCameraPermission();
    results['location'] = await requestLocationPermission();
    results['storage'] = await requestStoragePermission();

    return results;
  }

  /// Check if all attendance permissions are granted
  static Future<bool> areAllAttendancePermissionsGranted() async {
    final results = await checkAllAttendancePermissions();
    return results.values.every((result) => result.isGranted);
  }

  /// Open app settings - FIXED: Use correct permission_handler method
  static Future<bool> openAppSettings() async {
    try {
      return await permission_handler.openAppSettings();
    } catch (e) {
      debugPrint('$_tag: Error opening app settings: $e');
      return false;
    }
  }

  /// Get permission message based on status
  static String _getPermissionMessage(
      String permissionName, PermissionStatus status) {
    switch (status) {
      case PermissionStatus.granted:
        return '$permissionName permission granted';
      case PermissionStatus.denied:
        return '$permissionName permission denied';
      case PermissionStatus.permanentlyDenied:
        return '$permissionName permission permanently denied. Please enable it in app settings.';
      case PermissionStatus.restricted:
        return '$permissionName permission restricted';
      case PermissionStatus.limited:
        return '$permissionName permission limited';
      case PermissionStatus.provisional:
        return '$permissionName permission provisional';
    }
  }
}

/// Result class for permission operations
class PermissionResult {
  final bool isGranted;
  final bool isDenied;
  final bool isPermanentlyDenied;
  final bool canRequest;
  final String message;

  const PermissionResult({
    required this.isGranted,
    required this.isDenied,
    required this.isPermanentlyDenied,
    required this.canRequest,
    required this.message,
  });

  @override
  String toString() {
    return 'PermissionResult(isGranted: $isGranted, isDenied: $isDenied, '
        'isPermanentlyDenied: $isPermanentlyDenied, canRequest: $canRequest, '
        'message: $message)';
  }
}
