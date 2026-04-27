import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../services/dashboard_service.dart';
import '../services/wali_kelas_service.dart';
import '../utils/constants.dart';
import '../widgets/access_denied_scaffold.dart';
import 'leave_detail_screen.dart';

class WaliClassDetailScreen extends StatefulWidget {
  final int classId;

  const WaliClassDetailScreen({
    super.key,
    required this.classId,
  });

  @override
  State<WaliClassDetailScreen> createState() => _WaliClassDetailScreenState();
}

class _WaliClassDetailScreenState extends State<WaliClassDetailScreen> {
  final DashboardService _dashboardService = DashboardService();
  final WaliKelasService _service = WaliKelasService();

  bool _hasAccess = false;
  String _pageTitle = 'Detail Kelas';
  bool _isLoading = true;
  String? _errorMessage;
  String _academicContextLabel = '-';
  WaliClassDetailData? _detail;

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _hasAccess = user?.canOpenAttendanceMonitoringMenu ?? false;
    _pageTitle = user?.attendanceMonitoringMenuTitle == 'Monitoring Kelas'
        ? 'Monitoring Kelas'
        : 'Detail Kelas';
    if (_hasAccess) {
      _loadDetail();
    }
  }

  Future<void> _loadDetail() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final contextResponse = await _dashboardService.getAcademicContext();
    final response = await _service.getClassDetailBundle(widget.classId);
    if (!mounted) {
      return;
    }

    setState(() {
      _academicContextLabel =
          contextResponse.success && contextResponse.data != null
              ? contextResponse.data!.compactLabel
              : '-';
      _detail = response.data;
      _errorMessage = response.success ? null : response.message;
      _isLoading = false;
    });
  }

  String _formatDate(DateTime? value) {
    if (value == null) {
      return '-';
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
    return '${value.day.toString().padLeft(2, '0')} ${months[value.month - 1]} ${value.year}';
  }

  String _formatDateTime(DateTime? value) {
    if (value == null) {
      return '-';
    }
    return '${_formatDate(value)} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
  }

  String _resolveLeaveLabel(WaliClassLeaveEntry leave) {
    final label = leave.jenisIzinLabel?.trim();
    if (label != null && label.isNotEmpty) {
      return label;
    }

    return leave.jenisIzin.replaceAll('_', ' ');
  }

  String _resolveLeaveStatusLabel(WaliClassLeaveEntry leave) {
    final label = leave.statusLabel?.trim();
    if (label != null && label.isNotEmpty) {
      return label;
    }

    switch (leave.status.trim().toLowerCase()) {
      case 'approved':
        return 'Disetujui';
      case 'rejected':
        return 'Ditolak';
      default:
        return 'Menunggu Persetujuan';
    }
  }

  Future<void> _openLeaveDetail(int leaveId) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => LeaveDetailScreen(leaveId: leaveId),
      ),
    );

    if (changed == true) {
      await _loadDetail();
    }
  }

  Color _statusColor(String status) {
    switch (status.toLowerCase()) {
      case 'hadir':
      case 'approved':
        return const Color(0xFF16A34A);
      case 'pending':
        return const Color(0xFFF59E0B);
      case 'izin':
      case 'sakit':
        return const Color(0xFF2563EB);
      default:
        return const Color(0xFFDC2626);
    }
  }

  Color _fraudValidationColor(String status) {
    switch (status.toLowerCase()) {
      case 'warning':
        return const Color(0xFFD97706);
      default:
        return const Color(0xFF16A34A);
      }
  }

  Color _fraudRiskColor(String level) {
    switch (level.toLowerCase()) {
      case 'critical':
        return const Color(0xFFB42318);
      case 'high':
        return const Color(0xFFDC2626);
      case 'medium':
        return const Color(0xFFD97706);
      default:
        return const Color(0xFF2563EB);
    }
  }

  Color _securityStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'needs_reopen':
        return const Color(0xFFB42318);
      case 'needs_case':
        return const Color(0xFFD97706);
      case 'in_progress':
        return const Color(0xFF2563EB);
      case 'done':
        return const Color(0xFF16A34A);
      default:
        return const Color(0xFF66758A);
    }
  }

  Color _casePriorityColor(String priority) {
    switch (priority.toLowerCase()) {
      case 'critical':
        return const Color(0xFFB42318);
      case 'high':
        return const Color(0xFFDC2626);
      case 'low':
        return const Color(0xFF2563EB);
      default:
        return const Color(0xFFD97706);
    }
  }

  Color _caseStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'resolved':
        return const Color(0xFF16A34A);
      case 'escalated':
        return const Color(0xFFB42318);
      case 'reopened':
        return const Color(0xFFD97706);
      default:
        return const Color(0xFF2563EB);
    }
  }

  Widget _buildFraudSection(WaliClassDetailData detail) {
    return _WaliSection(
      title: 'Fraud Monitoring',
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Mode ${detail.fraudSummary.config.rolloutMode}. Semua sinyal fraud dicatat sebagai warning-only. Hanya device binding akun siswa yang tetap menjadi hard block.',
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFF66758A),
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _CompactMetricCard(
                  label: 'Assessment',
                  value: '${detail.fraudSummary.totalAssessments}',
                  color: const Color(0xFF2563EB),
                ),
                _CompactMetricCard(
                  label: 'Warning',
                  value: '${detail.fraudSummary.warningCount}',
                  color: const Color(0xFFD97706),
                ),
                _CompactMetricCard(
                  label: 'Siswa',
                  value: '${detail.fraudSummary.uniqueStudents}',
                  color: const Color(0xFF123B67),
                ),
              ],
            ),
            if (detail.fraudSummary.topFlags.isNotEmpty) ...[
              const SizedBox(height: 16),
              const Text(
                'Top Flags',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF123B67),
                ),
              ),
              const SizedBox(height: 8),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: detail.fraudSummary.topFlags
                    .take(6)
                    .map(
                      (flag) => _SignalChip(
                        label: '${flag.label} (${flag.total})',
                        color: _fraudRiskColor(flag.severity ?? 'medium'),
                      ),
                    )
                    .toList(),
              ),
            ],
            const SizedBox(height: 16),
            const Text(
              'Siswa Tindak Lanjut',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: Color(0xFF123B67),
              ),
            ),
            const SizedBox(height: 8),
            if (detail.fraudSummary.followUpStudents.isEmpty)
              const Text(
                'Belum ada siswa yang perlu tindak lanjut dari hasil fraud monitoring kelas ini.',
                style: TextStyle(
                  fontSize: 12,
                  color: Color(0xFF66758A),
                ),
              )
            else
              Column(
                children: detail.fraudSummary.followUpStudents
                    .take(5)
                    .map(
                      (student) => ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(
                          student.studentName,
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF123B67),
                          ),
                        ),
                        subtitle: Text(
                          [
                            if ((student.studentIdentifier ?? '')
                                .trim()
                                .isNotEmpty)
                              student.studentIdentifier!,
                            'warning ${student.warningAttempts}',
                            'pra-cek ${student.precheckWarningAttempts}',
                            'submit ${student.submitWarningAttempts}',
                          ].join('  |  '),
                          style: const TextStyle(
                            fontSize: 11,
                            color: Color(0xFF66758A),
                          ),
                        ),
                        trailing: Text(
                          _formatDateTime(student.lastAssessmentAt),
                          textAlign: TextAlign.right,
                          style: const TextStyle(
                            fontSize: 10,
                            color: Color(0xFF66758A),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    )
                    .toList(),
              ),
            const SizedBox(height: 16),
            const Text(
              'Assessment Terbaru',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: Color(0xFF123B67),
              ),
            ),
            const SizedBox(height: 8),
            if (detail.fraudAssessments.isEmpty)
              const Text(
                'Belum ada assessment fraud terbaru untuk ditampilkan.',
                style: TextStyle(
                  fontSize: 12,
                  color: Color(0xFF66758A),
                ),
              )
            else
              Column(
                children: detail.fraudAssessments
                    .map((assessment) => _FraudAssessmentCard(
                          assessment: assessment,
                          validationColor:
                              _fraudValidationColor(assessment.validationStatus),
                          formattedAt: _formatDateTime(assessment.createdAt),
                        ))
                    .toList(),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildSecuritySection(WaliClassDetailData detail) {
    final summary = detail.securityStudentSummary;

    return _WaliSection(
      title: 'Siswa Keamanan & Kasus',
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Ringkasan ini memakai endpoint security-students dan security-cases. Daftar siswa difokuskan ke status yang perlu dibuatkan kasus atau pelanggaran lanjutan.',
              style: TextStyle(
                fontSize: 12,
                color: Color(0xFF66758A),
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _CompactMetricCard(
                  label: 'Perlu Tindak',
                  value: '${summary.studentsNeedFollowUp}',
                  color: const Color(0xFFD97706),
                ),
                _CompactMetricCard(
                  label: 'Kasus Aktif',
                  value: '${summary.totalOpenCases}',
                  color: const Color(0xFF2563EB),
                ),
                _CompactMetricCard(
                  label: 'Selesai',
                  value: '${summary.totalResolvedCases}',
                  color: const Color(0xFF16A34A),
                ),
                _CompactMetricCard(
                  label: 'Lanjutan',
                  value: '${summary.studentsWithRepeatViolation}',
                  color: const Color(0xFFB42318),
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Text(
              'Siswa Keamanan',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: Color(0xFF123B67),
              ),
            ),
            const SizedBox(height: 8),
            if (detail.securityStudents.isEmpty)
              const Text(
                'Belum ada siswa yang perlu dibuatkan kasus keamanan pada filter aktif.',
                style: TextStyle(
                  fontSize: 12,
                  color: Color(0xFF66758A),
                ),
              )
            else
              Column(
                children: detail.securityStudents
                    .map(
                      (student) => _SecurityStudentCard(
                        student: student,
                        statusColor:
                            _securityStatusColor(student.operationalStatus),
                        formattedAt: _formatDateTime(student.latestActivityAt),
                      ),
                    )
                    .toList(),
              ),
            const SizedBox(height: 16),
            const Text(
              'Kasus Aktif',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w800,
                color: Color(0xFF123B67),
              ),
            ),
            const SizedBox(height: 8),
            if (detail.activeSecurityCases.isEmpty)
              const Text(
                'Belum ada kasus keamanan aktif untuk kelas ini.',
                style: TextStyle(
                  fontSize: 12,
                  color: Color(0xFF66758A),
                ),
              )
            else
              Column(
                children: detail.activeSecurityCases
                    .map(
                      (securityCase) => _SecurityCaseCard(
                        securityCase: securityCase,
                        statusColor: _caseStatusColor(securityCase.status),
                        priorityColor:
                            _casePriorityColor(securityCase.priority),
                        formattedAt: _formatDateTime(securityCase.updatedAt),
                      ),
                    )
                    .toList(),
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess) {
      return AccessDeniedScaffold(
        title: _pageTitle,
        message:
            'Detail monitoring kelas hanya tersedia untuk role Super Admin, Wali Kelas, atau Wakasek Kesiswaan.',
      );
    }

    final detail = _detail;

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: Text(_pageTitle),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: RefreshIndicator(
        onRefresh: _loadDetail,
        color: AppColors.primary,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_errorMessage != null)
              _WaliDetailErrorState(
                message: _errorMessage!,
                onRetry: _loadDetail,
              )
            else if (detail == null)
              const _WaliDetailEmptyState()
            else ...[
              Container(
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
                    Text(
                      detail.namaKelas,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      [
                        if ((detail.tingkatNama ?? '').trim().isNotEmpty)
                          detail.tingkatNama!,
                        '${detail.jumlahSiswa} siswa',
                      ].join('  |  '),
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.84),
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    if (_academicContextLabel.trim().isNotEmpty &&
                        _academicContextLabel != '-') ...[
                      const SizedBox(height: 8),
                      Text(
                        'Tahun ajaran: $_academicContextLabel',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.95),
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  _MetricCard(
                    label: 'Hadir',
                    value: '${detail.hadirHariIni}',
                    color: const Color(0xFF16A34A),
                  ),
                  const SizedBox(width: 8),
                  _MetricCard(
                    label: 'Tidak Hadir',
                    value: '${detail.tidakHadirHariIni}',
                    color: const Color(0xFFDC2626),
                  ),
                  const SizedBox(width: 8),
                  _MetricCard(
                    label: 'Pending',
                    value: '${detail.izinPending}',
                    color: const Color(0xFFF59E0B),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              _WaliSection(
                title: 'Statistik Bulan Ini',
                child: Column(
                  children: [
                    _InfoRow(
                      label: 'Persentase Kehadiran',
                      value:
                          '${detail.persentaseKehadiran.toStringAsFixed(2)}%',
                    ),
                    _InfoRow(
                      label: 'Total Hadir',
                      value: '${detail.totalHadir}',
                    ),
                    _InfoRow(
                      label: 'Total Tidak Hadir',
                      value: '${detail.totalTidakHadir}',
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              _buildFraudSection(detail),
              const SizedBox(height: 16),
              _buildSecuritySection(detail),
              const SizedBox(height: 16),
              _WaliSection(
                title: 'Absensi Hari Ini',
                child: detail.absensiDetail.isEmpty
                    ? const Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'Belum ada detail absensi untuk hari ini.',
                          style: TextStyle(
                            fontSize: 13,
                            color: Color(0xFF66758A),
                          ),
                        ),
                      )
                    : Column(
                        children: detail.absensiDetail
                            .map(
                              (row) => ListTile(
                                title: Text(
                                  row.nama,
                                  style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF123B67),
                                  ),
                                ),
                                subtitle: Text(
                                  [
                                    if ((row.nisn ?? '').trim().isNotEmpty)
                                      row.nisn!,
                                    if ((row.keterangan ?? '').trim().isNotEmpty)
                                      row.keterangan!,
                                    if ((row.warningSummary ?? '').trim().isNotEmpty)
                                      'warning: ${row.warningSummary!}',
                                  ].join('  |  ').trim(),
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Color(0xFF66758A),
                                  ),
                                ),
                                trailing: Column(
                                  mainAxisSize: MainAxisSize.min,
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 10,
                                        vertical: 6,
                                      ),
                                      decoration: BoxDecoration(
                                        color:
                                            _statusColor(row.status).withValues(
                                          alpha: 0.12,
                                        ),
                                        borderRadius: BorderRadius.circular(999),
                                      ),
                                      child: Text(
                                        row.status.toUpperCase(),
                                        style: TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w700,
                                          color: _statusColor(row.status),
                                        ),
                                      ),
                                    ),
                                    if (row.hasWarning) ...[
                                      const SizedBox(height: 6),
                                      Container(
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 10,
                                          vertical: 6,
                                        ),
                                        decoration: BoxDecoration(
                                          color: _fraudValidationColor(
                                            row.validationStatus,
                                          ).withValues(alpha: 0.10),
                                          borderRadius:
                                              BorderRadius.circular(999),
                                          border: Border.all(
                                            color: _fraudValidationColor(
                                              row.validationStatus,
                                            ).withValues(alpha: 0.14),
                                          ),
                                        ),
                                        child: Text(
                                          'WARNING ${row.fraudFlagsCount}',
                                          style: TextStyle(
                                            fontSize: 10,
                                            fontWeight: FontWeight.w800,
                                            color: _fraudValidationColor(
                                              row.validationStatus,
                                            ),
                                          ),
                                        ),
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                            )
                            .toList(),
                      ),
              ),
              const SizedBox(height: 16),
              _WaliSection(
                title: 'Pengajuan Izin Kelas',
                child: detail.izinList.isEmpty
                    ? const Padding(
                        padding: EdgeInsets.all(16),
                        child: Text(
                          'Belum ada pengajuan izin di kelas ini.',
                          style: TextStyle(
                            fontSize: 13,
                            color: Color(0xFF66758A),
                          ),
                        ),
                      )
                    : Column(
                        children: detail.izinList
                            .map(
                              (leave) => ListTile(
                                onTap: () => _openLeaveDetail(leave.id),
                                title: Text(
                                  leave.studentName,
                                  style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF123B67),
                                  ),
                                ),
                                subtitle: Text(
                                  '${_resolveLeaveLabel(leave)}  |  ${_formatDate(leave.tanggalMulai)} - ${_formatDate(leave.tanggalSelesai)}',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Color(0xFF66758A),
                                  ),
                                ),
                                trailing: Row(
                                  mainAxisSize: MainAxisSize.min,
                                  children: [
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 10,
                                        vertical: 6,
                                      ),
                                      decoration: BoxDecoration(
                                        color:
                                            _statusColor(leave.status)
                                                .withValues(alpha: 0.12),
                                        borderRadius:
                                            BorderRadius.circular(999),
                                      ),
                                      child: Text(
                                        _resolveLeaveStatusLabel(leave),
                                        style: TextStyle(
                                          fontSize: 11,
                                          fontWeight: FontWeight.w700,
                                          color: _statusColor(leave.status),
                                        ),
                                      ),
                                    ),
                                    const SizedBox(width: 8),
                                    const Icon(
                                      Icons.arrow_forward_ios_rounded,
                                      size: 16,
                                      color: Color(0xFF7B8EA8),
                                    ),
                                  ],
                                ),
                              ),
                            )
                            .toList(),
                      ),
              ),
              if (detail.students.isNotEmpty) ...[
                const SizedBox(height: 16),
                _WaliSection(
                  title: 'Daftar Siswa',
                  child: Column(
                    children: detail.students
                        .map(
                          (student) => ListTile(
                            title: Text(
                              (student['nama_lengkap'] ?? '-').toString(),
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w700,
                                color: Color(0xFF123B67),
                              ),
                            ),
                            subtitle: Text(
                              (student['nisn'] ?? '-').toString(),
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF66758A),
                              ),
                            ),
                          ),
                        )
                        .toList(),
                  ),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _MetricCard({
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: color.withValues(alpha: 0.18)),
          boxShadow: [
            BoxShadow(
              color: color.withValues(alpha: 0.08),
              blurRadius: 16,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              value,
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: color,
              ),
            ),
            const SizedBox(height: 6),
            Text(
              label,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: Color(0xFF66758A),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CompactMetricCard extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _CompactMetricCard({
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minWidth: 88),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.14)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: Color(0xFF66758A),
            ),
          ),
        ],
      ),
    );
  }
}

