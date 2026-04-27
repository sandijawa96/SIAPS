import 'dart:convert';
import 'dart:io' show Platform;
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../models/user.dart';
import '../models/login_response.dart';
import '../utils/constants.dart';
import 'api_service.dart';
import '../middleware/device_binding_middleware.dart';
import 'auto_token_refresh_service.dart';
import 'push_notification_service.dart';
import 'attendance_reminder_service.dart';
import 'live_tracking_background_service.dart';
import 'live_tracking_service.dart';

class AuthService {
  static final AuthService _instance = AuthService._internal();
  factory AuthService() => _instance;
  AuthService._internal();

  final ApiService _apiService = ApiService();
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  final PushNotificationService _pushNotificationService =
      PushNotificationService();
  final AttendanceReminderService _attendanceReminderService =
      AttendanceReminderService();
  final LiveTrackingBackgroundService _liveTrackingBackgroundService =
      LiveTrackingBackgroundService();
  final LiveTrackingService _liveTrackingService = LiveTrackingService();

  // Login untuk pegawai/staff
  Future<LoginResponse> loginStaff({
    required String email,
    required String password,
  }) async {
    try {
      await _ensureLiveTrackingStopped();

      final deviceContext = await _resolveRequiredLoginDeviceContext();
      if (deviceContext == null) {
        return LoginResponse(
          success: false,
          message:
              'Tidak dapat mengidentifikasi perangkat. Tutup aplikasi lalu coba lagi. Jika tetap gagal, hapus aplikasi lalu instal ulang versi terbaru.',
        );
      }

      final request = LoginRequest(
        email: email,
        password: password,
        clientType: 'mobile',
      );

      // Add device binding data to request
      final requestData = request.toJson();
      requestData.addAll(deviceContext);
      requestData.addAll(await _buildPushLoginPayload());

      final response = await _apiService.post(
        AppConstants.loginMobileEndpoint,
        data: requestData,
      );

      print('🔍 Raw API Response: ${response.data}');

      final loginResponse = LoginResponse.fromJson(response.data);

      print(
        '🔍 Parsed LoginResponse: success=${loginResponse.success}, message=${loginResponse.message}',
      );
      print('🔍 LoginData: ${loginResponse.data != null ? 'exists' : 'null'}');

      if (loginResponse.data != null) {
        print('🔍 User: ${loginResponse.data!.user.username}');
        print(
          '🔍 Token: ${loginResponse.data!.effectiveToken.isNotEmpty ? 'exists' : 'empty'}',
        );
        print(
          '🔍 Roles: ${loginResponse.data!.user.roles.map((r) => r.name).toList()}',
        );
        print(
          '🔍 Permissions: ${loginResponse.data!.user.permissions.length} permissions',
        );
      }

      if (loginResponse.success && loginResponse.data != null) {
        // Save authentication data
        await _saveAuthData(
          token: loginResponse.data!.effectiveToken,
          user: loginResponse.data!.user,
          authType: 'jwt',
          loginType: LoginType.staff,
        );

        // Start auto token refresh
        AutoTokenRefreshService.startAutoRefresh();
        await _pushNotificationService
            .registerCurrentDevice(loginResponse.data!.user);
      }

      return loginResponse;
    } on ApiException catch (e) {
      print('🔍 ApiException: ${e.message}');
      return LoginResponse(success: false, message: e.userFriendlyMessage);
    } catch (e, stackTrace) {
      print('🔍 Unknown error in loginStaff: $e');
      print('🔍 Stack trace: $stackTrace');
      return LoginResponse(success: false, message: 'Login error: $e');
    }
  }

