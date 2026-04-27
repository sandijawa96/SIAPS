import 'package:flutter/material.dart';
import '../utils/constants.dart';

class AttendancePopup {
  static Future<void> showLoadingPopup(
    BuildContext context,
    String actionType, {
    VoidCallback? onShown,
    ValueChanged<Route<dynamic>>? onRouteReady,
  }) {
    var hasNotifiedShown = false;
    var hasNotifiedRoute = false;
    return showDialog<void>(
      context: context,
      barrierDismissible: false,
      useRootNavigator: true,
      builder: (BuildContext context) {
        if (!hasNotifiedRoute && onRouteReady != null) {
          final route = ModalRoute.of(context);
          if (route != null) {
            hasNotifiedRoute = true;
            onRouteReady(route);
          }
        }

        if (!hasNotifiedShown && onShown != null) {
          hasNotifiedShown = true;
          WidgetsBinding.instance.addPostFrameCallback((_) {
            onShown();
          });
        }

        return PopScope(
          canPop: false,
          child: Dialog(
            backgroundColor: Colors.transparent,
            child: Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(15),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withOpacity(0.3),
                    spreadRadius: 2,
                    blurRadius: 10,
                    offset: const Offset(0, 5),
                  ),
                ],
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  // Loading animation
                  Container(
                    width: 60,
                    height: 60,
                    decoration: BoxDecoration(
                      color:
                          Color(AppColors.primaryColorValue).withOpacity(0.1),
                      shape: BoxShape.circle,
                    ),
                    child: Center(
                      child: CircularProgressIndicator(
                        valueColor: AlwaysStoppedAnimation<Color>(
                          Color(AppColors.primaryColorValue),
                        ),
                        strokeWidth: 3,
                      ),
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Title
                  Text(
                    'Mengirim Data ${actionType == 'checkin' ? 'Check-in' : 'Check-out'}',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: Colors.grey[800],
                    ),
                    textAlign: TextAlign.center,
                  ),

                  const SizedBox(height: 10),

                  // Description
                  Text(
                    'Mohon tunggu sebentar...\nData sedang dikirim ke server',
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey[600],
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  static void showSuccessPopup(
    BuildContext context,
    String actionType,
    String time, {
    Map<String, dynamic>? verification,
    Map<String, dynamic>? securityNotice,
    VoidCallback? onClose,
  }) {
    final verificationStatus = verification?['status']?.toString() ??
        verification?['result']?.toString();
    final verificationMode = verification?['mode']?.toString();
    final verificationScore = _parseDouble(verification?['score']);
    final hasVerificationInfo =
        verificationStatus != null || verificationMode != null;
    final securityIssues = securityNotice?['issues'] is List
        ? List<dynamic>.from(securityNotice?['issues'] as List)
        : const <dynamic>[];
    final hasSecurityNotice = securityNotice != null && securityIssues.isNotEmpty;

    showDialog(
      context: context,
      barrierDismissible: false,
      useRootNavigator: true,
      builder: (BuildContext context) {
        return Dialog(
          backgroundColor: Colors.transparent,
          child: Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(15),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.3),
                  spreadRadius: 2,
                  blurRadius: 10,
                  offset: const Offset(0, 5),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Success icon
                Container(
                  width: 80,
                  height: 80,
                  decoration: BoxDecoration(
                    color: Colors.green.withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.check_circle,
                    size: 50,
                    color: Colors.green[600],
                  ),
                ),

                const SizedBox(height: 20),

                // Title
                Text(
                  '${actionType == 'checkin' ? 'Check-in' : 'Check-out'} Berhasil!',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: Colors.grey[800],
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 15),

                // Time info
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 20, vertical: 12),
                  decoration: BoxDecoration(
                    color: Color(AppColors.primaryColorValue).withOpacity(0.1),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        Icons.access_time,
                        size: 20,
                        color: Color(AppColors.primaryColorValue),
                      ),
                      const SizedBox(width: 8),
                      Text(
                        'Waktu: $time',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          color: Color(AppColors.primaryColorValue),
                        ),
                      ),
                    ],
                  ),
                ),

                if (hasVerificationInfo) ...[
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: _verificationColor(verificationStatus)
                          .withOpacity(0.08),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: _verificationColor(verificationStatus)
                            .withOpacity(0.25),
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              _verificationIcon(verificationStatus),
                              size: 16,
                              color: _verificationColor(verificationStatus),
                            ),
                            const SizedBox(width: 6),
                            Text(
                              'Verifikasi Wajah: ${_verificationLabel(verificationStatus)}',
                              style: TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w700,
                                color: _verificationColor(verificationStatus),
                              ),
                            ),
                          ],
                        ),
                        if (verificationMode != null) ...[
                          const SizedBox(height: 6),
                          Text(
                            'Mode: ${verificationMode == 'async_pending' ? 'Pending Verification' : 'Final Langsung'}',
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.grey[700],
                            ),
                          ),
                        ],
                        if (verificationScore != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            'Skor: ${verificationScore.toStringAsFixed(3)}',
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.grey[700],
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],

                if (hasSecurityNotice) ...[
                  const SizedBox(height: 12),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.amber.withOpacity(0.10),
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(
                        color: Colors.amber.withOpacity(0.28),
                      ),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.warning_amber_rounded,
                              size: 16,
                              color: Colors.amber[800],
                            ),
                            const SizedBox(width: 6),
                            Expanded(
                              child: Text(
                                securityNotice?['title']?.toString() ??
                                    'Warning keamanan tercatat',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w700,
                                  color: Colors.amber[900],
                                ),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 6),
                        Text(
                          securityNotice?['message']?.toString() ??
                              'Absensi tetap diproses dan warning keamanan disimpan untuk monitoring.',
                          style: TextStyle(
                            fontSize: 12,
                            color: Colors.grey[700],
                          ),
                        ),
                        const SizedBox(height: 8),
                        Wrap(
                          spacing: 6,
                          runSpacing: 6,
                          children: securityIssues.map((issue) {
                            final label = issue is Map
                                ? issue['label']?.toString()
                                : issue?.toString();
                            return Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: 10,
                                vertical: 6,
                              ),
                              decoration: BoxDecoration(
                                color: Colors.white,
                                borderRadius: BorderRadius.circular(999),
                                border: Border.all(
                                  color: Colors.amber.withOpacity(0.28),
                                ),
                              ),
                              child: Text(
                                label ?? '-',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: Colors.amber[900],
                                ),
                              ),
                            );
                          }).toList(growable: false),
                        ),
                      ],
                    ),
                  ),
                ],

                const SizedBox(height: 20),

                // Description
                Text(
                  actionType == 'checkin'
                      ? 'Data absensi masuk telah tersimpan\ndengan selfie dan lokasi GPS'
                      : 'Data absensi pulang telah tersimpan\ndengan selfie dan lokasi GPS',
                  style: TextStyle(
                    fontSize: 14,
                    color: Colors.grey[600],
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 25),

                // Close button
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: () {
                      Navigator.of(context, rootNavigator: true).pop();
                      if (onClose != null) onClose();
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Color(AppColors.primaryColorValue),
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(10),
                      ),
                      elevation: 2,
                    ),
                    child: const Text(
                      'Tutup',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  static void showErrorPopup(
      BuildContext context, String actionType, String errorMessage,
      {VoidCallback? onRetry}) {
    showDialog(
      context: context,
      barrierDismissible: false,
      useRootNavigator: true,
      builder: (BuildContext context) {
        return Dialog(
          backgroundColor: Colors.transparent,
          child: Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(15),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.3),
                  spreadRadius: 2,
                  blurRadius: 10,
                  offset: const Offset(0, 5),
                ),
              ],
            ),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Error icon
                Container(
                  width: 80,
                  height: 80,
                  decoration: BoxDecoration(
                    color: Colors.red.withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    Icons.error_outline,
                    size: 50,
                    color: Colors.red[600],
                  ),
                ),

                const SizedBox(height: 20),

                // Title
                Text(
                  '${actionType == 'checkin' ? 'Check-in' : 'Check-out'} Gagal',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.bold,
                    color: Colors.grey[800],
                  ),
                  textAlign: TextAlign.center,
                ),

                const SizedBox(height: 15),

                // Error message
                Container(
                  padding: const EdgeInsets.all(15),
                  decoration: BoxDecoration(
                    color: Colors.red.withOpacity(0.1),
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: Colors.red.withOpacity(0.3),
                      width: 1,
                    ),
                  ),
                  child: Text(
                    errorMessage,
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.red[700],
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),

                const SizedBox(height: 25),

                // Action buttons
                Row(
                  children: [
                    if (onRetry != null) ...[
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            Navigator.of(context, rootNavigator: true).pop();
                            onRetry();
                          },
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Color(AppColors.primaryColorValue),
                            side: BorderSide(
                              color: Color(AppColors.primaryColorValue),
                            ),
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          child: const Text(
                            'Coba Lagi',
                            style: TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                    ],
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () {
                          Navigator.of(context, rootNavigator: true).pop();
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: Colors.grey[600],
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 12),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                          elevation: 2,
                        ),
                        child: const Text(
                          'Tutup',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  static double? _parseDouble(dynamic value) {
    if (value == null) {
      return null;
    }
    if (value is double) {
      return value;
    }
    if (value is int) {
      return value.toDouble();
    }
    if (value is String) {
      return double.tryParse(value);
    }
    return null;
  }

  static String _verificationLabel(String? status) {
    switch ((status ?? '').toLowerCase()) {
      case 'verified':
        return 'Terverifikasi';
      case 'pending':
        return 'Pending';
      case 'manual_review':
        return 'Review Manual';
      case 'rejected':
        return 'Ditolak';
      default:
        return 'Tidak diketahui';
    }
  }

  static IconData _verificationIcon(String? status) {
    switch ((status ?? '').toLowerCase()) {
      case 'verified':
        return Icons.verified;
      case 'pending':
        return Icons.schedule;
      case 'manual_review':
        return Icons.rule;
      case 'rejected':
        return Icons.gpp_bad;
      default:
        return Icons.info_outline;
    }
  }

  static Color _verificationColor(String? status) {
    switch ((status ?? '').toLowerCase()) {
      case 'verified':
        return Colors.green;
      case 'pending':
        return Colors.orange;
      case 'manual_review':
        return Colors.blue;
      case 'rejected':
        return Colors.red;
      default:
        return Colors.grey;
    }
  }
}
