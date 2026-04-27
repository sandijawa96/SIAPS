import '../models/login_response.dart';
import '../utils/constants.dart';
import 'api_service.dart';

class LivePdReportSummary {
  final int totalStudents;
  final int hadir;
  final int terlambat;
  final int izin;
  final int sakit;
  final int alpha;
  final int belumAbsen;
  final int maleStudents;
  final int femaleStudents;

  const LivePdReportSummary({
    required this.totalStudents,
    required this.hadir,
    required this.terlambat,
    required this.izin,
    required this.sakit,
    required this.alpha,
    required this.belumAbsen,
    required this.maleStudents,
    required this.femaleStudents,
  });

  factory LivePdReportSummary.fromJson(Map<String, dynamic> json) {
    int parseInt(dynamic value) {
      if (value is int) {
        return value;
      }
      return int.tryParse('${value ?? 0}') ?? 0;
    }

    return LivePdReportSummary(
      totalStudents: parseInt(json['total_students']),
      hadir: parseInt(json['hadir']),
      terlambat: parseInt(json['terlambat']),
      izin: parseInt(json['izin']),
      sakit: parseInt(json['sakit']),
      alpha: parseInt(json['alpha']),
      belumAbsen: parseInt(json['belum_absen']),
      maleStudents: parseInt(json['male_students']),
      femaleStudents: parseInt(json['female_students']),
    );
  }
}

class LivePdReportItem {
  final int userId;
  final String name;
  final String? nis;
  final String? nisn;
  final String status;
  final String statusLabel;
  final String? checkInTime;
  final String? checkOutTime;
  final String? expectedCheckInTime;
  final String? expectedCheckOutTime;
  final int lateMinutes;
  final bool isLate;
  final bool isCheckoutPending;
  final String indicatorKey;
  final String indicatorLabel;
  final String? timeHint;
  final String? notes;
  final String? roleLabel;
  final String? locationLabel;
  final String? userPhotoUrl;
  final bool isSelf;

  const LivePdReportItem({
    required this.userId,
    required this.name,
    required this.nis,
    required this.nisn,
    required this.status,
    required this.statusLabel,
    required this.checkInTime,
    required this.checkOutTime,
    required this.expectedCheckInTime,
    required this.expectedCheckOutTime,
    required this.lateMinutes,
    required this.isLate,
    required this.isCheckoutPending,
    required this.indicatorKey,
    required this.indicatorLabel,
    required this.timeHint,
    required this.notes,
    required this.roleLabel,
    required this.locationLabel,
    required this.userPhotoUrl,
    required this.isSelf,
  });

  factory LivePdReportItem.fromJson(Map<String, dynamic> json) {
    int parseInt(dynamic value) {
      if (value is int) {
        return value;
      }
      return int.tryParse('${value ?? 0}') ?? 0;
    }

    bool parseBool(dynamic value) {
      if (value is bool) {
        return value;
      }
      final normalized = '${value ?? ''}'.trim().toLowerCase();
      return normalized == '1' || normalized == 'true' || normalized == 'yes';
    }

    return LivePdReportItem(
      userId: parseInt(json['user_id']),
      name: (json['name'] ?? '-').toString(),
      nis: json['nis']?.toString(),
      nisn: json['nisn']?.toString(),
      status: (json['status'] ?? 'belum_absen').toString(),
      statusLabel: (json['status_label'] ?? 'Belum Absen').toString(),
      checkInTime: json['check_in_time']?.toString(),
      checkOutTime: json['check_out_time']?.toString(),
      expectedCheckInTime: json['expected_check_in_time']?.toString(),
      expectedCheckOutTime: json['expected_check_out_time']?.toString(),
      lateMinutes: parseInt(json['late_minutes']),
      isLate: parseBool(json['is_late']),
      isCheckoutPending: parseBool(json['is_checkout_pending']),
      indicatorKey: (json['indicator_key'] ?? json['status'] ?? 'belum_absen')
          .toString(),
      indicatorLabel:
          (json['indicator_label'] ?? json['status_label'] ?? 'Belum Absen')
              .toString(),
      timeHint: json['time_hint']?.toString(),
      notes: json['notes']?.toString(),
      roleLabel: json['role_label']?.toString(),
      locationLabel: json['location_label']?.toString(),
      userPhotoUrl: json['user_photo_url']?.toString(),
      isSelf: parseBool(json['is_self']),
    );
  }
}

class LivePdReportData {
  final String date;
  final int? classId;
  final String? className;
  final LivePdReportSummary summary;
  final List<LivePdReportItem> items;

  const LivePdReportData({
    required this.date,
    required this.classId,
    required this.className,
    required this.summary,
    required this.items,
  });

  factory LivePdReportData.fromJson(Map<String, dynamic> json) {
    int? parseNullableInt(dynamic value) {
      if (value is int) {
        return value;
      }
      return int.tryParse('${value ?? ''}');
    }

    final summaryMap = json['summary'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['summary'])
        : <String, dynamic>{};
    final itemList = json['items'] is List
        ? List<dynamic>.from(json['items'])
        : const <dynamic>[];

    return LivePdReportData(
      date: (json['date'] ?? '').toString(),
      classId: parseNullableInt(json['class_id']),
      className: json['class_name']?.toString(),
      summary: LivePdReportSummary.fromJson(summaryMap),
      items: itemList
          .whereType<Map>()
          .map((item) => LivePdReportItem.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
    );
  }
}

class LivePdReportService {
  LivePdReportService._();
  static final LivePdReportService _instance = LivePdReportService._();
  factory LivePdReportService() => _instance;

  final ApiService _apiService = ApiService();

  Future<ApiResponse<LivePdReportData>> getTodayReport() async {
    try {
      final response = await _apiService.get('/dashboard/live-class-report');
      final body = response.data is Map<String, dynamic>
          ? Map<String, dynamic>.from(response.data)
          : <String, dynamic>{};
      final success = body['success'] == true;
      final data = body['data'];

      if (success && data is Map<String, dynamic>) {
        return ApiResponse<LivePdReportData>(
          success: true,
          message: (body['message'] ?? 'Laporan PD berhasil diambil').toString(),
          data: LivePdReportData.fromJson(data),
        );
      }

      return ApiResponse<LivePdReportData>(
        success: false,
        message: (body['message'] ?? 'Gagal mengambil laporan PD').toString(),
      );
    } on ApiException catch (e) {
      return ApiResponse<LivePdReportData>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (_) {
      return ApiResponse<LivePdReportData>(
        success: false,
        message: AppStrings.unknownError,
      );
    }
  }
}
