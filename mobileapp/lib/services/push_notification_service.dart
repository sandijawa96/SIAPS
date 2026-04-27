import 'dart:async';
import 'dart:convert';
import 'dart:io' show Platform;
import 'dart:ui';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:flutter/widgets.dart';

import '../middleware/device_binding_middleware.dart';
import '../models/user.dart';
import '../utils/constants.dart';
import 'api_service.dart';
import 'notification_service.dart';

const String _kAndroidChannelId = 'siaps_notifications';
const String _kAndroidChannelName = 'SIAPS Notifications';
const String _kAndroidChannelDescription = 'Notifikasi utama aplikasi SIAPS';
const String _kAndroidNotificationIcon = 'ic_notification';
const String _kAndroidLargeNotificationIcon = 'ic_notification';
const Color _kAndroidNotificationColor = Color(0xFF64B5F6);

const AndroidNotificationChannel _kAndroidChannel = AndroidNotificationChannel(
  _kAndroidChannelId,
  _kAndroidChannelName,
  description: _kAndroidChannelDescription,
  importance: Importance.max,
);

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  WidgetsFlutterBinding.ensureInitialized();
  DartPluginRegistrant.ensureInitialized();
  await Firebase.initializeApp();

  final localNotifications = FlutterLocalNotificationsPlugin();
  const androidInit = AndroidInitializationSettings(_kAndroidNotificationIcon);
  const initSettings = InitializationSettings(android: androidInit);
  await localNotifications.initialize(initSettings);
  final androidNotifications =
      localNotifications.resolvePlatformSpecificImplementation<
          AndroidFlutterLocalNotificationsPlugin>();
  await androidNotifications?.createNotificationChannel(_kAndroidChannel);

  final title = message.notification?.title ??
      message.data['title']?.toString() ??
      'Notifikasi Baru';
  final body =
      message.notification?.body ?? message.data['message']?.toString() ?? '';

  if (title.trim().isNotEmpty || body.trim().isNotEmpty) {
    await localNotifications.show(
      message.messageId?.hashCode ?? DateTime.now().millisecondsSinceEpoch,
      title,
      body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          _kAndroidChannelId,
          _kAndroidChannelName,
          channelDescription: _kAndroidChannelDescription,
          icon: _kAndroidNotificationIcon,
          largeIcon:
              DrawableResourceAndroidBitmap(_kAndroidLargeNotificationIcon),
          color: _kAndroidNotificationColor,
          importance: Importance.max,
          priority: Priority.high,
          visibility: NotificationVisibility.public,
          playSound: true,
        ),
      ),
      payload: jsonEncode(message.data),
    );
  }

  debugPrint(
      '[PushNotificationService] background message received: ${message.messageId}');
}

class PushNotificationService {
  static final PushNotificationService _instance =
      PushNotificationService._internal();
  factory PushNotificationService() => _instance;
  PushNotificationService._internal();

  final ApiService _apiService = ApiService();
  final NotificationService _notificationService = NotificationService();
  final FlutterLocalNotificationsPlugin _localNotifications =
      FlutterLocalNotificationsPlugin();
  final ValueNotifier<bool> realtimeReadyNotifier = ValueNotifier<bool>(false);

  bool _initialized = false;
  bool _hasPushPermission = false;
  User? _currentUser;
  static const int _maxTokenResolveAttempts = 5;
  Timer? _deferredRegistrationTimer;
  Future<void>? _registrationInFlight;
  String? _registrationInFlightKey;
  String? _lastRegisteredKey;

  bool get isRealtimeReady => realtimeReadyNotifier.value;

  Future<void> initialize() async {
    if (_initialized) {
      return;
    }

    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp();
    }
    await FirebaseMessaging.instance.setAutoInitEnabled(true);
    FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
    await FirebaseMessaging.instance.requestPermission(
      alert: true,
      badge: true,
      sound: true,
      provisional: false,
    );
    final settings = await FirebaseMessaging.instance.getNotificationSettings();
    _hasPushPermission =
        settings.authorizationStatus == AuthorizationStatus.authorized ||
            settings.authorizationStatus == AuthorizationStatus.provisional;
    _updateRealtimeReady();
    _log('FCM authorization status: ${settings.authorizationStatus.name}');

    const androidInit =
        AndroidInitializationSettings(_kAndroidNotificationIcon);
    const initSettings = InitializationSettings(android: androidInit);
    await _localNotifications.initialize(initSettings);

