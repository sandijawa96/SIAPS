import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/manual_data_sync_service.dart';
import '../services/personal_data_service.dart';
import '../utils/constants.dart';

class PersonalDataScreen extends StatefulWidget {
  const PersonalDataScreen({super.key});

  @override
  State<PersonalDataScreen> createState() => _PersonalDataScreenState();
}

class _PersonalDataScreenState extends State<PersonalDataScreen> {
  final PersonalDataService _service = PersonalDataService();
  final ImagePicker _imagePicker = ImagePicker();
  final ManualDataSyncService _manualDataSyncService = ManualDataSyncService();

  bool _isLoading = true;
  bool _isUploadingAvatar = false;
  String? _errorMessage;
  PersonalDataPayload? _payload;
  List<PersonalDataSectionSchema> _sections = const <PersonalDataSectionSchema>[];
  int _lastManualSyncVersion = 0;

  @override
  void initState() {
    super.initState();
    _lastManualSyncVersion = _manualDataSyncService.syncVersion;
    _manualDataSyncService.addListener(_handleManualSyncChanged);
    _loadData();
  }

  @override
  void dispose() {
    _manualDataSyncService.removeListener(_handleManualSyncChanged);
    super.dispose();
  }

  void _handleManualSyncChanged() {
    if (!mounted) {
      return;
    }

    if (_lastManualSyncVersion == _manualDataSyncService.syncVersion) {
      return;
    }

    _lastManualSyncVersion = _manualDataSyncService.syncVersion;
    _loadData();
  }

  Future<void> _loadData() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final profileResponse = await _service.getProfile();
    final schemaResponse = await _service.getSchema();

    if (!mounted) {
      return;
    }