  // Login untuk siswa
  Future<LoginResponse> loginStudent({
    required String nis,
    required String tanggalLahir,
  }) async {
    try {
      await _ensureLiveTrackingStopped();

      final deviceContext = await _resolveRequiredLoginDeviceContext();
      if (deviceContext == null) {
        return LoginResponse(
          success: false,
          message:
              'Tidak dapat mengidentifikasi perangkat. Tutup aplikasi lalu coba lagi. Jika tetap gagal, hapus aplikasi lalu instal ulang versi terbaru.',
        );
      }

      final request = StudentLoginRequest(nis: nis, tanggalLahir: tanggalLahir);

      // Add device binding data to request
      final requestData = request.toJson();
      requestData.addAll(deviceContext);
      requestData.addAll(await _buildPushLoginPayload());

      final response = await _apiService.post(
        AppConstants.loginSiswaEndpoint,
        data: requestData,
      );

      final loginResponse = LoginResponse.fromJson(response.data);

      if (loginResponse.success && loginResponse.data != null) {
        // Save authentication data
        await _saveAuthData(
          token: loginResponse.data!.effectiveToken,
          user: loginResponse.data!.user,
          authType: 'jwt',
          loginType: LoginType.student,
        );

        // Handle device binding after successful login
        await _handleDeviceBinding(loginResponse.data!.user);

        // Start auto token refresh
        AutoTokenRefreshService.startAutoRefresh();
        await _pushNotificationService
            .registerCurrentDevice(loginResponse.data!.user);
      }

      return loginResponse;
    } on ApiException catch (e) {
      return LoginResponse(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return LoginResponse(success: false, message: AppStrings.unknownError);
    }
  }

  // Get user profile
  Future<ApiResponse<User>> getProfile() async {
    try {
      final response = await _apiService.get(AppConstants.profileEndpoint);

      if (response.data['success'] == true && response.data['data'] != null) {
        final user = User.fromJson(response.data['data']);

        // Update stored user data
        await _storage.write(
          key: AppConstants.userKey,
          value: jsonEncode(user.toJson()),
        );

        return ApiResponse<User>(
          success: true,
          message: response.data['message'] ?? 'Profile retrieved successfully',
          data: user,
        );
      } else {
        return ApiResponse<User>(
          success: false,
          message: response.data['message'] ?? 'Failed to get profile',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<User>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<User>(
        success: false,
        message: AppStrings.unknownError,
      );
    }
  }

  // Logout
  Future<bool> logout() async {
    try {
      // Try to call logout endpoint
      await _apiService.post(AppConstants.logoutEndpoint);
    } catch (e) {
      // Continue with local logout even if API call fails
      print('Logout API call failed: $e');
    }

    // Clear local storage
    await _clearAuthData();
    return true;
  }

  // Refresh token
  Future<bool> refreshToken() async {
    try {
      final response = await _apiService.post(
        AppConstants.refreshTokenEndpoint,
      );

      final body = response.data is Map<String, dynamic>
          ? response.data as Map<String, dynamic>
          : <String, dynamic>{};
      final payload = body['data'] is Map<String, dynamic>
          ? body['data'] as Map<String, dynamic>
          : body;
      final newToken = (payload['token'] ??
              payload['access_token'] ??
              body['token'] ??
              body['access_token'])
          ?.toString();

      if ((body['success'] == true || newToken != null) &&
          newToken != null &&
          newToken.isNotEmpty) {
        await _apiService.setToken(newToken);
        AutoTokenRefreshService.startAutoRefresh();
        return true;
      }
      return false;
    } catch (e) {
      print('Token refresh failed: $e');
      return false;
    }
  }

  // Check if user is authenticated
  Future<bool> isAuthenticated() async {
    final token = await _apiService.getToken();
    return token != null && token.isNotEmpty;
  }

  // Get stored user data
  Future<User?> getStoredUser() async {
    try {
      final userJson = await _storage.read(key: AppConstants.userKey);
      if (userJson != null) {
        final userMap = jsonDecode(userJson);
        return User.fromJson(userMap);
      }
    } catch (e) {
      print('Error reading stored user: $e');
    }
    return null;
  }

  // Get stored login type
  Future<LoginType?> getStoredLoginType() async {
    try {
      final user = await getStoredUser();
      if (user != null) {
        return user.isSiswa ? LoginType.student : LoginType.staff;
      }
    } catch (e) {
      print('Error getting login type: $e');
    }
    return null;
  }

  // Auto login check
  Future<AuthState> checkAuthState() async {
    try {
      final token = await _apiService.getToken();
      if (token == null || token.isEmpty) {
        return AuthState(isAuthenticated: false);
      }

      final user = await getStoredUser();
      if (user == null) {
        // Token exists but no user data, try to get profile
        final profileResponse = await getProfile();
        if (profileResponse.success && profileResponse.data != null) {
          final loginType = profileResponse.data!.isSiswa
              ? LoginType.student
              : LoginType.staff;
          AutoTokenRefreshService.startAutoRefresh();
          await _pushNotificationService
              .registerCurrentDevice(profileResponse.data!);
          if (!profileResponse.data!.isSiswa) {
            await _ensureLiveTrackingStopped();
          }

          return AuthState(
            isAuthenticated: true,
            user: profileResponse.data,
            token: token,
            loginType: loginType,
          );
        } else {
          // Profile fetch failed, clear invalid token
          await _clearAuthData();
          return AuthState(isAuthenticated: false);
        }
      }

      final loginType = user.isSiswa ? LoginType.student : LoginType.staff;
      AutoTokenRefreshService.startAutoRefresh();
      await _pushNotificationService.registerCurrentDevice(user);
      if (!user.isSiswa) {
        await _ensureLiveTrackingStopped();
      }
      return AuthState(
        isAuthenticated: true,
        user: user,
        token: token,
        loginType: loginType,
      );
    } catch (e) {
      print('Auth state check failed: $e');
      await _clearAuthData();
      return AuthState(isAuthenticated: false);
    }
  }

  // Private methods
  Future<void> _saveAuthData({
    required String token,
    required User user,
    required String authType,
    required LoginType loginType,
  }) async {
    await Future.wait([
      _apiService.setToken(token),
      _storage.write(
        key: AppConstants.userKey,
        value: jsonEncode(user.toJson()),
      ),
      _storage.write(key: AppConstants.authTypeKey, value: authType),
    ]);
  }

  Future<Map<String, dynamic>> _buildPushLoginPayload() async {
    final payload = <String, dynamic>{
      'device_type': Platform.isIOS ? 'ios' : 'android',
    };

    try {
      if (Firebase.apps.isEmpty) {
        await Firebase.initializeApp();
      }

      final token = await FirebaseMessaging.instance.getToken();
      if (token != null && token.isNotEmpty) {
        payload['push_token'] = token;
      }
    } catch (e) {
      print('Unable to resolve FCM token before login: $e');
    }

    return payload;
  }

  Future<void> _clearAuthData() async {
    await _ensureLiveTrackingStopped();

    await Future.wait([
      _apiService.clearToken(),
      _storage.delete(key: AppConstants.userKey),
      _storage.delete(key: AppConstants.authTypeKey),
      _storage.delete(key: AppConstants.rememberMeKey),
    ]);

    await _attendanceReminderService.cancelAllAttendanceReminders();

    // Stop auto token refresh when clearing auth data
    AutoTokenRefreshService.stopAutoRefresh();
  }

  Future<void> _ensureLiveTrackingStopped() async {
    await _liveTrackingBackgroundService.stopTracking();

    if (_liveTrackingService.isTracking) {
      _liveTrackingService.stopTracking();
    }
  }

  Future<Map<String, dynamic>?> _resolveRequiredLoginDeviceContext() async {
    final deviceId = (await DeviceBindingMiddleware.getDeviceId()).trim();
    if (deviceId.isEmpty) {
      return null;
    }

    final deviceName = (await DeviceBindingMiddleware.getDeviceName()).trim();
    final deviceInfo = await DeviceBindingMiddleware.getDeviceInfo();

    return {
      'device_id': deviceId,
      'device_name': deviceName.isNotEmpty ? deviceName : 'Mobile App',
      'device_info': deviceInfo,
    };
  }

  /// Handle device binding after successful login
  Future<void> _handleDeviceBinding(User user) async {
    try {
      if (!user.isSiswa) {
        print('Device binding skipped for non-student account');
        return;
      }
      print('🔒 Starting device binding process...');

      // Check current device binding status
      final status = await DeviceBindingMiddleware.checkDeviceBinding();

      if (!status.isBound && status.canBind) {
        print('🔒 Device not bound, attempting to bind...');

        // Try to bind device automatically
        final result = await DeviceBindingMiddleware.bindDevice();

        if (result.success) {
          print('🔒 Device bound successfully');
        } else if (result.isAlreadyBound) {
          print('🔒 Device already bound to another account');
          // This is a critical security issue - logout user
          await logout();
          throw Exception(
              'Device sudah terikat dengan akun lain. Silakan hubungi administrator.');
        } else {
          print('🔒 Failed to bind device: ${result.message}');
          // Continue without binding for now, but log the issue
        }
      } else if (status.isBound) {
        print('🔒 Device already bound, validating access...');

        // Validate device access
        final accessResult =
            await DeviceBindingMiddleware.validateDeviceAccess();

        if (!accessResult.success) {
          print('🔒 Device access denied: ${accessResult.message}');

          if (accessResult.isBlocked) {
            // Device is blocked - logout user
            await logout();
            throw Exception(
                'Akses device ditolak. Device ini tidak diizinkan untuk akun ini.');
          }
        } else {
          print('🔒 Device access validated successfully');
        }
      }
    } catch (e) {
      print('🔒 Device binding error: $e');
      // Re-throw critical errors
      if (e.toString().contains('Device sudah terikat') ||
          e.toString().contains('Akses device ditolak')) {
        rethrow;
      }
      // For other errors, continue but log them
      print('🔒 Non-critical device binding error, continuing...');
    }
  }

  // Validation helpers
  static String? validateEmail(String? email) {
    if (email == null || email.isEmpty) {
      return AppStrings.emailRequired;
    }

    final emailRegex = RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$');
    if (!emailRegex.hasMatch(email)) {
      return AppStrings.emailInvalid;
    }

    return null;
  }

  static String? validatePassword(String? password) {
    if (password == null || password.isEmpty) {
      return AppStrings.passwordRequired;
    }

    if (password.length < AppConstants.minPasswordLength) {
      return AppStrings.passwordTooShort;
    }

    return null;
  }

  static String? validateNIS(String? nis) {
    if (nis == null || nis.isEmpty) {
      return AppStrings.nisRequired;
    }

    return null;
  }

  static String? validateBirthDate(String? birthDate) {
    if (birthDate == null || birthDate.isEmpty) {
      return AppStrings.birthDateRequired;
    }

    // Validate DD/MM/YYYY format
    final dateRegex = RegExp(r'^\d{2}/\d{2}/\d{4}$');
    if (!dateRegex.hasMatch(birthDate)) {
      return AppStrings.birthDateInvalid;
    }

    try {
      final parts = birthDate.split('/');
      final day = int.parse(parts[0]);
      final month = int.parse(parts[1]);
      final year = int.parse(parts[2]);

      final date = DateTime(year, month, day);
      final now = DateTime.now();

      if (date.isAfter(now)) {
        return AppStrings.birthDateInvalid;
      }

      return null;
    } catch (e) {
      return AppStrings.birthDateInvalid;
    }
  }
}
