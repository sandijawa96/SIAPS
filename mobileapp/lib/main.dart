import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:provider/provider.dart';
import 'models/user.dart';
import 'providers/auth_provider.dart';
import 'providers/class_provider.dart';
import 'services/android_update_installer_service.dart';
import 'services/api_service.dart';
import 'services/live_tracking_background_service.dart';
import 'services/mobile_release_service.dart';
import 'services/network_service.dart';
import 'services/push_notification_service.dart';
import 'widgets/permission_checker_widget.dart';
import 'widgets/app_version_text.dart';
import 'screens/login_screen.dart';
import 'screens/main_dashboard.dart';
import 'utils/constants.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Firebase push must not block app startup; if init fails we still run app.
  try {
    if (Firebase.apps.isEmpty) {
      await Firebase.initializeApp();
    }
  } catch (e) {
    debugPrint('Firebase initialization skipped: $e');
  }

  // Initialize network service
  NetworkService().initialize();

  // Initialize API service with network fallback
  ApiService().initialize();

  // Background live tracking is initialized behind a feature flag.
  try {
    await LiveTrackingBackgroundService().initialize();
  } catch (e) {
    debugPrint('Background live tracking initialization skipped: $e');
  }

  runApp(const MyApp());

  // Initialize push stack after UI is mounted to avoid blank startup screen.
  Future<void>.microtask(() async {
    try {
      await PushNotificationService().initialize();
    } catch (e) {
      debugPrint('Push initialization skipped: $e');
    }
  });
}

class MyApp extends StatelessWidget {
  const MyApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (context) => AuthProvider()),
        ChangeNotifierProvider(create: (context) => ClassProvider()),
      ],
      child: MaterialApp(
        title: AppConstants.appName,
        debugShowCheckedModeBanner: false,
        theme: _buildTheme(),
        localizationsDelegates: const [
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        supportedLocales: const [
          Locale('id', 'ID'),
          Locale('en', 'US'),
        ],
        home: const PermissionWrapper(),
      ),
    );
  }

  ThemeData _buildTheme() {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: const Color(AppColors.primaryColorValue),
      brightness: Brightness.light,
      primary: const Color(0xFF0C4A7A),
      secondary: const Color(0xFF1E88E5),
      tertiary: const Color(0xFF21C7A8),
      surface: const Color(0xFFF4F8FC),
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: colorScheme,
      scaffoldBackgroundColor: const Color(0xFFF4F8FC),
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: colorScheme.primary,
        foregroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
      ),
      cardTheme: CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        color: Colors.white,
        shadowColor: const Color(0x170B395E),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: const Color(0xFFF8FBFF),
        hintStyle: const TextStyle(
          color: Color(0xFF7B8EA8),
          fontWeight: FontWeight.w500,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFD8E6F8)),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFFD8E6F8)),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(14),
          borderSide: const BorderSide(color: Color(0xFF2A6FDB), width: 1.5),
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          elevation: 0,
          backgroundColor: colorScheme.primary,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          elevation: 0,
          backgroundColor: colorScheme.primary,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: colorScheme.primary,
          side: BorderSide(color: colorScheme.primary.withValues(alpha: 0.28)),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: colorScheme.primary,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
        ),
      ),
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: colorScheme.primary,
        foregroundColor: Colors.white,
        elevation: 4,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(18)),
      ),
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: Colors.white,
        indicatorColor: colorScheme.primary.withValues(alpha: 0.14),
        labelTextStyle: WidgetStateProperty.resolveWith<TextStyle>(
          (states) {
            final selected = states.contains(WidgetState.selected);
            return TextStyle(
              fontSize: 12,
              fontWeight: selected ? FontWeight.w800 : FontWeight.w600,
              color: selected ? colorScheme.primary : const Color(0xFF6A7C93),
            );
          },
        ),
        iconTheme: WidgetStateProperty.resolveWith<IconThemeData>(
          (states) {
            final selected = states.contains(WidgetState.selected);
            return IconThemeData(
              size: 24,
              color: selected ? colorScheme.primary : const Color(0xFF6A7C93),
            );
          },
        ),
      ),
      bottomNavigationBarTheme: BottomNavigationBarThemeData(
        selectedItemColor: colorScheme.primary,
        unselectedItemColor: const Color(0xFF6A7C93),
        showUnselectedLabels: true,
        selectedLabelStyle: const TextStyle(
          fontWeight: FontWeight.w800,
          fontSize: 12,
        ),
        unselectedLabelStyle: const TextStyle(
          fontWeight: FontWeight.w600,
          fontSize: 12,
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: const Color(0xFF123B67),
        contentTextStyle: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w600,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
    );
  }
}

