import '../models/login_response.dart';
import '../utils/constants.dart';
import 'api_service.dart';

class DashboardService {
  static final DashboardService _instance = DashboardService._internal();
  factory DashboardService() => _instance;
  DashboardService._internal();

  final ApiService _apiService = ApiService();
  static const Duration _academicContextCacheTtl = Duration(seconds: 5);
  ApiResponse<AcademicContext>? _academicContextCache;
  DateTime? _academicContextFetchedAt;
  Future<ApiResponse<AcademicContext>>? _academicContextInFlight;

  Future<ApiResponse<AcademicContext>> getAcademicContext({
    bool forceRefresh = false,
  }) async {
    final now = DateTime.now();
    if (!forceRefresh &&
        _academicContextCache != null &&
        _academicContextFetchedAt != null &&
        now.difference(_academicContextFetchedAt!) < _academicContextCacheTtl) {
      return _academicContextCache!;
    }

    if (!forceRefresh && _academicContextInFlight != null) {
      return _academicContextInFlight!;
    }

    final future = _fetchAcademicContext();
    _academicContextInFlight = future;

    try {
      final result = await future;
      if (result.success) {
        _academicContextCache = result;
        _academicContextFetchedAt = DateTime.now();
      }

      return result;
    } finally {
      if (identical(_academicContextInFlight, future)) {
        _academicContextInFlight = null;
      }
    }
  }

  Future<ApiResponse<AcademicContext>> _fetchAcademicContext() async {
    try {
      final response = await _apiService.get('/academic-context/current');
      final body = response.data is Map<String, dynamic>
          ? Map<String, dynamic>.from(response.data)
          : <String, dynamic>{};
      final success = body['success'] == true || body['status'] == 'success';
      final data = body['data'];

      if (success && data is Map<String, dynamic>) {
        return ApiResponse<AcademicContext>(
          success: true,
          message: (body['message'] ?? 'Konteks akademik berhasil diambil')
              .toString(),
          data: AcademicContext.fromJson(data),
        );
      }

      return ApiResponse<AcademicContext>(
        success: false,
        message:
            (body['message'] ?? 'Konteks akademik aktif belum tersedia')
                .toString(),
      );
    } on ApiException catch (e) {
      return ApiResponse<AcademicContext>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<AcademicContext>(
        success: false,
        message: AppStrings.unknownError,
      );
    }
  }

