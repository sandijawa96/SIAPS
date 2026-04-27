import 'package:flutter/material.dart';
import '../services/permission_service_final.dart';
import 'notification_popup.dart';

class PermissionCheckerWidget extends StatefulWidget {
  final Widget child;
  final bool checkOnInit;
  final VoidCallback? onAllPermissionsGranted;
  final Function(Map<String, PermissionResult>)? onPermissionResults;

  const PermissionCheckerWidget({
    Key? key,
    required this.child,
    this.checkOnInit = true,
    this.onAllPermissionsGranted,
    this.onPermissionResults,
  }) : super(key: key);

  @override
  State<PermissionCheckerWidget> createState() =>
      _PermissionCheckerWidgetState();
}

class _PermissionCheckerWidgetState extends State<PermissionCheckerWidget> {
  Map<String, PermissionResult>? _permissionResults;
  bool _isLoading = false;
  bool _showPermissionDialog = false;

  @override
  void initState() {
    super.initState();
    if (widget.checkOnInit) {
      _checkPermissions();
    }
  }

  Future<void> _checkPermissions() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final results = await PermissionService.checkAllAttendancePermissions();
      setState(() {
        _permissionResults = results;
        _isLoading = false;
      });

      widget.onPermissionResults?.call(results);

      // Check if all permissions are granted
      final allGranted = results.values.every((result) => result.isGranted);
      if (allGranted) {
        widget.onAllPermissionsGranted?.call();
      } else {
        // Show permission dialog if some permissions are missing
        _showPermissionRequestDialog();
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
      });
      debugPrint('Error checking permissions: $e');
    }
  }

  Future<void> _requestPermissions() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final results = await PermissionService.requestAllAttendancePermissions();
      setState(() {
        _permissionResults = results;
        _isLoading = false;
        _showPermissionDialog = false;
      });

      widget.onPermissionResults?.call(results);

      // Check if all permissions are granted
      final allGranted = results.values.every((result) => result.isGranted);
      if (allGranted) {
        widget.onAllPermissionsGranted?.call();
        _showSuccessSnackBar();
      } else {
        _showPermissionDeniedDialog();
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
      });
      debugPrint('Error requesting permissions: $e');
    }
  }

  void _showPermissionRequestDialog() {
    if (!mounted) return;

    setState(() {
      _showPermissionDialog = true;
    });

    showDialog(
      context: context,
      useRootNavigator: true,
      barrierDismissible: false,
      builder: (context) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.security, color: Colors.orange),
            SizedBox(width: 8),
            Text('Izin Diperlukan'),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Aplikasi memerlukan izin berikut untuk berfungsi dengan baik:',
              style: TextStyle(fontSize: 16),
            ),
            const SizedBox(height: 16),
            _buildPermissionItem(
              icon: Icons.camera_alt,
              title: 'Kamera',
              description: 'Untuk mengambil foto selfie saat absensi',
              isGranted: _permissionResults?['camera']?.isGranted ?? false,
            ),
            _buildPermissionItem(
              icon: Icons.location_on,
              title: 'Lokasi',
              description: 'Untuk memverifikasi lokasi absensi',
              isGranted: _permissionResults?['location']?.isGranted ?? false,
            ),
            _buildPermissionItem(
              icon: Icons.storage,
              title: 'Penyimpanan',
              description: 'Untuk menyimpan foto dan data',
              isGranted: _permissionResults?['storage']?.isGranted ?? false,
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.of(context, rootNavigator: true).pop();
              setState(() {
                _showPermissionDialog = false;
              });
            },
            child: const Text('Nanti'),
          ),
          ElevatedButton(
            onPressed: _isLoading
                ? null
                : () {
                    Navigator.of(context, rootNavigator: true).pop();
                    _requestPermissions();
                  },
            child: _isLoading
                ? const SizedBox(
                    width: 16,
                    height: 16,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Berikan Izin'),
          ),
        ],
      ),
    );
  }

  void _showPermissionDeniedDialog() {
    if (!mounted) return;

    showDialog(
      context: context,
      useRootNavigator: true,
      builder: (context) => AlertDialog(
        title: const Row(
          children: [
            Icon(Icons.warning, color: Colors.red),
            SizedBox(width: 8),
            Text('Izin Ditolak'),
          ],
        ),
        content: const Text(
          'Beberapa izin ditolak. Aplikasi mungkin tidak berfungsi dengan baik. '
          'Anda dapat mengaktifkan izin melalui pengaturan aplikasi.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context, rootNavigator: true).pop(),
            child: const Text('Tutup'),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.of(context, rootNavigator: true).pop();
              PermissionService.openAppSettings();
            },
            child: const Text('Buka Pengaturan'),
          ),
        ],
      ),
    );
  }

  void _showSuccessSnackBar() {
    if (!mounted) return;

    NotificationPopup.showSuccess(
      context,
      title: 'Berhasil!',
      message: 'Semua izin telah diberikan',
    );
  }

  Widget _buildPermissionItem({
    required IconData icon,
    required String title,
    required String description,
    required bool isGranted,
  }) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Icon(
            icon,
            color: isGranted ? Colors.green : Colors.grey,
            size: 20,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontWeight: FontWeight.w500,
                    color: isGranted ? Colors.green : Colors.black87,
                  ),
                ),
                Text(
                  description,
                  style: const TextStyle(
                    fontSize: 12,
                    color: Colors.grey,
                  ),
                ),
              ],
            ),
          ),
          Icon(
            isGranted ? Icons.check_circle : Icons.cancel,
            color: isGranted ? Colors.green : Colors.red,
            size: 16,
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading && _permissionResults == null) {
      return const Scaffold(
        body: Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircularProgressIndicator(),
              SizedBox(height: 16),
              Text('Memeriksa izin aplikasi...'),
            ],
          ),
        ),
      );
    }

    return Stack(
      children: [
        widget.child,
        if (_isLoading)
          Container(
            color: Colors.black26,
            child: const Center(
              child: CircularProgressIndicator(),
            ),
          ),
      ],
    );
  }
}

