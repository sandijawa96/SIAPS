import 'dart:io' show Platform;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../services/manual_data_sync_service.dart';
import '../utils/constants.dart';
import '../widgets/app_version_text.dart';
import 'attendance_reminder_settings_screen.dart';
import 'face_template_screen.dart';
import 'leave_approval_screen.dart';
import 'manual_attendance_management_screen.dart';
import 'notification_center_screen.dart';
import 'wali_class_overview_screen.dart';

const Color _settingsBackgroundStart = Color(0xFFF4F8FC);
const Color _settingsBackgroundEnd = Color(0xFFEAF2FF);
const Color _settingsInk = Color(0xFF123B67);
const Color _settingsMuted = Color(0xFF66758A);
const Color _settingsBorder = Color(0xFFD8E6F8);
const Color _settingsTileBorder = Color(0xFFE1ECFA);
const Color _settingsTileFill = Color(0xFFF7FAFF);
const Color _settingsAccentPrimary = Color(0xFF0C4A7A);
const Color _settingsAccentSecondary = Color(0xFF2A6FDB);

class SettingsScreen extends StatefulWidget {
  const SettingsScreen({super.key});

  @override
  State<SettingsScreen> createState() => _SettingsScreenState();
}

class _SettingsScreenState extends State<SettingsScreen> {
  final ManualDataSyncService _manualDataSyncService = ManualDataSyncService();

  String _formatDeviceTime(DateTime? value,
      {String fallback = 'Belum tersedia'}) {
    if (value == null) {
      return fallback;
    }

    final local = value.toLocal();
    final day = local.day.toString().padLeft(2, '0');
    final month = local.month.toString().padLeft(2, '0');
    final hour = local.hour.toString().padLeft(2, '0');
    final minute = local.minute.toString().padLeft(2, '0');
    return '$day/$month/${local.year} $hour:$minute';
  }

  String _formatSyncTime(DateTime? value) {
    if (value == null) {
      return 'Belum pernah disinkronkan manual';
    }

    final local = value.toLocal();
    final day = local.day.toString().padLeft(2, '0');
    final month = local.month.toString().padLeft(2, '0');
    final hour = local.hour.toString().padLeft(2, '0');
    final minute = local.minute.toString().padLeft(2, '0');
    return 'Sinkron terakhir $day/$month/${local.year} $hour:$minute';
  }

