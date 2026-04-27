import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../providers/attendance_precheck_provider.dart';
import '../utils/constants.dart';

class AttendanceInfoCardRealtime extends StatelessWidget {
  final int refreshToken;

  const AttendanceInfoCardRealtime({
    Key? key,
    this.refreshToken = 0,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final precheck = context.watch<AttendancePrecheckProvider>();

    final String schemaMeta = [
      if (precheck.schemaType.trim().isNotEmpty) precheck.schemaType,
      if (precheck.schemaVersion != null) 'v${precheck.schemaVersion}',
    ].join(' | ');

    final String sourceMeta = schemaMeta.isNotEmpty
        ? schemaMeta
        : (precheck.isRefreshing ? 'Memuat skema...' : 'Skema efektif');

    final String requirementMeta =
        'Toleransi ${precheck.toleransi} menit | Hari aktif ${precheck.hariKerjaCount > 0 ? precheck.hariKerjaCount : '-'} | GPS ${precheck.wajibGps ? 'wajib' : 'opsional'} | Foto ${precheck.wajibFoto ? 'wajib' : 'opsional'} | Face ${precheck.faceVerificationEnabled ? 'aktif' : 'nonaktif'}';

    final bool isLocationCompliant = precheck.canAttend == true &&
        (!precheck.wajibGps || precheck.isAccuracyValid == true);

    final String locationStatus = _buildLocationStatus(precheck, isLocationCompliant);
    final String accuracyStatus = _buildAccuracyStatus(precheck);

    final Color locationColor = precheck.canAttend == null
        ? const Color(0xFF6A7C93)
        : isLocationCompliant
            ? const Color(0xFF166534)
            : (precheck.canAttend == false
                ? const Color(0xFFB4232C)
                : const Color(0xFFB45309));

    return LayoutBuilder(
      builder: (context, constraints) {
        final bool compact = constraints.maxWidth < 430;
        final double spacing = compact ? 4 : 8;

        if (constraints.maxWidth >= 760) {
          return Row(
            children: [
              Expanded(
                child: _buildKpiCard(
                  title: 'Skema Aktif',
                  value: precheck.schema,
                  subtitle: sourceMeta,
                  subtitleColor: const Color(0xFF6A7C93),
                  compact: false,
                ),
              ),
              SizedBox(width: spacing),
              Expanded(
                child: _buildKpiCard(
                  title: 'Lokasi Absensi',
                  value: precheck.location,
                  subtitle:
                      '${precheck.distance} | $locationStatus | $accuracyStatus',
                  subtitleColor: locationColor,
                  compact: false,
                ),
              ),
              SizedBox(width: spacing),
              Expanded(
                child: _buildKpiCard(
                  title: 'Jam Efektif',
                  value: '${precheck.jamMasuk} - ${precheck.jamPulang}',
                  subtitle: requirementMeta,
                  subtitleColor: const Color(0xFF6A7C93),
                  compact: false,
                ),
              ),
            ],
          );
        }

        return Row(
          children: [
            Expanded(
              child: _buildKpiCard(
                title: 'Skema Aktif',
                value: precheck.schema,
                subtitle: sourceMeta,
                subtitleColor: const Color(0xFF6A7C93),
                compact: compact,
              ),
            ),
            SizedBox(width: spacing),
            Expanded(
              child: _buildKpiCard(
                title: 'Lokasi Absensi',
                value: precheck.location,
                subtitle:
                    '${precheck.distance} | $locationStatus | $accuracyStatus',
                subtitleColor: locationColor,
                compact: compact,
              ),
            ),
            SizedBox(width: spacing),
            Expanded(
              child: _buildKpiCard(
                title: 'Jam Efektif',
                value: '${precheck.jamMasuk} - ${precheck.jamPulang}',
                subtitle: requirementMeta,
                subtitleColor: const Color(0xFF6A7C93),
                compact: compact,
              ),
            ),
          ],
        );
      },
    );
  }

  String _buildLocationStatus(
    AttendancePrecheckProvider precheck,
    bool isLocationCompliant,
  ) {
    if (!precheck.wajibGps) {
      return 'GPS tidak diwajibkan';
    }

    if (precheck.stage == AttendancePrecheckStage.gettingGps) {
      return 'Mengambil lokasi';
    }

    if (precheck.stage == AttendancePrecheckStage.checkingArea) {
      return 'Memeriksa area';
    }

    if (precheck.canAttend == null) {
      return 'Status lokasi belum siap';
    }

    if (isLocationCompliant) {
      return 'Siap untuk absen';
    }

    if (precheck.canAttend == false) {
      return 'Di luar area';
    }

    return 'Akurasi belum cukup';
  }

  String _buildAccuracyStatus(AttendancePrecheckProvider precheck) {
    final allowedAccuracy =
        precheck.gpsAccuracy.toDouble() + precheck.gpsAccuracyGrace;

    if (!precheck.wajibGps) {
      return 'Akurasi tidak diwajibkan';
    }

    if (precheck.currentGpsAccuracy == null) {
      return 'Batas akurasi ${allowedAccuracy.toStringAsFixed(1)}m';
    }

    return 'Akurasi ${precheck.currentGpsAccuracy!.toStringAsFixed(1)}m dari batas ${allowedAccuracy.toStringAsFixed(1)}m';
  }

  Widget _buildKpiCard({
    required String title,
    required String value,
    required String subtitle,
    required Color subtitleColor,
    bool compact = false,
  }) {
    final Color primaryColor = Color(AppColors.primaryColorValue);
    return Container(
      width: double.infinity,
      height: compact ? 72 : 92,
      padding: EdgeInsets.all(compact ? 4 : 7),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(compact ? 8 : 12),
        border:
            Border.all(color: primaryColor.withValues(alpha: 0.16), width: 1),
        boxShadow: [
          BoxShadow(
            color: primaryColor.withValues(alpha: 0.08),
            blurRadius: compact ? 6 : 10,
            offset: Offset(0, compact ? 3 : 5),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _AutoMarqueeText(
            text: title,
            style: TextStyle(
              fontSize: compact ? 10 : 12,
              fontWeight: FontWeight.w600,
              color: const Color(0xFF70839B),
            ),
          ),
          SizedBox(height: compact ? 1 : 2),
          _AutoMarqueeText(
            text: value,
            style: TextStyle(
              fontSize: compact ? 12 : 15,
              fontWeight: FontWeight.w800,
              color: const Color(0xFF13426E),
            ),
          ),
          SizedBox(height: compact ? 0.5 : 1.5),
          _AutoMarqueeText(
            text: subtitle,
            style: TextStyle(
              fontSize: compact ? 9.5 : 11,
              fontWeight: FontWeight.w600,
              color: subtitleColor,
            ),
          ),
        ],
      ),
    );
  }
}

class _AutoMarqueeText extends StatefulWidget {
  final String text;
  final TextStyle style;

