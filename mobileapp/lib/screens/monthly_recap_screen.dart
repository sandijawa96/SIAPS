import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/monthly_recap_data.dart';
import '../providers/auth_provider.dart';
import '../services/dashboard_service.dart';
import '../services/monthly_recap_service.dart';
import '../utils/constants.dart';
import '../widgets/access_denied_scaffold.dart';

class MonthlyRecapScreen extends StatefulWidget {
  const MonthlyRecapScreen({super.key});

  @override
  State<MonthlyRecapScreen> createState() => _MonthlyRecapScreenState();
}

class _MonthlyRecapScreenState extends State<MonthlyRecapScreen> {
  final DashboardService _dashboardService = DashboardService();
  final MonthlyRecapService _service = MonthlyRecapService();

  bool _hasAccess = false;
  bool _isLoading = true;
  String? _errorMessage;
  String? _comparisonWarning;
  AcademicContext? _academicContext;
  DateTime _selectedPeriod = DateTime(DateTime.now().year, DateTime.now().month);
  MonthlyRecapData _selectedData = MonthlyRecapData.empty;
  MonthlyRecapData _previousData = MonthlyRecapData.empty;
  String _selectedLabel = '';
  String _previousLabel = '';

  @override
  void initState() {
    super.initState();
    _hasAccess = context.read<AuthProvider>().user?.isSiswa ?? false;
    if (_hasAccess) {
      _initializeAcademicContextAndData();
    }
  }

  Future<void> _initializeAcademicContextAndData() async {
    final contextResponse = await _dashboardService.getAcademicContext();
    if (mounted && contextResponse.success && contextResponse.data != null) {
      setState(() {
        _academicContext = contextResponse.data;
        _selectedPeriod = _clampPeriodToRange(_selectedPeriod);
      });
    }

    await _loadData();
  }