  Future<void> _runManualSync(BuildContext context) async {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final result =
        await _manualDataSyncService.syncNonCriticalData(authProvider);

    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(result.message),
        backgroundColor: result.success ? Colors.green : Colors.red,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    final user = authProvider.user;
    final bottomNavSpace = 120.0 + MediaQuery.viewPaddingOf(context).bottom;

    return Scaffold(
      backgroundColor: _settingsBackgroundStart,
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [_settingsBackgroundStart, _settingsBackgroundEnd],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: ListView(
          padding: EdgeInsets.fromLTRB(16, 16, 16, bottomNavSpace),
          children: [
            _SettingsHeaderCard(
              title: 'Pengaturan',
              subtitle: user?.isSiswa == true
                  ? 'Kelola sinkronisasi, notifikasi, perangkat, dan sesi akun.'
                  : 'Kelola sinkronisasi, notifikasi, tugas, dan sesi akun.',
            ),
            const SizedBox(height: 16),
            _SettingsPanel(
              icon: Icons.manage_accounts_rounded,
              title: 'Akun',
              subtitle: 'Kontrol dasar akun dan akses notifikasi',
              child: Column(
                children: [
                  AnimatedBuilder(
                    animation: _manualDataSyncService,
                    builder: (context, _) {
                      return _SettingsActionTile(
                        icon: Icons.sync_rounded,
                        title: 'Sinkronisasi Data',
                        subtitle:
                            'Sinkron manual untuk profil, kelas, wali kelas, template wajah, dan jadwal. ${_formatSyncTime(_manualDataSyncService.lastSyncedAt)}',
                        accent: _settingsAccentPrimary,
                        onTap: _manualDataSyncService.isSyncing
                            ? null
                            : () => _runManualSync(context),
                        trailingOverride: _manualDataSyncService.isSyncing
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: _settingsAccentPrimary,
                                ),
                              )
                            : null,
                      );
                    },
                  ),
                  const SizedBox(height: 12),
                  _SettingsActionTile(
                    icon: Icons.notifications_outlined,
                    title: 'Pusat Notifikasi',
                    subtitle: 'Lihat semua notifikasi sistem',
                    accent: _settingsAccentSecondary,
                    onTap: () {
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const NotificationCenterScreen(),
                        ),
                      );
                    },
                  ),
                ],
              ),
            ),
            if ((user?.canApproveStudentLeave ?? false) ||
                (user?.canOpenAttendanceMonitoringMenu ?? false) ||
                (user?.canManageManualAttendance ?? false)) ...[
              const SizedBox(height: 16),
              _SettingsPanel(
                icon: Icons.assignment_turned_in_rounded,
                title: 'Tugas',
                subtitle: 'Akses cepat ke fitur operasional sesuai role',
                child: Column(
                  children: [
                    if (user?.canManageManualAttendance ?? false)
                      _SettingsActionTile(
                        icon: Icons.rule_folder_outlined,
                        title: 'Pengelolaan Absensi',
                        subtitle:
                            'Kelola lupa tap-out, koreksi absensi, dan absensi manual siswa',
                        accent: _settingsAccentPrimary,
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) =>
                                  const ManualAttendanceManagementScreen(),
                            ),
                          );
                        },
                      ),
                    if ((user?.canManageManualAttendance ?? false) &&
                        ((user?.canApproveStudentLeave ?? false) ||
                            (user?.canOpenAttendanceMonitoringMenu ?? false)))
                      const SizedBox(height: 12),
                    if (user?.canApproveStudentLeave ?? false)
                      _SettingsActionTile(
                        icon: Icons.fact_check_outlined,
                        title: 'Persetujuan Izin',
                        subtitle:
                            'Tinjau pengajuan izin siswa sesuai scope role Anda',
                        accent: _settingsAccentPrimary,
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const LeaveApprovalScreen(),
                            ),
                          );
                        },
                      ),
                    if ((user?.canApproveStudentLeave ?? false) &&
                        (user?.canOpenAttendanceMonitoringMenu ?? false))
                      const SizedBox(height: 12),
                    if (user?.canOpenAttendanceMonitoringMenu ?? false)
                      _SettingsActionTile(
                        icon: Icons.groups_2_outlined,
                        title: user?.attendanceMonitoringMenuTitle ??
                            'Monitoring Kelas',
                        subtitle: user?.attendanceMonitoringMenuSubtitle ??
                            'Buka monitoring kehadiran siswa hari ini',
                        accent: _settingsAccentSecondary,
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const WaliClassOverviewScreen(),
                            ),
                          );
                        },
                      ),
                  ],
                ),
              ),
            ],
            if (user != null) ...[
              const SizedBox(height: 16),
              _SettingsPanel(
                icon: Icons.smartphone_rounded,
                title: user.isSiswa ? 'Keamanan Perangkat' : 'Perangkat Mobile',
                subtitle: user.isSiswa
                    ? 'Status binding, versi SIAPS, pengingat, dan template wajah'
                    : 'Perangkat aktif terakhir yang terdaftar di server',
                child: Column(
                  children: [
                    _SettingsActionTile(
                      icon: user.isSiswa
                          ? Icons.lock_outline_rounded
                          : Icons.devices_outlined,
                      title: user.isSiswa
                          ? 'Status Device Binding'
                          : 'Status Perangkat Mobile',
                      subtitle: user.isSiswa
                          ? (user.deviceLocked
                              ? 'Perangkat sudah terkunci ke akun siswa'
                              : 'Perangkat belum dikunci')
                          : ((user.deviceId ?? '').trim().isEmpty
                              ? 'Belum ada perangkat mobile yang tercatat'
                              : 'Perangkat aktif terakhir tercatat di server'),
                      accent: _settingsAccentPrimary,
                    ),
                    const SizedBox(height: 12),
                    _SettingsActionTile(
                      icon: Icons.smartphone_outlined,
                      title: 'Nama Device',
                      subtitle: (user.deviceName ?? '').trim().isEmpty
                          ? 'Belum tersedia'
                          : user.deviceName!,
                      accent: _settingsAccentSecondary,
                    ),
                    const SizedBox(height: 12),
                    _SettingsActionTile(
                      icon: Icons.key_outlined,
                      title: 'ID Device',
                      subtitle: (user.deviceId ?? '').trim().isEmpty
                          ? 'Belum tersedia'
                          : user.deviceId!,
                      accent: _settingsAccentSecondary,
                    ),
                    const SizedBox(height: 12),
                    _SettingsActionTile(
                      icon: Icons.link_rounded,
                      title: user.isSiswa
                          ? 'Waktu Binding'
                          : 'Registrasi Device Terakhir',
                      subtitle: _formatDeviceTime(
                        user.deviceBoundAt,
                        fallback: user.isSiswa
                            ? 'Belum pernah di-binding'
                            : 'Belum pernah tercatat',
                      ),
                      accent: _settingsAccentPrimary,
                    ),
                    const SizedBox(height: 12),
                    _SettingsActionTile(
                      icon: Icons.access_time_rounded,
                      title: 'Aktivitas Device Terakhir',
                      subtitle: _formatDeviceTime(
                        user.lastDeviceActivity,
                        fallback: 'Belum ada aktivitas tercatat',
                      ),
                      accent: _settingsAccentSecondary,
                    ),
                    const SizedBox(height: 12),
                    _SettingsActionTile(
                      icon: Icons.system_update_alt_rounded,
                      title: 'Versi App SIAPS',
                      subtitle: '',
                      subtitleWidget: AppVersionText(
                        includeAppName: true,
                        fallback:
                            '${AppConstants.appName} ${AppConstants.appVersion}',
                        style: const TextStyle(
                          fontSize: 12,
                          color: _settingsMuted,
                          height: 1.4,
                        ),
                      ),
                      accent: _settingsAccentPrimary,
                    ),
                    if (user.isSiswa) ...[
                      const SizedBox(height: 12),
                      _SettingsActionTile(
                        icon: Icons.alarm_on_outlined,
                        title: 'Pengingat Masuk/Pulang',
                        subtitle: Platform.isAndroid
                            ? 'Atur notifikasi 10 menit sebelum jam masuk/pulang'
                            : 'Hanya tersedia di Android',
                        accent: _settingsAccentPrimary,
                        onTap: Platform.isAndroid
                            ? () {
                                Navigator.of(context).push(
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        const AttendanceReminderSettingsScreen(),
                                  ),
                                );
                              }
                            : null,
                      ),
                      const SizedBox(height: 12),
                      _SettingsActionTile(
                        icon: Icons.face_retouching_natural_outlined,
                        title: 'Template Wajah',
                        subtitle: 'Kelola self submit template wajah siswa',
                        accent: _settingsAccentSecondary,
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const FaceTemplateScreen(),
                            ),
                          );
                        },
                      ),
                    ],
                  ],
                ),
              ),
            ],
            const SizedBox(height: 16),
            _SettingsPanel(
              icon: Icons.phone_android_rounded,
              title: 'Aplikasi',
              subtitle: 'Informasi build yang sedang digunakan saat ini',
              child: _SettingsActionTile(
                icon: Icons.info_outline_rounded,
                title: 'Versi Aplikasi',
                subtitle: '',
                subtitleWidget: AppVersionText(
                  includeAppName: true,
                  fallback:
                      '${AppConstants.appName} ${AppConstants.appVersion}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: _settingsMuted,
                    height: 1.4,
                  ),
                ),
                accent: _settingsAccentPrimary,
              ),
            ),
            const SizedBox(height: 16),
            _SettingsPanel(
              icon: Icons.logout_rounded,
              title: 'Sesi Akun',
              subtitle: 'Keluar dari perangkat ini bila diperlukan',
              child: FilledButton.icon(
                onPressed: () => _showLogoutDialog(context),
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFFB4232C),
                  foregroundColor: Colors.white,
                  minimumSize: const Size.fromHeight(52),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(18),
                  ),
                ),
                icon: const Icon(Icons.logout),
                label: const Text(
                  'Keluar',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showLogoutDialog(BuildContext context) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text('Konfirmasi Keluar'),
          content: const Text('Apakah Anda yakin ingin keluar dari aplikasi?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () async {
                Navigator.of(dialogContext).pop();
                final authProvider =
                    Provider.of<AuthProvider>(context, listen: false);
                await authProvider.logout();
              },
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFFB4232C),
              ),
              child: const Text('Keluar'),
            ),
          ],
        );
      },
    );
  }
}

