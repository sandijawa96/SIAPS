import 'package:dio/dio.dart';
import '../utils/constants.dart';

class NetworkService {
  static final NetworkService _instance = NetworkService._internal();
  factory NetworkService() => _instance;
  NetworkService._internal();

  late Dio _dio;
  String _currentBaseUrl = AppConstants.baseUrl;
  bool _isUsingFallback = false;

  // List of URLs to try in order
  final List<String> _baseUrls = [
    AppConstants.baseUrl,
  ];

  void initialize() {
    _dio = Dio(
      BaseOptions(
        baseUrl: _currentBaseUrl,
        connectTimeout: const Duration(
          milliseconds: AppConstants.connectionTimeout,
        ),
        receiveTimeout: const Duration(
          milliseconds: AppConstants.receiveTimeout,
        ),
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
      ),
    );

    // Add only fallback interceptor - auth will be handled by ApiService
    _dio.interceptors.add(
      InterceptorsWrapper(
        onError: (error, handler) async {
          // If connection error and not using fallback yet, try fallback URL
          if (_shouldTryFallback(error) && !_isUsingFallback) {
            print(
              '🔄 Connection failed to ${_currentBaseUrl}, trying fallback...',
            );

            final fallbackResponse = await _tryFallbackUrl(
              error.requestOptions,
            );
            if (fallbackResponse != null) {
              return handler.resolve(fallbackResponse);
            }
          }

          handler.next(error);
        },
      ),
    );
  }

  bool _shouldTryFallback(DioException error) {
    return error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout ||
        error.type == DioExceptionType.connectionError ||
        (error.response?.statusCode == null);
  }

  Future<Response?> _tryFallbackUrl(RequestOptions originalOptions) async {
    for (int i = 1; i < _baseUrls.length; i++) {
      final fallbackUrl = _baseUrls[i];

      try {
        print('🔄 Trying fallback URL: $fallbackUrl');

        // Create new Dio instance with fallback URL
        final fallbackDio = Dio(
          BaseOptions(
            baseUrl: fallbackUrl,
            connectTimeout: const Duration(
              milliseconds: AppConstants.connectionTimeout,
            ),
            receiveTimeout: const Duration(
              milliseconds: AppConstants.receiveTimeout,
            ),
            headers: originalOptions.headers,
          ),
        );

        // Retry the original request with fallback URL
        final response = await fallbackDio.request(
          originalOptions.path,
          data: originalOptions.data,
          queryParameters: originalOptions.queryParameters,
          options: Options(
            method: originalOptions.method,
            headers: originalOptions.headers,
          ),
        );

        // If successful, switch to fallback URL
        _currentBaseUrl = fallbackUrl;
        _isUsingFallback = true;
        _dio.options.baseUrl = fallbackUrl;

        print('✅ Successfully connected to fallback URL: $fallbackUrl');
        return response;
      } catch (e) {
        print('❌ Fallback URL $fallbackUrl also failed: $e');
        continue;
      }
    }

    return null;
  }

  // Method to manually test connection and switch to best URL
  Future<String> findBestUrl() async {
    for (final url in _baseUrls) {
      try {
        print('🔍 Testing connection to: $url');

        final testDio = Dio(
          BaseOptions(
            baseUrl: url,
            connectTimeout: const Duration(
              milliseconds: 5000,
            ), // Shorter timeout for testing
            receiveTimeout: const Duration(milliseconds: 5000),
          ),
        );

        // Try a simple GET request to test connectivity
        await testDio.get(
          '/profile',
          options: Options(
            headers: {'Accept': 'application/json'},
            validateStatus: (status) =>
                status != null && status < 500, // Accept any non-server error
          ),
        );

        print('✅ Successfully connected to: $url');
        _currentBaseUrl = url;
        _dio.options.baseUrl = url;
        _isUsingFallback = url != _baseUrls.first;

        return url;
      } catch (e) {
        print('❌ Failed to connect to $url: $e');
        continue;
      }
    }

    // If all URLs fail, return the first one as default
    print('⚠️ All URLs failed, using default: ${_baseUrls.first}');
    return _baseUrls.first;
  }

  // Getters
  Dio get dio => _dio;
  String get currentBaseUrl => _currentBaseUrl;
  bool get isUsingFallback => _isUsingFallback;
  List<String> get availableUrls => List.unmodifiable(_baseUrls);

  // Method to manually switch URL
  void switchToUrl(String url) {
    if (_baseUrls.contains(url)) {
      _currentBaseUrl = url;
      _dio.options.baseUrl = url;
      _isUsingFallback = url != _baseUrls.first;
      print('🔄 Manually switched to URL: $url');
    }
  }

  // Method to reset to primary URL
  void resetToPrimaryUrl() {
    _currentBaseUrl = _baseUrls.first;
    _dio.options.baseUrl = _currentBaseUrl;
    _isUsingFallback = false;
    print('🔄 Reset to primary URL: $_currentBaseUrl');
  }

  // Get connection status info
  Map<String, dynamic> getConnectionInfo() {
    return {
      'currentUrl': _currentBaseUrl,
      'isUsingFallback': _isUsingFallback,
      'availableUrls': _baseUrls,
      'primaryUrl': _baseUrls.first,
    };
  }
}
