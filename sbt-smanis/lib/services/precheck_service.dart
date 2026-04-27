import 'dart:async';
import 'dart:io';

import 'package:flutter/services.dart';

import '../models/precheck_item.dart';
import 'exam_guard_service.dart';
import 'sbt_api_service.dart';

class PrecheckService {
  PrecheckService({ExamGuardService? guard, SbtApiService? sbt})
    : _guard = guard ?? ExamGuardService.instance,
      _sbt = sbt ?? SbtApiService.instance;

  final ExamGuardService _guard;
  final SbtApiService _sbt;
  static const _isrgRootX1Asset = 'assets/certs/isrgrootx1.pem';

  Future<List<PrecheckItem>> runAll() async {
    await _sbt.loadConfig(force: true);

    final results = await Future.wait<PrecheckItem>([
      _checkSbtConfig(),
      _checkAppVersion(),
      _checkCbtServer(),
      _checkBattery(),
      _checkMultiWindow(),
      _checkScreenPinning(),
      _checkDoNotDisturb(),
      _checkOverlayProtection(),
      _checkScreenProtection(),
    ]);

    return results;
  }

  Future<PrecheckItem> _checkSbtConfig() async {
    final config = _sbt.config;

    if (!config.enabled) {
      return const PrecheckItem(
        id: 'sbt-config',
        title: 'Konfigurasi SBT',
        description: 'Aplikasi ujian sedang dinonaktifkan oleh SIAPS.',
        status: PrecheckStatus.failed,
      );
    }

    if (config.maintenanceEnabled) {
      return PrecheckItem(
        id: 'sbt-config',
        title: 'Konfigurasi SBT',
        description:
            config.maintenanceMessage ??
            'Aplikasi ujian sedang dalam mode maintenance.',
        status: PrecheckStatus.failed,
      );
    }

    if (_sbt.lastConfigError != null) {
      return PrecheckItem(
        id: 'sbt-config',
        title: 'Konfigurasi SBT',
        description: 'SIAPS belum terhubung, konfigurasi bawaan dipakai.',
        status: PrecheckStatus.warning,
        detail: _sbt.lastConfigError,
        required: false,
      );
    }

    return PrecheckItem(
      id: 'sbt-config',
      title: 'Konfigurasi SBT',
      description: 'Pengaturan ujian berhasil disinkronkan dari SIAPS.',
      status: PrecheckStatus.passed,
      detail: 'Versi ${config.configVersion}',
      required: false,
    );
  }

  Future<PrecheckItem> _checkAppVersion() async {
    final check = await _sbt.checkForUpdate();

    if (!check.available) {
      return PrecheckItem(
        id: 'app-update',
        title: 'Versi aplikasi',
        description:
            check.message ?? 'Release SBT belum tersedia di Pusat Download.',
        status: PrecheckStatus.warning,
        required: false,
      );
    }

    if (check.mustUpdate || !check.isSupported) {
      return PrecheckItem(
        id: 'app-update',
        title: 'Versi aplikasi',
        description:
            'Versi SBT ini sudah kedaluwarsa. Perbarui aplikasi sebelum ujian.',
        status: PrecheckStatus.failed,
        detail: 'Versi terbaru: ${check.latestLabel}',
        actionLabel: check.downloadUrl == null ? null : 'Unduh Update',
      );
    }

    if (check.hasUpdate) {
      return PrecheckItem(
        id: 'app-update',
        title: 'Versi aplikasi',
        description:
            'Update SBT tersedia, tetapi versi ini masih diizinkan untuk ujian.',
        status: PrecheckStatus.warning,
        detail: 'Versi terbaru: ${check.latestLabel}',
        required: false,
        actionLabel: check.downloadUrl == null ? null : 'Unduh Update',
      );
    }

    return PrecheckItem(
      id: 'app-update',
      title: 'Versi aplikasi',
      description: 'Aplikasi SBT sudah memakai versi terbaru.',
      status: PrecheckStatus.passed,
      detail:
          '${check.currentVersion ?? '-'} (${check.currentBuildNumber ?? '-'})',
      required: false,
    );
  }

