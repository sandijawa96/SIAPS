import 'dart:async';
import 'dart:io';

import 'package:dio/dio.dart';
import '../models/login_response.dart';
import 'api_service.dart';
import '../utils/constants.dart';

String? _resolveLeaveMediaUrl(dynamic primary, dynamic fallback) {
  final raw = (primary ?? fallback)?.toString().trim();
  if (raw == null || raw.isEmpty) {
    return null;
  }

  if (raw.startsWith('http://') || raw.startsWith('https://')) {
    return raw;
  }

  final apiBase = AppConstants.baseUrl.replaceFirst(RegExp(r'/api/?$'), '');
  var path = raw;

  if (path.startsWith('/api/storage/')) {
    path = path.replaceFirst('/api/storage/', '/storage/');
  } else if (path.startsWith('api/storage/')) {
    path = path.replaceFirst('api/storage/', 'storage/');
  }

  if (path.startsWith('/storage/')) {
    return '$apiBase$path';
  }

  if (path.startsWith('storage/')) {
    return '$apiBase/$path';
  }

  if (path.startsWith('/public/')) {
    path = '/storage/${path.replaceFirst('/public/', '')}';
    return '$apiBase$path';
  }

  if (path.startsWith('public/')) {
    return '$apiBase/storage/${path.replaceFirst('public/', '')}';
  }

  return '$apiBase/storage/${path.replaceFirst(RegExp(r'^/+'), '')}';
}

