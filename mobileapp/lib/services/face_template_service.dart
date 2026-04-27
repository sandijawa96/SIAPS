import 'dart:io';

import 'package:dio/dio.dart';

import '../models/login_response.dart';
import 'api_service.dart';

class FaceTemplateRecord {
  final int id;
  final String? templateVersion;
  final double? qualityScore;
  final String? templatePath;
  final String? templateUrl;
  final DateTime? enrolledAt;
  final bool isActive;

  const FaceTemplateRecord({
    required this.id,
    required this.templateVersion,
    required this.qualityScore,
    required this.templatePath,
    required this.templateUrl,
    required this.enrolledAt,
    required this.isActive,
  });

  factory FaceTemplateRecord.fromJson(Map<String, dynamic> json) {
    return FaceTemplateRecord(
      id: json['id'] is int ? json['id'] as int : int.tryParse('${json['id']}') ?? 0,
      templateVersion: json['template_version']?.toString(),
      qualityScore: (json['quality_score'] as num?)?.toDouble(),
      templatePath: json['template_path']?.toString(),
      templateUrl: json['template_url']?.toString(),
      enrolledAt: json['enrolled_at'] != null ? DateTime.tryParse(json['enrolled_at'].toString()) : null,
      isActive: json['is_active'] == true,
    );
  }
}

class FaceTemplateSubmissionState {
  final int limit;
  final int selfSubmitCount;
  final int baseQuotaRemaining;
  final int unlockAllowanceRemaining;
  final bool canSelfSubmitNow;
  final bool requiresAdminUnlock;
  final DateTime? lastSubmittedAt;
  final DateTime? lastUnlockedAt;
  final String? lastUnlockedByName;

  const FaceTemplateSubmissionState({
    required this.limit,
    required this.selfSubmitCount,
    required this.baseQuotaRemaining,
    required this.unlockAllowanceRemaining,
    required this.canSelfSubmitNow,
    required this.requiresAdminUnlock,
    required this.lastSubmittedAt,
    required this.lastUnlockedAt,
    required this.lastUnlockedByName,
  });

  factory FaceTemplateSubmissionState.fromJson(Map<String, dynamic> json) {
    return FaceTemplateSubmissionState(
      limit: json['limit'] is int ? json['limit'] as int : int.tryParse('${json['limit']}') ?? 3,
      selfSubmitCount: json['self_submit_count'] is int
          ? json['self_submit_count'] as int
          : int.tryParse('${json['self_submit_count']}') ?? 0,
      baseQuotaRemaining: json['base_quota_remaining'] is int
          ? json['base_quota_remaining'] as int
          : int.tryParse('${json['base_quota_remaining']}') ?? 0,
      unlockAllowanceRemaining: json['unlock_allowance_remaining'] is int
          ? json['unlock_allowance_remaining'] as int
          : int.tryParse('${json['unlock_allowance_remaining']}') ?? 0,
      canSelfSubmitNow: json['can_self_submit_now'] == true,
      requiresAdminUnlock: json['requires_admin_unlock'] == true,
      lastSubmittedAt: json['last_submitted_at'] != null
          ? DateTime.tryParse(json['last_submitted_at'].toString())
          : null,
      lastUnlockedAt: json['last_unlocked_at'] != null
          ? DateTime.tryParse(json['last_unlocked_at'].toString())
          : null,
      lastUnlockedByName: json['last_unlocked_by_name']?.toString(),
    );
  }
}

class FaceTemplateStatusPayload {
  final int userId;
  final String userName;
  final bool hasActiveTemplate;
  final FaceTemplateRecord? activeTemplate;
  final int templatesCount;
  final FaceTemplateSubmissionState submissionState;

  const FaceTemplateStatusPayload({
    required this.userId,
    required this.userName,
    required this.hasActiveTemplate,
    required this.activeTemplate,
    required this.templatesCount,
    required this.submissionState,
  });

  factory FaceTemplateStatusPayload.fromJson(Map<String, dynamic> json) {
    final rawSubmissionState = json['submission_state'] is Map<String, dynamic>
        ? json['submission_state'] as Map<String, dynamic>
        : json['submission_state'] is Map
            ? Map<String, dynamic>.from(json['submission_state'] as Map)
            : <String, dynamic>{};

    return FaceTemplateStatusPayload(
      userId: json['user_id'] is int ? json['user_id'] as int : int.tryParse('${json['user_id']}') ?? 0,
      userName: (json['user_name'] ?? '-').toString(),
      hasActiveTemplate: json['has_active_template'] == true,
      activeTemplate: json['active_template'] is Map<String, dynamic>
          ? FaceTemplateRecord.fromJson(json['active_template'] as Map<String, dynamic>)
          : json['active_template'] is Map
              ? FaceTemplateRecord.fromJson(Map<String, dynamic>.from(json['active_template'] as Map))
              : null,
      templatesCount: json['templates_count'] is int
          ? json['templates_count'] as int
          : int.tryParse('${json['templates_count']}') ?? 0,
      submissionState: FaceTemplateSubmissionState.fromJson(rawSubmissionState),
    );
  }
}

class FaceTemplateService {
  FaceTemplateService._();
  static final FaceTemplateService _instance = FaceTemplateService._();
  factory FaceTemplateService() => _instance;

  final ApiService _apiService = ApiService();

  Future<ApiResponse<FaceTemplateStatusPayload>> getMyStatus() async {
    try {
      final response = await _apiService.get('/face-templates/me');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};

      return ApiResponse<FaceTemplateStatusPayload>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Status template wajah berhasil diambil').toString(),
        data: body['data'] is Map<String, dynamic>
            ? FaceTemplateStatusPayload.fromJson(body['data'] as Map<String, dynamic>)
            : body['data'] is Map
                ? FaceTemplateStatusPayload.fromJson(Map<String, dynamic>.from(body['data'] as Map))
                : null,
      );
    } on ApiException catch (e) {
      return ApiResponse<FaceTemplateStatusPayload>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<FaceTemplateStatusPayload>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<FaceTemplateStatusPayload>> selfSubmit(String filePath) async {
    try {
      final fileName = filePath.split(Platform.pathSeparator).last;
      final formData = FormData.fromMap({
        'foto_file': await MultipartFile.fromFile(filePath, filename: fileName),
      });

      final response = await _apiService.post(
        '/face-templates/self-submit',
        data: formData,
        options: Options(contentType: 'multipart/form-data'),
      );

      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};

      return ApiResponse<FaceTemplateStatusPayload>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Template wajah berhasil dikirim').toString(),
        data: body['data'] is Map<String, dynamic>
            ? FaceTemplateStatusPayload.fromJson(body['data'] as Map<String, dynamic>)
            : body['data'] is Map
                ? FaceTemplateStatusPayload.fromJson(Map<String, dynamic>.from(body['data'] as Map))
                : null,
      );
    } on ApiException catch (e) {
      return ApiResponse<FaceTemplateStatusPayload>(
        success: false,
        message: e.userFriendlyMessage,
        errors: e.data is Map ? Map<String, dynamic>.from(e.data as Map) : null,
      );
    } catch (e) {
      return ApiResponse<FaceTemplateStatusPayload>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }
}
