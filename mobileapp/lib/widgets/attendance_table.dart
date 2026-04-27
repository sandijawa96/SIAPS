import 'package:flutter/material.dart';
import 'dart:async';
import '../utils/constants.dart';
import '../hooks/use_attendance_state.dart';
import '../services/permission_service_final.dart';
import '../services/attendance_service.dart';
import '../services/dashboard_service.dart';
import '../services/dialog_guard_service.dart';
import '../widgets/enhanced_location_popup.dart';
import '../widgets/attendance_popup.dart';
import '../widgets/notification_popup.dart';
import '../providers/auth_provider.dart';
import '../providers/attendance_precheck_provider.dart';
import '../screens/face_template_screen.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

class AttendanceTable extends StatelessWidget {
  final AttendanceState state;
  final VoidCallback onTap;
  final VoidCallback? onRefresh;
  final int refreshToken;
  final bool isAttendanceLocked;
  final String? attendanceLockReason;
  final bool isHolidayToday;
  final String? holidayMessage;

  const AttendanceTable(
      {super.key,
      required this.state,
      required this.onTap,
      this.onRefresh,
      this.refreshToken = 0,
      this.isAttendanceLocked = false,
      this.attendanceLockReason,
      this.isHolidayToday = false,
      this.holidayMessage});

  @override
  Widget build(BuildContext context) {
    final primaryColor = Color(AppColors.primaryColorValue);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border:
            Border.all(color: primaryColor.withValues(alpha: 0.16), width: 1),
        boxShadow: [
          BoxShadow(
            color: primaryColor.withValues(alpha: 0.10),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: _FingerprintWithOverlay(
        state: state,
        onTap: onTap,
        onRefresh: onRefresh,
        refreshToken: refreshToken,
        isAttendanceLocked: isAttendanceLocked,
        attendanceLockReason: attendanceLockReason,
        isHolidayToday: isHolidayToday,
        holidayMessage: holidayMessage,
      ),
    );
  }
}

class _FingerprintWithOverlay extends StatefulWidget {
  final AttendanceState state;
  final VoidCallback onTap;
  final VoidCallback? onRefresh;
  final int refreshToken;
  final bool isAttendanceLocked;
  final String? attendanceLockReason;
  final bool isHolidayToday;
  final String? holidayMessage;

  const _FingerprintWithOverlay({
    required this.state,
    required this.onTap,
    this.onRefresh,
    this.refreshToken = 0,
    this.isAttendanceLocked = false,
    this.attendanceLockReason,
    this.isHolidayToday = false,
    this.holidayMessage,
  });

  @override
  State<_FingerprintWithOverlay> createState() =>
      _FingerprintWithOverlayState();
}

class _FingerprintWithOverlayState extends State<_FingerprintWithOverlay> {
  final AttendanceService _attendanceService = AttendanceService();
  bool _isProcessing = false;
  bool _isLoadingWorkingHours = false;

  // Working hours data - NO DEFAULT VALUES, ONLY FROM DATABASE
  String? _jamMasuk;
  String? _jamPulang;
  int? _toleransi;
  int? _minimalOpenTime;
  bool _workingHoursLoaded = false;
  bool _isHoliday = false;
  bool _holidayCheckLoaded = false;
  List<String> _hariKerjaAktif = const <String>[];
  bool _wajibGps = true;
  bool _wajibFoto = true;
  String _attendanceScope = 'siswa_only';
  String _verificationMode = 'async_pending';
  bool _faceVerificationEnabled = true;
  bool _faceTemplateRequired = true;
  bool _isUserAllowedByScope = true;

  @override
  void initState() {
    super.initState();
    _loadWorkingHours();
  }

