import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../config/app_config.dart';
import '../config/app_theme.dart';
import '../widgets/classic_backdrop.dart';
import '../widgets/dashboard_action_button.dart';
import 'about_screen.dart';
import 'precheck_screen.dart';

class StudentDashboardScreen extends StatelessWidget {
  const StudentDashboardScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: ClassicBackdrop(
        child: SafeArea(
          child: LayoutBuilder(
            builder: (context, constraints) {
              final layout = _DashboardLayout.from(constraints);
              final availableHeight = math.max(
                0.0,
                constraints.maxHeight - layout.verticalPadding * 2,
              );

              return Center(
                child: ConstrainedBox(
                  constraints: BoxConstraints(maxWidth: layout.maxWidth),
                  child: Padding(
                    padding: EdgeInsets.symmetric(
                      horizontal: layout.horizontalPadding,
                      vertical: layout.verticalPadding,
                    ),
                    child: SizedBox(
                      height: availableHeight,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          _HeaderSection(layout: layout),
                          Expanded(
                            child: Align(
                              alignment: Alignment.center,
                              child: _MenuSection(
                                layout: layout,
                                onStart: () => _openPrecheck(context),
                                onAbout: () => _openAbout(context),
                                onExit: () => _confirmExit(context),
                              ),
                            ),
                          ),
                          const _FooterSection(),
                        ],
                      ),
                    ),
                  ),
                ),
              );
            },
          ),
        ),
      ),
    );
  }

  void _openPrecheck(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (_) => const PrecheckScreen()));
  }

  void _openAbout(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (_) => const AboutScreen()));
  }

  Future<void> _confirmExit(BuildContext context) async {
    final exit = await showDialog<bool>(
      context: context,
      builder: (context) {
        return AlertDialog(
          title: const Text('Keluar dari aplikasi?'),
          content: const Text('Pastikan tidak sedang berada dalam sesi ujian.'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Keluar'),
            ),
          ],
        );
      },
    );

    if (exit == true) {
      SystemNavigator.pop();
    }
  }
}

class _HeaderSection extends StatelessWidget {
  const _HeaderSection({required this.layout});

  final _DashboardLayout layout;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        SbtLogoMark(size: layout.logoSize),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                AppConfig.appName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: AppColors.ink,
                  fontSize: layout.compact ? 15 : 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                AppConfig.schoolName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.muted,
                  fontSize: layout.compact ? 12 : 13,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
        if (layout.showSecureBadge) ...[
          const SizedBox(width: 12),
          const SecureBadge(label: 'Secure'),
        ],
      ],
    );
  }
}

class _MenuSection extends StatelessWidget {
  const _MenuSection({
    required this.layout,
    required this.onStart,
    required this.onAbout,
    required this.onExit,
  });

  final _DashboardLayout layout;
  final VoidCallback onStart;
  final VoidCallback onAbout;
  final VoidCallback onExit;

  @override
  Widget build(BuildContext context) {
    final menuPanel = _MenuPanel(
      layout: layout,
      onStart: onStart,
      onAbout: onAbout,
      onExit: onExit,
    );

    return ConstrainedBox(
      constraints: BoxConstraints(maxWidth: layout.menuWidth),
      child: layout.useSplitBody
          ? Row(
              crossAxisAlignment: CrossAxisAlignment.center,
              children: [
                Expanded(child: _ExamIntro(layout: layout)),
                SizedBox(width: layout.sectionGap),
                Expanded(child: menuPanel),
              ],
            )
          : Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _ExamIntro(layout: layout),
                SizedBox(height: layout.menuGap),
                menuPanel,
              ],
            ),
    );
  }
}

class _MenuPanel extends StatelessWidget {
  const _MenuPanel({
    required this.layout,
    required this.onStart,
    required this.onAbout,
    required this.onExit,
  });

  final _DashboardLayout layout;
  final VoidCallback onStart;
  final VoidCallback onAbout;
  final VoidCallback onExit;

