import 'dart:io';
import '../services/leave_service.dart';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:path/path.dart' as path;
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../utils/constants.dart';

class QuickSubmissionScreen extends StatefulWidget {
  const QuickSubmissionScreen({Key? key}) : super(key: key);

  @override
  State<QuickSubmissionScreen> createState() => _QuickSubmissionScreenState();
}

class _QuickSubmissionScreenState extends State<QuickSubmissionScreen> {
  final TextEditingController _reasonController = TextEditingController();
  final ImagePicker _imagePicker = ImagePicker();
  final LeaveService _leaveService = LeaveService();

  DateTime? _startDate;
  DateTime? _endDate;
  String? _selectedPhotoPath;
  String? _pendingSubmissionRequestId;
  bool _isSubmitting = false;
  _SubmissionType? _selectedType;

  bool _isPdfAttachment(String? filePath) {
    final raw = filePath?.trim().toLowerCase();
    return raw != null && raw.endsWith('.pdf');
  }

  String _attachmentFileName(String filePath) {
    return path.basename(filePath);
  }

  final List<_SubmissionType> _submissionTypes = const [
    _SubmissionType(
      value: 'sakit',
      label: 'Sakit',
      description: 'Tidak masuk karena kondisi kesehatan',
      icon: Icons.local_hospital_outlined,
      color: Color(0xFFE53935),
      reasonHint: 'Contoh: Demam tinggi dan butuh istirahat',
    ),
    _SubmissionType(
      value: 'izin',
      label: 'Izin Pribadi',
      description: 'Keperluan pribadi mendesak di luar sekolah',
      icon: Icons.event_note_outlined,
      color: Color(0xFF1E88E5),
      reasonHint: 'Contoh: Ada urusan pribadi yang tidak bisa ditunda',
    ),
    _SubmissionType(
      value: 'keperluan_keluarga',
      label: 'Urusan Keluarga',
      description: 'Mendampingi atau menghadiri kebutuhan keluarga inti',
      icon: Icons.people_outline,
      color: Color(0xFF00897B),
      reasonHint: 'Contoh: Menghadiri acara keluarga inti',
    ),
    _SubmissionType(
      value: 'dispensasi',
      label: 'Dispensasi Sekolah',
      description: 'Kegiatan resmi dengan persetujuan sekolah',
      icon: Icons.school_outlined,
      color: Color(0xFFFB8C00),
      reasonHint: 'Contoh: Mengikuti lomba yang mewakili sekolah',
    ),
    _SubmissionType(
      value: 'tugas_sekolah',
      label: 'Tugas Sekolah',
      description: 'Penugasan sekolah di luar kelas atau lokasi belajar biasa',
      icon: Icons.assignment_outlined,
      color: Color(0xFF8E24AA),
      reasonHint: 'Contoh: Tugas observasi lapangan dari guru mapel',
    ),
  ];

  @override
  void dispose() {
    _reasonController.dispose();
    super.dispose();
  }

  int get _requestedDayCount {
    final startDate = _startDate;
    final endDate = _endDate;
    if (startDate == null || endDate == null) {
      return 0;
    }

    if (endDate.isBefore(startDate)) {
      return 0;
    }

    return endDate.difference(startDate).inDays + 1;
  }

  bool _isAttachmentRequired(_SubmissionType type) {
    return type.value == 'sakit' && _requestedDayCount > 1;
  }

  String _attachmentHint(_SubmissionType type) {
    final required = _isAttachmentRequired(type);
    switch (type.value) {
      case 'sakit':
        return required
            ? 'Lampiran wajib untuk sakit lebih dari 1 hari. Unggah gambar atau PDF surat/nota pemeriksaan jika ada.'
            : 'Lampiran opsional untuk sakit 1 hari. Jika ada surat/nota pemeriksaan, unggah agar review lebih cepat.';
      case 'dispensasi':
      case 'tugas_sekolah':
        return 'Lampiran opsional. Unggah gambar atau PDF surat tugas, memo, atau bukti kegiatan jika tersedia.';
      default:
        return 'Lampiran opsional. Unggah gambar atau PDF jika diperlukan untuk memperjelas alasan pengajuan.';
    }
  }

