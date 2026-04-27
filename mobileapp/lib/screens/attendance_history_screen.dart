import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/attendance_service.dart';
import '../services/dashboard_service.dart';
import '../utils/constants.dart';
import '../widgets/access_denied_scaffold.dart';
import 'attendance_detail_screen.dart';

class AttendanceHistoryScreen extends StatefulWidget {
  const AttendanceHistoryScreen({super.key});

  @override
  State<AttendanceHistoryScreen> createState() => _AttendanceHistoryScreenState();
}

class _AttendanceHistoryScreenState extends State<AttendanceHistoryScreen> {
  final AttendanceService _attendanceService = AttendanceService();
  final DashboardService _dashboardService = DashboardService();

  bool _hasAccess = false;
  bool _isLoading = true;
  String? _errorMessage;
  AcademicContext? _academicContext;
  List<AttendanceRecord> _records = const <AttendanceRecord>[];
  AttendanceStatistics _stats = AttendanceStatistics(
    totalDays: 0,
    presentDays: 0,
    absentDays: 0,
    lateDays: 0,
    lateMinutes: 0,
    permissionDays: 0,
    attendancePercentage: 0,
  );
  DateTime _selectedPeriod = DateTime(DateTime.now().year, DateTime.now().month);

  @override
  void initState() {
    super.initState();
    _hasAccess = context.read<AuthProvider>().user?.isSiswa ?? false;
    if (_hasAccess) {
      _initializeAcademicContextAndHistory();
    }
  }

  Future<void> _initializeAcademicContextAndHistory() async {
    final contextResponse = await _dashboardService.getAcademicContext();
    if (mounted && contextResponse.success && contextResponse.data != null) {
      setState(() {
        _academicContext = contextResponse.data;
        _selectedPeriod = _clampPeriodToRange(_selectedPeriod);
      });
    }

    await _loadHistory();
  }