/// Simple permission status widget
class PermissionStatusWidget extends StatefulWidget {
  final bool showDetails;

  const PermissionStatusWidget({
    Key? key,
    this.showDetails = false,
  }) : super(key: key);

  @override
  State<PermissionStatusWidget> createState() => _PermissionStatusWidgetState();
}

class _PermissionStatusWidgetState extends State<PermissionStatusWidget> {
  Map<String, PermissionResult>? _permissionResults;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _checkPermissions();
  }

  Future<void> _checkPermissions() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final results = await PermissionService.checkAllAttendancePermissions();
      setState(() {
        _permissionResults = results;
        _isLoading = false;
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
      });
      debugPrint('Error checking permissions: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Row(
            children: [
              SizedBox(
                width: 16,
                height: 16,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
              SizedBox(width: 12),
              Text('Memeriksa izin...'),
            ],
          ),
        ),
      );
    }

    if (_permissionResults == null) {
      return const Card(
        child: Padding(
          padding: EdgeInsets.all(16),
          child: Text('Gagal memeriksa izin aplikasi'),
        ),
      );
    }

    final allGranted =
        _permissionResults!.values.every((result) => result.isGranted);

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  allGranted ? Icons.check_circle : Icons.warning,
                  color: allGranted ? Colors.green : Colors.orange,
                ),
                const SizedBox(width: 8),
                Text(
                  allGranted ? 'Semua izin aktif' : 'Beberapa izin tidak aktif',
                  style: const TextStyle(fontWeight: FontWeight.w500),
                ),
              ],
            ),
            if (widget.showDetails) ...[
              const SizedBox(height: 12),
              ..._permissionResults!.entries.map((entry) {
                final name = entry.key;
                final result = entry.value;
                return Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Row(
                    children: [
                      Icon(
                        result.isGranted ? Icons.check : Icons.close,
                        color: result.isGranted ? Colors.green : Colors.red,
                        size: 16,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        name.toUpperCase(),
                        style: const TextStyle(fontSize: 12),
                      ),
                    ],
                  ),
                );
              }).toList(),
            ],
          ],
        ),
      ),
    );
  }
}
