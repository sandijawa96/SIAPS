import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../services/manual_attendance_service.dart';
import '../widgets/access_denied_scaffold.dart';

String _formatPendingDate(DateTime? value) {
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

DateTime _pendingToday() {
  final now = DateTime.now();
  return DateTime(now.year, now.month, now.day);
}

class ManualAttendancePendingCheckoutScreen extends StatefulWidget {
  const ManualAttendancePendingCheckoutScreen({super.key});

  @override
  State<ManualAttendancePendingCheckoutScreen> createState() =>
      _ManualAttendancePendingCheckoutScreenState();
}

class _ManualAttendancePendingCheckoutScreenState
    extends State<ManualAttendancePendingCheckoutScreen> {
  final ManualAttendanceService _service = ManualAttendanceService();

  bool _hasAccess = false;
  bool _isLoading = true;
  bool _isLoadingMore = false;
  bool _includeOverdue = false;
  String? _errorMessage;
  List<ManualAttendanceEntry> _items = const <ManualAttendanceEntry>[];
  int _currentPage = 1;
  int _lastPage = 1;
  int _totalItems = 0;

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

  bool get _hasMoreItems => _currentPage < _lastPage;

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

    final response = await _service.getPendingCheckoutPage(
      page: targetPage,
      includeOverdue: _includeOverdue,
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

  Future<void> _openResolveDialog(ManualAttendanceEntry item) async {
    final authUser = context.read<AuthProvider>().user;
    final isOverdue = item.date != null &&
        item.date!.isBefore(_pendingToday().subtract(const Duration(days: 1)));

    final changed = await showDialog<bool>(
      context: context,
      builder: (context) {
        return _ResolveCheckoutDialog(
          item: item,
          canOverride: authUser?.canOverrideManualAttendanceBackdate ?? false,
          isOverdue: isOverdue,
          onSubmit: (payload) async {
            final response = await _service.resolveCheckout(item.id, payload);
            if (!mounted) {
              return false;
            }

            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text(response.message)),
            );

            return response.success;
          },
        );
      },
    );

    if (changed == true) {
      await _loadData();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess && !_isLoading) {
      return AccessDeniedScaffold(
        title: 'Lupa Tap-Out',
        message: _errorMessage ?? 'Akses ditolak.',
      );
    }

    final user = context.watch<AuthProvider>().user;
    final canOverride = user?.canOverrideManualAttendanceBackdate ?? false;

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Lupa Tap-Out'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: RefreshIndicator(
        onRefresh: () => _loadData(),
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
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
                    'Tindak lanjut cepat',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF123B67),
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Default menampilkan data H+1. Jika Anda memiliki izin override, backlog lebih lama bisa ikut ditampilkan.',
                    style: TextStyle(
                      fontSize: 12,
                      height: 1.45,
                      color: Color(0xFF66758A),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  if (canOverride) ...[
                    const SizedBox(height: 12),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Tampilkan backlog di atas H+1'),
                      subtitle: const Text('Gunakan hanya saat memang perlu koreksi yang lebih lama.'),
                      value: _includeOverdue,
                      onChanged: (value) {
                        setState(() {
                          _includeOverdue = value;
                        });
                        _loadData();
                      },
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 12),
            if (_isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 56),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_errorMessage != null)
              _PendingStateCard(
                icon: Icons.error_outline,
                title: 'Gagal memuat daftar',
                message: _errorMessage!,
                actionLabel: 'Coba Lagi',
                onTap: _loadData,
              )
            else if (_items.isEmpty)
              const _PendingStateCard(
                icon: Icons.check_circle_outline,
                title: 'Tidak ada backlog',
                message: 'Tidak ditemukan data lupa tap-out pada filter saat ini.',
              )
            else ...[
              Text(
                '$_totalItems data perlu tindak lanjut',
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
                  child: _PendingCheckoutCard(
                    item: item,
                    onResolve: () => _openResolveDialog(item),
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
}

class _PendingCheckoutCard extends StatelessWidget {
  final ManualAttendanceEntry item;
  final VoidCallback onResolve;

  const _PendingCheckoutCard({
    required this.item,
    required this.onResolve,
  });

  @override
  Widget build(BuildContext context) {
    final today = _pendingToday();
    final isOverdue = item.date != null &&
        item.date!.isBefore(today.subtract(const Duration(days: 1)));

    return Container(
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
                  item.userName ?? 'Siswa',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF123B67),
                  ),
                ),
              ),
              if (isOverdue)
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFEE2E2),
                    borderRadius: BorderRadius.circular(999),
                  ),
                  child: const Text(
                    'Overdue',
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFFB91C1C),
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
              _formatPendingDate(item.date),
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
              _PendingChip(label: 'Status', value: item.statusLabel),
              _PendingChip(label: 'Jam Masuk', value: item.checkInTime ?? '-'),
            ],
          ),
          const SizedBox(height: 14),
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: onResolve,
              icon: const Icon(Icons.task_alt_outlined),
              label: const Text('Selesaikan Tap-Out'),
            ),
          ),
        ],
      ),
    );
  }
}

class _PendingChip extends StatelessWidget {
  final String label;
  final String value;

