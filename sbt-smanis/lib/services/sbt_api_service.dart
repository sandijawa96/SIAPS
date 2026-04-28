import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:math';

import 'package:flutter/services.dart';

import '../config/app_config.dart';
import '../models/guard_event.dart';

class SbtRuntimeConfig {
  const SbtRuntimeConfig({
    required this.enabled,
    required this.examUrl,
    required this.examHost,
    required this.webviewUserAgent,
    required this.securityMode,
    required this.requiresSupervisorCode,
    required this.hasSupervisorCode,
    required this.minimumBatteryLevel,
    required this.requireDnd,
    required this.requireScreenPinning,
    required this.requireOverlayProtection,
    required this.iosLockOnBackground,
    required this.heartbeatIntervalSeconds,
    required this.maintenanceEnabled,
    required this.configVersion,
    this.minimumAppVersion,
    this.maintenanceMessage,
    this.announcement,
  });

  factory SbtRuntimeConfig.fallback() {
    return const SbtRuntimeConfig(
      enabled: true,
      examUrl: AppConfig.examUrl,
      examHost: AppConfig.examHost,
      webviewUserAgent: 'SBT-SMANIS/1.0',
      securityMode: 'warning',
      requiresSupervisorCode: false,
      hasSupervisorCode: false,
      minimumBatteryLevel: AppConfig.minimumBatteryLevel,
      requireDnd: false,
      requireScreenPinning: true,
      requireOverlayProtection: true,
      iosLockOnBackground: true,
      heartbeatIntervalSeconds: 30,
      maintenanceEnabled: false,
      configVersion: 1,
    );
  }

  factory SbtRuntimeConfig.fromJson(Map<String, dynamic> value) {
    final fallback = SbtRuntimeConfig.fallback();
    final examUrl = value['exam_url']?.toString().trim();
    final configuredHost = value['exam_host']?.toString().trim().toLowerCase();
    final webviewUserAgent = value['webview_user_agent']?.toString().trim();
    final parsedHost = examUrl == null || examUrl.isEmpty
        ? null
        : Uri.tryParse(examUrl)?.host;

    return SbtRuntimeConfig(
      enabled: value['enabled'] != false,
      examUrl: examUrl == null || examUrl.isEmpty ? fallback.examUrl : examUrl,
      examHost: configuredHost == null || configuredHost.isEmpty
          ? (parsedHost == null || parsedHost.isEmpty
                ? fallback.examHost
                : parsedHost)
          : configuredHost,
      webviewUserAgent:
          webviewUserAgent == null || webviewUserAgent.isEmpty
              ? fallback.webviewUserAgent
              : webviewUserAgent,
      securityMode: value['security_mode']?.toString() ?? fallback.securityMode,
      requiresSupervisorCode:
          value['requires_supervisor_code'] == true ||
          value['security_mode'] == 'supervisor_code' ||
          value['security_mode'] == 'locked',
      hasSupervisorCode: value['has_supervisor_code'] == true,
      minimumBatteryLevel:
          (value['minimum_battery_level'] as num?)?.toInt() ??
          fallback.minimumBatteryLevel,
      requireDnd: value['require_dnd'] == true,
      requireScreenPinning: value['require_screen_pinning'] != false,
      requireOverlayProtection: value['require_overlay_protection'] != false,
      iosLockOnBackground: value['ios_lock_on_background'] != false,
      heartbeatIntervalSeconds:
          (value['heartbeat_interval_seconds'] as num?)?.toInt() ??
          fallback.heartbeatIntervalSeconds,
      maintenanceEnabled: value['maintenance_enabled'] == true,
      maintenanceMessage: value['maintenance_message']?.toString(),
      announcement: value['announcement']?.toString(),
      minimumAppVersion: value['minimum_app_version']?.toString(),
      configVersion:
          (value['config_version'] as num?)?.toInt() ?? fallback.configVersion,
    );
  }

