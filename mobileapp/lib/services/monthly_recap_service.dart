import 'api_service.dart';
import '../models/monthly_recap_data.dart';

class MonthlyRecapResponse {
  final bool success;
  final MonthlyRecapData? data;
  final String? message;
  final String? month;

  MonthlyRecapResponse({
    required this.success,
    this.data,
    this.message,
    this.month,
  });

  factory MonthlyRecapResponse.fromJson(Map<String, dynamic> json) {
    MonthlyRecapData? recapData;

    if (json['status'] == 'success' && json['data'] != null) {
      recapData = MonthlyRecapData.fromJson(
        Map<String, dynamic>.from(json['data'] as Map),
      );
    }

    return MonthlyRecapResponse(
      success: json['status'] == 'success',
      data: recapData,
      message: json['message'],
      month: json['month'],
    );
  }
}

class MonthlyRecapService {
  final ApiService _apiService = ApiService();

  /// Get current month recap data
  Future<MonthlyRecapResponse> getCurrentMonthRecap({
    int? tahunAjaranId,
  }) async {
    try {
      final query = <String, dynamic>{};
      if (tahunAjaranId != null && tahunAjaranId > 0) {
        query['tahun_ajaran_id'] = tahunAjaranId;
      }

      final response = await _apiService.get(
        '/monthly-recap/current',
        queryParameters: query,
      );

      if (response.statusCode == 200 && response.data != null) {
        return MonthlyRecapResponse.fromJson(response.data);
      } else {
        return MonthlyRecapResponse(
          success: false,
          message: 'Gagal mengambil data rekapitulasi bulan berjalan',
        );
      }
    } on ApiException catch (e) {
      return MonthlyRecapResponse(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return MonthlyRecapResponse(
        success: false,
        message: 'Error: $e',
      );
    }
  }

  /// Get previous month recap data
  Future<MonthlyRecapResponse> getPreviousMonthRecap({
    int? tahunAjaranId,
  }) async {
    try {
      final query = <String, dynamic>{};
      if (tahunAjaranId != null && tahunAjaranId > 0) {
        query['tahun_ajaran_id'] = tahunAjaranId;
      }

      final response = await _apiService.get(
        '/monthly-recap/previous',
        queryParameters: query,
      );

      if (response.statusCode == 200 && response.data != null) {
        return MonthlyRecapResponse.fromJson(response.data);
      } else {
        return MonthlyRecapResponse(
          success: false,
          message: 'Gagal mengambil data rekapitulasi bulan sebelumnya',
        );
      }
    } on ApiException catch (e) {
      return MonthlyRecapResponse(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return MonthlyRecapResponse(
        success: false,
        message: 'Error: $e',
      );
    }
  }

  /// Get specific month recap data
  Future<MonthlyRecapResponse> getSpecificMonthRecap(
    int year,
    int month, {
    int? tahunAjaranId,
  }) async {
    try {
      final query = <String, dynamic>{
        'year': year.toString(),
        'month': month.toString(),
      };
      if (tahunAjaranId != null && tahunAjaranId > 0) {
        query['tahun_ajaran_id'] = tahunAjaranId;
      }

      final response = await _apiService.get(
        '/monthly-recap/specific',
        queryParameters: query,
      );

      if (response.statusCode == 200 && response.data != null) {
        return MonthlyRecapResponse.fromJson(response.data);
      } else {
        return MonthlyRecapResponse(
          success: false,
          message: 'Gagal mengambil data rekapitulasi untuk bulan yang dipilih',
        );
      }
    } on ApiException catch (e) {
      return MonthlyRecapResponse(
        success: false,
        message: e.userFriendlyMessage,
      );
    } catch (e) {
      return MonthlyRecapResponse(
        success: false,
        message: 'Error: $e',
      );
    }
  }
}
