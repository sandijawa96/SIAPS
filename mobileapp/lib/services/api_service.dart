import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../utils/constants.dart';
import 'network_service.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  final NetworkService _networkService = NetworkService();
  final FlutterSecureStorage _storage = const FlutterSecureStorage();
  String? _cachedToken;
  bool _isInitialized = false;

  void initialize() {
    if (_isInitialized) {
      return;
    }

    _isInitialized = true;
    _networkService.initialize();

    // Add request interceptor for authentication
    _networkService.dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = _cachedToken ?? await getToken();
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }

          options.headers.putIfAbsent('X-Client-Platform', () => 'mobile');
          options.headers.putIfAbsent('X-Client-App', () => 'mobileapp');

          // Log request for debugging
          print('REQUEST: ${options.method} ${options.path}');
          print('Headers: ${_sanitizeHeadersForLog(options.headers)}');
          if (options.data != null) {
            print('Data: ${_sanitizeDataForLog(options.data)}');
          }

          handler.next(options);
        },
        onResponse: (response, handler) {
          // Log response for debugging
          print(
              'RESPONSE: ${response.statusCode} ${response.requestOptions.path}');
          print('Response Data: ${_sanitizeDataForLog(response.data)}');

          handler.next(response);
        },
        onError: (error, handler) async {
          // Log error for debugging
          print(
              'ERROR: ${error.response?.statusCode} ${error.requestOptions.path}');
          print('Error Data: ${_sanitizeDataForLog(error.response?.data)}');

          // Do not clear local auth state automatically on every 401.
          // A single unauthorized response should not put the app into a
          // half-authenticated state where UI still thinks the user is logged in
          // but every subsequent request loses its bearer token.
          if (error.response?.statusCode == 401) {
            print(
              'Unauthorized response received. Token retained until explicit logout or refresh handling.',
            );
          }

          handler.next(error);
        },
      ),
    );
  }

  Map<String, dynamic> _sanitizeHeadersForLog(Map<String, dynamic> headers) {
    final sanitized = <String, dynamic>{};
    headers.forEach((key, value) {
      sanitized[key] = _isSensitiveKey(key) ? '[omitted]' : value;
    });
    return sanitized;
  }

  dynamic _sanitizeDataForLog(dynamic data) {
    if (data == null) {
      return null;
    }

    if (data is FormData) {
      final result = <String, dynamic>{};

      for (final field in data.fields) {
        if (_isSensitiveKey(field.key)) {
          result[field.key] = '[omitted]';
        } else {
          result[field.key] = _truncateIfNeeded(field.value);
        }
      }

      for (final fileEntry in data.files) {
        final key = fileEntry.key;
        if (_isSensitiveKey(key)) {
          result[key] = '[multipart file omitted]';
        } else {
          final file = fileEntry.value;
          result[key] =
              '[multipart file: ${file.filename ?? 'unknown'}, ${file.length} bytes]';
        }
      }

      return result;
    }

    if (data is Map) {
      final result = <String, dynamic>{};
      data.forEach((key, value) {
        final keyText = key.toString();
        if (_isSensitiveKey(keyText)) {
          result[keyText] = '[omitted]';
        } else {
          result[keyText] = _sanitizeDataForLog(value);
        }
      });
      return result;
    }

    if (data is List) {
      return data.map(_sanitizeDataForLog).toList();
    }

    if (data is String) {
      return _truncateIfNeeded(data);
    }

    return data;
  }

  bool _isSensitiveKey(String key) {
    final normalized = key.toLowerCase().trim();
    return normalized.contains('authorization') ||
        normalized.contains('token') ||
        normalized.contains('password') ||
        normalized == 'foto' ||
        normalized == 'foto_file';
  }

  String _truncateIfNeeded(String value, {int maxLength = 240}) {
    if (value.length <= maxLength) {
      return value;
    }

    return '${value.substring(0, maxLength)}...(len=${value.length})';
  }

  Dio get dio => _networkService.dio;

  // Network service methods
  Future<String> findBestUrl() => _networkService.findBestUrl();
  String get currentBaseUrl => _networkService.currentBaseUrl;
  bool get isUsingFallback => _networkService.isUsingFallback;
  List<String> get availableUrls => _networkService.availableUrls;
  Map<String, dynamic> get connectionInfo =>
      _networkService.getConnectionInfo();

  void switchToUrl(String url) => _networkService.switchToUrl(url);
  void resetToPrimaryUrl() => _networkService.resetToPrimaryUrl();

  // Token management
  Future<String?> getToken() async {
    if (_cachedToken != null && _cachedToken!.isNotEmpty) {
      return _cachedToken;
    }

    try {
      final token = await _storage.read(key: AppConstants.tokenKey);
      if (token != null && token.isNotEmpty) {
        _cachedToken = token;
      }
      return token;
    } catch (e) {
      print('Error reading token: $e');
      return null;
    }
  }

  Future<void> setToken(String token) async {
    try {
      _cachedToken = token;
      await _storage.write(key: AppConstants.tokenKey, value: token);
    } catch (e) {
      print('Error saving token: $e');
    }
  }

  Future<void> clearToken() async {
    try {
      _cachedToken = null;
      await _storage.delete(key: AppConstants.tokenKey);
      await _storage.delete(key: AppConstants.userKey);
      await _storage.delete(key: AppConstants.authTypeKey);
    } catch (e) {
      print('Error clearing token: $e');
    }
  }

  /// Update token (for auto refresh)
  void updateToken(String newToken) {
    _cachedToken = newToken;
    setToken(newToken);
    print('Token updated in ApiService');
  }

  // Generic API methods with automatic fallback
  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _networkService.dio.get<T>(
        path,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  Future<Response<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _networkService.dio.post<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  Future<Response<T>> put<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _networkService.dio.put<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  Future<Response<T>> delete<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    try {
      return await _networkService.dio.delete<T>(
        path,
        data: data,
        queryParameters: queryParameters,
        options: options,
      );
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  // Test connection to all available URLs
  Future<Map<String, bool>> testAllUrls() async {
    final results = <String, bool>{};

    for (final url in availableUrls) {
      try {
        final testDio = Dio(
          BaseOptions(
            baseUrl: url,
            connectTimeout: const Duration(milliseconds: 5000),
            receiveTimeout: const Duration(milliseconds: 5000),
          ),
        );

        await testDio.get(
          '/profile',
          options: Options(
            headers: {'Accept': 'application/json'},
            validateStatus: (status) => status != null && status < 500,
          ),
        );

        results[url] = true;
        print('✅ $url: Connected');
      } catch (e) {
        results[url] = false;
        print('❌ $url: Failed - $e');
      }
    }

    return results;
  }

  // Error handling
  ApiException _handleDioError(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return ApiException(
          message: AppStrings.networkError,
          statusCode: 0,
          type: ApiExceptionType.timeout,
        );

      case DioExceptionType.badResponse:
        final statusCode = error.response?.statusCode ?? 0;
        final data = error.response?.data;

        String message = AppStrings.serverError;
        if (data is Map<String, dynamic>) {
          message = data['message'] ?? message;
        }

        return ApiException(
          message: message,
          statusCode: statusCode,
          type: _getExceptionType(statusCode),
          data: data,
        );

      case DioExceptionType.cancel:
        return ApiException(
          message: 'Request was cancelled',
          statusCode: 0,
          type: ApiExceptionType.cancel,
        );

      case DioExceptionType.unknown:
      default:
        return ApiException(
          message: AppStrings.networkError,
          statusCode: 0,
          type: ApiExceptionType.unknown,
        );
    }
  }

  ApiExceptionType _getExceptionType(int statusCode) {
    switch (statusCode) {
      case 400:
        return ApiExceptionType.badRequest;
      case 401:
        return ApiExceptionType.unauthorized;
      case 403:
        return ApiExceptionType.forbidden;
      case 404:
        return ApiExceptionType.notFound;
      case 422:
        return ApiExceptionType.validation;
      case 500:
        return ApiExceptionType.serverError;
      default:
        return ApiExceptionType.unknown;
    }
  }
}