  @override
  Widget build(BuildContext context) {
    return ClassicPanel(
      padding: EdgeInsets.all(layout.panelPadding),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          DashboardActionButton(
            icon: Icons.play_circle_fill_rounded,
            title: 'Mulai Ujian',
            description: 'Cek perangkat lalu buka CBT siswa.',
            isPrimary: true,
            height: layout.buttonHeight,
            showDescription: layout.showButtonDescriptions,
            onPressed: onStart,
          ),
          SizedBox(height: layout.buttonGap),
          DashboardActionButton(
            icon: Icons.info_rounded,
            title: 'Tentang',
            description: 'Informasi aplikasi dan aturan singkat.',
            height: layout.buttonHeight,
            showDescription: layout.showButtonDescriptions,
            onPressed: onAbout,
          ),
          SizedBox(height: layout.buttonGap),
          DashboardActionButton(
            icon: Icons.logout_rounded,
            title: 'Keluar',
            description: 'Tutup aplikasi SBT SMANIS.',
            height: layout.buttonHeight,
            showDescription: layout.showButtonDescriptions,
            onPressed: onExit,
          ),
          if (layout.showMenuNote) ...[
            SizedBox(height: layout.menuGap),
            Text(
              'Gunakan tombol mulai saat ujian dibuka pengawas.',
              textAlign: TextAlign.center,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.muted,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _ExamIntro extends StatelessWidget {
  const _ExamIntro({required this.layout});

  final _DashboardLayout layout;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.symmetric(horizontal: layout.introInset),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          ClassicPill(
            icon: Icons.phone_android_rounded,
            label: AppConfig.appLongName,
            color: AppColors.primary,
          ),
          SizedBox(height: layout.badgeGap),
          Wrap(
            spacing: 8,
            runSpacing: layout.badgeGap,
            children: const [
              ClassicPill(
                icon: Icons.auto_awesome_rounded,
                label: 'Browser tes siswa',
              ),
              ClassicPill(
                icon: Icons.shield_rounded,
                label: 'Mode fokus',
                color: AppColors.accent,
              ),
            ],
          ),
          SizedBox(height: layout.titleGap),
          Text(
            'Masuk ke ruang ujian.',
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
              color: AppColors.ink,
              fontSize: layout.introTitleSize,
              fontWeight: FontWeight.w900,
            ),
          ),
          if (layout.showIntroBody) ...[
            const SizedBox(height: 6),
            Text(
              'Siapkan perangkat, buka CBT, dan ikuti arahan pengawas.',
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                color: AppColors.muted,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _FooterSection extends StatelessWidget {
  const _FooterSection();

  @override
  Widget build(BuildContext context) {
    return const ClassicWatermark();
  }
}

class _DashboardLayout {
  const _DashboardLayout({
    required this.compact,
    required this.dense,
    required this.useSplitBody,
    required this.showSecureBadge,
    required this.showIntroBody,
    required this.showMenuNote,
    required this.showButtonDescriptions,
    required this.maxWidth,
    required this.menuWidth,
    required this.horizontalPadding,
    required this.verticalPadding,
    required this.panelPadding,
    required this.buttonHeight,
    required this.buttonGap,
    required this.menuGap,
    required this.logoSize,
    required this.introInset,
    required this.introTitleSize,
    required this.sectionGap,
    required this.badgeGap,
    required this.titleGap,
  });

  final bool compact;
  final bool dense;
  final bool useSplitBody;
  final bool showSecureBadge;
  final bool showIntroBody;
  final bool showMenuNote;
  final bool showButtonDescriptions;
  final double maxWidth;
  final double menuWidth;
  final double horizontalPadding;
  final double verticalPadding;
  final double panelPadding;
  final double buttonHeight;
  final double buttonGap;
  final double menuGap;
  final double logoSize;
  final double introInset;
  final double introTitleSize;
  final double sectionGap;
  final double badgeGap;
  final double titleGap;

  static _DashboardLayout from(BoxConstraints constraints) {
    final size = constraints.biggest;
    final isLandscape = size.width > size.height;
    final isWide = size.width >= 760;
    final compact =
        size.height < 760 ||
        size.width < 390 ||
        (isLandscape && size.height < 520);
    final dense = size.height < 600 || (isLandscape && size.height < 440);

    return _DashboardLayout(
      compact: compact,
      dense: dense,
      useSplitBody: isLandscape,
      showSecureBadge: size.width >= 390,
      showIntroBody: !dense && (!isLandscape || size.height >= 700),
      showMenuNote: size.height >= 820 && !isLandscape,
      showButtonDescriptions:
          size.height >= 780 && size.width >= 360 && !isLandscape,
      maxWidth: isWide ? 920 : (size.width >= 700 ? 620 : double.infinity),
      menuWidth: isLandscape
          ? (isWide ? 800 : double.infinity)
          : (isWide ? 560 : double.infinity),
      horizontalPadding: size.width < 360 ? 12 : (isWide ? 28 : 16),
      verticalPadding: dense ? 8 : 16,
      panelPadding: dense ? 8 : (compact ? 14 : 18),
      buttonHeight: dense ? 48 : (compact ? 58 : 70),
      buttonGap: dense ? 5 : 10,
      menuGap: dense ? 6 : (compact ? 12 : 16),
      logoSize: dense ? 38 : (compact ? 48 : 56),
      introInset: dense ? 0 : 2,
      introTitleSize: dense ? 22 : (compact ? 27 : 30),
      sectionGap: dense ? 12 : 20,
      badgeGap: dense ? 5 : 8,
      titleGap: dense ? 6 : 10,
    );
  }
}
