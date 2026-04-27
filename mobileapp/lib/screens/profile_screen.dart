import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/user.dart';
import '../providers/auth_provider.dart';
import '../utils/constants.dart';
import 'personal_data_screen.dart';
import 'schedule_overview_screen.dart';
import 'student_leave_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }

      final authProvider = context.read<AuthProvider>();
      if (authProvider.isAuthenticated) {
        authProvider.refreshProfile();
      }
    });
  }

  String _label(String? value) {
    final text = (value ?? '').trim();
    return text.isEmpty ? '-' : text;
  }

  String _formatDate(DateTime value) {
    final local = value.toLocal();
    final day = local.day.toString().padLeft(2, '0');
    final month = local.month.toString().padLeft(2, '0');
    return '$day/$month/${local.year}';
  }

  String _formatRole(User? user) {
    final raw = (user?.role ??
            (user?.roles.isNotEmpty == true ? user!.roles.first.name : '-'))
        .toString();
    if (raw.trim().isEmpty) {
      return '-';
    }

    return raw.replaceAll('_', ' ');
  }

  String _attendanceMethodsLabel(User? user) {
    final explicit = (user?.attendanceMethodsLabel ?? '').trim();
    if (explicit.isNotEmpty) {
      return explicit;
    }

    final methods = user?.attendanceMethods ?? const <String>[];
    if (methods.isEmpty) {
      return '-';
    }

    return methods.map((method) {
      switch (method.trim().toLowerCase()) {
        case 'selfie':
          return 'Selfie';
        case 'qr_code':
          return 'QR Code';
        case 'manual':
          return 'Manual';
        case 'face_recognition':
          return 'Face Recognition';
        default:
          return method.replaceAll('_', ' ');
      }
    }).join(', ');
  }

  Future<void> _refreshProfile(BuildContext context) async {
    await context.read<AuthProvider>().refreshProfile();
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    final user = authProvider.user;
    final isSiswa = user?.isSiswa ?? false;
    final bottomNavSpace = 120.0 + MediaQuery.viewPaddingOf(context).bottom;
    final kelasAktif = authProvider.userKelasNama.trim().isNotEmpty
        ? authProvider.userKelasNama
        : (user?.kelasNama ?? '-');
    final templateSummary = !(user?.hasActiveFaceTemplate ?? false)
        ? 'Belum tersedia'
        : user?.faceTemplateEnrolledAt == null
            ? 'Aktif'
            : 'Aktif sejak ${_formatDate(user!.faceTemplateEnrolledAt!)}';

    return Scaffold(
      backgroundColor: const Color(0xFFF4F8FC),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFF4F8FC), Color(0xFFEAF2FF)],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: RefreshIndicator(
          color: const Color(0xFF0C4A7A),
          onRefresh: () => _refreshProfile(context),
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: EdgeInsets.fromLTRB(16, 16, 16, bottomNavSpace),
            children: [
              _ProfileHeroCard(
                name: user?.displayName ?? 'Pengguna',
                subtitle: user?.email ?? user?.username ?? '-',
                roleLabel: _formatRole(user),
                statusLabel: user?.isActive == true ? 'Aktif' : 'Tidak aktif',
                photoUrl: user?.fotoProfil,
              ),
              const SizedBox(height: 18),
              if (isSiswa)
                _WaliKelasShowcaseCard(
                  kelasLabel: _label(kelasAktif),
                  waliKelasNama: _label(user?.waliKelasNama),
                  waliKelasNip: _label(user?.waliKelasNip),
                ),
              if (isSiswa) const SizedBox(height: 18),
              _ProfilePanel(
                icon: Icons.badge_rounded,
                title: 'Identitas',
                subtitle: isSiswa
                    ? 'Ringkasan identitas utama siswa'
                    : 'Ringkasan identitas utama akun',
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final wide = constraints.maxWidth >= 520;
                    final tileWidth = wide
                        ? (constraints.maxWidth - 12) / 2
                        : constraints.maxWidth;

                    final tiles = <Widget>[
                      if (isSiswa)
                        _ProfileInfoTile(
                          width: tileWidth,
                          icon: Icons.perm_identity_rounded,
                          label: 'NISN',
                          value: _label(user?.nisn),
                          accent: const Color(0xFF2A6FDB),
                        ),
                      if (isSiswa)
                        _ProfileInfoTile(
                          width: tileWidth,
                          icon: Icons.badge_outlined,
                          label: 'NIS',
                          value: _label(user?.nis),
                          accent: const Color(0xFF00A3A3),
                        ),
                      if (!isSiswa)
                        _ProfileInfoTile(
                          width: tileWidth,
                          icon: Icons.alternate_email_rounded,
                          label: 'Username',
                          value: _label(user?.username),
                          accent: const Color(0xFF2A6FDB),
                        ),
                      if (!isSiswa)
                        _ProfileInfoTile(
                          width: tileWidth,
                          icon: Icons.credit_card_rounded,
                          label: 'Identitas',
                          value: _label(user?.nip ?? user?.nik ?? user?.email),
                          accent: const Color(0xFF00A3A3),
                        ),
                      _ProfileInfoTile(
                        width: tileWidth,
                        icon: Icons.workspace_premium_rounded,
                        label: 'Role Utama',
                        value: _formatRole(user),
                        accent: const Color(0xFF7A56F0),
                      ),
                      _ProfileInfoTile(
                        width: tileWidth,
                        icon: Icons.verified_user_rounded,
                        label: 'Status',
                        value: user?.isActive == true ? 'Aktif' : 'Tidak aktif',
                        accent: const Color(0xFF138A5B),
                      ),
                      _ProfileInfoTile(
                        width: tileWidth,
                        icon: Icons.face_retouching_natural_rounded,
                        label: 'Template Wajah',
                        value: templateSummary,
                        accent: const Color(0xFFE07A12),
                      ),
                    ];

                    return Wrap(
                      spacing: 12,
                      runSpacing: 12,
                      children: tiles,
                    );
                  },
                ),
              ),
              const SizedBox(height: 18),
              _ProfilePanel(
                icon: Icons.tune_rounded,
                title: 'Konfigurasi Presensi',
                subtitle: 'Skema presensi dan lokasi aktif pengguna',
                child: Column(
                  children: [
                    _ProfileInfoTile(
                      width: double.infinity,
                      icon: Icons.fact_check_rounded,
                      label: 'Skema Absensi',
                      value: _attendanceMethodsLabel(user),
                      accent: const Color(0xFF0C4A7A),
                      valueMaxLines: 2,
                    ),
                    const SizedBox(height: 12),
                    _ProfileInfoTile(
                      width: double.infinity,
                      icon: Icons.place_rounded,
                      label: 'Lokasi Presensi',
                      value: _label(user?.attendanceLocationLabel),
                      accent: const Color(0xFFD95F43),
                      valueMaxLines: 2,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              _ProfilePanel(
                icon: Icons.flash_on_rounded,
                title: 'Pintasan',
                subtitle: 'Akses cepat ke menu yang sering dipakai',
                child: Column(
                  children: [
                    if (!(user?.isSuperAdmin ?? false))
                      _ProfileShortcut(
                        icon: Icons.badge_outlined,
                        title: 'Data Pribadi',
                        subtitle: 'Buka detail data diri sesuai backend',
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const PersonalDataScreen(),
                            ),
                          );
                        },
                      ),
                    if (user?.canViewScheduleOnMobile ?? false)
                      _ProfileShortcut(
                        icon: Icons.event_note_outlined,
                        title: 'Jadwal Saya',
                        subtitle: 'Buka agenda aktif sesuai role',
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const ScheduleOverviewScreen(),
                            ),
                          );
                        },
                      ),
                    if (user?.isSiswa ?? false)
                      _ProfileShortcut(
                        icon: Icons.assignment_outlined,
                        title: 'Izin Saya',
                        subtitle: 'Pantau pengajuan izin siswa',
                        onTap: () {
                          Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const StudentLeaveScreen(),
                            ),
                          );
                        },
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ProfileHeroCard extends StatelessWidget {
  final String name;
  final String subtitle;
  final String roleLabel;
  final String statusLabel;
  final String? photoUrl;

  const _ProfileHeroCard({
    required this.name,
    required this.subtitle,
    required this.roleLabel,
    required this.statusLabel,
    required this.photoUrl,
  });

  @override
  Widget build(BuildContext context) {
    final hasPhoto = (photoUrl ?? '').trim().isNotEmpty;

    return Container(
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0B395E), Color(0xFF1E7BC8), Color(0xFF6FC6FF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(28),
        boxShadow: const [
          BoxShadow(
            color: Color(0x220B395E),
            blurRadius: 24,
            offset: Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 34,
                backgroundColor: Colors.white.withValues(alpha: 0.20),
                backgroundImage:
                    hasPhoto ? NetworkImage(photoUrl!.trim()) : null,
                child: !hasPhoto
                    ? const Icon(
                        Icons.person_rounded,
                        size: 34,
                        color: Colors.white,
                      )
                    : null,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      subtitle,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.84),
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              _HeroBadge(
                icon: Icons.workspace_premium_rounded,
                label: roleLabel,
              ),
              _HeroBadge(
                icon: Icons.verified_rounded,
                label: statusLabel,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _HeroBadge extends StatelessWidget {
  final IconData icon;
  final String label;

  const _HeroBadge({
    required this.icon,
    required this.label,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 9),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: Colors.white),
          const SizedBox(width: 8),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _WaliKelasShowcaseCard extends StatelessWidget {
  final String kelasLabel;
  final String waliKelasNama;
  final String waliKelasNip;

  const _WaliKelasShowcaseCard({
    required this.kelasLabel,
    required this.waliKelasNama,
    required this.waliKelasNip,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFFF6FBFF), Color(0xFFE8F3FF)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: const Color(0xFFD3E5FB)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x140B395E),
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
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: const Color(0xFF0C4A7A),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(
                  Icons.school_rounded,
                  color: Colors.white,
                  size: 24,
                ),
              ),
              const SizedBox(width: 14),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Data Wali Kelas',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF123B67),
                      ),
                    ),
                    SizedBox(height: 4),
                    Text(
                      'Pendamping akademik kelas aktif',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF66758A),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            waliKelasNama,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 12,
            runSpacing: 12,
            children: [
              _ProfileMiniInfo(
                icon: Icons.badge_outlined,
                label: 'NIP',
                value: waliKelasNip,
                accent: const Color(0xFF2A6FDB),
              ),
              _ProfileMiniInfo(
                icon: Icons.groups_rounded,
                label: 'Kelas',
                value: kelasLabel,
                accent: const Color(0xFF00A3A3),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ProfilePanel extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Widget child;

  const _ProfilePanel({
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
        border: Border.all(color: const Color(0xFFD8E6F8)),
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
                  gradient: const LinearGradient(
                    colors: [Color(0xFF0C4A7A), Color(0xFF4DA8FF)],
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
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
                        color: Color(0xFF123B67),
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF66758A),
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

class _ProfileInfoTile extends StatelessWidget {
  final double width;
  final IconData icon;
  final String label;
  final String value;
  final Color accent;
  final int valueMaxLines;

  const _ProfileInfoTile({
    required this.width,
    required this.icon,
    required this.label,
    required this.value,
    required this.accent,
    this.valueMaxLines = 3,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      width: width,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: accent.withValues(alpha: 0.16)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: BoxDecoration(
              color: accent,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, size: 20, color: Colors.white),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF66758A),
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  value,
                  maxLines: valueMaxLines,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF123B67),
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

class _ProfileMiniInfo extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color accent;

  const _ProfileMiniInfo({
    required this.icon,
    required this.label,
    required this.value,
    required this.accent,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.74),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: accent.withValues(alpha: 0.18)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(icon, size: 17, color: accent),
          ),
          const SizedBox(width: 10),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF66758A),
                ),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF123B67),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ProfileShortcut extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  const _ProfileShortcut({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: onTap,
          child: Ink(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: const Color(0xFFF7FAFF),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: const Color(0xFFE1ECFA)),
            ),
            child: Row(
              children: [
                Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: Icon(icon, color: AppColors.primary),
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
                          color: Color(0xFF123B67),
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF66758A),
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(
                  Icons.arrow_forward_ios_rounded,
                  size: 16,
                  color: Color(0xFF7B8EA8),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
