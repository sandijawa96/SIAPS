import 'dart:io';
import 'package:dio/dio.dart';
import '../models/login_response.dart';
import 'api_service.dart';

class PersonalDataFieldSchema {
  final String key;
  final String label;
  final String type;
  final bool editable;

  const PersonalDataFieldSchema({
    required this.key,
    required this.label,
    required this.type,
    required this.editable,
  });

  factory PersonalDataFieldSchema.fromJson(Map<String, dynamic> json) {
    return PersonalDataFieldSchema(
      key: (json['key'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
      type: (json['type'] ?? 'text').toString(),
      editable: json['editable'] == true,
    );
  }
}

class PersonalDataSectionSchema {
  final String key;
  final String label;
  final List<PersonalDataFieldSchema> fields;

  const PersonalDataSectionSchema({
    required this.key,
    required this.label,
    required this.fields,
  });

  factory PersonalDataSectionSchema.fromJson(Map<String, dynamic> json) {
    final rawFields = json['fields'] is List ? json['fields'] as List<dynamic> : const <dynamic>[];
    return PersonalDataSectionSchema(
      key: (json['key'] ?? '').toString(),
      label: (json['label'] ?? '').toString(),
      fields: rawFields
          .whereType<Map>()
          .map((field) => PersonalDataFieldSchema.fromJson(Map<String, dynamic>.from(field)))
          .toList(),
    );
  }
}

class PersonalDataPayload {
  final String profileType;
  final Map<String, dynamic> common;
  final Map<String, dynamic> detail;
  final Map<String, dynamic>? activeClass;

  const PersonalDataPayload({
    required this.profileType,
    required this.common,
    required this.detail,
    required this.activeClass,
  });

  factory PersonalDataPayload.fromJson(Map<String, dynamic> json) {
    return PersonalDataPayload(
      profileType: (json['profile_type'] ?? '').toString(),
      common: json['common'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['common'])
          : json['common'] is Map
              ? Map<String, dynamic>.from(json['common'])
              : <String, dynamic>{},
      detail: json['detail'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['detail'])
          : json['detail'] is Map
              ? Map<String, dynamic>.from(json['detail'])
              : <String, dynamic>{},
      activeClass: json['active_class'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['active_class'])
          : json['active_class'] is Map
              ? Map<String, dynamic>.from(json['active_class'])
              : null,
    );
  }

  dynamic valueFor(String key) {
    if (key.startsWith('active_class.')) {
      final classKey = key.substring('active_class.'.length);
      return activeClass?[classKey];
    }

    if (common.containsKey(key)) {
      return common[key];
    }

    return detail[key];
  }
}

class PersonalDataAvatarResult {
  final String? photoPath;
  final String? photoUrl;

  const PersonalDataAvatarResult({
    required this.photoPath,
    required this.photoUrl,
  });
}

class PersonalDataService {
  PersonalDataService._();
  static final PersonalDataService _instance = PersonalDataService._();
  factory PersonalDataService() => _instance;

  final ApiService _apiService = ApiService();

  Future<ApiResponse<PersonalDataPayload>> getProfile() async {
    try {
      final response = await _apiService.get('/personal-data');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};

      return ApiResponse<PersonalDataPayload>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Data pribadi berhasil diambil').toString(),
        data: body['data'] is Map<String, dynamic>
            ? PersonalDataPayload.fromJson(body['data'])
            : body['data'] is Map
                ? PersonalDataPayload.fromJson(Map<String, dynamic>.from(body['data']))
                : null,
      );
    } on ApiException catch (e) {
      return ApiResponse<PersonalDataPayload>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<PersonalDataPayload>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<List<PersonalDataSectionSchema>>> getSchema() async {
    try {
      final response = await _apiService.get('/personal-data/schema');
      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final data = body['data'] as Map<String, dynamic>? ?? <String, dynamic>{};
      final rawSections = data['sections'] is List ? data['sections'] as List<dynamic> : const <dynamic>[];

      return ApiResponse<List<PersonalDataSectionSchema>>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Skema data pribadi berhasil diambil').toString(),
        data: rawSections
            .whereType<Map>()
            .map((item) => PersonalDataSectionSchema.fromJson(Map<String, dynamic>.from(item)))
            .toList(),
      );
    } on ApiException catch (e) {
      return ApiResponse<List<PersonalDataSectionSchema>>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<List<PersonalDataSectionSchema>>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }

  Future<ApiResponse<PersonalDataAvatarResult>> updateAvatar(String filePath) async {
    try {
      final fileName = filePath.split(Platform.pathSeparator).last;
      final formData = FormData.fromMap({
        'avatar': await MultipartFile.fromFile(filePath, filename: fileName),
      });

      final response = await _apiService.post(
        '/personal-data/avatar',
        data: formData,
        options: Options(contentType: 'multipart/form-data'),
      );

      final body = response.data as Map<String, dynamic>? ?? <String, dynamic>{};
      final data = body['data'] as Map<String, dynamic>? ?? <String, dynamic>{};

      return ApiResponse<PersonalDataAvatarResult>(
        success: body['success'] == true,
        message: (body['message'] ?? 'Foto profil berhasil diperbarui').toString(),
        data: PersonalDataAvatarResult(
          photoPath: data['foto_profil']?.toString(),
          photoUrl: data['foto_profil_url']?.toString(),
        ),
      );
    } on ApiException catch (e) {
      return ApiResponse<PersonalDataAvatarResult>(success: false, message: e.userFriendlyMessage);
    } catch (e) {
      return ApiResponse<PersonalDataAvatarResult>(success: false, message: 'Terjadi kesalahan: $e');
    }
  }
}
