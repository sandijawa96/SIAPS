import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../services/face_template_service.dart';
import '../widgets/access_denied_scaffold.dart';

class FaceTemplateScreen extends StatefulWidget {
  const FaceTemplateScreen({super.key});

  @override
  State<FaceTemplateScreen> createState() => _FaceTemplateScreenState();
}

class _FaceTemplateScreenState extends State<FaceTemplateScreen> {
  final FaceTemplateService _service = FaceTemplateService();
  final ImagePicker _imagePicker = ImagePicker();

  bool _hasAccess = false;
  bool _isLoading = true;
  bool _isSubmitting = false;
  String? _errorMessage;
  String? _selectedImagePath;
  FaceTemplateStatusPayload? _payload;

  @override
  void initState() {
    super.initState();
    _hasAccess = context.read<AuthProvider>().user?.isSiswa ?? false;
    if (_hasAccess) {
      _loadStatus();
    } else {
      _isLoading = false;
    }
  }

  Future<void> _loadStatus() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final response = await _service.getMyStatus();
    if (!mounted) {
      return;
    }

    setState(() {
      _payload = response.data;
      _errorMessage = response.success ? null : response.message;
      _isLoading = false;
    });
  }

  Future<void> _captureImage() async {
    if (_isSubmitting) {
      return;
    }

    final image = await _imagePicker.pickImage(
      source: ImageSource.camera,
      imageQuality: 88,
      maxWidth: 1600,
    );

    if (image == null || !mounted) {
      return;
    }

    setState(() {
      _selectedImagePath = image.path;
    });
  }

  Future<void> _submitTemplate() async {
    if (_selectedImagePath == null || _selectedImagePath!.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Ambil foto wajah dari kamera terlebih dahulu.')),
      );
      return;
    }

    setState(() {
      _isSubmitting = true;
      _errorMessage = null;
    });

    final response = await _service.selfSubmit(_selectedImagePath!);

    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(response.message)),
    );

    if (response.success) {
      await context.read<AuthProvider>().refreshProfile();
    }

    setState(() {
      _isSubmitting = false;
      if (response.success) {
        _payload = response.data;
        _selectedImagePath = null;
      } else {
        _errorMessage = response.message;
      }
    });
  }

  String _formatDateTime(DateTime? value) {
    if (value == null) {
      return '-';
    }

    final local = value.toLocal();
    final day = local.day.toString().padLeft(2, '0');
    final month = local.month.toString().padLeft(2, '0');
    final hour = local.hour.toString().padLeft(2, '0');
    final minute = local.minute.toString().padLeft(2, '0');
    return '$day/$month/${local.year} $hour:$minute';
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess) {
      return const AccessDeniedScaffold(
        title: 'Template Wajah',
        message: 'Template wajah mandiri hanya tersedia untuk akun siswa.',
      );
    }

    final authProvider = context.watch<AuthProvider>();
    final user = authProvider.user;
    final payload = _payload;
    final submissionState = payload?.submissionState;
    final canSelfSubmitNow = submissionState?.canSelfSubmitNow ?? false;

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Template Wajah'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: RefreshIndicator(
        onRefresh: _loadStatus,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(22),
                border: Border.all(color: const Color(0xFFD8E6F8)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    payload?.userName ?? user?.displayName ?? 'Siswa',
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF123B67),
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Self submit mandiri maksimal 3 kali. Setelah itu harus dibukakan 1 kali submit tambahan oleh admin, wali kelas, atau kesiswaan.',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF66758A),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (_isLoading || _isSubmitting)
              const LinearProgressIndicator(minHeight: 3),
            if (_errorMessage != null && _errorMessage!.trim().isNotEmpty) ...[
              const SizedBox(height: 16),
              _InfoCard(
                title: 'Status',
                child: Text(
                  _errorMessage!,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: Color(0xFFB4232C),
                  ),
                ),
              ),
            ],
            const SizedBox(height: 16),
            _InfoCard(
              title: 'Template Aktif',
              child: payload?.hasActiveTemplate == true && payload?.activeTemplate != null
                  ? Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if ((payload?.activeTemplate?.templateUrl ?? '').trim().isNotEmpty)
                          ClipRRect(
                            borderRadius: BorderRadius.circular(16),
                            child: Image.network(
                              payload!.activeTemplate!.templateUrl!,
                              height: 180,
                              width: double.infinity,
                              fit: BoxFit.cover,
                            ),
                          ),
                        const SizedBox(height: 12),
                        Text(
                          'Engine: ${payload?.activeTemplate?.templateVersion ?? '-'}',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF516173),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Quality: ${payload?.activeTemplate?.qualityScore?.toStringAsFixed(4) ?? '-'}',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF516173),
                          ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Enrolled: ${_formatDateTime(payload?.activeTemplate?.enrolledAt)}',
                          style: const TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF516173),
                          ),
                        ),
                      ],
                    )
                  : const Text(
                      'Belum ada template wajah aktif.',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF66758A),
                      ),
                    ),
            ),
            const SizedBox(height: 16),
            _InfoCard(
              title: 'Kuota Self Submit',
              child: submissionState == null
                  ? const Text(
                      'Status kuota belum tersedia.',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF66758A),
                      ),
                    )
                  : Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        _InfoLine(
                          label: 'Terpakai',
                          value: '${submissionState.selfSubmitCount}/${submissionState.limit}',
                        ),
                        _InfoLine(
                          label: 'Sisa kuota dasar',
                          value: '${submissionState.baseQuotaRemaining}',
                        ),
                        _InfoLine(
                          label: 'Jatah tambahan aktif',
                          value: '${submissionState.unlockAllowanceRemaining}',
                        ),
                        _InfoLine(
                          label: 'Terakhir submit',
                          value: _formatDateTime(submissionState.lastSubmittedAt),
                        ),
                        _InfoLine(
                          label: 'Unlock terakhir',
                          value: submissionState.lastUnlockedByName == null
                              ? _formatDateTime(submissionState.lastUnlockedAt)
                              : '${_formatDateTime(submissionState.lastUnlockedAt)} oleh ${submissionState.lastUnlockedByName}',
                        ),
                        const SizedBox(height: 12),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                          decoration: BoxDecoration(
                            color: canSelfSubmitNow ? const Color(0xFFE8F7EE) : const Color(0xFFFFF4E5),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Row(
                            children: [
                              Icon(
                                canSelfSubmitNow ? Icons.check_circle_outline : Icons.lock_outline,
                                color: canSelfSubmitNow ? const Color(0xFF1E7D46) : const Color(0xFFB26A00),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  canSelfSubmitNow
                                      ? 'Anda masih bisa merekam template wajah dari akun siswa.'
                                      : 'Kuota self submit habis. Minta admin, wali kelas, atau kesiswaan membuka 1 kali submit tambahan.',
                                  style: TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                    color: canSelfSubmitNow ? const Color(0xFF1E7D46) : const Color(0xFFB26A00),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
            ),
            const SizedBox(height: 16),
            _InfoCard(
              title: 'Kirim Template Baru',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Template wajah harus direkam langsung dari kamera. Pilih posisi wajah tunggal yang jelas. Template lama akan digantikan otomatis setelah submit berhasil.',
                    style: TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: Color(0xFF66758A),
                    ),
                  ),
                  const SizedBox(height: 14),
                  if (_selectedImagePath != null) ...[
                    ClipRRect(
                      borderRadius: BorderRadius.circular(16),
                      child: Image.file(
                        File(_selectedImagePath!),
                        height: 180,
                        width: double.infinity,
                        fit: BoxFit.cover,
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  SizedBox(
                    width: double.infinity,
                    child: OutlinedButton.icon(
                      onPressed: canSelfSubmitNow && !_isSubmitting
                          ? _captureImage
                          : null,
                      icon: const Icon(Icons.camera_alt_outlined),
                      label: const Text('Ambil Foto dari Kamera'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: canSelfSubmitNow && !_isSubmitting && _selectedImagePath != null
                          ? _submitTemplate
                          : null,
                      icon: const Icon(Icons.upload_outlined),
                      label: const Text('Kirim Template Wajah'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoCard extends StatelessWidget {
  final String title;
  final Widget child;

  const _InfoCard({
    required this.title,
    required this.child,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  final String label;
  final String value;

  const _InfoLine({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 138,
            child: Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF66758A),
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: Color(0xFF123B67),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
