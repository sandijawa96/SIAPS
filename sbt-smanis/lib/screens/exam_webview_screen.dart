import 'dart:async';

import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../config/app_theme.dart';
import '../models/guard_event.dart';
import '../services/exam_guard_service.dart';
import '../services/sbt_api_service.dart';

class ExamWebViewScreen extends StatefulWidget {
  const ExamWebViewScreen({super.key});

  @override
  State<ExamWebViewScreen> createState() => _ExamWebViewScreenState();
}

class _ExamWebViewScreenState extends State<ExamWebViewScreen> {
  final _sbt = SbtApiService.instance;
  late final WebViewController _controller;
  StreamSubscription<GuardEvent>? _guardSubscription;
  Timer? _heartbeatTimer;
  var _progress = 0;
  var _pageLoading = true;
  String? _blockedNavigation;
  GuardEvent? _lastGuardEvent;
  GuardEvent? _guardLockEvent;
  DateTime? _lastScreenPinningWarningAt;

  @override
  void initState() {
    super.initState();
    _controller = _buildController();
    _guardSubscription = ExamGuardService.instance.events.listen(_onGuardEvent);
    unawaited(_prepareExam());
  }

  Future<void> _prepareExam() async {
    await _sbt.loadConfig();
    if (!mounted) return;

    await ExamGuardService.instance.enableExamGuard(
      requireScreenPinning: _sbt.config.requireScreenPinning,
    );
    await _sbt.startSession();
    _startHeartbeat();
    await _controller.loadRequest(Uri.parse(_sbt.config.examUrl));
  }

