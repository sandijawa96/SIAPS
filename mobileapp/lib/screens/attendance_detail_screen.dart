import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../services/attendance_service.dart';
import '../utils/constants.dart';

class AttendanceDetailScreen extends StatefulWidget {
  final String attendanceId;
  final AttendanceRecord initialRecord;

  const AttendanceDetailScreen({
    super.key,
    required this.attendanceId,
    required this.initialRecord,
  });

  @override
  State<AttendanceDetailScreen> createState() => _AttendanceDetailScreenState();
}

class _AttendanceDetailScreenState extends State<AttendanceDetailScreen> {
  final AttendanceService _attendanceService = AttendanceService();

  late AttendanceRecord _record;
  bool _isLoading = true;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _record = widget.initialRecord;
    _loadDetail();
  }

  Future<void> _loadDetail() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final response = await _attendanceService.getAttendanceDetail(widget.attendanceId);
    if (!mounted) {
      return;
    }

    setState(() {
      if (response.success && response.data != null) {
        _record = response.data!;
      } else {
        _errorMessage = response.message;
      }
      _isLoading = false;
    });
  }

  String _formatDate(DateTime value) {
    const months = <String>[
      'Januari',
      'Februari',
      'Maret',
      'April',
      'Mei',
      'Juni',
      'Juli',
      'Agustus',
      'September',
      'Oktober',
      'November',
      'Desember',
    ];
    return '${value.day.toString().padLeft(2, '0')} ${months[value.month - 1]} ${value.year}';
  }

  Color _statusColor() {
    switch ((_record.status ?? '').toLowerCase()) {
      case 'terlambat':
        return const Color(0xFFF59E0B);
      case 'alpha':
      case 'alpa':
        return const Color(0xFFB4232C);
      case 'izin':
        return const Color(0xFF2563EB);
      case 'sakit':
        return const Color(0xFF7C3AED);
      default:
        return const Color(0xFF16A34A);
    }
  }

  IconData _statusIcon() {
    switch ((_record.status ?? '').toLowerCase()) {
      case 'terlambat':
        return Icons.schedule_rounded;
      case 'alpha':
      case 'alpa':
        return Icons.cancel_rounded;
      case 'izin':
        return Icons.event_note_rounded;
      case 'sakit':
        return Icons.local_hospital_rounded;
      default:
        return Icons.check_circle_rounded;
    }
  }

  String _formatCoordinate(double? latitude, double? longitude) {
    if (latitude == null || longitude == null) {
      return '-';
    }
    return '${latitude.toStringAsFixed(6)}, ${longitude.toStringAsFixed(6)}';
  }

  String _formatScore(double? value) {
    if (value == null) {
      return '-';
    }
    return value.toStringAsFixed(2);
  }

  String _formatAccuracy(double? value) {
    if (value == null) {
      return '-';
    }
    return '${value.toStringAsFixed(1)} m';
  }

  @override
  Widget build(BuildContext context) {
    final displayDate = _record.attendanceDate ?? _record.timestamp;
    final statusColor = _statusColor();
    final statusIcon = _statusIcon();

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Detail Presensi'),
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
                  Text(
                    _formatDate(displayDate),
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.88),
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.14),
                          borderRadius: BorderRadius.circular(999),
                          border: Border.all(
                            color: Colors.white.withValues(alpha: 0.18),
                          ),
                        ),
                        child: Row(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Container(
                              width: 20,
                              height: 20,
                              decoration: BoxDecoration(
                                color: statusColor.withValues(alpha: 0.18),
                                shape: BoxShape.circle,
                              ),
                              child: Icon(
                                statusIcon,
                                size: 12,
                                color: Colors.white,
                              ),
                            ),
                            const SizedBox(width: 6),
                            Text(
                              _record.displayStatusLabel,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 12,
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _record.metodeAbsensi?.isNotEmpty == true
                              ? 'Metode ${_record.metodeAbsensi}'
                              : 'Presensi harian',
                          textAlign: TextAlign.end,
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.88),
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
            if (_errorMessage != null) ...[
              const SizedBox(height: 16),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: const Color(0xFFFFF4E8),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: const Color(0xFFF4C98C)),
                ),
                child: Row(
                  children: [
                    const Icon(Icons.info_outline, color: Color(0xFF9A6700)),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        _errorMessage!,
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: Color(0xFF9A6700),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 16),
            _SectionCard(
              title: 'Waktu Presensi',
              child: Column(
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: _TimeCard(
                          label: 'Masuk',
                          value: _record.formattedCheckInTime,
                          color: const Color(0xFF16A34A),
                          icon: Icons.login_rounded,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _TimeCard(
                          label: 'Pulang',
                          value: _record.formattedCheckOutTime,
                          color: const Color(0xFF2563EB),
                          icon: Icons.logout_rounded,
                        ),
                      ),
                    ],
                  ),
                  if ((_record.durationText ?? '').trim().isNotEmpty &&
                      _record.durationText != '-') ...[
                    const SizedBox(height: 10),
                    _InfoLine(label: 'Durasi sekolah', value: _record.durationText!),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
            _SectionCard(
              title: 'Foto Presensi',
              child: Column(
                children: [
                  _PhotoCard(
                    title: 'Foto Masuk',
                    imageUrl: _record.fotoMasukUrl,
                    fallbackLabel: 'Foto masuk belum tersedia',
                  ),
                  const SizedBox(height: 12),
                  _PhotoCard(
                    title: 'Foto Pulang',
                    imageUrl: _record.fotoPulangUrl,
                    fallbackLabel: 'Foto pulang belum tersedia',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _SectionCard(
              title: 'Peta Absensi',
              child: Column(
                children: [
                  _MapCard(
                    title: 'Lokasi Masuk',
                    locationName: _record.lokasiMasukNama,
                    coordinates:
                        _formatCoordinate(_record.latitudeMasuk, _record.longitudeMasuk),
                    latitude: _record.latitudeMasuk,
                    longitude: _record.longitudeMasuk,
                  ),
                  const SizedBox(height: 12),
                  _MapCard(
                    title: 'Lokasi Pulang',
                    locationName: _record.lokasiPulangNama,
                    coordinates:
                        _formatCoordinate(_record.latitudePulang, _record.longitudePulang),
                    latitude: _record.latitudePulang,
                    longitude: _record.longitudePulang,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _SectionCard(
              title: 'Detail Lainnya',
              child: Column(
                children: [
                  _InfoLine(label: 'Status', value: _record.displayStatusLabel),
                  _InfoLine(
                    label: 'Verifikasi',
                    value: (_record.verificationStatus ?? '').trim().isEmpty
                        ? (_record.isVerified ? 'Terverifikasi' : 'Belum diverifikasi')
                        : _record.verificationStatus!,
                  ),
                  _InfoLine(
                    label: 'Skor selfie masuk',
                    value: _formatScore(_record.faceScoreCheckIn),
                  ),
                  _InfoLine(
                    label: 'Skor selfie pulang',
                    value: _formatScore(_record.faceScoreCheckOut),
                  ),
                  _InfoLine(
                    label: 'Akurasi GPS masuk',
                    value: _formatAccuracy(_record.gpsAccuracyMasuk),
                  ),
                  _InfoLine(
                    label: 'Akurasi GPS pulang',
                    value: _formatAccuracy(_record.gpsAccuracyPulang),
                  ),
                  _InfoLine(
                    label: 'Metode absensi',
                    value: (_record.metodeAbsensi ?? '').trim().isEmpty
                        ? '-'
                        : _record.metodeAbsensi!,
                  ),
                  _InfoLine(
                    label: 'Keterangan',
                    value: (_record.keterangan ?? '').trim().isEmpty
                        ? '-'
                        : _record.keterangan!,
                    isMultiline: true,
                  ),
                ],
              ),
            ),
            if (_isLoading) ...[
              const SizedBox(height: 16),
              const Center(child: CircularProgressIndicator()),
            ],
          ],
        ),
      ),
    );
  }
}

class _SectionCard extends StatelessWidget {
  final String title;
  final Widget child;

  const _SectionCard({required this.title, required this.child});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFFD8E6F8)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x110F4C81),
            blurRadius: 14,
            offset: Offset(0, 6),
          ),
        ],
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
          const SizedBox(height: 14),
          child,
        ],
      ),
    );
  }
}