class _WaliSection extends StatelessWidget {
  final String title;
  final Widget child;

  const _WaliSection({
    required this.title,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 10),
            child: Text(
              title,
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: Color(0xFF123B67),
              ),
            ),
          ),
          const Divider(height: 1, color: Color(0xFFE6EEF8)),
          child,
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  final String label;
  final String value;

  const _InfoRow({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF66758A),
              ),
            ),
          ),
          const SizedBox(width: 12),
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
    );
  }
}

class _StatusPill extends StatelessWidget {
  final String label;
  final Color color;

  const _StatusPill({
    required this.label,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.16)),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: color,
        ),
      ),
    );
  }
}

class _SignalChip extends StatelessWidget {
  final String label;
  final Color color;

  const _SignalChip({
    required this.label,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: 0.12)),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: color,
        ),
      ),
    );
  }
}

class _FraudAssessmentCard extends StatelessWidget {
  final WaliFraudAssessment assessment;
  final Color validationColor;
  final String formattedAt;

  const _FraudAssessmentCard({
    required this.assessment,
    required this.validationColor,
    required this.formattedAt,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FBFF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFDCE8F8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      assessment.studentName,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF123B67),
                      ),
                    ),
                    if ((assessment.studentIdentifier ?? '').trim().isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          assessment.studentIdentifier!,
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF66758A),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Text(
                formattedAt,
                textAlign: TextAlign.right,
                style: const TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF66758A),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _StatusPill(
                label: assessment.validationStatusLabel,
                color: validationColor,
              ),
              _SignalChip(
                label: '${assessment.fraudFlagsCount} signal',
                color: const Color(0xFF475467),
              ),
            ],
          ),
          if ((assessment.warningSummary ?? assessment.decisionReason ?? '')
              .trim()
              .isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              assessment.warningSummary ?? assessment.decisionReason!,
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFF344054),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          if ((assessment.recommendedAction ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              'Tindak lanjut: ${assessment.recommendedAction!}',
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFF66758A),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          if (assessment.signalLabels.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
                children: assessment.signalLabels
                    .take(6)
                    .map(
                      (label) => _SignalChip(
                        label: label,
                        color: validationColor,
                      ),
                    )
                    .toList(),
            ),
          ],
        ],
      ),
    );
  }
}

