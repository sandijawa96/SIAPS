import 'package:flutter/material.dart';

import '../config/app_theme.dart';
import '../models/precheck_item.dart';
import '../services/exam_guard_service.dart';
import '../services/precheck_service.dart';
import '../services/sbt_api_service.dart';
import '../widgets/classic_backdrop.dart';
import '../widgets/precheck_tile.dart';
import '../widgets/responsive_shell.dart';
import 'exam_webview_screen.dart';

class PrecheckScreen extends StatefulWidget {
  const PrecheckScreen({super.key});

  @override
  State<PrecheckScreen> createState() => _PrecheckScreenState();
}

class _PrecheckScreenState extends State<PrecheckScreen> {
  final _service = PrecheckService();
  var _loading = true;
  var _items = <PrecheckItem>[];

  bool get _canStartExam =>
      _items.isNotEmpty && !_items.any((item) => item.blocksExam);

  @override
  void initState() {
    super.initState();
    _runChecks();
  }

  Future<void> _runChecks() async {
    setState(() => _loading = true);

    final items = await _service.runAll();

    if (!mounted) return;
    setState(() {
      _items = items;
      _loading = false;
    });
  }

  Future<void> _openDndSettings() async {
    await ExamGuardService.instance.openDoNotDisturbSettings();
  }

  Future<void> _openUpdateDownload() async {
    final url = SbtApiService.instance.lastVersionCheck?.downloadUrl;
    if (url == null || url.trim().isEmpty) return;

    await ExamGuardService.instance.openExternalUrl(url);
  }

  Future<void> _requestScreenPinning() async {
    await ExamGuardService.instance.requestScreenPinning();
    if (!mounted) return;
    await _runChecks();
  }

  VoidCallback? _actionFor(PrecheckItem item) {
    if (item.id == 'dnd') {
      return _openDndSettings;
    }

    if (item.id == 'app-update') {
      final url = SbtApiService.instance.lastVersionCheck?.downloadUrl;
      return url == null || url.trim().isEmpty ? null : _openUpdateDownload;
    }

    if (item.id == 'screen-pinning') {
      return _requestScreenPinning;
    }

    return null;
  }

  void _startExam() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute<void>(builder: (_) => const ExamWebViewScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final failedCount = _items.where((item) => item.blocksExam).length;

    return Scaffold(
      appBar: AppBar(title: const Text('Precheck Ujian')),
      body: ClassicBackdrop(
        child: SafeArea(
          child: ResponsiveShell(
            maxWidth: 860,
            child: Column(
              children: [
                Expanded(
                  child: ListView(
                    children: [
                      const ClassicPill(
                        icon: Icons.fact_check_rounded,
                        label: 'Precheck perangkat',
                      ),
                      const SizedBox(height: 14),
                      Text(
                        'Kesiapan Perangkat',
                        style: Theme.of(context).textTheme.headlineMedium,
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Periksa kondisi perangkat sebelum membuka CBT.',
                        style: Theme.of(
                          context,
                        ).textTheme.bodyLarge?.copyWith(color: AppColors.muted),
                      ),
                      const SizedBox(height: 18),
                      if (_loading)
                        const _CheckingPlaceholder()
                      else ...[
                        for (final item in _items) ...[
                          PrecheckTile(item: item, onAction: _actionFor(item)),
                          const SizedBox(height: 12),
                        ],
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: _loading ? null : _runChecks,
                        icon: const Icon(Icons.refresh_rounded),
                        label: const Text('Cek Ulang'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: !_loading && _canStartExam
                            ? _startExam
                            : null,
                        icon: const Icon(Icons.play_arrow_rounded),
                        label: Text(
                          failedCount == 0 ? 'Mulai Ujian' : 'Belum Siap',
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _CheckingPlaceholder extends StatelessWidget {
  const _CheckingPlaceholder();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
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
      child: Row(
        children: [
          const SizedBox(
            width: 26,
            height: 26,
            child: CircularProgressIndicator(strokeWidth: 3),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Text(
              'Memeriksa server CBT, baterai, dan mode tampilan perangkat.',
              style: Theme.of(context).textTheme.bodyLarge,
            ),
          ),
        ],
      ),
    );
  }
}