class PermissionWrapper extends StatefulWidget {
  const PermissionWrapper({super.key});

  @override
  State<PermissionWrapper> createState() => _PermissionWrapperState();
}

class _PermissionWrapperState extends State<PermissionWrapper> {
  @override
  Widget build(BuildContext context) {
    return PermissionCheckerWidget(
      child: const AuthWrapper(),
      onAllPermissionsGranted: () {
        debugPrint('All permissions granted - App ready to use');
      },
      onPermissionResults: (results) {
        debugPrint('Permission results: $results');
        // Log individual permission status
        results.forEach((permission, result) {
          debugPrint('$permission: ${result.isGranted ? "GRANTED" : "DENIED"}');
        });
      },
    );
  }
}

class AuthWrapper extends StatefulWidget {
  const AuthWrapper({super.key});

  @override
  State<AuthWrapper> createState() => _AuthWrapperState();
}

class _AuthWrapperState extends State<AuthWrapper> {
  final MobileReleaseService _mobileReleaseService = MobileReleaseService();
  final AndroidUpdateInstallerService _updateInstallerService =
      AndroidUpdateInstallerService();
  MobileReleaseCheckResult? _releaseCheckResult;
  bool _bootstrapped = false;
  bool _optionalPromptShown = false;
  bool _isUpdateInProgress = false;
  bool _isCheckingRelease = false;
  int? _releaseCheckUserId;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _bootstrap();
    });
  }

  Future<void> _bootstrap() async {
    final authProvider = context.read<AuthProvider>();
    await authProvider.initialize();

    if (!mounted) {
      return;
    }

    setState(() {
      _bootstrapped = true;
    });

    if (authProvider.isAuthenticated && authProvider.user != null) {
      await _checkReleaseForAuthenticatedUser(authProvider.user!);
    }
  }

  Future<void> _checkReleaseForAuthenticatedUser(User user) async {
    if (_isCheckingRelease || _releaseCheckUserId == user.id) {
      return;
    }

    setState(() {
      _isCheckingRelease = true;
    });

    final releaseCheck =
        await _mobileReleaseService.checkAuthenticatedRelease();

    if (!mounted) {
      return;
    }

    setState(() {
      _releaseCheckResult = releaseCheck;
      _releaseCheckUserId = user.id;
      _optionalPromptShown = false;
      _isCheckingRelease = false;
    });
  }

  void _clearReleaseCheckState() {
    if (!mounted) {
      return;
    }

    setState(() {
      _releaseCheckResult = null;
      _releaseCheckUserId = null;
      _optionalPromptShown = false;
      _isCheckingRelease = false;
    });
  }

  Future<void> _performUpdate(MobileReleaseInfo? releaseInfo) async {
    if (releaseInfo == null || _isUpdateInProgress) {
      return;
    }

    _isUpdateInProgress = true;
    final progressNotifier = ValueNotifier<double>(0);
    NavigatorState? rootNavigator;
    var dialogOpened = false;

    if (mounted) {
      rootNavigator = Navigator.of(context, rootNavigator: true);
      dialogOpened = true;

      showDialog<void>(
        context: context,
        barrierDismissible: false,
        useRootNavigator: true,
        builder: (context) {
          return ValueListenableBuilder<double>(
            valueListenable: progressNotifier,
            builder: (context, progressValue, _) {
              final progressPercent = progressValue <= 0
                  ? null
                  : (progressValue * 100).clamp(0, 100).round();

              return AlertDialog(
                title: const Text('Menyiapkan Update'),
                content: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Versi ${releaseInfo.publicVersion} (${releaseInfo.buildNumber}) sedang diproses.',
                    ),
                    const SizedBox(height: 16),
                    LinearProgressIndicator(
                      value: progressValue > 0 ? progressValue : null,
                    ),
                    const SizedBox(height: 10),
                    Text(
                      progressPercent == null
                          ? 'Menghubungkan ke server update...'
                          : 'Mengunduh paket $progressPercent%',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                  ],
                ),
              );
            },
          );
        },
      );
    }

    try {
      await _updateInstallerService.performUpdate(
        releaseInfo,
        onProgress: (value) {
          progressNotifier.value = value;
        },
      );

      if (dialogOpened && rootNavigator?.mounted == true) {
        rootNavigator!.pop();
      }

      if (!mounted) {
        return;
      }

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            releaseInfo.platform == 'android'
                ? 'Installer update dibuka. Lanjutkan pemasangan di perangkat.'
                : 'Tautan update berhasil dibuka.',
          ),
        ),
      );
    } catch (error) {
      if (dialogOpened && rootNavigator?.mounted == true) {
        rootNavigator!.pop();
      }

      if (!mounted) {
        return;
      }

      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Update Gagal'),
          content: Text(error.toString().replaceFirst('Exception: ', '')),
          actions: [
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Tutup'),
            ),
          ],
        ),
      );
    } finally {
      progressNotifier.dispose();
      _isUpdateInProgress = false;
    }
  }

  void _showOptionalUpdatePrompt(MobileReleaseCheckResult releaseCheck) {
    if (_optionalPromptShown ||
        releaseCheck.mustUpdate ||
        !releaseCheck.hasUpdate) {
      return;
    }

    _optionalPromptShown = true;

    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) {
        return;
      }

      final latest = releaseCheck.latest;

      showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Update Tersedia'),
          content: Text(
            latest == null
                ? 'Versi baru aplikasi tersedia.'
                : 'Versi ${latest.publicVersion} (${latest.buildNumber}) tersedia untuk diunduh.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Nanti'),
            ),
            FilledButton(
              onPressed: () async {
                Navigator.of(context).pop();
                await _performUpdate(latest);
              },
              child: const Text('Update'),
            ),
          ],
        ),
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    return Consumer<AuthProvider>(
      builder: (context, authProvider, child) {
        if (!_bootstrapped || authProvider.isLoading || _isCheckingRelease) {
          return const LoadingScreen();
        }

        if (!authProvider.isAuthenticated || authProvider.user == null) {
          if (_releaseCheckUserId != null || _releaseCheckResult != null) {
            WidgetsBinding.instance.addPostFrameCallback((_) {
              _clearReleaseCheckState();
            });
          }

          return const LoginScreen();
        }

        if (_releaseCheckUserId != authProvider.user!.id) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            _checkReleaseForAuthenticatedUser(authProvider.user!);
          });

          return const LoadingScreen();
        }

        final releaseCheck = _releaseCheckResult;
        if (releaseCheck != null &&
            releaseCheck.mustUpdate &&
            releaseCheck.latest != null) {
          return UpdateRequiredScreen(
            releaseInfo: releaseCheck.latest!,
            onOpenUpdate: () => _performUpdate(releaseCheck.latest),
          );
        }

        if (releaseCheck != null &&
            releaseCheck.hasUpdate &&
            !releaseCheck.mustUpdate) {
          _showOptionalUpdatePrompt(releaseCheck);
        }

        return const MainDashboard();
      },
    );
  }
}