  Future<PrecheckItem> _checkCbtServer() async {
    HttpClient? client;

    try {
      client = await _createCbtHttpClient();
      final request = await client
          .getUrl(Uri.parse(_sbt.config.examUrl))
          .timeout(const Duration(seconds: 6));
      request.headers.set(HttpHeaders.userAgentHeader, 'SBT-SMANIS/1.0');

      final response = await request.close().timeout(
        const Duration(seconds: 8),
      );
      final ok = response.statusCode >= 200 && response.statusCode < 500;
      await response.drain<void>();

      if (ok) {
        return PrecheckItem(
          id: 'server',
          title: 'Server ujian',
          description: 'Situs CBT dapat diakses.',
          status: PrecheckStatus.passed,
          detail: 'HTTP ${response.statusCode}',
        );
      }

      return PrecheckItem(
        id: 'server',
        title: 'Server ujian',
        description: 'Situs CBT merespons dengan status tidak normal.',
        status: PrecheckStatus.failed,
        detail: 'HTTP ${response.statusCode}',
      );
    } on Object catch (error) {
      final isCertificateError = error is HandshakeException;
      return PrecheckItem(
        id: 'server',
        title: 'Server ujian',
        description: isCertificateError
            ? 'Sertifikat HTTPS CBT belum dipercaya perangkat ini.'
            : 'Situs CBT belum bisa diakses dari perangkat ini.',
        status: PrecheckStatus.failed,
        detail: _formatServerError(error),
      );
    } finally {
      client?.close(force: true);
    }
  }

  Future<HttpClient> _createCbtHttpClient() async {
    final context = SecurityContext(withTrustedRoots: true);

    try {
      final certificate = await rootBundle.load(_isrgRootX1Asset);
      context.setTrustedCertificatesBytes(
        certificate.buffer.asUint8List(
          certificate.offsetInBytes,
          certificate.lengthInBytes,
        ),
      );
    } on Object {
      // The system trust store is still used if the bundled root cannot load.
    }

    return HttpClient(context: context)
      ..connectionTimeout = const Duration(seconds: 5);
  }

  String _formatServerError(Object error) {
    if (error is HandshakeException) {
      return 'Validasi sertifikat HTTPS gagal. ${error.message}';
    }

    return error.toString();
  }

  Future<PrecheckItem> _checkBattery() async {
    final battery = await _guard.getBatteryInfo();

    if (battery.level < 0) {
      return const PrecheckItem(
        id: 'battery',
        title: 'Baterai',
        description: 'Status baterai tidak dapat dibaca.',
        status: PrecheckStatus.warning,
        required: false,
      );
    }

    final enough = battery.level >= _sbt.config.minimumBatteryLevel;
    final charging = battery.isCharging;

    if (enough || charging) {
      return PrecheckItem(
        id: 'battery',
        title: 'Baterai',
        description: 'Daya perangkat cukup untuk memulai ujian.',
        status: PrecheckStatus.passed,
        detail: '${battery.level}%${charging ? ' - mengisi daya' : ''}',
      );
    }

    return PrecheckItem(
      id: 'battery',
      title: 'Baterai',
      description: 'Isi daya perangkat sebelum ujian.',
      status: PrecheckStatus.failed,
      detail: '${battery.level}%',
    );
  }

  Future<PrecheckItem> _checkMultiWindow() async {
    final inMultiWindow = await _guard.isInMultiWindowMode();

    if (!inMultiWindow) {
      return const PrecheckItem(
        id: 'window',
        title: 'Tampilan aplikasi',
        description: 'Aplikasi berjalan penuh, tidak dalam split-screen.',
        status: PrecheckStatus.passed,
      );
    }

    return const PrecheckItem(
      id: 'window',
      title: 'Tampilan aplikasi',
      description: 'Tutup mode split-screen atau floating window.',
      status: PrecheckStatus.failed,
    );
  }