class ApiException implements Exception {
  final String message;
  final int statusCode;
  final ApiExceptionType type;
  final dynamic data;

  ApiException({
    required this.message,
    required this.statusCode,
    required this.type,
    this.data,
  });

  @override
  String toString() {
    return 'ApiException: $message (Status: $statusCode, Type: $type)';
  }

  Map<String, dynamic>? get errors {
    if (data is Map<String, dynamic>) {
      return data['errors'];
    }
    return null;
  }

  String get userFriendlyMessage {
    switch (type) {
      case ApiExceptionType.timeout:
        return AppStrings.networkError;
      case ApiExceptionType.unauthorized:
        return AppStrings.invalidCredentials;
      case ApiExceptionType.validation:
        return _getValidationMessage();
      case ApiExceptionType.serverError:
        return AppStrings.serverError;
      default:
        return message.isNotEmpty ? message : AppStrings.unknownError;
    }
  }

  String _getValidationMessage() {
    if (errors != null) {
      final firstError = errors!.values.first;
      if (firstError is List && firstError.isNotEmpty) {
        return firstError.first.toString();
      }
    }
    return message;
  }
}

enum ApiExceptionType {
  timeout,
  badRequest,
  unauthorized,
  forbidden,
  notFound,
  validation,
  serverError,
  cancel,
  unknown,
}
