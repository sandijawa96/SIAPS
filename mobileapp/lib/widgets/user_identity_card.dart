import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../utils/constants.dart';

class UserIdentityCard extends StatelessWidget {
  final dynamic user;

  const UserIdentityCard({Key? key, required this.user}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final primaryColor = Color(AppColors.primaryColorValue);
    final accentColor = Color(AppColors.accentColorValue);
    final roleInfo = _getUserRoleInfo(context, user);
    final roleLabel = _getPrimaryRoleLabel(user);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            const Color(0xFF0C4A7A),
            primaryColor,
            accentColor,
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: primaryColor.withOpacity(0.35),
            blurRadius: 24,
            offset: const Offset(0, 12),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _buildPill(icon: Icons.school_outlined, text: roleLabel),
                    if (roleInfo.trim().isNotEmpty)
                      _buildPill(icon: Icons.badge_outlined, text: roleInfo),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  user?.namaLengkap ?? user?.displayName ?? 'User',
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                    height: 1.05,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 6),
                Text(
                  user?.identifier ?? '',
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.78),
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 18),
                Text(
                  _buildDateLabel(),
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.84),
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          Container(
            width: 84,
            height: 84,
            margin: const EdgeInsets.only(left: 12),
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              border:
                  Border.all(color: Colors.white.withOpacity(0.92), width: 3),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.18),
                  blurRadius: 12,
                  offset: const Offset(0, 6),
                ),
              ],
            ),
            child: ClipOval(
              child: user?.fotoProfil != null && user!.fotoProfil!.isNotEmpty
                  ? Image.network(
                      user.fotoProfil!,
                      width: 84,
                      height: 84,
                      fit: BoxFit.cover,
                      errorBuilder: (context, error, stackTrace) {
                        return _buildDefaultAvatar(user);
                      },
                    )
                  : _buildDefaultAvatar(user),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPill({required IconData icon, required String text}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.18),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withOpacity(0.30), width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 12, color: Colors.white),
          const SizedBox(width: 4),
          Text(
            text,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }

  String _buildDateLabel() {
    final now = DateTime.now();
    const months = [
      'Jan',
      'Feb',
      'Mar',
      'Apr',
      'Mei',
      'Jun',
      'Jul',
      'Agu',
      'Sep',
      'Okt',
      'Nov',
      'Des',
    ];

    final day = now.day.toString().padLeft(2, '0');
    final month = months[now.month - 1];
    return '$day $month ${now.year}';
  }

  String _getPrimaryRoleLabel(dynamic user) {
    if (user == null) return 'Pengguna';

    final roles = user.roles as List<dynamic>?;
    if (roles != null && roles.isNotEmpty) {
      return roles.first.name?.toString() ?? 'Pengguna';
    }

    if (user.nis != null) return 'Siswa';
    if (user.nip != null) return 'Pegawai';
    return 'Pengguna';
  }

  String _getUserRoleInfo(BuildContext context, dynamic user) {
    if (user == null) return '';

    final authProvider = Provider.of<AuthProvider>(context, listen: false);

    final roles = user.roles as List<dynamic>?;
    if (roles != null && roles.isNotEmpty) {
      final roleName = roles.first.name?.toLowerCase() ?? '';
      if (roleName.contains('siswa')) {
        return authProvider.userKelasNama;
      }

      final mainRole = roles.first.name ?? 'Pegawai';
      final subRole = user.statusKepegawaian ?? '';
      if (subRole.isNotEmpty) {
        return '$mainRole - $subRole';
      }

      return mainRole;
    }

    if (user.nis != null) {
      return authProvider.userKelasNama;
    }

    if (user.nip != null) {
      return user.statusKepegawaian ?? 'Pegawai';
    }

    return '';
  }

  Widget _buildDefaultAvatar(dynamic user) {
    final roles = user?.roles as List<dynamic>?;
    IconData iconData = Icons.person;

    if (roles != null && roles.isNotEmpty) {
      final roleName = roles.first.name?.toLowerCase() ?? '';
      if (roleName.contains('siswa')) {
        iconData = Icons.school;
      } else {
        iconData = Icons.work;
      }
    } else if (user?.nis != null) {
      iconData = Icons.school;
    } else if (user?.nip != null) {
      iconData = Icons.work;
    }

    return Container(
      width: 84,
      height: 84,
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.24),
        shape: BoxShape.circle,
      ),
      child: Icon(iconData, size: 40, color: Colors.white),
    );
  }
}