class _TimeCard extends StatelessWidget {
  final String label;
  final String value;
  final Color color;
  final IconData icon;

  const _TimeCard({
    required this.label,
    required this.value,
    required this.color,
    required this.icon,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
        children: [
          Icon(icon, color: color),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: Color(0xFF66758A),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF123B67),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PhotoCard extends StatelessWidget {
  final String title;
  final String? imageUrl;
  final String fallbackLabel;

  const _PhotoCard({
    required this.title,
    required this.imageUrl,
    required this.fallbackLabel,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: Color(0xFF123B67),
          ),
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(18),
          child: Container(
            height: 190,
            width: double.infinity,
            color: const Color(0xFFF7FAFF),
            child: imageUrl == null || imageUrl!.trim().isEmpty
                ? _ImagePlaceholder(label: fallbackLabel)
                : Image.network(
                    imageUrl!,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) {
                      return _ImagePlaceholder(label: fallbackLabel);
                    },
                    loadingBuilder: (context, child, progress) {
                      if (progress == null) {
                        return child;
                      }
                      return const Center(child: CircularProgressIndicator());
                    },
                  ),
          ),
        ),
      ],
    );
  }
}

class _ImagePlaceholder extends StatelessWidget {
  final String label;

  const _ImagePlaceholder({required this.label});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const Icon(Icons.image_not_supported_outlined, color: Color(0xFF7B8EA8), size: 36),
          const SizedBox(height: 10),
          Text(
            label,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Color(0xFF66758A),
            ),
          ),
        ],
      ),
    );
  }
}