  Future<void> _loadData() async {
    final selected = _clampPeriodToRange(
      DateTime(_selectedPeriod.year, _selectedPeriod.month),
    );
    final previous = DateTime(selected.year, selected.month - 1);

    setState(() {
      _isLoading = true;
      _errorMessage = null;
      _comparisonWarning = null;
    });

    final selectedResponse = await _service.getSpecificMonthRecap(
      selected.year,
      selected.month,
      tahunAjaranId: _academicContext?.tahunAjaranId,
    );

    final previousIsInRange = _isPeriodWithinRange(previous);
    final previousResponse = previousIsInRange
        ? await _service.getSpecificMonthRecap(
            previous.year,
            previous.month,
            tahunAjaranId: _academicContext?.tahunAjaranId,
          )
        : MonthlyRecapResponse(
            success: false,
            message: 'Periode pembanding di luar rentang tahun ajaran aktif',
          );

    if (!mounted) {
      return;
    }

    setState(() {
      _selectedPeriod = selected;
      _selectedData = selectedResponse.data ?? MonthlyRecapData.empty;
      _previousData = previousResponse.data ?? MonthlyRecapData.empty;
      _selectedLabel = selectedResponse.month ?? _formatPeriod(selected);
      _previousLabel = previousResponse.month ?? _formatPeriod(previous);
      _errorMessage = selectedResponse.success ? null : selectedResponse.message;
      _comparisonWarning = previousResponse.success ? null : previousResponse.message;
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

    final normalized = _clampPeriodToRange(DateTime(picked.year, picked.month));
    if (normalized.year == _selectedPeriod.year &&
        normalized.month == _selectedPeriod.month) {
      return;
    }

    setState(() {
      _selectedPeriod = normalized;
    });
    await _loadData();
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
    await _loadData();
  }

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

  bool _isPeriodWithinRange(DateTime period) {
    final normalized = DateTime(period.year, period.month);
    final minPeriod = _minSelectablePeriod;
    final maxPeriod = _maxSelectablePeriod;

    if (minPeriod != null && normalized.isBefore(minPeriod)) {
      return false;
    }

    return !normalized.isAfter(maxPeriod);
  }

  String? get _academicScopeLabel {
    final compact = (_academicContext?.compactLabel ?? '').trim();
    if (compact.isEmpty || compact == '-') {
      return null;
    }
    return 'Tahun ajaran aktif: $compact';
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

  String _formatPercent(double value) {
    final rounded =
        value % 1 == 0 ? value.toStringAsFixed(0) : value.toStringAsFixed(1);
    return '$rounded%';
  }

  String _disciplineUnitShort(DisciplineThresholdMetric metric) {
    return metric.metricUnit == 'hari' ? 'hari' : 'mnt';
  }

  String _disciplineMetricValue(DisciplineThresholdMetric metric) {
    final unit = _disciplineUnitShort(metric);
    if (metric.limit <= 0) {
      return metric.currentValue > 0
          ? '${metric.currentValue} $unit'
          : 'Tidak aktif';
    }

    return '${metric.currentValue} / ${metric.limit} $unit';
  }

  String _disciplineStatusLabel(DisciplineThresholdMetric metric) {
    if (metric.limit <= 0) {
      return 'Threshold tidak aktif';
    }

    if (metric.exceeded) {
      return metric.alertable ? 'Melewati batas + alert' : 'Melewati batas';
    }

    return metric.mode == 'alertable' ? 'Monitoring + alert siap' : 'Monitoring';
  }

  String _disciplineLabel(
    DisciplineThresholdMetric metric,
    String fallback,
  ) {
    final resolved = metric.label.trim();
    return resolved.isEmpty ? fallback : resolved;
  }

  List<DisciplineThresholdMetric> _alertableExceededMetrics(
    DisciplineThresholdSnapshot snapshot,
  ) {
    return <DisciplineThresholdMetric>[
      snapshot.monthlyLate,
      snapshot.semesterTotalViolation,
      snapshot.semesterAlpha,
    ].where((metric) => metric.exceeded && metric.alertable).toList();
  }

  _InsightTone _buildInsightTone(MonthlyRecapData data) {
    if (data.disciplineThresholds.attentionNeeded) {
      return const _InsightTone(
        label: 'Perlu perhatian',
        color: Color(0xFFB4232C),
        icon: Icons.warning_amber_rounded,
      );
    }

    if (data.pelanggaranMenit > 0 || data.alpaHari > 0) {
      return const _InsightTone(
        label: 'Cukup aman',
        color: Color(0xFFF59E0B),
        icon: Icons.insights_rounded,
      );
    }

    return const _InsightTone(
      label: 'Sangat baik',
      color: Color(0xFF16A34A),
      icon: Icons.verified_rounded,
    );
  }

  List<String> _buildInsights(MonthlyRecapData current, MonthlyRecapData previous) {
    final insights = <String>[];
    final attendanceDelta = current.attendanceRate - previous.attendanceRate;
    final lateDelta = current.terlambatMenit - previous.terlambatMenit;
    final alphaDelta = current.alpaHari - previous.alpaHari;
    final monthlyLate = current.disciplineThresholds.monthlyLate;
    final semesterViolation = current.disciplineThresholds.semesterTotalViolation;
    final semesterAlpha = current.disciplineThresholds.semesterAlpha;

    insights.add(
      current.attendanceRate >= 85
          ? 'Kehadiran bulan ini berada di ${_formatPercent(current.attendanceRate)} dan sudah tergolong kuat.'
          : 'Kehadiran bulan ini masih ${_formatPercent(current.attendanceRate)} dan perlu ditingkatkan.',
    );

    if (attendanceDelta > 0) {
      insights.add(
        'Tingkat kehadiran naik ${_formatPercent(attendanceDelta.abs())} dibanding $_previousLabel.',
      );
    } else if (attendanceDelta < 0) {
      insights.add(
        'Tingkat kehadiran turun ${_formatPercent(attendanceDelta.abs())} dibanding $_previousLabel.',
      );
    }

    if (lateDelta > 0) {
      insights.add(
        'Total menit terlambat bertambah ${lateDelta.abs()} menit dari bulan sebelumnya.',
      );
    } else if (lateDelta < 0) {
      insights.add(
        'Total menit terlambat membaik ${lateDelta.abs()} menit dari bulan sebelumnya.',
      );
    }

    if (alphaDelta > 0) {
      insights.add('Jumlah alpa naik ${alphaDelta.abs()} hari dan perlu dipantau.');
    } else if (alphaDelta < 0) {
      insights.add('Jumlah alpa turun ${alphaDelta.abs()} hari dari bulan sebelumnya.');
    }

    if (current.tapMenit > 0) {
      insights.add(
        'Masih ada ${current.tapHari} kejadian Lupa Pulang (TAP) dengan dampak ${current.tapMenit} menit. Biasakan absen pulang agar pelanggaran tidak menumpuk.',
      );
    }

    if (monthlyLate.exceeded) {
      insights.add(
        '${monthlyLate.label.isNotEmpty ? monthlyLate.label : 'Keterlambatan bulanan'} sudah ${monthlyLate.currentValue} ${monthlyLate.metricUnit} dan melewati ambang ${monthlyLate.limit} ${monthlyLate.metricUnit}.',
      );
    }

    if (semesterViolation.exceeded) {
      insights.add(
        '${semesterViolation.label.isNotEmpty ? semesterViolation.label : 'Akumulasi pelanggaran semester'} sudah ${semesterViolation.currentValue} ${semesterViolation.metricUnit} dan melewati ambang ${semesterViolation.limit} ${semesterViolation.metricUnit}.',
      );
    }

    if (semesterAlpha.exceeded) {
      insights.add(
        '${semesterAlpha.label.isNotEmpty ? semesterAlpha.label : 'Jumlah alpha semester'} sudah ${semesterAlpha.currentValue} ${semesterAlpha.metricUnit} dan melewati ambang ${semesterAlpha.limit} ${semesterAlpha.metricUnit}.',
      );
    }

    return insights.take(4).toList();
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess) {
      return const AccessDeniedScaffold(
        title: 'Rekap Bulanan',
        message: 'Rekap bulanan mobile hanya tersedia untuk akun siswa.',
      );
    }

    final tone = _buildInsightTone(_selectedData);
    final insights = _buildInsights(_selectedData, _previousData);
    final alertableExceededMetrics = _alertableExceededMetrics(
      _selectedData.disciplineThresholds,
    );
    final notificationTargets = <String>[
      if (alertableExceededMetrics.any((metric) => metric.notifyWaliKelas))
        'Wali Kelas',
      if (alertableExceededMetrics.any((metric) => metric.notifyKesiswaan))
        'Kesiswaan',
    ];
    final disciplineStatusColor = _selectedData.disciplineThresholds.attentionNeeded
        ? const Color(0xFFB4232C)
        : const Color(0xFF123B67);
    final disciplineStatusValue = _selectedData.disciplineThresholds.attentionNeeded
        ? 'Perlu perhatian'
        : 'Monitoring';
    final disciplineStatusHint = alertableExceededMetrics.isEmpty
        ? 'Mengikuti threshold aktif dari sistem'
        : '${alertableExceededMetrics.length} indikator alert aktif';
    final notificationValue = notificationTargets.isEmpty
        ? 'Belum aktif'
        : notificationTargets.join(' & ');
    final notificationHint = alertableExceededMetrics.isEmpty
        ? 'Notifikasi mengikuti rule alert aktif'
        : 'Target saat alert otomatis terpicu';

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Rekap Bulanan'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: RefreshIndicator(
        onRefresh: _initializeAcademicContextAndData,
        color: AppColors.primary,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _MonthlyHeroCard(
              periodLabel:
                  _selectedLabel.isEmpty ? _formatPeriod(_selectedPeriod) : _selectedLabel,
              comparisonLabel:
                  _previousLabel.isEmpty ? null : 'Bandingkan dengan $_previousLabel',
              scopeLabel: _academicScopeLabel,
              attendanceRate: _formatPercent(_selectedData.attendanceRate),
              workingDays: _selectedData.workingDays,
              presentDays: _selectedData.masuk,
              violationMinutes: _selectedData.pelanggaranMenit,
              tone: tone,
              onPrevious: _canGoToPreviousPeriod ? () => _shiftPeriod(-1) : null,
              onNext: _canGoToNextPeriod ? () => _shiftPeriod(1) : null,
              onPickPeriod: _pickPeriod,
            ),
            const SizedBox(height: 16),
            if (_isLoading)
              const _LoadingState()
            else if (_errorMessage != null)
              _RecapErrorState(message: _errorMessage!, onRetry: _loadData)
            else ...[
              if (_comparisonWarning != null) ...[
                _WarningCard(message: _comparisonWarning!),
                const SizedBox(height: 16),
              ],
              _SectionCard(
                title: 'Ikhtisar Utama',
                subtitle: 'Fokus cepat pada ringkasan hari hadir, alpa, izin, sakit, telat, dan TAP bulan ini.',
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: _MetricTile(
                            label: 'Hadir',
                            value: '${_selectedData.masuk}',
                            unit: 'hari',
                            icon: Icons.check_circle_rounded,
                            color: const Color(0xFF16A34A),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _MetricTile(
                            label: 'Alpa',
                            value: '${_selectedData.alpaHari}',
                            unit: 'hari',
                            icon: Icons.cancel_rounded,
                            color: const Color(0xFFB4232C),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: _MetricTile(
                            label: 'Izin',
                            value: '${_selectedData.izin}',
                            unit: 'hari',
                            icon: Icons.event_note_rounded,
                            color: const Color(0xFF2563EB),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _MetricTile(
                            label: 'Sakit',
                            value: '${_selectedData.sakit}',
                            unit: 'hari',
                            icon: Icons.healing_rounded,
                            color: const Color(0xFF7C3AED),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    Row(
                      children: [
                        Expanded(
                          child: _MetricTile(
                            label: 'Terlambat',
                            value: '${_selectedData.terlambatHari}',
                            unit: 'hari',
                            icon: Icons.schedule_rounded,
                            color: const Color(0xFFF59E0B),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: _MetricTile(
                            label: 'Lupa Pulang (TAP)',
                            value: '${_selectedData.tapHari}',
                            unit: 'hari',
                            icon: Icons.logout_rounded,
                            color: const Color(0xFF0F766E),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _SectionCard(
                title: 'Rekap Bulan Berjalan',
                subtitle: 'Ringkasan capaian hari yang tercatat dibanding total hari sekolah bulan ini.',
                child: Column(
                  children: [
                    _BreakdownRow(
                      label: 'Hadir',
                      value: _selectedData.masuk,
                      total: _selectedData.schoolDaysInMonth,
                      color: const Color(0xFF16A34A),
                    ),
                    _BreakdownRow(
                      label: 'Terlambat',
                      value: _selectedData.terlambatHari,
                      total: _selectedData.schoolDaysInMonth,
                      color: const Color(0xFFF59E0B),
                    ),
                    _BreakdownRow(
                      label: 'Izin',
                      value: _selectedData.izin,
                      total: _selectedData.schoolDaysInMonth,
                      color: const Color(0xFF2563EB),
                    ),
                    _BreakdownRow(
                      label: 'Sakit',
                      value: _selectedData.sakit,
                      total: _selectedData.schoolDaysInMonth,
                      color: const Color(0xFF7C3AED),
                    ),
                    _BreakdownRow(
                      label: 'Alpa',
                      value: _selectedData.alpaHari,
                      total: _selectedData.schoolDaysInMonth,
                      color: const Color(0xFFB4232C),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _SectionCard(
                title: 'Pelanggaran & Disiplin',
                subtitle: 'Data pelanggaran memakai threshold aktif dari sistem untuk bulan ini dan monitoring semester berjalan.',
                child: Column(
                  children: [
                    _TwoUpCards(
                      left: _StatStrip(
                        label: 'Total TK',
                        value: '${_selectedData.totalTK} mnt',
                        hint: 'Akumulasi bulan ini',
                        color: const Color(0xFF123B67),
                      ),
                      right: _StatStrip(
                        label: 'Persentase',
                        value: _formatPercent(_selectedData.persentasePelanggaran),
                        hint: 'Dari menit sekolah bulan',
                        color: tone.color,
                      ),
                    ),
                    const SizedBox(height: 10),
                    _TwoUpCards(
                      left: _StatStrip(
                        label: 'Terlambat',
                        value: '${_selectedData.terlambatMenit} mnt',
                        hint: '${_selectedData.terlambatHari} hari telat',
                        color: const Color(0xFFF59E0B),
                      ),
                      right: _StatStrip(
                        label: 'Lupa Pulang (TAP)',
                        value: '${_selectedData.tapMenit} mnt',
                        hint: '${_selectedData.tapHari} hari tanpa checkout',
                        color: const Color(0xFF0F766E),
                      ),
                    ),
                    const SizedBox(height: 10),
                    _TwoUpCards(
                      left: _StatStrip(
                        label: 'Alpha',
                        value: '${_selectedData.alpaMenit} mnt',
                        hint: '${_selectedData.alpaHari} hari alpha',
                        color: const Color(0xFFB4232C),
                      ),
                      right: _DisciplineInfoTile(
                        label: 'Status Disiplin',
                        value: disciplineStatusValue,
                        hint: disciplineStatusHint,
                        color: disciplineStatusColor,
                        icon: _selectedData.disciplineThresholds.attentionNeeded
                            ? Icons.gpp_bad_rounded
                            : Icons.insights_rounded,
                      ),
                    ),
                    const SizedBox(height: 12),
                    _TwoUpCards(
                      left: _DisciplineThresholdTile(
                        label: _disciplineLabel(
                          _selectedData.disciplineThresholds.monthlyLate,
                          'Keterlambatan Bulanan',
                        ),
                        value: _disciplineMetricValue(
                          _selectedData.disciplineThresholds.monthlyLate,
                        ),
                        statusLabel: _disciplineStatusLabel(
                          _selectedData.disciplineThresholds.monthlyLate,
                        ),
                        exceeded:
                            _selectedData.disciplineThresholds.monthlyLate.exceeded,
                      ),
                      right: _DisciplineThresholdTile(
                        label: _disciplineLabel(
                          _selectedData.disciplineThresholds.semesterTotalViolation,
                          'Total Pelanggaran Semester',
                        ),
                        value: _disciplineMetricValue(
                          _selectedData
                              .disciplineThresholds
                              .semesterTotalViolation,
                        ),
                        statusLabel: _disciplineStatusLabel(
                          _selectedData
                              .disciplineThresholds
                              .semesterTotalViolation,
                        ),
                        exceeded: _selectedData
                            .disciplineThresholds
                            .semesterTotalViolation
                            .exceeded,
                      ),
                    ),
                    const SizedBox(height: 10),
                    _TwoUpCards(
                      left: _DisciplineThresholdTile(
                        label: _disciplineLabel(
                          _selectedData.disciplineThresholds.semesterAlpha,
                          'Alpha Semester',
                        ),
                        value: _disciplineMetricValue(
                          _selectedData.disciplineThresholds.semesterAlpha,
                        ),
                        statusLabel: _disciplineStatusLabel(
                          _selectedData.disciplineThresholds.semesterAlpha,
                        ),
                        exceeded:
                            _selectedData.disciplineThresholds.semesterAlpha.exceeded,
                      ),
                      right: _DisciplineInfoTile(
                        label: 'Notifikasi',
                        value: notificationValue,
                        hint: notificationHint,
                        color: notificationTargets.isEmpty
                            ? const Color(0xFF66758A)
                            : const Color(0xFF123B67),
                        icon: notificationTargets.isEmpty
                            ? Icons.notifications_off_rounded
                            : Icons.notifications_active_rounded,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _SectionCard(
                title: 'Insight Bulan Ini',
                subtitle: 'Ringkasan singkat untuk membaca kondisi bulan ini lebih cepat.',
                child: Column(
                  children: insights
                      .map(
                        (item) => Padding(
                          padding: const EdgeInsets.only(bottom: 10),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Container(
                                width: 22,
                                height: 22,
                                margin: const EdgeInsets.only(top: 1),
                                decoration: BoxDecoration(
                                  color: tone.color.withValues(alpha: 0.12),
                                  shape: BoxShape.circle,
                                ),
                                child: Icon(
                                  tone.icon,
                                  size: 13,
                                  color: tone.color,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  item,
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w600,
                                    color: Color(0xFF42566E),
                                    height: 1.45,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      )
                      .toList(),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _MonthlyHeroCard extends StatelessWidget {
  final String periodLabel;
  final String? comparisonLabel;
  final String? scopeLabel;
  final String attendanceRate;
  final int workingDays;
  final int presentDays;
  final int violationMinutes;
  final _InsightTone tone;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;
  final VoidCallback onPickPeriod;

  const _MonthlyHeroCard({
    required this.periodLabel,
    required this.comparisonLabel,
    this.scopeLabel,
    required this.attendanceRate,
    required this.workingDays,
    required this.presentDays,
    required this.violationMinutes,
    required this.tone,
    required this.onPrevious,
    required this.onNext,
    required this.onPickPeriod,
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
        borderRadius: BorderRadius.circular(24),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1A0F4C81),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Rekap Bulanan',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.86),
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.4,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      periodLabel,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if ((comparisonLabel ?? '').isNotEmpty) ...[
                      const SizedBox(height: 5),
                      Text(
                        comparisonLabel!,
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.8),
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                    if ((scopeLabel ?? '').isNotEmpty) ...[
                      const SizedBox(height: 5),
                      Text(
                        scopeLabel!,
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.9),
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(999),
                  border: Border.all(color: Colors.white.withValues(alpha: 0.18)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(tone.icon, size: 14, color: Colors.white),
                    const SizedBox(width: 6),
                    Text(
                      tone.label,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: _HeroMiniStat(
                  label: 'Kehadiran',
                  value: attendanceRate,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _HeroMiniStat(
                  label: 'Hari Sekolah',
                  value: '$workingDays hari',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _HeroMiniStat(
                  label: 'Pelanggaran',
                  value: '$violationMinutes mnt',
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(18),
            ),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '$presentDays hari hadir tercatat pada periode ini.',
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.88),
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                const SizedBox(width: 10),
                _PeriodButton(
                  icon: Icons.chevron_left_rounded,
                  onTap: onPrevious,
                  enabled: onPrevious != null,
                ),
                const SizedBox(width: 8),
                InkWell(
                  onTap: onPickPeriod,
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          Icons.calendar_month_outlined,
                          size: 16,
                          color: Color(0xFF123B67),
                        ),
                        SizedBox(width: 6),
                        Text(
                          'Pilih',
                          style: TextStyle(
                            color: Color(0xFF123B67),
                            fontSize: 12,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(width: 8),
                _PeriodButton(
                  icon: Icons.chevron_right_rounded,
                  onTap: onNext,
                  enabled: onNext != null,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _HeroMiniStat extends StatelessWidget {
  final String label;
  final String value;

  const _HeroMiniStat({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.74),
              fontSize: 11,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 15,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final String subtitle;
  final Widget child;

  const _SectionCard({
    required this.title,
    required this.subtitle,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
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
          Text(
            title,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Color(0xFF66758A),
              height: 1.4,
            ),
          ),
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

class _MetricTile extends StatelessWidget {
  final String label;
  final String value;
  final String unit;
  final IconData icon;
  final Color color;

  const _MetricTile({
    required this.label,
    required this.value,
    required this.unit,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: color.withValues(alpha: 0.14)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(999),
            ),
            child: Icon(icon, size: 18, color: color),
          ),
          const SizedBox(height: 14),
          RichText(
            text: TextSpan(
              children: [
                TextSpan(
                  text: value,
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: color,
                  ),
                ),
                TextSpan(
                  text: ' $unit',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                    color: color.withValues(alpha: 0.84),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 6),
          Text(
            label,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: Color(0xFF4E6178),
            ),
          ),
        ],
      ),
    );
  }
}

class _BreakdownRow extends StatelessWidget {
  final String label;
  final int value;
  final int total;
  final Color color;

  const _BreakdownRow({
    required this.label,
    required this.value,
    required this.total,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    final ratio = total <= 0 ? 0.0 : (value / total).clamp(0, 1).toDouble();

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF42566E),
                  ),
                ),
              ),
              Text(
                '$value / $total hari',
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF66758A),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: LinearProgressIndicator(
              value: ratio,
              minHeight: 9,
              backgroundColor: color.withValues(alpha: 0.12),
              valueColor: AlwaysStoppedAnimation<Color>(color),
            ),
          ),
        ],
      ),
    );
  }
}

class _TwoUpCards extends StatelessWidget {
  final Widget left;
  final Widget right;

  const _TwoUpCards({
    required this.left,
    required this.right,
  });

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(child: left),
          const SizedBox(width: 10),
          Expanded(child: right),
        ],
      ),
    );
  }
}

class _StatStrip extends StatelessWidget {
  final String label;
  final String value;
  final String hint;
  final Color color;

  const _StatStrip({
    required this.label,
    required this.value,
    required this.hint,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 124),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(18),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: 30,
              child: Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF66758A),
                ),
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 24,
              child: Align(
                alignment: Alignment.centerLeft,
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    value,
                    style: TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w900,
                      color: color,
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 6),
            SizedBox(
              height: 30,
              child: Text(
                hint,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF4E6178),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DisciplineInfoTile extends StatelessWidget {
  final String label;
  final String value;
  final String hint;
  final Color color;
  final IconData icon;

  const _DisciplineInfoTile({
    required this.label,
    required this.value,
    required this.hint,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 124),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: color.withValues(alpha: 0.12)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(icon, size: 16, color: color),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    label,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF66758A),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 24,
              child: Align(
                alignment: Alignment.centerLeft,
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    value,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                      color: color,
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 6),
            SizedBox(
              height: 30,
              child: Text(
                hint,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF4E6178),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DisciplineThresholdTile extends StatelessWidget {
  final String label;
  final String value;
  final String statusLabel;
  final bool exceeded;

  const _DisciplineThresholdTile({
    required this.label,
    required this.value,
    required this.statusLabel,
    required this.exceeded,
  });

  @override
  Widget build(BuildContext context) {
    final color = exceeded ? const Color(0xFFB4232C) : const Color(0xFF16A34A);

    return ConstrainedBox(
      constraints: const BoxConstraints(minHeight: 124),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: color.withValues(alpha: 0.12)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              height: 30,
              child: Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF66758A),
                ),
              ),
            ),
            const SizedBox(height: 8),
            SizedBox(
              height: 24,
              child: Align(
                alignment: Alignment.centerLeft,
                child: FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    value,
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                      color: color,
                    ),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 6),
            SizedBox(
              height: 30,
              child: Text(
                statusLabel,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: color,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WarningCard extends StatelessWidget {
  final String message;

  const _WarningCard({required this.message});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFF6E7),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFF3D294)),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline, color: Color(0xFF9A6700)),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: Color(0xFF9A6700),
              ),
            ),
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
        width: 40,
        height: 40,
        decoration: BoxDecoration(
          color: enabled
              ? Colors.white.withValues(alpha: 0.16)
              : Colors.white.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
        ),
        child: Icon(
          icon,
          size: 20,
          color: enabled ? Colors.white : Colors.white.withValues(alpha: 0.45),
        ),
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
              'Pilih Bulan Rekap',
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
                      : () => Navigator.of(context).pop(DateTime(_year, month)),
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    decoration: BoxDecoration(
                      color: isSelected ? AppColors.primary : const Color(0xFFF7FAFF),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: isSelected ? AppColors.primary : const Color(0xFFD8E6F8),
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

class _RecapErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _RecapErrorState({
    required this.message,
    required this.onRetry,
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
          const Icon(Icons.error_outline, size: 40, color: Color(0xFFB4232C)),
          const SizedBox(height: 12),
          const Text(
            'Gagal memuat rekap bulanan',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 13,
              color: Color(0xFF66758A),
            ),
          ),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: () {
              onRetry();
            },
            child: const Text('Muat ulang'),
          ),
        ],
      ),
    );
  }
}

class _InsightTone {
  final String label;
  final Color color;
  final IconData icon;

  const _InsightTone({
    required this.label,
    required this.color,
    required this.icon,
  });
}
