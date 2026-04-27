import '../models/login_response.dart';
import 'api_service.dart';

class LessonScheduleService {
  static final LessonScheduleService _instance =
      LessonScheduleService._internal();
  factory LessonScheduleService() => _instance;
  LessonScheduleService._internal();

  final ApiService _apiService = ApiService();

  Future<ApiResponse<List<LessonScheduleItem>>> getTodaySchedule({
    int? tahunAjaranId,
  }) {
    return getScheduleByWeekday(
      DateTime.now().weekday,
      tahunAjaranId: tahunAjaranId,
    );
  }

  Future<ApiResponse<List<LessonScheduleItem>>> getScheduleByWeekday(
    int weekday, {
    int? tahunAjaranId,
  }) async {
    final hari = _mapWeekdayToHari(weekday);
    final query = <String, dynamic>{
      'hari': hari,
      'no_pagination': true,
      'is_active': true,
      'status': 'published',
    };
    if (tahunAjaranId != null && tahunAjaranId > 0) {
      query['tahun_ajaran_id'] = tahunAjaranId;
    }

    try {
      final response = await _apiService.get(
        '/jadwal-pelajaran/my-schedule',
        queryParameters: query,
      );

      final body = response.data is Map<String, dynamic>
          ? Map<String, dynamic>.from(response.data as Map<String, dynamic>)
          : <String, dynamic>{};
      final success = body['success'] == true || body['status'] == 'success';

      if (!success) {
        return ApiResponse<List<LessonScheduleItem>>(
          success: false,
          message:
              (body['message'] ?? 'Gagal mengambil jadwal pelajaran').toString(),
          data: const <LessonScheduleItem>[],
        );
      }

      final rawData = body['data'];
      final List<dynamic> rows;
      if (rawData is List) {
        rows = rawData;
      } else if (rawData is Map && rawData['data'] is List) {
        rows = rawData['data'] as List<dynamic>;
      } else {
        rows = const <dynamic>[];
      }

      final items = rows
          .whereType<Map>()
          .map((row) =>
              LessonScheduleItem.fromJson(Map<String, dynamic>.from(row)))
          .toList()
        ..sort((a, b) {
          final byStart = a.jamMulai.compareTo(b.jamMulai);
          if (byStart != 0) {
            return byStart;
          }
          return (a.jamKe ?? 0).compareTo(b.jamKe ?? 0);
        });

      return ApiResponse<List<LessonScheduleItem>>(
        success: true,
        message: (body['message'] ?? 'Jadwal pelajaran berhasil diambil')
            .toString(),
        data: items,
      );
    } on ApiException catch (e) {
      return ApiResponse<List<LessonScheduleItem>>(
        success: false,
        message: e.userFriendlyMessage,
        data: const <LessonScheduleItem>[],
      );
    } catch (e) {
      return ApiResponse<List<LessonScheduleItem>>(
        success: false,
        message: 'Terjadi kesalahan: $e',
        data: const <LessonScheduleItem>[],
      );
    }
  }

  String _mapWeekdayToHari(int weekday) {
    switch (weekday) {
      case DateTime.monday:
        return 'senin';
      case DateTime.tuesday:
        return 'selasa';
      case DateTime.wednesday:
        return 'rabu';
      case DateTime.thursday:
        return 'kamis';
      case DateTime.friday:
        return 'jumat';
      case DateTime.saturday:
        return 'sabtu';
      default:
        return 'minggu';
    }
  }
}

class LessonScheduleItem {
  final String hari;
  final int? jamKe;
  final String jamMulai;
  final String jamSelesai;
  final String mataPelajaranNama;
  final String kelasNama;
  final String guruNama;

  const LessonScheduleItem({
    required this.hari,
    required this.jamMulai,
    required this.jamSelesai,
    required this.mataPelajaranNama,
    required this.kelasNama,
    required this.guruNama,
    this.jamKe,
  });

  factory LessonScheduleItem.fromJson(Map<String, dynamic> json) {
    final mapelData = json['mata_pelajaran'];
    final kelasData = json['kelas'];
    final guruData = json['guru'];

    return LessonScheduleItem(
      hari: _readString(json['hari']),
      jamKe: _readInt(json['jam_ke']),
      jamMulai: _normalizeTime(_readString(json['jam_mulai'])),
      jamSelesai: _normalizeTime(_readString(json['jam_selesai'])),
      mataPelajaranNama: mapelData is Map
          ? _readString(mapelData['nama_mapel'])
          : _readString(json['mata_pelajaran']),
      kelasNama:
          kelasData is Map ? _readString(kelasData['nama_kelas']) : '-',
      guruNama:
          guruData is Map ? _readString(guruData['nama_lengkap']) : '-',
    );
  }

  String get timeRange => '$jamMulai - $jamSelesai';

  static String _readString(dynamic value) {
    final text = (value ?? '').toString().trim();
    return text.isEmpty ? '-' : text;
  }

  static int? _readInt(dynamic value) {
    if (value is int) {
      return value;
    }
    if (value is String) {
      return int.tryParse(value.trim());
    }
    return null;
  }

  static String _normalizeTime(String value) {
    if (value.length >= 5 && value.contains(':')) {
      return value.substring(0, 5);
    }
    return value;
  }
}