  const _PendingChip({
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

class _PendingStateCard extends StatelessWidget {
  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final Future<void> Function()? onTap;

  const _PendingStateCard({
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

class _ResolveCheckoutDialog extends StatefulWidget {
  final ManualAttendanceEntry item;
  final bool canOverride;
  final bool isOverdue;
  final Future<bool> Function(PendingCheckoutResolutionPayload payload) onSubmit;

  const _ResolveCheckoutDialog({
    required this.item,
    required this.canOverride,
    required this.isOverdue,
    required this.onSubmit,
  });

  @override
  State<_ResolveCheckoutDialog> createState() => _ResolveCheckoutDialogState();
}

class _ResolveCheckoutDialogState extends State<_ResolveCheckoutDialog> {
  final TextEditingController _reasonController = TextEditingController();
  final TextEditingController _noteController = TextEditingController();
  final TextEditingController _overrideReasonController = TextEditingController();
  bool _isSubmitting = false;
  String _status = 'hadir';
  String? _checkOutTime;

  @override
  void initState() {
    super.initState();
    _status = widget.item.status.trim().toLowerCase();
  }

  @override
  void dispose() {
    _reasonController.dispose();
    _noteController.dispose();
    _overrideReasonController.dispose();
    super.dispose();
  }

  Future<void> _pickTime() async {
    final initial = _checkOutTime != null && _checkOutTime!.contains(':')
        ? TimeOfDay(
            hour: int.tryParse(_checkOutTime!.split(':').first) ?? 15,
            minute: int.tryParse(_checkOutTime!.split(':').last) ?? 0,
          )
        : const TimeOfDay(hour: 15, minute: 0);

    final picked = await showTimePicker(
      context: context,
      initialTime: initial,
      helpText: 'Pilih jam pulang',
    );

    if (picked == null || !mounted) {
      return;
    }

    setState(() {
      _checkOutTime =
          '${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}';
    });
  }

  Future<void> _submit() async {
    if (_isSubmitting) {
      return;
    }

    if ((_checkOutTime ?? '').isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Jam pulang wajib diisi.')),
      );
      return;
    }

    if (_reasonController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Alasan tindakan wajib diisi.')),
      );
      return;
    }

    if (widget.isOverdue &&
        widget.canOverride &&
        _overrideReasonController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Alasan override wajib diisi untuk backlog di atas H+1.')),
      );
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    final success = await widget.onSubmit(
      PendingCheckoutResolutionPayload(
        checkOutTime: _checkOutTime!,
        reason: _reasonController.text.trim(),
        overrideReason: _overrideReasonController.text.trim(),
        status: _status,
        note: _noteController.text.trim(),
      ),
    );

    if (!mounted) {
      return;
    }

    setState(() {
      _isSubmitting = false;
    });

    if (success) {
      Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Selesaikan Tap-Out'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                widget.item.userName ?? 'Siswa',
                style: const TextStyle(
                  fontWeight: FontWeight.w800,
                  color: Color(0xFF123B67),
                ),
              ),
            ),
            const SizedBox(height: 4),
            Align(
              alignment: Alignment.centerLeft,
              child: Text(
                'Tanggal ${_formatPendingDate(widget.item.date)}',
                style: const TextStyle(
                  fontSize: 12,
                  color: Color(0xFF66758A),
                ),
              ),
            ),
            const SizedBox(height: 12),
            OutlinedButton.icon(
              onPressed: _pickTime,
              icon: const Icon(Icons.logout_rounded),
              label: Text(_checkOutTime ?? 'Pilih Jam Pulang'),
            ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              value: _status,
              decoration: const InputDecoration(labelText: 'Status akhir'),
              items: const [
                DropdownMenuItem(value: 'hadir', child: Text('Hadir')),
                DropdownMenuItem(value: 'terlambat', child: Text('Terlambat')),
                DropdownMenuItem(value: 'izin', child: Text('Izin')),
                DropdownMenuItem(value: 'sakit', child: Text('Sakit')),
                DropdownMenuItem(value: 'alpha', child: Text('Alpha')),
              ],
              onChanged: (value) {
                if (value == null) {
                  return;
                }
                setState(() {
                  _status = value;
                });
              },
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _reasonController,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Alasan tindakan',
                hintText: 'Contoh: Wali kelas sudah konfirmasi jam pulang siswa',
              ),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _noteController,
              minLines: 2,
              maxLines: 4,
              decoration: const InputDecoration(
                labelText: 'Keterangan',
                hintText: 'Catatan tambahan jika diperlukan',
              ),
            ),
            if (widget.isOverdue && widget.canOverride) ...[
              const SizedBox(height: 12),
              TextField(
                controller: _overrideReasonController,
                minLines: 2,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'Alasan override H+N',
                  hintText: 'Wajib diisi untuk backlog lebih lama dari H+1',
                ),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _isSubmitting ? null : () => Navigator.of(context).pop(),
          child: const Text('Batal'),
        ),
        FilledButton(
          onPressed: _isSubmitting ? null : _submit,
          child: _isSubmitting
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    color: Colors.white,
                  ),
                )
              : const Text('Simpan'),
        ),
      ],
    );
  }
}
