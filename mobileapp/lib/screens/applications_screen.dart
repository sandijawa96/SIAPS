import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/user.dart';
import '../providers/auth_provider.dart';
import 'attendance_history_screen.dart';
import 'face_template_screen.dart';
import 'leave_approval_screen.dart';
import 'live_pd_report_screen.dart';
import 'manual_attendance_management_screen.dart';
import 'monthly_recap_screen.dart';
import 'schedule_overview_screen.dart';
import 'student_leave_screen.dart';
import 'wali_class_overview_screen.dart';

const Color _applicationsBackgroundStart = Color(0xFFF4F8FC);
const Color _applicationsBackgroundEnd = Color(0xFFEAF2FF);
const Color _applicationsInk = Color(0xFF123B67);
const Color _applicationsMuted = Color(0xFF66758A);
const Color _applicationsBorder = Color(0xFFD8E6F8);
const Color _applicationsTileBorder = Color(0xFFE1ECFA);
const Color _applicationsTileFill = Color(0xFFF7FAFF);
const Color _applicationsAccentPrimary = Color(0xFF0C4A7A);
const Color _applicationsAccentSecondary = Color(0xFF2A6FDB);

class ApplicationsScreen extends StatelessWidget {
  const ApplicationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    final user = authProvider.user;
    final menuItems = _buildMenuItems(user);
    final bottomNavSpace = 120.0 + MediaQuery.viewPaddingOf(context).bottom;

    return Scaffold(
      backgroundColor: _applicationsBackgroundStart,
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [_applicationsBackgroundStart, _applicationsBackgroundEnd],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: ListView(
          padding: EdgeInsets.fromLTRB(16, 16, 16, bottomNavSpace),
          children: [
            _ApplicationsHeaderCard(
              title:
                  user?.isSiswa == true ? 'Aplikasi Siswa' : 'Aplikasi Mobile',
              subtitle: user?.isSiswa == true
                  ? 'Fitur layanan siswa dengan akses cepat ke presensi, izin, dan rekap.'
                  : 'Fitur operasional mobile untuk presensi, monitoring, dan tindak lanjut.',
            ),
            const SizedBox(height: 16),
            _ApplicationsPanel(
              icon: Icons.widgets_rounded,
              title: 'Menu Aktif',
              subtitle: 'Fitur yang tersedia sesuai role login saat ini',
              child: Column(
                children: [
                  for (var index = 0; index < menuItems.length; index++) ...[
                    if (index > 0) const SizedBox(height: 12),
                    _ApplicationMenuTile(
                      icon: menuItems[index].icon,
                      title: menuItems[index].title,
                      subtitle: menuItems[index].subtitle,
                      accent: index.isEven
                          ? _applicationsAccentPrimary
                          : _applicationsAccentSecondary,
                      onTap: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => menuItems[index].builder(),
                          ),
                        );
                      },
                    ),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  List<_MenuItem> _buildMenuItems(User? user) {
    if (user == null) {
      return <_MenuItem>[];
    }

    return <_MenuItem>[
      if (user.isSiswa)
        _MenuItem(
          title: 'Riwayat Presensi',
          subtitle: 'Lihat catatan check-in dan check-out terbaru.',
          icon: Icons.history_edu_outlined,
          builder: () => const AttendanceHistoryScreen(),
        ),
      if (user.isSiswa)
        _MenuItem(
          title: 'Rekap Bulanan',
          subtitle: 'Bandingkan performa presensi bulan ini dan sebelumnya.',
          icon: Icons.bar_chart_rounded,
          builder: () => const MonthlyRecapScreen(),
        ),
      if (user.isSiswa)
        _MenuItem(
          title: 'Live Laporan PD',
          subtitle: 'Pantau absensi teman sekelas hari ini secara langsung.',
          icon: Icons.groups_rounded,
          builder: () => const LivePdReportScreen(),
        ),
      if (user.canViewScheduleOnMobile)
        _MenuItem(
          title: 'Jadwal Saya',
          subtitle: 'Agenda pelajaran atau jadwal pribadi yang aktif.',
          icon: Icons.event_note_outlined,
          builder: () => const ScheduleOverviewScreen(),
        ),
      if (user.isSiswa)
        _MenuItem(
          title: 'Izin Saya',
          subtitle: 'Pantau pengajuan izin siswa dan buat pengajuan baru.',
          icon: Icons.assignment_outlined,
          builder: () => const StudentLeaveScreen(),
        ),
      if (user.isSiswa)
        _MenuItem(
          title: 'Template Wajah',
          subtitle: 'Rekam atau cek template wajah untuk presensi siswa.',
          icon: Icons.face_retouching_natural_outlined,
          builder: () => const FaceTemplateScreen(),
        ),
      if (user.canOpenAttendanceMonitoringMenu)
        _MenuItem(
          title: user.attendanceMonitoringMenuTitle,
          subtitle: user.attendanceMonitoringMenuSubtitle,
          icon: Icons.groups_2_outlined,
          builder: () => const WaliClassOverviewScreen(),
        ),
      if (user.canApproveStudentLeave)
        _MenuItem(
          title: 'Persetujuan Izin',
          subtitle: 'Approval izin siswa sesuai scope role Anda.',
          icon: Icons.fact_check_outlined,
          builder: () => const LeaveApprovalScreen(),
        ),
      if (user.canManageManualAttendance)
        _MenuItem(
          title: 'Pengelolaan Absensi',
          subtitle:
              'Tindak lanjut lupa tap-out, koreksi, dan absensi manual siswa.',
          icon: Icons.rule_folder_outlined,
          builder: () => const ManualAttendanceManagementScreen(),
        ),
    ];
  }
}

class _ApplicationsHeaderCard extends StatelessWidget {
  final String title;
  final String subtitle;

  const _ApplicationsHeaderCard({
    required this.title,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [_applicationsAccentPrimary, _applicationsAccentSecondary],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1A0B395E),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.16),
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.apps_rounded,
              color: Colors.white,
              size: 22,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.84),
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
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
}

class _ApplicationsPanel extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Widget child;

  const _ApplicationsPanel({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: _applicationsBorder),
        boxShadow: const [
          BoxShadow(
            color: Color(0x120B395E),
            blurRadius: 20,
            offset: Offset(0, 10),
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
                  color: _applicationsAccentPrimary,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: Colors.white, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: _applicationsInk,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: _applicationsMuted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          child,
        ],
      ),
    );
  }
}

class _ApplicationMenuTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color accent;
  final VoidCallback onTap;

  const _ApplicationMenuTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.accent,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Ink(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: _applicationsTileFill,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: _applicationsTileBorder),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: accent, size: 22),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: _applicationsInk,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 12,
                        color: _applicationsMuted,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              const Icon(
                Icons.arrow_forward_ios_rounded,
                size: 16,
                color: Color(0xFF7B8EA8),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MenuItem {
  final String title;
  final String subtitle;
  final IconData icon;
  final Widget Function() builder;

  const _MenuItem({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.builder,
  });
}