class _SettingsHeaderCard extends StatelessWidget {
  final String title;
  final String subtitle;

  const _SettingsHeaderCard({
    required this.title,
    required this.subtitle,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [_settingsAccentPrimary, _settingsAccentSecondary],
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
              Icons.settings_rounded,
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

class _SettingsPanel extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Widget child;

  const _SettingsPanel({
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
        border: Border.all(color: _settingsBorder),
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
                  color: _settingsAccentPrimary,
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
                        color: _settingsInk,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: _settingsMuted,
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

class _SettingsActionTile extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Widget? subtitleWidget;
  final Color accent;
  final VoidCallback? onTap;
  final Widget? trailingOverride;

  const _SettingsActionTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.subtitleWidget,
    required this.accent,
    this.onTap,
    this.trailingOverride,
  });

  @override
  Widget build(BuildContext context) {
    final trailing = trailingOverride ??
        (onTap != null
            ? const Icon(
                Icons.arrow_forward_ios_rounded,
                size: 16,
                color: Color(0xFF7B8EA8),
              )
            : null);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Ink(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: _settingsTileFill,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: _settingsTileBorder),
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
                        color: _settingsInk,
                      ),
                    ),
                    const SizedBox(height: 4),
                    if (subtitleWidget != null)
                      subtitleWidget!
                    else
                      Text(
                        subtitle,
                        style: const TextStyle(
                          fontSize: 12,
                          color: _settingsMuted,
                          height: 1.4,
                        ),
                      ),
                  ],
                ),
              ),
              if (trailing != null) ...[
                const SizedBox(width: 12),
                trailing,
              ],
            ],
          ),
        ),
      ),
    );
  }
}
