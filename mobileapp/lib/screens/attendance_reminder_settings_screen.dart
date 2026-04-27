import 'dart:io' show Platform;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/auth_provider.dart';
import '../services/attendance_reminder_service.dart';
import '../services/attendance_service.dart';

class AttendanceReminderSettingsScreen extends StatefulWidget {
  const AttendanceReminderSettingsScreen({super.key});

  @override
  State<AttendanceReminderSettingsScreen> createState() =>
      _AttendanceReminderSettingsScreenState();
}

class _AttendanceReminderSettingsScreenState
    extends State<AttendanceReminderSettingsScreen> {
  final AttendanceReminderService _reminderService =
      AttendanceReminderService();
  final AttendanceService _attendanceService = AttendanceService();

  AttendanceReminderPreferences _preferences =
      AttendanceReminderPreferences.defaults;
  WorkingHours? _workingHours;
  bool _isLoading = true;
  bool _isSaving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  Future<void> _loadData() async {
    final user = context.read<AuthProvider>().user;
    final isEligible = Platform.isAndroid && (user?.isSiswa ?? false);
    setState(() {
      _isLoading = true;
      _error = null;
    });

    try {
      final prefs = await _reminderService.getPreferences();
      WorkingHours? workingHours;
      if (isEligible) {
        final response = await _attendanceService.getWorkingHours();
        if (response.success) {
          workingHours = response.data;
        }
      }

      if (!mounted) {
        return;
      }

      setState(() {
        _preferences = prefs;
        _workingHours = workingHours;
        _isLoading = false;
      });

      if (!isEligible) {
        return;
      }
    } catch (e) {
      if (!mounted) {
        return;
      }
      setState(() {
        _isLoading = false;
        _error = 'Gagal memuat pengaturan pengingat: $e';
      });
    }
  }

  Future<void> _updatePreference({
    bool? checkInEnabled,
    bool? checkOutEnabled,
  }) async {
    if (_isSaving) {
      return;
    }

    final user = context.read<AuthProvider>().user;
    setState(() => _isSaving = true);

    try {
      final updated = await _reminderService.savePreferences(
        checkInEnabled: checkInEnabled,
        checkOutEnabled: checkOutEnabled,
      );
      await _reminderService.applySchedulesForUser(user);

      if (!mounted) {
        return;
      }

      setState(() {
        _preferences = updated;
        _isSaving = false;
      });

      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Pengaturan pengingat berhasil disimpan')),
      );
    } catch (e) {
      if (!mounted) {
        return;
      }

      setState(() => _isSaving = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Gagal menyimpan pengaturan: $e')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final isEligible = Platform.isAndroid && (user?.isSiswa ?? false);

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Pengingat Absensi'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 24),
        children: [
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFFD8E6F8)),
            ),
            child: Text(
              'Pengingat memakai patokan jam kerja siswa (jam masuk/pulang), '
              'bukan jam buka absensi. Notifikasi dikirim 10 menit sebelum waktu tersebut.',
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF4A607A),
              ),
            ),
          ),
          const SizedBox(height: 12),
          if (_workingHours != null)
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFD8E6F8)),
              ),
              child: Row(
                children: [
                  const Icon(Icons.schedule, color: Color(0xFF2563EB)),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'Jam kerja aktif: ${_workingHours!.jamMasuk} - ${_workingHours!.jamPulang}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF123B67),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(
              _error!,
              style: const TextStyle(
                fontSize: 12,
                color: Color(0xFFB4232C),
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
          const SizedBox(height: 12),
          if (_isLoading)
            const Center(
              child: Padding(
                padding: EdgeInsets.symmetric(vertical: 32),
                child: CircularProgressIndicator(),
              ),
            )
          else if (!isEligible)
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFD8E6F8)),
              ),
              child: const Text(
                'Pengaturan ini khusus akun siswa pada perangkat Android.',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF4A607A),
                ),
              ),
            )
          else
            Container(
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: const Color(0xFFD8E6F8)),
              ),
              child: Column(
                children: [
                  SwitchListTile.adaptive(
                    value: _preferences.checkInEnabled,
                    onChanged: _isSaving
                        ? null
                        : (value) => _updatePreference(checkInEnabled: value),
                    title: const Text(
                      'Pengingat Absen Masuk',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF123B67),
                      ),
                    ),
                    subtitle:
                        const Text('Notifikasi 10 menit sebelum jam masuk'),
                  ),
                  const Divider(height: 1),
                  SwitchListTile.adaptive(
                    value: _preferences.checkOutEnabled,
                    onChanged: _isSaving
                        ? null
                        : (value) => _updatePreference(checkOutEnabled: value),
                    title: const Text(
                      'Pengingat Absen Pulang',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF123B67),
                      ),
                    ),
                    subtitle:
                        const Text('Notifikasi 10 menit sebelum jam pulang'),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
