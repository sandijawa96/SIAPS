import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../widgets/custom_text_field.dart';
import '../widgets/app_version_text.dart';
import '../widgets/notification_popup.dart';
import '../utils/constants.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _staffFormKey = GlobalKey<FormState>();
  final _studentFormKey = GlobalKey<FormState>();

  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _nisController = TextEditingController();
  final _birthDateController = TextEditingController();

  bool _rememberMe = false;
  int _selectedTab = 1;

  static const Color _ink = Color(0xFF0F2A43);
  static const Color _mutedInk = Color(0xFF5D7083);
  static const Color _sky = Color(0xFF64B5F6);
  static const Color _deepSky = Color(0xFF1E88E5);
  static const Color _mint = Color(0xFF21C7A8);
  static const Color _paper = Color(0xFFF7FBFF);

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _nisController.dispose();
    _birthDateController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      resizeToAvoidBottomInset: true,
      backgroundColor: _paper,
      body: Consumer<AuthProvider>(
        builder: (context, authProvider, child) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (authProvider.error != null) {
              _showErrorMessage(authProvider.error!);
              authProvider.clearError();
            }
          });

          return _LoginBackdrop(
            child: SafeArea(
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final layout = _LoginLayoutConfig.from(context, constraints);
                  final contentHeight = math
                      .max(
                        0,
                        constraints.maxHeight -
                            layout.topPadding -
                            layout.bottomPadding,
                      )
                      .toDouble();

                  return Padding(
                    padding: EdgeInsets.fromLTRB(
                      layout.horizontalPadding,
                      layout.topPadding,
                      layout.horizontalPadding,
                      layout.bottomPadding,
                    ),
                    child: Center(
                      child: ConstrainedBox(
                        constraints: BoxConstraints(
                          maxWidth: layout.maxContentWidth,
                        ),
                        child: SizedBox(
                          height: contentHeight,
                          child: layout.isWide
                              ? _buildWideLayout(authProvider, layout)
                              : _buildNarrowLayout(authProvider, layout),
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          );
        },
      ),
    );
  }

  Widget _buildWideLayout(
    AuthProvider authProvider,
    _LoginLayoutConfig layout,
  ) {
    final showIntro = layout.showHero || layout.showCompactHero;

    return Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          flex: 11,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _buildTopBar(layout),
              if (showIntro) SizedBox(height: layout.sectionGap),
              Expanded(
                child: showIntro
                    ? Align(
                        alignment: layout.showHero
                            ? Alignment.centerLeft
                            : Alignment.topLeft,
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            layout.showHero
                                ? _buildHeroHeader(layout)
                                : _buildCompactHeroHeader(layout),
                            if (layout.showHero && layout.showFeatures) ...[
                              SizedBox(height: layout.sectionGap),
                              _buildFeatureStrip(),
                            ],
                          ],
                        ),
                      )
                    : const SizedBox.shrink(),
              ),
              if (layout.showFooter) _buildFooter(),
            ],
          ),
        ),
        SizedBox(width: layout.sectionGap + 8),
        Expanded(
          flex: 10,
          child: Align(
            alignment: Alignment.centerRight,
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: _buildLoginCard(authProvider, layout),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildNarrowLayout(
    AuthProvider authProvider,
    _LoginLayoutConfig layout,
  ) {
    final showIntro = layout.showHero || layout.showCompactHero;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _buildTopBar(layout),
        SizedBox(height: layout.sectionGap),
        if (showIntro) ...[
          layout.showHero
              ? _buildHeroHeader(layout)
              : _buildCompactHeroHeader(layout),
          SizedBox(height: layout.sectionGap),
        ],
        Expanded(
          child: Align(
            alignment: showIntro ? Alignment.topCenter : Alignment.center,
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: _buildLoginCard(authProvider, layout),
            ),
          ),
        ),
        if (layout.showFooter) ...[
          SizedBox(height: layout.ultraCompactHeight ? 6 : 8),
          _buildFooter(),
        ],
      ],
    );
  }

  Widget _buildTopBar(_LoginLayoutConfig layout) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 320 || layout.ultraCompactHeight;
        final identity = Row(
          children: [
            _buildLogoMark(size: layout.logoSize),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    AppConstants.appName,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: _ink,
                          fontWeight: FontWeight.w900,
                          letterSpacing: -0.4,
                        ),
                  ),
                  if (layout.showVersion)
                    AppVersionText(
                      prefix: 'Mobile v',
                      fallback: AppConstants.appVersion,
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                            color: _mutedInk,
                            fontWeight: FontWeight.w600,
                          ),
                    ),
                ],
              ),
            ),
          ],
        );

        final secureBadge = Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.82),
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
              Icon(
                Icons.verified_user_rounded,
                size: 16,
                color: _deepSky,
              ),
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

        if (compact) {
          return identity;
        }

        return Row(
          children: [
            Expanded(child: identity),
            if (layout.showSecureBadge) ...[
              const SizedBox(width: 12),
              secureBadge,
            ],
          ],
        );
      },
    );
  }

  Widget _buildHeroHeader(_LoginLayoutConfig layout) {
    final compactCopy = layout.compactHeight && !layout.isWide;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
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
        SizedBox(height: layout.compactHeight ? 14 : 18),
        Text(
          compactCopy
              ? 'Masuk ke pusat aktivitas sekolah.'
              : 'Masuk untuk mulai\nhari sekolah Anda.',
          style: Theme.of(context).textTheme.displaySmall?.copyWith(
                color: _ink,
                fontWeight: FontWeight.w900,
                height: 1.03,
                letterSpacing: -1.5,
                fontSize: layout.heroTitleSize,
              ),
        ),
        const SizedBox(height: 12),
        Text(
          compactCopy
              ? 'Akses absensi, izin, pengumuman, dan rekap sekolah dari satu aplikasi mobile.'
              : 'SIAPS mobile menyatukan absensi real-time, rekap kehadiran, izin, pengumuman, dan notifikasi sekolah dalam satu akses.',
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: _mutedInk,
                height: 1.5,
                fontWeight: FontWeight.w500,
                fontSize: layout.heroBodyFontSize,
              ),
        ),
      ],
    );
  }

  Widget _buildCompactHeroHeader(_LoginLayoutConfig layout) {
    final denseLandscape = layout.isLandscape && layout.isWide;
    final showBody =
        !denseLandscape && (layout.isWide || MediaQuery.sizeOf(context).height >= 780);

    return Container(
      padding: EdgeInsets.all(
        layout.ultraCompactHeight
            ? 12
            : (layout.isWide ? 16 : 14),
      ),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.72),
        borderRadius: BorderRadius.circular(26),
        border: Border.all(color: const Color(0xFFD8EAFB)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x141E88E5),
            blurRadius: 18,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(Icons.auto_awesome_rounded, size: 16, color: _deepSky),
              SizedBox(width: 8),
              Flexible(
                child: Text(
                  'Absensi, izin, dan monitoring siswa',
                  style: TextStyle(
                    color: _deepSky,
                    fontWeight: FontWeight.w800,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Masuk ke pusat aktivitas sekolah.',
            maxLines: denseLandscape ? 1 : 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: _ink,
                  fontWeight: FontWeight.w900,
                  letterSpacing: -0.5,
                  height: 1.1,
                ),
          ),
          if (showBody) ...[
            const SizedBox(height: 6),
            Text(
              'Akses absensi, izin, pengumuman, dan rekap sekolah dari satu aplikasi mobile.',
              maxLines: denseLandscape ? 2 : 3,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: _mutedInk,
                    height: 1.4,
                    fontWeight: FontWeight.w600,
                  ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildLoginCard(
    AuthProvider authProvider,
    _LoginLayoutConfig layout,
  ) {
    final isStudent = _selectedTab == 1;
    final compactCard = layout.ultraCompactHeight ||
        (!layout.isWide && (layout.showHero || layout.showCompactHero)) ||
        MediaQuery.sizeOf(context).width < 430;

    return Container(
      padding: EdgeInsets.all(layout.cardPadding),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius:
            BorderRadius.circular(layout.ultraCompactHeight ? 26 : 32),
        border: Border.all(color: Colors.white, width: 1.5),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1C0F2A43),
            blurRadius: 42,
            offset: Offset(0, 22),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isStudent ? 'Login Siswa' : 'Login Pegawai',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: _ink,
                            fontWeight: FontWeight.w900,
                            letterSpacing: -0.5,
                            fontSize: compactCard ? 20 : null,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      isStudent
                          ? (compactCard
                              ? 'Gunakan NIS dan tanggal lahir.'
                              : 'Gunakan NIS dan tanggal lahir sesuai data sekolah.')
                          : 'Gunakan User dan Password yang terdaftar di sekolah.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: _mutedInk,
                            height: 1.35,
                            fontWeight: FontWeight.w500,
                          ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 12),
              _buildModeIcon(layout),
            ],
          ),
          SizedBox(height: layout.fieldGap + 2),
          AnimatedSize(
            duration: const Duration(milliseconds: 320),
            curve: Curves.easeInOutCubic,
            alignment: Alignment.topCenter,
            child: ClipRect(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 280),
                switchInCurve: Curves.easeOutCubic,
                switchOutCurve: Curves.easeInOutCubic,
                transitionBuilder: (child, animation) {
                  final curved = CurvedAnimation(
                    parent: animation,
                    curve: Curves.easeInOutCubic,
                  );

                  return FadeTransition(
                    opacity: curved,
                    child: SizeTransition(
                      sizeFactor: curved,
                      axisAlignment: -1,
                      child: child,
                    ),
                  );
                },
                child: isStudent
                    ? _buildStudentLoginForm(authProvider, layout)
                    : _buildStaffLoginForm(authProvider, layout),
              ),
            ),
          ),
          SizedBox(height: layout.ultraCompactHeight ? 8 : 10),
          _buildModeSwitchCta(layout),
        ],
      ),
    );
  }

  Widget _buildModeIcon(_LoginLayoutConfig layout) {
    final icon = _selectedTab == 1
        ? Icons.school_rounded
        : Icons.admin_panel_settings_rounded;

    return Container(
      width: layout.ultraCompactHeight ? 44 : 54,
      height: layout.ultraCompactHeight ? 44 : 54,
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [_deepSky, _sky],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius:
            BorderRadius.circular(layout.ultraCompactHeight ? 16 : 22),
        boxShadow: const [
          BoxShadow(
            color: Color(0x401E88E5),
            blurRadius: 20,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: Icon(
        icon,
        color: Colors.white,
        size: layout.ultraCompactHeight ? 22 : 27,
      ),
    );
  }

  Widget _buildModeSwitchCta(_LoginLayoutConfig layout) {
    final isStudent = _selectedTab == 1;
    final compactSwitch = layout.ultraCompactHeight ||
        (!layout.isWide && (layout.showHero || layout.showCompactHero)) ||
        MediaQuery.sizeOf(context).width < 430;
    final preface = isStudent ? 'Akses pegawai?' : 'Akses siswa?';
    final actionLabel =
        isStudent ? 'Masuk sebagai Pegawai' : 'Masuk sebagai Siswa';
    final actionIcon =
        isStudent ? Icons.work_outline_rounded : Icons.school_outlined;
    final targetIndex = isStudent ? 0 : 1;

    return Center(
      child: Wrap(
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: compactSwitch ? 4 : 6,
        children: [
          Text(
            preface,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: _mutedInk,
                  fontWeight: FontWeight.w600,
                ),
          ),
          TextButton.icon(
            onPressed: () => _selectTab(targetIndex),
            style: TextButton.styleFrom(
              foregroundColor: _deepSky,
              padding: EdgeInsets.symmetric(
                horizontal: compactSwitch ? 0 : 2,
                vertical: 0,
              ),
              minimumSize: Size.zero,
              tapTargetSize: MaterialTapTargetSize.shrinkWrap,
              visualDensity: VisualDensity.compact,
            ),
            icon: Icon(actionIcon, size: 16),
            label: Text(
              actionLabel,
              style:
                  const TextStyle(fontWeight: FontWeight.w800, fontSize: 12.5),
            ),
          ),
        ],
      ),
    );
  }

  void _selectTab(int index) {
    if (_selectedTab == index) {
      return;
    }

    FocusScope.of(context).unfocus();
    setState(() {
      _selectedTab = index;
    });
  }

  Widget _buildStaffLoginForm(
    AuthProvider authProvider,
    _LoginLayoutConfig layout,
  ) {
    final useWideFields = layout.isWide && layout.ultraCompactHeight;

    return Form(
      key: _staffFormKey,
      child: Column(
        key: const ValueKey('staff-login-form'),
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (useWideFields)
            Row(
              children: [
                Expanded(
                  child: CustomTextField(
                    label: AppStrings.emailLabel,
                    hint: 'Masukan Email',
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    prefixIcon: const Icon(Icons.alternate_email_rounded),
                    validator: authProvider.validateEmail,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: CustomTextField(
                    label: AppStrings.passwordLabel,
                    hint: 'Masukkan password',
                    controller: _passwordController,
                    obscureText: true,
                    prefixIcon: const Icon(Icons.lock_rounded),
                    validator: authProvider.validatePassword,
                  ),
                ),
              ],
            )
          else ...[
            CustomTextField(
              label: AppStrings.emailLabel,
              hint: 'nama@email.sch.id',
              controller: _emailController,
              keyboardType: TextInputType.emailAddress,
              prefixIcon: const Icon(Icons.alternate_email_rounded),
              validator: authProvider.validateEmail,
            ),
            SizedBox(height: layout.fieldGap),
            CustomTextField(
              label: AppStrings.passwordLabel,
              hint: 'Masukkan password',
              controller: _passwordController,
              obscureText: true,
              prefixIcon: const Icon(Icons.lock_rounded),
              validator: authProvider.validatePassword,
            ),
          ],
          _buildFormSupportArea(
            layout,
            child: _buildStaffOptionsRow(layout),
          ),
          SizedBox(height: layout.fieldGap),
          _buildPrimaryAction(
            label: 'Masuk sebagai Pegawai',
            icon: Icons.login_rounded,
            isLoading: authProvider.isLoading,
            height: layout.loginButtonHeight,
            onPressed: () => _handleStaffLogin(authProvider),
          ),
        ],
      ),
    );
  }

  Widget _buildStudentLoginForm(
    AuthProvider authProvider,
    _LoginLayoutConfig layout,
  ) {
    final useWideFields = layout.isWide && layout.ultraCompactHeight;

    return Form(
      key: _studentFormKey,
      child: Column(
        key: const ValueKey('student-login-form'),
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (useWideFields)
            Row(
              children: [
                Expanded(
                  child: CustomTextField(
                    label: AppStrings.nisLabel,
                    hint: 'Contoh: Masukan NIS',
                    controller: _nisController,
                    keyboardType: TextInputType.number,
                    prefixIcon: const Icon(Icons.badge_rounded),
                    validator: authProvider.validateNIS,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: DatePickerTextField(
                    label: AppStrings.birthDateLabel,
                    hint: 'Pilih tanggal lahir',
                    controller: _birthDateController,
                    validator: authProvider.validateBirthDate,
                    initialDate: DateTime.now().subtract(
                      const Duration(days: 365 * 15),
                    ),
                    firstDate: DateTime(1980),
                    lastDate: DateTime.now(),
                  ),
                ),
              ],
            )
          else ...[
            CustomTextField(
              label: AppStrings.nisLabel,
              hint: 'Contoh: 23241001',
              controller: _nisController,
              keyboardType: TextInputType.number,
              prefixIcon: const Icon(Icons.badge_rounded),
              validator: authProvider.validateNIS,
            ),
            SizedBox(height: layout.fieldGap),
            DatePickerTextField(
              label: AppStrings.birthDateLabel,
              hint: 'Pilih tanggal lahir',
              controller: _birthDateController,
              validator: authProvider.validateBirthDate,
              initialDate: DateTime.now().subtract(
                const Duration(days: 365 * 15),
              ),
              firstDate: DateTime(1980),
              lastDate: DateTime.now(),
            ),
          ],
          _buildFormSupportArea(
            layout,
            child: layout.showStudentHint
                ? _buildStudentHint(layout)
                : const SizedBox.shrink(),
          ),
          SizedBox(height: layout.fieldGap),
          _buildPrimaryAction(
            label: 'Masuk sebagai Siswa',
            icon: Icons.arrow_forward_rounded,
            isLoading: authProvider.isLoading,
            height: layout.loginButtonHeight,
            onPressed: () => _handleStudentLogin(authProvider),
          ),
        ],
      ),
    );
  }

  Widget _buildStaffOptionsRow(_LoginLayoutConfig layout) {
    final rememberControl = InkWell(
      borderRadius: BorderRadius.circular(16),
      onTap: () {
        setState(() {
          _rememberMe = !_rememberMe;
        });
      },
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            SizedBox(
              width: 24,
              height: 24,
              child: Checkbox(
                value: _rememberMe,
                onChanged: (value) {
                  setState(() {
                    _rememberMe = value ?? false;
                  });
                },
                materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(7),
                ),
              ),
            ),
            const SizedBox(width: 10),
            const Flexible(
              child: Text(
                AppStrings.rememberMe,
                style: TextStyle(
                  color: _mutedInk,
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    );

    final helpButton = TextButton(
      onPressed: _handleForgotPassword,
      style: TextButton.styleFrom(
        foregroundColor: _deepSky,
        padding: const EdgeInsets.symmetric(horizontal: 0, vertical: 4),
        tapTargetSize: MaterialTapTargetSize.shrinkWrap,
      ),
      child: const Text(
        'Butuh bantuan?',
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
      ),
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        return Row(
          crossAxisAlignment: CrossAxisAlignment.center,
          children: [
            Expanded(child: rememberControl),
            const SizedBox(width: 8),
            helpButton,
          ],
        );
      },
    );
  }

  Widget _buildFormSupportArea(
    _LoginLayoutConfig layout, {
    required Widget child,
  }) {
    final topGap = layout.ultraCompactHeight ? 6.0 : 8.0;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(height: topGap),
        SizedBox(
          height: layout.formSupportHeight,
          child: Align(
            alignment: Alignment.topLeft,
            child: child,
          ),
        ),
      ],
    );
  }

  Widget _buildStudentHint(_LoginLayoutConfig layout) {
    return Container(
      padding: EdgeInsets.all(layout.ultraCompactHeight ? 10 : 12),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF9F6),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: const Color(0xFFCDEFE6)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.tips_and_updates_rounded, size: 18, color: _mint),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              'Tanggal lahir dipakai sebagai verifikasi siswa. Pilih sesuai data sekolah.',
              style: TextStyle(
                color: _mutedInk,
                height: 1.4,
                fontSize: layout.ultraCompactHeight ? 11.5 : 12,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPrimaryAction({
    required String label,
    required IconData icon,
    required bool isLoading,
    required double height,
    required VoidCallback onPressed,
  }) {
    return SizedBox(
      height: height,
      child: ElevatedButton(
        onPressed: isLoading ? null : onPressed,
        style: ElevatedButton.styleFrom(
          elevation: 0,
          backgroundColor: _ink,
          foregroundColor: Colors.white,
          disabledBackgroundColor: _ink.withValues(alpha: 0.55),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(22),
          ),
        ),
        child: isLoading
            ? const SizedBox(
                width: 22,
                height: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.4,
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                ),
              )
            : FittedBox(
                fit: BoxFit.scaleDown,
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(icon, size: 20),
                    const SizedBox(width: 10),
                    Text(
                      label,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
      ),
    );
  }

  Widget _buildFeatureStrip() {
    return const Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        _FeaturePill(
          icon: Icons.location_on_rounded,
          label: 'GPS attendance',
        ),
        _FeaturePill(
          icon: Icons.fact_check_rounded,
          label: 'Rekap real-time',
        ),
        _FeaturePill(
          icon: Icons.notifications_active_rounded,
          label: 'Notifikasi sekolah',
        ),
      ],
    );
  }

  Widget _buildFooter() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Text(
          'Login Anda terekam untuk keamanan sistem.',
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: _mutedInk,
                height: 1.45,
                fontWeight: FontWeight.w500,
              ),
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

  void _showErrorMessage(String message) {
    if (mounted) {
      NotificationPopup.showError(
        context,
        title: 'Login Gagal',
        message: message,
      );
    }
  }

  Future<void> _handleStaffLogin(AuthProvider authProvider) async {
    if (!_staffFormKey.currentState!.validate()) return;

    authProvider.clearError();

    final success = await authProvider.loginStaff(
      email: _emailController.text.trim(),
      password: _passwordController.text,
    );

    if (success) {
      _showSuccessMessage(AppStrings.loginSuccess);
    }
  }

  Future<void> _handleStudentLogin(AuthProvider authProvider) async {
    if (!_studentFormKey.currentState!.validate()) return;

    authProvider.clearError();

    final success = await authProvider.loginStudent(
      nis: _nisController.text.trim(),
      tanggalLahir: _birthDateController.text,
    );

    if (success) {
      _showSuccessMessage(AppStrings.loginSuccess);
    }
  }

  void _handleForgotPassword() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(28)),
        title: const Text('Bantuan Login'),
        content: const Text(
          'Silakan hubungi administrator untuk reset password atau Reset Melalui Website.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Mengerti'),
          ),
        ],
      ),
    );
  }

  void _showSuccessMessage(String message) {
    if (mounted) {
      NotificationPopup.showSuccess(
        context,
        title: 'Berhasil!',
        message: message,
      );
    }
  }
}

