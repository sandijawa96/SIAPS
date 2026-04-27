import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../services/manual_attendance_service.dart';
import '../widgets/access_denied_scaffold.dart';
import 'manual_attendance_editor_screen.dart';

String _formatCorrectionDate(DateTime? value) {
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

DateTime _todayOnly() {
  final now = DateTime.now();
  return DateTime(now.year, now.month, now.day);
}

class ManualAttendanceCorrectionScreen extends StatefulWidget {
  const ManualAttendanceCorrectionScreen({super.key});

  @override
  State<ManualAttendanceCorrectionScreen> createState() =>
      _ManualAttendanceCorrectionScreenState();
}

class _ManualAttendanceCorrectionScreenState
    extends State<ManualAttendanceCorrectionScreen> {
  final ManualAttendanceService _service = ManualAttendanceService();
  final TextEditingController _searchController = TextEditingController();

  bool _hasAccess = false;
  bool _isLoading = true;
  bool _isLoadingMore = false;
  String? _errorMessage;
  DateTime _selectedDate = _todayOnly();
  List<ManualAttendanceEntry> _items = const <ManualAttendanceEntry>[];
  int _currentPage = 1;
  int _lastPage = 1;
  int _totalItems = 0;
  String _statusFilter = '';

  @override
  void initState() {
    super.initState();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_hasAccess) {
      return;
    }

    _hasAccess = context.read<AuthProvider>().user?.canManageManualAttendance ?? false;
    if (_hasAccess) {
      _loadData();
    } else {
      _isLoading = false;
      _errorMessage = 'Halaman ini hanya tersedia untuk pengelola absensi.';
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  bool get _hasMoreItems => _currentPage < _lastPage;

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2024, 1, 1),
      lastDate: _todayOnly(),
      helpText: 'Pilih tanggal koreksi',
    );

    if (picked == null || !mounted) {
      return;
    }

    setState(() {
      _selectedDate = DateTime(picked.year, picked.month, picked.day);
    });

    await _loadData();
  }

  Future<void> _loadData({bool loadMore = false}) async {
    final targetPage = loadMore ? _currentPage + 1 : 1;

    setState(() {
      if (loadMore) {
        _isLoadingMore = true;
      } else {
        _isLoading = true;
        _errorMessage = null;
      }
    });

    final response = await _service.getCorrectionPage(
      date: _selectedDate,
      search: _searchController.text.trim(),
      status: _statusFilter.trim().isEmpty ? null : _statusFilter,
      page: targetPage,
    );

    if (!mounted) {
      return;
    }

    setState(() {
      if (response.success && response.data != null) {
        final page = response.data!;
        _items = loadMore ? <ManualAttendanceEntry>[..._items, ...page.items] : page.items;
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

  Future<void> _openEditor(ManualAttendanceEntry item) async {
    final changed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => ManualAttendanceEditorScreen(attendance: item),
      ),
    );

    if (changed == true) {
      await _loadData();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess && !_isLoading) {
      return AccessDeniedScaffold(
        title: 'Koreksi Absensi',
        message: _errorMessage ?? 'Akses ditolak.',
      );
    }

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Koreksi Absensi'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: RefreshIndicator(
        onRefresh: () => _loadData(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            _buildFilterCard(),
            const SizedBox(height: 12),
            if (_isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 56),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_errorMessage != null)
              _CorrectionStateCard(
                icon: Icons.error_outline,
                title: 'Gagal memuat data',
                message: _errorMessage!,
                actionLabel: 'Coba Lagi',
                onTap: _loadData,
              )
            else if (_items.isEmpty)
              const _CorrectionStateCard(
                icon: Icons.inbox_outlined,
                title: 'Belum ada data',
                message: 'Tidak ditemukan data absensi pada filter yang dipilih.',
              )
            else ...[
              Text(
                '$_totalItems data ditemukan',
                style: const TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: Color(0xFF66758A),
                ),
              ),
              const SizedBox(height: 8),
              ..._items.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _CorrectionItemCard(
                    item: item,
                    onTap: () => _openEditor(item),
                  ),
                ),
              ),
              if (_hasMoreItems) ...[
                const SizedBox(height: 4),
                OutlinedButton.icon(
                  onPressed: _isLoadingMore ? null : () => _loadData(loadMore: true),
                  icon: _isLoadingMore
                      ? const SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.expand_more_rounded),
                  label: const Text('Muat lebih banyak'),
                ),
              ],
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildFilterCard() {
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
            'Filter koreksi',
            style: TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _searchController,
            decoration: const InputDecoration(
              labelText: 'Cari nama / NIS / username',
              prefixIcon: Icon(Icons.search_rounded),
            ),
            onSubmitted: (_) => _loadData(),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: _pickDate,
                  icon: const Icon(Icons.calendar_today_outlined),
                  label: Text(_formatCorrectionDate(_selectedDate)),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: DropdownButtonFormField<String>(
                  value: _statusFilter,
                  decoration: const InputDecoration(labelText: 'Status'),
                  items: const [
                    DropdownMenuItem(value: '', child: Text('Semua')),
                    DropdownMenuItem(value: 'hadir', child: Text('Hadir')),
                    DropdownMenuItem(value: 'terlambat', child: Text('Terlambat')),
                    DropdownMenuItem(value: 'izin', child: Text('Izin')),
                    DropdownMenuItem(value: 'sakit', child: Text('Sakit')),
                    DropdownMenuItem(value: 'alpha', child: Text('Alpha')),
                  ],
                  onChanged: (value) {
                    setState(() {
                      _statusFilter = value ?? '';
                    });
                    _loadData();
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: () {
                    _searchController.clear();
                    setState(() {
                      _statusFilter = '';
                      _selectedDate = _todayOnly();
                    });
                    _loadData();
                  },
                  child: const Text('Reset'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton(
                  onPressed: _loadData,
                  child: const Text('Terapkan'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CorrectionItemCard extends StatelessWidget {
  final ManualAttendanceEntry item;
  final VoidCallback onTap;

  const _CorrectionItemCard({
    required this.item,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    final sourceColor = switch (item.source) {
      'manual' => const Color(0xFF2563EB),
      'auto_alpha' => const Color(0xFFDC2626),
      'leave_approval' => const Color(0xFF7C3AED),
      _ => const Color(0xFF059669),
    };

    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Ink(
          padding: const EdgeInsets.all(16),
          decoration: BoxDecoration(
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
                      item.userName ?? 'Siswa',
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: Color(0xFF123B67),
                      ),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                    decoration: BoxDecoration(
                      color: sourceColor.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(999),
                    ),
                    child: Text(
                      item.sourceLabel,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        color: sourceColor,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                [
                  if ((item.userIdentifier ?? '').trim().isNotEmpty)
                    item.userIdentifier!.trim(),
                  if ((item.className ?? '').trim().isNotEmpty)
                    item.className!.trim(),
                  _formatCorrectionDate(item.date),
                ].join('  |  '),
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
                  _InfoChip(label: 'Status', value: item.statusLabel),
                  _InfoChip(label: 'Masuk', value: item.checkInTime ?? '-'),
                  _InfoChip(label: 'Pulang', value: item.checkOutTime ?? '-'),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _InfoChip extends StatelessWidget {
  final String label;
  final String value;

  const _InfoChip({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFC),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        '$label: $value',
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: Color(0xFF334155),
        ),
      ),
    );
  }
}

class _CorrectionStateCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final Future<void> Function()? onTap;

  const _CorrectionStateCard({
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onTap,
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
          Icon(icon, size: 40, color: const Color(0xFF64748B)),
          const SizedBox(height: 10),
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
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: Color(0xFF66758A),
              height: 1.45,
            ),
          ),
          if (actionLabel != null && onTap != null) ...[
            const SizedBox(height: 14),
            FilledButton(
              onPressed: onTap,
              child: Text(actionLabel!),
            ),
          ],
        ],
      ),
    );
  }
}