  final bool enabled;
  final String examUrl;
  final String examHost;
  final String webviewUserAgent;
  final String securityMode;
  final bool requiresSupervisorCode;
  final bool hasSupervisorCode;
  final int minimumBatteryLevel;
  final bool requireDnd;
  final bool requireScreenPinning;
  final bool requireOverlayProtection;
  final bool iosLockOnBackground;
  final int heartbeatIntervalSeconds;
  final bool maintenanceEnabled;
  final String? maintenanceMessage;
  final String? announcement;
  final String? minimumAppVersion;
  final int configVersion;
}

class SbtUnlockResult {
  const SbtUnlockResult({required this.allowed, required this.message});

  final bool allowed;
  final String message;
}

class SbtVersionCheck {
  const SbtVersionCheck({
    required this.available,
    required this.hasUpdate,
    required this.mustUpdate,
    required this.isSupported,
    this.currentVersion,
    this.currentBuildNumber,
    this.latestVersion,
    this.latestBuildNumber,
    this.updateMode,
    this.downloadUrl,
    this.fileSizeBytes,
    this.message,
  });

  factory SbtVersionCheck.unavailable(String message) {
    return SbtVersionCheck(
      available: false,
      hasUpdate: false,
      mustUpdate: false,
      isSupported: true,
      message: message,
    );
  }

  factory SbtVersionCheck.fromJson(Map<String, dynamic> value) {
    final latest = value['latest'] is Map<String, dynamic>
        ? value['latest'] as Map<String, dynamic>
        : <String, dynamic>{};

    return SbtVersionCheck(
      available: value['available'] == true,
      hasUpdate: value['has_update'] == true,
      mustUpdate: value['must_update'] == true,
      isSupported: value['is_supported'] != false,
      currentVersion: value['current_version']?.toString(),
      currentBuildNumber: (value['current_build_number'] as num?)?.toInt(),
      latestVersion: latest['public_version']?.toString(),
      latestBuildNumber: (latest['build_number'] as num?)?.toInt(),
      updateMode: value['update_mode']?.toString(),
      downloadUrl: latest['download_url']?.toString(),
      fileSizeBytes: (latest['file_size_bytes'] as num?)?.toInt(),
      message: value['message']?.toString(),
    );
  }

  final bool available;
  final bool hasUpdate;
  final bool mustUpdate;
  final bool isSupported;
  final String? currentVersion;
  final int? currentBuildNumber;
  final String? latestVersion;
  final int? latestBuildNumber;
  final String? updateMode;
  final String? downloadUrl;
  final int? fileSizeBytes;
  final String? message;

  String get latestLabel {
    final version = latestVersion == null ? '-' : latestVersion!;
    final build = latestBuildNumber == null ? '-' : latestBuildNumber!;
    return '$version ($build)';
  }
}

class SbtApiException implements Exception {
  const SbtApiException(this.message);

  final String message;

  @override
  String toString() => message;
}

class SbtApiService {
  SbtApiService._()
    : appSessionId = _generateSessionId(),
      _config = SbtRuntimeConfig.fallback();

  static final instance = SbtApiService._();
  static const _isrgRootX1Asset = 'assets/certs/isrgrootx1.pem';

  final String appSessionId;
  SbtRuntimeConfig _config;
  String? _lastConfigError;
  SbtVersionCheck? _lastVersionCheck;
  bool _sessionStarted = false;

  SbtRuntimeConfig get config => _config;
  String? get lastConfigError => _lastConfigError;
  SbtVersionCheck? get lastVersionCheck => _lastVersionCheck;

  Future<SbtRuntimeConfig> loadConfig({bool force = false}) async {
    if (!force && _lastConfigError == null && _config.configVersion > 1) {
      return _config;
    }

    try {
      final payload = await _requestJson('GET', '/sbt/mobile/config');
      final data = _extractData(payload);
      _config = SbtRuntimeConfig.fromJson(data);
      _lastConfigError = null;
    } on Object catch (error) {
      _lastConfigError = error.toString();
    }

    return _config;
  }

