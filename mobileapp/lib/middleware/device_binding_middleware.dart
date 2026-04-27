import 'dart:io';
import 'dart:math';

import 'package:device_info_plus/device_info_plus.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import '../services/api_service.dart';
import '../services/app_info_service.dart';
import '../services/device_security_service.dart';

class DeviceBindingMiddleware {
  static final DeviceInfoPlugin _deviceInfo = DeviceInfoPlugin();
  static final ApiService _apiService = ApiService();
  static const FlutterSecureStorage _secureStorage = FlutterSecureStorage();
  static const String _installDeviceIdKey = 'siaps_install_device_id';

  /// Get a stable per-installation device ID for SIAPS binding.
  static Future<String> getDeviceId() async {
    try {
      final stored = (await _secureStorage.read(key: _installDeviceIdKey))
          ?.trim();
      if (stored != null && stored.isNotEmpty) {
        return stored;
      }

      final generated = 'siaps-${_generateSecureHex(16)}';
      await _secureStorage.write(
        key: _installDeviceIdKey,
        value: generated,
      );

      return generated;
    } catch (e) {
      print('Error getting device ID: $e');
      return '';
    }
  }

  static String _generateSecureHex(int byteLength) {
    final random = Random.secure();
    final buffer = StringBuffer();

    for (var i = 0; i < byteLength; i += 1) {
      buffer.write(random.nextInt(256).toRadixString(16).padLeft(2, '0'));
    }

    return buffer.toString();
  }

  /// Get device name
  static Future<String> getDeviceName() async {
    try {
      if (Platform.isAndroid) {
        final androidInfo = await _deviceInfo.androidInfo;
        return '${androidInfo.brand} ${androidInfo.model}';
      } else if (Platform.isIOS) {
        final iosInfo = await _deviceInfo.iosInfo;
        return '${iosInfo.name} (${iosInfo.model})';
      }
      return 'Unknown Device';
    } catch (e) {
      print('Error getting device name: $e');
      return 'Unknown Device';
    }
  }

  /// Get device info for binding
  static Future<Map<String, dynamic>> getDeviceInfo() async {
    try {
      final appInfoService = AppInfoService();
      final packageInfo = await appInfoService.getPackageInfo();
      final appVersion = await appInfoService.getCurrentVersion();
      final appBuildNumber = await appInfoService.getCurrentBuildNumber();
      final appVersionLabel = await appInfoService.getVersionLabel(
        includeBuild: true,
        includeAppName: true,
      );
      final securitySignals = await DeviceSecurityService().collectSignals();

      if (Platform.isAndroid) {
        final androidInfo = await _deviceInfo.androidInfo;
        return {
          'platform': 'Android',
          'brand': androidInfo.brand,
          'model': androidInfo.model,
          'version': androidInfo.version.release,
          'sdk_int': androidInfo.version.sdkInt,
          'manufacturer': androidInfo.manufacturer,
          'device': androidInfo.device,
          'hardware': androidInfo.hardware,
          'legacy_android_build_id': androidInfo.id,
          'is_physical_device': androidInfo.isPhysicalDevice,
          'emulator_detected': !androidInfo.isPhysicalDevice,
          'package_name': packageInfo.packageName,
          'app_version': appVersion,
          'app_build_number': appBuildNumber,
          'app_version_label': appVersionLabel,
          ...securitySignals,
        };
      } else if (Platform.isIOS) {
        final iosInfo = await _deviceInfo.iosInfo;
        return {
          'platform': 'iOS',
          'name': iosInfo.name,
          'model': iosInfo.model,
          'system_name': iosInfo.systemName,
          'system_version': iosInfo.systemVersion,
          'localized_model': iosInfo.localizedModel,
          'identifier_for_vendor': iosInfo.identifierForVendor,
          'is_physical_device': iosInfo.isPhysicalDevice,
          'emulator_detected': !iosInfo.isPhysicalDevice,
          'package_name': packageInfo.packageName,
          'app_version': appVersion,
          'app_build_number': appBuildNumber,
          'app_version_label': appVersionLabel,
          ...securitySignals,
        };
      }
      return {
        'platform': 'Unknown',
        'package_name': packageInfo.packageName,
        'app_version': appVersion,
        'app_build_number': appBuildNumber,
        'app_version_label': appVersionLabel,
      };
    } catch (e) {
      print('Error getting device info: $e');
      return {'platform': 'Unknown', 'error': e.toString()};
    }
  }

  /// Check device binding status
  static Future<DeviceBindingStatus> checkDeviceBinding() async {
    try {
      final response = await _apiService.get('/device-binding/status');

      if (response.statusCode == 200 && response.data != null) {
        final data = response.data;
        if (data['success'] == true) {
          return DeviceBindingStatus.fromJson(data['data']);
        }
      }

      return DeviceBindingStatus(
        isBound: false,
        canBind: true,
        deviceId: null,
        deviceName: null,
        boundAt: null,
      );
    } catch (e) {
      print('Error checking device binding: $e');
      return DeviceBindingStatus(
        isBound: false,
        canBind: true,
        deviceId: null,
        deviceName: null,
        boundAt: null,
      );
    }
  }