    final androidNotifications =
        _localNotifications.resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>();
    await androidNotifications?.createNotificationChannel(_kAndroidChannel);
    if (Platform.isAndroid) {
      final permissionGranted =
          await androidNotifications?.requestNotificationsPermission();
      _log(
          'Local notification permission (Android): ${permissionGranted ?? false}');
    }

    FirebaseMessaging.onMessage.listen(_handleForegroundMessage);
    FirebaseMessaging.onMessageOpenedApp.listen(_handleNotificationOpened);
    final initialMessage = await FirebaseMessaging.instance.getInitialMessage();
    if (initialMessage != null) {
      await _handleNotificationOpened(initialMessage);
    }
    FirebaseMessaging.instance.onTokenRefresh.listen((token) async {
      final user = _currentUser;
      if (user == null || token.isEmpty) {
        return;
      }
      _log('FCM token refreshed: ${_tokenSuffix(token)}');
      await registerCurrentDevice(user);
    });

    _initialized = true;
  }

  Future<void> registerCurrentDevice(User user) async {
    String? registrationKey;
    try {
      _currentUser = user;
      await initialize();

      final token = await _resolveMessagingTokenWithRetry();
      if (token == null || token.isEmpty) {
        _log('FCM token empty, register device without token and retry later');
        _updateRealtimeReady(false);
        final deviceId = await _resolveDeviceId(user, '');
        final deviceName = await _resolveDeviceName();
        final deviceInfo = await _resolveDeviceInfo(user);
        registrationKey = '${user.id}|$deviceId|';
        if (_lastRegisteredKey != registrationKey) {
          await _registerToken(
            user: user,
            token: '',
            deviceId: deviceId,
            deviceName: deviceName,
            deviceInfo: deviceInfo,
          );
          _lastRegisteredKey = registrationKey;
        }
        _scheduleDeferredRegistration(user);
        return;
      }

      _log('FCM token acquired: ${_tokenSuffix(token)}');
      final deviceId = await _resolveDeviceId(user, token);
      final deviceName = await _resolveDeviceName();
      final deviceInfo = await _resolveDeviceInfo(user);

      registrationKey = '${user.id}|$deviceId|$token';
      if (_lastRegisteredKey == registrationKey) {
        _log('Skipping duplicate device token registration for user ${user.id}');
        _updateRealtimeReady(true);
        _deferredRegistrationTimer?.cancel();
        _deferredRegistrationTimer = null;
        return;
      }

      if (_registrationInFlightKey == registrationKey &&
          _registrationInFlight != null) {
        await _registrationInFlight;
        return;
      }

      final registrationFuture = _registerToken(
        user: user,
        token: token,
        deviceId: deviceId,
        deviceName: deviceName,
        deviceInfo: deviceInfo,
      );
      _registrationInFlightKey = registrationKey;
      _registrationInFlight = registrationFuture;

      await registrationFuture;
      _lastRegisteredKey = registrationKey;
      _updateRealtimeReady(true);
      _deferredRegistrationTimer?.cancel();
      _deferredRegistrationTimer = null;
    } catch (e) {
      _updateRealtimeReady(false);
      _log('Push registration skipped: $e');
      _scheduleDeferredRegistration(user);
    } finally {
      if (registrationKey != null && _registrationInFlightKey == registrationKey) {
        _registrationInFlight = null;
        _registrationInFlightKey = null;
      }
    }
  }

  Future<String?> _resolveMessagingTokenWithRetry() async {
    for (var attempt = 1; attempt <= _maxTokenResolveAttempts; attempt++) {
      final token = await FirebaseMessaging.instance.getToken();
      if (token != null && token.isNotEmpty) {
        if (attempt > 1) {
          _log('FCM token resolved after retry attempt $attempt');
        }
        return token;
      }

      if (attempt < _maxTokenResolveAttempts) {
        _log('FCM token not ready, retry attempt $attempt');
        await Future<void>.delayed(const Duration(seconds: 2));
      }
    }

    return null;
  }

  Future<void> _registerToken({
    required User user,
    required String token,
    required String deviceId,
    required String deviceName,
    required Map<String, dynamic> deviceInfo,
  }) async {
    try {
      final response = await _apiService.post(
        AppConstants.registerDeviceTokenEndpoint,
        data: {
          'device_id': deviceId,
          'device_name': deviceName,
          'device_type': Platform.isIOS ? 'ios' : 'android',
          'push_token': token.isNotEmpty ? token : null,
          'device_info': deviceInfo,
        },
      );
      final success = response.data is Map<String, dynamic>
          ? response.data['success'] == true
          : false;
      _log(
        'Register device token response: status=${response.statusCode}, success=$success, '
        'deviceId=$deviceId, hasToken=${token.isNotEmpty}, tokenSuffix=${token.isNotEmpty ? _tokenSuffix(token) : '-'}',
      );
      if (!success) {
        throw Exception('Register device token returned success=false');
      }
    } catch (e) {
      _log('Register device token failed: $e');
      rethrow;
    }
  }

  Future<void> _handleForegroundMessage(RemoteMessage message) async {
    _log('Foreground message received: ${message.messageId}');
    try {
      await _notificationService.getUnreadCount();
    } catch (e) {
      _log('Failed to refresh unread count: $e');
    }

    final notification = message.notification;
    final title = notification?.title ??
        message.data['title']?.toString() ??
        'Notifikasi Baru';
    final body =
        notification?.body ?? message.data['message']?.toString() ?? '';
    if (title.trim().isEmpty && body.trim().isEmpty) {
      return;
    }

    await showLocalFallbackNotification(
        title: title, body: body, payload: jsonEncode(message.data));
  }

  Future<void> showLocalFallbackNotification({
    required String title,
    required String body,
    String? payload,
  }) async {
    await _localNotifications.show(
      DateTime.now().millisecondsSinceEpoch,
      title,
      body,
      const NotificationDetails(
        android: AndroidNotificationDetails(
          _kAndroidChannelId,
          _kAndroidChannelName,
          channelDescription: _kAndroidChannelDescription,
          icon: _kAndroidNotificationIcon,
          largeIcon:
              DrawableResourceAndroidBitmap(_kAndroidLargeNotificationIcon),
          color: _kAndroidNotificationColor,
          importance: Importance.max,
          priority: Priority.high,
          visibility: NotificationVisibility.public,
          playSound: true,
        ),
      ),
      payload: payload,
    );
  }

  Future<void> _handleNotificationOpened(RemoteMessage message) async {
    _log('Notification opened: ${message.messageId}');
    try {
      await _notificationService.getUnreadCount();
    } catch (e) {
      _log('Failed to refresh unread count: $e');
    }
  }

  void _log(String message) {
    debugPrint('[PushNotificationService] $message');
  }

  String _tokenSuffix(String token) {
    if (token.length <= 12) {
      return token;
    }

    return token.substring(token.length - 12);
  }

  Future<String> _resolveDeviceId(User user, String token) async {
    final platform = Platform.isIOS ? 'ios' : 'android';

    final localDeviceId = (await DeviceBindingMiddleware.getDeviceId()).trim();
    if (localDeviceId.isNotEmpty) {
      return _normalizeDeviceId('$platform-u${user.id}-$localDeviceId');
    }

    final userDeviceId = (user.deviceId ?? '').trim();
    if (userDeviceId.isNotEmpty) {
      return _normalizeDeviceId('$platform-u${user.id}-$userDeviceId');
    }

    return _normalizeDeviceId(
        'mobile-$platform-u${user.id}-${_tokenSuffix(token)}');
  }

  Future<String> _resolveDeviceName() async {
    final name = (await DeviceBindingMiddleware.getDeviceName()).trim();
    if (name.isNotEmpty) {
      return name;
    }

    return 'Mobile App';
  }

  Future<Map<String, dynamic>> _resolveDeviceInfo(User user) async {
    final info = await DeviceBindingMiddleware.getDeviceInfo();
    return {
      ...info,
      'user_id': user.id,
      'username': user.username,
      'platform': Platform.operatingSystem,
      'app': 'mobile',
    };
  }

  String _normalizeDeviceId(String value) {
    final compact = value.replaceAll(RegExp(r'\s+'), '-');
    if (compact.length <= 255) {
      return compact;
    }

    return compact.substring(0, 255);
  }

  void _scheduleDeferredRegistration(User user) {
    _deferredRegistrationTimer?.cancel();
    _deferredRegistrationTimer = Timer(const Duration(seconds: 30), () {
      _log('Running deferred push registration retry');
      unawaited(registerCurrentDevice(user));
    });
  }

  void _updateRealtimeReady([bool? registeredWithToken]) {
    final nextValue =
        _hasPushPermission && (registeredWithToken ?? realtimeReadyNotifier.value);
    if (realtimeReadyNotifier.value != nextValue) {
      realtimeReadyNotifier.value = nextValue;
    }
  }
}
