import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../providers/auth_provider.dart';
import '../services/dashboard_service.dart';
import '../services/lesson_schedule_service.dart';
import '../services/manual_data_sync_service.dart';
import '../utils/constants.dart';
import '../widgets/access_denied_scaffold.dart';

class ScheduleOverviewScreen extends StatefulWidget {
  const ScheduleOverviewScreen({super.key});

  @override
  State<ScheduleOverviewScreen> createState() => _ScheduleOverviewScreenState();
}

class _ScheduleOverviewScreenState extends State<ScheduleOverviewScreen>
    with SingleTickerProviderStateMixin {
  final DashboardService _dashboardService = DashboardService();
  final LessonScheduleService _service = LessonScheduleService();
  final ManualDataSyncService _manualDataSyncService = ManualDataSyncService();

  bool _hasAccess = false;
  late final TabController _tabController;
  final Map<int, List<LessonScheduleItem>> _cache = <int, List<LessonScheduleItem>>{};
  bool _isLoading = true;
  String? _errorMessage;
  int? _activeTahunAjaranId;
  String _academicContextLabel = '-';
  int _lastManualSyncVersion = 0;

  static const List<Map<String, dynamic>> _days = <Map<String, dynamic>>[
    {'label': 'Sen', 'weekday': DateTime.monday},
    {'label': 'Sel', 'weekday': DateTime.tuesday},
    {'label': 'Rab', 'weekday': DateTime.wednesday},
    {'label': 'Kam', 'weekday': DateTime.thursday},
    {'label': 'Jum', 'weekday': DateTime.friday},
    {'label': 'Sab', 'weekday': DateTime.saturday},
  ];

  @override
  void initState() {
    super.initState();
    final initialIndex = DateTime.now().weekday >= DateTime.monday &&
            DateTime.now().weekday <= DateTime.saturday
        ? DateTime.now().weekday - 1
        : 0;
    _tabController = TabController(
      length: _days.length,
      vsync: this,
      initialIndex: initialIndex,
    );
    _tabController.addListener(() {
      if (!_tabController.indexIsChanging) {
        _loadDay(_days[_tabController.index]['weekday'] as int);
      }
    });
    _lastManualSyncVersion = _manualDataSyncService.syncVersion;
    _manualDataSyncService.addListener(_handleManualSyncChanged);
    _hasAccess = context.read<AuthProvider>().user?.canViewScheduleOnMobile ?? false;
    if (_hasAccess) {
      _initializeContextAndData(_days[initialIndex]['weekday'] as int);
    } else {
      _isLoading = false;
    }
  }

  void _handleManualSyncChanged() {
    if (!_hasAccess) {
      return;
    }

    if (!mounted) {
      return;
    }

    if (_lastManualSyncVersion == _manualDataSyncService.syncVersion) {
      return;
    }

    _lastManualSyncVersion = _manualDataSyncService.syncVersion;
    _cache.clear();
    _refreshDayWithContext(_days[_tabController.index]['weekday'] as int);
  }

  @override
  void dispose() {
    _manualDataSyncService.removeListener(_handleManualSyncChanged);
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _initializeContextAndData(int initialWeekday) async {
    final contextResponse = await _dashboardService.getAcademicContext();
    if (mounted && contextResponse.success && contextResponse.data != null) {
      setState(() {
        _activeTahunAjaranId = contextResponse.data?.tahunAjaranId;
        _academicContextLabel = contextResponse.data?.compactLabel ?? '-';
      });
    }

    await _loadDay(initialWeekday);
  }

  Future<void> _refreshDayWithContext(int weekday) async {
    final contextResponse = await _dashboardService.getAcademicContext();
    if (mounted && contextResponse.success && contextResponse.data != null) {
      setState(() {
        _activeTahunAjaranId = contextResponse.data?.tahunAjaranId;
        _academicContextLabel = contextResponse.data?.compactLabel ?? '-';
      });
    }

    await _loadDay(weekday);
  }

  Future<void> _loadDay(int weekday) async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final response = await _service.getScheduleByWeekday(
      weekday,
      tahunAjaranId: _activeTahunAjaranId,
    );
    if (!mounted) {
      return;
    }

    setState(() {
      if (response.success) {
        _cache[weekday] = response.data ?? const <LessonScheduleItem>[];
      } else {
        _errorMessage = response.message;
      }
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (!_hasAccess) {
      return const AccessDeniedScaffold(
        title: 'Jadwal Saya',
        message: 'Jadwal pribadi hanya tersedia untuk role Siswa, Guru, atau Wali Kelas.',
      );
    }

    final activeWeekday = _days[_tabController.index]['weekday'] as int;
    final lessons = _cache[activeWeekday] ?? const <LessonScheduleItem>[];
    final scheduleItems = _buildScheduleVisualItems(lessons);

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Jadwal Saya'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
        bottom: TabBar(
          controller: _tabController,
          isScrollable: true,
          labelColor: const Color(0xFF123B67),
          indicatorColor: AppColors.primary,
          tabs: _days
              .map((day) => Tab(text: day['label'] as String))
              .toList(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () => _refreshDayWithContext(activeWeekday),
        color: AppColors.primary,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: const Color(0xFFD8E6F8)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Agenda Hari Aktif',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF123B67),
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Jadwal ditampilkan sesuai hari aktif dan scope akun pada backend.',
                    style: TextStyle(
                      fontSize: 13,
                      color: Color(0xFF66758A),
                    ),
                  ),
                  if (_academicContextLabel.trim().isNotEmpty &&
                      _academicContextLabel != '-') ...[
                    const SizedBox(height: 6),
                    Text(
                      'Tahun ajaran: $_academicContextLabel',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF2A5C8E),
                      ),
                    ),
                  ],
                  if (lessons.isNotEmpty) ...[
                    const SizedBox(height: 10),
                    Text(
                      '${lessons.length} JP terjadwal',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF607893),
                      ),
                    ),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (_isLoading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 48),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_errorMessage != null)
              _ScheduleErrorState(message: _errorMessage!, onRetry: () => _loadDay(activeWeekday))
            else if (lessons.isEmpty)
              const _ScheduleEmptyState()
            else
              ...scheduleItems.map(
                (item) => Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: item.isBreak ? const Color(0xFFFFF9E7) : Colors.white,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(
                        color: item.isBreak
                            ? const Color(0xFFF1D18A)
                            : const Color(0xFFD8E6F8),
                      ),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 62,
                          padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 8),
                          decoration: BoxDecoration(
                            color: item.isBreak
                                ? const Color(0xFFFFF1CC)
                                : const Color(0xFFEAF4FF),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Column(
                            children: [
                              Text(
                                item.jamMulai,
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w800,
                                  color: item.isBreak
                                      ? const Color(0xFF9A6700)
                                      : const Color(0xFF123B67),
                                ),
                              ),
                              const SizedBox(height: 2),
                              Text(
                                item.jamSelesai,
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: item.isBreak
                                      ? const Color(0xFFB07A00)
                                      : const Color(0xFF66758A),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.title,
                                style: TextStyle(
                                  fontSize: 15,
                                  fontWeight: FontWeight.w700,
                                  color: item.isBreak
                                      ? const Color(0xFF9A6700)
                                      : const Color(0xFF123B67),
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                item.subtitle,
                                style: TextStyle(
                                  fontSize: 13,
                                  fontWeight: FontWeight.w600,
                                  color: item.isBreak
                                      ? const Color(0xFFB07A00)
                                      : const Color(0xFF66758A),
                                ),
                              ),
                              if (!item.isBreak &&
                                  item.teacher.trim().isNotEmpty &&
                                  item.teacher != '-')
                                Padding(
                                  padding: const EdgeInsets.only(top: 4),
                                  child: Text(
                                    item.teacher,
                                    style: const TextStyle(
                                      fontSize: 12,
                                      color: Color(0xFF7B8EA8),
                                    ),
                                  ),
                                ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  List<_ScheduleVisualItem> _buildScheduleVisualItems(
    List<LessonScheduleItem> sourceLessons,
  ) {
    if (sourceLessons.isEmpty) {
      return const <_ScheduleVisualItem>[];
    }

    final lessonsSorted = [...sourceLessons]..sort((a, b) {
      final byStart = _parseTimeToMinutes(a.jamMulai).compareTo(
        _parseTimeToMinutes(b.jamMulai),
      );
      if (byStart != 0) {
        return byStart;
      }
      return (a.jamKe ?? 0).compareTo(b.jamKe ?? 0);
    });

    final items = <_ScheduleVisualItem>[];
    for (var i = 0; i < lessonsSorted.length; i++) {
      final lesson = lessonsSorted[i];
      items.add(
        _ScheduleVisualItem.lesson(
          jamMulai: lesson.jamMulai,
          jamSelesai: lesson.jamSelesai,
          title: lesson.mataPelajaranNama,
          subtitle: lesson.kelasNama,
          teacher: lesson.guruNama,
        ),
      );

      if (i >= lessonsSorted.length - 1) {
        continue;
      }

      final nextLesson = lessonsSorted[i + 1];
      final currentEnd = _parseTimeToMinutes(lesson.jamSelesai);
      final nextStart = _parseTimeToMinutes(nextLesson.jamMulai);
      final gapMinutes = nextStart - currentEnd;
      if (gapMinutes <= 0) {
        continue;
      }

      items.add(
        _ScheduleVisualItem.breakTime(
          jamMulai: lesson.jamSelesai,
          jamSelesai: nextLesson.jamMulai,
          title: _buildBreakLabel(gapMinutes),
          subtitle:
              '${_formatHm(lesson.jamSelesai)} - ${_formatHm(nextLesson.jamMulai)} (${gapMinutes} menit)',
        ),
      );
    }

    return items;
  }

  int _parseTimeToMinutes(String value) {
    final parts = value.split(':');
    if (parts.length < 2) {
      return 0;
    }

    final hour = int.tryParse(parts[0]) ?? 0;
    final minute = int.tryParse(parts[1]) ?? 0;
    return (hour * 60) + minute;
  }

  String _buildBreakLabel(int gapMinutes) {
    if (gapMinutes >= 30) {
      return 'Istirahat Panjang';
    }
    return 'Istirahat';
  }

  String _formatHm(String value) {
    if (value.length >= 5 && value.contains(':')) {
      return value.substring(0, 5);
    }
    return value;
  }
}

class _ScheduleErrorState extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;

  const _ScheduleErrorState({required this.message, required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 40),
        child: Column(
          children: [
            const Icon(Icons.error_outline, size: 40, color: Color(0xFFB4232C)),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            OutlinedButton(
              onPressed: onRetry,
              child: const Text('Muat ulang'),
            ),
          ],
        ),
      ),
    );
  }
}

class _ScheduleEmptyState extends StatelessWidget {
  const _ScheduleEmptyState();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: const Column(
        children: [
          Icon(Icons.event_busy_outlined, size: 42, color: Color(0xFF7B8EA8)),
          SizedBox(height: 12),
          Text(
            'Belum ada jadwal di hari ini',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          SizedBox(height: 6),
          Text(
            'Jadwal akan tampil jika sudah dipublish dan sesuai role akun Anda.',
            textAlign: TextAlign.center,
            style: TextStyle(
              fontSize: 13,
              color: Color(0xFF66758A),
            ),
          ),
        ],
      ),
    );
  }
}

class _ScheduleVisualItem {
  final bool isBreak;
  final String jamMulai;
  final String jamSelesai;
  final String title;
  final String subtitle;
  final String teacher;

  const _ScheduleVisualItem._({
    required this.isBreak,
    required this.jamMulai,
    required this.jamSelesai,
    required this.title,
    required this.subtitle,
    required this.teacher,
  });

  factory _ScheduleVisualItem.lesson({
    required String jamMulai,
    required String jamSelesai,
    required String title,
    required String subtitle,
    required String teacher,
  }) {
    return _ScheduleVisualItem._(
      isBreak: false,
      jamMulai: jamMulai,
      jamSelesai: jamSelesai,
      title: title,
      subtitle: subtitle,
      teacher: teacher,
    );
  }

  factory _ScheduleVisualItem.breakTime({
    required String jamMulai,
    required String jamSelesai,
    required String title,
    required String subtitle,
  }) {
    return _ScheduleVisualItem._(
      isBreak: true,
      jamMulai: jamMulai,
      jamSelesai: jamSelesai,
      title: title,
      subtitle: subtitle,
      teacher: '',
    );
  }
}
