import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/leave_service.dart';
import '../utils/constants.dart';
import 'leave_detail_screen.dart';
import 'quick_submission_screen.dart';

class StudentLeaveScreen extends StatefulWidget {
  const StudentLeaveScreen({super.key});

  @override
  State<StudentLeaveScreen> createState() => _StudentLeaveScreenState();
}

class _StudentLeaveScreenState extends State<StudentLeaveScreen> {
  final LeaveService _leaveService = LeaveService();

  bool _hasAccess = false;
  bool _accessChecked = false;
  bool _isLoading = true;
  bool _isLoadingMore = false;
  String? _errorMessage;
  List<LeaveItem> _items = const <LeaveItem>[];
  int _currentPage = 1;
  int _lastPage = 1;
  int _totalItems = 0;

  @override
  void initState() {
    super.initState();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_accessChecked) {
      return;
    }

    _accessChecked = true;
    _hasAccess = context.read<AuthProvider>().user?.isSiswa ?? false;
    if (_hasAccess) {
      _loadLeaves();
      return;
    }

    _isLoading = false;
    _errorMessage = 'Halaman ini hanya tersedia untuk siswa.';
  }

  bool get _hasMoreItems => _currentPage < _lastPage;

  Future<void> _loadLeaves({bool loadMore = false}) async {
    final targetPage = loadMore ? _currentPage + 1 : 1;

    setState(() {
      if (loadMore) {
        _isLoadingMore = true;
      } else {
        _isLoading = true;
        _errorMessage = null;
      }
    });

    final response = await _leaveService.getOwnLeavesPage(page: targetPage);
    if (!mounted) {
      return;
    }

    setState(() {
      if (response.success && response.data != null) {
        final page = response.data!;
        _items = loadMore
            ? <LeaveItem>[..._items, ...page.items]
            : page.items;
        _currentPage = page.currentPage;
        _lastPage = page.lastPage;
        _totalItems = page.total;
        _errorMessage = null;
      } else {
        _errorMessage = response.message;
      }

      _isLoading = false;
      _isLoadingMore = false;
    });

    if (loadMore || response.success) {
      return;
    }
  }

  Future<void> _loadMoreLeaves() async {
    if (_isLoading || _isLoadingMore || !_hasMoreItems) {
      return;
    }

    await _loadLeaves(loadMore: true);

    if (!mounted || _errorMessage == null) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(_errorMessage!)),
    );
  }

  Future<void> _openSubmissionForm() async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => const QuickSubmissionScreen()),
    );
    await _loadLeaves();
  }

  Future<void> _openDetail(LeaveItem item) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => LeaveDetailScreen(leaveId: item.id),
      ),
    );

    if (changed == true) {
      await _loadLeaves();
    }
  }

  Color _statusColor(String status) {
    switch (status.toLowerCase()) {
      case 'approved':
        return const Color(0xFF16A34A);
      case 'rejected':
        return const Color(0xFFDC2626);
      default:
        return const Color(0xFFF59E0B);
    }
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

  String _resolveJenisLabel(LeaveItem item) {
    final label = item.jenisIzinLabel?.trim();
    if (label != null && label.isNotEmpty) {
      return label;
    }

    return item.jenisIzin.replaceAll('_', ' ');
  }

  String _resolveStatusLabel(LeaveItem item) {
    final label = item.statusLabel?.trim();
    if (label != null && label.isNotEmpty) {
      return label;
    }

    switch (item.status.trim().toLowerCase()) {
      case 'approved':
        return 'Disetujui';
      case 'rejected':
        return 'Ditolak';
      default:
        return 'Menunggu Persetujuan';
    }
  }

  Widget _buildNoAccessScaffold() {
    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Izin Saya'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.lock_outline, size: 44, color: Color(0xFF64748B)),
              const SizedBox(height: 12),
              const Text(
                'Akses ditolak',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF123B67),
                ),
              ),
              const SizedBox(height: 6),
              const Text(
                'Halaman izin siswa hanya bisa diakses oleh akun siswa.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Color(0xFF64748B)),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () => Navigator.of(context).maybePop(),
                icon: const Icon(Icons.arrow_back),
                label: const Text('Kembali'),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess) {
      return _buildNoAccessScaffold();
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Izin Saya'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      floatingActionButton: FloatingActionButton(
        onPressed: _openSubmissionForm,
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        child: const Icon(Icons.add),
      ),
      body: RefreshIndicator(
        onRefresh: _loadLeaves,
        color: AppColors.primary,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                gradient: const LinearGradient(
                  colors: [Color(0xFF0C4A7A), Color(0xFF64B5F6)],
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(20),
              ),
              child: Row(
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.16),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(Icons.assignment_outlined, color: Colors.white),
                  ),
                  const SizedBox(width: 14),
                  const Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Pengajuan Izin Siswa',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        SizedBox(height: 4),
                        Text(
                          'Pantau status pengajuan dan buat izin baru dari menu ini.',
                          style: TextStyle(
                            color: Colors.white70,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (_isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_errorMessage != null)
              _StudentLeaveErrorState(message: _errorMessage!, onRetry: _loadLeaves)
            else if (_items.isEmpty)
              _StudentLeaveEmptyState(onCreate: _openSubmissionForm)
            else
              ..._items.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(18),
                    onTap: () => _openDetail(item),
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
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  _resolveJenisLabel(item),
                                  style: const TextStyle(
                                    fontSize: 15,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF123B67),
                                  ),
                                ),
                              ),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                decoration: BoxDecoration(
                                  color: _statusColor(item.status).withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(999),
                                ),
                                child: Text(
                                  _resolveStatusLabel(item),
                                  style: TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                    color: _statusColor(item.status),
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            '${_formatDate(item.tanggalMulai)} - ${_formatDate(item.tanggalSelesai)}',
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF66758A),
                            ),
                          ),
                          const SizedBox(height: 8),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                decoration: BoxDecoration(
                                  color: const Color(0xFFF8FBFF),
                                  borderRadius: BorderRadius.circular(999),
                                  border: Border.all(color: const Color(0xFFD8E6F8)),
                                ),
                                child: Text(
                                  '${item.schoolDaysAffected} hari sekolah terdampak',
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF123B67),
                                  ),
                                ),
                              ),
                              if (item.nonWorkingDaysSkipped > 0)
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFFFFFBEB),
                                    borderRadius: BorderRadius.circular(999),
                                    border: Border.all(color: const Color(0xFFFCD34D)),
                                  ),
                                  child: Text(
                                    '${item.nonWorkingDaysSkipped} hari non-sekolah dilewati',
                                    style: const TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w700,
                                      color: Color(0xFF92400E),
                                    ),
                                  ),
                                ),
                            ],
                          ),
                          const SizedBox(height: 8),
                          Text(
                            item.alasan,
                            maxLines: 3,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 13,
                              color: Color(0xFF334155),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            if (_items.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: const Color(0xFFD8E6F8)),
                  ),
                  child: Column(
                    children: [
                      Text(
                        _totalItems > 0
                            ? 'Menampilkan ${_items.length} dari $_totalItems pengajuan'
                            : 'Menampilkan ${_items.length} pengajuan',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF66758A),
                        ),
                      ),
                      if (_hasMoreItems || _isLoadingMore) ...[
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: _isLoadingMore ? null : _loadMoreLeaves,
                          icon: _isLoadingMore
                              ? const SizedBox(
                                  width: 16,
                                  height: 16,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.expand_more_rounded),
                          label: Text(
                            _isLoadingMore ? 'Memuat...' : 'Muat lebih banyak',
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _StudentLeaveErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _StudentLeaveErrorState({required this.message, required this.onRetry});

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

class _StudentLeaveEmptyState extends StatelessWidget {
  final VoidCallback onCreate;

  const _StudentLeaveEmptyState({required this.onCreate});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: Column(
        children: [
          const Icon(Icons.assignment_turned_in_outlined, size: 42, color: Color(0xFF7B8EA8)),
          const SizedBox(height: 12),
          const Text(
            'Belum ada pengajuan izin',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Gunakan tombol tambah untuk membuat pengajuan izin baru.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              color: Color(0xFF66758A),
            ),
          ),
          const SizedBox(height: 14),
          FilledButton.icon(
            onPressed: onCreate,
            icon: const Icon(Icons.add),
            label: const Text('Ajukan izin'),
          ),
        ],
      ),
    );
  }
}
