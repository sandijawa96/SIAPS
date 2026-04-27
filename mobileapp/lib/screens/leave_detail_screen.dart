import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../providers/auth_provider.dart';
import '../services/leave_service.dart';
import '../utils/constants.dart';

class LeaveDetailScreen extends StatefulWidget {
  final int leaveId;

  const LeaveDetailScreen({
    super.key,
    required this.leaveId,
  });

  @override
  State<LeaveDetailScreen> createState() => _LeaveDetailScreenState();
}

class _LeaveDetailScreenState extends State<LeaveDetailScreen> {
  final LeaveService _service = LeaveService();

  bool _isLoading = true;
  bool _isSubmitting = false;
  String? _errorMessage;
  LeaveItem? _item;

  @override
  void initState() {
    super.initState();
    _loadDetail();
  }

  Future<void> _loadDetail() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final response = await _service.getById(widget.leaveId);
    if (!mounted) {
      return;
    }

    setState(() {
      _item = response.data;
      _errorMessage = response.success ? null : response.message;
      _isLoading = false;
    });
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

  Widget _buildPendingReviewNotice(LeaveItem item) {
    final state = (item.pendingReviewState ?? '').trim().toLowerCase();
    final label = (item.pendingReviewLabel ?? '').trim();
    if (label.isEmpty || (state != 'overdue' && state != 'due_today')) {
      return const SizedBox.shrink();
    }

    final isOverdue = state == 'overdue';

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: isOverdue ? const Color(0xFFFFF1F2) : const Color(0xFFFFFBEB),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isOverdue ? const Color(0xFFFDA4AF) : const Color(0xFFFCD34D),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.schedule_outlined,
            color: isOverdue ? const Color(0xFFBE123C) : const Color(0xFF92400E),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w800,
                    color: isOverdue ? const Color(0xFF9F1239) : const Color(0xFF92400E),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  isOverdue
                      ? 'Pengajuan masih pending, tetapi tanggal mulai sudah lewat. Approval tetap bisa dilakukan secara retroaktif.'
                      : 'Periode izin mulai hari ini. Review sebaiknya diproses segera.',
                  style: TextStyle(
                    fontSize: 12,
                    height: 1.45,
                    color: isOverdue ? const Color(0xFF9F1239) : const Color(0xFF92400E),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  bool _isPdfDocument(String? source) {
    final raw = source?.trim().toLowerCase();
    return raw != null && raw.endsWith('.pdf');
  }

  Future<void> _openAttachmentUrl(String source) async {
    final uri = Uri.tryParse(source.trim());
    if (uri == null) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Lampiran tidak valid')),
      );
      return;
    }

    final launched = await launchUrl(
      uri,
      mode: LaunchMode.externalApplication,
    );

    if (!launched && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Gagal membuka lampiran')),
      );
    }
  }

  Future<void> _showImagePreviewDialog(String imageUrl) async {
    await showDialog<void>(
      context: context,
      builder: (context) {
        return Dialog(
          insetPadding: const EdgeInsets.all(16),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(18),
            child: Container(
              color: Colors.black,
              constraints: const BoxConstraints(maxHeight: 640),
              child: Stack(
                children: [
                  Positioned.fill(
                    child: InteractiveViewer(
                      minScale: 0.8,
                      maxScale: 4,
                      child: Image.network(
                        imageUrl,
                        fit: BoxFit.contain,
                        errorBuilder: (_, __, ___) => const Center(
                          child: Icon(
                            Icons.broken_image_outlined,
                            size: 40,
                            color: Colors.white70,
                          ),
                        ),
                      ),
                    ),
                  ),
                  Positioned(
                    top: 8,
                    right: 8,
                    child: IconButton.filledTonal(
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close),
                    ),
                  ),
                ],
              ),
            ),
          ),
        );
      },
    );
  }

  Widget _buildEvidencePreview(String? imageUrl) {
    if (imageUrl == null || imageUrl.trim().isEmpty) {
      return const _DetailRow(label: 'Lampiran Pendukung', value: 'Tidak ada');
    }

    if (_isPdfDocument(imageUrl)) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: const Color(0xFFFFFBEB),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFFCD34D)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Row(
                children: [
                  Icon(Icons.picture_as_pdf_outlined, color: Color(0xFFB4232C)),
                  SizedBox(width: 8),
                  Text(
                    'Lampiran PDF tersedia',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF123B67),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              const Text(
                'PDF dibuka melalui aplikasi browser atau viewer bawaan perangkat.',
                style: TextStyle(
                  fontSize: 12,
                  height: 1.45,
                  color: Color(0xFF92400E),
                ),
              ),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: () => _openAttachmentUrl(imageUrl),
                icon: const Icon(Icons.open_in_new_rounded),
                label: const Text('Buka PDF'),
              ),
              const SizedBox(height: 10),
              SelectableText(
                imageUrl,
                style: const TextStyle(
                  fontSize: 11,
                  color: Color(0xFF64748B),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          InkWell(
            onTap: () => _showImagePreviewDialog(imageUrl),
            borderRadius: BorderRadius.circular(16),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(16),
              child: AspectRatio(
                aspectRatio: 1,
                child: Image.network(
                  imageUrl,
                  fit: BoxFit.cover,
                  loadingBuilder: (context, child, progress) {
                    if (progress == null) {
                      return child;
                    }

                    return Container(
                      color: const Color(0xFFF8FAFC),
                      alignment: Alignment.center,
                      child: const CircularProgressIndicator(strokeWidth: 2),
                    );
                  },
                  errorBuilder: (_, __, ___) => Container(
                    color: const Color(0xFFF8FAFC),
                    alignment: Alignment.center,
                    child: const Icon(
                      Icons.broken_image_outlined,
                      size: 36,
                      color: Color(0xFF94A3B8),
                    ),
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),
          OutlinedButton.icon(
            onPressed: () => _showImagePreviewDialog(imageUrl),
            icon: const Icon(Icons.zoom_in_rounded),
            label: const Text('Lihat gambar penuh'),
          ),
          const SizedBox(height: 10),
          SelectableText(
            imageUrl,
            style: const TextStyle(
              fontSize: 11,
              color: Color(0xFF64748B),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _approve() async {
    final item = _item;
    if (item == null || _isSubmitting) {
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    final response = await _service.approve(item.id);
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(response.message)));

    setState(() {
      _isSubmitting = false;
    });

    if (response.success) {
      Navigator.of(context).pop(true);
    }
  }

  Future<void> _reject() async {
    final item = _item;
    if (item == null || _isSubmitting) {
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

    if (note == null || note.isEmpty || !mounted) {
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    final response = await _service.reject(item.id, note: note);
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(response.message)));

    setState(() {
      _isSubmitting = false;
    });

    if (response.success) {
      Navigator.of(context).pop(true);
    }
  }

  Future<void> _cancel() async {
    final item = _item;
    if (item == null || _isSubmitting) {
      return;
    }

    final shouldCancel = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Batalkan Pengajuan'),
          content: const Text('Pengajuan izin yang masih pending akan dihapus. Lanjutkan?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Tidak'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              style: FilledButton.styleFrom(backgroundColor: const Color(0xFFB4232C)),
              child: const Text('Batalkan'),
            ),
          ],
        );
      },
    );

    if (shouldCancel != true || !mounted) {
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    final response = await _service.cancel(item.id);
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(response.message)));

    setState(() {
      _isSubmitting = false;
    });

    if (response.success) {
      Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    final currentUser = authProvider.user;
    final item = _item;
    final isOwner = item != null && currentUser != null && item.userId == currentUser.id;
    final canApprove = item != null && currentUser?.canApproveStudentLeave == true && !isOwner && item.status.toLowerCase() == 'pending';
    final canCancel = item != null && isOwner && item.status.toLowerCase() == 'pending';
    final statusColor = item != null ? _statusColor(item.status) : const Color(0xFFF59E0B);

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Detail Izin'),
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
              _LeaveDetailErrorState(message: _errorMessage!, onRetry: _loadDetail)
            else if (item == null)
              const _LeaveDetailEmptyState()
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
                    Row(
                      children: [
                        Expanded(
                            child: Text(
                            _resolveJenisLabel(item),
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 18,
                              fontWeight: FontWeight.w800,
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                          decoration: BoxDecoration(
                            color: statusColor.withValues(alpha: 0.18),
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            _resolveStatusLabel(item),
                            style: TextStyle(
                              color: statusColor,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '${_formatDate(item.tanggalMulai)} - ${_formatDate(item.tanggalSelesai)}',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.85),
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        _DetailPill(label: item.userName ?? 'Pengguna'),
                        if ((item.kelasNama ?? '').trim().isNotEmpty) _DetailPill(label: item.kelasNama!),
                        if (item.createdAt != null) _DetailPill(label: 'Dibuat ${_formatDate(item.createdAt)}'),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              if ((item.pendingReviewLabel ?? '').trim().isNotEmpty) ...[
                _buildPendingReviewNotice(item),
                const SizedBox(height: 16),
              ],
              _DetailSection(
                title: 'Ringkasan',
                children: [
                  _DetailRow(label: 'Pemohon', value: item.userName ?? '-'),
                  _DetailRow(label: 'Kelas', value: item.kelasNama ?? '-'),
                  _DetailRow(label: 'Jenis izin', value: _resolveJenisLabel(item)),
                  _DetailRow(label: 'Periode', value: '${_formatDate(item.tanggalMulai)} - ${_formatDate(item.tanggalSelesai)}'),
                  _DetailRow(label: 'Rentang diajukan', value: '${item.requestedDayCount} hari kalender'),
                  _DetailRow(label: 'Hari sekolah terdampak', value: '${item.schoolDaysAffected} hari'),
                  _DetailRow(label: 'Hari non-sekolah dilewati', value: '${item.nonWorkingDaysSkipped} hari'),
                ],
              ),
              const SizedBox(height: 16),
              _DetailSection(
                title: 'Lampiran Pendukung',
                children: [
                  _buildEvidencePreview(item.dokumenPendukung),
                  if ((item.evidenceHint ?? '').trim().isNotEmpty)
                    _DetailRow(
                      label: 'Catatan lampiran',
                      value: item.evidenceHint!.trim(),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              _DetailSection(
                title: 'Alasan',
                children: [
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
                    child: Text(
                      item.alasan,
                      style: const TextStyle(
                        fontSize: 14,
                        height: 1.5,
                        color: Color(0xFF334155),
                      ),
                    ),
                  ),
                ],
              ),
              if ((item.approvalNotes ?? '').trim().isNotEmpty || (item.approvedByName ?? '').trim().isNotEmpty || (item.rejectedByName ?? '').trim().isNotEmpty) ...[
                const SizedBox(height: 16),
                _DetailSection(
                  title: 'Catatan Approval',
                  children: [
                    if ((item.approvedByName ?? '').trim().isNotEmpty) _DetailRow(label: 'Disetujui oleh', value: item.approvedByName!),
                    if ((item.rejectedByName ?? '').trim().isNotEmpty) _DetailRow(label: 'Ditolak oleh', value: item.rejectedByName!),
                    if ((item.approvalNotes ?? '').trim().isNotEmpty) _DetailRow(label: 'Catatan', value: item.approvalNotes!),
                  ],
                ),
              ],
              if (canCancel || canApprove) ...[
                const SizedBox(height: 18),
                if (canCancel)
                  FilledButton.icon(
                    onPressed: _isSubmitting ? null : _cancel,
                    style: FilledButton.styleFrom(backgroundColor: const Color(0xFFB4232C)),
                    icon: const Icon(Icons.delete_outline),
                    label: const Text('Batalkan Pengajuan'),
                  ),
                if (canApprove)
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: _isSubmitting ? null : _reject,
                          style: OutlinedButton.styleFrom(foregroundColor: const Color(0xFFB4232C)),
                          child: const Text('Tolak'),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: FilledButton(
                          onPressed: _isSubmitting ? null : _approve,
                          child: const Text('Setujui'),
                        ),
                      ),
                    ],
                  ),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class _DetailPill extends StatelessWidget {
  final String label;

  const _DetailPill({required this.label});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class _DetailSection extends StatelessWidget {
  final String title;
  final List<Widget> children;

  const _DetailSection({
    required this.title,
    required this.children,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w800,
            color: Color(0xFF123B67),
          ),
        ),
        const SizedBox(height: 10),
        Container(
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: const Color(0xFFD8E6F8)),
          ),
          child: Column(children: children),
        ),
      ],
    );
  }
}

class _DetailRow extends StatelessWidget {
  final String label;
  final String value;

  const _DetailRow({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      title: Text(
        label,
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: Color(0xFF66758A),
        ),
      ),
      subtitle: Text(
        value,
        style: const TextStyle(
          fontSize: 14,
          fontWeight: FontWeight.w700,
          color: Color(0xFF123B67),
        ),
      ),
    );
  }
}

class _LeaveDetailErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _LeaveDetailErrorState({
    required this.message,
    required this.onRetry,
  });

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
          const Icon(Icons.error_outline, size: 42, color: Color(0xFFB4232C)),
          const SizedBox(height: 12),
          const Text(
            'Gagal memuat detail izin',
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

class _LeaveDetailEmptyState extends StatelessWidget {
  const _LeaveDetailEmptyState();

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
          Icon(Icons.assignment_late_outlined, size: 42, color: Color(0xFF7B8EA8)),
          SizedBox(height: 12),
          Text(
            'Detail izin tidak tersedia',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
        ],
      ),
    );
  }
}
