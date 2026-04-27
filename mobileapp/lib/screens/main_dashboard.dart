import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../widgets/app_header.dart';
import '../providers/auth_provider.dart';
import '../services/live_tracking_background_service.dart';
import '../services/live_tracking_service.dart';
import '../services/manual_data_sync_service.dart';
import '../services/notification_service.dart';
import '../services/permission_service_final.dart';
import '../services/attendance_reminder_service.dart';
import '../services/dashboard_service.dart';
import '../services/location_service.dart';
import '../services/dialog_guard_service.dart';
import '../widgets/broadcast_announcement_dialog.dart';
import '../widgets/notification_popup.dart';
import 'attendance_screen_clean.dart';
import 'applications_screen.dart';
import 'face_template_screen.dart';
import 'notification_center_screen.dart';
import 'settings_screen.dart';
import 'profile_screen.dart';
import 'quick_submission_screen.dart';

class MainDashboard extends StatefulWidget {
  const MainDashboard({super.key});

  @override
  State<MainDashboard> createState() => _MainDashboardState();
}

class _MainDashboardState extends State<MainDashboard>
    with WidgetsBindingObserver {
  final LiveTrackingService _liveTrackingService = LiveTrackingService();
  final LiveTrackingBackgroundService _liveTrackingBackgroundService =
      LiveTrackingBackgroundService();
  final NotificationService _notificationService = NotificationService();
  final AttendanceReminderService _attendanceReminderService =
      AttendanceReminderService();
  final DashboardService _dashboardService = DashboardService();
  final ManualDataSyncService _manualDataSyncService = ManualDataSyncService();
  final LocationService _locationService = LocationService();
  int? _trackingUserId;
  bool _isUnreadFetchRunning = false;
  bool _isAnnouncementFetchRunning = false;
  bool _isAnnouncementDialogVisible = false;
  bool _isFaceTemplateDialogVisible = false;
  bool _isBackgroundPermissionDialogVisible = false;
  int? _lastFaceTemplatePromptUserId;
  int? _lastBackgroundPermissionPromptUserId;
  final Set<int> _shownAnnouncementIds = <int>{};

  int _currentIndex = 0;
  int _notificationCount = 0;
  int _attendanceRefreshTick = 0;
  String _academicContextLabel = '-';
  int _lastManualSyncVersion = 0;
  int? _lastReminderSyncedUserId;
  AuthProvider? _authProvider;
  Timer? _backgroundPermissionRetryTimer;
  int _trackingSyncRevision = 0;
  bool _mockWarningShown = false;

  bool get _isDialogGuardedByAttendanceSubmission =>
      DialogGuardService.instance.isAttendanceSubmissionInProgress;

  bool get _backgroundTrackingEnabled =>
      LiveTrackingBackgroundService.isAvailable;

  List<Widget> get _screens => [
        AttendanceScreenClean(
          refreshTick: _attendanceRefreshTick,
        ),
        const ApplicationsScreen(),
        const SettingsScreen(),
        const ProfileScreen(),
      ];

  final List<String> _titles = [
    'Beranda SIAPS',
    'Aplikasi',
    'Pengaturan',
    'Profil',
  ];

  String? _buildHeaderSubtitle() {
    if (_currentIndex != 0) {
      return null;
    }

    return _academicContextLabel;
  }

  Future<void> _loadAcademicContext() async {
    final response = await _dashboardService.getAcademicContext();
    if (!mounted) {
      return;
    }

    final label = response.success && response.data != null
        ? response.data!.compactLabel
        : '-';

    if (_academicContextLabel == label) {
      return;
    }

    setState(() {
      _academicContextLabel = label;
    });
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _notificationService.unreadCountNotifier
        .addListener(_handleUnreadCountChanged);
    _lastManualSyncVersion = _manualDataSyncService.syncVersion;
    _manualDataSyncService.addListener(_handleManualSyncChanged);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      _authProvider = Provider.of<AuthProvider>(context, listen: false);
      _authProvider?.addListener(_handleAuthStateChanged);
      unawaited(_syncLiveTrackingState());
      unawaited(_loadUnreadCount());
      unawaited(_checkUnreadPopupNotifications());
      unawaited(_loadAcademicContext());
      unawaited(_promptMissingFaceTemplateIfNeeded());
      unawaited(_checkMockLocationOnLaunch());
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    unawaited(_syncLiveTrackingState());
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(_syncLiveTrackingState(forceRestart: true));
      unawaited(_loadUnreadCount());
      unawaited(_checkUnreadPopupNotifications());
      unawaited(_promptMissingFaceTemplateIfNeeded());
      unawaited(_checkMockLocationOnLaunch());
      return;
    }

    if (state == AppLifecycleState.paused ||
        state == AppLifecycleState.inactive ||
        state == AppLifecycleState.detached) {
      if (_backgroundTrackingEnabled) {
        return;
      }

      _liveTrackingService.stopTracking();
    }
  }

  Future<void> _syncLiveTrackingState({
    bool forceRestart = false,
  }) async {
    if (!mounted) {
      return;
    }

    final syncRevision = ++_trackingSyncRevision;
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final user = authProvider.user;
    final isAuthenticated = authProvider.isAuthenticated;

    if (isAuthenticated &&
        user != null &&
        user.isSiswa &&
        _lastReminderSyncedUserId != user.id) {
      _lastReminderSyncedUserId = user.id;
      unawaited(_attendanceReminderService.applySchedulesForUser(user));
    } else if ((!isAuthenticated || user == null || !user.isSiswa) &&
        _lastReminderSyncedUserId != null) {
      _lastReminderSyncedUserId = null;
      unawaited(_attendanceReminderService.cancelAllAttendanceReminders());
    }

    final canTrack = isAuthenticated && (user?.isSiswa ?? false);

    if (!canTrack || user == null) {
      _lastBackgroundPermissionPromptUserId = null;
      if (_backgroundTrackingEnabled) {
        await _liveTrackingBackgroundService.stopTracking();
        if (!mounted || syncRevision != _trackingSyncRevision) {
          return;
        }
      } else if (_liveTrackingService.isTracking) {
        _liveTrackingService.stopTracking();
      }
      _trackingUserId = null;
      return;
    }

    if (_backgroundTrackingEnabled) {
      final shouldStart = forceRestart || _trackingUserId != user.id;
      if (shouldStart) {
        _trackingUserId = user.id;
        await _syncBackgroundTrackingState(
          userId: user.id,
          forceRestart: forceRestart,
          syncRevision: syncRevision,
        );
      }
      return;
    }

    final shouldStart = forceRestart ||
        !_liveTrackingService.isTracking ||
        _trackingUserId != user.id;

    if (shouldStart) {
      _trackingUserId = user.id;
      unawaited(_liveTrackingService.startTracking(userId: user.id));
    }
  }

  Future<void> _checkMockLocationOnLaunch() async {
    if (!mounted || _mockWarningShown) {
      return;
    }

    final permission = await _locationService.checkLocationPermission();
    if (!permission.isGranted) {
      return;
    }

    final location = await _locationService.getCurrentLocation(
      includeAddress: false,
    );
    if (!mounted || !location.success) {
      return;
    }

    if (location.isMocked == true) {
      _mockWarningShown = true;
      NotificationPopup.showError(
        context,
        title: 'Lokasi Palsu Terdeteksi',
        message:
            'Mock location/Fake GPS masih aktif. Nonaktifkan sebelum melakukan absensi.',
      );
    }
  }

  Future<void> _syncBackgroundTrackingState({
    required int userId,
    required bool forceRestart,
    required int syncRevision,
  }) async {
    final isReady =
        await _ensureBackgroundLocationPermissionReady(userId: userId);
    if (!_isTrackingSyncStillValid(syncRevision, expectedUserId: userId)) {
      return;
    }

    if (!isReady) {
      await _liveTrackingBackgroundService.stopTracking();
      return;
    }

    await _liveTrackingBackgroundService.syncTrackingState(
      shouldTrack: true,
      userId: userId,
      forceRestart: forceRestart,
    );
  }

  Future<bool> _ensureBackgroundLocationPermissionReady({
    required int userId,
  }) async {
    if (!_backgroundTrackingEnabled) {
      return true;
    }

    final permissionResult =
        await PermissionService.checkBackgroundLocationPermission();
    if (permissionResult.isGranted) {
      _backgroundPermissionRetryTimer?.cancel();
      _lastBackgroundPermissionPromptUserId = userId;
      return true;
    }

    if (!mounted) {
      return false;
    }

    final dialogsBlocked = _isAnnouncementDialogVisible ||
        _isFaceTemplateDialogVisible ||
        _isBackgroundPermissionDialogVisible ||
        _isDialogGuardedByAttendanceSubmission;
    final canShowDialogs = await _canShowSecondaryDialogs();

    if (dialogsBlocked || !canShowDialogs) {
      _scheduleBackgroundPermissionRetry(userId);
      return false;
    }

    if (_lastBackgroundPermissionPromptUserId == userId) {
      return false;
    }

    _lastBackgroundPermissionPromptUserId = userId;

    if (permissionResult.isPermanentlyDenied) {
      await _showBackgroundLocationSettingsDialog();
      return false;
    }

    return _showBackgroundLocationRequestDialog();
  }

  Future<bool> _showBackgroundLocationRequestDialog() async {
    if (!mounted) {
      return false;
    }

    _isBackgroundPermissionDialogVisible = true;
    final shouldRequest = await showDialog<bool>(
          context: context,
          barrierDismissible: true,
          builder: (dialogContext) {
            return AlertDialog(
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
              title: const Text('Aktifkan Lokasi Latar Belakang'),
              content: const Text(
                'Untuk live tracking siswa saat aplikasi di-background-kan, '
                'Android memerlukan izin lokasi latar belakang. '
                'Izin ini tidak mengubah flow absensi masuk/pulang.',
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(dialogContext).pop(false),
                  child: const Text('Nanti'),
                ),
                FilledButton(
                  onPressed: () => Navigator.of(dialogContext).pop(true),
                  child: const Text('Lanjutkan'),
                ),
              ],
            );
          },
        ) ??
        false;
    _isBackgroundPermissionDialogVisible = false;

    if (!shouldRequest) {
      return false;
    }

    final requestResult =
        await PermissionService.requestBackgroundLocationPermission();
    if (requestResult.isGranted) {
      if (!mounted) {
        return true;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Izin lokasi latar belakang berhasil diaktifkan.'),
        ),
      );
      return true;
    }

    if (requestResult.isPermanentlyDenied) {
      await _showBackgroundLocationSettingsDialog();
      return false;
    }

    // Android 11+ bisa tetap menolak tanpa menampilkan opsi "Allow all the time"
    // pada dialog runtime. Reset guard agar user masih bisa diprompt ulang
    // ketika kembali ke dashboard / resume berikutnya.
    _lastBackgroundPermissionPromptUserId = null;
    return false;
  }

  void _scheduleBackgroundPermissionRetry(int userId) {
    _backgroundPermissionRetryTimer?.cancel();
    _backgroundPermissionRetryTimer = Timer(
      const Duration(seconds: 2),
      () {
        if (!mounted) {
          return;
        }

        final authProvider = Provider.of<AuthProvider>(context, listen: false);
        final user = authProvider.user;
        if (!authProvider.isAuthenticated ||
            user == null ||
            !user.isSiswa ||
            user.id != userId) {
          return;
        }

        unawaited(
          _syncBackgroundTrackingState(
            userId: userId,
            forceRestart: false,
            syncRevision: _trackingSyncRevision,
          ),
        );
      },
    );
  }

  Future<void> _showBackgroundLocationSettingsDialog() async {
    if (!mounted) {
      return;
    }

    _isBackgroundPermissionDialogVisible = true;
    await showDialog<void>(
      context: context,
      barrierDismissible: true,
      builder: (dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          title: const Text('Izin Latar Belakang Diperlukan'),
          content: const Text(
            'Izin lokasi latar belakang sudah ditolak permanen. '
            'Jika ingin mengaktifkan live tracking background, buka pengaturan aplikasi lalu izinkan "Allow all the time". '
            'Pada beberapa vendor seperti Xiaomi, Oppo, Vivo, dan Realme, Anda juga perlu mengatur battery ke "No restrictions", mengaktifkan autostart, dan mengunci aplikasi di recent apps.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Tutup'),
            ),
            FilledButton(
              onPressed: () async {
                Navigator.of(dialogContext).pop();
                await PermissionService.openAppSettings();
              },
              child: const Text('Buka Pengaturan'),
            ),
          ],
        );
      },
    );
    _isBackgroundPermissionDialogVisible = false;
  }

  Future<void> _loadUnreadCount() async {
    if (_isUnreadFetchRunning) {
      return;
    }

    _isUnreadFetchRunning = true;
    final response = await _notificationService.getUnreadCount();
    try {
      if (!mounted || !response.success) {
        return;
      }

      setState(() {
        _notificationCount = response.data ?? 0;
      });

      if ((response.data ?? 0) > 0) {
        unawaited(_checkUnreadPopupNotifications());
      }
    } finally {
      _isUnreadFetchRunning = false;
    }
  }

  Future<void> _checkUnreadPopupNotifications() async {
    if (!mounted ||
        _isAnnouncementFetchRunning ||
        _isAnnouncementDialogVisible ||
        _isFaceTemplateDialogVisible ||
        _isDialogGuardedByAttendanceSubmission) {
      return;
    }

    _isAnnouncementFetchRunning = true;
    final response = await _notificationService.getUnreadPopupNotifications();

    try {
      if (!mounted || !response.success) {
        return;
      }

      final candidate = (response.data ?? const <AppNotificationItem>[])
          .where((item) => !_shownAnnouncementIds.contains(item.id))
          .cast<AppNotificationItem?>()
          .firstWhere((item) => item != null, orElse: () => null);

      if (candidate == null) {
        return;
      }

      if (_isDialogGuardedByAttendanceSubmission) {
        return;
      }

      _shownAnnouncementIds.add(candidate.id);
      _isAnnouncementDialogVisible = true;

      await showDialog<void>(
        context: context,
        barrierDismissible: !candidate.popupSticky,
        builder: (dialogContext) {
          return WillPopScope(
            onWillPop: () async => !candidate.popupSticky,
            child: BroadcastAnnouncementDialog(
              item: candidate,
              onDismiss: () => Navigator.of(dialogContext).pop(),
              onCtaTap: () => Navigator.of(dialogContext).pop(),
            ),
          );
        },
      );

      await _notificationService.markAsRead(candidate.id);
      await _loadUnreadCount();
    } finally {
      _isAnnouncementDialogVisible = false;
      _isAnnouncementFetchRunning = false;
    }
  }

  void _handleUnreadCountChanged() {
    if (!mounted) {
      return;
    }

    setState(() {
      _notificationCount = _notificationService.unreadCountNotifier.value;
    });

    if (_notificationService.unreadCountNotifier.value > 0) {
      unawaited(_checkUnreadPopupNotifications());
    }
  }

  void _handleAuthStateChanged() {
    unawaited(_syncLiveTrackingState());
    unawaited(_promptMissingFaceTemplateIfNeeded());
  }

  void _handleManualSyncChanged() {
    if (!mounted) {
      return;
    }

    if (_lastManualSyncVersion == _manualDataSyncService.syncVersion) {
      return;
    }

    _lastManualSyncVersion = _manualDataSyncService.syncVersion;
    unawaited(_loadAcademicContext());
    unawaited(_promptMissingFaceTemplateIfNeeded(force: true));
  }

  bool _shouldPromptMissingFaceTemplate({bool force = false}) {
    final user = _authProvider?.user;
    if (user == null || !user.isSiswa || user.hasActiveFaceTemplate) {
      _lastFaceTemplatePromptUserId = null;
      return false;
    }

    if (_isAnnouncementDialogVisible || _isFaceTemplateDialogVisible) {
      return false;
    }

    if (_isDialogGuardedByAttendanceSubmission) {
      return false;
    }

    if (!force && _lastFaceTemplatePromptUserId == user.id) {
      return false;
    }

    return true;
  }

  Future<bool> _canShowSecondaryDialogs() async {
    final permissionResults =
        await PermissionService.checkAllAttendancePermissions();

    return permissionResults.values.every((result) => result.isGranted);
  }

  Future<void> _promptMissingFaceTemplateIfNeeded({bool force = false}) async {
    if (!mounted ||
        !_shouldPromptMissingFaceTemplate(force: force) ||
        !await _canShowSecondaryDialogs()) {
      return;
    }

    final user = _authProvider?.user;
    if (user == null) {
      return;
    }

    _isFaceTemplateDialogVisible = true;
    _lastFaceTemplatePromptUserId = user.id;

    await showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
          ),
          title: const Text('Template Wajah Wajib'),
          content: const Text(
            'Akun siswa ini belum memiliki template wajah aktif. Silakan rekam template wajah terlebih dahulu sebelum melakukan absensi.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Nanti'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.of(dialogContext).pop();
                unawaited(
                  Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => const FaceTemplateScreen(),
                    ),
                  ),
                );
              },
              child: const Text('Buka Template'),
            ),
          ],
        );
      },
    );

    _isFaceTemplateDialogVisible = false;

    if (!mounted) {
      return;
    }

    await _authProvider?.refreshProfile();
  }

  @override
  void dispose() {
    _trackingSyncRevision++;
    WidgetsBinding.instance.removeObserver(this);
    _notificationService.unreadCountNotifier
        .removeListener(_handleUnreadCountChanged);
    _manualDataSyncService.removeListener(_handleManualSyncChanged);
    _authProvider?.removeListener(_handleAuthStateChanged);
    _backgroundPermissionRetryTimer?.cancel();
    if (!_backgroundTrackingEnabled) {
      _liveTrackingService.stopTracking();
    }
    super.dispose();
  }

  bool _isTrackingSyncStillValid(
    int syncRevision, {
    int? expectedUserId,
  }) {
    if (!mounted || syncRevision != _trackingSyncRevision) {
      return false;
    }

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final user = authProvider.user;
    if (!authProvider.isAuthenticated || user == null || !user.isSiswa) {
      return false;
    }

    if (expectedUserId != null && user.id != expectedUserId) {
      return false;
    }

    return true;
  }

  void _handleMainNavigationTap(int index) {
    final refreshAttendanceHome = index == 0;
    setState(() {
      _currentIndex = index;
      if (refreshAttendanceHome) {
        _attendanceRefreshTick++;
      }
    });
  }

  Future<void> _handleCenterActionTap(bool isSiswa) async {
    if (isSiswa) {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (context) => const QuickSubmissionScreen(),
        ),
      );
      if (mounted) {
        unawaited(_loadUnreadCount());
      }
      return;
    }

    _handleMainNavigationTap(1);
  }

  Widget _buildNavTab({
    required IconData icon,
    required String label,
    required int index,
  }) {
    final colorScheme = Theme.of(context).colorScheme;
    final selected = _currentIndex == index;
    final color = selected ? colorScheme.primary : const Color(0xFF7B8EA8);

    return GestureDetector(
      onTap: () => _handleMainNavigationTap(index),
      behavior: HitTestBehavior.opaque,
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 6),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          curve: Curves.easeOutCubic,
          padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 4),
          decoration: BoxDecoration(
            color: selected
                ? colorScheme.primary.withValues(alpha: 0.12)
                : Colors.transparent,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 21, color: color),
              const SizedBox(height: 2),
              SizedBox(
                height: 14,
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  child: Text(
                    label,
                    maxLines: 1,
                    style: TextStyle(
                      color: color,
                      fontSize: 11,
                      fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  double _bottomBarReserve(bool isSiswa) {
    final bottomInset = MediaQuery.viewPaddingOf(context).bottom;
    final base = isSiswa ? 104.0 : 88.0;
    return base + bottomInset;
  }

  Widget _buildFloatingBottomBar(bool isSiswa) {
    final colorScheme = Theme.of(context).colorScheme;
    final bottomInset = MediaQuery.viewPaddingOf(context).bottom;
    final bottomOffset = bottomInset > 0 ? bottomInset : 8.0;

    return SizedBox(
      height: _bottomBarReserve(isSiswa),
      child: Stack(
        alignment: Alignment.bottomCenter,
        clipBehavior: Clip.none,
        children: [
          Positioned(
            left: 12,
            right: 12,
            bottom: bottomOffset,
            child: Container(
              height: 68,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(24),
                border: Border.all(color: const Color(0xFFD8E6F8)),
                boxShadow: const [
                  BoxShadow(
                    color: Color(0x140B395E),
                    blurRadius: 12,
                    offset: Offset(0, -2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Expanded(
                    child: _buildNavTab(
                      icon: Icons.home_rounded,
                      label: 'Beranda',
                      index: 0,
                    ),
                  ),
                  Expanded(
                    child: _buildNavTab(
                      icon: Icons.apps_rounded,
                      label: 'Aplikasi',
                      index: 1,
                    ),
                  ),
                  if (isSiswa) const SizedBox(width: 76),
                  Expanded(
                    child: _buildNavTab(
                      icon: Icons.settings_rounded,
                      label: 'Pengaturan',
                      index: 2,
                    ),
                  ),
                  Expanded(
                    child: _buildNavTab(
                      icon: Icons.person_rounded,
                      label: 'Profil',
                      index: 3,
                    ),
                  ),
                ],
              ),
            ),
          ),
          if (isSiswa)
            Positioned(
              bottom: bottomOffset + 34,
              child: Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () => unawaited(_handleCenterActionTap(isSiswa)),
                  customBorder: const CircleBorder(),
                  child: Container(
                    width: 58,
                    height: 58,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      gradient: LinearGradient(
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                        colors: [
                          colorScheme.secondary,
                          colorScheme.primary,
                        ],
                      ),
                      border: Border.all(
                        color: Colors.white,
                        width: 3,
                      ),
                      boxShadow: const [
                        BoxShadow(
                          color: Color(0x2A0C4A7A),
                          blurRadius: 14,
                          offset: Offset(0, 6),
                        ),
                      ],
                    ),
                    child: const Icon(
                      Icons.dashboard_customize_rounded,
                      color: Colors.white,
                      size: 28,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final isSiswa = authProvider.user?.isSiswa ?? false;

    return Scaffold(
      extendBody: true,
      appBar: AppHeader(
        title: _titles[_currentIndex],
        subtitle: _buildHeaderSubtitle(),
        showNotification: true,
        notificationCount: _notificationCount,
        onNotificationTap: _handleNotificationTap,
      ),
      body: IndexedStack(index: _currentIndex, children: _screens),
      bottomNavigationBar: _buildFloatingBottomBar(isSiswa),
    );
  }

  void _handleNotificationTap() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const NotificationCenterScreen()),
    );
    unawaited(_loadUnreadCount());
  }
}
