import 'package:flutter/foundation.dart';
import '../models/user.dart';
import '../services/auth_service.dart';

class ClassProvider extends ChangeNotifier {
  final AuthService _authService = AuthService();

  String? _kelasNama;
  bool _isLoading = false;
  String? _error;
  User? _currentUser;

  // Getters
  String? get kelasNama => _kelasNama;
  bool get isLoading => _isLoading;
  String? get error => _error;

  // Initialize class data
  Future<void> initialize(User? user) async {
    if (user == null) {
      _kelasNama = null;
      _currentUser = null;
      notifyListeners();
      return;
    }

    _currentUser = user;

    // Set initial data from user
    _kelasNama = user.kelasNama;

    // For students, ensure we have class data
    if (user.isSiswa && (_kelasNama == null || _kelasNama!.isEmpty)) {
      // Try to get from user properties
      if (user.nis != null) {
        _kelasNama = user.kelasNama ?? 'Kelas tidak ditemukan';
      }
    }

    notifyListeners();

    // Only refetch when stored profile data does not already have class info.
    if (user.isSiswa && (_kelasNama == null || _kelasNama!.isEmpty)) {
      refreshClassData();
    }
  }

  // Refresh class data from API
  Future<bool> refreshClassData() async {
    if (_currentUser == null) return false;

    _setLoading(true);
    _clearError();

    try {
      // Get fresh profile data from API
      final response = await _authService.getProfile();

      if (response.success && response.data != null) {
        final updatedUser = response.data!;
        final newKelasNama = updatedUser.kelasNama;

        // Update current user reference
        _currentUser = updatedUser;

        // Update class name if changed
        if (_kelasNama != newKelasNama) {
          _kelasNama = newKelasNama;
        }

        // For students, ensure we have class data
        if (updatedUser.isSiswa &&
            (_kelasNama == null || _kelasNama!.isEmpty)) {
          _kelasNama = 'Kelas tidak ditemukan';
        }

        _setLoading(false);
        notifyListeners();
        return true;
      } else {
        _setError(response.message);
        _setLoading(false);
        return false;
      }
    } catch (e) {
      _setError('Failed to refresh class data: $e');
      _setLoading(false);
      return false;
    }
  }

  // Update class data manually (for immediate UI updates)
  void updateKelasNama(String? newKelasNama) {
    if (_kelasNama != newKelasNama) {
      _kelasNama = newKelasNama;
      notifyListeners();
    }
  }

  // Get class name with fallback logic
  String getKelasNamaWithFallback() {
    // Priority 1: ClassProvider data
    if (_kelasNama != null && _kelasNama!.isNotEmpty) {
      return _kelasNama!;
    }

    // Priority 2: Current user data
    if (_currentUser != null) {
      if (_currentUser!.isSiswa) {
        return _currentUser!.kelasNama ?? 'Kelas tidak ditemukan';
      } else {
        return _currentUser!.statusKepegawaian ?? 'Pegawai';
      }
    }

    // Priority 3: Default fallback
    return 'Data tidak tersedia';
  }

  // Clear class data
  void clear() {
    _kelasNama = null;
    _currentUser = null;
    _isLoading = false;
    _error = null;
    notifyListeners();
  }

  // Private helper methods
  void _setLoading(bool loading) {
    _isLoading = loading;
    notifyListeners();
  }

  void _setError(String error) {
    _error = error;
    notifyListeners();
  }

  void _clearError() {
    _error = null;
    notifyListeners();
  }

  // Debug info
  Map<String, dynamic> get debugInfo {
    return {
      'kelasNama': _kelasNama,
      'isLoading': _isLoading,
      'error': _error,
      'hasCurrentUser': _currentUser != null,
      'currentUserIsSiswa': _currentUser?.isSiswa ?? false,
      'currentUserKelasNama': _currentUser?.kelasNama,
      'fallbackResult': getKelasNamaWithFallback(),
    };
  }
}
