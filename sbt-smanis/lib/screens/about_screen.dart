import 'package:flutter/material.dart';

import '../config/app_config.dart';
import '../widgets/classic_backdrop.dart';
import '../widgets/responsive_shell.dart';

class AboutScreen extends StatelessWidget {
  const AboutScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Tentang')),
      body: ClassicBackdrop(
        child: SafeArea(
          child: ResponsiveShell(
            maxWidth: 760,
            child: ListView(
              children: [
                Row(
                  children: [
                    const SbtLogoMark(size: 58),
                    const SizedBox(width: 14),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            AppConfig.appName,
                            style: Theme.of(context).textTheme.headlineMedium,
                          ),
                          const SizedBox(height: 4),
                          Text(
                            AppConfig.schoolName,
                            style: Theme.of(context).textTheme.bodyMedium,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 22),
                _InfoBlock(title: 'Alamat Ujian', content: AppConfig.examUrl),
                const SizedBox(height: 14),
                const _InfoBlock(
                  title: 'Penggunaan',
                  content:
                      'Gunakan aplikasi ini hanya saat ujian. Login dan pengerjaan soal tetap dilakukan pada halaman CBT sekolah.',
                ),
                const SizedBox(height: 14),
                const _InfoBlock(
                  title: 'Aturan Singkat',
                  content:
                      'Tutup aplikasi mengambang, aktifkan mode Jangan Ganggu, dan jangan keluar dari aplikasi selama ujian berlangsung.',
                ),
                const SizedBox(height: 14),
                const _InfoBlock(title: 'Versi', content: '1.0.0'),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _InfoBlock extends StatelessWidget {
  const _InfoBlock({required this.title, required this.content});

  final String title;
  final String content;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.9),
        border: Border.all(color: Colors.white, width: 1.4),
        borderRadius: BorderRadius.circular(8),
        boxShadow: const [
          BoxShadow(
            color: Color(0x120F2A43),
            blurRadius: 20,
            offset: Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: 6),
          Text(content, style: Theme.of(context).textTheme.bodyMedium),
        ],
      ),
    );
  }
}