  // Get dashboard stats
  Future<ApiResponse<DashboardStats>> getDashboardStats() async {
    try {
      final response = await _apiService.get('/dashboard/stats');

      if (response.data['success'] == true && response.data['data'] != null) {
        final stats = DashboardStats.fromJson(response.data['data']);
        return ApiResponse<DashboardStats>(
          success: true,
          message: response.data['message'] ?? 'Stats retrieved successfully',
          data: stats,
        );
      } else {
        return ApiResponse<DashboardStats>(
          success: false,
          message: response.data['message'] ?? 'Failed to get stats',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<DashboardStats>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<DashboardStats>(
        success: false,
        message: AppStrings.unknownError,
      );
    }
  }

  // Get today's attendance status
  Future<ApiResponse<TodayAttendanceStatus>> getTodayAttendanceStatus() async {
    try {
      final response = await _apiService.get('/dashboard/my-attendance-status');

      final payload = response.data['data'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(response.data['data'])
          : Map<String, dynamic>.from(response.data);
      final status = TodayAttendanceStatus.fromBackendJson(payload);
      return ApiResponse<TodayAttendanceStatus>(
        success: true,
        message: 'Status retrieved successfully',
        data: status,
      );
    } on ApiException catch (e) {
      return ApiResponse<TodayAttendanceStatus>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<TodayAttendanceStatus>(
        success: false,
        message: AppStrings.unknownError,
      );
    }
  }

  // Get recent activities
  Future<ApiResponse<List<RecentActivity>>> getRecentActivities() async {
    try {
      final response = await _apiService.get('/dashboard/recent-activities');

      if (response.data['success'] == true && response.data['data'] != null) {
        final activities = (response.data['data'] as List)
            .map((item) => RecentActivity.fromJson(item))
            .toList();
        return ApiResponse<List<RecentActivity>>(
          success: true,
          message:
              response.data['message'] ?? 'Activities retrieved successfully',
          data: activities,
        );
      } else {
        return ApiResponse<List<RecentActivity>>(
          success: false,
          message: response.data['message'] ?? 'Failed to get activities',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<List<RecentActivity>>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<List<RecentActivity>>(
        success: false,
        message: AppStrings.unknownError,
      );
    }
  }

  // Get system status
  Future<ApiResponse<SystemStatus>> getSystemStatus() async {
    try {
      final response = await _apiService.get('/dashboard/system-status');

      if (response.data['success'] == true && response.data['data'] != null) {
        final status = SystemStatus.fromJson(response.data['data']);
        return ApiResponse<SystemStatus>(
          success: true,
          message: response.data['message'] ??
              'System status retrieved successfully',
          data: status,
        );
      } else {
        return ApiResponse<SystemStatus>(
          success: false,
          message: response.data['message'] ?? 'Failed to get system status',
        );
      }
    } on ApiException catch (e) {
      return ApiResponse<SystemStatus>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<SystemStatus>(
        success: false,
        message: AppStrings.unknownError,
      );
    }
  }
}

class AcademicContext {
  final int? tahunAjaranId;
  final int? periodeAktifId;
  final String? tahunAjaranNama;
  final String? periodeNama;
  final String? semester;
  final DateTime? effectiveStartDate;
  final DateTime? effectiveEndDate;

  const AcademicContext({
    this.tahunAjaranId,
    this.periodeAktifId,
    this.tahunAjaranNama,
    this.periodeNama,
    this.semester,
    this.effectiveStartDate,
    this.effectiveEndDate,
  });

  factory AcademicContext.fromJson(Map<String, dynamic> json) {
    final tahunAjaran = json['tahun_ajaran'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['tahun_ajaran'])
        : <String, dynamic>{};
    final periodeAktif = json['periode_aktif'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['periode_aktif'])
        : <String, dynamic>{};
    final effectiveRange = json['effective_date_range'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['effective_date_range'])
        : <String, dynamic>{};

    return AcademicContext(
      tahunAjaranId: _parseInt(tahunAjaran['id']),
      periodeAktifId: _parseInt(periodeAktif['id']),
      tahunAjaranNama: tahunAjaran['nama']?.toString(),
      periodeNama: periodeAktif['nama']?.toString(),
      semester: periodeAktif['semester']?.toString(),
      effectiveStartDate: _parseDate(
        effectiveRange['start_date'] ?? tahunAjaran['tanggal_mulai'],
      ),
      effectiveEndDate: _parseDate(
        effectiveRange['end_date'] ?? tahunAjaran['tanggal_selesai'],
      ),
    );
  }

  static int? _parseInt(dynamic value) {
    if (value is int) {
      return value;
    }
    if (value is String) {
      return int.tryParse(value);
    }
    return null;
  }

  static DateTime? _parseDate(dynamic value) {
    if (value == null) {
      return null;
    }
    return DateTime.tryParse(value.toString());
  }

  String get semesterLabel {
    final raw = (semester ?? '').toLowerCase().trim();
    if (raw == 'genap') {
      return 'Genap';
    }
    if (raw == 'ganjil') {
      return 'Ganjil';
    }

    final fromPeriodeName = (periodeNama ?? '').toLowerCase();
    if (fromPeriodeName.contains('genap')) {
      return 'Genap';
    }
    if (fromPeriodeName.contains('ganjil')) {
      return 'Ganjil';
    }

    return '';
  }

  String get compactLabel {
    final tahun = (tahunAjaranNama ?? '').trim();
    final periode = semesterLabel.trim();

    if (tahun.isNotEmpty && periode.isNotEmpty) {
      return '$tahun I $periode';
    }

    if (tahun.isNotEmpty) {
      return tahun;
    }

    return '-';
  }
}

// Dashboard Stats Model
class DashboardStats {
  final int totalUsers;
  final int totalStudents;
  final int totalTeachers;
  final int todayActivities;
  final int attendanceCount;
  final int leaveCount;
  final String attendanceRate;
  final int lateCount;
  final int totalRoles;
  final int totalPermissions;
  final int pendingApprovals;
  final String userRole;

  DashboardStats({
    this.totalUsers = 0,
    this.totalStudents = 0,
    this.totalTeachers = 0,
    this.todayActivities = 0,
    this.attendanceCount = 0,
    this.leaveCount = 0,
    this.attendanceRate = '0%',
    this.lateCount = 0,
    this.totalRoles = 0,
    this.totalPermissions = 0,
    this.pendingApprovals = 0,
    this.userRole = 'Unknown',
  });

  factory DashboardStats.fromJson(Map<String, dynamic> json) {
    return DashboardStats(
      totalUsers: json['totalUsers'] ?? 0,
      totalStudents: json['totalStudents'] ?? 0,
      totalTeachers: json['totalTeachers'] ?? 0,
      todayActivities: json['todayActivities'] ?? 0,
      attendanceCount: json['attendanceCount'] ?? 0,
      leaveCount: json['leaveCount'] ?? 0,
      attendanceRate: json['attendanceRate'] ?? '0%',
      lateCount: json['lateCount'] ?? 0,
      totalRoles: json['totalRoles'] ?? 0,
      totalPermissions: json['totalPermissions'] ?? 0,
      pendingApprovals: json['pendingApprovals'] ?? 0,
      userRole: json['userRole'] ?? 'Unknown',
    );
  }
}

// Today Attendance Status Model
class TodayAttendanceStatus {
  final bool hasCheckedIn;
  final bool hasCheckedOut;
  final String? checkinTime;
  final String? checkoutTime;
  final String status;
  final String statusKey;
  final String statusLabel;
  final bool isLate;
  final String? location;
  final bool isNonPresenceStatus;
  final bool isHoliday;
  final String? holidayMessage;
  final String? attendanceLockReason;

  TodayAttendanceStatus({
    required this.hasCheckedIn,
    required this.hasCheckedOut,
    this.checkinTime,
    this.checkoutTime,
    required this.status,
    required this.statusKey,
    required this.statusLabel,
    required this.isLate,
    this.location,
    required this.isNonPresenceStatus,
    required this.isHoliday,
    this.holidayMessage,
    this.attendanceLockReason,
  });

  factory TodayAttendanceStatus.fromJson(Map<String, dynamic> json) {
    return TodayAttendanceStatus(
      hasCheckedIn: json['has_checked_in'] ?? false,
      hasCheckedOut: json['has_checked_out'] ?? false,
      checkinTime: json['checkin_time'],
      checkoutTime: json['checkout_time'],
      status: json['status'] ?? 'Belum Absen',
      statusKey: json['status_key'] ?? 'belum_absen',
      statusLabel: json['status_label'] ?? (json['status'] ?? 'Belum Absen'),
      isLate: json['is_late'] ?? false,
      location: json['location'],
      isNonPresenceStatus: json['is_non_presence_status'] ?? false,
      isHoliday: json['is_holiday'] == true ||
          (json['status_key']?.toString().trim().toLowerCase() == 'libur'),
      holidayMessage: json['holiday_message']?.toString(),
      attendanceLockReason: json['attendance_lock_reason'],
    );
  }

  // Factory for backend response format
  factory TodayAttendanceStatus.fromBackendJson(Map<String, dynamic> json) {
    final payload = json['data'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['data'])
        : json;

    final hasAttendance = payload['has_attendance'] ?? false;
    final checkinData = payload['check_in'];
    final checkoutData = payload['check_out'];
    final statusRaw = (payload['status'] ?? 'Belum Absen').toString();
    final statusKey = (payload['status_key'] ?? statusRaw).toString();
    final normalizedStatusKey = statusKey.trim().toLowerCase();
    final isHoliday =
        payload['is_holiday'] == true || normalizedStatusKey == 'libur';
    final isNonPresenceStatus = payload['is_non_presence_status'] == true ||
        normalizedStatusKey == 'izin' ||
        normalizedStatusKey == 'sakit' ||
        normalizedStatusKey == 'alpha';
    final holidayMessage = payload['holiday_message'] != null
        ? payload['holiday_message'].toString()
        : (isHoliday
            ? 'Selamat menikmati hari libur Anda. Absensi tidak dibuka hari ini.'
            : null);
    final attendanceLockReason = payload['attendance_lock_reason'] != null
        ? payload['attendance_lock_reason'].toString()
        : (isNonPresenceStatus
            ? 'Absensi dikunci karena status ${payload['status_label'] ?? statusRaw} hari ini.'
            : null);
    final hasCheckedIn =
        payload['has_checked_in'] ?? (hasAttendance && checkinData != null);
    final hasCheckedOut =
        payload['has_checked_out'] ?? (hasAttendance && checkoutData != null);

    return TodayAttendanceStatus(
      hasCheckedIn: hasCheckedIn == true,
      hasCheckedOut: hasCheckedOut == true,
      checkinTime: checkinData,
      checkoutTime: checkoutData,
      status: statusRaw,
      statusKey: statusKey,
      statusLabel: (payload['status_label'] ?? statusRaw).toString(),
      isLate: payload['is_late'] ?? false,
      location: payload['location_in'],
      isNonPresenceStatus: isNonPresenceStatus,
      isHoliday: isHoliday,
      holidayMessage: holidayMessage,
      attendanceLockReason: attendanceLockReason,
    );
  }
}

// Recent Activity Model
class RecentActivity {
  final String id;
  final String type;
  final String description;
  final String time;
  final String? userInfo;

  RecentActivity({
    required this.id,
    required this.type,
    required this.description,
    required this.time,
    this.userInfo,
  });

  factory RecentActivity.fromJson(Map<String, dynamic> json) {
    return RecentActivity(
      id: json['id'].toString(),
      type: json['type'] ?? '',
      description: json['description'] ?? '',
      time: json['time'] ?? '',
      userInfo: json['user_info'],
    );
  }
}

// System Status Model
class SystemStatus {
  final bool isOnline;
  final String serverTime;
  final String version;
  final int activeUsers;

  SystemStatus({
    required this.isOnline,
    required this.serverTime,
    required this.version,
    required this.activeUsers,
  });

  factory SystemStatus.fromJson(Map<String, dynamic> json) {
    return SystemStatus(
      isOnline: json['is_online'] ?? true,
      serverTime: json['server_time'] ?? '',
      version: json['version'] ?? '1.0.0',
      activeUsers: json['active_users'] ?? 0,
    );
  }
}
