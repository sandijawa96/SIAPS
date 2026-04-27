import 'dart:async';

import 'package:flutter/services.dart';

import '../models/guard_event.dart';

class BatteryInfo {
  const BatteryInfo({required this.level, required this.isCharging});

  final int level;
  final bool isCharging;
}

class DndStatus {
  const DndStatus({
    required this.supported,
    required this.permissionGranted,
    required this.enabled,
    required this.label,
  });

  final bool supported;
  final bool permissionGranted;
  final bool enabled;
  final String label;
}

class OverlayProtectionStatus {
  const OverlayProtectionStatus({
    required this.supported,
    required this.enabled,
  });

  final bool supported;
  final bool enabled;
}

class ScreenPinningStatus {
  const ScreenPinningStatus({
    required this.supported,
    required this.active,
    required this.canRequest,
    required this.label,
  });

  final bool supported;
  final bool active;
  final bool canRequest;
  final String label;
}

class ExamGuardService {
  ExamGuardService._();

  static final instance = ExamGuardService._();
  static const _channel = MethodChannel('id.sch.sman1sumbercirebon.sbt/guard');

  final _events = StreamController<GuardEvent>.broadcast();
  bool _initialized = false;

  Stream<GuardEvent> get events => _events.stream;

  void initialize() {
    if (_initialized) return;
    _initialized = true;

    _channel.setMethodCallHandler((call) async {
      if (call.method == 'guardEvent' && call.arguments is Map) {
        _events.add(
          GuardEvent.fromMap(call.arguments as Map<dynamic, dynamic>),
        );
      }
    });
  }

  Future<void> enableExamGuard({bool requireScreenPinning = true}) async {
    await _channel.invokeMethod<void>('enableExamGuard', {
      'requireScreenPinning': requireScreenPinning,
    });
  }

  Future<void> disableExamGuard() async {
    await _channel.invokeMethod<void>('disableExamGuard');
  }

  Future<bool> isInMultiWindowMode() async {
    return await _channel.invokeMethod<bool>('isInMultiWindowMode') ?? false;
  }

  Future<BatteryInfo> getBatteryInfo() async {
    final result = await _channel.invokeMapMethod<String, dynamic>(
      'getBatteryInfo',
    );

    return BatteryInfo(
      level: (result?['level'] as num?)?.toInt() ?? -1,
      isCharging: result?['isCharging'] == true,
    );
  }

  Future<DndStatus> getDoNotDisturbStatus() async {
    final result = await _channel.invokeMapMethod<String, dynamic>(
      'getDoNotDisturbStatus',
    );

    return DndStatus(
      supported: result?['supported'] == true,
      permissionGranted: result?['permissionGranted'] == true,
      enabled: result?['enabled'] == true,
      label: result?['label']?.toString() ?? 'Tidak diketahui',
    );
  }

  Future<OverlayProtectionStatus> getOverlayProtectionStatus() async {
    final result = await _channel.invokeMapMethod<String, dynamic>(
      'getOverlayProtectionStatus',
    );

    return OverlayProtectionStatus(
      supported: result?['supported'] == true,
      enabled: result?['enabled'] == true,
    );
  }

  Future<ScreenPinningStatus> getScreenPinningStatus() async {
    final result = await _channel.invokeMapMethod<String, dynamic>(
      'getScreenPinningStatus',
    );

    return ScreenPinningStatus(
      supported: result?['supported'] == true,
      active: result?['active'] == true,
      canRequest: result?['canRequest'] == true,
      label: result?['label']?.toString() ?? 'Tidak diketahui',
    );
  }

  Future<ScreenPinningStatus> requestScreenPinning() async {
    final result = await _channel.invokeMapMethod<String, dynamic>(
      'requestScreenPinning',
    );

    return ScreenPinningStatus(
      supported: result?['supported'] == true,
      active: result?['active'] == true,
      canRequest: result?['canRequest'] == true,
      label: result?['label']?.toString() ?? 'Tidak diketahui',
    );
  }

  Future<void> openDoNotDisturbSettings() async {
    await _channel.invokeMethod<void>('openDoNotDisturbSettings');
  }

  Future<void> openExternalUrl(String url) async {
    await _channel.invokeMethod<void>('openExternalUrl', {'url': url});
  }
}
