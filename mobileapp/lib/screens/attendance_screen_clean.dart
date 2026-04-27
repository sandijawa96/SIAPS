import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/attendance_precheck_provider.dart';
import '../providers/auth_provider.dart';
import '../utils/constants.dart';
import '../widgets/user_identity_card.dart';
import '../widgets/attendance_info_card_realtime.dart';
import '../widgets/attendance_table.dart';
import '../models/user.dart';
import '../hooks/use_attendance_state.dart';
import '../services/dashboard_service.dart';
import '../services/lesson_schedule_service.dart';
import '../services/manual_data_sync_service.dart';
import '../services/attendance_service.dart';
import 'attendance_history_screen.dart';
import 'leave_approval_screen.dart';
import 'manual_attendance_management_screen.dart';
import 'monthly_recap_screen.dart';
import 'notification_center_screen.dart';
import 'schedule_overview_screen.dart';
import 'student_leave_screen.dart';
import 'wali_class_overview_screen.dart';

class AttendanceScreenClean extends StatefulWidget {
  final int refreshTick;

  const AttendanceScreenClean({
    Key? key,
    this.refreshTick = 0,
  }) : super(key: key);

  @override
  State<AttendanceScreenClean> createState() => _AttendanceScreenCleanState();
}

class _AttendanceScreenCleanState extends State<AttendanceScreenClean>
    with WidgetsBindingObserver {
  late UseAttendanceState _attendanceState;
  late AttendancePrecheckProvider _attendancePrecheckProvider;
  final DashboardService _dashboardService = DashboardService();
  final LessonScheduleService _lessonScheduleService = LessonScheduleService();
  final ManualDataSyncService _manualDataSyncService = ManualDataSyncService();
  final AttendanceService _attendanceService = AttendanceService();

  bool _isLoading = false;
  bool _isRefreshing = false;
  int _refreshTrigger = 0; // Counter to trigger refresh in AttendanceTable
  bool _isAttendanceLockedByStatus = false;
  String? _attendanceLockReason;
  bool _isHolidayToday = false;
  String? _holidayMessage;

  bool _isLoadingSchedule = true;
  List<LessonScheduleItem> _todaySchedule = const <LessonScheduleItem>[];
  String? _scheduleError;
  int _lastManualSyncVersion = 0;
  String? _lastSecurityWarningFingerprint;
  bool _securityWarningDialogVisible = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _attendanceState = UseAttendanceState();
    _attendancePrecheckProvider = AttendancePrecheckProvider();
    _attendancePrecheckProvider.addListener(_handlePrecheckStateChanged);
    _lastManualSyncVersion = _manualDataSyncService.syncVersion;
    _manualDataSyncService.addListener(_handleManualSyncChanged);
    _loadTodayAttendance();
    _loadTodaySchedule();
  }

  void _handleManualSyncChanged() {
    if (!mounted) {
      return;
    }

    if (_lastManualSyncVersion == _manualDataSyncService.syncVersion) {
      return;
    }

    _lastManualSyncVersion = _manualDataSyncService.syncVersion;
    _loadTodaySchedule();
  }

  void _refreshWorkingHours() {
    // Increment counter to trigger refresh in AttendanceTable
    setState(() {
      _refreshTrigger++;
    });
    unawaited(_refreshAttendancePrecheck());
  }

  Future<void> _loadTodaySchedule() async {
    if (!mounted) {
      return;
    }

    setState(() {
      _isLoadingSchedule = true;
    });

    try {
      final contextResponse = await _dashboardService.getAcademicContext();
      final tahunAjaranId =
          contextResponse.success ? contextResponse.data?.tahunAjaranId : null;

      final response = await _lessonScheduleService.getTodaySchedule(
        tahunAjaranId: tahunAjaranId,
      );
      if (!mounted) {
        return;
      }

      setState(() {
        _todaySchedule = response.data ?? const <LessonScheduleItem>[];
        _scheduleError = response.success ? null : response.message;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      setState(() {
        _todaySchedule = const <LessonScheduleItem>[];
        _scheduleError = 'Gagal mengambil jadwal pelajaran';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoadingSchedule = false;
        });
      }
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _manualDataSyncService.removeListener(_handleManualSyncChanged);
    _attendancePrecheckProvider.removeListener(_handlePrecheckStateChanged);
    _attendancePrecheckProvider.dispose();
    _attendanceState.dispose();
    super.dispose();
  }

  void _handlePrecheckStateChanged() {
    if (!mounted || _securityWarningDialogVisible) {
      return;
    }

    final fingerprint = _attendancePrecheckProvider.securityWarningFingerprint;
    if (fingerprint == null || fingerprint.isEmpty) {
      _lastSecurityWarningFingerprint = null;
      return;
    }

    if (_attendancePrecheckProvider.isActionLoading ||
        _lastSecurityWarningFingerprint == fingerprint) {
      return;
    }

    _lastSecurityWarningFingerprint = fingerprint;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }
      unawaited(_showPrecheckSecurityWarningDialog());
    });
  }

  Future<void> _showPrecheckSecurityWarningDialog() async {
    final warnings = _attendancePrecheckProvider.securityWarnings;
    if (warnings.isEmpty || _securityWarningDialogVisible || !mounted) {
      return;
    }

    _securityWarningDialogVisible = true;
    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(18),
          ),
          title: const Text('Warning Keamanan Pra-cek'),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Sistem mendeteksi indikator keamanan pada perangkat. Absensi tetap bisa dilanjutkan, tetapi warning ini akan dicatat untuk monitoring dan klarifikasi.',
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
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Saya Mengerti'),
            ),
          ],
        );
      },
    );
    _securityWarningDialogVisible = false;

    final payload = _attendancePrecheckProvider.buildSecurityWarningPayload(
      trigger: 'precheck_popup',
      acknowledged: true,
    );
    if (payload == null) {
      return;
    }

    unawaited(_attendanceService.reportPrecheckSecurityWarning(
      securityWarningPayload: payload,
    ));
  }

  @override
  void didUpdateWidget(covariant AttendanceScreenClean oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.refreshTick != widget.refreshTick) {
      _refreshDataSilently();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      _refreshDataSilently();
    }
  }

  Future<void> _loadTodayAttendance() async {
    if (_isLoading) return;

    setState(() {
      _isLoading = true;
    });

    try {
      final response = await _dashboardService
          .getTodayAttendanceStatus()
          .timeout(const Duration(seconds: 10));

      if (response.success && response.data != null) {
        final todayAttendance = response.data!;
        final normalizedStatusKey =
            todayAttendance.statusKey.trim().toLowerCase();
        final isStatusLocked = todayAttendance.isNonPresenceStatus ||
            normalizedStatusKey == 'izin' ||
            normalizedStatusKey == 'sakit' ||
            normalizedStatusKey == 'alpha';
        final lockReason = isStatusLocked
            ? ((todayAttendance.attendanceLockReason ?? '').trim().isNotEmpty
                ? todayAttendance.attendanceLockReason!.trim()
                : 'Absensi dikunci karena status ${todayAttendance.statusLabel} hari ini.')
            : null;

        // SELALU update state dengan data dari backend, termasuk nilai null
        _attendanceState.updateFromBackendData(
          checkinTime: todayAttendance.checkinTime,
          checkoutTime: todayAttendance.checkoutTime,
          isCheckedIn:
              todayAttendance.hasCheckedIn && !todayAttendance.hasCheckedOut,
        );
        _isAttendanceLockedByStatus = isStatusLocked;
        _attendanceLockReason = lockReason;
        _isHolidayToday = todayAttendance.isHoliday;
        _holidayMessage = todayAttendance.holidayMessage;

        debugPrint('Today attendance loaded:');
        debugPrint('   - hasCheckedIn: ${todayAttendance.hasCheckedIn}');
        debugPrint('   - hasCheckedOut: ${todayAttendance.hasCheckedOut}');
        debugPrint('   - checkinTime: ${todayAttendance.checkinTime}');
        debugPrint('   - checkoutTime: ${todayAttendance.checkoutTime}');
        debugPrint('   - status: ${todayAttendance.statusKey}');
        debugPrint('   - is holiday: $_isHolidayToday');
        debugPrint('   - attendance locked: $_isAttendanceLockedByStatus');
        debugPrint(
            '   - isCheckedIn state: ${_attendanceState.state.isCheckedIn}');

        if (!todayAttendance.hasCheckedIn && !todayAttendance.hasCheckedOut) {
          debugPrint(
              'User belum absen hari ini - state updated with null values');
        } else {
          debugPrint('User sudah absen - state updated with actual values');
        }
      } else {
        debugPrint('Failed to load attendance state, keeping last known state');
      }
      unawaited(_refreshAttendancePrecheck());
    } catch (e) {
      unawaited(_refreshAttendancePrecheck());
      debugPrint('Error loading today attendance: $e');
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  void _handleAttendanceTap() {
    // AttendanceTable now handles camera directly
    // This callback is kept for state updates only
    // Reload data after attendance action
    _loadTodayAttendance();
  }

  Future<void> _refreshAttendancePrecheck() async {
    if (!mounted) {
      return;
    }

    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final user = authProvider.user;
    final userId = user?.id;
    if (userId == null || !(user?.isSiswa ?? false)) {
      _attendancePrecheckProvider.reset();
      return;
    }

    await _attendancePrecheckProvider.refresh(
      userId: userId,
      isCheckedIn: _attendanceState.state.isCheckedIn,
    );
  }

  Future<void> _refreshData() async {
    // Prevent multiple refresh calls
    if (_isRefreshing) return;

    setState(() {
      _isRefreshing = true;
    });

    try {
      // Add delay to show refresh indicator properly
      await Future.delayed(const Duration(milliseconds: 500));

      debugPrint('Starting refresh data...');

      // Pull refresh hanya untuk data yang menentukan absensi saat ini.
      await _loadTodayAttendance();

      debugPrint('Triggering working hours refresh...');

      // Trigger refresh working hours by incrementing counter
      _refreshWorkingHours();

      debugPrint('Refresh completed');

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Data berhasil diperbarui'),
            backgroundColor: Colors.green,
            duration: Duration(seconds: 2),
          ),
        );
      }
    } catch (e) {
      debugPrint('Error refreshing data: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Gagal memperbarui data'),
            backgroundColor: Colors.red,
            duration: Duration(seconds: 2),
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isRefreshing = false;
        });
      }
    }
  }

  Future<void> _refreshDataSilently() async {
    if (_isRefreshing || _isLoading) return;

    setState(() {
      _isRefreshing = true;
    });

    try {
      await _loadTodayAttendance();
      _refreshWorkingHours();
    } catch (e) {
      debugPrint('Error refreshing data silently: $e');
    } finally {
      if (mounted) {
        setState(() {
          _isRefreshing = false;
        });
      }
    }
  }

  Future<void> _openHistory() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const AttendanceHistoryScreen()),
    );
  }

  Future<void> _openMonthlyRecap() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const MonthlyRecapScreen()),
    );
  }

  Future<void> _openSchedule() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const ScheduleOverviewScreen()),
    );
  }

  Future<void> _openNotifications() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const NotificationCenterScreen()),
    );
  }

  Future<void> _openLeaveMenu() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const StudentLeaveScreen()),
    );
    await _refreshDataSilently();
  }

  Future<void> _openApprovals() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const LeaveApprovalScreen()),
    );
  }

  Future<void> _openWaliClasses() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const WaliClassOverviewScreen()),
    );
  }

  Future<void> _openManualAttendanceManagement() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => const ManualAttendanceManagementScreen(),
      ),
    );
  }

  Widget _buildStudentAttendanceCard() {
    return Column(
      children: [
        Consumer<UseAttendanceState>(
          builder: (context, attendanceState, child) {
            return AttendanceTable(
              state: attendanceState.state,
              onTap: _handleAttendanceTap,
              onRefresh: _refreshWorkingHours,
              refreshToken: _refreshTrigger,
              isAttendanceLocked: _isAttendanceLockedByStatus,
              attendanceLockReason: _attendanceLockReason,
              isHolidayToday: _isHolidayToday,
              holidayMessage: _holidayMessage,
            );
          },
        ),
        Container(
          width: double.infinity,
          margin: const EdgeInsets.only(top: 8),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: AppColors.primary.withValues(alpha: 0.16),
              width: 1,
            ),
            boxShadow: const [
              BoxShadow(
                color: Color(0x110F4C81),
                blurRadius: 12,
                offset: Offset(0, 6),
              ),
            ],
          ),
          child: Material(
            color: Colors.transparent,
            child: InkWell(
              borderRadius: BorderRadius.circular(18),
              onTap: _openHistory,
              child: const Padding(
                padding: EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                child: Row(
                  children: [
                    Icon(
                      Icons.history_edu_outlined,
                      color: Color(0xFF2A67A9),
                    ),
                    SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        'Riwayat Presensi',
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF123B67),
                        ),
                      ),
                    ),
                    Icon(Icons.arrow_forward_ios_rounded,
                        size: 16, color: Color(0xFF7B8EA8)),
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildNonStudentNoticeCard(User? user) {
    final bool canApproveStudentLeave = user?.canApproveStudentLeave ?? false;
    final bool canOpenWaliClass =
        user?.canOpenAttendanceMonitoringMenu ?? false;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFD8E6F8)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x110F4C81),
            blurRadius: 14,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF3FF),
                  borderRadius: BorderRadius.circular(14),
                ),
                child:
                    const Icon(Icons.badge_outlined, color: Color(0xFF2563EB)),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Akun Non-Siswa',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF123B67),
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Absensi pegawai menggunakan aplikasi JSA. SIAP mobile dipakai untuk monitoring, jadwal, dan approval sesuai hak akses.',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF66758A),
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (canApproveStudentLeave || canOpenWaliClass) ...[
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (canApproveStudentLeave)
                  _MiniActionChip(
                    label: 'Persetujuan Izin',
                    icon: Icons.fact_check_outlined,
                    onTap: _openApprovals,
                  ),
                if (canOpenWaliClass)
                  _MiniActionChip(
                    label: user?.attendanceMonitoringMenuTitle ??
                        'Monitoring Kelas',
                    icon: Icons.groups_2_outlined,
                    onTap: _openWaliClasses,
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final user = authProvider.user;
    final bottomNavSpace = 122.0 + MediaQuery.viewPaddingOf(context).bottom;

    return MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: _attendanceState),
        ChangeNotifierProvider.value(value: _attendancePrecheckProvider),
      ],
      child: Scaffold(
        backgroundColor: const Color(0xFFF3F7FF),
        body: Container(
          decoration: const BoxDecoration(
            gradient: LinearGradient(
              colors: [Color(0xFFE8F2FF), Color(0xFFF6FAFF)],
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
            ),
          ),
          child: RefreshIndicator(
            onRefresh: _refreshData,
            color: Color(AppColors.primaryColorValue),
            child: SingleChildScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.fromLTRB(18, 18, 18, bottomNavSpace),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  UserIdentityCard(user: user),
                  const SizedBox(height: 14),
                  AttendanceInfoCardRealtime(
                    refreshToken: _refreshTrigger,
                  ),
                  const SizedBox(height: 10),
                  if (user?.isSiswa ?? false)
                    _buildStudentAttendanceCard()
                  else
                    _buildNonStudentNoticeCard(user),
                  const SizedBox(height: 14),
                  _TimelineScheduleSection(
                    user: user,
                    kelasLabel: authProvider.userKelasNama,
                    lessons: _todaySchedule,
                    isLoadingSchedule: _isLoadingSchedule,
                    scheduleError: _scheduleError,
                  ),
                  const SizedBox(height: 10),
                  const _SectionLabel(
                    icon: Icons.grid_view_rounded,
                    title: 'Akses Cepat',
                    subtitle: 'Menu inti yang paling sering dipakai',
                  ),
                  const SizedBox(height: 10),
                  _QuickActionGrid(
                    user: user,
                    onOpenHistory: _openHistory,
                    onOpenMonthlyRecap: _openMonthlyRecap,
                    onOpenSchedule: _openSchedule,
                    onOpenNotifications: _openNotifications,
                    onOpenLeave: _openLeaveMenu,
                    onOpenApprovals: _openApprovals,
                    onOpenWaliClass: _openWaliClasses,
                    onOpenManualAttendanceManagement:
                        _openManualAttendanceManagement,
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _QuickActionGrid extends StatelessWidget {
  final User? user;
  final Future<void> Function() onOpenHistory;
  final Future<void> Function() onOpenMonthlyRecap;
  final Future<void> Function() onOpenSchedule;
  final Future<void> Function() onOpenNotifications;
  final Future<void> Function() onOpenLeave;
  final Future<void> Function() onOpenApprovals;
  final Future<void> Function() onOpenWaliClass;
  final Future<void> Function() onOpenManualAttendanceManagement;

  const _QuickActionGrid({
    required this.user,
    required this.onOpenHistory,
    required this.onOpenMonthlyRecap,
    required this.onOpenSchedule,
    required this.onOpenNotifications,
    required this.onOpenLeave,
    required this.onOpenApprovals,
    required this.onOpenWaliClass,
    required this.onOpenManualAttendanceManagement,
  });

  @override
  Widget build(BuildContext context) {
    final items = <_QuickActionItem>[
      if (user?.isSiswa ?? false)
        _QuickActionItem(
          title: 'Rekap Bulanan',
          icon: Icons.bar_chart_rounded,
          color: const Color(0xFF0891B2),
          onTap: onOpenMonthlyRecap,
        ),
      if (user?.canViewScheduleOnMobile ?? false)
        _QuickActionItem(
          title: 'Jadwal Saya',
          icon: Icons.event_note_outlined,
          color: const Color(0xFF7C3AED),
          onTap: onOpenSchedule,
        ),
      if (user?.isSiswa ?? false)
        _QuickActionItem(
          title: 'Izin Saya',
          icon: Icons.assignment_outlined,
          color: const Color(0xFF059669),
          onTap: onOpenLeave,
        ),
      if (user?.canApproveStudentLeave ?? false)
        _QuickActionItem(
          title: 'Approval',
          icon: Icons.fact_check_outlined,
          color: const Color(0xFFD97706),
          onTap: onOpenApprovals,
        ),
      if (user?.canManageManualAttendance ?? false)
        _QuickActionItem(
          title: 'Kelola Absen',
          icon: Icons.rule_folder_outlined,
          color: const Color(0xFF2563EB),
          onTap: onOpenManualAttendanceManagement,
        ),
      if (user?.canOpenAttendanceMonitoringMenu ?? false)
        _QuickActionItem(
          title: user?.attendanceMonitoringMenuTitle ?? 'Monitoring Kelas',
          icon: Icons.groups_2_outlined,
          color: const Color(0xFF0F766E),
          onTap: onOpenWaliClass,
        ),
      _QuickActionItem(
        title: 'Notifikasi',
        icon: Icons.notifications_outlined,
        color: const Color(0xFFE11D48),
        onTap: onOpenNotifications,
      ),
      if (user?.isSiswa ?? false)
        _QuickActionItem(
          title: 'Riwayat',
          icon: Icons.history_edu_outlined,
          color: const Color(0xFF2563EB),
          onTap: onOpenHistory,
        ),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final isNarrow = constraints.maxWidth < 360;
        final maxColumns = isNarrow ? 3 : 4;
        final crossAxisCount = items.length <= maxColumns
            ? items.length.clamp(1, maxColumns)
            : maxColumns;
        final crossAxisSpacing = isNarrow ? 8.0 : 10.0;
        final mainAxisSpacing = isNarrow ? 8.0 : 10.0;
        final mainAxisExtent = isNarrow ? 88.0 : 92.0;
        final iconBoxSize = isNarrow ? 40.0 : 42.0;
        final iconSize = isNarrow ? 20.0 : 21.0;
        final labelSize = 11.0;
        final cardPadding = isNarrow ? 10.0 : 12.0;

        return Container(
          width: double.infinity,
          padding: EdgeInsets.all(cardPadding),
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: const Color(0xFFD9E7F8)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x110F4C81),
                blurRadius: 14,
                offset: Offset(0, 6),
              ),
            ],
          ),
          child: GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: crossAxisCount,
              crossAxisSpacing: crossAxisSpacing,
              mainAxisSpacing: mainAxisSpacing,
              mainAxisExtent: mainAxisExtent,
            ),
            itemCount: items.length,
            itemBuilder: (context, index) {
              final item = items[index];
              return Material(
                color: Colors.transparent,
                child: InkWell(
                  borderRadius: BorderRadius.circular(14),
                  onTap: () {
                    item.onTap();
                  },
                  child: Padding(
                    padding: EdgeInsets.symmetric(
                      horizontal: isNarrow ? 1 : 2,
                      vertical: isNarrow ? 4 : 6,
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Container(
                          width: iconBoxSize,
                          height: iconBoxSize,
                          decoration: BoxDecoration(
                            color: item.color.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Icon(item.icon,
                              color: item.color, size: iconSize),
                        ),
                        SizedBox(height: isNarrow ? 6 : 8),
                        Text(
                          item.title,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            fontSize: labelSize,
                            fontWeight: FontWeight.w700,
                            color: const Color(0xFF123B67),
                            height: 1.2,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }
}

class _QuickActionItem {
  final String title;
  final IconData icon;
  final Color color;
  final Future<void> Function() onTap;

  const _QuickActionItem({
    required this.title,
    required this.icon,
    required this.color,
    required this.onTap,
  });
}

class _MiniActionChip extends StatelessWidget {
  final String label;
  final IconData icon;
  final Future<void> Function() onTap;

  const _MiniActionChip({
    required this.label,
    required this.icon,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(999),
        onTap: () {
          onTap();
        },
        child: Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: const Color(0xFFEAF3FF),
            borderRadius: BorderRadius.circular(999),
          ),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 14, color: const Color(0xFF2A67A9)),
              const SizedBox(width: 6),
              Text(
                label,
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF123B67),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _TimelineScheduleSection extends StatelessWidget {
  final User? user;
  final String kelasLabel;
  final List<LessonScheduleItem> lessons;
  final bool isLoadingSchedule;
  final String? scheduleError;

  const _TimelineScheduleSection({
    Key? key,
    required this.user,
    required this.kelasLabel,
    required this.lessons,
    required this.isLoadingSchedule,
    required this.scheduleError,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final dayLabel = _dayLabel(now.weekday);
    final isStudent = user?.isSiswa ?? false;
    final displayAudience = isStudent
        ? (kelasLabel.trim().isEmpty ? 'Kelas belum terdeteksi' : kelasLabel)
        : (_isTeacherUser(user) ? 'Jadwal Mengajar' : 'Jadwal Pribadi');
    final scheduleVisualItems = _buildScheduleVisualItems(lessons, isStudent);

    final scheduleCard = _DashboardPanel(
      title: 'Jadwal Hari Ini',
      icon: Icons.menu_book_rounded,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _buildMetaChip(
                text: displayAudience,
                textColor: const Color(0xFF2A67A9),
                bgColor: const Color(0xFFEAF3FF),
              ),
              _buildMetaChip(
                text: dayLabel,
                textColor: const Color(0xFF15803D),
                bgColor: const Color(0xFFE7FFF4),
              ),
              _buildMetaChip(
                text: '${lessons.length} JP',
                textColor: const Color(0xFF9A6700),
                bgColor: const Color(0xFFFFF4DC),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (isLoadingSchedule)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              decoration: BoxDecoration(
                color: const Color(0xFFF3F8FF),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Text(
                'Memuat jadwal pelajaran...',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF607893),
                ),
              ),
            )
          else if (scheduleError != null && lessons.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              decoration: BoxDecoration(
                color: const Color(0xFFF3F8FF),
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                _mapScheduleError(scheduleError!),
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF607893),
                ),
              ),
            )
          else if (lessons.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
              decoration: BoxDecoration(
                color: const Color(0xFFF3F8FF),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Text(
                'Tidak ada jadwal pelajaran hari ini',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF607893),
                ),
              ),
            )
          else
            ...scheduleVisualItems
                .map(
                  (item) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 9,
                      ),
                      decoration: BoxDecoration(
                        color: item.isBreak
                            ? const Color(0xFFFFF4DC)
                            : const Color(0xFFF3F8FF),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(
                          color: item.isBreak
                              ? const Color(0xFFF1D18A)
                              : const Color(0xFFD6E6F7),
                        ),
                      ),
                      child: Text(
                        item.text,
                        style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: item.isBreak
                              ? const Color(0xFF9A6700)
                              : const Color(0xFF35587A),
                        ),
                      ),
                    ),
                  ),
                )
                .toList(),
        ],
      ),
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        return scheduleCard;
      },
    );
  }

  Widget _buildMetaChip({
    required String text,
    required Color textColor,
    required Color bgColor,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: textColor,
        ),
      ),
    );
  }

  String _dayLabel(int weekday) {
    switch (weekday) {
      case DateTime.monday:
        return 'Senin';
      case DateTime.tuesday:
        return 'Selasa';
      case DateTime.wednesday:
        return 'Rabu';
      case DateTime.thursday:
        return 'Kamis';
      case DateTime.friday:
        return 'Jumat';
      case DateTime.saturday:
        return 'Sabtu';
      default:
        return 'Minggu';
    }
  }

  bool _isTeacherUser(User? currentUser) {
    if (currentUser == null) {
      return false;
    }

    for (final role in currentUser.roles) {
      final normalized = role.name.toLowerCase().replaceAll('_', ' ').trim();
      if (normalized.contains('guru') || normalized.contains('wali kelas')) {
        return true;
      }
    }

    return false;
  }

  String _buildLessonText(LessonScheduleItem lesson, bool isStudent) {
    final jpPrefix = lesson.jamKe != null ? 'JP ${lesson.jamKe} | ' : '';
    final shouldShowClass = !isStudent && lesson.kelasNama != '-';
    final classSuffix = shouldShowClass ? ' (${lesson.kelasNama})' : '';
    return '$jpPrefix${lesson.timeRange}  ${lesson.mataPelajaranNama}$classSuffix';
  }

  List<_ScheduleVisualItem> _buildScheduleVisualItems(
    List<LessonScheduleItem> sourceLessons,
    bool isStudent,
  ) {
    if (sourceLessons.isEmpty) {
      return const <_ScheduleVisualItem>[];
    }

    final lessonsSorted = [...sourceLessons]..sort((a, b) {
        final byStart = _parseTimeToMinutes(a.jamMulai)
            .compareTo(_parseTimeToMinutes(b.jamMulai));
        if (byStart != 0) {
          return byStart;
        }
        return (a.jamKe ?? 0).compareTo(b.jamKe ?? 0);
      });

    final items = <_ScheduleVisualItem>[];
    for (var i = 0; i < lessonsSorted.length; i++) {
      final lesson = lessonsSorted[i];
      items.add(_ScheduleVisualItem(
        isBreak: false,
        text: _buildLessonText(lesson, isStudent),
      ));

      if (i >= lessonsSorted.length - 1) {
        continue;
      }

      final nextLesson = lessonsSorted[i + 1];
      final currentEnd = _parseTimeToMinutes(lesson.jamSelesai);
      final nextStart = _parseTimeToMinutes(nextLesson.jamMulai);
      final gapMinutes = nextStart - currentEnd;

      if (gapMinutes <= 0) {
        continue;
      }

      items.add(_ScheduleVisualItem(
        isBreak: true,
        text:
            '${_buildBreakLabel(gapMinutes)} | ${_formatHm(lesson.jamSelesai)} - ${_formatHm(nextLesson.jamMulai)} (${gapMinutes} menit)',
      ));
    }

    return items;
  }

  int _parseTimeToMinutes(String value) {
    final parts = value.split(':');
    if (parts.length < 2) {
      return -1;
    }

    final hour = int.tryParse(parts[0]) ?? -1;
    final minute = int.tryParse(parts[1]) ?? -1;
    if (hour < 0 || minute < 0) {
      return -1;
    }

    return (hour * 60) + minute;
  }

  String _formatHm(String value) {
    if (value.length >= 5 && value.contains(':')) {
      return value.substring(0, 5);
    }
    return value;
  }

  String _buildBreakLabel(int minutes) {
    if (minutes >= 30) {
      return 'Istirahat Panjang';
    }
    if (minutes >= 10) {
      return 'Istirahat';
    }
    return 'Jeda';
  }

  String _mapScheduleError(String rawMessage) {
    final message = rawMessage.trim();
    if (message.isEmpty) {
      return 'Jadwal pelajaran tidak tersedia';
    }

    final normalized = message.toLowerCase();
    if (normalized.contains('forbidden') ||
        normalized.contains('unauthorized') ||
        normalized.contains('akses')) {
      return 'Akun ini belum memiliki akses jadwal pelajaran';
    }

    return message;
  }
}

