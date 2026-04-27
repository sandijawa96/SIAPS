import 'package:package_info_plus/package_info_plus.dart';

import '../utils/constants.dart';

class AppInfoService {
  static final AppInfoService _instance = AppInfoService._internal();
  factory AppInfoService() => _instance;
  AppInfoService._internal();

  PackageInfo? _cachedPackageInfo;

  Future<PackageInfo> getPackageInfo() async {
    if (_cachedPackageInfo != null) {
      return _cachedPackageInfo!;
    }

    _cachedPackageInfo = await PackageInfo.fromPlatform();
    return _cachedPackageInfo!;
  }

  Future<String> getVersionLabel({
    bool includeBuild = true,
    bool includeAppName = false,
  }) async {
    final version = await getCurrentVersion();
    final buildNumber = await getCurrentBuildNumber();

    final versionText = includeBuild
        ? '$version ($buildNumber)'
        : version;

    final packageInfo = await getPackageInfo();
    if (!includeAppName) {
      return versionText;
    }

    final appName = packageInfo.appName.isNotEmpty
        ? packageInfo.appName
        : AppConstants.appName;

    return '$appName $versionText';
  }

  Future<String> getCurrentVersion() async {
    final packageInfo = await getPackageInfo();
    return packageInfo.version.isNotEmpty
        ? packageInfo.version
        : AppConstants.appVersion;
  }

  Future<String> getCurrentBuildNumber() async {
    final packageInfo = await getPackageInfo();
    return packageInfo.buildNumber.isNotEmpty
        ? packageInfo.buildNumber
        : AppConstants.appBuildNumber;
  }

  Future<int?> getCurrentBuildNumberAsInt() async {
    final buildNumber = await getCurrentBuildNumber();
    return int.tryParse(buildNumber);
  }
}
