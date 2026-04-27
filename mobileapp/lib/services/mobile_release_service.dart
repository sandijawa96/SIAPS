import 'package:flutter/foundation.dart';

import 'app_info_service.dart';
import 'api_service.dart';

class MobileReleaseInfo {
  final String platform;
  final String platformLabel;
  final String publicVersion;
  final int buildNumber;
  final String? downloadUrl;
  final String? assetOriginalName;
  final String? assetMimeType;
  final String? checksumSha256;
  final int? fileSizeBytes;
  final String? releaseNotes;
  final String? distributionNotes;
  final String updateMode;

  const MobileReleaseInfo({
    required this.platform,
    required this.platformLabel,
    required this.publicVersion,
    required this.buildNumber,
    required this.downloadUrl,
    required this.assetOriginalName,
    required this.assetMimeType,
    required this.checksumSha256,
    required this.fileSizeBytes,
    required this.releaseNotes,
    required this.distributionNotes,
    required this.updateMode,
  });

  factory MobileReleaseInfo.fromJson(Map<String, dynamic> json) {
    return MobileReleaseInfo(
      platform: (json['platform'] ?? '').toString(),
      platformLabel: (json['platform_label'] ?? json['platform'] ?? '').toString(),
      publicVersion: (json['public_version'] ?? '').toString(),
      buildNumber: int.tryParse((json['build_number'] ?? '').toString()) ?? 0,
      downloadUrl: json['download_url']?.toString(),
      assetOriginalName: json['asset_original_name']?.toString(),
      assetMimeType: json['asset_mime_type']?.toString(),
      checksumSha256: json['checksum_sha256']?.toString(),
      fileSizeBytes: int.tryParse((json['file_size_bytes'] ?? '').toString()),
      releaseNotes: json['release_notes']?.toString(),
      distributionNotes: json['distribution_notes']?.toString(),
      updateMode: (json['update_mode'] ?? 'optional').toString(),
    );
  }
}

class MobileReleaseCheckResult {
  final bool hasUpdate;
  final bool isSupported;
  final bool mustUpdate;
  final String updateMode;
  final MobileReleaseInfo? latest;

  const MobileReleaseCheckResult({
    required this.hasUpdate,
    required this.isSupported,
    required this.mustUpdate,
    required this.updateMode,
    required this.latest,
  });

  factory MobileReleaseCheckResult.fromJson(Map<String, dynamic> json) {
    return MobileReleaseCheckResult(
      hasUpdate: json['has_update'] == true,
      isSupported: json['is_supported'] != false,
      mustUpdate: json['must_update'] == true,
      updateMode: (json['update_mode'] ?? 'none').toString(),
      latest: json['latest'] is Map<String, dynamic>
          ? MobileReleaseInfo.fromJson(json['latest'] as Map<String, dynamic>)
          : null,
    );
  }
}

class MobileReleaseService {
  final ApiService _apiService = ApiService();
  final AppInfoService _appInfoService = AppInfoService();

  String? resolveCurrentPlatform() {
    if (kIsWeb) {
      return null;
    }

    switch (defaultTargetPlatform) {
      case TargetPlatform.android:
        return 'android';
      case TargetPlatform.iOS:
        return 'ios';
      default:
        return null;
    }
  }

  Future<MobileReleaseCheckResult?> checkAuthenticatedRelease() async {
    final platform = resolveCurrentPlatform();
    if (platform == null) {
      return null;
    }

    try {
      final appVersion = await _appInfoService.getCurrentVersion();
      final buildNumber = await _appInfoService.getCurrentBuildNumberAsInt();
      final response = await _apiService.get<Map<String, dynamic>>(
        '/mobile-releases/check-authenticated',
        queryParameters: {
          'platform': platform,
          'app_version': appVersion,
          if (buildNumber != null) 'build_number': buildNumber,
        },
      );

      final payload = response.data;
      if (payload == null || payload['data'] is! Map<String, dynamic>) {
        return null;
      }

      return MobileReleaseCheckResult.fromJson(
        payload['data'] as Map<String, dynamic>,
      );
    } catch (error) {
      debugPrint('Authenticated mobile release check skipped: $error');
      return null;
    }
  }
}