  bool get _isFormComplete {
    final selectedType = _selectedType;
    if (selectedType == null) {
      return false;
    }

    final photoReady = !_isAttachmentRequired(selectedType) ||
        (_selectedPhotoPath != null && _selectedPhotoPath!.isNotEmpty);

    return photoReady &&
        _startDate != null &&
        _endDate != null &&
        _reasonController.text.trim().isNotEmpty;
  }

  Future<void> _pickDate({required bool isStartDate}) async {
    final now = DateTime.now();
    final initialDate = isStartDate
        ? (_startDate ?? now)
        : (_endDate ?? _startDate ?? now);
    final firstDate = isStartDate ? now : (_startDate ?? now);

    final picked = await showDatePicker(
      context: context,
      initialDate: initialDate,
      firstDate: DateTime(firstDate.year, firstDate.month, firstDate.day),
      lastDate: now.add(const Duration(days: 365)),
    );

    if (picked == null || !mounted) {
      return;
    }

    setState(() {
      if (isStartDate) {
        _startDate = picked;
        if (_endDate != null && _endDate!.isBefore(_startDate!)) {
          _endDate = _startDate;
        }
      } else {
        _endDate = picked;
      }
      _pendingSubmissionRequestId = null;
    });
  }

  Future<void> _showPhotoSourcePicker() async {
    await showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
      ),
      builder: (context) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.camera_alt_outlined),
                title: const Text('Ambil dari Kamera'),
                onTap: () {
                  Navigator.of(context).pop();
                  _pickPhoto(ImageSource.camera);
                },
              ),
              ListTile(
                leading: const Icon(Icons.photo_library_outlined),
                title: const Text('Pilih dari Galeri'),
                onTap: () {
                  Navigator.of(context).pop();
                  _pickPhoto(ImageSource.gallery);
                },
              ),
              ListTile(
                leading: const Icon(Icons.picture_as_pdf_outlined),
                title: const Text('Pilih File PDF'),
                onTap: () {
                  Navigator.of(context).pop();
                  _pickPdf();
                },
              ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _pickPhoto(ImageSource source) async {
    try {
      final image = await _imagePicker.pickImage(
        source: source,
        imageQuality: 75,
        maxWidth: 1600,
        maxHeight: 1600,
      );

      if (image == null || !mounted) {
        return;
      }

      setState(() {
        _selectedPhotoPath = image.path;
        _pendingSubmissionRequestId = null;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Gagal memilih lampiran'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  Future<void> _pickPdf() async {
    try {
      final result = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['pdf'],
      );

      if (result == null || result.files.isEmpty || !mounted) {
        return;
      }

      final selectedPath = result.files.single.path?.trim();
      if (selectedPath == null || selectedPath.isEmpty) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('File PDF tidak valid'),
            backgroundColor: Colors.red,
          ),
        );
        return;
      }

      setState(() {
        _selectedPhotoPath = selectedPath;
        _pendingSubmissionRequestId = null;
      });
    } catch (_) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Gagal memilih file PDF'),
          backgroundColor: Colors.red,
        ),
      );
    }
  }

  void _removePhoto() {
    setState(() {
      _selectedPhotoPath = null;
      _pendingSubmissionRequestId = null;
    });
  }

  Future<void> _showLocalImagePreview(String imagePath) async {
    await showDialog<void>(
      context: context,
      builder: (context) {
        return Dialog(
          insetPadding: const EdgeInsets.all(16),
          child: ClipRRect(
            borderRadius: BorderRadius.circular(18),
            child: Container(
              color: Colors.black,
              constraints: const BoxConstraints(maxHeight: 560),
              child: Stack(
                children: [
                  Positioned.fill(
                    child: InteractiveViewer(
                      minScale: 0.8,
                      maxScale: 4,
                      child: Image.file(
                        File(imagePath),
                        fit: BoxFit.contain,
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

  String _formatDisplayDate(DateTime date) {
    const months = [
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

    return '${date.day.toString().padLeft(2, '0')} '
        '${months[date.month - 1]} ${date.year}';
  }

  @override
  Widget build(BuildContext context) {
    final canAccess = context.watch<AuthProvider>().user?.isSiswa ?? false;
    if (!canAccess) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Pengajuan Izin'),
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          elevation: 0,
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
                  'Form pengajuan izin hanya tersedia untuk siswa.',
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

    final selectedType = _selectedType;
    final theme = Theme.of(context);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Pengajuan Izin'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFFF3F8FF), Color(0xFFFFFFFF)],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '1. Pilih Jenis Pengajuan',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  'Pilih kategori izin yang paling sesuai dengan kebutuhan Anda.',
                  style: theme.textTheme.bodyMedium?.copyWith(
                    color: Colors.grey[700],
                  ),
                ),
                const SizedBox(height: 16),
                GridView.builder(
                  itemCount: _submissionTypes.length,
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                    childAspectRatio: 1.05,
                  ),
                  itemBuilder: (context, index) {
                    final type = _submissionTypes[index];
                    final isSelected = selectedType?.value == type.value;
                    return _buildSubmissionCard(type, isSelected);
                  },
                ),
                const SizedBox(height: 24),
                Text(
                  '2. Isi Detail Pengajuan',
                  style: theme.textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w700,
                    color: AppColors.primary,
                  ),
                ),
                const SizedBox(height: 12),
                if (selectedType == null)
                  _buildSelectTypeHint()
                else
                  _buildFormCard(selectedType),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildSubmissionCard(_SubmissionType type, bool isSelected) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOut,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isSelected ? type.color : Colors.grey.shade300,
          width: isSelected ? 1.8 : 1,
        ),
        boxShadow: [
          BoxShadow(
            color: isSelected
                ? type.color.withOpacity(0.2)
                : Colors.black.withOpacity(0.04),
            blurRadius: isSelected ? 14 : 8,
            offset: const Offset(0, 4),
          ),
        ],
        color: Colors.white,
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: () {
            setState(() {
              _selectedType = type;
              _pendingSubmissionRequestId = null;
            });
          },
          child: Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: type.color.withOpacity(0.13),
                        borderRadius: BorderRadius.circular(10),
                      ),
                      child: Icon(type.icon, size: 22, color: type.color),
                    ),
                    const Spacer(),
                    Icon(
                      isSelected
                          ? Icons.radio_button_checked
                          : Icons.radio_button_unchecked,
                      color: isSelected ? type.color : Colors.grey.shade400,
                      size: 20,
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Text(
                  type.label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        color: isSelected ? type.color : Colors.black87,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  type.description,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Colors.grey[700],
                      ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildSelectTypeHint() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: Colors.grey.shade300),
      ),
      child: Row(
        children: [
          const Icon(Icons.info_outline, color: AppColors.warning, size: 22),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Pilih salah satu jenis pengajuan terlebih dahulu untuk membuka form.',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Colors.grey[700],
                  ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFormCard(_SubmissionType selectedType) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.grey.shade200),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: selectedType.color.withOpacity(0.13),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  selectedType.icon,
                  size: 24,
                  color: selectedType.color,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      selectedType.label,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      selectedType.description,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: Colors.grey[700],
                          ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          LayoutBuilder(
            builder: (context, constraints) {
              final isWide = constraints.maxWidth >= 560;
              final children = [
                _buildDateInput(
                  label: 'Tanggal Mulai',
                  value: _startDate,
                  onTap: () => _pickDate(isStartDate: true),
                ),
                _buildDateInput(
                  label: 'Tanggal Selesai',
                  value: _endDate,
                  onTap: () => _pickDate(isStartDate: false),
                ),
              ];

              if (!isWide) {
                return Column(
                  children: [
                    children[0],
                    const SizedBox(height: 12),
                    children[1],
                  ],
                );
              }

              return Row(
                children: [
                  Expanded(child: children[0]),
                  const SizedBox(width: 12),
                  Expanded(child: children[1]),
                ],
              );
            },
          ),
          const SizedBox(height: 16),
          _buildImpactSummaryCard(selectedType),
          const SizedBox(height: 16),
          Text(
            'Alasan',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  fontWeight: FontWeight.w600,
                ),
          ),
          const SizedBox(height: 8),
          TextField(
            controller: _reasonController,
            maxLines: 4,
            maxLength: 500,
            textInputAction: TextInputAction.done,
            decoration: InputDecoration(
              hintText: selectedType.reasonHint,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
                borderSide: BorderSide(color: Colors.grey.shade300),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(10),
                borderSide: BorderSide(color: selectedType.color, width: 1.6),
              ),
              counterStyle: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: Colors.grey[600],
                  ),
            ),
            onChanged: (_) => setState(() {
              _pendingSubmissionRequestId = null;
            }),
          ),
          const SizedBox(height: 4),
          _buildAttachmentUploader(selectedType),
          const SizedBox(height: 12),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: !_isFormComplete || _isSubmitting
                  ? null
                  : () => _submitRequest(),
              icon: _isSubmitting
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.2,
                        valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                      ),
                    )
                  : const Icon(Icons.send_rounded),
              label: Text(_isSubmitting ? 'Mengirim...' : 'Ajukan Izin'),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.primary,
                foregroundColor: Colors.white,
                disabledBackgroundColor: Colors.grey.shade400,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildImpactSummaryCard(_SubmissionType selectedType) {
    final requestedDayCount = _requestedDayCount;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FBFF),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(Icons.info_outline, size: 18, color: selectedType.color),
              const SizedBox(width: 8),
              Text(
                'Ringkasan Pengajuan',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: const Color(0xFF123B67),
                    ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _buildSummaryPill(
                label: requestedDayCount > 0
                    ? '$requestedDayCount hari kalender diajukan'
                    : 'Pilih tanggal untuk melihat rentang',
              ),
              _buildSummaryPill(
                label: _isAttachmentRequired(selectedType)
                    ? 'Lampiran wajib'
                    : 'Lampiran opsional',
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Hari non-sekolah tidak akan ditandai saat approval. Pastikan alasan singkat dan jelas agar approver lebih cepat meninjau.',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Colors.grey[700],
                  height: 1.4,
                ),
          ),
        ],
      ),
    );
  }

  Widget _buildSummaryPill({required String label}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
              fontWeight: FontWeight.w600,
              color: const Color(0xFF123B67),
            ),
      ),
    );
  }

  Widget _buildAttachmentUploader(_SubmissionType selectedType) {
    final attachmentRequired = _isAttachmentRequired(selectedType);
    final photoPath = _selectedPhotoPath;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Text(
              'Lampiran Pendukung',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
            ),
            if (attachmentRequired)
              const Text(
                ' *',
                style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold),
              ),
          ],
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          onPressed: _showPhotoSourcePicker,
          icon: const Icon(Icons.upload_file_outlined),
          label: Text(photoPath == null ? 'Unggah Lampiran' : 'Ganti Lampiran'),
          style: OutlinedButton.styleFrom(
            minimumSize: const Size.fromHeight(46),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(10),
            ),
          ),
        ),
        if (photoPath != null) ...[
          const SizedBox(height: 10),
          _isPdfAttachment(photoPath)
              ? Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: const Color(0xFFFCD34D)),
                    color: const Color(0xFFFFFBEB),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 56,
                        height: 56,
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: const Color(0xFFFDE68A)),
                        ),
                        child: const Icon(
                          Icons.picture_as_pdf_outlined,
                          color: Color(0xFFB4232C),
                          size: 28,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _attachmentFileName(photoPath),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'PDF siap diunggah sebagai lampiran pendukung.',
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: Colors.grey[700],
                                  ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        onPressed: _removePhoto,
                        tooltip: 'Hapus lampiran',
                        icon: const Icon(Icons.delete_outline, color: Colors.red),
                      ),
                    ],
                  ),
                )
              : Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.grey.shade300),
                  ),
                  child: Row(
                    children: [
                      InkWell(
                        onTap: () => _showLocalImagePreview(photoPath),
                        borderRadius: BorderRadius.circular(8),
                        child: ClipRRect(
                          borderRadius: BorderRadius.circular(8),
                          child: Image.file(
                            File(photoPath),
                            width: 56,
                            height: 56,
                            fit: BoxFit.cover,
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _attachmentFileName(photoPath),
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              'Ketuk gambar untuk melihat preview lebih besar.',
                              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: Colors.grey[700],
                                  ),
                            ),
                          ],
                        ),
                      ),
                      IconButton(
                        onPressed: _removePhoto,
                        tooltip: 'Hapus lampiran',
                        icon: const Icon(Icons.delete_outline, color: Colors.red),
                      ),
                    ],
                  ),
                ),
        ],
        const SizedBox(height: 4),
        Text(
          '${_attachmentHint(selectedType)} Format saat ini di aplikasi: gambar JPG/JPEG/PNG/WEBP atau PDF maksimal 5MB.',
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: Colors.grey[600],
              ),
        ),
      ],
    );
  }

  Widget _buildDateInput({
    required String label,
    required DateTime? value,
    required VoidCallback onTap,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                fontWeight: FontWeight.w600,
              ),
        ),
        const SizedBox(height: 8),
        InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(10),
          child: Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.grey.shade300),
            ),
            child: Row(
              children: [
                Icon(Icons.calendar_month, color: Colors.grey[700], size: 20),
                const SizedBox(width: 8),
                Text(
                  value == null ? 'Pilih tanggal' : _formatDisplayDate(value),
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: value == null ? Colors.grey[600] : Colors.black,
                      ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _submitRequest() async {
    final selectedType = _selectedType;
    final startDate = _startDate;
    final endDate = _endDate;

    if (selectedType == null ||
        startDate == null ||
        endDate == null ||
        _reasonController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Mohon lengkapi semua field pengajuan'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (endDate.isBefore(startDate)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Tanggal selesai tidak boleh sebelum tanggal mulai'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    if (_isAttachmentRequired(selectedType) &&
        (_selectedPhotoPath == null || _selectedPhotoPath!.isEmpty)) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Lampiran pendukung wajib diunggah untuk pengajuan ini'),
          backgroundColor: Colors.red,
        ),
      );
      return;
    }

    setState(() {
      _isSubmitting = true;
      _pendingSubmissionRequestId ??= _buildSubmissionRequestId();
    });

    try {
      final response = await _leaveService.submit(
        LeaveSubmissionPayload(
          jenisIzin: selectedType.value,
          alasan: _reasonController.text.trim(),
          tanggalMulai: startDate,
          tanggalSelesai: endDate,
          dokumenPendukungPath: _selectedPhotoPath,
        ),
        clientRequestId: _pendingSubmissionRequestId,
      );

      if (!mounted) {
        return;
      }

      if (response.success) {
        setState(() {
          _startDate = null;
          _endDate = null;
          _selectedPhotoPath = null;
          _pendingSubmissionRequestId = null;
          _reasonController.clear();
        });

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response.message.isNotEmpty
                  ? response.message
                  : 'Pengajuan ${selectedType.label} berhasil dikirim',
            ),
            backgroundColor: Colors.green,
          ),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              response.message.isNotEmpty ? response.message : 'Pengajuan gagal dikirim',
            ),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (_) {
      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Terjadi kesalahan saat mengirim pengajuan'),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) {
        setState(() {
          _isSubmitting = false;
        });
      }
    }
  }

  String _buildSubmissionRequestId() {
    final now = DateTime.now();
    final selectedType = _selectedType?.value ?? 'izin';
    return 'mobile-izin-${now.microsecondsSinceEpoch}-$selectedType';
  }
}

class _SubmissionType {
  const _SubmissionType({
    required this.value,
    required this.label,
    required this.description,
    required this.icon,
    required this.color,
    required this.reasonHint,
  });

  final String value;
  final String label;
  final String description;
  final IconData icon;
  final Color color;
  final String reasonHint;
}