  Future<void> _loadWorkingHours({bool forceRefresh = false}) async {
    if (_isLoadingWorkingHours) {
      return;
    }
    _isLoadingWorkingHours = true;

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final user = authProvider.user;
    final defaultPolicy = const AttendanceGlobalPolicy(
      verificationMode: 'async_pending',
      attendanceScope: 'siswa_only',
      targetTingkatIds: <int>[],
      targetKelasIds: <int>[],
      faceVerificationEnabled: true,
      faceTemplateRequired: true,
    );

    try {
      // REALTIME: Get fresh data from database only - NO FALLBACK
      final workingHoursResponse = await _attendanceService.getWorkingHours(
        forceRefresh: forceRefresh,
      );
      final policyResponse =
          await _attendanceService.getAttendancePolicy(
        user?.id,
        forceRefresh: forceRefresh,
      );
      final policy = policyResponse.data ?? defaultPolicy;

      if (workingHoursResponse.success && workingHoursResponse.data != null) {
        final workingHours = workingHoursResponse.data!;
        final minimalOpenTime = workingHours.minimalOpenTime;

        if (!mounted) return;
        setState(() {
          _jamMasuk = workingHours.jamMasuk;
          _jamPulang = workingHours.jamPulang;
          _toleransi = workingHours.toleransi;
          _minimalOpenTime = minimalOpenTime;
          _hariKerjaAktif = workingHours.hariKerja;
          _wajibGps = workingHours.wajibGps;
          _wajibFoto = workingHours.wajibFoto;
          _attendanceScope = policy.attendanceScope;
          _verificationMode = policy.verificationMode;
          _faceVerificationEnabled = policy.faceVerificationEnabled;
          _faceTemplateRequired = policy.faceTemplateRequired;
          _isUserAllowedByScope = policy.allowsUser(user);
          _workingHoursLoaded = true;
        });
        _checkHoliday();

        debugPrint('Realtime working hours from database:');
        debugPrint('   - Jam Masuk: $_jamMasuk');
        debugPrint('   - Jam Pulang: $_jamPulang');
        debugPrint('   - Toleransi: $_toleransi menit');
        debugPrint('   - Minimal Open Time: $_minimalOpenTime menit');
        debugPrint('   - Hari Kerja Aktif: $_hariKerjaAktif');
        debugPrint('   - Source: ${workingHours.source}');
        debugPrint('   - Schema ID: ${workingHours.schemaId}');
        debugPrint('   - Schema Name: ${workingHours.schemaName}');
        debugPrint('   - Wajib GPS: $_wajibGps');
        debugPrint('   - Wajib Foto: $_wajibFoto');
      } else {
        // NO FALLBACK - Show "-" if database has no data
        if (!mounted) return;
        setState(() {
          _jamMasuk = '-';
          _jamPulang = '-';
          _toleransi = 0;
          _minimalOpenTime = 0;
          _hariKerjaAktif = const <String>[];
          _wajibGps = true;
          _wajibFoto = true;
          _attendanceScope = policy.attendanceScope;
          _verificationMode = policy.verificationMode;
          _faceVerificationEnabled = policy.faceVerificationEnabled;
          _faceTemplateRequired = policy.faceTemplateRequired;
          _isUserAllowedByScope = policy.allowsUser(user);
          _workingHoursLoaded = true;
        });
        _checkHoliday();
        debugPrint('No data from database - showing "-"');
      }
    } catch (e) {
      debugPrint('Database error: $e');
      if (!mounted) return;
      setState(() {
        _jamMasuk = '-';
        _jamPulang = '-';
        _toleransi = 0;
        _minimalOpenTime = 0;
        _hariKerjaAktif = const <String>[];
        _wajibGps = true;
        _wajibFoto = true;
        _attendanceScope = defaultPolicy.attendanceScope;
        _verificationMode = defaultPolicy.verificationMode;
        _faceVerificationEnabled = defaultPolicy.faceVerificationEnabled;
        _faceTemplateRequired = defaultPolicy.faceTemplateRequired;
        _isUserAllowedByScope = defaultPolicy.allowsUser(user);
        _workingHoursLoaded = true;
      });
      _checkHoliday();
    } finally {
      _isLoadingWorkingHours = false;
    }
  }

  // Method to refresh working hours data (can be called when user pulls to refresh)
  Future<void> refreshWorkingHours() async {
    if (!mounted || _isLoadingWorkingHours) {
      return;
    }

    setState(() {
      _workingHoursLoaded = false;
    });
    await _loadWorkingHours(forceRefresh: true);
  }

  @override
  void didUpdateWidget(_FingerprintWithOverlay oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.refreshToken != widget.refreshToken && !_isProcessing) {
      unawaited(refreshWorkingHours());
    }
  }

  Future<void> _checkHoliday() async {
    try {
      final now = DateTime.now();
      final dayMap = <int, String>{
        DateTime.monday: 'Senin',
        DateTime.tuesday: 'Selasa',
        DateTime.wednesday: 'Rabu',
        DateTime.thursday: 'Kamis',
        DateTime.friday: 'Jumat',
        DateTime.saturday: 'Sabtu',
        DateTime.sunday: 'Minggu',
      };
      final todayName = dayMap[now.weekday] ?? 'Senin';
      final normalizedHariKerja = _hariKerjaAktif
          .map((e) => e.toString().trim().toLowerCase())
          .where((e) => e.isNotEmpty)
          .toSet();
      final hasHariKerja = normalizedHariKerja.isNotEmpty;
      final isWorkingDay = hasHariKerja
          ? normalizedHariKerja.contains(todayName.toLowerCase())
          : !(now.weekday == DateTime.saturday ||
              now.weekday == DateTime.sunday);
      if (!mounted) return;
      setState(() {
        _isHoliday = !isWorkingDay;
        _holidayCheckLoaded = true;
      });

      debugPrint('Holiday check from schema day list:');
      debugPrint('   - Hari ini: $todayName');
      debugPrint('   - Hari kerja aktif: $_hariKerjaAktif');
      debugPrint('   - Is working day: $isWorkingDay');
    } catch (e) {
      debugPrint('Error checking holiday: $e');
      if (!mounted) return;
      setState(() {
        _isHoliday = false;
        _holidayCheckLoaded = true;
      });
    }
  }

  bool _isAttendanceBlockedByScope() {
    return _attendanceScope == 'siswa_only' && !_isUserAllowedByScope;
  }

  bool _isAttendanceBlockedByMissingFaceTemplate() {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final user = authProvider.user;

    return (user?.isSiswa ?? false) &&
        _faceTemplateRequired &&
        !(user?.hasActiveFaceTemplate ?? false);
  }