  Future<void> _loadHistory() async {
    final period = _clampPeriodToRange(
      DateTime(_selectedPeriod.year, _selectedPeriod.month),
    );
    final start = DateTime(period.year, period.month, 1);
    final end = DateTime(period.year, period.month + 1, 0);

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final historyResponse = await _attendanceService.getHistory(
      limit: 100,
      startDate: _toApiDate(start),
      endDate: _toApiDate(end),
      tahunAjaranId: _academicContext?.tahunAjaranId,
    );
    final statsResponse = await _attendanceService.getStatistics(
      month: period.month,
      year: period.year,
      tahunAjaranId: _academicContext?.tahunAjaranId,
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _selectedPeriod = period;
      _records = historyResponse.data ?? const <AttendanceRecord>[];
      _stats = statsResponse.data ??
          AttendanceStatistics(
            totalDays: 0,
            presentDays: 0,
            absentDays: 0,
            lateDays: 0,
            lateMinutes: 0,
            permissionDays: 0,
            attendancePercentage: 0,
          );
      _errorMessage = historyResponse.success ? null : historyResponse.message;
      _isLoading = false;
    });
  }

  Future<void> _pickPeriod() async {
    final picked = await showModalBottomSheet<DateTime>(
      context: context,
      showDragHandle: true,
      backgroundColor: Colors.white,
      builder: (_) => _MonthPickerSheet(
        initialPeriod: _selectedPeriod,
        minPeriod: _minSelectablePeriod,
        maxPeriod: _maxSelectablePeriod,
      ),
    );

    if (picked == null) {
      return;
    }

    final nextPeriod = _clampPeriodToRange(DateTime(picked.year, picked.month));
    if (nextPeriod.year == _selectedPeriod.year &&
        nextPeriod.month == _selectedPeriod.month) {
      return;
    }

    setState(() {
      _selectedPeriod = nextPeriod;
    });
    await _loadHistory();
  }

  Future<void> _shiftPeriod(int offset) async {
    final nextPeriod = _clampPeriodToRange(
      DateTime(_selectedPeriod.year, _selectedPeriod.month + offset),
    );
    if (nextPeriod.year == _selectedPeriod.year &&
        nextPeriod.month == _selectedPeriod.month) {
      return;
    }

    setState(() {
      _selectedPeriod = nextPeriod;
    });
    await _loadHistory();
  }

  DateTime? get _minSelectablePeriod {
    final start = _academicContext?.effectiveStartDate;
    if (start == null) {
      return null;
    }
    return DateTime(start.year, start.month);
  }

  DateTime get _maxSelectablePeriod {
    final nowMonth = DateTime(DateTime.now().year, DateTime.now().month);
    final end = _academicContext?.effectiveEndDate;
    if (end == null) {
      return nowMonth;
    }

    final contextMonth = DateTime(end.year, end.month);
    return contextMonth.isBefore(nowMonth) ? contextMonth : nowMonth;
  }

  DateTime _clampPeriodToRange(DateTime period) {
    final normalized = DateTime(period.year, period.month);
    final minPeriod = _minSelectablePeriod;
    var maxPeriod = _maxSelectablePeriod;

    if (minPeriod != null && minPeriod.isAfter(maxPeriod)) {
      maxPeriod = minPeriod;
    }

    if (minPeriod != null && normalized.isBefore(minPeriod)) {
      return minPeriod;
    }
    if (normalized.isAfter(maxPeriod)) {
      return maxPeriod;
    }

    return normalized;
  }

  String? get _academicScopeLabel {
    final compactLabel = (_academicContext?.compactLabel ?? '').trim();
    if (compactLabel.isEmpty || compactLabel == '-') {
      return null;
    }
    return 'Tahun ajaran aktif: $compactLabel';
  }

  String _toApiDate(DateTime value) {
    final month = value.month.toString().padLeft(2, '0');
    final day = value.day.toString().padLeft(2, '0');
    return '${value.year}-$month-$day';
  }

  String _formatPeriod(DateTime value) {
    const months = <String>[
      'Januari',
      'Februari',
      'Maret',
      'April',
      'Mei',
      'Juni',
      'Juli',
      'Agustus',
      'September',
      'Oktober',
      'November',
      'Desember',
    ];
    return '${months[value.month - 1]} ${value.year}';
  }

  String _formatDate(DateTime value) {
    const days = <String>[
      'Senin',
      'Selasa',
      'Rabu',
      'Kamis',
      'Jumat',
      'Sabtu',
      'Minggu',
    ];
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
    final dayName = days[value.weekday - 1];
    return '$dayName, ${value.day.toString().padLeft(2, '0')} ${months[value.month - 1]} ${value.year}';
  }

  Color _statusColor(AttendanceRecord record) {
    switch ((record.status ?? '').toLowerCase()) {
      case 'libur':
        return const Color(0xFF64748B);
      case 'terlambat':
        return const Color(0xFFF59E0B);
      case 'alpha':
      case 'alpa':
        return const Color(0xFFB4232C);
      case 'izin':
        return const Color(0xFF2563EB);
      case 'sakit':
        return const Color(0xFF7C3AED);
      default:
        return const Color(0xFF16A34A);
    }
  }

  IconData _statusIcon(AttendanceRecord record) {
    switch ((record.status ?? '').toLowerCase()) {
      case 'libur':
        return Icons.beach_access_rounded;
      case 'terlambat':
        return Icons.schedule_rounded;
      case 'alpha':
      case 'alpa':
        return Icons.cancel_rounded;
      case 'izin':
        return Icons.event_note_rounded;
      case 'sakit':
        return Icons.local_hospital_rounded;
      default:
        return Icons.check_circle_rounded;
    }
  }

  int get _presentCount => _stats.presentDays;

  bool get _canGoToNextPeriod {
    return _selectedPeriod.isBefore(_maxSelectablePeriod);
  }

  bool get _canGoToPreviousPeriod {
    final minPeriod = _minSelectablePeriod;
    if (minPeriod == null) {
      return true;
    }
    return _selectedPeriod.isAfter(minPeriod);
  }

  void _openDetail(AttendanceRecord record) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => AttendanceDetailScreen(
          attendanceId: record.id,
          initialRecord: record,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess) {
      return const AccessDeniedScaffold(
        title: 'Riwayat Presensi',
        message: 'Riwayat presensi mobile hanya tersedia untuk akun siswa.',
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Riwayat Presensi'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: RefreshIndicator(
        onRefresh: _initializeAcademicContextAndHistory,
        color: AppColors.primary,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _PeriodCard(
              periodLabel: _formatPeriod(_selectedPeriod),
              recordCount: _records.length,
              scopeLabel: _academicScopeLabel,
              onPickPeriod: _pickPeriod,
              onPrevious: _canGoToPreviousPeriod ? () => _shiftPeriod(-1) : null,
              onNext: _canGoToNextPeriod ? () => _shiftPeriod(1) : null,
            ),
            const SizedBox(height: 16),
            _SummaryCard(
              hadir: _presentCount,
              alpha: _stats.absentDays,
              terlambatMenit: _stats.lateMinutes,
            ),
            const SizedBox(height: 16),
            if (_isLoading)
              const _LoadingState()
            else if (_errorMessage != null)
              _ErrorState(message: _errorMessage!, onRetry: _loadHistory)
            else if (_records.isEmpty)
              _EmptyState(
                icon: Icons.history_toggle_off_outlined,
                title: 'Belum ada riwayat presensi',
                subtitle:
                    'Tidak ada catatan presensi pada periode ${_formatPeriod(_selectedPeriod)}.',
              )
            else
              ..._records.map(
                (record) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _AttendanceHistoryCard(
                    record: record,
                    dateLabel: _formatDate(record.attendanceDate ?? record.timestamp),
                    statusColor: _statusColor(record),
                    statusIcon: _statusIcon(record),
                    onTap: _isSyntheticInfoRow(record)
                        ? null
                        : () => _openDetail(record),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  bool _isSyntheticInfoRow(AttendanceRecord record) {
    final normalizedStatus = (record.status ?? '').toLowerCase();
    return normalizedStatus == 'libur' || record.id.startsWith('alpha-');
  }
}

class _PeriodCard extends StatelessWidget {
  final String periodLabel;
  final int recordCount;
  final String? scopeLabel;
  final VoidCallback onPickPeriod;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;

  const _PeriodCard({
    required this.periodLabel,
    required this.recordCount,
    this.scopeLabel,
    required this.onPickPeriod,
    required this.onPrevious,
    required this.onNext,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF0C4A7A), Color(0xFF64B5F6)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Filter Periode',
            style: TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          if ((scopeLabel ?? '').trim().isNotEmpty) ...[
            Text(
              scopeLabel!,
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.92),
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
            const SizedBox(height: 4),
          ],
          Text(
            '$recordCount catatan presensi pada periode ini',
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.84),
              fontSize: 12,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              _PeriodButton(
                icon: Icons.chevron_left_rounded,
                onTap: onPrevious,
                enabled: onPrevious != null,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: InkWell(
                  onTap: onPickPeriod,
                  borderRadius: BorderRadius.circular(16),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.14),
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
                    ),
                    child: Row(
                      children: [
                        const Icon(Icons.calendar_month_outlined, color: Colors.white),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            periodLabel,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 15,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        const Icon(Icons.keyboard_arrow_down_rounded, color: Colors.white),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 10),
              _PeriodButton(
                icon: Icons.chevron_right_rounded,
                onTap: onNext,
                enabled: onNext != null,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _PeriodButton extends StatelessWidget {
  final IconData icon;
  final VoidCallback? onTap;
  final bool enabled;

  const _PeriodButton({
    required this.icon,
    required this.onTap,
    this.enabled = true,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: enabled ? onTap : null,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          color: enabled
              ? Colors.white.withValues(alpha: 0.14)
              : Colors.white.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
        ),
        child: Icon(
          icon,
          color: enabled ? Colors.white : Colors.white.withValues(alpha: 0.5),
        ),
      ),
    );
  }
}

class _SummaryCard extends StatelessWidget {
  final int hadir;
  final int alpha;
  final int terlambatMenit;

  const _SummaryCard({
    required this.hadir,
    required this.alpha,
    required this.terlambatMenit,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFD8E6F8)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x0F0F4C81),
            blurRadius: 8,
            offset: Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: _SummaryItem(
              label: 'Hadir',
              value: hadir.toString(),
              color: const Color(0xFF16A34A),
              icon: Icons.check_circle_outline_rounded,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _SummaryItem(
              label: 'Alpa',
              value: alpha.toString(),
              color: const Color(0xFFB4232C),
              icon: Icons.cancel_outlined,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _SummaryItem(
              label: 'Terlambat',
              value: '$terlambatMenit mnt',
              color: const Color(0xFFF59E0B),
              icon: Icons.schedule_rounded,
            ),
          ),
        ],
      ),
    );
  }
}

class _SummaryItem extends StatelessWidget {
  final String label;
  final String value;
  final Color color;
  final IconData icon;

  const _SummaryItem({
    required this.label,
    required this.value,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 104,
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 8),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 28,
            height: 28,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Icon(icon, color: color, size: 16),
          ),
          const SizedBox(height: 8),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w700,
              color: Color(0xFF66758A),
            ),
          ),
        ],
      ),
    );
  }
}

