import 'package:flutter/foundation.dart';

import '../providers/auth_provider.dart';

class ManualDataSyncResult {
  final bool success;
  final String message;

  const ManualDataSyncResult({
    required this.success,
    required this.message,
  });
}

class ManualDataSyncService extends ChangeNotifier {
  ManualDataSyncService._internal();

  static final ManualDataSyncService _instance =
      ManualDataSyncService._internal();

  factory ManualDataSyncService() => _instance;

  bool _isSyncing = false;
  int _syncVersion = 0;
  DateTime? _lastSyncedAt;

  bool get isSyncing => _isSyncing;
  int get syncVersion => _syncVersion;
  DateTime? get lastSyncedAt => _lastSyncedAt;

  Future<ManualDataSyncResult> syncNonCriticalData(
    AuthProvider authProvider,
  ) async {
    if (_isSyncing) {
      return const ManualDataSyncResult(
        success: false,
        message: 'Sinkronisasi data sedang berjalan.',
      );
    }

    _isSyncing = true;
    notifyListeners();

    try {
      final refreshed = await authProvider.refreshProfile();
      if (!refreshed) {
        return ManualDataSyncResult(
          success: false,
          message: authProvider.error ?? 'Gagal menyinkronkan data mobile.',
        );
      }

      _lastSyncedAt = DateTime.now();
      _syncVersion++;
      return const ManualDataSyncResult(
        success: true,
        message: 'Data mobile non-kritis berhasil disinkronkan.',
      );
    } catch (e) {
      return ManualDataSyncResult(
        success: false,
        message: 'Gagal menyinkronkan data mobile: $e',
      );
    } finally {
      _isSyncing = false;
      notifyListeners();
    }
  }
}
