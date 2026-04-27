import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../services/manual_attendance_service.dart';
import '../widgets/access_denied_scaffold.dart';
import 'manual_attendance_correction_screen.dart';
import 'manual_attendance_editor_screen.dart';
import 'manual_attendance_pending_checkout_screen.dart';

String _formatSummaryTimestamp(DateTime? value) {
  if (value == null) {
    return '-';
  }

  final local = value.toLocal();
  final hour = local.hour.toString().padLeft(2, '0');
  final minute = local.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}

class ManualAttendanceManagementScreen extends StatefulWidget {
  const ManualAttendanceManagementScreen({super.key});

  @override
  State<ManualAttendanceManagementScreen> createState() =>
      _ManualAttendanceManagementScreenState();
}

class _ManualAttendanceManagementScreenState
    extends State<ManualAttendanceManagementScreen> {
  final ManualAttendanceService _service = ManualAttendanceService();

  bool _hasAccess = false;
  bool _isLoadingSummary = true;
  String? _summaryError;
  ManualAttendanceMobileSummary? _summary;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();

    final hasAccess =
        context.read<AuthProvider>().user?.canManageManualAttendance ?? false;
    if (_hasAccess == hasAccess && (_summary != null || !_isLoadingSummary)) {
      return;
    }

    _hasAccess = hasAccess;
    if (_hasAccess) {
      _loadSummary();
    } else {
      _isLoadingSummary = false;
      _summary = null;
      _summaryError = null;
    }
  }

  Future<void> _loadSummary() async {
    setState(() {
      _isLoadingSummary = true;
      _summaryError = null;
    });

    final response = await _service.getMobileSummary();
    if (!mounted) {
      return;
    }

    setState(() {
      _isLoadingSummary = false;
      if (response.success) {
        _summary = response.data;
        _summaryError = null;
      } else {
        _summary = null;
        _summaryError = response.message;
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final hasAccess = user?.canManageManualAttendance ?? false;

    if (!hasAccess) {
      return const AccessDeniedScaffold(
        title: 'Pengelolaan Absensi',
        message:
            'Menu ini hanya tersedia untuk pengguna yang memiliki akses pengelolaan absensi.',
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Pengelolaan Absensi'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: RefreshIndicator(
        onRefresh: _loadSummary,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF0C4A7A), Color(0xFF2A6FDB)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(22),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Tindak lanjut absensi siswa',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Fokus mobile tetap ringan: buat absensi yang belum ada, koreksi data yang sudah tercatat, dan selesaikan lupa tap-out.',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: Colors.white.withValues(alpha: 0.88),
                          height: 1.45,
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _buildSummarySection(),
            const SizedBox(height: 16),
            _ActionCard(
              icon: Icons.timer_outlined,
              title: 'Lupa Tap-Out',
              subtitle:
                  'Tindak lanjuti siswa yang sudah check-in tetapi belum memiliki jam pulang.',
              color: const Color(0xFF0F766E),
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) =>
                        const ManualAttendancePendingCheckoutScreen(),
                  ),
                );
              },
            ),
            const SizedBox(height: 12),
            _ActionCard(
              icon: Icons.edit_note_outlined,
              title: 'Koreksi Absensi',
              subtitle:
                  'Cari data absensi yang sudah ada lalu koreksi status, jam masuk, atau jam pulangnya.',
              color: const Color(0xFF7C3AED),
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const ManualAttendanceCorrectionScreen(),
                  ),
                );
              },
            ),
            const SizedBox(height: 12),
            _ActionCard(
              icon: Icons.playlist_add_check_circle_outlined,
              title: 'Absensi Manual',
              subtitle:
                  'Buat absensi baru jika siswa belum memiliki data absensi sama sekali pada tanggal tersebut.',
              color: const Color(0xFF2563EB),
              onTap: () {
                Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const ManualAttendanceEditorScreen(),
                  ),
                );
              },
            ),
            const SizedBox(height: 16),
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFD8E6F8)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 36,
                    height: 36,
                    decoration: BoxDecoration(
                      color: const Color(0xFFFEF3C7),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(
                      Icons.desktop_windows_outlined,
                      color: Color(0xFFB45309),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Insiden Server tetap web-only',
                          style:
                              Theme.of(context).textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: const Color(0xFF123B67),
                                  ),
                        ),
                        const SizedBox(height: 4),
                        const Text(
                          'Batch massal ribuan siswa tetap dijalankan dari aplikasi web agar preview, audit, dan export operator tetap aman.',
                          style: TextStyle(
                            fontSize: 12,
                            height: 1.45,
                            color: Color(0xFF66758A),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildSummarySection() {
    if (_isLoadingSummary) {
      return Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: const Color(0xFFD8E6F8)),
        ),
        child: const Center(
          child: Padding(
            padding: EdgeInsets.symmetric(vertical: 24),
            child: CircularProgressIndicator(),
          ),
        ),
      );
    }

    if (_summaryError != null) {
      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: const Color(0xFFD8E6F8)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Ringkasan belum tersedia',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: Color(0xFF123B67),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              _summaryError!,
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFF66758A),
                height: 1.45,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _loadSummary,
              icon: const Icon(Icons.refresh_rounded),
              label: const Text('Muat ulang ringkasan'),
            ),
          ],
        ),
      );
    }

    final summary = _summary;
    if (summary == null) {
      return const SizedBox.shrink();
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Ringkasan operasional',
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: const Color(0xFF123B67),
                      ),
                ),
              ),
              Text(
                'Update ${_formatSummaryTimestamp(summary.generatedAt)}',
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF64748B),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          GridView.count(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisCount: 2,
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            childAspectRatio: 1.35,
            children: [
              _SummaryTile(
                label: 'Scope Siswa',
                value: '${summary.manageableStudentsCount}',
                color: const Color(0xFF2563EB),
                hint: 'Siswa yang bisa Anda kelola',
              ),
              _SummaryTile(
                label: 'Lupa Tap-Out',
                value: '${summary.pendingCheckoutHPlusOneCount}',
                color: const Color(0xFF0F766E),
                hint: 'Backlog H+1 yang siap ditindaklanjuti',
              ),
              _SummaryTile(
                label: 'Koreksi Hari Ini',
                value: '${summary.correctionTodayCount}',
                color: const Color(0xFF7C3AED),
                hint: 'Data existing yang bisa dikoreksi',
              ),
              _SummaryTile(
                label: 'Manual Hari Ini',
                value: '${summary.manualTodayCount}',
                color: const Color(0xFFD97706),
                hint: 'Data manual yang sudah tercatat',
              ),
            ],
          ),
          if (summary.canOverrideBackdate) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
              decoration: BoxDecoration(
                color: const Color(0xFFEEF2FF),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Row(
                children: [
                  const Icon(Icons.shield_outlined,
                      color: Color(0xFF4338CA), size: 18),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'Override H+N aktif. Backlog di atas H+1 saat ini: ${summary.pendingCheckoutOverdueCount}',
                      style: const TextStyle(
                        fontSize: 12,
                        height: 1.4,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF3730A3),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _SummaryTile extends StatelessWidget {
  final String label;
  final String value;
  final String hint;
  final Color color;

  const _SummaryTile({
    required this.label,
    required this.value,
    required this.hint,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            value,
            style: TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.w900,
              color: color,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w800,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 2),
          Text(
            hint,
            style: const TextStyle(
              fontSize: 11,
              height: 1.35,
              color: Color(0xFF66758A),
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  const _ActionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        borderRadius: BorderRadius.circular(20),
        onTap: onTap,
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(20),
            border: Border.all(color: const Color(0xFFD8E6F8)),
          ),
          child: Row(
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(icon, color: color, size: 24),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF123B67),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 12,
                        height: 1.45,
                        color: Color(0xFF66758A),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              const Icon(
                Icons.chevron_right_rounded,
                color: Color(0xFF94A3B8),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
