import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/dashboard_service.dart';
import '../services/wali_kelas_service.dart';
import '../utils/constants.dart';
import '../widgets/access_denied_scaffold.dart';
import 'wali_class_detail_screen.dart';

class WaliClassOverviewScreen extends StatefulWidget {
  const WaliClassOverviewScreen({super.key});

  @override
  State<WaliClassOverviewScreen> createState() => _WaliClassOverviewScreenState();
}

class _WaliClassOverviewScreenState extends State<WaliClassOverviewScreen> {
  final DashboardService _dashboardService = DashboardService();
  final WaliKelasService _service = WaliKelasService();

  bool _hasAccess = false;
  String _pageTitle = 'Monitoring Kelas';
  bool _isLoading = true;
  String? _errorMessage;
  String _academicContextLabel = '-';
  List<WaliClassSummary> _classes = const <WaliClassSummary>[];

  @override
  void initState() {
    super.initState();
    final user = context.read<AuthProvider>().user;
    _hasAccess = user?.canOpenAttendanceMonitoringMenu ?? false;
    _pageTitle = user?.attendanceMonitoringMenuTitle ?? 'Monitoring Kelas';
    if (_hasAccess) {
      _loadClasses();
    }
  }

  Future<void> _loadClasses() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final contextResponse = await _dashboardService.getAcademicContext();
    final response = await _service.getMyClasses();
    if (!mounted) {
      return;
    }

    setState(() {
      _academicContextLabel = contextResponse.success && contextResponse.data != null
          ? contextResponse.data!.compactLabel
          : '-';
      _classes = response.data ?? const <WaliClassSummary>[];
      _errorMessage = response.success ? null : response.message;
      _isLoading = false;
    });
  }

  Future<void> _openClassDetail(WaliClassSummary kelas) async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => WaliClassDetailScreen(classId: kelas.id),
      ),
    );
    await _loadClasses();
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess) {
      return AccessDeniedScaffold(
        title: _pageTitle,
        message:
            'Monitoring kelas hanya tersedia untuk role Super Admin, Wali Kelas, atau Wakasek Kesiswaan.',
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: Text(_pageTitle),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: RefreshIndicator(
        onRefresh: _loadClasses,
        color: AppColors.primary,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_academicContextLabel.trim().isNotEmpty &&
                _academicContextLabel != '-') ...[
              Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF4FF),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: const Color(0xFFD1E5FB)),
                ),
                child: Text(
                  'Tahun ajaran: $_academicContextLabel',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF2A5C8E),
                  ),
                ),
              ),
            ],
            Container(
              margin: const EdgeInsets.only(bottom: 12),
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFD8E6F8)),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.shield_outlined,
                    size: 20,
                    color: Color(0xFF0C4A7A),
                  ),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Buka detail kelas untuk melihat absensi, izin, fraud monitoring, siswa keamanan, dan kasus aktif.',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF4A5B72),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            if (_isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_errorMessage != null)
              _WaliClassErrorState(message: _errorMessage!, onRetry: _loadClasses)
            else if (_classes.isEmpty)
              const _WaliClassEmptyState()
            else
              ..._classes.map(
                (kelas) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(18),
                    onTap: () => _openClassDetail(kelas),
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(color: const Color(0xFFD8E6F8)),
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            kelas.namaKelas,
                            style: const TextStyle(
                              fontSize: 16,
                              fontWeight: FontWeight.w700,
                              color: Color(0xFF123B67),
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            [
                              if ((kelas.tingkatNama ?? '').trim().isNotEmpty) kelas.tingkatNama!,
                              '${kelas.jumlahSiswa} siswa',
                            ].join('  |  '),
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF66758A),
                            ),
                          ),
                          const SizedBox(height: 14),
                          Row(
                            children: [
                              _MetricChip(
                                label: 'Hadir',
                                value: '${kelas.hadirHariIni}',
                                color: const Color(0xFF16A34A),
                              ),
                              const SizedBox(width: 8),
                              _MetricChip(
                                label: 'Tidak Hadir',
                                value: '${kelas.tidakHadirHariIni}',
                                color: const Color(0xFFDC2626),
                              ),
                              const SizedBox(width: 8),
                              _MetricChip(
                                label: 'Pending',
                                value: '${kelas.izinPending}',
                                color: const Color(0xFFF59E0B),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _MetricChip extends StatelessWidget {
  final String label;
  final String value;
  final Color color;

  const _MetricChip({
    required this.label,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(12),
        ),
        child: Column(
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
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: Color(0xFF66758A),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _WaliClassErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _WaliClassErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 40),
        child: Column(
          children: [
            const Icon(Icons.error_outline, size: 40, color: Color(0xFFB4232C)),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
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

class _WaliClassEmptyState extends StatelessWidget {
  const _WaliClassEmptyState();

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
          Icon(Icons.groups_2_outlined, size: 42, color: Color(0xFF7B8EA8)),
          SizedBox(height: 12),
          Text(
            'Belum ada kelas binaan',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          SizedBox(height: 6),
          Text(
            'Daftar kelas akan muncul jika akun ini terdaftar sebagai wali kelas.',
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
