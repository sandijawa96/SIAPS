import 'package:flutter/foundation.dart';
import '../models/user.dart';
import '../models/login_response.dart';
import '../services/auth_service.dart';
import 'class_provider.dart';

class AuthProvider extends ChangeNotifier {
  final AuthService _authService = AuthService();
  final ClassProvider _classProvider = ClassProvider();

  AuthState _state = AuthState();
  AuthState get state => _state;

  // Expose class provider
  ClassProvider get classProvider => _classProvider;

  // Getters for convenience
  bool get isAuthenticated => _state.isAuthenticated;
  bool get isLoading => _state.isLoading;
  User? get user => _state.user;
  String? get token => _state.token;
  String? get error => _state.error;
  LoginType? get loginType => _state.loginType;

  // User role checks
  bool get isSuperAdmin => user?.isSuperAdmin ?? false;
  bool get isSiswa => user?.isSiswa ?? false;
  bool get isPegawai => user?.isPegawai ?? false;

  // Permission check
  bool hasPermission(String permission) {
    return user?.hasPermission(permission) ?? false;
  }

  bool hasRole(String roleName) {
    return user?.hasRole(roleName) ?? false;
  }

  // Initialize auth state
  Future<void> initialize() async {
    _updateState(_state.setLoading(true));

    try {
      final authState = await _authService.checkAuthState();
      _updateState(authState);

      // Initialize class provider if user is authenticated
      if (authState.isAuthenticated && authState.user != null) {
        await _classProvider.initialize(authState.user);
      }
    } catch (e) {
      _updateState(_state.setError('Failed to initialize auth: $e'));
    }
  }

  // Login for staff/pegawai
  Future<bool> loginStaff({
    required String email,
    required String password,
  }) async {
    _updateState(_state.setLoading(true));

    try {
      final response = await _authService.loginStaff(
        email: email,
        password: password,
      );

      if (response.success && response.data != null) {
        final user = response.data!.user;
        _updateState(
          _state.setAuthenticated(
            user,
            response.data!.effectiveToken,
            LoginType.staff,
          ),
        );

        // Initialize class provider
        await _classProvider.initialize(user);

        return true;
      } else {
        _updateState(_state.setError(response.message));
        return false;
      }
    } catch (e) {
      _updateState(_state.setError('Login failed: $e'));
      return false;
    }
  }

  // Login for students
  Future<bool> loginStudent({
    required String nis,
    required String tanggalLahir,
  }) async {
    _updateState(_state.setLoading(true));

    try {
      final response = await _authService.loginStudent(
        nis: nis,
        tanggalLahir: tanggalLahir,
      );

      if (response.success && response.data != null) {
        final user = response.data!.user;
        _updateState(
          _state.setAuthenticated(
            user,
            response.data!.effectiveToken,
            LoginType.student,
          ),
        );

        // Initialize class provider
        await _classProvider.initialize(user);

        return true;
      } else {
        _updateState(_state.setError(response.message));
        return false;
      }
    } catch (e) {
      _updateState(_state.setError('Login failed: $e'));
      return false;
    }
  }

  // Logout
  Future<void> logout() async {
    _updateState(_state.setLoading(true));

    try {
      await _authService.logout();
      _updateState(_state.setUnauthenticated());

      // Clear class provider
      _classProvider.clear();
    } catch (e) {
      // Even if logout API fails, clear local state
      _updateState(_state.setUnauthenticated());
      _classProvider.clear();
    }
  }

  // Refresh user profile and class data
  Future<bool> refreshProfile() async {
    if (!isAuthenticated) return false;

    try {
      final response = await _authService.getProfile();

      if (response.success && response.data != null) {
        final updatedUser = response.data!;

        // Update auth state
        _updateState(_state.copyWith(user: updatedUser));

        // Update class provider with fresh data
        await _classProvider.initialize(updatedUser);

        return true;
      } else {
        _updateState(_state.setError(response.message));
        return false;
      }
    } catch (e) {
      _updateState(_state.setError('Failed to refresh profile: $e'));
      return false;
    }
  }

  // Refresh only class data
  Future<bool> refreshClassData() async {
    if (!isAuthenticated) return false;
    return await _classProvider.refreshClassData();
  }

  // Refresh token
  Future<bool> refreshToken() async {
    if (!isAuthenticated) return false;

    try {
      final success = await _authService.refreshToken();
      if (!success) {
        // Token refresh failed, logout user
        await logout();
      }
      return success;
    } catch (e) {
      await logout();
      return false;
    }
  }

  // Clear error
  void clearError() {
    _updateState(_state.clearError());
  }

  // Update state and notify listeners
  void _updateState(AuthState newState) {
    _state = newState;
    notifyListeners();
  }

  // Method to refresh class data manually, can be called after hot restart
  Future<void> refreshClassDataManually() async {
    await _classProvider.refreshClassData();
    notifyListeners();
  }

  // Validation methods
  String? validateEmail(String? email) {
    return AuthService.validateEmail(email);
  }

  String? validatePassword(String? password) {
    return AuthService.validatePassword(password);
  }

  String? validateNIS(String? nis) {
    return AuthService.validateNIS(nis);
  }

  String? validateBirthDate(String? birthDate) {
    return AuthService.validateBirthDate(birthDate);
  }

  // Auto-retry login for expired tokens
  Future<bool> _handleTokenExpiry() async {
    try {
      // Try to refresh token first
      final refreshSuccess = await refreshToken();
      if (refreshSuccess) {
        return true;
      }

      // If refresh fails, logout user
      await logout();
      return false;
    } catch (e) {
      await logout();
      return false;
    }
  }

  // Check if token is about to expire (if you implement JWT expiry checking)
  bool get isTokenExpiringSoon {
    // Implement JWT token expiry checking if needed
    // For now, return false
    return false;
  }

  // Get user display information
  String get userDisplayName {
    return user?.displayName ?? 'User';
  }

  String get userIdentifier {
    return user?.identifier ?? '';
  }

  String get userRole {
    return user?.role ?? 'Unknown';
  }

  // Get class information with realtime data
  String get userKelasNama {
    // Use ClassProvider's fallback logic which handles all scenarios
    return _classProvider.getKelasNamaWithFallback();
  }

  // Check if user has specific permissions for UI rendering
  bool canAccessUserManagement() {
    return hasPermission('manage_users') || isSuperAdmin;
  }

  bool canAccessAttendanceSettings() {
    return hasPermission('manage_attendance_settings') || isSuperAdmin;
  }

  bool canViewReports() {
    return hasPermission('view_reports') || isSuperAdmin;
  }

  bool canManageClasses() {
    return hasPermission('manage_kelas') || isSuperAdmin;
  }

  // Debug information
  Map<String, dynamic> get debugInfo {
    return {
      'isAuthenticated': isAuthenticated,
      'isLoading': isLoading,
      'hasUser': user != null,
      'hasToken': token != null,
      'loginType': loginType?.toString(),
      'userRole': userRole,
      'permissions': user?.permissions ?? [],
      'error': error,
      'kelasNama': userKelasNama,
      'classProviderData': _classProvider.kelasNama,
    };
  }
}