class _ScheduleVisualItem {
  final bool isBreak;
  final String text;

  const _ScheduleVisualItem({
    required this.isBreak,
    required this.text,
  });
}

class _DashboardPanel extends StatelessWidget {
  final String title;
  final IconData icon;
  final Widget child;

  const _DashboardPanel({
    required this.title,
    required this.icon,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    final primaryColor = Color(AppColors.primaryColorValue);
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: primaryColor.withOpacity(0.14), width: 1),
        boxShadow: [
          BoxShadow(
            color: primaryColor.withOpacity(0.08),
            blurRadius: 14,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: primaryColor.withOpacity(0.14),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Icon(icon, size: 16, color: primaryColor),
              ),
              const SizedBox(width: 8),
              Text(
                title,
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: Colors.grey[900],
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }
}

class _TimelineStep extends StatelessWidget {
  final String label;
  final String value;
  final bool isDone;
  final bool showConnector;

  const _TimelineStep({
    required this.label,
    required this.value,
    required this.isDone,
    required this.showConnector,
  });

  @override
  Widget build(BuildContext context) {
    final Color dotColor =
        isDone ? const Color(0xFF16A34A) : const Color(0xFFE11D48);
    final Color textColor =
        isDone ? const Color(0xFF26415C) : const Color(0xFF607893);

    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 16,
          child: Column(
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  color: dotColor,
                  shape: BoxShape.circle,
                ),
              ),
              if (showConnector)
                Container(
                  width: 2,
                  height: 26,
                  margin: const EdgeInsets.only(top: 4),
                  color: const Color(0xFFC4D7EA),
                ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(top: 0.5),
            child: Text(
              '$label  $value',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: textColor,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _SectionLabel extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;

  const _SectionLabel({
    Key? key,
    required this.icon,
    required this.title,
    required this.subtitle,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final primaryColor = Color(AppColors.primaryColorValue);
    return Row(
      children: [
        Container(
          width: 32,
          height: 32,
          decoration: BoxDecoration(
            color: primaryColor.withOpacity(0.14),
            borderRadius: BorderRadius.circular(10),
          ),
          child: Icon(icon, size: 18, color: primaryColor),
        ),
        const SizedBox(width: 10),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              title,
              style: TextStyle(
                fontSize: 16,
                fontWeight: FontWeight.w800,
                color: Colors.grey[900],
              ),
            ),
            Text(
              subtitle,
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
      ],
    );
  }
}