class _LoginLayoutConfig {
  const _LoginLayoutConfig({
    required this.isWide,
    required this.isLandscape,
    required this.keyboardVisible,
    required this.compactHeight,
    required this.ultraCompactHeight,
    required this.showHero,
    required this.showCompactHero,
    required this.showFeatures,
    required this.showFooter,
    required this.showStudentHint,
    required this.showVersion,
    required this.showSecureBadge,
    required this.maxContentWidth,
    required this.horizontalPadding,
    required this.topPadding,
    required this.bottomPadding,
    required this.sectionGap,
    required this.fieldGap,
    required this.cardPadding,
    required this.logoSize,
    required this.heroTitleSize,
    required this.heroBodyFontSize,
    required this.loginButtonHeight,
    required this.formSupportHeight,
  });

  final bool isWide;
  final bool isLandscape;
  final bool keyboardVisible;
  final bool compactHeight;
  final bool ultraCompactHeight;
  final bool showHero;
  final bool showCompactHero;
  final bool showFeatures;
  final bool showFooter;
  final bool showStudentHint;
  final bool showVersion;
  final bool showSecureBadge;
  final double maxContentWidth;
  final double horizontalPadding;
  final double topPadding;
  final double bottomPadding;
  final double sectionGap;
  final double fieldGap;
  final double cardPadding;
  final double logoSize;
  final double heroTitleSize;
  final double heroBodyFontSize;
  final double loginButtonHeight;
  final double formSupportHeight;

