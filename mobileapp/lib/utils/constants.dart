import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';

class AppConstants {
  // API Configuration
  static const String baseUrl = 'https://load.sman1sumbercirebon.sch.id/api';
  static const String baseUrlLocal = 'http://localhost:8000/api';

  // API Endpoints
  static const String loginMobileEndpoint = '/mobile/login';
  static const String loginSiswaEndpoint = '/mobile/login-siswa';
  static const String profileEndpoint = '/profile';
  static const String logoutEndpoint = '/logout';
  static const String refreshTokenEndpoint = '/refresh-token';
  static const String registerDeviceTokenEndpoint = '/device-tokens/register';

  // Storage Keys
  static const String tokenKey = 'auth_token';
  static const String userKey = 'user_data';
  static const String authTypeKey = 'auth_type';
  static const String rememberMeKey = 'remember_me';

  // App Info
  static const String appName = 'SIAP Absensi';
  static const String appVersion = '1.0.0';
  static const String appBuildNumber = '1';

  // Feature Flags
  static const bool enableBackgroundLiveTracking = bool.fromEnvironment(
    'ENABLE_BACKGROUND_LIVE_TRACKING',
    // Release build defaults to ON so distributed APKs can keep live tracking
    // active in background for siswa after permission flow is completed.
    // Debug/profile can still opt in via --dart-define when needed.
    defaultValue: kReleaseMode,
  );

  // Live Tracking Foreground Service
  static const String liveTrackingForegroundNotificationChannelId =
      'live_tracking_background';
  static const String liveTrackingForegroundNotificationChannelName =
      'Live Tracking Background';
  static const int liveTrackingForegroundNotificationId = 3110;

  // Timeouts
  static const int connectionTimeout = 30000; // 30 seconds
  static const int receiveTimeout = 30000; // 30 seconds

  // Date Formats
  static const String dateFormat = 'dd/MM/yyyy';
  static const String dateTimeFormat = 'dd/MM/yyyy HH:mm';

  // Validation
  static const int minPasswordLength = 8;
  static const int maxPasswordLength = 50;
}

class AppColors {
  static const int primaryColorValue =
      0xFF64B5F6; // Light Blue - biru muda yang jelas
  static const int accentColorValue = 0xFF42A5F5; // Blue 400
  static const int errorColorValue = 0xFFB00020;
  static const int successColorValue = 0xFF4CAF50;
  static const int warningColorValue = 0xFFFF9800;

  // Color objects for Flutter widgets
  static const Color primary = Color(primaryColorValue);
  static const Color accent = Color(accentColorValue);
  static const Color error = Color(errorColorValue);
  static const Color success = Color(successColorValue);
  static const Color warning = Color(warningColorValue);
}

class AppStrings {
  // Login Screen
  static const String loginTitle = 'Masuk ke Sistem';
  static const String loginSubtitle = 'Silakan masuk dengan akun Anda';
  static const String emailLabel = 'Email';
  static const String passwordLabel = 'Password';
  static const String nisLabel = 'NIS';
  static const String birthDateLabel = 'Tanggal Lahir';
  static const String loginButton = 'Masuk';
  static const String loginAsStudent = 'Masuk sebagai Siswa';
  static const String loginAsStaff = 'Masuk sebagai Pegawai';
  static const String rememberMe = 'Ingat saya';
  static const String forgotPassword = 'Lupa password?';

  // Validation Messages
  static const String emailRequired = 'Email harus diisi';
  static const String emailInvalid = 'Format email tidak valid';
  static const String passwordRequired = 'Password harus diisi';
  static const String passwordTooShort = 'Password minimal 8 karakter';
  static const String nisRequired = 'NIS harus diisi';
  static const String birthDateRequired = 'Tanggal lahir harus diisi';
  static const String birthDateInvalid = 'Format tanggal tidak valid';

  // Error Messages
  static const String loginFailed = 'Login gagal';
  static const String invalidCredentials = 'Email atau password salah';
  static const String invalidStudentCredentials =
      'NIS atau tanggal lahir salah';
  static const String networkError = 'Tidak dapat terhubung ke server';
  static const String serverError = 'Terjadi kesalahan pada server';
  static const String unknownError = 'Terjadi kesalahan yang tidak diketahui';

  // Success Messages
  static const String loginSuccess = 'Login berhasil';
  static const String logoutSuccess = 'Logout berhasil';

  // General
  static const String loading = 'Memuat...';
  static const String retry = 'Coba Lagi';
  static const String cancel = 'Batal';
  static const String ok = 'OK';
  static const String yes = 'Ya';
  static const String no = 'Tidak';
}
