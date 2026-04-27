import '../models/login_response.dart';
import 'api_service.dart';

DateTime? _parseDateOnly(dynamic rawValue) {
  final raw = rawValue?.toString().trim();
  if (raw == null || raw.isEmpty) {
    return null;
  }

  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(raw);
  if (match != null) {
    final year = int.tryParse(match.group(1)!);
    final month = int.tryParse(match.group(2)!);
    final day = int.tryParse(match.group(3)!);
    if (year != null && month != null && day != null) {
      return DateTime(year, month, day);
    }
  }

  final parsed = DateTime.tryParse(raw);
  if (parsed == null) {
    return null;
  }

  return parsed.isUtc ? parsed.toLocal() : parsed;
}

DateTime? _parseDateTime(dynamic rawValue) {
  final raw = rawValue?.toString().trim();
  if (raw == null || raw.isEmpty) {
    return null;
  }

  final parsed = DateTime.tryParse(raw);
  if (parsed == null) {
    return null;
  }

  return parsed.isUtc ? parsed.toLocal() : parsed;
}

String? _normalizeTime(dynamic rawValue) {
  final raw = rawValue?.toString().trim();
  if (raw == null || raw.isEmpty) {
    return null;
  }

  final match = RegExp(r'^(\d{1,2}):(\d{2})').firstMatch(raw);
  if (match == null) {
    return raw;
  }

  final hour = int.tryParse(match.group(1)!);
  final minute = int.tryParse(match.group(2)!);
  if (hour == null || minute == null) {
    return raw;
  }

  return '${hour.toString().padLeft(2, '0')}:${minute.toString().padLeft(2, '0')}';
}

String _formatDateForRequest(DateTime value) {
  return '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';
}

int _parseInt(dynamic rawValue, {int fallback = 0}) {
  if (rawValue is int) {
    return rawValue;
  }

  return int.tryParse('${rawValue ?? ''}') ?? fallback;
}

bool _parseBool(dynamic rawValue) {
  if (rawValue is bool) {
    return rawValue;
  }
  if (rawValue is int) {
    return rawValue == 1;
  }

  final raw = rawValue?.toString().trim().toLowerCase();
  return raw == '1' || raw == 'true';
}

class ManualAttendanceManageableUser {
  final int id;
  final String name;
  final String? identifier;
  final String? className;
  final String? email;

  const ManualAttendanceManageableUser({
    required this.id,
    required this.name,
    required this.identifier,
    required this.className,
    required this.email,
  });

  factory ManualAttendanceManageableUser.fromJson(Map<String, dynamic> json) {
    return ManualAttendanceManageableUser(
      id: _parseInt(json['id']),
      name: (json['nama_lengkap'] ?? json['name'] ?? '-').toString(),
      identifier: (json['identifier'] ??
              json['nis'] ??
              json['nisn'] ??
              json['nip'] ??
              json['email'] ??
              json['username'])
          ?.toString(),
      className: json['kelas_nama']?.toString(),
      email: json['email']?.toString(),
    );
  }
}

class ManualAttendanceEntry {
  final int id;
  final int userId;
  final String? userName;
  final String? userIdentifier;
  final String? className;
  final DateTime? date;
  final String status;
  final String? note;
  final String? checkInTime;
  final String? checkOutTime;
  final bool isManual;
  final String? attendanceMethod;
  final int? leaveId;
  final String source;
  final String sourceLabel;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  const ManualAttendanceEntry({
    required this.id,
    required this.userId,
    required this.userName,
    required this.userIdentifier,
    required this.className,
    required this.date,
    required this.status,
    required this.note,
    required this.checkInTime,
    required this.checkOutTime,
    required this.isManual,
    required this.attendanceMethod,
    required this.leaveId,
    required this.source,
    required this.sourceLabel,
    required this.createdAt,
    required this.updatedAt,
  });