  const _AutoMarqueeText({
    required this.text,
    required this.style,
  });

  @override
  State<_AutoMarqueeText> createState() => _AutoMarqueeTextState();
}

class _AutoMarqueeTextState extends State<_AutoMarqueeText>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  int _durationMs = 5200;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: Duration(milliseconds: _durationMs),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _syncAnimation({
    required bool overflow,
    required double travel,
  }) {
    if (!mounted) return;

    if (!overflow) {
      if (_controller.isAnimating || _controller.value != 0) {
        _controller.stop();
        _controller.value = 0;
      }
      return;
    }

    final int targetDuration =
        (4500 + (travel * 22)).clamp(4500, 13000).toInt();
    if (targetDuration != _durationMs) {
      _durationMs = targetDuration;
      _controller.duration = Duration(milliseconds: _durationMs);
      if (_controller.isAnimating) {
        _controller
          ..stop()
          ..repeat(reverse: true);
      }
    } else if (!_controller.isAnimating) {
      _controller.repeat(reverse: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final textPainter = TextPainter(
          text: TextSpan(text: widget.text, style: widget.style),
          textDirection: Directionality.of(context),
          maxLines: 1,
        )..layout(maxWidth: double.infinity);

        final maxWidth = constraints.maxWidth;
        final textWidth = textPainter.width;
        final overflow = textWidth > maxWidth + 0.8;
        final travel = (textWidth - maxWidth).clamp(0, 400).toDouble();

        WidgetsBinding.instance.addPostFrameCallback((_) {
          _syncAnimation(overflow: overflow, travel: travel);
        });

        if (!overflow) {
          return Text(
            widget.text,
            style: widget.style,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          );
        }

        return ClipRect(
          child: AnimatedBuilder(
            animation: _controller,
            builder: (context, _) {
              final t = Curves.easeInOut.transform(_controller.value);
              final dx = -(travel * t);
              return Transform.translate(
                offset: Offset(dx, 0),
                child: SizedBox(
                  width: textWidth + 24,
                  child: Text(
                    widget.text,
                    style: widget.style,
                    maxLines: 1,
                    overflow: TextOverflow.visible,
                  ),
                ),
              );
            },
          ),
        );
      },
    );
  }
}
