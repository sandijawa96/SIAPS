import 'dart:async';
import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'api_service.dart';
import '../utils/constants.dart';

class AutoTokenRefreshService {
  static const _storage = FlutterSecureStorage();
  static Timer? _refreshTimer;
  static Timer? _backgroundTimer;
  static final ApiService _apiService = ApiService();

  /// Start auto token refresh
  static void startAutoRefresh() {
    stopAutoRefresh(); // Stop any existing timers

    // Refresh token every 6 days (before 7-day expiry)
    _refreshTimer = Timer.periodic(
      Duration(days: 6),
      (timer) async {
        final success = await _silentRefreshToken();
        if (!success) {
          timer.cancel();
          print('Auto refresh failed, timer stopped');
        }
      },
    );

    // Background check every hour
    _backgroundTimer = Timer.periodic(
      Duration(hours: 1),
      (timer) async {
        if (await _isTokenNearExpiry()) {
          await _silentRefreshToken();
        }
      },
    );

    print('Auto token refresh started');
  }

  /// Stop auto token refresh
  static void stopAutoRefresh() {
    _refreshTimer?.cancel();
    _backgroundTimer?.cancel();
    _refreshTimer = null;
    _backgroundTimer = null;
    print('Auto token refresh stopped');
  }

  /// Silent token refresh (no user interaction)
  static Future<bool> _silentRefreshToken() async {
    try {
      print('Attempting silent token refresh...');

      final currentToken = await _storage.read(key: AppConstants.tokenKey);
      if (currentToken == null) {
        print('No token found for refresh');
        return false;
      }

      final response = await _apiService.post(AppConstants.refreshTokenEndpoint);

      if (response.statusCode == 200 && response.data != null) {
        final data = response.data is Map<String, dynamic>
            ? response.data as Map<String, dynamic>
            : <String, dynamic>{};
        final payload = data['data'] is Map<String, dynamic>
            ? data['data'] as Map<String, dynamic>
            : data;
        final refreshedToken =
            (payload['token'] ?? payload['access_token'] ?? data['token'] ?? data['access_token'])
                ?.toString();
        if ((data['success'] == true || refreshedToken != null) &&
            refreshedToken != null &&
            refreshedToken.isNotEmpty) {
          await _apiService.setToken(refreshedToken);

          print('Token refreshed successfully');
          return true;
        }
      }

      print('Token refresh failed: ${response.statusCode}');
      return false;
    } catch (e) {
      print('Silent token refresh error: $e');
      return false;
    }
  }

  /// Check if token is near expiry
  static Future<bool> _isTokenNearExpiry() async {
    try {
      final token = await _storage.read(key: AppConstants.tokenKey);
      if (token == null) return true;

      // Decode JWT payload (simple base64 decode)
      final parts = token.split('.');
      if (parts.length != 3) return true;

      final payload = parts[1];
      // Add padding if needed
      final normalizedPayload = payload.padRight(
        (payload.length + 3) ~/ 4 * 4,
        '=',
      );

      final decoded = utf8.decode(base64Url.decode(normalizedPayload));
      final payloadMap = json.decode(decoded);

      final exp = payloadMap['exp'];
      if (exp == null) return true;

      final expiryTime = DateTime.fromMillisecondsSinceEpoch(exp * 1000);
      final now = DateTime.now();

      // Consider token near expiry if less than 1 day remaining
      final timeUntilExpiry = expiryTime.difference(now);
      final isNearExpiry = timeUntilExpiry.inDays < 1;

      if (isNearExpiry) {
        print(
            'Token is near expiry: ${timeUntilExpiry.inHours} hours remaining');
      }

      return isNearExpiry;
    } catch (e) {
      print('Error checking token expiry: $e');
      return true; // Assume expired on error
    }
  }