  /// Bind current device to user account
  static Future<DeviceBindingResult> bindDevice() async {
    try {
      final deviceId = await getDeviceId();
      final deviceName = await getDeviceName();
      final deviceInfo = await getDeviceInfo();

      if (deviceId.isEmpty) {
        return DeviceBindingResult(
          success: false,
          message: 'Tidak dapat mengidentifikasi device',
        );
      }

      final response = await _apiService.post('/device-binding/bind', data: {
        'device_id': deviceId,
        'device_name': deviceName,
        'device_info': deviceInfo,
      });

      if (response.statusCode == 200 && response.data != null) {
        final data = response.data;
        return DeviceBindingResult(
          success: data['success'] ?? false,
          message: data['message'] ?? 'Unknown response',
          data: data['data'],
        );
      } else if (response.statusCode == 403) {
        // Device already bound to another account
        final data = response.data;
        return DeviceBindingResult(
          success: false,
          message: data['message'] ?? 'Device sudah terikat dengan akun lain',
          isAlreadyBound: true,
          data: data['data'],
        );
      } else {
        return DeviceBindingResult(
          success: false,
          message: 'Gagal mengikat device: ${response.statusCode}',
        );
      }
    } catch (e) {
      print('Error binding device: $e');
      return DeviceBindingResult(
        success: false,
        message: 'Error: $e',
      );
    }
  }

  /// Validate device access
  static Future<DeviceAccessResult> validateDeviceAccess() async {
    try {
      final deviceId = await getDeviceId();

      final response =
          await _apiService.post('/device-binding/validate', data: {
        'device_id': deviceId,
      });

      if (response.statusCode == 200 && response.data != null) {
        final data = response.data;
        return DeviceAccessResult(
          success: data['success'] ?? false,
          message: data['message'] ?? 'Unknown response',
          requiresBinding: data['requires_binding'] ?? false,
        );
      } else if (response.statusCode == 403) {
        // Device access denied
        final data = response.data;
        return DeviceAccessResult(
          success: false,
          message: data['message'] ?? 'Akses ditolak',
          requiresBinding: false,
          isBlocked: true,
          data: data['data'],
        );
      } else {
        return DeviceAccessResult(
          success: false,
          message: 'Gagal validasi device: ${response.statusCode}',
          requiresBinding: true,
        );
      }
    } catch (e) {
      print('Error validating device access: $e');
      return DeviceAccessResult(
        success: false,
        message: 'Error: $e',
        requiresBinding: true,
      );
    }
  }

  /// Auto-bind device if needed
  static Future<bool> autoBindIfNeeded() async {
    try {
      // Check current binding status
      final status = await checkDeviceBinding();

      if (!status.isBound && status.canBind) {
        // Try to bind device automatically
        final result = await bindDevice();
        if (result.success) {
          print('Device auto-bound successfully');
          return true;
        } else if (result.isAlreadyBound) {
          print('Device already bound to another account');
          return false;
        } else {
          print('Failed to auto-bind device: ${result.message}');
          return false;
        }
      } else if (status.isBound) {
        // Device already bound, validate access
        final accessResult = await validateDeviceAccess();
        return accessResult.success;
      }

      return true;
    } catch (e) {
      print('Error in auto-bind: $e');
      return false;
    }
  }
}

class DeviceBindingStatus {
  final bool isBound;
  final bool canBind;
  final String? deviceId;
  final String? deviceName;
  final DateTime? boundAt;

  DeviceBindingStatus({
    required this.isBound,
    required this.canBind,
    this.deviceId,
    this.deviceName,
    this.boundAt,
  });

  factory DeviceBindingStatus.fromJson(Map<String, dynamic> json) {
    return DeviceBindingStatus(
      isBound: json['is_bound'] ?? false,
      canBind: json['can_bind'] ?? false,
      deviceId: json['device_id'],
      deviceName: json['device_name'],
      boundAt:
          json['bound_at'] != null ? DateTime.parse(json['bound_at']) : null,
    );
  }
}

class DeviceBindingResult {
  final bool success;
  final String message;
  final bool isAlreadyBound;
  final Map<String, dynamic>? data;

  DeviceBindingResult({
    required this.success,
    required this.message,
    this.isAlreadyBound = false,
    this.data,
  });
}

class DeviceAccessResult {
  final bool success;
  final String message;
  final bool requiresBinding;
  final bool isBlocked;
  final Map<String, dynamic>? data;

  DeviceAccessResult({
    required this.success,
    required this.message,
    required this.requiresBinding,
    this.isBlocked = false,
    this.data,
  });
}