  Future<SbtVersionCheck> checkForUpdate() async {
    try {
      final query = Uri(
        queryParameters: {
          'platform': Platform.isIOS ? 'ios' : 'android',
          'app_version': AppConfig.appVersion,
          'build_number': AppConfig.appBuildNumber,
          'release_channel': 'stable',
        },
      ).query;
      final payload = await _requestJson(
        'GET',
        '/sbt/mobile/version-check?$query',
      );
      final check = SbtVersionCheck.fromJson(_extractData(payload));
      _lastVersionCheck = check;
      return check;
    } on Object catch (error) {
      final message = error is SbtApiException
          ? _friendlyVersionCheckError(error.message)
          : 'Cek update SBT belum bisa terhubung ke SIAPS.';
      final check = SbtVersionCheck.unavailable(message);
      _lastVersionCheck = check;
      return check;
    }
  }

  Future<void> startSession() async {
    try {
      final payload = await _requestJson('POST', '/sbt/mobile/sessions', {
        'app_session_id': appSessionId,
        'device_name': _deviceLabel(),
        'app_version': AppConfig.appVersion,
        'platform': Platform.operatingSystem,
        'exam_url': _config.examUrl,
        'metadata': _baseMetadata(),
      });
      final data = _extractData(payload);
      final configData = data['config'];
      if (configData is Map<String, dynamic>) {
        _config = SbtRuntimeConfig.fromJson(configData);
      }
      _sessionStarted = true;
    } on Object {
      _sessionStarted = false;
    }
  }

  Future<void> sendHeartbeat({String? currentUrl}) async {
    if (!_sessionStarted) {
      await startSession();
    }

    try {
      await _requestJson('POST', '/sbt/mobile/heartbeat', {
        'app_session_id': appSessionId,
        'current_url': currentUrl,
        'focus_state': 'active',
        'metadata': _baseMetadata(),
      });
    } on Object {
      // Heartbeat tidak boleh menghentikan ujian jika jaringan SIAPS sesaat putus.
    }
  }

  Future<void> reportGuardEvent(GuardEvent event) async {
    await reportEvent(
      eventType: event.type,
      severity: _severityForGuardEvent(event.type),
      message: event.message,
      metadata: {'occurred_at_local': event.occurredAt.toIso8601String()},
    );
  }

  Future<void> reportEvent({
    required String eventType,
    required String severity,
    String? message,
    Map<String, dynamic>? metadata,
  }) async {
    try {
      await _requestJson('POST', '/sbt/mobile/events', {
        'app_session_id': appSessionId,
        'event_type': eventType,
        'severity': severity,
        'message': message,
        'app_version': AppConfig.appVersion,
        'metadata': {..._baseMetadata(), if (metadata != null) ...metadata},
      });
    } on Object {
      // Pelaporan event bersifat best effort agar WebView ujian tidak mati.
    }
  }

  Future<SbtUnlockResult> validateSupervisorCode(
    String code,
    GuardEvent? event,
  ) async {
    try {
      final payload = await _requestJson('POST', '/sbt/mobile/unlock', {
        'app_session_id': appSessionId,
        'supervisor_code': code,
        'event_type': event?.type,
        'metadata': {
          ..._baseMetadata(),
          if (event != null)
            'guard_event': {
              'type': event.type,
              'message': event.message,
              'occurred_at_local': event.occurredAt.toIso8601String(),
            },
        },
      });
      final data = _extractData(payload);

      return SbtUnlockResult(
        allowed: data['allowed'] == true,
        message: payload['message']?.toString() ?? 'Kode pengawas valid.',
      );
    } on SbtApiException catch (error) {
      return SbtUnlockResult(allowed: false, message: error.message);
    } on Object {
      return const SbtUnlockResult(
        allowed: false,
        message: 'SIAPS belum bisa memvalidasi kode pengawas.',
      );
    }
  }

  Future<void> finishSession({String reason = 'exit'}) async {
    if (!_sessionStarted) return;

    try {
      await _requestJson('POST', '/sbt/mobile/finish', {
        'app_session_id': appSessionId,
        'reason': reason,
        'metadata': _baseMetadata(),
      });
    } on Object {
      // Penutupan sesi tetap best effort.
    } finally {
      _sessionStarted = false;
    }
  }