  /// Manual token refresh (with user feedback)
  static Future<TokenRefreshResult> refreshToken() async {
    try {
      print('Manual token refresh requested...');

      final response = await _apiService.post(AppConstants.refreshTokenEndpoint);

      if (response.statusCode == 200 && response.data != null) {
        final data = response.data is Map<String, dynamic>
            ? response.data as Map<String, dynamic>
            : <String, dynamic>{};
        final payload = data['data'] is Map<String, dynamic>
            ? data['data'] as Map<String, dynamic>
            : data;
        final refreshedToken =
            (payload['token'] ?? payload['access_token'] ?? data['token'] ?? data['access_token'])
                ?.toString();
        if ((data['success'] == true || refreshedToken != null) &&
            refreshedToken != null &&
            refreshedToken.isNotEmpty) {
          await _apiService.setToken(refreshedToken);

          print('Manual token refresh successful');
          return TokenRefreshResult(
            success: true,
            message: 'Token berhasil diperbarui',
            newToken: refreshedToken,
          );
        }
      }

      return TokenRefreshResult(
        success: false,
        message: 'Gagal memperbarui token: ${response.statusCode}',
      );
    } catch (e) {
      print('Manual token refresh error: $e');
      return TokenRefreshResult(
        success: false,
        message: 'Error: $e',
      );
    }
  }

  /// Check token validity
  static Future<bool> isTokenValid() async {
    try {
      final token = await _storage.read(key: AppConstants.tokenKey);
      if (token == null) return false;

      // Simple validation by making a test API call
      final response = await _apiService.get(AppConstants.profileEndpoint);
      return response.statusCode == 200;
    } catch (e) {
      print('Token validation error: $e');
      return false;
    }
  }

  /// Get token expiry info
  static Future<TokenExpiryInfo> getTokenExpiryInfo() async {
    try {
      final token = await _storage.read(key: AppConstants.tokenKey);
      if (token == null) {
        return TokenExpiryInfo(
          hasToken: false,
          isExpired: true,
          expiryTime: null,
          timeUntilExpiry: null,
        );
      }

      // Decode JWT payload
      final parts = token.split('.');
      if (parts.length != 3) {
        return TokenExpiryInfo(
          hasToken: true,
          isExpired: true,
          expiryTime: null,
          timeUntilExpiry: null,
        );
      }

      final payload = parts[1];
      final normalizedPayload = payload.padRight(
        (payload.length + 3) ~/ 4 * 4,
        '=',
      );

      final decoded = utf8.decode(base64Url.decode(normalizedPayload));
      final payloadMap = json.decode(decoded);

      final exp = payloadMap['exp'];
      if (exp == null) {
        return TokenExpiryInfo(
          hasToken: true,
          isExpired: true,
          expiryTime: null,
          timeUntilExpiry: null,
        );
      }

      final expiryTime = DateTime.fromMillisecondsSinceEpoch(exp * 1000);
      final now = DateTime.now();
      final timeUntilExpiry = expiryTime.difference(now);
      final isExpired = timeUntilExpiry.isNegative;

      return TokenExpiryInfo(
        hasToken: true,
        isExpired: isExpired,
        expiryTime: expiryTime,
        timeUntilExpiry: isExpired ? null : timeUntilExpiry,
      );
    } catch (e) {
      print('Error getting token expiry info: $e');
      return TokenExpiryInfo(
        hasToken: false,
        isExpired: true,
        expiryTime: null,
        timeUntilExpiry: null,
      );
    }
  }

  /// Initialize auto refresh on app start
  static Future<void> initialize() async {
    try {
      final isValid = await isTokenValid();
      if (isValid) {
        startAutoRefresh();
        print('Auto token refresh initialized');
      } else {
        print('Invalid token, auto refresh not started');
      }
    } catch (e) {
      print('Error initializing auto token refresh: $e');
    }
  }

  /// Cleanup on app dispose
  static void dispose() {
    stopAutoRefresh();
    print('Auto token refresh disposed');
  }
}

class TokenRefreshResult {
  final bool success;
  final String message;
  final String? newToken;

  TokenRefreshResult({
    required this.success,
    required this.message,
    this.newToken,
  });
}

class TokenExpiryInfo {
  final bool hasToken;
  final bool isExpired;
  final DateTime? expiryTime;
  final Duration? timeUntilExpiry;

  TokenExpiryInfo({
    required this.hasToken,
    required this.isExpired,
    this.expiryTime,
    this.timeUntilExpiry,
  });

  String get formattedTimeUntilExpiry {
    if (timeUntilExpiry == null) return 'Expired';

    final days = timeUntilExpiry!.inDays;
    final hours = timeUntilExpiry!.inHours % 24;
    final minutes = timeUntilExpiry!.inMinutes % 60;

    if (days > 0) {
      return '$days hari $hours jam';
    } else if (hours > 0) {
      return '$hours jam $minutes menit';
    } else {
      return '$minutes menit';
    }
  }
}