  factory ManualAttendanceEntry.fromJson(Map<String, dynamic> json) {
    final user = json['user'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['user'])
        : (json['user'] is Map ? Map<String, dynamic>.from(json['user']) : null);
    final kelas = json['kelas'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['kelas'])
        : (json['kelas'] is Map ? Map<String, dynamic>.from(json['kelas']) : null);

    final isManual = _parseBool(json['is_manual']);
    final leaveId = json['izin_id'] == null ? null : _parseInt(json['izin_id']);
    final status = (json['status'] ?? '-').toString();
    final normalizedStatus = status.trim().toLowerCase();

    String source = 'realtime';
    String sourceLabel = 'Realtime';

    if (leaveId != null) {
      source = 'leave_approval';
      sourceLabel = 'Approval Izin';
    } else if (!isManual && (normalizedStatus == 'alpha' || normalizedStatus == 'alpa')) {
      source = 'auto_alpha';
      sourceLabel = 'Auto Alpha';
    } else if (isManual) {
      source = 'manual';
      sourceLabel = 'Manual';
    }

    return ManualAttendanceEntry(
      id: _parseInt(json['id']),
      userId: _parseInt(json['user_id']),
      userName: (user?['nama_lengkap'] ?? user?['name'])?.toString(),
      userIdentifier: (user?['nis'] ?? user?['nisn'] ?? user?['nip'] ?? user?['username'] ?? user?['email'])?.toString(),
      className: kelas?['nama_kelas']?.toString(),
      date: _parseDateOnly(json['tanggal']),
      status: status,
      note: json['keterangan']?.toString(),
      checkInTime: _normalizeTime(json['jam_masuk']),
      checkOutTime: _normalizeTime(json['jam_pulang']),
      isManual: isManual,
      attendanceMethod: json['metode_absensi']?.toString(),
      leaveId: leaveId,
      source: source,
      sourceLabel: sourceLabel,
      createdAt: _parseDateTime(json['created_at']),
      updatedAt: _parseDateTime(json['updated_at']),
    );
  }

  String get statusLabel {
    switch (status.trim().toLowerCase()) {
      case 'hadir':
        return 'Hadir';
      case 'terlambat':
        return 'Terlambat';
      case 'izin':
        return 'Izin';
      case 'sakit':
        return 'Sakit';
      case 'alpha':
      case 'alpa':
        return 'Alpha';
      default:
        return status;
    }
  }
}

class ManualAttendancePage {
  final List<ManualAttendanceEntry> items;
  final int currentPage;
  final int lastPage;
  final int total;
  final int perPage;

  const ManualAttendancePage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
    required this.perPage,
  });

  bool get hasMore => currentPage < lastPage;
}

class ManualAttendanceDuplicateCheck {
  final bool isDuplicate;
  final int? attendanceId;

  const ManualAttendanceDuplicateCheck({
    required this.isDuplicate,
    required this.attendanceId,
  });
}

class ManualAttendanceMobileSummary {
  final int manageableStudentsCount;
  final int correctionTodayCount;
  final int manualTodayCount;
  final int pendingCheckoutHPlusOneCount;
  final int pendingCheckoutOverdueCount;
  final bool canOverrideBackdate;
  final DateTime? generatedAt;

  const ManualAttendanceMobileSummary({
    required this.manageableStudentsCount,
    required this.correctionTodayCount,
    required this.manualTodayCount,
    required this.pendingCheckoutHPlusOneCount,
    required this.pendingCheckoutOverdueCount,
    required this.canOverrideBackdate,
    required this.generatedAt,
  });

  factory ManualAttendanceMobileSummary.fromJson(Map<String, dynamic> json) {
    return ManualAttendanceMobileSummary(
      manageableStudentsCount: _parseInt(json['manageable_students_count']),
      correctionTodayCount: _parseInt(json['correction_today_count']),
      manualTodayCount: _parseInt(json['manual_today_count']),
      pendingCheckoutHPlusOneCount:
          _parseInt(json['pending_checkout_h_plus_one_count']),
      pendingCheckoutOverdueCount:
          _parseInt(json['pending_checkout_overdue_count']),
      canOverrideBackdate: _parseBool(json['can_override_backdate']),
      generatedAt: _parseDateTime(json['generated_at']),
    );
  }
}

class ManualAttendanceSubmissionPayload {
  final int userId;
  final DateTime date;
  final String status;
  final String reason;
  final String? checkInTime;
  final String? checkOutTime;
  final String? note;

  const ManualAttendanceSubmissionPayload({
    required this.userId,
    required this.date,
    required this.status,
    required this.reason,
    this.checkInTime,
    this.checkOutTime,
    this.note,
  });

  Map<String, dynamic> toJson() {
    return {
      'user_id': userId,
      'tanggal': _formatDateForRequest(date),
      'status': status,
      'reason': reason.trim(),
      if (checkInTime != null && checkInTime!.trim().isNotEmpty)
        'jam_masuk': checkInTime!.trim(),
      if (checkOutTime != null && checkOutTime!.trim().isNotEmpty)
        'jam_pulang': checkOutTime!.trim(),
      if (note != null && note!.trim().isNotEmpty) 'keterangan': note!.trim(),
    };
  }
}

class PendingCheckoutResolutionPayload {
  final String checkOutTime;
  final String reason;
  final String? overrideReason;
  final String? status;
  final String? note;

  const PendingCheckoutResolutionPayload({
    required this.checkOutTime,
    required this.reason,
    this.overrideReason,
    this.status,
    this.note,
  });

