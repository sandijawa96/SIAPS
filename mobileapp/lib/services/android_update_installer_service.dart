import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:path/path.dart' as path;
import 'package:path_provider/path_provider.dart';
import 'package:url_launcher/url_launcher.dart';

import 'api_service.dart';
import 'mobile_release_service.dart';

class AndroidUpdateInstallerService {
  static const MethodChannel _channel = MethodChannel('siaps/app_update');
  final ApiService _apiService = ApiService();

  Future<void> performUpdate(
    MobileReleaseInfo releaseInfo, {
    void Function(double progress)? onProgress,
  }) async {
    final downloadUrl = releaseInfo.downloadUrl;
    if (downloadUrl == null || downloadUrl.trim().isEmpty) {
      throw Exception('URL update belum tersedia.');
    }

    if (kIsWeb || defaultTargetPlatform != TargetPlatform.android) {
      await _openExternal(downloadUrl);
      return;
    }

    final uri = Uri.tryParse(downloadUrl.trim());
    if (uri == null) {
      throw Exception('URL update tidak valid.');
    }

    final tempDir = await getTemporaryDirectory();
    final fileName = _resolveFileName(uri, releaseInfo);
    final filePath = path.join(tempDir.path, fileName);

    _apiService.initialize();

    await _apiService.dio.downloadUri(
      uri,
      filePath,
      deleteOnError: true,
      onReceiveProgress: (received, total) {
        if (total <= 0) {
          onProgress?.call(0);
          return;
        }

        onProgress?.call(received / total);
      },
    );

    final expectedChecksum = releaseInfo.checksumSha256?.trim();
    if (expectedChecksum != null && expectedChecksum.isNotEmpty) {
      final valid = await _verifyChecksum(filePath, expectedChecksum);
      if (!valid) {
        try {
          await File(filePath).delete();
        } catch (_) {
        }
        throw Exception('Checksum file update tidak cocok. Unduhan dibatalkan.');
      }
    }

    await _channel.invokeMethod('installApk', {
      'filePath': filePath,
    });
  }

  Future<void> _openExternal(String rawUrl) async {
    final uri = Uri.tryParse(rawUrl);
    if (uri == null) {
      throw Exception('URL update tidak valid.');
    }

    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched) {
      throw Exception('Gagal membuka tautan update.');
    }
  }

  String _resolveFileName(Uri uri, MobileReleaseInfo releaseInfo) {
    final originalName = releaseInfo.assetOriginalName?.trim();
    if (originalName != null && originalName.isNotEmpty && originalName.contains('.')) {
      return originalName;
    }

    final fromUrl = uri.pathSegments.isNotEmpty ? uri.pathSegments.last.trim() : '';
    if (fromUrl.isNotEmpty && fromUrl.contains('.')) {
      return fromUrl;
    }

    return 'siaps-${releaseInfo.platform}-${releaseInfo.publicVersion}-${releaseInfo.buildNumber}.apk';
  }

  Future<bool> _verifyChecksum(String filePath, String expectedChecksum) async {
    final digest = await sha256.bind(File(filePath).openRead()).first;
    return digest.toString().toLowerCase() == expectedChecksum.toLowerCase();
  }
}