class _SecurityStudentCard extends StatelessWidget {
  final WaliSecurityStudentRow student;
  final Color statusColor;
  final String formattedAt;

  const _SecurityStudentCard({
    required this.student,
    required this.statusColor,
    required this.formattedAt,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FBFF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFDCE8F8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      student.studentName,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF123B67),
                      ),
                    ),
                    if ((student.studentIdentifier ?? '').trim().isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          student.studentIdentifier!,
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF66758A),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Text(
                formattedAt,
                textAlign: TextAlign.right,
                style: const TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF66758A),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _StatusPill(
                label: student.operationalStatusLabel,
                color: statusColor,
              ),
              if ((student.violationSequenceLabel ?? '').trim().isNotEmpty)
                _SignalChip(
                  label: student.violationSequenceLabel!,
                  color: statusColor,
                ),
              _SignalChip(
                label: '${student.totalWarnings} warning',
                color: const Color(0xFFD97706),
              ),
              _SignalChip(
                label: '${student.openCases} kasus aktif',
                color: const Color(0xFF2563EB),
              ),
            ],
          ),
          if ((student.lastEventLabel ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              'Event terakhir: ${student.lastEventLabel!}',
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFF344054),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          if ((student.recommendation ?? '').trim().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              'Rekomendasi: ${student.recommendation!}',
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFF66758A),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          if (student.topIssues.isNotEmpty) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: student.topIssues
                  .take(5)
                  .map(
                    (issue) => _SignalChip(
                      label: '${issue.label} (${issue.total})',
                      color: statusColor,
                    ),
                  )
                  .toList(),
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _SignalChip(
                label: 'event ${student.securityEventsCount}',
                color: const Color(0xFF475467),
              ),
              _SignalChip(
                label: 'fraud ${student.fraudAssessmentsCount}',
                color: const Color(0xFF475467),
              ),
              if (student.mockLocationEvents > 0)
                _SignalChip(
                  label: 'fake GPS ${student.mockLocationEvents}',
                  color: const Color(0xFFB42318),
                ),
              if (student.deviceEvents > 0)
                _SignalChip(
                  label: 'device ${student.deviceEvents}',
                  color: const Color(0xFFB42318),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SecurityCaseCard extends StatelessWidget {
  final WaliSecurityCase securityCase;
  final Color statusColor;
  final Color priorityColor;
  final String formattedAt;

  const _SecurityCaseCard({
    required this.securityCase,
    required this.statusColor,
    required this.priorityColor,
    required this.formattedAt,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF9FBFF),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFDCE8F8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      securityCase.caseNumber,
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF123B67),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      securityCase.studentName,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF123B67),
                      ),
                    ),
                    if ((securityCase.studentIdentifier ?? '').trim().isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          securityCase.studentIdentifier!,
                          style: const TextStyle(
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF66758A),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              Text(
                formattedAt,
                textAlign: TextAlign.right,
                style: const TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF66758A),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _StatusPill(
                label: securityCase.statusLabel,
                color: statusColor,
              ),
              _SignalChip(
                label: securityCase.priorityLabel,
                color: priorityColor,
              ),
              _SignalChip(
                label: '${securityCase.itemsCount} item',
                color: const Color(0xFF475467),
              ),
              _SignalChip(
                label: '${securityCase.evidenceCount} bukti',
                color: const Color(0xFF475467),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            securityCase.summary,
            style: const TextStyle(
              fontSize: 12,
              color: Color(0xFF344054),
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _WaliDetailErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _WaliDetailErrorState({
    required this.message,
    required this.onRetry,
  });

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 40),
        child: Column(
          children: [
            const Icon(
              Icons.error_outline_rounded,
              size: 40,
              color: Color(0xFFB42318),
            ),
            const SizedBox(height: 12),
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 13,
                color: Color(0xFF344054),
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
      ),
    );
  }
}

class _WaliDetailEmptyState extends StatelessWidget {
  const _WaliDetailEmptyState();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: const Column(
        children: [
          Icon(Icons.analytics_outlined, size: 42, color: Color(0xFF7B8EA8)),
          SizedBox(height: 12),
          Text(
            'Data monitoring belum tersedia',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          SizedBox(height: 6),
          Text(
            'Coba muat ulang layar ini setelah data absensi dan fraud assessment tersedia.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              color: Color(0xFF66758A),
            ),
          ),
        ],
      ),
    );
  }
}