  static _LoginLayoutConfig from(
    BuildContext context,
    BoxConstraints constraints,
  ) {
    final size = constraints.biggest;
    final viewInsets = MediaQuery.viewInsetsOf(context);
    final keyboardVisible = viewInsets.bottom > 0;
    final isLandscape = size.width > size.height;
    final isWide = size.width >= 760;
    final compactHeight = size.height < 760;
    final ultraCompactHeight =
        size.height < 640 || (isLandscape && size.height < 460);
    final showHero = !keyboardVisible &&
        ((isWide && !isLandscape && size.height >= 620) ||
            (isWide && isLandscape && size.height >= 500));
    final showCompactHero = !keyboardVisible &&
        !showHero &&
        (((!isWide && !isLandscape) && size.height >= 700) ||
            (isWide && isLandscape && size.height >= 360));
    final hasPhoneIntro = !isWide && (showHero || showCompactHero);
    final showStudentHint = !keyboardVisible &&
        !ultraCompactHeight &&
        (isWide ? size.height >= 700 : size.height >= 820);
    final showFooter = !keyboardVisible &&
        !isLandscape &&
        (isWide ? size.height >= 860 : size.height >= 780);

    return _LoginLayoutConfig(
      isWide: isWide,
      isLandscape: isLandscape,
      keyboardVisible: keyboardVisible,
      compactHeight: compactHeight,
      ultraCompactHeight: ultraCompactHeight,
      showHero: showHero,
      showCompactHero: showCompactHero,
      showFeatures: !keyboardVisible && isWide && !isLandscape && size.height >= 780,
      showFooter: showFooter,
      showStudentHint: showStudentHint,
      showVersion: !keyboardVisible && size.height >= 560 && size.width >= 360,
      showSecureBadge:
          !keyboardVisible && size.height >= 720 && size.width >= 390,
      maxContentWidth: isWide
          ? 1080
          : size.width >= 700
              ? 560
              : double.infinity,
      horizontalPadding: ultraCompactHeight || size.width < 360 ? 14 : 20,
      topPadding: keyboardVisible
          ? 12
          : (ultraCompactHeight
              ? 8
              : (hasPhoneIntro ? 14 : (compactHeight ? 16 : 18))),
      bottomPadding: keyboardVisible
          ? 12
          : (ultraCompactHeight
              ? 8
              : (hasPhoneIntro
                  ? (showFooter ? 12 : 16)
                  : (compactHeight ? 16 : 22))),
      sectionGap: keyboardVisible
          ? 10
          : (ultraCompactHeight || size.width < 360
              ? 10
              : (hasPhoneIntro ? 14 : 20)),
      fieldGap: ultraCompactHeight
          ? 6
          : (hasPhoneIntro ? 10 : (size.width < 360 ? 10 : 16)),
      cardPadding: keyboardVisible || ultraCompactHeight || size.width < 360
          ? (ultraCompactHeight ? 8 : 14)
          : (hasPhoneIntro ? 14 : 18),
      logoSize: keyboardVisible
          ? 38
          : (ultraCompactHeight
              ? 40
              : (size.width < 360 ? 42 : (compactHeight ? 46 : 52))),
      heroTitleSize: isWide
          ? 48
          : ultraCompactHeight
              ? 28
              : compactHeight
                  ? 32
                  : 38,
      heroBodyFontSize: ultraCompactHeight ? 13 : 14.5,
      loginButtonHeight: ultraCompactHeight
          ? 42
          : (hasPhoneIntro ? 52 : (size.width < 360 ? 50 : 56)),
      formSupportHeight: showStudentHint
          ? (ultraCompactHeight ? 62 : 64)
          : (ultraCompactHeight ? 30 : 36),
    );
  }
}

