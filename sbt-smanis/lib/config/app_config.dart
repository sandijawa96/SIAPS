class AppConfig {
  static const appName = 'SBT SMANIS';
  static const appKey = 'sbt-smanis';
  static const appLongName = 'Smartphone Based Test';
  static const schoolName = 'SMAN 1 Sumber Cirebon';
  static const appVersion = String.fromEnvironment(
    'SBT_APP_VERSION',
    defaultValue: '1.0.0',
  );
  static const appBuildNumber = String.fromEnvironment(
    'SBT_BUILD_NUMBER',
    defaultValue: '1',
  );
  static const siapsApiBaseUrl = String.fromEnvironment(
    'SIAPS_API_BASE_URL',
    defaultValue: 'https://load.sman1sumbercirebon.sch.id/api',
  );
  static const examUrl = 'https://res.sman1sumbercirebon.sch.id';
  static const examHost = 'res.sman1sumbercirebon.sch.id';
  static const minimumBatteryLevel = 20;
}
