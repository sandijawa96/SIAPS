import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../services/manual_attendance_service.dart';
import '../widgets/access_denied_scaffold.dart';

String _formatDateLabel(DateTime? value) {
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

DateTime _today() {
  final now = DateTime.now();
  return DateTime(now.year, now.month, now.day);
}

class ManualAttendanceEditorScreen extends StatefulWidget {
  final ManualAttendanceEntry? attendance;

  const ManualAttendanceEditorScreen({
    super.key,
    this.attendance,
  });

  bool get isCorrectionMode => attendance != null;

  @override
  State<ManualAttendanceEditorScreen> createState() =>
      _ManualAttendanceEditorScreenState();
}

class _ManualAttendanceEditorScreenState
    extends State<ManualAttendanceEditorScreen> {
  final ManualAttendanceService _service = ManualAttendanceService();
  final TextEditingController _searchController = TextEditingController();
  final TextEditingController _reasonController = TextEditingController();
  final TextEditingController _noteController = TextEditingController();

  ManualAttendanceManageableUser? _selectedUser;
  List<ManualAttendanceManageableUser> _searchResults =
      const <ManualAttendanceManageableUser>[];
  bool _hasAccess = false;
  bool _isSearching = false;
  bool _isSubmitting = false;
  DateTime _selectedDate = _today();
  String _status = 'hadir';
  String? _checkInTime;
  String? _checkOutTime;

  @override
  void initState() {
    super.initState();
    final attendance = widget.attendance;
    if (attendance != null) {
      _selectedDate = attendance.date ?? _today();
      _status = attendance.status.trim().toLowerCase();
      _checkInTime = attendance.checkInTime;
      _checkOutTime = attendance.checkOutTime;
      _noteController.text = attendance.note ?? '';
      _selectedUser = ManualAttendanceManageableUser(
        id: attendance.userId,
        name: attendance.userName ?? 'Siswa',
        identifier: attendance.userIdentifier,
        className: attendance.className,
        email: null,
      );
    }
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _hasAccess = context.read<AuthProvider>().user?.canManageManualAttendance ?? false;
  }

  @override
  void dispose() {
    _searchController.dispose();
    _reasonController.dispose();
    _noteController.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2024, 1, 1),
      lastDate: _today(),
      helpText: 'Pilih tanggal absensi',
    );

    if (picked == null || !mounted) {
      return;
    }

    setState(() {
      _selectedDate = DateTime(picked.year, picked.month, picked.day);
    });
  }

  Future<void> _pickTime({required bool isCheckIn}) async {
    final current = isCheckIn ? _checkInTime : _checkOutTime;
    final initial = current != null && current.contains(':')
        ? TimeOfDay(
            hour: int.tryParse(current.split(':').first) ?? 7,
            minute: int.tryParse(current.split(':').last) ?? 0,
          )
        : const TimeOfDay(hour: 7, minute: 0);

    final picked = await showTimePicker(
      context: context,
      initialTime: initial,
      helpText: isCheckIn ? 'Pilih jam masuk' : 'Pilih jam pulang',
    );

    if (picked == null || !mounted) {
      return;
    }

    final value =
        '${picked.hour.toString().padLeft(2, '0')}:${picked.minute.toString().padLeft(2, '0')}';

    setState(() {
      if (isCheckIn) {
        _checkInTime = value;
      } else {
        _checkOutTime = value;
      }
    });
  }

  Future<void> _searchUsers() async {
    final query = _searchController.text.trim();
    if (query.length < 2) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Masukkan minimal 2 karakter untuk mencari siswa.')),
      );
      return;
    }

    setState(() {
      _isSearching = true;
    });

    final response = await _service.searchUsers(query);
    if (!mounted) {
      return;
    }

    setState(() {
      _searchResults = response.data ?? const <ManualAttendanceManageableUser>[];
      _isSearching = false;
    });

    if (!response.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message)),
      );
    }
  }

  bool _isCheckoutAfterCheckin() {
    if ((_checkInTime ?? '').isEmpty || (_checkOutTime ?? '').isEmpty) {
      return true;
    }

    final checkInParts = _checkInTime!.split(':');
    final checkOutParts = _checkOutTime!.split(':');
    if (checkInParts.length < 2 || checkOutParts.length < 2) {
      return true;
    }

    final checkInMinutes =
        (int.tryParse(checkInParts[0]) ?? 0) * 60 + (int.tryParse(checkInParts[1]) ?? 0);
    final checkOutMinutes =
        (int.tryParse(checkOutParts[0]) ?? 0) * 60 + (int.tryParse(checkOutParts[1]) ?? 0);

    return checkOutMinutes > checkInMinutes;
  }

  Future<void> _submit() async {
    if (_isSubmitting) {
      return;
    }

    if (!_hasAccess) {
      return;
    }

    if (_selectedUser == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pilih siswa terlebih dahulu.')),
      );
      return;
    }

    if (_reasonController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Alasan tindakan wajib diisi.')),
      );
      return;
    }

    if (_status == 'terlambat' && (_checkInTime ?? '').isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Jam masuk wajib diisi untuk status terlambat.')),
      );
      return;
    }

    if (!_isCheckoutAfterCheckin()) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Jam pulang harus setelah jam masuk.')),
      );
      return;
    }

    setState(() {
      _isSubmitting = true;
    });

    final payload = ManualAttendanceSubmissionPayload(
      userId: _selectedUser!.id,
      date: _selectedDate,
      status: _status,
      reason: _reasonController.text.trim(),
      checkInTime: _checkInTime,
      checkOutTime: _checkOutTime,
      note: _noteController.text.trim(),
    );

    if (!widget.isCorrectionMode) {
      final duplicate = await _service.checkDuplicate(_selectedUser!.id, _selectedDate);
      if (!mounted) {
        return;
      }

      if (duplicate.success && duplicate.data?.isDuplicate == true) {
        setState(() {
          _isSubmitting = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Data absensi sudah ada pada tanggal tersebut. Gunakan menu Koreksi Absensi.'),
          ),
        );
        return;
      }
    }

    final response = widget.isCorrectionMode
        ? await _service.updateManualAttendance(widget.attendance!.id, payload)
        : await _service.createManualAttendance(payload);

    if (!mounted) {
      return;
    }

    setState(() {
      _isSubmitting = false;
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(response.message)),
    );

    if (response.success) {
      Navigator.of(context).pop(true);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess) {
      return const AccessDeniedScaffold(
        title: 'Pengelolaan Absensi',
        message: 'Halaman ini hanya tersedia untuk pengguna yang memiliki akses pengelolaan absensi.',
      );
    }

    final isCorrectionMode = widget.isCorrectionMode;

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: Text(isCorrectionMode ? 'Koreksi Absensi' : 'Absensi Manual'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (!isCorrectionMode) ...[
            _buildSectionCard(
              title: 'Cari siswa',
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  TextField(
                    controller: _searchController,
                    decoration: InputDecoration(
                      labelText: 'Nama / NIS / Username',
                      hintText: 'Cari siswa yang akan diinput absensinya',
                      prefixIcon: const Icon(Icons.search_rounded),
                      suffixIcon: IconButton(
                        onPressed: _isSearching ? null : _searchUsers,
                        icon: _isSearching
                            ? const SizedBox(
                                width: 18,
                                height: 18,
                                child: CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Icon(Icons.arrow_forward_rounded),
                      ),
                    ),
                    onSubmitted: (_) => _searchUsers(),
                  ),
                  if (_searchResults.isNotEmpty) ...[
                    const SizedBox(height: 12),
                    ..._searchResults.map(
                      (item) => RadioListTile<int>(
                        value: item.id,
                        groupValue: _selectedUser?.id,
                        contentPadding: EdgeInsets.zero,
                        title: Text(
                          item.name,
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF123B67),
                          ),
                        ),
                        subtitle: Text(
                          [
                            if ((item.identifier ?? '').trim().isNotEmpty)
                              item.identifier!.trim(),
                            if ((item.className ?? '').trim().isNotEmpty)
                              item.className!.trim(),
                          ].join('  |  '),
                        ),
                        onChanged: (_) {
                          setState(() {
                            _selectedUser = item;
                          });
                        },
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 12),
          ],
          if (_selectedUser != null) ...[
            _buildSectionCard(
              title: 'Target siswa',
              child: Row(
                children: [
                  Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: const Color(0xFFDBEAFE),
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: const Icon(Icons.person_outline, color: Color(0xFF2563EB)),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          _selectedUser!.name,
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF123B67),
                          ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          [
                            if ((_selectedUser!.identifier ?? '').trim().isNotEmpty)
                              _selectedUser!.identifier!.trim(),
                            if ((_selectedUser!.className ?? '').trim().isNotEmpty)
                              _selectedUser!.className!.trim(),
                          ].join('  |  '),
                          style: const TextStyle(
                            fontSize: 12,
                            color: Color(0xFF66758A),
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
          ],
          _buildSectionCard(
            title: 'Detail absensi',
            child: Column(
              children: [
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Tanggal'),
                  subtitle: Text(_formatDateLabel(_selectedDate)),
                  trailing: const Icon(Icons.calendar_today_outlined),
                  onTap: isCorrectionMode ? null : _pickDate,
                ),
                DropdownButtonFormField<String>(
                  value: _status,
                  decoration: const InputDecoration(labelText: 'Status'),
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
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _pickTime(isCheckIn: true),
                        icon: const Icon(Icons.login_rounded),
                        label: Text(_checkInTime ?? 'Jam Masuk'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => _pickTime(isCheckIn: false),
                        icon: const Icon(Icons.logout_rounded),
                        label: Text(_checkOutTime ?? 'Jam Pulang'),
                      ),
                    ),
                  ],
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
                const SizedBox(height: 12),
                TextField(
                  controller: _reasonController,
                  minLines: 2,
                  maxLines: 4,
                  decoration: const InputDecoration(
                    labelText: 'Alasan tindakan',
                    hintText: 'Contoh: Koreksi setelah verifikasi wali kelas',
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          FilledButton.icon(
            onPressed: _isSubmitting ? null : _submit,
            icon: _isSubmitting
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Icon(Icons.save_outlined),
            label: Text(
              isCorrectionMode ? 'Simpan Koreksi' : 'Simpan Absensi Manual',
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionCard({
    required String title,
    required Widget child,
  }) {
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