  Future<void> _openFaceTemplateScreen() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const FaceTemplateScreen()),
    );

    if (!mounted) {
      return;
    }

    await Provider.of<AuthProvider>(context, listen: false).refreshProfile();
    if (!mounted) {
      return;
    }

    await refreshWorkingHours();
    widget.onRefresh?.call();
  }

  Future<void> _showFaceTemplateRequiredDialog() async {
    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          title: const Text('Template Wajah Wajib'),
          content: const Text(
            'Sebelum absensi, akun siswa ini harus memiliki template wajah aktif. Silakan rekam template wajah terlebih dahulu dari kamera.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Nanti'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.of(dialogContext).pop();
                unawaited(_openFaceTemplateScreen());
              },
              child: const Text('Buka Template'),
            ),
          ],
        );
      },
    );
  }

  Future<void> _showPrecheckFailure(
      AttendancePrecheckProvider precheck) async {
    if (precheck.failureType == 'location') {
      showEnhancedLocationPopup(
        context,
        distanceToArea: precheck.distanceToArea ?? 0.0,
        locationName: precheck.location,
        geofenceType: precheck.resolvedGeofenceType ?? 'circle',
        referenceDistance: precheck.allowedRadius,
        onRetry: () {
          Navigator.of(context, rootNavigator: true).pop();
          _handleFingerprintTap();
        },
        onClose: () {
          Navigator.of(context, rootNavigator: true).pop();
        },
        showForceOption: false,
      );
      return;
    }

    final title = precheck.failureType == 'mock'
        ? 'Lokasi Palsu Terdeteksi'
        : (precheck.failureType == 'accuracy'
            ? 'Akurasi GPS Belum Cukup'
            : (precheck.failureType == 'time'
                ? 'Jadwal Absensi'
                : 'Pra-cek Absensi'));
    final message = precheck.actionSecondaryMessage.trim().isNotEmpty
        ? '${precheck.actionPrimaryMessage}\n${precheck.actionSecondaryMessage}'
        : precheck.actionPrimaryMessage;

    NotificationPopup.showError(
      context,
      title: title,
      message: message,
    );
  }

  Future<bool> _showSecuritySubmitWarning(
    AttendancePrecheckProvider precheck,
  ) async {
    if (!precheck.hasSecurityWarnings) {
      return true;
    }

    final warnings = precheck.securityWarnings;
    final result = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: const Text('Warning Keamanan Saat Presensi'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Perangkat Anda terdeteksi memiliki indikator keamanan. Jika Anda tetap melanjutkan, absensi tetap diproses tetapi catatan warning ini akan tersimpan untuk monitoring dan klarifikasi.',
                ),
                const SizedBox(height: 14),
                ...warnings.map((issue) => Container(
                      width: double.infinity,
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: const Color(0xFFFFF8E1),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFFFE082)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            issue.label,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF8A4B00),
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            issue.message,
                            style: const TextStyle(
                              fontSize: 12,
                              color: Color(0xFF6B4F00),
                              height: 1.35,
                            ),
                          ),
                        ],
                      ),
                    )),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: const Text('Tidak'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(true),
              child: const Text('Ya'),
            ),
          ],
        );
      },
    );

    return result == true;
  }

  bool _shouldShowAttendanceButton() {
    if (widget.isAttendanceLocked) return false;
    if (!_workingHoursLoaded) return false;
    if (_isAttendanceBlockedByScope()) return false;
    if (_isAttendanceBlockedByMissingFaceTemplate()) return false;

    // Check if data is valid and not null
    if (_jamMasuk == null ||
        _jamPulang == null ||
        _minimalOpenTime == null ||
        _toleransi == null) {
      return false;
    }
    if (_jamMasuk == '-' || _jamPulang == '-') return false;

    final now = DateTime.now();
    final attendanceState =
        Provider.of<UseAttendanceState>(context, listen: false);
    final isCheckedIn = attendanceState.state.isCheckedIn;
    final hasCheckedOut = attendanceState.state.checkoutTime != null;

    try {
      // Parse jam masuk dan jam pulang with validation
      final jamMasukParts = _jamMasuk!.split(':');
      final jamPulangParts = _jamPulang!.split(':');

      if (jamMasukParts.length < 2 || jamPulangParts.length < 2) return false;

      final jamMasukTime = DateTime(now.year, now.month, now.day,
          int.parse(jamMasukParts[0]), int.parse(jamMasukParts[1]));
      final jamPulangTime = DateTime(now.year, now.month, now.day,
          int.parse(jamPulangParts[0]), int.parse(jamPulangParts[1]));

      // Window absen masuk: menggunakan minimal_open_time dari backend
      final windowMasukStart =
          jamMasukTime.subtract(Duration(minutes: _minimalOpenTime!));
      final windowMasukEnd = jamMasukTime.add(Duration(minutes: _toleransi!));

      if (!isCheckedIn && !hasCheckedOut) {
        // Belum check-in: tampilkan tombol jika dalam window absen masuk
        return now.isAfter(windowMasukStart) && now.isBefore(windowMasukEnd);
      } else if (isCheckedIn && !hasCheckedOut) {
        // Sudah check-in tapi belum check-out: tampilkan tombol jika sudah waktunya pulang
        return now.isAfter(jamPulangTime);
      }

      // Sudah check-out: jangan tampilkan tombol
      return false;
    } catch (e) {
      debugPrint('Error parsing time: $e');
      return false;
    }
  }

  String _getAttendanceButtonMessage() {
    if (widget.isAttendanceLocked) {
      final customMessage = (widget.attendanceLockReason ?? '').trim();
      if (customMessage.isNotEmpty) {
        return customMessage;
      }

      return 'Absensi dinonaktifkan karena status kehadiran hari ini bukan hadir/terlambat.';
    }

    if (!_workingHoursLoaded) return 'Memuat...';
    if (_isAttendanceBlockedByScope()) {
      return 'Absensi hanya tersedia untuk akun siswa.';
    }
    if (_isAttendanceBlockedByMissingFaceTemplate()) {
      return 'Template wajah wajib tersedia sebelum absensi.';
    }

    // Check if data is valid and not null
    if (_jamMasuk == null ||
        _jamPulang == null ||
        _minimalOpenTime == null ||
        _toleransi == null) {
      return 'Data jam kerja tidak tersedia';
    }
    if (_jamMasuk == '-' || _jamPulang == '-') {
      return 'Data jam kerja tidak tersedia';
    }

    final now = DateTime.now();
    final attendanceState =
        Provider.of<UseAttendanceState>(context, listen: false);
    final isCheckedIn = attendanceState.state.isCheckedIn;
    final hasCheckedOut = attendanceState.state.checkoutTime != null;

    try {
      // Parse jam masuk dan jam pulang with validation
      final jamMasukParts = _jamMasuk!.split(':');
      final jamPulangParts = _jamPulang!.split(':');

      if (jamMasukParts.length < 2 || jamPulangParts.length < 2) {
        return 'Format jam tidak valid';
      }

      final jamMasukTime = DateTime(now.year, now.month, now.day,
          int.parse(jamMasukParts[0]), int.parse(jamMasukParts[1]));
      final jamPulangTime = DateTime(now.year, now.month, now.day,
          int.parse(jamPulangParts[0]), int.parse(jamPulangParts[1]));

      // Window absen masuk: menggunakan minimal_open_time dari backend
      final windowMasukStart =
          jamMasukTime.subtract(Duration(minutes: _minimalOpenTime!));
      final windowMasukEnd = jamMasukTime.add(Duration(minutes: _toleransi!));

      if (hasCheckedOut) {
        return 'Absensi hari ini sudah selesai';
      } else if (isCheckedIn) {
        if (now.isBefore(jamPulangTime)) {
          final remainingTime = jamPulangTime.difference(now);
          final hours = remainingTime.inHours;
          final minutes = remainingTime.inMinutes % 60;
          return 'Waktu pulang dalam ${hours}j ${minutes}m';
        } else {
          return 'Tap untuk absen pulang';
        }
      } else {
        if (now.isBefore(windowMasukStart)) {
          final remainingTime = windowMasukStart.difference(now);
          final hours = remainingTime.inHours;
          final minutes = remainingTime.inMinutes % 60;
          return 'Absen masuk dalam ${hours}j ${minutes}m';
        } else if (now.isAfter(windowMasukEnd)) {
          return 'Waktu absen masuk sudah terlewat';
        } else {
          return 'Tap untuk absen masuk';
        }
      }
    } catch (e) {
      debugPrint('Error parsing time in message: $e');
      return 'Error memuat jadwal absensi';
    }
  }

  Future<void> _handleFingerprintTap() async {
    if (_isProcessing) return;

    if (widget.isAttendanceLocked) {
      NotificationPopup.showInfo(
        context,
        title: 'Absensi Dikunci',
        message: _getAttendanceButtonMessage(),
      );
      return;
    }

    if (_isAttendanceBlockedByScope()) {
      NotificationPopup.showInfo(
        context,
        title: 'Akses Absensi Dibatasi',
        message:
            'Mode absensi saat ini hanya untuk siswa. Akun ini tidak dapat melakukan absensi selfie.',
      );
      return;
    }

    final isHolidayToday =
        widget.isHolidayToday || (_holidayCheckLoaded && _isHoliday);
    if (isHolidayToday) {
      final holidayMessage = (widget.holidayMessage ?? '').trim().isNotEmpty
          ? widget.holidayMessage!.trim()
          : 'Selamat menikmati hari libur Anda. Absensi tidak dibuka hari ini.';
      NotificationPopup.showInfo(
        context,
        title: 'Hari Libur',
        message: holidayMessage,
      );
      return;
    }

    if (_isAttendanceBlockedByMissingFaceTemplate()) {
      await _showFaceTemplateRequiredDialog();
      return;
    }

    setState(() {
      _isProcessing = true;
    });
    DialogGuardService.instance.beginAttendanceSubmission();

    bool isLoadingPopupVisible = false;
    Route<dynamic>? loadingPopupRoute;

    void closeLoadingPopupIfNeeded() {
      if (!isLoadingPopupVisible) {
        return;
      }

      final route = loadingPopupRoute;
      final navigator = route?.navigator;
      if (route != null && navigator != null && route.isActive) {
        navigator.removeRoute(route);
      }
      isLoadingPopupVisible = false;
      loadingPopupRoute = null;
    }

    try {
      final attendanceState =
          Provider.of<UseAttendanceState>(context, listen: false);
      final isCurrentlyCheckedIn = attendanceState.state.isCheckedIn;
      final actionType = isCurrentlyCheckedIn ? 'checkout' : 'checkin';
      debugPrint(
          'Attendance action: $actionType (currently checked in: $isCurrentlyCheckedIn)');

      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final userId = authProvider.user?.id;
      if (userId == null) {
        if (!mounted) return;
        NotificationPopup.showError(
          context,
          title: 'Error',
          message: 'User tidak ditemukan. Silakan coba login kembali.',
        );
        return;
      }

      final precheck =
          Provider.of<AttendancePrecheckProvider>(context, listen: false);
      final precheckSnapshot = await precheck.ensureReadyForAction(
        userId: userId,
        isCheckedIn: isCurrentlyCheckedIn,
      );
      if (!mounted) return;

      if (precheckSnapshot == null) {
        await _showPrecheckFailure(precheck);
        return;
      }

      final shouldProceed = await _showSecuritySubmitWarning(precheck);
      if (!mounted || !shouldProceed) {
        return;
      }

      double? latitude;
      double? longitude;
      double? accuracy;
      bool? isMocked;
      int? lokasiId;
      String? fotoPath;
      final requirePhoto = precheck.wajibFoto;
      final securityWarningPayload = precheck.buildSecurityWarningPayload(
        trigger: 'attendance_submit_confirmation',
        acknowledged: true,
        includeConfirmedAt: true,
      );

      latitude = precheckSnapshot.latitude;
      longitude = precheckSnapshot.longitude;
      accuracy = precheckSnapshot.accuracy;
      isMocked = precheckSnapshot.isMocked;
      lokasiId = precheckSnapshot.locationId;

      if (requirePhoto) {
        final cameraPermission =
            await PermissionService.requestCameraPermission();
        if (!cameraPermission.isGranted) {
          if (!mounted) return;
          NotificationPopup.showError(
            context,
            title: 'Izin Kamera Diperlukan',
            message:
                'Izin kamera diperlukan untuk mengambil foto. ${cameraPermission.message}',
          );
          return;
        }

        if (!mounted) return;
        final picker = ImagePicker();
        final XFile? image = await picker.pickImage(
          source: ImageSource.camera,
          imageQuality: 60,
          maxWidth: 800,
          maxHeight: 800,
        );

        if (image == null) {
          if (!mounted) return;
          NotificationPopup.showInfo(
            context,
            title: 'Foto Diperlukan',
            message: 'Ambil foto selfie untuk melanjutkan absensi.',
          );
          return;
        }

        fotoPath = image.path;
      }

      if (!mounted) return;
      final loadingShownCompleter = Completer<void>();
      final loadingFuture = AttendancePopup.showLoadingPopup(
        context,
        actionType,
        onRouteReady: (route) {
          loadingPopupRoute ??= route;
          isLoadingPopupVisible = true;
          if (!loadingShownCompleter.isCompleted) {
            loadingShownCompleter.complete();
          }
        },
        onShown: () {
          if (!loadingShownCompleter.isCompleted) {
            loadingShownCompleter.complete();
          }
        },
      );
      await loadingShownCompleter.future.timeout(
        const Duration(milliseconds: 600),
        onTimeout: () {},
      );
      if (!mounted) {
        closeLoadingPopupIfNeeded();
        return;
      }
      unawaited(loadingFuture.whenComplete(() {
        isLoadingPopupVisible = false;
        loadingPopupRoute = null;
      }));

      final user = authProvider.user;
      final checkInNote = requirePhoto
          ? 'Check-in via mobile app dengan selfie'
          : 'Check-in via mobile app';
      final checkOutNote = requirePhoto
          ? 'Check-out via mobile app dengan selfie'
          : 'Check-out via mobile app';

      final submitFuture = actionType == 'checkin'
          ? _attendanceService.checkIn(
              latitude: latitude,
              longitude: longitude,
              accuracy: accuracy,
              isMocked: isMocked,
              securityWarningPayload: securityWarningPayload,
              keterangan: checkInNote,
              fotoPath: fotoPath,
              kelasNama: user?.kelasNama,
              idKelas: user?.idKelas,
              lokasiId: lokasiId,
            )
          : _attendanceService.checkOut(
              latitude: latitude,
              longitude: longitude,
              accuracy: accuracy,
              isMocked: isMocked,
              securityWarningPayload: securityWarningPayload,
              keterangan: checkOutNote,
              fotoPath: fotoPath,
              lokasiId: lokasiId,
            );
      final response = await submitFuture.timeout(
        const Duration(seconds: 45),
      );

      closeLoadingPopupIfNeeded();
      if (!mounted) return;

      if (response.success) {
        final now = DateTime.now();
        final actionTime = response.data?.formattedTime ??
            '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';

        if (actionType == 'checkin') {
          attendanceState.doCheckin(actionTime);
        } else {
          attendanceState.doCheckout(actionTime);
        }

        debugPrint('Timezone Debug - Success Response:');
        debugPrint('   - Action Type: $actionType');
        debugPrint('   - Backend Time: $actionTime');
        debugPrint(
            '   - Verification Mode: ${response.data?.verificationMode ?? _verificationMode}');

        final dashboardService = DashboardService();
        unawaited(() async {
          final statusResponse = await dashboardService.getTodayAttendanceStatus();
          if (!mounted || !statusResponse.success || statusResponse.data == null) {
            return;
          }

          final attendanceData = statusResponse.data!;
          attendanceState.updateFromBackendData(
            checkinTime: attendanceData.checkinTime,
            checkoutTime: attendanceData.checkoutTime,
            isCheckedIn:
                attendanceData.hasCheckedIn && !attendanceData.hasCheckedOut,
          );
        }());

        AttendancePopup.showSuccessPopup(
          context,
          actionType,
          actionTime,
          verification: {
            'mode': response.data?.verificationMode ?? _verificationMode,
            'status': response.data?.verificationStatus,
            'score': response.data?.verificationScore,
          },
          securityNotice: response.data?.securityNotice,
          onClose: () {
            widget.onTap();
          },
        );
      } else {
        AttendancePopup.showErrorPopup(
          context,
          actionType,
          response.message,
        );
      }
    } on TimeoutException {
      closeLoadingPopupIfNeeded();
      if (!mounted) return;
      AttendancePopup.showErrorPopup(
        context,
        'error',
        'Request timeout. Pastikan koneksi stabil lalu coba lagi.',
      );
    } catch (e) {
      closeLoadingPopupIfNeeded();
      if (!mounted) return;
      AttendancePopup.showErrorPopup(
        context,
        'error',
        'Error: $e',
      );
    } finally {
      closeLoadingPopupIfNeeded();
      DialogGuardService.instance.endAttendanceSubmission();
      if (mounted) {
        setState(() {
          _isProcessing = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final primaryColor = Color(AppColors.primaryColorValue);
    final precheck = context.watch<AttendancePrecheckProvider>();
    final shouldShowButton = _shouldShowAttendanceButton();
    final blockedByStatus = widget.isAttendanceLocked;
    final blockedByScope = _isAttendanceBlockedByScope();
    final blockedByMissingFaceTemplate =
        _isAttendanceBlockedByMissingFaceTemplate();
    final isHoliday =
        widget.isHolidayToday || (_holidayCheckLoaded && _isHoliday);
    final showHolidayOnlyCard =
        isHoliday && !blockedByStatus && !blockedByScope;
    final resolvedHolidayMessage =
        (widget.holidayMessage ?? '').trim().isNotEmpty
            ? widget.holidayMessage!.trim()
            : 'Selamat menikmati hari libur Anda. Absensi tidak dibuka hari ini.';
    final shouldUsePrecheckStatus = !blockedByStatus &&
        !blockedByScope &&
        !isHoliday &&
        !blockedByMissingFaceTemplate;
    final isPrecheckLoading = shouldUsePrecheckStatus && precheck.isActionLoading;
    final isPrecheckReady = shouldUsePrecheckStatus && precheck.isActionReady;
    final hasSecurityWarnings =
        shouldUsePrecheckStatus && precheck.hasSecurityWarnings;
    final hasPrecheckBlocker =
        shouldUsePrecheckStatus && precheck.hasActionBlocker;
    final shouldUseResolvedPrecheckCopy = shouldUsePrecheckStatus &&
        (precheck.isActionLoading ||
            precheck.isActionReady ||
            (precheck.hasActionBlocker && precheck.failureType != 'time'));
    final usesResolvedPrecheckBlocker =
        shouldUseResolvedPrecheckCopy && hasPrecheckBlocker;

    final statusMessage = blockedByStatus
        ? _getAttendanceButtonMessage()
        : (blockedByScope
            ? 'Absensi hanya tersedia untuk akun siswa.'
            : (isHoliday
                ? resolvedHolidayMessage
                : (blockedByMissingFaceTemplate
                    ? 'Template wajah wajib tersedia sebelum absensi.'
                    : (shouldUseResolvedPrecheckCopy
                        ? precheck.actionPrimaryMessage
                        : _getAttendanceButtonMessage()))));

    final Color statusBg;
    final Color statusFg;
    if (blockedByStatus) {
      statusBg = const Color(0xFFEAF3FF);
      statusFg = const Color(0xFF2A65A8);
    } else if (blockedByScope) {
      statusBg = const Color(0xFFFFF4DB);
      statusFg = const Color(0xFF8A640E);
    } else if (isHoliday) {
      statusBg = const Color(0xFFEAF3FF);
      statusFg = const Color(0xFF2A65A8);
    } else if (blockedByMissingFaceTemplate) {
      statusBg = const Color(0xFFFFF4E5);
      statusFg = const Color(0xFFB26A00);
    } else if (isPrecheckLoading) {
      statusBg = const Color(0xFFEAF3FF);
      statusFg = const Color(0xFF2A65A8);
    } else if (hasSecurityWarnings) {
      statusBg = const Color(0xFFFFF4E5);
      statusFg = const Color(0xFFB26A00);
    } else if (isPrecheckReady || shouldShowButton) {
      statusBg = const Color(0xFFE9F8EC);
      statusFg = const Color(0xFF1E7A38);
    } else if (usesResolvedPrecheckBlocker) {
      statusBg = const Color(0xFFFFF4E5);
      statusFg = const Color(0xFFB26A00);
    } else {
      statusBg = const Color(0xFFFCE8EA);
      statusFg = const Color(0xFFB4232C);
    }

    final secondaryMessage = blockedByStatus
        ? 'Status kehadiran hari ini ditetapkan melalui approval/admin'
        : (blockedByScope
            ? 'Cakupan aktif: $_attendanceScope'
            : (isHoliday
                ? 'Hari ini ditandai sebagai libur atau non-hari kerja oleh sistem.'
                : (blockedByMissingFaceTemplate
                    ? 'Silakan buka menu Template Wajah lalu rekam template siswa dari kamera sebelum absensi.'
                    : (shouldUseResolvedPrecheckCopy
                        ? precheck.actionSecondaryMessage
                        : 'Cakupan: ${_attendanceScope == 'siswa_only' ? 'siswa saja' : _attendanceScope} | Verifikasi: ${_verificationMode == 'async_pending' ? 'asinkron' : 'sinkron langsung'} | Face ${_faceVerificationEnabled ? 'aktif' : 'nonaktif'}'))));

    final canTapButton = shouldShowButton &&
        !blockedByStatus &&
        !blockedByScope &&
        !blockedByMissingFaceTemplate &&
        !isHoliday &&
        precheck.isActionReady &&
        !_isProcessing;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Aksi Absensi',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w800,
            color: Colors.grey[900],
          ),
        ),
        if (!showHolidayOnlyCard) ...[
          const SizedBox(height: 4),
          Text(
            _buildWindowText(),
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Color(0xFF6A7C93),
            ),
          ),
        ],
        const SizedBox(height: 14),
        if (showHolidayOnlyCard)
          _buildHolidayBannerCard(
            message: resolvedHolidayMessage,
            subtitle: secondaryMessage,
          )
        else
          Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              GestureDetector(
                onTap: canTapButton
                    ? _handleFingerprintTap
                    : (blockedByMissingFaceTemplate
                        ? _showFaceTemplateRequiredDialog
                        : null),
                child: _buildAttendanceActionVisual(
                  canTapButton: canTapButton,
                  isPrecheckLoading: isPrecheckLoading,
                  blockedByStatus: blockedByStatus,
                  blockedByScope: blockedByScope,
                  isHoliday: isHoliday || blockedByMissingFaceTemplate,
                  primaryColor: primaryColor,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  children: [
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 10,
                      ),
                      decoration: BoxDecoration(
                        color: statusBg,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        statusMessage,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: statusFg,
                        ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 9,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEAF3FF),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(
                        secondaryMessage,
                        style: const TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF2A65A8),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        if (!showHolidayOnlyCard) ...[
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _buildTimeBox(
                  label: 'Check-in',
                  value: _formatDisplayTime(widget.state.checkinTime),
                  color: _resolveCheckinColor(),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _buildTimeBox(
                  label: 'Check-out',
                  value: _formatDisplayTime(widget.state.checkoutTime),
                  color: const Color(0xFFDC2626),
                ),
              ),
            ],
          ),
        ],
      ],
    );
  }

  Widget _buildHolidayBannerCard({
    required String message,
    required String subtitle,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFEAF4FF), Color(0xFFF8FBFF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFCFE3FF)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: const Color(0xFF2A65A8),
              borderRadius: BorderRadius.circular(16),
            ),
            child: const Icon(
              Icons.beach_access_rounded,
              color: Colors.white,
              size: 28,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 6,
                  ),
                  decoration: BoxDecoration(
                    color: const Color(0xFFDCEBFF),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Text(
                    'Hari Libur',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF2A65A8),
                    ),
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'Selamat menikmati hari libur Anda',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF123B67),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  message,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF35587A),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF607893),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
  Widget _buildAttendanceActionVisual({
    required bool canTapButton,
    required bool isPrecheckLoading,
    required bool blockedByStatus,
    required bool blockedByScope,
    required bool isHoliday,
    required Color primaryColor,
  }) {
    if (isPrecheckLoading) {
      return _buildLoadingActionButton();
    }

    final blockedIcon = (blockedByStatus || blockedByScope)
        ? Icons.lock_clock_outlined
        : (isHoliday
            ? Icons.beach_access
            : Icons.hourglass_disabled_rounded);

    if (!canTapButton) {
      return _buildFaceIdMinimalActionButton(
        stateIcon: blockedIcon,
        blockedByStatus: blockedByStatus,
        blockedByScope: blockedByScope,
        isHoliday: isHoliday,
      );
    }

    return _buildRadarScanActionButton(primaryColor: primaryColor);
  }

  Widget _buildLoadingActionButton() {
    return Container(
      width: 96,
      height: 96,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: const Color(0xFFEAF3FF),
        border: Border.all(
          color: const Color(0xFFC6D8F0),
          width: 1.4,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x20364A61),
            blurRadius: 10,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            SizedBox(
              width: 28,
              height: 28,
              child: CircularProgressIndicator(
                strokeWidth: 3,
                valueColor: AlwaysStoppedAnimation<Color>(Color(0xFF2A65A8)),
              ),
            ),
            SizedBox(height: 8),
            Icon(
              Icons.radar_rounded,
              color: Color(0xFF2A65A8),
              size: 18,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildRadarScanActionButton({required Color primaryColor}) {
    return TweenAnimationBuilder<double>(
      duration: const Duration(milliseconds: 680),
      curve: Curves.easeOutBack,
      tween: Tween<double>(begin: 0.9, end: 1),
      builder: (context, scale, child) {
        return Transform.scale(scale: scale, child: child);
      },
      child: Container(
        width: 96,
        height: 96,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [
              Color(0xFF86D4FF),
              Color(0xFF3B98E8),
            ],
          ),
          boxShadow: [
            BoxShadow(
              color: primaryColor.withValues(alpha: 0.36),
              blurRadius: 16,
              offset: const Offset(0, 9),
            ),
          ],
        ),
        child: Stack(
          alignment: Alignment.center,
          children: [
            Container(
              width: 86,
              height: 86,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.24),
                  width: 1.2,
                ),
              ),
            ),
            Container(
              width: 70,
              height: 70,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.46),
                  width: 2.0,
                ),
                color: const Color(0x2A2A5F93),
              ),
              child: const Stack(
                alignment: Alignment.center,
                children: [
                  Icon(
                    Icons.radar_rounded,
                    color: Colors.white54,
                    size: 40,
                  ),
                  Icon(
                    Icons.face_retouching_natural_rounded,
                    color: Colors.white,
                    size: 27,
                  ),
                ],
              ),
            ),
            Positioned(
              top: 15,
              right: 18,
              child: Container(
                width: 9,
                height: 9,
                decoration: const BoxDecoration(
                  color: Colors.white,
                  shape: BoxShape.circle,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFaceIdMinimalActionButton({
    required IconData stateIcon,
    required bool blockedByStatus,
    required bool blockedByScope,
    required bool isHoliday,
  }) {
    final stateColor = blockedByStatus
        ? const Color(0xFF2A65A8)
        : (blockedByScope
            ? const Color(0xFF8A640E)
            : (isHoliday
                ? const Color(0xFF2A65A8)
                : const Color(0xFF8A97A8)));

    return Container(
      width: 96,
      height: 96,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: const Color(0xFFE6EBF2),
        border: Border.all(
          color: const Color(0xFFC2CEDD),
          width: 1.4,
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x30364A61),
            blurRadius: 10,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          Container(
            width: 68,
            height: 68,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border: Border.all(
                color: const Color(0xFFADBACC),
                width: 1.8,
              ),
              color: const Color(0xFFF0F4FA),
            ),
            child: const Stack(
              alignment: Alignment.center,
              children: [
                Icon(
                  Icons.center_focus_strong_rounded,
                  color: Color(0xFF8C9AAD),
                  size: 34,
                ),
                Icon(
                  Icons.face_retouching_natural_rounded,
                  color: Color(0xFF6F7E90),
                  size: 24,
                ),
              ],
            ),
          ),
          Positioned(
            right: 10,
            top: 10,
            child: Container(
              width: 24,
              height: 24,
              decoration: BoxDecoration(
                color: stateColor,
                shape: BoxShape.circle,
              ),
              child: Icon(
                stateIcon,
                color: Colors.white,
                size: 14,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTimeBox({
    required String label,
    required String value,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: const Color(0xFFF3F8FF),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: Color(0xFF6A7C93),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: value == '--:--' ? const Color(0xFF607893) : color,
            ),
          ),
        ],
      ),
    );
  }

  String _formatDisplayTime(String? raw) {
    if (raw == null || raw.trim().isEmpty) {
      return '--:--';
    }

    final value = raw.trim();
    if (value.contains(':') && value.length >= 5) {
      return value.substring(0, 5);
    }
    return value;
  }

  Color _resolveCheckinColor() {
    final checkinRaw = widget.state.checkinTime;
    if (checkinRaw == null || checkinRaw.trim().isEmpty) {
      return const Color(0xFF16A34A);
    }
    if (_jamMasuk == null || _jamMasuk == '-' || _jamMasuk!.trim().isEmpty) {
      return const Color(0xFF16A34A);
    }

    final checkinTime = _parseTimeForToday(checkinRaw);
    final jamMasukTime = _parseTimeForToday(_jamMasuk!);
    if (checkinTime == null || jamMasukTime == null) {
      return const Color(0xFF16A34A);
    }

    return checkinTime.isAfter(jamMasukTime)
        ? const Color(0xFFDC2626)
        : const Color(0xFF16A34A);
  }

  DateTime? _parseTimeForToday(String raw) {
    final value = raw.trim();
    if (value.isEmpty || value == '--:--' || value == '-') {
      return null;
    }

    final parts = value.split(':');
    if (parts.length < 2) {
      return null;
    }

    final hour = int.tryParse(parts[0]);
    final minute = int.tryParse(parts[1]);
    if (hour == null || minute == null) {
      return null;
    }

    final now = DateTime.now();
    return DateTime(now.year, now.month, now.day, hour, minute);
  }

  String _buildWindowText() {
    if (_jamMasuk == null ||
        _jamPulang == null ||
        _minimalOpenTime == null ||
        _toleransi == null ||
        _jamMasuk == '-' ||
        _jamPulang == '-') {
      return 'Window absensi mengikuti skema aktif';
    }

    try {
      final now = DateTime.now();
      final jamMasukParts = _jamMasuk!.split(':');
      final jamPulangParts = _jamPulang!.split(':');
      final jamMasukTime = DateTime(
        now.year,
        now.month,
        now.day,
        int.parse(jamMasukParts[0]),
        int.parse(jamMasukParts[1]),
      );
      final jamPulangTime = DateTime(
        now.year,
        now.month,
        now.day,
        int.parse(jamPulangParts[0]),
        int.parse(jamPulangParts[1]),
      );
      final windowMasukStart =
          jamMasukTime.subtract(Duration(minutes: _minimalOpenTime!));
      final windowMasukEnd = jamMasukTime.add(Duration(minutes: _toleransi!));
      return 'Window masuk ${_hhmm(windowMasukStart)} - ${_hhmm(windowMasukEnd)} | Pulang mulai ${_hhmm(jamPulangTime)}';
    } catch (_) {
      return 'Window absensi mengikuti skema aktif';
    }
  }

  String _hhmm(DateTime time) {
    final hh = time.hour.toString().padLeft(2, '0');
    final mm = time.minute.toString().padLeft(2, '0');
    return '$hh:$mm';
  }
}