class _AttendanceHistoryCard extends StatelessWidget {
  final AttendanceRecord record;
  final String dateLabel;
  final Color statusColor;
  final IconData statusIcon;
  final VoidCallback? onTap;

  const _AttendanceHistoryCard({
    required this.record,
    required this.dateLabel,
    required this.statusColor,
    required this.statusIcon,
    required this.onTap,
  });

  String _buildLocationSummary() {
    final locations = <String>[
      if ((record.lokasiMasukNama ?? '').trim().isNotEmpty) record.lokasiMasukNama!.trim(),
      if ((record.lokasiPulangNama ?? '').trim().isNotEmpty) record.lokasiPulangNama!.trim(),
    ];

    return locations.join('  •  ');
  }

  @override
  Widget build(BuildContext context) {
    final locationSummary = _buildLocationSummary();
    final isHoliday = (record.status ?? '').toLowerCase() == 'libur';
    final isSyntheticAlpha = record.id.startsWith('alpha-');
    final isInfoRow = isHoliday || isSyntheticAlpha;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(18),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: const Color(0xFFD8E6F8)),
          boxShadow: const [
            BoxShadow(
              color: Color(0x0E0F4C81),
              blurRadius: 10,
              offset: Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: statusColor.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(statusIcon, color: statusColor, size: 20),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    dateLabel,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF123B67),
                    ),
                  ),
                ),
                _StatusBadge(
                  label: record.displayStatusLabel,
                  color: statusColor,
                  icon: statusIcon,
                ),
              ],
            ),
            const SizedBox(height: 10),
            if (isInfoRow)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: statusColor.withValues(alpha: 0.14)),
                ),
                child: Text(
                  (record.keterangan ?? '').trim().isEmpty
                      ? (isHoliday
                          ? 'Hari libur sesuai skema aktif'
                          : 'Tidak ada absensi pada hari sekolah ini')
                      : record.keterangan!.trim(),
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: statusColor,
                    height: 1.35,
                  ),
                ),
              )
            else
              Row(
                children: [
                  Expanded(
                    child: _TimeInfo(
                      icon: Icons.login_rounded,
                      label: 'Masuk',
                      value: record.formattedCheckInTime,
                      color: const Color(0xFF16A34A),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: _TimeInfo(
                      icon: Icons.logout_rounded,
                      label: 'Pulang',
                      value: record.formattedCheckOutTime,
                      color: const Color(0xFF2563EB),
                    ),
                  ),
                ],
              ),
            if (locationSummary.isNotEmpty) ...[
              const SizedBox(height: 9),
              Row(
                children: [
                  const Icon(
                    Icons.location_on_outlined,
                    size: 15,
                    color: Color(0xFF7B8EA8),
                  ),
                  const SizedBox(width: 6),
                  Expanded(
                    child: Text(
                      locationSummary,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF66758A),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String label;
  final Color color;
  final IconData icon;

  const _StatusBadge({
    required this.label,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.2)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 13, color: color),
          const SizedBox(width: 5),
          Text(
            label,
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              color: color,
              letterSpacing: 0.1,
            ),
          ),
        ],
      ),
    );
  }
}