  Map<String, dynamic> toJson() {
    return {
      'jam_pulang': checkOutTime.trim(),
      'reason': reason.trim(),
      if (overrideReason != null && overrideReason!.trim().isNotEmpty)
        'override_reason': overrideReason!.trim(),
      if (status != null && status!.trim().isNotEmpty) 'status': status!.trim(),
      if (note != null && note!.trim().isNotEmpty) 'keterangan': note!.trim(),
    };
  }
}

class ManualAttendanceService {
  ManualAttendanceService._();
  static final ManualAttendanceService _instance =
      ManualAttendanceService._();
  factory ManualAttendanceService() => _instance;

  final ApiService _apiService = ApiService();

  Future<ApiResponse<List<ManualAttendanceManageableUser>>> searchUsers(
    String query, {
    int limit = 20,
  }) async {
    try {
      final response = await _apiService.get(
        '/manual-attendance/users/search',
        queryParameters: {
          'q': query,
          'limit': '$limit',
        },
      );

      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final rawItems = body['data'] is List ? body['data'] as List<dynamic> : const <dynamic>[];

      return ApiResponse<List<ManualAttendanceManageableUser>>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Data siswa berhasil diambil').toString(),
        data: rawItems
            .whereType<Map>()
            .map((item) => ManualAttendanceManageableUser.fromJson(
                Map<String, dynamic>.from(item)))
            .toList(),
      );
    } on ApiException catch (e) {
      return ApiResponse<List<ManualAttendanceManageableUser>>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<List<ManualAttendanceManageableUser>>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<ManualAttendanceMobileSummary>> getMobileSummary() async {
    try {
      final response = await _apiService.get('/manual-attendance/mobile-summary');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final raw = body['data'] is Map<String, dynamic>
          ? body['data'] as Map<String, dynamic>
          : body['data'] is Map
              ? Map<String, dynamic>.from(body['data'])
              : const <String, dynamic>{};

      return ApiResponse<ManualAttendanceMobileSummary>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Ringkasan pengelolaan absensi berhasil diambil').toString(),
        data: ManualAttendanceMobileSummary.fromJson(raw),
      );
    } on ApiException catch (e) {
      return ApiResponse<ManualAttendanceMobileSummary>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<ManualAttendanceMobileSummary>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<ManualAttendancePage>> getCorrectionPage({
    required DateTime date,
    String search = '',
    String? status,
    int page = 1,
    int perPage = 15,
  }) async {
    return _fetchAttendancePage(
      '/manual-attendance/history',
      queryParameters: {
        'bucket': 'correction',
        'date': _formatDateForRequest(date),
        if (search.trim().isNotEmpty) 'search': search.trim(),
        if (status != null && status.trim().isNotEmpty) 'status': status.trim(),
        'page': '$page',
        'per_page': '$perPage',
      },
      fallbackMessage: 'Data koreksi absensi berhasil diambil',
    );
  }

  Future<ApiResponse<ManualAttendancePage>> getPendingCheckoutPage({
    int page = 1,
    int perPage = 15,
    bool includeOverdue = false,
  }) async {
    return _fetchAttendancePage(
      '/manual-attendance/pending-checkout',
      queryParameters: {
        'page': '$page',
        'per_page': '$perPage',
        if (includeOverdue) 'include_overdue': '1',
      },
      fallbackMessage: 'Daftar lupa tap-out berhasil diambil',
    );
  }

  Future<ApiResponse<ManualAttendanceEntry?>> findAttendanceByUserAndDate(
    int userId,
    DateTime date,
  ) async {
    try {
      final formattedDate = _formatDateForRequest(date);
      final response = await _apiService.get(
        '/manual-attendance/date-range',
        queryParameters: {
          'user_id': '$userId',
          'start_date': formattedDate,
          'end_date': formattedDate,
        },
      );

      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final rawItems = body['data'] is List ? body['data'] as List<dynamic> : const <dynamic>[];
      final items = rawItems
          .whereType<Map>()
          .map((row) => ManualAttendanceEntry.fromJson(Map<String, dynamic>.from(row)))
          .toList();
      final item = items.isEmpty ? null : items.first;

      return ApiResponse<ManualAttendanceEntry?>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Data absensi berhasil diambil').toString(),
        data: item,
      );
    } on ApiException catch (e) {
      return ApiResponse<ManualAttendanceEntry?>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<ManualAttendanceEntry?>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<ManualAttendanceDuplicateCheck>> checkDuplicate(
    int userId,
    DateTime date,
  ) async {
    try {
      final response = await _apiService.post(
        '/manual-attendance/check-duplicate',
        data: {
          'user_id': userId,
          'tanggal': _formatDateForRequest(date),
        },
      );

      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final data = body['data'] is Map<String, dynamic>
          ? body['data'] as Map<String, dynamic>
          : body['data'] is Map
              ? Map<String, dynamic>.from(body['data'])
              : const <String, dynamic>{};

      return ApiResponse<ManualAttendanceDuplicateCheck>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Status duplikasi berhasil diperiksa').toString(),
        data: ManualAttendanceDuplicateCheck(
          isDuplicate: _parseBool(data['is_duplicate']),
          attendanceId: data['attendance_id'] == null
              ? null
              : _parseInt(data['attendance_id']),
        ),
      );
    } on ApiException catch (e) {
      return ApiResponse<ManualAttendanceDuplicateCheck>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<ManualAttendanceDuplicateCheck>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<ManualAttendanceEntry>> createManualAttendance(
    ManualAttendanceSubmissionPayload payload,
  ) async {
    return _submitAttendance(
      '/manual-attendance/create',
      payload.toJson(),
      successMessageFallback: 'Absensi manual berhasil dibuat',
    );
  }

  Future<ApiResponse<ManualAttendanceEntry>> updateManualAttendance(
    int attendanceId,
    ManualAttendanceSubmissionPayload payload,
  ) async {
    final data = payload.toJson()..remove('user_id')..remove('tanggal');
    return _submitAttendance(
      '/manual-attendance/$attendanceId',
      data,
      method: 'put',
      successMessageFallback: 'Koreksi absensi berhasil disimpan',
    );
  }

  Future<ApiResponse<ManualAttendanceEntry>> resolveCheckout(
    int attendanceId,
    PendingCheckoutResolutionPayload payload,
  ) async {
    return _submitAttendance(
      '/manual-attendance/$attendanceId/resolve-checkout',
      payload.toJson(),
      successMessageFallback: 'Lupa tap-out berhasil diperbaiki',
    );
  }

  Future<ApiResponse<ManualAttendancePage>> _fetchAttendancePage(
    String path, {
    required Map<String, dynamic> queryParameters,
    required String fallbackMessage,
  }) async {
    try {
      final response = await _apiService.get(
        path,
        queryParameters: queryParameters,
      );

      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final page = _parseAttendancePage(body['data']);

      return ApiResponse<ManualAttendancePage>(
        success: body['success'] == true,
        message: (body['message'] ?? fallbackMessage).toString(),
        data: page,
      );
    } on ApiException catch (e) {
      return ApiResponse<ManualAttendancePage>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<ManualAttendancePage>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  ManualAttendancePage _parseAttendancePage(dynamic rawData) {
    final source = rawData is Map<String, dynamic>
        ? rawData
        : rawData is Map
            ? Map<String, dynamic>.from(rawData)
            : const <String, dynamic>{};
    final itemsRaw = source['data'] is List ? source['data'] as List<dynamic> : const <dynamic>[];

    return ManualAttendancePage(
      items: itemsRaw
          .whereType<Map>()
          .map((item) => ManualAttendanceEntry.fromJson(Map<String, dynamic>.from(item)))
          .toList(),
      currentPage: _parseInt(source['current_page'], fallback: 1),
      lastPage: _parseInt(source['last_page'], fallback: 1),
      total: _parseInt(source['total']),
      perPage: _parseInt(source['per_page'], fallback: itemsRaw.length),
    );
  }

  Future<ApiResponse<ManualAttendanceEntry>> _submitAttendance(
    String path,
    Map<String, dynamic> payload, {
    String method = 'post',
    required String successMessageFallback,
  }) async {
    try {
      final response = method == 'put'
          ? await _apiService.put(path, data: payload)
          : await _apiService.post(path, data: payload);

      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final rawItem = body['data'];

      return ApiResponse<ManualAttendanceEntry>(
        success: body['success'] == true,
        message: (body['message'] ?? successMessageFallback).toString(),
        data: rawItem is Map<String, dynamic>
            ? ManualAttendanceEntry.fromJson(rawItem)
            : rawItem is Map
                ? ManualAttendanceEntry.fromJson(Map<String, dynamic>.from(rawItem))
                : null,
        errors: body['errors'] is Map<String, dynamic>
            ? body['errors'] as Map<String, dynamic>
            : body['errors'] is Map
                ? Map<String, dynamic>.from(body['errors'])
                : null,
      );
    } on ApiException catch (e) {
      return ApiResponse<ManualAttendanceEntry>(
        success: false,
        message: e.userFriendlyMessage,
        errors: e.errors,
      );
    } catch (e) {
      return ApiResponse<ManualAttendanceEntry>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }
}
