import 'dart:io';

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

class DeviceSecurityService {
  DeviceSecurityService._();
  static final DeviceSecurityService _instance = DeviceSecurityService._();
  factory DeviceSecurityService() => _instance;

  static const MethodChannel _channel = MethodChannel('siaps/device_security');
  static const Duration _cacheTtl = Duration(minutes: 5);

  Map<String, dynamic>? _cachedSignals;
  DateTime? _cachedAt;

  Future<Map<String, dynamic>> collectSignals({
    bool forceRefresh = false,
  }) async {
    if (!Platform.isAndroid) {
      return const <String, dynamic>{
        'platform_security_supported': false,
      };
    }

    final now = DateTime.now();
    if (!forceRefresh &&
        _cachedSignals != null &&
        _cachedAt != null &&
        now.difference(_cachedAt!) < _cacheTtl) {
      return Map<String, dynamic>.from(_cachedSignals!);
    }

    try {
      final dynamic result =
          await _channel.invokeMethod<dynamic>('collectSecuritySignals');

      if (result is Map) {
        final signals = Map<String, dynamic>.from(
          result.map((key, value) => MapEntry(key.toString(), value)),
        );
        signals['platform_security_supported'] = true;
        _cachedSignals = signals;
        _cachedAt = now;
        return Map<String, dynamic>.from(signals);
      }
    } on PlatformException catch (error) {
      debugPrint('Device security collection failed: ${error.message}');
    } catch (error) {
      debugPrint('Unexpected device security error: $error');
    }

    return const <String, dynamic>{
      'platform_security_supported': false,
    };
  }
}