class _TimeInfo extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final Color color;

  const _TimeInfo({
    required this.icon,
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(icon, size: 16, color: color),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 10,
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
          ),
        ],
      ),
    );
  }
}

class _MonthPickerSheet extends StatefulWidget {
  final DateTime initialPeriod;
  final DateTime? minPeriod;
  final DateTime? maxPeriod;

  const _MonthPickerSheet({
    required this.initialPeriod,
    this.minPeriod,
    this.maxPeriod,
  });

  @override
  State<_MonthPickerSheet> createState() => _MonthPickerSheetState();
}

class _MonthPickerSheetState extends State<_MonthPickerSheet> {
  late int _year;
  late DateTime _minPeriod;
  late DateTime _maxPeriod;
  late DateTime _selectedPeriod;

  static const List<String> _months = <String>[
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

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _maxPeriod = _normalizeMonth(
      widget.maxPeriod ?? DateTime(now.year, now.month),
    );
    _minPeriod = _normalizeMonth(
      widget.minPeriod ?? DateTime(_maxPeriod.year - 5, 1),
    );

    if (_minPeriod.isAfter(_maxPeriod)) {
      _minPeriod = _maxPeriod;
    }

    final initial = _clampToRange(_normalizeMonth(widget.initialPeriod));
    _selectedPeriod = initial;
    _year = initial.year;
  }