  Future<PrecheckItem> _checkDoNotDisturb() async {
    final dnd = await _guard.getDoNotDisturbStatus();

    if (!dnd.supported) {
      return PrecheckItem(
        id: 'dnd',
        title: 'Mode Jangan Ganggu',
        description: 'Perangkat ini belum mendukung pemeriksaan DND.',
        status: _sbt.config.requireDnd
            ? PrecheckStatus.failed
            : PrecheckStatus.warning,
        required: _sbt.config.requireDnd,
      );
    }

    if (!dnd.permissionGranted) {
      return PrecheckItem(
        id: 'dnd',
        title: 'Mode Jangan Ganggu',
        description: 'Izinkan akses DND agar notifikasi tidak mengganggu.',
        status: _sbt.config.requireDnd
            ? PrecheckStatus.failed
            : PrecheckStatus.warning,
        detail: 'Izin belum diberikan',
        required: _sbt.config.requireDnd,
        actionLabel: 'Buka Pengaturan',
      );
    }

    if (dnd.enabled) {
      return PrecheckItem(
        id: 'dnd',
        title: 'Mode Jangan Ganggu',
        description: 'Mode gangguan sudah aktif.',
        status: PrecheckStatus.passed,
        detail: dnd.label,
        required: false,
      );
    }

    return PrecheckItem(
      id: 'dnd',
      title: 'Mode Jangan Ganggu',
      description: 'Aktifkan DND sebelum ujian agar notifikasi tidak muncul.',
      status: _sbt.config.requireDnd
          ? PrecheckStatus.failed
          : PrecheckStatus.warning,
      detail: dnd.label,
      required: _sbt.config.requireDnd,
      actionLabel: 'Buka Pengaturan',
    );
  }

  Future<PrecheckItem> _checkScreenPinning() async {
    final status = await _guard.getScreenPinningStatus();
    final required = _sbt.config.requireScreenPinning;

    if (!required) {
      return PrecheckItem(
        id: 'screen-pinning',
        title: 'Sematan layar',
        description: 'Screen pinning tidak diwajibkan oleh pengaturan SIAPS.',
        status: status.active ? PrecheckStatus.passed : PrecheckStatus.warning,
        detail: status.label,
        required: false,
      );
    }

    if (!status.supported) {
      return PrecheckItem(
        id: 'screen-pinning',
        title: 'Sematan layar',
        description: 'Perangkat ini belum mendukung screen pinning.',
        status: PrecheckStatus.failed,
        detail: status.label,
      );
    }

    if (status.active) {
      return PrecheckItem(
        id: 'screen-pinning',
        title: 'Sematan layar',
        description: 'Aplikasi sudah disematkan di layar.',
        status: PrecheckStatus.passed,
        detail: status.label,
      );
    }

    return PrecheckItem(
      id: 'screen-pinning',
      title: 'Sematan layar',
      description:
          'Setujui sematan layar agar siswa tidak mudah keluar dari ujian.',
      status: PrecheckStatus.failed,
      detail: status.label,
      actionLabel: status.canRequest ? 'Aktifkan Sematan' : null,
    );
  }

  Future<PrecheckItem> _checkOverlayProtection() async {
    final overlay = await _guard.getOverlayProtectionStatus();

    if (overlay.supported) {
      return const PrecheckItem(
        id: 'overlay',
        title: 'Aplikasi mengambang',
        description: 'Perangkat mendukung pemblokiran overlay saat ujian.',
        status: PrecheckStatus.passed,
        detail: 'Aktif saat ujian dimulai',
        required: false,
      );
    }

    return PrecheckItem(
      id: 'overlay',
      title: 'Aplikasi mengambang',
      description: 'Android lama tidak dapat memblokir semua overlay.',
      status: _sbt.config.requireOverlayProtection
          ? PrecheckStatus.failed
          : PrecheckStatus.warning,
      detail: 'Tutup chat head, bubble, dan floating app secara manual',
      required: _sbt.config.requireOverlayProtection,
    );
  }

  Future<PrecheckItem> _checkScreenProtection() async {
    return const PrecheckItem(
      id: 'screen',
      title: 'Proteksi layar',
      description: 'Screenshot dan screen recording akan diblokir saat ujian.',
      status: PrecheckStatus.passed,
      detail: 'Aktif saat ujian dimulai',
      required: false,
    );
  }
}
