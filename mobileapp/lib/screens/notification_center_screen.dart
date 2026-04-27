import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../models/login_response.dart';
import '../providers/auth_provider.dart';
import '../services/notification_service.dart';
import '../utils/constants.dart';
import '../widgets/broadcast_announcement_dialog.dart';
import 'leave_detail_screen.dart';
import 'wali_class_detail_screen.dart';

class NotificationCenterScreen extends StatefulWidget {
  const NotificationCenterScreen({super.key});

  @override
  State<NotificationCenterScreen> createState() => _NotificationCenterScreenState();
}

class _NotificationCenterScreenState extends State<NotificationCenterScreen>
    with SingleTickerProviderStateMixin {
  final NotificationService _service = NotificationService();

  bool _isLoading = true;
  String? _errorMessage;
  List<AppNotificationItem> _items = const <AppNotificationItem>[];
  late final TabController _tabController;
  NotificationUnreadSummary _summary = const NotificationUnreadSummary(
    totalUnreadCount: 0,
    systemUnreadCount: 0,
    announcementUnreadCount: 0,
  );

  List<AppNotificationItem> get _systemItems =>
      _items.where((item) => item.isSystemMessage).toList();

  List<AppNotificationItem> get _announcementItems =>
      _items.where((item) => item.isAnnouncement).toList();

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this)
      ..addListener(() {
        if (!mounted) {
          return;
        }

        if (!_tabController.indexIsChanging) {
          setState(() {});
        }
      });
    _loadNotifications();
  }

  Future<void> _loadNotifications() async {
    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final responses = await Future.wait([
      _service.getNotifications(),
      _service.getUnreadSummary(),
    ]);
    final response =
        responses[0] as ApiResponse<List<AppNotificationItem>>;
    final summaryResponse =
        responses[1] as ApiResponse<NotificationUnreadSummary>;
    if (!mounted) {
      return;
    }

    setState(() {
      _items = response.data ?? const <AppNotificationItem>[];
      _errorMessage = response.success ? null : response.message;
      _summary = summaryResponse.data ??
          const NotificationUnreadSummary(
            totalUnreadCount: 0,
            systemUnreadCount: 0,
            announcementUnreadCount: 0,
          );
      _isLoading = false;
    });
  }

  Future<void> _markAllAsRead() async {
    final response = await _service.markAllAsRead(
      category: _activeCategory,
    );
    if (!mounted) {
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(response.message)),
    );
    await _loadNotifications();
  }

  Future<bool> _confirmAndDelete(AppNotificationItem item) async {
    final shouldDelete = await showDialog<bool>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: const Text('Hapus Notifikasi'),
          content: const Text('Notifikasi ini akan dihapus dari daftar Anda. Lanjutkan?'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(dialogContext).pop(false),
              child: const Text('Batal'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(dialogContext).pop(true),
              style: FilledButton.styleFrom(
                backgroundColor: const Color(0xFFB4232C),
              ),
              child: const Text('Hapus'),
            ),
          ],
        );
      },
    );

    if (shouldDelete != true) {
      return false;
    }

    final response = await _service.deleteNotification(
      item.id,
      wasRead: item.isRead,
    );

    if (!mounted) {
      return false;
    }

    if (!response.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(response.message)),
      );
      return false;
    }

    setState(() {
      _items = _items.where((current) => current.id != item.id).toList();
    });

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(response.message)),
    );

    return true;
  }

  Future<void> _openNotification(AppNotificationItem item) async {
    if (!item.isRead) {
      final response = await _service.markAsRead(item.id);
      if (!response.success) {
        if (!mounted) {
          return;
        }

        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(response.message)),
        );
        return;
      }

      if (mounted) {
        setState(() {
          _items = _items
              .map((current) => current.id == item.id
                  ? AppNotificationItem(
                      id: current.id,
                      title: current.title,
                      message: current.message,
                      type: current.type,
                      isRead: true,
                      createdAt: current.createdAt,
                      data: current.data,
                    )
                  : current)
              .toList();
        });
      }
    }

    if (!mounted) {
      return;
    }

    final user = context.read<AuthProvider>().user;

    if (item.leaveId != null) {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => LeaveDetailScreen(leaveId: item.leaveId!),
        ),
      );
      return;
    }

    if (item.classId != null && (user?.canOpenWaliClassMenu ?? false)) {
      await Navigator.of(context).push(
        MaterialPageRoute(
          builder: (_) => WaliClassDetailScreen(classId: item.classId!),
        ),
      );
      return;
    }

    if (item.shouldShowPopup) {
      await _showPopupDialog(item);
      return;
    }

    _showNotificationSheet(item);
  }

  Future<void> _showPopupDialog(AppNotificationItem item) async {
    await showDialog<void>(
      context: context,
      barrierDismissible: !item.popupSticky,
      builder: (dialogContext) {
        return WillPopScope(
          onWillPop: () async => !item.popupSticky,
          child: BroadcastAnnouncementDialog(
            item: item,
            onDismiss: () => Navigator.of(dialogContext).pop(),
            onCtaTap: () => Navigator.of(dialogContext).pop(),
          ),
        );
      },
    );
  }

  void _showNotificationSheet(AppNotificationItem item) {
    showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.title,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: Color(0xFF123B67),
                  ),
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    _NotificationBadge(
                      label: item.presentationLabel,
                      textColor: const Color(0xFF0F766E),
                      backgroundColor: const Color(0xFFD1FAE5),
                    ),
                    Text(
                      _formatDate(item.createdAt),
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                        color: Color(0xFF7B8EA8),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Text(
                  item.message,
                  style: const TextStyle(
                    fontSize: 14,
                    height: 1.5,
                    color: Color(0xFF334155),
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  String _formatDate(DateTime? value) {
    if (value == null) {
      return '-';
    }
    return '${value.day.toString().padLeft(2, '0')}/${value.month.toString().padLeft(2, '0')}/${value.year} ${value.hour.toString().padLeft(2, '0')}:${value.minute.toString().padLeft(2, '0')}';
  }

  Color _typeColor(String type) {
    switch (type) {
      case 'success':
        return const Color(0xFF16A34A);
      case 'warning':
        return const Color(0xFFF59E0B);
      case 'error':
        return const Color(0xFFDC2626);
      default:
        return const Color(0xFF2563EB);
    }
  }

  @override
  Widget build(BuildContext context) {
    final user = context.watch<AuthProvider>().user;
    final activeItems = _activeCategory == 'announcement'
        ? _announcementItems
        : _systemItems;

    return Scaffold(
      backgroundColor: const Color(0xFFF3F7FF),
      appBar: AppBar(
        title: const Text('Pusat Notifikasi'),
        backgroundColor: Colors.white,
        foregroundColor: const Color(0xFF123B67),
        surfaceTintColor: Colors.transparent,
        actions: [
          TextButton(
            onPressed: activeItems.isEmpty ? null : _markAllAsRead,
            child: const Text('Tandai semua'),
          ),
        ],
        bottom: TabBar(
          controller: _tabController,
          labelColor: const Color(0xFF123B67),
          unselectedLabelColor: const Color(0xFF7B8EA8),
          indicatorColor: const Color(0xFF123B67),
          indicatorWeight: 3,
          tabs: [
            Tab(text: 'Pesan Sistem (${_summary.systemUnreadCount})'),
            Tab(text: 'Pengumuman (${_summary.announcementUnreadCount})'),
          ],
        ),
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          _buildNotificationTab(
            items: _systemItems,
            user: user,
            emptyTitle: 'Belum ada pesan sistem',
            emptyMessage:
                'Pengajuan izin, persetujuan, dan notifikasi proses akan tampil di sini.',
          ),
          _buildNotificationTab(
            items: _announcementItems,
            user: user,
            emptyTitle: 'Belum ada pengumuman',
            emptyMessage:
                'Broadcast pengumuman, popup informasi, dan flyer sekolah akan tampil di sini.',
          ),
        ],
      ),
    );
  }

  String get _activeCategory =>
      _tabController.index == 1 ? 'announcement' : 'system';

  Widget _buildNotificationTab({
    required List<AppNotificationItem> items,
    required dynamic user,
    required String emptyTitle,
    required String emptyMessage,
  }) {
    return RefreshIndicator(
      onRefresh: _loadNotifications,
      color: AppColors.primary,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (_isLoading)
            const Padding(
              padding: EdgeInsets.symmetric(vertical: 48),
              child: Center(child: CircularProgressIndicator()),
            )
          else if (_errorMessage != null)
            _NotificationErrorState(message: _errorMessage!, onRetry: _loadNotifications)
          else if (items.isEmpty)
            _NotificationEmptyState(title: emptyTitle, message: emptyMessage)
          else
            ...items.map(
              (item) => Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Dismissible(
                  key: ValueKey<int>(item.id),
                  direction: DismissDirection.endToStart,
                  confirmDismiss: (_) => _confirmAndDelete(item),
                  background: Container(
                    alignment: Alignment.centerRight,
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    decoration: BoxDecoration(
                      color: const Color(0xFFB4232C),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: const Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.delete_outline, color: Colors.white),
                        SizedBox(height: 4),
                        Text(
                          'Hapus',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                  child: InkWell(
                    borderRadius: BorderRadius.circular(18),
                    onTap: () => _openNotification(item),
                    child: Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(
                          color: item.isRead
                              ? const Color(0xFFD8E6F8)
                              : _typeColor(item.type).withValues(alpha: 0.35),
                        ),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Container(
                            width: 44,
                            height: 44,
                            decoration: BoxDecoration(
                              color: _typeColor(item.type).withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(14),
                            ),
                            child: Icon(Icons.notifications_outlined, color: _typeColor(item.type)),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  item.title,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 14,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF123B67),
                                  ),
                                ),
                                const SizedBox(height: 6),
                                Text(
                                  item.message,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    fontSize: 12,
                                    color: Color(0xFF66758A),
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  crossAxisAlignment: WrapCrossAlignment.center,
                                  children: [
                                    _NotificationBadge(
                                      label: item.presentationLabel,
                                      textColor: const Color(0xFF0F766E),
                                      backgroundColor: const Color(0xFFD1FAE5),
                                    ),
                                    Text(
                                      _formatDate(item.createdAt),
                                      style: const TextStyle(
                                        fontSize: 11,
                                        fontWeight: FontWeight.w600,
                                        color: Color(0xFF7B8EA8),
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                          if (item.leaveId != null || (item.classId != null && (user?.canOpenWaliClassMenu ?? false)))
                            const Padding(
                              padding: EdgeInsets.only(left: 8, top: 6),
                              child: Icon(
                                Icons.arrow_forward_ios_rounded,
                                size: 16,
                                color: Color(0xFF7B8EA8),
                              ),
                            ),
                          if (!item.isRead)
                            Container(
                              width: 10,
                              height: 10,
                              margin: const EdgeInsets.only(left: 8, top: 6),
                              decoration: BoxDecoration(
                                color: _typeColor(item.type),
                                shape: BoxShape.circle,
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }
}

class _NotificationErrorState extends StatelessWidget {
  final String message;
  final Future<void> Function() onRetry;

  const _NotificationErrorState({required this.message, required this.onRetry});

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
              onPressed: () {
                onRetry();
              },
              child: const Text('Muat ulang'),
            ),
          ],
        ),
      ),
    );
  }
}

class _NotificationEmptyState extends StatelessWidget {
  final String title;
  final String message;

  const _NotificationEmptyState({
    required this.title,
    required this.message,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFFD8E6F8)),
      ),
      child: Column(
        children: [
          const Icon(Icons.notifications_off_outlined, size: 42, color: Color(0xFF7B8EA8)),
          const SizedBox(height: 12),
          Text(
            title,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w700,
              color: Color(0xFF123B67),
            ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 13,
              color: Color(0xFF66758A),
            ),
          ),
        ],
      ),
    );
  }
}

class _NotificationBadge extends StatelessWidget {
  final String label;
  final Color textColor;
  final Color backgroundColor;

  const _NotificationBadge({
    required this.label,
    required this.textColor,
    required this.backgroundColor,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w700,
          color: textColor,
        ),
      ),
    );
  }
}
