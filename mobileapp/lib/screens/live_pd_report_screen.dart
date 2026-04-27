import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../services/live_pd_report_service.dart';
import '../widgets/access_denied_scaffold.dart';

const Color _livePdBackgroundStart = Color(0xFFF4F8FC);
const Color _livePdBackgroundEnd = Color(0xFFEAF2FF);
const Color _livePdInk = Color(0xFF123B67);
const Color _livePdMuted = Color(0xFF66758A);
const Color _livePdBorder = Color(0xFFD8E6F8);
const Color _livePdTileBorder = Color(0xFFE1ECFA);
const Color _livePdTileFill = Color(0xFFFFFFFF);
const Color _livePdAccentPrimary = Color(0xFF0C4A7A);
const Color _livePdPresent = Color(0xFF15803D);
const Color _livePdLate = Color(0xFFB54708);
const Color _livePdNotCheckedIn = Color(0xFFB42318);
const Color _livePdAlpha = Color(0xFF912018);
const Color _livePdPermission = Color(0xFF0F766E);
const Color _livePdSick = Color(0xFF667085);
const Color _livePdCheckoutPending = Color(0xFF175CD3);

class LivePdReportScreen extends StatefulWidget {
  const LivePdReportScreen({super.key});

  @override
  State<LivePdReportScreen> createState() => _LivePdReportScreenState();
}

class _LivePdReportScreenState extends State<LivePdReportScreen> {
  final LivePdReportService _service = LivePdReportService();

  bool _hasAccess = false;
  Timer? _autoRefreshTimer;
  bool _isLoading = true;
  String? _errorMessage;
  LivePdReportData? _report;

  @override
  void initState() {
    super.initState();
    _hasAccess = context.read<AuthProvider>().user?.isSiswa ?? false;
    if (_hasAccess) {
      _loadReport();
      _autoRefreshTimer = Timer.periodic(
        const Duration(seconds: 45),
        (_) => _loadReport(silent: true),
      );
    }
  }

  @override
  void dispose() {
    _autoRefreshTimer?.cancel();
    super.dispose();
  }

  Future<void> _loadReport({bool silent = false}) async {
    if (!silent && mounted) {
      setState(() {
        _isLoading = true;
        _errorMessage = null;
      });
    }

    final response = await _service.getTodayReport();
    if (!mounted) {
      return;
    }

    setState(() {
      _report = response.data ?? _report;
      _errorMessage = response.success ? null : response.message;
      _isLoading = false;
    });
  }

  Future<void> _refresh() => _loadReport();