DateTime? _parseServerDateOnly(dynamic rawValue) {
  final raw = rawValue?.toString().trim();
  if (raw == null || raw.isEmpty) {
    return null;
  }

  // Keep date semantics stable (no timezone shifts) for YYYY-MM-DD fields.
  final dateMatch = RegExp(r'^(\d{4})-(\d{2})-(\d{2})').firstMatch(raw);
  if (dateMatch != null) {
    final year = int.tryParse(dateMatch.group(1)!);
    final month = int.tryParse(dateMatch.group(2)!);
    final day = int.tryParse(dateMatch.group(3)!);

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

DateTime? _parseServerDateTime(dynamic rawValue) {
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

class LeaveItem {
  final int id;
  final int userId;
  final int? kelasId;
  final String jenisIzin;
  final String? jenisIzinLabel;
  final String alasan;
  final String status;
  final String? statusLabel;
  final DateTime? tanggalMulai;
  final DateTime? tanggalSelesai;
  final String? userName;
  final String? kelasNama;
  final String? approvalNotes;
  final String? dokumenPendukung;
  final String? approvedByName;
  final String? rejectedByName;
  final DateTime? createdAt;
  final int requestedDayCount;
  final int schoolDaysAffected;
  final int nonWorkingDaysSkipped;
  final bool evidenceRequired;
  final String? evidenceHint;
  final String? pendingReviewState;
  final String? pendingReviewLabel;
  final bool isPendingOverdue;
  final int pendingOverdueDays;

  const LeaveItem({
    required this.id,
    required this.userId,
    required this.kelasId,
    required this.jenisIzin,
    required this.jenisIzinLabel,
    required this.alasan,
    required this.status,
    required this.statusLabel,
    required this.tanggalMulai,
    required this.tanggalSelesai,
    required this.userName,
    required this.kelasNama,
    required this.approvalNotes,
    required this.dokumenPendukung,
    required this.approvedByName,
    required this.rejectedByName,
    required this.createdAt,
    required this.requestedDayCount,
    required this.schoolDaysAffected,
    required this.nonWorkingDaysSkipped,
    required this.evidenceRequired,
    required this.evidenceHint,
    required this.pendingReviewState,
    required this.pendingReviewLabel,
    required this.isPendingOverdue,
    required this.pendingOverdueDays,
  });

  factory LeaveItem.fromJson(Map<String, dynamic> json) {
    final user = json['user'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['user'])
        : null;
    final kelas = json['kelas'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['kelas'])
        : null;
    final approvedBy = json['approved_by'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['approved_by'])
        : null;
    final rejectedBy = json['rejected_by'] is Map<String, dynamic>
        ? Map<String, dynamic>.from(json['rejected_by'])
        : null;

    return LeaveItem(
      id: json['id'] is int ? json['id'] as int : int.tryParse('${json['id']}') ?? 0,
      userId: json['user_id'] is int
          ? json['user_id'] as int
          : int.tryParse('${json['user_id']}') ?? 0,
      kelasId: json['kelas_id'] is int
          ? json['kelas_id'] as int
          : int.tryParse('${json['kelas_id'] ?? ''}'),
      jenisIzin: (json['jenis_izin'] ?? '-').toString(),
      jenisIzinLabel: (json['jenis_izin_label'] ?? json['jenis_izin'])?.toString(),
      alasan: (json['alasan'] ?? '-').toString(),
      status: (json['status'] ?? 'pending').toString(),
      statusLabel: (json['status_label'] ?? json['status'])?.toString(),
      tanggalMulai: _parseServerDateOnly(json['tanggal_mulai']),
      tanggalSelesai: _parseServerDateOnly(json['tanggal_selesai']),
      userName: (user?['nama_lengkap'] ?? user?['name'])?.toString(),
      kelasNama: kelas?['nama_kelas']?.toString(),
      approvalNotes: (json['catatan_approval'] ?? json['approval_notes'])?.toString(),
      dokumenPendukung: _resolveLeaveMediaUrl(
        json['dokumen_pendukung_url'],
        json['dokumen_pendukung'],
      ),
      approvedByName: approvedBy?['nama_lengkap']?.toString(),
      rejectedByName: rejectedBy?['nama_lengkap']?.toString(),
      createdAt: _parseServerDateTime(json['created_at']),
      requestedDayCount: json['requested_day_count'] is int
          ? json['requested_day_count'] as int
          : int.tryParse('${json['requested_day_count'] ?? json['durasi'] ?? 0}') ?? 0,
      schoolDaysAffected: json['school_days_affected'] is int
          ? json['school_days_affected'] as int
          : int.tryParse('${json['school_days_affected'] ?? json['school_day_count'] ?? 0}') ?? 0,
      nonWorkingDaysSkipped: json['non_working_days_skipped'] is int
          ? json['non_working_days_skipped'] as int
          : int.tryParse('${json['non_working_days_skipped'] ?? json['non_working_day_count'] ?? 0}') ?? 0,
      evidenceRequired: json['evidence_required'] == true,
      evidenceHint: (json['evidence_hint']
              ?? (json['evidence_policy'] is Map ? (json['evidence_policy']['hint']) : null))
          ?.toString(),
      pendingReviewState: json['pending_review_state']?.toString(),
      pendingReviewLabel: json['pending_review_label']?.toString(),
      isPendingOverdue: json['is_pending_overdue'] == true,
      pendingOverdueDays: json['pending_overdue_days'] is int
          ? json['pending_overdue_days'] as int
          : int.tryParse('${json['pending_overdue_days'] ?? 0}') ?? 0,
    );
  }
}

class LeaveListPage {
  final List<LeaveItem> items;
  final int currentPage;
  final int lastPage;
  final int perPage;
  final int total;

  const LeaveListPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.perPage,
    required this.total,
  });

  bool get hasMore => currentPage < lastPage;
}

class LeaveSubmissionPayload {
  final String jenisIzin;
  final String alasan;
  final DateTime tanggalMulai;
  final DateTime tanggalSelesai;
  final String? dokumenPendukungPath;

  const LeaveSubmissionPayload({
    required this.jenisIzin,
    required this.alasan,
    required this.tanggalMulai,
    required this.tanggalSelesai,
    this.dokumenPendukungPath,
  });

  Future<FormData> toFormData({String? clientRequestId}) async {
    final data = <String, dynamic>{
      'jenis_izin': jenisIzin,
      'tanggal_mulai': _formatDateForRequest(tanggalMulai),
      'tanggal_selesai': _formatDateForRequest(tanggalSelesai),
      'alasan': alasan.trim(),
      if (clientRequestId != null && clientRequestId.trim().isNotEmpty)
        'client_request_id': clientRequestId.trim(),
    };

    final documentPath = dokumenPendukungPath?.trim();
    if (documentPath != null && documentPath.isNotEmpty) {
      final filename = documentPath.split(Platform.pathSeparator).last;
      data['dokumen_pendukung'] = await MultipartFile.fromFile(
        documentPath,
        filename: filename,
      );
    }

    return FormData.fromMap(data);
  }
}

class LeaveService {
  LeaveService._();
  static final LeaveService _instance = LeaveService._();
  factory LeaveService() => _instance;

  final ApiService _apiService = ApiService();

  Future<ApiResponse<void>> submit(
    LeaveSubmissionPayload payload, {
    String? clientRequestId,
  }) async {
    try {
      final response = await _apiService.post(
        '/izin',
        data: await payload.toFormData(clientRequestId: clientRequestId),
        options: Options(contentType: 'multipart/form-data'),
      );
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      return ApiResponse<void>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Pengajuan izin berhasil dikirim dan sedang ditinjau').toString(),
      );
    } on ApiException catch (e) {
      if (_isTimeoutException(e)) {
        final recovered = await _recoverTimedOutSubmission(payload);
        if (recovered) {
          return ApiResponse<void>(
            success: true,
            message: 'Pengajuan izin sudah tersimpan dan sedang ditinjau. Respons server terlambat, data disinkronkan dari riwayat.',
          );
        }
      }

      return ApiResponse<void>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<void>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<LeaveListPage>> getOwnLeavesPage({
    String? status,
    int page = 1,
    int perPage = 30,
  }) async {
    try {
      final response = await _apiService.get(
        '/izin',
        queryParameters: {
          if (status != null && status.isNotEmpty) 'status': status,
          'page': '$page',
          'per_page': '$perPage',
        },
      );
      return _parseLeavePageResponse(
        response.data,
        fallbackMessage: 'Riwayat izin berhasil diambil',
      );
    } on ApiException catch (e) {
      return ApiResponse<LeaveListPage>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<LeaveListPage>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<List<LeaveItem>>> getOwnLeaves({
    String? status,
  }) async {
    final response = await getOwnLeavesPage(status: status);
    return ApiResponse<List<LeaveItem>>(
      success: response.success,
      message: response.message,
      data: response.data?.items ?? const <LeaveItem>[],
      errors: response.errors,
    );
  }

  Future<ApiResponse<LeaveListPage>> getStudentApprovalQueuePage({
    String status = 'pending',
    int page = 1,
    int perPage = 30,
  }) async {
    try {
      final response = await _apiService.get(
        '/izin/approval/list',
        queryParameters: {
          'type': 'siswa',
          'status': status,
          'page': '$page',
          'per_page': '$perPage',
        },
      );
      return _parseLeavePageResponse(
        response.data,
        fallbackMessage: 'Daftar review izin berhasil diambil',
      );
    } on ApiException catch (e) {
      return ApiResponse<LeaveListPage>(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return ApiResponse<LeaveListPage>(
        success: false,
        message: 'Terjadi kesalahan: $e',
      );
    }
  }

  Future<ApiResponse<List<LeaveItem>>> getStudentApprovalQueue({
    String status = 'pending',
  }) async {
    final response = await getStudentApprovalQueuePage(status: status);
    return ApiResponse<List<LeaveItem>>(
      success: response.success,
      message: response.message,
      data: response.data?.items ?? const <LeaveItem>[],
      errors: response.errors,
    );
  }

  Future<ApiResponse<LeaveItem>> getById(int id) async {
    try {
      final response = await _apiService.get('/izin/$id');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final raw = body['data'];

      return ApiResponse<LeaveItem>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Detail izin berhasil diambil').toString(),
        data: raw is Map<String, dynamic>
            ? LeaveItem.fromJson(raw)
            : raw is Map
                ? LeaveItem.fromJson(Map<String, dynamic>.from(raw))
                : null,
      );
    } on ApiException catch (e) {
      return ApiResponse<LeaveItem>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<LeaveItem>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<void>> approve(int id, {String? note}) async {
    try {
      final response = await _apiService.post(
        '/izin/$id/approve',
        data: {
          if (note != null && note.trim().isNotEmpty) 'catatan_approval': note.trim(),
        },
      );
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      return ApiResponse<void>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Pengajuan izin disetujui').toString(),
      );
    } on ApiException catch (e) {
      if (_isAlreadyProcessedIzinDecision(e)) {
        return ApiResponse<void>(
          success: true,
          message: _buildAlreadyProcessedMessage(
            fallback: 'Pengajuan ini sudah diproses oleh petugas lain. Data diperbarui.',
            errorData: e.data,
          ),
        );
      }

      if (_isTimeoutException(e)) {
        final recovered = await _recoverTimedOutDecision(id, expectedStatus: 'approved');
        if (recovered) {
          return ApiResponse<void>(
            success: true,
            message: 'Pengajuan izin sudah disetujui. Respons server terlambat, status diperbarui dari detail izin.',
          );
        }
      }

      return ApiResponse<void>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<void>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<void>> reject(int id, {required String note}) async {
    try {
      final response = await _apiService.post(
        '/izin/$id/reject',
        data: {'catatan_approval': note.trim()},
      );
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      return ApiResponse<void>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Pengajuan izin ditolak').toString(),
      );
    } on ApiException catch (e) {
      if (_isAlreadyProcessedIzinDecision(e)) {
        return ApiResponse<void>(
          success: true,
          message: _buildAlreadyProcessedMessage(
            fallback: 'Pengajuan ini sudah diproses oleh petugas lain. Data diperbarui.',
            errorData: e.data,
          ),
        );
      }

      if (_isTimeoutException(e)) {
        final recovered = await _recoverTimedOutDecision(id, expectedStatus: 'rejected');
        if (recovered) {
          return ApiResponse<void>(
            success: true,
            message: 'Pengajuan izin sudah ditolak. Respons server terlambat, status diperbarui dari detail izin.',
          );
        }
      }

      return ApiResponse<void>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<void>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<void>> cancel(int id) async {
    try {
      final response = await _apiService.delete('/izin/$id');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      return ApiResponse<void>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Pengajuan izin berhasil dibatalkan').toString(),
      );
    } on ApiException catch (e) {
      return ApiResponse<void>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<void>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  ApiResponse<LeaveListPage> _parseLeavePageResponse(
    dynamic rawResponse, {
    required String fallbackMessage,
  }) {
    final body = rawResponse as Map<String, dynamic>? ?? <String, dynamic>{};
    final raw = body['data'];
    final List<dynamic> rows;
    int currentPage = 1;
    int lastPage = 1;
    int perPage = 30;
    int total = 0;

    if (raw is List) {
      rows = raw;
      total = rows.length;
      perPage = rows.length == 0 ? 30 : rows.length;
    } else if (raw is Map<String, dynamic>) {
      rows = raw['data'] is List ? raw['data'] as List<dynamic> : const <dynamic>[];
      currentPage = raw['current_page'] is int
          ? raw['current_page'] as int
          : int.tryParse('${raw['current_page'] ?? 1}') ?? 1;
      lastPage = raw['last_page'] is int
          ? raw['last_page'] as int
          : int.tryParse('${raw['last_page'] ?? currentPage}') ?? currentPage;
      perPage = raw['per_page'] is int
          ? raw['per_page'] as int
          : int.tryParse('${raw['per_page'] ?? rows.length}') ??
              (rows.isEmpty ? 30 : rows.length);
      total = raw['total'] is int
          ? raw['total'] as int
          : int.tryParse('${raw['total'] ?? rows.length}') ?? rows.length;
    } else {
      rows = const <dynamic>[];
    }

    final items = rows
        .whereType<Map>()
        .map((item) => LeaveItem.fromJson(Map<String, dynamic>.from(item)))
        .toList();

    return ApiResponse<LeaveListPage>(
      success: body['success'] == true,
      message: (body['message'] ?? fallbackMessage).toString(),
      data: LeaveListPage(
        items: items,
        currentPage: currentPage,
        lastPage: lastPage < 1 ? 1 : lastPage,
        perPage: perPage < 1 ? 30 : perPage,
        total: total < items.length ? items.length : total,
      ),
    );
  }

  bool _isAlreadyProcessedIzinDecision(ApiException exception) {
    if (exception.statusCode != 422 || exception.data is! Map) {
      return false;
    }

    final payload = Map<String, dynamic>.from(exception.data as Map);
    final data = payload['data'];
    if (data is! Map) {
      return false;
    }

    final detail = Map<String, dynamic>.from(data);
    final status = (detail['current_status'] ?? '').toString().trim().toLowerCase();
    return status == 'approved' || status == 'rejected';
  }

  String _buildAlreadyProcessedMessage({
    required String fallback,
    dynamic errorData,
  }) {
    if (errorData is! Map) {
      return fallback;
    }

    final payload = Map<String, dynamic>.from(errorData);
    final detailRaw = payload['data'];
    final detail = detailRaw is Map ? Map<String, dynamic>.from(detailRaw) : <String, dynamic>{};
    final status = (detail['current_status'] ?? '').toString().trim().toLowerCase();
    final statusLabel = (detail['current_status_label'] ?? '').toString().trim();

    if (status == 'approved') {
      return statusLabel.isNotEmpty
          ? 'Pengajuan ini sudah berstatus $statusLabel. Daftar review diperbarui.'
          : 'Pengajuan ini sudah disetujui oleh petugas lain. Daftar review diperbarui.';
    }

    if (status == 'rejected') {
      return statusLabel.isNotEmpty
          ? 'Pengajuan ini sudah berstatus $statusLabel. Daftar review diperbarui.'
          : 'Pengajuan ini sudah ditolak oleh petugas lain. Daftar review diperbarui.';
    }

    return fallback;
  }

  bool _isTimeoutException(ApiException exception) {
    return exception.type == ApiExceptionType.timeout;
  }

  Future<bool> _recoverTimedOutDecision(
    int id, {
    required String expectedStatus,
  }) async {
    await Future<void>.delayed(const Duration(milliseconds: 900));

    final detail = await getById(id);
    final item = detail.data;
    if (!detail.success || item == null) {
      return false;
    }

    return item.status.trim().toLowerCase() == expectedStatus.trim().toLowerCase();
  }

  Future<bool> _recoverTimedOutSubmission(LeaveSubmissionPayload payload) async {
    await Future<void>.delayed(const Duration(milliseconds: 900));

    final response = await getOwnLeaves();
    final rows = response.data ?? const <LeaveItem>[];
    final now = DateTime.now();

    return rows.any((item) {
      if (!_sameDate(item.tanggalMulai, payload.tanggalMulai) ||
          !_sameDate(item.tanggalSelesai, payload.tanggalSelesai)) {
        return false;
      }

      if (item.jenisIzin.trim().toLowerCase() != payload.jenisIzin.trim().toLowerCase()) {
        return false;
      }

      if (item.alasan.trim() != payload.alasan.trim()) {
        return false;
      }

      final createdAt = item.createdAt;
      if (createdAt == null) {
        return true;
      }

      final age = now.difference(createdAt).abs();
      return age.inMinutes <= 15;
    });
  }

  bool _sameDate(DateTime? left, DateTime? right) {
    if (left == null || right == null) {
      return false;
    }

    return left.year == right.year &&
        left.month == right.month &&
        left.day == right.day;
  }

}

String _formatDateForRequest(DateTime date) {
  final year = date.year.toString().padLeft(4, '0');
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '$year-$month-$day';
}
