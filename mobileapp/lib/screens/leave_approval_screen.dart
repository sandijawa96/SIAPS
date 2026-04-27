import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/leave_service.dart';
import '../utils/constants.dart';
import 'leave_detail_screen.dart';

class LeaveApprovalScreen extends StatefulWidget {
  const LeaveApprovalScreen({super.key});

  @override
  State<LeaveApprovalScreen> createState() => _LeaveApprovalScreenState();
}

class _LeaveApprovalScreenState extends State<LeaveApprovalScreen> {
  final LeaveService _leaveService = LeaveService();

  bool _hasAccess = false;
  bool _accessChecked = false;
  bool _isLoading = true;
  bool _isLoadingMore = false;
  String? _errorMessage;
  List<LeaveItem> _items = const <LeaveItem>[];
  final Set<int> _processingIds = <int>{};
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
    _hasAccess = context.read<AuthProvider>().user?.canApproveStudentLeave ?? false;
    if (_hasAccess) {
      _loadQueue();
      return;
    }

    _isLoading = false;
    _errorMessage = 'Halaman ini hanya untuk role approver izin siswa.';
  }

  bool get _hasMoreItems => _currentPage < _lastPage;

  Future<void> _loadQueue({bool loadMore = false}) async {
    final targetPage = loadMore ? _currentPage + 1 : 1;

    setState(() {
      if (loadMore) {
        _isLoadingMore = true;
      } else {
        _isLoading = true;
        _errorMessage = null;
      }
    });

    final response = await _leaveService.getStudentApprovalQueuePage(page: targetPage);
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
  }

  Future<void> _loadMoreQueue() async {
    if (_isLoading || _isLoadingMore || !_hasMoreItems) {
      return;
    }

    await _loadQueue(loadMore: true);

    if (!mounted || _errorMessage == null) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(_errorMessage!)),
    );
  }

  Future<void> _approve(LeaveItem item) async {
    if (_processingIds.contains(item.id)) {
      return;
    }

    setState(() {
      _processingIds.add(item.id);
    });

    final response = await _leaveService.approve(item.id);
    if (!mounted) {
      return;
    }

    if (response.success) {
      setState(() {
        _items = _items.where((row) => row.id != item.id).toList();
        _processingIds.remove(item.id);
      });

      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(response.message)));
      unawaited(_reloadQueueSilently());
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(response.message)));

    setState(() {
      _processingIds.remove(item.id);
    });
  }

  Future<void> _reject(LeaveItem item) async {
    if (_processingIds.contains(item.id)) {
      return;
    }

    final controller = TextEditingController();
    final note = await showDialog<String>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Tolak Pengajuan'),
          content: TextField(
            controller: controller,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Catatan penolakan',
              hintText: 'Tuliskan alasan penolakan',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(controller.text.trim()),
              child: const Text('Tolak'),
            ),
          ],
        );
      },
    );

    if (note == null || note.isEmpty) {
      return;
    }

    setState(() {
      _processingIds.add(item.id);
    });

    final response = await _leaveService.reject(item.id, note: note);
    if (!mounted) {
      return;
    }

    if (response.success) {
      setState(() {
        _items = _items.where((row) => row.id != item.id).toList();
        _processingIds.remove(item.id);
      });

      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(response.message)));
      unawaited(_reloadQueueSilently());
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(response.message)));

    setState(() {
      _processingIds.remove(item.id);
    });
  }

  Future<void> _openDetail(LeaveItem item) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => LeaveDetailScreen(leaveId: item.id),
      ),
    );

    if (changed == true) {
      await _loadQueue();
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

  Widget _buildPendingReviewBadge(LeaveItem item) {
    final state = (item.pendingReviewState ?? '').trim().toLowerCase();
    final label = (item.pendingReviewLabel ?? '').trim();
    if (label.isEmpty || (state != 'overdue' && state != 'due_today')) {
      return const SizedBox.shrink();
    }

    final isOverdue = state == 'overdue';

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: isOverdue ? const Color(0xFFFFF1F2) : const Color(0xFFFFFBEB),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(
          color: isOverdue ? const Color(0xFFFDA4AF) : const Color(0xFFFCD34D),
        ),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: isOverdue ? const Color(0xFFBE123C) : const Color(0xFF92400E),
        ),
      ),
    );
  }

  bool _isPdfDocument(String? source) {
    final raw = source?.trim().toLowerCase();
    return raw != null && raw.endsWith('.pdf');
  }

  Future<void> _reloadQueueSilently() async {
    final response = await _leaveService.getStudentApprovalQueuePage(
      page: 1,
      perPage: _totalItems > 30 ? _totalItems : 30,
    );
    if (!mounted) {
      return;
    }

    setState(() {
      if (response.success && response.data != null) {
        final page = response.data!;
        _items = page.items;
        _currentPage = page.currentPage;
        _lastPage = page.lastPage;
        _totalItems = page.total;
        _errorMessage = null;
      } else if (_items.isEmpty) {
        _errorMessage = response.message;
      }
    });
  }

  Widget _buildEvidenceThumbnail(String? imageUrl) {
    if (imageUrl == null || imageUrl.trim().isEmpty) {
      return Container(
        width: 96,
        height: 96,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFD8E6F8)),
          color: const Color(0xFFF8FAFC),
        ),
        child: const Icon(
          Icons.image_not_supported_outlined,
          color: Color(0xFF94A3B8),
        ),
      );
    }

    if (_isPdfDocument(imageUrl)) {
      return Container(
        width: 96,
        height: 96,
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFD8E6F8)),
          color: const Color(0xFFFFFBEB),
        ),
        child: const Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.picture_as_pdf_outlined, color: Color(0xFFB4232C)),
            SizedBox(height: 6),
            Text(
              'PDF',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w700,
                color: Color(0xFF92400E),
              ),
            ),
          ],
        ),
      );
    }

    return ClipRRect(
      borderRadius: BorderRadius.circular(14),
      child: Container(
        width: 96,
        height: 96,
        decoration: BoxDecoration(
          border: Border.all(color: const Color(0xFFD8E6F8)),
          borderRadius: BorderRadius.circular(14),
        ),
        child: Image.network(
          imageUrl,
          fit: BoxFit.cover,
          errorBuilder: (_, __, ___) => Container(
            color: const Color(0xFFF8FAFC),
            alignment: Alignment.center,
            child: const Icon(
              Icons.broken_image_outlined,
              color: Color(0xFF94A3B8),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNoAccessScaffold() {
    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Persetujuan Izin'),
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
                'Persetujuan izin hanya untuk Super Admin, Admin, Wakasek Kesiswaan, dan Wali Kelas.',
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
        title: const Text('Persetujuan Izin'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: RefreshIndicator(
        onRefresh: _loadQueue,
        color: AppColors.primary,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: const Color(0xFFD8E6F8)),
              ),
              child: Text(
                'Approval izin siswa mengikuti guard backend terbaru: Super Admin, Admin, Wakasek Kesiswaan, dan Wali Kelas.',
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF123B67),
                ),
              ),
            ),
            const SizedBox(height: 16),
            if (_isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_errorMessage != null)
              _ApprovalErrorState(message: _errorMessage!, onRetry: _loadQueue)
            else if (_items.isEmpty)
              const _ApprovalEmptyState()
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
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      item.userName ?? 'Siswa',
                                      style: const TextStyle(
                                        fontSize: 15,
                                        fontWeight: FontWeight.w700,
                                        color: Color(0xFF123B67),
                                      ),
                                    ),
                                    const SizedBox(height: 4),
                                    Text(
                                      item.kelasNama ?? '-',
                                      style: const TextStyle(
                                        fontSize: 12,
                                        fontWeight: FontWeight.w600,
                                        color: Color(0xFF66758A),
                                      ),
                                    ),
                                    const SizedBox(height: 10),
                                    Text(
                                      _resolveJenisLabel(item),
                                      style: const TextStyle(
                                        fontSize: 13,
                                        fontWeight: FontWeight.w700,
                                        color: Color(0xFF2563EB),
                                      ),
                                    ),
                                    const SizedBox(height: 6),
                                    Text(
                                      '${_formatDate(item.tanggalMulai)} - ${_formatDate(item.tanggalSelesai)}',
                                      style: const TextStyle(
                                        fontSize: 13,
                                        color: Color(0xFF66758A),
                                      ),
                                    ),
                                    const SizedBox(height: 8),
                                    Text(
                                      item.alasan,
                                      style: const TextStyle(
                                        fontSize: 13,
                                        color: Color(0xFF334155),
                                      ),
                                    ),
                                    const SizedBox(height: 10),
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
                                        if ((item.pendingReviewLabel ?? '').trim().isNotEmpty)
                                          _buildPendingReviewBadge(item),
                                        if ((item.evidenceHint ?? '').trim().isNotEmpty)
                                          Container(
                                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                                            decoration: BoxDecoration(
                                              color: const Color(0xFFF8FAFC),
                                              borderRadius: BorderRadius.circular(999),
                                              border: Border.all(color: const Color(0xFFE2E8F0)),
                                            ),
                                            child: Text(
                                              item.evidenceRequired ? 'Lampiran wajib' : 'Lampiran opsional',
                                              style: const TextStyle(
                                                fontSize: 11,
                                                fontWeight: FontWeight.w700,
                                                color: Color(0xFF475569),
                                              ),
                                            ),
                                          ),
                                      ],
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 14),
                              _buildEvidenceThumbnail(item.dokumenPendukung),
                            ],
                          ),
                          const SizedBox(height: 14),
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: _processingIds.contains(item.id) ? null : () => _reject(item),
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: const Color(0xFFB4232C),
                                  ),
                                  child: _processingIds.contains(item.id)
                                      ? const SizedBox(
                                          width: 18,
                                          height: 18,
                                          child: CircularProgressIndicator(strokeWidth: 2),
                                        )
                                      : const Text('Tolak'),
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: FilledButton(
                                  onPressed: _processingIds.contains(item.id) ? null : () => _approve(item),
                                  child: _processingIds.contains(item.id)
                                      ? const SizedBox(
                                          width: 18,
                                          height: 18,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                                          ),
                                        )
                                      : const Text('Setujui'),
                                ),
                              ),
                            ],
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
                            ? 'Menampilkan ${_items.length} dari $_totalItems izin pending'
                            : 'Menampilkan ${_items.length} izin pending',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF66758A),
                        ),
                      ),
                      if (_hasMoreItems || _isLoadingMore) ...[
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: _isLoadingMore ? null : _loadMoreQueue,
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

class _ApprovalErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _ApprovalErrorState({required this.message, required this.onRetry});

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

class _ApprovalEmptyState extends StatelessWidget {
  const _ApprovalEmptyState();

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
          Icon(Icons.inbox_outlined, size: 42, color: Color(0xFF7B8EA8)),
          SizedBox(height: 12),
          Text(
            'Tidak ada izin menunggu review',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          SizedBox(height: 6),
          Text(
            'Pengajuan izin siswa yang menunggu persetujuan akan tampil di sini.',
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