  WebViewController _buildController() {
    return WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.white)
      ..setUserAgent('SBT-SMANIS/1.0 Android WebView')
      ..setNavigationDelegate(
        NavigationDelegate(
          onProgress: (progress) {
            if (mounted) setState(() => _progress = progress);
          },
          onPageStarted: (_) {
            if (mounted) {
              setState(() {
                _pageLoading = true;
                _blockedNavigation = null;
              });
            }
          },
          onPageFinished: (_) {
            if (mounted) setState(() => _pageLoading = false);
          },
          onWebResourceError: (error) {
            if (!mounted || error.isForMainFrame != true) return;
            unawaited(
              _sbt.reportEvent(
                eventType: 'WEBVIEW_ERROR',
                severity: 'medium',
                message: error.description,
                metadata: {
                  'error_code': error.errorCode,
                  'error_type': error.errorType.toString(),
                },
              ),
            );
            setState(() {
              _pageLoading = false;
              _blockedNavigation =
                  'Gagal memuat halaman ujian. ${error.description}';
            });
          },
          onNavigationRequest: _handleNavigation,
        ),
      );
  }

  NavigationDecision _handleNavigation(NavigationRequest request) {
    final uri = Uri.tryParse(request.url);
    if (uri == null) {
      _showBlockedNavigation('Alamat tidak valid diblokir.');
      return NavigationDecision.prevent;
    }

    if (uri.scheme == 'about' || uri.scheme == 'data' || uri.scheme == 'blob') {
      return NavigationDecision.navigate;
    }

    final host = uri.host.toLowerCase();
    final allowed = host == _sbt.config.examHost.toLowerCase();

    if (allowed) {
      final firstSegment = uri.pathSegments.isEmpty
          ? ''
          : uri.pathSegments.first.toLowerCase();
      if (firstSegment == 'adm') {
        _showBlockedNavigation('Halaman admin tidak dibuka di aplikasi siswa.');
        unawaited(
          _sbt.reportEvent(
            eventType: 'NAVIGATION_BLOCKED',
            severity: 'medium',
            message: 'Halaman admin CBT diblokir.',
            metadata: {'url': request.url},
          ),
        );
        return NavigationDecision.prevent;
      }

      return NavigationDecision.navigate;
    }

    _showBlockedNavigation('Navigasi luar CBT diblokir: ${uri.host}');
    unawaited(
      _sbt.reportEvent(
        eventType: 'NAVIGATION_BLOCKED',
        severity: 'medium',
        message: 'Navigasi luar CBT diblokir.',
        metadata: {'url': request.url, 'host': uri.host},
      ),
    );
    return NavigationDecision.prevent;
  }

  void _showBlockedNavigation(String message) {
    if (!mounted) return;
    setState(() => _blockedNavigation = message);
  }

  void _onGuardEvent(GuardEvent event) {
    if (!mounted) return;
    unawaited(_sbt.reportGuardEvent(event));
    setState(() {
      _lastGuardEvent = event;
      if (_shouldLockExam(event.type) && _sbt.config.requiresSupervisorCode) {
        _guardLockEvent = event;
      }
    });
  }

  bool _shouldLockExam(String type) {
    return type == 'APP_PAUSED' ||
        type == 'APP_STOPPED' ||
        type == 'MULTI_WINDOW' ||
        type == 'PIP_MODE' ||
        type == 'LOCK_TASK_NOT_ACTIVE' ||
        type == 'LOCK_TASK_UNAVAILABLE';
  }

  void _continueAfterGuardLock() {
    setState(() => _guardLockEvent = null);
    unawaited(
      ExamGuardService.instance.enableExamGuard(
        requireScreenPinning: _sbt.config.requireScreenPinning,
      ),
    );
  }

  Future<SbtUnlockResult> _validateSupervisorCode(String code) {
    return _sbt.validateSupervisorCode(code, _guardLockEvent);
  }

  void _startHeartbeat() {
    _heartbeatTimer?.cancel();
    final seconds = _sbt.config.heartbeatIntervalSeconds < 10
        ? 10
        : _sbt.config.heartbeatIntervalSeconds;

    _heartbeatTimer = Timer.periodic(
      Duration(seconds: seconds),
      (_) => unawaited(_sendHeartbeat()),
    );
    unawaited(_sendHeartbeat());
  }

  Future<void> _sendHeartbeat() async {
    String? currentUrl;

    try {
      currentUrl = await _controller.currentUrl();
    } on Object {
      currentUrl = null;
    }

    await _verifyScreenPinningDuringExam();
    await _sbt.sendHeartbeat(currentUrl: currentUrl);
  }

  Future<void> _verifyScreenPinningDuringExam() async {
    if (!_sbt.config.requireScreenPinning) return;

    final status = await ExamGuardService.instance.getScreenPinningStatus();
    if (status.active || !mounted) return;

    final now = DateTime.now();
    final lastWarning = _lastScreenPinningWarningAt;
    if (lastWarning != null && now.difference(lastWarning).inSeconds < 20) {
      return;
    }
    _lastScreenPinningWarningAt = now;

    final event = GuardEvent(
      type: status.supported ? 'LOCK_TASK_NOT_ACTIVE' : 'LOCK_TASK_UNAVAILABLE',
      message: status.supported
          ? 'Screen pinning tidak aktif saat ujian berlangsung.'
          : 'Perangkat belum mendukung screen pinning.',
      occurredAt: now,
    );
    _onGuardEvent(event);
  }

  Future<void> _confirmExitExam() async {
    final exit = await showDialog<bool>(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return AlertDialog(
          title: const Text('Keluar dari ujian?'),
          content: const Text(
            'Keluar dari mode ujian dapat mengganggu pengerjaan. Lanjutkan hanya jika diarahkan pengawas.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Tetap Ujian'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('Keluar'),
            ),
          ],
        );
      },
    );

    if (exit != true || !mounted) return;
    await ExamGuardService.instance.disableExamGuard();
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _reload() async {
    setState(() => _blockedNavigation = null);
    await _controller.reload();
  }

  @override
  void dispose() {
    _heartbeatTimer?.cancel();
    _guardSubscription?.cancel();
    unawaited(_sbt.finishSession(reason: 'dispose'));
    unawaited(ExamGuardService.instance.disableExamGuard());
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) unawaited(_confirmExitExam());
      },
      child: Scaffold(
        body: SafeArea(
          child: Column(
            children: [
              _ExamToolbar(
                progress: _progress,
                pageLoading: _pageLoading,
                onExit: _confirmExitExam,
                onReload: _reload,
              ),
              Expanded(
                child: Stack(
                  children: [
                    WebViewWidget(controller: _controller),
                    if (_blockedNavigation != null)
                      _MessageBanner(
                        message: _blockedNavigation!,
                        color: AppColors.warning,
                        icon: Icons.block_rounded,
                        onDismissed: () =>
                            setState(() => _blockedNavigation = null),
                      ),
                    if (_lastGuardEvent != null)
                      Positioned(
                        left: 12,
                        right: 12,
                        bottom: 12,
                        child: _GuardWarning(
                          event: _lastGuardEvent!,
                          onDismissed: () =>
                              setState(() => _lastGuardEvent = null),
                        ),
                      ),
                    if (_guardLockEvent != null)
                      _GuardLockOverlay(
                        event: _guardLockEvent!,
                        requiresSupervisorCode:
                            _sbt.config.requiresSupervisorCode,
                        onValidateSupervisorCode: _validateSupervisorCode,
                        onContinue: _continueAfterGuardLock,
                        onExit: _confirmExitExam,
                      ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _GuardLockOverlay extends StatefulWidget {
  const _GuardLockOverlay({
    required this.event,
    required this.requiresSupervisorCode,
    required this.onValidateSupervisorCode,
    required this.onContinue,
    required this.onExit,
  });

  final GuardEvent event;
  final bool requiresSupervisorCode;
  final Future<SbtUnlockResult> Function(String code) onValidateSupervisorCode;
  final VoidCallback onContinue;
  final VoidCallback onExit;

  @override
  State<_GuardLockOverlay> createState() => _GuardLockOverlayState();
}

class _GuardLockOverlayState extends State<_GuardLockOverlay> {
  final _codeController = TextEditingController();
  var _checking = false;
  String? _error;

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _submitSupervisorCode() async {
    final code = _codeController.text.trim();
    if (code.isEmpty || _checking) return;

    setState(() {
      _checking = true;
      _error = null;
    });

    final result = await widget.onValidateSupervisorCode(code);

    if (!mounted) return;
    setState(() => _checking = false);

    if (result.allowed) {
      widget.onContinue();
      return;
    }

    setState(() => _error = result.message);
  }

  @override
  Widget build(BuildContext context) {
    return Positioned.fill(
      child: ColoredBox(
        color: Colors.white.withValues(alpha: 0.96),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 460),
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Material(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(8),
                child: Container(
                  padding: const EdgeInsets.all(18),
                  decoration: BoxDecoration(
                    border: Border.all(color: AppColors.line),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      const Icon(
                        Icons.lock_person_rounded,
                        color: AppColors.danger,
                        size: 44,
                      ),
                      const SizedBox(height: 14),
                      Text(
                        'Tampilan ujian dikunci',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          color: AppColors.ink,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        widget.event.message,
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium,
                      ),
                      const SizedBox(height: 6),
                      Text(
                        'Panggil pengawas jika ini terjadi saat ujian berlangsung.',
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 18),
                      if (widget.requiresSupervisorCode) ...[
                        TextField(
                          controller: _codeController,
                          obscureText: true,
                          enabled: !_checking,
                          textInputAction: TextInputAction.done,
                          onSubmitted: (_) => _submitSupervisorCode(),
                          decoration: InputDecoration(
                            labelText: 'Kode Pengawas',
                            errorText: _error,
                            prefixIcon: const Icon(Icons.key_rounded),
                          ),
                        ),
                        const SizedBox(height: 12),
                        FilledButton.icon(
                          onPressed: _checking ? null : _submitSupervisorCode,
                          icon: _checking
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                  ),
                                )
                              : const Icon(Icons.lock_open_rounded),
                          label: Text(
                            _checking
                                ? 'Memvalidasi...'
                                : 'Buka dengan Kode Pengawas',
                          ),
                        ),
                      ] else
                        FilledButton.icon(
                          onPressed: widget.onContinue,
                          icon: const Icon(Icons.lock_open_rounded),
                          label: const Text('Lanjutkan Ujian'),
                        ),
                      const SizedBox(height: 8),
                      OutlinedButton.icon(
                        onPressed: widget.onExit,
                        icon: const Icon(Icons.logout_rounded),
                        label: const Text('Keluar'),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ExamToolbar extends StatelessWidget {
  const _ExamToolbar({
    required this.progress,
    required this.pageLoading,
    required this.onExit,
    required this.onReload,
  });

  final int progress;
  final bool pageLoading;
  final VoidCallback onExit;
  final VoidCallback onReload;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        border: Border(bottom: BorderSide(color: AppColors.line)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            height: 52,
            child: Row(
              children: [
                const SizedBox(width: 14),
                const Icon(
                  Icons.verified_user_rounded,
                  color: AppColors.primary,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    'Mode Ujian',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                IconButton(
                  tooltip: 'Muat ulang',
                  onPressed: onReload,
                  icon: const Icon(Icons.refresh_rounded),
                ),
                TextButton(onPressed: onExit, child: const Text('Keluar')),
                const SizedBox(width: 8),
              ],
            ),
          ),
          if (pageLoading)
            LinearProgressIndicator(
              value: progress > 0 && progress < 100 ? progress / 100 : null,
              minHeight: 3,
            )
          else
            const SizedBox(height: 3),
        ],
      ),
    );
  }
}

class _MessageBanner extends StatelessWidget {
  const _MessageBanner({
    required this.message,
    required this.color,
    required this.icon,
    required this.onDismissed,
  });

  final String message;
  final Color color;
  final IconData icon;
  final VoidCallback onDismissed;

  @override
  Widget build(BuildContext context) {
    return Positioned(
      top: 12,
      left: 12,
      right: 12,
      child: Material(
        color: color,
        borderRadius: BorderRadius.circular(8),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              Icon(icon, color: Colors.white),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  message,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    letterSpacing: 0,
                  ),
                ),
              ),
              IconButton(
                onPressed: onDismissed,
                icon: const Icon(Icons.close_rounded, color: Colors.white),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _GuardWarning extends StatelessWidget {
  const _GuardWarning({required this.event, required this.onDismissed});

  final GuardEvent event;
  final VoidCallback onDismissed;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: AppColors.danger,
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
        child: Row(
          children: [
            const Icon(Icons.warning_rounded, color: Colors.white),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                event.message,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 14,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 0,
                ),
              ),
            ),
            IconButton(
              onPressed: onDismissed,
              icon: const Icon(Icons.close_rounded, color: Colors.white),
            ),
          ],
        ),
      ),
    );
  }
}
