import 'package:flutter/foundation.dart';
import '../models/login_response.dart';
import 'api_service.dart';

class AppNotificationItem {
  final int id;
  final String title;
  final String message;
  final String type;
  final bool isRead;
  final DateTime? createdAt;
  final Map<String, dynamic>? data;

  const AppNotificationItem({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.isRead,
    required this.createdAt,
    required this.data,
  });

  int? get leaveId => _parseNullableInt(data?['izin_id']);

  int? get classId => _parseNullableInt(data?['kelas_id']);

  String? get status => data?['status']?.toString();

  Map<String, dynamic> get presentationData {
    final raw = data?['presentation'];
    if (raw is Map<String, dynamic>) {
      return raw;
    }
    if (raw is Map) {
      return Map<String, dynamic>.from(raw);
    }
    return const <String, dynamic>{};
  }

  Map<String, dynamic> get popupData {
    final raw = data?['popup'];
    if (raw is Map<String, dynamic>) {
      return raw;
    }
    if (raw is Map) {
      return Map<String, dynamic>.from(raw);
    }
    return const <String, dynamic>{};
  }

  bool get isAnnouncement {
    final messageCategory =
        (data?['message_category'] ?? '').toString().trim().toLowerCase();
    if (messageCategory == 'announcement') {
      return true;
    }
    if (messageCategory == 'system') {
      return false;
    }

    final broadcastCampaignId = data?['broadcast_campaign_id'];
    if (broadcastCampaignId != null && '$broadcastCampaignId'.trim().isNotEmpty) {
      return true;
    }

    final source = (data?['source'] ?? '').toString().trim().toLowerCase();
    return source.contains('broadcast');
  }

  bool get isSystemMessage => !isAnnouncement;

  bool get shouldShowPopup => presentationData['popup'] == true;

  bool get showsInAppNotification {
    final hasInAppFlag = presentationData.containsKey('in_app');
    if (hasInAppFlag) {
      return presentationData['in_app'] == true ||
          presentationData['in_app'] == 1 ||
          presentationData['in_app'] == '1' ||
          presentationData['in_app'] == 'true';
    }

    return !shouldShowPopup;
  }

  String get popupTitle {
    final customTitle = popupData['title']?.toString().trim() ?? '';
    if (customTitle.isNotEmpty) {
      return customTitle;
    }
    return title;
  }

  String? get popupImageUrl {
    final value = popupData['image_url']?.toString().trim() ?? '';
    return value.isEmpty ? null : value;
  }

  String get popupVariant {
    final value = popupData['variant']?.toString().trim() ?? '';
    if (value == 'info' || value == 'flyer') {
      return value;
    }

    return popupImageUrl != null ? 'flyer' : 'info';
  }

  String get categoryLabel => isAnnouncement ? 'Pengumuman' : 'Pesan Sistem';

  String get presentationLabel {
    if (!shouldShowPopup) {
      return 'Notifikasi';
    }

    if (!showsInAppNotification) {
      return popupVariant == 'flyer' ? 'Flyer' : 'Popup';
    }

    return popupVariant == 'flyer'
        ? 'Notifikasi + Flyer'
        : 'Notifikasi + Popup';
  }

  String get popupDismissLabel {
    final value = popupData['dismiss_label']?.toString().trim() ?? '';
    return value.isEmpty ? 'Tutup' : value;
  }

  String? get popupCtaLabel {
    final value = popupData['cta_label']?.toString().trim() ?? '';
    return value.isEmpty ? null : value;
  }

  String? get popupCtaUrl {
    final value = popupData['cta_url']?.toString().trim() ?? '';
    return value.isEmpty ? null : value;
  }

  bool get popupSticky => popupData['sticky'] == true;

  factory AppNotificationItem.fromJson(Map<String, dynamic> json) {
    return AppNotificationItem(
      id: json['id'] is int ? json['id'] as int : int.tryParse('${json['id']}') ?? 0,
      title: (json['title'] ?? '').toString(),
      message: (json['message'] ?? '').toString(),
      type: (json['type'] ?? 'info').toString(),
      isRead: json['is_read'] == true || json['is_read'] == 1,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'].toString())
          : null,
      data: json['data'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['data'])
          : json['data'] is Map
              ? Map<String, dynamic>.from(json['data'])
              : null,
    );
  }

  static int? _parseNullableInt(dynamic value) {
    if (value is int) {
      return value;
    }

    return int.tryParse('${value ?? ''}');
  }
}

class NotificationUnreadSummary {
  final int totalUnreadCount;
  final int systemUnreadCount;
  final int announcementUnreadCount;

  const NotificationUnreadSummary({
    required this.totalUnreadCount,
    required this.systemUnreadCount,
    required this.announcementUnreadCount,
  });
}

class NotificationService {
  NotificationService._();
  static final NotificationService _instance = NotificationService._();
  factory NotificationService() => _instance;

  final ApiService _apiService = ApiService();
  final ValueNotifier<int> unreadCountNotifier = ValueNotifier<int>(0);