class UpdateRequiredScreen extends StatelessWidget {
  final MobileReleaseInfo releaseInfo;
  final Future<void> Function() onOpenUpdate;

  const UpdateRequiredScreen({
    super.key,
    required this.releaseInfo,
    required this.onOpenUpdate,
  });

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Container(
          width: double.infinity,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              colors: [
                Colors.orange.shade50,
                Colors.white,
                Colors.cyan.shade50,
              ],
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
            ),
          ),
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.orange.shade100,
                    borderRadius: BorderRadius.circular(24),
                  ),
                  child: Icon(
                    Icons.system_update_alt,
                    size: 56,
                    color: Colors.orange.shade800,
                  ),
                ),
                const SizedBox(height: 28),
                Text(
                  'Update Wajib Diperlukan',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.bold,
                      ),
                ),
                const SizedBox(height: 12),
                Text(
                  'Versi ${releaseInfo.publicVersion} (${releaseInfo.buildNumber}) sudah tersedia. Aplikasi perlu diperbarui sebelum dapat digunakan kembali.',
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        color: Colors.grey.shade700,
                      ),
                ),
                if ((releaseInfo.releaseNotes ?? '').trim().isNotEmpty) ...[
                  const SizedBox(height: 20),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: Colors.grey.shade200),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Catatan Rilis',
                          style:
                              Theme.of(context).textTheme.titleSmall?.copyWith(
                                    fontWeight: FontWeight.w700,
                                  ),
                        ),
                        const SizedBox(height: 8),
                        Text(
                          releaseInfo.releaseNotes!,
                          style:
                              Theme.of(context).textTheme.bodyMedium?.copyWith(
                                    color: Colors.grey.shade700,
                                  ),
                        ),
                      ],
                    ),
                  ),
                ],
                if ((releaseInfo.distributionNotes ?? '')
                    .trim()
                    .isNotEmpty) ...[
                  const SizedBox(height: 14),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.cyan.shade50,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: Colors.cyan.shade100),
                    ),
                    child: Text(
                      releaseInfo.distributionNotes!,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Colors.cyan.shade900,
                          ),
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: () async {
                      await onOpenUpdate();
                    },
                    icon: const Icon(Icons.download_for_offline_outlined),
                    label: Text(
                      releaseInfo.platform == 'android'
                          ? 'Unduh & Instal Update'
                          : 'Buka Update',
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class LoadingScreen extends StatelessWidget {
  const LoadingScreen({super.key});

  static const Color _ink = Color(0xFF0F2A43);
  static const Color _mutedInk = Color(0xFF5D7083);
  static const Color _deepSky = Color(0xFF1E88E5);
  static const Color _mint = Color(0xFF21C7A8);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF7FBFF),
      body: _SplashBackdrop(
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final size = constraints.biggest;
              final isLandscape = size.width > size.height;
              final compactHeight = size.height < 720;
              final ultraCompactHeight = size.height < 480;
              final showBody = size.height >= 760 &&
                  size.width >= 360 &&
                  !ultraCompactHeight;
              final showFooter = size.height >= 760;
              final showTicket =
                  !isLandscape && size.width >= 390 && size.height >= 760;
              final showSecureBadge = size.width >= 340;
              final heroMaxWidth = isLandscape ? 420.0 : 520.0;
              final contentMaxWidth = isLandscape ? 960.0 : 560.0;
              final horizontalPadding = isLandscape ? 20.0 : 18.0;
              final topPadding = ultraCompactHeight ? 12.0 : 18.0;
              final sectionGap =
                  ultraCompactHeight ? 14.0 : (compactHeight ? 18.0 : 24.0);

              return Stack(
                children: [
                  if (showTicket)
                    Positioned(
                      top: 132,
                      right: 14,
                      child: Transform.rotate(
                        angle: -0.18,
                        child: _SplashAttendanceTicket(),
                      ),
                    ),
                  Padding(
                    padding: EdgeInsets.fromLTRB(
                      horizontalPadding,
                      topPadding,
                      horizontalPadding,
                      18,
                    ),
                    child: Center(
                      child: ConstrainedBox(
                        constraints: BoxConstraints(maxWidth: contentMaxWidth),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (showSecureBadge)
                              Align(
                                alignment: Alignment.topRight,
                                child: _buildSecureBadge(),
                              ),
                            SizedBox(height: showSecureBadge ? sectionGap : 0),
                            Expanded(
                              child: Center(
                                child: FittedBox(
                                  fit: BoxFit.scaleDown,
                                  alignment: Alignment.center,
                                  child: SizedBox(
                                    width: heroMaxWidth,
                                    child: Column(
                                      mainAxisSize: MainAxisSize.min,
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        _buildHero(
                                          context,
                                          compactHeight,
                                          showBody,
                                          ultraCompactHeight,
                                        ),
                                        SizedBox(height: sectionGap),
                                        _buildStatusCard(
                                          context,
                                          compactHeight: compactHeight,
                                          ultraCompactHeight:
                                              ultraCompactHeight,
                                        ),
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                            ),
                            if (showFooter) ...[
                              SizedBox(height: ultraCompactHeight ? 8 : 12),
                              _buildFooter(context),
                            ],
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  Widget _buildSecureBadge() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.84),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1A1E88E5),
            blurRadius: 18,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: const Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.verified_user_rounded, size: 16, color: _deepSky),
          SizedBox(width: 6),
          Text(
            'Secure',
            style: TextStyle(
              color: _ink,
              fontWeight: FontWeight.w800,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHero(
    BuildContext context,
    bool compactHeight,
    bool showBody,
    bool ultraCompactHeight,
  ) {
    final logoSize = ultraCompactHeight ? 64.0 : (compactHeight ? 76.0 : 92.0);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _buildLogoMark(size: logoSize),
        SizedBox(height: ultraCompactHeight ? 12 : (compactHeight ? 16 : 20)),
        if (!ultraCompactHeight) ...[
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: const Color(0xFFE5F6FF),
              borderRadius: BorderRadius.circular(999),
              border: Border.all(color: const Color(0xFFC8EBFF)),
            ),
            child: const Wrap(
              spacing: 7,
              runSpacing: 4,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                Icon(Icons.auto_awesome_rounded, size: 16, color: _deepSky),
                Text(
                  'Absensi, izin, dan monitoring siswa',
                  style: TextStyle(
                    color: _deepSky,
                    fontWeight: FontWeight.w800,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          SizedBox(height: compactHeight ? 14 : 18),
        ],
        Text(
          'Menyiapkan pusat aktivitas sekolah.',
          style: Theme.of(context).textTheme.displaySmall?.copyWith(
                color: _ink,
                fontWeight: FontWeight.w900,
                height: 1.03,
                letterSpacing: -1.2,
                fontSize: ultraCompactHeight ? 26 : (compactHeight ? 30 : 38),
              ),
        ),
        if (!ultraCompactHeight) ...[
          SizedBox(height: compactHeight ? 8 : 12),
          Text(
            showBody
                ? 'SIAPS mobile menyatukan absensi real-time, rekap kehadiran, izin, pengumuman, dan notifikasi sekolah dalam satu akses.'
                : 'Akses absensi, izin, pengumuman, dan rekap sekolah dari satu aplikasi mobile.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: _mutedInk,
                  height: 1.5,
                  fontWeight: FontWeight.w500,
                  fontSize: compactHeight ? 13 : 14,
                ),
          ),
        ],
      ],
    );
  }

  Widget _buildStatusCard(
    BuildContext context, {
    required bool compactHeight,
    required bool ultraCompactHeight,
  }) {
    final mutedText = Theme.of(context).textTheme.bodySmall?.copyWith(
          color: _mutedInk,
          height: 1.45,
          fontWeight: FontWeight.w500,
        );

    return Container(
      padding:
          EdgeInsets.all(ultraCompactHeight ? 14 : (compactHeight ? 16 : 18)),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.9),
        borderRadius: BorderRadius.circular(
          ultraCompactHeight ? 22 : (compactHeight ? 24 : 28),
        ),
        border: Border.all(color: Colors.white, width: 1.4),
        boxShadow: const [
          BoxShadow(
            color: Color(0x161E88E5),
            blurRadius: 24,
            offset: Offset(0, 14),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: ultraCompactHeight ? 40 : (compactHeight ? 44 : 48),
                height: ultraCompactHeight ? 40 : (compactHeight ? 44 : 48),
                decoration: BoxDecoration(
                  color: const Color(0xFFEAF5FF),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Padding(
                  padding: EdgeInsets.all(ultraCompactHeight ? 9 : 10),
                  child: CircularProgressIndicator(
                    strokeWidth: 3,
                    valueColor: AlwaysStoppedAnimation<Color>(_deepSky),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Menyiapkan aplikasi...',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: _ink,
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.3,
                            fontSize: ultraCompactHeight ? 15 : null,
                          ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      'Memuat sesi, izin, dan layanan sekolah.',
                      style: mutedText?.copyWith(
                        fontSize: ultraCompactHeight ? 12 : null,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          SizedBox(height: ultraCompactHeight ? 12 : 14),
          ClipRRect(
            borderRadius: BorderRadius.circular(999),
            child: const LinearProgressIndicator(
              minHeight: 7,
              backgroundColor: Color(0xFFD9EBF9),
              valueColor: AlwaysStoppedAnimation<Color>(_mint),
            ),
          ),
          SizedBox(height: ultraCompactHeight ? 10 : 12),
          Row(
            children: [
              Expanded(
                child: AppVersionText(
                  prefix: 'Versi ',
                  fallback: AppConstants.appVersion,
                  style: mutedText?.copyWith(fontWeight: FontWeight.w700),
                ),
              ),
              Text(
                AppStrings.loading,
                textAlign: TextAlign.right,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: _deepSky,
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildFooter(BuildContext context) {
    final textStyle = Theme.of(context).textTheme.bodySmall?.copyWith(
          color: _mutedInk,
          height: 1.45,
          fontWeight: FontWeight.w500,
        );

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Akses aman untuk presensi, izin, dan informasi sekolah.',
          textAlign: TextAlign.center,
          style: textStyle,
        ),
        const SizedBox(height: 6),
        Text(
          'Copyright Ictsmanis@2025',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: _mutedInk.withValues(alpha: 0.9),
                fontWeight: FontWeight.w700,
                letterSpacing: 0.2,
              ),
        ),
      ],
    );
  }

  Widget _buildLogoMark({required double size}) {
    return Container(
      width: size,
      height: size,
      padding: EdgeInsets.all(size * 0.13),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(size * 0.34),
        boxShadow: const [
          BoxShadow(
            color: Color(0x241E88E5),
            blurRadius: 24,
            offset: Offset(0, 12),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(size * 0.22),
        child: Image.asset(
          'assets/icon.png',
          fit: BoxFit.contain,
        ),
      ),
    );
  }
}

class _SplashBackdrop extends StatelessWidget {
  const _SplashBackdrop({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFFE4F5FF),
            Color(0xFFF8FCFF),
            Color(0xFFEAF8F4),
          ],
        ),
      ),
      child: Stack(
        children: [
          const Positioned(
            top: -90,
            right: -80,
            child: _SplashOrb(
              size: 240,
              color: Color(0x5564B5F6),
            ),
          ),
          const Positioned(
            top: 220,
            left: -120,
            child: _SplashOrb(
              size: 260,
              color: Color(0x4421C7A8),
            ),
          ),
          child,
        ],
      ),
    );
  }
}

class _SplashOrb extends StatelessWidget {
  const _SplashOrb({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: color,
      ),
    );
  }
}

class _SplashAttendanceTicket extends StatelessWidget {
  const _SplashAttendanceTicket();

  @override
  Widget build(BuildContext context) {
    return IgnorePointer(
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.72),
          borderRadius: BorderRadius.circular(20),
          border: Border.all(color: Colors.white),
        ),
        child: const Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.fingerprint_rounded, color: LoadingScreen._deepSky),
            SizedBox(width: 8),
            Text(
              'Check-in',
              style: TextStyle(
                color: LoadingScreen._ink,
                fontWeight: FontWeight.w900,
                fontSize: 12,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