  Future<Map<String, dynamic>> _requestJson(
    String method,
    String path, [
    Map<String, dynamic>? body,
  ]) async {
    final client = await _createHttpClient();

    try {
      final request = await client
          .openUrl(method, _uri(path))
          .timeout(const Duration(seconds: 8));
      request.headers.set(HttpHeaders.acceptHeader, ContentType.json.mimeType);
      request.headers.set(HttpHeaders.userAgentHeader, 'SBT-SMANIS/1.0');
      request.headers.set('X-SBT-App', 'sbt-smanis');
      request.headers.set('X-SBT-App-Version', AppConfig.appVersion);

      if (body != null) {
        request.headers.contentType = ContentType.json;
        request.write(jsonEncode(body));
      }

      final response = await request.close().timeout(
        const Duration(seconds: 10),
      );
      final responseText = await utf8.decodeStream(response);
      final trimmedResponse = responseText.trim();
      final decoded = trimmedResponse.isEmpty
          ? <String, dynamic>{}
          : _decodeJsonResponse(trimmedResponse, response.statusCode);
      final payload = decoded is Map<String, dynamic>
          ? decoded
          : <String, dynamic>{'data': decoded};

      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw SbtApiException(
          payload['message']?.toString() ??
              'SIAPS merespons HTTP ${response.statusCode}.',
        );
      }

      return payload;
    } finally {
      client.close(force: true);
    }
  }

  Future<HttpClient> _createHttpClient() async {
    final context = SecurityContext(withTrustedRoots: true);

    try {
      final certificate = await rootBundle.load(_isrgRootX1Asset);
      context.setTrustedCertificatesBytes(
        certificate.buffer.asUint8List(
          certificate.offsetInBytes,
          certificate.lengthInBytes,
        ),
      );
    } on Object {
      // Trust store sistem tetap dipakai bila sertifikat bawaan gagal dibaca.
    }

    return HttpClient(context: context)
      ..connectionTimeout = const Duration(seconds: 6);
  }

  Uri _uri(String path) {
    final base = AppConfig.siapsApiBaseUrl.replaceFirst(RegExp(r'/+$'), '');
    final suffix = path.startsWith('/') ? path : '/$path';
    return Uri.parse('$base$suffix');
  }

  Map<String, dynamic> _extractData(Map<String, dynamic> payload) {
    final data = payload['data'];
    if (data is Map<String, dynamic>) {
      return data;
    }

    return <String, dynamic>{};
  }

  Object _decodeJsonResponse(String responseText, int statusCode) {
    try {
      return jsonDecode(responseText);
    } on FormatException {
      throw SbtApiException(
        statusCode >= 500
            ? 'SIAPS sedang mengalami gangguan saat cek update SBT.'
            : 'SIAPS mengirim respons yang belum sesuai untuk cek update SBT.',
      );
    }
  }

  String _friendlyVersionCheckError(String message) {
    final normalized = message.trim();
    if (normalized.isEmpty || normalized.toLowerCase() == 'server error') {
      return 'Cek update SBT belum bisa diproses server SIAPS.';
    }

    return normalized;
  }

  Map<String, dynamic> _baseMetadata() {
    return {
      'app_key': AppConfig.appKey,
      'app_name': AppConfig.appName,
      'app_version': AppConfig.appVersion,
      'build_number': AppConfig.appBuildNumber,
      'webview_user_agent': _config.webviewUserAgent,
      'platform': Platform.operatingSystem,
      'platform_version': Platform.operatingSystemVersion,
    };
  }

  String _deviceLabel() {
    return '${Platform.operatingSystem} ${Platform.operatingSystemVersion}';
  }

  String _severityForGuardEvent(String type) {
    return switch (type) {
      'APP_PAUSED' ||
      'APP_STOPPED' ||
      'IOS_APP_BACKGROUND' ||
      'IOS_APP_HIDDEN' ||
      'MULTI_WINDOW' ||
      'PIP_MODE' => 'high',
      'IOS_APP_INACTIVE' => 'medium',
      _ => 'medium',
    };
  }

  static String _generateSessionId() {
    final random = Random.secure().nextInt(1 << 32).toRadixString(16);
    return '${DateTime.now().microsecondsSinceEpoch}-$random';
  }
}