  DateTime _normalizeMonth(DateTime value) {
    return DateTime(value.year, value.month);
  }

  DateTime _clampToRange(DateTime period) {
    if (period.isBefore(_minPeriod)) {
      return _minPeriod;
    }
    if (period.isAfter(_maxPeriod)) {
      return _maxPeriod;
    }
    return period;
  }

  bool _isDisabledMonth(int month) {
    final period = DateTime(_year, month);
    return period.isBefore(_minPeriod) || period.isAfter(_maxPeriod);
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Pilih Bulan',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w800,
                color: Color(0xFF123B67),
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                IconButton(
                  onPressed: _year > _minPeriod.year
                      ? () {
                          setState(() {
                            _year -= 1;
                          });
                        }
                      : null,
                  icon: const Icon(Icons.chevron_left_rounded),
                ),
                Expanded(
                  child: Text(
                    _year.toString(),
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF123B67),
                    ),
                  ),
                ),
                IconButton(
                  onPressed: _year < _maxPeriod.year
                      ? () {
                          setState(() {
                            _year += 1;
                          });
                        }
                      : null,
                  icon: const Icon(Icons.chevron_right_rounded),
                ),
              ],
            ),
            const SizedBox(height: 8),
            GridView.builder(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: 12,
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 3,
                mainAxisSpacing: 10,
                crossAxisSpacing: 10,
                childAspectRatio: 2.4,
              ),
              itemBuilder: (context, index) {
                final month = index + 1;
                final isDisabled = _isDisabledMonth(month);
                final isSelected =
                    _year == _selectedPeriod.year && month == _selectedPeriod.month;

                return InkWell(
                  onTap: isDisabled
                      ? null
                      : () {
                          Navigator.of(context).pop(DateTime(_year, month));
                        },
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    decoration: BoxDecoration(
                      color: isSelected
                          ? AppColors.primary
                          : const Color(0xFFF7FAFF),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: isSelected
                            ? AppColors.primary
                            : const Color(0xFFD8E6F8),
                      ),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      _months[index],
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: isDisabled
                            ? const Color(0xFFB4BFCD)
                            : (isSelected ? Colors.white : const Color(0xFF123B67)),
                      ),
                    ),
                  ),
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _LoadingState extends StatelessWidget {
  const _LoadingState();

  @override
  Widget build(BuildContext context) {
    return const Padding(
      padding: EdgeInsets.symmetric(vertical: 48),
      child: Center(child: CircularProgressIndicator()),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _ErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return _EmptyState(
      icon: Icons.error_outline,
      title: 'Gagal memuat riwayat',
      subtitle: message,
      actionLabel: 'Muat ulang',
      onAction: () {
        onRetry();
      },
    );
  }
}

class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String title;
  final String subtitle;
  final String? actionLabel;
  final VoidCallback? onAction;

  const _EmptyState({
    required this.icon,
    required this.title,
    required this.subtitle,
    this.actionLabel,
    this.onAction,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: Column(
        children: [
          Icon(icon, size: 42, color: const Color(0xFF7B8EA8)),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 13,
              color: Color(0xFF66758A),
            ),
          ),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: 14),
            OutlinedButton(
              onPressed: () {
                onAction!();
              },
              child: Text(actionLabel!),
            ),
          ],
        ],
      ),
    );
  }
}