class _MapCard extends StatelessWidget {
  final String title;
  final String? locationName;
  final String coordinates;
  final double? latitude;
  final double? longitude;

  const _MapCard({
    required this.title,
    required this.locationName,
    required this.coordinates,
    required this.latitude,
    required this.longitude,
  });

  @override
  Widget build(BuildContext context) {
    final hasCoordinates = latitude != null && longitude != null;
    final point = hasCoordinates ? LatLng(latitude!, longitude!) : null;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF7FAFF),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            (locationName ?? '').trim().isEmpty ? 'Lokasi tidak tersedia' : locationName!,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Color(0xFF66758A),
            ),
          ),
          const SizedBox(height: 4),
          Text(
            coordinates,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Color(0xFF4E6178),
            ),
          ),
          const SizedBox(height: 10),
          ClipRRect(
            borderRadius: BorderRadius.circular(16),
            child: Container(
              height: 170,
              width: double.infinity,
              color: Colors.white,
              child: !hasCoordinates
                  ? const _ImagePlaceholder(label: 'Koordinat belum tersedia')
                  : FlutterMap(
                      options: MapOptions(
                        initialCenter: point!,
                        initialZoom: 16,
                        interactionOptions: const InteractionOptions(
                          flags: InteractiveFlag.pinchZoom | InteractiveFlag.drag,
                        ),
                      ),
                      children: [
                        TileLayer(
                          urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                          userAgentPackageName: 'id.sch.sman1sumbercirebon.mobileapp',
                        ),
                        MarkerLayer(
                          markers: [
                            Marker(
                              point: point!,
                              width: 44,
                              height: 44,
                              child: const Icon(
                                Icons.location_on_rounded,
                                color: Color(0xFFB4232C),
                                size: 36,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
            ),
          ),
        ],
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  final String label;
  final String value;
  final bool isMultiline;

  const _InfoLine({
    required this.label,
    required this.value,
    this.isMultiline = false,
  });

  @override
  Widget build(BuildContext context) {
    if (isMultiline) {
      return Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF66758A),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              value,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: Color(0xFF123B67),
              ),
            ),
          ],
        ),
      );
    }

    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          SizedBox(
            width: 126,
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
              textAlign: TextAlign.end,
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
