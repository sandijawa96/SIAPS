import 'dart:async';
import 'dart:io' show Platform;
import 'dart:ui' as ui;
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import '../utils/constants.dart';
import 'api_service.dart';
import 'live_tracking_service.dart';

class LiveTrackingBackgroundService {
  LiveTrackingBackgroundService._internal();

  static final LiveTrackingBackgroundService _instance =
      LiveTrackingBackgroundService._internal();

  factory LiveTrackingBackgroundService() => _instance;

  static bool get isAvailable =>
      AppConstants.enableBackgroundLiveTracking &&
      !kIsWeb &&
      Platform.isAndroid;

  final FlutterBackgroundService _service = FlutterBackgroundService();
  final FlutterLocalNotificationsPlugin _notifications =
      FlutterLocalNotificationsPlugin();

  bool _isInitialized = false;

  Future<void> initialize() async {
    if (!isAvailable || _isInitialized) {
      return;
    }

    const channel = AndroidNotificationChannel(
      AppConstants.liveTrackingForegroundNotificationChannelId,
      AppConstants.liveTrackingForegroundNotificationChannelName,
      description: 'Channel untuk live tracking background siswa.',
      importance: Importance.low,
    );

    await _notifications
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    await _service.configure(
      iosConfiguration: IosConfiguration(
        autoStart: false,
        onForeground: liveTrackingBackgroundOnStart,
        onBackground: liveTrackingBackgroundOnIosBackground,
      ),
      androidConfiguration: AndroidConfiguration(
        onStart: liveTrackingBackgroundOnStart,
        autoStart: false,
        autoStartOnBoot: false,
        isForegroundMode: true,
        notificationChannelId:
            AppConstants.liveTrackingForegroundNotificationChannelId,
        initialNotificationTitle: 'SIAP Absensi',
        initialNotificationContent: 'Menyiapkan pemantauan kehadiran',
        foregroundServiceNotificationId:
            AppConstants.liveTrackingForegroundNotificationId,
      ),
    );

    _isInitialized = true;
  }

  Future<void> syncTrackingState({
    required bool shouldTrack,
    int? userId,
    bool forceRestart = false,
  }) async {
    if (!isAvailable) {
      return;
    }

    if (!shouldTrack || userId == null) {
      await stopTracking();
      return;
    }

    await startTracking(userId: userId, forceRestart: forceRestart);
  }

  Future<void> startTracking({
    required int userId,
    bool forceRestart = false,
  }) async {
    if (!isAvailable) {
      return;
    }

    await initialize();

    final isRunning = await _service.isRunning();
    if (!isRunning) {
      await _service.startService();
      await Future<void>.delayed(const Duration(milliseconds: 350));
    }

    _service.invoke('start_tracking', <String, dynamic>{
      'user_id': userId,
      'force_restart': forceRestart,
    });
  }

  Future<void> stopTracking() async {
    if (!isAvailable) {
      return;
    }

    final isRunning = await _service.isRunning();
    if (!isRunning) {
      return;
    }

    _service.invoke('stop_tracking');
  }
}

@pragma('vm:entry-point')
Future<bool> liveTrackingBackgroundOnIosBackground(
    ServiceInstance service) async {
  WidgetsFlutterBinding.ensureInitialized();
  ui.DartPluginRegistrant.ensureInitialized();
  return true;
}

@pragma('vm:entry-point')
void liveTrackingBackgroundOnStart(ServiceInstance service) async {
  WidgetsFlutterBinding.ensureInitialized();
  ui.DartPluginRegistrant.ensureInitialized();

  ApiService().initialize();

  final trackingService = LiveTrackingService();
  int? activeUserId;
  trackingService.bindNotificationUpdater((title, content) async {
    await _setForegroundNotificationInfo(
      service,
      title: title,
      content: content,
    );
  });
  await _setForegroundNotificationInfo(
    service,
    title: 'SIAP Absensi',
    content: 'Menyiapkan pemantauan kehadiran',
  );

  service.on('start_tracking').listen((payload) async {
    final userId = _parseUserId(payload?['user_id']);
    if (userId == null) {
      return;
    }

    final forceRestart = payload?['force_restart'] == true;
    if (!forceRestart &&
        trackingService.isTracking &&
        activeUserId == userId) {
      return;
    }

    trackingService.stopTracking();
    activeUserId = userId;
    trackingService.bindNotificationUpdater((title, content) async {
      await _setForegroundNotificationInfo(
        service,
        title: title,
        content: content,
      );
    });
    await trackingService.startTracking(userId: userId);
  });

  service.on('stop_tracking').listen((_) async {
    trackingService.stopTracking();
    activeUserId = null;
    service.stopSelf();
  });
}

int? _parseUserId(dynamic raw) {
  if (raw is int) {
    return raw;
  }

  if (raw is String) {
    return int.tryParse(raw);
  }

  return null;
}

Future<void> _setForegroundNotificationInfo(
  ServiceInstance service, {
  required String title,
  required String content,
}) async {
  try {
    final dynamic androidService = service;
    await androidService.setForegroundNotificationInfo(
      title: title,
      content: content,
    );
  } catch (_) {
    // No-op outside Android foreground service contexts.
  }
}