    setState(() {
      _payload = profileResponse.data;
      _sections = schemaResponse.data ?? const <PersonalDataSectionSchema>[];
      _errorMessage = profileResponse.success ? (schemaResponse.success ? null : schemaResponse.message) : profileResponse.message;
      _isLoading = false;
    });
  }

  Future<void> _pickAndUploadAvatar() async {
    if (_isUploadingAvatar) {
      return;
    }

    final image = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      imageQuality: 88,
      maxWidth: 1600,
    );

    if (image == null || !mounted) {
      return;
    }

    setState(() {
      _isUploadingAvatar = true;
    });

    final response = await _service.updateAvatar(image.path);
    if (!mounted) {
      return;
    }

    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(response.message)));

    if (response.success) {
      await context.read<AuthProvider>().refreshProfile();
      await _loadData();
    }

    if (mounted) {
      setState(() {
        _isUploadingAvatar = false;
      });
    }
  }

  bool _hasValue(dynamic value) {
    if (value == null) {
      return false;
    }
    if (value is String) {
      final trimmed = value.trim();
      return trimmed.isNotEmpty && trimmed != '-';
    }
    if (value is List) {
      return value.isNotEmpty;
    }
    if (value is Map) {
      return value.isNotEmpty;
    }
    return true;
  }

  String _formatDate(DateTime value) {
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

  String _formatValue(dynamic value, String type) {
    if (value == null) {
      return '-';
    }

    if (type == 'boolean' && value is bool) {
      return value ? 'Ya' : 'Tidak';
    }

    if (value is bool) {
      return value ? 'Ya' : 'Tidak';
    }

    if (value is List) {
      if (value.isEmpty) {
        return '-';
      }
      return value.map((item) => item.toString()).join(', ');
    }

    if (value is Map) {
      if (value.isEmpty) {
        return '-';
      }
      return value.entries.map((entry) => '${entry.key}: ${entry.value}').join(', ');
    }

    if (type == 'date') {
      final parsed = DateTime.tryParse(value.toString());
      if (parsed != null) {
        return _formatDate(parsed);
      }
    }

    return value.toString();
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = context.watch<AuthProvider>();
    final user = authProvider.user;
    final payload = _payload;
    final common = payload?.common ?? const <String, dynamic>{};
    final photoUrl = common['foto_profil_url']?.toString() ?? user?.fotoProfil;
    final displayName = common['nama_lengkap']?.toString() ?? user?.displayName ?? 'Pengguna';
    final nis = (common['nis'] ?? '').toString().trim();
    final nip = (common['nip'] ?? '').toString().trim();
    final identifier = nis.isNotEmpty
        ? nis
        : nip.isNotEmpty
            ? nip
            : user?.identifier ?? '-';
    final roleLabel = user?.roles.isNotEmpty == true ? user!.roles.first.name : '-';
    final canUploadAvatar = !_isUploadingAvatar && payload != null && !(user?.isSuperAdmin ?? false);

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Data Pribadi'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              gradient: const LinearGradient(
                colors: [Color(0xFF0C4A7A), Color(0xFF64B5F6)],
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              borderRadius: BorderRadius.circular(24),
            ),
            child: Column(
              children: [
                Stack(
                  children: [
                    CircleAvatar(
                      radius: 42,
                      backgroundColor: Colors.white.withValues(alpha: 0.18),
                      backgroundImage: (photoUrl ?? '').trim().isNotEmpty ? NetworkImage(photoUrl!) : null,
                      child: (photoUrl ?? '').trim().isEmpty ? const Icon(Icons.person, size: 42, color: Colors.white) : null,
                    ),
                    Positioned(
                      right: 0,
                      bottom: 0,
                      child: InkWell(
                        onTap: canUploadAvatar ? _pickAndUploadAvatar : null,
                        borderRadius: BorderRadius.circular(999),
                        child: Container(
                          width: 34,
                          height: 34,
                          decoration: BoxDecoration(
                            color: Colors.white,
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(color: const Color(0xFFD8E6F8)),
                          ),
                          child: _isUploadingAvatar
                              ? const Padding(
                                  padding: EdgeInsets.all(8),
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Icon(Icons.photo_camera_outlined, size: 18, color: Color(0xFF123B67)),
                        ),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Text(
                  displayName,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 22,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  identifier,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.82),
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  alignment: WrapAlignment.center,
                  children: [
                    _HeaderPill(label: roleLabel),
                    _HeaderPill(label: payload?.profileType == 'siswa' ? 'Profil Siswa' : 'Profil Pegawai'),
                    _HeaderPill(label: user?.isActive == true ? 'Akun Aktif' : 'Akun Nonaktif'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const _ReadOnlyInfoCard(),
          const SizedBox(height: 16),
          if (_isLoading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 48),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_errorMessage != null)
            _PersonalDataErrorState(message: _errorMessage!, onRetry: _loadData)
          else if (payload == null)
            const _PersonalDataEmptyState()
          else ...[
            _SummaryCard(payload: payload),
            const SizedBox(height: 16),
            ..._sections
                .map((section) {
                  final rows = section.fields
                      .map((field) {
                        final value = payload.valueFor(field.key);
                        if (!_hasValue(value)) {
                          return null;
                        }
                        return _FieldRow(label: field.label, value: _formatValue(value, field.type));
                      })
                      .whereType<_FieldRow>()
                      .toList();

                  if (rows.isEmpty) {
                    return null;
                  }

                  return Padding(
                    padding: const EdgeInsets.only(bottom: 16),
                    child: _SectionCard(title: section.label, children: rows),
                  );
                })
                .whereType<Widget>(),
          ],
        ],
      ),
    );
  }
}

class _HeaderPill extends StatelessWidget {
  final String label;

  const _HeaderPill({required this.label});

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

class _SummaryCard extends StatelessWidget {
  final PersonalDataPayload payload;

  const _SummaryCard({required this.payload});

  String _label(dynamic value) {
    if (value == null) {
      return '-';
    }
    final text = value.toString().trim();
    return text.isEmpty ? '-' : text;
  }

  @override
  Widget build(BuildContext context) {
    final common = payload.common;
    final activeClass = payload.activeClass;

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Ringkasan Akun',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 12),
          _FieldRow(label: 'Username', value: _label(common['username'])),
          _FieldRow(label: 'Email', value: _label(common['email'])),
          _FieldRow(label: 'NIK', value: _label(common['nik'])),
          if (payload.profileType == 'siswa') ...[
            _FieldRow(label: 'NIS', value: _label(common['nis'])),
            _FieldRow(label: 'NISN', value: _label(common['nisn'])),
            if (activeClass != null) _FieldRow(label: 'Kelas Aktif', value: _label(activeClass['nama_kelas'])),
            if (activeClass != null) _FieldRow(label: 'Wali Kelas', value: _label(activeClass['wali_kelas_nama'])),
            if (activeClass != null) _FieldRow(label: 'Tahun Ajaran', value: _label(activeClass['tahun_ajaran_nama'])),
            _FieldRow(
              label: 'Template Wajah',
              value: common['has_active_face_template'] == true
                  ? (common['face_template_enrolled_at'] != null
                      ? 'Aktif'
                      : 'Aktif')
                  : 'Belum tersedia',
            ),
          ] else ...[
            _FieldRow(label: 'NIP', value: _label(common['nip'])),
            _FieldRow(label: 'Status Kepegawaian', value: _label(common['status_kepegawaian'])),
          ],
        ],
      ),
    );
  }
}

class _ReadOnlyInfoCard extends StatelessWidget {
  const _ReadOnlyInfoCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.info_outline, color: Color(0xFF2A67A9)),
          SizedBox(width: 12),
          Expanded(
            child: Text(
              'Data identitas di mobile bersifat baca-saja. Perubahan biodata dilakukan melalui frontend web. Di mobile, yang dapat diperbarui hanya foto profil.',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF66758A),
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final List<Widget> children;

  const _SectionCard({
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

class _FieldRow extends StatelessWidget {
  final String label;
  final String value;

  const _FieldRow({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
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

class _PersonalDataErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _PersonalDataErrorState({
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
            'Gagal memuat data pribadi',
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

class _PersonalDataEmptyState extends StatelessWidget {
  const _PersonalDataEmptyState();

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
          Icon(Icons.badge_outlined, size: 42, color: Color(0xFF7B8EA8)),
          SizedBox(height: 12),
          Text(
            'Data pribadi belum tersedia',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          SizedBox(height: 6),
          Text(
            'Silakan coba lagi setelah data akun berhasil disinkronkan.',
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