class _LoginBackdrop extends StatelessWidget {
  const _LoginBackdrop({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    final size = MediaQuery.sizeOf(context);
    final keyboardVisible = MediaQuery.viewInsetsOf(context).bottom > 0;
    final showTicket =
        !keyboardVisible && size.width >= 390 && size.height >= 760;

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
            child: _AtmosphereOrb(
              size: 240,
              color: Color(0x5564B5F6),
            ),
          ),
          const Positioned(
            top: 220,
            left: -120,
            child: _AtmosphereOrb(
              size: 260,
              color: Color(0x4421C7A8),
            ),
          ),
          if (showTicket)
            Positioned(
              top: 138,
              right: 10,
              child: Transform.rotate(
                angle: -0.18,
                child: const _AttendanceTicket(),
              ),
            ),
          child,
        ],
      ),
    );
  }
}

class _AtmosphereOrb extends StatelessWidget {
  const _AtmosphereOrb({required this.size, required this.color});

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

class _AttendanceTicket extends StatelessWidget {
  const _AttendanceTicket();

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
            Icon(Icons.fingerprint_rounded, color: _LoginScreenState._deepSky),
            SizedBox(width: 8),
            Text(
              'Check-in',
              style: TextStyle(
                color: _LoginScreenState._ink,
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

class _FeaturePill extends StatelessWidget {
  const _FeaturePill({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.82),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: _LoginScreenState._deepSky),
          const SizedBox(width: 7),
          Text(
            label,
            style: const TextStyle(
              color: _LoginScreenState._ink,
              fontWeight: FontWeight.w800,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }
}