  static int _parseCount(dynamic value) {
    if (value is int) {
      return value;
    }

    return int.tryParse('${value ?? 0}') ?? 0;
  }

  Future<ApiResponse<int>> getUnreadCount() async {
    try {
      final response = await _apiService.get('/notifications/unread/count');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final data = body['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final unreadCount = _parseCount(
        data['unread_count_total'] ?? data['unread_count'],
      );

      if (body['success'] == true) {
        unreadCountNotifier.value = unreadCount;
      }

      return ApiResponse<int>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Jumlah notifikasi belum dibaca berhasil diambil').toString(),
        data: unreadCount,
      );
    } on ApiException catch (e) {
      return ApiResponse<int>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<int>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<NotificationUnreadSummary>> getUnreadSummary() async {
    try {
      final response = await _apiService.get('/notifications/unread/count');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final data = body['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final summary = NotificationUnreadSummary(
        totalUnreadCount: _parseCount(
          data['unread_count_total'] ?? data['unread_count'],
        ),
        systemUnreadCount: _parseCount(data['system_unread_count']),
        announcementUnreadCount: _parseCount(data['announcement_unread_count']),
      );

      if (body['success'] == true) {
        unreadCountNotifier.value = summary.totalUnreadCount;
      }

      return ApiResponse<NotificationUnreadSummary>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Ringkasan notifikasi berhasil diambil')
            .toString(),
        data: summary,
      );
    } on ApiException catch (e) {
      return ApiResponse<NotificationUnreadSummary>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<NotificationUnreadSummary>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<List<AppNotificationItem>>> getNotifications({
    bool? isRead,
    int perPage = 20,
    String? category,
    bool popupOnly = false,
  }) async {
    try {
      final response = await _apiService.get(
        '/notifications',
        queryParameters: {
          'per_page': '$perPage',
          if (isRead != null) 'is_read': isRead ? '1' : '0',
          if (category != null && category.trim().isNotEmpty)
            'category': category.trim(),
          if (popupOnly) 'popup': '1',
        },
      );
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final raw = body['data'];
      final List<dynamic> rows;
      if (raw is List) {
        rows = raw;
      } else if (raw is Map<String, dynamic> && raw['data'] is List) {
        rows = raw['data'] as List<dynamic>;
      } else {
        rows = const <dynamic>[];
      }

      return ApiResponse<List<AppNotificationItem>>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Notifikasi berhasil diambil').toString(),
        data: rows
            .whereType<Map>()
            .map((item) => AppNotificationItem.fromJson(Map<String, dynamic>.from(item)))
            .toList(),
      );
    } on ApiException catch (e) {
      return ApiResponse<List<AppNotificationItem>>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<List<AppNotificationItem>>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<List<AppNotificationItem>>> getUnreadPopupNotifications({
    int perPage = 20,
  }) async {
    final response = await getNotifications(
      isRead: false,
      perPage: perPage,
      popupOnly: true,
    );
    if (!response.success) {
      return response;
    }

    final popupItems = (response.data ?? const <AppNotificationItem>[])
        .where((item) => item.shouldShowPopup)
        .toList();

    return ApiResponse<List<AppNotificationItem>>(
      success: true,
      message: response.message,
      data: popupItems,
    );
  }

  Future<ApiResponse<List<AppNotificationItem>>> getUnreadPopupAnnouncements({
    int perPage = 20,
  }) {
    return getUnreadPopupNotifications(perPage: perPage);
  }

  Future<ApiResponse<void>> markAsRead(int id) async {
    try {
      final response = await _apiService.post('/notifications/$id/read');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      if (body['success'] == true && unreadCountNotifier.value > 0) {
        unreadCountNotifier.value = unreadCountNotifier.value - 1;
      }
      return ApiResponse<void>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Notifikasi ditandai dibaca').toString(),
      );
    } on ApiException catch (e) {
      return ApiResponse<void>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<void>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<void>> markAllAsRead({String? category}) async {
    try {
      final response = await _apiService.post(
        '/notifications/read-all',
        data: category != null && category.trim().isNotEmpty
            ? <String, dynamic>{'category': category.trim()}
            : null,
      );
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      if (body['success'] == true) {
        unreadCountNotifier.value = 0;
      }
      return ApiResponse<void>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Semua notifikasi ditandai dibaca').toString(),
      );
    } on ApiException catch (e) {
      return ApiResponse<void>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<void>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<void>> deleteNotification(
    int id, {
    required bool wasRead,
  }) async {
    try {
      final response = await _apiService.delete('/notifications/$id');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      if (body['success'] == true && !wasRead && unreadCountNotifier.value > 0) {
        unreadCountNotifier.value = unreadCountNotifier.value - 1;
      }
      return ApiResponse<void>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Notifikasi berhasil dihapus').toString(),
      );
    } on ApiException catch (e) {
      return ApiResponse<void>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<void>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }
}
