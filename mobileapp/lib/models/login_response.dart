import 'user.dart';

class LoginResponse {
  final bool success;
  final String message;
  final LoginData? data;

  LoginResponse({required this.success, required this.message, this.data});

  factory LoginResponse.fromJson(Map<String, dynamic> json) {
    return LoginResponse(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      data: json['data'] != null ? LoginData.fromJson(json['data']) : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {'success': success, 'message': message, 'data': data?.toJson()};
  }
}

class LoginData {
  final User user;
  final String accessToken;
  final String? token; // For JWT
  final String tokenType;
  final String? authType;
  final int? expiresIn;

  LoginData({
    required this.user,
    required this.accessToken,
    this.token,
    required this.tokenType,
    this.authType,
    this.expiresIn,
  });

  factory LoginData.fromJson(Map<String, dynamic> json) {
    return LoginData(
      user: User.fromJson(json['user']),
      accessToken: json['token'] ?? json['access_token'] ?? '',
      token: json['token'],
      tokenType: json['token_type'] ?? 'Bearer',
      authType: json['auth_type'],
      expiresIn: json['expires_in'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'user': user.toJson(),
      'access_token': accessToken,
      'token': token,
      'token_type': tokenType,
      'auth_type': authType,
      'expires_in': expiresIn,
    };
  }

  String get effectiveToken {
    return accessToken.isNotEmpty ? accessToken : (token ?? '');
  }
}

class ApiResponse<T> {
  final bool success;
  final String message;
  final T? data;
  final Map<String, dynamic>? errors;

  ApiResponse({
    required this.success,
    required this.message,
    this.data,
    this.errors,
  });

  factory ApiResponse.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic)? fromJsonT,
  ) {
    return ApiResponse<T>(
      success: json['success'] ?? false,
      message: json['message'] ?? '',
      data: json['data'] != null && fromJsonT != null
          ? fromJsonT(json['data'])
          : json['data'],
      errors: json['errors'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'success': success,
      'message': message,
      'data': data,
      'errors': errors,
    };
  }
}

class LoginRequest {
  final String email;
  final String password;
  final String? clientType;

  LoginRequest({
    required this.email,
    required this.password,
    this.clientType = 'mobile',
  });

  Map<String, dynamic> toJson() {
    return {'email': email, 'password': password, 'client_type': clientType};
  }
}

class StudentLoginRequest {
  final String nis;
  final String tanggalLahir;

  StudentLoginRequest({required this.nis, required this.tanggalLahir});

  Map<String, dynamic> toJson() {
    return {'nis': nis, 'tanggal_lahir': tanggalLahir};
  }
}

enum LoginType { staff, student }

class AuthState {
  final bool isAuthenticated;
  final bool isLoading;
  final User? user;
  final String? token;
  final String? error;
  final LoginType? loginType;

  AuthState({
    this.isAuthenticated = false,
    this.isLoading = false,
    this.user,
    this.token,
    this.error,
    this.loginType,
  });

  AuthState copyWith({
    bool? isAuthenticated,
    bool? isLoading,
    User? user,
    String? token,
    String? error,
    LoginType? loginType,
  }) {
    return AuthState(
      isAuthenticated: isAuthenticated ?? this.isAuthenticated,
      isLoading: isLoading ?? this.isLoading,
      user: user ?? this.user,
      token: token ?? this.token,
      error: error,
      loginType: loginType ?? this.loginType,
    );
  }

  AuthState clearError() {
    return copyWith(error: null);
  }

  AuthState setLoading(bool loading) {
    return copyWith(isLoading: loading, error: null);
  }

  AuthState setError(String errorMessage) {
    return copyWith(isLoading: false, error: errorMessage);
  }

  AuthState setAuthenticated(User user, String token, LoginType type) {
    return copyWith(
      isAuthenticated: true,
      isLoading: false,
      user: user,
      token: token,
      loginType: type,
      error: null,
    );
  }

  AuthState setUnauthenticated() {
    return AuthState(
      isAuthenticated: false,
      isLoading: false,
      user: null,
      token: null,
      error: null,
      loginType: null,
    );
  }
}