  String _formatDate(String rawDate) {
    final parsed = DateTime.tryParse(rawDate);
    if (parsed == null) {
      return rawDate;
    }

    const months = <String>[
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

    return '${parsed.day.toString().padLeft(2, '0')} ${months[parsed.month - 1]} ${parsed.year}';
  }

  Color _statusAccent(LivePdReportItem item) {
    switch (item.indicatorKey) {
      case 'belum_absen':
        return _livePdNotCheckedIn;
      case 'alpha':
        return _livePdAlpha;
      case 'sakit':
        return _livePdSick;
      case 'izin':
        return _livePdPermission;
      case 'terlambat':
        return _livePdLate;
      case 'belum_pulang':
        return _livePdCheckoutPending;
      default:
        return _livePdPresent;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess) {
      return const AccessDeniedScaffold(
        title: 'Live Laporan PD',
        message: 'Laporan PD live hanya tersedia untuk akun siswa.',
      );
    }

    final report = _report;

    return Scaffold(
      backgroundColor: _livePdBackgroundStart,
      appBar: AppBar(
        title: const Text(
          'Live Laporan PD',
          style: TextStyle(
            fontSize: 17,
            fontWeight: FontWeight.w800,
            color: _livePdInk,
          ),
        ),
        backgroundColor: Colors.transparent,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        iconTheme: const IconThemeData(color: _livePdInk),
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [_livePdBackgroundStart, _livePdBackgroundEnd],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: _isLoading && report == null
            ? const Center(
                child: CircularProgressIndicator(color: _livePdAccentPrimary),
              )
            : RefreshIndicator(
                color: _livePdAccentPrimary,
                onRefresh: _refresh,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
                  children: [
                    _LivePdPanel(
                      icon: Icons.groups_rounded,
                      title: 'Status Kelas Hari Ini',
                      subtitle: report?.className?.trim().isNotEmpty == true
                          ? '${report!.className} | ${_formatDate(report.date)}'
                          : 'Kelas aktif belum tersedia',
                      child: Column(
                        children: [
                          if (_errorMessage != null)
                            Container(
                              width: double.infinity,
                              margin: const EdgeInsets.only(bottom: 12),
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: const Color(0xFFFFF5F5),
                                borderRadius: BorderRadius.circular(16),
                                border:
                                    Border.all(color: const Color(0xFFF3D3D3)),
                              ),
                              child: Text(
                                _errorMessage!,
                                style: const TextStyle(
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                  color: Color(0xFF8A3B3B),
                                ),
                              ),
                            ),
                          LayoutBuilder(
                            builder: (context, constraints) {
                              const spacing = 10.0;
                              final summaryCards = <Widget>[
                                _SummaryCard(
                                  label: 'Hadir',
                                  value: '${report?.summary.hadir ?? 0}',
                                ),
                                _SummaryCard(
                                  label: 'Terlambat',
                                  value: '${report?.summary.terlambat ?? 0}',
                                ),
                                _SummaryCard(
                                  label: 'Izin',
                                  value: '${report?.summary.izin ?? 0}',
                                ),
                                _SummaryCard(
                                  label: 'Sakit',
                                  value: '${report?.summary.sakit ?? 0}',
                                ),
                                _SummaryCard(
                                  label: 'Alpha',
                                  value: '${report?.summary.alpha ?? 0}',
                                ),
                                _SummaryCard(
                                  label: 'Belum Absen',
                                  value: '${report?.summary.belumAbsen ?? 0}',
                                ),
                              ];
                              const columns = 3;
                              final cardWidth =
                                  (constraints.maxWidth - ((columns - 1) * spacing)) /
                                      columns;

                              return Wrap(
                                spacing: spacing,
                                runSpacing: spacing,
                                children: summaryCards
                                    .map((card) => SizedBox(
                                          width: cardWidth,
                                          child: card,
                                        ))
                                    .toList(),
                              );
                            },
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    _LivePdPanel(
                      icon: Icons.fact_check_rounded,
                      title: 'Daftar Absensi Kelas',
                      subtitle:
                          'Total Siswa = ${report?.summary.totalStudents ?? 0} | L = ${report?.summary.maleStudents ?? 0} | P = ${report?.summary.femaleStudents ?? 0}',
                      child: report == null || report.items.isEmpty
                          ? Container(
                              width: double.infinity,
                              padding: const EdgeInsets.all(18),
                              decoration: BoxDecoration(
                                color: _livePdTileFill,
                                borderRadius: BorderRadius.circular(18),
                                border:
                                    Border.all(color: _livePdTileBorder),
                              ),
                              child: const Text(
                                'Belum ada data absensi kelas untuk ditampilkan.',
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: _livePdMuted,
                                ),
                              ),
                            )
                          : Column(
                              children: [
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: const [
                                    _LegendChip(
                                      label: 'Hadir',
                                      color: _livePdPresent,
                                    ),
                                    _LegendChip(
                                      label: 'Terlambat',
                                      color: _livePdLate,
                                    ),
                                    _LegendChip(
                                      label: 'Belum Pulang',
                                      color: _livePdCheckoutPending,
                                    ),
                                    _LegendChip(
                                      label: 'Belum Absen',
                                      color: _livePdNotCheckedIn,
                                    ),
                                    _LegendChip(
                                      label: 'Izin',
                                      color: _livePdPermission,
                                    ),
                                    _LegendChip(
                                      label: 'Sakit',
                                      color: _livePdSick,
                                    ),
                                    _LegendChip(
                                      label: 'Alpha',
                                      color: _livePdAlpha,
                                    ),
                                  ],
                                ),
                                const SizedBox(height: 12),
                                for (var index = 0;
                                    index < report.items.length;
                                    index++) ...[
                                  if (index > 0)
                                    const SizedBox(height: 12),
                                  _ClassmateAttendanceTile(
                                    item: report.items[index],
                                    accent: _statusAccent(report.items[index]),
                                  ),
                                ],
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

class _LivePdPanel extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final Widget child;

  const _LivePdPanel({
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
        border: Border.all(color: _livePdBorder),
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
                  color: _livePdAccentPrimary,
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
                        color: _livePdInk,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: _livePdMuted,
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

class _SummaryCard extends StatelessWidget {
  final String label;
  final String value;

  const _SummaryCard({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 86),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: _livePdTileFill,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _livePdTileBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: _livePdMuted,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            value,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: _livePdInk,
            ),
          ),
        ],
      ),
    );
  }
}

class _ClassmateAttendanceTile extends StatelessWidget {
  final LivePdReportItem item;
  final Color accent;

  const _ClassmateAttendanceTile({
    required this.item,
    required this.accent,
  });

  String _roleAndNisText() {
    final role = (item.roleLabel ?? 'Siswa').trim();
    final nis = (item.nis ?? '').trim();
    final resolvedRole = role.isEmpty ? 'Siswa' : role;
    final resolvedNis = nis.isEmpty ? '-' : nis;
    return '$resolvedRole | NIS $resolvedNis';
  }

  String _timeValue(String? value) {
    final trimmed = (value ?? '').trim();
    return trimmed.isEmpty ? '--:--' : trimmed;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 102),
      decoration: BoxDecoration(
        color: _livePdTileFill,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _livePdTileBorder),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(10, 14, 8, 14),
            child: SizedBox(
              width: 6,
              height: 60,
              child: DecoratedBox(
                decoration: BoxDecoration(
                  color: accent,
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
            ),
          ),
          Expanded(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(0, 10, 12, 10),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.center,
                children: [
                  _StudentPhotoAvatar(
                    photoUrl: item.userPhotoUrl,
                    accent: accent,
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Text(
                          item.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 13.5,
                            fontWeight: FontWeight.w800,
                            color: _livePdInk,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          _roleAndNisText(),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontSize: 11.5,
                            fontWeight: FontWeight.w600,
                            color: _livePdMuted,
                          ),
                        ),
                        const SizedBox(height: 8),
                        Row(
                          children: [
                            Expanded(
                              child: _AttendanceTimeCell(
                                label: 'Masuk',
                                value: _timeValue(item.checkInTime),
                              ),
                            ),
                            const SizedBox(width: 8),
                            Expanded(
                              child: _AttendanceTimeCell(
                                label: 'Pulang',
                                value: _timeValue(item.checkOutTime),
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _AttendanceTimeCell extends StatelessWidget {
  final String label;
  final String value;

  const _AttendanceTimeCell({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 34,
      padding: const EdgeInsets.symmetric(horizontal: 9),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FBFF),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: _livePdTileBorder),
      ),
      child: Row(
        children: [
          Icon(
            Icons.schedule_rounded,
            size: 14,
            color: _livePdAccentPrimary,
          ),
          const SizedBox(width: 6),
          Expanded(
            child: Text(
              value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                fontSize: 11.5,
                fontWeight: FontWeight.w800,
                color: _livePdInk,
              ),
            ),
          ),
          const SizedBox(width: 6),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: _livePdMuted,
            ),
          ),
        ],
      ),
    );
  }
}

class _LegendChip extends StatelessWidget {
  final String label;
  final Color color;

  const _LegendChip({
    required this.label,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: _livePdTileBorder),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 8,
            height: 8,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(999),
            ),
          ),
          const SizedBox(width: 7),
          Text(
            label,
            style: const TextStyle(
              fontSize: 10.5,
              fontWeight: FontWeight.w700,
              color: _livePdInk,
            ),
          ),
        ],
      ),
    );
  }
}

class _StudentPhotoAvatar extends StatelessWidget {
  final String? photoUrl;
  final Color accent;

  const _StudentPhotoAvatar({
    required this.photoUrl,
    required this.accent,
  });

  @override
  Widget build(BuildContext context) {
    final resolvedUrl = (photoUrl ?? '').trim();

    return Container(
      width: 48,
      height: 48,
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(14),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(14),
        child: resolvedUrl.isEmpty
            ? Icon(
                Icons.person_rounded,
                color: accent,
                size: 24,
              )
            : Image.network(
                resolvedUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => Icon(
                  Icons.person_rounded,
                  color: accent,
                  size: 24,
                ),
              ),
      ),
    );
  }
}


